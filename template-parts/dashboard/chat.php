<?php
/**
 * Chat inbox — conversations on the left, the thread on the right (one pane on phones).
 * List/thread: sc_chat_inbox / sc_chat_thread (inc/admin-dashboard/chat-dashboard.php).
 * Send, poll, upload, close/archive: SC_Chat actions (inc/database/class-sc-chat.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list, $load_wd_form;
$load_wd_list = true;
$load_wd_form = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head w-chat__head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.chat', 'Chat')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('chat.sub', 'Live chat from the website. New messages appear here within a few seconds; the visitor sees your reply in the chat window, and by email if they left one.')); ?></p>
        </div>
    </div>

    <div class="w-chat" id="chat" data-mode="list">
        <section class="w-chat__list" aria-label="<?php echo esc_attr(sc_t('chat.conversations', 'Conversations')); ?>">
            <div class="w-tabs w-chat__tabs" role="tablist" id="chat-tabs"></div>
            <label class="w-search w-chat__search">
                <span class="sr-only"><?php echo esc_html(sc_t('chat.search', 'Search name, email, phone or message')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" id="chat-search" placeholder="<?php echo esc_attr(sc_t('chat.search', 'Search name, email, phone or message')); ?>" autocomplete="off">
            </label>
            <ul class="w-chat__items" id="chat-items" role="listbox" aria-label="<?php echo esc_attr(sc_t('chat.conversations', 'Conversations')); ?>"></ul>
            <button type="button" class="btn btn-secondary btn-sm w-chat__more" id="chat-more" hidden><?php echo esc_html(sc_t('chat.load_more', 'Load more')); ?></button>
        </section>

        <section class="w-chat__thread" aria-live="polite" aria-label="<?php echo esc_attr(sc_t('chat.conversation', 'Conversation')); ?>">
            <div class="w-chat__empty" id="chat-empty">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/></svg>
                <p><?php echo esc_html(sc_t('chat.pick', 'Choose a conversation to read and reply.')); ?></p>
            </div>
            <div class="w-chat__open" id="chat-open" hidden>
                <header class="w-chat__bar">
                    <button type="button" class="w-icon-btn w-chat__back" id="chat-back" aria-label="<?php echo esc_attr(sc_t('chat.back', 'Back to conversations')); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <div class="w-chat__who">
                        <strong id="th-name"></strong>
                        <span class="w-sub w-ltr" id="th-contact"></span>
                        <span class="w-sub" id="th-meta"></span>
                    </div>
                    <div class="w-chat__actions">
                        <a class="btn btn-sm btn-secondary" id="th-wa" href="#" target="_blank" rel="noopener" hidden>WhatsApp</a>
                        <button type="button" class="btn btn-sm btn-secondary" data-conv-op="close" hidden><?php echo esc_html(sc_t('chat.close', 'Close')); ?></button>
                        <button type="button" class="btn btn-sm btn-secondary" data-conv-op="reopen" hidden><?php echo esc_html(sc_t('chat.reopen', 'Reopen')); ?></button>
                        <button type="button" class="btn btn-sm btn-secondary" data-conv-op="archive" hidden><?php echo esc_html(sc_t('chat.archive', 'Archive')); ?></button>
                        <button type="button" class="btn btn-sm btn-secondary" data-conv-op="restore" hidden><?php echo esc_html(sc_t('chat.restore', 'Restore')); ?></button>
                    </div>
                </header>
                <div class="w-chat__messages" id="th-messages" tabindex="0">
                    <button type="button" class="btn btn-link btn-sm w-chat__older" id="th-older" hidden><?php echo esc_html(sc_t('chat.older', 'Show earlier messages')); ?></button>
                    <ol class="w-chat__stream" id="th-stream"></ol>
                </div>
                <form class="w-chat__composer" id="th-form" autocomplete="off">
                    <p class="w-chat__closed" id="th-closed" hidden></p>
                    <div class="w-chat__row">
                        <button type="button" class="w-icon-btn" id="th-attach" aria-label="<?php echo esc_attr(sc_t('chat.attach', 'Attach a file')); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.4 11.1l-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg>
                        </button>
                        <input type="file" id="th-file" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.txt" hidden>
                        <label class="sr-only" for="th-input"><?php echo esc_html(sc_t('chat.reply', 'Reply')); ?></label>
                        <textarea class="form-control" id="th-input" rows="1" maxlength="5000" dir="auto" placeholder="<?php echo esc_attr(sc_t('chat.reply_ph', 'Write a reply — Enter to send, Shift+Enter for a new line')); ?>"></textarea>
                        <button type="submit" class="btn btn-primary" id="th-send"><?php echo esc_html(sc_t('chat.send', 'Send')); ?></button>
                    </div>
                </form>
            </div>
        </section>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var chatNonce = <?php echo $js(wp_create_nonce('sc_chat_nonce')); ?>;
    var L = <?php echo $js(array(
        'views'      => array('active' => sc_t('chat.active', 'Active'), 'unread' => sc_t('chat.unread', 'Unread'), 'closed' => sc_t('chat.closed', 'Closed'), 'archived' => sc_t('chat.archived', 'Archived')),
        'visitor'    => sc_t('chat.visitor', 'Visitor'),
        'you'        => sc_t('chat.you', 'You'),
        'image'      => sc_t('chat.sent_image', 'Sent an image'),
        'file'       => sc_t('chat.sent_file', 'Sent a file'),
        'empty'      => array('active' => sc_t('chat.empty_active', 'No open conversations.'), 'unread' => sc_t('chat.empty_unread', 'Nothing waiting for a reply.'), 'closed' => sc_t('chat.empty_closed', 'No closed conversations.'), 'archived' => sc_t('chat.empty_archived', 'Nothing archived.')),
        'noMatch'    => sc_t('chat.no_match', 'No conversations match.'),
        'account'    => sc_t('chat.has_account', 'Signed in'),
        'regs'       => sc_t('chat.n_registrations', '%s registrations'),
        'oneReg'     => sc_t('chat.one_registration', '1 registration'),
        'started'    => sc_t('chat.started', 'Started %s'),
        'closedNote' => sc_t('chat.closed_note', 'This conversation is closed. Replying reopens it.'),
        'archivedNote' => sc_t('chat.archived_note', 'This conversation is archived. Restore it to reply.'),
        'unreadN'    => sc_t('chat.unread_n', '%s unread'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'sendFailed' => sc_t('chat.send_failed', 'The message was not sent. Try again.'),
        'tooBig'     => sc_t('chat.too_big', 'Files can be up to 5 MB.'),
        'uploading'  => sc_t('chat.uploading', 'Uploading…'),
        'today'      => sc_t('chat.today', 'Today'),
        'yesterday'  => sc_t('chat.yesterday', 'Yesterday'),
    )); ?>;

    var root = $('#chat');
    var state = { view: 'active', search: '', page: 1, rows: [], counts: {}, current: null, lastId: 0, oldestId: 0, sending: false };
    try { var saved = JSON.parse(sessionStorage.getItem('scChatView') || '{}'); if (L.views[saved.view]) { state.view = saved.view; } } catch (x) { /* storage blocked */ }

    function tone(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) % 4; } return h; }
    function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?'; }
    function toDate(d) { return new Date(String(d).replace(' ', 'T')); }
    function shortWhen(d) {
        if (!d) { return ''; }
        var dt = toDate(d), now = new Date();
        if (dt.toDateString() === now.toDateString()) { return dt.toTimeString().slice(0, 5); }
        var days = Math.floor((now - dt) / 86400000);
        if (days < 7) { return dt.toLocaleDateString('en-GB', { weekday: 'short' }); }
        return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    }
    function dayLabel(d) {
        var dt = toDate(d), now = new Date(), y = new Date(now.getTime() - 86400000);
        if (dt.toDateString() === now.toDateString()) { return L.today; }
        if (dt.toDateString() === y.toDateString()) { return L.yesterday; }
        return dt.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: dt.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
    }
    function waNumber(phone) {
        var d = String(phone || '').replace(/[^\d]/g, '');
        if (/^00/.test(d)) { d = d.slice(2); }
        if (/^01\d{9}$/.test(d)) { d = '2' + d; }
        return d.length >= 10 ? d : '';
    }
    function post(data) { return $.post(scDashboard.ajaxurl, $.extend({ nonce: scDashboard.nonce }, data)); }
    function chatPost(data) { return $.post(scDashboard.ajaxurl, $.extend({ nonce: chatNonce }, data)); }

    /* ------------------------------------------------------------- inbox */

    function renderTabs() {
        $('#chat-tabs').html(Object.keys(L.views).map(function (k) {
            var n = state.counts[k];
            var on = k === state.view;
            return '<button type="button" role="tab" class="w-tab" data-view="' + k + '" aria-selected="' + on + '" tabindex="' + (on ? 0 : -1) + '">' + esc(L.views[k]) +
                (n !== undefined ? ' <span class="w-tab__count' + (k === 'unread' && n ? ' is-hot' : '') + '">' + n + '</span>' : '') + '</button>';
        }).join(''));
    }

    function preview(r) {
        if (!r.last) { return ''; }
        var text = r.last.type === 'image' ? L.image : (r.last.type === 'file' ? L.file : r.last.text);
        return (r.last.from === 'organizer' ? L.you + ': ' : '') + text.replace(/\s+/g, ' ');
    }

    function renderItems(append) {
        var html = state.rows.map(function (r) {
            var on = state.current && state.current.id === r.id;
            return '<li role="option" aria-selected="' + on + '" class="w-chat__item' + (r.unread ? ' is-unread' : '') + (on ? ' is-open' : '') + '" data-id="' + r.id + '" tabindex="0">' +
                '<span class="w-person__avatar" data-tone="' + tone(r.name || r.email) + '">' + esc(initials(r.name || r.email)) + '</span>' +
                '<span class="w-chat__itembody"><span class="w-chat__itemtop"><span class="w-chat__itemname">' + esc(r.name || r.email || L.visitor) + '</span><span class="w-chat__itemtime">' + esc(shortWhen(r.last_at)) + '</span></span>' +
                '<span class="w-chat__itemtext" dir="auto">' + esc(preview(r)) + '</span></span>' +
                (r.unread ? '<span class="w-chat__badge" aria-label="' + esc(L.unreadN.replace('%s', r.unread)) + '">' + r.unread + '</span>' : '') + '</li>';
        }).join('');
        $('#chat-items').html(html || '<li class="w-chat__none">' + esc(state.search ? L.noMatch : L.empty[state.view]) + '</li>');
    }

    var inboxReq = null;
    function loadInbox(opts) {
        opts = opts || {};
        if (inboxReq && !opts.silent) { inboxReq.abort(); }
        var page = opts.more ? state.page + 1 : 1;
        inboxReq = post({ action: 'sc_chat_inbox', view: state.view, search: state.search, page: page, per_page: opts.silent ? Math.max(30, state.rows.length) : 30 });
        return inboxReq.done(function (res) {
            if (!res.success) { return; }
            state.page = page;
            state.rows = opts.more ? state.rows.concat(res.data.rows) : res.data.rows;
            state.counts = res.data.counts;
            renderTabs();
            renderItems();
            $('#chat-more').prop('hidden', state.rows.length >= res.data.total);
        });
    }

    $('#chat-tabs').on('click', '[data-view]', function () {
        state.view = this.getAttribute('data-view');
        try { sessionStorage.setItem('scChatView', JSON.stringify({ view: state.view })); } catch (x) { /* storage blocked */ }
        loadInbox();
    });
    var searchTimer = null;
    $('#chat-search').on('input', function () {
        clearTimeout(searchTimer);
        var v = $.trim(this.value);
        searchTimer = setTimeout(function () { state.search = v; loadInbox(); }, 300);
    });
    $('#chat-more').on('click', function () { loadInbox({ more: true }); });
    $('#chat-items').on('click keydown', '.w-chat__item', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
        e.preventDefault();
        openThread(+this.getAttribute('data-id'));
    });

    /* ------------------------------------------------------------ thread */

    function normalize(m) {
        // Messages from polling come straight from the table.
        if (m.from) { return m; }
        var system = m.sender_type !== 'visitor' && m.sender_type !== 'organizer';
        return { id: +m.id, from: system ? 'system' : m.sender_type, type: system ? 'system' : (m.message_type || 'text'), text: m.message || '', file_url: m.file_url || '', file_name: m.file_name || '', by: '', at: m.created_at };
    }

    function bubble(m) {
        m = normalize(m);
        if (m.type === 'system') {
            return '<li class="w-chat__notice" data-mid="' + m.id + '" data-day="' + esc(String(m.at).slice(0, 10)) + '">' + esc(m.text) + '</li>';
        }
        var body;
        if (m.type === 'image' && m.file_url) {
            body = '<a href="' + esc(m.file_url) + '" target="_blank" rel="noopener"><img class="w-chat__img" src="' + esc(m.file_url) + '" alt="' + esc(m.file_name) + '" loading="lazy"></a>';
        } else if (m.type === 'file' && m.file_url) {
            body = '<a class="w-chat__file" href="' + esc(m.file_url) + '" target="_blank" rel="noopener" download><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6"/></svg>' + esc(m.file_name || L.file) + '</a>';
        } else {
            body = '<span class="w-chat__text" dir="auto">' + esc(m.text) + '</span>';
        }
        var time = toDate(m.at).toTimeString().slice(0, 5);
        return '<li class="w-chat__msg is-' + m.from + '" data-mid="' + m.id + '" data-day="' + esc(String(m.at).slice(0, 10)) + '">' + body +
            '<span class="w-chat__time">' + esc((m.from === 'organizer' && m.by ? m.by + ' · ' : '') + time) + '</span></li>';
    }

    function withDays(html, messages, prevDay) {
        var out = '', day = prevDay || '';
        messages.forEach(function (m) {
            m = normalize(m);
            var d = String(m.at).slice(0, 10);
            if (d !== day) { out += '<li class="w-chat__day"><span>' + esc(dayLabel(m.at)) + '</span></li>'; day = d; }
            out += bubble(m);
        });
        return out;
    }

    function scrollToEnd() { var el = document.getElementById('th-messages'); el.scrollTop = el.scrollHeight; }
    function nearEnd() { var el = document.getElementById('th-messages'); return el.scrollHeight - el.scrollTop - el.clientHeight < 120; }

    function renderHeader(c) {
        $('#th-name').text(c.name || c.email || L.visitor);
        $('#th-contact').html([c.email ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '', c.phone ? '<a href="tel:' + esc(String(c.phone).replace(/[^\d+]/g, '')) + '">' + esc(c.phone) + '</a>' : ''].filter(Boolean).join(' · '));
        var meta = [c.event, L.started.replace('%s', toDate(c.started).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }))];
        if (c.account) { meta.unshift(L.account); }
        $('#th-meta').html(esc(meta.filter(Boolean).join(' · ')) + (c.registrations ? ' · <a href="' + esc(dashboardUrl + 'attendees?search=' + encodeURIComponent(c.email)) + '">' + esc(c.registrations === 1 ? L.oneReg : L.regs.replace('%s', c.registrations)) + '</a>' : ''));
        var wa = waNumber(c.phone);
        $('#th-wa').prop('hidden', !wa).attr('href', wa ? 'https://wa.me/' + wa : '#');
        $('[data-conv-op="close"]').prop('hidden', c.status !== 'active');
        $('[data-conv-op="reopen"]').prop('hidden', c.status !== 'closed');
        $('[data-conv-op="archive"]').prop('hidden', c.status === 'archived');
        $('[data-conv-op="restore"]').prop('hidden', c.status !== 'archived');
        $('#th-closed').prop('hidden', c.status === 'active').text(c.status === 'archived' ? L.archivedNote : L.closedNote);
        $('#th-input, #th-send, #th-attach').prop('disabled', c.status === 'archived');
    }

    var threadReq = null;
    function openThread(id) {
        if (threadReq) { threadReq.abort(); }
        root.attr('data-mode', 'thread');
        $('#chat-empty').prop('hidden', true);
        $('#chat-open').prop('hidden', false);
        threadReq = post({ action: 'sc_chat_thread', id: id }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            var c = res.data.conversation, msgs = res.data.messages;
            state.current = c;
            state.lastId = msgs.length ? msgs[msgs.length - 1].id : 0;
            state.oldestId = msgs.length ? msgs[0].id : 0;
            renderHeader(c);
            $('#th-stream').html(withDays('', msgs));
            $('#th-older').prop('hidden', !res.data.has_more);
            scrollToEnd();
            // Opening a conversation reads it.
            state.rows.forEach(function (r) { if (r.id === c.id) { r.unread = 0; } });
            if (state.counts.unread && c.unread) { state.counts.unread = Math.max(0, state.counts.unread - 1); }
            renderTabs();
            renderItems();
            if (window.matchMedia('(min-width: 992px)').matches) { $('#th-input').trigger('focus'); }
        });
    }
    $('#chat-back').on('click', function () { root.attr('data-mode', 'list'); state.current = null; renderItems(); });

    $('#th-older').on('click', function () {
        var el = document.getElementById('th-messages');
        var before = el.scrollHeight;
        post({ action: 'sc_chat_thread', id: state.current.id, before: state.oldestId }).done(function (res) {
            if (!res.success) { return; }
            var msgs = res.data.messages;
            if (!msgs.length) { $('#th-older').prop('hidden', true); return; }
            state.oldestId = msgs[0].id;
            $('#th-stream .w-chat__day').first().remove();
            $('#th-stream').prepend(withDays('', msgs));
            $('#th-older').prop('hidden', !res.data.has_more);
            el.scrollTop = el.scrollHeight - before;
        });
    });

    /* -------------------------------------------------------------- send */

    function appendMessages(msgs) {
        if (!msgs.length) { return; }
        var stick = nearEnd();
        var lastDay = $('#th-stream [data-day]').last().attr('data-day');
        var fresh = msgs.map(normalize).filter(function (m) { return !document.querySelector('#th-stream [data-mid="' + m.id + '"]'); });
        if (!fresh.length) { return; }
        $('#th-stream').append(withDays('', fresh, lastDay));
        state.lastId = Math.max(state.lastId, fresh[fresh.length - 1].id);
        if (stick) { scrollToEnd(); }
    }

    function send() {
        var text = $.trim($('#th-input').val());
        if (!text || !state.current || state.sending) { return; }
        state.sending = true;
        $('#th-send').prop('disabled', true);
        var c = state.current;
        var go = function () {
            return chatPost({ action: 'sc_chat_send', conversation_id: c.id, message: text, sender_type: 'organizer' }).done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.sendFailed); return; }
                $('#th-input').val('').trigger('input');
                appendMessages([res.data.message]);
                scrollToEnd();
                loadInbox({ silent: true });
            }).fail(function () { showError(L.sendFailed); });
        };
        // Replying to a closed conversation reopens it first.
        var ready = c.status === 'closed' ? chatPost({ action: 'sc_chat_reopen_conversation', conversation_id: c.id }).then(function () { c.status = 'active'; renderHeader(c); }) : $.Deferred().resolve();
        ready.then(go).always(function () { state.sending = false; $('#th-send').prop('disabled', false); });
    }
    $('#th-form').on('submit', function (e) { e.preventDefault(); send(); });
    $('#th-input').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
    }).on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });

    $('#th-attach').on('click', function () { $('#th-file').trigger('click'); });
    $('#th-file').on('change', function () {
        var file = this.files && this.files[0];
        this.value = '';
        if (!file || !state.current) { return; }
        if (file.size > 5 * 1024 * 1024) { showError(L.tooBig); return; }
        var fd = new FormData();
        fd.append('action', 'sc_chat_upload_file');
        fd.append('nonce', chatNonce);
        fd.append('conversation_id', state.current.id);
        fd.append('sender_type', 'organizer');
        fd.append('file', file);
        if (window.toastr) { toastr.info(L.uploading); }
        fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            appendMessages([res.data.message]);
            scrollToEnd();
        }).catch(function () { showError(L.failed); });
    });

    $('[data-conv-op]').on('click', function () {
        var op = this.getAttribute('data-conv-op');
        var c = state.current;
        if (!c) { return; }
        chatPost({ action: 'sc_chat_' + op + '_conversation', conversation_id: c.id }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            if (window.toastr) { toastr.success(res.data.message); }
            openThread(c.id);
            loadInbox({ silent: true });
        }).fail(function () { showError(L.failed); });
    });

    /* ----------------------------------------------------------- polling */

    function poll() {
        if (document.hidden) { return; }
        if (state.current) {
            chatPost({ action: 'sc_chat_load', conversation_id: state.current.id, last_id: state.lastId, reader_type: 'organizer' }).done(function (res) {
                if (res.success && res.data.messages.length) { appendMessages(res.data.messages); }
            });
        }
    }
    setInterval(poll, 5000);
    setInterval(function () { if (!document.hidden) { loadInbox({ silent: true }); } }, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); loadInbox({ silent: true }); } });

    var openId = +new URLSearchParams(location.search).get('conversation') || 0;
    loadInbox().done(function () {
        if (openId) { openThread(openId); }
        else if (window.matchMedia('(min-width: 992px)').matches && state.rows.length) { openThread(state.rows[0].id); }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
