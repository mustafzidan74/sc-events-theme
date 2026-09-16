/**
 * Lets the scanner page open again when the venue has no connection.
 *
 * Served from the site root by sc_scanner_service_worker() and registered for
 * the scanner page only. The network always goes first; the saved copy is
 * used only when the network fails. Ajax calls are never touched — the page
 * handles those itself (assets/dashboard/js/scanner-offline.js).
 */
'use strict';

var CACHE = 'sc-scanner-v1';

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) {
                return k.indexOf('sc-scanner-') === 0 && k !== CACHE;
            }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isScannerPage(url) {
    return /\/event-manager-dashboard\/scanner\/?$/.test(url.pathname);
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    var url = new URL(req.url);
    if (url.pathname.indexOf('admin-ajax.php') !== -1 || url.searchParams.has('sc_scanner_sw')) {
        return;
    }

    if (req.mode === 'navigate') {
        if (url.origin !== self.location.origin || !isScannerPage(url)) {
            return;
        }
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok && !res.redirected) {
                    var copy = res.clone();
                    caches.open(CACHE).then(function (c) { c.put(req, copy); });
                }
                return res;
            }).catch(function () {
                return caches.open(CACHE).then(function (c) {
                    return c.match(req).then(function (hit) {
                        return hit || c.match(req, { ignoreSearch: true });
                    });
                }).then(function (hit) {
                    return hit || Response.error();
                });
            })
        );
        return;
    }

    // Styles, scripts, fonts and images the page loads.
    event.respondWith(
        fetch(req).then(function (res) {
            if (res.ok || res.type === 'opaque') {
                var copy = res.clone();
                caches.open(CACHE).then(function (c) { c.put(req, copy); });
            }
            return res;
        }).catch(function () {
            return caches.match(req).then(function (hit) {
                return hit || Response.error();
            });
        })
    );
});

self.addEventListener('message', function (event) {
    if (event.data === 'sc-scanner-forget') {
        event.waitUntil(caches.delete(CACHE));
    }
});
