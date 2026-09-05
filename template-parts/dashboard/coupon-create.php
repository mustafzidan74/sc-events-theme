<?php
/**
 * Dashboard - Create Coupons Page (Bulk Support)
 * Standalone page for creating single or multiple coupons
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.create_coupons', 'Create Coupons'),
    'coupons' => sc_t('dashboard_pages.coupons', 'Coupons'),
    'create' => sc_t('dashboard_pages.create', 'Create'),
    'back_to_coupons' => sc_t('dashboard_pages.back_to_coupons', 'Back to Coupons'),
    'single_coupon' => sc_t('dashboard_pages.single_coupon', 'Single Coupon'),
    'bulk_generate' => sc_t('dashboard_pages.bulk_generate', 'Bulk Generate'),
    // Single Coupon Form
    'coupon_details' => sc_t('dashboard_pages.coupon_details', 'Coupon Details'),
    'coupon_code' => sc_t('dashboard_pages.coupon_code', 'Coupon Code'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    'enter_coupon_code' => sc_t('dashboard_pages.enter_coupon_code', 'Enter coupon code'),
    'generate_random_code' => sc_t('dashboard_pages.generate_random_code', 'Generate random code'),
    'discount_configuration' => sc_t('dashboard_pages.discount_configuration', 'Discount Configuration'),
    'discount_type' => sc_t('dashboard_pages.discount_type', 'Discount Type'),
    'percentage' => sc_t('dashboard_pages.percentage', 'Percentage (%)'),
    'fixed_amount' => sc_t('dashboard_pages.fixed_amount', 'Fixed Amount'),
    'free_100' => sc_t('dashboard_pages.free_100', '100% Free'),
    'discount_value' => sc_t('dashboard_pages.discount_value', 'Discount Value'),
    'usage_limit' => sc_t('dashboard_pages.usage_limit', 'Usage Limit'),
    'unlimited_hint' => sc_t('dashboard_pages.unlimited_hint', '0 = Unlimited'),
    'per_user_limit' => sc_t('dashboard_pages.per_user_limit', 'Per User Limit'),
    'per_user_unlimited' => sc_t('dashboard_pages.per_user_unlimited', '0 = unlimited'),
    'event_restrictions' => sc_t('dashboard_pages.event_restrictions', 'Event Restrictions'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'specific_events' => sc_t('dashboard_pages.specific_events', 'Specific Events'),
    'specific_event' => sc_t('dashboard_pages.specific_event', 'Specific Event'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'select_event_placeholder' => sc_t('dashboard_pages.select_event_placeholder', 'Select an event...'),
    'create_coupon' => sc_t('dashboard_pages.create_coupon', 'Create Coupon'),
    'expiry_date' => sc_t('dashboard_pages.expiry_date', 'Expiry Date'),
    'no_expiration_hint' => sc_t('dashboard_pages.no_expiration_hint', 'Leave empty for no expiration'),
    // Bulk Generation Form
    'bulk_generation_settings' => sc_t('dashboard_pages.bulk_generation_settings', 'Bulk Generation Settings'),
    'number_of_coupons' => sc_t('dashboard_pages.number_of_coupons', 'Number of Coupons'),
    'max_per_batch' => sc_t('dashboard_pages.max_per_batch', 'Max 1000 per batch'),
    'code_prefix' => sc_t('dashboard_pages.code_prefix', 'Code Prefix'),
    'code_prefix_example' => sc_t('dashboard_pages.code_prefix_example', 'e.g., SALE'),
    'code_length' => sc_t('dashboard_pages.code_length', 'Code Length'),
    'usage_limit_each' => sc_t('dashboard_pages.usage_limit_each', 'Usage Limit (each)'),
    'apply_to_events' => sc_t('dashboard_pages.apply_to_events', 'Apply to Events'),
    'preview' => sc_t('dashboard_pages.preview', 'Preview'),
    'will_generate' => sc_t('dashboard_pages.will_generate', 'Will generate'),
    'coupons_with' => sc_t('dashboard_pages.coupons_with', 'coupons with'),
    'discount' => sc_t('dashboard_pages.discount', 'discount'),
    'example_codes' => sc_t('dashboard_pages.example_codes', 'Example codes'),
    'generate' => sc_t('dashboard_pages.generate', 'Generate'),
    'generate_coupons' => sc_t('dashboard_pages.generate_coupons', 'Generate Coupons'),
    'generated' => sc_t('dashboard_pages.generated', 'Generated!'),
    'coupons_created_successfully' => sc_t('dashboard_pages.coupons_created_successfully', 'coupons created successfully!'),
    'download_csv' => sc_t('dashboard_pages.download_csv', 'Download CSV'),
    'view_all_coupons' => sc_t('dashboard_pages.view_all_coupons', 'View All Coupons'),
    // JavaScript translations
    'please_enter_coupon_code' => sc_t('dashboard_pages.please_enter_coupon_code', 'Please enter a coupon code'),
    'creating' => sc_t('dashboard_pages.creating', 'Creating...'),
    'coupon_created' => sc_t('dashboard_pages.coupon_created', 'Coupon created successfully!'),
    'error_creating_coupon' => sc_t('dashboard_pages.error_creating_coupon', 'Error creating coupon'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'generating' => sc_t('dashboard_pages.generating', 'Generating...'),
    'coupons_generated' => sc_t('dashboard_pages.coupons_generated', 'coupons generated successfully!'),
    'error_generating' => sc_t('dashboard_pages.error_generating', 'Error generating coupons'),
    'generate_more' => sc_t('dashboard_pages.generate_more', 'Generate More'),
    'no_coupons_download' => sc_t('dashboard_pages.no_coupons_download', 'No coupons to download'),
    'free_label' => sc_t('dashboard_pages.free_label', 'Free'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'no_expiry' => sc_t('dashboard_pages.no_expiry', 'No Expiry'),
    'csv_code' => sc_t('dashboard_pages.csv_code', 'Code'),
    'csv_discount_type' => sc_t('dashboard_pages.csv_discount_type', 'Discount Type'),
    'csv_discount_value' => sc_t('dashboard_pages.csv_discount_value', 'Discount Value'),
    'csv_usage_limit' => sc_t('dashboard_pages.csv_usage_limit', 'Usage Limit'),
    'csv_expiry_date' => sc_t('dashboard_pages.csv_expiry_date', 'Expiry Date'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get all events for selection
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => 'publish',
        'limit' => 500,
        'orderby' => 'start_date',
        'order' => 'DESC'
    ));
}

// Get all coupon categories
$coupon_categories = class_exists('SC_Coupon_Category') ? SC_Coupon_Category::get_all() : array();

$currency = sc_get_currency_symbol();
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['page_title']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>"><?php echo $t['coupons']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['create']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back_to_coupons']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Creation Mode Selection -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="body">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons" id="creation-mode">
                            <label class="btn btn-outline-primary active" style="flex: 1;">
                                <input type="radio" name="creation_mode" value="single" checked>
                                <i class="fa fa-tag"></i> <?php echo $t['single_coupon']; ?>
                            </label>
                            <label class="btn btn-outline-primary" style="flex: 1;">
                                <input type="radio" name="creation_mode" value="bulk">
                                <i class="fa fa-tags"></i> <?php echo $t['bulk_generate']; ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Coupon Form -->
        <div id="single-coupon-form">
            <form id="coupon-create-form" class="coupon-form">
                <?php wp_nonce_field('sc_coupon_action', 'sc_coupon_nonce'); ?>
                <input type="hidden" name="action" value="sc_save_coupon">

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Basic Information -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-tag"></i> <?php echo $t['coupon_details']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="coupon-code"><?php echo $t['coupon_code']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control text-uppercase" id="coupon-code" name="code" required placeholder="<?php echo esc_attr($t['enter_coupon_code']); ?>">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="generate-code-btn" title="<?php echo esc_attr($t['generate_random_code']); ?>">
                                                <i class="fa fa-refresh"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Discount Configuration -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-percent"></i> <?php echo $t['discount_configuration']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="coupon-discount-type"><?php echo $t['discount_type']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                            <select class="form-control" id="coupon-discount-type" name="discount_type" required>
                                                <option value="percentage"><?php echo $t['percentage']; ?></option>
                                                <option value="fixed"><?php echo $t['fixed_amount']; ?></option>
                                                <option value="free"><?php echo $t['free_100']; ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" id="discount-value-group">
                                            <label for="coupon-discount-value"><?php echo $t['discount_value']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="coupon-discount-value" name="discount_value" min="0" step="0.01" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text" id="discount-suffix">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="coupon-usage-limit"><?php echo $t['usage_limit']; ?></label>
                                            <input type="number" class="form-control" id="coupon-usage-limit" name="usage_limit" min="0" value="1" placeholder="<?php echo esc_attr($t['unlimited_hint']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="coupon-per-user-limit"><?php echo $t['per_user_limit']; ?></label>
                                            <input type="number" class="form-control" id="coupon-per-user-limit" name="per_user_limit" min="0" value="1">
                                            <small class="text-muted"><?php echo $t['per_user_unlimited']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Event Restrictions -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-calendar"></i> <?php echo $t['event_restrictions']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="apply-all-events" name="event_restriction" value="all" checked>
                                        <label class="custom-control-label" for="apply-all-events"><?php echo $t['all_events']; ?></label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="apply-specific-events" name="event_restriction" value="specific">
                                        <label class="custom-control-label" for="apply-specific-events"><?php echo $t['specific_events']; ?></label>
                                    </div>
                                </div>

                                <div class="form-group" id="events-selection" style="display:none;">
                                    <label for="coupon-events"><?php echo $t['select_event']; ?></label>
                                    <select class="form-control" id="coupon-events" name="event_id" data-placeholder="<?php echo esc_attr($t['select_event_placeholder']); ?>">
                                        <option value=""><?php echo $t['select_event_placeholder'] ?? __('-- Select Event --', 'sc_events'); ?></option>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo $event->id; ?>">
                                                <?php echo esc_html($event->title); ?> (<?php echo date('M d, Y', strtotime($event->start_date)); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mt-3">
                                    <label for="coupon-category"><i class="fa fa-folder mr-1"></i> <?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                                    <select class="form-control" id="coupon-category" name="category_id">
                                        <?php foreach ($coupon_categories as $cat): ?>
                                            <option value="<?php echo (int) $cat->id; ?>" <?php selected((int) $cat->id, 1); ?>><?php echo esc_html($cat->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted"><?php echo esc_html(sc_t('dashboard_pages.coupon_category_hint', 'Group this coupon under a category for organization')); ?> &mdash; <a href="<?php echo esc_url(home_url('/event-manager-dashboard/coupon-categories')); ?>" target="_blank"><?php echo esc_html(sc_t('dashboard_pages.manage_categories', 'Manage Categories')); ?></a></small>
                                </div>

                                <div class="form-group mt-3">
                                    <label for="ticket-type-filter"><i class="fa fa-filter mr-1"></i> <?php echo esc_html(sc_t('coupons.ticket_type_filter', 'Ticket Type')); ?></label>
                                    <select class="form-control" id="ticket-type-filter" name="ticket_type_filter">
                                        <option value="all"><?php echo esc_html(sc_t('coupons.all_ticket_types', 'All Ticket Types')); ?></option>
                                        <option value="general"><?php echo esc_html(sc_t('coupons.general_only', 'General (Attendees) Only')); ?></option>
                                        <option value="competitor"><?php echo esc_html(sc_t('coupons.competitor_only', 'Competitor Only')); ?></option>
                                    </select>
                                    <small class="form-text text-muted"><?php echo esc_html(sc_t('coupons.ticket_type_hint', 'Restrict this coupon to specific ticket types')); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Submit Box -->
                        <div class="card">
                            <div class="header bg-success">
                                <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['create_coupon']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="coupon-expiry"><?php echo $t['expiry_date']; ?></label>
                                    <input type="date" class="form-control" id="coupon-expiry" name="expiry_date">
                                    <small class="text-muted"><?php echo $t['no_expiration_hint']; ?></small>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-coupon-btn">
                                    <i class="fa fa-plus"></i> <?php echo $t['create_coupon']; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Coupon Form -->
        <div id="bulk-coupon-form" style="display: none;">
            <form id="bulk-create-form">
                <?php wp_nonce_field('sc_coupon_action', 'sc_bulk_nonce'); ?>
                <input type="hidden" name="action" value="sc_bulk_create_coupons">

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Bulk Settings -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-cogs"></i> <?php echo $t['bulk_generation_settings']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bulk-count"><?php echo $t['number_of_coupons']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                            <input type="number" class="form-control" id="bulk-count" name="count" min="1" max="1000" value="10" required>
                                            <small class="text-muted"><?php echo $t['max_per_batch']; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bulk-prefix"><?php echo $t['code_prefix']; ?></label>
                                            <input type="text" class="form-control text-uppercase" id="bulk-prefix" name="prefix" placeholder="<?php echo esc_attr($t['code_prefix_example']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bulk-length"><?php echo $t['code_length']; ?></label>
                                            <input type="number" class="form-control" id="bulk-length" name="code_length" min="4" max="20" value="8">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bulk-discount-type"><?php echo $t['discount_type']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                            <select class="form-control" id="bulk-discount-type" name="discount_type" required>
                                                <option value="percentage"><?php echo $t['percentage']; ?></option>
                                                <option value="fixed"><?php echo $t['fixed_amount']; ?></option>
                                                <option value="free"><?php echo $t['free_100']; ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" id="bulk-discount-value-group">
                                            <label for="bulk-discount-value"><?php echo $t['discount_value']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="bulk-discount-value" name="discount_value" min="0" step="0.01" value="10" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text" id="bulk-discount-suffix">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bulk-usage-limit"><?php echo $t['usage_limit_each']; ?></label>
                                            <input type="number" class="form-control" id="bulk-usage-limit" name="usage_limit" min="0" value="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Event Restrictions for Bulk -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-calendar"></i> <?php echo $t['apply_to_events']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="bulk-all-events" name="bulk_event_restriction" value="all" checked>
                                        <label class="custom-control-label" for="bulk-all-events"><?php echo $t['all_events']; ?></label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="bulk-specific-events" name="bulk_event_restriction" value="specific">
                                        <label class="custom-control-label" for="bulk-specific-events"><?php echo $t['specific_event']; ?></label>
                                    </div>
                                </div>

                                <div class="form-group" id="bulk-events-selection" style="display:none;">
                                    <label for="bulk-event"><?php echo $t['select_event']; ?></label>
                                    <select class="form-control select2" id="bulk-event" name="event_id">
                                        <option value=""><?php echo $t['all_events']; ?></option>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo $event->id; ?>">
                                                <?php echo esc_html($event->title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mt-3">
                                    <label for="bulk-category"><i class="fa fa-folder mr-1"></i> <?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                                    <select class="form-control" id="bulk-category" name="category_id">
                                        <?php foreach ($coupon_categories as $cat): ?>
                                            <option value="<?php echo (int) $cat->id; ?>" <?php selected((int) $cat->id, 1); ?>><?php echo esc_html($cat->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted"><?php echo esc_html(sc_t('dashboard_pages.coupon_category_hint_bulk', 'All generated coupons will be assigned to this category')); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-eye"></i> <?php echo $t['preview']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="alert alert-info" id="bulk-preview">
                                    <i class="fa fa-info-circle"></i>
                                    <?php echo $t['will_generate']; ?> <strong><span id="preview-count">10</span></strong> <?php echo $t['coupons_with']; ?>
                                    <strong><span id="preview-discount">10%</span></strong> <?php echo $t['discount']; ?>.
                                    <span id="preview-usage-info"></span>
                                    <br>
                                    <small><?php echo $t['example_codes']; ?>: <code id="preview-codes">SALE1A2B3C4D, SALE5E6F7G8H, ...</code></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Generate Box -->
                        <div class="card">
                            <div class="header bg-primary">
                                <h2 class="text-white"><i class="fa fa-magic"></i> <?php echo $t['generate']; ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="bulk-expiry"><?php echo $t['expiry_date']; ?></label>
                                    <input type="date" class="form-control" id="bulk-expiry" name="expiry_date">
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="generate-coupons-btn">
                                    <i class="fa fa-magic"></i> <?php echo $t['generate_coupons']; ?>
                                </button>

                                <div class="progress mt-3" id="generation-progress" style="display: none;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Generated Results -->
                        <div class="card" id="generated-results" style="display: none;">
                            <div class="header bg-success">
                                <h2 class="text-white"><i class="fa fa-check"></i> <?php echo $t['generated']; ?></h2>
                            </div>
                            <div class="body">
                                <p><strong id="generated-count">0</strong> <?php echo $t['coupons_created_successfully']; ?></p>
                                <button type="button" class="btn btn-outline-primary btn-block" id="download-csv-btn">
                                    <i class="fa fa-download"></i> <?php echo $t['download_csv']; ?>
                                </button>
                                <a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>" class="btn btn-outline-secondary btn-block mt-2">
                                    <i class="fa fa-list"></i> <?php echo $t['view_all_coupons']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var couponTranslations = {
    select_event_placeholder: '<?php echo esc_js($t['select_event_placeholder']); ?>',
    please_enter_coupon_code: '<?php echo esc_js($t['please_enter_coupon_code']); ?>',
    creating: '<?php echo esc_js($t['creating']); ?>',
    coupon_created: '<?php echo esc_js($t['coupon_created']); ?>',
    error_creating_coupon: '<?php echo esc_js($t['error_creating_coupon']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    create_coupon: '<?php echo esc_js($t['create_coupon']); ?>',
    generating: '<?php echo esc_js($t['generating']); ?>',
    coupons_generated: '<?php echo esc_js($t['coupons_generated']); ?>',
    error_generating: '<?php echo esc_js($t['error_generating']); ?>',
    generate_coupons: '<?php echo esc_js($t['generate_coupons']); ?>',
    generate_more: '<?php echo esc_js($t['generate_more']); ?>',
    no_coupons_download: '<?php echo esc_js($t['no_coupons_download']); ?>',
    free_label: '<?php echo esc_js($t['free_label']); ?>',
    no_expiry: '<?php echo esc_js($t['no_expiry']); ?>',
    csv_code: '<?php echo esc_js($t['csv_code']); ?>',
    csv_discount_type: '<?php echo esc_js($t['csv_discount_type']); ?>',
    csv_discount_value: '<?php echo esc_js($t['csv_discount_value']); ?>',
    csv_usage_limit: '<?php echo esc_js($t['csv_usage_limit']); ?>',
    csv_expiry_date: '<?php echo esc_js($t['csv_expiry_date']); ?>'
};

jQuery(document).ready(function($) {
    var generatedCoupons = [];
    var select2Initialized = false;
    var bulkSelect2Initialized = false;

    // Function to initialize all Select2 elements (with retry for late loading)
    function initAllSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            // Select2 not loaded yet, retry after 100ms
            setTimeout(initAllSelect2, 100);
            return;
        }

        // Initialize single coupon events select
        if (!select2Initialized && $('#coupon-events').length) {
            $('#coupon-events').select2({
                width: '100%',
                placeholder: couponTranslations.select_event_placeholder,
                allowClear: true
            });
            select2Initialized = true;
        }

        // Initialize bulk event select
        if (!bulkSelect2Initialized && $('#bulk-event').length) {
            $('#bulk-event').select2({
                width: '100%',
                placeholder: couponTranslations.select_event_placeholder,
                allowClear: true
            });
            bulkSelect2Initialized = true;
        }
    }

    // Initialize Select2 on page load
    initAllSelect2();

    // Toggle creation mode
    $('input[name="creation_mode"]').on('change', function() {
        var mode = $(this).val();
        if (mode === 'bulk') {
            $('#single-coupon-form').hide();
            $('#bulk-coupon-form').show();
        } else {
            $('#single-coupon-form').show();
            $('#bulk-coupon-form').hide();
        }
    });

    // Single coupon - discount type change
    $('#coupon-discount-type').on('change', function() {
        var type = $(this).val();
        if (type === 'free') {
            $('#discount-value-group').hide();
            $('#coupon-discount-value').val(100).prop('required', false);
        } else {
            $('#discount-value-group').show();
            $('#coupon-discount-value').prop('required', true);
            $('#discount-suffix').text(type === 'percentage' ? '%' : '<?php echo $currency; ?>');
        }
    });

    // Bulk - discount type change
    $('#bulk-discount-type').on('change', function() {
        var type = $(this).val();
        if (type === 'free') {
            $('#bulk-discount-value-group').hide();
            $('#bulk-discount-value').val(100).prop('required', false);
        } else {
            $('#bulk-discount-value-group').show();
            $('#bulk-discount-value').prop('required', true);
            $('#bulk-discount-suffix').text(type === 'percentage' ? '%' : '<?php echo $currency; ?>');
        }
        updateBulkPreview();
    });

    // Toggle event selection
    $('input[name="event_restriction"]').on('change', function() {
        if ($(this).val() === 'specific') {
            $('#events-selection').slideDown();
        } else {
            $('#events-selection').slideUp();
        }
    });

    // Toggle bulk event selection
    $('input[name="bulk_event_restriction"]').on('change', function() {
        if ($(this).val() === 'specific') {
            $('#bulk-events-selection').slideDown();
        } else {
            $('#bulk-events-selection').slideUp();
        }
    });

    // Generate random code
    $('#generate-code-btn').on('click', function() {
        $('#coupon-code').val(generateRandomCode(8, ''));
    });

    function generateRandomCode(length, prefix) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var code = prefix || '';
        for (var i = 0; i < length; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return code;
    }

    // Convert code to uppercase
    $('#coupon-code, #bulk-prefix').on('input', function() {
        $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g, ''));
    });

    // Update bulk preview
    function updateBulkPreview() {
        var count = $('#bulk-count').val() || 10;
        var prefix = $('#bulk-prefix').val() || '';
        var length = $('#bulk-length').val() || 8;
        var discountType = $('#bulk-discount-type').val();
        var discountValue = $('#bulk-discount-value').val() || 0;
        var usageLimit = parseInt($('#bulk-usage-limit').val()) || 0;

        var discountText = discountType === 'free' ? '100% (' + couponTranslations.free_label + ')' :
            (discountType === 'percentage' ? discountValue + '%' : discountValue + ' <?php echo $currency; ?>');

        $('#preview-count').text(count);
        $('#preview-discount').text(discountText);

        // Show usage limit info
        var usageText = usageLimit > 0
            ? '<?php echo esc_js(sc_t('dashboard_pages.each_with_usage', 'Each with')); ?> ' + usageLimit + ' <?php echo esc_js(sc_t('dashboard_pages.uses_label', 'use(s)')); ?>.'
            : '<?php echo esc_js(sc_t('dashboard_pages.unlimited_usage', 'Unlimited usage.')); ?>';
        $('#preview-usage-info').text(usageText);

        // Generate example codes
        var examples = [];
        for (var i = 0; i < Math.min(3, count); i++) {
            examples.push(generateRandomCode(length, prefix));
        }
        if (count > 3) examples.push('...');
        $('#preview-codes').text(examples.join(', '));
    }

    $('#bulk-count, #bulk-prefix, #bulk-length, #bulk-discount-value, #bulk-discount-type, #bulk-usage-limit').on('input change', updateBulkPreview);
    updateBulkPreview();

    // Single coupon form submission
    $('#coupon-create-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-coupon-btn');

        if (!$('#coupon-code').val().trim()) {
            toastr.error(couponTranslations.please_enter_coupon_code);
            return;
        }

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + couponTranslations.creating);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(couponTranslations.coupon_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/coupons'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || couponTranslations.error_creating_coupon);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> ' + couponTranslations.create_coupon);
                }
            },
            error: function() {
                toastr.error(couponTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> ' + couponTranslations.create_coupon);
            }
        });
    });

    // Bulk form submission
    $('#bulk-create-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#generate-coupons-btn');
        var count = parseInt($('#bulk-count').val()) || 10;

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + couponTranslations.generating);
        $('#generation-progress').show();
        $('#generated-results').hide();

        generatedCoupons = [];

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                $('#generation-progress').hide();

                if (response.success) {
                    generatedCoupons = response.data.coupons || [];
                    $('#generated-count').text(generatedCoupons.length);
                    $('#generated-results').show();
                    toastr.success(generatedCoupons.length + ' ' + couponTranslations.coupons_generated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-magic"></i> ' + couponTranslations.generate_more);
                } else {
                    toastr.error(response.data || couponTranslations.error_generating);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-magic"></i> ' + couponTranslations.generate_coupons);
                }
            },
            error: function() {
                $('#generation-progress').hide();
                toastr.error(couponTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-magic"></i> ' + couponTranslations.generate_coupons);
            }
        });
    });

    // Download CSV
    $('#download-csv-btn').on('click', function() {
        if (generatedCoupons.length === 0) {
            toastr.warning(couponTranslations.no_coupons_download);
            return;
        }

        var csv = couponTranslations.csv_code + ',' + couponTranslations.csv_discount_type + ',' + couponTranslations.csv_discount_value + ',' + couponTranslations.csv_usage_limit + ',' + couponTranslations.csv_expiry_date + '\n';
        generatedCoupons.forEach(function(coupon) {
            csv += coupon.code + ',' + coupon.discount_type + ',' + coupon.discount_value + ',' + coupon.usage_limit + ',' + (coupon.expiry_date || couponTranslations.no_expiry) + '\n';
        });

        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'coupons_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
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
.btn-group-toggle .btn {
    padding: 15px 20px;
}
.btn-group-toggle .btn.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: #fff;
}
#bulk-preview {
    background: #e8f4fd;
    border-color: #b8daff;
}
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
