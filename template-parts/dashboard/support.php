<?php
/**
 * Support Messages Management Page
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

// Translations
$page_title = sc_t('dashboard_pages.support_messages', 'Support Messages');
$t = array(
    'support_messages' => sc_t('dashboard_pages.support_messages', 'Support Messages'),
    'support' => sc_t('dashboard_pages.support', 'Support'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'all_status' => sc_t('dashboard_pages.all_status', 'All Status'),
    'new' => sc_t('dashboard_pages.new', 'New'),
    'contacted' => sc_t('dashboard_pages.contacted', 'Contacted'),
    'resolved' => sc_t('dashboard_pages.resolved', 'Resolved'),
    'search' => sc_t('dashboard_pages.search', 'Search'),
    'search_placeholder' => sc_t('dashboard_pages.name_email_subject', 'Name, Email, Subject...'),
    'reset' => sc_t('dashboard_pages.reset', 'Reset'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'subject' => sc_t('dashboard_pages.subject', 'Subject'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'message_details' => sc_t('dashboard_pages.message_details', 'Message Details'),
    'message' => sc_t('dashboard_pages.message', 'Message'),
    'update_status' => sc_t('dashboard_pages.update_status', 'Update Status'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'close' => sc_t('dashboard_pages.close', 'Close'),
    'view' => sc_t('dashboard_pages.view', 'View'),
    'mark_as_contacted' => sc_t('dashboard_pages.mark_as_contacted', 'Mark as Contacted'),
    'no_messages_found' => sc_t('dashboard_pages.no_messages_found', 'No messages found.'),
    'status_updated' => sc_t('dashboard_pages.status_updated', 'Status updated successfully'),
    'marked_as_contacted' => sc_t('dashboard_pages.marked_as_contacted', 'Marked as contacted'),
    'confirm_delete_message' => sc_t('dashboard_pages.confirm_delete_message', 'Are you sure you want to delete this message?'),
    'message_deleted' => sc_t('dashboard_pages.message_deleted', 'Message deleted'),
);
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['support_messages']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['support']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form id="support-filters" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="filter-status" class="mr-2"><?php echo $t['status']; ?>:</label>
                            <select class="form-control" id="filter-status" name="status">
                                <option value=""><?php echo $t['all_status']; ?></option>
                                <option value="new"><?php echo $t['new']; ?></option>
                                <option value="contacted"><?php echo $t['contacted']; ?></option>
                                <option value="resolved"><?php echo $t['resolved']; ?></option>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-search" class="mr-2"><?php echo $t['search']; ?>:</label>
                            <input type="text" class="form-control" id="filter-search" name="search" placeholder="<?php echo esc_attr($t['search_placeholder']); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary mb-2">
                            <i class="fa fa-search"></i> <?php echo $t['search']; ?>
                        </button>
                        <button type="button" class="btn btn-secondary mb-2 ml-2" id="reset-filters">
                            <i class="fa fa-refresh"></i> <?php echo $t['reset']; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="support-table">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%"><?php echo $t['name']; ?></th>
                                    <th width="15%"><?php echo $t['email']; ?></th>
                                    <th width="10%"><?php echo $t['phone']; ?></th>
                                    <th width="15%"><?php echo $t['subject']; ?></th>
                                    <th width="10%"><?php echo $t['status']; ?></th>
                                    <th width="15%"><?php echo $t['date']; ?></th>
                                    <th width="15%"><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody id="support-table-body">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading']; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="support-pagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $t['message_details']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><?php echo $t['name']; ?>:</strong>
                        <p id="modal-name"></p>
                    </div>
                    <div class="col-md-6">
                        <strong><?php echo $t['email']; ?>:</strong>
                        <p id="modal-email"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong><?php echo $t['phone']; ?>:</strong>
                        <p id="modal-phone"></p>
                    </div>
                    <div class="col-md-6">
                        <strong><?php echo $t['date']; ?>:</strong>
                        <p id="modal-date"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong><?php echo $t['subject']; ?>:</strong>
                        <p id="modal-subject"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong><?php echo $t['message']; ?>:</strong>
                        <div id="modal-message" class="border p-3 bg-light"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong><?php echo $t['status']; ?>:</strong>
                        <select id="modal-status" class="form-control">
                            <option value="new"><?php echo $t['new']; ?></option>
                            <option value="contacted"><?php echo $t['contacted']; ?></option>
                            <option value="resolved"><?php echo $t['resolved']; ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="modal-message-id">
                <button type="button" class="btn btn-primary" id="update-status-btn">
                    <i class="fa fa-save"></i> <?php echo $t['update_status']; ?>
                </button>
                <button type="button" class="btn btn-danger" id="delete-message-btn">
                    <i class="fa fa-trash"></i> <?php echo $t['delete']; ?>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['close']; ?></button>
            </div>
        </div>
    </div>
</div>

<style>
tbody#support-table-body .btn i {
    margin: 0;
}
</style>

<script>
// Translations for JavaScript
var supportTranslations = {
    loading: '<?php echo esc_js($t['loading']); ?>',
    no_messages_found: '<?php echo esc_js($t['no_messages_found']); ?>',
    view: '<?php echo esc_js($t['view']); ?>',
    mark_as_contacted: '<?php echo esc_js($t['mark_as_contacted']); ?>',
    delete: '<?php echo esc_js($t['delete']); ?>',
    status_updated: '<?php echo esc_js($t['status_updated']); ?>',
    marked_as_contacted: '<?php echo esc_js($t['marked_as_contacted']); ?>',
    confirm_delete_message: '<?php echo esc_js($t['confirm_delete_message']); ?>',
    message_deleted: '<?php echo esc_js($t['message_deleted']); ?>'
};

jQuery(document).ready(function($) {
    var currentPage = 1;
    var currentFilters = {};

    // Modal helper functions (Bootstrap 3/4 compatible)
    function showModal(modalId) {
        var $modal = $(modalId);
        $modal.addClass('show').css('display', 'block');
        $('body').addClass('modal-open');
        if (!$('.modal-backdrop').length) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
    }

    function hideModal(modalId) {
        var $modal = $(modalId);
        $modal.removeClass('show').css('display', 'none');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    }

    // Close modal on close button or backdrop click
    $(document).on('click', '[data-dismiss="modal"], .modal-backdrop', function() {
        hideModal('#viewMessageModal');
    });

    // Close modal on ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            hideModal('#viewMessageModal');
        }
    });

    // Load messages
    function loadMessages(page) {
        page = page || 1;
        currentPage = page;

        var data = {
            action: 'sc_get_support_messages',
            nonce: scDashboard.nonce,
            page: page,
            status: $('#filter-status').val(),
            search: $('#filter-search').val()
        };

        $('#support-table-body').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> ' + supportTranslations.loading + '</td></tr>');

        $.post(scDashboard.ajaxurl, data, function(response) {
            if (response.success) {
                renderMessages(response.data.messages);
                renderPagination(response.data.total_pages, page);
            } else {
                $('#support-table-body').html('<tr><td colspan="8" class="text-center text-danger">' + response.data.message + '</td></tr>');
            }
        });
    }

    // Render messages table
    function renderMessages(messages) {
        if (messages.length === 0) {
            $('#support-table-body').html('<tr><td colspan="8" class="text-center">' + supportTranslations.no_messages_found + '</td></tr>');
            return;
        }

        var html = '';
        $.each(messages, function(index, msg) {
            var statusClass = 'badge-secondary';
            if (msg.status === 'new') statusClass = 'badge-danger';
            else if (msg.status === 'contacted') statusClass = 'badge-warning';
            else if (msg.status === 'resolved') statusClass = 'badge-success';

            html += '<tr data-id="' + msg.id + '">';
            html += '<td>' + msg.id + '</td>';
            html += '<td>' + escapeHtml(msg.name) + '</td>';
            html += '<td><a href="mailto:' + escapeHtml(msg.email) + '">' + escapeHtml(msg.email) + '</a></td>';
            html += '<td>' + (msg.phone ? '<a href="tel:' + escapeHtml(msg.phone) + '">' + escapeHtml(msg.phone) + '</a>' : '-') + '</td>';
            html += '<td>' + escapeHtml(msg.subject) + '</td>';
            html += '<td><span class="badge ' + statusClass + '">' + escapeHtml(msg.status.charAt(0).toUpperCase() + msg.status.slice(1)) + '</span></td>';
            html += '<td>' + msg.created_at + '</td>';
            html += '<td>';
            html += '<div class="btn-group">';
            html += '<button class="btn btn-sm btn-info view-message-btn" data-id="' + msg.id + '" title="' + supportTranslations.view + '"><i class="fa fa-eye"></i></button>';
            html += '<button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="sr-only">Toggle Dropdown</span></button>';
            html += '<div class="dropdown-menu dropdown-menu-right">';
            html += '<a class="dropdown-item mark-contacted-btn" href="javascript:void(0);" data-id="' + msg.id + '"><i class="fa fa-check text-success mr-2"></i> ' + supportTranslations.mark_as_contacted + '</a>';
            html += '<div class="dropdown-divider"></div>';
            html += '<a class="dropdown-item text-danger delete-message-btn-inline" href="javascript:void(0);" data-id="' + msg.id + '"><i class="fa fa-trash mr-2"></i> ' + supportTranslations.delete + '</a>';
            html += '</div></div>';
            html += '</td>';
            html += '</tr>';
        });

        $('#support-table-body').html(html);
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Render pagination
    function renderPagination(totalPages, currentPage) {
        if (totalPages <= 1) {
            $('#support-pagination').html('');
            return;
        }

        var html = '<nav><ul class="pagination">';

        // Previous
        html += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">&laquo;</a></li>';

        // Pages
        for (var i = 1; i <= totalPages; i++) {
            html += '<li class="page-item ' + (i === currentPage ? 'active' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }

        // Next
        html += '<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">&raquo;</a></li>';

        html += '</ul></nav>';
        $('#support-pagination').html(html);
    }

    // Filter form submit
    $('#support-filters').on('submit', function(e) {
        e.preventDefault();
        loadMessages(1);
    });

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#support-filters')[0].reset();
        loadMessages(1);
    });

    // Pagination click
    $(document).on('click', '#support-pagination .page-link', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page > 0) {
            loadMessages(page);
        }
    });

    // View message
    $(document).on('click', '.view-message-btn', function() {
        var id = $(this).data('id');

        $.post(scDashboard.ajaxurl, {
            action: 'sc_get_support_message',
            nonce: scDashboard.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                var msg = response.data;
                $('#modal-message-id').val(msg.id);
                $('#modal-name').text(msg.name);
                $('#modal-email').html('<a href="mailto:' + msg.email + '">' + msg.email + '</a>');
                $('#modal-phone').text(msg.phone || '-');
                $('#modal-date').text(msg.created_at);
                $('#modal-subject').text(msg.subject);
                $('#modal-message').text(msg.message);
                $('#modal-status').val(msg.status);
                showModal('#viewMessageModal');
            }
        });
    });

    // Update status
    $('#update-status-btn').on('click', function() {
        var id = $('#modal-message-id').val();
        var status = $('#modal-status').val();

        $.post(scDashboard.ajaxurl, {
            action: 'sc_update_support_status',
            nonce: scDashboard.nonce,
            id: id,
            status: status
        }, function(response) {
            if (response.success) {
                hideModal('#viewMessageModal');
                loadMessages(currentPage);
                toastr.success(supportTranslations.status_updated);
            } else {
                toastr.error(response.data.message);
            }
        });
    });

    // Mark as contacted (inline)
    $(document).on('click', '.mark-contacted-btn', function() {
        var id = $(this).data('id');

        $.post(scDashboard.ajaxurl, {
            action: 'sc_update_support_status',
            nonce: scDashboard.nonce,
            id: id,
            status: 'contacted'
        }, function(response) {
            if (response.success) {
                loadMessages(currentPage);
                toastr.success(supportTranslations.marked_as_contacted);
            }
        });
    });

    // Delete message (inline)
    $(document).on('click', '.delete-message-btn-inline', function() {
        if (!confirm(supportTranslations.confirm_delete_message)) return;

        var id = $(this).data('id');

        $.post(scDashboard.ajaxurl, {
            action: 'sc_delete_support_message',
            nonce: scDashboard.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                loadMessages(currentPage);
                toastr.success(supportTranslations.message_deleted);
            }
        });
    });

    // Delete from modal
    $('#delete-message-btn').on('click', function() {
        if (!confirm(supportTranslations.confirm_delete_message)) return;

        var id = $('#modal-message-id').val();

        $.post(scDashboard.ajaxurl, {
            action: 'sc_delete_support_message',
            nonce: scDashboard.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                hideModal('#viewMessageModal');
                loadMessages(currentPage);
                toastr.success(supportTranslations.message_deleted);
            }
        });
    });

    // Initial load
    loadMessages(1);
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
