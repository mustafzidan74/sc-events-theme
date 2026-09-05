<?php
/**
 * Dashboard Events Management Page - Custom Implementation
 * Uses Custom Tables (sc_events) for data storage
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

$page_title = sc_t('dashboard_pages.events_management', 'Events Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Auto-complete past events (throttled to once per 5 minutes)
global $wpdb;
$events_table = $wpdb->prefix . 'sc_events';
if (!get_transient('sc_auto_complete_check')) {
    $rows_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$events_table} SET status = 'completed'
         WHERE status = 'publish'
         AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
        date('Y-m-d'), date('Y-m-d')
    ));
    set_transient('sc_auto_complete_check', 1, 300);
    if ($rows_updated > 0) {
        delete_transient('sc_dashboard_home_stats_v2');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sc_analytics_%' OR option_name LIKE '_transient_timeout_sc_analytics_%'");
    }
}

// One-time fix: correct payment_method inconsistencies
if (!get_option('sc_payment_method_fix_v2')) {
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    // Fix 1: coupon_code set but payment_method not 'coupon'
    $wpdb->query(
        "UPDATE {$attendees_table} SET payment_method = 'coupon'
         WHERE coupon_code IS NOT NULL AND coupon_code != ''
         AND payment_method != 'coupon'"
    );
    // Fix 2: amount_paid > 0 but payment_method is empty/null (should be 'paid')
    $wpdb->query(
        "UPDATE {$attendees_table} SET payment_method = 'paid'
         WHERE amount_paid > 0
         AND (payment_method IS NULL OR payment_method = '' OR payment_method = 'free')
         AND (coupon_code IS NULL OR coupon_code = '')"
    );
    update_option('sc_payment_method_fix_v2', 1);
}

// Get events statistics (fresh on every page load)
$published = intval($wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE status = 'publish'"));
$draft = intval($wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE status = 'draft'"));
$upcoming_count = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $events_table WHERE status = 'publish' AND start_date >= %s",
    date('Y-m-d')
)));
$total_tickets = intval($wpdb->get_var("SELECT COALESCE(SUM(total_sold), 0) FROM $events_table"));
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.events_management', 'Events Management')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.events', 'Events')); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/event-create'); ?>" class="btn btn-primary">
                                <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.create_new_event', 'Create New Event')); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row clearfix" id="events-stats-row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-calendar bg-blue"></i></div>
                    <div class="content">
                        <div class="text"><?php echo esc_html(sc_t('dashboard_pages.published_events', 'Published Events')); ?></div>
                        <div class="number" id="stat-published"><?php echo intval($published); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-edit bg-orange"></i></div>
                    <div class="content">
                        <div class="text"><?php echo esc_html(sc_t('dashboard_pages.draft_events', 'Draft Events')); ?></div>
                        <div class="number" id="stat-draft"><?php echo intval($draft); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-clock-o bg-green"></i></div>
                    <div class="content">
                        <div class="text"><?php echo esc_html(sc_t('dashboard_pages.upcoming_events', 'Upcoming Events')); ?></div>
                        <div class="number" id="stat-upcoming"><?php echo intval($upcoming_count); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-ticket bg-purple"></i></div>
                    <div class="content">
                        <div class="text"><?php echo esc_html(sc_t('dashboard_pages.total_tickets_sold', 'Total Tickets Sold')); ?></div>
                        <div class="number" id="stat-tickets-sold"><?php echo intval($total_tickets); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events List Table -->
        <div class="row clearfix">
            <div class="col-lg-12">
                <div class="card">
                    <div class="header">
                        <h2><?php echo esc_html(sc_t('dashboard_pages.events_list', 'Events List')); ?></h2>
                        <ul class="header-dropdown">
                            <li class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li><a href="javascript:void(0);" id="refresh-events"><i class="fa fa-refresh"></i> <?php echo esc_html(sc_t('dashboard_pages.refresh', 'Refresh')); ?></a></li>
                                    <li><a href="javascript:void(0);" id="export-events"><i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.export_csv', 'Export CSV')); ?></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <!-- Filter Tabs -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#" data-filter="all">
                                    <i class="fa fa-th"></i> <?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#" data-filter="upcoming">
                                    <i class="fa fa-calendar-plus-o"></i> <?php echo esc_html(sc_t('dashboard_pages.upcoming_events', 'Upcoming')); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#" data-filter="past">
                                    <i class="fa fa-calendar-check-o"></i> <?php echo esc_html(sc_t('dashboard_pages.past_events', 'Past')); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#" data-filter="draft">
                                    <i class="fa fa-pencil"></i> <?php echo esc_html(sc_t('dashboard_pages.drafts', 'Drafts')); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#" data-filter="completed">
                                    <i class="fa fa-check-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.completed_events', 'Completed')); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#" data-filter="disabled">
                                    <i class="fa fa-ban"></i> <?php echo esc_html(sc_t('dashboard_pages.disabled_events', 'Disabled')); ?>
                                </a>
                            </li>
                        </ul>

                        <!-- Search and Category Filter -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="events-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_events_placeholder', 'Search events by name, venue, or description...')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <select class="form-control" id="events-category-filter">
                                    <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_categories', 'All Categories')); ?></option>
                                    <?php
                                    $categories = get_terms(array(
                                        'taxonomy' => 'sc_event_category',
                                        'hide_empty' => false,
                                        'orderby' => 'name',
                                        'order' => 'ASC'
                                    ));
                                    if (!is_wp_error($categories) && !empty($categories)) {
                                        foreach ($categories as $category) {
                                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Events Table -->
                        <div class="table-responsive">
                            <table id="events-table" class="table table-hover table-custom spacing5">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" id="select-all-events"></th>
                                        <th><?php echo esc_html(sc_t('dashboard_pages.event_name', 'Event Name')); ?></th>
                                        <th><?php echo esc_html(sc_t('dashboard_pages.date_time', 'Date & Time')); ?></th>
                                        <th><?php echo esc_html(sc_t('dashboard_pages.venue', 'Venue')); ?></th>
                                        <th><?php echo esc_html(sc_t('tickets.available_tickets', 'Available Tickets')); ?></th>
                                        <th><?php echo esc_html(sc_t('dashboard_pages.sold', 'Sold')); ?></th>
                                        <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                        <th class="text-right"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Events will be loaded via AJAX -->
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                            <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading events...')); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls -->
                        <div class="row mt-3" id="events-pagination-controls" style="display: none;">
                            <div class="col-md-6">
                                <div class="pagination-info">
                                    <span id="events-showing-info"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <nav>
                                    <ul class="pagination justify-content-end mb-0" id="events-pagination">
                                        <!-- Pagination buttons will be inserted here -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- View Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.event_details', 'Event Details')); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="event-details-content">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                    <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading_event_details', 'Loading event details...')); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manage Attendees Modal -->
<div class="modal fade" id="attendeesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-users"></i> <span id="attendees-modal-title"><?php echo esc_html(sc_t('dashboard_pages.manage_attendees', 'Manage Attendees')); ?></span></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="current-event-id" value="">

                <!-- Attendees Stats -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="body text-center">
                                <h3 class="mb-0" id="total-attendees-count">0</h3>
                                <small><?php echo esc_html(sc_t('dashboard_pages.total_attendees', 'Total Attendees')); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="body text-center">
                                <h3 class="mb-0" id="used-tickets-count">0</h3>
                                <small><?php echo esc_html(sc_t('dashboard_pages.used_tickets', 'Used Tickets')); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="body text-center">
                                <h3 class="mb-0" id="unused-tickets-count">0</h3>
                                <small><?php echo esc_html(sc_t('dashboard_pages.unused_tickets', 'Unused Tickets')); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="body text-center">
                                <h3 class="mb-0" id="capacity-remaining">0</h3>
                                <small><?php echo esc_html(sc_t('dashboard_pages.capacity_remaining', 'Capacity Remaining')); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <button class="btn btn-primary" id="export-attendees-btn">
                        <i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.export_csv', 'Export CSV')); ?>
                    </button>
                </div>

                <!-- Attendees Table -->
                <div class="table-responsive">
                    <table id="attendees-table" class="table table-hover table-custom spacing5">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-attendees"></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.email', 'Email')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.phone', 'Phone')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.ticket_type', 'Ticket Type')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.ticket_status', 'Ticket Status')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                    <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading_attendees', 'Loading attendees...')); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ticket Add/Edit Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ticket-modal-title">
                    <i class="fa fa-ticket"></i> <?php echo esc_html(sc_t('dashboard_pages.add_ticket', 'Add Ticket')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="ticket-form">
                    <input type="hidden" id="ticket-id" value="">
                    <input type="hidden" id="ticket-index" value="">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ticket-name"><?php echo esc_html(sc_t('dashboard_pages.ticket_name', 'Ticket Name')); ?> *</label>
                                <input type="text" class="form-control" id="ticket-name" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.ticket_name_placeholder', 'e.g., VIP Ticket')); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="ticket-price-group">
                                <label for="ticket-price"><?php echo esc_html(sc_t('dashboard_pages.price', 'Price')); ?> *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="ticket-price" placeholder="0" min="0" step="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?php echo esc_html(sc_t('dashboard_pages.currency_egp', 'EGP')); ?></span>
                                    </div>
                                </div>
                                <small class="form-text text-muted"><?php echo esc_html(sc_t('dashboard_pages.enter_zero_free', 'Enter 0 for free tickets')); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="ticket-use-coupons">
                            <label class="custom-control-label" for="ticket-use-coupons">
                                <strong><?php echo esc_html(sc_t('dashboard_pages.use_coupons_only', 'Use Coupons Only')); ?></strong> - <?php echo esc_html(sc_t('dashboard_pages.coupon_only_desc', 'Ticket can only be obtained with a coupon code (no payment required)')); ?>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ticket-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                        <textarea class="form-control" id="ticket-description" rows="2" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.ticket_desc_placeholder', 'Short description of this ticket type')); ?>"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ticket-qty"><?php echo esc_html(sc_t('dashboard_pages.total_quantity', 'Total Quantity')); ?> *</label>
                                <input type="number" class="form-control" id="ticket-qty" placeholder="100" min="1" required>
                                <small class="form-text text-muted"><?php echo esc_html(sc_t('tickets.available_tickets', 'Available tickets')); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ticket-min-qty"><?php echo esc_html(sc_t('dashboard_pages.min_per_order', 'Min Per Order')); ?> *</label>
                                <input type="number" class="form-control" id="ticket-min-qty" placeholder="1" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ticket-max-qty"><?php echo esc_html(sc_t('dashboard_pages.max_per_order', 'Max Per Order')); ?> *</label>
                                <input type="number" class="form-control" id="ticket-max-qty" placeholder="10" min="1" value="10" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ticket-sale-start"><?php echo esc_html(sc_t('dashboard_pages.sale_start_date', 'Sale Start Date')); ?> *</label>
                                <input type="date" class="form-control" id="ticket-sale-start" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ticket-sale-end"><?php echo esc_html(sc_t('dashboard_pages.sale_end_date', 'Sale End Date')); ?> *</label>
                                <input type="date" class="form-control" id="ticket-sale-end" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="ticket-status" checked>
                            <label class="custom-control-label" for="ticket-status">
                                <strong><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?></strong> - <?php echo esc_html(sc_t('dashboard_pages.ticket_available_purchase', 'Ticket is available for purchase')); ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?>
                </button>
                <button type="button" class="btn btn-primary" id="save-ticket-btn">
                    <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.save_ticket', 'Save Ticket')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Section Type Selection Modal -->
<div class="modal fade" id="sectionTypeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-th-large"></i> <?php echo esc_html(sc_t('dashboard_pages.choose_section_type', 'Choose Section Type')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4"><?php echo esc_html(sc_t('dashboard_pages.select_section_desc', 'Select the type of section you want to add to your event page:')); ?></p>

                <div class="row">
                    <!-- Image Slider Section -->
                    <div class="col-md-6 mb-3">
                        <div class="section-type-card" data-section-type="image_slider" style="cursor: pointer; border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; transition: all 0.3s;">
                            <div class="text-center mb-3">
                                <i class="fa fa-picture-o" style="font-size: 48px; color: var(--primary-color);"></i>
                            </div>
                            <h5 class="text-center mb-2"><?php echo esc_html(sc_t('dashboard_pages.image_slider_section', 'Image Slider Section')); ?></h5>
                            <p class="text-muted small text-center mb-0"><?php echo esc_html(sc_t('dashboard_pages.image_slider_desc', 'Add a section with a title and multiple images displayed in a slider format')); ?></p>
                        </div>
                    </div>

                    <!-- About Section -->
                    <div class="col-md-6 mb-3">
                        <div class="section-type-card" data-section-type="about" style="cursor: pointer; border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; transition: all 0.3s;">
                            <div class="text-center mb-3">
                                <i class="fa fa-info-circle" style="font-size: 48px; color: var(--primary-color);"></i>
                            </div>
                            <h5 class="text-center mb-2"><?php echo esc_html(sc_t('dashboard_pages.about_section', 'About Section')); ?></h5>
                            <p class="text-muted small text-center mb-0"><?php echo esc_html(sc_t('dashboard_pages.about_section_desc', 'Add a section with heading, description paragraphs, button, and a main image')); ?></p>
                        </div>
                    </div>

                    <!-- Card Section -->
                    <div class="col-md-6 mb-3">
                        <div class="section-type-card" data-section-type="card" style="cursor: pointer; border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; transition: all 0.3s;">
                            <div class="text-center mb-3">
                                <i class="fa fa-th" style="font-size: 48px; color: var(--primary-color);"></i>
                            </div>
                            <h5 class="text-center mb-2"><?php echo esc_html(sc_t('dashboard_pages.card_section', 'Card Section')); ?></h5>
                            <p class="text-muted small text-center mb-0"><?php echo esc_html(sc_t('dashboard_pages.card_section_desc', 'Add a section with heading and 3 cards, each with an icon, title, and description')); ?></p>
                        </div>
                    </div>

                    <!-- Image Grid Section -->
                    <div class="col-md-6 mb-3">
                        <div class="section-type-card" data-section-type="image_grid" style="cursor: pointer; border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; transition: all 0.3s;">
                            <div class="text-center mb-3">
                                <i class="fa fa-th-large" style="font-size: 48px; color: var(--primary-color);"></i>
                            </div>
                            <h5 class="text-center mb-2"><?php echo esc_html(sc_t('dashboard_pages.image_grid_section', 'Image Grid Section')); ?></h5>
                            <p class="text-muted small text-center mb-0"><?php echo esc_html(sc_t('dashboard_pages.image_grid_desc', 'Add a section with heading and multiple images displayed in a grid layout')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.section-type-card:hover {
    border-color: var(--primary-color) !important;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    transform: translateY(-5px);
}
</style>

<!-- Fix Select2 dropdown z-index for modals -->
<style>
.select2-container {
    z-index: 9999 !important;
}
.select2-dropdown {
    z-index: 9999 !important;
}
.modal {
    overflow: visible !important;
}
.modal-body {
    overflow: visible !important;
}
</style>

<script>
// JavaScript Translations Object
var eventsTranslations = {
    // Status badges
    published: '<?php echo esc_js(sc_t('dashboard_pages.published', 'Published')); ?>',
    draft: '<?php echo esc_js(sc_t('dashboard_pages.draft', 'Draft')); ?>',

    // Table content
    not_set: '<?php echo esc_js(sc_t('dashboard_pages.not_set', 'Not set')); ?>',
    na: '<?php echo esc_js(sc_t('dashboard_pages.na', 'N/A')); ?>',
    general: '<?php echo esc_js(sc_t('dashboard_pages.general', 'General')); ?>',
    used: '<?php echo esc_js(sc_t('dashboard_pages.used', 'Used')); ?>',
    unused: '<?php echo esc_js(sc_t('dashboard_pages.unused', 'Unused')); ?>',
    unlimited: '<?php echo esc_js(sc_t('dashboard_pages.unlimited', 'Unlimited')); ?>',

    // Loading states
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    loading_events: '<?php echo esc_js(sc_t('dashboard_pages.loading_events', 'Loading events...')); ?>',
    loading_attendees: '<?php echo esc_js(sc_t('dashboard_pages.loading_attendees', 'Loading attendees...')); ?>',
    exporting: '<?php echo esc_js(sc_t('dashboard_pages.exporting', 'Exporting...')); ?>',

    // Empty states
    no_events_found: '<?php echo esc_js(sc_t('dashboard_pages.no_events_found', 'No events found')); ?>',
    no_attendees_yet: '<?php echo esc_js(sc_t('dashboard_pages.no_attendees_yet', 'No attendees yet')); ?>',
    create_first_event: '<?php echo esc_js(sc_t('dashboard_pages.create_first_event', 'Create Your First Event')); ?>',

    // Errors
    failed_load_events: '<?php echo esc_js(sc_t('dashboard_pages.failed_load_events', 'Failed to load events')); ?>',
    error_loading_events: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_events', 'Error loading events. Please try again.')); ?>',
    error_loading_attendees: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_attendees', 'Error loading attendees')); ?>',
    error_deleting_event: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_event', 'Error deleting event')); ?>',
    error_duplicating_event: '<?php echo esc_js(sc_t('dashboard_pages.error_duplicating_event', 'Error duplicating event')); ?>',
    error_exporting: '<?php echo esc_js(sc_t('dashboard_pages.error_exporting', 'Error exporting attendees')); ?>',
    error_sending_email: '<?php echo esc_js(sc_t('dashboard_pages.error_sending_email', 'Error sending email')); ?>',
    failed_load_event: '<?php echo esc_js(sc_t('dashboard_pages.failed_load_event', 'Failed to load event data')); ?>',

    // Confirmations
    confirm_delete_event: '<?php echo esc_js(sc_t('dashboard_pages.confirm_delete_event', 'Are you sure you want to delete this event? This action cannot be undone.')); ?>',
    confirm_duplicate_event: '<?php echo esc_js(sc_t('dashboard_pages.confirm_duplicate_event', 'Create a duplicate of this event?')); ?>',
    confirm_delete_attendee: '<?php echo esc_js(sc_t('dashboard_pages.confirm_delete_attendee', 'Are you sure you want to delete this attendee? This action cannot be undone.')); ?>',
    confirm_resend_email: '<?php echo esc_js(sc_t('dashboard_pages.confirm_resend_email', 'Resend confirmation email to this attendee?')); ?>',

    // Actions
    edit_event: '<?php echo esc_js(sc_t('dashboard_pages.edit_event', 'Edit Event')); ?>',
    manage_attendees: '<?php echo esc_js(sc_t('dashboard_pages.manage_attendees', 'Manage Attendees')); ?>',
    export_csv: '<?php echo esc_js(sc_t('dashboard_pages.export_csv', 'Export CSV')); ?>',
    resend_email: '<?php echo esc_js(sc_t('dashboard_pages.resend_email', 'Resend Email')); ?>',
    delete: '<?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?>',

    // Pagination
    showing: '<?php echo esc_js(sc_t('dashboard_pages.showing', 'Showing')); ?>',
    of: '<?php echo esc_js(sc_t('dashboard_pages.of', 'of')); ?>',
    previous: '<?php echo esc_js(sc_t('dashboard_pages.previous', 'Previous')); ?>',
    next: '<?php echo esc_js(sc_t('dashboard_pages.next', 'Next')); ?>',

    // Tooltips
    view_details: '<?php echo esc_js(sc_t('dashboard_pages.view_details', 'View Details')); ?>',
    edit: '<?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?>',
    duplicate: '<?php echo esc_js(sc_t('dashboard_pages.duplicate', 'Duplicate')); ?>',

    // Select2 placeholders
    select_categories: '<?php echo esc_js(sc_t('dashboard_pages.select_categories', 'Select event categories')); ?>',
    select_speakers: '<?php echo esc_js(sc_t('dashboard_pages.select_speakers', 'Select speakers for this event')); ?>',
    select_organizers: '<?php echo esc_js(sc_t('dashboard_pages.select_organizers', 'Select organizers for this event')); ?>',
    select_timezone: '<?php echo esc_js(sc_t('dashboard_pages.select_timezone', 'Select timezone')); ?>',

    // FAQ
    question: '<?php echo esc_js(sc_t('dashboard_pages.question', 'Question')); ?>',
    answer: '<?php echo esc_js(sc_t('dashboard_pages.answer', 'Answer')); ?>',
    up: '<?php echo esc_js(sc_t('dashboard_pages.up', 'Up')); ?>',
    down: '<?php echo esc_js(sc_t('dashboard_pages.down', 'Down')); ?>',
    remove: '<?php echo esc_js(sc_t('dashboard_pages.remove', 'Remove')); ?>',

    // Extra fields
    label: '<?php echo esc_js(sc_t('dashboard_pages.label', 'Label')); ?>',
    type: '<?php echo esc_js(sc_t('dashboard_pages.type', 'Type')); ?>',
    default_value: '<?php echo esc_js(sc_t('dashboard_pages.default_value', 'Default Value')); ?>',
    options_comma: '<?php echo esc_js(sc_t('dashboard_pages.options_comma', 'Options (comma separated, for Select/Radio/Checkbox)')); ?>',
    required_field: '<?php echo esc_js(sc_t('dashboard_pages.required_field', 'Required Field')); ?>',

    // File upload
    file_uploaded: '<?php echo esc_js(sc_t('dashboard_pages.file_uploaded', 'File uploaded')); ?>',
    choose_pdf: '<?php echo esc_js(sc_t('dashboard_pages.choose_pdf', 'Choose PDF file...')); ?>',

    // Form validation
    fill_required_fields: '<?php echo esc_js(sc_t('events.fill_required_fields', 'Please fill in all required fields')); ?>',
    missing_required_fields: '<?php echo esc_js(sc_t('events.missing_required_fields', 'Missing Required Fields')); ?>',
    please_add: '<?php echo esc_js(sc_t('events.please_add', 'Please add')); ?>',
    required_field: '<?php echo esc_js(sc_t('events.required_field', 'Required Field')); ?>',

    // Image upload
    images_uploaded: '<?php echo esc_js(sc_t('events.images_uploaded', 'images uploaded successfully')); ?>',
    image_upload_failed: '<?php echo esc_js(sc_t('events.image_upload_failed', 'Image upload failed')); ?>',
    image_uploaded: '<?php echo esc_js(sc_t('events.image_uploaded', 'Image uploaded successfully')); ?>',
    error_processing_images: '<?php echo esc_js(sc_t('events.error_processing_images', 'Error processing images')); ?>'
};

// ===============================
// Global: Initialize Select2 for dropdowns
// ===============================
window.initSelect2 = function() {
    if (typeof jQuery === 'undefined') {
        console.error('jQuery not loaded');
        return;
    }

    var $ = jQuery;
    if (typeof $.fn.select2 === 'undefined') {
        console.warn('Select2 library not loaded');
        return;
    }

    // Only initialize once - check if already initialized
    if (window.select2Initialized) {
        // Debug removed
        return;
    }

    // Initializing Select2

    // Destroy existing Select2 instances first to avoid conflicts
    $('.select2').each(function() {
        if ($(this).hasClass("select2-hidden-accessible")) {
            try {
                $(this).select2('destroy');
            } catch(e) {
                console.warn('Error destroying Select2 instance:', e);
            }
        }
    });

    // Use modal as parent for proper z-index
    var modalParent = $('#eventModal');

    // Initialize Categories Select2
    if ($('#event-categories').length) {
        try {
            $('#event-categories').select2({
                placeholder: 'Select event categories',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap',
                dropdownParent: modalParent,
                dropdownCssClass: 'select2-dropdown-in-modal'
            });
            
        } catch(e) {
            console.error('Error initializing Categories Select2:', e);
        }
    }

    // Initialize Speakers Select2
    if ($('#event-speakers').length) {
        try {
            $('#event-speakers').select2({
                placeholder: 'Select speakers for this event',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap',
                dropdownParent: modalParent,
                dropdownCssClass: 'select2-dropdown-in-modal'
            });
            
        } catch(e) {
            console.error('Error initializing Speakers Select2:', e);
        }
    }

    // Initialize Organizers Select2
    if ($('#event-organizers').length) {
        try {
            $('#event-organizers').select2({
                placeholder: 'Select organizers for this event',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap',
                dropdownParent: modalParent,
                dropdownCssClass: 'select2-dropdown-in-modal'
            });
            
        } catch(e) {
            console.error('Error initializing Organizers Select2:', e);
        }
    }

    // Initialize Timezone Select2
    if ($('#event-timezone').length) {
        try {
            $('#event-timezone').select2({
                placeholder: 'Select timezone',
                allowClear: false,
                width: '100%',
                theme: 'bootstrap',
                dropdownParent: modalParent,
                dropdownCssClass: 'select2-dropdown-in-modal'
            });
            
        } catch(e) {
            console.error('Error initializing Timezone Select2:', e);
        }
    }

    // Mark as initialized
    window.select2Initialized = true;
    

    // Apply any pending Select2 values (from Edit Event)
    if (window.pendingSelect2Values) {
        
        setTimeout(function() {
            if (typeof window.applyPendingSelect2Values === 'function') {
                window.applyPendingSelect2Values();
            }
        }, 100);
    }
};

// ===============================
// Global: Apply pending Select2 values
// ===============================
window.applyPendingSelect2Values = function() {
    if (typeof jQuery === 'undefined') return;
    var $ = jQuery;


    if (window.pendingSelect2Values) {
        var values = window.pendingSelect2Values;

        // Check Select2 initialization status

        // Categories
        if (values.categories && values.categories.length > 0) {
            $('#event-categories').val(values.categories).trigger('change');
        }
        // Speakers
        if (values.speakers && values.speakers.length > 0) {
            $('#event-speakers').val(values.speakers).trigger('change');
        }
        // Organizers
        if (values.organizers && values.organizers.length > 0) {
            $('#event-organizers').val(values.organizers).trigger('change');
        }

        // Timezone
        if (values.timezone) {
            var $tz = $('#event-timezone');
            if ($tz.find('option[value="' + values.timezone + '"]').length > 0) {
                $tz.val(values.timezone).trigger('change');
            } else {
                console.warn('Timezone option not found:', values.timezone);
            }
        }


        // Clear pending values
        window.pendingSelect2Values = null;
    }
};

// ===============================
// Main Events Script
// ===============================
jQuery(document).ready(function($) {
    'use strict';

    // ===============================
    // Image Compression Helper Function
    // ===============================
    const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2MB - compress if larger
    const MAX_DIMENSION = 2000; // Max width/height in pixels
    const COMPRESSION_QUALITY = 0.85; // 85% quality for JPEG

    /**
     * Compress image file using Canvas API
     * @param {File} file - Original image file
     * @returns {Promise<File>} - Compressed file
     */
    function compressImage(file) {
        return new Promise((resolve, reject) => {
            // If file is small enough or not an image, return as-is
            if (file.size <= MAX_IMAGE_SIZE || !file.type.startsWith('image/')) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // Calculate new dimensions
                    let width = img.width;
                    let height = img.height;

                    if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
                        if (width > height) {
                            height = Math.round((height * MAX_DIMENSION) / width);
                            width = MAX_DIMENSION;
                        } else {
                            width = Math.round((width * MAX_DIMENSION) / height);
                            height = MAX_DIMENSION;
                        }
                    }

                    // Create canvas and compress
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert to blob
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            // Create new file from blob
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        } else {
                            resolve(file); // Fallback to original
                        }
                    }, 'image/jpeg', COMPRESSION_QUALITY);
                };
                img.onerror = function() {
                    resolve(file); // Fallback to original on error
                };
                img.src = e.target.result;
            };
            reader.onerror = function() {
                resolve(file); // Fallback to original on error
            };
            reader.readAsDataURL(file);
        });
    }

    /**
     * Compress multiple images
     * @param {FileList} files - Array of files
     * @returns {Promise<File[]>} - Array of compressed files
     */
    async function compressImages(files) {
        const compressed = [];
        for (let i = 0; i < files.length; i++) {
            const file = await compressImage(files[i]);
            compressed.push(file);
        }
        return compressed;
    }

    let eventsTable;
    let currentFilter = 'all';

    // ===============================
    // 0) Refresh Stats Cards via AJAX
    // ===============================
    function refreshStats() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_events_page_stats',
                nonce: scDashboard.nonce
            }
        }).done(function(response) {
            if (response.success && response.data) {
                var stats = response.data;
                $('#stat-published').text(parseInt(stats.published, 10) || 0);
                $('#stat-draft').text(parseInt(stats.draft, 10) || 0);
                $('#stat-upcoming').text(parseInt(stats.upcoming, 10) || 0);
                $('#stat-tickets-sold').text(parseInt(stats.tickets_sold, 10) || 0);
            }
        }).fail(function() {
            console.warn('Failed to refresh events stats');
        });
    }

    // ===============================
    // 1) Load Events via AJAX with Pagination
    // ===============================
    let currentPage = 1;
    let perPage = 20;
    let searchTerm = '';
    let categoryFilter = '';
    let totalPages = 1;

    function loadEvents(filter = 'all', page = 1) {
        currentFilter = filter;
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_events_paginated',
                nonce: scDashboard.nonce,
                filter: filter,
                page: page,
                per_page: perPage,
                search: searchTerm,
                category_id: categoryFilter
            },
            beforeSend: function() {
                const tbody = $('#events-table tbody');
                tbody.html('<tr><td colspan="8" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">Loading...</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success && response.data.events) {
                renderEventsTable(response.data.events);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
                // Re-apply client-side filter after AJAX renders new rows
                if (searchTerm) { filterVisibleRows(searchTerm); }
            } else {
                $('#events-table tbody').html(
                    '<tr><td colspan="8" class="text-center py-5">' +
                    '<i class="fa fa-exclamation-triangle fa-3x text-muted"></i>' +
                    '<p class="mt-3">' + (response.data.message || 'Failed to load events') + '</p>' +
                    '</td></tr>'
                );
                $('#events-pagination-controls').hide();
            }
        }).fail(function(xhr, status, error) {
            $('#events-table tbody').html(
                '<tr><td colspan="8" class="text-center py-5">' +
                '<i class="fa fa-exclamation-triangle fa-3x text-danger"></i>' +
                '<p class="mt-3">Error loading events. Please try again.</p>' +
                '</td></tr>'
            );
            $('#events-pagination-controls').hide();
        });
    }

    // Update pagination controls
    function updatePagination(total, pages, current) {
        totalPages = pages;
        currentPage = current;

        if (pages <= 1) {
            $('#events-pagination-controls').hide();
            return;
        }

        // Show pagination controls
        $('#events-pagination-controls').show();

        // Update showing info
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#events-showing-info').text(`Showing ${start}-${end} of ${total}`);

        // Build pagination buttons
        const paginationHtml = [];

        // Previous button
        paginationHtml.push(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">Previous</a>
            </li>
        `);

        // Page numbers
        const maxButtons = 5;
        let startPage = Math.max(1, current - Math.floor(maxButtons / 2));
        let endPage = Math.min(pages, startPage + maxButtons - 1);

        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            paginationHtml.push(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                paginationHtml.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml.push(`
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        if (endPage < pages) {
            if (endPage < pages - 1) {
                paginationHtml.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            paginationHtml.push(`<li class="page-item"><a class="page-link" href="#" data-page="${pages}">${pages}</a></li>`);
        }

        // Next button
        paginationHtml.push(`
            <li class="page-item ${current === pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current + 1}">Next</a>
            </li>
        `);

        $('#events-pagination').html(paginationHtml.join(''));
    }

    // Pagination click handler
    $(document).on('click', '#events-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadEvents(currentFilter, page);
        }
    });

    // ===============================
    // Client-side row filter (instant visual feedback)
    // ===============================
    function filterVisibleRows(term) {
        if (!term) {
            $('#events-table tbody tr').show();
            return;
        }
        var lower = term.toLowerCase();
        $('#events-table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(lower) >= 0);
        });
    }

    // Search handler with debounce — use 'input' for paste/autocomplete support
    let searchTimeout;

    // Use event delegation for robustness (works even if element is re-created)
    $(document).on('input keyup', '#events-search', function(e) {
        var newVal = $(this).val().trim();

        // Immediately apply client-side filter for instant feedback
        filterVisibleRows(newVal);

        if (newVal === searchTerm && e.type !== 'keyup') return; // Skip duplicate input events
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchTerm = $('#events-search').val().trim();
            loadEvents(currentFilter, 1);
        }, 400);
    });

    // Category filter handler
    $('#events-category-filter').on('change', function() {
        categoryFilter = $(this).val();
        loadEvents(currentFilter, 1);
    });

    // ===============================
    // 2) Render Events Table
    // ===============================
    function renderEventsTable(events) {
        const tbody = $('#events-table tbody');
        tbody.empty();

        if (events.length === 0) {
            tbody.html(
                '<tr><td colspan="8" class="text-center py-5">' +
                '<i class="fa fa-calendar-times-o fa-3x text-muted"></i>' +
                '<p class="mt-3">No events found</p>' +
                '<a href="<?php echo home_url('/event-manager-dashboard/event-create'); ?>" class="btn btn-primary">' +
                '<i class="fa fa-plus"></i> Create Your First Event' +
                '</a>' +
                '</td></tr>'
            );
            return;
        }

        events.forEach(function(event) {
            // Status badge
            let statusBadge = '';
            if (event.status === 'publish') {
                statusBadge = '<span class="badge badge-success">Published</span>';
            } else if (event.status === 'draft') {
                statusBadge = '<span class="badge badge-secondary">Draft</span>';
            } else if (event.status === 'completed') {
                statusBadge = '<span class="badge badge-info">Completed</span>';
            } else if (event.status === 'disabled') {
                statusBadge = '<span class="badge badge-danger">Disabled</span>';
            } else {
                statusBadge = '<span class="badge badge-warning">' + event.status + '</span>';
            }

            // Build last action item (enable/disable/delete)
            let lastAction = '';
            if (event.status === 'disabled') {
                lastAction = '<a class="dropdown-item enable-event" data-id="' + event.ID + '" href="javascript:void(0);"><i class="fa fa-check text-success mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.enable_event', 'Enable Event')); ?></a>';
            } else if (event.status === 'completed') {
                lastAction = '<a class="dropdown-item" href="javascript:void(0);" style="cursor:default;opacity:0.5;"><i class="fa fa-check-circle text-info mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.completed', 'Completed')); ?></a>';
            } else if (event.attendees_count > 0) {
                let attendeeText = event.attendees_count === 1
                    ? '<?php echo esc_js(sc_t('dashboard_pages.disable_has_attendee', '1 attendee registered')); ?>'
                    : event.attendees_count + ' <?php echo esc_js(sc_t('dashboard_pages.attendees_registered', 'attendees registered')); ?>';
                lastAction = '<a class="dropdown-item disable-event" data-id="' + event.ID + '" href="javascript:void(0);"><i class="fa fa-ban text-warning mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.disable', 'Disable')); ?> (' + attendeeText + ')</a>';
            } else {
                lastAction = '<a class="dropdown-item delete-event text-danger" data-id="' + event.ID + '" href="javascript:void(0);"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>';
            }

            const row = `
                <tr data-event-id="${event.ID}">
                    <td><input type="checkbox" class="event-checkbox" value="${event.ID}"></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-blue mr-2">
                                <i class="fa fa-calendar"></i>
                            </div>
                            <div>
                                <strong>${escapeHtml(event.title)}</strong>
                            </div>
                        </div>
                    </td>
                    <td>
                        <i class="fa fa-clock-o text-muted"></i> ${event.date_display || 'Not set'}
                    </td>
                    <td>
                        ${event.venue || '<i class="fa fa-map-marker"></i> Not set'}
                    </td>
                    <td>
                        ${event.available_tickets || '<span class="text-muted">N/A</span>'}
                    </td>
                    <td>
                        <span class="badge badge-info">${event.tickets_sold || 0}</span>
                    </td>
                    <td>${statusBadge}</td>
                    <td class="text-right">
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/event-edit'); ?>?id=${event.ID}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/event-view'); ?>?id=${event.ID}"><i class="fa fa-eye mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.view_details', 'View Details')); ?></a>
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>?event_id=${event.ID}"><i class="fa fa-users mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.manage_attendees', 'Manage Attendees')); ?></a>
                                <a class="dropdown-item duplicate-event" data-id="${event.ID}" href="javascript:void(0);"><i class="fa fa-copy mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.duplicate', 'Duplicate')); ?></a>
                                <div class="dropdown-divider"></div>
                                ${lastAction}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // ===============================
    // 3) Create Event Button - Redirect to standalone page
    // ===============================
    $(document).on('click', '#create-event-btn', function(e) {
        e.preventDefault();
        window.location.href = '<?php echo home_url('/event-manager-dashboard/event-create'); ?>';
    });

    // Note: The Save Event form submit handler is now at the bottom of the file
    // (after all the data serialization functions) to ensure all data is properly serialized

    // ===============================
    // 5) View Event Details
    // ===============================
    $(document).on('click', '.view-event', function() {
        const eventId = $(this).data('id');

        // Show modal with fallback
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#eventDetailsModal').modal('show');
            } else {
                $('#eventDetailsModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            console.error('Error showing details modal:', e);
            $('#eventDetailsModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }

        $('#event-details-content').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_details',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success) {
                $('#event-details-content').html(response.data.html);
            } else {
                $('#event-details-content').html('<div class="alert alert-danger">' + response.data.message + '</div>');
            }
        });
    });

    // ===============================
    // 6) Edit Event
    // ===============================
    $(document).on('click', '.edit-event', function() {
        const eventId = $(this).data('id');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_for_edit',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success && response.data.event) {
                const event = response.data.event;

                // Basic Info
                $('#event-modal-title').text('Edit Event');
                $('#event-id').val(event.ID);
                $('#event-title').val(event.title);
                $('#event-slug').val(event.slug);

                // Set TinyMCE content
                if (typeof tinyMCE !== 'undefined') {
                    var editor = tinyMCE.get('event-description');
                    if (editor) {
                        editor.setContent(event.description || '');
                    }
                } else {
                    $('#event-description').val(event.description);
                }

                $('#event-status').val(event.status);

                // Set attendance tracking checkbox
                if (event.attendance_tracking === 'yes') {
                    $('#attendance-tracking').prop('checked', true);
                } else {
                    $('#attendance-tracking').prop('checked', false);
                }

                // Store values for later (after modal is shown)

                const categoryIds = (event.categories && event.categories.length > 0)
                    ? event.categories.map(String) : [];
                const speakerIds = (event.speakers && event.speakers.length > 0)
                    ? event.speakers.map(String) : [];
                const organizerIds = (event.organizers && event.organizers.length > 0)
                    ? event.organizers.map(String) : [];


                // Check available options in select elements
                $('#event-categories option').each(function() {
                });
                $('#event-speakers option').each(function() {
                });
                $('#event-organizers option').each(function() {
                });

                // Date & Time
                $('#event-start-date').val(event.start_date);
                $('#event-end-date').val(event.end_date);
                $('#event-start-time').val(event.start_time);
                $('#event-end-time').val(event.end_time);
                // Timezone will be set via pendingSelect2Values after modal opens

                // Location - Set event type based on location_type
                if (event.location_type === 'online') {
                    $('#event-type-online').prop('checked', true);
                    $('#btn-online').addClass('active');
                    $('#btn-offline').removeClass('active');
                    $('#offline-event-fields').hide();
                    $('#online-event-fields').show();
                } else {
                    $('#event-type-offline').prop('checked', true);
                    $('#btn-offline').addClass('active');
                    $('#btn-online').removeClass('active');
                    $('#offline-event-fields').show();
                    $('#online-event-fields').hide();
                }
                // Set address (venue location for offline)
                var venueLocation = event.venue || event.address || '';
                if (event.address && event.venue && event.venue !== event.address) {
                    venueLocation = event.venue + ', ' + event.address;
                }
                $('#event-address').val(venueLocation);
                $('#event-meeting-link').val(event.meeting_link || '');

                // Media
                if (event.image_url) {
                    $('#image-preview img').attr('src', event.image_url);
                    $('#image-preview').show();
                }

                // Note: Speakers and Organizers will be set after modal opens (see below)

                // Populate FAQ
                $('#faq-list').empty();
                if (event.faq && event.faq.length > 0) {
                    event.faq.forEach(function(faq) {
                        const faqId = 'faq-' + (++faqIndex);
                        const faqHtml = `
                            <div class="card mb-2" id="${faqId}">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Question *</label>
                                        <input type="text" class="form-control faq-question" value="${escapeHtml(faq.q || faq.sc_faq_title || '')}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Answer *</label>
                                        <textarea class="form-control faq-answer" rows="2" required>${escapeHtml(faq.a || faq.sc_faq_content || '')}</textarea>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-sm btn-secondary move-faq-up" data-id="${faqId}">
                                            <i class="fa fa-arrow-up"></i> Up
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary move-faq-down" data-id="${faqId}">
                                            <i class="fa fa-arrow-down"></i> Down
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger remove-faq" data-id="${faqId}">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#faq-list').append(faqHtml);
                    });
                }

                // Populate Extra Fields
                $('#extra-fields-list').empty();
                if (event.extra_fields && event.extra_fields.length > 0) {
                    event.extra_fields.forEach(function(field) {
                        const fieldId = 'field-' + (++extraFieldIndex);
                        const fieldType = field.field_type || field.type || 'text';

                        // Convert field_options to comma-separated string
                        let optionsValue = '';
                        if (field.field_options && Array.isArray(field.field_options)) {
                            // Eventin format: [{value: "Option1"}, {value: "Option2"}]
                            optionsValue = field.field_options.map(opt => opt.value || opt).join(', ');
                        } else if (field.options) {
                            if (Array.isArray(field.options)) {
                                // Array format: ["Option1", "Option2"]
                                optionsValue = field.options.join(', ');
                            } else if (typeof field.options === 'string' && field.options.length > 0) {
                                // String format (newline or comma separated)
                                optionsValue = field.options.split(/[\n,]+/).map(o => o.trim()).filter(o => o.length > 0).join(', ');
                            }
                        }

                        const fieldHtml = `
                            <div class="card mb-3" id="${fieldId}" data-field-id="${field.id || extraFieldIndex}">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Label *</label>
                                                <input type="text" class="form-control field-label" value="${escapeHtml(field.label || '')}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Type *</label>
                                                <select class="form-control field-type" required>
                                                    <option value="text" ${fieldType === 'text' ? 'selected' : ''}>Text</option>
                                                    <option value="number" ${fieldType === 'number' ? 'selected' : ''}>Number</option>
                                                    <option value="email" ${fieldType === 'email' ? 'selected' : ''}>Email</option>
                                                    <option value="select" ${fieldType === 'select' ? 'selected' : ''}>Select</option>
                                                    <option value="radio" ${fieldType === 'radio' ? 'selected' : ''}>Radio</option>
                                                    <option value="checkbox" ${fieldType === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                                                    <option value="textarea" ${fieldType === 'textarea' ? 'selected' : ''}>Textarea</option>
                                                    <option value="date" ${fieldType === 'date' ? 'selected' : ''}>Date</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Default Value</label>
                                                <input type="text" class="form-control field-default" value="${escapeHtml(field.default || '')}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Options (comma separated, for Select/Radio/Checkbox)</label>
                                        <input type="text" class="form-control field-options" value="${escapeHtml(optionsValue)}">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input field-required" ${field.required ? 'checked' : ''}>
                                        <label class="form-check-label">Required Field</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger remove-extra-field" data-id="${fieldId}">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        `;
                        $('#extra-fields-list').append(fieldHtml);
                    });
                }

                // Populate Additional Sections
                additionalSectionsData = [];
                sectionCounter = 0;
                if (event.additional_sections && event.additional_sections.length > 0) {
                    event.additional_sections.forEach(function(section) {
                        var newSection = {
                            id: sectionCounter++,
                            type: section.type || 'image_slider', // Default to image_slider for backward compatibility
                            title: section.title || '',
                        };

                        // Add type-specific fields
                        switch(newSection.type) {
                            case 'image_slider':
                                newSection.images = section.images || [];
                                break;
                            case 'about':
                                newSection.heading = section.heading || '';
                                // Support both new (description) and old (paragraph1/paragraph2) formats
                                if (section.description) {
                                    newSection.description = section.description;
                                } else if (section.paragraph1 || section.paragraph2) {
                                    newSection.description = (section.paragraph1 || '') + (section.paragraph2 ? '<br><br>' + section.paragraph2 : '');
                                } else {
                                    newSection.description = '';
                                }
                                newSection.button_text = section.button_text || '';
                                newSection.button_link = section.button_link || '';
                                newSection.main_image = section.main_image || '';
                                break;
                            case 'card':
                                newSection.heading = section.heading || '';
                                newSection.cards = section.cards || [
                                    { icon: '', title: '', description: '' },
                                    { icon: '', title: '', description: '' },
                                    { icon: '', title: '', description: '' }
                                ];
                                break;
                            case 'image_grid':
                                newSection.heading = section.heading || '';
                                newSection.images = section.images || [];
                                break;
                        }

                        additionalSectionsData.push(newSection);
                    });
                    renderAdditionalSections();
                }

                // Populate Tickets - Convert from Eventin format if needed
                ticketsArray = [];
                if (event.tickets && event.tickets.length > 0) {
                    ticketsArray = event.tickets.map(function(ticket) {
                        // Check if it's Eventin format (has sc_ticket_name) or dashboard format (has name)
                        if (ticket.sc_ticket_name !== undefined) {
                            // Convert from Eventin format to dashboard format
                            return {
                                id: ticket.sc_ticket_slug || generateTicketId(),
                                name: ticket.sc_ticket_name || '',
                                price: parseFloat(ticket.sc_ticket_price) || 0,
                                description: ticket.sc_ticket_description || '',
                                qty: parseInt(ticket.sc_available_tickets) || 0, // Note: Eventin has typo 'avaiilable'
                                min_qty: parseInt(ticket.sc_min_ticket) || 1,
                                max_qty: parseInt(ticket.sc_max_ticket) || 10,
                                sale_start: ticket.start_date || '',
                                sale_end: ticket.end_date || '',
                                status: ticket.sc_enable_ticket ? 'active' : 'disabled',
                                sold: parseInt(ticket.sc_sold_tickets) || 0,
                                use_coupons: ticket.sc_use_coupons === true || ticket.use_coupons === true
                            };
                        }
                        // Already in dashboard format
                        return ticket;
                    });
                    renderTicketsList();
                }

                // Populate Schedules
                schedulesArray = [];
                if (event.schedules && event.schedules.length > 0) {
                    schedulesArray = event.schedules;
                    renderSchedulesList();
                }

                // Populate Social Links
                socialLinksArray = [];
                if (event.social_links && event.social_links.length > 0) {
                    socialLinksArray = event.social_links;
                } else if (event.sc_event_socials && Array.isArray(event.sc_event_socials) && event.sc_event_socials.length > 0) {
                    // Convert from Eventin format
                    event.sc_event_socials.forEach(function(social) {
                        // Skip null/undefined entries
                        if (!social) return;
                        var iconClass = social.sc_social_icon || 'fa fa-link';
                        // Convert fab/fas to fa for FA4 compatibility
                        iconClass = iconClass.replace(/^(fab|fas) fa-/, 'fa fa-');
                        socialLinksArray.push({
                            icon: iconClass,
                            url: social.sc_social_url || ''
                        });
                    });
                }
                renderSocialLinks();

                // Load Event Banner
                if (event.banner_url) {
                    $('#banner-preview img').attr('src', event.banner_url);
                    $('#banner-preview').show();
                } else {
                    $('#banner-preview').hide().find('img').attr('src', '');
                }

                // Load Event Schedules PDF File
                if (event.schedules_file_url) {
                    $('#schedules-file-link').attr('href', event.schedules_file_url);
                    $('#schedules-file-preview').show();
                    $('.custom-file-label[for="event-schedules-file"]').text('File uploaded');
                } else {
                    $('#schedules-file-preview').hide();
                    $('#schedules-file-link').attr('href', '#');
                    $('.custom-file-label[for="event-schedules-file"]').text('Choose PDF file...');
                }
                // Remove any previous removal marker
                $('input[name="remove_schedules_file"]').remove();

                // Load Event Colors (mapped from brand_primary_color and brand_secondary_color)
                $('#calendar-bg-color').val(event.brand_primary_color || '#007bff');
                $('#calendar-text-color').val(event.brand_secondary_color || '#ffffff');

                // Store values globally to set after modal is shown
                window.pendingSelect2Values = {
                    categories: categoryIds,
                    speakers: speakerIds,
                    organizers: organizerIds,
                    timezone: event.timezone || ''
                };

                // Show modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#eventModal').modal('show');
                    } else {
                        $('#eventModal').addClass('show').css('display', 'block');
                        $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                        // Manually trigger the Select2 value setting for non-Bootstrap modal
                        setTimeout(function() {
                            applyPendingSelect2Values();
                        }, 500);
                    }
                } catch (e) {
                    console.error('Error showing edit modal:', e);
                    $('#eventModal').addClass('show').css('display', 'block');
                    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                    setTimeout(function() {
                        applyPendingSelect2Values();
                    }, 500);
                }
            } else {
                showError('Failed to load event data');
            }
        });
    });

    // ===============================
    // 7) Delete Event
    // ===============================
    $(document).on('click', '.delete-event', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const eventId = $(this).data('id');
        const btn = $(this);

        if (!eventId) {
            console.error('Delete: No event ID found');
            showError('Error: Event ID not found');
            return;
        }

        // Use SweetAlert2 directly if available, otherwise use confirm
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Are you sure you want to delete this event? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    deleteEvent(eventId, btn);
                }
            });
        } else if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
            deleteEvent(eventId, btn);
        }
    });

    function deleteEvent(eventId, btn) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_delete_event',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            console.log('Delete response:', response);
            if (response.success) {
                showSuccess(response.data.message);
                loadEvents(currentFilter);
                refreshStats();
            } else {
                showError(response.data.message || 'Error deleting event');
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
            }
        }).fail(function(xhr, status, error) {
            console.error('Delete AJAX error:', status, error);
            showError('Error deleting event');
            btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
        });
    }

    // ===============================
    // 8) Duplicate Event
    // ===============================
    $(document).on('click', '.duplicate-event', function() {
        const eventId = $(this).data('id');
        const btn = $(this);

        showConfirm('Create a duplicate of this event?').then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_duplicate_event',
                    nonce: scDashboard.nonce,
                    event_id: eventId
                }
            }).done(function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    loadEvents(currentFilter);
                    refreshStats();
                } else {
                    showError(response.data.message || 'Error duplicating event');
                }
                btn.prop('disabled', false).html('<i class="fa fa-copy"></i>');
            });
        });
    });

    // ===============================
    // 8.1) Disable Event (for events with attendees)
    // ===============================
    $(document).on('click', '.disable-event', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const eventId = $(this).data('id');
        const btn = $(this);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '<?php echo esc_js(sc_t('dashboard_pages.disable_event_title', 'Disable Event?')); ?>',
                text: '<?php echo esc_js(sc_t('dashboard_pages.disable_event_text', 'This event has attendees. It will be disabled instead of deleted to preserve data.')); ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f0ad4e',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<?php echo esc_js(sc_t('dashboard_pages.yes_disable', 'Yes, disable it')); ?>',
                cancelButtonText: '<?php echo esc_js(sc_t('dashboard_pages.cancel', 'Cancel')); ?>'
            }).then(function(result) {
                if (result.isConfirmed) {
                    disableEvent(eventId, btn);
                }
            });
        } else if (confirm('<?php echo esc_js(sc_t('dashboard_pages.disable_event_confirm', 'Disable this event?')); ?>')) {
            disableEvent(eventId, btn);
        }
    });

    function disableEvent(eventId, btn) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_update_event_status',
                nonce: scDashboard.nonce,
                event_id: eventId,
                status: 'disabled'
            }
        }).done(function(response) {
            if (response.success) {
                showSuccess(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.event_disabled', 'Event disabled successfully')); ?>');
                loadEvents(currentFilter);
                refreshStats();
            } else {
                showError(response.data.message || 'Error disabling event');
                btn.prop('disabled', false).html('<i class="fa fa-ban"></i>');
            }
        }).fail(function() {
            showError('Error disabling event');
            btn.prop('disabled', false).html('<i class="fa fa-ban"></i>');
        });
    }

    // ===============================
    // 8.2) Enable Event (re-enable disabled events)
    // ===============================
    $(document).on('click', '.enable-event', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const eventId = $(this).data('id');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_update_event_status',
                nonce: scDashboard.nonce,
                event_id: eventId,
                status: 'publish'
            }
        }).done(function(response) {
            if (response.success) {
                showSuccess(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.event_enabled', 'Event enabled successfully')); ?>');
                loadEvents(currentFilter);
                refreshStats();
            } else {
                showError(response.data.message || 'Error enabling event');
                btn.prop('disabled', false).html('<i class="fa fa-check"></i>');
            }
        }).fail(function() {
            showError('Error enabling event');
            btn.prop('disabled', false).html('<i class="fa fa-check"></i>');
        });
    });

    // ===============================
    // 9) Filter Events
    // ===============================
    $('.nav-tabs a[data-filter]').on('click', function(e) {
        e.preventDefault();
        $('.nav-tabs a').removeClass('active');
        $(this).addClass('active');
        loadEvents($(this).data('filter'));
    });

    // ===============================
    // 10) Refresh Events
    // ===============================
    $(document).on('click', '#refresh-events', function(e) {
        e.preventDefault();
        loadEvents(currentFilter);
    });

    // ===============================
    // 11) Image Preview
    // ===============================
    $('#event-image').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#image-preview img').attr('src', e.target.result);
                $('#image-preview').show();
            };
            reader.readAsDataURL(file);
            $('.custom-file-label[for="event-image"]').text(file.name);
        }
    });

    // Banner Preview
    $('#event-banner').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#banner-preview img').attr('src', e.target.result);
                $('#banner-preview').show();
            };
            reader.readAsDataURL(file);
            $('.custom-file-label[for="event-banner"]').text(file.name);
        }
    });

    // ===============================
    // 12) Manage Attendees
    // ===============================
    let attendeesTable;

    $(document).on('click', '.manage-attendees', function() {
        const eventId = $(this).data('id');
        $('#current-event-id').val(eventId);

        // Get event name
        const eventName = $(this).closest('tr').find('strong').text();
        $('#attendees-modal-title').text('Manage Attendees - ' + eventName);

        // Show modal with fallback
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#attendeesModal').modal('show');
            } else {
                $('#attendeesModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            console.error('Error showing attendees modal:', e);
            $('#attendeesModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }

        loadAttendees(eventId);
    });

    function loadAttendees(eventId) {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_attendees',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success) {
                renderAttendeesTable(response.data.attendees);
                updateAttendeesStats(response.data.stats);
            } else {
                $('#attendees-table tbody').html(
                    '<tr><td colspan="8" class="text-center py-5 text-danger">' +
                    (response.data.message || 'Error loading attendees') +
                    '</td></tr>'
                );
            }
        }).fail(function() {
            $('#attendees-table tbody').html(
                '<tr><td colspan="8" class="text-center py-5 text-danger">Error loading attendees</td></tr>'
            );
        });
    }

    function renderAttendeesTable(attendees) {
        const tbody = $('#attendees-table tbody');
        tbody.empty();

        if (attendees.length === 0) {
            tbody.html(
                '<tr><td colspan="8" class="text-center py-5">' +
                '<i class="fa fa-users fa-3x text-muted"></i>' +
                '<p class="mt-3">No attendees yet</p>' +
                '</td></tr>'
            );
            return;
        }

        attendees.forEach(function(attendee) {
            const row = `
                <tr data-attendee-id="${attendee.ID}">
                    <td><input type="checkbox" class="attendee-checkbox" value="${attendee.ID}"></td>
                    <td>${escapeHtml(attendee.name)}</td>
                    <td>${escapeHtml(attendee.email)}</td>
                    <td>${attendee.phone || 'N/A'}</td>
                    <td>${attendee.ticket_type || 'General'}</td>
                    <td>${attendee.status_badge}</td>
                    <td>
                        <span class="badge ${attendee.ticket_status === 'used' ? 'badge-success' : 'badge-secondary'}">
                            ${attendee.ticket_status === 'used' ? 'Used' : 'Unused'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info resend-email" data-id="${attendee.ID}" title="Resend Email">
                            <i class="fa fa-envelope"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-attendee" data-id="${attendee.ID}" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Initialize or reinitialize DataTable
        if (attendeesTable) {
            attendeesTable.destroy();
        }
        attendeesTable = $('#attendees-table').DataTable({
            pageLength: 20,
            lengthMenu: [[20, 50, 100, -1], [20, 50, 100, 'All']],
            order: [[1, 'asc']],
            columnDefs: [
                { orderable: false, targets: [0, 6, 7] }
            ]
        });
    }

    function updateAttendeesStats(stats) {
        $('#total-attendees-count').text(stats.total || 0);
        $('#used-tickets-count').text(stats.used || 0);
        $('#unused-tickets-count').text(stats.unused || 0);
        $('#capacity-remaining').text(stats.remaining || 'Unlimited');
    }

    // ===============================
    // 13) Ticket Status Display (Read-only)
    // ===============================
    // Ticket status is now displayed as read-only badge
    // Status is managed through Eventin's ticket scanner app

    // ===============================
    // 14) Export Attendees
    // ===============================
    $(document).on('click', '#export-attendees-btn', function() {
        const eventId = $('#current-event-id').val();
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_export_event_attendees',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success) {
                // Create CSV download
                const blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                setTimeout(function() {
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }, 100);
                showSuccess(response.data.message);
            } else {
                showError(response.data.message || 'Error exporting attendees');
            }
        }).fail(function() {
            showError('Error exporting attendees');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-download"></i> Export CSV');
        });
    });

    // ===============================
    // 15) Refresh Attendees
    // ===============================
    $(document).on('click', '#refresh-attendees-btn', function() {
        const eventId = $('#current-event-id').val();
        loadAttendees(eventId);
    });

    // ===============================
    // 16) Resend Email to Attendee
    // ===============================
    $(document).on('click', '.resend-email', function() {
        const attendeeId = $(this).data('id');
        const btn = $(this);

        showConfirm('Resend confirmation email to this attendee?').then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_resend_attendee_email',
                    nonce: scDashboard.nonce,
                    attendee_id: attendeeId
                }
            }).done(function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                } else {
                    showError(response.data.message || 'Error sending email');
                }
                btn.prop('disabled', false).html('<i class="fa fa-envelope"></i>');
            });
        });
    });

    // ===============================
    // 17) Delete Attendee
    // ===============================
    $(document).on('click', '.delete-attendee', function() {
        const attendeeId = $(this).data('id');
        const btn = $(this);

        showDeleteConfirm('Are you sure you want to delete this attendee? This action cannot be undone.').then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_attendee',
                    nonce: scDashboard.nonce,
                    attendee_id: attendeeId
                }
            }).done(function(response) {
                if (response.success) {
                    const eventId = $('#current-event-id').val();
                    loadAttendees(eventId);
                    showSuccess(response.data.message);
                } else {
                    showError(response.data.message || 'Error deleting attendee');
                    btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
                }
            });
        });
    });

    // ===============================
    // Helper: Escape HTML
    // ===============================
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        // Convert to string in case it's a number or other type
        text = String(text);
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ===============================
    // Handle Modal Close Buttons (X and Cancel)
    // ===============================
    $(document).on('click', '[data-dismiss="modal"]', function() {
        var modalId = $(this).closest('.modal').attr('id');
        if (modalId) {
            try {
                if (typeof $.fn.modal !== 'undefined') {
                    $('#' + modalId).modal('hide');
                } else {
                    $('#' + modalId).removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
            } catch (e) {
                $('#' + modalId).removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        }
    });

    // ===============================
    // 18) Tab Navigation Fallback
    // ===============================
    $('#event-tabs .nav-link').on('click', function(e) {
        e.preventDefault();

        var targetTab = $(this).attr('href');

        try {
            if (typeof $.fn.tab !== 'undefined') {
                $(this).tab('show');
            } else {
                // Manual tab switching
                $('#event-tabs .nav-link').removeClass('active');
                $(this).addClass('active');
                $('.tab-pane').removeClass('show active');
                $(targetTab).addClass('show active');
            }
        } catch (err) {
            // Fallback
            $('#event-tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            $('.tab-pane').removeClass('show active');
            $(targetTab).addClass('show active');
        }
    });

    // ===============================
    // 19) Auto-generate Slug from Title
    // ===============================
    $('#event-title').on('input', function() {
        // Only auto-generate if creating new event (not editing)
        if (!$('#event-id').val()) {
            const title = $(this).val();
            const slug = title.toLowerCase()
                .replace(/[^\w\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-')      // Replace spaces with hyphens
                .replace(/-+/g, '-')       // Replace multiple hyphens with single
                .trim();
            $('#event-slug').val(slug);
        }
    });

    // ===============================
    // 19) Edit/Confirm Slug
    // ===============================
    $('#edit-slug-btn').on('click', function() {
        $('#event-slug').prop('readonly', false).focus();
        $(this).hide();
        $('#confirm-slug-btn').show();
    });

    $('#confirm-slug-btn').on('click', function() {
        const slug = $('#event-slug').val().toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
        $('#event-slug').val(slug).prop('readonly', true);
        $(this).hide();
        $('#edit-slug-btn').show();
    });

    // ===============================
    // 21) All Day Event - Removed (not needed)
    // ===============================

    // ===============================
    // 22) Remove Image & Banner
    // ===============================
    $('#remove-image-btn').on('click', function() {
        $('#event-image').val('');
        $('.custom-file-label[for="event-image"]').text('Choose file...');
        $('#image-preview').hide();
    });

    $('#remove-banner-btn').on('click', function() {
        $('#event-banner').val('');
        $('.custom-file-label[for="event-banner"]').text('Choose banner...');
        $('#banner-preview').hide();
    });

    // Schedules PDF File handlers
    $('#event-schedules-file').on('change', function() {
        var file = this.files[0];
        if (file) {
            $('.custom-file-label[for="event-schedules-file"]').text(file.name);
        }
    });

    $('#remove-schedules-file-btn').on('click', function() {
        $('#event-schedules-file').val('');
        $('.custom-file-label[for="event-schedules-file"]').text('Choose PDF file...');
        $('#schedules-file-preview').hide();
        // Mark for removal on save
        $('#event-form').append('<input type="hidden" name="remove_schedules_file" value="1">');
    });

    // ===============================
    // 23) Speakers & Organizers - Now using Select instead of manual input
    // ===============================
    // Removed old manual input code - now managed via separate pages

    // ===============================
    // 24.5) Social Links Management
    // ===============================
    // Available social icons (Font Awesome 4.7 compatible + Eventin Pro format)
    var socialIcons = [
        { id: 'fa fa-facebook', name: 'Facebook', class: 'fa fa-facebook' },
        { id: 'fa fa-facebook-square', name: 'Facebook Square', class: 'fa fa-facebook-square' },
        { id: 'fa fa-facebook-official', name: 'Facebook Official', class: 'fa fa-facebook-official' },
        { id: 'fa fa-linkedin', name: 'LinkedIn', class: 'fa fa-linkedin' },
        { id: 'fa fa-linkedin-square', name: 'LinkedIn Square', class: 'fa fa-linkedin-square' },
        { id: 'fa fa-twitter', name: 'Twitter', class: 'fa fa-twitter' },
        { id: 'fa fa-twitter-square', name: 'Twitter Square', class: 'fa fa-twitter-square' },
        { id: 'fa fa-youtube', name: 'YouTube', class: 'fa fa-youtube' },
        { id: 'fa fa-youtube-play', name: 'YouTube Play', class: 'fa fa-youtube-play' },
        { id: 'fa fa-youtube-square', name: 'YouTube Square', class: 'fa fa-youtube-square' },
        { id: 'fa fa-google', name: 'Google', class: 'fa fa-google' },
        { id: 'fa fa-google-plus', name: 'Google Plus', class: 'fa fa-google-plus' },
        { id: 'fa fa-google-plus-square', name: 'Google Plus Square', class: 'fa fa-google-plus-square' },
        { id: 'fa fa-vk', name: 'VK', class: 'fa fa-vk' },
        { id: 'fa fa-whatsapp', name: 'WhatsApp', class: 'fa fa-whatsapp' },
        { id: 'fa fa-instagram', name: 'Instagram', class: 'fa fa-instagram' },
        { id: 'fa fa-wordpress', name: 'WordPress', class: 'fa fa-wordpress' },
        { id: 'fa fa-snapchat', name: 'Snapchat', class: 'fa fa-snapchat' },
        { id: 'fa fa-snapchat-ghost', name: 'Snapchat Ghost', class: 'fa fa-snapchat-ghost' },
        { id: 'fa fa-snapchat-square', name: 'Snapchat Square', class: 'fa fa-snapchat-square' },
        { id: 'fa fa-reddit', name: 'Reddit', class: 'fa fa-reddit' },
        { id: 'fa fa-reddit-alien', name: 'Reddit Alien', class: 'fa fa-reddit-alien' },
        { id: 'fa fa-reddit-square', name: 'Reddit Square', class: 'fa fa-reddit-square' },
        { id: 'fa fa-pinterest', name: 'Pinterest', class: 'fa fa-pinterest' },
        { id: 'fa fa-pinterest-p', name: 'Pinterest P', class: 'fa fa-pinterest-p' },
        { id: 'fa fa-pinterest-square', name: 'Pinterest Square', class: 'fa fa-pinterest-square' },
        { id: 'fa fa-tumblr', name: 'Tumblr', class: 'fa fa-tumblr' },
        { id: 'fa fa-tumblr-square', name: 'Tumblr Square', class: 'fa fa-tumblr-square' },
        { id: 'fa fa-flickr', name: 'Flickr', class: 'fa fa-flickr' },
        { id: 'fa fa-vimeo', name: 'Vimeo', class: 'fa fa-vimeo' },
        { id: 'fa fa-vimeo-square', name: 'Vimeo Square', class: 'fa fa-vimeo-square' },
        { id: 'fa fa-weibo', name: 'Weibo', class: 'fa fa-weibo' },
        { id: 'fa fa-telegram', name: 'Telegram', class: 'fa fa-telegram' },
        { id: 'fa fa-slack', name: 'Slack', class: 'fa fa-slack' },
        { id: 'fa fa-github', name: 'GitHub', class: 'fa fa-github' },
        { id: 'fa fa-github-square', name: 'GitHub Square', class: 'fa fa-github-square' },
        { id: 'fa fa-dribbble', name: 'Dribbble', class: 'fa fa-dribbble' },
        { id: 'fa fa-behance', name: 'Behance', class: 'fa fa-behance' },
        { id: 'fa fa-behance-square', name: 'Behance Square', class: 'fa fa-behance-square' },
        { id: 'fa fa-spotify', name: 'Spotify', class: 'fa fa-spotify' },
        { id: 'fa fa-soundcloud', name: 'SoundCloud', class: 'fa fa-soundcloud' },
        { id: 'fa fa-twitch', name: 'Twitch', class: 'fa fa-twitch' },
        { id: 'fa fa-skype', name: 'Skype', class: 'fa fa-skype' },
        { id: 'fa fa-apple', name: 'Apple', class: 'fa fa-apple' },
        { id: 'fa fa-android', name: 'Android', class: 'fa fa-android' },
        { id: 'fa fa-globe', name: 'Website', class: 'fa fa-globe' },
        { id: 'fa fa-envelope', name: 'Email', class: 'fa fa-envelope' },
        { id: 'fa fa-envelope-o', name: 'Email Outline', class: 'fa fa-envelope-o' },
        { id: 'fa fa-phone', name: 'Phone', class: 'fa fa-phone' },
        { id: 'fa fa-mobile', name: 'Mobile', class: 'fa fa-mobile' },
        { id: 'fa fa-link', name: 'Link', class: 'fa fa-link' },
        { id: 'fa fa-external-link', name: 'External Link', class: 'fa fa-external-link' },
        { id: 'fa fa-share-alt', name: 'Share', class: 'fa fa-share-alt' },
        { id: 'fa fa-rss', name: 'RSS', class: 'fa fa-rss' },
        { id: 'fa fa-rss-square', name: 'RSS Square', class: 'fa fa-rss-square' }
    ];

    var socialLinksArray = [];
    var socialLinkIndex = 0;

    // Render social link item
    function renderSocialLinkItem(index, icon, url) {
        var iconClass = icon || 'fa fa-link';
        var iconName = '';
        socialIcons.forEach(function(si) {
            if (si.id === iconClass || si.class === iconClass) {
                iconName = si.name;
            }
        });
        return `
            <div class="social-link-item d-flex align-items-center mb-2 p-2 border rounded" data-index="${index}">
                <button type="button" class="btn btn-sm btn-outline-secondary select-social-icon mr-2" data-index="${index}" title="Select Icon">
                    <i class="${iconClass}"></i>
                </button>
                <input type="hidden" class="social-icon-value" value="${iconClass}">
                <input type="text" class="form-control form-control-sm social-url-input" placeholder="Enter URL (https://...)" value="${url || ''}">
                <button type="button" class="btn btn-sm btn-danger ml-2 remove-social-link" data-index="${index}">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        `;
    }

    // Render all social links
    function renderSocialLinks() {
        var container = $('#social-links-container');
        container.empty();
        socialLinksArray.forEach(function(item, idx) {
            container.append(renderSocialLinkItem(idx, item.icon, item.url));
        });
    }

    // Add new social link
    $('#add-social-link-btn').on('click', function() {
        socialLinksArray.push({ icon: 'fa fa-link', url: '' });
        renderSocialLinks();
    });

    // Remove social link
    $(document).on('click', '.remove-social-link', function() {
        var index = $(this).data('index');
        socialLinksArray.splice(index, 1);
        renderSocialLinks();
    });

    // Update URL in array
    $(document).on('input', '.social-url-input', function() {
        var $item = $(this).closest('.social-link-item');
        var index = $item.data('index');
        socialLinksArray[index].url = $(this).val();
    });

    // Icon selector popup (using dropdown instead of modal to avoid conflicts)
    var currentIconIndex = -1;

    $(document).on('click', '.select-social-icon', function(e) {
        e.stopPropagation();
        currentIconIndex = $(this).data('index');
        var $btn = $(this);
        var $popup = $('#icon-selector-popup');

        // Create popup if not exists
        if ($popup.length === 0) {
            var popupHtml = `
                <div id="icon-selector-popup" class="card shadow" style="position: absolute; z-index: 9999; width: 350px; max-height: 400px; display: none;">
                    <div class="card-header py-2">
                        <input type="text" class="form-control form-control-sm" id="icon-search" placeholder="Search Icon...">
                    </div>
                    <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                        <div class="icon-grid d-flex flex-wrap" id="icon-grid">
                            ${socialIcons.map(function(icon) {
                                return '<button type="button" class="btn btn-outline-secondary m-1 icon-option" data-icon="' + icon.id + '" title="' + icon.name + '" style="width: 40px; height: 40px; padding: 0;"><i class="' + icon.class + '"></i></button>';
                            }).join('')}
                        </div>
                    </div>
                </div>
            `;
            $('body').append(popupHtml);
            $popup = $('#icon-selector-popup');

            // Icon search filter
            $(document).on('input', '#icon-search', function() {
                var search = $(this).val().toLowerCase();
                $('.icon-option').each(function() {
                    var name = $(this).attr('title').toLowerCase();
                    $(this).toggle(name.indexOf(search) !== -1);
                });
            });
        }

        // Position popup near button (fixed positioning for modal compatibility)
        var offset = $btn.offset();
        $popup.css({
            position: 'fixed',
            top: Math.min(offset.top + $btn.outerHeight() + 5, $(window).height() - 420),
            left: Math.min(offset.left, $(window).width() - 380)
        });

        // Toggle popup
        if ($popup.is(':visible')) {
            $popup.hide();
        } else {
            $('#icon-search').val('');
            $('.icon-option').show();
            $popup.show();
        }
    });

    // Close popup when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#icon-selector-popup, .select-social-icon').length) {
            $('#icon-selector-popup').hide();
        }
    });

    // Select icon
    $(document).on('click', '.icon-option', function() {
        var icon = $(this).data('icon');
        if (currentIconIndex >= 0 && socialLinksArray[currentIconIndex]) {
            socialLinksArray[currentIconIndex].icon = icon;
            renderSocialLinks();
        }
        $('#icon-selector-popup').hide();
    });

    // ===============================
    // 25) FAQ Management
    // ===============================
    let faqIndex = 0;

    $('#add-faq-btn').on('click', function() {
        const faqId = 'faq-' + (++faqIndex);
        const faqHtml = `
            <div class="card mb-2" id="${faqId}">
                <div class="card-body">
                    <div class="form-group">
                        <label>Question *</label>
                        <input type="text" class="form-control faq-question" placeholder="What is the refund policy?" required>
                    </div>
                    <div class="form-group">
                        <label>Answer *</label>
                        <textarea class="form-control faq-answer" rows="2" placeholder="Refunds allowed 48 hours before event..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-secondary move-faq-up" data-id="${faqId}">
                            <i class="fa fa-arrow-up"></i> Up
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary move-faq-down" data-id="${faqId}">
                            <i class="fa fa-arrow-down"></i> Down
                        </button>
                        <button type="button" class="btn btn-sm btn-danger remove-faq" data-id="${faqId}">
                            <i class="fa fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#faq-list').append(faqHtml);
    });

    $(document).on('click', '.remove-faq', function() {
        const id = $(this).data('id');
        $('#' + id).remove();
    });

    $(document).on('click', '.move-faq-up', function() {
        const id = $(this).data('id');
        const $card = $('#' + id);
        $card.insertBefore($card.prev());
    });

    $(document).on('click', '.move-faq-down', function() {
        const id = $(this).data('id');
        const $card = $('#' + id);
        $card.insertAfter($card.next());
    });

    // ===============================
    // 26) Extra Fields Management
    // ===============================
    let extraFieldIndex = 0;

    $('#add-extra-field-btn').on('click', function() {
        const fieldId = 'field-' + (++extraFieldIndex);
        const fieldHtml = `
            <div class="card mb-2" id="${fieldId}" data-field-id="${extraFieldIndex}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Field Label *</label>
                                <input type="text" class="form-control field-label" placeholder="Dress Code" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Field Type *</label>
                                <select class="form-control field-type" required>
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                    <option value="radio">Radio</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="date">Date</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Default Value</label>
                                <input type="text" class="form-control field-default" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Options (for Select/Radio/Checkbox - comma separated)</label>
                        <input type="text" class="form-control field-options" placeholder="Online,Offline,Hybrid">
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input field-required" id="req-${fieldId}">
                        <label class="custom-control-label" for="req-${fieldId}">Required</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger remove-field" data-id="${fieldId}">
                        <i class="fa fa-trash"></i> Remove
                    </button>
                </div>
            </div>
        `;
        $('#extra-fields-list').append(fieldHtml);
    });

    $(document).on('click', '.remove-field', function() {
        const id = $(this).data('id');
        $('#' + id).remove();
    });

    // ===============================
    // 28) Tickets Management
    // ===============================
    let ticketsArray = [];
    let editingTicketIndex = -1;

    // Helper function to generate unique ID
    function generateTicketId() {
        return 'ticket_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Render tickets list
    function renderTicketsList() {
        const container = $('#tickets-list');
        container.empty();

        if (ticketsArray.length === 0) {
            return;
        }

        ticketsArray.forEach(function(ticket, index) {
            const sold = ticket.sold || 0;
            const remaining = ticket.qty - sold;
            const statusBadge = ticket.status === 'active'
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-secondary">Disabled</span>';

            // Check if coupon-only ticket
            let priceDisplay;
            if (ticket.use_coupons) {
                priceDisplay = '<strong class="text-warning"><i class="fa fa-ticket"></i> Coupon Only</strong>';
            } else if (parseFloat(ticket.price) === 0) {
                priceDisplay = '<strong class="text-success">FREE</strong>';
            } else {
                priceDisplay = '<strong>' + parseFloat(ticket.price).toFixed(2) + ' EGP</strong>';
            }

            const ticketCard = `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">${escapeHtml(ticket.name)}</h5>
                                    ${statusBadge}
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-dark" type="button" data-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item edit-ticket" href="#" data-index="${index}">
                                            <i class="fa fa-edit"></i> Edit Ticket
                                        </a>
                                        <a class="dropdown-item duplicate-ticket" href="#" data-index="${index}">
                                            <i class="fa fa-copy"></i> Duplicate Ticket
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-ticket" href="#" data-index="${index}">
                                            <i class="fa fa-trash"></i> Delete Ticket
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <div class="text-muted small mb-1">Price</div>
                                <div>${priceDisplay}</div>
                            </div>

                            ${ticket.description ? '<p class="text-muted small mb-2">' + escapeHtml(ticket.description) + '</p>' : ''}

                            <div class="row mb-2">
                                <div class="col-6">
                                    <div class="text-muted small">Min Qty</div>
                                    <strong>${ticket.min_qty}</strong>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Max Qty</div>
                                    <strong>${ticket.max_qty}</strong>
                                </div>
                            </div>

                            <div class="mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input toggle-ticket-status"
                                           id="status-${index}" data-index="${index}"
                                           ${ticket.status === 'active' ? 'checked' : ''}>
                                    <label class="custom-control-label small" for="status-${index}">
                                        ${ticket.status === 'active' ? 'Active' : 'Disabled'}
                                    </label>
                                </div>
                            </div>

                            <hr>

                            <div class="small">
                                <div class="text-success mb-1">
                                    <i class="fa fa-calendar"></i> Sale will end on ${ticket.sale_end}
                                </div>
                                <div class="text-primary">
                                    <i class="fa fa-ticket"></i> <strong>${sold} / ${ticket.qty}</strong> sold
                                    ${remaining > 0
                                        ? '<span class="text-muted">(' + remaining + ' remaining)</span>'
                                        : '<span class="text-danger">(Sold Out)</span>'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            container.append(ticketCard);
        });
    }

    // Toggle price field visibility based on Use Coupons checkbox
    $('#ticket-use-coupons').on('change', function() {
        if ($(this).is(':checked')) {
            $('#ticket-price-group').hide();
            $('#ticket-price').val(0).removeAttr('required');
        } else {
            $('#ticket-price-group').show();
            $('#ticket-price').attr('required', true);
        }
    });

    // Open ticket modal for adding
    $('#add-ticket-card').on('click', function() {
        $('#ticket-modal-title').html('<i class="fa fa-ticket"></i> Add Ticket');
        $('#ticket-form')[0].reset();
        $('#ticket-id').val('');
        $('#ticket-index').val('');
        $('#ticket-status').prop('checked', true);
        $('#ticket-use-coupons').prop('checked', false);
        $('#ticket-price-group').show();
        $('#ticket-price').attr('required', true);

        // Set default dates (today and 30 days from now)
        const today = new Date().toISOString().split('T')[0];
        const futureDate = new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0];
        $('#ticket-sale-start').val(today);
        $('#ticket-sale-end').val(futureDate);

        editingTicketIndex = -1;

        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#ticketModal').modal('show');
            } else {
                $('#ticketModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#ticketModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Edit ticket
    $(document).on('click', '.edit-ticket', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        const ticket = ticketsArray[index];

        $('#ticket-modal-title').html('<i class="fa fa-edit"></i> Edit Ticket');
        $('#ticket-name').val(ticket.name);
        $('#ticket-price').val(ticket.price);
        $('#ticket-description').val(ticket.description);
        $('#ticket-qty').val(ticket.qty);
        $('#ticket-min-qty').val(ticket.min_qty);
        $('#ticket-max-qty').val(ticket.max_qty);

        // Set Flatpickr dates properly
        var saleStartEl = document.getElementById('ticket-sale-start');
        var saleEndEl = document.getElementById('ticket-sale-end');
        if (saleStartEl && saleStartEl._flatpickr) {
            saleStartEl._flatpickr.setDate(ticket.sale_start ? ticket.sale_start.split(' ')[0] : '', false);
        } else {
            $('#ticket-sale-start').val(ticket.sale_start);
        }
        if (saleEndEl && saleEndEl._flatpickr) {
            saleEndEl._flatpickr.setDate(ticket.sale_end ? ticket.sale_end.split(' ')[0] : '', false);
        } else {
            $('#ticket-sale-end').val(ticket.sale_end);
        }

        $('#ticket-status').prop('checked', ticket.status === 'active');
        $('#ticket-use-coupons').prop('checked', ticket.use_coupons === true);
        $('#ticket-id').val(ticket.id);
        $('#ticket-index').val(index);

        // Toggle price visibility based on use_coupons
        if (ticket.use_coupons) {
            $('#ticket-price-group').hide();
            $('#ticket-price').removeAttr('required');
        } else {
            $('#ticket-price-group').show();
            $('#ticket-price').attr('required', true);
        }

        editingTicketIndex = index;

        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#ticketModal').modal('show');
            } else {
                $('#ticketModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#ticketModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Duplicate ticket
    $(document).on('click', '.duplicate-ticket', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        const ticket = JSON.parse(JSON.stringify(ticketsArray[index])); // Deep copy

        ticket.id = generateTicketId();
        ticket.name = ticket.name + ' (Copy)';
        ticket.sold = 0;

        ticketsArray.push(ticket);
        renderTicketsList();
    });

    // Delete ticket
    $(document).on('click', '.delete-ticket', function(e) {
        e.preventDefault();
        const index = $(this).data('index');

        showDeleteConfirm('Are you sure you want to delete this ticket? This action cannot be undone.').then(function(result) {
            if (result.isConfirmed) {
                ticketsArray.splice(index, 1);
                renderTicketsList();
            }
        });
    });

    // Toggle ticket status
    $(document).on('change', '.toggle-ticket-status', function() {
        const index = $(this).data('index');
        const isActive = $(this).is(':checked');
        ticketsArray[index].status = isActive ? 'active' : 'disabled';
        renderTicketsList();
    });

    // Save ticket
    $('#save-ticket-btn').on('click', function() {
        const form = $('#ticket-form')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const useCoupons = $('#ticket-use-coupons').is(':checked');
        const ticketData = {
            id: $('#ticket-id').val() || generateTicketId(),
            name: $('#ticket-name').val(),
            price: useCoupons ? 0 : parseFloat($('#ticket-price').val()),
            description: $('#ticket-description').val(),
            qty: parseInt($('#ticket-qty').val()),
            min_qty: parseInt($('#ticket-min-qty').val()),
            max_qty: parseInt($('#ticket-max-qty').val()),
            sale_start: $('#ticket-sale-start').val(),
            sale_end: $('#ticket-sale-end').val(),
            status: $('#ticket-status').is(':checked') ? 'active' : 'disabled',
            use_coupons: useCoupons,
            sold: 0,
            qr_prefix: 'EVT',
            checkin_list: []
        };

        if (editingTicketIndex >= 0) {
            // Update existing ticket, preserve sold count
            ticketData.sold = ticketsArray[editingTicketIndex].sold || 0;
            ticketData.checkin_list = ticketsArray[editingTicketIndex].checkin_list || [];
            ticketsArray[editingTicketIndex] = ticketData;
        } else {
            // Add new ticket
            ticketsArray.push(ticketData);
        }

        renderTicketsList();

        // Hide modal
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#ticketModal').modal('hide');
            } else {
                $('#ticketModal').removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        } catch (e) {
            $('#ticketModal').removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    });

    // ===============================
    // 29) Schedules Management
    // ===============================
    let schedulesArray = [];

    // Render schedules list
    function renderSchedulesList() {
        const container = $('#schedules-container');
        container.empty();

        // Get event start and end dates
        const eventStartDate = $('#event-start-date').val();
        const eventEndDate = $('#event-end-date').val();

        if (schedulesArray.length === 0) {
            container.html('<p class="text-muted text-center py-3">No schedules added yet. Click "Add Schedule Topic" to create one.</p>');
            return;
        }

        schedulesArray.forEach(function(schedule, index) {
            // Build date input with min/max if event dates are set (no required to avoid hidden field validation)
            let dateInputAttrs = '';
            if (eventStartDate && eventEndDate) {
                dateInputAttrs = ` min="${eventStartDate}" max="${eventEndDate}"`;
            }

            const scheduleCard = `
                <div class="card mb-3 schedule-item" data-index="${index}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fa fa-calendar-check"></i> ${escapeHtml(schedule.topic_name) || 'New Schedule'}
                        </h6>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-secondary move-schedule-up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>
                                <i class="fa fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary move-schedule-down" data-index="${index}" ${index === schedulesArray.length - 1 ? 'disabled' : ''}>
                                <i class="fa fa-arrow-down"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger remove-schedule" data-index="${index}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-tag"></i> Topic Name</label>
                                    <input type="text" class="form-control schedule-topic-name"
                                           data-index="${index}"
                                           value="${escapeHtml(schedule.topic_name)}"
                                           placeholder="Enter topic name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-calendar"></i> Date</label>
                                    <input type="date" class="form-control schedule-date"
                                           data-index="${index}"
                                           value="${schedule.date}"${dateInputAttrs}>
                                    ${!eventStartDate || !eventEndDate ? '<small class="text-muted">Please set event start and end dates first</small>' : ''}
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-clock"></i> Start Time</label>
                                    <input type="time" class="form-control schedule-start-time"
                                           data-index="${index}"
                                           value="${schedule.start_time}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-clock"></i> End Time</label>
                                    <input type="time" class="form-control schedule-end-time"
                                           data-index="${index}"
                                           value="${schedule.end_time}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-user"></i> Speaker/Organizer <span class="text-danger">*</span></label>
                                    ${getSpeakerOrganizerField(index, schedule.speaker_id, schedule.speaker_name)}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-map-marker"></i> Location</label>
                                    <input type="text" class="form-control schedule-location"
                                           data-index="${index}"
                                           value="${escapeHtml(schedule.location)}"
                                           placeholder="Enter room or location">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fa fa-align-left"></i> Description</label>
                            <textarea class="form-control schedule-description"
                                      data-index="${index}"
                                      rows="3"
                                      placeholder="Enter schedule description">${escapeHtml(schedule.description)}</textarea>
                        </div>
                    </div>
                </div>
            `;
            container.append(scheduleCard);
        });
    }

    // Helper function to get speaker/organizer field (select + text)
    function getSpeakerOrganizerField(index, selectedId, speakerName) {
        // Get ALL available speakers and organizers from the system (not just selected ones)
        let options = '<option value="">-- Select Speaker/Organizer --</option>';
        let hasOptions = false;

        // Get all speakers from the speakers select dropdown
        $('#event-speakers option').each(function() {
            const val = $(this).val();
            if (val) {
                hasOptions = true;
                const selected = val == selectedId ? 'selected' : '';
                options += `<option value="${val}" ${selected}>${escapeHtml($(this).text())} (Speaker)</option>`;
            }
        });

        // Get all organizers from the organizers select dropdown
        $('#event-organizers option').each(function() {
            const val = $(this).val();
            if (val) {
                hasOptions = true;
                const selected = val == selectedId ? 'selected' : '';
                options += `<option value="${val}" ${selected}>${escapeHtml($(this).text())} (Organizer)</option>`;
            }
        });

        if (hasOptions) {
            // Return select + text input
            return `
                <select class="form-control schedule-speaker" data-index="${index}">
                    ${options}
                </select>
                <input type="text" class="form-control schedule-speaker-name mt-2"
                       data-index="${index}"
                       value="${escapeHtml(speakerName || '')}"
                       placeholder="Or type name manually if not in list"
                       ${selectedId ? 'disabled' : ''}>
                <small class="text-muted">Select from list OR type a name manually</small>
            `;
        } else {
            // No speakers/organizers in system - show only text input
            return `
                <input type="hidden" class="schedule-speaker" data-index="${index}" value="">
                <input type="text" class="form-control schedule-speaker-name"
                       data-index="${index}"
                       value="${escapeHtml(speakerName || '')}"
                       placeholder="Enter speaker/organizer name"
                       required>
                <small class="text-muted"><i class="fa fa-info-circle"></i> No speakers/organizers found in system. Type name manually.</small>
            `;
        }
    }

    // Add schedule button
    $('#add-schedule-btn').on('click', function() {
        const newSchedule = {
            topic_name: '',
            date: '',
            start_time: '',
            end_time: '',
            speaker_id: '',
            speaker_name: '',
            location: '',
            description: ''
        };

        schedulesArray.push(newSchedule);
        renderSchedulesList();

        // Scroll to the new schedule item
        setTimeout(function() {
            const newItem = $('.schedule-item').last();
            if (newItem.length) {
                newItem[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                newItem.find('.schedule-topic-name').focus();
            }
        }, 100);
    });

    // Remove schedule
    $(document).on('click', '.remove-schedule', function() {
        const index = $(this).data('index');

        showDeleteConfirm('Are you sure you want to remove this schedule topic?').then(function(result) {
            if (result.isConfirmed) {
                schedulesArray.splice(index, 1);
                renderSchedulesList();
            }
        });
    });

    // Move schedule up
    $(document).on('click', '.move-schedule-up', function() {
        const index = parseInt($(this).data('index'));
        if (index > 0) {
            // Swap with previous item
            const temp = schedulesArray[index];
            schedulesArray[index] = schedulesArray[index - 1];
            schedulesArray[index - 1] = temp;
            renderSchedulesList();
        }
    });

    // Move schedule down
    $(document).on('click', '.move-schedule-down', function() {
        const index = parseInt($(this).data('index'));
        if (index < schedulesArray.length - 1) {
            // Swap with next item
            const temp = schedulesArray[index];
            schedulesArray[index] = schedulesArray[index + 1];
            schedulesArray[index + 1] = temp;
            renderSchedulesList();
        }
    });

    // Update schedule data when fields change
    $(document).on('input change', '.schedule-topic-name, .schedule-date, .schedule-start-time, .schedule-end-time, .schedule-speaker, .schedule-speaker-name, .schedule-location, .schedule-description', function() {
        const index = parseInt($(this).data('index'));
        const $el = $(this);

        // Find the specific schedule class to determine the field
        let field = '';
        if ($el.hasClass('schedule-topic-name')) field = 'topic_name';
        else if ($el.hasClass('schedule-date')) field = 'date';
        else if ($el.hasClass('schedule-start-time')) field = 'start_time';
        else if ($el.hasClass('schedule-end-time')) field = 'end_time';
        else if ($el.hasClass('schedule-speaker')) field = 'speaker_id';
        else if ($el.hasClass('schedule-speaker-name')) field = 'speaker_name';
        else if ($el.hasClass('schedule-location')) field = 'location';
        else if ($el.hasClass('schedule-description')) field = 'description';

        if (field && schedulesArray[index] !== undefined) {
            schedulesArray[index][field] = $el.val();
        }
    });

    // Toggle between speaker select and text input
    $(document).on('change', '.schedule-speaker', function() {
        const index = parseInt($(this).data('index'));
        const $nameInput = $(this).closest('.form-group').find('.schedule-speaker-name');

        if ($(this).val()) {
            // Speaker selected from list - disable text input and clear it
            $nameInput.val('').prop('disabled', true);
            if (schedulesArray[index]) {
                schedulesArray[index].speaker_name = '';
            }
        } else {
            // No speaker selected - enable text input
            $nameInput.prop('disabled', false);
        }
    });

    // When typing in speaker name, clear the select
    $(document).on('input', '.schedule-speaker-name', function() {
        const index = parseInt($(this).data('index'));
        const $select = $(this).closest('.form-group').find('.schedule-speaker');

        if ($(this).val().trim()) {
            // Text entered - clear select
            $select.val('');
            if (schedulesArray[index]) {
                schedulesArray[index].speaker_id = '';
            }
        }
    });

    // Re-render schedules when event dates change to update date restrictions
    $('#event-start-date, #event-end-date').on('change', function() {
        if (schedulesArray.length > 0) {
            renderSchedulesList();
        }
    });

    // Re-render schedules when speakers or organizers selection changes
    $('#event-speakers, #event-organizers').on('change', function() {
        if (schedulesArray.length > 0) {
            renderSchedulesList();
        }
    });

    // ===============================
    // 30) Serialize data before form submit
    // ===============================
    $('#event-form').on('submit', function(e) {
        // Prevent default temporarily
        e.preventDefault();

        // ===============================
        // VALIDATION - Check Required Fields
        // ===============================
        let missingFields = [];

        // Check event title
        if (!$('#event-title').val().trim()) {
            missingFields.push('Event Title');
        }

        // Check start date
        if (!$('#event-start-date').val()) {
            missingFields.push('Start Date');
        }

        // Check schedules - each schedule must have either speaker_id or speaker_name
        let scheduleErrors = [];
        schedulesArray.forEach(function(schedule, index) {
            if (!schedule.speaker_id && !schedule.speaker_name) {
                scheduleErrors.push('Schedule #' + (index + 1) + ' (' + (schedule.topic_name || 'Untitled') + ') requires a Speaker/Organizer');
            }
        });

        if (scheduleErrors.length > 0) {
            scheduleErrors.forEach(function(err) {
                missingFields.push(err);
            });
        }

        // If there are missing fields, show error and stop
        if (missingFields.length > 0) {
            // Configure Toastr for error messages
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "timeOut": "6000",
                "extendedTimeOut": "2000",
                "preventDuplicates": false,
                "newestOnTop": true
            };

            // Show main error message
            toastr.error(eventsTranslations.fill_required_fields, eventsTranslations.missing_required_fields);

            // Show each missing field as a separate toast with slight delay
            missingFields.forEach(function(field, index) {
                setTimeout(function() {
                    toastr.warning(eventsTranslations.please_add + ': ' + field, eventsTranslations.required_field);
                }, (index + 1) * 200);
            });

            // Focus on the first missing field - use native click instead of Bootstrap tab()
            setTimeout(function() {
                if (!$('#event-title').val().trim()) {
                    // Native click on tab to avoid Bootstrap dependency
                    $('#basic-tab')[0].click();
                    setTimeout(function() {
                        $('#event-title').focus();
                    }, 300);
                } else if (!$('#event-start-date').val()) {
                    $('#datetime-tab')[0].click();
                    setTimeout(function() {
                        $('#event-start-date').focus();
                    }, 300);
                }
            }, 100);

            return false;
        }

        // Get TinyMCE content (already exists)
        if (typeof tinyMCE !== 'undefined') {
            var editor = tinyMCE.get('event-description');
            if (editor) {
                $('#event-description').val(editor.getContent());
            }
        }

        // Speakers and Organizers are now handled via select fields
        // No need for manual serialization - IDs will be sent directly via form

        // Serialize FAQ
        const faqs = [];
        $('#faq-list .card').each(function() {
            faqs.push({
                q: $(this).find('.faq-question').val(),
                a: $(this).find('.faq-answer').val()
            });
        });
        $('#faq-data').val(JSON.stringify(faqs));

        // Serialize Extra Fields
        const extraFields = [];
        $('#extra-fields-list .card').each(function() {
            const fieldType = $(this).find('.field-type').val();
            const fieldOptions = $(this).find('.field-options').val();

            // Build field object matching Eventin format
            const field = {
                label: $(this).find('.field-label').val(),
                field_type: fieldType, // Changed from 'type' to 'field_type'
                required: $(this).find('.field-required').is(':checked'),
                show_attendee_form: true,
                id: $(this).data('field-id') || extraFields.length + 1
            };

            // Add field_options only for select/radio/checkbox types
            if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
                // Convert comma-separated string to array of objects
                field.field_options = fieldOptions.split(',')
                    .map(s => s.trim())
                    .filter(s => s.length > 0)
                    .map(option => ({ value: option }));
            }

            extraFields.push(field);
        });
        $('#extra-fields-data').val(JSON.stringify(extraFields));

        // Serialize Tickets
        $('#tickets-data').val(JSON.stringify(ticketsArray));

        // Serialize Schedules
        $('#schedules-data').val(JSON.stringify(schedulesArray));

        // Serialize Social Links
        $('#social-links-data').val(JSON.stringify(socialLinksArray));

        // Now submit the form (continue with existing AJAX)
        const formData = new FormData(this);
        formData.append('action', 'sc_create_or_update_event');
        formData.append('nonce', scDashboard.nonce);

        const btn = $('#save-event-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            if (response.success) {
                const wasNewEvent = !$('#event-id').val();
                const savedEventId = response.data.event_id || $('#event-id').val();

                showSuccess(response.data.message);

                // Hide modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#eventModal').modal('hide');
                    } else {
                        $('#eventModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#eventModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }

                loadEvents(currentFilter);
                refreshStats();

                // If this was a new event, reopen the modal for editing additional details
                if (wasNewEvent && savedEventId) {
                    setTimeout(function() {
                        // Trigger edit event programmatically
                        $('[data-action="edit-event"][data-id="' + savedEventId + '"]').first().click();
                    }, 500);
                }
            } else {
                showError(response.data.message || 'Error saving event');
            }
        }).fail(function() {
            showError('Error saving event. Please try again.');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Event');
        });
    });

    // ===============================
    // 28) Add Media Button (WordPress Media Library)
    // ===============================
    $('#add-media-btn').on('click', function(e) {
        e.preventDefault();

        // Check if wp.media is available
        if (typeof wp !== 'undefined' && wp.media) {
            var mediaUploader = wp.media({
                title: 'Add Media',
                button: {
                    text: 'Insert'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                // Insert image into TinyMCE editor
                if (typeof tinyMCE !== 'undefined') {
                    var editor = tinyMCE.get('event-description');
                    if (editor) {
                        editor.execCommand('mceInsertContent', false, '<img src="' + attachment.url + '" alt="' + attachment.alt + '" />');
                    }
                }
            });

            mediaUploader.open();
        } else {
            showWarning('Media library not available');
        }
    });

    // ===============================
    // Event Type Toggle (Offline/Online)
    // ===============================
    // Handle click on the button labels
    $(document).on('click', '#btn-offline, #btn-online', function(e) {
        var isOnline = $(this).attr('id') === 'btn-online';

        // Update radio buttons
        if (isOnline) {
            $('#event-type-online').prop('checked', true);
            $('#event-type-offline').prop('checked', false);
        } else {
            $('#event-type-offline').prop('checked', true);
            $('#event-type-online').prop('checked', false);
        }

        // Update button styles
        $('#btn-offline').toggleClass('active', !isOnline);
        $('#btn-online').toggleClass('active', isOnline);

        // Show/hide fields
        $('#offline-event-fields').toggle(!isOnline);
        $('#online-event-fields').toggle(isOnline);
    });

    // Also handle radio change (for accessibility)
    $('input[name="event_type"]').on('change', function() {
        var isOnline = $(this).val() === 'online';
        $('#offline-event-fields').toggle(!isOnline);
        $('#online-event-fields').toggle(isOnline);
        $('#btn-offline').toggleClass('active', !isOnline);
        $('#btn-online').toggleClass('active', isOnline);
    });

    // Initialize Select2 when modal is shown
    // ===============================
    $('#eventModal').on('shown.bs.modal', function () {
        // Reset the flag to allow re-initialization
        window.select2Initialized = false;
        // Initialize Select2 (it will automatically apply pending values when done)
        if (typeof initSelect2 === 'function') {
            initSelect2();
        }
    });

    // ===============================
    // Additional Sections Management
    // ===============================
    var additionalSectionsData = [];
    var sectionCounter = 0;

    // Add Section Button - Show modal to choose section type
    $('#add-section-btn').on('click', function() {
        $('#sectionTypeModal').modal('show');
    });

    // Handle section type selection
    $(document).on('click', '.section-type-card', function() {
        var sectionType = $(this).data('section-type');
        var sectionIndex = sectionCounter++;

        // Create section based on type
        var newSection = {
            id: sectionIndex,
            type: sectionType,
            title: '',
        };

        // Add type-specific fields
        switch(sectionType) {
            case 'image_slider':
                newSection.images = [];
                break;
            case 'about':
                newSection.heading = '';
                newSection.description = '';
                newSection.button_text = '';
                newSection.button_link = '';
                newSection.main_image = '';
                break;
            case 'card':
                newSection.heading = '';
                newSection.cards = [
                    { icon: '', title: '', description: '' },
                    { icon: '', title: '', description: '' },
                    { icon: '', title: '', description: '' }
                ];
                break;
            case 'image_grid':
                newSection.heading = '';
                newSection.images = [];
                break;
        }

        additionalSectionsData.push(newSection);
        renderAdditionalSections();
        $('#sectionTypeModal').modal('hide');
    });

    function renderAdditionalSections() {
        var html = '';
        additionalSectionsData.forEach(function(section, index) {
            // Get section type label
            var typeLabel = '';
            var typeIcon = '';
            switch(section.type) {
                case 'image_slider':
                    typeLabel = 'Image Slider Section';
                    typeIcon = 'fa-images';
                    break;
                case 'about':
                    typeLabel = 'About Section';
                    typeIcon = 'fa-info-circle';
                    break;
                case 'card':
                    typeLabel = 'Card Section';
                    typeIcon = 'fa-th';
                    break;
                case 'image_grid':
                    typeLabel = 'Image Grid Section';
                    typeIcon = 'fa-th-large';
                    break;
                default:
                    typeLabel = 'Section';
                    typeIcon = 'fa-th-large';
            }

            html += `
            <div class="card mb-3 section-card" data-section-id="${section.id}" data-section-type="${section.type}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span><i class="fa ${typeIcon}"></i> ${typeLabel} ${index + 1}</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-sm btn-outline-secondary move-section-up" ${index === 0 ? 'disabled' : ''}>
                            <i class="fa fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary move-section-down" ${index === additionalSectionsData.length - 1 ? 'disabled' : ''}>
                            <i class="fa fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger remove-section">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
            `;

            // Render different fields based on section type
            if (section.type === 'image_slider') {
                html += renderImageSliderFields(section);
            } else if (section.type === 'about') {
                html += renderAboutFields(section);
            } else if (section.type === 'card') {
                html += renderCardFields(section);
            } else if (section.type === 'image_grid') {
                html += renderImageGridFields(section);
            }

            html += `
                </div>
            </div>
            `;
        });

        $('#additional-sections-list').html(html);

        // Initialize WYSIWYG editors
        initializeWysiwygEditors();

        updateAdditionalSectionsData();
    }

    // Initialize WYSIWYG editors
    function initializeWysiwygEditors() {
        // Handle toolbar button clicks
        $(document).off('click', '.editor-btn').on('click', '.editor-btn', function(e) {
            e.preventDefault();
            var command = $(this).data('command');
            var editor = $(this).closest('.form-group').find('.wysiwyg-editor');

            if (command === 'createLink') {
                var url = prompt('Enter URL:');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
            } else {
                document.execCommand(command, false, null);
            }

            // Focus back on editor
            editor.focus();
        });
    }

    // Render Image Slider Section Fields
    function renderImageSliderFields(section) {
        return `
            <div class="form-group">
                <label>Section Title</label>
                <input type="text" class="form-control section-title" value="${section.title || ''}" placeholder="Enter section title">
            </div>
            <div class="form-group">
                <label>Images</label>
                <div class="section-images-container" data-section-id="${section.id}">
                    ${renderSectionImages(section)}
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-section-image">
                    <i class="fa fa-plus"></i> Add Image
                </button>
            </div>
        `;
    }

    // Render About Section Fields
    function renderAboutFields(section) {
        var uniqueId = 'section-description-' + section.id;
        // Merge paragraph1 and paragraph2 into single description field for backward compatibility
        var description = section.description || '';
        if (!description && (section.paragraph1 || section.paragraph2)) {
            description = (section.paragraph1 || '') + (section.paragraph2 ? '<br><br>' + section.paragraph2 : '');
        }

        return `
            <div class="form-group">
                <label>Main Heading</label>
                <input type="text" class="form-control section-heading" value="${section.heading || ''}" placeholder="Why Attend the Marketing Summit Event 2025">
            </div>
            <div class="form-group">
                <label>Description</label>
                <div class="editor-toolbar mb-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light editor-btn" data-command="bold" title="Bold"><i class="fa fa-bold"></i></button>
                        <button type="button" class="btn btn-light editor-btn" data-command="italic" title="Italic"><i class="fa fa-italic"></i></button>
                        <button type="button" class="btn btn-light editor-btn" data-command="underline" title="Underline"><i class="fa fa-underline"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm ml-2" role="group">
                        <button type="button" class="btn btn-light editor-btn" data-command="insertUnorderedList" title="Bullet List"><i class="fa fa-list-ul"></i></button>
                        <button type="button" class="btn btn-light editor-btn" data-command="insertOrderedList" title="Numbered List"><i class="fa fa-list-ol"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm ml-2" role="group">
                        <button type="button" class="btn btn-light editor-btn" data-command="createLink" title="Insert Link"><i class="fa fa-link"></i></button>
                    </div>
                </div>
                <div id="${uniqueId}" class="form-control section-description wysiwyg-editor" contenteditable="true" style="min-height: 150px; max-height: 300px; overflow-y: auto;">${description}</div>
                <small class="form-text text-muted">
                    <i class="fa fa-info-circle"></i> Use the toolbar above to format your text
                </small>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Button Text</label>
                        <input type="text" class="form-control section-button-text" value="${section.button_text || ''}" placeholder="About Event">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Button Link</label>
                        <input type="text" class="form-control section-button-link" value="${section.button_link || ''}" placeholder="https://...">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Main Image</label>
                ${section.main_image ? `
                    <div class="mb-2">
                        <img src="${section.main_image}" class="img-thumbnail" style="max-width: 200px;">
                        <button type="button" class="btn btn-sm btn-danger ml-2 remove-main-image">
                            <i class="fa fa-times"></i> Remove
                        </button>
                    </div>
                ` : ''}
                <button type="button" class="btn btn-sm btn-outline-primary add-main-image">
                    <i class="fa fa-plus"></i> ${section.main_image ? 'Change' : 'Add'} Image
                </button>
            </div>
        `;
    }

    // Render Card Section Fields
    function renderCardFields(section) {
        var html = `
            <div class="form-group">
                <label>Section Heading</label>
                <input type="text" class="form-control section-heading" value="${section.heading || ''}" placeholder="Where Marketers Go to Learn">
            </div>
            <hr>
            <h6 class="mb-3">Cards (3 Cards)</h6>
        `;

        section.cards.forEach(function(card, cardIndex) {
            html += `
                <div class="card mb-3 bg-light">
                    <div class="card-body">
                        <h6>Card ${cardIndex + 1}</h6>
                        <div class="form-group">
                            <label>Icon Image</label>
                            ${card.icon ? `
                                <div class="mb-2">
                                    <img src="${card.icon}" class="img-thumbnail" style="max-width: 80px;">
                                    <button type="button" class="btn btn-sm btn-danger ml-2 remove-card-icon" data-card-index="${cardIndex}">
                                        <i class="fa fa-times"></i> Remove
                                    </button>
                                </div>
                            ` : ''}
                            <button type="button" class="btn btn-sm btn-outline-primary add-card-icon" data-card-index="${cardIndex}">
                                <i class="fa fa-plus"></i> ${card.icon ? 'Change' : 'Add'} Icon
                            </button>
                        </div>
                        <div class="form-group">
                            <label>Card Title</label>
                            <input type="text" class="form-control card-title" data-card-index="${cardIndex}" value="${card.title || ''}" placeholder="Connect with Visionaries">
                        </div>
                        <div class="form-group">
                            <label>Card Description</label>
                            <textarea class="form-control card-description" data-card-index="${cardIndex}" rows="2" placeholder="Enter card description...">${card.description || ''}</textarea>
                        </div>
                    </div>
                </div>
            `;
        });

        return html;
    }

    // Render Image Grid Section Fields
    function renderImageGridFields(section) {
        return `
            <div class="form-group">
                <label>Section Heading</label>
                <input type="text" class="form-control section-heading" value="${section.heading || ''}" placeholder="Our Valued Sponsors & Partners">
            </div>
            <div class="form-group">
                <label>Grid Images</label>
                <div class="section-images-container" data-section-id="${section.id}">
                    ${renderSectionImages(section)}
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-section-image">
                    <i class="fa fa-plus"></i> Add Image
                </button>
            </div>
        `;
    }

    function renderSectionImages(section) {
        if (!section.images || section.images.length === 0) {
            return '<p class="text-muted small">No images added yet</p>';
        }

        var html = '<div class="row">';
        section.images.forEach(function(image, imgIndex) {
            html += `
            <div class="col-md-3 mb-3 section-image-item" data-image-index="${imgIndex}">
                <div class="position-relative">
                    <img src="${image}" class="img-fluid img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">
                    <div class="position-absolute" style="top: 5px; right: 5px;">
                        <button type="button" class="btn btn-sm btn-danger remove-section-image mb-1" style="display: block; width: 32px;">
                            <i class="fa fa-times"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary move-image-left ${imgIndex === 0 ? 'd-none' : ''}" style="display: block; width: 32px;">
                            <i class="fa fa-arrow-left"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary move-image-right ${imgIndex === section.images.length - 1 ? 'd-none' : ''}" style="display: block; width: 32px;">
                            <i class="fa fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            `;
        });
        html += '</div>';
        return html;
    }

    // Remove Section
    $(document).on('click', '.remove-section', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        additionalSectionsData = additionalSectionsData.filter(s => s.id !== sectionId);
        renderAdditionalSections();
    });

    // Move Section Up
    $(document).on('click', '.move-section-up', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var index = additionalSectionsData.findIndex(s => s.id === sectionId);
        if (index > 0) {
            [additionalSectionsData[index - 1], additionalSectionsData[index]] =
            [additionalSectionsData[index], additionalSectionsData[index - 1]];
            renderAdditionalSections();
        }
    });

    // Move Section Down
    $(document).on('click', '.move-section-down', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var index = additionalSectionsData.findIndex(s => s.id === sectionId);
        if (index < additionalSectionsData.length - 1) {
            [additionalSectionsData[index], additionalSectionsData[index + 1]] =
            [additionalSectionsData[index + 1], additionalSectionsData[index]];
            renderAdditionalSections();
        }
    });

    // Update Title
    $(document).on('input', '.section-title', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.title = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Add Image (with compression for large files)
    $(document).on('click', '.add-section-image', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.multiple = true;

        input.onchange = async function(e) {
            var files = e.target.files;
            if (files.length > 0) {
                // Show loading indicator with compression message
                var section = additionalSectionsData.find(s => s.id === sectionId);
                if (!section.images) section.images = [];

                // Check if any file needs compression
                var needsCompression = Array.from(files).some(f => f.size > 2 * 1024 * 1024);
                var loadingText = needsCompression ? 'جاري ضغط ورفع الصور...' : 'Uploading...';
                var loadingHtml = '<div class="col-md-3 mb-3 text-center section-image-loading"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p class="small mt-2">' + loadingText + '</p></div>';
                card.find('.section-images-container').append(loadingHtml);

                try {
                    // Compress images before upload
                    var compressedFiles = await compressImages(files);

                    // Update loading text
                    card.find('.section-image-loading p').text('Uploading...');

                    var formData = new FormData();
                    for (var i = 0; i < compressedFiles.length; i++) {
                        formData.append('files[]', compressedFiles[i]);
                    }
                    formData.append('action', 'sc_upload_section_images');
                    formData.append('nonce', scDashboard.nonce);

                    $.ajax({
                        url: scDashboard.ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function(evt) {
                                if (evt.lengthComputable) {
                                    var percent = Math.round((evt.loaded / evt.total) * 100);
                                    card.find('.section-image-loading p').text('Uploading... ' + percent + '%');
                                }
                            }, false);
                            return xhr;
                        },
                        success: function(response) {
                            card.find('.section-image-loading').remove();

                            if (response.success && response.data) {
                                var section = additionalSectionsData.find(s => s.id === sectionId);
                                if (section) {
                                    if (!section.images) section.images = [];
                                    section.images = section.images.concat(response.data);
                                    renderAdditionalSections();
                                    if (typeof toastr !== 'undefined') {
                                        toastr.success(response.data.length + ' ' + eventsTranslations.images_uploaded);
                                    }
                                }
                            } else {
                                var errorMsg = response.data || 'Unknown error';
                                if (typeof toastr !== 'undefined') {
                                    toastr.error(eventsTranslations.image_upload_failed + ': ' + errorMsg);
                                } else {
                                    alert(eventsTranslations.image_upload_failed + ': ' + errorMsg);
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            card.find('.section-image-loading').remove();
                            if (typeof toastr !== 'undefined') {
                                toastr.error(eventsTranslations.image_upload_failed);
                            } else {
                                alert(eventsTranslations.image_upload_failed);
                            }
                        }
                    });
                } catch (err) {
                    card.find('.section-image-loading').remove();
                    console.error('Compression error:', err);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(eventsTranslations.error_processing_images);
                    } else {
                        alert(eventsTranslations.error_processing_images);
                    }
                }
            }
        };

        input.click();
    });

    // Remove Image
    $(document).on('click', '.remove-section-image', function() {
        var imageItem = $(this).closest('.section-image-item');
        var imgIndex = imageItem.data('image-index');
        var container = $(this).closest('.section-images-container');
        var sectionId = container.data('section-id');

        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.images) {
            section.images.splice(imgIndex, 1);
            renderAdditionalSections();
        }
    });

    // Move Image Left
    $(document).on('click', '.move-image-left', function() {
        var imageItem = $(this).closest('.section-image-item');
        var imgIndex = imageItem.data('image-index');
        var container = $(this).closest('.section-images-container');
        var sectionId = container.data('section-id');

        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.images && imgIndex > 0) {
            // Swap with previous image
            [section.images[imgIndex - 1], section.images[imgIndex]] =
            [section.images[imgIndex], section.images[imgIndex - 1]];
            renderAdditionalSections();
        }
    });

    // Move Image Right
    $(document).on('click', '.move-image-right', function() {
        var imageItem = $(this).closest('.section-image-item');
        var imgIndex = imageItem.data('image-index');
        var container = $(this).closest('.section-images-container');
        var sectionId = container.data('section-id');

        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.images && imgIndex < section.images.length - 1) {
            // Swap with next image
            [section.images[imgIndex], section.images[imgIndex + 1]] =
            [section.images[imgIndex + 1], section.images[imgIndex]];
            renderAdditionalSections();
        }
    });

    // ===============================
    // About Section Event Handlers
    // ===============================

    // Update Heading
    $(document).on('input', '.section-heading', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.heading = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Update Description (merged paragraph1 and paragraph2)
    $(document).on('input blur', '.section-description', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.description = $(this).html();
            // Remove old paragraph1 and paragraph2 if they exist
            delete section.paragraph1;
            delete section.paragraph2;
            updateAdditionalSectionsData();
        }
    });

    // Update Button Text
    $(document).on('input', '.section-button-text', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.button_text = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Update Button Link
    $(document).on('input', '.section-button-link', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.button_link = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Add Main Image (About Section) - with compression
    $(document).on('click', '.add-main-image', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var btn = $(this);

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';

        input.onchange = async function(e) {
            var file = e.target.files[0];
            if (file) {
                // Show loading state
                var originalText = btn.html();
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الرفع...');

                try {
                    // Compress if needed
                    var compressedFile = await compressImage(file);

                    var formData = new FormData();
                    formData.append('files[]', compressedFile);
                    formData.append('action', 'sc_upload_section_images');
                    formData.append('nonce', scDashboard.nonce);

                    $.ajax({
                        url: scDashboard.ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            btn.prop('disabled', false).html(originalText);
                            if (response.success && response.data && response.data.length > 0) {
                                var section = additionalSectionsData.find(s => s.id === sectionId);
                                if (section) {
                                    section.main_image = response.data[0];
                                    renderAdditionalSections();
                                    if (typeof toastr !== 'undefined') {
                                        toastr.success(eventsTranslations.image_uploaded);
                                    }
                                }
                            } else {
                                var errorMsg = response.data || 'Unknown error';
                                if (typeof toastr !== 'undefined') {
                                    toastr.error(eventsTranslations.image_upload_failed + ': ' + errorMsg);
                                } else {
                                    alert(eventsTranslations.image_upload_failed + ': ' + errorMsg);
                                }
                            }
                        },
                        error: function(xhr) {
                            btn.prop('disabled', false).html(originalText);
                            if (typeof toastr !== 'undefined') {
                                toastr.error(eventsTranslations.image_upload_failed);
                            } else {
                                alert(eventsTranslations.image_upload_failed);
                            }
                        }
                    });
                } catch (err) {
                    btn.prop('disabled', false).html(originalText);
                    console.error('Compression error:', err);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(eventsTranslations.error_processing_images);
                    } else {
                        alert(eventsTranslations.error_processing_images);
                    }
                }
            }
        };

        input.click();
    });

    // Remove Main Image (About Section)
    $(document).on('click', '.remove-main-image', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section) {
            section.main_image = '';
            renderAdditionalSections();
        }
    });

    // ===============================
    // Card Section Event Handlers
    // ===============================

    // Update Card Title
    $(document).on('input', '.card-title', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var cardIndex = $(this).data('card-index');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.cards && section.cards[cardIndex]) {
            section.cards[cardIndex].title = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Update Card Description
    $(document).on('input', '.card-description', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var cardIndex = $(this).data('card-index');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.cards && section.cards[cardIndex]) {
            section.cards[cardIndex].description = $(this).val();
            updateAdditionalSectionsData();
        }
    });

    // Add Card Icon
    $(document).on('click', '.add-card-icon', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var cardIndex = $(this).data('card-index');

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';

        input.onchange = function(e) {
            var file = e.target.files[0];
            if (file) {
                var formData = new FormData();
                formData.append('files[]', file);
                formData.append('action', 'sc_upload_section_images');
                formData.append('nonce', scDashboard.nonce);

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            var section = additionalSectionsData.find(s => s.id === sectionId);
                            if (section && section.cards && section.cards[cardIndex]) {
                                section.cards[cardIndex].icon = response.data[0];
                                renderAdditionalSections();
                            }
                        } else {
                            alert('Error uploading icon: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error uploading icon. Please try again.');
                    }
                });
            }
        };

        input.click();
    });

    // Remove Card Icon
    $(document).on('click', '.remove-card-icon', function() {
        var card = $(this).closest('.section-card');
        var sectionId = card.data('section-id');
        var cardIndex = $(this).data('card-index');
        var section = additionalSectionsData.find(s => s.id === sectionId);
        if (section && section.cards && section.cards[cardIndex]) {
            section.cards[cardIndex].icon = '';
            renderAdditionalSections();
        }
    });

    function updateAdditionalSectionsData() {
        $('#additional-sections-data').val(JSON.stringify(additionalSectionsData));
    }

    // ===============================
    // Select All Events Checkbox
    // ===============================
    $('#select-all-events').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.event-checkbox').prop('checked', isChecked);
    });

    // Update select-all checkbox when individual checkboxes change
    $(document).on('change', '.event-checkbox', function() {
        var totalCheckboxes = $('.event-checkbox').length;
        var checkedCheckboxes = $('.event-checkbox:checked').length;

        if (checkedCheckboxes === 0) {
            $('#select-all-events').prop('checked', false).prop('indeterminate', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#select-all-events').prop('checked', true).prop('indeterminate', false);
        } else {
            $('#select-all-events').prop('checked', false).prop('indeterminate', true);
        }
    });

    // ===============================
    // Initialize: Load Events & Stats
    // ===============================
    loadEvents('all');
    refreshStats();
});
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>

<!-- Initialize Select2 after all scripts are loaded -->
<script>
jQuery(document).ready(function($) {
    // Wait a bit for all scripts to load, then initialize Select2
    setTimeout(function() {
        if (typeof initSelect2 === 'function') {
            initSelect2();
        } else {
            console.error('initSelect2 function not found');
        }
    }, 300);
});
</script>
