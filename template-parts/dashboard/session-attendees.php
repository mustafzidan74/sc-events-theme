<?php
/**
 * Dashboard - Session Attendees Page
 * Shows registered attendees and their attendance/check-in status
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$session_id) {
    wp_redirect(home_url('/event-manager-dashboard/sessions'));
    exit;
}

// Get session info
global $wpdb;
$sessions_table = $wpdb->prefix . 'sc_sessions';
$events_table = $wpdb->prefix . 'sc_events';
$session = $wpdb->get_row($wpdb->prepare(
    "SELECT s.*, e.title as event_title FROM $sessions_table s LEFT JOIN $events_table e ON s.event_id = e.id WHERE s.id = %d",
    $session_id
));

if (!$session) {
    wp_redirect(home_url('/event-manager-dashboard/sessions'));
    exit;
}

$page_title = sc_t('sessions.session_attendees', 'Session Attendees') . ' - ' . $session->title;
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="block-header">
                    <h2><?php echo esc_html($session->title); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/sessions'); ?>"><?php echo esc_html(sc_t('nav.sessions', 'Sessions')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('sessions.attendees', 'Attendees')); ?></li>
                    </ul>
                </div>

                <div>
                    <a href="<?php echo home_url('/event-manager-dashboard/session-edit'); ?>?id=<?php echo $session_id; ?>" class="btn btn-info">
                        <i class="fa fa-edit"></i> <?php echo esc_html(sc_t('dashboard_pages.edit_session', 'Edit Session')); ?>
                    </a>
                    <a href="<?php echo home_url('/event-manager-dashboard/sessions'); ?>" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html(sc_t('dashboard_pages.back_to_sessions', 'Back to Sessions')); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Info -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap">
                        <span class="mr-4"><strong><?php echo esc_html(sc_t('events.event', 'Event')); ?>:</strong> <?php echo esc_html($session->event_title); ?></span>
                        <span class="mr-4"><strong><?php echo esc_html(sc_t('general.date', 'Date')); ?>:</strong> <?php echo esc_html($session->session_date); ?></span>
                        <span class="mr-4"><strong><?php echo esc_html(sc_t('sessions.time', 'Time')); ?>:</strong> <?php echo esc_html(substr($session->start_time, 11, 5)); ?><?php echo $session->end_time ? ' - ' . esc_html(substr($session->end_time, 11, 5)) : ''; ?></span>
                        <span class="mr-4"><strong><?php echo esc_html(sc_t('sessions.hall', 'Hall')); ?>:</strong> <?php echo esc_html($session->hall_name ?: '-'); ?></span>
                        <?php if ($session->cme_hours > 0): ?>
                        <span class="mr-4"><strong><?php echo esc_html(sc_t('sessions.cme_hours', 'CME')); ?>:</strong> <?php echo esc_html($session->cme_hours); ?>h</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-3">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.registered', 'Registered')); ?></h5>
                    <h3 id="stat-registered" class="mb-0">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.checked_in', 'Checked In')); ?></h5>
                    <h3 id="stat-checked-in" class="mb-0 text-success">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.checked_out', 'Checked Out')); ?></h5>
                    <h3 id="stat-checked-out" class="mb-0 text-info">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.avg_attendance', 'Avg Attendance')); ?></h5>
                    <h3 id="stat-avg-attendance" class="mb-0 text-warning">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.cert_eligible', 'Cert. Eligible')); ?></h5>
                    <h3 id="stat-eligible" class="mb-0 text-primary">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.min_attendance', 'Min %')); ?></h5>
                    <h3 id="stat-min-pct" class="mb-0"><?php echo esc_html($session->min_attendance_percentage); ?>%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendees Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="attendees-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(sc_t('general.name', 'Name')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.email', 'Email')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.reg_status', 'Reg. Status')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.check_in_time', 'Check-in')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.check_out_time', 'Check-out')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.attendance_pct', 'Attendance %')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.cme_earned', 'CME Earned')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.certificate', 'Certificate')); ?></th>
                                    <th width="120"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var saTranslations = {
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    no_attendees: '<?php echo esc_js(sc_t('sessions.no_attendees', 'No attendees registered for this session.')); ?>',
    error_loading: '<?php echo esc_js(sc_t('dashboard_pages.error_loading', 'Error loading data')); ?>',
    check_in: '<?php echo esc_js(sc_t('sessions.check_in', 'Check In')); ?>',
    check_out: '<?php echo esc_js(sc_t('sessions.check_out', 'Check Out')); ?>',
    checked_in: '<?php echo esc_js(sc_t('sessions.checked_in', 'Checked In')); ?>',
    checked_out: '<?php echo esc_js(sc_t('sessions.checked_out', 'Checked Out')); ?>',
    registered: '<?php echo esc_js(sc_t('sessions.registered', 'Registered')); ?>',
    waitlisted: '<?php echo esc_js(sc_t('sessions.waitlisted', 'Waitlisted')); ?>',
    cancelled: '<?php echo esc_js(sc_t('general.cancelled', 'Cancelled')); ?>',
    eligible: '<?php echo esc_js(sc_t('sessions.eligible', 'Eligible')); ?>',
    not_eligible: '<?php echo esc_js(sc_t('sessions.not_eligible', 'Not Eligible')); ?>',
    issued: '<?php echo esc_js(sc_t('sessions.issued', 'Issued')); ?>'
};

var statusClasses = {
    'registered': 'badge-success',
    'waitlisted': 'badge-warning',
    'cancelled': 'badge-danger'
};

var statusLabels = {
    'registered': saTranslations.registered,
    'waitlisted': saTranslations.waitlisted,
    'cancelled': saTranslations.cancelled
};

jQuery(document).ready(function($) {
    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatDateTime(dt) {
        if (!dt) return '-';
        var parts = dt.split(' ');
        if (parts.length > 1) return parts[1].substring(0, 5);
        return dt;
    }

    function loadAttendees() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_session_registrations',
                nonce: scDashboard.nonce,
                session_id: <?php echo $session_id; ?>
            },
            beforeSend: function() {
                $('#attendees-table tbody').html(
                    '<tr><td colspan="9" class="text-center py-5">' +
                    '<i class="fa fa-spinner fa-spin fa-3x text-muted"></i>' +
                    '<p class="mt-3">' + saTranslations.loading + '</p></td></tr>'
                );
            }
        }).done(function(response) {
            if (response.success) {
                renderTable(response.data.attendees || []);
                updateStats(response.data.stats || {});
            } else {
                showError(response.data.message || saTranslations.error_loading);
            }
        }).fail(function() {
            showError(saTranslations.error_loading);
        });
    }

    function renderTable(attendees) {
        var tbody = $('#attendees-table tbody');
        tbody.empty();

        if (!attendees || attendees.length === 0) {
            tbody.html('<tr><td colspan="9" class="text-center py-4">' + saTranslations.no_attendees + '</td></tr>');
            return;
        }

        attendees.forEach(function(a) {
            var regStatusClass = statusClasses[a.reg_status] || 'badge-secondary';
            var regStatusLabel = statusLabels[a.reg_status] || a.reg_status;

            var attendancePct = a.attendance_percentage > 0 ? a.attendance_percentage.toFixed(1) + '%' : '-';
            var cmeEarned = a.earned_cme_hours > 0 ? a.earned_cme_hours.toFixed(2) : '-';

            var certStatus = '-';
            if (a.certificate_issued) {
                certStatus = '<span class="badge badge-success">' + saTranslations.issued + '</span>';
            } else if (a.certificate_eligible) {
                certStatus = '<span class="badge badge-info">' + saTranslations.eligible + '</span>';
            } else if (a.check_out_time) {
                certStatus = '<span class="badge badge-secondary">' + saTranslations.not_eligible + '</span>';
            }

            var actions = '';
            if (!a.check_in_time) {
                actions += '<button class="btn btn-sm btn-success checkin-btn" data-attendee="' + a.attendee_id + '" title="' + saTranslations.check_in + '">' +
                    '<i class="fa fa-sign-in"></i></button> ';
            } else if (!a.check_out_time) {
                actions += '<button class="btn btn-sm btn-warning checkout-btn" data-attendee="' + a.attendee_id + '" title="' + saTranslations.check_out + '">' +
                    '<i class="fa fa-sign-out"></i></button> ';
            }

            var row = '<tr>' +
                '<td><strong>' + escapeHtml(a.name) + '</strong></td>' +
                '<td>' + escapeHtml(a.email || '-') + '</td>' +
                '<td><span class="badge ' + regStatusClass + '">' + escapeHtml(regStatusLabel) + '</span></td>' +
                '<td>' + formatDateTime(a.check_in_time) + '</td>' +
                '<td>' + formatDateTime(a.check_out_time) + '</td>' +
                '<td>' + attendancePct + '</td>' +
                '<td>' + cmeEarned + '</td>' +
                '<td>' + certStatus + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';

            tbody.append(row);
        });
    }

    function updateStats(stats) {
        $('#stat-registered').text(stats.total_registered || 0);
        $('#stat-checked-in').text(stats.total_checked_in || 0);
        $('#stat-checked-out').text(stats.total_checked_out || 0);
        $('#stat-avg-attendance').text((stats.avg_attendance || 0) + '%');
        $('#stat-eligible').text(stats.total_eligible || 0);
    }

    // Manual check-in
    $(document).on('click', '.checkin-btn', function() {
        var btn = $(this);
        var attendeeId = btn.data('attendee');
        btn.prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_session_checkin',
                nonce: scDashboard.nonce,
                session_id: <?php echo $session_id; ?>,
                attendee_id: attendeeId,
                scan_method: 'manual'
            }
        }).done(function(response) {
            if (response.success) {
                toastr.success(response.data.message || saTranslations.checked_in);
                loadAttendees();
            } else {
                toastr.error(response.data.message || saTranslations.error_loading);
                btn.prop('disabled', false);
            }
        }).fail(function() {
            btn.prop('disabled', false);
        });
    });

    // Manual check-out
    $(document).on('click', '.checkout-btn', function() {
        var btn = $(this);
        var attendeeId = btn.data('attendee');
        btn.prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_session_checkout',
                nonce: scDashboard.nonce,
                session_id: <?php echo $session_id; ?>,
                attendee_id: attendeeId
            }
        }).done(function(response) {
            if (response.success) {
                toastr.success(response.data.message || saTranslations.checked_out);
                loadAttendees();
            } else {
                toastr.error(response.data.message || saTranslations.error_loading);
                btn.prop('disabled', false);
            }
        }).fail(function() {
            btn.prop('disabled', false);
        });
    });

    // Initial load
    loadAttendees();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
