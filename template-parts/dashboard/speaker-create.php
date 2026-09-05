<?php
/**
 * Dashboard - Create Speaker Page
 * Standalone page for creating new speakers
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.add_speaker', 'Add Speaker'),
    'speakers' => sc_t('dashboard_pages.speakers', 'Speakers'),
    'add' => sc_t('dashboard_pages.add', 'Add'),
    'back_to_speakers' => sc_t('dashboard_pages.back_to_speakers', 'Back to Speakers'),
    'basic_information' => sc_t('dashboard_pages.basic_information', 'Basic Information'),
    'full_name' => sc_t('dashboard_pages.full_name', 'Full Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'designation_title' => sc_t('dashboard_pages.designation_title', 'Designation/Title'),
    'company_org' => sc_t('dashboard_pages.company_org', 'Company/Organization'),
    'biography' => sc_t('dashboard_pages.biography', 'Biography'),
    'social_links' => sc_t('dashboard_pages.social_links', 'Social Links'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'save_speaker' => sc_t('dashboard_pages.save_speaker', 'Save Speaker'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'profile_image' => sc_t('dashboard_pages.profile_image', 'Profile Image'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'select_image' => sc_t('dashboard_pages.select_image', 'Select Image'),
    'image_hint' => sc_t('dashboard_pages.image_hint', 'Recommended: Square image, minimum 200x200px'),
    'display_order' => sc_t('dashboard_pages.display_order', 'Display Order'),
    'order' => sc_t('dashboard_pages.order', 'Order'),
    'order_hint' => sc_t('dashboard_pages.order_hint', 'Lower numbers appear first'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // Placeholders
    'enter_speaker_name' => sc_t('dashboard_pages.enter_speaker_name', "Enter speaker's name"),
    'enter_email' => sc_t('dashboard_pages.enter_email', 'Enter email address'),
    'enter_phone' => sc_t('dashboard_pages.enter_phone', 'Enter phone number'),
    'designation_example' => sc_t('dashboard_pages.designation_example', 'e.g., CEO, Professor, Developer'),
    'enter_company' => sc_t('dashboard_pages.enter_company', 'Enter company or organization name'),
    // JavaScript
    'select_speaker_image' => sc_t('dashboard_pages.select_speaker_image', 'Select Speaker Image'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'speaker_created' => sc_t('dashboard_pages.speaker_created', 'Speaker created successfully!'),
    'error_creating_speaker' => sc_t('dashboard_pages.error_creating_speaker', 'Error creating speaker'),
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
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/speakers'); ?>"><?php echo $t['speakers']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['add']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/speakers'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back_to_speakers']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="speaker-create-form" class="speaker-form" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_speaker_action', 'sc_speaker_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_speaker">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-user"></i> <?php echo $t['basic_information']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-name"><?php echo $t['full_name']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <input type="text" class="form-control" id="speaker-name" name="name" required placeholder="<?php echo esc_attr($t['enter_speaker_name']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-email"><?php echo $t['email_address']; ?></label>
                                        <input type="email" class="form-control" id="speaker-email" name="email" placeholder="<?php echo esc_attr($t['enter_email']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-phone"><?php echo $t['phone_number']; ?></label>
                                        <input type="tel" class="form-control" id="speaker-phone" name="phone" placeholder="<?php echo esc_attr($t['enter_phone']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-designation"><?php echo $t['designation_title']; ?></label>
                                        <input type="text" class="form-control" id="speaker-designation" name="designation" placeholder="<?php echo esc_attr($t['designation_example']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="speaker-company"><?php echo $t['company_org']; ?></label>
                                <input type="text" class="form-control" id="speaker-company" name="company" placeholder="<?php echo esc_attr($t['enter_company']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="speaker-bio"><?php echo $t['biography']; ?></label>
                                <?php
                                wp_editor('', 'speaker-bio', array(
                                    'textarea_name' => 'bio',
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
                                        <label for="speaker-website"><i class="fa fa-globe"></i> <?php echo $t['website']; ?></label>
                                        <input type="url" class="form-control" id="speaker-website" name="website" placeholder="https://example.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-linkedin"><i class="fa fa-linkedin"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="speaker-linkedin" name="linkedin" placeholder="https://linkedin.com/in/username">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-twitter"><i class="fa fa-twitter"></i> Twitter/X</label>
                                        <input type="url" class="form-control" id="speaker-twitter" name="twitter" placeholder="https://twitter.com/username">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-facebook"><i class="fa fa-facebook"></i> Facebook</label>
                                        <input type="url" class="form-control" id="speaker-facebook" name="facebook" placeholder="https://facebook.com/username">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-instagram"><i class="fa fa-instagram"></i> Instagram</label>
                                        <input type="url" class="form-control" id="speaker-instagram" name="instagram" placeholder="https://instagram.com/username">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-youtube"><i class="fa fa-youtube"></i> YouTube</label>
                                        <input type="url" class="form-control" id="speaker-youtube" name="youtube" placeholder="https://youtube.com/channel">
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
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['save_speaker']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="speaker-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="speaker-status" name="is_active">
                                    <option value="1"><?php echo $t['active']; ?></option>
                                    <option value="0"><?php echo $t['inactive']; ?></option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-speaker-btn">
                                    <i class="fa fa-microphone"></i> <?php echo $t['page_title']; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Image -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-image"></i> <?php echo $t['profile_image']; ?></h2>
                        </div>
                        <div class="body">
                            <div id="speaker-image-preview" class="mb-3 text-center" style="display:none;">
                                <img src="" alt="Speaker Image" class="img-fluid rounded-circle" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-speaker-image">
                                    <i class="fa fa-times"></i> <?php echo $t['remove']; ?>
                                </button>
                            </div>
                            <input type="hidden" name="image" id="speaker-image-id" value="">
                            <input type="file" name="speaker_image" id="speaker-image-file" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-speaker-image">
                                <i class="fa fa-upload"></i> <?php echo $t['select_image']; ?>
                            </button>
                            <small class="text-muted d-block mt-2"><?php echo $t['image_hint']; ?></small>
                        </div>
                    </div>

                    <!-- Display Order -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sort"></i> <?php echo $t['display_order']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="speaker-order"><?php echo $t['order']; ?></label>
                                <input type="number" class="form-control" id="speaker-order" name="display_order" min="0" value="0">
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
var speakerTranslations = {
    select_speaker_image: '<?php echo esc_js($t['select_speaker_image']); ?>',
    use_this_image: '<?php echo esc_js($t['use_this_image']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    speaker_created: '<?php echo esc_js($t['speaker_created']); ?>',
    error_creating_speaker: '<?php echo esc_js($t['error_creating_speaker']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    add_speaker: '<?php echo esc_js($t['page_title']); ?>'
};

jQuery(document).ready(function($) {
    // Direct file upload - bypasses wp.media to avoid admin-ajax upload-attachment issues
    $('#select-speaker-image').on('click', function(e) {
        e.preventDefault();
        $('#speaker-image-file').trigger('click');
    });

    $('#speaker-image-file').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#speaker-image-preview img').attr('src', e.target.result);
            $('#speaker-image-preview').show();
            $('#speaker-image-id').val(''); // Clear media library ID since we're using file upload
        };
        reader.readAsDataURL(file);
    });

    $('#remove-speaker-image').on('click', function() {
        $('#speaker-image-id').val('');
        $('#speaker-image-file').val('');
        $('#speaker-image-preview').hide();
    });

    // Form submission
    $('#speaker-create-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-speaker-btn');

        // Get editor content
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('speaker-bio')) {
            tinyMCE.get('speaker-bio').save();
        }

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + speakerTranslations.saving);

        // Use FormData so files (speaker_image) are included in the request
        var formData = new FormData($form[0]);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(speakerTranslations.speaker_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/speakers'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || speakerTranslations.error_creating_speaker);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-microphone"></i> ' + speakerTranslations.add_speaker);
                }
            },
            error: function() {
                toastr.error(speakerTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-microphone"></i> ' + speakerTranslations.add_speaker);
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
