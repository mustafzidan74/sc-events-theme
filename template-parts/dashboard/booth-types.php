<?php
/**
 * Dashboard Booth Types Management Page
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

$page_title = sc_t('booths.booth_types', 'Booth Types');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booth_types' => sc_t('booths.booth_types', 'Booth Types'),
    'booths' => sc_t('nav.booths', 'Booths'),
    'add_booth_type' => sc_t('booths.add_booth_type', 'Add Booth Type'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'size' => sc_t('booths.size', 'Size'),
    'category' => sc_t('booths.category', 'Category'),
    'price' => sc_t('dashboard_pages.price', 'Price'),
    'available' => sc_t('booths.available', 'Available'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_booth_types' => sc_t('booths.no_booth_types', 'No booth types found'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'refresh' => sc_t('dashboard_pages.refresh', 'Refresh'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'total_types' => sc_t('booths.total_types', 'Total Types'),
    'active_types' => sc_t('booths.active_types', 'Active Types'),
    'total_quantity' => sc_t('booths.total_quantity', 'Total Quantity'),
    'available_quantity' => sc_t('booths.available_quantity', 'Available'),
    'delete_confirm' => sc_t('booths.delete_type_confirm', 'Are you sure you want to delete this booth type?'),
    'deleted_success' => sc_t('booths.type_deleted', 'Booth type deleted successfully'),
    'delete_failed' => sc_t('booths.delete_type_failed', 'Failed to delete booth type'),
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

// Get stats
$total_types = 0;
$active_types = 0;
$total_qty = 0;
$available_qty = 0;

if ($event_id) {
    $total_types = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booth_types WHERE event_id = %d", $event_id)) ?: 0;
    $active_types = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booth_types WHERE event_id = %d AND is_active = 1", $event_id)) ?: 0;
    $total_qty = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_quantity), 0) FROM {$wpdb->prefix}sc_booth_types WHERE event_id = %d", $event_id)) ?: 0;
    $available_qty = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(available_quantity), 0) FROM {$wpdb->prefix}sc_booth_types WHERE event_id = %d", $event_id)) ?: 0;
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['booth_types']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>"><?php echo $t['booths']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['booth_types']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/booth-type-create' . ($event_id ? '?event_id=' . $event_id : '')); ?>" class="btn btn-primary" <?php echo !$event_id ? 'disabled' : ''; ?>>
                                <i class="fa fa-plus"></i> <?php echo $t['add_booth_type']; ?>
                            </a>
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
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-tags"></i>
                        </div>
                        <h3 class="stat-number" id="stat-total"><?php echo $total_types; ?></h3>
                        <p class="stat-label"><?php echo $t['total_types']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <h3 class="stat-number" id="stat-active"><?php echo $active_types; ?></h3>
                        <p class="stat-label"><?php echo $t['active_types']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-cubes"></i>
                        </div>
                        <h3 class="stat-number" id="stat-total-qty"><?php echo $total_qty; ?></h3>
                        <p class="stat-label"><?php echo $t['total_quantity']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-cube"></i>
                        </div>
                        <h3 class="stat-number" id="stat-available-qty"><?php echo $available_qty; ?></h3>
                        <p class="stat-label"><?php echo $t['available_quantity']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .stat-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.2s; margin-bottom: 20px; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.12); }
        .stat-card .card-body { padding: 20px 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .stat-icon i { font-size: 22px; color: #fff; }
        .stat-number { font-size: 28px; font-weight: 700; margin: 0 0 5px 0; color: #333; }
        .stat-label { font-size: 13px; color: #888; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-primary { background: #3b82f6 !important; }
        .bg-success { background: #22c55e !important; }
        .bg-warning { background: #f59e0b !important; }
        .bg-info { background: #06b6d4 !important; }
        @media (max-width: 575px) {
            .stat-card .card-body { padding: 15px 10px; }
            .stat-icon { width: 40px; height: 40px; }
            .stat-icon i { font-size: 18px; }
            .stat-number { font-size: 20px; }
            .stat-label { font-size: 10px; }
        }
        </style>

        <!-- Booth Types Table -->
        <div class="row" id="content-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-tags"></i> <?php echo $t['booth_types']; ?></h2>
                        <ul class="header-dropdown">
                            <li>
                                <button class="btn btn-sm btn-outline-primary" id="btn-refresh">
                                    <i class="fa fa-refresh"></i> <?php echo $t['refresh']; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="booth-types-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th><?php echo $t['name']; ?></th>
                                        <th><?php echo $t['size']; ?></th>
                                        <th><?php echo $t['category']; ?></th>
                                        <th><?php echo $t['price']; ?></th>
                                        <th><?php echo $t['available']; ?></th>
                                        <th><?php echo $t['status']; ?></th>
                                        <th style="width: 120px;"><?php echo $t['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody id="booth-types-list">
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
                        <h4><?php _e('Please select an event to manage booth types', 'sc_events'); ?></h4>
                        <p class="text-muted"><?php _e('Use the dropdown above to choose an event', 'sc_events'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var boothTypesTranslations = <?php echo json_encode($t); ?>;
var currentEventId = <?php echo $event_id ?: 0; ?>;

jQuery(document).ready(function($) {
    // Event selector change
    $('#event-selector').on('change', function() {
        var eventId = $(this).val();
        if (eventId) {
            window.location.href = '<?php echo home_url('/event-manager-dashboard/booth-types'); ?>?event_id=' + eventId;
        } else {
            $('#stats-row, #content-row').hide();
            $('#no-event-message').show();
        }
    });

    // Load booth types
    function loadBoothTypes() {
        if (!currentEventId) return;

        var tbody = $('#booth-types-list');
        tbody.html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> ' + boothTypesTranslations.loading + '</td></tr>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_types_get_all',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                event_id: currentEventId
            },
            success: function(response) {
                if (response.success && response.data.booth_types) {
                    var types = response.data.booth_types;
                    if (types.length === 0) {
                        tbody.html('<tr><td colspan="8" class="text-center text-muted">' + boothTypesTranslations.no_booth_types + '</td></tr>');
                        return;
                    }

                    var html = '';
                    types.forEach(function(type) {
                        var statusBadge = type.is_active == 1
                            ? '<span class="badge badge-success">' + boothTypesTranslations.active + '</span>'
                            : '<span class="badge badge-secondary">' + boothTypesTranslations.inactive + '</span>';

                        html += '<tr>';
                        html += '<td><div style="width: 20px; height: 20px; border-radius: 4px; background-color: ' + (type.color || '#3B82F6') + ';"></div></td>';
                        html += '<td><strong>' + escapeHtml(type.name) + '</strong></td>';
                        html += '<td>' + escapeHtml(type.size_code) + ' (' + type.width_meters + 'x' + type.depth_meters + 'm)</td>';
                        html += '<td>' + escapeHtml(type.booth_category || '-') + '</td>';
                        html += '<td>' + formatCurrency(type.base_price) + '</td>';
                        html += '<td>' + type.available_quantity + ' / ' + type.total_quantity + '</td>';
                        html += '<td>' + statusBadge + '</td>';
                        html += '<td>';
                        html += '<div class="btn-group">';
                        html += '<a href="<?php echo home_url('/event-manager-dashboard/booth-type-edit'); ?>?id=' + type.id + '" class="btn btn-sm btn-outline-primary" title="' + boothTypesTranslations.edit + '"><i class="fa fa-pencil"></i></a>';
                        html += '<button class="btn btn-sm btn-outline-danger btn-delete-type" data-id="' + type.id + '" title="' + boothTypesTranslations.delete + '"><i class="fa fa-trash"></i></button>';
                        html += '</div>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="8" class="text-center text-danger">Connection error</td></tr>');
            }
        });
    }

    // Refresh button
    $('#btn-refresh').on('click', function() {
        loadBoothTypes();
    });

    // Delete booth type
    $(document).on('click', '.btn-delete-type', function() {
        var typeId = $(this).data('id');

        Swal.fire({
            title: boothTypesTranslations.delete,
            text: boothTypesTranslations.delete_confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: boothTypesTranslations.delete
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_booth_types_delete',
                        nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                        id: typeId
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: boothTypesTranslations.deleted_success,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadBoothTypes();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: boothTypesTranslations.delete_failed,
                                text: response.data.message
                            });
                        }
                    }
                });
            }
        });
    });

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('en-SA', {minimumFractionDigits: 2}) + ' SAR';
    }

    // Initial load
    if (currentEventId) {
        loadBoothTypes();
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
