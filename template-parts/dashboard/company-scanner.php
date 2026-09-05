<?php
/**
 * Company Scanner Page - Full Screen Design
 * QR Code scanner for company/B2B attendees
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions - allow event_manager OR event_scanner with company access
if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Check if user is scanner-only role
$is_scanner_only = SC_Event_Manager_Dashboard::is_event_scanner();

// Check scanner_type permission for scanner users
if ($is_scanner_only) {
    $scanner_type = get_user_meta(get_current_user_id(), 'sc_scanner_type', true);
    // If scanner_type is 'attendee' only, deny access to company scanner
    if ($scanner_type === 'attendee') {
        wp_die(__('You only have access to the Attendee Scanner. Please use the Attendance Scanner page.', 'sc_events'));
    }
}

$page_title = sc_t('dashboard_pages.company_scanner', 'Company Scanner');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'scanner' => sc_t('dashboard_pages.company_scanner', 'Company Scanner'),
    'company_attendees' => sc_t('dashboard_pages.company_attendees', 'Company Attendees'),
    'select_event_first' => sc_t('dashboard_pages.select_event_first', 'Select Event First'),
    'required' => sc_t('dashboard_pages.required', 'Required'),
    'ready_to_scan' => sc_t('dashboard_pages.ready_to_scan', 'Ready to Scan'),
    'please_select_event' => sc_t('dashboard_pages.please_select_event', 'Please select an event first'),
    'start_camera' => sc_t('dashboard_pages.start_camera', 'Start Camera'),
    'stop_camera' => sc_t('dashboard_pages.stop_camera', 'Stop Camera'),
    'or_enter_manually' => sc_t('dashboard_pages.or_enter_manually', 'Or enter company code manually below'),
    'company_code' => sc_t('dashboard_pages.company_code', 'Company Code'),
    'enter_company_code' => sc_t('dashboard_pages.enter_company_code', 'Enter Company Code...'),
    'search' => sc_t('dashboard_pages.search', 'Search'),
    'checkin_successful' => sc_t('dashboard_pages.checkin_successful', 'Check-in Successful!'),
    'company_details' => sc_t('dashboard_pages.company_details', 'Company Details'),
    'company_name' => sc_t('dashboard_pages.company_name', 'Company Name'),
    'contact' => sc_t('dashboard_pages.contact', 'Contact'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'booth' => sc_t('dashboard_pages.booth', 'Booth'),
    'sponsorship' => sc_t('dashboard_pages.sponsorship', 'Sponsorship'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'another_scan' => sc_t('dashboard_pages.another_scan', 'Another Scan'),
    'view_companies' => sc_t('dashboard_pages.view_companies', 'View Companies'),
    'invalid_code' => sc_t('dashboard_pages.invalid_code', 'Invalid Code'),
    'company_not_found' => sc_t('dashboard_pages.company_not_found', 'This company code was not found in the system.'),
    'scan_again' => sc_t('dashboard_pages.scan_again', 'Scan Again'),
    'processing' => sc_t('dashboard_pages.processing', 'Processing...'),
    'click_start_camera' => sc_t('dashboard_pages.click_start_camera', 'Click "Start Camera" to begin scanning'),
    'scanning' => sc_t('dashboard_pages.scanning', 'Scanning... Point camera at QR code'),
    'camera_stopped' => sc_t('dashboard_pages.camera_stopped', 'Camera stopped'),
    'already_checked_in' => sc_t('dashboard_pages.already_checked_in', 'Already Checked In'),
    'checked_in_at' => sc_t('dashboard_pages.checked_in_at', 'Checked in at'),
    'check_in_now' => sc_t('dashboard_pages.check_in_now', 'CHECK IN NOW'),
    'total_companies' => sc_t('dashboard_pages.total_companies', 'Total'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'remaining' => sc_t('dashboard_pages.remaining', 'Remaining'),
);

// Only show sidebar for full access users
if (!$is_scanner_only) {
    get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
}

// Add CSS to hide navbar for scanner-only users
if ($is_scanner_only): ?>
<style>
    /* Hide navbar and sidebar for scanner-only users */
    .navbar.navbar-fixed-top { display: none !important; }
    #left-sidebar { display: none !important; }
    #main-content { margin-left: 0 !important; margin-top: 0 !important; }
    body { padding-top: 0 !important; }
    .container-fluid { padding: 15px !important; }
</style>
<?php endif;

