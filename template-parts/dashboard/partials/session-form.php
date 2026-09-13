<?php
/**
 * Session form — shared by session-create.php and session-edit.php.
 *
 * Posts to sc_save_session. Speakers are a small repeater (speaker, role,
 * talk title) sent as speakers[i][…] with a speakers_present marker so an
 * emptied list clears.
 *
 * Expects: $session (object|null), $preselect_event_id (int).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($session);
$dashboard_url = home_url('/event-manager-dashboard/');
$s = $is_edit ? $session : (object) array(
    'id' => 0, 'event_id' => (int) ($preselect_event_id ?? 0), 'title' => '', 'description' => '', 'session_type' => 'lecture', 'track' => '',
    'session_date' => '', 'start_time' => '', 'end_time' => '', 'hall_name' => '', 'capacity' => 0, 'status' => 'draft', 'sort_order' => 0,
    'cme_hours' => 0, 'cme_category' => '', 'enable_certificate' => 0, 'certificate_template_id' => 0, 'min_attendance_percentage' => 80,
    'require_registration' => 0, 'updated_at' => '',
);
$clock = function ($value) {
    return preg_match('/(\d{1,2}:\d{2})(?::\d{2})?$/', trim((string) $value), $m) ? str_pad($m[1], 5, '0', STR_PAD_LEFT) : '';
};

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, COALESCE(end_date, start_date) AS end_date FROM {$p}sc_events
     WHERE status IN ('publish', 'draft', 'completed') OR id = %d ORDER BY start_date DESC LIMIT 200",
    (int) $s->event_id
));
$halls = $wpdb->get_col("SELECT name FROM {$p}sc_halls ORDER BY is_active DESC, sort_order, name");
if ($s->hall_name && !in_array($s->hall_name, $halls, true)) {
    array_unshift($halls, $s->hall_name);
}
$speakers = $wpdb->get_results("SELECT id, name FROM {$p}sc_speakers ORDER BY name");
$event_speakers = array();
foreach ($wpdb->get_results("SELECT event_id, speaker_id FROM {$p}sc_event_speakers") as $row) {
    $event_speakers[(int) $row->event_id][] = (int) $row->speaker_id;
}
$session_speakers = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT speaker_id, role, presentation_title FROM {$p}sc_session_speakers WHERE session_id = %d ORDER BY sort_order, id",
    (int) $s->id
)) : array();
$templates = class_exists('SC_Certificate_Template') ? SC_Certificate_Template::get_all(array('is_active' => 1, 'limit' => 200)) : array();
$stats = $is_edit ? $wpdb->get_row($wpdb->prepare(
    "SELECT (SELECT COUNT(*) FROM {$p}sc_session_registrations WHERE session_id = %d AND status <> 'cancelled') AS registered,
            (SELECT COUNT(*) FROM {$p}sc_session_attendance WHERE session_id = %d AND check_in_time IS NOT NULL) AS attended",
    (int) $s->id,
    (int) $s->id
)) : null;

$types = array(
    'lecture' => 'Lecture', 'keynote' => 'Keynote', 'panel' => 'Panel', 'symposium' => 'Symposium', 'workshop' => 'Workshop', 'hands_on' => 'Hands-on',
    'poster' => 'Poster', 'exhibition' => 'Exhibition', 'networking' => 'Networking', 'break' => 'Break', 'other' => 'Other',
);
$statuses = array(
    'draft'     => array(sc_t('dashboard_pages.status_draft', 'Draft'), sc_t('dashboard_pages.session_draft_help', 'Not on the event page, no check-in')),
    'published' => array(sc_t('dashboard_pages.status_published', 'Published'), sc_t('dashboard_pages.session_published_help', 'On the event page, open for check-in')),
    'live'      => array(sc_t('dashboard_pages.status_live', 'Live'), sc_t('dashboard_pages.session_live_help', 'Happening now')),
    'ended'     => array(sc_t('dashboard_pages.ended', 'Ended'), sc_t('dashboard_pages.session_ended_help', 'Over; kept for records')),
    'cancelled' => array(sc_t('dashboard_pages.status_cancelled', 'Cancelled'), sc_t('dashboard_pages.session_cancelled_help', 'Hidden')),
);
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_session', 'Create session');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="session-form" novalidate>
    <input type="hidden" name="session_id" value="<?php echo (int) $s->id; ?>">
    <input type="hidden" name="speakers_present" value="1">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.session', 'Session') . ' · ID ' . (int) $s->id : sc_t('dashboard_pages.new_session', 'New session')); ?></span>
            <h1 data-w-title><?php echo esc_html($s->title !== '' ? $s->title : sc_t('dashboard_pages.new_session', 'New session')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html(sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $s->updated_at))); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <?php if ($is_edit): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'session-attendees?id=' . (int) $s->id); ?>"><i class="fa fa-users" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?> <span class="w-ltr">(<?php echo (int) $stats->registered; ?>)</span></a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'sessions'); ?>"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="ses-basic">
                <div class="w-section__head"><h2 id="ses-basic"><?php echo esc_html(sc_t('dashboard_pages.section_basic', 'Basic information')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="se-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="se-event" name="event_id" required>
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" data-start="<?php echo esc_attr($ev->start_date); ?>" data-end="<?php echo esc_attr($ev->end_date); ?>" <?php selected((int) $s->event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title . ' · ' . mysql2date('j M Y', $ev->start_date)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="se-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="se-title" name="title" value="<?php echo esc_attr($s->title); ?>" maxlength="255" required>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="se-type"><?php echo esc_html(sc_t('dashboard_pages.session_type', 'Type')); ?></label>
                            <select class="form-control" id="se-type" name="session_type">
                                <?php foreach ($types as $k => $label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($s->session_type, $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-field">
                            <label for="se-track"><?php echo esc_html(sc_t('dashboard_pages.track', 'Track')); ?></label>
                            <input type="text" class="form-control" id="se-track" name="track" value="<?php echo esc_attr($s->track); ?>" maxlength="100">
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="se-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                        <textarea class="form-control" id="se-description" name="description" rows="4"><?php echo esc_textarea($s->description); ?></textarea>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="ses-when">
                <div class="w-section__head"><h2 id="ses-when"><?php echo esc_html(sc_t('dashboard_pages.when_where', 'When & where')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--4">
                        <div class="w-field">
                            <label for="se-date"><?php echo esc_html(sc_t('dashboard_pages.day', 'Day')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="se-date" name="session_date" value="<?php echo esc_attr($s->session_date); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="se-start"><?php echo esc_html(sc_t('dashboard_pages.starts', 'Starts')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="time" class="form-control" id="se-start" name="start_time" value="<?php echo esc_attr($clock($s->start_time)); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="se-end"><?php echo esc_html(sc_t('dashboard_pages.ends', 'Ends')); ?></label>
                            <input type="time" class="form-control" id="se-end" name="end_time" value="<?php echo esc_attr($clock($s->end_time)); ?>">
                        </div>
                        <div class="w-field">
                            <label for="se-capacity"><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></label>
                            <input type="number" class="form-control" id="se-capacity" name="capacity" value="<?php echo (int) $s->capacity ?: ''; ?>" min="0" step="1" inputmode="numeric" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.unlimited_cap', 'Unlimited')); ?>">
                        </div>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="se-hall"><?php echo esc_html(sc_t('dashboard_pages.hall', 'Hall')); ?></label>
                            <select class="form-control" id="se-hall" name="hall_name">
                                <option value=""><?php echo esc_html(sc_t('dashboard_pages.no_hall', 'No hall')); ?></option>
                                <?php foreach ($halls as $h): ?><option value="<?php echo esc_attr($h); ?>" <?php selected($s->hall_name, $h); ?>><?php echo esc_html($h); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-field">
                            <label for="se-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                            <input type="number" class="form-control" id="se-order" name="sort_order" value="<?php echo (int) $s->sort_order; ?>" step="1" inputmode="numeric">
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="ses-speakers">
                <div class="w-section__head">
                    <h2 id="ses-speakers"><?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></h2>
                    <button type="button" class="btn btn-sm btn-secondary" id="add-speaker-row"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_speaker', 'Add speaker')); ?></button>
                </div>
                <div class="w-repeat" id="speaker-rows"></div>
            </section>

            <section class="w-section" aria-labelledby="ses-cme">
                <div class="w-section__head">
                    <h2 id="ses-cme"><?php echo esc_html(sc_t('dashboard_pages.cme_certificates', 'CME & certificate')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.cme_hint', 'Attendance comes from session check-in and check-out')); ?></span>
                </div>
                <div class="w-fields">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="se-cme"><?php echo esc_html(sc_t('dashboard_pages.cme_hours', 'CME hours')); ?></label>
                            <input type="number" class="form-control" id="se-cme" name="cme_hours" value="<?php echo esc_attr((float) $s->cme_hours ?: ''); ?>" min="0" step="0.25" inputmode="decimal">
                        </div>
                        <div class="w-field">
                            <label for="se-cme-cat"><?php echo esc_html(sc_t('dashboard_pages.cme_category', 'CME category')); ?></label>
                            <input type="text" class="form-control" id="se-cme-cat" name="cme_category" value="<?php echo esc_attr((string) $s->cme_category); ?>" maxlength="100">
                        </div>
                    </div>
                    <div class="w-field" style="max-width:260px">
                        <label for="se-min"><?php echo esc_html(sc_t('dashboard_pages.min_attendance', 'Minimum attendance (%)')); ?></label>
                        <input type="number" class="form-control" id="se-min" name="min_attendance_percentage" value="<?php echo esc_attr((float) $s->min_attendance_percentage); ?>" min="0" max="100" step="1" inputmode="numeric">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.min_attendance_help', 'Share of the session someone must attend to earn CME and the certificate.')); ?></p>
                    </div>
                    <input type="hidden" name="enable_certificate" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="enable_certificate" value="1" <?php checked((int) $s->enable_certificate, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.session_certificate', 'Certificate for this session')); ?></strong></span>
                    </label>
                    <?php if ($templates): ?>
                    <div class="w-field" data-w-show-if="enable_certificate:1">
                        <label for="se-template"><?php echo esc_html(sc_t('dashboard_pages.certificate_template', 'Template')); ?></label>
                        <select class="form-control" id="se-template" name="certificate_template_id">
                            <option value="0"><?php echo esc_html(sc_t('dashboard_pages.default_template', 'Default template')); ?></option>
                            <?php foreach ($templates as $tpl): ?><option value="<?php echo (int) $tpl->id; ?>" <?php selected((int) $s->certificate_template_id, (int) $tpl->id); ?>><?php echo esc_html($tpl->name); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" name="require_registration" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="require_registration" value="1" <?php checked((int) $s->require_registration, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.require_session_registration', 'Only people registered for this session')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.require_session_registration_help', 'Off: anyone with a ticket for the event can be checked in.')); ?></span></span>
                    </label>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title" id="se-status-title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup" aria-labelledby="se-status-title">
                    <?php foreach ($statuses as $value => $info): ?>
                    <label class="w-choice__item">
                        <input type="radio" name="status" value="<?php echo esc_attr($value); ?>" <?php checked($s->status ?: 'draft', $value); ?>>
                        <span class="w-choice__box"><span><?php echo esc_html($info[0]); ?><span class="w-choice__sub"><?php echo esc_html($info[1]); ?></span></span></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.statistics', 'Statistics')); ?></span>
                <div class="w-stats">
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo esc_html(number_format_i18n((int) $stats->registered)); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo esc_html(number_format_i18n((int) $stats->attended)); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.attended', 'Attended')); ?></span></span>
                </div>
            </div>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_session', 'Delete session')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_session_help', 'Removes its registrations, check-ins and speaker links.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-session-btn"><?php echo esc_html(sc_t('dashboard_pages.delete_session', 'Delete session')); ?></button>
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
    var sessionId = <?php echo (int) $s->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var speakers = <?php echo $js(array_map(function ($x) { return array('id' => (int) $x->id, 'name' => $x->name); }, $speakers)); ?>;
    var eventSpeakers = <?php echo $js($event_speakers); ?>;
    var rows = <?php echo $js(array_map(function ($x) { return array('id' => (int) $x->speaker_id, 'role' => $x->role ?: 'speaker', 'presentation_title' => (string) $x->presentation_title, 'custom_name' => ''); }, $session_speakers)); ?>;
    var L = <?php echo $js(array(
        'roles'      => array('speaker' => 'Speaker', 'moderator' => 'Moderator', 'panelist' => 'Panelist', 'chair' => 'Chair', 'instructor' => 'Instructor'),
        'speaker'    => sc_t('dashboard_pages.speaker', 'Speaker'),
        'role'       => sc_t('dashboard_pages.role', 'Role'),
        'talk'       => sc_t('dashboard_pages.talk_title', 'Talk title'),
        'choose'     => sc_t('dashboard_pages.choose_speaker', '— Choose —'),
        'eventSpeakers' => sc_t('dashboard_pages.this_events_speakers', 'This event’s speakers'),
        'otherSpeakers' => sc_t('dashboard_pages.other_speakers', 'Other speakers'),
        'newSpeaker' => sc_t('dashboard_pages.new_speaker_named', 'New speaker (type the name)'),
        'newName'    => sc_t('dashboard_pages.speaker_name', 'Speaker name'),
        'missing'    => sc_t('dashboard_pages.missing_speaker', 'Deleted speaker #%d'),
        'remove'     => sc_t('dashboard_pages.remove', 'Remove'),
        'none'       => sc_t('dashboard_pages.no_session_speakers', 'No speakers yet.'),
        'errTitle'   => sc_t('dashboard_pages.err_title_short', 'Title is required.'),
        'errEvent'   => sc_t('dashboard_pages.err_event', 'Choose the event.'),
        'errDate'    => sc_t('dashboard_pages.err_day', 'Pick the day.'),
        'errStart'   => sc_t('dashboard_pages.err_start_time', 'Start time is required.'),
        'errEnd'     => sc_t('dashboard_pages.err_end_time', 'Ends before it starts. Check the end time.'),
        'outside'    => sc_t('dashboard_pages.outside_event_days', 'This day is outside the event’s dates.'),
        'untitled'   => sc_t('dashboard_pages.new_session', 'New session'),
        'saved'      => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'    => sc_t('dashboard_pages.session_created', 'Session created.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_session', 'Delete “%s”? Its registrations and check-ins are deleted too.'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'     => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'  => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'      => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'savedAt'    => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var formEl = document.getElementById('session-form');
    var $rows = $('#speaker-rows');

    function speakerOptions(selected) {
        var eventId = +$('#se-event').val() || 0;
        var mine = eventSpeakers[eventId] || [];
        var opt = function (x) { return '<option value="' + x.id + '"' + (x.id === selected ? ' selected' : '') + '>' + esc(x.name) + '</option>'; };
        var inEvent = speakers.filter(function (x) { return mine.indexOf(x.id) !== -1; });
        var others = speakers.filter(function (x) { return mine.indexOf(x.id) === -1; });
        // A linked speaker that no longer exists still shows, so saving doesn't silently drop the link.
        var missing = typeof selected === 'number' && selected > 0 && !speakers.some(function (x) { return x.id === selected; });
        return '<option value="">' + esc(L.choose) + '</option>' +
            (missing ? '<option value="' + selected + '" selected>' + esc(L.missing.replace('%d', selected)) + '</option>' : '') +
            (inEvent.length ? '<optgroup label="' + esc(L.eventSpeakers) + '">' + inEvent.map(opt).join('') + '</optgroup>' : '') +
            (others.length ? '<optgroup label="' + esc(L.otherSpeakers) + '">' + others.map(opt).join('') + '</optgroup>' : '') +
            '<option value="new"' + (selected === 'new' ? ' selected' : '') + '>' + esc(L.newSpeaker) + '</option>';
    }

    // Rows are real inputs named speakers[i][…], so the form's FormData and dirty tracking see them.
    function renderRows() {
        if (!rows.length) {
            $rows.html('<p class="w-repeat__empty">' + esc(L.none) + '</p>');
        } else {
            $rows.html(rows.map(function (r, i) {
                var roles = Object.keys(L.roles).map(function (k) { return '<option value="' + k + '"' + (r.role === k ? ' selected' : '') + '>' + esc(L.roles[k]) + '</option>'; }).join('');
                if (!L.roles[r.role]) { roles += '<option value="' + esc(r.role) + '" selected>' + esc(r.role) + '</option>'; }
                var isNew = r.id === 'new';
                return '<div class="w-repeat__item" data-i="' + i + '"><div class="w-repeat__body">' +
                    '<div class="w-fields w-fields--speaker">' +
                    '<div class="w-field"><label for="sr-sp-' + i + '">' + esc(L.speaker) + '</label><select class="form-control" id="sr-sp-' + i + '" data-k="id">' + speakerOptions(r.id) + '</select></div>' +
                    '<div class="w-field"><label for="sr-role-' + i + '">' + esc(L.role) + '</label><select class="form-control" id="sr-role-' + i + '" name="speakers[' + i + '][role]" data-k="role">' + roles + '</select></div>' +
                    '<button type="button" class="w-icon-btn w-icon-btn--danger" data-remove="' + i + '" aria-label="' + esc(L.remove) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                    '</div>' +
                    '<input type="hidden" name="speakers[' + i + '][id]" value="' + (isNew ? '' : esc(r.id || '')) + '">' +
                    '<input type="hidden" name="speakers[' + i + '][sort_order]" value="' + i + '">' +
                    (isNew ? '<div class="w-field"><label for="sr-new-' + i + '">' + esc(L.newName) + '</label><input type="text" class="form-control" id="sr-new-' + i + '" name="speakers[' + i + '][custom_name]" data-k="custom_name" value="' + esc(r.custom_name) + '"></div>' : '') +
                    '<div class="w-field"><label for="sr-talk-' + i + '">' + esc(L.talk) + '</label><input type="text" class="form-control" id="sr-talk-' + i + '" name="speakers[' + i + '][presentation_title]" data-k="presentation_title" value="' + esc(r.presentation_title) + '"></div>' +
                    '</div></div>';
            }).join(''));
        }
    }
    $rows.on('change input', '[data-k]', function () {
        var i = +$(this).closest('[data-i]').attr('data-i');
        var k = this.getAttribute('data-k');
        var v = this.value;
        rows[i][k] = k === 'id' ? (v === 'new' ? 'new' : (+v || 0)) : v;
        if (k === 'id') { renderRows(); $rows.find('#sr-' + (v === 'new' ? 'new-' : 'sp-') + i).focus(); }
    });
    $rows.on('click', '[data-remove]', function () {
        rows.splice(+this.getAttribute('data-remove'), 1);
        renderRows();
        formEl.dispatchEvent(new Event('change', { bubbles: true }));
    });
    $('#add-speaker-row').on('click', function () {
        rows.push({ id: 0, role: 'speaker', presentation_title: '', custom_name: '' });
        renderRows();
        $rows.find('select[data-k="id"]').last().focus();
        formEl.dispatchEvent(new Event('change', { bubbles: true }));
    });
    $('#se-event').on('change', function () {
        renderRows();
        var o = this.selectedOptions[0];
        if (o && o.getAttribute('data-start')) { $('#se-date').attr({ min: o.getAttribute('data-start'), max: o.getAttribute('data-end') }); }
    }).trigger('change');
    renderRows();

    $('#se-title').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave, savedJustNow: L.savedAt },
        validate: function (v) {
            var e = [];
            if (!v.event_id) { e.push({ field: 'event_id', message: L.errEvent }); }
            if (!$.trim(v.title)) { e.push({ field: 'title', message: L.errTitle }); }
            if (!v.session_date) { e.push({ field: 'session_date', message: L.errDate }); }
            else {
                var o = $('#se-event')[0].selectedOptions[0];
                if (o && o.getAttribute('data-start') && (v.session_date < o.getAttribute('data-start') || v.session_date > o.getAttribute('data-end'))) { e.push({ field: 'session_date', message: L.outside }); }
            }
            if (!v.start_time) { e.push({ field: 'start_time', message: L.errStart }); }
            if (v.end_time && v.start_time && v.end_time <= v.start_time) { e.push({ field: 'end_time', message: L.errEnd }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_session');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            if (!isEdit && data.redirect) { api.markClean(); window.location.href = data.redirect; return; }
            // New speakers were created server-side; reload to pick up their ids.
            if (rows.some(function (r) { return r.id === 'new'; })) { api.markClean(); window.location.reload(); return; }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    $('#delete-session-btn').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#se-title').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_delete_session', nonce: scDashboard.nonce, session_id: sessionId } })
                .done(function (res) { if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'sessions'; } else { showError(res.data && res.data.message || L.failed); } })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
