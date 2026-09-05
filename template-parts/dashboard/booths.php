<?php
/**
 * Dashboard Booths List Page
 * Uses Custom Table (sc_booths)
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

$page_title = sc_t('nav.booths', 'Booths');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'all_booths' => sc_t('booths.all_booths', 'All Booths'),
    'add_booth' => sc_t('booths.add_booth', 'Add Booth'),
    'bulk_create' => sc_t('booths.bulk_create', 'Bulk Create'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'search' => sc_t('dashboard_pages.search', 'Search'),
    'all_types' => sc_t('booths.all_types', 'All Types'),
    'all_statuses' => sc_t('booths.all_statuses', 'All Statuses'),
    'available' => sc_t('booths.available', 'Available'),
    'reserved' => sc_t('booths.reserved', 'Reserved'),
    'booked' => sc_t('booths.booked', 'Booked'),
    'occupied' => sc_t('booths.occupied', 'Occupied'),
    'unavailable' => sc_t('booths.unavailable', 'Unavailable'),
    'refresh' => sc_t('dashboard_pages.refresh', 'Refresh'),
    'total_booths' => sc_t('booths.total_booths', 'Total Booths'),
    'revenue' => sc_t('booths.revenue', 'Revenue'),
    'bookings' => sc_t('booths.bookings', 'Bookings'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_booths' => sc_t('booths.no_booths', 'No booths found'),
    'please_select_event' => sc_t('dashboard_pages.please_select_event', 'Please select an event'),
);

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get events for dropdown
global $wpdb;
$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY start_date DESC");

// Auto-select if only one event
if (!$event_id && count($events) === 1) {
    $event_id = $events[0]->id;
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['all_booths']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['booths']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/booth-create'); ?><?php echo $event_id ? '?event_id=' . $event_id : ''; ?>" class="btn btn-success" <?php echo !$event_id ? 'style="pointer-events:none;opacity:0.5"' : ''; ?>>
                                <i class="fa fa-plus"></i> <?php echo $t['add_booth']; ?>
                            </a>
                            <button class="btn btn-info ml-2" id="btn-bulk-create" <?php echo !$event_id ? 'disabled' : ''; ?>>
                                <i class="fa fa-copy"></i> <?php echo $t['bulk_create']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Selector -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="form-group">
                    <label><strong><?php echo $t['select_event']; ?></strong></label>
                    <select id="event-selector" class="form-control">
                        <option value="">-- <?php echo $t['select_event']; ?> --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>" <?php selected($event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row clearfix" id="stats-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-th-large"></i>
                        </div>
                        <h3 class="stat-number" id="stat-total">0</h3>
                        <p class="stat-label"><?php echo $t['total_booths']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <h3 class="stat-number" id="stat-available">0</h3>
                        <p class="stat-label"><?php echo $t['available']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-calendar-check-o"></i>
                        </div>
                        <h3 class="stat-number" id="stat-booked">0</h3>
                        <p class="stat-label"><?php echo $t['booked']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-purple">
                            <i class="fa fa-building"></i>
                        </div>
                        <h3 class="stat-number" id="stat-occupied">0</h3>
                        <p class="stat-label"><?php echo $t['occupied']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-file-text"></i>
                        </div>
                        <h3 class="stat-number" id="stat-bookings">0</h3>
                        <p class="stat-label"><?php echo $t['bookings']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-money"></i>
                        </div>
                        <h3 class="stat-number" id="stat-revenue">0</h3>
                        <p class="stat-label"><?php echo $t['revenue']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booths List -->
        <div class="row" id="content-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-th-large"></i> <?php echo $t['all_booths']; ?></h2>
                    </div>
                    <div class="body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="search-booths" placeholder="<?php echo $t['search']; ?>...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="filter-booth-type">
                                    <option value=""><?php echo $t['all_types']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="filter-booth-status">
                                    <option value=""><?php echo $t['all_statuses']; ?></option>
                                    <option value="available"><?php echo $t['available']; ?></option>
                                    <option value="reserved"><?php echo $t['reserved']; ?></option>
                                    <option value="booked"><?php echo $t['booked']; ?></option>
                                    <option value="occupied"><?php echo $t['occupied']; ?></option>
                                    <option value="unavailable"><?php echo $t['unavailable']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary btn-block" id="btn-refresh-booths">
                                    <i class="fa fa-refresh"></i> <?php echo $t['refresh']; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="booths-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html(sc_t('booths.booth_number', 'Booth #')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.booth_type', 'Type')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.size', 'Size')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.price', 'Price')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.status', 'Status')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.company', 'Company')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.floor', 'Floor')); ?></th>
                                        <th><?php echo esc_html(sc_t('booths.actions', 'Actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="booths-list">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading']; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Event Selected Message -->
        <div class="row" id="no-event-message" style="<?php echo $event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="body text-center p-5">
                        <i class="fa fa-calendar fa-4x text-muted mb-3"></i>
                        <h4><?php echo $t['please_select_event']; ?></h4>
                        <p class="text-muted"><?php echo esc_html(sc_t('booths.select_event_hint', 'Use the dropdown above to choose an event')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Create Modal -->
<div class="modal fade" id="bulkCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $t['bulk_create']; ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bulk-create-form">
                    <div class="form-group">
                        <label><?php echo esc_html(sc_t('booths.booth_type_label', 'Booth Type')); ?> *</label>
                        <select class="form-control" id="bulk_type" name="booth_type_id" required>
                            <option value=""><?php echo esc_html(sc_t('booths.select_type', 'Select Type')); ?></option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo esc_html(sc_t('booths.prefix', 'Prefix')); ?> *</label>
                                <input type="text" class="form-control" id="bulk_prefix" name="prefix" value="A" maxlength="5" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo esc_html(sc_t('booths.start_number', 'Start #')); ?></label>
                                <input type="number" class="form-control" id="bulk_start" name="start" value="1" min="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo esc_html(sc_t('booths.count', 'Count')); ?> *</label>
                                <input type="number" class="form-control" id="bulk_count" name="count" value="10" min="1" max="100">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo esc_html(sc_t('booths.floor_level', 'Floor Level')); ?></label>
                        <input type="number" class="form-control" id="bulk_floor" name="floor_level" value="1" min="1">
                    </div>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <span id="bulk-preview"><?php echo esc_html(sc_t('booths.will_create', 'Will create')); ?>: A1, A2, A3, ... A10</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('general.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-success" id="btn-confirm-bulk"><?php echo esc_html(sc_t('booths.create_booths', 'Create Booths')); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
/* Stat Cards */
.stat-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 20px;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}
.stat-card .card-body {
    padding: 20px 15px;
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}
.stat-icon i {
    font-size: 22px;
    color: #fff;
}
.stat-number {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: #333;
}
.stat-label {
    font-size: 13px;
    color: #888;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.bg-primary { background: #3b82f6 !important; }
.bg-success { background: #22c55e !important; }
.bg-warning { background: #f59e0b !important; }
.bg-purple { background: #8b5cf6 !important; }
.bg-info { background: #06b6d4 !important; }
.bg-danger { background: #ef4444 !important; }

/* Status Badges */
.badge-available { background: #22c55e; color: #fff; }
.badge-reserved { background: #f59e0b; color: #fff; }
.badge-booked { background: #3b82f6; color: #fff; }
.badge-occupied { background: #8b5cf6; color: #fff; }
.badge-unavailable { background: #ef4444; color: #fff; }

/* Responsive adjustments */
@media (max-width: 1199px) {
    .stat-number { font-size: 24px; }
    .stat-label { font-size: 11px; }
}
@media (max-width: 575px) {
    .stat-card .card-body { padding: 15px 10px; }
    .stat-icon { width: 40px; height: 40px; }
    .stat-icon i { font-size: 18px; }
    .stat-number { font-size: 20px; }
    .stat-label { font-size: 10px; }
}
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var eventId = <?php echo $event_id ?: 0; ?>;
    var boothTypes = [];

    // Event selector change
    $('#event-selector').on('change', function() {
        var newEventId = $(this).val();
        if (newEventId) {
            window.location.href = '<?php echo home_url('/event-manager-dashboard/booths'); ?>?event_id=' + newEventId;
        } else {
            $('#stats-row, #content-row').hide();
            $('#no-event-message').show();
        }
    });

    if (eventId) {
        loadStats();
        loadBoothTypes();
        loadBooths();
    }

    // Load statistics
    function loadStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'sc_booths_get_stats', nonce: nonce, event_id: eventId },
            success: function(response) {
                if (response.success) {
                    $('#stat-total').text(response.data.booths.total);
                    $('#stat-available').text(response.data.booths.available);
                    $('#stat-booked').text(response.data.booths.booked + response.data.booths.reserved);
                    $('#stat-occupied').text(response.data.booths.occupied);
                    $('#stat-bookings').text(response.data.bookings.total);
                    $('#stat-revenue').text(formatMoney(response.data.bookings.collected_revenue, true));
                }
            }
        });
    }

    // Load booth types for filter and bulk create
    function loadBoothTypes() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'sc_booth_types_get_all', nonce: nonce, event_id: eventId },
            success: function(response) {
                if (response.success) {
                    boothTypes = response.data.booth_types;
                    populateTypeDropdowns();
                }
            }
        });
    }

    // Populate type dropdowns
    function populateTypeDropdowns() {
        var filterHtml = '<option value=""><?php echo esc_js($t['all_types']); ?></option>';
        var bulkHtml = '<option value=""><?php echo esc_js(sc_t('booths.select_type', 'Select Type')); ?></option>';

        boothTypes.forEach(function(t) {
            filterHtml += '<option value="' + t.id + '">' + escapeHtml(t.name) + '</option>';
            if (t.is_active == 1) {
                bulkHtml += '<option value="' + t.id + '">' + escapeHtml(t.name) + ' (' + t.size_code + ')</option>';
            }
        });

        $('#filter-booth-type').html(filterHtml);
        $('#bulk_type').html(bulkHtml);
    }

    // Load booths
    function loadBooths() {
        var search = $('#search-booths').val();
        var typeId = $('#filter-booth-type').val();
        var status = $('#filter-booth-status').val();

        $('#booths-list').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> <?php echo esc_js($t['loading']); ?></td></tr>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booths_get_all',
                nonce: nonce,
                event_id: eventId,
                search: search,
                booth_type_id: typeId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    renderBooths(response.data.booths);
                }
            }
        });
    }

    // Render booths table
    function renderBooths(booths) {
        var html = '';
        if (booths.length === 0) {
            html = '<tr><td colspan="8" class="text-center"><?php echo esc_js($t['no_booths']); ?></td></tr>';
        } else {
            booths.forEach(function(b) {
                html += '<tr>';
                html += '<td><strong>' + escapeHtml(b.booth_number) + '</strong>';
                if (b.booth_name) html += '<br><small class="text-muted">' + escapeHtml(b.booth_name) + '</small>';
                if (b.is_featured == 1) html += ' <i class="fa fa-star text-warning" title="<?php echo esc_js(sc_t('booths.featured', 'Featured')); ?>"></i>';
                html += '</td>';
                html += '<td>' + escapeHtml(b.type_name || 'N/A') + '</td>';
                html += '<td>' + b.effective_width + 'x' + b.effective_depth + 'm</td>';
                html += '<td>' + formatMoney(b.effective_price) + '</td>';
                html += '<td><span class="badge badge-' + b.status + '">' + b.status + '</span></td>';
                html += '<td>' + (b.company_name ? escapeHtml(b.company_name) : '-') + '</td>';
                html += '<td>' + (b.floor_level || 1) + '</td>';
                html += '<td>';
                html += '<div class="btn-group">';
                html += '<a href="<?php echo home_url('/event-manager-dashboard/booth-edit'); ?>?id=' + b.id + '" class="btn btn-sm btn-primary" title="Edit"><i class="fa fa-edit"></i></a>';
                html += '<button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="sr-only">Toggle Dropdown</span></button>';
                html += '<div class="dropdown-menu dropdown-menu-right">';
                html += '<a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/booth-edit'); ?>?id=' + b.id + '"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('booths.edit_booth', 'Edit')); ?></a>';
                html += '<div class="dropdown-divider"></div>';
                html += '<a class="dropdown-item text-danger btn-delete-booth" href="javascript:void(0);" data-id="' + b.id + '"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('booths.delete_booth', 'Delete')); ?></a>';
                html += '</div></div>';
                html += '</td>';
                html += '</tr>';
            });
        }
        $('#booths-list').html(html);
    }

    // Bulk create
    $('#btn-bulk-create').on('click', function() {
        updateBulkPreview();
        $('#bulkCreateModal').modal('show');
    });

    $('#btn-confirm-bulk').on('click', function() {
        var $btn = $(this);
        var data = {
            action: 'sc_booths_bulk_create',
            nonce: nonce,
            event_id: eventId,
            booth_type_id: $('#bulk_type').val(),
            prefix: $('#bulk_prefix').val(),
            start: $('#bulk_start').val(),
            count: $('#bulk_count').val(),
            floor_level: $('#bulk_floor').val()
        };

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo esc_js(sc_t('general.processing', 'Creating...')); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#bulkCreateModal').modal('hide');
                    loadBooths();
                    loadStats();
                    toastr.success(response.data.message);
                } else {
                    toastr.error(response.data.message);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(sc_t('booths.create_booths', 'Create Booths')); ?>');
            }
        });
    });

    // Update bulk preview
    function updateBulkPreview() {
        var prefix = $('#bulk_prefix').val() || 'A';
        var start = parseInt($('#bulk_start').val()) || 1;
        var count = parseInt($('#bulk_count').val()) || 10;
        var examples = [];
        for (var i = 0; i < Math.min(count, 5); i++) {
            examples.push(prefix + (start + i));
        }
        if (count > 5) examples.push('...');
        examples.push(prefix + (start + count - 1));
        $('#bulk-preview').text('<?php echo esc_js(sc_t('booths.will_create', 'Will create')); ?>: ' + examples.join(', '));
    }

    $('#bulk_prefix, #bulk_start, #bulk_count').on('input', updateBulkPreview);

    // Delete booth
    $(document).on('click', '.btn-delete-booth', function() {
        var id = $(this).data('id');
        if (confirm('<?php echo esc_js(sc_t('general.confirm_delete', 'Are you sure you want to delete this booth?')); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'sc_booths_delete', nonce: nonce, booth_id: id },
                success: function(response) {
                    if (response.success) {
                        loadBooths();
                        loadStats();
                        toastr.success(response.data.message);
                    } else {
                        toastr.error(response.data.message);
                    }
                }
            });
        }
    });

    // Filters
    var searchTimeout;
    $('#search-booths').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadBooths, 300);
    });

    $('#filter-booth-type, #filter-booth-status').on('change', loadBooths);
    $('#btn-refresh-booths').on('click', loadBooths);

    function formatMoney(amount, short) {
        var num = parseFloat(amount || 0);
        if (short && num >= 1000) {
            return 'SAR ' + (num / 1000).toFixed(1) + 'K';
        }
        return 'SAR ' + num.toLocaleString();
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
