<?php
/**
 * Scanner Team Management Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.scanner_team', 'Scanner Team');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo sc_t('dashboard_pages.scanner_team', 'Scanner Team'); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/event-manager-dashboard/home')); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo sc_t('dashboard_pages.scanner_team', 'Scanner Team'); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 text-right">
                <a href="<?php echo esc_url(home_url('/event-manager-dashboard/scanner-create')); ?>" class="btn btn-primary">
                    <i class="fa fa-plus"></i> <?php echo sc_t('scanners.add_scanner', 'Add Scanner'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row clearfix">
        <div class="col-lg-3 col-md-6">
            <div class="card overflowhidden">
                <div class="body">
                    <div class="d-flex align-items-center">
                        <div class="icon-in-bg bg-info text-white rounded-circle"><i class="fa fa-users"></i></div>
                        <div class="ml-4">
                            <span class="text-muted"><?php echo sc_t('scanners.total', 'Total Scanners'); ?></span>
                            <h4 class="mb-0 font-weight-medium" id="stat-total">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card overflowhidden">
                <div class="body">
                    <div class="d-flex align-items-center">
                        <div class="icon-in-bg bg-success text-white rounded-circle"><i class="fa fa-globe"></i></div>
                        <div class="ml-4">
                            <span class="text-muted"><?php echo sc_t('scanners.full_access', 'Full Access'); ?></span>
                            <h4 class="mb-0 font-weight-medium" id="stat-full">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card overflowhidden">
                <div class="body">
                    <div class="d-flex align-items-center">
                        <div class="icon-in-bg bg-warning text-white rounded-circle"><i class="fa fa-calendar"></i></div>
                        <div class="ml-4">
                            <span class="text-muted"><?php echo sc_t('scanners.event_specific', 'Event Specific'); ?></span>
                            <h4 class="mb-0 font-weight-medium" id="stat-event">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card overflowhidden">
                <div class="body">
                    <div class="d-flex align-items-center">
                        <div class="icon-in-bg bg-info text-white rounded-circle"><i class="fa fa-th-list"></i></div>
                        <div class="ml-4">
                            <span class="text-muted"><?php echo sc_t('scanners.session_specific', 'Session Specific'); ?></span>
                            <h4 class="mb-0 font-weight-medium" id="stat-session">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scanners Table -->
    <div class="row clearfix">
        <div class="col-lg-12">
            <div class="card">
                <div class="header">
                    <h2><i class="fa fa-qrcode"></i> <?php echo sc_t('scanners.all_scanners', 'All Scanners'); ?></h2>
                </div>
                <div class="body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="scanners-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo sc_t('scanners.name', 'Name'); ?></th>
                                    <th><?php echo sc_t('scanners.email', 'Email'); ?></th>
                                    <th><?php echo sc_t('scanners.phone', 'Phone'); ?></th>
                                    <th><?php echo sc_t('scanners.access_type', 'Access Type'); ?></th>
                                    <th><?php echo sc_t('scanners.access_details', 'Details'); ?></th>
                                    <th><?php echo sc_t('scanners.created', 'Created'); ?></th>
                                    <th><?php echo sc_t('dashboard_pages.actions', 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                        <p class="mt-2 text-muted"><?php echo sc_t('dashboard_pages.loading', 'Loading...'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo sc_t('scanners.delete_scanner', 'Delete Scanner'); ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p><?php echo sc_t('scanners.delete_confirm', 'Are you sure you want to delete this scanner? The user account will be changed to a regular subscriber.'); ?></p>
                <p class="font-weight-bold" id="delete-scanner-name"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo sc_t('dashboard_pages.cancel', 'Cancel'); ?></button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                    <i class="fa fa-trash"></i> <?php echo sc_t('dashboard_pages.delete', 'Delete'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var scannersTranslations = {
    full_access: '<?php echo esc_js(sc_t('scanners.full_access', 'Full Access')); ?>',
    event_specific: '<?php echo esc_js(sc_t('scanners.event_specific', 'Event Specific')); ?>',
    session_specific: '<?php echo esc_js(sc_t('scanners.session_specific', 'Session Specific')); ?>',
    no_scanners: '<?php echo esc_js(sc_t('scanners.no_scanners', 'No scanners found. Click "Add Scanner" to create one.')); ?>',
    error_loading: '<?php echo esc_js(sc_t('scanners.error_loading', 'Error loading scanners.')); ?>',
    deleted: '<?php echo esc_js(sc_t('scanners.deleted', 'Scanner deleted successfully.')); ?>',
    error_deleting: '<?php echo esc_js(sc_t('scanners.error_deleting', 'Error deleting scanner.')); ?>'
};

jQuery(document).ready(function($) {
    var deleteUserId = null;

    function loadScanners() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_scanners',
                nonce: scDashboard.nonce
            }
        }).done(function(response) {
            if (response.success) {
                renderTable(response.data.scanners);
                updateStats(response.data.stats);
            } else {
                toastr.error(response.data.message || scannersTranslations.error_loading);
            }
        }).fail(function() {
            toastr.error(scannersTranslations.error_loading);
        });
    }

    function updateStats(stats) {
        $('#stat-total').text(stats.total);
        $('#stat-full').text(stats.full);
        $('#stat-event').text(stats.event);
        $('#stat-session').text(stats.session);
    }

    function getAccessBadge(type) {
        if (type === 'full') return '<span class="badge badge-success">' + scannersTranslations.full_access + '</span>';
        if (type === 'event') return '<span class="badge badge-warning">' + scannersTranslations.event_specific + '</span>';
        if (type === 'session') return '<span class="badge badge-info">' + scannersTranslations.session_specific + '</span>';
        return '<span class="badge badge-secondary">' + type + '</span>';
    }

    function renderTable(scanners) {
        var tbody = $('#scanners-table tbody');
        tbody.empty();

        if (!scanners.length) {
            tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">' + scannersTranslations.no_scanners + '</td></tr>');
            return;
        }

        scanners.forEach(function(s, i) {
            var details = '';
            if (s.access_type === 'full') {
                details = '<span class="text-muted">—</span>';
            } else if (s.access_details && s.access_details.length) {
                details = s.access_details.map(function(d) {
                    return '<span class="badge badge-light mr-1 mb-1" style="font-size:11px;">' + $('<span>').text(d).html() + '</span>';
                }).join('');
            }

            var date = s.registered ? new Date(s.registered).toLocaleDateString() : '';

            var editUrl = '<?php echo esc_url(home_url('/event-manager-dashboard/scanner-edit')); ?>?id=' + s.id;

            tbody.append(
                '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td><strong>' + $('<span>').text(s.name).html() + '</strong></td>' +
                    '<td>' + $('<span>').text(s.email).html() + '</td>' +
                    '<td>' + $('<span>').text(s.phone).html() + '</td>' +
                    '<td>' + getAccessBadge(s.access_type) + '</td>' +
                    '<td>' + details + '</td>' +
                    '<td>' + date + '</td>' +
                    '<td>' +
                        '<div class="btn-group">' +
                            '<a href="' + editUrl + '" class="btn btn-sm btn-primary" title="Edit"><i class="fa fa-pencil"></i></a>' +
                            '<button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
                                '<span class="sr-only">Toggle Dropdown</span>' +
                            '</button>' +
                            '<div class="dropdown-menu dropdown-menu-right">' +
                                '<a class="dropdown-item" href="' + editUrl + '"><i class="fa fa-pencil mr-2"></i> <?php echo esc_js(sc_t("dashboard_pages.edit", "Edit")); ?></a>' +
                                '<div class="dropdown-divider"></div>' +
                                '<a class="dropdown-item text-danger delete-scanner-btn" href="javascript:void(0);" data-id="' + s.id + '" data-name="' + $('<span>').text(s.name).html() + '"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t("dashboard_pages.delete", "Delete")); ?></a>' +
                            '</div>' +
                        '</div>' +
                    '</td>' +
                '</tr>'
            );
        });
    }

    // Delete
    $(document).on('click', '.delete-scanner-btn', function() {
        deleteUserId = $(this).data('id');
        $('#delete-scanner-name').text($(this).data('name'));
        $('#deleteModal').modal('show');
    });

    $('#confirm-delete-btn').on('click', function() {
        if (!deleteUserId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_delete_scanner',
                nonce: scDashboard.nonce,
                user_id: deleteUserId
            }
        }).done(function(response) {
            if (response.success) {
                toastr.success(scannersTranslations.deleted);
                $('#deleteModal').modal('hide');
                loadScanners();
            } else {
                toastr.error(response.data.message || scannersTranslations.error_deleting);
            }
        }).fail(function() {
            toastr.error(scannersTranslations.error_deleting);
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?>');
            deleteUserId = null;
        });
    });

    // Initial load
    loadScanners();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
