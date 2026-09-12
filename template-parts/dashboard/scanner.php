<?php
/**
 * Attendance Scanner Page - Full Screen Design
 * QR Code Scanner with auto check-in functionality
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions - allow event_manager OR event_scanner
if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Check if user is scanner-only role
$is_scanner_only = SC_Event_Manager_Dashboard::is_event_scanner();

// Check scanner_type permission for scanner users
if ($is_scanner_only) {
    $scanner_type = get_user_meta(get_current_user_id(), 'sc_scanner_type', true);
    // If scanner_type is 'company' only, deny access to attendee scanner
    if ($scanner_type === 'company') {
        wp_die(__('You only have access to the Company Scanner. Please use the Company Scanner page.', 'sc_events'));
    }
}

// Load scanner permissions for access filtering
$scanner_allowed_event_ids = array();
$scanner_allowed_session_ids = array();
$is_full_scanner_access = true;

if ($is_scanner_only) {
    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';

    // Check table exists before querying
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$perms_table'");
    if ($table_exists) {
        $scanner_perms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $perms_table WHERE user_id = %d",
            get_current_user_id()
        ));

        if (!empty($scanner_perms)) {
            $is_full_scanner_access = (count($scanner_perms) === 1 && $scanner_perms[0]->access_type === 'full');

            if (!$is_full_scanner_access) {
                foreach ($scanner_perms as $perm) {
                    if ($perm->event_id) $scanner_allowed_event_ids[] = (int) $perm->event_id;
                    if ($perm->session_id) $scanner_allowed_session_ids[] = (int) $perm->session_id;
                }
                $scanner_allowed_event_ids = array_unique($scanner_allowed_event_ids);
            }
        }
    }
}

$page_title = sc_t('dashboard_pages.attendance_scanner', 'Attendance Scanner');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'scanner' => sc_t('dashboard_pages.scanner', 'Scanner'),
    'attendance_scanner' => sc_t('dashboard_pages.attendance_scanner', 'Attendance Scanner'),
    'select_event_first' => sc_t('dashboard_pages.select_event_first', 'Select Event First'),
    'required' => sc_t('dashboard_pages.required', 'Required'),
    'no_gate_optional' => sc_t('dashboard_pages.no_gate_optional', 'No Gate (Optional)'),
    'loading_gates' => sc_t('dashboard_pages.loading_gates', 'Loading gates...'),
    'all_sessions' => sc_t('dashboard_pages.all_sessions', 'All Sessions'),
    'loading_sessions' => sc_t('dashboard_pages.loading_sessions', 'Loading sessions...'),
    'ready_to_scan' => sc_t('dashboard_pages.ready_to_scan', 'Ready to Scan'),
    'please_select_event' => sc_t('dashboard_pages.please_select_event', 'Please select an event first'),
    'start_camera' => sc_t('dashboard_pages.start_camera', 'Start Camera'),
    'stop_camera' => sc_t('dashboard_pages.stop_camera', 'Stop Camera'),
    'or_enter_manually' => sc_t('dashboard_pages.or_enter_manually', 'Or enter ticket ID manually below'),
    'ticket_id' => sc_t('dashboard_pages.ticket_id', 'Ticket ID'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'enter_ticket_id' => sc_t('dashboard_pages.enter_ticket_id', 'Enter Ticket ID...'),
    'search' => sc_t('dashboard_pages.search', 'Search'),
    'checkin_successful' => sc_t('dashboard_pages.checkin_successful', 'Check-in Successful!'),
    'checkout_successful' => sc_t('dashboard_pages.checkout_successful', 'Check-out Successful!'),
    'attendee_details' => sc_t('dashboard_pages.attendee_details', 'Attendee Details'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'ticket' => sc_t('dashboard_pages.ticket', 'Ticket'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'scans' => sc_t('dashboard_pages.scans', 'Scans'),
    'duration' => sc_t('dashboard_pages.duration', 'Duration'),
    'additional_info' => sc_t('dashboard_pages.additional_info', 'Additional Information'),
    'another_scan' => sc_t('dashboard_pages.another_scan', 'Another Scan'),
    'view_attendees' => sc_t('dashboard_pages.view_attendees', 'View Attendees'),
    'invalid_ticket' => sc_t('dashboard_pages.invalid_ticket', 'Invalid Ticket'),
    'ticket_not_found' => sc_t('dashboard_pages.ticket_not_found', 'This ticket was not found in the system.'),
    'scan_again' => sc_t('dashboard_pages.scan_again', 'Scan Again'),
    'processing' => sc_t('dashboard_pages.processing', 'Processing...'),
    'click_start_camera' => sc_t('dashboard_pages.click_start_camera', 'Click "Start Camera" to begin scanning'),
    'scanning' => sc_t('dashboard_pages.scanning', 'Scanning... Point camera at QR code'),
    'camera_stopped' => sc_t('dashboard_pages.camera_stopped', 'Camera stopped'),
    'used_checked_in' => sc_t('dashboard_pages.used_checked_in', 'Used / Checked-in'),
    'select_gate_optional' => sc_t('dashboard_pages.select_gate_optional', 'Select Gate (Optional)'),
    'all_sessions_event' => sc_t('dashboard_pages.all_sessions_event', 'All Sessions (Event Check-in)'),
    'select_attendee' => sc_t('dashboard_pages.select_attendee', 'Select Attendee'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked-in'),
    'not_checked_in' => sc_t('dashboard_pages.not_checked_in', 'Not checked-in'),
);

// Only show sidebar for full access users
if (!$is_scanner_only) {
    get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
}

// Add CSS to hide navbar for scanner-only users
if ($is_scanner_only):
    $current_user_scanner = wp_get_current_user();
    $is_rtl = is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl());
?>
<!-- Scanner Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/dashboard/css/scanner.css">
<style>
    /* Scanner-only layout overrides - must stay inline for immediate effect */
    .navbar.navbar-fixed-top { display: none !important; }
    #left-sidebar { display: none !important; }
    #sc-admin-chat-widget { display: none !important; }
    #main-content { margin-left: 0 !important; margin-top: 0 !important; padding-top: 70px !important; }
    body { padding-top: 0 !important; }
    .container-fluid { padding: 10px 15px !important; }
    .block-header { display: none !important; }
    /* Ensure hidden scanner panels are completely invisible and don't leak text */
    #result-mode[hidden],
    #error-mode[hidden],
    #processing-overlay[hidden],
    #result-mode[aria-hidden="true"],
    #error-mode[aria-hidden="true"],
    #processing-overlay[aria-hidden="true"] {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        overflow: hidden !important;
        position: absolute !important;
        clip: rect(0,0,0,0) !important;
    }
