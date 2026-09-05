<?php
/**
 * Dashboard Create Booth Page
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

$page_title = sc_t('booths.add_booth', 'Add Booth');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'add_booth' => sc_t('booths.add_booth', 'Add Booth'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'booth_info' => sc_t('booths.booth_info', 'Booth Information'),
    'location_amenities' => sc_t('booths.location_amenities', 'Location & Amenities'),
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
                    <h2><?php echo $t['add_booth']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>"><?php echo $t['booths']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['add_booth']; ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-th-large"></i> <?php echo $t['booth_info']; ?></h2>
                    </div>
                    <div class="body">
                        <form id="booth-form">
                            <!-- Event Selector -->
                            <div class="form-group">
                                <label><strong><?php echo $t['select_event']; ?> *</strong></label>
                                <select id="event_id" name="event_id" class="form-control" required>
                                    <option value="">-- <?php echo $t['select_event']; ?> --</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>" <?php selected($event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Number *</label>
                                        <input type="text" class="form-control" id="booth_number" name="booth_number" required placeholder="e.g., A1, B12">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Type *</label>
                                        <select class="form-control" id="booth_type_id" name="booth_type_id" required>
                                            <option value="">Select Type</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Name</label>
                                        <input type="text" class="form-control" id="booth_name" name="booth_name" placeholder="Optional display name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="available">Available</option>
                                            <option value="reserved">Reserved</option>
                                            <option value="unavailable">Unavailable</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Price (SAR)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_price" name="custom_price" placeholder="Override type price">
                                        <small class="text-muted">Leave empty to use type price</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Width (m)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_width" name="custom_width" placeholder="Override type width">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Depth (m)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_depth" name="custom_depth" placeholder="Override type depth">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Internal notes about this booth"></textarea>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-map-marker"></i> <?php echo $t['location_amenities']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label>Floor Level</label>
                            <input type="number" class="form-control" id="floor_level" name="floor_level" value="1" min="1">
                        </div>

                        <div class="form-group">
                            <label>Zone/Section</label>
                            <input type="text" class="form-control" id="zone" name="zone" placeholder="e.g., Hall A, Section B">
                        </div>

                        <hr>

                        <div class="form-group">
                            <label class="d-block mb-2">Amenities</label>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_electricity" name="has_electricity" value="1" checked>
                                <label class="custom-control-label" for="has_electricity"><i class="fa fa-bolt text-warning"></i> Electricity</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_wifi" name="has_wifi" value="1" checked>
                                <label class="custom-control-label" for="has_wifi"><i class="fa fa-wifi text-primary"></i> WiFi</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_water" name="has_water" value="1">
                                <label class="custom-control-label" for="has_water"><i class="fa fa-tint text-info"></i> Water</label>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1">
                                <label class="custom-control-label" for="is_featured"><i class="fa fa-star text-warning"></i> Featured Booth</label>
                            </div>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-success btn-block btn-lg" id="btn-save">
                            <i class="fa fa-save"></i> <?php echo $t['save']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/booths'); ?><?php echo $event_id ? '?event_id=' . $event_id : ''; ?>" class="btn btn-secondary btn-block">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['cancel']; ?>
                        </a>
                    </div>
                </div>

                <!-- Type Info Card -->
                <div class="card" id="type-info-card" style="display:none;">
                    <div class="header">
                        <h2><i class="fa fa-info-circle"></i> Type Details</h2>
                    </div>
                    <div class="body">
                        <div id="type-info-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var boothTypes = [];

    // Load booth types when event changes
    $('#event_id').on('change', function() {
        var eventId = $(this).val();
        if (eventId) {
            loadBoothTypes(eventId);
        } else {
            $('#booth_type_id').html('<option value="">Select Type</option>');
            $('#type-info-card').hide();
        }
    });

    // Load types on page load if event is selected
    var initialEventId = <?php echo $event_id ?: 0; ?>;
    if (initialEventId) {
        loadBoothTypes(initialEventId);
    }

    function loadBoothTypes(eventId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_types_get_all',
                nonce: nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    boothTypes = response.data.booth_types;
                    var html = '<option value="">Select Type</option>';
                    boothTypes.forEach(function(t) {
                        if (t.is_active == 1) {
                            html += '<option value="' + t.id + '">' + escapeHtml(t.name) + ' (' + t.size_code + ') - SAR ' + parseFloat(t.base_price).toLocaleString() + '</option>';
                        }
                    });
                    $('#booth_type_id').html(html);
                }
            }
        });
    }

    // Show type info when type is selected
    $('#booth_type_id').on('change', function() {
        var typeId = $(this).val();
        if (typeId) {
            var type = boothTypes.find(function(t) { return t.id == typeId; });
            if (type) {
                var html = '<table class="table table-sm">';
                html += '<tr><th>Size</th><td>' + type.width_meters + 'm x ' + type.depth_meters + 'm</td></tr>';
                html += '<tr><th>Category</th><td>' + type.booth_category + '</td></tr>';
                html += '<tr><th>Base Price</th><td>SAR ' + parseFloat(type.base_price).toLocaleString() + '</td></tr>';
                html += '<tr><th>Deposit</th><td>' + type.deposit_percentage + '%</td></tr>';
                if (type.description) {
                    html += '<tr><th>Description</th><td>' + escapeHtml(type.description) + '</td></tr>';
                }
                html += '</table>';
                $('#type-info-content').html(html);
                $('#type-info-card').show();
            }
        } else {
            $('#type-info-card').hide();
        }
    });

    // Save booth
    $('#btn-save').on('click', function() {
        var $btn = $(this);
        var eventId = $('#event_id').val();
        var boothTypeId = $('#booth_type_id').val();
        var boothNumber = $('#booth_number').val();

        if (!eventId) {
            toastr.error('Please select an event');
            return;
        }
        if (!boothTypeId) {
            toastr.error('Please select a booth type');
            return;
        }
        if (!boothNumber) {
            toastr.error('Please enter a booth number');
            return;
        }

        var data = {
            action: 'sc_booths_create',
            nonce: nonce,
            event_id: eventId,
            booth_type_id: boothTypeId,
            booth_number: boothNumber,
            booth_name: $('#booth_name').val(),
            status: $('#status').val(),
            custom_price: $('#custom_price').val(),
            custom_width: $('#custom_width').val(),
            custom_depth: $('#custom_depth').val(),
            floor_level: $('#floor_level').val(),
            zone: $('#zone').val(),
            has_electricity: $('#has_electricity').is(':checked') ? 1 : 0,
            has_wifi: $('#has_wifi').is(':checked') ? 1 : 0,
            has_water: $('#has_water').is(':checked') ? 1 : 0,
            is_featured: $('#is_featured').is(':checked') ? 1 : 0,
            notes: $('#notes').val()
        };

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    window.location.href = '<?php echo home_url('/event-manager-dashboard/booths'); ?>?event_id=' + eventId;
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
            }
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
