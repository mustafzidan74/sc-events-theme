<?php
/**
 * Dashboard - Edit Coupon Page
 * Standalone page for editing coupon details
 * Uses WordPress Posts (sc_coupon post type) for consistency
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

// Get coupon ID from URL
$coupon_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$coupon_id) {
    wp_redirect(home_url('/event-manager-dashboard/coupons'));
    exit;
}

// Get coupon data from WordPress Posts
$coupon_post = get_post($coupon_id);

if (!$coupon_post || $coupon_post->post_type !== 'sc_coupon') {
    wp_redirect(home_url('/event-manager-dashboard/coupons'));
    exit;
}

// Build coupon object from post and meta
$coupon = new stdClass();
$coupon->id = $coupon_post->ID;
$coupon->code = $coupon_post->post_title;
$coupon->name = get_post_meta($coupon_id, 'coupon_name', true);
$coupon->description = $coupon_post->post_content;
$coupon->discount_type = get_post_meta($coupon_id, 'discount_type', true) ?: 'percentage';
$coupon->discount_value = floatval(get_post_meta($coupon_id, 'discount_value', true));
$coupon->usage_limit = intval(get_post_meta($coupon_id, 'usage_limit', true));
$coupon->usage_count = intval(get_post_meta($coupon_id, 'usage_count', true));
$coupon->min_purchase = floatval(get_post_meta($coupon_id, 'min_purchase', true));
$coupon->max_discount = floatval(get_post_meta($coupon_id, 'max_discount', true));
$coupon->per_user_limit = intval(get_post_meta($coupon_id, 'per_user_limit', true)) ?: 1;
$coupon->event_id = intval(get_post_meta($coupon_id, 'event_id', true));
$coupon->category_id = intval(get_post_meta($coupon_id, 'category_id', true));
if ($coupon->category_id <= 0) { $coupon->category_id = 1; }
$coupon->expiry_date = get_post_meta($coupon_id, 'expiry_date', true);
$coupon->start_date = get_post_meta($coupon_id, 'start_date', true);
$coupon->end_date = $coupon->expiry_date; // Alias
$coupon->is_active = ($coupon_post->post_status === 'publish') ? 1 : 0;
$coupon->created_at = $coupon_post->post_date;
$coupon->updated_at = $coupon_post->post_modified;
$coupon->total_discount = floatval(get_post_meta($coupon_id, 'total_discount', true));
$coupon->ticket_type_filter = get_post_meta($coupon_id, 'ticket_type_filter', true) ?: 'all';

$page_title = 'Edit Coupon: ' . esc_html($coupon->code);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get all events for selection (use custom table or WP posts)
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => 'publish',
        'limit' => 500,
        'orderby' => 'start_date',
        'order' => 'DESC'
    ));
}

// Coupon categories
$coupon_categories = class_exists('SC_Coupon_Category') ? SC_Coupon_Category::get_all() : array();

// Get coupon's assigned events from meta
$coupon_events = get_post_meta($coupon_id, 'allowed_events', true);
if (!is_array($coupon_events)) {
    $coupon_events = array();
    // If single event_id is set
    if ($coupon->event_id > 0) {
        $coupon_events = array($coupon->event_id);
    }
}

// Get usage history from attendees who used this coupon
global $wpdb;
$usage_history = array();
// Try to get from sc_attendees table if it exists
$attendees_table = $wpdb->prefix . 'sc_attendees';
if ($wpdb->get_var("SHOW TABLES LIKE '$attendees_table'") === $attendees_table) {
    $usage_history = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, e.title as event_title
         FROM {$wpdb->prefix}sc_attendees a
         LEFT JOIN {$wpdb->prefix}sc_events e ON a.event_id = e.id
         WHERE a.coupon_code = %s
         ORDER BY a.created_at DESC
         LIMIT 20",
        $coupon->code
    ));
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2>Edit Coupon</h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>">Coupons</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left"></i> Back to Coupons
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="coupon-edit-form" class="coupon-form">
            <?php wp_nonce_field('sc_coupon_action', 'sc_coupon_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_coupon">
            <input type="hidden" name="coupon_id" value="<?php echo $coupon_id; ?>">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-tag"></i> Coupon Details</h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="coupon-code">Coupon Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="coupon-code" name="code" required value="<?php echo esc_attr($coupon->code); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Discount Configuration -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-percent"></i> Discount Configuration</h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="coupon-discount-type">Discount Type <span class="text-danger">*</span></label>
                                        <select class="form-control" id="coupon-discount-type" name="discount_type" required>
                                            <option value="percentage" <?php selected($coupon->discount_type, 'percentage'); ?>>Percentage (%)</option>
                                            <option value="fixed" <?php selected($coupon->discount_type, 'fixed'); ?>>Fixed Amount</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="coupon-discount-value">Discount Value <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="coupon-discount-value" name="discount_value" min="0" step="0.01" required value="<?php echo $coupon->discount_value; ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text" id="discount-suffix"><?php echo $coupon->discount_type === 'percentage' ? '%' : sc_get_currency_symbol(); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Usage Limits -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sliders"></i> Usage Limits</h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="coupon-usage-limit">Total Usage Limit</label>
                                        <input type="number" class="form-control" id="coupon-usage-limit" name="usage_limit" min="0" value="<?php echo $coupon->usage_limit ?: 0; ?>">
                                        <small class="text-muted">0 = unlimited</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="coupon-per-user-limit">Per User Limit</label>
                                        <input type="number" class="form-control" id="coupon-per-user-limit" name="per_user_limit" min="0" value="<?php echo $coupon->per_user_limit ?: 1; ?>">
                                        <small class="text-muted">0 = unlimited</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Event Restrictions -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-calendar"></i> Event Restrictions</h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label>Apply to Events</label>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" id="apply-all-events" name="event_restriction" value="all" <?php echo empty($coupon_events) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="apply-all-events">All Events</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" id="apply-specific-events" name="event_restriction" value="specific" <?php echo !empty($coupon_events) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="apply-specific-events">Specific Events Only</label>
                                </div>
                            </div>

                            <div class="form-group" id="events-selection" style="<?php echo empty($coupon_events) ? 'display:none;' : ''; ?>">
                                <label for="coupon-events">Select Event</label>
                                <select class="form-control" id="coupon-events" name="event_id" data-placeholder="Select an event...">
                                    <option value=""></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>" <?php echo in_array($event->id, $coupon_events) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($event->title); ?> (<?php echo date('M d, Y', strtotime($event->start_date)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mt-3">
                                <label for="coupon-category"><i class="fa fa-folder mr-1"></i> <?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                                <select class="form-control" id="coupon-category" name="category_id">
                                    <?php foreach ($coupon_categories as $cat): ?>
                                        <option value="<?php echo (int) $cat->id; ?>" <?php selected((int) $cat->id, (int) $coupon->category_id); ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><a href="<?php echo esc_url(home_url('/event-manager-dashboard/coupon-categories')); ?>" target="_blank"><?php echo esc_html(sc_t('dashboard_pages.manage_categories', 'Manage Categories')); ?></a></small>
                            </div>

                            <div class="form-group mt-3">
                                <label for="ticket-type-filter"><i class="fa fa-filter mr-1"></i> <?php echo esc_html(sc_t('coupons.ticket_type_filter', 'Ticket Type')); ?></label>
                                <select class="form-control" id="ticket-type-filter" name="ticket_type_filter">
                                    <option value="all" <?php selected($coupon->ticket_type_filter, 'all'); ?>><?php echo esc_html(sc_t('coupons.all_ticket_types', 'All Ticket Types')); ?></option>
                                    <option value="general" <?php selected($coupon->ticket_type_filter, 'general'); ?>><?php echo esc_html(sc_t('coupons.general_only', 'General (Attendees) Only')); ?></option>
                                    <option value="competitor" <?php selected($coupon->ticket_type_filter, 'competitor'); ?>><?php echo esc_html(sc_t('coupons.competitor_only', 'Competitor Only')); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Usage History -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-history"></i> Usage History</h2>
                        </div>
                        <div class="body">
                            <?php if (empty($usage_history)): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> This coupon has not been used yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Event</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usage_history as $usage): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo esc_html($usage->name ?: 'Unknown'); ?>
                                                        <?php if (!empty($usage->email)): ?>
                                                            <br><small class="text-muted"><?php echo esc_html($usage->email); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($usage->event_title ?: 'Unknown Event'); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($usage->created_at)); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Submit Box -->
                    <div class="card">
                        <div class="header bg-primary">
                            <h2 class="text-white"><i class="fa fa-save"></i> Update Coupon</h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="coupon-status">Status</label>
                                <select class="form-control" id="coupon-status" name="is_active">
                                    <option value="1" <?php selected($coupon->is_active, 1); ?>>Active</option>
                                    <option value="0" <?php selected($coupon->is_active, 0); ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($coupon->created_at)); ?><br>
                                    <i class="fa fa-refresh"></i> Updated: <?php echo date('M d, Y', strtotime($coupon->updated_at)); ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-coupon-btn">
                                    <i class="fa fa-save"></i> Update Coupon
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-bar-chart"></i> Statistics</h2>
                        </div>
                        <div class="body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h3 class="text-primary mb-0"><?php echo $coupon->usage_count ?: 0; ?></h3>
                                    <small class="text-muted">Times Used</small>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-success mb-0"><?php echo number_format($coupon->total_discount ?: 0, 2); ?></h3>
                                    <small class="text-muted">Total Discount</small>
                                </div>
                            </div>

                            <?php if ($coupon->usage_limit > 0): ?>
                            <hr>
                            <div class="progress mb-2" style="height: 20px;">
                                <?php $usage_percent = min(100, round(($coupon->usage_count / $coupon->usage_limit) * 100)); ?>
                                <div class="progress-bar bg-info" style="width: <?php echo $usage_percent; ?>%">
                                    <?php echo $usage_percent; ?>%
                                </div>
                            </div>
                            <small class="text-muted"><?php echo $coupon->usage_count; ?> of <?php echo $coupon->usage_limit; ?> uses</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Validity Period -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-clock-o"></i> Validity Period</h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="coupon-end-date">End Date</label>
                                <input type="datetime-local" class="form-control" id="coupon-end-date" name="end_date" value="<?php echo $coupon->end_date ? date('Y-m-d\TH:i', strtotime($coupon->end_date)) : ''; ?>">
                            </div>

                            <?php
                            $now = current_time('mysql');
                            $is_expired = $coupon->end_date && $coupon->end_date < $now;
                            ?>

                            <?php if ($is_expired): ?>
                                <div class="alert alert-danger mb-0">
                                    <i class="fa fa-warning"></i> This coupon has expired!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="card border-danger">
                        <div class="header bg-danger">
                            <h2 class="text-white"><i class="fa fa-warning"></i> Danger Zone</h2>
                        </div>
                        <div class="body">
                            <button type="button" class="btn btn-outline-danger btn-block" id="delete-coupon-btn" data-coupon-id="<?php echo $coupon_id; ?>">
                                <i class="fa fa-trash"></i> Delete Coupon
                            </button>
                            <small class="text-muted d-block mt-2">This action cannot be undone.</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var couponEditTranslations = {
    coupon_updated: '<?php echo esc_js(sc_t("coupons.coupon_updated", "Coupon updated successfully!")); ?>',
    error_updating: '<?php echo esc_js(sc_t("coupons.error_updating", "Error updating coupon")); ?>',
    error_occurred: '<?php echo esc_js(sc_t("general.error_occurred", "An error occurred. Please try again.")); ?>',
    confirm_delete: '<?php echo esc_js(sc_t("coupons.confirm_delete", "Are you sure you want to delete this coupon?")); ?>',
    cannot_undo: '<?php echo esc_js(sc_t("general.cannot_undo", "This action cannot be undone!")); ?>',
    coupon_deleted: '<?php echo esc_js(sc_t("coupons.coupon_deleted", "Coupon deleted!")); ?>',
    error_deleting: '<?php echo esc_js(sc_t("coupons.error_deleting", "Error deleting coupon")); ?>'
};

jQuery(document).ready(function($) {
    var select2Initialized = false;

    // Function to initialize Select2 (with retry for late loading)
    function initSelect2() {
        if (select2Initialized) return;

        if (typeof $.fn.select2 === 'undefined') {
            // Select2 not loaded yet, retry after 100ms
            setTimeout(initSelect2, 100);
            return;
        }

        if ($('#coupon-events').length) {
            $('#coupon-events').select2({
                width: '100%',
                placeholder: 'Select an event...',
                allowClear: true
            });
            select2Initialized = true;
        }
    }

    // Initialize Select2 on page load
    initSelect2();

    // Update discount suffix based on type
    $('#coupon-discount-type').on('change', function() {
        var type = $(this).val();
        if (type === 'percentage') {
            $('#discount-suffix').text('%');
        } else {
            $('#discount-suffix').text('<?php echo sc_get_currency_symbol(); ?>');
        }
    });

    // Toggle events selection
    $('input[name="event_restriction"]').on('change', function() {
        if ($(this).val() === 'specific') {
            $('#events-selection').slideDown();
        } else {
            $('#events-selection').slideUp();
        }
    });

    // Convert code to uppercase
    $('#coupon-code').on('input', function() {
        $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g, ''));
    });

    // Form submission
    $('#coupon-edit-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-coupon-btn');

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(couponEditTranslations.coupon_updated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Coupon');
                } else {
                    toastr.error(response.data || couponEditTranslations.error_updating);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Coupon');
                }
            },
            error: function() {
                toastr.error(couponEditTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Coupon');
            }
        });
    });

    // Delete coupon
    $('#delete-coupon-btn').on('click', function() {
        var couponId = $(this).data('coupon-id');

        if (!confirm(couponEditTranslations.confirm_delete + '\n\n' + couponEditTranslations.cannot_undo)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'delete_coupon',
                coupon_id: couponId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(couponEditTranslations.coupon_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/coupons'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || couponEditTranslations.error_deleting);
                }
            }
        });
    });
});
</script>

<style>
.card .header h2 {
    font-size: 16px;
}
.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
}
.border-danger {
    border: 1px solid #dc3545 !important;
}
.form-check {
    margin-bottom: 10px;
}
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
