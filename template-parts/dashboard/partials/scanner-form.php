<?php
/**
 * Scanner account form — shared by scanner-create.php and scanner-edit.php.
 *
 * Posts to sc_save_scanner / sc_update_scanner. Access is either every event,
 * chosen events, or chosen sessions; a limited account must pick at least one,
 * because an account with no permission rows is treated as full access.
 *
 * Expects: $scanner (WP_User|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_edit = !empty($scanner);
$uid = $is_edit ? (int) $scanner->ID : 0;
$dashboard_url = home_url('/event-manager-dashboard/');
$today = current_time('Y-m-d');
$p = $wpdb->prefix;

// Current permission rows.
$rows = $uid ? $wpdb->get_results($wpdb->prepare("SELECT access_type, event_id, session_id FROM {$p}sc_scanner_permissions WHERE user_id = %d", $uid)) : array();
$types = array_unique(wp_list_pluck($rows, 'access_type'));
if (!$rows || in_array('full', $types, true)) {
    $access = 'full';
} elseif (in_array('session', $types, true) && !in_array('event', $types, true)) {
    $access = 'session';
} else {
    $access = 'event';
}
$chosen_events = array_map('intval', wp_list_pluck(array_filter($rows, function ($r) { return $r->access_type === 'event'; }), 'event_id'));
$chosen_sessions = array_map('intval', wp_list_pluck(array_filter($rows, function ($r) { return $r->access_type === 'session'; }), 'session_id'));

// Events to offer: not yet over, plus anything this account already has.
$keep_ids = array_unique(array_merge($chosen_events, array_map('intval', wp_list_pluck($rows, 'event_id'))));
$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, COALESCE(end_date, start_date) >= %s AS current
     FROM {$p}sc_events
     WHERE (status IN ('publish', 'draft') AND COALESCE(end_date, start_date) >= %s)" . ($keep_ids ? ' OR id IN (' . implode(',', array_filter($keep_ids)) . ')' : '') . "
     ORDER BY current DESC, start_date ASC",
    $today,
    $today
));
$sessions_by_event = array();
if ($events) {
    foreach ($wpdb->get_results(
        "SELECT id, event_id, title, session_date, start_time FROM {$p}sc_sessions
         WHERE event_id IN (" . implode(',', array_map('intval', wp_list_pluck($events, 'id'))) . ')
         ORDER BY session_date, start_time, title'
    ) as $s) {
        $sessions_by_event[(int) $s->event_id][] = $s;
    }
}

$stats = null;
if ($uid) {
    $stats = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS n, MAX(checked_in_at) AS last_at FROM {$p}sc_attendees WHERE checked_in = 1 AND checked_in_by = %d", $uid));
}

$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$title = $is_edit ? $scanner->display_name : sc_t('dashboard_pages.new_scanner', 'New scanner');
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_scanner', 'Create scanner');
?>

<div id="main-content">
<div class="container-fluid">
<form id="scanner-form" novalidate autocomplete="off">
    <input type="hidden" name="user_id" value="<?php echo (int) $uid; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.scanner_account', 'Scanner account') : sc_t('dashboard_pages.scanner_team', 'Scanner team')); ?></span>
            <h1 data-w-title><?php echo esc_html($title); ?></h1>
            <div class="w-form-head__meta">
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'scanners'); ?>"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.back_to_team', 'Scanner team') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" id="account" aria-labelledby="account-title">
                <div class="w-section__head">
                    <h2 id="account-title"><?php echo esc_html(sc_t('dashboard_pages.account', 'Account')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.scanner_login_hint', 'They sign in with this email and password on the scanner page.')); ?></span>
                </div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="s-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="s-name" name="name" value="<?php echo esc_attr($is_edit ? $scanner->display_name : ''); ?>" required autocomplete="off">
                    </div>
                    <div class="w-field">
                        <label for="s-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                        <input type="tel" class="form-control w-ltr" id="s-phone" name="phone" value="<?php echo esc_attr($is_edit ? (string) get_user_meta($uid, 'phone', true) : ''); ?>" autocomplete="off">
                    </div>
                    <div class="w-field">
                        <label for="s-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="email" class="form-control w-ltr" id="s-email" name="email" value="<?php echo esc_attr($is_edit ? $scanner->user_email : ''); ?>" required autocomplete="off">
                    </div>
                    <div class="w-field">
                        <label for="s-password"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.new_password', 'New password') : sc_t('dashboard_pages.password', 'Password')); ?><?php echo $is_edit ? '' : '<span class="w-req" aria-hidden="true">*</span>'; ?></label>
                        <div class="w-affix">
                            <input type="password" class="form-control w-ltr" id="s-password" name="password" minlength="8" autocomplete="new-password"<?php echo $is_edit ? '' : ' required'; ?>>
                            <button type="button" class="w-affix__btn" id="s-password-show" aria-pressed="false"><?php echo esc_html(sc_t('dashboard_pages.show', 'Show')); ?></button>
                            <button type="button" class="w-affix__btn" id="s-password-generate"><?php echo esc_html(sc_t('dashboard_pages.generate', 'Generate')); ?></button>
                        </div>
                        <p class="w-field__help"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.password_keep_help', 'Leave empty to keep the current password. At least 8 characters.') : sc_t('dashboard_pages.password_help', 'At least 8 characters. Share it with the scanner yourself.')); ?></p>
                    </div>
                </div>
            </section>

            <section class="w-section" id="access" aria-labelledby="access-title">
                <div class="w-section__head">
                    <h2 id="access-title"><?php echo esc_html(sc_t('dashboard_pages.can_scan', 'Can scan')); ?></h2>
                </div>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup" aria-labelledby="access-title">
                        <?php foreach (array(
                            'full'    => array(sc_t('dashboard_pages.every_event', 'Every event'), sc_t('dashboard_pages.access_full_help', 'Including future ones')),
                            'event'   => array(sc_t('dashboard_pages.chosen_events', 'Chosen events'), sc_t('dashboard_pages.access_event_help', 'Every ticket and session in them')),
                            'session' => array(sc_t('dashboard_pages.chosen_sessions', 'Chosen sessions'), sc_t('dashboard_pages.access_session_help', 'Session check-in only')),
                        ) as $value => $info): ?>
                        <label class="w-choice__item">
                            <input type="radio" name="access_type" value="<?php echo esc_attr($value); ?>" <?php checked($access, $value); ?>>
                            <span class="w-choice__box"><span><?php echo esc_html($info[0]); ?><span class="w-choice__sub"><?php echo esc_html($info[1]); ?></span></span></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <fieldset class="w-field" data-w-show-if="access_type:event">
                        <legend class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.events', 'Events')); ?></legend>
                        <?php if ($events): ?>
                        <div class="w-checklist">
                            <?php foreach ($events as $ev): ?>
                            <label class="w-checklist__item">
                                <input type="checkbox" name="event_ids[]" value="<?php echo (int) $ev->id; ?>" <?php checked(in_array((int) $ev->id, $chosen_events, true)); ?>>
                                <span><strong><?php echo esc_html($ev->title); ?></strong><span class="w-sub w-ltr"><?php echo esc_html(mysql2date('j M Y', $ev->start_date)); ?><?php echo (int) $ev->current ? '' : ' · ' . esc_html(sc_t('dashboard_pages.ended_untick', 'Ended — untick to remove')); ?></span></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.no_upcoming_events', 'No upcoming events.')); ?></p>
                        <?php endif; ?>
                    </fieldset>

                    <fieldset class="w-field" data-w-show-if="access_type:session">
                        <legend class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.sessions', 'Sessions')); ?></legend>
                        <?php if ($sessions_by_event): ?>
                        <div class="w-checklist">
                            <?php foreach ($events as $ev): if (empty($sessions_by_event[(int) $ev->id])) { continue; } ?>
                                <div class="w-checklist__group"><?php echo esc_html($ev->title); ?></div>
                                <?php foreach ($sessions_by_event[(int) $ev->id] as $s): ?>
                                <label class="w-checklist__item">
                                    <input type="checkbox" name="session_ids[]" value="<?php echo (int) $ev->id . ':' . (int) $s->id; ?>" <?php checked(in_array((int) $s->id, $chosen_sessions, true)); ?>>
                                    <span><strong><?php echo esc_html($s->title); ?></strong><span class="w-sub w-ltr"><?php echo esc_html(trim(($s->session_date ? mysql2date('D j M', $s->session_date) : '') . ($s->start_time ? ' · ' . substr((string) $s->start_time, -8, 5) : ''))); ?></span></span>
                                </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.no_sessions_to_pick', 'No sessions have been added to upcoming events.')); ?></p>
                        <?php endif; ?>
                    </fieldset>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.activity', 'Activity')); ?></span>
                <div class="w-stats">
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo esc_html(number_format_i18n((int) $stats->n)); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.checkins_made', 'Check-ins')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr" style="font-size:15px"><?php echo esc_html($stats->last_at ? mysql2date('j M Y', $stats->last_at) : '—'); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.last_checkin', 'Last check-in')); ?></span></span>
                </div>
                <p class="w-field__help mb-0 mt-2"><?php echo esc_html(sprintf(sc_t('dashboard_pages.account_since', 'Account since %s'), mysql2date('j M Y', $scanner->user_registered))); ?></p>
            </div>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.remove_scanner', 'Remove scanner')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.remove_scanner_help', 'Takes away scanner access. The account stays as a regular site user and its past check-ins are kept.')); ?></p>
                <button type="button" class="btn btn-sm" id="remove-scanner-btn"><?php echo esc_html(sc_t('dashboard_pages.remove_scanner', 'Remove scanner')); ?></button>
            </div>
            <?php else: ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.how_it_works', 'How it works')); ?></span>
                <p class="w-field__help mb-0"><?php echo esc_html(sc_t('dashboard_pages.scanner_how', 'A scanner account only sees the scanner page. Give it the events it will work at; you can change that any time from the Scanner team page.')); ?></p>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($save_label); ?></button>
    </div>
</form>
</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'name'      => sc_t('dashboard_pages.err_scanner_name', 'Enter the scanner’s name.'),
        'email'     => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'password'  => sc_t('dashboard_pages.err_password', 'Use at least 8 characters.'),
        'events'    => sc_t('dashboard_pages.choose_one_event', 'Choose at least one event.'),
        'sessions'  => sc_t('dashboard_pages.choose_one_session', 'Choose at least one session.'),
        'show'      => sc_t('dashboard_pages.show', 'Show'),
        'hide'      => sc_t('dashboard_pages.hide', 'Hide'),
        'created'   => sc_t('dashboard_pages.scanner_created', 'Scanner created.'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'untitled'  => sc_t('dashboard_pages.new_scanner', 'New scanner'),
        'confirmRemove' => sc_t('dashboard_pages.confirm_remove_scanner', 'Remove scanner access for %s? The account stays, as a regular site user, and its past check-ins are kept.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var formEl = document.getElementById('scanner-form');
    var pw = document.getElementById('s-password');

    $('#s-password-show').on('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.textContent = show ? L.hide : L.show;
        this.setAttribute('aria-pressed', String(show));
    });
    $('#s-password-generate').on('click', function () {
        // Readable characters only (no 0/O, 1/l/I) — it gets read out to someone at a desk.
        var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var bytes = new Uint32Array(12);
        window.crypto.getRandomValues(bytes);
        pw.value = Array.prototype.map.call(bytes, function (b) { return chars[b % chars.length]; }).join('');
        pw.type = 'text';
        $('#s-password-show').text(L.hide).attr('aria-pressed', 'true');
        pw.dispatchEvent(new Event('input', { bubbles: true }));
    });
    $('#s-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!$.trim(v.name)) { e.push({ field: 'name', message: L.name }); }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(v.email || ''))) { e.push({ field: 'email', message: L.email }); }
            if ((!isEdit || v.password) && String(v.password || '').length < 8) { e.push({ field: 'password', message: L.password }); }
            if (v.access_type === 'event' && !(v['event_ids[]'] || []).length) { e.push({ field: 'event_ids[]', message: L.events }); }
            if (v.access_type === 'session' && !(v['session_ids[]'] || []).length) { e.push({ field: 'session_ids[]', message: L.sessions }); }
            return e;
        },
        submit: function (fd) {
            // Sessions travel as event/session pairs; drop whichever list the chosen access doesn't use.
            var access = fd.get('access_type');
            var pairs = fd.getAll('session_ids[]');
            fd.delete('session_ids[]');
            if (access !== 'event') { fd.delete('event_ids[]'); }
            if (access === 'session') {
                pairs.forEach(function (pair, i) {
                    var parts = pair.split(':');
                    fd.append('session_permissions[' + i + '][event_id]', parts[0]);
                    fd.append('session_permissions[' + i + '][session_id]', parts[1]);
                });
            }
            fd.append('action', isEdit ? 'sc_update_scanner' : 'sc_save_scanner');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (!isEdit && data.user_id) {
                try { sessionStorage.setItem('scScannerCreated', '1'); } catch (x) { /* storage blocked */ }
                window.location.href = dashboardUrl + 'scanner-edit?id=' + data.user_id;
                return;
            }
            pw.value = '';
            api.markClean();
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    try {
        if (sessionStorage.getItem('scScannerCreated')) {
            sessionStorage.removeItem('scScannerCreated');
            if (window.toastr) { toastr.success(L.created); }
        }
    } catch (x) { /* storage blocked */ }

    $('#remove-scanner-btn').on('click', function () {
        showDeleteConfirm(L.confirmRemove.replace('%s', $('#s-name').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_delete_scanner', nonce: scDashboard.nonce, user_id: formEl.user_id.value } })
                .done(function (res) {
                    if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'scanners'; }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
