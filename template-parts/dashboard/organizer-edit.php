<?php
/**
 * Dashboard - Edit Organizer Page
 * Standalone page for editing organizer details
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.edit_organizer', 'Edit Organizer'),
    'organizers' => sc_t('dashboard_pages.organizers', 'Organizers'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'back_to_organizers' => sc_t('dashboard_pages.back_to_organizers', 'Back to Organizers'),
    'organization_details' => sc_t('dashboard_pages.organization_details', 'Organization Details'),
    'organization_name' => sc_t('dashboard_pages.organization_name', 'Organization Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'address' => sc_t('dashboard_pages.address', 'Address'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'social_links' => sc_t('dashboard_pages.social_links', 'Social Links'),
    'events' => sc_t('dashboard_pages.events', 'Events'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'action' => sc_t('dashboard_pages.action', 'Action'),
    'no_events_assigned' => sc_t('dashboard_pages.no_events_assigned_organizer', 'This organizer is not assigned to any events yet.'),
    'update_organizer' => sc_t('dashboard_pages.update_organizer', 'Update Organizer'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'created' => sc_t('dashboard_pages.created', 'Created'),
    'updated' => sc_t('dashboard_pages.updated', 'Updated'),
    'logo' => sc_t('dashboard_pages.logo', 'Logo'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'select_logo' => sc_t('dashboard_pages.select_logo', 'Select Logo'),
    'display_order' => sc_t('dashboard_pages.display_order', 'Display Order'),
    'order' => sc_t('dashboard_pages.order', 'Order'),
    'order_hint' => sc_t('dashboard_pages.order_hint', 'Lower numbers appear first'),
    'danger_zone' => sc_t('dashboard_pages.danger_zone', 'Danger Zone'),
    'delete_organizer' => sc_t('dashboard_pages.delete_organizer', 'Delete Organizer'),
    'delete_organizer_warning' => sc_t('dashboard_pages.delete_organizer_warning', 'This will also remove the organizer from all events.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // JavaScript
    'select_organizer_logo' => sc_t('dashboard_pages.select_organizer_logo', 'Select Organizer Logo'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'organizer_updated' => sc_t('dashboard_pages.organizer_updated', 'Organizer updated successfully!'),
    'error_updating_organizer' => sc_t('dashboard_pages.error_updating_organizer', 'Error updating organizer'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'confirm_delete_organizer' => sc_t('dashboard_pages.confirm_delete_organizer', 'Are you sure you want to delete this organizer?\n\nThis will also remove them from all events.'),
    'organizer_deleted' => sc_t('dashboard_pages.organizer_deleted', 'Organizer deleted!'),
    'error_deleting_organizer' => sc_t('dashboard_pages.error_deleting_organizer', 'Error deleting organizer'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

// Get organizer ID from URL
$organizer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$organizer_id) {
    wp_redirect(home_url('/event-manager-dashboard/organizers'));
    exit;
}

// Get organizer data
global $wpdb;
$organizer = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_organizers WHERE id = %d",
    $organizer_id
));

if (!$organizer) {
    wp_redirect(home_url('/event-manager-dashboard/organizers'));
    exit;
}

$page_title = $t['page_title'] . ': ' . esc_html($organizer->name);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get organizer's events
$organizer_events = $wpdb->get_results($wpdb->prepare(
    "SELECT e.* FROM {$wpdb->prefix}sc_events e
     INNER JOIN {$wpdb->prefix}sc_event_organizers eo ON e.id = eo.event_id
     WHERE eo.organizer_id = %d
     ORDER BY e.start_date DESC
     LIMIT 10",
    $organizer_id
));

// Parse social links
$social_links = array();
if (!empty($organizer->social_links)) {
    $social_links = is_array($organizer->social_links) ? $organizer->social_links : json_decode($organizer->social_links, true);
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
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/organizers'); ?>"><?php echo $t['organizers']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['edit']; ?></li>
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

        <form id="organizer-edit-form" class="organizer-form" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_organizer_action', 'sc_organizer_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_organizer">
            <input type="hidden" name="organizer_id" value="<?php echo $organizer_id; ?>">

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
                                <input type="text" class="form-control" id="organizer-name" name="name" required value="<?php echo esc_attr($organizer->name); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-email"><?php echo $t['email_address']; ?></label>
                                        <input type="email" class="form-control" id="organizer-email" name="email" value="<?php echo esc_attr($organizer->email ?: ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-phone"><?php echo $t['phone_number']; ?></label>
                                        <input type="tel" class="form-control" id="organizer-phone" name="phone" value="<?php echo esc_attr($organizer->phone ?: ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="organizer-website"><?php echo $t['website']; ?></label>
                                <input type="url" class="form-control" id="organizer-website" name="website" value="<?php echo esc_url($organizer->website ?: ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="organizer-address"><?php echo $t['address']; ?></label>
                                <textarea class="form-control" id="organizer-address" name="address" rows="2"><?php echo esc_textarea($organizer->address ?: ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="organizer-description"><?php echo $t['description']; ?></label>
                                <?php
                                wp_editor($organizer->description ?: '', 'organizer-description', array(
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
                                        <input type="url" class="form-control" id="organizer-facebook" name="facebook" value="<?php echo esc_url($social_links['facebook'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-twitter"><i class="fa fa-twitter"></i> Twitter/X</label>
                                        <input type="url" class="form-control" id="organizer-twitter" name="twitter" value="<?php echo esc_url($social_links['twitter'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-linkedin"><i class="fa fa-linkedin"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="organizer-linkedin" name="linkedin" value="<?php echo esc_url($social_links['linkedin'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer-instagram"><i class="fa fa-instagram"></i> Instagram</label>
                                        <input type="url" class="form-control" id="organizer-instagram" name="instagram" value="<?php echo esc_url($social_links['instagram'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organizer's Events -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-calendar"></i> <?php echo $t['events']; ?></h2>
                        </div>
                        <div class="body">
                            <?php if (empty($organizer_events)): ?>
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
                                            <?php foreach ($organizer_events as $event): ?>
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
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['update_organizer']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="organizer-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="organizer-status" name="is_active">
                                    <option value="1" <?php selected($organizer->is_active, 1); ?>><?php echo $t['active']; ?></option>
                                    <option value="0" <?php selected($organizer->is_active, 0); ?>><?php echo $t['inactive']; ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-calendar"></i> <?php echo $t['created']; ?>: <?php echo date('M d, Y', strtotime($organizer->created_at)); ?><br>
                                    <i class="fa fa-refresh"></i> <?php echo $t['updated']; ?>: <?php echo date('M d, Y', strtotime($organizer->updated_at)); ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-organizer-btn">
                                    <i class="fa fa-save"></i> <?php echo $t['update_organizer']; ?>
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
                            <?php $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : ''; ?>
                            <div id="organizer-logo-preview" class="mb-3 text-center" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                <img src="<?php echo esc_url($logo_url); ?>" alt="Organizer Logo" class="img-fluid rounded" style="max-width: 150px; max-height: 150px; object-fit: contain;">
                                <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-organizer-logo">
                                    <i class="fa fa-times"></i> <?php echo $t['remove']; ?>
                                </button>
                            </div>
                            <input type="hidden" name="logo" id="organizer-logo-id" value="<?php echo $organizer->logo ?: ''; ?>">
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-organizer-logo">
                                <i class="fa fa-upload"></i> <?php echo $t['select_logo']; ?>
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
                                <label for="organizer-order"><?php echo $t['order']; ?></label>
                                <input type="number" class="form-control" id="organizer-order" name="display_order" min="0" value="<?php echo $organizer->display_order ?: 0; ?>">
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
    organizer_updated: '<?php echo esc_js($t['organizer_updated']); ?>',
    error_updating_organizer: '<?php echo esc_js($t['error_updating_organizer']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    update_organizer: '<?php echo esc_js($t['update_organizer']); ?>',
    confirm_delete_organizer: '<?php echo esc_js($t['confirm_delete_organizer']); ?>',
    organizer_deleted: '<?php echo esc_js($t['organizer_deleted']); ?>',
    error_deleting_organizer: '<?php echo esc_js($t['error_deleting_organizer']); ?>'
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
    $('#organizer-edit-form').on('submit', function(e) {
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
                    toastr.success(organizerTranslations.organizer_updated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + organizerTranslations.update_organizer);
                } else {
                    toastr.error(response.data || organizerTranslations.error_updating_organizer);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + organizerTranslations.update_organizer);
                }
            },
            error: function() {
                toastr.error(organizerTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + organizerTranslations.update_organizer);
            }
        });
    });

    // Delete organizer
    $('#delete-organizer-btn').on('click', function() {
        var organizerId = $(this).data('organizer-id');

        if (!confirm(organizerTranslations.confirm_delete_organizer)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_delete_organizer',
                organizer_id: organizerId,
                nonce: '<?php echo wp_create_nonce('sc_organizer_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(organizerTranslations.organizer_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/organizers'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || organizerTranslations.error_deleting_organizer);
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
