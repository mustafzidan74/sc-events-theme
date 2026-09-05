<?php
/**
 * Dashboard - Edit Hall Page
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

$hall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$hall_id) {
    wp_redirect(home_url('/event-manager-dashboard/halls'));
    exit;
}

$page_title = sc_t('dashboard_pages.edit_hall', 'Edit Hall');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.edit_hall', 'Edit Hall')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/halls'); ?>"><?php echo esc_html(sc_t('nav.halls', 'Halls')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/halls'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html(sc_t('dashboard_pages.back_to_halls', 'Back to Halls')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="hall-loading" class="text-center py-5">
            <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
            <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
        </div>

        <!-- Hall Form (hidden until loaded) -->
        <div id="hall-form-container" style="display:none;">
            <form id="hall-form">
                <?php wp_nonce_field('sc_hall_action', 'sc_hall_nonce'); ?>
                <input type="hidden" name="action" value="sc_save_hall">
                <input type="hidden" name="hall_id" value="<?php echo esc_attr($hall_id); ?>">

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-building"></i> <?php echo esc_html(sc_t('dashboard_pages.basic_information', 'Basic Information')); ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="hall-name"><?php echo esc_html(sc_t('dashboard_pages.hall_name', 'Hall Name')); ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="hall-name" name="name" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hall-capacity"><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></label>
                                            <input type="number" class="form-control" id="hall-capacity" name="capacity" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hall-location"><?php echo esc_html(sc_t('dashboard_pages.location', 'Location')); ?></label>
                                            <input type="text" class="form-control" id="hall-location" name="location">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="hall-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                                    <textarea class="form-control" id="hall-description" name="description" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Update Box -->
                        <div class="card">
                            <div class="header bg-primary">
                                <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.update', 'Update')); ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="hall-sort-order"><?php echo esc_html(sc_t('dashboard_pages.sort_order', 'Sort Order')); ?></label>
                                    <input type="number" class="form-control" id="hall-sort-order" name="sort_order" min="0" value="0">
                                    <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first')); ?></small>
                                </div>

                                <div class="mb-3" id="hall-meta-info"></div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-hall-btn">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.update_hall', 'Update Hall')); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-image"></i> <?php echo esc_html(sc_t('dashboard_pages.image', 'Image')); ?></h2>
                            </div>
                            <div class="body">
                                <div id="hall-image-preview" class="mb-3 text-center" style="display:none;">
                                    <img src="" alt="Hall Image" class="img-fluid rounded" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-hall-image">
                                        <i class="fa fa-times"></i> <?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?>
                                    </button>
                                </div>
                                <input type="hidden" name="image" id="hall-image-id" value="">
                                <input type="hidden" name="remove_image" id="hall-remove-image" value="0">
                                <button type="button" class="btn btn-outline-primary btn-block" id="select-hall-image">
                                    <i class="fa fa-upload"></i> <?php echo esc_html(sc_t('dashboard_pages.select_image', 'Select Image')); ?>
                                </button>
                                <small class="text-muted d-block mt-2"><?php echo esc_html(sc_t('dashboard_pages.image_hint', 'Recommended: 800x400px')); ?></small>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="card">
                            <div class="header bg-danger">
                                <h2 class="text-white"><i class="fa fa-exclamation-triangle"></i> <?php echo esc_html(sc_t('dashboard_pages.danger_zone', 'Danger Zone')); ?></h2>
                            </div>
                            <div class="body">
                                <p class="text-muted small"><?php echo esc_html(sc_t('dashboard_pages.delete_hall_warning', 'Deleting a hall will remove it from all schedules.')); ?></p>
                                <button type="button" class="btn btn-outline-danger btn-block" id="delete-hall-btn">
                                    <i class="fa fa-trash"></i> <?php echo esc_html(sc_t('dashboard_pages.delete_hall', 'Delete Hall')); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var hallId = <?php echo intval($hall_id); ?>;

    // Load hall data
    $.ajax({
        url: scDashboard.ajaxurl,
        type: 'POST',
        data: { action: 'sc_get_hall', nonce: scDashboard.nonce, hall_id: hallId }
    }).done(function(response) {
        if (response.success) {
            var hall = response.data.hall;
            $('#hall-name').val(hall.name);
            $('#hall-capacity').val(hall.capacity);
            $('#hall-location').val(hall.location);
            $('#hall-description').val(hall.description);
            $('#hall-sort-order').val(hall.sort_order);

            if (hall.image && hall.image_url) {
                $('#hall-image-id').val(hall.image);
                $('#hall-image-preview img').attr('src', hall.image_url);
                $('#hall-image-preview').show();
            }

            // Meta info
            var metaHtml = '<small class="text-muted">';
            metaHtml += '<i class="fa fa-calendar"></i> <?php echo esc_js(sc_t('dashboard_pages.schedules_count', 'Schedules')); ?>: <strong>' + (hall.schedules_count || 0) + '</strong>';
            metaHtml += '</small>';
            $('#hall-meta-info').html(metaHtml);

            $('#hall-loading').hide();
            $('#hall-form-container').show();
        } else {
            toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.hall_not_found', 'Hall not found')); ?>');
        }
    }).fail(function() {
        toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
    });

    // WordPress Media Uploader
    var mediaUploader;
    $('#select-hall-image').on('click', function(e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }

        mediaUploader = wp.media({
            title: '<?php echo esc_js(sc_t('dashboard_pages.select_hall_image', 'Select Hall Image')); ?>',
            button: { text: '<?php echo esc_js(sc_t('dashboard_pages.use_this_image', 'Use this image')); ?>' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#hall-image-id').val(attachment.id);
            $('#hall-remove-image').val('0');
            $('#hall-image-preview img').attr('src', attachment.url);
            $('#hall-image-preview').show();
        });
        mediaUploader.open();
    });

    $('#remove-hall-image').on('click', function() {
        $('#hall-image-id').val('');
        $('#hall-remove-image').val('1');
        $('#hall-image-preview').hide();
    });

    // Form submission
    $('#hall-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#save-hall-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo esc_js(sc_t('dashboard_pages.saving', 'Saving...')); ?>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.hall_updated', 'Hall updated successfully!')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_hall', 'Update Hall')); ?>');
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_updating_hall', 'Error updating hall')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_hall', 'Update Hall')); ?>');
                }
            },
            error: function() {
                toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_hall', 'Update Hall')); ?>');
            }
        });
    });

    // Delete hall
    $('#delete-hall-btn').on('click', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: { action: 'sc_delete_hall', nonce: scDashboard.nonce, hall_id: hallId }
            }).done(function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/halls'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_hall', 'Error deleting hall')); ?>');
                }
            });
        });
    });
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
