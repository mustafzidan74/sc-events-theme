<?php
/**
 * Dashboard - Edit Sponsor Page
 * Standalone page for editing sponsor details
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.edit_sponsor', 'Edit Sponsor'),
    'sponsors' => sc_t('dashboard_pages.sponsors', 'Sponsors'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'back_to_sponsors' => sc_t('dashboard_pages.back_to_sponsors', 'Back to Sponsors'),
    'basic_information' => sc_t('dashboard_pages.basic_information', 'Basic Information'),
    'name' => sc_t('dashboard_pages.sponsor_name', 'Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'publish' => sc_t('dashboard_pages.publish', 'Publish'),
    'tier' => sc_t('dashboard_pages.sponsor_tier', 'Tier'),
    'tier_platinum' => sc_t('dashboard_pages.tier_platinum', 'Platinum'),
    'tier_gold' => sc_t('dashboard_pages.tier_gold', 'Gold'),
    'tier_silver' => sc_t('dashboard_pages.tier_silver', 'Silver'),
    'tier_bronze' => sc_t('dashboard_pages.tier_bronze', 'Bronze'),
    'sort_order' => sc_t('dashboard_pages.sort_order', 'Sort Order'),
    'sort_order_hint' => sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'update_sponsor' => sc_t('dashboard_pages.update_sponsor', 'Update Sponsor'),
    'logo' => sc_t('dashboard_pages.sponsor_logo', 'Logo'),
    'select_logo' => sc_t('dashboard_pages.select_logo', 'Select Logo'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'logo_hint' => sc_t('dashboard_pages.logo_hint', 'Recommended: Square image, minimum 200x200px'),
    'assigned_events' => sc_t('dashboard_pages.assigned_events', 'Assigned Events'),
    'no_events_assigned' => sc_t('dashboard_pages.no_events_assigned_sponsor', 'This sponsor is not assigned to any events yet.'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'action' => sc_t('dashboard_pages.action', 'Action'),
    'created' => sc_t('dashboard_pages.created', 'Created'),
    'updated' => sc_t('dashboard_pages.updated', 'Updated'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'danger_zone' => sc_t('dashboard_pages.danger_zone', 'Danger Zone'),
    'delete_sponsor' => sc_t('dashboard_pages.delete_sponsor', 'Delete Sponsor'),
    'delete_sponsor_warning' => sc_t('dashboard_pages.delete_sponsor_warning', 'This will also remove the sponsor from all events.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // Placeholders
    'enter_sponsor_name' => sc_t('dashboard_pages.enter_sponsor_name', 'Enter sponsor name'),
    'enter_email' => sc_t('dashboard_pages.enter_email', 'Enter email address'),
    'enter_phone' => sc_t('dashboard_pages.enter_phone', 'Enter phone number'),
    'enter_website' => sc_t('dashboard_pages.enter_website', 'https://example.com'),
    'enter_description' => sc_t('dashboard_pages.enter_description', 'Enter sponsor description...'),
    // JavaScript
    'select_sponsor_logo' => sc_t('dashboard_pages.select_sponsor_logo', 'Select Sponsor Logo'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'sponsor_updated' => sc_t('dashboard_pages.sponsor_updated', 'Sponsor updated successfully!'),
    'error_updating_sponsor' => sc_t('dashboard_pages.error_updating_sponsor', 'Error updating sponsor'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'error_loading_sponsor' => sc_t('dashboard_pages.error_loading_sponsor', 'Error loading sponsor data'),
    'confirm_delete_sponsor' => sc_t('dashboard_pages.confirm_delete_sponsor', 'Are you sure you want to delete this sponsor?\n\nThis will also remove them from all events.'),
    'sponsor_deleted' => sc_t('dashboard_pages.sponsor_deleted', 'Sponsor deleted!'),
    'error_deleting_sponsor' => sc_t('dashboard_pages.error_deleting_sponsor', 'Error deleting sponsor'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

// Get sponsor ID from URL
$sponsor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$sponsor_id) {
    wp_redirect(home_url('/event-manager-dashboard/sponsors'));
    exit;
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
                    <h2><?php echo esc_html($t['page_title']); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/sponsors'); ?>"><?php echo esc_html($t['sponsors']); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html($t['edit']); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/sponsors'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html($t['back_to_sponsors']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div id="sponsor-loading" class="text-center py-5">
            <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
            <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
        </div>

        <!-- Form (hidden until data loads) -->
        <div id="sponsor-form-wrapper" style="display:none;">
            <form id="sponsor-form" class="sponsor-form">
                <?php wp_nonce_field('sc_sponsor_action', 'sc_sponsor_nonce'); ?>
                <input type="hidden" name="action" value="sc_save_sponsor">
                <input type="hidden" name="sponsor_id" value="<?php echo esc_attr($sponsor_id); ?>">

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Basic Information -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-building"></i> <?php echo esc_html($t['basic_information']); ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="sponsor-name"><?php echo esc_html($t['name']); ?> <span class="text-danger"><?php echo esc_html($t['required_field']); ?></span></label>
                                    <input type="text" class="form-control" id="sponsor-name" name="name" required placeholder="<?php echo esc_attr($t['enter_sponsor_name']); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="sponsor-title">Title / Tagline</label>
                                    <input type="text" class="form-control" id="sponsor-title" name="title" placeholder="e.g. Platinum Sponsor, Strategic Partner">
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sponsor-email"><?php echo esc_html($t['email_address']); ?></label>
                                            <input type="email" class="form-control" id="sponsor-email" name="email" placeholder="<?php echo esc_attr($t['enter_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sponsor-phone"><?php echo esc_html($t['phone_number']); ?></label>
                                            <input type="tel" class="form-control" id="sponsor-phone" name="phone" placeholder="<?php echo esc_attr($t['enter_phone']); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="sponsor-website"><?php echo esc_html($t['website']); ?></label>
                                    <input type="url" class="form-control" id="sponsor-website" name="website" placeholder="<?php echo esc_attr($t['enter_website']); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="sponsor-description"><?php echo esc_html($t['description']); ?></label>
                                    <textarea class="form-control" id="sponsor-description" name="description" rows="5" placeholder="<?php echo esc_attr($t['enter_description']); ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Publish Box -->
                        <div class="card">
                            <div class="header bg-primary">
                                <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html($t['update_sponsor']); ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="sponsor-tier"><?php echo esc_html($t['tier']); ?></label>
                                    <select class="form-control" id="sponsor-tier" name="tier">
                                        <option value="bronze"><?php echo esc_html($t['tier_bronze']); ?></option>
                                        <option value="silver"><?php echo esc_html($t['tier_silver']); ?></option>
                                        <option value="gold"><?php echo esc_html($t['tier_gold']); ?></option>
                                        <option value="platinum"><?php echo esc_html($t['tier_platinum']); ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="sponsor-sort-order"><?php echo esc_html($t['sort_order']); ?></label>
                                    <input type="number" class="form-control" id="sponsor-sort-order" name="sort_order" min="0" value="0">
                                    <small class="text-muted"><?php echo esc_html($t['sort_order_hint']); ?></small>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fa fa-calendar"></i> <?php echo esc_html($t['created']); ?>: <span id="sponsor-created-at">-</span><br>
                                        <i class="fa fa-refresh"></i> <?php echo esc_html($t['updated']); ?>: <span id="sponsor-updated-at">-</span>
                                    </small>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-sponsor-btn">
                                        <i class="fa fa-save"></i> <?php echo esc_html($t['update_sponsor']); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Logo -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-image"></i> <?php echo esc_html($t['logo']); ?></h2>
                            </div>
                            <div class="body">
                                <div id="sponsor-logo-preview" class="mb-3 text-center" style="display:none;">
                                    <img src="" alt="Sponsor Logo" class="img-fluid rounded" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-sponsor-logo">
                                        <i class="fa fa-times"></i> <?php echo esc_html($t['remove']); ?>
                                    </button>
                                </div>
                                <input type="hidden" name="logo" id="sponsor-logo-id" value="">
                                <button type="button" class="btn btn-outline-primary btn-block" id="select-sponsor-logo">
                                    <i class="fa fa-upload"></i> <?php echo esc_html($t['select_logo']); ?>
                                </button>
                                <small class="text-muted d-block mt-2"><?php echo esc_html($t['logo_hint']); ?></small>
                            </div>
                        </div>

                        <!-- Assigned Events -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-calendar"></i> <?php echo esc_html($t['assigned_events']); ?></h2>
                            </div>
                            <div class="body">
                                <div id="sponsor-events-list">
                                    <div class="text-center py-3">
                                        <i class="fa fa-spinner fa-spin text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="card border-danger">
                            <div class="header">
                                <h2><i class="fa fa-exclamation-triangle text-danger"></i> <?php echo esc_html($t['danger_zone']); ?></h2>
                            </div>
                            <div class="body">
                                <p class="text-muted mb-2"><?php echo esc_html($t['delete_sponsor_warning']); ?></p>
                                <button type="button" class="btn btn-outline-danger btn-block" id="delete-sponsor-btn" data-sponsor-id="<?php echo esc_attr($sponsor_id); ?>">
                                    <i class="fa fa-trash"></i> <?php echo esc_html($t['delete_sponsor']); ?>
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
var sponsorTranslations = {
    select_sponsor_logo: '<?php echo esc_js($t['select_sponsor_logo']); ?>',
    use_this_image: '<?php echo esc_js($t['use_this_image']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    sponsor_updated: '<?php echo esc_js($t['sponsor_updated']); ?>',
    error_updating_sponsor: '<?php echo esc_js($t['error_updating_sponsor']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    update_sponsor: '<?php echo esc_js($t['update_sponsor']); ?>',
    error_loading_sponsor: '<?php echo esc_js($t['error_loading_sponsor']); ?>',
    confirm_delete_sponsor: '<?php echo esc_js($t['confirm_delete_sponsor']); ?>',
    sponsor_deleted: '<?php echo esc_js($t['sponsor_deleted']); ?>',
    error_deleting_sponsor: '<?php echo esc_js($t['error_deleting_sponsor']); ?>',
    no_events_assigned: '<?php echo esc_js($t['no_events_assigned']); ?>'
};

jQuery(document).ready(function($) {
    var sponsorId = <?php echo intval($sponsor_id); ?>;

    // Escape HTML helper function
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Load sponsor data on page load
    function loadSponsorData() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_sponsor',
                nonce: scDashboard.nonce,
                sponsor_id: sponsorId
            },
            success: function(response) {
                if (response.success) {
                    populateForm(response.data.sponsor);
                    $('#sponsor-loading').hide();
                    $('#sponsor-form-wrapper').show();
                } else {
                    toastr.error(response.data || sponsorTranslations.error_loading_sponsor);
                }
            },
            error: function() {
                toastr.error(sponsorTranslations.error_occurred);
            }
        });
    }

    // Populate form fields with sponsor data
    function populateForm(sponsor) {
        $('#sponsor-name').val(sponsor.name || '');
        $('#sponsor-title').val(sponsor.title || '');
        $('#sponsor-email').val(sponsor.email || '');
        $('#sponsor-phone').val(sponsor.phone || '');
        $('#sponsor-website').val(sponsor.website || '');
        $('#sponsor-description').val(sponsor.description || '');
        $('#sponsor-tier').val(sponsor.tier || 'bronze');
        $('#sponsor-sort-order').val(sponsor.sort_order || 0);

        // Set timestamps
        if (sponsor.created_at) {
            $('#sponsor-created-at').text(sponsor.created_at);
        }
        if (sponsor.updated_at) {
            $('#sponsor-updated-at').text(sponsor.updated_at);
        }

        // Set logo
        if (sponsor.logo_url) {
            $('#sponsor-logo-id').val(sponsor.logo || '');
            $('#sponsor-logo-preview img').attr('src', sponsor.logo_url);
            $('#sponsor-logo-preview').show();
        }

        // Render assigned events
        renderAssignedEvents(sponsor.events || []);
    }

    // Render the assigned events list
    function renderAssignedEvents(events) {
        var container = $('#sponsor-events-list');
        container.empty();

        if (!events || events.length === 0) {
            container.html('<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> ' + sponsorTranslations.no_events_assigned + '</div>');
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
        html += '<thead><tr><th><?php echo esc_js($t['event']); ?></th><th><?php echo esc_js($t['date']); ?></th><th><?php echo esc_js($t['action']); ?></th></tr></thead>';
        html += '<tbody>';

        events.forEach(function(event) {
            html += '<tr>';
            html += '<td>' + escapeHtml(event.title) + '</td>';
            html += '<td><small>' + escapeHtml(event.start_date || '-') + '</small></td>';
            html += '<td><a href="<?php echo home_url('/event-manager-dashboard/event-view'); ?>?id=' + event.id + '" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.html(html);
    }

    // WordPress Media Uploader
    var mediaUploader;
    $('#select-sponsor-logo').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: sponsorTranslations.select_sponsor_logo,
            button: { text: sponsorTranslations.use_this_image },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#sponsor-logo-id').val(attachment.id);
            $('#sponsor-logo-preview img').attr('src', attachment.url);
            $('#sponsor-logo-preview').show();
        });

        mediaUploader.open();
    });

    $('#remove-sponsor-logo').on('click', function() {
        $('#sponsor-logo-id').val('');
        $('#sponsor-logo-preview').hide();
    });

    // Form submission
    $('#sponsor-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-sponsor-btn');

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + sponsorTranslations.saving);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(sponsorTranslations.sponsor_updated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + sponsorTranslations.update_sponsor);
                } else {
                    toastr.error(response.data || sponsorTranslations.error_updating_sponsor);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + sponsorTranslations.update_sponsor);
                }
            },
            error: function() {
                toastr.error(sponsorTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + sponsorTranslations.update_sponsor);
            }
        });
    });

    // Delete sponsor
    $('#delete-sponsor-btn').on('click', function() {
        var deleteSponsorId = $(this).data('sponsor-id');

        if (!confirm(sponsorTranslations.confirm_delete_sponsor)) {
            return;
        }

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_delete_sponsor',
                sponsor_id: deleteSponsorId,
                nonce: scDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(sponsorTranslations.sponsor_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/sponsors'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || sponsorTranslations.error_deleting_sponsor);
                }
            }
        });
    });

    // Initial load
    loadSponsorData();
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
