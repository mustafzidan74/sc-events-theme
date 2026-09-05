<?php
/**
 * Dashboard - Create Organizer Page
 * Standalone page for creating new organizers
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.add_organizer', 'Add Organizer'),
    'organizers' => sc_t('dashboard_pages.organizers', 'Organizers'),
    'add' => sc_t('dashboard_pages.add', 'Add'),
    'back_to_organizers' => sc_t('dashboard_pages.back_to_organizers', 'Back to Organizers'),
    'organization_details' => sc_t('dashboard_pages.organization_details', 'Organization Details'),
    'organization_name' => sc_t('dashboard_pages.organization_name', 'Organization Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'address' => sc_t('dashboard_pages.address', 'Address'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'social_links' => sc_t('dashboard_pages.social_links', 'Social Links'),
    'save_organizer' => sc_t('dashboard_pages.save_organizer', 'Save Organizer'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'logo' => sc_t('dashboard_pages.logo', 'Logo'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'select_logo' => sc_t('dashboard_pages.select_logo', 'Select Logo'),
    'logo_hint' => sc_t('dashboard_pages.logo_hint', 'Recommended: Square or rectangular logo, PNG with transparency'),
    'display_order' => sc_t('dashboard_pages.display_order', 'Display Order'),
    'order' => sc_t('dashboard_pages.order', 'Order'),
    'order_hint' => sc_t('dashboard_pages.order_hint', 'Lower numbers appear first'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // Placeholders
    'enter_org_name' => sc_t('dashboard_pages.enter_org_name', 'Enter organization name'),
    'enter_email' => sc_t('dashboard_pages.enter_email', 'Enter email address'),
    'enter_phone' => sc_t('dashboard_pages.enter_phone', 'Enter phone number'),
    'enter_address' => sc_t('dashboard_pages.enter_address', 'Enter full address'),
    // JavaScript
    'select_organizer_logo' => sc_t('dashboard_pages.select_organizer_logo', 'Select Organizer Logo'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'organizer_created' => sc_t('dashboard_pages.organizer_created', 'Organizer created successfully!'),
    'error_creating_organizer' => sc_t('dashboard_pages.error_creating_organizer', 'Error creating organizer'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');
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
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/organizers'); ?>"><?php echo $t['organizers']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['add']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/organizers'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back_to_organizers']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="organizer-create-form" class="organizer-form" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_organizer_action', 'sc_organizer_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_organizer">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-building"></i> <?php echo $t['organization_details']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="organizer-name"><?php echo $t['organization_name']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                <input type="text" class="form-control" id="organizer-name" name="name" required placeholder="<?php echo esc_attr($t['enter_org_name']); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-email"><?php echo $t['email_address']; ?></label>
                                        <input type="email" class="form-control" id="organizer-email" name="email" placeholder="<?php echo esc_attr($t['enter_email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-phone"><?php echo $t['phone_number']; ?></label>
                                        <input type="tel" class="form-control" id="organizer-phone" name="phone" placeholder="<?php echo esc_attr($t['enter_phone']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="organizer-website"><?php echo $t['website']; ?></label>
                                <input type="url" class="form-control" id="organizer-website" name="website" placeholder="https://example.com">
                            </div>

                            <div class="form-group">
                                <label for="organizer-address"><?php echo $t['address']; ?></label>
                                <textarea class="form-control" id="organizer-address" name="address" rows="2" placeholder="<?php echo esc_attr($t['enter_address']); ?>"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="organizer-description"><?php echo $t['description']; ?></label>
                                <?php
                                wp_editor('', 'organizer-description', array(
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true,
                                ));
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-share-alt"></i> <?php echo $t['social_links']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-facebook"><i class="fa fa-facebook"></i> Facebook</label>
                                        <input type="url" class="form-control" id="organizer-facebook" name="facebook" placeholder="https://facebook.com/page">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-twitter"><i class="fa fa-twitter"></i> Twitter/X</label>
                                        <input type="url" class="form-control" id="organizer-twitter" name="twitter" placeholder="https://twitter.com/username">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-linkedin"><i class="fa fa-linkedin"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="organizer-linkedin" name="linkedin" placeholder="https://linkedin.com/company/name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-instagram"><i class="fa fa-instagram"></i> Instagram</label>
                                        <input type="url" class="form-control" id="organizer-instagram" name="instagram" placeholder="https://instagram.com/username">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Submit Box -->
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['save_organizer']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="organizer-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="organizer-status" name="is_active">
                                    <option value="1"><?php echo $t['active']; ?></option>
                                    <option value="0"><?php echo $t['inactive']; ?></option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-organizer-btn">
                                    <i class="fa fa-building"></i> <?php echo $t['page_title']; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-image"></i> <?php echo $t['logo']; ?></h2>
                        </div>
                        <div class="body">
                            <div id="organizer-logo-preview" class="mb-3 text-center" style="display:none;">
                                <img src="" alt="Organizer Logo" class="img-fluid rounded" style="max-width: 150px; max-height: 150px; object-fit: contain;">
                                <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-organizer-logo">
                                    <i class="fa fa-times"></i> <?php echo $t['remove']; ?>
                                </button>
                            </div>
                            <input type="hidden" name="logo" id="organizer-logo-id" value="">
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-organizer-logo">
                                <i class="fa fa-upload"></i> <?php echo $t['select_logo']; ?>
                            </button>
                            <small class="text-muted d-block mt-2"><?php echo $t['logo_hint']; ?></small>
                        </div>
                    </div>

                    <!-- Display Order -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sort"></i> <?php echo $t['display_order']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="organizer-order"><?php echo $t['order']; ?></label>
                                <input type="number" class="form-control" id="organizer-order" name="display_order" min="0" value="0">
                                <small class="text-muted"><?php echo $t['order_hint']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var organizerTranslations = {
    select_organizer_logo: '<?php echo esc_js($t['select_organizer_logo']); ?>',
    use_this_image: '<?php echo esc_js($t['use_this_image']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    organizer_created: '<?php echo esc_js($t['organizer_created']); ?>',
    error_creating_organizer: '<?php echo esc_js($t['error_creating_organizer']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    add_organizer: '<?php echo esc_js($t['page_title']); ?>'
};

jQuery(document).ready(function($) {
    // WordPress Media Uploader
    var mediaUploader;
    $('#select-organizer-logo').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: organizerTranslations.select_organizer_logo,
            button: { text: organizerTranslations.use_this_image },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#organizer-logo-id').val(attachment.id);
            $('#organizer-logo-preview img').attr('src', attachment.url);
            $('#organizer-logo-preview').show();
        });

        mediaUploader.open();
    });

    $('#remove-organizer-logo').on('click', function() {
        $('#organizer-logo-id').val('');
        $('#organizer-logo-preview').hide();
    });

    // Form submission
    $('#organizer-create-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-organizer-btn');

        // Get editor content
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('organizer-description')) {
            tinyMCE.get('organizer-description').save();
        }

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + organizerTranslations.saving);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(organizerTranslations.organizer_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/organizers'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || organizerTranslations.error_creating_organizer);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-building"></i> ' + organizerTranslations.add_organizer);
                }
            },
            error: function() {
                toastr.error(organizerTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-building"></i> ' + organizerTranslations.add_organizer);
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
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
