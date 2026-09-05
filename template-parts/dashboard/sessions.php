<?php
/**
 * Sessions Management Page
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

$page_title = sc_t('dashboard_pages.sessions_management', 'Sessions Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get all events for filter dropdown
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => 'publish',
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}

// Session type labels
$session_types = array(
    'lecture'     => sc_t('sessions.type_lecture', 'Lecture'),
    'workshop'    => sc_t('sessions.type_workshop', 'Workshop'),
    'panel'       => sc_t('sessions.type_panel', 'Panel'),
    'keynote'     => sc_t('sessions.type_keynote', 'Keynote'),
    'break'       => sc_t('sessions.type_break', 'Break'),
    'networking'  => sc_t('sessions.type_networking', 'Networking'),
    'exhibition'  => sc_t('sessions.type_exhibition', 'Exhibition'),
    'poster'      => sc_t('sessions.type_poster', 'Poster'),
    'symposium'   => sc_t('sessions.type_symposium', 'Symposium'),
    'hands_on'    => sc_t('sessions.type_hands_on', 'Hands-on'),
    'other'       => sc_t('sessions.type_other', 'Other'),
);
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="block-header">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.sessions_management', 'Sessions Management')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.sessions', 'Sessions')); ?></li>
                    </ul>
                </div>

                <div>
                    <a href="<?php echo home_url('/event-manager-dashboard/session-create'); ?>" class="btn btn-primary">
                        <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_session', 'Add Session')); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form id="sessions-filters" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="filter-event" class="mr-2"><?php echo esc_html(sc_t('events.event', 'Event')); ?>:</label>
                            <select class="form-control" id="filter-event" name="event_id">
                                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-status" class="mr-2"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?>:</label>
                            <select class="form-control" id="filter-status" name="status">
                                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_statuses', 'All Status')); ?></option>
                                <option value="draft"><?php echo esc_html(sc_t('dashboard_pages.draft', 'Draft')); ?></option>
                                <option value="published"><?php echo esc_html(sc_t('dashboard_pages.published', 'Published')); ?></option>
                                <option value="live"><?php echo esc_html(sc_t('sessions.live', 'Live')); ?></option>
                                <option value="ended"><?php echo esc_html(sc_t('sessions.ended', 'Ended')); ?></option>
                                <option value="cancelled"><?php echo esc_html(sc_t('general.cancelled', 'Cancelled')); ?></option>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-date" class="mr-2"><?php echo esc_html(sc_t('general.date', 'Date')); ?>:</label>
                            <input type="date" class="form-control" id="filter-date" name="date">
                        </div>

                        <div class="form-group mb-2">
                            <input type="text" class="form-control" id="filter-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_sessions', 'Search by title...')); ?>" style="width: 250px;">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-3" id="stats-row" style="display: none;">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.total_sessions', 'Total Sessions')); ?></h5>
                    <h3 id="stat-total" class="mb-0">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.published', 'Published')); ?></h5>
                    <h3 id="stat-published" class="mb-0 text-success">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.total_registered', 'Total Registered')); ?></h5>
                    <h3 id="stat-registered" class="mb-0 text-info">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted mb-1"><?php echo esc_html(sc_t('sessions.total_attended', 'Total Attended')); ?></h5>
                    <h3 id="stat-attended" class="mb-0 text-warning">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="sessions-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(sc_t('general.title', 'Title')); ?></th>
                                    <th><?php echo esc_html(sc_t('events.event', 'Event')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.date', 'Date')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.time', 'Time')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.hall', 'Hall')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.type', 'Type')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.capacity', 'Capacity')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.registered', 'Registered')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.attended', 'Attended')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="pagination-info-text"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="sessions-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var sessionsTranslations = {
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    no_sessions_found: '<?php echo esc_js(sc_t('dashboard_pages.no_sessions_found', 'No sessions found. Click "Add Session" to create one.')); ?>',
    error_loading: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_sessions', 'Error loading sessions')); ?>',
    error_deleting: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_session', 'Error deleting session')); ?>',
    showing: '<?php echo esc_js(sc_t('dashboard_pages.showing_to_of', 'Showing {start} to {end} of {total}')); ?>',
    confirm_delete: '<?php echo esc_js(sc_t('dashboard_pages.confirm_delete_session', 'Are you sure you want to delete this session?')); ?>'
};

var sessionTypeLabels = <?php echo json_encode($session_types); ?>;

var sessionStatusLabels = {
    'draft': '<?php echo esc_js(sc_t('dashboard_pages.draft', 'Draft')); ?>',
    'published': '<?php echo esc_js(sc_t('dashboard_pages.published', 'Published')); ?>',
    'live': '<?php echo esc_js(sc_t('sessions.live', 'Live')); ?>',
    'ended': '<?php echo esc_js(sc_t('sessions.ended', 'Ended')); ?>',
    'cancelled': '<?php echo esc_js(sc_t('general.cancelled', 'Cancelled')); ?>'
};

var sessionStatusClasses = {
    'draft': 'badge-secondary',
    'published': 'badge-success',
    'live': 'badge-primary',
    'ended': 'badge-warning',
    'cancelled': 'badge-danger'
};

jQuery(document).ready(function($) {
    'use strict';

    let searchTimeout;

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatTime(timeStr) {
        if (!timeStr) return '-';
        // Handle both datetime (2025-01-01 09:00:00) and time (09:00:00) formats
        var parts = timeStr.split(' ');
        var time = parts.length > 1 ? parts[1] : parts[0];
        var timeParts = time.split(':');
        if (timeParts.length >= 2) {
            return timeParts[0] + ':' + timeParts[1];
        }
        return time;
    }

    function loadSessions() {
        var eventId = $('#filter-event').val();
        var status = $('#filter-status').val();
        var date = $('#filter-date').val();
        var search = $('#filter-search').val().trim();

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_sessions',
                nonce: scDashboard.nonce,
                event_id: eventId,
                status: status,
                date: date,
                search: search
            },
            beforeSend: function() {
                $('#sessions-table tbody').html(
                    '<tr><td colspan="11" class="text-center py-5">' +
                    '<i class="fa fa-spinner fa-spin fa-3x text-muted"></i>' +
                    '<p class="mt-3">' + sessionsTranslations.loading + '</p></td></tr>'
                );
            }
        }).done(function(response) {
            if (response.success) {
                var sessions = response.data.sessions || [];
                renderSessionsTable(sessions);
                updateStats(sessions);
            } else {
                showError(response.data.message || sessionsTranslations.error_loading);
            }
        }).fail(function() {
            showError(sessionsTranslations.error_loading);
        });
    }

    function renderSessionsTable(sessions) {
        var tbody = $('#sessions-table tbody');
        tbody.empty();

        if (!sessions || sessions.length === 0) {
            tbody.html('<tr><td colspan="11" class="text-center py-4">' + sessionsTranslations.no_sessions_found + '</td></tr>');
            $('#pagination-info-text').text('');
            return;
        }

        sessions.forEach(function(session) {
            var timeDisplay = formatTime(session.start_time);
            if (session.end_time) {
                timeDisplay += ' - ' + formatTime(session.end_time);
            }

            var typeLabel = sessionTypeLabels[session.session_type] || session.session_type || '-';
            var statusLabel = sessionStatusLabels[session.status] || session.status;
            var statusClass = sessionStatusClasses[session.status] || 'badge-secondary';

            var capacityDisplay = (session.capacity !== null && session.capacity !== '' && parseInt(session.capacity) >= 0) ? session.capacity : '<span class="text-muted">-</span>';

            var row = '<tr>' +
                '<td><strong>' + escapeHtml(session.title) + '</strong>' +
                    (session.track ? '<br><small class="text-muted">' + escapeHtml(session.track) + '</small>' : '') +
                '</td>' +
                '<td>' + escapeHtml(session.event_title || '-') + '</td>' +
                '<td>' + escapeHtml(session.session_date || '-') + '</td>' +
                '<td>' + timeDisplay + '</td>' +
                '<td>' + escapeHtml(session.hall_name || '-') + '</td>' +
                '<td><span class="badge badge-info">' + escapeHtml(typeLabel) + '</span></td>' +
                '<td>' + capacityDisplay + '</td>' +
                '<td>' + (session.registered_count || 0) + '</td>' +
                '<td>' + (session.attended_count || 0) + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>' +
                '<td>' +
                    '<div class="btn-group">' +
                        '<a href="<?php echo home_url('/event-manager-dashboard/session-edit'); ?>?id=' + session.id + '" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">' +
                            '<i class="fa fa-edit"></i>' +
                        '</a>' +
                        '<button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
                            '<span class="sr-only">Toggle Dropdown</span>' +
                        '</button>' +
                        '<div class="dropdown-menu dropdown-menu-right">' +
                            '<a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/session-edit'); ?>?id=' + session.id + '"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>' +
                            '<a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/session-attendees'); ?>?id=' + session.id + '"><i class="fa fa-users mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.manage_attendees', 'Manage Attendees')); ?></a>' +
                            '<div class="dropdown-divider"></div>' +
                            '<a class="dropdown-item text-danger delete-session" href="javascript:void(0);" data-id="' + session.id + '"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
            '</tr>';

            tbody.append(row);
        });

        $('#pagination-info-text').text(
            sessionsTranslations.showing
                .replace('{start}', '1')
                .replace('{end}', sessions.length)
                .replace('{total}', sessions.length)
        );
    }

    function updateStats(sessions) {
        if (!sessions || sessions.length === 0) {
            $('#stats-row').hide();
            return;
        }

        var total = sessions.length;
        var published = 0;
        var totalRegistered = 0;
        var totalAttended = 0;

        sessions.forEach(function(s) {
            if (s.status === 'published' || s.status === 'live') published++;
            totalRegistered += parseInt(s.registered_count) || 0;
            totalAttended += parseInt(s.attended_count) || 0;
        });

        $('#stat-total').text(total);
        $('#stat-published').text(published);
        $('#stat-registered').text(totalRegistered);
        $('#stat-attended').text(totalAttended);
        $('#stats-row').show();
    }

    // Delete session
    $(document).on('click', '.delete-session', function() {
        var sessionId = $(this).data('id');
        var btn = $(this);

        showDeleteConfirm().then(function(result) {
            if (!result.isConfirmed) return;

            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_session',
                    nonce: scDashboard.nonce,
                    session_id: sessionId
                }
            }).done(function(response) {
                if (response.success) {
                    loadSessions();
                } else {
                    showError(response.data.message || sessionsTranslations.error_deleting);
                    btn.prop('disabled', false);
                }
            }).fail(function() {
                showError(sessionsTranslations.error_deleting);
                btn.prop('disabled', false);
            });
        });
    });

    // Filter handlers
    $('#filter-event, #filter-status').on('change', function() {
        loadSessions();
    });

    $('#filter-date').on('change', function() {
        loadSessions();
    });

    $('#filter-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadSessions();
        }, 500);
    });

    // Initial load
    loadSessions();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
