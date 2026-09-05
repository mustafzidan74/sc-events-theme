<?php
/**
 * Dashboard Edit Booth Page
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

// Get booth ID
$booth_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$booth_id) {
    wp_redirect(home_url('/event-manager-dashboard/booths'));
    exit;
}

// Get booth data
global $wpdb;
$booth = $wpdb->get_row($wpdb->prepare(
    "SELECT b.*, bt.name as type_name, bt.size_code, bt.width_meters as type_width, bt.depth_meters as type_depth, bt.base_price as type_price
     FROM {$wpdb->prefix}sc_booths b
     LEFT JOIN {$wpdb->prefix}sc_booth_types bt ON b.booth_type_id = bt.id
     WHERE b.id = %d",
    $booth_id
));

if (!$booth) {
    wp_redirect(home_url('/event-manager-dashboard/booths'));
    exit;
}

$event_id = $booth->event_id;

$page_title = sc_t('booths.edit_booth', 'Edit Booth');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'edit_booth' => sc_t('booths.edit_booth', 'Edit Booth'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'booth_info' => sc_t('booths.booth_info', 'Booth Information'),
    'location_amenities' => sc_t('booths.location_amenities', 'Location & Amenities'),
);

// Get booth types for this event
$booth_types = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_booth_types WHERE event_id = %d ORDER BY name",
    $event_id
));
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['edit_booth']; ?>: <?php echo esc_html($booth->booth_number); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>?event_id=<?php echo $event_id; ?>"><?php echo $t['booths']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['edit_booth']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 text-right">
                    <span class="badge badge-<?php echo $booth->status; ?>" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo ucfirst($booth->status); ?>
                    </span>
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
                            <input type="hidden" id="booth_id" value="<?php echo $booth_id; ?>">
                            <input type="hidden" id="event_id" value="<?php echo $event_id; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Number *</label>
                                        <input type="text" class="form-control" id="booth_number" name="booth_number" value="<?php echo esc_attr($booth->booth_number); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Type *</label>
                                        <select class="form-control" id="booth_type_id" name="booth_type_id" required>
                                            <option value="">Select Type</option>
                                            <?php foreach ($booth_types as $type): ?>
                                                <option value="<?php echo $type->id; ?>" <?php selected($booth->booth_type_id, $type->id); ?>>
                                                    <?php echo esc_html($type->name); ?> (<?php echo $type->size_code; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth Name</label>
                                        <input type="text" class="form-control" id="booth_name" name="booth_name" value="<?php echo esc_attr($booth->booth_name); ?>" placeholder="Optional display name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="available" <?php selected($booth->status, 'available'); ?>>Available</option>
                                            <option value="reserved" <?php selected($booth->status, 'reserved'); ?>>Reserved</option>
                                            <option value="booked" <?php selected($booth->status, 'booked'); ?>>Booked</option>
                                            <option value="occupied" <?php selected($booth->status, 'occupied'); ?>>Occupied</option>
                                            <option value="unavailable" <?php selected($booth->status, 'unavailable'); ?>>Unavailable</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Price (SAR)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_price" name="custom_price" value="<?php echo $booth->custom_price; ?>" placeholder="Override type price">
                                        <small class="text-muted">Type price: SAR <?php echo number_format($booth->type_price ?: 0, 2); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Width (m)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_width" name="custom_width" value="<?php echo $booth->custom_width; ?>" placeholder="Override type width">
                                        <small class="text-muted">Type width: <?php echo $booth->type_width ?: 3; ?>m</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Custom Depth (m)</label>
                                        <input type="number" step="0.01" class="form-control" id="custom_depth" name="custom_depth" value="<?php echo $booth->custom_depth; ?>" placeholder="Override type depth">
                                        <small class="text-muted">Type depth: <?php echo $booth->type_depth ?: 3; ?>m</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo esc_textarea($booth->notes); ?></textarea>
                            </div>

                            <?php if ($booth->company_id): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-building"></i>
                                <strong>Assigned Company:</strong>
                                <?php
                                $company = $wpdb->get_row($wpdb->prepare(
                                    "SELECT company_name FROM {$wpdb->prefix}sc_companies WHERE id = %d",
                                    $booth->company_id
                                ));
                                echo esc_html($company ? $company->company_name : 'Unknown');
                                ?>
                            </div>
                            <?php endif; ?>
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
                            <input type="number" class="form-control" id="floor_level" name="floor_level" value="<?php echo $booth->floor_level ?: 1; ?>" min="1">
                        </div>

                        <div class="form-group">
                            <label>Zone/Section</label>
                            <input type="text" class="form-control" id="zone" name="zone" value="<?php echo esc_attr($booth->zone); ?>" placeholder="e.g., Hall A, Section B">
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Position X</label>
                                    <input type="number" class="form-control" id="position_x" name="position_x" value="<?php echo $booth->position_x; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Position Y</label>
                                    <input type="number" class="form-control" id="position_y" name="position_y" value="<?php echo $booth->position_y; ?>">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label class="d-block mb-2">Amenities</label>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_electricity" name="has_electricity" value="1" <?php checked($booth->has_electricity, 1); ?>>
                                <label class="custom-control-label" for="has_electricity"><i class="fa fa-bolt text-warning"></i> Electricity</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_wifi" name="has_wifi" value="1" <?php checked($booth->has_wifi, 1); ?>>
                                <label class="custom-control-label" for="has_wifi"><i class="fa fa-wifi text-primary"></i> WiFi</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="has_water" name="has_water" value="1" <?php checked($booth->has_water, 1); ?>>
                                <label class="custom-control-label" for="has_water"><i class="fa fa-tint text-info"></i> Water</label>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1" <?php checked($booth->is_featured, 1); ?>>
                                <label class="custom-control-label" for="is_featured"><i class="fa fa-star text-warning"></i> Featured Booth</label>
                            </div>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-success btn-block btn-lg" id="btn-save">
                            <i class="fa fa-save"></i> <?php echo $t['save']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>?event_id=<?php echo $event_id; ?>" class="btn btn-secondary btn-block">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['cancel']; ?>
                        </a>
                        <button type="button" class="btn btn-danger btn-block" id="btn-delete">
                            <i class="fa fa-trash"></i> <?php echo $t['delete']; ?>
                        </button>
                    </div>
                </div>

                <!-- Booth Info Card -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-info-circle"></i> Booth Details</h2>
                    </div>
                    <div class="body">
                        <table class="table table-sm">
                            <tr>
                                <th>Created</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($booth->created_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Updated</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($booth->updated_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Type</th>
                                <td><?php echo esc_html($booth->type_name ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Effective Size</th>
                                <td><?php echo ($booth->custom_width ?: $booth->type_width ?: 3); ?>m x <?php echo ($booth->custom_depth ?: $booth->type_depth ?: 3); ?>m</td>
                            </tr>
                            <tr>
                                <th>Effective Price</th>
                                <td>SAR <?php echo number_format($booth->custom_price ?: $booth->type_price ?: 0, 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-available { background: #22c55e; color: #fff; }
.badge-reserved { background: #f59e0b; color: #fff; }
.badge-booked { background: #3b82f6; color: #fff; }
.badge-occupied { background: #8b5cf6; color: #fff; }
.badge-unavailable { background: #ef4444; color: #fff; }
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var boothId = <?php echo $booth_id; ?>;
    var eventId = <?php echo $event_id; ?>;

    // Save booth
    $('#btn-save').on('click', function() {
        var $btn = $(this);
        var boothNumber = $('#booth_number').val();
        var boothTypeId = $('#booth_type_id').val();

        if (!boothNumber) {
            toastr.error('Please enter a booth number');
            return;
        }
        if (!boothTypeId) {
            toastr.error('Please select a booth type');
            return;
        }

        var data = {
            action: 'sc_booths_update',
            nonce: nonce,
            booth_id: boothId,
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
            position_x: $('#position_x').val(),
            position_y: $('#position_y').val(),
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
                } else {
                    toastr.error(response.data.message);
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
            }
        });
    });

    // Delete booth
    $('#btn-delete').on('click', function() {
        if (!confirm('Are you sure you want to delete this booth? This action cannot be undone.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booths_delete',
                nonce: nonce,
                booth_id: boothId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    window.location.href = '<?php echo home_url('/event-manager-dashboard/booths'); ?>?event_id=' + eventId;
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> <?php echo esc_js($t['delete']); ?>');
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> <?php echo esc_js($t['delete']); ?>');
            }
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
