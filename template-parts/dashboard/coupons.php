<?php
/**
 * Dashboard Discount & Coupons Page
 * With Server-Side AJAX Pagination for performance
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations - load early for permission check
$t_no_permission = sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.');

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t_no_permission);
}

$page_title = sc_t('dashboard_pages.discount_coupons', 'Discount & Coupons');
$load_flatpickr = true;
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'discount_coupons' => sc_t('dashboard_pages.discount_coupons', 'Discount & Coupons'),
    'coupons' => sc_t('nav.coupons', 'Coupons'),
    'delete_selected' => sc_t('dashboard_pages.delete_selected', 'Delete Selected'),
    'delete_all' => sc_t('dashboard_pages.delete_all', 'Delete All'),
    'import' => sc_t('dashboard_pages.import', 'Import'),
    'export' => sc_t('dashboard_pages.export', 'Export'),
    'create' => sc_t('dashboard_pages.create', 'Create'),
    'total_coupons' => sc_t('dashboard_pages.total_coupons', 'Total Coupons'),
    'active_coupons' => sc_t('dashboard_pages.active_coupons', 'Active Coupons'),
    'expired_coupons' => sc_t('dashboard_pages.expired_coupons', 'Expired Coupons'),
    'total_uses' => sc_t('dashboard_pages.total_uses', 'Total Uses'),
    'search_coupons' => sc_t('dashboard_pages.search_coupons', 'Search coupons...'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'all_types' => sc_t('dashboard_pages.all_types', 'All Types'),
    'percentage' => sc_t('dashboard_pages.percentage', 'Percentage'),
    'fixed' => sc_t('dashboard_pages.fixed', 'Fixed'),
    'all_status' => sc_t('dashboard_pages.all_status', 'All Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'expired' => sc_t('dashboard_pages.expired', 'Expired'),
    'coupon_code' => sc_t('dashboard_pages.coupon_code', 'Coupon Code'),
    'discount' => sc_t('dashboard_pages.discount', 'Discount'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'usage' => sc_t('dashboard_pages.usage', 'Usage'),
    'expiry' => sc_t('dashboard_pages.expiry', 'Expiry'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'per_page' => sc_t('dashboard_pages.per_page', 'per page'),
    'fully_used' => sc_t('dashboard_pages.fully_used', 'Fully Used'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    // Import Modal
    'import_coupons_csv' => sc_t('dashboard_pages.import_coupons_csv', 'Import Coupons from CSV'),
    'step1_download_template' => sc_t('dashboard_pages.step1_download_template', 'Step 1: Download Template'),
    'download_template_desc' => sc_t('dashboard_pages.download_template_desc', 'Download the CSV template and fill it with your coupon data:'),
    'download_csv_template' => sc_t('dashboard_pages.download_csv_template', 'Download CSV Template'),
    'csv_columns' => sc_t('dashboard_pages.csv_columns', 'CSV Columns'),
    'column' => sc_t('dashboard_pages.column', 'Column'),
    'required' => sc_t('dashboard_pages.required', 'Required'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'example' => sc_t('dashboard_pages.example', 'Example'),
    'yes' => sc_t('dashboard_pages.yes', 'Yes'),
    'no' => sc_t('dashboard_pages.no', 'No'),
    'unique_coupon_code' => sc_t('dashboard_pages.unique_coupon_code', 'Unique coupon code'),
    'discount_type_desc' => sc_t('dashboard_pages.discount_type_desc', 'percentage, fixed, or free'),
    'discount_amount' => sc_t('dashboard_pages.discount_amount', 'Discount amount'),
    'max_uses_desc' => sc_t('dashboard_pages.max_uses_desc', 'Max uses (0 = unlimited)'),
    'expiration_date_format' => sc_t('dashboard_pages.expiration_date_format', 'Expiration date (YYYY-MM-DD)'),
    'step2_select_event' => sc_t('dashboard_pages.step2_select_event', 'Step 2: Select Target Event'),
    'apply_imported_to' => sc_t('dashboard_pages.apply_imported_to', 'Apply imported coupons to:'),
    'all_events_no_restriction' => sc_t('dashboard_pages.all_events_no_restriction', 'All Events (No restriction)'),
    'all_imported_assigned' => sc_t('dashboard_pages.all_imported_assigned', 'All imported coupons will be assigned to this event'),
    'step3_upload_file' => sc_t('dashboard_pages.step3_upload_file', 'Step 3: Upload CSV File'),
    'select_csv_file' => sc_t('dashboard_pages.select_csv_file', 'Select your CSV file:'),
    'choose_file' => sc_t('dashboard_pages.choose_file', 'Choose file...'),
    'accepted_formats' => sc_t('dashboard_pages.accepted_formats', 'Accepted formats: .csv, .txt (First row should be the header)'),
    'file_preview' => sc_t('dashboard_pages.file_preview', 'File Preview (first 5 rows):'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'import_coupons' => sc_t('dashboard_pages.import_coupons', 'Import Coupons'),
    // Export Modal
    'export_coupons' => sc_t('dashboard_pages.export_coupons', 'Export Coupons'),
    'export_coupons_for' => sc_t('dashboard_pages.export_coupons_for', 'Export coupons for:'),
    'filter_by_status' => sc_t('dashboard_pages.filter_by_status', 'Filter by status:'),
    'active_only' => sc_t('dashboard_pages.active_only', 'Active Only'),
    'expired_only' => sc_t('dashboard_pages.expired_only', 'Expired Only'),
    'fully_used_only' => sc_t('dashboard_pages.fully_used_only', 'Fully Used Only'),
    'export_info' => sc_t('dashboard_pages.export_info', 'The exported file will include: Code, Discount, Event, Usage, Expiry Date, and Status'),
    'download_csv' => sc_t('dashboard_pages.download_csv', 'Download CSV'),
    // JavaScript translations
    'loading_coupons' => sc_t('dashboard_pages.loading_coupons', 'Loading coupons...'),
    'error_loading_coupons' => sc_t('dashboard_pages.error_loading_coupons', 'Error loading coupons'),
    'connection_error' => sc_t('dashboard_pages.connection_error', 'Connection error. Please try again.'),
    'no_coupons_found' => sc_t('dashboard_pages.no_coupons_found', 'No coupons found.'),
    'free_100' => sc_t('dashboard_pages.free_100', '100% (Free)'),
    'limit_reached' => sc_t('dashboard_pages.limit_reached', 'Limit Reached'),
    'event_deleted' => sc_t('dashboard_pages.event_deleted', 'Event Deleted'),
    'no_expiry' => sc_t('dashboard_pages.no_expiry', 'No Expiry'),
    'showing_x_to_y_of_z' => sc_t('dashboard_pages.showing_x_to_y_of_z', 'Showing %s to %s of %s coupons'),
    'delete_coupon_title' => sc_t('dashboard_pages.delete_coupon_title', 'Delete Coupon?'),
    'delete_coupon_confirm' => sc_t('dashboard_pages.delete_coupon_confirm', 'Are you sure you want to delete this coupon?'),
    'yes_delete' => sc_t('dashboard_pages.yes_delete', 'Yes, Delete'),
    'deleted' => sc_t('dashboard_pages.deleted', 'Deleted!'),
    'coupon_deleted' => sc_t('dashboard_pages.coupon_deleted', 'Coupon has been deleted.'),
    'error' => sc_t('dashboard_pages.error', 'Error'),
    'error_deleting_coupon' => sc_t('dashboard_pages.error_deleting_coupon', 'Error deleting coupon'),
    'delete_selected_title' => sc_t('dashboard_pages.delete_selected_title', 'Delete Selected Coupons?'),
    'delete_selected_confirm' => sc_t('dashboard_pages.delete_selected_confirm', 'Are you sure you want to delete %s selected coupons?'),
    'yes_delete_all' => sc_t('dashboard_pages.yes_delete_all', 'Yes, Delete All'),
    'deleting' => sc_t('dashboard_pages.deleting', 'Deleting...'),
    'selected_deleted' => sc_t('dashboard_pages.selected_deleted', 'Selected coupons have been deleted.'),
    'error_deleting_coupons' => sc_t('dashboard_pages.error_deleting_coupons', 'Error deleting coupons'),
    'delete_all_title' => sc_t('dashboard_pages.delete_all_title', 'Delete ALL Coupons?'),
    'delete_all_warning' => sc_t('dashboard_pages.delete_all_warning', 'This will permanently delete ALL coupons!'),
    'action_cannot_undone' => sc_t('dashboard_pages.action_cannot_undone', 'This action cannot be undone.'),
    'type_delete_confirm' => sc_t('dashboard_pages.type_delete_confirm', 'Type DELETE to confirm'),
    'please_type_delete' => sc_t('dashboard_pages.please_type_delete', 'Please type DELETE to confirm'),
    'deleting_all' => sc_t('dashboard_pages.deleting_all', 'Deleting All Coupons...'),
    'all_deleted' => sc_t('dashboard_pages.all_deleted', 'All coupons have been deleted.'),
    'no_file_selected' => sc_t('dashboard_pages.no_file_selected', 'No File Selected'),
    'select_csv_to_import' => sc_t('dashboard_pages.select_csv_to_import', 'Please select a CSV file to import.'),
    'importing' => sc_t('dashboard_pages.importing', 'Importing...'),
    'import_complete' => sc_t('dashboard_pages.import_complete', 'Import Complete!'),
    'import_failed' => sc_t('dashboard_pages.import_failed', 'Import Failed'),
    'error_importing' => sc_t('dashboard_pages.error_importing', 'Error importing coupons'),
    'template_downloaded' => sc_t('dashboard_pages.template_downloaded', 'Template Downloaded!'),
    'fill_template_upload' => sc_t('dashboard_pages.fill_template_upload', 'Fill the template with your coupon data and upload it.'),
    'exporting' => sc_t('dashboard_pages.exporting', 'Exporting...'),
    'export_complete' => sc_t('dashboard_pages.export_complete', 'Export Complete!'),
    'exported_x_coupons' => sc_t('dashboard_pages.exported_x_coupons', 'Exported %s coupons.'),
    'export_failed' => sc_t('dashboard_pages.export_failed', 'Export Failed'),
    'error_exporting' => sc_t('dashboard_pages.error_exporting', 'Error exporting coupons'),
    'failed_read_file' => sc_t('dashboard_pages.failed_read_file', 'Failed to read the file.'),
);

// Get counts efficiently using SQL
global $wpdb;
$total_coupons = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND post_status = 'publish'");

// Get active/expired counts
$today = current_time('Y-m-d');
$active_coupons = (int) $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'expiry_date'
    WHERE p.post_type = 'sc_coupon' AND p.post_status = 'publish'
    AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value >= %s)
", $today));
$expired_coupons = $total_coupons - $active_coupons;

// Get total usage count
$total_usage = (int) $wpdb->get_var("
    SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0) FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'usage_count'
    WHERE p.post_type = 'sc_coupon' AND p.post_status = 'publish'
");

// Get all events for dropdowns from sc_events custom table
$sc_events_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_results("SELECT id, title, start_date FROM $sc_events_table WHERE status = 'publish' ORDER BY title ASC");

// Get all coupon categories for filter/import dropdowns
$coupon_categories = class_exists('SC_Coupon_Category') ? SC_Coupon_Category::get_all() : array();
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['discount_coupons']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['coupons']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action d-flex flex-wrap">
                            <button class="btn btn-danger mr-2 mb-1" id="bulk-delete-btn" disabled>
                                <i class="fa fa-trash"></i> <?php echo $t['delete_selected']; ?>
                            </button>
                            <button class="btn btn-warning mr-2 mb-1" id="delete-all-btn">
                                <i class="fa fa-trash-o"></i> <?php echo $t['delete_all']; ?>
                            </button>
                            <button class="btn btn-info mr-2 mb-1" data-toggle="modal" data-target="#importCouponsModal">
                                <i class="fa fa-upload"></i> <?php echo $t['import']; ?>
                            </button>
                            <button class="btn btn-success mr-2 mb-1" data-toggle="modal" data-target="#exportCouponsModal">
                                <i class="fa fa-download"></i> <?php echo $t['export']; ?>
                            </button>
                            <a href="<?php echo home_url('/event-manager-dashboard/coupon-create'); ?>" class="btn btn-primary mb-1">
                                <i class="fa fa-plus"></i> <?php echo $t['create']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row clearfix">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-ticket bg-blue"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['total_coupons']; ?></div>
                        <div class="number" id="stat-total"><?php echo number_format($total_coupons); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-check-circle bg-green"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['active_coupons']; ?></div>
                        <div class="number" id="stat-active"><?php echo number_format($active_coupons); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-clock-o bg-orange"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['expired_coupons']; ?></div>
                        <div class="number" id="stat-expired"><?php echo number_format($expired_coupons); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-users bg-cyan"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['total_uses']; ?></div>
                        <div class="number" id="stat-usage"><?php echo number_format($total_usage); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="card">
            <div class="header">
                <h2><?php echo $t['coupons']; ?></h2>
            </div>
            <div class="body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="coupon-search" placeholder="<?php echo $t['search_coupons']; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="event-filter">
                            <option value=""><?php echo $t['all_events']; ?></option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="category-filter">
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_categories', 'All Categories')); ?></option>
                            <?php foreach ($coupon_categories as $cat): ?>
                                <option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="status-filter">
                            <option value=""><?php echo $t['all_status']; ?></option>
                            <option value="active"><?php echo $t['active']; ?></option>
                            <option value="expired"><?php echo $t['expired']; ?></option>
                            <option value="used"><?php echo $t['fully_used']; ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="per-page-select">
                            <option value="25">25 <?php echo $t['per_page']; ?></option>
                            <option value="50" selected>50 <?php echo $t['per_page']; ?></option>
                            <option value="100">100 <?php echo $t['per_page']; ?></option>
                            <option value="200">200 <?php echo $t['per_page']; ?></option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive" id="coupons-table-container">
                    <table class="table table-hover table-custom spacing5" id="coupons-table">
                        <thead>
                            <tr>
                                <th width="50">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="select-all-coupons">
                                        <label class="custom-control-label" for="select-all-coupons"></label>
                                    </div>
                                </th>
                                <th><?php echo $t['coupon_code']; ?></th>
                                <th><?php echo $t['discount']; ?></th>
                                <th><?php echo $t['event']; ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></th>
                                <th><?php echo $t['usage']; ?></th>
                                <th><?php echo $t['expiry']; ?></th>
                                <th><?php echo $t['status']; ?></th>
                                <th><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody id="coupons-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i>
                                    <p class="mt-2"><?php echo $t['loading']; ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="pagination-info-text">Loading...</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav aria-label="Coupons pagination">
                                <ul class="pagination justify-content-end mb-0" id="coupons-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Import Coupons Modal -->
<div class="modal fade" id="importCouponsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-upload"></i> <?php echo $t['import_coupons_csv']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Download Template -->
                <div class="card mb-3">
                    <div class="card-header bg-info text-white py-2">
                        <i class="fa fa-download"></i> <?php echo $t['step1_download_template']; ?>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><?php echo $t['download_template_desc']; ?></p>
                        <button type="button" class="btn btn-outline-info btn-sm" id="download-template-btn">
                            <i class="fa fa-file-excel-o"></i> <?php echo $t['download_csv_template']; ?>
                        </button>
                        <div class="mt-3">
                            <h6><i class="fa fa-info-circle"></i> <?php echo $t['csv_columns']; ?>:</h6>
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?php echo $t['column']; ?></th>
                                        <th><?php echo $t['required']; ?></th>
                                        <th><?php echo $t['description']; ?></th>
                                        <th><?php echo $t['example']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><code>code</code></td><td><span class="text-danger"><?php echo $t['yes']; ?></span></td><td><?php echo $t['unique_coupon_code']; ?></td><td>SUMMER2024</td></tr>
                                    <tr><td><code>discount_type</code></td><td><span class="text-danger"><?php echo $t['yes']; ?></span></td><td><?php echo $t['discount_type_desc']; ?></td><td>percentage</td></tr>
                                    <tr><td><code>discount_value</code></td><td><span class="text-danger"><?php echo $t['yes']; ?></span></td><td><?php echo $t['discount_amount']; ?></td><td>20</td></tr>
                                    <tr><td><code>usage_limit</code></td><td><?php echo $t['no']; ?></td><td><?php echo $t['max_uses_desc']; ?></td><td>100</td></tr>
                                    <tr><td><code>expiry_date</code></td><td><?php echo $t['no']; ?></td><td><?php echo $t['expiration_date_format']; ?></td><td>2025-12-31</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Select Event -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white py-2">
                        <i class="fa fa-calendar"></i> <?php echo $t['step2_select_event']; ?>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label for="import-event-id"><?php echo $t['apply_imported_to']; ?></label>
                            <select class="form-control" id="import-event-id">
                                <option value="0"><?php echo $t['all_events_no_restriction']; ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><?php echo $t['all_imported_assigned']; ?></small>
                        </div>

                        <div class="form-group mb-0">
                            <label for="import-category-id"><i class="fa fa-folder mr-1"></i> <?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                            <select class="form-control" id="import-category-id">
                                <?php foreach ($coupon_categories as $cat): ?>
                                    <option value="<?php echo (int) $cat->id; ?>" <?php selected((int) $cat->id, 1); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.coupon_category_hint_import', 'All imported coupons will be assigned to this category')); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Upload File -->
                <div class="card">
                    <div class="card-header bg-success text-white py-2">
                        <i class="fa fa-file"></i> <?php echo $t['step3_upload_file']; ?>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label for="import-csv-file"><?php echo $t['select_csv_file']; ?></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="import-csv-file" accept=".csv,.txt">
                                <label class="custom-file-label" for="import-csv-file"><?php echo $t['choose_file']; ?></label>
                            </div>
                            <small class="text-muted d-block mt-2"><?php echo $t['accepted_formats']; ?></small>
                            <div id="file-preview" class="mt-3" style="display:none;">
                                <label><?php echo $t['file_preview']; ?></label>
                                <pre class="bg-light p-2 border rounded" id="csv-preview-content" style="max-height: 150px; overflow: auto; font-size: 12px;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                <button type="button" class="btn btn-success" id="import-coupons-btn">
                    <i class="fa fa-upload"></i> <?php echo $t['import_coupons']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Coupons Modal -->
<div class="modal fade" id="exportCouponsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-download"></i> <?php echo $t['export_coupons']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="export-event-filter"><?php echo $t['export_coupons_for']; ?></label>
                    <select class="form-control" id="export-event-filter">
                        <option value="all"><?php echo $t['all_events']; ?></option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="export-status-filter"><?php echo $t['filter_by_status']; ?></label>
                    <select class="form-control" id="export-status-filter">
                        <option value="all"><?php echo $t['all_status']; ?></option>
                        <option value="active"><?php echo $t['active_only']; ?></option>
                        <option value="expired"><?php echo $t['expired_only']; ?></option>
                        <option value="used"><?php echo $t['fully_used_only']; ?></option>
                    </select>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fa fa-info-circle"></i> <?php echo $t['export_info']; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                <button type="button" class="btn btn-success" id="do-export-btn">
                    <i class="fa fa-download"></i> <?php echo $t['download_csv']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var couponsTranslations = {
    loading_coupons: '<?php echo esc_js($t['loading_coupons']); ?>',
    error_loading_coupons: '<?php echo esc_js($t['error_loading_coupons']); ?>',
    connection_error: '<?php echo esc_js($t['connection_error']); ?>',
    no_coupons_found: '<?php echo esc_js($t['no_coupons_found']); ?>',
    free_100: '<?php echo esc_js($t['free_100']); ?>',
    expired: '<?php echo esc_js($t['expired']); ?>',
    limit_reached: '<?php echo esc_js($t['limit_reached']); ?>',
    active: '<?php echo esc_js($t['active']); ?>',
    all_events: '<?php echo esc_js($t['all_events']); ?>',
    event_deleted: '<?php echo esc_js($t['event_deleted']); ?>',
    no_expiry: '<?php echo esc_js($t['no_expiry']); ?>',
    showing_x_to_y_of_z: '<?php echo esc_js($t['showing_x_to_y_of_z']); ?>',
    delete_coupon_title: '<?php echo esc_js($t['delete_coupon_title']); ?>',
    delete_coupon_confirm: '<?php echo esc_js($t['delete_coupon_confirm']); ?>',
    yes_delete: '<?php echo esc_js($t['yes_delete']); ?>',
    cancel: '<?php echo esc_js($t['cancel']); ?>',
    deleted: '<?php echo esc_js($t['deleted']); ?>',
    coupon_deleted: '<?php echo esc_js($t['coupon_deleted']); ?>',
    error: '<?php echo esc_js($t['error']); ?>',
    error_deleting_coupon: '<?php echo esc_js($t['error_deleting_coupon']); ?>',
    delete_selected_title: '<?php echo esc_js($t['delete_selected_title']); ?>',
    delete_selected_confirm: '<?php echo esc_js($t['delete_selected_confirm']); ?>',
    yes_delete_all: '<?php echo esc_js($t['yes_delete_all']); ?>',
    deleting: '<?php echo esc_js($t['deleting']); ?>',
    selected_deleted: '<?php echo esc_js($t['selected_deleted']); ?>',
    error_deleting_coupons: '<?php echo esc_js($t['error_deleting_coupons']); ?>',
    delete_all_title: '<?php echo esc_js($t['delete_all_title']); ?>',
    delete_all_warning: '<?php echo esc_js($t['delete_all_warning']); ?>',
    action_cannot_undone: '<?php echo esc_js($t['action_cannot_undone']); ?>',
    type_delete_confirm: '<?php echo esc_js($t['type_delete_confirm']); ?>',
    please_type_delete: '<?php echo esc_js($t['please_type_delete']); ?>',
    deleting_all: '<?php echo esc_js($t['deleting_all']); ?>',
    all_deleted: '<?php echo esc_js($t['all_deleted']); ?>',
    no_file_selected: '<?php echo esc_js($t['no_file_selected']); ?>',
    select_csv_to_import: '<?php echo esc_js($t['select_csv_to_import']); ?>',
    importing: '<?php echo esc_js($t['importing']); ?>',
    import_complete: '<?php echo esc_js($t['import_complete']); ?>',
    import_failed: '<?php echo esc_js($t['import_failed']); ?>',
    error_importing: '<?php echo esc_js($t['error_importing']); ?>',
    template_downloaded: '<?php echo esc_js($t['template_downloaded']); ?>',
    fill_template_upload: '<?php echo esc_js($t['fill_template_upload']); ?>',
    exporting: '<?php echo esc_js($t['exporting']); ?>',
    export_complete: '<?php echo esc_js($t['export_complete']); ?>',
    exported_x_coupons: '<?php echo esc_js($t['exported_x_coupons']); ?>',
    export_failed: '<?php echo esc_js($t['export_failed']); ?>',
    error_exporting: '<?php echo esc_js($t['error_exporting']); ?>',
    failed_read_file: '<?php echo esc_js($t['failed_read_file']); ?>',
    choose_file: '<?php echo esc_js($t['choose_file']); ?>'
};

jQuery(function($) {
    // Fix modal backdrop not being removed on close
    $('.modal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });

    // State
    let currentPage = 1;
    let perPage = 50;
    let searchQuery = '';
    let eventFilter = '';
    let categoryFilter = '';
    let statusFilter = '';
    let isLoading = false;
    let searchTimeout = null;
    const selectedCoupons = new Set();

    // Initialize
    loadCoupons();

    // ===============================
    // Load Coupons via AJAX
    // ===============================
    function loadCoupons() {
        if (isLoading) return;
        isLoading = true;

        $('#coupons-tbody').html('<tr><td colspan="9" class="text-center py-4"><i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i><p class="mt-2 mb-0">' + couponsTranslations.loading_coupons + '</p></td></tr>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_coupons_paginated',
                nonce: scDashboard.nonce,
                page: currentPage,
                per_page: perPage,
                search: searchQuery,
                event_id: eventFilter,
                category_id: categoryFilter,
                status: statusFilter
            },
            success: function(response) {
                isLoading = false;

                if (response.success) {
                    renderCoupons(response.data.coupons);
                    renderPagination(response.data.total, response.data.pages, response.data.current_page);
                    updatePaginationInfo(response.data);
                } else {
                    $('#coupons-tbody').html('<tr><td colspan="9" class="text-center text-danger py-5">' + couponsTranslations.error_loading_coupons + '</td></tr>');
                }
            },
            error: function() {
                isLoading = false;
                $('#coupons-loading').hide();
                $('#coupons-tbody').html('<tr><td colspan="9" class="text-center text-danger py-5">' + couponsTranslations.connection_error + '</td></tr>');
            }
        });
    }

    function renderCoupons(coupons) {
        if (!coupons || coupons.length === 0) {
            $('#coupons-tbody').html(`
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="fa fa-ticket" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mt-2">` + couponsTranslations.no_coupons_found + `</p>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        coupons.forEach(function(coupon) {
            const isChecked = selectedCoupons.has(String(coupon.id)) ? 'checked' : '';

            // Discount display - use dynamic currency
            let discountText = '';
            if (coupon.discount_type === 'percentage') {
                discountText = coupon.discount_value + '%';
            } else if (coupon.discount_type === 'fixed') {
                discountText = coupon.discount_value + ' <?php echo sc_get_currency_symbol(); ?>';
            } else {
                discountText = couponsTranslations.free_100;
            }

            // Status badge
            let statusBadge = '';
            if (coupon.status === 'expired') {
                statusBadge = '<span class="badge badge-danger">' + couponsTranslations.expired + '</span>';
            } else if (coupon.status === 'used') {
                statusBadge = '<span class="badge badge-warning">' + couponsTranslations.limit_reached + '</span>';
            } else {
                statusBadge = '<span class="badge badge-success">' + couponsTranslations.active + '</span>';
            }

            // Event display
            let eventDisplay = '';
            if (coupon.event_title) {
                eventDisplay = '<span class="text-primary">' + escapeHtml(coupon.event_title) + '</span>';
            } else if (coupon.event_id == 0) {
                eventDisplay = '<span class="badge badge-info">' + couponsTranslations.all_events + '</span>';
            } else {
                eventDisplay = '<span class="text-muted">' + couponsTranslations.event_deleted + '</span>';
            }

            // Category badge
            const catName = coupon.category_name || 'General';
            const catColor = coupon.category_color || '#7c1314';
            const categoryBadge = '<span class="badge" style="background:' + escapeHtml(catColor) + ';color:#fff;">' + escapeHtml(catName) + '</span>';

            html += `
                <tr>
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input coupon-checkbox" id="coupon-${coupon.id}" value="${coupon.id}" ${isChecked}>
                            <label class="custom-control-label" for="coupon-${coupon.id}"></label>
                        </div>
                    </td>
                    <td><strong>${escapeHtml(coupon.code)}</strong></td>
                    <td>${discountText}</td>
                    <td>${eventDisplay}</td>
                    <td>${categoryBadge}</td>
                    <td><span class="badge badge-secondary">${coupon.usage_count} / ${coupon.usage_limit || '∞'}</span></td>
                    <td>${coupon.expiry_date || couponsTranslations.no_expiry}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/coupon-edit'); ?>?id=${coupon.id}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/coupon-edit'); ?>?id=${coupon.id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-coupon" href="javascript:void(0);" data-id="${coupon.id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#coupons-tbody').html(html);
        updateBulkButton();
        syncSelectAllCheckbox();
    }

    function renderPagination(total, pages, current) {
        if (pages <= 1) {
            $('#coupons-pagination').html('');
            return;
        }

        let html = '';

        // Previous
        html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current - 1}">«</a>
        </li>`;

        // Page numbers (show max 7 pages)
        let startPage = Math.max(1, current - 3);
        let endPage = Math.min(pages, startPage + 6);

        if (endPage - startPage < 6) {
            startPage = Math.max(1, endPage - 6);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        if (endPage < pages) {
            if (endPage < pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pages}">${pages}</a></li>`;
        }

        // Next
        html += `<li class="page-item ${current >= pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current + 1}">»</a>
        </li>`;

        $('#coupons-pagination').html(html);
    }

    function updatePaginationInfo(data) {
        const start = ((data.current_page - 1) * perPage) + 1;
        const end = Math.min(data.current_page * perPage, data.total);
        var text = couponsTranslations.showing_x_to_y_of_z.replace('%s', start).replace('%s', end).replace('%s', data.total);
        $('#pagination-info-text').text(text);
    }

    // ===============================
    // Event Handlers
    // ===============================

    // Pagination click
    $(document).on('click', '#coupons-pagination .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== currentPage) {
            currentPage = page;
            loadCoupons();
        }
    });

    // Search
    $('#coupon-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchQuery = $('#coupon-search').val();
            currentPage = 1;
            loadCoupons();
        }, 500);
    });

    // Event filter
    $('#event-filter').on('change', function() {
        eventFilter = $(this).val();
        currentPage = 1;
        loadCoupons();
    });

    // Category filter
    $('#category-filter').on('change', function() {
        categoryFilter = $(this).val();
        currentPage = 1;
        loadCoupons();
    });

    // Status filter
    $('#status-filter').on('change', function() {
        statusFilter = $(this).val();
        currentPage = 1;
        loadCoupons();
    });

    // Per page
    $('#per-page-select').on('change', function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        loadCoupons();
    });

    // ===============================
    // Checkbox handling
    // ===============================

    function updateBulkButton() {
        $('#bulk-delete-btn').prop('disabled', selectedCoupons.size === 0);
    }

    function syncSelectAllCheckbox() {
        const checkboxes = $('.coupon-checkbox');
        if (checkboxes.length === 0) {
            $('#select-all-coupons').prop('checked', false);
            return;
        }
        const allChecked = checkboxes.length === checkboxes.filter(':checked').length;
        $('#select-all-coupons').prop('checked', allChecked);
    }

    $('#select-all-coupons').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.coupon-checkbox').each(function() {
            const id = String($(this).val());
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedCoupons.add(id);
            } else {
                selectedCoupons.delete(id);
            }
        });
        updateBulkButton();
    });

    $(document).on('change', '.coupon-checkbox', function() {
        const id = String($(this).val());
        if ($(this).prop('checked')) {
            selectedCoupons.add(id);
        } else {
            selectedCoupons.delete(id);
        }
        updateBulkButton();
        syncSelectAllCheckbox();
    });

    // ===============================
    // Delete coupon
    // ===============================

    $(document).on('click', '.delete-coupon', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: couponsTranslations.delete_coupon_title,
            text: couponsTranslations.delete_coupon_confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: couponsTranslations.yes_delete,
            cancelButtonText: couponsTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_coupon',
                        nonce: scDashboard.nonce,
                        coupon_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            selectedCoupons.delete(String(id));
                            loadCoupons();
                            Swal.fire({
                                icon: 'success',
                                title: couponsTranslations.deleted,
                                text: couponsTranslations.coupon_deleted,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: couponsTranslations.error,
                                text: couponsTranslations.error_deleting_coupon
                            });
                        }
                    }
                });
            }
        });
    });

    // Bulk delete
    $('#bulk-delete-btn').on('click', function() {
        if (selectedCoupons.size === 0) return;

        Swal.fire({
            title: couponsTranslations.delete_selected_title,
            text: couponsTranslations.delete_selected_confirm.replace('%s', selectedCoupons.size),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: couponsTranslations.yes_delete_all,
            cancelButtonText: couponsTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: couponsTranslations.deleting,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bulk_delete_coupons',
                        nonce: scDashboard.nonce,
                        coupon_ids: Array.from(selectedCoupons)
                    },
                    success: function(response) {
                        if (response.success) {
                            selectedCoupons.clear();
                            loadCoupons();
                            Swal.fire({
                                icon: 'success',
                                title: couponsTranslations.deleted,
                                text: couponsTranslations.selected_deleted,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: couponsTranslations.error,
                                text: response.data?.message || couponsTranslations.error_deleting_coupons
                            });
                        }
                    }
                });
            }
        });
    });

    // Delete all
    $('#delete-all-btn').on('click', function() {
        Swal.fire({
            title: couponsTranslations.delete_all_title,
            html: '<p class="text-danger"><strong>' + couponsTranslations.delete_all_warning + '</strong></p><p>' + couponsTranslations.action_cannot_undone + '</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: couponsTranslations.yes_delete_all,
            cancelButtonText: couponsTranslations.cancel,
            input: 'text',
            inputPlaceholder: couponsTranslations.type_delete_confirm,
            inputValidator: (value) => {
                if (value !== 'DELETE') {
                    return couponsTranslations.please_type_delete;
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: couponsTranslations.deleting_all,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_all_coupons',
                        nonce: scDashboard.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            selectedCoupons.clear();
                            loadCoupons();
                            $('#stat-total').text('0');
                            $('#stat-active').text('0');
                            $('#stat-expired').text('0');
                            Swal.fire({
                                icon: 'success',
                                title: couponsTranslations.deleted,
                                text: couponsTranslations.all_deleted,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: couponsTranslations.error,
                                text: response.data?.message || couponsTranslations.error_deleting_coupons
                            });
                        }
                    }
                });
            }
        });
    });

    // ===============================
    // Import Coupons - File Upload
    // ===============================

    let importCsvData = '';

    // Handle file selection
    $('#import-csv-file').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            importCsvData = '';
            $('#file-preview').hide();
            $(this).next('.custom-file-label').text('Choose file...');
            return;
        }

        $(this).next('.custom-file-label').text(file.name);

        const reader = new FileReader();
        reader.onload = function(event) {
            importCsvData = event.target.result;

            // Show preview
            const lines = importCsvData.split('\n').slice(0, 5);
            $('#csv-preview-content').text(lines.join('\n'));
            $('#file-preview').show();
        };
        reader.onerror = function() {
            Swal.fire({
                icon: 'error',
                title: couponsTranslations.error,
                text: couponsTranslations.failed_read_file
            });
        };
        reader.readAsText(file);
    });

    // Import button click
    $('#import-coupons-btn').on('click', function() {
        const eventId = $('#import-event-id').val();
        const categoryId = $('#import-category-id').val() || 1;

        if (!importCsvData) {
            Swal.fire({
                icon: 'warning',
                title: couponsTranslations.no_file_selected,
                text: couponsTranslations.select_csv_to_import
            });
            return;
        }

        Swal.fire({
            title: couponsTranslations.importing,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'import_coupons',
                nonce: scDashboard.nonce,
                csv_data: importCsvData,
                event_id: eventId,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    $('#importCouponsModal').modal('hide');
                    // Reset file input
                    $('#import-csv-file').val('');
                    $('#import-csv-file').next('.custom-file-label').text(couponsTranslations.choose_file);
                    $('#file-preview').hide();
                    importCsvData = '';
                    loadCoupons();
                    Swal.fire({
                        icon: 'success',
                        title: couponsTranslations.import_complete,
                        text: response.data.message
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: couponsTranslations.import_failed,
                        text: response.data?.message || couponsTranslations.error_importing
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: couponsTranslations.error,
                    text: couponsTranslations.connection_error
                });
            }
        });
    });

    // Reset on modal close
    $('#importCouponsModal').on('hidden.bs.modal', function() {
        $('#import-csv-file').val('');
        $('#import-csv-file').next('.custom-file-label').text(couponsTranslations.choose_file);
        $('#file-preview').hide();
        importCsvData = '';
    });

    // ===============================
    // Download CSV Template
    // ===============================

    $('#download-template-btn').on('click', function() {
        const templateCSV = 'code,discount_type,discount_value,usage_limit,expiry_date\nSUMMER20,percentage,20,100,2025-12-31\nVIP50,fixed,50,10,\nFREE100,free,0,1,2025-06-30';

        const blob = new Blob(['\ufeff' + templateCSV], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'coupons_import_template.csv';
        link.click();

        Swal.fire({
            icon: 'success',
            title: couponsTranslations.template_downloaded,
            text: couponsTranslations.fill_template_upload,
            timer: 2000,
            showConfirmButton: false
        });
    });

    // ===============================
    // Export Coupons
    // ===============================

    $('#do-export-btn').on('click', function() {
        const exportEventId = $('#export-event-filter').val();
        const exportStatus = $('#export-status-filter').val();

        $('#exportCouponsModal').modal('hide');

        Swal.fire({
            title: couponsTranslations.exporting,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'export_coupons_ajax',
                nonce: scDashboard.nonce,
                export_event_id: exportEventId,
                export_status: exportStatus
            },
            success: function(response) {
                Swal.close();
                if (response.success) {
                    // Download CSV
                    const blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = response.data.filename;
                    link.click();

                    Swal.fire({
                        icon: 'success',
                        title: couponsTranslations.export_complete,
                        text: couponsTranslations.exported_x_coupons.replace('%s', response.data.count),
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: couponsTranslations.export_failed,
                        text: response.data?.message || couponsTranslations.error_exporting
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: couponsTranslations.error,
                    text: couponsTranslations.connection_error
                });
            }
        });
    });

    // ===============================
    // Create Coupon
    // ===============================

    $('#discount-type').on('change', function() {
        const type = $(this).val();
        if (type === 'free') {
            $('#discount-value-group').hide();
            $('#discount-value').prop('required', false);
        } else {
            $('#discount-value-group').show();
            $('#discount-value').prop('required', true);
            if (type === 'percentage') {
                $('#discount-value-hint').text('Enter percentage (0-100)');
            } else {
                $('#discount-value-hint').text('Enter fixed amount');
            }
        }
    }).trigger('change');

    // Note: Create coupon form is now on coupon-create page

    // ===============================
    // Helpers
    // ===============================

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
