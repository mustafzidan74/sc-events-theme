<?php
/**
 * Programme — one event's schedule, day by day, edited in a dialog.
 *
 * Replaces the flat schedules list and the separate create/edit pages: an
 * organiser entering a congress programme adds dozens of items in a row, so
 * "Save and add another" keeps the day and hall and starts where the last
 * item ended.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_overview;
$load_wd_overview = true;
$p = $wpdb->prefix;
$today = current_time('Y-m-d');

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, COALESCE(end_date, start_date) AS end_date, COALESCE(end_date, start_date) >= %s AS current
     FROM {$p}sc_events WHERE status IN ('publish', 'draft', 'completed')
     ORDER BY current DESC, start_date ASC",
    $today
));
// Upcoming events first (soonest on top), then past ones newest first.
usort($events, function ($a, $b) {
    if ($a->current !== $b->current) {
        return (int) $b->current - (int) $a->current;
    }
    return $a->current ? strcmp($a->start_date, $b->start_date) : strcmp($b->start_date, $a->start_date);
});

$requested = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
$featured = (int) get_option('sc_featured_event_id', 0);
$event_ids = array_map('intval', wp_list_pluck($events, 'id'));
$selected = in_array($requested, $event_ids, true) ? $requested : (in_array($featured, $event_ids, true) ? $featured : ($event_ids[0] ?? 0));

$halls = $wpdb->get_results("SELECT id, name, capacity, is_active FROM {$p}sc_halls ORDER BY is_active DESC, sort_order, name");
$speakers = $wpdb->get_results("SELECT id, name, title FROM {$p}sc_speakers ORDER BY name");
$event_speakers = array();
foreach ($wpdb->get_results("SELECT event_id, speaker_id FROM {$p}sc_event_speakers") as $row) {
    $event_speakers[(int) $row->event_id][] = (int) $row->speaker_id;
}

$dashboard_url = home_url('/event-manager-dashboard/');
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
            <h1><?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?><span class="w-page-head__count" id="prog-count"></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.programme_subtitle', 'The timetable shown on the event page, day by day and hall by hall.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'halls'); ?>"><i class="fa fa-building-o" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.halls', 'Halls')); ?></a>
            <button type="button" class="btn btn-primary" id="prog-add"<?php disabled(!$selected); ?>><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_item', 'Add item')); ?></button>
        </div>
    </div>

    <?php if (!$events): ?>
        <div class="w-panel"><p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_events_yet', 'No events yet. Create one to start selling tickets.')); ?></p></div>
    <?php else: ?>

    <div class="w-toolbar">
        <label class="sr-only" for="prog-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
        <select class="form-control w-prog__event" id="prog-event">
            <?php foreach ($events as $ev): ?>
                <option value="<?php echo (int) $ev->id; ?>" <?php selected($selected, (int) $ev->id); ?>><?php echo esc_html($ev->title . ' · ' . mysql2date('j M Y', $ev->start_date) . ((int) $ev->current ? '' : ' · ' . sc_t('dashboard_pages.ended', 'Ended'))); ?></option>
            <?php endforeach; ?>
        </select>
        <label class="w-search">
            <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_programme', 'Search titles and speakers')); ?></span>
            <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
            <input type="search" class="form-control" id="prog-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_programme', 'Search titles and speakers')); ?>" autocomplete="off">
        </label>
        <select class="form-control" id="prog-hall" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_halls', 'All halls')); ?>">
            <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_halls', 'All halls')); ?></option>
            <?php foreach ($halls as $h): ?>
                <option value="<?php echo (int) $h->id; ?>"><?php echo esc_html($h->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <p class="w-panel__note" id="prog-sessions-note" hidden></p>

    <div class="w-tabs" role="tablist" id="prog-days" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.days', 'Days')); ?>"></div>
    <div id="prog-body" class="w-prog" aria-live="polite"></div>

    <?php endif; ?>
</div>
</div>

<!-- Item editor -->
<div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-labelledby="item-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="item-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="item-title"><?php echo esc_html(sc_t('dashboard_pages.add_item', 'Add item')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="schedule_id" value="">
                    <input type="hidden" name="event_id" value="">
                    <input type="hidden" name="registration_start" value="">
                    <input type="hidden" name="registration_end" value="">
                    <input type="hidden" name="break_start" value="">
                    <input type="hidden" name="break_end" value="">
                    <div class="w-fields">
                        <div class="w-choice" role="radiogroup" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.item_type', 'Type')); ?>">
                            <?php foreach (array('session' => sc_t('dashboard_pages.type_session', 'Talk or session'), 'break' => sc_t('dashboard_pages.type_break', 'Break'), 'registration' => sc_t('dashboard_pages.type_registration', 'Registration')) as $value => $label): ?>
                            <label class="w-choice__item"><input type="radio" name="type" value="<?php echo esc_attr($value); ?>"<?php echo $value === 'session' ? ' checked' : ''; ?>><span class="w-choice__box"><?php echo esc_html($label); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="w-field">
                            <label for="i-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="i-title" name="title" maxlength="255" required>
                        </div>
                        <div class="w-fields w-fields--4">
                            <div class="w-field">
                                <label for="i-date"><?php echo esc_html(sc_t('dashboard_pages.day', 'Day')); ?><span class="w-req" aria-hidden="true">*</span></label>
                                <input type="date" class="form-control" id="i-date" name="schedule_date" required>
                            </div>
                            <div class="w-field">
                                <label for="i-start"><?php echo esc_html(sc_t('dashboard_pages.starts', 'Starts')); ?><span class="w-req" aria-hidden="true">*</span></label>
                                <input type="time" class="form-control" id="i-start" name="start_time" required>
                            </div>
                            <div class="w-field">
                                <label for="i-end"><?php echo esc_html(sc_t('dashboard_pages.ends', 'Ends')); ?><span class="w-req" aria-hidden="true">*</span></label>
                                <input type="time" class="form-control" id="i-end" name="end_time" required>
                            </div>
                            <div class="w-field">
                                <label for="i-hall"><?php echo esc_html(sc_t('dashboard_pages.hall', 'Hall')); ?></label>
                                <select class="form-control" id="i-hall" name="hall_id">
                                    <option value=""><?php echo esc_html(sc_t('dashboard_pages.no_hall', 'No hall')); ?></option>
                                    <?php foreach ($halls as $h): ?>
                                        <option value="<?php echo (int) $h->id; ?>"><?php echo esc_html($h->name . ((int) $h->is_active ? '' : ' (' . sc_t('dashboard_pages.inactive', 'inactive') . ')')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="w-fields w-fields--2" data-type-show="session">
                            <div class="w-field">
                                <label for="i-speaker"><?php echo esc_html(sc_t('dashboard_pages.speaker', 'Speaker')); ?></label>
                                <select class="form-control" id="i-speaker" name="speaker_id"></select>
                            </div>
                            <div class="w-field" id="i-speaker-name-wrap" hidden>
                                <label for="i-speaker-name"><?php echo esc_html(sc_t('dashboard_pages.speaker_name', 'Speaker name')); ?></label>
                                <input type="text" class="form-control" id="i-speaker-name" name="speaker_name" maxlength="255">
                            </div>
                        </div>
                        <div class="w-field">
                            <label for="i-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="i-description" name="description" rows="3"></textarea>
                        </div>
                        <div class="w-field" style="max-width:160px">
                            <label for="i-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                            <input type="number" class="form-control" id="i-order" name="sort_order" value="0" step="1" inputmode="numeric">
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.order_same_time', 'For items starting at the same time.')); ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-danger mr-auto" id="item-delete" hidden><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="button" class="btn btn-secondary" id="item-save-next"><?php echo esc_html(sc_t('dashboard_pages.save_add_another', 'Save and add another')); ?></button>
                    <button type="submit" class="btn btn-primary" id="item-save"><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var eventsById = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = array('title' => $e->title, 'start' => $e->start_date, 'end' => $e->end_date); return $c; }, array())); ?>;
    var speakers = <?php echo $js(array_map(function ($s) { return array('id' => (int) $s->id, 'name' => $s->name, 'title' => $s->title); }, $speakers)); ?>;
    var eventSpeakers = <?php echo $js($event_speakers); ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'allDays'    => sc_t('dashboard_pages.all_days', 'All days'),
        'dayN'       => sc_t('frontend.day_n', 'Day %d'),
        'empty'      => sc_t('dashboard_pages.no_programme', 'No programme yet for this event.'),
        'emptyHint'  => sc_t('dashboard_pages.no_programme_hint', 'Add talks, breaks and registration times; they appear on the event page grouped by day.'),
        'noMatch'    => sc_t('dashboard_pages.no_programme_match', 'Nothing matches this search.'),
        'addFirst'   => sc_t('dashboard_pages.add_first_item', 'Add the first item'),
        'add'        => sc_t('dashboard_pages.add_item', 'Add item'),
        'edit'       => sc_t('dashboard_pages.edit_item', 'Edit item'),
        'duplicate'  => sc_t('dashboard_pages.duplicate', 'Duplicate'),
        'delete'     => sc_t('dashboard_pages.delete', 'Delete'),
        'break'      => sc_t('dashboard_pages.type_break', 'Break'),
        'registration' => sc_t('dashboard_pages.type_registration', 'Registration'),
        'noHall'     => sc_t('dashboard_pages.no_hall', 'No hall'),
        'noSpeaker'  => sc_t('dashboard_pages.no_speaker', 'No speaker'),
        'eventSpeakers' => sc_t('dashboard_pages.this_events_speakers', 'This event’s speakers'),
        'otherSpeakers' => sc_t('dashboard_pages.other_speakers', 'Other speakers'),
        'someoneElse' => sc_t('dashboard_pages.someone_else', 'Someone else (type the name)'),
        'sessionsNote' => sc_t('dashboard_pages.sessions_override', 'This event has %d published sessions, so its page shows those sessions instead of the programme below.'),
        'outsideEvent' => sc_t('dashboard_pages.outside_event_days', 'This day is outside the event’s dates.'),
        'errTitle'   => sc_t('dashboard_pages.err_title_short', 'Title is required.'),
        'errDate'    => sc_t('dashboard_pages.err_day', 'Pick the day.'),
        'errStart'   => sc_t('dashboard_pages.err_start_time', 'Start time is required.'),
        'errEnd'     => sc_t('dashboard_pages.err_end_time', 'Ends before it starts. Check the end time.'),
        'saved'      => sc_t('dashboard_pages.saved', 'Saved.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_item', 'Delete “%s” from the programme?'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'items'      => sc_t('dashboard_pages.n_items', '%d items'),
    )); ?>;

    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var post = function (data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); };
    var fmtDay = function (d) { var x = new Date(d + 'T00:00:00'); return isNaN(x) ? d : x.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); };

    var $event = $('#prog-event'), $body = $('#prog-body'), $days = $('#prog-days');
    var items = [], day = '', eventId = +$event.val() || 0;
    var pendingEdit = +(new URLSearchParams(location.search).get('edit') || 0);
    try { day = sessionStorage.getItem('scProgDay:' + eventId) || ''; } catch (x) { /* storage blocked */ }

    function load() {
        if (!eventId) { return; }
        $body.attr('aria-busy', 'true');
        post({ action: 'sc_get_programme', event_id: eventId }).done(function (res) {
            $body.attr('aria-busy', 'false');
            if (!res.success) { $body.html('<div class="w-panel"><p class="w-panel__empty">' + esc(res.data && res.data.message || L.failed) + '</p></div>'); return; }
            items = res.data.items;
            var n = res.data.published_sessions;
            $('#prog-sessions-note').prop('hidden', !n).text(n ? L.sessionsNote.replace('%d', n) : '');
            render();
            if (pendingEdit) {
                var it = find(pendingEdit);
                pendingEdit = 0;
                if (it) { day = it.schedule_date; render(); open(it); }
            }
        }).fail(function () { $body.attr('aria-busy', 'false'); showError(L.failed); });
    }

    function filtered() {
        var q = $.trim($('#prog-search').val()).toLowerCase(), hall = $('#prog-hall').val();
        return items.filter(function (it) {
            return (!hall || String(it.hall_id) === hall) && (!q || (it.title + ' ' + (it.speaker || '')).toLowerCase().indexOf(q) !== -1);
        });
    }

    function render() {
        $('#prog-count').text(items.length ? items.length : '');
        var list = filtered();
        var dates = [];
        items.forEach(function (it) { if (dates.indexOf(it.schedule_date) === -1) { dates.push(it.schedule_date); } });
        dates.sort();
        if (day && dates.indexOf(day) === -1) { day = ''; }

        $days.html(dates.length > 1 ? ['<button type="button" role="tab" class="w-tab" data-day="" aria-selected="' + (!day) + '">' + esc(L.allDays) + '</button>'].concat(dates.map(function (d, i) {
            var count = items.filter(function (it) { return it.schedule_date === d; }).length;
            return '<button type="button" role="tab" class="w-tab" data-day="' + esc(d) + '" aria-selected="' + (day === d) + '">' + esc(L.dayN.replace('%d', i + 1) + ' · ' + fmtDay(d)) + '<span class="w-tab__count">' + count + '</span></button>';
        })).join('') : '').prop('hidden', dates.length <= 1);

        if (!items.length) {
            $body.html('<div class="w-state"><p class="w-state__title">' + esc(L.empty) + '</p><p class="w-state__text">' + esc(L.emptyHint) + '</p><div class="w-state__actions"><button type="button" class="btn btn-primary" data-add>' + esc(L.addFirst) + '</button></div></div>');
            return;
        }
        var shown = list.filter(function (it) { return !day || it.schedule_date === day; });
        if (!shown.length) {
            $body.html('<div class="w-state"><p class="w-state__title">' + esc(L.noMatch) + '</p></div>');
            return;
        }

        var html = '', lastDate = null;
        var byDate = {};
        shown.forEach(function (it) { (byDate[it.schedule_date] = byDate[it.schedule_date] || []).push(it); });
        Object.keys(byDate).sort().forEach(function (d) {
            if (!day) { html += '<h2 class="w-prog__day">' + esc(fmtDay(d)) + '</h2>'; }
            var slots = {};
            byDate[d].forEach(function (it) { (slots[it.start_time] = slots[it.start_time] || []).push(it); });
            Object.keys(slots).sort().forEach(function (time) {
                html += '<div class="w-prog__slot"><span class="w-prog__time w-ltr">' + esc(time) + '</span><div class="w-prog__items">' +
                    slots[time].map(card).join('') + '</div></div>';
            });
        });
        $body.html(html);
    }

    function card(it) {
        var type = it.type === 'break' ? '<span class="w-tag">' + esc(L.break) + '</span>' : it.type === 'registration' ? '<span class="w-tag w-tag--gold">' + esc(L.registration) + '</span>' : '';
        return '<article class="w-prog__item' + (it.type === 'break' ? ' is-break' : '') + '" data-id="' + it.id + '">' +
            '<div class="w-prog__meta"><span class="w-ltr">' + esc(it.start_time + '–' + it.end_time) + '</span>' + (it.hall_name ? '<span>' + esc(it.hall_name) + '</span>' : '') + type + '</div>' +
            '<button type="button" class="w-prog__title" data-edit="' + it.id + '">' + esc(it.title) + '</button>' +
            (it.speaker ? '<span class="w-sub">' + esc(it.speaker) + '</span>' : '') +
            '<div class="w-prog__actions">' +
            '<button type="button" class="w-icon-btn" data-dup="' + it.id + '" aria-label="' + esc(L.duplicate) + '" title="' + esc(L.duplicate) + '"><i class="fa fa-clone" aria-hidden="true"></i></button>' +
            '<button type="button" class="w-icon-btn w-icon-btn--danger" data-del="' + it.id + '" aria-label="' + esc(L.delete) + '" title="' + esc(L.delete) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
            '</div></article>';
    }

    /* --------------------------------------------------------------- editor */

    var $modal = $('#itemModal'), form = document.getElementById('item-form');
    var find = function (id) { return items.filter(function (it) { return it.id === +id; })[0]; };

    function speakerOptions(selectedId) {
        var mine = eventSpeakers[eventId] || [];
        var inEvent = speakers.filter(function (s) { return mine.indexOf(s.id) !== -1; });
        var others = speakers.filter(function (s) { return mine.indexOf(s.id) === -1; });
        var opt = function (s) { return '<option value="' + s.id + '"' + (s.id === selectedId ? ' selected' : '') + '>' + esc(s.name) + '</option>'; };
        return '<option value="">' + esc(L.noSpeaker) + '</option>' +
            (inEvent.length ? '<optgroup label="' + esc(L.eventSpeakers) + '">' + inEvent.map(opt).join('') + '</optgroup>' : '') +
            (others.length ? '<optgroup label="' + esc(L.otherSpeakers) + '">' + others.map(opt).join('') + '</optgroup>' : '') +
            '<option value="other">' + esc(L.someoneElse) + '</option>';
    }
    function syncSpeaker() {
        $('#i-speaker-name-wrap').prop('hidden', $('#i-speaker').val() !== 'other');
    }
    function syncType() {
        var type = $(form).find('[name="type"]:checked').val();
        $(form).find('[data-type-show]').prop('hidden', type !== 'session');
    }
    function clearErrors() {
        $(form).find('.has-error').removeClass('has-error');
        $(form).find('.w-field__error').remove();
    }
    function fieldError(name, message) {
        var $f = $(form).find('[name="' + name + '"]').closest('.w-field').addClass('has-error');
        $f.append($('<p class="w-field__error">').text(message));
    }

    function open(it, asCopy, defaults) {
        form.reset();
        clearErrors();
        var ev = eventsById[eventId];
        form.event_id.value = eventId;
        form.schedule_id.value = it && !asCopy ? it.id : '';
        $('#item-title').text(it && !asCopy ? L.edit : L.add);
        $('#item-delete').prop('hidden', !(it && !asCopy));
        var src = it || defaults || {};
        $(form).find('[name="type"][value="' + (src.type || 'session') + '"]').prop('checked', true);
        form.title.value = it ? it.title : '';
        form.schedule_date.value = src.schedule_date || day || (ev ? ev.start : '');
        form.start_time.value = src.start_time || '';
        form.end_time.value = it ? it.end_time : '';
        form.hall_id.value = src.hall_id || '';
        $('#i-speaker').html(speakerOptions(it && it.speaker_id ? it.speaker_id : null));
        if (it && !it.speaker_id && it.speaker_name) {
            $('#i-speaker').val('other');
            form.speaker_name.value = it.speaker_name;
        }
        form.description.value = it ? it.description || '' : '';
        form.sort_order.value = it ? it.sort_order || 0 : 0;
        ['registration_start', 'registration_end', 'break_start', 'break_end'].forEach(function (k) { form[k].value = it ? it[k] || '' : ''; });
        if (ev) { form.schedule_date.min = ev.start; form.schedule_date.max = ev.end; }
        syncSpeaker();
        syncType();
        $modal.modal('show');
    }
    $modal.on('shown.bs.modal', function () { form.title.focus(); });
    $('#i-speaker').on('change', syncSpeaker);
    $(form).on('change', '[name="type"]', syncType);

    function save(next) {
        clearErrors();
        var bad = false;
        if (!$.trim(form.title.value)) { fieldError('title', L.errTitle); bad = true; }
        if (!form.schedule_date.value) { fieldError('schedule_date', L.errDate); bad = true; }
        if (!form.start_time.value) { fieldError('start_time', L.errStart); bad = true; }
        if (!form.end_time.value || (form.start_time.value && form.end_time.value <= form.start_time.value)) { fieldError('end_time', L.errEnd); bad = true; }
        if (bad) { $(form).find('.has-error input, .has-error select').first().focus(); return; }

        var data = $(form).serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {});
        if ($(form).find('[name="type"]:checked').val() !== 'session') { data.speaker_id = ''; data.speaker_name = ''; }
        if (data.speaker_id !== 'other') { data.speaker_name = ''; }
        var $buttons = $('#item-save, #item-save-next').prop('disabled', true);
        post($.extend({ action: 'sc_save_schedule' }, data)).done(function (res) {
            $buttons.prop('disabled', false);
            if (!res.success) {
                var errs = res.data && res.data.errors || {};
                Object.keys(errs).forEach(function (k) { fieldError(k, errs[k]); });
                if (!Object.keys(errs).length) { showError(res.data && res.data.message || L.failed); }
                return;
            }
            if (window.toastr) { toastr.success(L.saved); }
            try { sessionStorage.setItem('scProgDay:' + eventId, data.schedule_date); } catch (x) { /* storage blocked */ }
            day = $days.find('[data-day]').length ? data.schedule_date : day;
            if (next) {
                // Same day, hall and type; start where this one ended.
                open(null, false, { schedule_date: data.schedule_date, hall_id: data.hall_id, type: data.type, start_time: data.end_time });
            } else {
                $modal.modal('hide');
            }
            load();
        }).fail(function () { $buttons.prop('disabled', false); showError(L.failed); });
    }
    $(form).on('submit', function (e) { e.preventDefault(); save(false); });
    $('#item-save-next').on('click', function () { save(true); });

    function remove(it) {
        showDeleteConfirm(L.confirmDelete.replace('%s', it.title)).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_delete_schedule', schedule_id: it.id }).done(function (res) {
                if (res.success) { $modal.modal('hide'); load(); } else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }
    $('#item-delete').on('click', function () { var it = find(form.schedule_id.value); if (it) { remove(it); } });

    /* --------------------------------------------------------------- events */

    $('#prog-add').on('click', function () { open(null); });
    $body.on('click', '[data-add]', function () { open(null); });
    $body.on('click', '[data-edit]', function () { open(find(this.getAttribute('data-edit'))); });
    $body.on('click', '[data-dup]', function () { open(find(this.getAttribute('data-dup')), true); });
    $body.on('click', '[data-del]', function () { remove(find(this.getAttribute('data-del'))); });
    $days.on('click', '[data-day]', function () {
        day = this.getAttribute('data-day');
        try { sessionStorage.setItem('scProgDay:' + eventId, day); } catch (x) { /* storage blocked */ }
        render();
    });
    $('#prog-search').on('input', render);
    $('#prog-hall').on('change', render);
    $event.on('change', function () {
        eventId = +this.value;
        day = '';
        try { day = sessionStorage.getItem('scProgDay:' + eventId) || ''; } catch (x) { /* storage blocked */ }
        var url = new URL(window.location.href);
        url.searchParams.set('event_id', eventId);
        history.replaceState(null, '', url.pathname + url.search);
        load();
    });

    load();
    // Deep links from the old create/edit pages.
    var params = new URLSearchParams(location.search);
    if (params.get('add') === '1') { open(null); }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