</style>

<!-- Scanner-Only Top Bar -->
<div class="scanner-topbar">
    <div class="scanner-brand">
        <div class="brand-icon"><i class="fa fa-qrcode"></i></div>
        <div>
            <div class="brand-text"><?php echo esc_html(sc_t('dashboard_pages.attendance_scanner', 'Attendance Scanner')); ?></div>
            <div class="brand-sub"><?php echo esc_html(get_option('sc_platform_name', get_bloginfo('name'))); ?></div>
        </div>
    </div>
    <div class="topbar-actions">
        <span class="user-info"><i class="fa fa-user"></i> <?php echo esc_html($current_user_scanner->display_name); ?></span>
        <a href="#" class="topbar-btn btn-lang scanner-lang-switch" data-lang="<?php echo $is_rtl ? 'en' : 'ar'; ?>">
            <?php echo $is_rtl ? 'EN' : 'ع'; ?>
        </a>
        <a href="#" class="topbar-btn btn-logout logout-link">
            <i class="fa fa-power-off"></i>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.scanner-lang-switch').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var lang = this.getAttribute('data-lang');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { if (xhr.status === 200) window.location.reload(); };
            xhr.send('action=sc_switch_language&lang=' + lang + '&nonce=<?php echo wp_create_nonce("sc_language_switch"); ?>');
        });
    });
});
</script>
<?php endif;

// Get event_id from URL if passed
$url_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Check which modules are enabled for graceful degradation
$sessions_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions');
$venues_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('venues');

