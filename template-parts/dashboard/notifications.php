<?php
/**
 * Push notifications — connection status, compose with a phone preview, and the send log.
 * Handlers: inc/admin-dashboard/notifications-ajax-handlers.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_form, $load_wd_overview;
$load_wd_list = true;
$load_wd_form = true;
$load_wd_overview = true;
$p = $wpdb->prefix;

$connected = get_option('sc_fcm_server_key', '') !== '';
$on_new_event = get_option('sc_push_on_new_event', '0') === '1';
$tokens_table = $p . 'sc_fcm_tokens';
$devices = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table");
$linked = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $tokens_table WHERE user_id IS NOT NULL AND user_id > 0");
$last_seen = $wpdb->get_var("SELECT MAX(updated_at) FROM $tokens_table");
$sent_30 = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_notifications_log WHERE status IN ('sent', 'partial') AND created_at >= %s", gmdate('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS)));
$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title FROM {$p}sc_events WHERE status = 'publish' AND COALESCE(end_date, start_date) >= %s ORDER BY start_date ASC LIMIT 50",
    gmdate('Y-m-d', current_time('timestamp') - 30 * DAY_IN_SECONDS)
));
$site_name = get_option('sc_platform_name', get_bloginfo('name'));
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.notifications', 'Push notifications')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('push.sub', 'Send a notification to phones that have the app installed with notifications turned on.')); ?></p>
        </div>
    </div>

    <div class="w-push__status <?php echo $connected ? 'is-on' : 'is-off'; ?>" role="status">
        <strong><?php echo esc_html($connected ? sc_t('push.connected', 'Firebase key saved') : sc_t('push.not_connected', 'Push notifications are not connected')); ?></strong>
        <span><?php echo esc_html($connected
            ? sc_t('push.connected_help', 'Sends use the legacy Firebase server key. Google has retired that method, so each send is checked and logged as failed if Firebase does not accept it.')
            : sc_t('push.not_connected_help', 'No Firebase key is saved, so nothing can be sent. Connecting needs the Firebase project that the mobile app uses.')); ?></span>
    </div>

    <div class="w-kpis">
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('push.devices', 'Devices')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($devices)); ?></span><span class="w-kpi__sub"><?php echo esc_html($last_seen ? sprintf(sc_t('push.last_registered', 'Last registered %s'), mysql2date('j M Y', $last_seen)) : sc_t('push.none_registered', 'None registered yet')); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('push.signed_in', 'Signed-in devices')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($linked)); ?></span><span class="w-kpi__sub"><?php echo esc_html(sc_t('push.signed_in_help', 'Can be reached by event')); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('push.sent_30', 'Delivered, last 30 days')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($sent_30)); ?></span></div>
    </div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <form class="w-section" id="push-form" novalidate autocomplete="off" aria-labelledby="push-compose">
                <div class="w-section__head"><h2 id="push-compose"><?php echo esc_html(sc_t('push.compose', 'New notification')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="push-title"><?php echo esc_html(sc_t('push.title', 'Title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="push-title" name="notif_title" maxlength="65" data-w-count="65" dir="auto" required>
                        <span class="w-field__count"></span>
                    </div>
                    <div class="w-field">
                        <label for="push-body"><?php echo esc_html(sc_t('push.message', 'Message')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <textarea class="form-control" id="push-body" name="notif_body" rows="3" maxlength="240" data-w-count="240" dir="auto" required></textarea>
                        <span class="w-field__count"></span>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('push.audience', 'Send to')); ?></span>
                        <div class="w-choice" role="radiogroup">
                            <label class="w-choice__item"><input type="radio" name="notif_target" value="all" checked><span class="w-choice__box"><span><?php echo esc_html(sc_t('push.everyone', 'Everyone with the app')); ?></span></span></label>
                            <label class="w-choice__item"><input type="radio" name="notif_target" value="event" <?php disabled(!$events); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('push.registered_for', 'People registered for an event')); ?></span></span></label>
                        </div>
                    </div>
                    <div class="w-field" data-w-show-if="notif_target:event">
                        <label for="push-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
                        <select class="form-control" id="push-event" name="notif_event_id">
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="w-push__reach" id="push-reach" aria-live="polite"></p>
                </div>
                <div class="w-push__actions">
                    <button type="submit" class="btn btn-primary" data-w-save <?php disabled(!$connected); ?>><?php echo esc_html(sc_t('push.send', 'Send notification')); ?></button>
                    <?php if (!$connected): ?><span class="w-sub"><?php echo esc_html(sc_t('push.send_disabled', 'Sending is off until push notifications are connected.')); ?></span><?php endif; ?>
                </div>
                <div class="w-errors" data-w-errors hidden></div>
            </form>

            <section class="w-section" aria-labelledby="push-log">
                <div class="w-section__head"><h2 id="push-log"><?php echo esc_html(sc_t('push.history', 'Sent notifications')); ?></h2></div>
                <div id="push-history">
                    <div class="w-table-card" data-w-card aria-live="polite">
                        <div class="w-table-card__progress" data-w-progress hidden></div>
                        <div class="w-table-scroll" data-w-scroll><table class="w-table" data-w-table><thead></thead><tbody></tbody></table></div>
                        <div class="w-state" data-w-state hidden></div>
                        <div class="w-pager" data-w-pager hidden></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('push.preview', 'Preview')); ?></span>
                <div class="w-push__phone" aria-hidden="true">
                    <div class="w-push__card">
                        <span class="w-push__app"><?php echo esc_html($site_name); ?> · <?php echo esc_html(sc_t('push.now', 'now')); ?></span>
                        <strong class="w-push__ptitle" id="pv-title" dir="auto"><?php echo esc_html(sc_t('push.pv_title', 'Your title')); ?></strong>
                        <span class="w-push__pbody" id="pv-body" dir="auto"><?php echo esc_html(sc_t('push.pv_body', 'Your message appears here.')); ?></span>
                    </div>
                </div>
            </div>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('push.automatic', 'Automatic')); ?></span>
                <label class="w-switch">
                    <input type="checkbox" id="push-auto" <?php checked($on_new_event); ?>>
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('push.on_new_event', 'Notify everyone when an event is created')); ?></strong><span><?php echo esc_html(sc_t('push.on_new_event_help', 'Off by default: it fires for every new event, including drafts.')); ?></span></span>
                </label>
            </div>
        </aside>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var L = <?php echo $js(array(
        'pvTitle'   => sc_t('push.pv_title', 'Your title'),
        'pvBody'    => sc_t('push.pv_body', 'Your message appears here.'),
        'reach'     => sc_t('push.reach', 'Reaches %s devices.'),
        'reachOne'  => sc_t('push.reach_one', 'Reaches 1 device.'),
        'reachNone' => sc_t('push.reach_none', 'No devices in this audience.'),
        'errTitle'  => sc_t('push.err_title', 'Enter a title.'),
        'errBody'   => sc_t('push.err_body', 'Enter the message.'),
        'errEvent'  => sc_t('dashboard_pages.err_event', 'Choose the event.'),
        'confirm'   => sc_t('push.confirm', 'Send this notification to %s devices now? It cannot be taken back.'),
        'notif'     => sc_t('push.notification', 'Notification'),
        'audience'  => sc_t('push.audience', 'Send to'),
        'devicesH'  => sc_t('push.devices', 'Devices'),
        'sentH'     => sc_t('push.sent', 'Sent'),
        'everyone'  => sc_t('push.everyone', 'Everyone with the app'),
        'status'    => array('sent' => sc_t('push.delivered', 'Delivered'), 'partial' => sc_t('push.partial', 'Partly delivered'), 'failed' => sc_t('push.failed', 'Failed')),
        'emptyText' => sc_t('push.no_history', 'Nothing has been sent yet.'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('push.sending', 'Sending…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
    )); ?>;
    var formEl = document.getElementById('push-form');
    var reach = null;

    function preview() {
        $('#pv-title').text($.trim($('#push-title').val()) || L.pvTitle);
        $('#pv-body').text($.trim($('#push-body').val()) || L.pvBody);
    }
    $('#push-title, #push-body').on('input', preview);

    var reachReq = null;
    function updateReach() {
        var target = $('[name="notif_target"]:checked').val();
        var ev = target === 'event' ? $('#push-event').val() : '';
        if (target === 'event' && !ev) { $('#push-reach').text(''); reach = null; return; }
        if (reachReq) { reachReq.abort(); }
        reachReq = $.post(scDashboard.ajaxurl, { action: 'sc_push_audience', nonce: scDashboard.nonce, event_id: ev }).done(function (res) {
            if (!res.success) { return; }
            reach = res.data.devices;
            $('#push-reach').text(reach === 0 ? L.reachNone : (reach === 1 ? L.reachOne : L.reach.replace('%s', reach)));
        });
    }
    $(formEl).on('change', '[name="notif_target"], #push-event', updateReach);
    updateReach();

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed },
        validate: function (v) {
            var e = [];
            if (!$.trim(v.notif_title)) { e.push({ field: 'notif_title', message: L.errTitle }); }
            if (!$.trim(v.notif_body)) { e.push({ field: 'notif_body', message: L.errBody }); }
            if (v.notif_target === 'event' && !v.notif_event_id) { e.push({ field: 'notif_event_id', message: L.errEvent }); }
            return e;
        },
        submit: function (fd) {
            return showConfirm(L.confirm.replace('%s', reach === null ? '?' : reach)).then(function (r) {
                if (!r.isConfirmed) { return { cancelled: true }; }
                fd.append('action', 'sc_send_notification');
                fd.append('nonce', scDashboard.nonce);
                return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (res) { return res.json(); });
            });
        },
        onSuccess: function (data, api) {
            showSuccess(data.message);
            formEl.reset();
            api.markClean();
            preview();
            log.reload();
        }
    });

    $('#push-auto').on('change', function () {
        var box = this;
        $.post(scDashboard.ajaxurl, { action: 'sc_push_settings', nonce: scDashboard.nonce, on_new_event: box.checked ? 1 : 0 })
            .done(function (res) { if (res.success) { if (window.toastr) { toastr.success(L.saved); } } else { box.checked = !box.checked; showError(L.failed); } })
            .fail(function () { box.checked = !box.checked; showError(L.failed); });
    });

    var TAG = { sent: 'w-tag--teal', partial: 'w-tag--gold', failed: 'w-tag--red' };
    var log = WDList.create({
        root: document.getElementById('push-history'),
        action: 'sc_get_notifications_history',
        rowsKey: 'rows',
        filters: [],
        perPage: 25,
        perPageOptions: [25, 50, 100],
        emptyText: L.emptyText,
        columns: [
            { label: L.notif, render: function (r) { return '<div class="w-stack w-msg__cell"><span class="w-strong" dir="auto">' + esc(r.title) + '</span><span class="w-sub w-msg__excerpt" dir="auto">' + esc(r.body) + '</span></div>'; } },
            { label: L.audience, render: function (r) { return '<span class="w-truncate">' + esc(r.event || L.everyone) + '</span>'; } },
            { label: L.devicesH, render: function (r) { return '<div class="w-stack w-nowrap"><span>' + WDList.num(r.devices) + '</span><span class="w-tag ' + (TAG[r.status] || '') + '">' + esc(L.status[r.status] || r.status) + '</span></div>'; } },
            { label: L.sentH, className: 'w-col-xl', render: function (r) { return '<div class="w-stack w-nowrap"><span>' + esc(new Date(String(r.at).replace(' ', 'T')).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })) + '</span><span class="w-sub">' + esc(r.by) + '</span></div>'; } }
        ]
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
