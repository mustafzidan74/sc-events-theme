<?php
/**
 * Scanner — check people in at the door by QR code, ticket code or phone.
 *
 * Handlers: sc_scan_and_checkin (attendance-ajax-handlers.php), sc_session_checkin
 * (sessions-ajax-handlers.php), sc_search_attendee_by_phone (attendees-ajax-handlers.php),
 * sc_venues_get_gates_by_event and sc_sessions_get_by_event.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Scanner staff see a bare frame and only the events they are assigned to.
$is_scanner_only = SC_Event_Manager_Dashboard::is_event_scanner() && !SC_Event_Manager_Dashboard::is_event_manager();

if ($is_scanner_only && get_user_meta(get_current_user_id(), 'sc_scanner_type', true) === 'company') {
    wp_die(__('You only have access to the Company Scanner. Please use the Company Scanner page.', 'sc_events'));
}

$scanner_allowed_event_ids = array();
$scanner_allowed_session_ids = array();
$is_full_scanner_access = true;

if ($is_scanner_only) {
    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $perms_table))) {
        $scanner_perms = $wpdb->get_results($wpdb->prepare("SELECT * FROM $perms_table WHERE user_id = %d", get_current_user_id()));
        if (!empty($scanner_perms)) {
            $is_full_scanner_access = (count($scanner_perms) === 1 && $scanner_perms[0]->access_type === 'full');
            if (!$is_full_scanner_access) {
                foreach ($scanner_perms as $perm) {
                    if ($perm->event_id) { $scanner_allowed_event_ids[] = (int) $perm->event_id; }
                    if ($perm->session_id) { $scanner_allowed_session_ids[] = (int) $perm->session_id; }
                }
                $scanner_allowed_event_ids = array_values(array_unique($scanner_allowed_event_ids));
            }
        }
    }
}

$url_event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$sessions_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions');
$venues_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('venues');

// Completed events stay listed so late arrivals can still be marked.
$all_events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed'),
    'limit'   => 1000,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

$filtered_events = array();
foreach ($all_events as $event) {
    if ($is_scanner_only && !$is_full_scanner_access && !in_array((int) $event->id, $scanner_allowed_event_ids, true)) {
        continue;
    }
    $filtered_events[] = $event;
}

// No event asked for: start on the featured event when it is in the list.
if (!$url_event_id && count($filtered_events) > 1) {
    $featured = (int) get_option('sc_featured_event_id', 0);
    if ($featured && in_array($featured, array_map('intval', wp_list_pluck($filtered_events, 'id')), true)) {
        $url_event_id = $featured;
    }
}
if (count($filtered_events) === 1) {
    $url_event_id = (int) $filtered_events[0]->id;
}

// Workshops of the listed events: scanning at a workshop door accepts only that workshop's tickets.
$scanner_workshops = array();
if ($filtered_events) {
    global $wpdb;
    $ids = array_map('intval', wp_list_pluck($filtered_events, 'id'));
    $ws_rows = $wpdb->get_results($wpdb->prepare(
        'SELECT id, event_id, title FROM ' . $wpdb->prefix . 'sc_workshops WHERE event_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ") AND status <> 'cancelled' ORDER BY start_date, start_time, title",
        $ids
    ));
    foreach ($ws_rows as $ws) {
        $scanner_workshops[(int) $ws->event_id][] = array('id' => (int) $ws->id, 'title' => $ws->title);
    }
}

$i18n = array(
    'choose_event'      => sc_t('scanner.choose_event', 'Choose the event you are scanning for.'),
    'start_camera'      => sc_t('scanner.start_camera', 'Start camera'),
    'camera_idle'       => sc_t('scanner.camera_idle', 'Camera is off'),
    'camera_on'         => sc_t('scanner.camera_on', 'Scanning — hold the QR code inside the square'),
    'camera_starting'   => sc_t('scanner.camera_starting', 'Starting camera…'),
    'camera_stopped'    => sc_t('scanner.camera_stopped', 'Camera stopped'),
    'camera_error'      => sc_t('scanner.camera_error', 'The camera could not start. Allow camera access for this site, or type the ticket code below.'),
    'checking'          => sc_t('scanner.checking', 'Checking…'),
    'enter_code'        => sc_t('scanner.enter_code', 'Type the ticket code first.'),
    'enter_phone'       => sc_t('scanner.enter_phone', 'Type at least 4 digits of the phone number.'),
    'code_placeholder'  => sc_t('scanner.code_placeholder', 'Ticket or badge code'),
    'phone_placeholder' => sc_t('scanner.phone_placeholder', 'Phone number'),
    'checked_in'        => sc_t('scanner.checked_in', 'Checked in'),
    'checked_out'       => sc_t('scanner.checked_out', 'Checked out'),
    'already'           => sc_t('scanner.already', 'Already checked in'),
    'already_company'   => sc_t('scanner.already_company', 'Company already checked in'),
    'first_in'          => sc_t('scanner.first_in', 'First checked in: %s. Make sure this is the same person.'),
    'scans_today'       => sc_t('scanner.scans_today', '%d scans today'),
    'not_found'         => sc_t('scanner.not_found', 'Not found'),
    'no_phone_match'    => sc_t('scanner.no_phone_match', 'Nobody registered for this event has that phone number.'),
    'network'           => sc_t('scanner.network', 'Could not reach the server. Check the connection and scan again.'),
    'network_title'     => sc_t('scanner.network_title', 'No connection'),
    'error_title'       => sc_t('scanner.error_title', 'Not accepted'),
    'pick_person'       => sc_t('scanner.pick_person', 'Who is it?'),
    'session_prefix'    => sc_t('scanner.session_prefix', 'Session: %s'),
    'workshop_prefix'   => sc_t('scanner.workshop_prefix', 'Workshop: %s'),
    'all_sessions'      => sc_t('scanner.all_sessions', 'Event check-in (no session)'),
    'no_gate'           => sc_t('scanner.no_gate', 'No gate'),
    'workshop_tag'      => sc_t('scanner.workshop_tag', 'Workshop'),
    'cancelled_tag'     => sc_t('scanner.cancelled_tag', 'Cancelled'),
    'in_tag'            => sc_t('scanner.in_tag', 'Checked in'),
    'mode_out'          => sc_t('scanner.mode_out', 'Scanning the same ticket again today checks the person out.'),
    'mode_warn'         => sc_t('scanner.mode_warn', 'Scanning the same ticket again today shows "Already checked in".'),
);

global $load_wd_form;
$load_wd_form = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$page_title = sc_t('dashboard_pages.scanner', 'Scanner');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
if (!$is_scanner_only) {
    get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
}
?>
<link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/scanner.css')); ?>">

<?php if ($is_scanner_only):
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));
    $logo_id = get_option('sc_platform_logo');
    $logo = $logo_id ? wp_get_attachment_image_src($logo_id, 'medium') : false;
    $is_rtl = is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl());
    ?>
    <header class="w-scanbar">
        <div class="w-scanbar__brand">
            <?php if ($logo): ?>
                <img src="<?php echo esc_url($logo[0]); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php else: ?>
                <strong><?php echo esc_html($platform_name); ?></strong>
            <?php endif; ?>
            <span class="w-scanbar__title"><?php echo esc_html(sc_t('dashboard_pages.scanner', 'Scanner')); ?></span>
        </div>
        <div class="w-scanbar__actions">
            <span class="w-scanbar__user w-truncate"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
            <?php if (!get_option('sc_site_language', '')): // Hidden while the site is pinned to one language. ?>
                <button type="button" class="w-scanbar__btn lang-switch" data-lang="<?php echo $is_rtl ? 'en' : 'ar'; ?>"><?php echo $is_rtl ? 'EN' : 'عربي'; ?></button>
            <?php endif; ?>
            <a href="#" class="w-scanbar__btn logout-link"><?php echo esc_html(sc_t('dashboard.logout', 'Log out')); ?></a>
        </div>
    </header>
<?php endif; ?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-scan">
        <?php if (!$is_scanner_only): ?>
            <div class="w-page-head">
                <div>
                    <h1><?php echo esc_html($page_title); ?></h1>
                    <p class="w-page-head__sub"><?php echo esc_html(sc_t('scanner.sub', 'Scan tickets and exhibitor badges at the door, or look people up by code or phone.')); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$filtered_events): ?>
            <div class="w-section">
                <p class="mb-0"><?php echo esc_html($is_scanner_only
                    ? sc_t('scanner.no_assigned_events', 'You are not assigned to any open event. Ask the event manager to give you access.')
                    : sc_t('scanner.no_events', 'There are no published events to scan for.')); ?></p>
            </div>
        <?php else: ?>

        <section class="w-section w-scan__setup" aria-labelledby="scan-at">
            <h2 class="w-scan__label" id="scan-at"><?php echo esc_html(sc_t('dashboard_pages.scanning_at', 'Scanning at')); ?></h2>
            <div class="w-scan__fields">
                <div class="w-field" <?php echo count($filtered_events) === 1 ? 'hidden' : ''; ?>>
                    <label class="w-field__label" for="scanner-event-select"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
                    <select class="form-control" id="scanner-event-select">
                        <option value=""><?php echo esc_html(sc_t('scanner.pick_event', 'Choose an event')); ?></option>
                        <?php foreach ($filtered_events as $event): ?>
                            <option value="<?php echo esc_attr($event->id); ?>" <?php selected($url_event_id, (int) $event->id); ?>>
                                <?php echo esc_html($event->title . (!empty($event->start_date) ? ' · ' . mysql2date('j M Y', $event->start_date) : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (count($filtered_events) === 1): ?>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('events.event', 'Event')); ?></span>
                        <span class="w-scan__static" dir="auto"><?php echo esc_html($filtered_events[0]->title); ?></span>
                    </div>
                <?php endif; ?>
                <div class="w-field" id="workshop-field" hidden>
                    <label class="w-field__label" for="scanner-workshop-select"><?php echo esc_html(sc_t('scanner.door', 'Door')); ?></label>
                    <select class="form-control" id="scanner-workshop-select">
                        <option value=""><?php echo esc_html(sc_t('dashboard_pages.event_entrance', 'Event entrance')); ?></option>
                    </select>
                </div>
                <?php if ($sessions_enabled): ?>
                    <div class="w-field" id="session-field" hidden>
                        <label class="w-field__label" for="scanner-session-select"><?php echo esc_html(sc_t('scanner.session', 'Session')); ?></label>
                        <select class="form-control" id="scanner-session-select">
                            <option value=""><?php echo esc_html($i18n['all_sessions']); ?></option>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($venues_enabled): ?>
                    <div class="w-field" id="gate-field" hidden>
                        <label class="w-field__label" for="scanner-gate-select"><?php echo esc_html(sc_t('scanner.gate', 'Gate')); ?></label>
                        <select class="form-control" id="scanner-gate-select">
                            <option value=""><?php echo esc_html($i18n['no_gate']); ?></option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <p class="w-scan__hint" id="event-required-hint" hidden><?php echo esc_html($i18n['choose_event']); ?></p>
            <p class="w-scan__mode" id="scan-mode-note" hidden></p>
        </section>

        <!-- Scanning -->
        <section class="w-scan__panel" id="scanner-mode" aria-label="<?php echo esc_attr(sc_t('scanner.camera', 'Camera')); ?>">
            <div class="w-scan__camera" id="camera-container">
                <div id="qr-video-container" class="w-scan__video"></div>
                <div class="w-scan__start" id="camera-start-overlay">
                    <svg class="w-scan__start-icon" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
                    <button type="button" id="start-camera-btn" class="btn btn-primary btn-lg w-scan__startbtn"><?php echo esc_html($i18n['start_camera']); ?></button>
                    <p class="w-scan__start-note" id="select-event-message" hidden><?php echo esc_html($i18n['choose_event']); ?></p>
                </div>
                <div class="w-scan__status" id="status-bar">
                    <span class="w-scan__dot" id="scanner-dot" aria-hidden="true"></span>
                    <span id="scanner-status" role="status"><?php echo esc_html($i18n['camera_idle']); ?></span>
                    <button type="button" class="w-scan__stop" id="stop-camera-btn" hidden><?php echo esc_html(sc_t('scanner.stop', 'Stop')); ?></button>
                </div>
            </div>

            <form class="w-scan__manual" id="manual-form" novalidate>
                <div class="w-scan__manualhead">
                    <span class="w-scan__label" id="lookup-label"><?php echo esc_html(sc_t('scanner.lookup', 'Look up by')); ?></span>
                    <div class="w-scan__seg" role="radiogroup" aria-labelledby="lookup-label">
                        <label><input type="radio" name="search-type" value="ticket" checked><span><?php echo esc_html(sc_t('scanner.code', 'Code')); ?></span></label>
                        <label><input type="radio" name="search-type" value="phone"><span><?php echo esc_html(sc_t('scanner.phone', 'Phone')); ?></span></label>
                    </div>
                </div>
                <div class="w-scan__manualrow">
                    <input type="text" id="manual-ticket-id" class="form-control" autocomplete="off" autocapitalize="characters" spellcheck="false" dir="ltr"
                           placeholder="<?php echo esc_attr($i18n['code_placeholder']); ?>" aria-label="<?php echo esc_attr($i18n['code_placeholder']); ?>" aria-describedby="manual-note">
                    <button type="submit" class="btn btn-primary" id="manual-scan-btn"><?php echo esc_html(sc_t('scanner.check', 'Check')); ?></button>
                </div>
                <p class="w-scan__note" id="manual-note" role="alert" hidden></p>
            </form>
        </section>

        <!-- Result -->
        <section class="w-scan__result" id="result-mode" data-tone="ok" hidden aria-live="assertive">
            <div class="w-scan__band">
                <span class="w-scan__bandicon" id="result-icon" aria-hidden="true"></span>
                <div>
                    <h2 class="w-scan__bandtitle" id="result-status-text"></h2>
                    <p class="w-scan__bandsub" id="result-time"></p>
                </div>
            </div>
            <div class="w-scan__body">
                <p class="w-scan__warn" id="result-note" hidden></p>
                <p class="w-scan__name" id="result-name" dir="auto"></p>
                <p class="w-scan__ticket" id="result-ticket" dir="auto"></p>
                <dl class="w-scan__facts" id="result-facts"></dl>
                <div class="w-scan__actions">
                    <button type="button" id="another-scan-btn" class="btn btn-primary btn-lg"><?php echo esc_html(sc_t('scanner.next', 'Scan next')); ?></button>
                    <?php if (!$is_scanner_only): ?>
                        <a class="btn btn-outline-secondary btn-lg" id="result-attendee-link" href="<?php echo esc_url($dashboard_url . 'attendees'); ?>"><?php echo esc_html(sc_t('scanner.open_attendees', 'Attendee list')); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Refused -->
        <section class="w-scan__result" id="error-mode" data-tone="error" hidden aria-live="assertive">
            <div class="w-scan__band">
                <span class="w-scan__bandicon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></span>
                <div>
                    <h2 class="w-scan__bandtitle" id="error-title"></h2>
                </div>
            </div>
            <div class="w-scan__body">
                <p class="w-scan__errmsg" id="error-message"></p>
                <div class="w-scan__actions">
                    <button type="button" id="error-scan-again-btn" class="btn btn-primary btn-lg"><?php echo esc_html(sc_t('scanner.try_next', 'Scan again')); ?></button>
                </div>
            </div>
        </section>

        <div class="w-scan__busy" id="processing-overlay" hidden>
            <span class="w-scan__spinner" aria-hidden="true"></span>
            <span><?php echo esc_html($i18n['checking']); ?></span>
        </div>

        <?php endif; ?>
    </div>

</div>
</div>

<?php if ($filtered_events): ?>
<script src="<?php echo esc_url(sc_dashboard_asset('dashboard/js/lib/html5-qrcode/html5-qrcode.min.js')); ?>"></script>
<script>
jQuery(function ($) {
    'use strict';

    var T = <?php echo wp_json_encode($i18n); ?>;
    var workshopsByEvent = <?php echo wp_json_encode($scanner_workshops, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var urlWorkshopId = <?php echo isset($_GET['workshop_id']) ? (int) $_GET['workshop_id'] : 0; ?>;
    var urlSessionId = <?php echo isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0; ?>;
    var venuesEnabled = <?php echo $venues_enabled ? 'true' : 'false'; ?>;
    var sessionsEnabled = <?php echo $sessions_enabled ? 'true' : 'false'; ?>;
    var eventTracking = <?php
        $tracking_map = array();
        foreach ($filtered_events as $ev) { $tracking_map[(int) $ev->id] = (int) $ev->attendance_tracking === 1; }
        echo wp_json_encode($tracking_map ?: new stdClass());
    ?>;
    var restrictedSessions =<?php echo ($is_scanner_only && !$is_full_scanner_access) ? wp_json_encode(array_map('intval', $scanner_allowed_session_ids)) : 'null'; ?>;

    var scanner = null;
    var isScanning = false;
    var cameraWanted = false;
    var busy = false;
    var selectedEventId = $('#scanner-event-select').val() || null;
    var selectedWorkshopId = null;
    var selectedSessionId = null;
    var selectedGateId = null;

    function fmt(str, value) { return String(str).replace(/%[sd]/, value); }

    // ---- Setup ------------------------------------------------------------

    function setStatus(text, state) {
        $('#scanner-status').text(text);
        $('#scanner-dot').attr('data-state', state || 'off');
    }

    function modeLabel() {
        if (selectedWorkshopId) { return fmt(T.workshop_prefix, $('#scanner-workshop-select option:selected').text()); }
        if (selectedSessionId) { return fmt(T.session_prefix, $('#scanner-session-select option:selected').text()); }
        return '';
    }

    function refreshMode() {
        var show = !!selectedEventId && !selectedSessionId;
        $('#scan-mode-note').prop('hidden', !show).text(show ? (eventTracking[selectedEventId] ? T.mode_out : T.mode_warn) : '');
    }

    function refreshReady() {
        refreshMode();
        var ready = !!selectedEventId;
        $('#event-required-hint, #select-event-message').prop('hidden', ready);
        $('#start-camera-btn, #manual-scan-btn, #manual-ticket-id').prop('disabled', !ready);
        if (!isScanning) { setStatus(modeLabel() || T.camera_idle, 'off'); }
    }

    function fillWorkshops(eventId) {
        var list = (eventId && workshopsByEvent[eventId]) || [];
        var $sel = $('#scanner-workshop-select');
        $sel.find('option:not(:first)').remove();
        list.forEach(function (w) { $('<option>').val(w.id).text(w.title).appendTo($sel); });
        var keep = list.some(function (w) { return w.id === urlWorkshopId; }) ? String(urlWorkshopId) : '';
        $sel.val(keep);
        $('#workshop-field').prop('hidden', list.length === 0);
        selectedWorkshopId = keep || null;
    }

    function loadGates(eventId) {
        if (!venuesEnabled) { return; }
        var $sel = $('#scanner-gate-select');
        $('#gate-field').prop('hidden', true);
        $sel.find('option:not(:first)').remove();
        selectedGateId = null;
        $.post(scDashboard.ajaxurl, { action: 'sc_venues_get_gates_by_event', nonce: scDashboard.nonce, event_id: eventId }).done(function (res) {
            var gates = (res && res.success && res.data && res.data.gates) || [];
            if (String(eventId) !== String(selectedEventId) || !gates.length) { return; }
            var groups = {};
            gates.forEach(function (g) { (groups[g.venue_name] = groups[g.venue_name] || []).push(g); });
            var many = Object.keys(groups).length > 1;
            Object.keys(groups).forEach(function (venue) {
                var $parent = many ? $('<optgroup>').attr('label', venue).appendTo($sel) : $sel;
                groups[venue].forEach(function (g) {
                    var label = g.name + (g.zone_name ? ' → ' + g.zone_name : '') + (g.status !== 'open' ? ' (closed)' : '');
                    $('<option>').val(g.id).text(label).appendTo($parent);
                });
            });
            $('#gate-field').prop('hidden', false);
        });
    }

    function loadSessions(eventId) {
        if (!sessionsEnabled) { return; }
        var $sel = $('#scanner-session-select');
        $('#session-field').prop('hidden', true);
        $sel.find('option:not(:first), optgroup').remove();
        selectedSessionId = null;
        $.post(scDashboard.ajaxurl, { action: 'sc_sessions_get_by_event', nonce: scDashboard.nonce, event_id: eventId, status: 'all' }).done(function (res) {
            var sessions = (res && res.success && res.data && res.data.sessions) || [];
            if (String(eventId) !== String(selectedEventId)) { return; }
            if (restrictedSessions && restrictedSessions.length) {
                sessions = sessions.filter(function (s) { return restrictedSessions.indexOf(parseInt(s.id, 10)) !== -1; });
            }
            if (!sessions.length) { return; }
            var byDate = {};
            sessions.forEach(function (s) {
                var d = s.start_time ? String(s.start_time).split(' ')[0] : '';
                (byDate[d] = byDate[d] || []).push(s);
            });
            var dates = Object.keys(byDate).sort();
            dates.forEach(function (d) {
                var $parent = dates.length > 1 ? $('<optgroup>').attr('label', d || '—').appendTo($sel) : $sel;
                byDate[d].forEach(function (s) {
                    var time = s.start_time && String(s.start_time).indexOf(' ') > 0 ? String(s.start_time).split(' ')[1].substring(0, 5) + ' · ' : '';
                    var label = time + s.title + (s.hall ? ' · ' + s.hall : '') + (s.status === 'live' ? ' (live)' : '');
                    $('<option>').val(s.id).text(label).appendTo($parent);
                });
            });
            $('#session-field').prop('hidden', !!selectedWorkshopId);

            if (urlSessionId && !selectedWorkshopId && $sel.find('option[value="' + urlSessionId + '"]').length) {
                $sel.val(String(urlSessionId)).trigger('change');
                urlSessionId = 0;
            }
            // A scanner assigned to one session scans only that session.
            if (restrictedSessions && sessions.length === 1) {
                $sel.val(String(sessions[0].id)).trigger('change');
                $('#session-field').prop('hidden', true);
            }
        });
    }

    function selectEvent(eventId) {
        selectedEventId = eventId || null;
        fillWorkshops(selectedEventId);
        if (selectedEventId) {
            loadGates(selectedEventId);
            if (!selectedWorkshopId) { loadSessions(selectedEventId); }
        } else {
            $('#gate-field, #session-field').prop('hidden', true);
            selectedGateId = selectedSessionId = null;
        }
        refreshReady();
    }

    $('#scanner-event-select').on('change', function () { selectEvent($(this).val()); });
    $('#scanner-gate-select').on('change', function () { selectedGateId = $(this).val() || null; });
    $('#scanner-workshop-select').on('change', function () {
        selectedWorkshopId = $(this).val() || null;
        if (selectedWorkshopId) {
            // Sessions belong to the event programme, not to a workshop door.
            selectedSessionId = null;
            $('#scanner-session-select').val('');
            $('#session-field').prop('hidden', true);
        } else if (selectedEventId) {
            loadSessions(selectedEventId);
        }
        refreshReady();
    });
    $('#scanner-session-select').on('change', function () {
        selectedSessionId = $(this).val() || null;
        refreshReady();
    });

    selectEvent(selectedEventId);

    // ---- Camera -----------------------------------------------------------

    function cameraConfig() {
        return {
            fps: 10,
            qrbox: function (w, h) { var s = Math.max(160, Math.floor(Math.min(w, h) * 0.7)); return { width: s, height: s }; },
            aspectRatio: window.matchMedia('(max-width: 767px)').matches ? 1 : 1.333334
        };
    }

    function startCamera() {
        if (!selectedEventId) { refreshReady(); $('#scanner-event-select').trigger('focus'); return; }
        if (typeof Html5Qrcode === 'undefined') { setStatus(T.camera_error, 'error'); return; }
        if (!scanner) { scanner = new Html5Qrcode('qr-video-container'); }
        cameraWanted = true;
        setStatus(T.camera_starting, 'wait');
        $('#start-camera-btn').prop('disabled', true);
        scanner.start({ facingMode: 'environment' }, cameraConfig(), onScanDetected, function () {}).then(function () {
            isScanning = true;
            $('#camera-start-overlay').prop('hidden', true);
            $('#stop-camera-btn').prop('hidden', false);
            setStatus(modeLabel() ? modeLabel() + ' — ' + T.camera_on : T.camera_on, 'on');
            beep('start');
        }).catch(function () {
            cameraWanted = false;
            setStatus(T.camera_error, 'error');
            $('#start-camera-btn').prop('disabled', false);
        });
    }

    function pauseCamera() {
        if (scanner && isScanning) {
            isScanning = false;
            return scanner.stop().catch(function () {});
        }
        return $.Deferred().resolve().promise();
    }

    $('#start-camera-btn').on('click', startCamera);
    $('#stop-camera-btn').on('click', function () {
        cameraWanted = false;
        pauseCamera();
        $('#camera-start-overlay').prop('hidden', false);
        $('#start-camera-btn').prop('disabled', !selectedEventId);
        $('#stop-camera-btn').prop('hidden', true);
        setStatus(T.camera_stopped, 'off');
    });

    // ---- Manual lookup ----------------------------------------------------

    function searchType() { return $('input[name="search-type"]:checked').val(); }

    function manualNote(text) { $('#manual-note').text(text || '').prop('hidden', !text); }

    $('input[name="search-type"]').on('change', function () {
        var phone = searchType() === 'phone';
        $('#manual-ticket-id').val('').attr({
            placeholder: phone ? T.phone_placeholder : T.code_placeholder,
            'aria-label': phone ? T.phone_placeholder : T.code_placeholder,
            inputmode: phone ? 'tel' : 'text',
            autocapitalize: phone ? 'off' : 'characters'
        }).trigger('focus');
        manualNote('');
    });

    $('#manual-form').on('submit', function (e) {
        e.preventDefault();
        if (!selectedEventId) { refreshReady(); return; }
        var value = $.trim($('#manual-ticket-id').val());
        if (searchType() === 'phone') {
            if (value.replace(/\D/g, '').length < 4) { manualNote(T.enter_phone); $('#manual-ticket-id').trigger('focus'); return; }
            manualNote('');
            searchByPhone(value);
        } else {
            if (!value) { manualNote(T.enter_code); $('#manual-ticket-id').trigger('focus'); return; }
            manualNote('');
            processTicket(value);
        }
    });
    $('#manual-ticket-id').on('input', function () { manualNote(''); });

    function searchByPhone(phone) {
        showBusy(true);
        $.post(scDashboard.ajaxurl, { action: 'sc_search_attendee_by_phone', nonce: scDashboard.nonce, phone: phone, event_id: selectedEventId }).done(function (res) {
            showBusy(false);
            if (!res || !res.success) { showError((res && res.data && res.data.message) || T.network, T.error_title); return; }
            var people = res.data.attendees || [];
            if (!people.length) { showError(T.no_phone_match, T.not_found); return; }
            if (people.length === 1) { processTicket(people[0].ticket_id); return; }
            pickPerson(people);
        }).fail(function () {
            showBusy(false);
            showError(T.network, T.network_title);
        });
    }

    function pickPerson(people) {
        var $list = $('<div class="w-scan__people">');
        people.forEach(function (p) {
            var $b = $('<button type="button" class="w-scan__person">').attr('data-ticket', p.ticket_id);
            $('<span class="w-scan__personname" dir="auto">').text(p.name || p.email || p.ticket_id).appendTo($b);
            var $meta = $('<span class="w-scan__personmeta">').text(p.ticket_id + (p.phone ? ' · ' + p.phone : '')).appendTo($b);
            if (p.workshop_id) { $('<span class="w-tag">').text(T.workshop_tag).appendTo($meta); }
            if (p.status && p.status !== 'active') { $('<span class="w-tag w-tag--red">').text(T.cancelled_tag).appendTo($meta); }
            if (p.ticket_status === 'used') { $('<span class="w-tag w-tag--teal">').text(T.in_tag).appendTo($meta); }
            $list.append($b);
        });
        Swal.fire({
            title: T.pick_person,
            html: $list[0],
            showConfirmButton: false,
            showCloseButton: true,
            width: 520,
            didOpen: function (popup) {
                $(popup).on('click', '.w-scan__person', function () {
                    var code = $(this).attr('data-ticket');
                    Swal.close();
                    processTicket(code);
                });
            }
        });
    }

    // ---- Check in ---------------------------------------------------------

    function onScanDetected(data) {
        if (busy) { return; }
        pauseCamera();
        beep('scan');
        var code = String(data).trim();
        if (/^\s*\{/.test(code)) {
            // Badge QR as JSON, e.g. company badges: {"type":"company","code":"COMP-…"}
            try { var qr = JSON.parse(code); code = qr.code || qr.company_code || qr.ticket_code || code; } catch (e) {}
        } else if (code.indexOf('ticket_code=') !== -1) {
            var m1 = code.match(/ticket_code=([^&]+)/);
            if (m1) { code = decodeURIComponent(m1[1]); }
        } else if (code.indexOf('sc_unique_ticket_id=') !== -1) {
            var m2 = code.match(/sc_unique_ticket_id=([^&]+)/);
            if (m2) { code = decodeURIComponent(m2[1]); }
        } else if (code.indexOf('ticket_id=') !== -1) {
            var m3 = code.match(/ticket_id=([^&]+)/);
            if (m3) { code = decodeURIComponent(m3[1]); }
        } else if (code.indexOf('ticket=') !== -1) {
            try { code = new URL(code).searchParams.get('ticket') || code; } catch (e) {}
        }
        processTicket(code);
    }

    function processTicket(code) {
        if (busy) { return; }
        showBusy(true);
        var sessionMode = !!selectedSessionId && !selectedWorkshopId;
        var req = { action: sessionMode ? 'sc_session_checkin' : 'sc_scan_and_checkin', nonce: scDashboard.nonce, ticket_id: code, event_id: selectedEventId };
        if (selectedGateId) { req.gate_id = selectedGateId; }
        if (selectedWorkshopId) { req.workshop_id = selectedWorkshopId; }
        if (sessionMode) { req.session_id = selectedSessionId; }

        $.post(scDashboard.ajaxurl, req).done(function (res) {
            showBusy(false);
            if (res && res.success) {
                showResult(sessionMode ? fromSession(res.data) : res.data);
            } else {
                showError((res && res.data && res.data.message) || T.network, (res && res.data && res.data.title) || T.error_title);
            }
        }).fail(function () {
            showBusy(false);
            showError(T.network, T.network_title);
        });
    }

    // sc_session_checkin answers in its own shape; map it onto the door result.
    function fromSession(d) {
        return {
            action_type: 'check_in',
            already_checked_in: !!d.already_checked_in,
            attendee: { name: d.attendee_name, email: d.attendee_email, phone: d.attendee_phone, ticket_type: d.ticket_name, event_name: d.session_title },
            extra_fields: {}
        };
    }

    var ICONS = {
        ok: '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        warn: '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>',
        out: '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>'
    };

    function nowLabel() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function showResult(data) {
        var a = data.attendee || {};
        var tone = data.already_checked_in ? 'warn' : (data.action_type === 'check_out' ? 'out' : 'ok');
        var title = data.already_checked_in ? (data.is_company ? T.already_company : T.already) : (data.action_type === 'check_out' ? T.checked_out : T.checked_in);

        $('#result-mode').attr('data-tone', tone);
        $('#result-icon').html(ICONS[tone]);
        $('#result-status-text').text(title);
        $('#result-time').text([nowLabel(), a.event_name].filter(Boolean).join(' · '));
        var firstIn = data.already_checked_in && data.first_checked_in_at ? new Date(data.first_checked_in_at) : null;
        var firstLabel = firstIn && !isNaN(firstIn) ? firstIn.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
        $('#result-note').text(firstLabel ? fmt(T.first_in, firstLabel) : '').prop('hidden', !firstLabel);
        $('#result-name').text(a.name || '—');
        $('#result-ticket').text(a.ticket_type || '').prop('hidden', !a.ticket_type);

        var facts = [
            ['<?php echo esc_js(sc_t('attendees.email', 'Email')); ?>', a.email],
            ['<?php echo esc_js(sc_t('attendees.phone', 'Phone')); ?>', a.phone]
        ];
        if (data.tracking_enabled) {
            facts.push(['<?php echo esc_js(sc_t('scanner.today', 'Today')); ?>', fmt(T.scans_today, data.total_scans || 1)]);
            if (data.duration) { facts.push(['<?php echo esc_js(sc_t('scanner.time_inside', 'Time inside')); ?>', data.duration]); }
        }
        if (data.gate && data.gate.name) { facts.push(['<?php echo esc_js(sc_t('scanner.gate', 'Gate')); ?>', data.gate.name]); }
        $.each(data.extra_fields || {}, function (k, v) {
            if (v !== null && v !== '' && typeof v !== 'object') { facts.push([k, v]); }
        });
        var $dl = $('#result-facts').empty();
        facts.forEach(function (f) {
            if (f[1] === undefined || f[1] === null || f[1] === '') { return; }
            $('<div>').append($('<dt>').text(f[0]), $('<dd dir="auto">').text(String(f[1]))).appendTo($dl);
        });

        var $link = $('#result-attendee-link');
        if ($link.length) {
            $link.attr('href', '<?php echo esc_js($dashboard_url); ?>' + (data.is_company ? 'company-attendees' : 'attendees') + '?event_id=' + encodeURIComponent(a.event_id || selectedEventId || ''));
        }

        $('#scanner-mode, #error-mode').prop('hidden', true);
        $('#result-mode').prop('hidden', false);
        beep(tone === 'ok' || tone === 'out' ? 'success' : 'error');
        $('#another-scan-btn').trigger('focus');
    }

    function showError(message, title) {
        $('#error-title').text(title || T.error_title);
        $('#error-message').text(message);
        $('#scanner-mode, #result-mode').prop('hidden', true);
        $('#error-mode').prop('hidden', false);
        beep('error');
        $('#error-scan-again-btn').trigger('focus');
    }

    $('#another-scan-btn, #error-scan-again-btn').on('click', function () {
        $('#result-mode, #error-mode').prop('hidden', true);
        $('#scanner-mode').prop('hidden', false);
        $('#manual-ticket-id').val('');
        if (cameraWanted) {
            startCamera();
        } else {
            $('#manual-ticket-id').trigger('focus');
        }
    });

    function showBusy(on) {
        busy = on;
        $('#processing-overlay').prop('hidden', !on);
    }

    var audio = null;
    function beep(type) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            var osc = audio.createOscillator();
            var gain = audio.createGain();
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.type = type === 'error' ? 'square' : 'sine';
            osc.frequency.value = type === 'success' ? 880 : (type === 'error' ? 280 : 1200);
            gain.gain.setValueAtTime(0.15, audio.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audio.currentTime + (type === 'error' ? 0.35 : 0.15));
            osc.start(audio.currentTime);
            osc.stop(audio.currentTime + (type === 'error' ? 0.35 : 0.15));
        } catch (e) {}
    }
});
</script>
<?php endif; ?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
