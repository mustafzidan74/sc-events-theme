<?php
/**
 * Dashboard Edit Booth Type Page
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

// Get booth type ID
$type_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$type_id) {
    wp_redirect(home_url('/event-manager-dashboard/booth-types'));
    exit;
}

// Get booth type data
global $wpdb;
$type = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_booth_types WHERE id = %d",
    $type_id
));

if (!$type) {
    wp_redirect(home_url('/event-manager-dashboard/booth-types'));
    exit;
}

$event_id = $type->event_id;

// Get booth count for this type
$booth_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}sc_booths WHERE booth_type_id = %d",
    $type_id
));

$page_title = sc_t('booths.edit_booth_type', 'Edit Booth Type');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'booth_types' => sc_t('booths.booth_types', 'Booth Types'),
    'edit_booth_type' => sc_t('booths.edit_booth_type', 'Edit Booth Type'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'type_info' => sc_t('booths.type_info', 'Type Information'),
    'pricing' => sc_t('booths.pricing', 'Pricing'),
    'dimensions' => sc_t('booths.dimensions', 'Dimensions'),
);
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['edit_booth_type']; ?>: <?php echo esc_html($type->name); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booth-types'); ?>?event_id=<?php echo $event_id; ?>"><?php echo $t['booth_types']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['edit_booth_type']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 text-right">
                    <?php if ($type->is_active): ?>
                        <span class="badge badge-success" style="font-size: 14px; padding: 8px 15px;">Active</span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size: 14px; padding: 8px 15px;">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-tags"></i> <?php echo $t['type_info']; ?></h2>
                    </div>
                    <div class="body">
                        <form id="booth-type-form">
                            <input type="hidden" id="booth_type_id" value="<?php echo $type_id; ?>">
                            <input type="hidden" id="event_id" value="<?php echo $event_id; ?>">

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Type Name * <?php echo sc_is_rtl() ? '<small class="text-muted">(الاسم)</small>' : ''; ?></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo esc_attr($type->name); ?>" required <?php echo sc_is_rtl() ? 'dir="rtl"' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Color</label>
                                        <input type="color" class="form-control" id="color" name="color" value="<?php echo esc_attr($type->color ?: '#3B82F6'); ?>" style="height: 38px;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo esc_textarea($type->description); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <select class="form-control" id="booth_category" name="booth_category">
                                            <option value="standard" <?php selected($type->booth_category, 'standard'); ?>>Standard</option>
                                            <option value="corner" <?php selected($type->booth_category, 'corner'); ?>>Corner</option>
                                            <option value="island" <?php selected($type->booth_category, 'island'); ?>>Island</option>
                                            <option value="peninsula" <?php selected($type->booth_category, 'peninsula'); ?>>Peninsula</option>
                                            <option value="inline" <?php selected($type->booth_category, 'inline'); ?>>Inline</option>
                                            <option value="custom" <?php selected($type->booth_category, 'custom'); ?>>Custom</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Total Quantity</label>
                                        <input type="number" class="form-control" id="total_quantity" name="total_quantity" value="<?php echo $type->total_quantity; ?>" min="0">
                                        <small class="text-muted">0 = Unlimited | Currently used: <?php echo $booth_count; ?></small>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Dimensions Card -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-arrows-alt"></i> <?php echo $t['dimensions']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Size Code *</label>
                                    <select class="form-control" id="size_code" name="size_code">
                                        <option value="3x3" <?php selected($type->size_code, '3x3'); ?>>3x3m (9m²)</option>
                                        <option value="3x4" <?php selected($type->size_code, '3x4'); ?>>3x4m (12m²)</option>
                                        <option value="4x4" <?php selected($type->size_code, '4x4'); ?>>4x4m (16m²)</option>
                                        <option value="6x3" <?php selected($type->size_code, '6x3'); ?>>6x3m (18m²)</option>
                                        <option value="6x6" <?php selected($type->size_code, '6x6'); ?>>6x6m (36m²)</option>
                                        <option value="9x6" <?php selected($type->size_code, '9x6'); ?>>9x6m (54m²)</option>
                                        <option value="9x9" <?php selected($type->size_code, '9x9'); ?>>9x9m (81m²)</option>
                                        <option value="12x9" <?php selected($type->size_code, '12x9'); ?>>12x9m (108m²)</option>
                                        <option value="custom" <?php selected($type->size_code, 'custom'); ?>>Custom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Width (meters)</label>
                                    <input type="number" step="0.01" class="form-control" id="width_meters" name="width_meters" value="<?php echo $type->width_meters; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Depth (meters)</label>
                                    <input type="number" step="0.01" class="form-control" id="depth_meters" name="depth_meters" value="<?php echo $type->depth_meters; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3" id="size-preview">
                            <i class="fa fa-info-circle"></i>
                            <strong>Total Area:</strong> <span id="total-area"><?php echo $type->width_meters * $type->depth_meters; ?></span> m² |
                            <strong>Dimensions:</strong> <span id="dimensions-text"><?php echo $type->width_meters; ?>m x <?php echo $type->depth_meters; ?>m</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <!-- Pricing Card -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-money"></i> <?php echo $t['pricing']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label>Base Price (SAR) *</label>
                            <input type="number" step="0.01" class="form-control" id="base_price" name="base_price" value="<?php echo $type->base_price; ?>" min="0">
                        </div>

                        <div class="form-group">
                            <label>Deposit Percentage (%)</label>
                            <input type="number" step="0.01" class="form-control" id="deposit_percentage" name="deposit_percentage" value="<?php echo $type->deposit_percentage; ?>" min="0" max="100">
                        </div>

                        <div class="alert alert-secondary mt-3">
                            <small>
                                <strong>Deposit Amount:</strong> SAR <span id="deposit-amount"><?php echo number_format($type->base_price * $type->deposit_percentage / 100, 2); ?></span>
                            </small>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1" <?php selected($type->is_active, 1); ?>>Active</option>
                                <option value="0" <?php selected($type->is_active, 0); ?>>Inactive</option>
                            </select>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-success btn-block btn-lg" id="btn-save">
                            <i class="fa fa-save"></i> <?php echo $t['save']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/booth-types'); ?>?event_id=<?php echo $event_id; ?>" class="btn btn-secondary btn-block">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['cancel']; ?>
                        </a>
                        <button type="button" class="btn btn-danger btn-block" id="btn-delete" <?php echo $booth_count > 0 ? 'disabled title="Cannot delete: Type has booths"' : ''; ?>>
                            <i class="fa fa-trash"></i> <?php echo $t['delete']; ?>
                        </button>
                        <?php if ($booth_count > 0): ?>
                        <small class="text-danger d-block text-center mt-2">
                            <i class="fa fa-warning"></i> Cannot delete: <?php echo $booth_count; ?> booth(s) using this type
                        </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Type Stats Card -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-bar-chart"></i> Type Statistics</h2>
                    </div>
                    <div class="body">
                        <table class="table table-sm">
                            <tr>
                                <th>Total Booths</th>
                                <td><?php echo $booth_count; ?></td>
                            </tr>
                            <tr>
                                <th>Available Qty</th>
                                <td><?php echo $type->available_quantity; ?></td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td><?php echo date('M j, Y', strtotime($type->created_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Updated</th>
                                <td><?php echo date('M j, Y', strtotime($type->updated_at)); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var typeId = <?php echo $type_id; ?>;
    var eventId = <?php echo $event_id; ?>;

    // Size code change
    $('#size_code').on('change', function() {
        var sizes = {
            '3x3': [3, 3], '3x4': [3, 4], '4x4': [4, 4],
            '6x3': [6, 3], '6x6': [6, 6], '9x6': [9, 6],
            '9x9': [9, 9], '12x9': [12, 9]
        };
        var size = sizes[$(this).val()];
        if (size) {
            $('#width_meters').val(size[0]);
            $('#depth_meters').val(size[1]);
        }
        updateSizePreview();
    });

    // Update size preview
    function updateSizePreview() {
        var width = parseFloat($('#width_meters').val()) || 0;
        var depth = parseFloat($('#depth_meters').val()) || 0;
        var area = width * depth;
        $('#total-area').text(area.toFixed(2));
        $('#dimensions-text').text(width + 'm x ' + depth + 'm');
    }

    $('#width_meters, #depth_meters').on('input', updateSizePreview);

    // Update deposit amount
    function updateDepositAmount() {
        var price = parseFloat($('#base_price').val()) || 0;
        var percentage = parseFloat($('#deposit_percentage').val()) || 0;
        var deposit = (price * percentage / 100).toFixed(2);
        $('#deposit-amount').text(parseFloat(deposit).toLocaleString());
    }

    $('#base_price, #deposit_percentage').on('input', updateDepositAmount);

    // Save booth type
    $('#btn-save').on('click', function() {
        var $btn = $(this);
        var name = $('#name').val();

        if (!name) {
            toastr.error('Please enter a type name');
            return;
        }

        var data = {
            action: 'sc_booth_types_update',
            nonce: nonce,
            booth_type_id: typeId,
            event_id: eventId,
            name: name,
            description: $('#description').val(),
            booth_category: $('#booth_category').val(),
            size_code: $('#size_code').val(),
            width_meters: $('#width_meters').val(),
            depth_meters: $('#depth_meters').val(),
            base_price: $('#base_price').val(),
            deposit_percentage: $('#deposit_percentage').val(),
            total_quantity: $('#total_quantity').val(),
            color: $('#color').val(),
            is_active: $('#is_active').val()
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

    // Delete booth type
    $('#btn-delete').on('click', function() {
        if (!confirm('Are you sure you want to delete this booth type? This action cannot be undone.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_types_delete',
                nonce: nonce,
                booth_type_id: typeId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    window.location.href = '<?php echo home_url('/event-manager-dashboard/booth-types'); ?>?event_id=' + eventId;
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