// Get all events from Custom Tables — include 'completed' so check-ins for past
// events still work (e.g., late arrivals, retroactive marking).
$all_events = array();
if (class_exists('SC_Event')) {
    $all_events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'),
        'limit'  => 1000,
        'orderby' => 'start_date',
        'order'   => 'DESC',
    ));
}
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['scanner']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo esc_url( home_url( '/event-manager-dashboard/home' ) ); ?>">
                            <i class="fa fa-dashboard"></i>
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?php echo $t['scanner']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner Mode: Full Screen Camera -->
    <div id="scanner-mode">
        <!-- Top Bar -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center flex-wrap">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <select class="form-control form-control-sm" id="scanner-event-select" style="max-width: 300px;">
                            <option value="">-- <?php echo $t['select_event_first']; ?> --</option>
                            <?php
                            $filtered_events = array();
                            foreach ($all_events as $event):
                                // Filter events based on scanner permissions
                                if (!$is_full_scanner_access && $is_scanner_only) {
                                    if (!in_array((int)$event->id, $scanner_allowed_event_ids)) continue;
                                }
                                $filtered_events[] = $event;
                                $event_date = isset($event->start_date) ? $event->start_date : '';
                                $has_tracking = isset($event->attendance_tracking) && $event->attendance_tracking === 'yes';
                                $selected = ($url_event_id === (int)$event->id) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $event->id; ?>" data-tracking="<?php echo $has_tracking ? 'yes' : 'no'; ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($event->title); ?>
                                    <?php if ($event_date): ?>(<?php echo date('Y-m-d', strtotime($event_date)); ?>)<?php endif; ?>
                                    <?php if ($has_tracking): ?> ⏱️<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="event-required-hint" class="text-danger ml-2" style="font-size: 12px;">
                            <i class="fa fa-exclamation-circle"></i> <?php echo $t['required']; ?>
                        </span>

                        <?php if ($venues_enabled): ?>
                        <!-- Gate Selection (Only if Venues module is enabled) -->
                        <select class="form-control form-control-sm ml-2" id="scanner-gate-select" style="max-width: 250px; display: none;">
                            <option value="">-- <?php echo $t['no_gate_optional']; ?> --</option>
                        </select>
                        <span id="gate-loading" class="text-muted ml-2" style="display: none; font-size: 12px;" aria-hidden="true" role="status">
                            <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading_gates']; ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($sessions_enabled): ?>
                        <!-- Session Selection (Only if Sessions module is enabled) -->
                        <select class="form-control form-control-sm ml-2" id="scanner-session-select" style="max-width: 300px; display: none;">
                            <option value="">-- <?php echo $t['all_sessions']; ?> --</option>
                        </select>
                        <span id="session-loading" class="text-muted ml-2" style="display: none; font-size: 12px;" aria-hidden="true" role="status">
                            <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading_sessions']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Full Width Camera -->
        <div class="card">
            <div class="card-body p-0">
                <div id="camera-container" style="position: relative; background: #1a1a2e; border-radius: 8px; overflow: hidden; min-height: 450px;">
                    <!-- QR Scanner Container (html5-qrcode uses this div) -->
                    <div id="qr-video-container" style="width: 100%; min-height: 450px;"></div>

                    <!-- Status Bar -->
                    <div id="status-bar" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); padding: 15px; text-align: center; z-index: 10;">
                        <span id="scanner-status" class="text-white">
                            <i class="fa fa-circle text-muted"></i> <?php echo $t['click_start_camera']; ?>
                        </span>
                    </div>

                    <!-- Camera Not Started Overlay -->
                    <div id="camera-start-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(26,26,46,0.98); z-index: 20;">
                        <div class="text-center">
                            <i class="fa fa-camera fa-4x text-muted mb-3"></i>
                            <h4 class="text-white mb-3"><?php echo $t['ready_to_scan']; ?></h4>
                            <p class="text-warning mb-3" id="select-event-message">
                                <i class="fa fa-exclamation-triangle"></i> <?php echo $t['please_select_event']; ?>
                            </p>
                            <button id="start-camera-btn" class="btn btn-secondary btn-lg px-5" disabled>
                                <i class="fa fa-play"></i> <?php echo $t['start_camera']; ?>
                            </button>
                            <p class="text-muted mt-3 mb-0"><?php echo $t['or_enter_manually']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Manual Entry -->
                <div class="p-3 bg-light border-top">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <select class="form-control form-control-lg" id="search-type" style="border-radius: 4px 0 0 4px; min-width: 140px;">
                                        <option value="ticket"><?php echo $t['ticket_id']; ?></option>
                                        <option value="phone"><?php echo $t['phone']; ?></option>
                                    </select>
                                </div>
                                <input type="text" id="manual-ticket-id" class="form-control form-control-lg" placeholder="<?php echo $t['enter_ticket_id']; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary btn-lg" id="manual-scan-btn">
                                        <i class="fa fa-search"></i> <?php echo $t['search']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-right">
                            <button id="stop-camera-btn" class="btn btn-outline-danger" style="display: none;">
                                <i class="fa fa-stop"></i> <?php echo $t['stop_camera']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Mode: After Scan -->
    <div id="result-mode" style="display: none;" aria-hidden="true" hidden>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <div class="card">
                    <!-- Result Header -->
                    <div class="card-header p-4" id="result-header-bg" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <div class="d-flex align-items-center">
                            <div id="result-icon" style="font-size: 60px; color: white;" class="mr-4">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <div class="text-white">
                                <h2 class="mb-1" id="result-status-text"><?php echo $t['checkin_successful']; ?></h2>
                                <p class="mb-0 opacity-75" id="result-time"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Attendee Details -->
                    <div class="card-body">
                        <h4 class="border-bottom pb-3 mb-4"><i class="fa fa-user"></i> <?php echo $t['attendee_details']; ?></h4>

                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%"><i class="fa fa-user text-primary"></i> <?php echo $t['name']; ?>:</th>
                                        <td><strong id="result-name"></strong></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-envelope text-primary"></i> <?php echo $t['email']; ?>:</th>
                                        <td id="result-email"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-phone text-primary"></i> <?php echo $t['phone']; ?>:</th>
                                        <td id="result-phone"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-ticket text-primary"></i> <?php echo $t['ticket']; ?>:</th>
                                        <td id="result-ticket"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%"><i class="fa fa-calendar text-info"></i> <?php echo $t['event']; ?>:</th>
                                        <td id="result-event"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-tag text-info"></i> <?php echo $t['status']; ?>:</th>
                                        <td id="result-ticket-status"></td>
                                    </tr>
                                    <tr id="tracking-row-scans" style="display: none;">
                                        <th><i class="fa fa-refresh text-info"></i> <?php echo $t['scans']; ?>:</th>
                                        <td id="result-scan-count"></td>
                                    </tr>
                                    <tr id="tracking-row-duration" style="display: none;">
                                        <th><i class="fa fa-clock-o text-info"></i> <?php echo $t['duration']; ?>:</th>
                                        <td id="result-duration"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Extra Fields -->
                        <div id="extra-fields-section" style="display: none;">
                            <h5 class="border-bottom pb-2 mb-3 mt-4"><i class="fa fa-list-alt"></i> <?php echo $t['additional_info']; ?></h5>
                            <div id="extra-fields-content" class="row"></div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mt-5 pt-4 border-top">
                            <button id="another-scan-btn" class="btn btn-success btn-lg px-5 mr-3">
                                <i class="fa fa-qrcode"></i> <?php echo $t['another_scan']; ?>
                            </button>
                            <a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>" class="btn btn-info btn-lg px-5">
                                <i class="fa fa-users"></i> <?php echo $t['view_attendees']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Mode -->
    <div id="error-mode" style="display: none;" aria-hidden="true" hidden>
        <div class="row">
            <div class="col-lg-6 offset-lg-3">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white text-center p-4">
                        <i class="fa fa-times-circle fa-4x mb-3"></i>
                        <h3 id="error-title"><?php echo $t['invalid_ticket']; ?></h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="lead" id="error-message"><?php echo $t['ticket_not_found']; ?></p>
                        <div class="mt-4">
                            <button id="error-scan-again-btn" class="btn btn-primary btn-lg px-5">
                                <i class="fa fa-refresh"></i> <?php echo $t['scan_again']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Overlay -->
    <div id="processing-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999;" aria-hidden="true" hidden>
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <i class="fa fa-spinner fa-spin fa-4x text-white mb-3"></i>
            <h4 class="text-white"><?php echo $t['processing']; ?></h4>
        </div>
    </div>

