<?php
/**
 * Dashboard - Edit Company Attendee Page
 * Page for editing existing company/B2B attendees
 *
 * @package sc_events
 * @version 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Get company ID from URL
$company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$company_id) {
    wp_redirect(home_url('/event-manager-dashboard/company-attendees'));
    exit;
}

// Get company data
$company = null;
if (class_exists('SC_Company_Attendee')) {
    $company = SC_Company_Attendee::get($company_id);
}

if (!$company) {
    wp_redirect(home_url('/event-manager-dashboard/company-attendees'));
    exit;
}

// Translations
$t = array(
    'edit_company' => sc_t('dashboard_pages.edit_company', 'Edit Company'),
    'company_attendees' => sc_t('dashboard_pages.company_attendees', 'Company Attendees'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'back' => sc_t('dashboard_pages.back', 'Back'),
    'view_badge' => sc_t('dashboard_pages.view_badge', 'View Badge'),
    'company_code' => sc_t('dashboard_pages.company_code', 'Company Code'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'not_checked_in' => sc_t('dashboard_pages.not_checked_in', 'Not Checked In'),
    'company_information' => sc_t('dashboard_pages.company_information', 'Company Information'),
    'company_name' => sc_t('dashboard_pages.company_name', 'Company Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'company_logo' => sc_t('dashboard_pages.company_logo', 'Company Logo'),
    'logo_url' => sc_t('dashboard_pages.logo_url', 'Logo URL'),
    'upload' => sc_t('dashboard_pages.upload', 'Upload'),
    'social_media_links' => sc_t('dashboard_pages.social_media_links', 'Social Media Links'),
    'add_social_media_link' => sc_t('dashboard_pages.add_social_media_link', 'Add Social Media Link'),
    'address' => sc_t('dashboard_pages.address', 'Address'),
    'country' => sc_t('dashboard_pages.country', 'Country'),
    'city' => sc_t('dashboard_pages.city', 'City'),
    'full_address' => sc_t('dashboard_pages.full_address', 'Full Address'),
    'products' => sc_t('dashboard_pages.products', 'Products'),
    'add_product' => sc_t('dashboard_pages.add_product', 'Add Product'),
    'click_to_upload' => sc_t('dashboard_pages.click_to_upload', 'Click to upload'),
    'product_name' => sc_t('dashboard_pages.product_name', 'Product Name'),
    'product_description' => sc_t('dashboard_pages.product_description', 'Product Description'),
    'booth_sponsorship' => sc_t('dashboard_pages.booth_sponsorship', 'Booth & Sponsorship'),
    'booth_number' => sc_t('dashboard_pages.booth_number', 'Booth'),
    'select_booth' => sc_t('dashboard_pages.select_booth', '-- Select Booth --'),
    'no_booths_available' => sc_t('dashboard_pages.no_booths_available', 'No booths available'),
    'sponsorship_level' => sc_t('dashboard_pages.sponsorship_level', 'Sponsorship Level'),
    'none' => sc_t('dashboard_pages.none', 'None'),
    'platinum' => sc_t('dashboard_pages.platinum', 'Platinum'),
    'gold' => sc_t('dashboard_pages.gold', 'Gold'),
    'silver' => sc_t('dashboard_pages.silver', 'Silver'),
    'bronze' => sc_t('dashboard_pages.bronze', 'Bronze'),
    'exhibitor' => sc_t('dashboard_pages.exhibitor', 'Exhibitor'),
    'event_ticket' => sc_t('dashboard_pages.event_ticket', 'Event & Ticket'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'select_event_option' => sc_t('dashboard_pages.select_event_option', '-- Select Event --'),
    'select_ticket' => sc_t('dashboard_pages.select_ticket', 'Select Ticket'),
    'select_ticket_option' => sc_t('dashboard_pages.select_ticket_option', '-- Select Ticket --'),
    'payment' => sc_t('dashboard_pages.payment', 'Payment'),
    'payment_status' => sc_t('dashboard_pages.payment_status', 'Payment Status'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'paid' => sc_t('dashboard_pages.paid', 'Paid'),
    'failed' => sc_t('dashboard_pages.failed', 'Failed'),
    'refunded' => sc_t('dashboard_pages.refunded', 'Refunded'),
    'payment_method' => sc_t('dashboard_pages.payment_method', 'Payment Method'),
    'select' => sc_t('dashboard_pages.select', 'Select'),
    'cash' => sc_t('dashboard_pages.cash', 'Cash'),
    'bank_transfer' => sc_t('dashboard_pages.bank_transfer', 'Bank Transfer'),
    'credit_card' => sc_t('dashboard_pages.credit_card', 'Credit Card'),
    'check' => sc_t('dashboard_pages.check', 'Check'),
    'free_complimentary' => sc_t('dashboard_pages.free_complimentary', 'Free/Complimentary'),
    'amount_paid' => sc_t('dashboard_pages.amount_paid', 'Amount Paid'),
    'checkin' => sc_t('dashboard_pages.checkin', 'Check-in'),
    'checked_in_on' => sc_t('dashboard_pages.checked_in_on', 'Checked in on'),
    'notes' => sc_t('dashboard_pages.notes', 'Notes'),
    'update_company' => sc_t('dashboard_pages.update_company', 'Update Company'),
    'send_badge_email' => sc_t('dashboard_pages.send_badge_email', 'Send Badge Email'),
    'delete_company' => sc_t('dashboard_pages.delete_company', 'Delete Company'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_tickets_available' => sc_t('dashboard_pages.no_tickets_available', 'No tickets available'),
    'upload_failed' => sc_t('dashboard_pages.upload_failed', 'Upload failed'),
    'updating' => sc_t('dashboard_pages.updating', 'Updating...'),
    'company_updated' => sc_t('dashboard_pages.company_updated', 'Company updated successfully!'),
    'failed_to_update' => sc_t('dashboard_pages.failed_to_update', 'Failed to update company'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'sending' => sc_t('dashboard_pages.sending', 'Sending...'),
    'send_badge_confirm' => sc_t('dashboard_pages.send_badge_confirm', 'Send badge email to'),
    'badge_sent' => sc_t('dashboard_pages.badge_sent', 'Badge email sent successfully!'),
    'failed_to_send' => sc_t('dashboard_pages.failed_to_send', 'Failed to send email'),
    'delete_confirm' => sc_t('dashboard_pages.delete_confirm', 'Are you sure you want to delete this company? This action cannot be undone.'),
    'company_deleted' => sc_t('dashboard_pages.company_deleted', 'Company deleted successfully!'),
    'failed_to_delete' => sc_t('dashboard_pages.failed_to_delete', 'Failed to delete company'),
);

$page_title = $t['edit_company'] . ': ' . esc_html($company->company_name);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get all events
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'),
        'limit' => 500,
        'orderby' => 'start_date',
        'order' => 'DESC'
    ));
}

// Get tickets for selected event
$event_tickets = array();
if ($company->event_id && class_exists('SC_Ticket')) {
    $event_tickets = SC_Ticket::get_by_event($company->event_id);
}

// Social media icons
$social_icons = array(
    'facebook' => 'fa-facebook',
    'twitter' => 'fa-twitter',
    'instagram' => 'fa-instagram',
    'linkedin' => 'fa-linkedin',
    'youtube' => 'fa-youtube',
    'tiktok' => 'fa-music',
    'snapchat' => 'fa-snapchat',
    'pinterest' => 'fa-pinterest',
    'whatsapp' => 'fa-whatsapp',
    'telegram' => 'fa-telegram',
    'website' => 'fa-globe',
    'other' => 'fa-link'
);

// Get logo URL
$logo_url = '';
if ($company->company_logo) {
    $logo_url = wp_get_attachment_url($company->company_logo);
}

// Get social media and products from dedicated columns (hydrated by model)
$social_media = !empty($company->social_media_array) ? $company->social_media_array : array();
$products = !empty($company->products_array) ? $company->products_array : array();
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['edit_company']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/company-attendees'); ?>"><?php echo $t['company_attendees']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['edit']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/company-attendees'); ?>" class="btn btn-outline-secondary ml-2">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['back']; ?>
                        </a>
                        <button type="button" class="btn btn-info" id="view-badge-btn">
                            <i class="fa fa-id-badge"></i> <?php echo $t['view_badge']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Code Display -->
        <div class="alert alert-info">
            <strong><i class="fa fa-barcode"></i> <?php echo $t['company_code']; ?>:</strong>
            <span class="badge badge-dark" style="font-size: 16px;"><?php echo esc_html($company->company_code); ?></span>
            <span class="ml-3">
                <strong><?php echo $t['status']; ?>:</strong>
                <?php if ($company->checked_in): ?>
                    <span class="badge badge-success"><i class="fa fa-check"></i> <?php echo $t['checked_in']; ?></span>
                <?php else: ?>
                    <span class="badge badge-warning"><?php echo $t['not_checked_in']; ?></span>
                <?php endif; ?>
            </span>
        </div>

        <form id="company-attendee-form">
            <?php wp_nonce_field('sc_dashboard_nonce', 'nonce'); ?>
            <input type="hidden" name="action" value="sc_update_company_attendee">
            <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Company Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-building"></i> <?php echo $t['company_information']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="company-name"><?php echo $t['company_name']; ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="company-name" name="company_name" required value="<?php echo esc_attr($company->company_name); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact-email"><?php echo $t['email']; ?> <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="contact-email" name="contact_email" required value="<?php echo esc_attr($company->contact_email); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact-phone"><?php echo $t['phone']; ?> <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="contact-phone" name="contact_phone" required value="<?php echo esc_attr($company->contact_phone); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="company-logo"><?php echo $t['company_logo']; ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="company-logo-url" name="company_logo_url" placeholder="<?php echo esc_attr($t['logo_url']); ?>" value="<?php echo esc_url($logo_url); ?>" readonly>
                                    <input type="hidden" id="company-logo" name="company_logo" value="<?php echo esc_attr($company->company_logo); ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="upload-logo-btn">
                                            <i class="fa fa-upload"></i> <?php echo $t['upload']; ?>
                                        </button>
                                    </div>
                                </div>
                                <div id="logo-preview" class="mt-2" <?php echo $logo_url ? '' : 'style="display: none;"'; ?>>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo preview" style="max-height: 80px;">
                                    <button type="button" class="btn btn-sm btn-danger ml-2" id="remove-logo-btn">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Social Media Repeater -->
                            <div class="form-group">
                                <label><?php echo $t['social_media_links']; ?></label>
                                <div id="social-media-container">
                                    <?php if (!empty($social_media)): $idx = 0; ?>
                                        <?php foreach ($social_media as $sm): ?>
                                            <div class="social-media-item border rounded p-2 mb-2">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <select class="form-control form-control-sm social-platform" name="social_media[<?php echo $idx; ?>][platform]">
                                                            <?php foreach ($social_icons as $platform => $icon): ?>
                                                                <option value="<?php echo $platform; ?>" data-icon="<?php echo $icon; ?>" <?php selected($sm['platform'], $platform); ?>>
                                                                    <?php echo ucfirst($platform); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="input-group input-group-sm">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text social-icon"><i class="fa <?php echo $social_icons[$sm['platform']] ?? 'fa-link'; ?>"></i></span>
                                                            </div>
                                                            <input type="url" class="form-control" name="social_media[<?php echo $idx; ?>][url]" value="<?php echo esc_url($sm['url']); ?>" placeholder="https://...">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-right">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-social-btn">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php $idx++; endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-social-btn">
                                    <i class="fa fa-plus"></i> <?php echo $t['add_social_media_link']; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-map-marker"></i> <?php echo $t['address']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company-country"><?php echo $t['country']; ?></label>
                                        <input type="text" class="form-control" id="company-country" name="country" value="<?php echo esc_attr($company->country); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company-city"><?php echo $t['city']; ?></label>
                                        <input type="text" class="form-control" id="company-city" name="city" value="<?php echo esc_attr($company->city); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="company-address"><?php echo $t['full_address']; ?></label>
                                <textarea class="form-control" id="company-address" name="address" rows="2"><?php echo esc_textarea($company->address); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Products Section -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-cube"></i> <?php echo $t['products']; ?></h2>
                        </div>
                        <div class="body">
                            <div id="products-container">
                                <?php if (!empty($products)): $pidx = 0; ?>
                                    <?php foreach ($products as $product): ?>
                                        <div class="product-item border rounded p-3 mb-3">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="product-image-container text-center">
                                                        <div class="product-image-preview" style="width: 100%; height: 100px; background: #f5f5f5; border-radius: 5px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                                            <?php if (!empty($product['image'])): ?>
                                                                <img src="<?php echo wp_get_attachment_url($product['image']); ?>" style="max-width: 100%; max-height: 100px; object-fit: contain;">
                                                            <?php else: ?>
                                                                <i class="fa fa-image fa-2x text-muted"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <input type="hidden" class="product-image-id" name="products[<?php echo $pidx; ?>][image]" value="<?php echo esc_attr($product['image'] ?? ''); ?>">
                                                        <small class="text-muted d-block mt-1"><?php echo $t['click_to_upload']; ?></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="form-group mb-2">
                                                        <input type="text" class="form-control form-control-sm" name="products[<?php echo $pidx; ?>][name]" value="<?php echo esc_attr($product['name'] ?? ''); ?>" placeholder="<?php echo esc_attr($t['product_name']); ?>">
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <textarea class="form-control form-control-sm" name="products[<?php echo $pidx; ?>][description]" rows="2" placeholder="<?php echo esc_attr($t['product_description']); ?>"><?php echo esc_textarea($product['description'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 text-right">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-product-btn">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php $pidx++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm mt-2" id="add-product-btn">
                                <i class="fa fa-plus"></i> <?php echo $t['add_product']; ?>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Event & Ticket Selection -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-calendar"></i> <?php echo $t['event_ticket']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="company-event"><?php echo $t['select_event']; ?> <span class="text-danger">*</span></label>
                                <select class="form-control" id="company-event" name="event_id" required>
                                    <option value=""><?php echo $t['select_event_option']; ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>" <?php selected($event->id, $company->event_id); ?>>
                                            <?php echo esc_html($event->title); ?> (<?php echo date('M d, Y', strtotime($event->start_date)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="company-ticket"><?php echo $t['select_ticket']; ?></label>
                                <select class="form-control" id="company-ticket" name="ticket_id">
                                    <option value=""><?php echo $t['select_ticket_option']; ?></option>
                                    <?php foreach ($event_tickets as $ticket): ?>
                                        <option value="<?php echo $ticket->id; ?>" <?php selected($ticket->id, $company->ticket_id); ?>>
                                            <?php echo esc_html($ticket->name); ?> - <?php echo sc_format_price($ticket->price); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-credit-card"></i> <?php echo $t['payment']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="payment-status"><?php echo $t['payment_status']; ?></label>
                                <select class="form-control" id="payment-status" name="payment_status">
                                    <option value="pending" <?php selected($company->payment_status, 'pending'); ?>><?php echo $t['pending']; ?></option>
                                    <option value="success" <?php selected($company->payment_status, 'success'); ?>><?php echo $t['paid']; ?></option>
                                    <option value="failed" <?php selected($company->payment_status, 'failed'); ?>><?php echo $t['failed']; ?></option>
                                    <option value="refunded" <?php selected($company->payment_status, 'refunded'); ?>><?php echo $t['refunded']; ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="payment-method"><?php echo $t['payment_method']; ?></label>
                                <select class="form-control" id="payment-method" name="payment_method">
                                    <option value="">-- <?php echo $t['select']; ?> --</option>
                                    <option value="cash" <?php selected($company->payment_method, 'cash'); ?>><?php echo $t['cash']; ?></option>
                                    <option value="bank_transfer" <?php selected($company->payment_method, 'bank_transfer'); ?>><?php echo $t['bank_transfer']; ?></option>
                                    <option value="credit_card" <?php selected($company->payment_method, 'credit_card'); ?>><?php echo $t['credit_card']; ?></option>
                                    <option value="check" <?php selected($company->payment_method, 'check'); ?>><?php echo $t['check']; ?></option>
                                    <option value="free" <?php selected($company->payment_method, 'free'); ?>><?php echo $t['free_complimentary']; ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="amount-paid"><?php echo $t['amount_paid']; ?></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="amount-paid" name="amount_paid" step="0.01" min="0" value="<?php echo esc_attr($company->amount_paid); ?>">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?php echo sc_get_currency_symbol(); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Check-in Status -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-check-circle"></i> <?php echo $t['checkin']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label><?php echo $t['status']; ?></label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="checked-in" name="checked_in" value="1" <?php checked($company->checked_in, 1); ?>>
                                    <label class="custom-control-label" for="checked-in"><?php echo $t['checked_in']; ?></label>
                                </div>
                            </div>
                            <?php if ($company->checked_in && $company->checked_in_at): ?>
                                <p class="text-muted mb-0">
                                    <small><?php echo $t['checked_in_on']; ?>: <?php echo date('M d, Y H:i', strtotime($company->checked_in_at)); ?></small>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sticky-note"></i> <?php echo $t['notes']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <textarea class="form-control" id="company-notes" name="notes" rows="4"><?php echo esc_textarea($company->notes); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="body">
                            <button type="submit" class="btn btn-primary btn-block btn-lg" id="save-company-btn">
                                <i class="fa fa-save"></i> <?php echo $t['update_company']; ?>
                            </button>
                            <button type="button" class="btn btn-success btn-block" id="send-badge-btn">
                                <i class="fa fa-envelope"></i> <?php echo $t['send_badge_email']; ?>
                            </button>
                            <button type="button" class="btn btn-danger btn-block" id="delete-company-btn">
                                <i class="fa fa-trash"></i> <?php echo $t['delete_company']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden file inputs -->
<input type="file" id="logo-file-input" accept="image/*" style="display: none;">
<input type="file" id="product-image-input" accept="image/*" style="display: none;">

<!-- Social Media Item Template -->
<template id="social-media-template">
    <div class="social-media-item border rounded p-2 mb-2">
        <div class="row align-items-center">
            <div class="col-md-4">
                <select class="form-control form-control-sm social-platform" name="social_media[{index}][platform]">
                    <?php foreach ($social_icons as $platform => $icon): ?>
                        <option value="<?php echo $platform; ?>" data-icon="<?php echo $icon; ?>">
                            <?php echo ucfirst($platform); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text social-icon"><i class="fa fa-facebook"></i></span>
                    </div>
                    <input type="url" class="form-control" name="social_media[{index}][url]" placeholder="https://...">
                </div>
            </div>
            <div class="col-md-2 text-right">
                <button type="button" class="btn btn-sm btn-outline-danger remove-social-btn">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<!-- Product Item Template -->
<template id="product-template">
    <div class="product-item border rounded p-3 mb-3">
        <div class="row">
            <div class="col-md-3">
                <div class="product-image-container text-center">
                    <div class="product-image-preview" style="width: 100%; height: 100px; background: #f5f5f5; border-radius: 5px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        <i class="fa fa-image fa-2x text-muted"></i>
                    </div>
                    <input type="hidden" class="product-image-id" name="products[{index}][image]">
                    <small class="text-muted d-block mt-1"><?php echo $t['click_to_upload']; ?></small>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-group mb-2">
                    <input type="text" class="form-control form-control-sm" name="products[{index}][name]" placeholder="<?php echo esc_attr($t['product_name']); ?>">
                </div>
                <div class="form-group mb-0">
                    <textarea class="form-control form-control-sm" name="products[{index}][description]" rows="2" placeholder="<?php echo esc_attr($t['product_description']); ?>"></textarea>
                </div>
            </div>
            <div class="col-md-1 text-right">
                <button type="button" class="btn btn-sm btn-outline-danger remove-product-btn">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<style>
.social-media-item .social-icon {
    width: 35px;
    justify-content: center;
}
.product-image-preview img {
    max-width: 100%;
    max-height: 100px;
    object-fit: contain;
}
</style>

<script>
// Define ajaxurl
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

// Translations
var companyEditTranslations = {
    loading: '<?php echo esc_js($t['loading']); ?>',
    select_ticket_option: '<?php echo esc_js($t['select_ticket_option']); ?>',
    no_tickets_available: '<?php echo esc_js($t['no_tickets_available']); ?>',
    upload_failed: '<?php echo esc_js($t['upload_failed']); ?>',
    updating: '<?php echo esc_js($t['updating']); ?>',
    company_updated: '<?php echo esc_js($t['company_updated']); ?>',
    failed_to_update: '<?php echo esc_js($t['failed_to_update']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    sending: '<?php echo esc_js($t['sending']); ?>',
    send_badge_confirm: '<?php echo esc_js($t['send_badge_confirm']); ?>',
    badge_sent: '<?php echo esc_js($t['badge_sent']); ?>',
    failed_to_send: '<?php echo esc_js($t['failed_to_send']); ?>',
    delete_confirm: '<?php echo esc_js($t['delete_confirm']); ?>',
    company_deleted: '<?php echo esc_js($t['company_deleted']); ?>',
    failed_to_delete: '<?php echo esc_js($t['failed_to_delete']); ?>'
};

jQuery(document).ready(function($) {
    var companyId = <?php echo $company_id; ?>;
    var socialIndex = <?php echo !empty($social_media) ? count($social_media) : 0; ?>;
    var productIndex = <?php echo !empty($products) ? count($products) : 0; ?>;
    var currentProductContainer = null;

    // Load tickets when event changes
    $('#company-event').on('change', function() {
        var eventId = $(this).val();
        var ticketSelect = $('#company-ticket');

        ticketSelect.html('<option value="">' + companyEditTranslations.loading + '</option>');

        if (!eventId) {
            ticketSelect.html('<option value="">' + companyEditTranslations.select_ticket_option + '</option>');
            return;
        }

        // Load tickets
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_tickets_by_event',
                event_id: eventId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.tickets) {
                    var html = '<option value="">' + companyEditTranslations.select_ticket_option + '</option>';
                    $.each(response.data.tickets, function(i, ticket) {
                        html += '<option value="' + ticket.id + '">' + ticket.name + ' - ' + ticket.price_formatted + '</option>';
                    });
                    ticketSelect.html(html);
                } else {
                    ticketSelect.html('<option value="">' + companyEditTranslations.no_tickets_available + '</option>');
                }
            }
        });
    });

    // Logo upload
    $('#upload-logo-btn').on('click', function() {
        $('#logo-file-input').click();
    });

    $('#logo-file-input').on('change', function() {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'sc_upload_company_logo');
        formData.append('nonce', '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#company-logo').val(response.data.attachment_id);
                    $('#company-logo-url').val(response.data.url);
                    $('#logo-preview img').attr('src', response.data.url);
                    $('#logo-preview').show();
                } else {
                    alert(response.data.message || companyEditTranslations.upload_failed);
                }
            }
        });
    });

    $('#remove-logo-btn').on('click', function() {
        $('#company-logo').val('');
        $('#company-logo-url').val('');
        $('#logo-preview').hide();
    });

    // ============ Social Media Repeater ============
    $('#add-social-btn').on('click', function() {
        var template = $('#social-media-template').html();
        template = template.replace(/{index}/g, socialIndex);
        $('#social-media-container').append(template);
        socialIndex++;
    });

    $(document).on('click', '.remove-social-btn', function() {
        $(this).closest('.social-media-item').remove();
    });

    // Update icon when platform changes
    $(document).on('change', '.social-platform', function() {
        var icon = $(this).find(':selected').data('icon');
        $(this).closest('.social-media-item').find('.social-icon i').attr('class', 'fa ' + icon);
    });

    // ============ Products Repeater ============
    $('#add-product-btn').on('click', function() {
        var template = $('#product-template').html();
        template = template.replace(/{index}/g, productIndex);
        $('#products-container').append(template);
        productIndex++;
    });

    $(document).on('click', '.remove-product-btn', function() {
        $(this).closest('.product-item').remove();
    });

    // Product image upload
    $(document).on('click', '.product-image-preview', function() {
        currentProductContainer = $(this).closest('.product-item');
        $('#product-image-input').click();
    });

    $('#product-image-input').on('change', function() {
        var file = this.files[0];
        if (!file || !currentProductContainer) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'sc_upload_company_logo');
        formData.append('nonce', '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>');

        var previewContainer = currentProductContainer.find('.product-image-preview');
        previewContainer.html('<i class="fa fa-spinner fa-spin fa-2x"></i>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    currentProductContainer.find('.product-image-id').val(response.data.attachment_id);
                    previewContainer.html('<img src="' + response.data.url + '" style="max-width: 100%; max-height: 100px; object-fit: contain;">');
                } else {
                    previewContainer.html('<i class="fa fa-image fa-2x text-muted"></i>');
                    alert(response.data.message || companyEditTranslations.upload_failed);
                }
            },
            error: function() {
                previewContainer.html('<i class="fa fa-image fa-2x text-muted"></i>');
                alert(companyEditTranslations.upload_failed);
            }
        });

        $(this).val('');
    });

    // Form submission
    $('#company-attendee-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#save-company-btn');
        var originalText = $btn.html();

        $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + companyEditTranslations.updating).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message || companyEditTranslations.company_updated);
                    // Update URL with timestamp so manual refresh bypasses server cache
                    var freshUrl = '<?php echo esc_js(strtok($_SERVER['REQUEST_URI'], '?')); ?>?id=<?php echo $company_id; ?>&_=' + Date.now();
                    history.replaceState(null, '', freshUrl);
                } else {
                    var errorMsg = response.data.message || companyEditTranslations.failed_to_update;
                    if (response.data.debug_error) {
                        console.log('DB Error:', response.data.debug_error);
                        console.log('Query:', response.data.debug_query);
                        errorMsg += ' - Check console for details';
                    }
                    toastr.error(errorMsg);
                }
                $btn.html(originalText).prop('disabled', false);
            },
            error: function() {
                toastr.error(companyEditTranslations.error_occurred);
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });

    // View badge
    $('#view-badge-btn').on('click', function() {
        window.open('<?php echo home_url('/company-ticket/' . $company->company_code . '/'); ?>', '_blank');
    });

    // Send badge email
    $('#send-badge-btn').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();

        if (!confirm(companyEditTranslations.send_badge_confirm + ' ' + $('#contact-email').val() + '?')) {
            return;
        }

        $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + companyEditTranslations.sending).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_send_company_badge_email',
                company_id: companyId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(companyEditTranslations.badge_sent);
                } else {
                    toastr.error(response.data.message || companyEditTranslations.failed_to_send);
                }
                $btn.html(originalText).prop('disabled', false);
            },
            error: function() {
                toastr.error(companyEditTranslations.error_occurred);
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Delete company
    $('#delete-company-btn').on('click', function() {
        if (!confirm(companyEditTranslations.delete_confirm)) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_delete_company_attendee',
                company_id: companyId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(companyEditTranslations.company_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/company-attendees'); ?>';
                    }, 1500);
                } else {
                    toastr.error(response.data.message || companyEditTranslations.failed_to_delete);
                }
            }
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
