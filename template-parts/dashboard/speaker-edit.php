<?php
/**
 * Dashboard - Edit Speaker Page
 * Standalone page for editing speaker details
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.edit_speaker', 'Edit Speaker'),
    'speakers' => sc_t('dashboard_pages.speakers', 'Speakers'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
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
    'events' => sc_t('dashboard_pages.events', 'Events'),
    'no_events_assigned' => sc_t('dashboard_pages.no_events_assigned', 'This speaker is not assigned to any events yet.'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'action' => sc_t('dashboard_pages.action', 'Action'),
    'update_speaker' => sc_t('dashboard_pages.update_speaker', 'Update Speaker'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'created' => sc_t('dashboard_pages.created', 'Created'),
    'updated' => sc_t('dashboard_pages.updated', 'Updated'),
    'profile_image' => sc_t('dashboard_pages.profile_image', 'Profile Image'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'select_image' => sc_t('dashboard_pages.select_image', 'Select Image'),
    'display_order' => sc_t('dashboard_pages.display_order', 'Display Order'),
    'order' => sc_t('dashboard_pages.order', 'Order'),
    'order_hint' => sc_t('dashboard_pages.order_hint', 'Lower numbers appear first'),
    'danger_zone' => sc_t('dashboard_pages.danger_zone', 'Danger Zone'),
    'delete_speaker' => sc_t('dashboard_pages.delete_speaker', 'Delete Speaker'),
    'delete_speaker_warning' => sc_t('dashboard_pages.delete_speaker_warning', 'This will also remove the speaker from all events.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // JavaScript
    'select_speaker_image' => sc_t('dashboard_pages.select_speaker_image', 'Select Speaker Image'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'speaker_updated' => sc_t('dashboard_pages.speaker_updated', 'Speaker updated successfully!'),
    'error_updating_speaker' => sc_t('dashboard_pages.error_updating_speaker', 'Error updating speaker'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'confirm_delete_speaker' => sc_t('dashboard_pages.confirm_delete_speaker', 'Are you sure you want to delete this speaker?\n\nThis will also remove them from all events.'),
    'speaker_deleted' => sc_t('dashboard_pages.speaker_deleted', 'Speaker deleted!'),
    'error_deleting_speaker' => sc_t('dashboard_pages.error_deleting_speaker', 'Error deleting speaker'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

// Get speaker ID from URL
$speaker_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$speaker_id) {
    wp_redirect(home_url('/event-manager-dashboard/speakers'));
    exit;
}

// Get speaker data
global $wpdb;
$speaker = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_speakers WHERE id = %d",
    $speaker_id
));

if (!$speaker) {
    wp_redirect(home_url('/event-manager-dashboard/speakers'));
    exit;
}

$page_title = $t['page_title'] . ': ' . esc_html($speaker->name);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get speaker's events
$speaker_events = $wpdb->get_results($wpdb->prepare(
    "SELECT e.* FROM {$wpdb->prefix}sc_events e
     INNER JOIN {$wpdb->prefix}sc_event_speakers es ON e.id = es.event_id
     WHERE es.speaker_id = %d
     ORDER BY e.start_date DESC
     LIMIT 10",
    $speaker_id
));

// Parse social links
$social_links = array();
if (!empty($speaker->social_links)) {
    $social_links = is_array($speaker->social_links) ? $speaker->social_links : json_decode($speaker->social_links, true);
}
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
                        <li class="breadcrumb-item active"><?php echo $t['edit']; ?></li>
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

        <form id="speaker-edit-form" class="speaker-form" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_speaker_action', 'sc_speaker_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_speaker">
            <input type="hidden" name="speaker_id" value="<?php echo $speaker_id; ?>">

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
                                        <input type="text" class="form-control" id="speaker-name" name="name" required value="<?php echo esc_attr($speaker->name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-email"><?php echo $t['email_address']; ?></label>
                                        <input type="email" class="form-control" id="speaker-email" name="email" value="<?php echo esc_attr($speaker->email ?: ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-phone"><?php echo $t['phone_number']; ?></label>
                                        <input type="tel" class="form-control" id="speaker-phone" name="phone" value="<?php echo esc_attr($speaker->phone ?: ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-designation"><?php echo $t['designation_title']; ?></label>
                                        <input type="text" class="form-control" id="speaker-designation" name="designation" value="<?php echo esc_attr($speaker->title ?: ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="speaker-company"><?php echo $t['company_org']; ?></label>
                                <input type="text" class="form-control" id="speaker-company" name="company" value="<?php echo esc_attr($speaker->company ?: ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="speaker-bio"><?php echo $t['biography']; ?></label>
                                <?php
                                wp_editor($speaker->bio ?: '', 'speaker-bio', array(
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
                                        <input type="url" class="form-control" id="speaker-website" name="website" value="<?php echo esc_url($speaker->website ?: ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-linkedin"><i class="fa fa-linkedin"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="speaker-linkedin" name="linkedin" value="<?php echo esc_url($social_links['linkedin'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-twitter"><i class="fa fa-twitter"></i> Twitter/X</label>
                                        <input type="url" class="form-control" id="speaker-twitter" name="twitter" value="<?php echo esc_url($social_links['twitter'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-facebook"><i class="fa fa-facebook"></i> Facebook</label>
                                        <input type="url" class="form-control" id="speaker-facebook" name="facebook" value="<?php echo esc_url($social_links['facebook'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-instagram"><i class="fa fa-instagram"></i> Instagram</label>
                                        <input type="url" class="form-control" id="speaker-instagram" name="instagram" value="<?php echo esc_url($social_links['instagram'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="speaker-youtube"><i class="fa fa-youtube"></i> YouTube</label>
                                        <input type="url" class="form-control" id="speaker-youtube" name="youtube" value="<?php echo esc_url($social_links['youtube'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Speaker's Events -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-calendar"></i> <?php echo $t['events']; ?></h2>
                        </div>
                        <div class="body">
                            <?php if (empty($speaker_events)): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> <?php echo $t['no_events_assigned']; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo $t['event']; ?></th>
                                                <th><?php echo $t['date']; ?></th>
                                                <th><?php echo $t['status']; ?></th>
                                                <th><?php echo $t['action']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($speaker_events as $event): ?>
                                                <tr>
                                                    <td><?php echo esc_html($event->title); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($event->start_date)); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $event->status === 'publish' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($event->status); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo home_url('/event-manager-dashboard/event-view?id=' . $event->id); ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                    </td>
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
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['update_speaker']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="speaker-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="speaker-status" name="is_active">
                                    <option value="1" <?php selected($speaker->is_active, 1); ?>><?php echo $t['active']; ?></option>
                                    <option value="0" <?php selected($speaker->is_active, 0); ?>><?php echo $t['inactive']; ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-calendar"></i> <?php echo $t['created']; ?>: <?php echo date('M d, Y', strtotime($speaker->created_at)); ?><br>
                                    <i class="fa fa-refresh"></i> <?php echo $t['updated']; ?>: <?php echo date('M d, Y', strtotime($speaker->updated_at)); ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-speaker-btn">
                                    <i class="fa fa-save"></i> <?php echo $t['update_speaker']; ?>
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
                            <?php $image_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : ''; ?>
                            <div id="speaker-image-preview" class="mb-3 text-center" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
                                <img src="<?php echo esc_url($image_url); ?>" alt="Speaker Image" class="img-fluid rounded-circle" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-speaker-image">
                                    <i class="fa fa-times"></i> <?php echo $t['remove']; ?>
                                </button>
                            </div>
                            <input type="hidden" name="image" id="speaker-image-id" value="<?php echo $speaker->photo ?: ''; ?>">
                            <input type="file" name="speaker_image" id="speaker-image-file" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-speaker-image">
                                <i class="fa fa-upload"></i> <?php echo $t['select_image']; ?>
                            </button>
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
                                <input type="number" class="form-control" id="speaker-order" name="display_order" min="0" value="<?php echo $speaker->display_order ?: 0; ?>">
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
    speaker_updated: '<?php echo esc_js($t['speaker_updated']); ?>',
    error_updating_speaker: '<?php echo esc_js($t['error_updating_speaker']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    update_speaker: '<?php echo esc_js($t['update_speaker']); ?>',
    confirm_delete_speaker: '<?php echo esc_js($t['confirm_delete_speaker']); ?>',
    speaker_deleted: '<?php echo esc_js($t['speaker_deleted']); ?>',
    error_deleting_speaker: '<?php echo esc_js($t['error_deleting_speaker']); ?>'
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
    $('#speaker-edit-form').on('submit', function(e) {
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
                    toastr.success(speakerTranslations.speaker_updated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + speakerTranslations.update_speaker);
                } else {
                    toastr.error(response.data || speakerTranslations.error_updating_speaker);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + speakerTranslations.update_speaker);
                }
            },
            error: function() {
                toastr.error(speakerTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + speakerTranslations.update_speaker);
            }
        });
    });

    // Delete speaker
    $('#delete-speaker-btn').on('click', function() {
        var speakerId = $(this).data('speaker-id');

        if (!confirm(speakerTranslations.confirm_delete_speaker)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_delete_speaker',
                speaker_id: speakerId,
                nonce: '<?php echo wp_create_nonce('sc_speaker_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(speakerTranslations.speaker_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/speakers'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || speakerTranslations.error_deleting_speaker);
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
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