</div>
</div>

<!-- QR Scanner Library - Using html5-qrcode (more compatible, no worker issues) -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
var scannerTranslations = {
    select_event_first: '<?php echo esc_js(sc_t("scanner.select_event_first", "Please select an event first")); ?>',
    camera_error: '<?php echo esc_js(sc_t("scanner.camera_error", "Camera error")); ?>',
    enter_phone: '<?php echo esc_js(sc_t("scanner.enter_phone", "Please enter a phone number")); ?>',
    enter_ticket_id: '<?php echo esc_js(sc_t("scanner.enter_ticket_id", "Please enter a ticket ID")); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let scanner = null;
    let isScanning = false;
    let selectedEventId = $('#scanner-event-select').val() || null;
    let selectedGateId = null;
    let selectedSessionId = null;

    // Module availability flags (set from PHP)
    const venuesEnabled = <?php echo $venues_enabled ? 'true' : 'false'; ?>;
    const sessionsEnabled = <?php echo $sessions_enabled ? 'true' : 'false'; ?>;

    // Scanner permission filtering
    const isFullScannerAccess = <?php echo $is_full_scanner_access ? 'true' : 'false'; ?>;
    const allowedSessionIds = <?php echo json_encode(array_map('intval', $scanner_allowed_session_ids)); ?>;
    const isScannerOnly = <?php echo $is_scanner_only ? 'true' : 'false'; ?>;

    // ===============================
    // Event Selection
    // ===============================
    $('#scanner-event-select').on('change', function() {
        selectedEventId = $(this).val() || null;
        selectedGateId = null;

        // Update UI based on event selection
        if (selectedEventId) {
            $('#event-required-hint').hide();
            $('#select-event-message').hide();
            $('#start-camera-btn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-success');
            $('#manual-scan-btn').prop('disabled', false);
            $('#manual-ticket-id').prop('disabled', false);

            // Load gates for this event (only if Venues module is enabled)
            if (venuesEnabled) {
                loadGatesForEvent(selectedEventId);
            }

            // Load sessions for this event (only if Sessions module is enabled)
            if (sessionsEnabled) {
                loadSessionsForEvent(selectedEventId);
            }
        } else {
            $('#event-required-hint').show();
            $('#select-event-message').show();
            $('#start-camera-btn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
            $('#manual-scan-btn').prop('disabled', true);
            $('#manual-ticket-id').prop('disabled', true);

            // Hide gate dropdown
            $('#scanner-gate-select').hide().html('<option value="">-- No Gate (Optional) --</option>');

            // Hide session dropdown
            $('#scanner-session-select').hide().html('<option value="">-- All Sessions --</option>');
            selectedSessionId = null;
        }
    });

    // Gate Selection
    $('#scanner-gate-select').on('change', function() {
        selectedGateId = $(this).val() || null;
    });

    // Session Selection
    $('#scanner-session-select').on('change', function() {
        selectedSessionId = $(this).val() || null;

        // Update UI to show session mode
        if (selectedSessionId) {
            $('#scanner-status').html('<i class="fa fa-clock-o text-info"></i> Session Mode: Scanning for specific session');
        }
    });

    // ===============================
    // Load Gates for Event
    // ===============================
    function loadGatesForEvent(eventId) {
        $('#gate-loading').show();
        $('#scanner-gate-select').hide();

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_venues_get_gates_by_event',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            $('#gate-loading').hide();

            if (response.success && response.data.gates && response.data.gates.length > 0) {
                let html = '<option value="">-- Select Gate (Optional) --</option>';

                // Group gates by venue
                const gatesByVenue = {};
                response.data.gates.forEach(function(gate) {
                    if (!gatesByVenue[gate.venue_name]) {
                        gatesByVenue[gate.venue_name] = [];
                    }
                    gatesByVenue[gate.venue_name].push(gate);
                });

                // Build options with optgroups
                Object.keys(gatesByVenue).forEach(function(venueName) {
                    if (Object.keys(gatesByVenue).length > 1) {
                        html += `<optgroup label="${venueName}">`;
                    }
                    gatesByVenue[venueName].forEach(function(gate) {
                        const statusIcon = gate.status === 'open' ? '🟢' : '🔴';
                        const typeLabel = gate.gate_type === 'entry' ? '↓' : (gate.gate_type === 'exit' ? '↑' : '↕');
                        const zoneInfo = gate.zone_name ? ` → ${gate.zone_name}` : '';
                        html += `<option value="${gate.id}" data-status="${gate.status}" data-type="${gate.gate_type}">
                            ${statusIcon} ${gate.name} ${typeLabel}${zoneInfo}
                        </option>`;
                    });
                    if (Object.keys(gatesByVenue).length > 1) {
                        html += '</optgroup>';
                    }
                });

                $('#scanner-gate-select').html(html).show();
            } else {
                // No gates configured - hide the dropdown
                $('#scanner-gate-select').hide().html('<option value="">-- No Gate (Optional) --</option>');
            }
        }).fail(function() {
            $('#gate-loading').hide();
            $('#scanner-gate-select').hide();
        });
    }

    // ===============================
    // Load Sessions for Event
    // ===============================
    function loadSessionsForEvent(eventId) {
        $('#session-loading').show();
        $('#scanner-session-select').hide();
        selectedSessionId = null;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_sessions_get_by_event',
                nonce: scDashboard.nonce,
                event_id: eventId,
                status: 'all' // Get all sessions for today
            }
        }).done(function(response) {
            $('#session-loading').hide();

            if (response.success && response.data.sessions && response.data.sessions.length > 0) {
                let sessions = response.data.sessions;

                // Filter sessions by scanner permissions
                if (!isFullScannerAccess && isScannerOnly && allowedSessionIds.length > 0) {
                    sessions = sessions.filter(function(s) {
                        return allowedSessionIds.indexOf(parseInt(s.id)) !== -1;
                    });
                }

                if (sessions.length === 0) {
                    $('#scanner-session-select').hide();
                    return;
                }

                let html = '<option value="">-- All Sessions (Event Check-in) --</option>';

                // Group sessions by date
                const sessionsByDate = {};
                sessions.forEach(function(session) {
                    const date = session.start_time ? session.start_time.split(' ')[0] : 'No Date';
                    if (!sessionsByDate[date]) {
                        sessionsByDate[date] = [];
                    }
                    sessionsByDate[date].push(session);
                });

                // Build options with optgroups by date
                Object.keys(sessionsByDate).sort().forEach(function(date) {
                    if (Object.keys(sessionsByDate).length > 1) {
                        html += `<optgroup label="${date}">`;
                    }
                    sessionsByDate[date].forEach(function(session) {
                        const time = session.start_time ? session.start_time.split(' ')[1].substring(0, 5) : '';
                        const typeEmoji = getSessionTypeEmoji(session.session_type);
                        const statusClass = session.status === 'live' ? '🟢' : (session.status === 'ended' ? '⚫' : '🔵');
                        const hall = session.hall ? ` [${session.hall}]` : '';
                        const cme = session.cme_hours > 0 ? ` 📚${session.cme_hours}h` : '';

                        html += `<option value="${session.id}" data-type="${session.session_type}" data-status="${session.status}">
                            ${statusClass} ${time} ${typeEmoji} ${session.title}${hall}${cme}
                        </option>`;
                    });
                    if (Object.keys(sessionsByDate).length > 1) {
                        html += '</optgroup>';
                    }
                });

                $('#scanner-session-select').html(html).show();

                // Auto-select if scanner has only one allowed session
                if (!isFullScannerAccess && isScannerOnly && sessions.length === 1) {
                    $('#scanner-session-select').val(sessions[0].id).trigger('change');
                    $('#scanner-session-select').hide();
                }
            } else {
                // No sessions configured - hide the dropdown
                $('#scanner-session-select').hide().html('<option value="">-- All Sessions --</option>');
            }
        }).fail(function() {
            $('#session-loading').hide();
            $('#scanner-session-select').hide();
        });
    }

    // Get emoji for session type
    function getSessionTypeEmoji(type) {
        const emojis = {
            'lecture': '📖',
            'workshop': '🛠️',
            'panel': '👥',
            'keynote': '🎤',
            'break': '☕',
            'networking': '🤝',
            'exhibition': '🎪',
            'poster': '📋',
            'symposium': '🎓',
            'hands_on': '✋',
            'other': '📌'
        };
        return emojis[type] || '📌';
    }

    // Auto-select for permission-restricted scanners
    <?php if ($is_scanner_only && !$is_full_scanner_access && count($filtered_events) === 1): ?>
    (function() {
        var $sel = $('#scanner-event-select');
        $sel.val('<?php echo (int)$filtered_events[0]->id; ?>').trigger('change');
        // Hide event dropdown when scanner has only one event
        $sel.closest('.d-flex').find('#event-required-hint').hide();
        $sel.hide();
    })();
    <?php elseif (!$is_scanner_only || $is_full_scanner_access): ?>
    // Initial state - disable controls if no event selected
    if (!selectedEventId) {
        $('#start-camera-btn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
        $('#manual-scan-btn').prop('disabled', true);
        $('#manual-ticket-id').prop('disabled', true);
        $('#select-event-message').show();
    } else {
        $('#event-required-hint').hide();
        $('#select-event-message').hide();
        // Load gates for pre-selected event
        loadGatesForEvent(selectedEventId);
        // Load sessions for pre-selected event
        loadSessionsForEvent(selectedEventId);
    }
    <?php else: ?>
    // Multiple events for restricted scanner - normal init
    if (!selectedEventId) {
        $('#start-camera-btn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
        $('#manual-scan-btn').prop('disabled', true);
        $('#manual-ticket-id').prop('disabled', true);
        $('#select-event-message').show();
    } else {
        $('#event-required-hint').hide();
        $('#select-event-message').hide();
        loadGatesForEvent(selectedEventId);
        loadSessionsForEvent(selectedEventId);
    }
    <?php endif; ?>

    // ===============================
    // Initialize Scanner
    // ===============================
    function initScanner() {
        if (scanner) return;

        scanner = new Html5Qrcode("qr-video-container");
    }

    // ===============================
    // Start Camera
    // ===============================
    $('#start-camera-btn').on('click', function() {
        // Check if event is selected
        if (!selectedEventId) {
            toastr.warning(scannerTranslations.select_event_first);
            $('#scanner-event-select').focus();
            return;
        }

        initScanner();

        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.777778
        };

        scanner.start(
            { facingMode: "environment" },
            config,
            (decodedText, decodedResult) => {
                onScanDetected(decodedText);
            },
            (errorMessage) => {
                // QR code parse error - ignore these
            }
        ).then(() => {
            isScanning = true;
            $('#camera-start-overlay').fadeOut();
            $('#stop-camera-btn').show();
            $('#scanner-status').html('<i class="fa fa-circle text-success blink"></i> Scanning... Point camera at QR code');
            playSound('start');
        }).catch(err => {
            toastr.error(scannerTranslations.camera_error + ': ' + err);
        });
    });

    // ===============================
    // Stop Camera
    // ===============================
    $('#stop-camera-btn').on('click', function() {
        if (scanner && isScanning) {
            scanner.stop().then(() => {
                isScanning = false;
                $('#camera-start-overlay').fadeIn();
                $('#stop-camera-btn').hide();
                $('#scanner-status').html('<i class="fa fa-circle text-muted"></i> Camera stopped');
            }).catch(err => console.log('Stop error:', err));
        }
    });

    // ===============================
    // Manual Entry
    // ===============================

    // Update placeholder based on search type
    $('#search-type').on('change', function() {
        const type = $(this).val();
        if (type === 'phone') {
            $('#manual-ticket-id').attr('placeholder', 'Enter Phone Number...').val('');
        } else {
            $('#manual-ticket-id').attr('placeholder', 'Enter Ticket ID...').val('');
        }
    });

    $('#manual-scan-btn').on('click', function() {
        // Check if event is selected
        if (!selectedEventId) {
            toastr.warning(scannerTranslations.select_event_first);
            $('#scanner-event-select').focus();
            return;
        }

        const searchType = $('#search-type').val();
        const searchValue = $('#manual-ticket-id').val().trim();

        if (!searchValue) {
            toastr.warning(searchType === 'phone' ? scannerTranslations.enter_phone : scannerTranslations.enter_ticket_id);
            $('#manual-ticket-id').focus();
            return;
        }

        if (searchType === 'phone') {
            searchByPhone(searchValue);
        } else {
            processTicket(searchValue);
        }
    });

    $('#manual-ticket-id').on('keypress', function(e) {
        if (e.which === 13) $('#manual-scan-btn').click();
    });

    // ===============================
    // Search by Phone
    // ===============================
    function searchByPhone(phone) {
        showProcessing(true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_search_attendee_by_phone',
                nonce: scDashboard.nonce,
                phone: phone,
                event_id: selectedEventId
            }
        }).done(function(response) {
            showProcessing(false);

            if (response.success) {
                if (response.data.attendees && response.data.attendees.length > 0) {
                    if (response.data.attendees.length === 1) {
                        // Single result - process directly
                        processTicket(response.data.attendees[0].ticket_id);
                    } else {
                        // Multiple results - show selection modal
                        showPhoneSearchResults(response.data.attendees);
                    }
                } else {
                    showError('No attendee found with this phone number', 'Not Found');
                    playSound('error');
                }
            } else {
                showError(response.data.message || 'Search failed', 'Error');
                playSound('error');
            }
        }).fail(function() {
            showProcessing(false);
            showError('Connection error. Please try again.', 'Network Error');
            playSound('error');
        });
    }

    // Show phone search results modal
    function showPhoneSearchResults(attendees) {
        let html = '<div class="list-group">';
        attendees.forEach(function(a) {
            const statusClass = a.ticket_status === 'used' ? 'list-group-item-success' : 'list-group-item-light';
            const statusIcon = a.ticket_status === 'used' ? '<i class="fa fa-check-circle text-success"></i>' : '<i class="fa fa-ticket text-muted"></i>';
            html += `
                <a href="#" class="list-group-item list-group-item-action ${statusClass} phone-result-item" data-ticket-id="${a.ticket_id}">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${statusIcon} ${a.name}</h6>
                            <small class="text-muted">${a.ticket_id}</small>
                        </div>
                        <span class="badge ${a.ticket_status === 'used' ? 'badge-success' : 'badge-secondary'}">${a.ticket_status === 'used' ? 'Checked-in' : 'Not checked-in'}</span>
                    </div>
                </a>
            `;
        });
        html += '</div>';

        Swal.fire({
            title: '<i class="fa fa-users"></i> Select Attendee',
            html: html,
            showConfirmButton: false,
            showCloseButton: true,
            width: '500px',
            didOpen: () => {
                $('.phone-result-item').on('click', function(e) {
                    e.preventDefault();
                    const ticketId = $(this).data('ticket-id');
                    Swal.close();
                    processTicket(ticketId);
                });
            }
        });
    }

    // ===============================
    // QR Scan Detected
    // ===============================
    function onScanDetected(data) {
        // Pause scanner
        if (scanner && isScanning) {
            scanner.stop().then(() => {
                isScanning = false;
            }).catch(err => {});
        }
        $('#scanner-status').html('<i class="fa fa-spinner fa-spin"></i> Processing...');
        playSound('scan');

        // Extract ticket ID from various URL formats
        let ticketId = data;
        if (data.includes('ticket_code=')) {
            // New secure format: /ticket-view/?attendee_id=X&ticket_code=XXXX-XXXX-XXXX
            const m = data.match(/ticket_code=([^&]+)/);
            if (m) ticketId = decodeURIComponent(m[1]);
        } else if (data.includes('ticket=')) {
            try { ticketId = new URL(data).searchParams.get('ticket'); } catch(e) {}
        } else if (data.includes('sc_unique_ticket_id=')) {
            const m = data.match(/sc_unique_ticket_id=([^&]+)/);
            if (m) ticketId = m[1];
        } else if (data.includes('ticket_id=')) {
            const m = data.match(/ticket_id=([^&]+)/);
            if (m) ticketId = m[1];
        }

        processTicket(ticketId);
    }

    // ===============================
    // Process Ticket (Auto Confirm)
    // ===============================
    function processTicket(ticketId) {
        showProcessing(true);

        // Build request data
        const requestData = {
            action: 'sc_scan_and_checkin',
            nonce: scDashboard.nonce,
            ticket_id: ticketId,
            event_id: selectedEventId
        };

        // Add gate_id if selected
        if (selectedGateId) {
            requestData.gate_id = selectedGateId;
        }

        // Add session_id if selected (for session-specific check-in)
        const sessionMode = !!selectedSessionId;
        if (sessionMode) {
            requestData.session_id = selectedSessionId;
            requestData.action = 'sc_session_checkin';
        }

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: requestData
        }).done(function(response) {
            showProcessing(false);

            if (response.success) {
                showResult(sessionMode ? sessionCheckinToResult(response.data) : response.data);
                playSound('success');
            } else {
                showError(response.data.message || 'Invalid ticket', response.data.title || 'Error');
                playSound('error');
            }
        }).fail(function() {
            showProcessing(false);
            showError('Network error. Please try again.', 'Connection Error');
            playSound('error');
        });
    }

    // ===============================
    // Show Result
    // ===============================
    // sc_session_checkin answers in its own shape; map it onto the gate result.
    function sessionCheckinToResult(d) {
        const when = new Date(String(d.check_in_time).replace(' ', 'T'));
        return {
            action_type: 'check_in',
            already_checked_in: !!d.already_checked_in,
            scan_time: when.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
            scan_date: when.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }),
            tracking_enabled: false,
            attendee: {
                name: d.attendee_name,
                email: d.attendee_email,
                phone: d.attendee_phone,
                ticket_type: d.ticket_name,
                event_name: d.session_title
            },
            extra_fields: {}
        };
    }

    function showResult(data) {
        // Hide scanner, show result
        $('#scanner-mode').hide();
        $('#error-mode').hide().attr('aria-hidden', 'true').prop('hidden', true);
        $('#result-mode').removeAttr('hidden').attr('aria-hidden', 'false').fadeIn();

        // Header styling based on action
        if (data.already_checked_in) {
            $('#result-header-bg').css('background', 'linear-gradient(135deg, #ffc107, #ff9800)');
            $('#result-icon').html('<i class="fa fa-exclamation-circle"></i>');
            $('#result-status-text').text('Already checked in to this session');
        } else if (data.action_type === 'check_out') {
            $('#result-header-bg').css('background', 'linear-gradient(135deg, #ffc107, #ff9800)');
            $('#result-icon').html('<i class="fa fa-sign-out"></i>');
            $('#result-status-text').text('Check-out Successful!');
        } else {
            $('#result-header-bg').css('background', 'linear-gradient(135deg, #28a745, #20c997)');
            $('#result-icon').html('<i class="fa fa-check-circle"></i>');
            $('#result-status-text').text('Check-in Successful!');
        }

        // Time
        $('#result-time').text(data.scan_time + ' - ' + data.scan_date);

        // Basic info
        $('#result-name').text(data.attendee.name || 'N/A');
        $('#result-email').text(data.attendee.email || 'N/A');
        $('#result-phone').text(data.attendee.phone || 'N/A');
        $('#result-ticket').text(data.attendee.ticket_type || 'General');
        $('#result-event').text(data.attendee.event_name || 'N/A');
        $('#result-ticket-status').html('<span class="badge badge-success"><i class="fa fa-check"></i> Used / Checked-in</span>');

        // Tracking info (only if enabled)
        if (data.tracking_enabled) {
            $('#tracking-row-scans').show();
            $('#tracking-row-duration').show();
            $('#result-scan-count').text(data.total_scans + ' scan(s) today');
            $('#result-duration').text(data.duration || '-');
        } else {
            $('#tracking-row-scans').hide();
            $('#tracking-row-duration').hide();
        }

        // Extra fields
        if (data.extra_fields && Object.keys(data.extra_fields).length > 0) {
            $('#extra-fields-section').show();
            let html = '';
            for (const [key, value] of Object.entries(data.extra_fields)) {
                html += `<div class="col-md-6 mb-2">
                    <strong>${escapeHtml(key)}:</strong> ${escapeHtml(value || 'N/A')}
                </div>`;
            }
            $('#extra-fields-content').html(html);
        } else {
            $('#extra-fields-section').hide();
        }
    }

    // ===============================
    // Show Error
    // ===============================
    function showError(message, title) {
        $('#scanner-mode').hide();
        $('#result-mode').hide().attr('aria-hidden', 'true').prop('hidden', true);
        $('#error-mode').removeAttr('hidden').attr('aria-hidden', 'false').fadeIn();

        $('#error-title').text(title || 'Invalid Ticket');
        $('#error-message').text(message);
    }

    // ===============================
    // Back to Scanner
    // ===============================
    $('#another-scan-btn, #error-scan-again-btn').on('click', function() {
        $('#result-mode').hide().attr('aria-hidden', 'true').prop('hidden', true);
        $('#error-mode').hide().attr('aria-hidden', 'true').prop('hidden', true);
        $('#scanner-mode').fadeIn();
        $('#manual-ticket-id').val('');

        // Restart camera
        if (scanner && !isScanning) {
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.777778
            };

            scanner.start(
                { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => {
                    onScanDetected(decodedText);
                },
                (errorMessage) => {}
            ).then(() => {
                isScanning = true;
                $('#camera-start-overlay').hide();
                $('#stop-camera-btn').show();
                $('#scanner-status').html('<i class="fa fa-circle text-success blink"></i> Scanning...');
            }).catch(err => {
            });
        }
    });

    // ===============================
    // Helpers
    // ===============================
    function showProcessing(show) {
        if (show) {
            $('#processing-overlay').removeAttr('hidden').attr('aria-hidden', 'false').css('display', 'flex');
        } else {
            $('#processing-overlay').hide().attr('aria-hidden', 'true').prop('hidden', true);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function playSound(type) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'success') {
                osc.frequency.value = 880;
                osc.type = 'sine';
            } else if (type === 'scan' || type === 'start') {
                osc.frequency.value = 1200;
                osc.type = 'sine';
            } else {
                osc.frequency.value = 280;
                osc.type = 'square';
            }

            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {}
    }
});
</script>

<!-- QR scanner styles moved to scanner.css -->

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
