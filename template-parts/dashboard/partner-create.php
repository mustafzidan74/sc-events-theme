<?php
/**
 * Dashboard - Create Partner Page
 * Standalone page for creating new partners
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.add_partner', 'Add Partner'),
    'partners' => sc_t('dashboard_pages.partners', 'Partners'),
    'add' => sc_t('dashboard_pages.add', 'Add'),
    'back_to_partners' => sc_t('dashboard_pages.back_to_partners', 'Back to Partners'),
    'basic_information' => sc_t('dashboard_pages.basic_information', 'Basic Information'),
    'name' => sc_t('dashboard_pages.partner_name', 'Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'publish' => sc_t('dashboard_pages.publish', 'Publish'),
    'tier' => sc_t('dashboard_pages.partner_tier', 'Tier'),
    'tier_platinum' => sc_t('dashboard_pages.tier_platinum', 'Platinum'),
    'tier_gold' => sc_t('dashboard_pages.tier_gold', 'Gold'),
    'tier_silver' => sc_t('dashboard_pages.tier_silver', 'Silver'),
    'tier_bronze' => sc_t('dashboard_pages.tier_bronze', 'Bronze'),
    'sort_order' => sc_t('dashboard_pages.sort_order', 'Sort Order'),
    'sort_order_hint' => sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'new_partner' => sc_t('dashboard_pages.new_partner', 'New'),
    'save_partner' => sc_t('dashboard_pages.save_partner', 'Save Partner'),
    'logo' => sc_t('dashboard_pages.partner_logo', 'Logo'),
    'select_logo' => sc_t('dashboard_pages.select_logo', 'Select Logo'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    'logo_hint' => sc_t('dashboard_pages.logo_hint', 'Recommended: Square image, minimum 200x200px'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // Placeholders
    'enter_partner_name' => sc_t('dashboard_pages.enter_partner_name', 'Enter partner name'),
    'enter_email' => sc_t('dashboard_pages.enter_email', 'Enter email address'),
    'enter_phone' => sc_t('dashboard_pages.enter_phone', 'Enter phone number'),
    'enter_website' => sc_t('dashboard_pages.enter_website', 'https://example.com'),
    'enter_description' => sc_t('dashboard_pages.enter_description', 'Enter partner description...'),
    // JavaScript
    'select_partner_logo' => sc_t('dashboard_pages.select_partner_logo', 'Select Partner Logo'),
    'use_this_image' => sc_t('dashboard_pages.use_this_image', 'Use this image'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'partner_created' => sc_t('dashboard_pages.partner_created', 'Partner created successfully!'),
    'error_creating_partner' => sc_t('dashboard_pages.error_creating_partner', 'Error creating partner'),
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
                    <h2><?php echo esc_html($t['page_title']); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/partners'); ?>"><?php echo esc_html($t['partners']); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html($t['add']); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/partners'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html($t['back_to_partners']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="partner-form" class="partner-form">
            <?php wp_nonce_field('sc_partner_action', 'sc_partner_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_partner">

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
                                <label for="partner-name"><?php echo esc_html($t['name']); ?> <span class="text-danger"><?php echo esc_html($t['required_field']); ?></span></label>
                                <input type="text" class="form-control" id="partner-name" name="name" required placeholder="<?php echo esc_attr($t['enter_partner_name']); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="partner-email"><?php echo esc_html($t['email_address']); ?></label>
                                        <input type="email" class="form-control" id="partner-email" name="email" placeholder="<?php echo esc_attr($t['enter_email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="partner-phone"><?php echo esc_html($t['phone_number']); ?></label>
                                        <input type="tel" class="form-control" id="partner-phone" name="phone" placeholder="<?php echo esc_attr($t['enter_phone']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="partner-website"><?php echo esc_html($t['website']); ?></label>
                                <input type="url" class="form-control" id="partner-website" name="website" placeholder="<?php echo esc_attr($t['enter_website']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="partner-description"><?php echo esc_html($t['description']); ?></label>
                                <textarea class="form-control" id="partner-description" name="description" rows="5" placeholder="<?php echo esc_attr($t['enter_description']); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Publish Box -->
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html($t['publish']); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="partner-tier"><?php echo esc_html($t['tier']); ?></label>
                                <select class="form-control" id="partner-tier" name="tier">
                                    <option value="bronze"><?php echo esc_html($t['tier_bronze']); ?></option>
                                    <option value="silver"><?php echo esc_html($t['tier_silver']); ?></option>
                                    <option value="gold"><?php echo esc_html($t['tier_gold']); ?></option>
                                    <option value="platinum"><?php echo esc_html($t['tier_platinum']); ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="partner-sort-order"><?php echo esc_html($t['sort_order']); ?></label>
                                <input type="number" class="form-control" id="partner-sort-order" name="sort_order" min="0" value="0">
                                <small class="text-muted"><?php echo esc_html($t['sort_order_hint']); ?></small>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i> <?php echo esc_html($t['status']); ?>: <span class="badge badge-secondary"><?php echo esc_html($t['new_partner']); ?></span>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-partner-btn">
                                    <i class="fa fa-save"></i> <?php echo esc_html($t['save_partner']); ?>
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
                            <div id="partner-logo-preview" class="mb-3 text-center" style="display:none;">
                                <img src="" alt="Partner Logo" class="img-fluid rounded" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-danger mt-2 d-block mx-auto" id="remove-partner-logo">
                                    <i class="fa fa-times"></i> <?php echo esc_html($t['remove']); ?>
                                </button>
                            </div>
                            <input type="hidden" name="logo" id="partner-logo-id" value="">
                            <button type="button" class="btn btn-outline-primary btn-block" id="select-partner-logo">
                                <i class="fa fa-upload"></i> <?php echo esc_html($t['select_logo']); ?>
                            </button>
                            <small class="text-muted d-block mt-2"><?php echo esc_html($t['logo_hint']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var partnerTranslations = {
    select_partner_logo: '<?php echo esc_js($t['select_partner_logo']); ?>',
    use_this_image: '<?php echo esc_js($t['use_this_image']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    partner_created: '<?php echo esc_js($t['partner_created']); ?>',
    error_creating_partner: '<?php echo esc_js($t['error_creating_partner']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    save_partner: '<?php echo esc_js($t['save_partner']); ?>'
};

jQuery(document).ready(function($) {
    // WordPress Media Uploader
    var mediaUploader;
    $('#select-partner-logo').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: partnerTranslations.select_partner_logo,
            button: { text: partnerTranslations.use_this_image },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#partner-logo-id').val(attachment.id);
            $('#partner-logo-preview img').attr('src', attachment.url);
            $('#partner-logo-preview').show();
        });

        mediaUploader.open();
    });

    $('#remove-partner-logo').on('click', function() {
        $('#partner-logo-id').val('');
        $('#partner-logo-preview').hide();
    });

    // Form submission
    $('#partner-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-partner-btn');

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + partnerTranslations.saving);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(partnerTranslations.partner_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/partners'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || partnerTranslations.error_creating_partner);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + partnerTranslations.save_partner);
                }
            },
            error: function() {
                toastr.error(partnerTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + partnerTranslations.save_partner);
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
