<?php
/**
 * Dashboard - Create Hall Page
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

$page_title = sc_t('dashboard_pages.add_hall', 'Add Hall');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.add_hall', 'Add Hall')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/halls'); ?>"><?php echo esc_html(sc_t('nav.halls', 'Halls')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.add', 'Add')); ?></li>
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

        <form id="hall-form">
            <?php wp_nonce_field('sc_hall_action', 'sc_hall_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_hall">

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
                                <input type="text" class="form-control" id="hall-name" name="name" required placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_hall_name', 'Enter hall name')); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hall-capacity"><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></label>
                                        <input type="number" class="form-control" id="hall-capacity" name="capacity" min="0" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_capacity', 'e.g. 500')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hall-location"><?php echo esc_html(sc_t('dashboard_pages.location', 'Location')); ?></label>
                                        <input type="text" class="form-control" id="hall-location" name="location" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_location', 'e.g. Building A, Floor 2')); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="hall-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                                <textarea class="form-control" id="hall-description" name="description" rows="4" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_description', 'Enter description...')); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Publish Box -->
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.publish', 'Publish')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="hall-sort-order"><?php echo esc_html(sc_t('dashboard_pages.sort_order', 'Sort Order')); ?></label>
                                <input type="number" class="form-control" id="hall-sort-order" name="sort_order" min="0" value="0">
                                <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first')); ?></small>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?>: <span class="badge badge-secondary"><?php echo esc_html(sc_t('dashboard_pages.new', 'New')); ?></span>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-hall-btn">
                                    <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.save_hall', 'Save Hall')); ?>
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
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-hall-image">
                                <i class="fa fa-upload"></i> <?php echo esc_html(sc_t('dashboard_pages.select_image', 'Select Image')); ?>
                            </button>
                            <small class="text-muted d-block mt-2"><?php echo esc_html(sc_t('dashboard_pages.image_hint', 'Recommended: 800x400px')); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
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
            $('#hall-image-preview img').attr('src', attachment.url);
            $('#hall-image-preview').show();
        });
        mediaUploader.open();
    });

    $('#remove-hall-image').on('click', function() {
        $('#hall-image-id').val('');
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
                    toastr.success('<?php echo esc_js(sc_t('dashboard_pages.hall_created', 'Hall created successfully!')); ?>');
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/halls'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_creating_hall', 'Error creating hall')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.save_hall', 'Save Hall')); ?>');
                }
            },
            error: function() {
                toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.save_hall', 'Save Hall')); ?>');
            }
        });
    });
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
