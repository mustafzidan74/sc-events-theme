<?php
/**
 * Edit Scanner Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$scanner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$scanner_id) {
    wp_redirect(home_url('/event-manager-dashboard/scanners'));
    exit;
}

// Get all published events
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => 'publish',
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}

$page_title = sc_t('scanners.edit_scanner', 'Edit Scanner');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo sc_t('scanners.edit_scanner', 'Edit Scanner'); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/event-manager-dashboard/home')); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/event-manager-dashboard/scanners')); ?>"><?php echo sc_t('dashboard_pages.scanner_team', 'Scanner Team'); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo sc_t('scanners.edit_scanner', 'Edit Scanner'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div id="scanner-loading" class="text-center py-5">
        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
        <p class="mt-3 text-muted"><?php echo sc_t('dashboard_pages.loading', 'Loading...'); ?></p>
    </div>

    <form id="scanner-edit-form" style="display:none;">
        <?php wp_nonce_field('sc_dashboard_nonce', 'sc_nonce'); ?>
        <input type="hidden" name="user_id" value="<?php echo $scanner_id; ?>">
        <div class="row clearfix">
            <div class="col-lg-8 col-md-12">
                <!-- Account Information -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-user"></i> <?php echo sc_t('scanners.account_info', 'Account Information'); ?></h2>
                    </div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scanner-name"><?php echo sc_t('scanners.name', 'Name'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="scanner-name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scanner-email"><?php echo sc_t('scanners.email', 'Email'); ?> <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="scanner-email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scanner-phone"><?php echo sc_t('scanners.phone', 'Phone'); ?></label>
                                    <input type="text" class="form-control" id="scanner-phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scanner-password"><?php echo sc_t('scanners.password', 'Password'); ?></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="scanner-password" name="password" placeholder="<?php echo esc_attr(sc_t('scanners.leave_blank', 'Leave blank to keep current')); ?>" autocomplete="new-password">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="generate-password-btn">
                                                <i class="fa fa-random"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo sc_t('scanners.leave_blank', 'Leave blank to keep current password'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access Permissions -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-shield"></i> <?php echo sc_t('scanners.access_permissions', 'Access Permissions'); ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="access-full" name="access_type" value="full" checked>
                                <label class="custom-control-label" for="access-full">
                                    <strong><?php echo sc_t('scanners.full_access', 'Full Access'); ?></strong>
                                    <small class="d-block text-muted"><?php echo sc_t('scanners.full_access_desc', 'Can scan all events and all sessions'); ?></small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="access-event" name="access_type" value="event">
                                <label class="custom-control-label" for="access-event">
                                    <strong><?php echo sc_t('scanners.event_specific', 'Specific Events'); ?></strong>
                                    <small class="d-block text-muted"><?php echo sc_t('scanners.event_specific_desc', 'Can only scan selected events'); ?></small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="access-session" name="access_type" value="session">
                                <label class="custom-control-label" for="access-session">
                                    <strong><?php echo sc_t('scanners.session_specific', 'Specific Sessions'); ?></strong>
                                    <small class="d-block text-muted"><?php echo sc_t('scanners.session_specific_desc', 'Can only scan selected sessions in selected events'); ?></small>
                                </label>
                            </div>
                        </div>

                        <!-- Event Selection -->
                        <div id="event-permissions" style="display:none;" class="mt-3 p-3 border rounded bg-light">
                            <label class="font-weight-bold mb-2"><?php echo sc_t('scanners.select_events', 'Select Events'); ?>:</label>
                            <?php if (empty($events)): ?>
                                <p class="text-muted"><?php echo sc_t('scanners.no_events', 'No published events found.'); ?></p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($events as $event): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input event-checkbox" id="event-<?php echo $event->id; ?>" value="<?php echo $event->id; ?>">
                                            <label class="custom-control-label" for="event-<?php echo $event->id; ?>">
                                                <?php echo esc_html($event->title); ?>
                                                <small class="text-muted">(<?php echo esc_html($event->start_date); ?>)</small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Session Selection -->
                        <div id="session-permissions" style="display:none;" class="mt-3 p-3 border rounded bg-light">
                            <label class="font-weight-bold mb-2"><?php echo sc_t('scanners.select_sessions', 'Select Sessions'); ?>:</label>
                            <div class="form-group">
                                <select class="form-control" id="session-event-select">
                                    <option value=""><?php echo sc_t('scanners.select_event_first', '-- Select Event --'); ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?> (<?php echo esc_html($event->start_date); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="sessions-checklist" class="mt-2">
                                <p class="text-muted"><?php echo sc_t('scanners.select_event_to_see_sessions', 'Select an event to see its sessions.'); ?></p>
                            </div>
                            <div id="selected-sessions-summary" class="mt-3" style="display:none;">
                                <label class="font-weight-bold"><?php echo sc_t('scanners.selected_sessions', 'Selected Sessions'); ?>:</label>
                                <div id="selected-sessions-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-save"></i> <?php echo sc_t('dashboard_pages.save', 'Save'); ?></h2>
                    </div>
                    <div class="body">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" id="save-scanner-btn">
                            <i class="fa fa-save"></i> <?php echo sc_t('scanners.update_scanner', 'Update Scanner'); ?>
                        </button>
                        <a href="<?php echo esc_url(home_url('/event-manager-dashboard/scanners')); ?>" class="btn btn-outline-secondary btn-block mt-2">
                            <i class="fa fa-arrow-left"></i> <?php echo sc_t('dashboard_pages.back', 'Back'); ?>
                        </a>
                        <hr>
                        <div id="access-summary" class="mt-3">
                            <small class="text-muted d-block mb-1"><?php echo sc_t('scanners.access_summary', 'Access Summary'); ?>:</small>
                            <span class="badge badge-success" id="summary-badge"><?php echo sc_t('scanners.full_access', 'Full Access'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<script>
var scannerTranslations = {
    saving: '<?php echo esc_js(sc_t('dashboard_pages.saving', 'Saving...')); ?>',
    updated: '<?php echo esc_js(sc_t('scanners.scanner_updated', 'Scanner updated successfully!')); ?>',
    error_updating: '<?php echo esc_js(sc_t('scanners.error_updating', 'Error updating scanner.')); ?>',
    error_occurred: '<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>',
    not_found: '<?php echo esc_js(sc_t('scanners.not_found', 'Scanner not found.')); ?>',
    update_scanner: '<?php echo esc_js(sc_t('scanners.update_scanner', 'Update Scanner')); ?>',
    full_access: '<?php echo esc_js(sc_t('scanners.full_access', 'Full Access')); ?>',
    event_specific: '<?php echo esc_js(sc_t('scanners.event_specific', 'Specific Events')); ?>',
    session_specific: '<?php echo esc_js(sc_t('scanners.session_specific', 'Specific Sessions')); ?>',
    select_at_least_one_event: '<?php echo esc_js(sc_t('scanners.select_at_least_one_event', 'Please select at least one event.')); ?>',
    select_at_least_one_session: '<?php echo esc_js(sc_t('scanners.select_at_least_one_session', 'Please select at least one session.')); ?>',
    loading_sessions: '<?php echo esc_js(sc_t('scanners.loading_sessions', 'Loading sessions...')); ?>',
    no_sessions: '<?php echo esc_js(sc_t('scanners.no_sessions', 'No sessions found for this event.')); ?>'
};

jQuery(document).ready(function($) {
    var selectedSessions = {};

    // Generate random password
    $('#generate-password-btn').on('click', function() {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
        var password = '';
        for (var i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#scanner-password').val(password);
    });

    // Access type toggle
    $('input[name="access_type"]').on('change', function() {
        var type = $(this).val();
        $('#event-permissions').toggle(type === 'event');
        $('#session-permissions').toggle(type === 'session');

        if (type === 'full') {
            $('#summary-badge').removeClass().addClass('badge badge-success').text(scannerTranslations.full_access);
        } else if (type === 'event') {
            $('#summary-badge').removeClass().addClass('badge badge-warning').text(scannerTranslations.event_specific);
        } else {
            $('#summary-badge').removeClass().addClass('badge badge-info').text(scannerTranslations.session_specific);
        }
    });

    // Load sessions for event
    $('#session-event-select').on('change', function() {
        var eventId = $(this).val();
        if (!eventId) {
            $('#sessions-checklist').html('<p class="text-muted">' + scannerTranslations.no_sessions + '</p>');
            return;
        }

        $('#sessions-checklist').html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> ' + scannerTranslations.loading_sessions + '</p>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_sessions_get_by_event',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success && response.data.sessions && response.data.sessions.length) {
                var html = '<div class="row">';
                var previouslySelected = selectedSessions[eventId] || [];
                response.data.sessions.forEach(function(s) {
                    var checked = previouslySelected.indexOf(String(s.id)) !== -1 ? 'checked' : '';
                    html += '<div class="col-md-6 mb-2">' +
                        '<div class="custom-control custom-checkbox">' +
                            '<input type="checkbox" class="custom-control-input session-checkbox" id="session-' + s.id + '" value="' + s.id + '" data-event-id="' + eventId + '" data-title="' + $('<span>').text(s.title).html() + '" ' + checked + '>' +
                            '<label class="custom-control-label" for="session-' + s.id + '">' +
                                $('<span>').text(s.title).html() +
                                '<small class="text-muted d-block">' + (s.session_date || '') + ' ' + (s.start_time ? s.start_time.substring(11, 16) : '') + '</small>' +
                            '</label>' +
                        '</div>' +
                    '</div>';
                });
                html += '</div>';
                $('#sessions-checklist').html(html);
            } else {
                $('#sessions-checklist').html('<p class="text-muted">' + scannerTranslations.no_sessions + '</p>');
            }
        });
    });

    // Track session selections
    $(document).on('change', '.session-checkbox', function() {
        var eventId = $(this).data('event-id');
        if (!selectedSessions[eventId]) selectedSessions[eventId] = [];

        if ($(this).is(':checked')) {
            if (selectedSessions[eventId].indexOf(String($(this).val())) === -1) {
                selectedSessions[eventId].push(String($(this).val()));
            }
        } else {
            var val = String($(this).val());
            selectedSessions[eventId] = selectedSessions[eventId].filter(function(id) { return id !== val; });
        }

        updateSessionSummary();
    });

    function updateSessionSummary() {
        var totalSelected = 0;
        var html = '';
        Object.keys(selectedSessions).forEach(function(eventId) {
            var sessions = selectedSessions[eventId];
            if (sessions.length > 0) {
                totalSelected += sessions.length;
                sessions.forEach(function(sid) {
                    var $checkbox = $('#session-' + sid);
                    var title = $checkbox.data('title') || sid;
                    html += '<span class="badge badge-light mr-1 mb-1">' + title + '</span>';
                });
            }
        });

        if (totalSelected > 0) {
            $('#selected-sessions-summary').show();
            $('#selected-sessions-list').html(html);
        } else {
            $('#selected-sessions-summary').hide();
        }
    }

    // Load scanner data
    function loadScanner() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_scanner',
                nonce: scDashboard.nonce,
                user_id: <?php echo $scanner_id; ?>
            }
        }).done(function(response) {
            if (response.success) {
                populateForm(response.data.scanner);
            } else {
                toastr.error(scannerTranslations.not_found);
            }
        }).fail(function() {
            toastr.error(scannerTranslations.error_occurred);
        });
    }

    function populateForm(scanner) {
        $('#scanner-name').val(scanner.name);
        $('#scanner-email').val(scanner.email);
        $('#scanner-phone').val(scanner.phone);

        // Set access type
        var accessType = scanner.access_type || 'full';
        $('input[name="access_type"][value="' + accessType + '"]').prop('checked', true).trigger('change');

        // Set event checkboxes
        if (accessType === 'event' && scanner.event_ids) {
            scanner.event_ids.forEach(function(eid) {
                $('#event-' + eid).prop('checked', true);
            });
        }

        // Set session checkboxes
        if (accessType === 'session' && scanner.session_data) {
            // Group by event
            scanner.session_data.forEach(function(sd) {
                var eid = String(sd.event_id);
                if (!selectedSessions[eid]) selectedSessions[eid] = [];
                selectedSessions[eid].push(String(sd.session_id));
            });

            // Load first event's sessions
            if (scanner.session_data.length > 0) {
                var firstEventId = scanner.session_data[0].event_id;
                $('#session-event-select').val(firstEventId).trigger('change');
            }

            updateSessionSummary();
        }

        $('#scanner-loading').hide();
        $('#scanner-edit-form').show();
    }

    // Form submission
    $('#scanner-edit-form').on('submit', function(e) {
        e.preventDefault();

        var accessType = $('input[name="access_type"]:checked').val();

        if (accessType === 'event') {
            if ($('.event-checkbox:checked').length === 0) {
                toastr.warning(scannerTranslations.select_at_least_one_event);
                return;
            }
        }

        if (accessType === 'session') {
            var totalSessions = 0;
            Object.keys(selectedSessions).forEach(function(k) {
                totalSessions += selectedSessions[k].length;
            });
            if (totalSessions === 0) {
                toastr.warning(scannerTranslations.select_at_least_one_session);
                return;
            }
        }

        var $btn = $('#save-scanner-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + scannerTranslations.saving);

        var data = {
            action: 'sc_update_scanner',
            nonce: scDashboard.nonce,
            user_id: <?php echo $scanner_id; ?>,
            name: $('#scanner-name').val(),
            email: $('#scanner-email').val(),
            phone: $('#scanner-phone').val(),
            password: $('#scanner-password').val(),
            access_type: accessType,
        };

        if (accessType === 'event') {
            data.event_ids = [];
            $('.event-checkbox:checked').each(function() {
                data.event_ids.push($(this).val());
            });
        }

        if (accessType === 'session') {
            data.session_permissions = [];
            Object.keys(selectedSessions).forEach(function(eventId) {
                selectedSessions[eventId].forEach(function(sessionId) {
                    data.session_permissions.push({
                        event_id: eventId,
                        session_id: sessionId
                    });
                });
            });
        }

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: data,
        }).done(function(response) {
            if (response.success) {
                toastr.success(scannerTranslations.updated);
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + scannerTranslations.update_scanner);
            } else {
                toastr.error(response.data.message || scannerTranslations.error_updating);
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + scannerTranslations.update_scanner);
            }
        }).fail(function() {
            toastr.error(scannerTranslations.error_occurred);
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + scannerTranslations.update_scanner);
        });
    });

    // Initialize
    loadScanner();
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
.custom-control-label { cursor: pointer; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