// Get event_id from URL if passed
$url_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get all events for dropdown
$all_events = array();
if (class_exists('SC_Event')) {
    $all_events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'),
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
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
                        <a href="<?php echo esc_url(home_url('/event-manager-dashboard/home')); ?>">
                            <i class="fa fa-dashboard"></i>
                        </a>
                    </li>
                    <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/event-manager-dashboard/company-attendees')); ?>"><?php echo $t['company_attendees']; ?></a></li>
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
        <!-- Top Bar with Stats -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select class="form-control form-control-sm" id="scanner-event-select" style="max-width: 300px;">
                        <option value="">-- <?php echo $t['select_event_first']; ?> --</option>
                        <?php foreach ($all_events as $event):
                            $event_date = isset($event->start_date) ? $event->start_date : '';
                            $selected = ($url_event_id === (int)$event->id) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $event->id; ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($event->title); ?>
                                <?php if ($event_date): ?>(<?php echo date('Y-m-d', strtotime($event_date)); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="event-required-hint" class="text-danger ml-2" style="font-size: 12px;">
                        <i class="fa fa-exclamation-circle"></i> <?php echo $t['required']; ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end">
                    <div class="scanner-stats d-flex gap-3">
                        <div class="stat-mini text-center px-3">
                            <div class="stat-value text-primary" id="stat-total">-</div>
                            <small class="text-muted"><?php echo $t['total_companies']; ?></small>
                        </div>
                        <div class="stat-mini text-center px-3">
                            <div class="stat-value text-success" id="stat-checked-in">-</div>
                            <small class="text-muted"><?php echo $t['checked_in']; ?></small>
                        </div>
                        <div class="stat-mini text-center px-3">
                            <div class="stat-value text-warning" id="stat-pending">-</div>
                            <small class="text-muted"><?php echo $t['remaining']; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Full Width Camera -->
        <div class="card">
            <div class="card-body p-0">
                <div id="camera-container" style="position: relative; background: #1a1a2e; border-radius: 8px; overflow: hidden; min-height: 450px;">
                    <!-- QR Scanner Container -->
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
                            <i class="fa fa-building fa-4x text-muted mb-3"></i>
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
                                    <span class="input-group-text"><i class="fa fa-building"></i></span>
                                </div>
                                <input type="text" id="manual-company-code" class="form-control form-control-lg" placeholder="<?php echo $t['enter_company_code']; ?>">
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
    <div id="result-mode" style="display: none;">
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

                    <!-- Company Details -->
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div id="result-logo"></div>
                            <h3 id="result-company-name" class="mt-3"></h3>
                            <p id="result-company-name-ar" class="text-muted"></p>
                            <code id="result-company-code" style="font-size: 16px;"></code>
                        </div>

                        <h4 class="border-bottom pb-3 mb-4"><i class="fa fa-building"></i> <?php echo $t['company_details']; ?></h4>

                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%"><i class="fa fa-user text-primary"></i> <?php echo $t['contact']; ?>:</th>
                                        <td id="result-contact"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-envelope text-primary"></i> <?php echo $t['email']; ?>:</th>
                                        <td id="result-email"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-phone text-primary"></i> <?php echo $t['phone']; ?>:</th>
                                        <td id="result-phone"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%"><i class="fa fa-map-marker text-info"></i> <?php echo $t['booth']; ?>:</th>
                                        <td><strong id="result-booth" class="text-primary" style="font-size: 24px;"></strong></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-star text-info"></i> <?php echo $t['sponsorship']; ?>:</th>
                                        <td id="result-sponsorship"></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fa fa-tag text-info"></i> <?php echo $t['status']; ?>:</th>
                                        <td id="result-status"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Check-in Button (if not already checked in) -->
                        <div id="checkin-action" class="text-center mt-4" style="display: none;">
                            <button id="do-checkin-btn" class="btn btn-success btn-lg px-5">
                                <i class="fa fa-check"></i> <?php echo $t['check_in_now']; ?>
                            </button>
                        </div>

                        <!-- Already Checked In Message -->
                        <div id="already-checked-in-msg" class="text-center mt-4" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle"></i>
                                <span id="checked-in-time-msg"></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mt-5 pt-4 border-top">
                            <button id="another-scan-btn" class="btn btn-success btn-lg px-5 mr-3">
                                <i class="fa fa-qrcode"></i> <?php echo $t['another_scan']; ?>
                            </button>
                            <a href="<?php echo home_url('/event-manager-dashboard/company-attendees'); ?>" class="btn btn-info btn-lg px-5">
                                <i class="fa fa-building"></i> <?php echo $t['view_companies']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Mode -->
    <div id="error-mode" style="display: none;">
        <div class="row">
            <div class="col-lg-6 offset-lg-3">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white text-center p-4">
                        <i class="fa fa-times-circle fa-4x mb-3"></i>
                        <h3 id="error-title"><?php echo $t['invalid_code']; ?></h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="lead" id="error-message"><?php echo $t['company_not_found']; ?></p>
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
    <div id="processing-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <i class="fa fa-spinner fa-spin fa-4x text-white mb-3"></i>
            <h4 class="text-white"><?php echo $t['processing']; ?></h4>
        </div>
    </div>

</div>
</div>

<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
var scannerTranslations = {
    select_event_first: '<?php echo esc_js($t['please_select_event']); ?>',
    camera_error: '<?php echo esc_js(sc_t("scanner.camera_error", "Camera error")); ?>',
    enter_code: '<?php echo esc_js(sc_t("scanner.enter_code", "Please enter a company code")); ?>',
    already_checked_in: '<?php echo esc_js($t['already_checked_in']); ?>',
    checked_in_at: '<?php echo esc_js($t['checked_in_at']); ?>',
    checkin_successful: '<?php echo esc_js($t['checkin_successful']); ?>',
    check_in_now: '<?php echo esc_js($t['check_in_now']); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let scanner = null;
    let isScanning = false;
    let selectedEventId = $('#scanner-event-select').val() || null;
    let currentCompany = null;

    // ===============================
    // Event Selection
    // ===============================
    $('#scanner-event-select').on('change', function() {
        selectedEventId = $(this).val() || null;

        if (selectedEventId) {
            $('#event-required-hint').hide();
            $('#select-event-message').hide();
            $('#start-camera-btn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-success');
            $('#manual-scan-btn').prop('disabled', false);
            $('#manual-company-code').prop('disabled', false);
            loadStats();
        } else {
            $('#event-required-hint').show();
            $('#select-event-message').show();
            $('#start-camera-btn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
            $('#manual-scan-btn').prop('disabled', true);
            $('#manual-company-code').prop('disabled', true);
        }
    });

    // Initial state
    if (!selectedEventId) {
        $('#start-camera-btn').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
        $('#manual-scan-btn').prop('disabled', true);
        $('#manual-company-code').prop('disabled', true);
    } else {
        $('#event-required-hint').hide();
        $('#select-event-message').hide();
        loadStats();
    }

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
            (errorMessage) => {}
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
    $('#manual-scan-btn').on('click', function() {
        if (!selectedEventId) {
            toastr.warning(scannerTranslations.select_event_first);
            $('#scanner-event-select').focus();
            return;
        }

        const code = $('#manual-company-code').val().trim();
        if (!code) {
            toastr.warning(scannerTranslations.enter_code);
            $('#manual-company-code').focus();
            return;
        }

        processCompanyCode(code);
    });

    $('#manual-company-code').on('keypress', function(e) {
        if (e.which === 13) $('#manual-scan-btn').click();
    });

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

        // Parse QR data - might be JSON or plain text
        let code = data;
        try {
            const parsed = JSON.parse(data);
            if (parsed.code) code = parsed.code;
            if (parsed.company_code) code = parsed.company_code;
        } catch (e) {
            // Not JSON, use as-is
        }

        processCompanyCode(code);
    }

    // ===============================
    // Process Company Code
    // ===============================
    function processCompanyCode(code) {
        showProcessing(true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_verify_company',
                nonce: scDashboard.nonce,
                code: code,
                event_id: selectedEventId
            }
        }).done(function(response) {
            showProcessing(false);

            if (response.success) {
                showResult(response.data.company, response.data.event);
                playSound('success');
            } else {
                showError(response.data.message || 'Company not found', response.data.title || 'Error');
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
    function showResult(company, event) {
        currentCompany = company;

        // Hide scanner, show result
        $('#scanner-mode').hide();
        $('#error-mode').hide();
        $('#result-mode').fadeIn();

        const isCheckedIn = company.checked_in;

        // Header styling
        if (isCheckedIn) {
            $('#result-header-bg').css('background', 'linear-gradient(135deg, #17a2b8, #138496)');
            $('#result-icon').html('<i class="fa fa-info-circle"></i>');
            $('#result-status-text').text(scannerTranslations.already_checked_in);
        } else {
            $('#result-header-bg').css('background', 'linear-gradient(135deg, #28a745, #20c997)');
            $('#result-icon').html('<i class="fa fa-check-circle"></i>');
            $('#result-status-text').text('Company Found!');
        }

        // Time
        const now = new Date();
        $('#result-time').text(now.toLocaleTimeString() + ' - ' + now.toLocaleDateString());

        // Logo
        if (company.logo_url) {
            $('#result-logo').html('<img src="' + company.logo_url + '" class="company-result-logo">');
        } else {
            $('#result-logo').html('<div class="company-result-placeholder"><i class="fa fa-building fa-4x text-muted"></i></div>');
        }

        // Company info
        $('#result-company-name').text(company.company_name || 'N/A');
        $('#result-company-name-ar').text(company.company_name_ar || '');
        $('#result-company-code').text(company.company_code || '');
        $('#result-contact').text(company.contact_name || 'N/A');
        $('#result-email').text(company.contact_email || 'N/A');
        $('#result-phone').text(company.contact_phone || 'N/A');
        $('#result-booth').text(company.booth_number || '-');
        $('#result-sponsorship').text(company.sponsorship_level || '-');

        // Status
        if (isCheckedIn) {
            $('#result-status').html('<span class="badge badge-success"><i class="fa fa-check"></i> Checked In</span>');
            $('#checkin-action').hide();
            $('#already-checked-in-msg').show();
            $('#checked-in-time-msg').text(scannerTranslations.checked_in_at + ' ' + company.checked_in_at);
        } else {
            $('#result-status').html('<span class="badge badge-warning">Not Checked In</span>');
            $('#checkin-action').show();
            $('#already-checked-in-msg').hide();
        }

        // Clear manual input
        $('#manual-company-code').val('');
    }

    // ===============================
    // Do Check-in
    // ===============================
    $('#do-checkin-btn').on('click', function() {
        if (!currentCompany) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Checking in...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_checkin_company',
                nonce: scDashboard.nonce,
                id: currentCompany.id
            }
        }).done(function(response) {
            if (response.success) {
                btn.removeClass('btn-success').addClass('btn-secondary')
                   .html('<i class="fa fa-check"></i> CHECKED IN');
                $('#result-status').html('<span class="badge badge-success"><i class="fa fa-check"></i> Checked In</span>');
                $('#result-header-bg').css('background', 'linear-gradient(135deg, #28a745, #20c997)');
                $('#result-icon').html('<i class="fa fa-check-circle"></i>');
                $('#result-status-text').text(scannerTranslations.checkin_successful);
                loadStats();
                playSound('success');
            } else {
                btn.prop('disabled', false).html('<i class="fa fa-check"></i> ' + scannerTranslations.check_in_now);
                toastr.error(response.data.message || 'Check-in failed');
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> ' + scannerTranslations.check_in_now);
            toastr.error('Network error. Please try again.');
        });
    });

    // ===============================
    // Show Error
    // ===============================
    function showError(message, title) {
        $('#scanner-mode').hide();
        $('#result-mode').hide();
        $('#error-mode').fadeIn();

        $('#error-title').text(title || 'Invalid Code');
        $('#error-message').text(message);
    }

    // ===============================
    // Back to Scanner
    // ===============================
    $('#another-scan-btn, #error-scan-again-btn').on('click', function() {
        $('#result-mode').hide();
        $('#error-mode').hide();
        $('#scanner-mode').fadeIn();
        $('#manual-company-code').val('');
        currentCompany = null;

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
            }).catch(err => {});
        }
    });

    // ===============================
    // Load Stats
    // ===============================
    function loadStats() {
        if (!selectedEventId) return;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_company_stats',
                nonce: scDashboard.nonce,
                event_id: selectedEventId
            }
        }).done(function(response) {
            if (response.success && response.data.stats) {
                $('#stat-total').text(response.data.stats.total);
                $('#stat-checked-in').text(response.data.stats.checked_in);
                $('#stat-pending').text(response.data.stats.not_checked_in);
            }
        });
    }

    // ===============================
    // Helpers
    // ===============================
    function showProcessing(show) {
        if (show) {
            $('#processing-overlay').css('display', 'flex');
        } else {
            $('#processing-overlay').hide();
        }
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

<style>
/* html5-qrcode container */
#qr-video-container {
    background: #1a1a2e;
}

