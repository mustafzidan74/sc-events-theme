/**
 * The scanner without a connection (template-parts/dashboard/scanner.php).
 *
 * Keeps the event's list on the phone, answers a scan from it when the server
 * cannot be reached, and holds those scans until the connection is back.
 * Server side: inc/admin-dashboard/scanner-offline.php.
 *
 * Stored in IndexedDB "sc-scanner":
 *   lists  — one record per event: the rows, when they were fetched, for whom
 *   queue  — scans waiting to be sent, one record each, written before the
 *            door sees the result so a closed tab loses nothing
 *   review — scans the server did not accept after the door let them through
 *
 * @package sc_events
 */
(function (window) {
    'use strict';

    var DB_NAME = 'sc-scanner';
    var DB_VERSION = 1;
    var REFRESH_MS = 2 * 60 * 1000;   // pick up changes this often
    var FULL_MS = 20 * 60 * 1000;     // and fetch everything again this often
    var SYNC_MS = 5000;               // try the waiting scans this often
    var BATCH = 50;
    var TIMEOUT_MS = 15000;

    // Row fields, as sent by sc_scanner_offline_rows().
    var CODE = 0, NAME = 1, TICKET = 2, WORKSHOP = 3, STATE = 4, PHONE = 5, LAST = 6, LAST_AT = 7, FIRST_AT = 8;
    // Company fields.
    var C_NAME = 1, C_STATE = 2, C_IN = 3;

    var cfg = null;
    var dbp = null;
    var list = null;         // the current event's record
    var loading = null;      // { done, total } while a full list is downloading
    var waiting = 0;
    var toReview = 0;
    var syncing = false;
    var reachable = true;
    var signedOut = false;
    var saveTimer = null;
    var refreshTimer = null;
    var listeners = [];
    var seq = 0;             // keeps two scans in the same millisecond in order

    function isoNow() { return new Date().toISOString(); }
    function ms(iso) { return iso ? Date.parse(iso) || 0 : 0; }

    /* ---- storage ------------------------------------------------------- */

    function openDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) { reject(new Error('IndexedDB is not available')); return; }
            var req = window.indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains('lists')) { db.createObjectStore('lists', { keyPath: 'event' }); }
                if (!db.objectStoreNames.contains('queue')) { db.createObjectStore('queue', { keyPath: 'ref' }); }
                if (!db.objectStoreNames.contains('review')) { db.createObjectStore('review', { keyPath: 'ref' }); }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    // Runs fn(store) in one transaction and resolves once it is written.
    function store(name, mode, fn) {
        return dbp.then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(name, mode);
                var req = fn(t.objectStore(name));
                t.oncomplete = function () { resolve(req ? req.result : undefined); };
                t.onerror = t.onabort = function () { reject(t.error); };
            });
        });
    }

    function getAll(name) {
        return store(name, 'readonly', function (s) { return s.getAll(); }).then(function (rows) { return rows || []; });
    }

    function saveListSoon() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveList, 800);
    }

    function saveList() {
        clearTimeout(saveTimer);
        if (!list) { return Promise.resolve(); }
        var copy = list;
        return store('lists', 'readwrite', function (s) { return s.put(copy); }).catch(function () {});
    }

    function countStores() {
        return Promise.all([
            store('queue', 'readonly', function (s) { return s.count(); }),
            store('review', 'readonly', function (s) { return s.count(); })
        ]).then(function (n) {
            waiting = n[0] || 0;
            toReview = n[1] || 0;
            emit();
        }).catch(function () {});
    }

    /* ---- server -------------------------------------------------------- */

    function post(data) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        var ctrl = window.AbortController ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS) : null;
        return fetch(cfg.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
            .then(function (res) {
                clearTimeout(timer);
                return res.text().then(function (text) {
                    var json = null;
                    try { json = JSON.parse(text); } catch (e) {}
                    // admin-ajax answers "0" with 400 to a request that is no longer signed in.
                    if (!json && (text === '0' || text === '-1')) { return { signedOut: true }; }
                    if (!json) { throw new Error('bad response ' + res.status); }
                    return json;
                });
            }, function (err) {
                clearTimeout(timer);
                throw err;
            });
    }

    // Sends with the current nonce, and once more with a fresh one if it expired.
    function call(data, retried) {
        data.nonce = cfg.getNonce();
        return post(data).then(function (json) {
            if (json.signedOut) {
                signedOut = true;
                reachable = true;
                emit();
                throw new Error('signed out');
            }
            signedOut = false;
            setReachable(true);
            if (!json.success && json.data && json.data.code === 'nonce' && !retried) {
                return refreshNonce().then(function () { return call(data, true); });
            }
            return json;
        }, function (err) {
            setReachable(false);
            throw err;
        });
    }

    function refreshNonce() {
        return post({ action: 'sc_scanner_nonce' }).then(function (json) {
            if (json && json.success && json.data.nonce) { cfg.setNonce(json.data.nonce); }
        });
    }

    function setReachable(on) {
        if (reachable === on) { return; }
        reachable = on;
        emit();
        if (on) { sync(); }
    }

    /* ---- the list ------------------------------------------------------ */

    function today() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: cfg.timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        } catch (e) {
            var d = new Date();
            return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        }
    }

    // A new day starts with nobody inside.
    function rollDay() {
        if (!list || list.day === today()) { return; }
        Object.keys(list.rows).forEach(function (k) {
            var r = list.rows[k];
            r[LAST] = ''; r[LAST_AT] = ''; r[FIRST_AT] = '';
        });
        list.day = today();
        saveListSoon();
    }

    function blankList(eventId) {
        return { event: String(eventId), user: cfg.userId, day: '', tracking: false, cursor: null, fullAt: 0, checkedAt: 0, rows: {}, companies: {} };
    }

    function useEvent(eventId) {
        clearTimeout(refreshTimer);
        list = null;
        loading = null;
        emit();
        if (!eventId || !dbp) { return Promise.resolve(); }
        var key = String(eventId);
        return store('lists', 'readonly', function (s) { return s.get(key); }).then(function (saved) {
            if (String(currentEvent) !== key) { return; }
            // Another account's list never serves this one.
            list = saved && saved.user === cfg.userId ? saved : null;
            if (list) { rollDay(); }
            emit();
            return refresh();
        }).catch(function () { emit(); });
    }

    var currentEvent = null;

    function refresh(forceFull) {
        clearTimeout(refreshTimer);
        var eventId = currentEvent;
        if (!eventId) { return Promise.resolve(); }
        var full = forceFull || !list || !list.cursor || Date.now() - list.fullAt > FULL_MS || list.day !== today();
        var job = full ? fetchFull(eventId) : fetchChanges(eventId);
        return job.then(function () {
            if (list) { list.checkedAt = Date.now(); saveList(); }
        }).catch(function () {}).then(function () {
            loading = null;
            emit();
            if (String(currentEvent) === String(eventId)) {
                refreshTimer = setTimeout(function () { refresh(false); }, REFRESH_MS);
            }
        });
    }

    function fetchFull(eventId) {
        var fresh = blankList(eventId);
        var after = 0;
        var first = true;
        loading = { done: 0 };
        emit();
        function page() {
            var data = { action: 'sc_scanner_offline_list', event_id: eventId, after: after };
            return call(data).then(function (json) {
                if (String(currentEvent) !== String(eventId)) { throw new Error('event changed'); }
                if (!json.success) { throw new Error((json.data && json.data.message) || 'list refused'); }
                var d = json.data;
                if (d.expired) { return dropList(eventId); }
                if (first) {
                    fresh.cursor = d.cursor;
                    fresh.day = d.day;
                    fresh.tracking = !!d.tracking;
                    fresh.user = d.user;
                    (d.companies || []).forEach(function (c) { fresh.companies[c[0]] = c; });
                    first = false;
                }
                d.rows.forEach(function (r) { fresh.rows[String(r[CODE]).toUpperCase()] = r; });
                loading.done += d.rows.length;
                emit();
                after = d.after;
                if (d.more) { return page(); }
                keepLocalScans(fresh);
                fresh.fullAt = Date.now();
                list = fresh;
            });
        }
        return page();
    }

    function fetchChanges(eventId) {
        var data = { action: 'sc_scanner_offline_list', event_id: eventId, since: list.cursor.since, ck: list.cursor.ck };
        return call(data).then(function (json) {
            if (!json.success || String(currentEvent) !== String(eventId) || !list) { return; }
            var d = json.data;
            if (d.expired) { return dropList(eventId); }
            if (d.reset) { return fetchFull(eventId); }
            d.rows.forEach(function (r) {
                var key = String(r[CODE]).toUpperCase();
                list.rows[key] = mergeRow(list.rows[key], r);
            });
            (d.companies || []).forEach(function (c) {
                var old = list.companies[c[0]];
                if (old && old[C_IN] && !c[C_IN]) { c[C_IN] = old[C_IN]; }
                list.companies[c[0]] = c;
            });
            list.cursor = d.cursor;
            list.tracking = !!d.tracking;
        });
    }

    // What the server says wins, unless this phone saw a scan it has not sent yet.
    function mergeRow(local, server) {
        if (local && local[LAST_AT] && ms(local[LAST_AT]) > ms(server[LAST_AT])) {
            server[LAST] = local[LAST];
            server[LAST_AT] = local[LAST_AT];
        }
        if (local && local[FIRST_AT] && (!server[FIRST_AT] || ms(local[FIRST_AT]) < ms(server[FIRST_AT]))) {
            server[FIRST_AT] = local[FIRST_AT];
        }
        return server;
    }

    function keepLocalScans(fresh) {
        if (!list || list.day !== fresh.day) { return; }
        Object.keys(fresh.rows).forEach(function (k) {
            if (list.rows[k]) { fresh.rows[k] = mergeRow(list.rows[k], fresh.rows[k]); }
        });
        Object.keys(fresh.companies).forEach(function (k) {
            var old = list.companies[k];
            if (old && old[C_IN] && !fresh.companies[k][C_IN]) { fresh.companies[k][C_IN] = old[C_IN]; }
        });
    }

    function dropList(eventId) {
        list = null;
        return store('lists', 'readwrite', function (s) { return s.delete(String(eventId)); }).catch(function () {});
    }

    /* ---- deciding at the door ------------------------------------------ */

    /**
     * The same checks the server makes, against the saved list.
     *
     * @param {string} code
     * @param {{workshop: ?string, workshopTitle: string}} door
     * @return {object} { refused, title, message } or a result for the door
     */
    function decide(code, door) {
        rollDay();
        var key = String(code).trim().toUpperCase();
        var T = cfg.text;
        var now = isoNow();

        if (/^COMP-/.test(key)) {
            var c = list.companies[key];
            if (!c) { return unknown(key); }
            if (c[C_STATE] !== 'ok') { return { refused: true, title: T.error_title, message: T.company_inactive }; }
            return {
                kind: 'company', key: key, action: 'check_in', at: now,
                already: !!c[C_IN], firstAt: c[C_IN] || '',
                name: c[C_NAME], ticket: T.company_ticket
            };
        }

        var r = list.rows[key];
        if (!r) { return unknown(key); }
        if (r[STATE] === 'x') { return { refused: true, title: T.error_title, message: T.inactive, name: r[NAME] }; }
        if (r[STATE] === 'p') { return { refused: true, title: T.error_title, message: T.unpaid, name: r[NAME] }; }
        if (door.workshop) {
            if (String(r[WORKSHOP]) !== String(door.workshop)) {
                return { refused: true, title: T.error_title, message: T.wrong_workshop.replace('%s', door.workshopTitle || ''), name: r[NAME] };
            }
        } else if (r[WORKSHOP]) {
            return { refused: true, title: T.error_title, message: T.workshop_ticket, name: r[NAME] };
        }

        var result = { kind: 'attendee', key: key, at: now, name: r[NAME], ticket: r[TICKET], action: 'check_in', already: false, firstAt: '', duration: '' };
        if (list.tracking) {
            if (r[LAST] === 'i') {
                result.action = 'check_out';
                var mins = r[LAST_AT] ? Math.max(0, Math.floor((Date.now() - Date.parse(r[LAST_AT])) / 60000)) : 0;
                result.duration = ('0' + Math.floor(mins / 60)).slice(-2) + ':' + ('0' + (mins % 60)).slice(-2);
            }
        } else if (r[FIRST_AT]) {
            result.already = true;
            result.firstAt = r[FIRST_AT];
        }
        return result;
    }

    function unknown(key) {
        return { kind: 'unknown', key: key, action: 'check_in', at: isoNow(), name: '', ticket: '' };
    }

    // Writes what the door just saw into the list, so the next scan knows.
    function note(result) {
        if (!list) { return; }
        if (result.kind === 'company') {
            var c = list.companies[result.key];
            if (c && !c[C_IN]) { c[C_IN] = result.at; }
        } else if (result.kind === 'attendee') {
            var r = list.rows[result.key];
            if (!r) { return; }
            r[LAST] = result.action === 'check_out' ? 'o' : 'i';
            r[LAST_AT] = result.at;
            if (result.action === 'check_in' && !r[FIRST_AT]) { r[FIRST_AT] = result.at; }
        }
        saveListSoon();
    }

    /** Keeps the list in step with a scan the server answered. */
    function noteOnline(code, data) {
        if (!list || !data) { return; }
        note({
            kind: data.is_company ? 'company' : 'attendee',
            key: String(code).trim().toUpperCase(),
            action: data.action_type === 'check_out' ? 'check_out' : 'check_in',
            at: isoNow()
        });
    }

    /** Saves a scan made without the server. Resolves once it is on disk. */
    function hold(result, door) {
        var scan = {
            ref: door.ref || newRef(),
            code: result.key,
            at: result.at,
            seq: Date.now() * 1000 + (seq++ % 1000),
            event: String(currentEvent),
            workshop: door.workshop || '',
            gate: door.gate || '',
            action: result.action,
            shown: result.kind === 'unknown' ? 'unknown' : (result.already ? 'already' : 'ok'),
            name: result.name || ''
        };
        note(result);
        return store('queue', 'readwrite', function (s) { return s.put(scan); }).then(function () {
            waiting++;
            emit();
            scheduleSync(1500);
        });
    }

    function searchPhone(digits) {
        if (!list) { return []; }
        var end = String(digits).replace(/\D/g, '').slice(-4);
        if (end.length < 4) { return []; }
        var out = [];
        Object.keys(list.rows).forEach(function (k) {
            var r = list.rows[k];
            if (r[PHONE] === end) {
                out.push({
                    name: r[NAME], ticket_id: r[CODE], phone: '•••' + r[PHONE],
                    workshop_id: r[WORKSHOP] || 0,
                    status: r[STATE] === 'x' ? 'cancelled' : 'active',
                    ticket_status: r[FIRST_AT] ? 'used' : ''
                });
            }
        });
        return out;
    }

    /* ---- sending the waiting scans ------------------------------------- */

    var syncTimer = null;

    function scheduleSync(ms) {
        clearTimeout(syncTimer);
        syncTimer = setTimeout(sync, ms);
    }

    function sync() {
        clearTimeout(syncTimer);
        if (syncing || !dbp) { return Promise.resolve(); }
        syncing = true;
        emit();
        return getAll('queue').then(function (all) {
            waiting = all.length;
            if (!all.length) { return; }
            // Oldest first: an exit must never reach the server before its entry.
            all.sort(function (a, b) { return (ms(a.at) - ms(b.at)) || ((a.seq || 0) - (b.seq || 0)); });
            var batch = all.slice(0, BATCH);
            var byRef = {};
            batch.forEach(function (s) { byRef[s.ref] = s; });
            var payload = batch.map(function (s) {
                return { ref: s.ref, code: s.code, at: s.at, event: s.event, workshop: s.workshop, gate: s.gate, action: s.action };
            });
            return call({ action: 'sc_scanner_offline_sync', scans: JSON.stringify(payload) }).then(function (json) {
                if (!json.success) { throw new Error((json.data && json.data.message) || 'sync refused'); }
                var results = json.data.results || [];
                return store('queue', 'readwrite', function (q) {
                    results.forEach(function (res) { q.delete(res.ref); });
                }).then(function () {
                    var worth = results.filter(function (res) {
                        var scan = byRef[res.ref];
                        if (!scan || res.status === 'done') { return false; }
                        // The door already said "already checked in"; nothing new to look at.
                        return !(res.status === 'already' && scan.shown === 'already');
                    });
                    if (!worth.length) { return; }
                    return store('review', 'readwrite', function (rv) {
                        worth.forEach(function (res) {
                            var scan = byRef[res.ref];
                            rv.put({ ref: res.ref, code: scan.code, at: scan.at, name: res.name || scan.name, status: res.status, message: res.message, shown: scan.shown });
                        });
                    });
                }).then(function () {
                    // Keep going while there is more and the server keeps answering.
                    if (all.length > batch.length && results.length) { syncing = false; return sync(); }
                });
            });
        }).catch(function () {}).then(function () {
            syncing = false;
            return countStores();
        }).then(function () {
            if (waiting) { scheduleSync(SYNC_MS); }
        });
    }

    function reviewList() {
        return getAll('review').then(function (rows) {
            return rows.sort(function (a, b) { return a.at < b.at ? 1 : -1; });
        });
    }

    function clearReview() {
        return store('review', 'readwrite', function (s) { return s.clear(); }).then(countStores);
    }

    /* ---- status -------------------------------------------------------- */

    function status() {
        var people = list ? Object.keys(list.rows).length : 0;
        var state;
        if (loading && !list) { state = 'loading'; }
        else if (signedOut) { state = 'signed_out'; }
        else if (!reachable) { state = list ? 'offline' : 'offline_nolist'; }
        else if (waiting) { state = 'sending'; }
        else if (list) { state = 'ready'; }
        else { state = 'nolist'; }
        return {
            state: state,
            people: people,
            companies: list ? Object.keys(list.companies).length : 0,
            loaded: loading ? loading.done : 0,
            checkedAt: list ? list.checkedAt : 0,
            waiting: waiting,
            review: toReview
        };
    }

    function emit() {
        var s = status();
        listeners.forEach(function (fn) { try { fn(s); } catch (e) {} });
    }

    function newRef() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        return 'r' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    /* ---- setup --------------------------------------------------------- */

    window.ScOffline = {
        /**
         * @param {object} options ajaxurl, userId, timeZone, text, getNonce(), setNonce(n)
         */
        init: function (options) {
            cfg = options;
            cfg.userId = parseInt(cfg.userId, 10) || 0;
            dbp = openDb();
            dbp.then(countStores).then(function () { if (waiting) { sync(); } }).catch(function () { dbp = null; emit(); });
            window.addEventListener('online', function () { reachable = true; emit(); sync(); refresh(false); });
            window.addEventListener('offline', function () { setReachable(false); });
            // A scan that has not reached the server must not be lost to a closed tab.
            window.addEventListener('beforeunload', function (e) {
                if (waiting > 0) { e.preventDefault(); e.returnValue = ''; }
            });
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') { saveList(); }
                else if (currentEvent) { sync(); if (list && Date.now() - list.checkedAt > REFRESH_MS) { refresh(false); } }
            });
        },
        supported: function () { return !!dbp; },
        onChange: function (fn) { listeners.push(fn); fn(status()); },
        useEvent: function (eventId) { currentEvent = eventId ? String(eventId) : null; return useEvent(currentEvent); },
        refresh: function () { return refresh(true); },
        hasList: function () { return !!list; },
        isOnline: function () { return reachable && navigator.onLine !== false; },
        markOffline: function () { setReachable(false); },
        markOnline: function () { signedOut = false; setReachable(true); },
        decide: decide,
        hold: hold,
        noteOnline: noteOnline,
        searchPhone: searchPhone,
        sync: sync,
        reviewList: reviewList,
        clearReview: clearReview,
        refreshNonce: refreshNonce,
        newRef: newRef,
        status: status
    };
})(window);
