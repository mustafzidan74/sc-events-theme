<?php
/**
 * WhatsApp — numbers connected through wa-bot, who is alerted about chat messages, and what was sent.
 * Handlers: inc/admin-dashboard/whatsapp-dashboard.php; sending and webhook: inc/whatsapp/wabot.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_form, $load_wd_list;
$load_wd_form = true;
$load_wd_list = true;
$is_admin = current_user_can('manage_options');
$state = sc_wabot_public_state();

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.whatsapp', 'WhatsApp')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('whatsapp.sub', 'Numbers connected through wa-bot. New chat messages alert your team on WhatsApp, and replies reach visitors who asked for WhatsApp.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <button type="button" class="btn btn-outline-secondary" id="wa-refresh"><?php echo esc_html(sc_t('whatsapp.check_now', 'Check connection')); ?></button>
        </div>
    </div>

    <div class="w-wa">

        <!-- Numbers -->
        <section class="w-section" aria-labelledby="wa-numbers-title">
            <div class="w-section__head">
                <h2 id="wa-numbers-title"><?php echo esc_html(sc_t('whatsapp.numbers', 'Numbers')); ?></h2>
                <?php if ($is_admin): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="wa-add-toggle" aria-expanded="false" aria-controls="wa-add"><?php echo esc_html(sc_t('whatsapp.add_number', 'Add number')); ?></button>
                <?php endif; ?>
            </div>

            <div id="wa-numbers" class="w-wa__numbers" aria-live="polite"></div>

            <?php if ($is_admin): ?>
            <div class="w-wa__add" id="wa-add" hidden>
                <div class="w-wa__seg" role="tablist" aria-label="<?php echo esc_attr(sc_t('whatsapp.add_how', 'How to add')); ?>">
                    <button type="button" role="tab" class="is-on" aria-selected="true" data-mode="token"><?php echo esc_html(sc_t('whatsapp.add_token', 'I have a device token')); ?></button>
                    <button type="button" role="tab" aria-selected="false" data-mode="link"><?php echo esc_html(sc_t('whatsapp.add_link', 'Link a new number by QR')); ?></button>
                </div>

                <form id="wa-add-token" class="w-wa__form" novalidate autocomplete="off">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label class="w-field__label" for="wa-t-label"><?php echo esc_html(sc_t('whatsapp.name', 'Name')); ?></label>
                            <input class="form-control" id="wa-t-label" name="label" placeholder="<?php echo esc_attr(sc_t('whatsapp.name_ph', 'Support line')); ?>" maxlength="60">
                        </div>
                        <div class="w-field">
                            <label class="w-field__label" for="wa-t-token"><?php echo esc_html(sc_t('whatsapp.device_token', 'Device token')); ?></label>
                            <input class="form-control w-ltr" id="wa-t-token" name="token" type="password" placeholder="wabot_…" spellcheck="false">
                        </div>
                        <div class="w-field">
                            <label class="w-field__label" for="wa-t-secret"><?php echo esc_html(sc_t('whatsapp.webhook_secret', 'Webhook secret')); ?></label>
                            <input class="form-control w-ltr" id="wa-t-secret" name="webhook_secret" type="password" spellcheck="false">
                            <p class="w-field__help"><?php echo esc_html(sc_t('whatsapp.webhook_secret_help', 'From the webhook you add in wa-bot for this number. Without it, WhatsApp replies and connection changes cannot reach the site.')); ?></p>
                        </div>
                    </div>
                    <p class="w-wa__note"><?php echo esc_html(sc_t('whatsapp.token_steps', 'In wa-bot: open the number, copy its token, then add a webhook with the address shown on the number after you save here, and paste that webhook\'s secret.')); ?></p>
                    <div class="w-wa__formactions">
                        <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('whatsapp.save_number', 'Save number')); ?></button>
                        <p class="w-wa__msg" role="alert" hidden></p>
                    </div>
                </form>

                <form id="wa-add-link" class="w-wa__form" novalidate autocomplete="off" hidden>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label class="w-field__label" for="wa-l-label"><?php echo esc_html(sc_t('whatsapp.name', 'Name')); ?></label>
                            <input class="form-control" id="wa-l-label" name="label" placeholder="<?php echo esc_attr(sc_t('whatsapp.name_ph', 'Support line')); ?>" maxlength="60">
                        </div>
                    </div>
                    <p class="w-wa__note" id="wa-link-note"></p>
                    <div class="w-wa__formactions">
                        <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('whatsapp.create_show_qr', 'Create and show QR')); ?></button>
                        <p class="w-wa__msg" role="alert" hidden></p>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </section>

        <!-- Chat alerts -->
        <section class="w-section" aria-labelledby="wa-alerts-title">
            <div class="w-section__head">
                <h2 id="wa-alerts-title"><?php echo esc_html(sc_t('whatsapp.chat_alerts', 'Chat alerts')); ?></h2>
                <span class="w-section__hint"><?php echo esc_html(sc_t('whatsapp.chat_alerts_hint', 'Who hears about a new website chat message, in this order.')); ?></span>
            </div>
            <form id="wa-alerts" class="w-wa__form" novalidate>
                <div class="w-choice w-choice--stack" role="radiogroup" aria-label="<?php echo esc_attr(sc_t('whatsapp.alert_mode', 'How to alert')); ?>">
                    <label class="w-choice__item"><input type="radio" name="alert_mode" value="escalate"><span class="w-choice__box"><span><strong><?php echo esc_html(sc_t('whatsapp.mode_escalate', 'One by one')); ?></strong><span class="w-choice__sub"><?php echo esc_html(sc_t('whatsapp.mode_escalate_sub', 'Alert the first person. If nobody answers in the chat in time, alert the next.')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="alert_mode" value="all"><span class="w-choice__box"><span><strong><?php echo esc_html(sc_t('whatsapp.mode_all', 'Everyone at once')); ?></strong><span class="w-choice__sub"><?php echo esc_html(sc_t('whatsapp.mode_all_sub', 'Every person below gets each alert.')); ?></span></span></span></label>
                </div>

                <div class="w-field w-wa__minfield">
                    <label class="w-field__label" for="wa-minutes"><?php echo esc_html(sc_t('whatsapp.minutes_label', 'Minutes before alerting the next person')); ?></label>
                    <input class="form-control" id="wa-minutes" type="number" name="escalate_minutes" min="2" max="120">
                </div>

                <ol class="w-wa__people" id="wa-people"></ol>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="wa-person-add"><?php echo esc_html(sc_t('whatsapp.add_person', 'Add person')); ?></button>

                <label class="w-switch mt-3">
                    <input type="checkbox" name="chat_replies" value="1">
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('whatsapp.chat_replies', 'Send chat replies on WhatsApp')); ?></strong><span><?php echo esc_html(sc_t('whatsapp.chat_replies_sub', 'When a visitor ticks "Reply on WhatsApp" in the website chat, your replies from the chat inbox also go to their WhatsApp, and their WhatsApp replies come back into the same conversation.')); ?></span></span>
                </label>

                <div class="w-wa__formactions">
                    <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('general.save', 'Save')); ?></button>
                    <p class="w-wa__msg" role="alert" hidden></p>
                </div>
            </form>
        </section>

        <?php if ($is_admin): ?>
        <!-- Connection -->
        <section class="w-section" aria-labelledby="wa-conn-title">
            <div class="w-section__head">
                <h2 id="wa-conn-title"><?php echo esc_html(sc_t('whatsapp.connection', 'wa-bot connection')); ?></h2>
                <span class="w-section__hint"><?php echo esc_html(sc_t('whatsapp.admins_only', 'Administrators only')); ?></span>
            </div>
            <form id="wa-conn" class="w-wa__form" novalidate autocomplete="off">
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label class="w-field__label" for="wa-base"><?php echo esc_html(sc_t('whatsapp.base_url', 'wa-bot address')); ?></label>
                        <input class="form-control w-ltr" id="wa-base" name="base_url" type="url" placeholder="https://whatsapp.example.com">
                    </div>
                    <div class="w-field">
                        <label class="w-field__label" for="wa-admin-key"><?php echo esc_html(sc_t('whatsapp.admin_key', 'Admin key (optional)')); ?> <span class="w-tag w-tag--teal" id="wa-admin-key-saved" hidden><?php echo esc_html(sc_t('whatsapp.saved', 'Saved')); ?></span></label>
                        <input class="form-control w-ltr" id="wa-admin-key" name="admin_key" type="password" placeholder="wabotk_…" spellcheck="false">
                        <p class="w-field__help"><?php echo esc_html(sc_t('whatsapp.admin_key_help', 'Lets you link new numbers by QR from this page. Leave empty to keep the saved key.')); ?></p>
                    </div>
                </div>
                <div class="w-wa__formactions">
                    <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('general.save', 'Save')); ?></button>
                    <button type="button" class="btn btn-link text-danger" id="wa-admin-key-clear" hidden><?php echo esc_html(sc_t('whatsapp.forget_admin_key', 'Forget admin key')); ?></button>
                    <p class="w-wa__msg" role="alert" hidden></p>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- Activity -->
        <section class="w-section" aria-labelledby="wa-log-title">
            <div class="w-section__head">
                <h2 id="wa-log-title"><?php echo esc_html(sc_t('whatsapp.activity', 'Recent WhatsApp messages')); ?></h2>
                <button type="button" class="btn btn-link btn-sm" id="wa-log-reload"><?php echo esc_html(sc_t('whatsapp.reload', 'Reload')); ?></button>
            </div>
            <div class="w-table-scroll"><table class="w-table w-wa__log">
                <thead><tr>
                    <th scope="col"><?php echo esc_html(sc_t('whatsapp.when', 'When')); ?></th>
                    <th scope="col"><?php echo esc_html(sc_t('whatsapp.to', 'To')); ?></th>
                    <th scope="col"><?php echo esc_html(sc_t('whatsapp.what', 'Message')); ?></th>
                    <th scope="col"><?php echo esc_html(sc_t('whatsapp.status', 'Status')); ?></th>
                </tr></thead>
                <tbody id="wa-log-body"><tr><td colspan="4" class="w-sub"><?php echo esc_html(sc_t('general.loading', 'Loading…')); ?></td></tr></tbody>
            </table></div>
            <div id="wa-unmatched-wrap" hidden>
                <h3 class="w-wa__h3"><?php echo esc_html(sc_t('whatsapp.unmatched', 'WhatsApp messages not linked to a chat')); ?></h3>
                <p class="w-sub"><?php echo esc_html(sc_t('whatsapp.unmatched_sub', 'Sent to your number by people with no open website chat, or from a hidden number. Answer them in WhatsApp.')); ?></p>
                <ul class="w-wa__unmatched" id="wa-unmatched"></ul>
            </div>
        </section>
    </div>

</div>
</div>

<style>
.w-wa { display: flex; flex-direction: column; gap: 16px; max-width: 1040px; }
.w-wa [hidden] { display: none !important; }
.w-wa__numbers { display: flex; flex-direction: column; gap: 10px; }
.w-wa__empty { margin: 0; color: var(--w-text-2); }
.w-wa__num { border: 1px solid var(--w-border); border-radius: var(--w-radius-md); padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; background: var(--w-surface); }
.w-wa__numtop { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.w-wa__dot { width: 10px; height: 10px; border-radius: 50%; background: var(--w-text-3); flex: none; }
.w-wa__dot--connected { background: #12B76A; box-shadow: 0 0 0 4px rgba(18, 183, 106, .15); }
.w-wa__dot--qr, .w-wa__dot--connecting, .w-wa__dot--at_risk, .w-wa__dot--unknown { background: #F79009; }
.w-wa__dot--disconnected, .w-wa__dot--logged_out, .w-wa__dot--banned, .w-wa__dot--token_rejected, .w-wa__dot--unreachable, .w-wa__dot--error, .w-wa__dot--deleted { background: var(--w-danger); }
.w-wa__numname { font-weight: 600; }
.w-wa__numphone { color: var(--w-text-2); }
.w-wa__numstatus { margin-inline-start: auto; font-size: 13px; color: var(--w-text-2); text-align: end; }
.w-wa__numrow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; }
.w-wa__numrow .w-switch { margin: 0; }
.w-wa__hook { display: flex; gap: 6px; align-items: center; font-size: 12.5px; color: var(--w-text-2); }
.w-wa__hook code { background: var(--w-surface-2); padding: 2px 6px; border-radius: 4px; overflow-wrap: anywhere; }
.w-wa__warn { margin: 0; padding: 8px 12px; border-radius: var(--w-radius-md); background: var(--w-warning-soft); color: var(--w-warning); font-size: 13px; }
.w-wa__add { border-top: 1px solid var(--w-border); margin-top: 14px; padding-top: 14px; display: flex; flex-direction: column; gap: 14px; }
.w-wa__seg { display: inline-flex; gap: 4px; padding: 3px; border: 1px solid var(--w-border); border-radius: var(--w-radius-md); background: var(--w-surface-2); width: max-content; max-width: 100%; flex-wrap: wrap; }
.w-wa__seg button { border: 0; background: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; color: var(--w-text-2); cursor: pointer; }
.w-wa__seg button.is-on { background: var(--w-surface); color: var(--w-text); box-shadow: var(--w-shadow-1); }
.w-wa__form { display: flex; flex-direction: column; gap: 14px; }
.w-wa__note { margin: 0; font-size: 13px; color: var(--w-text-2); }
.w-wa__formactions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.w-wa__msg { margin: 0; font-size: 13px; font-weight: 500; }
.w-wa__msg.is-error { color: var(--w-danger); }
.w-wa__msg.is-ok { color: var(--w-teal-text); }
.w-wa__minfield { max-width: 280px; }
.w-wa__people { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; counter-reset: wa; }
.w-wa__person { display: grid; grid-template-columns: 28px minmax(0, 1fr) minmax(0, 1fr) auto auto; gap: 8px; align-items: center; }
.w-wa__person::before { counter-increment: wa; content: counter(wa); width: 26px; height: 26px; border-radius: 50%; background: var(--w-primary-soft); color: var(--w-primary-text); font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
.w-wa__person.has-error .form-control { border-color: var(--w-danger); }
.w-wa__personbtns { display: inline-flex; gap: 2px; }
.w-wa__iconbtn { border: 1px solid var(--w-border); background: var(--w-surface); border-radius: 6px; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; color: var(--w-text-2); cursor: pointer; }
.w-wa__iconbtn:hover { color: var(--w-text); border-color: var(--w-border-strong); }
.w-wa__iconbtn:disabled { opacity: .4; cursor: default; }
.w-wa__log td { vertical-align: top; font-size: 13px; }
.w-wa__log td:nth-child(3) { min-width: 260px; }
.w-wa__log .w-wa__text { white-space: pre-line; overflow-wrap: anywhere; max-height: 7.5em; overflow: hidden; }
.w-wa__h3 { margin: 18px 0 4px; font-size: 14px; font-weight: 600; }
.w-wa__unmatched { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.w-wa__unmatched li { font-size: 13px; padding: 8px 10px; border: 1px solid var(--w-border); border-radius: var(--w-radius-md); }
.w-wa-qr { width: 100%; height: 460px; border: 0; border-radius: 8px; background: #fff; }
@media (max-width: 767.98px) {
    .w-wa__log thead { display: none; }
    .w-wa__log, .w-wa__log tbody, .w-wa__log tr, .w-wa__log td { display: block; width: 100%; }
    .w-dash .w-wa__log tbody tr { display: block; padding: 12px 0; }
    .w-dash .w-wa__log td { display: block !important; border: 0; padding: 2px 0 !important; min-width: 0 !important; text-align: start; }
    .w-dash .w-wa__log td::before { content: none !important; display: none !important; }
    .w-wa__log td > div, .w-wa__log td > a { display: block; }
    .w-wa__log td > .w-tag { display: inline-block; }
    .w-wa__person { grid-template-columns: 28px minmax(0, 1fr) auto; }
    .w-wa__person .wa-p-phone { grid-column: 2 / 3; }
    .w-wa__numstatus { margin-inline-start: 0; text-align: start; width: 100%; }
}
</style>

<script>
jQuery(function ($) {
    'use strict';
    var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    var state = <?php echo wp_json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var ajax = scDashboard.ajaxurl, nonce = scDashboard.nonce;

    var STATUS = {
        connected: 'Connected', qr: 'Waiting for the QR to be scanned', connecting: 'Connecting…',
        disconnected: 'Disconnected', logged_out: 'Logged out — scan the QR again', banned: 'Banned by WhatsApp',
        at_risk: 'At risk: recent sends failed', token_rejected: 'wa-bot rejected the token', unreachable: 'wa-bot not reachable',
        error: 'Error', deleted: 'Deleted in wa-bot', unknown: 'Not checked yet'
    };
    var OUT = { pending: 'Waiting to send', sending: 'Sending', sent: 'Sent', skipped: 'Not on WhatsApp', failed: 'Failed', expired: 'Not sent in time' };
    var DELIVERY = { server_ack: 'sent', delivered: 'delivered', read: 'read', played: 'played', error: 'error' };
    var CONTEXT = { chat_alert: 'Chat alert', chat_reply: 'Chat reply' };

    function post(action, data) {
        return $.post(ajax, $.extend({ action: action, nonce: nonce }, data || {}));
    }
    function say($form, text, ok) {
        $form.find('.w-wa__msg').first().text(text || '').toggleClass('is-error', !ok).toggleClass('is-ok', !!ok).prop('hidden', !text);
    }
    function fail($form) {
        return function (xhr) {
            var r = xhr && xhr.responseJSON;
            say($form, (r && r.data && r.data.message) || 'Could not save. Check the connection and try again.', false);
        };
    }
    function busy($btn, on) { $btn.prop('disabled', on); }
    function when(s) {
        if (!s) { return ''; }
        var d = new Date(String(s).replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    /* ---------------- numbers ---------------- */

    function renderNumbers() {
        var $box = $('#wa-numbers').empty();
        if (!state.numbers.length) {
            $box.append($('<p class="w-wa__empty">').text(isAdmin ? 'No number yet. Add one to start.' : 'No WhatsApp number is connected yet. Ask an administrator to add one.'));
            return;
        }
        var chatCount = state.numbers.filter(function (n) { return n.enabled && n.chat; }).length;
        if (!chatCount) {
            $box.append($('<p class="w-wa__warn">').text('No number is set to handle the chat, so chat alerts and replies are not sent.'));
        }
        state.numbers.forEach(function (n) {
            var $card = $('<div class="w-wa__num">').attr('data-key', n.key);
            var $top = $('<div class="w-wa__numtop">').appendTo($card);
            $('<span class="w-wa__dot">').addClass('w-wa__dot--' + (n.enabled ? n.status : 'unknown')).attr('aria-hidden', 'true').appendTo($top);
            $('<span class="w-wa__numname">').text(n.label).appendTo($top);
            if (n.phone) { $('<span class="w-wa__numphone w-ltr">').text('+' + n.phone).appendTo($top); }
            var status = n.enabled ? (STATUS[n.status] || n.status) : 'Turned off here';
            $('<span class="w-wa__numstatus">').text(status + (n.status_at && n.enabled ? ' · ' + when(n.status_at) : '') + (n.status_error ? ' — ' + n.status_error : '')).appendTo($top);

            if (isAdmin) {
                var $row = $('<div class="w-wa__numrow">').appendTo($card);
                $row.append(toggle('enabled', 'On', n.enabled), toggle('chat', 'Handles the website chat', n.chat), toggle('bulk', 'Used for bulk sending', n.bulk));
                var $acts = $('<span class="w-wa__numrow">').appendTo($row);
                if (n.can_qr && n.status !== 'connected') { $('<button type="button" class="btn btn-primary btn-sm" data-act="qr">').text('Show QR').appendTo($acts); }
                $('<button type="button" class="btn btn-outline-secondary btn-sm" data-act="test">').text('Send test').appendTo($acts);
                $('<button type="button" class="btn btn-outline-secondary btn-sm" data-act="tokens">').text('Replace token').appendTo($acts);
                $('<button type="button" class="btn btn-link btn-sm text-danger" data-act="remove">').text('Remove').appendTo($acts);
                if (!n.has_secret) {
                    $('<p class="w-wa__warn">').text('No webhook secret saved: WhatsApp replies and connection changes cannot reach the site. Add a webhook in wa-bot with the address below and paste its secret with "Replace token".').appendTo($card);
                }
                if (!n.linked) {
                    var $hook = $('<div class="w-wa__hook">').appendTo($card);
                    $hook.append($('<span>').text('Webhook address for wa-bot:'), $('<code class="w-ltr">').text(n.webhook_url), $('<button type="button" class="btn btn-link btn-sm" data-act="copy">').text('Copy'));
                }
            }
            $box.append($card);
        });
    }

    function toggle(name, label, on) {
        var $l = $('<label class="w-switch">');
        $('<input type="checkbox">').attr('data-flag', name).prop('checked', !!on).appendTo($l);
        $l.append('<span class="w-switch__track" aria-hidden="true"></span>', $('<span class="w-switch__text">').append($('<strong>').text(label)));
        return $l;
    }

    $('#wa-numbers').on('change', 'input[data-flag]', function () {
        var $in = $(this), key = $in.closest('.w-wa__num').data('key'), data = { key: key };
        data[$in.data('flag')] = $in.is(':checked') ? 1 : 0;
        post('sc_wabot_update_number', data).done(function (r) { if (r.success) { state = r.data; renderAll(); } });
    });

    $('#wa-numbers').on('click', 'button[data-act]', function () {
        var act = $(this).data('act'), key = $(this).closest('.w-wa__num').data('key');
        var n = state.numbers.filter(function (x) { return x.key === key; })[0];
        if (act === 'copy') {
            navigator.clipboard && navigator.clipboard.writeText(n.webhook_url);
            $(this).text('Copied');
        } else if (act === 'qr') {
            showQr(key);
        } else if (act === 'test') {
            Swal.fire({ title: 'Send a test message', input: 'tel', inputLabel: 'Phone number that should receive it', inputPlaceholder: '01012345678', showCancelButton: true, confirmButtonText: 'Send',
                preConfirm: function (phone) {
                    return post('sc_wabot_test', { key: key, phone: phone }).then(function (r) {
                        if (!r.success) { Swal.showValidationMessage(r.data.message); return false; }
                        return r.data.message;
                    }, function () { Swal.showValidationMessage('Could not reach the site.'); return false; });
                }
            }).then(function (res) { if (res.isConfirmed) { Swal.fire({ icon: 'success', title: res.value, timer: 2500, showConfirmButton: false }); } });
        } else if (act === 'tokens') {
            Swal.fire({ title: 'Replace token', html:
                '<input id="swal-token" class="swal2-input" type="password" placeholder="Device token (leave empty to keep)" autocomplete="off">' +
                '<input id="swal-secret" class="swal2-input" type="password" placeholder="Webhook secret (leave empty to keep)" autocomplete="off">',
                showCancelButton: true, confirmButtonText: 'Save',
                preConfirm: function () {
                    return post('sc_wabot_update_number', { key: key, token: $('#swal-token').val(), webhook_secret: $('#swal-secret').val() }).then(function (r) {
                        if (!r.success) { Swal.showValidationMessage(r.data.message); return false; }
                        state = r.data; renderAll(); return true;
                    });
                }
            });
        } else if (act === 'remove') {
            Swal.fire({ icon: 'warning', title: 'Remove "' + n.label + '"?', text: 'The site stops using this number. The number itself stays in wa-bot.', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#B42318' })
                .then(function (res) {
                    if (!res.isConfirmed) { return; }
                    post('sc_wabot_remove_number', { key: key }).done(function (r) { if (r.success) { state = r.data; renderAll(); } });
                });
        }
    });

    function showQr(key) {
        post('sc_wabot_qr', { key: key }).done(function (r) {
            if (!r.success) { Swal.fire({ icon: 'info', text: r.data.message }); return; }
            var frame = document.createElement('iframe');
            frame.className = 'w-wa-qr';
            frame.src = r.data.url;
            frame.title = 'WhatsApp QR code';
            Swal.fire({ title: 'Scan with WhatsApp', html: frame, width: 520, confirmButtonText: 'Done',
                footer: 'On the phone: WhatsApp → Settings → Linked devices → Link a device.' }).then(function () { refresh(true); });
            window.addEventListener('message', function onMsg(e) {
                if (e.data === 'wa-bot:connected' || (e.data && e.data.type === 'wa-bot:connected')) {
                    window.removeEventListener('message', onMsg);
                    Swal.close();
                    refresh(true);
                }
            });
        });
    }

    $('#wa-add-toggle').on('click', function () {
        var open = $('#wa-add').prop('hidden');
        $('#wa-add').prop('hidden', !open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
        if (open) { $('#wa-t-label').trigger('focus'); }
    });
    $('.w-wa__seg button').on('click', function () {
        var mode = $(this).data('mode');
        $('.w-wa__seg button').removeClass('is-on').attr('aria-selected', 'false');
        $(this).addClass('is-on').attr('aria-selected', 'true');
        $('#wa-add-token').prop('hidden', mode !== 'token');
        $('#wa-add-link').prop('hidden', mode !== 'link');
    });

    $('#wa-add-token').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('[type=submit]');
        busy($b, true); say($f, '');
        post('sc_wabot_add_number', { label: $('#wa-t-label').val(), token: $('#wa-t-token').val(), webhook_secret: $('#wa-t-secret').val() })
            .done(function (r) {
                if (!r.success) { say($f, r.data.message, false); return; }
                state = r.data.state; renderAll();
                $f[0].reset();
                say($f, r.data.status === 'connected' ? 'Saved — the number is connected.' : 'Saved. Status: ' + (STATUS[r.data.status] || r.data.status) + '. Now add the webhook in wa-bot with the address shown on the number.', r.data.status === 'connected');
            }).fail(fail($f)).always(function () { busy($b, false); });
    });

    $('#wa-add-link').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('[type=submit]');
        busy($b, true); say($f, '');
        post('sc_wabot_link_number', { label: $('#wa-l-label').val() })
            .done(function (r) {
                if (!r.success) { say($f, r.data.message, false); return; }
                state = r.data.state; renderAll();
                $f[0].reset();
                showQr(r.data.key);
            }).fail(fail($f)).always(function () { busy($b, false); });
    });

    /* ---------------- alerts ---------------- */

    function personRow(p) {
        var $li = $('<li class="w-wa__person">');
        $('<input class="form-control wa-p-name" maxlength="60" placeholder="Name">').val(p.name || '').attr('aria-label', 'Name').appendTo($li);
        $('<input class="form-control wa-p-phone w-ltr" type="tel" placeholder="01012345678">').val(p.phone ? '+' + String(p.phone).replace(/^\+/, '') : '').attr('aria-label', 'WhatsApp number').appendTo($li);
        var $on = $('<label class="w-switch m-0">');
        $('<input type="checkbox" class="wa-p-on">').prop('checked', p.enabled !== 0 && p.enabled !== false).appendTo($on);
        $on.append('<span class="w-switch__track" aria-hidden="true"></span>', $('<span class="sr-only">').text('On'));
        $li.append($on);
        var $btns = $('<span class="w-wa__personbtns">').appendTo($li);
        $('<button type="button" class="w-wa__iconbtn" data-move="-1" aria-label="Move up">↑</button>').appendTo($btns);
        $('<button type="button" class="w-wa__iconbtn" data-move="1" aria-label="Move down">↓</button>').appendTo($btns);
        $('<button type="button" class="w-wa__iconbtn" data-remove aria-label="Remove">×</button>').appendTo($btns);
        return $li;
    }
    function renderAlerts() {
        var $f = $('#wa-alerts');
        $f.find('input[name=alert_mode][value=' + (state.alert_mode === 'all' ? 'all' : 'escalate') + ']').prop('checked', true);
        $f.find('input[name=escalate_minutes]').val(state.escalate_minutes);
        $f.find('input[name=chat_replies]').prop('checked', !!state.chat_replies);
        var $ol = $('#wa-people').empty();
        (state.recipients.length ? state.recipients : [{}]).forEach(function (p) { $ol.append(personRow(p)); });
        syncMoves();
    }
    function syncMoves() {
        var $rows = $('#wa-people .w-wa__person');
        $rows.find('[data-move]').prop('disabled', false);
        $rows.first().find('[data-move="-1"]').prop('disabled', true);
        $rows.last().find('[data-move="1"]').prop('disabled', true);
    }
    $('#wa-person-add').on('click', function () { $('#wa-people').append(personRow({})); syncMoves(); $('#wa-people .wa-p-name').last().trigger('focus'); });
    $('#wa-people').on('click', '[data-move]', function () {
        var $li = $(this).closest('li');
        if (+$(this).data('move') < 0) { $li.prev().before($li); } else { $li.next().after($li); }
        syncMoves();
    }).on('click', '[data-remove]', function () { $(this).closest('li').remove(); syncMoves(); });

    $('#wa-alerts').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('[type=submit]');
        var people = $('#wa-people .w-wa__person').map(function () {
            return { name: $(this).find('.wa-p-name').val(), phone: $(this).find('.wa-p-phone').val(), enabled: $(this).find('.wa-p-on').is(':checked') ? 1 : 0 };
        }).get();
        $('#wa-people .w-wa__person').removeClass('has-error');
        busy($b, true); say($f, '');
        post('sc_wabot_save_alerts', {
            recipients: JSON.stringify(people),
            alert_mode: $f.find('input[name=alert_mode]:checked').val(),
            escalate_minutes: $f.find('input[name=escalate_minutes]').val(),
            chat_replies: $f.find('input[name=chat_replies]').is(':checked') ? 1 : 0
        }).done(function (r) {
            if (!r.success) {
                (r.data.rows || []).forEach(function (i) { $('#wa-people .w-wa__person').eq(i).addClass('has-error'); });
                say($f, r.data.message, false); return;
            }
            state = r.data; renderAll();
            say($f, 'Saved.', true);
        }).fail(fail($f)).always(function () { busy($b, false); });
    });

    /* ---------------- connection ---------------- */

    function renderConnection() {
        $('#wa-base').val(state.base_url);
        $('#wa-admin-key-saved, #wa-admin-key-clear').prop('hidden', !state.has_admin_key);
        $('#wa-link-note').text(state.has_admin_key
            ? 'A new number is created in wa-bot with this site\'s webhook already set. Scan the QR with the phone that has the WhatsApp account.'
            : 'Save the wa-bot admin key in "wa-bot connection" below to link numbers from here.');
    }
    $('#wa-conn').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('[type=submit]');
        busy($b, true); say($f, '');
        post('sc_wabot_save_connection', { base_url: $('#wa-base').val(), admin_key: $('#wa-admin-key').val() })
            .done(function (r) {
                if (!r.success) { say($f, r.data.message, false); return; }
                state = r.data; renderAll(); $('#wa-admin-key').val('');
                say($f, 'Saved.', true);
            }).fail(fail($f)).always(function () { busy($b, false); });
    });
    $('#wa-admin-key-clear').on('click', function () {
        post('sc_wabot_save_connection', { base_url: $('#wa-base').val(), clear_admin_key: 1 }).done(function (r) { if (r.success) { state = r.data; renderAll(); } });
    });

    /* ---------------- log ---------------- */

    function loadLog() {
        post('sc_wabot_log').done(function (r) {
            if (!r.success) { return; }
            var $body = $('#wa-log-body').empty();
            if (!r.data.outbox.length) {
                $body.append($('<tr>').append($('<td colspan="4" class="w-sub">').text('Nothing sent yet.')));
            }
            r.data.outbox.forEach(function (m) {
                var status = OUT[m.status] || m.status;
                if (m.status === 'sent' && m.delivery && DELIVERY[m.delivery]) { status = 'Sent · ' + DELIVERY[m.delivery]; }
                var $what = $('<td>').append($('<div class="w-sub">').text((CONTEXT[m.context] || m.context || 'Message') + (m.number ? ' · ' + m.number : '')), $('<div class="w-wa__text" dir="auto">').text(m.text));
                if (m.conversation) { $what.append($('<a>').attr('href', '<?php echo esc_js(home_url('/event-manager-dashboard/chat')); ?>?conversation=' + m.conversation).text('Open chat')); }
                $body.append($('<tr>').append(
                    $('<td class="w-nowrap">').text(when(m.at)),
                    $('<td class="w-ltr w-nowrap">').text('+' + m.to),
                    $what,
                    $('<td>').append($('<span class="w-tag">').addClass(m.status === 'sent' ? 'w-tag--teal' : (m.status === 'failed' || m.status === 'skipped' || m.status === 'expired' ? 'w-tag--red' : '')).text(status),
                        m.error && m.status !== 'sent' ? $('<div class="w-sub">').text(m.error + (m.attempts > 1 ? ' (' + m.attempts + ' tries)' : '')) : '')
                ));
            });
            var $un = $('#wa-unmatched').empty();
            $('#wa-unmatched-wrap').prop('hidden', !r.data.unmatched.length);
            r.data.unmatched.forEach(function (u) {
                $un.append($('<li>').append($('<strong dir="auto">').text((u.name || '') + ' '), $('<span class="w-ltr">').text(/^\d+$/.test(u.from) ? '+' + u.from : u.from), $('<span class="w-sub">').text(' · ' + when(u.at) + ' · ' + u.number), $('<div dir="auto">').text(u.text || '[' + u.type + ']')));
            });
        });
    }
    $('#wa-log-reload').on('click', loadLog);

    function refresh(remote) {
        var $b = $('#wa-refresh');
        busy($b, true);
        post('sc_wabot_state', { refresh: remote ? 1 : 0 }).done(function (r) { if (r.success) { state = r.data; renderAll(); } }).always(function () { busy($b, false); });
    }
    $('#wa-refresh').on('click', function () { refresh(true); loadLog(); });

    function renderAll() { renderNumbers(); renderConnection(); }
    renderAll();
    renderAlerts();
    loadLog();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