#qr-video-container video {
    width: 100% !important;
    height: 450px !important;
    object-fit: cover !important;
}

/* Style the scan region */
#qr-video-container #qr-shaded-region {
    border-color: rgba(0, 255, 136, 0.6) !important;
}

/* Blink animation */
.blink {
    animation: blink 1s infinite;
}
@keyframes blink {
    0%, 50%, 100% { opacity: 1; }
    25%, 75% { opacity: 0.3; }
}

/* Result card animation */
#result-mode .card {
    animation: slideUp 0.4s ease;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Processing overlay */
#processing-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Hide the library's default text */
#qr-video-container > div:first-child > div:last-child {
    display: none !important;
}

/* Company logo styles */
.company-result-logo {
    width: 120px;
    height: 120px;
    object-fit: contain;
    border-radius: 10px;
    background: #f8f9fa;
    padding: 10px;
}

.company-result-placeholder {
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    border-radius: 10px;
    margin: 0 auto;
}

/* Stats mini */
.scanner-stats {
    display: flex;
    gap: 15px;
}

.stat-mini {
    background: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-mini .stat-value {
    font-size: 24px;
    font-weight: 700;
}

/* Mobile responsive */
@media (max-width: 767px) {
    #qr-video-container video {
        height: 350px !important;
    }
    #camera-container {
        min-height: 350px !important;
    }
    .scanner-stats {
        justify-content: center;
        margin-top: 15px;
    }
    div#scanner-mode .input-group {
        flex-direction: column;
    }
    div#scanner-mode .input-group input#manual-company-code {
        width: 100%;
        margin: 10px 0;
    }
}
</style>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
