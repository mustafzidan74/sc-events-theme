<?php
/**
 * Dashboard Booth Floor Plan Page
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

$page_title = sc_t('booths.floor_plan', 'Floor Plan');

// Load jQuery UI for drag and drop
wp_enqueue_script('jquery-ui-draggable');
wp_enqueue_script('jquery-ui-droppable');

get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'floor_plan' => sc_t('booths.floor_plan', 'Floor Plan'),
    'booths' => sc_t('nav.booths', 'Booths'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'select_floor' => sc_t('booths.select_floor', 'Select Floor'),
    'all_floors' => sc_t('booths.all_floors', 'All Floors'),
    'floor' => sc_t('booths.floor', 'Floor'),
    'available' => sc_t('booths.available', 'Available'),
    'reserved' => sc_t('booths.reserved', 'Reserved'),
    'booked' => sc_t('booths.booked', 'Booked'),
    'occupied' => sc_t('booths.occupied', 'Occupied'),
    'unavailable' => sc_t('booths.unavailable', 'Unavailable'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'drag_to_move' => sc_t('booths.drag_to_move', 'Drag booths to reposition them'),
    'click_for_details' => sc_t('booths.click_for_details', 'Click on a booth for details'),
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
                    <h2><?php echo $t['floor_plan']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>"><?php echo $t['booths']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['floor_plan']; ?></li>
                    </ul>
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
            <div class="col-md-3" id="floor-filter-wrapper" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
                <div class="form-group">
                    <label><strong><?php echo $t['select_floor']; ?></strong></label>
                    <select id="floor-selector" class="form-control">
                        <option value=""><?php echo $t['all_floors']; ?></option>
                        <option value="1"><?php echo $t['floor']; ?> 1</option>
                        <option value="2"><?php echo $t['floor']; ?> 2</option>
                        <option value="3"><?php echo $t['floor']; ?> 3</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Floor Plan Content -->
        <div class="row" id="content-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-map"></i> <?php echo $t['floor_plan']; ?></h2>
                        <ul class="header-dropdown">
                            <li>
                                <div class="btn-group">
                                    <button class="btn btn-sm" style="background-color: #28a745; color: white;"><i class="fa fa-square"></i> <?php echo $t['available']; ?></button>
                                    <button class="btn btn-sm" style="background-color: #ffc107; color: black;"><i class="fa fa-square"></i> <?php echo $t['reserved']; ?></button>
                                    <button class="btn btn-sm" style="background-color: #17a2b8; color: white;"><i class="fa fa-square"></i> <?php echo $t['booked']; ?></button>
                                    <button class="btn btn-sm" style="background-color: #6f42c1; color: white;"><i class="fa fa-square"></i> <?php echo $t['occupied']; ?></button>
                                    <button class="btn btn-sm" style="background-color: #dc3545; color: white;"><i class="fa fa-square"></i> <?php echo $t['unavailable']; ?></button>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <div id="floor-plan-container" style="min-height: 600px; border: 2px dashed #ddd; border-radius: 8px; position: relative; overflow: hidden; background: #f8f9fa;">
                            <div id="floor-plan-loading" class="text-center p-5">
                                <i class="fa fa-spinner fa-spin fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?php echo $t['loading']; ?></p>
                            </div>
                            <div id="floor-plan-grid" style="display: none; width: 100%; height: 100%; position: relative; padding: 20px;">
                                <!-- Booths will be rendered here -->
                            </div>
                            <div id="floor-plan-empty" style="display: none;" class="text-center p-5">
                                <i class="fa fa-map fa-4x text-muted mb-3"></i>
                                <h4><?php _e('No booths with positions found', 'sc_events'); ?></h4>
                                <p class="text-muted"><?php echo $t['drag_to_move']; ?></p>
                            </div>
                        </div>
                        <div class="mt-3 text-muted">
                            <small><i class="fa fa-info-circle"></i> <?php echo $t['drag_to_move']; ?>. <?php echo $t['click_for_details']; ?>.</small>
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
                        <h4><?php _e('Please select an event to view floor plan', 'sc_events'); ?></h4>
                        <p class="text-muted"><?php _e('Use the dropdown above to choose an event', 'sc_events'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booth Details Modal -->
<div class="modal fade" id="boothDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booth Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="booth-details-content">
                <!-- Details loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="btn-edit-booth">Edit Booth</a>
            </div>
        </div>
    </div>
</div>

<style>
.booth-item {
    position: absolute;
    border-radius: 4px;
    cursor: move;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    color: white;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: transform 0.1s, box-shadow 0.1s;
    user-select: none;
}
.booth-item:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    z-index: 100;
}
.booth-item.status-available { background-color: #28a745; }
.booth-item.status-reserved { background-color: #ffc107; color: #333; }
.booth-item.status-booked { background-color: #17a2b8; }
.booth-item.status-occupied { background-color: #6f42c1; }
.booth-item.status-unavailable { background-color: #dc3545; }
</style>

<script>
var floorPlanTranslations = <?php echo json_encode($t); ?>;
var currentEventId = <?php echo $event_id ?: 0; ?>;

jQuery(document).ready(function($) {
    // Event selector change
    $('#event-selector').on('change', function() {
        var eventId = $(this).val();
        if (eventId) {
            window.location.href = '<?php echo home_url('/event-manager-dashboard/booth-floor-plan'); ?>?event_id=' + eventId;
        } else {
            $('#floor-filter-wrapper, #content-row').hide();
            $('#no-event-message').show();
        }
    });

    // Floor filter change
    $('#floor-selector').on('change', function() {
        loadFloorPlan();
    });

    // Load floor plan
    function loadFloorPlan() {
        if (!currentEventId) return;

        $('#floor-plan-loading').show();
        $('#floor-plan-grid, #floor-plan-empty').hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booths_get_floor_plan',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                event_id: currentEventId,
                floor_level: $('#floor-selector').val()
            },
            success: function(response) {
                $('#floor-plan-loading').hide();

                if (response.success && response.data.booths && response.data.booths.length > 0) {
                    renderFloorPlan(response.data.booths);
                    $('#floor-plan-grid').show();
                } else {
                    $('#floor-plan-empty').show();
                }
            },
            error: function() {
                $('#floor-plan-loading').hide();
                $('#floor-plan-empty').show();
            }
        });
    }

    // Render floor plan
    function renderFloorPlan(booths) {
        var container = $('#floor-plan-grid');
        container.empty();

        var containerWidth = container.width() - 40; // Padding
        var containerHeight = 560; // 600 - 40 padding
        var scale = 15; // pixels per meter
        var maxBoothWidth = 150; // Maximum booth width in pixels
        var maxBoothHeight = 150; // Maximum booth height in pixels
        var minBoothSize = 45; // Minimum booth size in pixels

        booths.forEach(function(booth) {
            // Calculate size with constraints
            var rawWidth = (parseFloat(booth.width) || 3) * scale;
            var rawHeight = (parseFloat(booth.depth) || 3) * scale;
            var width = Math.min(Math.max(rawWidth, minBoothSize), maxBoothWidth);
            var height = Math.min(Math.max(rawHeight, minBoothSize), maxBoothHeight);

            // Calculate position - ensure booths stay within container
            var left = booth.position_x ? parseFloat(booth.position_x) * 10 : Math.random() * (containerWidth - width);
            var top = booth.position_y ? parseFloat(booth.position_y) * 6 : Math.random() * (containerHeight - height);

            // Ensure booth stays within boundaries
            left = Math.max(0, Math.min(left, containerWidth - width));
            top = Math.max(0, Math.min(top, containerHeight - height));

            var boothEl = $('<div class="booth-item status-' + booth.status + '" data-id="' + booth.id + '"></div>');
            boothEl.css({
                width: width + 'px',
                height: height + 'px',
                left: left + 'px',
                top: top + 'px',
                backgroundColor: booth.color || undefined
            });
            boothEl.text(booth.booth_number);
            var sizeInfo = (booth.width || 3) + 'm x ' + (booth.depth || 3) + 'm';
            boothEl.attr('title', booth.booth_number + ' - ' + (booth.booth_category || 'No type') + ' (' + sizeInfo + ')');

            container.append(boothEl);
        });

        // Make booths draggable (if jQuery UI is available)
        if (typeof $.fn.draggable !== 'undefined') {
            $('.booth-item').draggable({
                containment: '#floor-plan-grid',
                stop: function(event, ui) {
                    var boothId = $(this).data('id');
                    var position = ui.position;

                    // Save position
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sc_booths_update_position',
                            nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                            id: boothId,
                            position_x: Math.round(position.left / 10),
                            position_y: Math.round(position.top / 6)
                        }
                    });
                }
            });
        }

        // Click for details
        $('.booth-item').on('click', function() {
            var boothId = $(this).data('id');
            showBoothDetails(boothId);
        });
    }

    // Show booth details
    function showBoothDetails(boothId) {
        $('#booth-details-content').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
        $('#btn-edit-booth').attr('href', '<?php echo home_url('/event-manager-dashboard/booth-edit'); ?>?id=' + boothId);
        $('#boothDetailsModal').modal('show');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booths_get',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                id: boothId
            },
            success: function(response) {
                if (response.success && response.data.booth) {
                    var booth = response.data.booth;
                    var html = '<table class="table table-sm">';
                    html += '<tr><th>Booth #</th><td>' + escapeHtml(booth.booth_number) + '</td></tr>';
                    html += '<tr><th>Name</th><td>' + escapeHtml(booth.booth_name || '-') + '</td></tr>';
                    html += '<tr><th>Type</th><td>' + escapeHtml(booth.type_name || '-') + '</td></tr>';
                    html += '<tr><th>Size</th><td>' + (booth.width || 3) + 'm x ' + (booth.depth || 3) + 'm</td></tr>';
                    html += '<tr><th>Status</th><td><span class="badge badge-' + getStatusColor(booth.status) + '">' + booth.status + '</span></td></tr>';
                    html += '<tr><th>Company</th><td>' + escapeHtml(booth.company_name || '-') + '</td></tr>';
                    html += '<tr><th>Floor</th><td>' + (booth.floor_level || 1) + '</td></tr>';
                    html += '</table>';
                    $('#booth-details-content').html(html);
                }
            }
        });
    }

    function getStatusColor(status) {
        var colors = {
            'available': 'success',
            'reserved': 'warning',
            'booked': 'info',
            'occupied': 'primary',
            'unavailable': 'danger'
        };
        return colors[status] || 'secondary';
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initial load
    if (currentEventId) {
        loadFloorPlan();
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
