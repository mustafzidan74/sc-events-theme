<?php
/**
 * Dashboard - Create Event Page
 * Matches exactly the old theme's event modal structure
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

$page_title = sc_t('dashboard_pages.create_new_event', 'Create New Event');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get categories
$categories = get_terms(array(
    'taxonomy' => 'sc_event_category',
    'hide_empty' => false,
));
if (is_wp_error($categories)) {
    $categories = array();
}

// Get speakers from custom table
global $wpdb;
$speakers = $wpdb->get_results("SELECT id, name, title FROM {$wpdb->prefix}sc_speakers WHERE is_active = 1 ORDER BY name ASC");

// Get organizers from custom table
$organizers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sc_organizers WHERE is_active = 1 ORDER BY name ASC");

// Get certificate templates (table may not exist yet)
$certificate_templates = array();
if (class_exists('SC_Certificate_Template')) {
    $table_name = $wpdb->prefix . 'sc_certificate_templates';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if ($table_exists) {
        $certificate_templates = SC_Certificate_Template::get_all(array('is_active' => 1));
    }
}

// Get sponsors from custom table (with table existence check)
$all_sponsors = array();
$sponsors_table = $wpdb->prefix . 'sc_sponsors';
if ($wpdb->get_var("SHOW TABLES LIKE '$sponsors_table'") === $sponsors_table) {
    $all_sponsors = $wpdb->get_results("SELECT id, name, tier FROM $sponsors_table WHERE is_active = 1 ORDER BY FIELD(tier, 'platinum', 'gold', 'silver', 'bronze'), sort_order ASC, name ASC");
}

// Get partners from custom table (with table existence check)
$all_partners = array();
$partners_table = $wpdb->prefix . 'sc_partners';
if ($wpdb->get_var("SHOW TABLES LIKE '$partners_table'") === $partners_table) {
    $all_partners = $wpdb->get_results("SELECT id, name, tier FROM $partners_table WHERE is_active = 1 ORDER BY FIELD(tier, 'platinum', 'gold', 'silver', 'bronze'), sort_order ASC, name ASC");
}

// Module status checks for conditional display
$speakers_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('speakers');
$certificates_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('certificates');

// RTL and language check
$is_rtl = is_rtl();

// Translations array - using central sc_t() function
$t = array(
    'create_event' => sc_t('dashboard_pages.create_new_event', 'Create New Event'),
    'create_new' => sc_t('dashboard_pages.create_event', 'Create New'),
    'events' => sc_t('nav.events', 'Events'),
    'publish_event' => sc_t('dashboard_pages.publish_event', 'Publish Event'),
    'save_draft' => sc_t('dashboard_pages.save_draft', 'Save as Draft'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'basic_info' => sc_t('dashboard_pages.basic_info', 'Basic Information'),
    'date_time' => sc_t('dashboard_pages.date_time', 'Date & Time'),
    'location' => sc_t('dashboard_pages.location', 'Location'),
    'speakers_organizers' => sc_t('dashboard_pages.speakers_organizers', 'Speakers & Organizers'),
    'organizers' => sc_t('dashboard_pages.organizers', 'Organizers'),
    'branding_media' => sc_t('dashboard_pages.branding_media', 'Branding & Media'),
    'faq' => sc_t('dashboard_pages.faq', 'FAQ'),
    'schedules' => sc_t('dashboard_pages.schedules', 'Schedules'),
    'extra_fields' => sc_t('dashboard_pages.extra_fields', 'Extra Fields'),
    'tickets_pricing' => sc_t('dashboard_pages.tickets_pricing', 'Tickets & Pricing'),
    'additional_sections' => sc_t('dashboard_pages.additional_sections', 'Additional Sections'),
    'certificates' => sc_t('dashboard_pages.certificates', 'Certificates'),
    'event_title' => sc_t('dashboard_pages.event_title', 'Event Title'),
    'event_permalink' => sc_t('dashboard_pages.event_permalink', 'Event Permalink'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'add_media' => sc_t('dashboard_pages.add_media', 'Add Media'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'confirm' => sc_t('dashboard_pages.confirm', 'Confirm'),
    // Status
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'publish' => sc_t('dashboard_pages.publish', 'Publish'),
    'draft' => sc_t('dashboard_pages.draft', 'Draft'),
    // Schedule file
    'event_schedules_file' => sc_t('dashboard_pages.event_schedules_file', 'Event Schedules File (PDF)'),
    'choose_pdf_file' => sc_t('dashboard_pages.choose_pdf_file', 'Choose PDF file...'),
    'upload_pdf_help' => sc_t('dashboard_pages.upload_pdf_help', 'Upload a PDF file containing the event schedule/agenda'),
    'view_current_file' => sc_t('dashboard_pages.view_current_file', 'View Current File'),
    'remove' => sc_t('dashboard_pages.remove', 'Remove'),
    // Categories
    'event_categories' => sc_t('dashboard_pages.event_categories', 'Event Categories'),
    // Attendance tracking
    'enable_attendance_tracking' => sc_t('dashboard_pages.enable_attendance_tracking', 'Enable Attendance Tracking (Check-in/Check-out)'),
    'attendance_tracking_help' => sc_t('dashboard_pages.attendance_tracking_help', 'When enabled, QR scanning will track attendance times'),
    'first_scan_checkin' => sc_t('dashboard_pages.first_scan_checkin', 'First scan = Check-in (arrival)'),
    'second_scan_checkout' => sc_t('dashboard_pages.second_scan_checkout', 'Second scan = Check-out (departure)'),
    'duration_calculated' => sc_t('dashboard_pages.duration_calculated', 'Duration calculated automatically'),
    // Date & Time
    'event_date_time' => sc_t('dashboard_pages.event_date_time', 'Event Date & Time'),
    'date_selection_mode' => sc_t('dashboard_pages.date_selection_mode', 'Date Selection Mode'),
    'date_range' => sc_t('dashboard_pages.date_range_mode', 'Date Range'),
    'specific_days' => sc_t('dashboard_pages.specific_days', 'Specific Days'),
    'start_date' => sc_t('dashboard_pages.start_date', 'Start Date'),
    'end_date' => sc_t('dashboard_pages.end_date', 'End Date'),
    'select_event_days' => sc_t('dashboard_pages.select_event_days', 'Select Event Days'),
    'add_day' => sc_t('dashboard_pages.add_day', 'Add Day'),
    'click_to_remove' => sc_t('dashboard_pages.click_to_remove', 'Click on a day badge to remove it'),
    'start_time' => sc_t('dashboard_pages.start_time', 'Start Time'),
    'end_time' => sc_t('dashboard_pages.end_time', 'End Time'),
    'select_time' => sc_t('dashboard_pages.select_time', 'Select time'),
    // Schedules tab
    'event_schedules' => sc_t('dashboard_pages.event_schedules', 'Event Schedules'),
    'add_schedules_help' => sc_t('dashboard_pages.add_schedules_help', 'Add multiple schedule topics/sessions for this event'),
    'add_schedule_topic' => sc_t('dashboard_pages.add_schedule_topic', 'Add Schedule Topic'),
    // Location
    'event_location' => sc_t('dashboard_pages.event_location', 'Event Location'),
    'event_type' => sc_t('dashboard_pages.event_type', 'Event Type'),
    'physical_event' => sc_t('dashboard_pages.physical_event', 'Physical Event'),
    'offline_event' => sc_t('dashboard_pages.offline_event', 'Offline Event'),
    'online_event' => sc_t('dashboard_pages.online_event', 'Online Event'),
    'venue_name' => sc_t('dashboard_pages.venue_name', 'Venue Name'),
    'venue_address' => sc_t('dashboard_pages.venue_address', 'Venue Address'),
    'venue_location' => sc_t('dashboard_pages.venue_location', 'Venue Location'),
    'venue_location_placeholder' => sc_t('dashboard_pages.venue_location_placeholder', 'Enter venue location'),
    'venue_full_address' => sc_t('dashboard_pages.venue_full_address', 'Full address of the event venue'),
    'city' => sc_t('dashboard_pages.city', 'City'),
    'country' => sc_t('dashboard_pages.country', 'Country'),
    'online_link' => sc_t('dashboard_pages.online_link', 'Online Event Link'),
    'online_link_placeholder' => sc_t('dashboard_pages.online_link_placeholder', 'https://zoom.us/meeting/...'),
    'custom_url' => sc_t('dashboard_pages.custom_url', 'Custom URL (Optional)'),
    'enter_custom_url' => sc_t('dashboard_pages.enter_custom_url', 'Enter custom URL here'),
    'meeting_link_help' => sc_t('dashboard_pages.meeting_link_help', 'Zoom, Google Meet, Microsoft Teams, or any custom meeting link'),
    // Speakers
    'speakers' => sc_t('dashboard_pages.speakers', 'Speakers'),
    'event_speakers' => sc_t('dashboard_pages.event_speakers', 'Event Speakers'),
    'event_organizers' => sc_t('dashboard_pages.event_organizers', 'Event Organizers'),
    'select_speakers' => sc_t('dashboard_pages.select_speakers', 'Select Speakers'),
    'no_speakers' => sc_t('dashboard_pages.no_speakers', 'No speakers available'),
    'select_organizers' => sc_t('dashboard_pages.select_organizers', 'Select Organizers'),
    'no_organizers' => sc_t('dashboard_pages.no_organizers', 'No organizers available'),
    'manage_speakers' => sc_t('dashboard_pages.manage_speakers', 'Manage Speakers'),
    'manage_organizers' => sc_t('dashboard_pages.manage_organizers', 'Manage Organizers'),
    // Branding
    'featured_image' => sc_t('dashboard_pages.featured_image', 'Featured Image'),
    'upload_featured' => sc_t('dashboard_pages.upload_featured', 'Upload Featured Image'),
    'banner_image' => sc_t('dashboard_pages.banner_image', 'Banner Image'),
    'upload_banner' => sc_t('dashboard_pages.upload_banner', 'Upload Banner Image'),
    'primary_color' => sc_t('dashboard_pages.primary_color', 'Primary Color'),
    'secondary_color' => sc_t('dashboard_pages.secondary_color', 'Secondary Color'),
    // FAQ
    'event_faq' => sc_t('dashboard_pages.event_faq', 'Event FAQ'),
    'add_faq_help' => sc_t('dashboard_pages.add_faq_help', 'Add frequently asked questions for this event'),
    'add_faq' => sc_t('dashboard_pages.add_faq', 'Add FAQ'),
    'question' => sc_t('dashboard_pages.question', 'Question'),
    'answer' => sc_t('dashboard_pages.answer', 'Answer'),
    // Extra fields
    'custom_fields' => sc_t('dashboard_pages.custom_fields', 'Custom Fields'),
    'add_custom_field' => sc_t('dashboard_pages.add_custom_field', 'Add Custom Field'),
    'field_label' => sc_t('dashboard_pages.field_label', 'Field Label'),
    'field_value' => sc_t('dashboard_pages.field_value', 'Field Value'),
    // Tickets
    'event_tickets' => sc_t('dashboard_pages.event_tickets', 'Event Tickets'),
    'add_ticket' => sc_t('dashboard_pages.add_ticket', 'Add Ticket'),
    'ticket_name' => sc_t('dashboard_pages.ticket_name', 'Ticket Name'),
    'ticket_price' => sc_t('dashboard_pages.ticket_price', 'Price'),
    'ticket_quantity' => sc_t('dashboard_pages.ticket_quantity', 'Quantity'),
    'unlimited' => sc_t('dashboard_pages.unlimited', 'Unlimited'),
    'early_bird' => sc_t('dashboard_pages.early_bird', 'Early Bird'),
    'early_bird_price' => sc_t('dashboard_pages.early_bird_price', 'Early Bird Price'),
    'early_bird_end' => sc_t('dashboard_pages.early_bird_end', 'Early Bird End Date'),
    // Additional sections
    'section_title' => sc_t('dashboard_pages.section_title', 'Section Title'),
    'section_content' => sc_t('dashboard_pages.section_content', 'Section Content'),
    'add_section' => sc_t('dashboard_pages.add_section', 'Add Section'),
    // Certificates
    'certificate_template' => sc_t('dashboard_pages.certificate_template', 'Certificate Template'),
    'select_template' => sc_t('dashboard_pages.select_template', 'Select Template'),
    'no_template' => sc_t('dashboard_pages.no_template', 'No Template'),
    'enable_certificates' => sc_t('dashboard_pages.enable_certificates', 'Enable Certificates'),
    // Common
    'required' => sc_t('dashboard_pages.required', 'Required'),
    'optional' => sc_t('dashboard_pages.optional', 'Optional'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    // Certificates Section
    'certificate_settings' => sc_t('dashboard_pages.certificate_settings', 'Certificate Settings'),
    'certificate_settings_desc' => sc_t('dashboard_pages.certificate_settings_desc', 'Configure certificate issuance for this event. Attendees who complete this event can receive a certificate.'),
    'enable_certificates_label' => sc_t('dashboard_pages.enable_certificates_label', 'Enable Certificates'),
    'enable_certificates_desc' => sc_t('dashboard_pages.enable_certificates_desc', 'Allow attendees to receive certificates for this event'),
    'choose_certificate_design' => sc_t('dashboard_pages.choose_certificate_design', 'Choose the certificate design for this event'),
    'no_templates_found' => sc_t('dashboard_pages.no_templates_found', 'No certificate templates found.'),
    'create_template_first' => sc_t('dashboard_pages.create_template_first', 'Create a template first'),
    'issuance_method' => sc_t('dashboard_pages.issuance_method', 'Issuance Method'),
    'automatic' => sc_t('dashboard_pages.automatic', 'Automatic'),
    'automatic_desc' => sc_t('dashboard_pages.automatic_desc', 'Issue certificates automatically when attendee checks in'),
    'manual' => sc_t('dashboard_pages.manual', 'Manual'),
    'manual_desc' => sc_t('dashboard_pages.manual_desc', 'Manually issue certificates from the attendees list'),
    'requirements_optional' => sc_t('dashboard_pages.requirements_optional', 'Requirements (Optional)'),
    'require_checkin' => sc_t('dashboard_pages.require_checkin', 'Require check-in to receive certificate'),
    'require_checkout' => sc_t('dashboard_pages.require_checkout', 'Require check-out (event completion) to receive certificate'),
    'tip_preview' => sc_t('dashboard_pages.tip_preview', 'Tip: You can preview certificate templates from the'),
    'certificate_templates_page' => sc_t('dashboard_pages.certificate_templates_page', 'Certificate Templates'),
    // JavaScript messages
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'event_created' => sc_t('dashboard_pages.event_created', 'Event created successfully!'),
    'error_creating_event' => sc_t('dashboard_pages.error_creating_event', 'Error creating event'),
    'connection_error' => sc_t('dashboard_pages.connection_error', 'Connection error. Please try again.'),
    'save_event' => sc_t('dashboard_pages.save_event', 'Save Event'),
    'confirm_delete_ticket' => sc_t('dashboard_pages.confirm_delete_ticket', 'Are you sure you want to delete this ticket?'),
    'ticket_deleted' => sc_t('dashboard_pages.ticket_deleted', 'Ticket deleted'),
    'select_categories' => sc_t('dashboard_pages.select_categories', 'Select categories...'),
    'select_speakers_placeholder' => sc_t('dashboard_pages.select_speakers_placeholder', 'Select speakers...'),
    'select_organizers_placeholder' => sc_t('dashboard_pages.select_organizers_placeholder', 'Select organizers...'),
    // Section types
    'image_slider_section' => sc_t('dashboard_pages.image_slider_section', 'Image Slider Section'),
    'about_section' => sc_t('dashboard_pages.about_section', 'About Section'),
    'card_section' => sc_t('dashboard_pages.card_section', 'Card Section'),
    'image_grid_section' => sc_t('dashboard_pages.image_grid_section', 'Image Grid Section'),
    'eg_event_gallery' => sc_t('dashboard_pages.eg_event_gallery', 'e.g., Event Gallery'),
    'images' => sc_t('dashboard_pages.images', 'Images'),
    'choose_images' => sc_t('dashboard_pages.choose_images', 'Choose images...'),
    'eg_about_event' => sc_t('dashboard_pages.eg_about_event', 'e.g., About This Event'),
    'enter_description' => sc_t('dashboard_pages.enter_description', 'Enter description paragraphs'),
    'button_text' => sc_t('dashboard_pages.button_text', 'Button Text'),
    'eg_learn_more' => sc_t('dashboard_pages.eg_learn_more', 'e.g., Learn More'),
    'button_url' => sc_t('dashboard_pages.button_url', 'Button URL'),
    'main_image' => sc_t('dashboard_pages.main_image', 'Main Image'),
    'choose_image' => sc_t('dashboard_pages.choose_image', 'Choose image...'),
    'eg_why_attend' => sc_t('dashboard_pages.eg_why_attend', 'e.g., Why Attend?'),
    'add_card' => sc_t('dashboard_pages.add_card', 'Add Card'),
    'icon' => sc_t('dashboard_pages.icon', 'Icon'),
    'card_title' => sc_t('dashboard_pages.card_title', 'Card Title'),
    'card_description' => sc_t('dashboard_pages.card_description', 'Card Description'),
    'eg_sponsors' => sc_t('dashboard_pages.eg_sponsors', 'e.g., Our Sponsors'),
);

// Default timezone
$wp_timezone = get_option('timezone_string', 'Africa/Cairo');
if (empty($wp_timezone)) {
    $wp_timezone = 'Africa/Cairo';
}

// Generate UTC offsets
$utc_offsets = array();
$offset_range = range(-12, 14);
foreach ($offset_range as $offset) {
    $hours = ($offset > 0) ? '+' . $offset : (string) $offset;
    if ($offset != 0) {
        $utc_offsets[] = 'UTC' . $hours;
        $utc_offsets[] = 'UTC' . $hours . ':30';
    } else {
        $utc_offsets[] = 'UTC+0';
        $utc_offsets[] = 'UTC+0:30';
        $utc_offsets[] = 'UTC-0:30';
    }
    if (in_array($offset, array(5, 8, 12, 13))) {
        $utc_offsets[] = 'UTC+' . $offset . ':45';
    }
}

// Group timezones by region
$all_timezones = array_merge($utc_offsets, DateTimeZone::listIdentifiers());
$grouped_timezones = array(
    'UTC Offsets' => array(),
    'Africa' => array(),
    'America' => array(),
    'Antarctica' => array(),
    'Arctic' => array(),
    'Asia' => array(),
    'Atlantic' => array(),
    'Australia' => array(),
    'Europe' => array(),
    'Indian' => array(),
    'Pacific' => array(),
);

foreach ($all_timezones as $tz) {
    if (strpos($tz, 'UTC') === 0) {
        $grouped_timezones['UTC Offsets'][] = $tz;
    } elseif (strpos($tz, '/') !== false) {
        $parts = explode('/', $tz);
        $region = $parts[0];
        if (isset($grouped_timezones[$region])) {
            $grouped_timezones[$region][] = $tz;
        }
    }
}

// Currency - use dynamic currency symbol
$currency = sc_get_currency_symbol();
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['create_event']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/events'); ?>"><?php echo $t['events']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['create_new']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <button type="submit" form="event-form" class="btn btn-primary <?php echo $is_rtl ? 'mr-2' : 'ml-2'; ?>">
                            <i class="fa fa-save"></i> <?php echo $t['publish_event']; ?>
                        </button>
                        <button type="button" id="save-draft-btn-top" class="btn btn-secondary <?php echo $is_rtl ? 'mr-2' : 'ml-2'; ?>">
                            <i class="fa fa-file-o"></i> <?php echo $t['save_draft']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/events'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="event-form" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_event_action', 'sc_event_nonce'); ?>
            <input type="hidden" name="action" value="sc_create_or_update_event">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>">
            <input type="hidden" name="event_id" value="">

            <div class="card">
                <div class="body">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs mb-4" id="event-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#basic-info" role="tab">
                                <i class="fa fa-info-circle"></i> <?php echo $t['basic_info']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#datetime-info" role="tab">
                                <i class="fa fa-clock-o"></i> <?php echo $t['date_time']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#location-info" role="tab">
                                <i class="fa fa-map-marker"></i> <?php echo $t['location']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#speakers-info" role="tab">
                                <i class="fa fa-<?php echo $speakers_enabled ? 'microphone' : 'users'; ?>"></i> <?php echo $speakers_enabled ? $t['speakers_organizers'] : $t['organizers']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#branding-info" role="tab">
                                <i class="fa fa-paint-brush"></i> <?php echo $t['branding_media']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#faq-info" role="tab">
                                <i class="fa fa-question-circle"></i> <?php echo $t['faq']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#schedules-info" role="tab">
                                <i class="fa fa-list-alt"></i> <?php echo $t['schedules']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#extra-info" role="tab">
                                <i class="fa fa-puzzle-piece"></i> <?php echo $t['extra_fields']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tickets-info" role="tab">
                                <i class="fa fa-ticket"></i> <?php echo $t['tickets_pricing']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#additional-sections-info" role="tab">
                                <i class="fa fa-th-large"></i> <?php echo $t['additional_sections']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#sponsors-partners-info" role="tab">
                                <i class="fa fa-handshake-o"></i> <?php echo sc_t('event_form.sponsors_partners', 'Sponsors & Partners'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#organizing-company-info" role="tab">
                                <i class="fa fa-building"></i> <?php echo sc_t('event_form.organizing_company', 'Organizing Company'); ?>
                            </a>
                        </li>
                        <?php if ($certificates_enabled): ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#certificates-info" role="tab">
                                <i class="fa fa-certificate"></i> <?php echo $t['certificates']; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Tabs Content -->
                    <div class="tab-content" id="event-tabs-content">

                        <!-- ==================== BASIC INFORMATION TAB ==================== -->
                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo $t['basic_info']; ?></h5>

                            <!-- Event Title -->
                            <div class="form-group">
                                <label for="event-title"><?php echo $t['event_title']; ?></label>
                                <input type="text" class="form-control" id="event-title" name="event_title" placeholder="<?php echo $t['event_title']; ?>">
                            </div>

                            <!-- Event Permalink -->
                            <div class="form-group">
                                <label><?php echo $t['event_permalink']; ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo home_url('/events/'); ?></span>
                                    </div>
                                    <input type="text" class="form-control" id="event-slug" name="slug" placeholder="event-slug" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="edit-slug-btn">
                                            <i class="fa fa-pencil"></i> <?php echo $t['edit']; ?>
                                        </button>
                                        <button class="btn btn-success" type="button" id="confirm-slug-btn" style="display: none;">
                                            <i class="fa fa-check"></i> <?php echo $t['confirm']; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Event Description -->
                            <div class="form-group">
                                <label for="event-description"><?php echo $t['description']; ?></label>
                                <?php
                                wp_editor('', 'event-description', array(
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'tinymce' => array(
                                        'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,blockquote,alignleft,aligncenter,alignright,undo,redo',
                                        'toolbar2' => ''
                                    )
                                ));
                                ?>
                            </div>

                            <!-- Status -->
                            <div class="form-group">
                                <label for="event-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="event-status" name="status">
                                    <option value="publish"><?php echo $t['publish']; ?></option>
                                    <option value="draft"><?php echo $t['draft']; ?></option>
                                </select>
                            </div>

                            <!-- Event Schedules File (PDF) -->
                            <div class="form-group">
                                <label for="event-schedules-file"><i class="fa fa-file-pdf-o"></i> <?php echo $t['event_schedules_file']; ?></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="event-schedules-file" name="schedules_file" accept=".pdf,application/pdf">
                                    <label class="custom-file-label" for="event-schedules-file"><?php echo $t['choose_pdf_file']; ?></label>
                                </div>
                                <small class="form-text text-muted"><?php echo $t['upload_pdf_help']; ?></small>
                                <div id="schedules-file-preview" class="mt-2" style="display: none;">
                                    <div class="alert alert-success py-2 d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="fa fa-file-pdf-o text-danger"></i>
                                            <a href="#" id="schedules-file-link" target="_blank" class="ml-2"><?php echo $t['view_current_file']; ?></a>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-danger" id="remove-schedules-file-btn">
                                            <i class="fa fa-trash"></i> <?php echo $t['remove']; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Event Categories -->
                            <div class="form-group">
                                <label for="event-categories"><?php echo $t['event_categories']; ?></label>
                                <select class="form-control select2" id="event-categories" name="categories[]" multiple>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Attendance Tracking Option -->
                            <div class="form-group mt-4">
                                <div class="card" style="border: 2px solid #17a2b8; background: #e7f5f8;">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-start">
                                            <input type="checkbox" id="attendance-tracking" name="attendance_tracking" value="1" style="width: 22px; height: 22px; cursor: pointer; margin-top: 2px; flex-shrink: 0;">
                                            <label for="attendance-tracking" style="cursor: pointer; margin-left: 12px; margin-bottom: 0;">
                                                <strong><i class="fa fa-qrcode text-info"></i> <?php echo $t['enable_attendance_tracking']; ?></strong>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="fa fa-info-circle"></i> <?php echo $t['attendance_tracking_help']; ?>:
                                                    <br><i class="fa fa-sign-in text-success ml-2"></i> <?php echo $t['first_scan_checkin']; ?>
                                                    <br><i class="fa fa-sign-out text-warning ml-2"></i> <?php echo $t['second_scan_checkout']; ?>
                                                    <br><i class="fa fa-clock-o text-primary ml-2"></i> <?php echo $t['duration_calculated']; ?>
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== DATE & TIME TAB ==================== -->
                        <div class="tab-pane fade" id="datetime-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo $t['event_date_time']; ?></h5>

                            <!-- Date Selection Mode -->
                            <div class="form-group">
                                <label><i class="fa fa-calendar"></i> <?php echo $t['date_selection_mode']; ?></label>
                                <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" style="max-width: 500px;">
                                    <label class="btn btn-outline-primary active flex-fill" id="btn-date-range">
                                        <input type="radio" name="date_selection_mode" value="range" checked>
                                        <i class="fa fa-calendar-minus-o"></i> <?php echo $t['date_range']; ?>
                                    </label>
                                    <label class="btn btn-outline-primary flex-fill" id="btn-specific-days">
                                        <input type="radio" name="date_selection_mode" value="specific">
                                        <i class="fa fa-calendar-check-o"></i> <?php echo $t['specific_days']; ?>
                                    </label>
                                </div>
                            </div>

                            <!-- Date Range Mode -->
                            <div id="date-range-fields">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="event-start-date"><?php echo $t['start_date']; ?></label>
                                            <input type="text" class="form-control" id="event-start-date" name="start_date" placeholder="Select date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="event-end-date"><?php echo $t['end_date']; ?></label>
                                            <input type="text" class="form-control" id="event-end-date" name="end_date" placeholder="Select date">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Specific Days Mode -->
                            <div id="specific-days-fields" style="display: none;">
                                <div class="form-group">
                                    <label><i class="fa fa-calendar-plus-o"></i> <?php echo $t['select_event_days']; ?></label>
                                    <div class="input-group mb-3" style="max-width: 300px;">
                                        <input type="text" class="form-control" id="add-specific-day" placeholder="Select date">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-success" id="add-specific-day-btn">
                                                <i class="fa fa-plus"></i> <?php echo $t['add_day']; ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="specific-days-list" class="d-flex flex-wrap gap-2"></div>
                                    <input type="hidden" id="specific-days-data" name="specific_days" value="">
                                    <small class="form-text text-muted mt-2"><?php echo $t['click_to_remove']; ?></small>
                                </div>
                            </div>

                            <div class="row" id="time-fields">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event-start-time"><?php echo $t['start_time']; ?></label>
                                        <input type="text" class="form-control" id="event-start-time" name="start_time" placeholder="<?php echo $t['select_time']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event-end-time"><?php echo $t['end_time']; ?></label>
                                        <input type="text" class="form-control" id="event-end-time" name="end_time" placeholder="<?php echo $t['select_time']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== SCHEDULES TAB ==================== -->
                        <div class="tab-pane fade" id="schedules-info" role="tabpanel">
                            <div class="text-muted text-center py-4">
                                <i class="fa fa-calendar-o fa-2x mb-2 d-block" style="opacity:.3"></i>
                                <?php echo esc_html(sc_t('dashboard_pages.no_schedules_yet', 'No schedules yet.')); ?>
                            </div>
                        </div>

                        <!-- ==================== LOCATION TAB ==================== -->
                        <div class="tab-pane fade" id="location-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo $t['event_location']; ?></h5>

                            <!-- Event Type Selection -->
                            <div class="form-group">
                                <label><i class="fa fa-globe"></i> <?php echo $t['event_type']; ?></label>
                                <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                    <label class="btn btn-outline-primary active flex-fill" id="btn-offline">
                                        <input type="radio" name="location_type" value="offline" checked>
                                        <i class="fa fa-map-marker"></i> <?php echo $t['offline_event']; ?>
                                    </label>
                                    <label class="btn btn-outline-primary flex-fill" id="btn-online">
                                        <input type="radio" name="location_type" value="online">
                                        <i class="fa fa-video-camera"></i> <?php echo $t['online_event']; ?>
                                    </label>
                                </div>
                            </div>

                            <!-- Offline Event Fields -->
                            <div id="offline-event-fields">
                                <div class="form-group">
                                    <label for="event-address"><?php echo $t['venue_location']; ?></label>
                                    <input type="text" class="form-control" id="event-address" name="venue_address" placeholder="<?php echo $t['venue_location_placeholder']; ?>">
                                    <small class="form-text text-muted"><?php echo $t['venue_full_address']; ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="event-google-maps-url"><i class="fa fa-map-marker"></i> Google Maps URL</label>
                                    <input type="url" class="form-control" id="event-google-maps-url" name="google_maps_url" placeholder="https://maps.app.goo.gl/...">
                                    <small class="form-text text-muted">الصق رابط الموقع من جوجل مابس (Share → Copy link)</small>
                                </div>
                                <div class="form-group mt-3">
                                    <label><?php echo sc_t('event_form.venue_image', 'Venue Image'); ?></label>
                                    <div id="venue-image-preview" style="display:none; margin-bottom:10px;">
                                        <img src="" style="max-width:200px; max-height:150px; border-radius:8px;">
                                        <button type="button" class="btn btn-sm btn-danger ml-2" id="remove-venue-image"><i class="fa fa-times"></i></button>
                                    </div>
                                    <input type="hidden" name="venue_image" id="venue-image-id" value="">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="select-venue-image">
                                        <i class="fa fa-image"></i> <?php echo sc_t('event_form.select_venue_image', 'Select Venue Image'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Online Event Fields -->
                            <div id="online-event-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="event-meeting-link"><i class="fa fa-link"></i> <?php echo $t['custom_url']; ?></label>
                                    <input type="url" class="form-control" id="event-meeting-link" name="meeting_link" placeholder="<?php echo $t['enter_custom_url']; ?>">
                                    <small class="form-text text-muted"><?php echo $t['meeting_link_help']; ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== SPEAKERS & ORGANIZERS TAB ==================== -->
                        <div class="tab-pane fade" id="speakers-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo $speakers_enabled ? $t['speakers_organizers'] : $t['organizers']; ?></h5>

                            <?php if ($speakers_enabled): ?>
                            <!-- Speakers Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><?php echo $t['event_speakers']; ?></h6>
                                    <a href="<?php echo home_url('/event-manager-dashboard/speakers'); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fa fa-plus"></i> <?php echo $t['manage_speakers']; ?>
                                    </a>
                                </div>
                                <div class="form-group">
                                    <label for="event-speakers"><?php echo $t['select_speakers']; ?></label>
                                    <select class="form-control select2" id="event-speakers" name="speakers[]" multiple>
                                        <?php foreach ($speakers as $speaker):
                                            $display_text = $speaker->name;
                                            if (!empty($speaker->title)) {
                                                $display_text .= ' (' . $speaker->title . ')';
                                            }
                                        ?>
                                            <option value="<?php echo $speaker->id; ?>"><?php echo esc_html($display_text); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Organizers Section -->
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><?php echo $t['event_organizers']; ?></h6>
                                    <a href="<?php echo home_url('/event-manager-dashboard/organizers'); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fa fa-plus"></i> <?php echo $t['manage_organizers']; ?>
                                    </a>
                                </div>
                                <div class="form-group">
                                    <label for="event-organizers"><?php echo $t['select_organizers']; ?></label>
                                    <select class="form-control select2" id="event-organizers" name="organizers[]" multiple>
                                        <?php foreach ($organizers as $organizer): ?>
                                            <option value="<?php echo $organizer->id; ?>"><?php echo esc_html($organizer->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== BRANDING & MEDIA TAB ==================== -->
                        <div class="tab-pane fade" id="branding-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo $t['branding_media']; ?></h5>

                            <!-- Event Logo -->
                            <div class="form-group">
                                <label><i class="fa fa-image"></i> Event Logo</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="brand-logo" name="brand_logo" accept="image/*">
                                    <label class="custom-file-label" for="brand-logo">Choose file...</label>
                                </div>
                                <small class="form-text text-muted">Recommended size: 800x800px (Square)</small>
                                <div id="logo-preview" class="mt-3" style="display: none;">
                                    <img src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                    <button type="button" class="btn btn-sm btn-danger ml-2" id="remove-logo-btn">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Event Banner -->
                            <div class="form-group">
                                <label><i class="fa fa-picture-o"></i> Event Banner</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="event-banner" name="event_banner" accept="image/*">
                                    <label class="custom-file-label" for="event-banner">Choose banner...</label>
                                </div>
                                <small class="form-text text-muted">Recommended size: 1920x600px</small>
                                <div id="banner-preview" class="mt-3" style="display: none;">
                                    <img src="" alt="Banner Preview" class="img-thumbnail" style="max-width: 100%; max-height: 150px;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2" id="remove-banner-btn">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3"><i class="fa fa-paint-brush"></i> Event Colors</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="calendar-bg-color">Primary Color</label>
                                        <input type="color" class="form-control" id="calendar-bg-color" name="calendar_bg_color" value="#007bff" style="height: 40px;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="calendar-text-color">Secondary Color</label>
                                        <input type="color" class="form-control" id="calendar-text-color" name="calendar_text_color" value="#ffffff" style="height: 40px;">
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3"><i class="fa fa-share-alt"></i> Social Media Links</h6>
                            <p class="text-muted small mb-3">Add social media links for this event. Click the icon button to select an icon.</p>

                            <div id="social-links-container"></div>

                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-social-link-btn">
                                <i class="fa fa-plus"></i> Add Social Link
                            </button>

                            <input type="hidden" id="social-links-data" name="social_links" value="">

                            <!-- FontAwesome Icon Picker Modal -->
                            <div class="modal fade" id="iconPickerModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title"><i class="fa fa-th"></i> Select Icon</h5>
                                            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group mb-3">
                                                <input type="text" class="form-control" id="icon-search" placeholder="Search icons...">
                                            </div>
                                            <div id="icon-grid" class="d-flex flex-wrap" style="max-height: 400px; overflow-y: auto;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== FAQ TAB ==================== -->
                        <div class="tab-pane fade" id="faq-info" role="tabpanel">
                            <h5 class="mb-3">Frequently Asked Questions</h5>
                            <p class="text-muted small">Add questions and answers. Use the arrows to reorder.</p>

                            <div id="faq-list"></div>

                            <button type="button" class="btn btn-sm btn-primary mt-3" id="add-faq-btn">
                                <i class="fa fa-plus"></i> Add Question
                            </button>

                            <input type="hidden" id="faq-data" name="faq_data" value="">
                        </div>

                        <!-- ==================== EXTRA FIELDS TAB ==================== -->
                        <div class="tab-pane fade" id="extra-info" role="tabpanel">
                            <h5 class="mb-3">Extra Custom Fields</h5>

                            <div id="extra-fields-list"></div>

                            <button type="button" class="btn btn-sm btn-primary mt-3" id="add-extra-field-btn">
                                <i class="fa fa-plus"></i> Add Field
                            </button>

                            <input type="hidden" id="extra-fields-data" name="extra_fields_data" value="">
                        </div>

                        <!-- ==================== TICKETS TAB ==================== -->
                        <div class="tab-pane fade" id="tickets-info" role="tabpanel">
                            <h5 class="mb-3">Tickets & Pricing</h5>

                            <div id="tickets-list" class="row"></div>

                            <!-- Inline Ticket Form (replaces modal) -->
                            <div class="card mt-3" id="ticket-form-card" style="display: none; border: 2px solid #007bff;">
                                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0" id="ticket-form-title"><i class="fa fa-ticket"></i> Add New Ticket</h6>
                                    <button type="button" class="btn btn-sm btn-light" id="cancel-ticket-btn">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" id="ticket-id">
                                    <input type="hidden" id="ticket-index" value="-1">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Ticket Name</label>
                                                <input type="text" class="form-control" id="ticket-name" placeholder="e.g., General Admission">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="ticket-price-group">
                                                <label>Price (<?php echo esc_html($currency); ?>)</label>
                                                <input type="number" class="form-control" id="ticket-price" min="0" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea class="form-control" id="ticket-description" rows="2" placeholder="What's included with this ticket?"></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Available Quantity</label>
                                                <input type="number" class="form-control" id="ticket-qty" min="1" placeholder="Unlimited">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Min Per Order</label>
                                                <input type="number" class="form-control" id="ticket-min-qty" min="1" value="1">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Max Per Order</label>
                                                <input type="number" class="form-control" id="ticket-max-qty" min="1" value="10">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Sale Start Date</label>
                                                <input type="text" class="form-control" id="ticket-sale-start" placeholder="Select date">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Sale End Date</label>
                                                <input type="text" class="form-control" id="ticket-sale-end" placeholder="Select date">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="ticket-status" checked>
                                                <label class="custom-control-label" for="ticket-status">Active</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="ticket-use-coupons">
                                                <label class="custom-control-label" for="ticket-use-coupons">Allow Coupons</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-control form-control-sm" id="ticket-type">
                                                <option value="general"><?php echo esc_html(sc_t('tickets.general', 'General')); ?></option>
                                                <option value="competitor"><?php echo esc_html(sc_t('tickets.competitor', 'Competitor')); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3 text-right">
                                        <button type="button" class="btn btn-secondary" id="cancel-ticket-btn-2">Cancel</button>
                                        <button type="button" class="btn btn-primary" id="save-ticket-inline-btn">
                                            <i class="fa fa-save"></i> Save Ticket
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Ticket Button -->
                            <div class="card mt-3 text-center" style="cursor: pointer; border: 2px dashed #007bff;" id="add-ticket-card">
                                <div class="card-body py-3">
                                    <i class="fa fa-plus-circle fa-2x text-primary mb-2"></i>
                                    <h6 class="text-primary mb-0">Add Event Ticket</h6>
                                </div>
                            </div>

                            <input type="hidden" id="tickets-data" name="tickets_data" value="">
                        </div>

                        <!-- ==================== ADDITIONAL SECTIONS TAB ==================== -->
                        <div class="tab-pane fade" id="additional-sections-info" role="tabpanel">
                            <h5 class="mb-3">Additional Sections</h5>
                            <p class="text-muted small">Add custom sections to your event page. Choose a section type below:</p>

                            <!-- Inline Section Type Selector (replaces modal) -->
                            <div class="section-type-selector mb-4">
                                <div class="row">
                                    <div class="col-6 col-md-3 mb-2">
                                        <div class="section-type-btn" data-section-type="image_slider">
                                            <i class="fa fa-picture-o"></i>
                                            <span>Image Slider</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-2">
                                        <div class="section-type-btn" data-section-type="about">
                                            <i class="fa fa-info-circle"></i>
                                            <span>About Section</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-2">
                                        <div class="section-type-btn" data-section-type="card">
                                            <i class="fa fa-th"></i>
                                            <span>Card Section</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-2">
                                        <div class="section-type-btn" data-section-type="image_grid">
                                            <i class="fa fa-th-large"></i>
                                            <span>Image Grid</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="additional-sections-list"></div>

                            <input type="hidden" id="additional-sections-data" name="additional_sections_data" value="">
                        </div>

                        <!-- ==================== SPONSORS & PARTNERS TAB ==================== -->
                        <div class="tab-pane fade" id="sponsors-partners-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo sc_t('event_form.sponsors_partners', 'Sponsors & Partners'); ?></h5>
                            <p class="text-muted small mb-4"><?php echo sc_t('event_form.sponsors_partners_desc', 'Select sponsors and partners for this event.'); ?></p>

                            <!-- Sponsors Section -->
                            <div class="form-group">
                                <label><i class="fa fa-handshake-o mr-1"></i> <?php echo sc_t('event_form.sponsors', 'Sponsors'); ?></label>
                                <select class="form-control select2" id="event-sponsors" name="sponsors[]" multiple>
                                    <?php
                                    $tier_labels = array('platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze');
                                    foreach ($all_sponsors as $sponsor):
                                        $tier_label = isset($tier_labels[$sponsor->tier]) ? $tier_labels[$sponsor->tier] : ucfirst($sponsor->tier);
                                    ?>
                                        <option value="<?php echo $sponsor->id; ?>"><?php echo esc_html($sponsor->name . ' (' . $tier_label . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Partners Section -->
                            <div class="form-group mt-4">
                                <label><i class="fa fa-users mr-1"></i> <?php echo sc_t('event_form.partners', 'Partners'); ?></label>
                                <select class="form-control select2" id="event-partners" name="partners[]" multiple>
                                    <?php foreach ($all_partners as $partner):
                                        $tier_label = isset($tier_labels[$partner->tier]) ? $tier_labels[$partner->tier] : ucfirst($partner->tier);
                                    ?>
                                        <option value="<?php echo $partner->id; ?>"><?php echo esc_html($partner->name . ' (' . $tier_label . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- ==================== ORGANIZING COMPANY TAB ==================== -->
                        <div class="tab-pane fade" id="organizing-company-info" role="tabpanel">
                            <h5 class="mb-3"><?php echo sc_t('event_form.organizing_company', 'Organizing Company'); ?></h5>
                            <p class="text-muted small mb-4"><?php echo sc_t('event_form.organizing_company_desc', 'Add the organizing company details for this event.'); ?></p>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-building mr-1"></i> <?php echo sc_t('event_form.company_name', 'Company Name'); ?></label>
                                        <input type="text" class="form-control" id="oc-name" name="oc_name" placeholder="<?php echo esc_attr(sc_t('event_form.company_name_placeholder', 'Enter company name')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-globe mr-1"></i> <?php echo sc_t('event_form.company_website', 'Website'); ?></label>
                                        <input type="url" class="form-control" id="oc-website" name="oc_website" placeholder="https://example.com">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-image mr-1"></i> <?php echo sc_t('event_form.company_logo', 'Company Logo'); ?></label>
                                        <div class="input-group">
                                            <input type="hidden" id="oc-logo" name="oc_logo" value="">
                                            <input type="text" class="form-control" id="oc-logo-preview" readonly placeholder="<?php echo esc_attr(sc_t('event_form.no_image_selected', 'No image selected')); ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="scSelectMedia('oc-logo', 'oc-logo-preview', 'oc-logo-img')">
                                                    <i class="fa fa-upload"></i> <?php echo sc_t('event_form.select_image', 'Select'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <img id="oc-logo-img" src="" class="mt-2 rounded" style="max-height:80px;display:none;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-picture-o mr-1"></i> <?php echo sc_t('event_form.company_banner', 'Company Banner'); ?></label>
                                        <div class="input-group">
                                            <input type="hidden" id="oc-banner" name="oc_banner" value="">
                                            <input type="text" class="form-control" id="oc-banner-preview" readonly placeholder="<?php echo esc_attr(sc_t('event_form.no_image_selected', 'No image selected')); ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="scSelectMedia('oc-banner', 'oc-banner-preview', 'oc-banner-img')">
                                                    <i class="fa fa-upload"></i> <?php echo sc_t('event_form.select_image', 'Select'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <img id="oc-banner-img" src="" class="mt-2 rounded" style="max-height:80px;display:none;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><i class="fa fa-align-left mr-1"></i> <?php echo sc_t('event_form.company_description', 'Description'); ?></label>
                                <textarea class="form-control" id="oc-description" name="oc_description" rows="5" placeholder="<?php echo esc_attr(sc_t('event_form.company_description_placeholder', 'Brief description about the organizing company...')); ?>"></textarea>
                            </div>

                            <h6 class="mt-4 mb-3"><i class="fa fa-phone mr-1"></i> <?php echo sc_t('event_form.contact_info', 'Contact Information'); ?></h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php echo sc_t('event_form.company_phone', 'Phone'); ?></label>
                                        <input type="text" class="form-control" id="oc-phone" name="oc_phone" placeholder="+20 xxx xxx xxxx">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php echo sc_t('event_form.company_email', 'Email'); ?></label>
                                        <input type="email" class="form-control" id="oc-email" name="oc_email" placeholder="info@company.com">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php echo sc_t('event_form.company_whatsapp', 'WhatsApp'); ?></label>
                                        <input type="text" class="form-control" id="oc-whatsapp" name="oc_whatsapp" placeholder="+20 xxx xxx xxxx">
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-4 mb-3"><i class="fa fa-share-alt mr-1"></i> <?php echo sc_t('event_form.company_social', 'Social Media'); ?></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-facebook mr-1"></i> Facebook</label>
                                        <input type="url" class="form-control" id="oc-facebook" name="oc_facebook" placeholder="https://facebook.com/...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-twitter mr-1"></i> Twitter / X</label>
                                        <input type="url" class="form-control" id="oc-twitter" name="oc_twitter" placeholder="https://twitter.com/...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-instagram mr-1"></i> Instagram</label>
                                        <input type="url" class="form-control" id="oc-instagram" name="oc_instagram" placeholder="https://instagram.com/...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-linkedin mr-1"></i> LinkedIn</label>
                                        <input type="url" class="form-control" id="oc-linkedin" name="oc_linkedin" placeholder="https://linkedin.com/...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== CERTIFICATES TAB ==================== -->
                        <?php if ($certificates_enabled): ?>
                        <div class="tab-pane fade" id="certificates-info" role="tabpanel">
                            <h5 class="mb-3">Certificate Settings</h5>
                            <p class="text-muted small mb-4">Configure certificate issuance for this event. Attendees who complete this event can receive a certificate.</p>

                            <!-- Enable Certificates Toggle -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><i class="fa fa-certificate text-primary mr-2"></i> Enable Certificates</h6>
                                            <p class="text-muted small mb-0">Allow attendees to receive certificates for this event</p>
                                        </div>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="enable-certificates" name="enable_certificates" value="1">
                                            <label class="custom-control-label" for="enable-certificates"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Certificate Options (shown when enabled) -->
                            <div id="certificate-options" style="display: none;">
                                <!-- Template Selection -->
                                <div class="form-group">
                                    <label for="certificate-template"><i class="fa fa-file-text-o mr-1"></i> Certificate Template</label>
                                    <?php if (!empty($certificate_templates)): ?>
                                    <select class="form-control" id="certificate-template" name="certificate_template_id">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($certificate_templates as $template): ?>
                                        <option value="<?php echo esc_attr($template->id); ?>">
                                            <?php echo esc_html($template->name); ?>
                                            <?php if (!empty($template->description)): ?>
                                                - <?php echo esc_html(wp_trim_words($template->description, 10)); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Choose the certificate design for this event</small>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-exclamation-triangle mr-2"></i>
                                        No certificate templates found.
                                        <a href="<?php echo home_url('/event-manager-dashboard/certificate-templates'); ?>" class="alert-link">Create a template first</a>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Auto Issue Option -->
                                <div class="form-group">
                                    <label><i class="fa fa-magic mr-1"></i> Issuance Method</label>
                                    <div class="card">
                                        <div class="card-body p-3">
                                            <div class="custom-control custom-radio mb-2">
                                                <input type="radio" id="cert-issue-auto" name="certificate_issue_method" value="auto" class="custom-control-input" checked>
                                                <label class="custom-control-label" for="cert-issue-auto">
                                                    <strong>Automatic</strong>
                                                    <p class="text-muted small mb-0">Issue certificates automatically when attendee checks in</p>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="cert-issue-manual" name="certificate_issue_method" value="manual" class="custom-control-input">
                                                <label class="custom-control-label" for="cert-issue-manual">
                                                    <strong>Manual</strong>
                                                    <p class="text-muted small mb-0">Manually issue certificates from the attendees list</p>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Certificate Requirements -->
                                <div class="form-group">
                                    <label><i class="fa fa-check-square-o mr-1"></i> Requirements (Optional)</label>
                                    <div class="card">
                                        <div class="card-body p-3">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="cert-require-checkin" name="certificate_require_checkin" value="1" checked>
                                                <label class="custom-control-label" for="cert-require-checkin">
                                                    Require check-in to receive certificate
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="cert-require-checkout" name="certificate_require_checkout" value="1">
                                                <label class="custom-control-label" for="cert-require-checkout">
                                                    Require check-out (event completion) to receive certificate
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="cert-require-event-ended" name="certificate_require_event_ended" value="1">
                                                <label class="custom-control-label" for="cert-require-event-ended">
                                                    <i class="fa fa-calendar-check-o mr-1"></i> Only show certificate after event has ended
                                                </label>
                                                <small class="form-text text-muted ml-4">Certificate will not be visible to attendees until the event end date has passed</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Info -->
                                <?php if (!empty($certificate_templates)): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle mr-2"></i>
                                    <strong>Tip:</strong> You can preview certificate templates from the
                                    <a href="<?php echo home_url('/event-manager-dashboard/certificate-templates'); ?>" target="_blank">Certificate Templates</a> page.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </form>

        <!-- Sticky Bottom Action Bar -->
        <div class="form-sticky-bottom-bar">
            <a href="<?php echo home_url('/event-manager-dashboard/events'); ?>" class="btn btn-outline-secondary">
                <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
            </a>
            <button type="button" id="save-draft-btn" class="btn btn-secondary">
                <i class="fa fa-file-o"></i> <?php echo $t['save_draft']; ?>
            </button>
            <button type="submit" form="event-form" class="btn btn-primary">
                <i class="fa fa-save"></i> <?php echo $t['publish_event']; ?>
            </button>
        </div>
    </div>
</div>

<!-- Icon Picker Modal (still needed) -->
<!-- Event Form Shared Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/dashboard/css/event-form.css">
<!-- Inline styles moved to event-form.css -->


<script>
// Event Create Translations
var eventCreateTranslations = {
    saving: '<?php echo esc_js($t['saving']); ?>',
    event_created: '<?php echo esc_js($t['event_created']); ?>',
    error_creating_event: '<?php echo esc_js($t['error_creating_event']); ?>',
    connection_error: '<?php echo esc_js($t['connection_error']); ?>',
    save_event: '<?php echo esc_js($t['save_event']); ?>',
    confirm_delete_ticket: '<?php echo esc_js($t['confirm_delete_ticket']); ?>',
    ticket_deleted: '<?php echo esc_js($t['ticket_deleted']); ?>',
    // Additional translations
    please_select_date: '<?php echo esc_js(sc_t('events.select_date', 'Please select a date')); ?>',
    day_already_added: '<?php echo esc_js(sc_t('events.day_already_added', 'This day is already added')); ?>',
    enter_ticket_name: '<?php echo esc_js(sc_t('tickets.enter_name', 'Please enter a ticket name')); ?>',
    ticket_updated: '<?php echo esc_js(sc_t('tickets.ticket_updated', 'Ticket updated successfully')); ?>',
    ticket_added: '<?php echo esc_js(sc_t('tickets.ticket_added', 'Ticket added successfully')); ?>',
    one_card_required: '<?php echo esc_js(sc_t('tickets.one_card_required', 'At least one card is required')); ?>',
    enter_event_title: '<?php echo esc_js(sc_t('events.enter_title', 'Please enter an event title')); ?>',
    saved_as_draft: '<?php echo esc_js(sc_t('events.saved_as_draft', 'Event saved as draft')); ?>',
    error_saving_draft: '<?php echo esc_js(sc_t('events.error_saving_draft', 'Error saving draft')); ?>',
    add_event_day: '<?php echo esc_js(sc_t('events.add_event_day', 'Please add at least one event day')); ?>',
    select_start_date: '<?php echo esc_js(sc_t('events.select_start_date', 'Please select a start date')); ?>',
    select_categories: '<?php echo esc_js($t['select_categories']); ?>',
    select_speakers: '<?php echo esc_js($t['select_speakers_placeholder']); ?>',
    select_organizers: '<?php echo esc_js($t['select_organizers_placeholder']); ?>',
    image_slider_section: '<?php echo esc_js($t['image_slider_section']); ?>',
    about_section: '<?php echo esc_js($t['about_section']); ?>',
    card_section: '<?php echo esc_js($t['card_section']); ?>',
    image_grid_section: '<?php echo esc_js($t['image_grid_section']); ?>',
    section_title: '<?php echo esc_js($t['section_title']); ?>',
    section_heading: '<?php echo esc_js(sc_t('dashboard_pages.section_heading', 'Section Heading')); ?>',
    eg_event_gallery: '<?php echo esc_js($t['eg_event_gallery']); ?>',
    images: '<?php echo esc_js($t['images']); ?>',
    choose_images: '<?php echo esc_js($t['choose_images']); ?>',
    eg_about_event: '<?php echo esc_js($t['eg_about_event']); ?>',
    description: '<?php echo esc_js($t['description']); ?>',
    enter_description: '<?php echo esc_js($t['enter_description']); ?>',
    button_text: '<?php echo esc_js($t['button_text']); ?>',
    eg_learn_more: '<?php echo esc_js($t['eg_learn_more']); ?>',
    button_url: '<?php echo esc_js($t['button_url']); ?>',
    main_image: '<?php echo esc_js($t['main_image']); ?>',
    choose_image: '<?php echo esc_js($t['choose_image']); ?>',
    eg_why_attend: '<?php echo esc_js($t['eg_why_attend']); ?>',
    add_card: '<?php echo esc_js($t['add_card']); ?>',
    icon: '<?php echo esc_js($t['icon']); ?>',
    card_title: '<?php echo esc_js($t['card_title']); ?>',
    card_description: '<?php echo esc_js($t['card_description']); ?>',
    eg_sponsors: '<?php echo esc_js($t['eg_sponsors']); ?>'
};

jQuery(document).ready(function($) {
    var tickets = [];
    var specificDays = [];
    var currentIconTarget = null;

    // FontAwesome icons for icon picker
    // Social media icons first, then common icons for cards
    var faIcons = [
        // Social Media
        'fa-facebook', 'fa-twitter', 'fa-instagram', 'fa-linkedin', 'fa-youtube', 'fa-whatsapp',
        'fa-telegram', 'fa-tiktok', 'fa-pinterest', 'fa-snapchat', 'fa-reddit', 'fa-discord',
        'fa-slack', 'fa-skype', 'fa-github', 'fa-dribbble', 'fa-behance', 'fa-vimeo',
        'fa-soundcloud', 'fa-spotify', 'fa-twitch', 'fa-medium',
        // Common/General
        'fa-globe', 'fa-link', 'fa-envelope', 'fa-phone', 'fa-map-marker', 'fa-home',
        'fa-building', 'fa-star', 'fa-heart', 'fa-thumbs-up', 'fa-share', 'fa-comment',
        'fa-bell', 'fa-calendar', 'fa-clock-o', 'fa-user', 'fa-users', 'fa-check',
        'fa-check-circle', 'fa-info-circle', 'fa-question-circle', 'fa-lightbulb-o',
        // Event/Activity
        'fa-camera', 'fa-video-camera', 'fa-microphone', 'fa-music', 'fa-film', 'fa-gamepad',
        'fa-trophy', 'fa-gift', 'fa-ticket', 'fa-tags',
        // Business
        'fa-shopping-cart', 'fa-credit-card', 'fa-money', 'fa-briefcase', 'fa-handshake-o',
        'fa-line-chart', 'fa-bar-chart', 'fa-pie-chart',
        // Travel
        'fa-plane', 'fa-car', 'fa-ship', 'fa-subway', 'fa-bicycle', 'fa-motorcycle',
        // Food/Drink
        'fa-coffee', 'fa-cutlery', 'fa-glass', 'fa-beer', 'fa-birthday-cake',
        // Education
        'fa-graduation-cap', 'fa-book', 'fa-newspaper-o', 'fa-pencil', 'fa-paint-brush',
        // Files/Tech
        'fa-image', 'fa-file', 'fa-folder', 'fa-archive', 'fa-download', 'fa-upload',
        'fa-cloud', 'fa-database', 'fa-server', 'fa-code', 'fa-terminal',
        // Security
        'fa-shield', 'fa-lock', 'fa-key', 'fa-eye', 'fa-eye-slash',
        // Tools
        'fa-cog', 'fa-wrench', 'fa-magic', 'fa-bolt', 'fa-fire', 'fa-leaf',
        // Nature/Weather
        'fa-sun-o', 'fa-moon-o', 'fa-umbrella', 'fa-tree', 'fa-paw',
        // Misc
        'fa-anchor', 'fa-flag', 'fa-rocket', 'fa-diamond', 'fa-cube', 'fa-cubes',
        // Health
        'fa-medkit', 'fa-stethoscope', 'fa-ambulance', 'fa-hospital-o', 'fa-heartbeat', 'fa-wheelchair',
        // Arrows/Navigation
        'fa-arrow-right', 'fa-arrow-left', 'fa-arrow-up', 'fa-arrow-down',
        'fa-chevron-right', 'fa-chevron-left', 'fa-chevron-up', 'fa-chevron-down',
        // Platform
        'fa-apple', 'fa-android', 'fa-windows', 'fa-linux'
    ];

    // Initialize icon picker grid
    function initIconPicker() {
        var grid = $('#icon-grid');
        grid.empty();
        faIcons.forEach(function(icon) {
            grid.append('<div class="icon-item" data-icon="' + icon + '"><i class="fa ' + icon + '"></i></div>');
        });
    }
    initIconPicker();

    // NOTE: Select2 initialization is done AFTER footer loads (see script at bottom of page)

    // Initialize Flatpickr for date inputs
    // Helper: close all other Flatpickr instances when one opens
    function closeOtherFlatpickrs(currentInstance) {
        document.querySelectorAll('.flatpickr-input').forEach(function(el) {
            if (el._flatpickr && el._flatpickr !== currentInstance) {
                el._flatpickr.close();
            }
        });
    }

    function initFlatpickr() {
        if (typeof flatpickr !== 'undefined') {
            var fpOnOpen = function(selectedDates, dateStr, instance) { closeOtherFlatpickrs(instance); };

            // Start Date
            if ($('#event-start-date').length && !$('#event-start-date').hasClass('flatpickr-input')) {
                flatpickr('#event-start-date', {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'F j, Y',
                    allowInput: true,
                    onOpen: fpOnOpen
                });
            }

            // End Date
            if ($('#event-end-date').length && !$('#event-end-date').hasClass('flatpickr-input')) {
                flatpickr('#event-end-date', {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'F j, Y',
                    allowInput: true,
                    onOpen: fpOnOpen
                });
            }

            // Specific day picker
            if ($('#add-specific-day').length && !$('#add-specific-day').hasClass('flatpickr-input')) {
                flatpickr('#add-specific-day', {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'F j, Y',
                    allowInput: true,
                    onOpen: fpOnOpen
                });
            }

            // NOTE: Ticket sale date pickers are initialized in showTicketForm()
            // to avoid creating calendar DOM elements for hidden inputs

            // ============ TIME PICKERS ============
            // Start Time
            if ($('#event-start-time').length && !$('#event-start-time').hasClass('flatpickr-input')) {
                flatpickr('#event-start-time', {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: 'H:i',
                    altInput: true,
                    altFormat: 'h:i K',
                    time_24hr: false,
                    allowInput: true,
                    minuteIncrement: 5,
                    onOpen: fpOnOpen
                });
            }

            // End Time
            if ($('#event-end-time').length && !$('#event-end-time').hasClass('flatpickr-input')) {
                flatpickr('#event-end-time', {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: 'H:i',
                    altInput: true,
                    altFormat: 'h:i K',
                    time_24hr: false,
                    allowInput: true,
                    minuteIncrement: 5,
                    onOpen: fpOnOpen
                });
            }

            console.log('Flatpickr initialized for date and time fields');
        } else {
            // Retry after 100ms if Flatpickr not loaded yet
            setTimeout(initFlatpickr, 100);
        }
    }

    // ================= SLUG GENERATION =================
    // (Placed BEFORE Flatpickr init to ensure it always registers)
    // Explicitly initialize slug edit state to false
    $('#event-slug').data('edited', false);

    function generateSlug(title) {
        return title
            .trim()
            .toLowerCase()
            .replace(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]+/g, function(match) {
                return match; // Keep Arabic characters
            })
            .replace(/[^\u0600-\u06FF\u0750-\u077Fa-z0-9\s-]/g, '') // Keep Arabic, Latin, numbers, spaces, hyphens
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, ''); // Trim leading/trailing hyphens
    }

    // Use event delegation for robustness, bind to multiple events
    $(document).on('input keyup change', '#event-title', function() {
        if (!$('#event-slug').data('edited')) {
            var slug = generateSlug($(this).val());
            $('#event-slug').val(slug);
        }
    });

    // Also bind directly as fallback
    $('#event-title').on('input', function() {
        if (!$('#event-slug').data('edited')) {
            var slug = generateSlug($(this).val());
            $('#event-slug').val(slug);
        }
    });

    $('#edit-slug-btn').on('click', function() {
        $('#event-slug').prop('readonly', false).focus().data('edited', true);
        $(this).hide();
        $('#confirm-slug-btn').show();
    });

    $('#confirm-slug-btn').on('click', function() {
        $('#event-slug').prop('readonly', true).data('edited', false);
        $(this).hide();
        $('#edit-slug-btn').show();
    });

    // Try immediately, if not loaded, will retry
    try { initFlatpickr(); } catch(e) { console.warn('Flatpickr init error:', e); }

    // ================= DATE SELECTION MODE =================
    $('input[name="date_selection_mode"]').on('change', function() {
        var mode = $(this).val();
        if (mode === 'specific') {
            $('#date-range-fields').hide();
            $('#specific-days-fields').show();
        } else {
            $('#date-range-fields').show();
            $('#specific-days-fields').hide();
        }
        // (schedule day options no longer needed)
    });

    // Add specific day
    $('#add-specific-day-btn').on('click', function() {
        var day = $('#add-specific-day').val();
        if (!day) {
            toastr.warning(eventCreateTranslations.please_select_date);
            return;
        }
        if (specificDays.includes(day)) {
            toastr.warning(eventCreateTranslations.day_already_added);
            return;
        }
        specificDays.push(day);
        specificDays.sort();
        renderSpecificDays();
        // (schedule day options no longer needed)
        $('#add-specific-day').val('');
    });

    function renderSpecificDays() {
        var container = $('#specific-days-list');
        container.empty();
        specificDays.forEach(function(day, index) {
            var formattedDate = new Date(day).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            container.append('<span class="specific-day-badge" data-index="' + index + '">' + formattedDate + ' <i class="fa fa-times"></i></span>');
        });
        $('#specific-days-data').val(JSON.stringify(specificDays));
    }

    $(document).on('click', '.specific-day-badge', function() {
        var index = $(this).data('index');
        specificDays.splice(index, 1);
        renderSpecificDays();
        // (schedule day options no longer needed)
    });

    // ================= LOCATION TYPE TOGGLE =================
    $('input[name="location_type"]').on('change', function() {
        var type = $(this).val();
        if (type === 'online') {
            $('#offline-event-fields').hide();
            $('#online-event-fields').show();
        } else {
            $('#offline-event-fields').show();
            $('#online-event-fields').hide();
        }
    });

    // ================= FILE INPUT LABELS =================
    $(document).on('change', '.custom-file-input', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file...');
    });

    // ================= LOGO PREVIEW =================
    $('#brand-logo').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-preview img').attr('src', e.target.result);
                $('#logo-preview').show();
            };
            reader.readAsDataURL(file);
        }
    });

    $('#remove-logo-btn').on('click', function() {
        $('#brand-logo').val('');
        $('#logo-preview').hide();
        $('#brand-logo').siblings('.custom-file-label').html('Choose file...');
    });

    $('#event-banner').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#banner-preview img').attr('src', e.target.result);
                $('#banner-preview').show();
            };
            reader.readAsDataURL(file);
        }
    });

    $('#remove-banner-btn').on('click', function() {
        $('#event-banner').val('');
        $('#banner-preview').hide();
        $('#event-banner').siblings('.custom-file-label').html('Choose banner...');
    });

    // Handle schedules PDF file upload preview
    $('#event-schedules-file').on('change', function() {
        var file = this.files[0];
        if (file) {
            $('#schedules-file-link').text(file.name);
            $('#schedules-file-preview').show();
            // Remove the remove_schedules_file flag if it exists
            $('input[name="remove_schedules_file"]').remove();
        }
    });

    // Handle remove schedules file
    $('#remove-schedules-file-btn').on('click', function() {
        $('#event-schedules-file').val('');
        $('.custom-file-label[for="event-schedules-file"]').text('Choose PDF file...');
        $('#schedules-file-preview').hide();
        // Mark for removal on save
        $('input[name="remove_schedules_file"]').remove();
        $('#event-form').append('<input type="hidden" name="remove_schedules_file" value="1">');
    });

    // ================= SOCIAL LINKS WITH SELECT2 ICON DROPDOWN =================
    // Generate icon options for select with empty placeholder
    function getIconSelectOptions(withPlaceholder) {
        var options = withPlaceholder ? '<option value=""></option>' : '';
        faIcons.forEach(function(icon) {
            var label = icon.replace('fa-', '').replace(/-/g, ' ');
            label = label.charAt(0).toUpperCase() + label.slice(1);
            options += '<option value="' + icon + '">' + label + '</option>';
        });
        return options;
    }

    // NOTE: Social Links handlers moved to AFTER footer (see script at bottom)

    // ================= FAQ WITH UP/DOWN REORDER =================
    $('#add-faq-btn').on('click', function() {
        addFaqItem();
    });

    function addFaqItem(question, answer) {
        var index = $('#faq-list .faq-item').length;
        var html = `
            <div class="faq-item" data-index="${index}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Question ${index + 1}</strong>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary faq-reorder-btn faq-move-up" title="Move Up"><i class="fa fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary faq-reorder-btn faq-move-down" title="Move Down"><i class="fa fa-arrow-down"></i></button>
                        <button type="button" class="btn btn-sm btn-danger remove-faq"><i class="fa fa-trash"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control faq-question" placeholder="Enter question" value="${question || ''}">
                </div>
                <div class="form-group mb-0">
                    <textarea class="form-control faq-answer" rows="2" placeholder="Enter answer">${answer || ''}</textarea>
                </div>
            </div>
        `;
        $('#faq-list').append(html);
        reindexFaqItems();
    }

    function reindexFaqItems() {
        $('#faq-list .faq-item').each(function(i) {
            $(this).attr('data-index', i);
            $(this).find('strong').first().text('Question ' + (i + 1));
        });
    }

    $(document).on('click', '.faq-move-up', function() {
        var item = $(this).closest('.faq-item');
        var prev = item.prev('.faq-item');
        if (prev.length) {
            item.insertBefore(prev);
            reindexFaqItems();
        }
    });

    $(document).on('click', '.faq-move-down', function() {
        var item = $(this).closest('.faq-item');
        var next = item.next('.faq-item');
        if (next.length) {
            item.insertAfter(next);
            reindexFaqItems();
        }
    });

    $(document).on('click', '.remove-faq', function() {
        $(this).closest('.faq-item').remove();
        reindexFaqItems();
    });

    // (Schedules are now managed from the Schedules page)

    // ================= EXTRA FIELDS =================
    $('#add-extra-field-btn').on('click', function() {
        var index = $('#extra-fields-list .extra-field-item').length;
        var html = `
            <div class="extra-field-item" data-index="${index}">
                <div class="d-flex justify-content-between mb-2">
                    <strong>Field ${index + 1}</strong>
                    <button type="button" class="btn btn-sm btn-danger remove-extra-field"><i class="fa fa-trash"></i></button>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Field Label</label>
                            <input type="text" class="form-control field-label" placeholder="e.g., Company Name">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Field Type</label>
                            <select class="form-control field-type">
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="number">Number</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Dropdown</option>
                                <option value="checkbox">Checkbox</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input field-required" id="field-req-${index}">
                                <label class="custom-control-label" for="field-req-${index}">Required</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group field-options-group mb-0" style="display: none;">
                    <label>Options (one per line)</label>
                    <textarea class="form-control field-options" rows="2" placeholder="Option 1\nOption 2"></textarea>
                </div>
            </div>
        `;
        $('#extra-fields-list').append(html);
    });

    $(document).on('change', '.field-type', function() {
        var $item = $(this).closest('.extra-field-item');
        $item.find('.field-options-group').toggle($(this).val() === 'select');
    });

    $(document).on('click', '.remove-extra-field', function() {
        $(this).closest('.extra-field-item').remove();
    });

    // ================= TICKETS (Inline Form - No Modal) =================
    function resetTicketForm() {
        $('#ticket-id').val('');
        $('#ticket-index').val('-1');
        $('#ticket-form-title').html('<i class="fa fa-ticket"></i> Add New Ticket');
        $('#ticket-name').val('');
        $('#ticket-price').val('');
        $('#ticket-description').val('');
        $('#ticket-qty').val('');
        $('#ticket-min-qty').val('1');
        $('#ticket-max-qty').val('10');
        $('#ticket-sale-start').val('');
        $('#ticket-sale-end').val('');
        $('#ticket-status').prop('checked', true);
        $('#ticket-use-coupons').prop('checked', false);
        $('#ticket-type').val('general');
        $('#ticket-price-group').show();
    }

    function showTicketForm() {
        $('#ticket-form-card').slideDown(300, function() {
            // Initialize flatpickr on ticket date fields AFTER form is visible
            if (typeof flatpickr !== 'undefined') {
                var fpOnOpen = function(s,d,inst) { closeOtherFlatpickrs(inst); };
                if ($('#ticket-sale-start').length && !$('#ticket-sale-start').hasClass('flatpickr-input')) {
                    flatpickr('#ticket-sale-start', {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'F j, Y',
                        allowInput: true,
                        onOpen: fpOnOpen
                    });
                }
                if ($('#ticket-sale-end').length && !$('#ticket-sale-end').hasClass('flatpickr-input')) {
                    flatpickr('#ticket-sale-end', {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'F j, Y',
                        allowInput: true,
                        onOpen: fpOnOpen
                    });
                }
            }
        });
        // Scroll to form
        setTimeout(function() {
            $('html, body').animate({
                scrollTop: $('#ticket-form-card').offset().top - 100
            }, 300);
        }, 100);
    }

    function hideTicketForm() {
        $('#ticket-form-card').slideUp(300);
        resetTicketForm();
    }

    $('#add-ticket-card').on('click', function() {
        // If form is already visible, just scroll to it
        if ($('#ticket-form-card').is(':visible')) {
            resetTicketForm();
            $('html, body').animate({
                scrollTop: $('#ticket-form-card').offset().top - 100
            }, 300);
            $('#ticket-name').focus();
        } else {
            resetTicketForm();
            showTicketForm();
        }
    });

    $('#cancel-ticket-btn, #cancel-ticket-btn-2').on('click', function() {
        hideTicketForm();
    });

    $('#ticket-use-coupons').on('change', function() {
        if ($(this).is(':checked')) {
            $('#ticket-price-group').hide();
            $('#ticket-price').val(0);
        } else {
            $('#ticket-price-group').show();
        }
    });

    $('#save-ticket-inline-btn').on('click', function() {
        var ticketData = {
            name: $('#ticket-name').val(),
            price: parseFloat($('#ticket-price').val()) || 0,
            description: $('#ticket-description').val(),
            quantity: parseInt($('#ticket-qty').val()) || 100,
            min_per_order: parseInt($('#ticket-min-qty').val()) || 1,
            max_per_order: parseInt($('#ticket-max-qty').val()) || 10,
            sale_start: $('#ticket-sale-start').val(),
            sale_end: $('#ticket-sale-end').val(),
            use_coupons_only: $('#ticket-use-coupons').is(':checked'),
            is_active: $('#ticket-status').is(':checked'),
            ticket_type: $('#ticket-type').val() || 'general'
        };

        if (!ticketData.name) {
            toastr.error(eventCreateTranslations.enter_ticket_name);
            $('#ticket-name').focus();
            return;
        }

        var index = parseInt($('#ticket-index').val());
        if (index >= 0) {
            tickets[index] = ticketData;
            toastr.success(eventCreateTranslations.ticket_updated);
        } else {
            tickets.push(ticketData);
            toastr.success(eventCreateTranslations.ticket_added);
        }

        renderTickets();
        hideTicketForm();
    });

    function renderTickets() {
        var container = $('#tickets-list');
        container.empty();

        tickets.forEach(function(ticket, index) {
            var priceText = ticket.use_coupons_only ? 'Coupon Only' : (ticket.price > 0 ? ticket.price + ' <?php echo esc_js($currency); ?>' : 'Free');
            var statusBadge = ticket.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
            var html = `
                <div class="col-md-4">
                    <div class="card ticket-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>${escapeHtml(ticket.name)}</strong>
                            ${statusBadge}
                        </div>
                        <div class="card-body">
                            <h4 class="text-primary mb-2">${priceText}</h4>
                            <p class="text-muted small mb-2">${escapeHtml(ticket.description || 'No description')}</p>
                            <div class="text-muted small">
                                <i class="fa fa-ticket"></i> Qty: ${ticket.quantity}
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-sm btn-primary edit-ticket" data-index="${index}"><i class="fa fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-danger delete-ticket" data-index="${index}"><i class="fa fa-trash"></i> Delete</button>
                        </div>
                    </div>
                </div>
            `;
            container.append(html);
        });

        $('#tickets-data').val(JSON.stringify(tickets));
    }

    $(document).on('click', '.edit-ticket', function() {
        var index = $(this).data('index');
        var ticket = tickets[index];
        $('#ticket-index').val(index);
        $('#ticket-form-title').html('<i class="fa fa-ticket"></i> Edit Ticket');
        $('#ticket-name').val(ticket.name);
        $('#ticket-price').val(ticket.price);
        $('#ticket-description').val(ticket.description);
        $('#ticket-qty').val(ticket.quantity);
        $('#ticket-min-qty').val(ticket.min_per_order);
        $('#ticket-max-qty').val(ticket.max_per_order);

        // Set Flatpickr dates properly
        var saleStartPicker = document.getElementById('ticket-sale-start')._flatpickr;
        var saleEndPicker = document.getElementById('ticket-sale-end')._flatpickr;
        if (saleStartPicker) {
            saleStartPicker.setDate(ticket.sale_start ? ticket.sale_start.split(' ')[0] : '', false);
        }
        if (saleEndPicker) {
            saleEndPicker.setDate(ticket.sale_end ? ticket.sale_end.split(' ')[0] : '', false);
        }

        $('#ticket-use-coupons').prop('checked', ticket.use_coupons_only);
        $('#ticket-type').val(ticket.ticket_type || 'general');
        $('#ticket-status').prop('checked', ticket.is_active);
        $('#ticket-price-group').toggle(!ticket.use_coupons_only);
        showTicketForm();
    });

    $(document).on('click', '.delete-ticket', function() {
        var index = $(this).data('index');
        if (confirm(eventCreateTranslations.confirm_delete_ticket)) {
            tickets.splice(index, 1);
            renderTickets();
            toastr.success(eventCreateTranslations.ticket_deleted);
        }
    });

    // ================= ADDITIONAL SECTIONS (Inline - No Modal) =================
    $('.section-type-btn').on('click', function() {
        var sectionType = $(this).data('section-type');
        addSection(sectionType);

        // Scroll to the new section
        setTimeout(function() {
            var $lastSection = $('#additional-sections-list .section-item').last();
            if ($lastSection.length) {
                $('html, body').animate({
                    scrollTop: $lastSection.offset().top - 100
                }, 300);
            }
        }, 100);
    });

    function addSection(type) {
        var index = $('#additional-sections-list .section-item').length;
        var html = '';

        if (type === 'image_slider') {
            html = `
                <div class="section-item" data-index="${index}" data-type="image_slider">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><i class="fa fa-picture-o"></i> Image Slider Section</strong>
                        <button type="button" class="btn btn-sm btn-danger remove-section"><i class="fa fa-trash"></i></button>
                    </div>
                    <div class="form-group">
                        <label>Section Title</label>
                        <input type="text" class="form-control section-title" placeholder="e.g., Event Gallery">
                    </div>
                    <div class="form-group mb-0">
                        <label>Images</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input section-images" multiple accept="image/*">
                            <label class="custom-file-label">Choose images...</label>
                        </div>
                        <div class="section-images-preview mt-2 d-flex flex-wrap"></div>
                    </div>
                </div>
            `;
        } else if (type === 'about') {
            html = `
                <div class="section-item" data-index="${index}" data-type="about">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><i class="fa fa-info-circle"></i> About Section</strong>
                        <button type="button" class="btn btn-sm btn-danger remove-section"><i class="fa fa-trash"></i></button>
                    </div>
                    <div class="form-group">
                        <label>Section Heading</label>
                        <input type="text" class="form-control section-title" placeholder="e.g., About This Event">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control section-content" rows="4" placeholder="Enter description paragraphs"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Button Text</label>
                                <input type="text" class="form-control section-btn-text" placeholder="e.g., Learn More">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Button URL</label>
                                <input type="url" class="form-control section-btn-url" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Main Image</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input section-images" accept="image/*">
                            <label class="custom-file-label">Choose image...</label>
                        </div>
                        <div class="section-images-preview mt-2"></div>
                    </div>
                </div>
            `;
        } else if (type === 'card') {
            html = `
                <div class="section-item" data-index="${index}" data-type="card">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><i class="fa fa-th"></i> Card Section</strong>
                        <button type="button" class="btn btn-sm btn-danger remove-section"><i class="fa fa-trash"></i></button>
                    </div>
                    <div class="form-group">
                        <label>Section Heading</label>
                        <input type="text" class="form-control section-title" placeholder="e.g., Why Attend?">
                    </div>
                    <div class="cards-container">
                        ${generateCardInputs(0)}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary add-card-btn mt-2">
                        <i class="fa fa-plus"></i> Add Card
                    </button>
                </div>
            `;

            // Initialize Select2 for the card icon after adding
            setTimeout(function() {
                var $newSection = $('#additional-sections-list .section-item').last();
                $newSection.find('.card-icon-select').each(function() {
                    if (typeof window.initIconSelect2 === 'function') {
                        window.initIconSelect2($(this), {width: '100%'});
                    }
                });
            }, 100);
        } else if (type === 'image_grid') {
            html = `
                <div class="section-item" data-index="${index}" data-type="image_grid">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><i class="fa fa-th-large"></i> Image Grid Section</strong>
                        <button type="button" class="btn btn-sm btn-danger remove-section"><i class="fa fa-trash"></i></button>
                    </div>
                    <div class="form-group">
                        <label>Section Title</label>
                        <input type="text" class="form-control section-title" placeholder="e.g., Our Sponsors">
                    </div>
                    <div class="form-group mb-0">
                        <label>Images</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input section-images" multiple accept="image/*">
                            <label class="custom-file-label">Choose images...</label>
                        </div>
                        <div class="section-images-preview mt-2 d-flex flex-wrap"></div>
                    </div>
                </div>
            `;
        }

        $('#additional-sections-list').append(html);
    }

    function generateCardInputs(cardIndex) {
        var iconOptions = getIconSelectOptions(false);
        return `
            <div class="card-item mb-3 p-3 bg-white border rounded">
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-card-btn">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-5">
                        <label>Icon</label>
                        <select class="form-control card-icon-select card-icon">
                            ${iconOptions}
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label>Card Title</label>
                        <input type="text" class="form-control card-title" placeholder="Card title">
                    </div>
                </div>
                <div class="form-group mt-2 mb-0">
                    <label>Card Description</label>
                    <textarea class="form-control card-desc" rows="2" placeholder="Card description"></textarea>
                </div>
            </div>
        `;
    }

    // Add card button handler
    $(document).on('click', '.add-card-btn', function() {
        var $section = $(this).closest('.section-item');
        var $container = $section.find('.cards-container');
        var cardIndex = $container.find('.card-item').length;
        var newCard = generateCardInputs(cardIndex);
        $container.append(newCard);

        // Initialize Select2 for the new card's icon select
        var $newSelect = $container.find('.card-item').last().find('.card-icon-select');
        if (typeof window.initIconSelect2 === 'function') {
            window.initIconSelect2($newSelect, {width: '100%'});
        }
    });

    // Remove card button handler
    $(document).on('click', '.remove-card-btn', function() {
        var $cardItem = $(this).closest('.card-item');
        var $container = $cardItem.closest('.cards-container');

        // Don't allow removing if it's the last card
        if ($container.find('.card-item').length > 1) {
            $cardItem.fadeOut(200, function() {
                $(this).remove();
            });
        } else {
            toastr.warning(eventCreateTranslations.one_card_required);
        }
    });

    $(document).on('click', '.remove-section', function() {
        $(this).closest('.section-item').remove();
    });

    // ================= CERTIFICATES TOGGLE =================
    $('#enable-certificates').on('change', function() {
        if ($(this).is(':checked')) {
            $('#certificate-options').slideDown(300);
        } else {
            $('#certificate-options').slideUp(300);
        }
    });

    // ================= SECTION IMAGES PREVIEW WITH DRAG & DROP (using SortableJS) =================
    var sectionSortables = {};

    // Make function global so it can be called from other script blocks
    window.initSortableForPreview = function($preview) {
        var previewEl = $preview[0];
        if (!previewEl) {
            console.log('Preview element not found');
            return;
        }

        // Check if SortableJS is loaded
        if (typeof Sortable === 'undefined') {
            console.log('SortableJS not loaded yet, retrying in 100ms...');
            setTimeout(function() { window.initSortableForPreview($preview); }, 100);
            return;
        }

        // Don't re-initialize if already done
        if ($preview.data('sortable-initialized')) {
            return;
        }

        // Give element a unique ID if it doesn't have one
        if (!previewEl.id) {
            previewEl.id = 'sortable-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }

        sectionSortables[previewEl.id] = new Sortable(previewEl, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            draggable: '.section-image-preview',
            forceFallback: true, // Force fallback for better cross-browser support
            fallbackClass: 'sortable-fallback',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onStart: function(evt) {
                console.log('Drag started');
                document.body.style.cursor = 'grabbing';
            },
            onEnd: function(evt) {
                console.log('Image reordered:', evt.oldIndex, '->', evt.newIndex);
                document.body.style.cursor = '';
            }
        });
        $preview.data('sortable-initialized', true);
        console.log('SortableJS initialized for:', previewEl.id);
    }

    $(document).on('change', '.section-images', function() {
        var $input = $(this);
        var $preview = $input.closest('.form-group').find('.section-images-preview');
        var files = this.files;

        for (var i = 0; i < files.length; i++) {
            (function(file, index) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var uniqueId = Date.now() + '-' + index;
                    var html = `
                        <div class="section-image-preview" data-id="${uniqueId}" style="cursor: grab;">
                            <img src="${e.target.result}" alt="Preview" draggable="false">
                            <button type="button" class="remove-img-btn" data-id="${uniqueId}"><i class="fa fa-times"></i></button>
                            <span class="drag-hint"><i class="fa fa-arrows"></i></span>
                        </div>
                    `;
                    $preview.append(html);

                    // Initialize SortableJS after adding images
                    window.initSortableForPreview($preview);
                };
                reader.readAsDataURL(file);
            })(files[i], i);
        }
    });

    // Remove individual image from section preview
    $(document).on('click', '.remove-img-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.section-image-preview').fadeOut(200, function() {
            $(this).remove();
        });
    });

    // ================= SAVE AS DRAFT =================
    $('#save-draft-btn, #save-draft-btn-top').on('click', function() {
        // Set status to draft
        $('#event-status').val('draft');

        // Validate title only for draft
        if (!$('#event-title').val().trim()) {
            toastr.error(eventCreateTranslations.enter_event_title);
            $('a[href="#basic-info"]').tab('show');
            $('#event-title').focus();
            return;
        }

        // Collect all data
        collectFormData();

        
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var formData = new FormData($('#event-form')[0]);
        formData.set('status', 'draft');

        // Ensure Select2 values are captured
        var sponsorVals = $('#event-sponsors').val();
        formData.delete('sponsors[]');
        if (sponsorVals && sponsorVals.length) {
            sponsorVals.forEach(function(v) { formData.append('sponsors[]', v); });
        }
        var partnerVals = $('#event-partners').val();
        formData.delete('partners[]');
        if (partnerVals && partnerVals.length) {
            partnerVals.forEach(function(v) { formData.append('partners[]', v); });
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(eventCreateTranslations.saved_as_draft);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/events'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || eventCreateTranslations.error_saving_draft);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                toastr.error(eventCreateTranslations.connection_error);
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // ================= FORM SUBMISSION =================
    $('#event-form').on('submit', function(e) {
        e.preventDefault();

        // Collect validation errors
        var errors = [];

        // Validate Event Title
        if (!$('#event-title').val().trim()) {
            errors.push({
                message: eventCreateTranslations.enter_event_title,
                tab: '#basic-info',
                field: '#event-title'
            });
        }

        // Validate date based on selection mode
        var dateMode = $('input[name="date_selection_mode"]:checked').val();
        if (dateMode === 'specific') {
            if (specificDays.length === 0) {
                errors.push({
                    message: eventCreateTranslations.add_event_day,
                    tab: '#datetime-info',
                    field: null
                });
            }
        } else {
            if (!$('#event-start-date').val()) {
                errors.push({
                    message: eventCreateTranslations.select_start_date,
                    tab: '#datetime-info',
                    field: '#event-start-date'
                });
            }
        }

        // Validate venue location for offline events
        var eventType = $('input[name="event_type"]:checked').val();
        if (eventType === 'offline' && !$('#event-address').val().trim()) {
            errors.push({
                message: '<?php echo esc_js(sc_t("validation.venue_required", "Please enter venue location")); ?>',
                tab: '#location-info',
                field: '#event-address'
            });
        }

        // Show errors if any
        if (errors.length > 0) {
            // Show all error messages
            errors.forEach(function(error) {
                toastr.error(error.message);
            });
            // Focus on first error
            var firstError = errors[0];
            $('a[href="' + firstError.tab + '"]').tab('show');
            if (firstError.field) {
                $(firstError.field).focus();
            }
            return;
        }

        // Collect all data
        collectFormData();

        // Save editor content
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('event-description')) {
            tinyMCE.get('event-description').save();
        }

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + eventCreateTranslations.saving);

        var formData = new FormData(this);

        // Ensure Select2 values are captured (Select2 v3.x may not sync to underlying select)
        var sponsorVals = $('#event-sponsors').val();
        formData.delete('sponsors[]');
        if (sponsorVals && sponsorVals.length) {
            sponsorVals.forEach(function(v) { formData.append('sponsors[]', v); });
        }
        var partnerVals = $('#event-partners').val();
        formData.delete('partners[]');
        if (partnerVals && partnerVals.length) {
            partnerVals.forEach(function(v) { formData.append('partners[]', v); });
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    toastr.success(eventCreateTranslations.event_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/events'); ?>';
                    }, 1000);
                } else {
                    var errorMsg = eventCreateTranslations.error_creating_event;
                    if (response.data) {
                        errorMsg = typeof response.data === 'object' ? (response.data.message || JSON.stringify(response.data)) : response.data;
                    }
                    console.error('Error:', errorMsg);
                    toastr.error(errorMsg);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + eventCreateTranslations.save_event);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                toastr.error(eventCreateTranslations.connection_error);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + eventCreateTranslations.save_event);
            }
        });
    });

    function collectFormData() {
        // Collect FAQ data
        var faqData = [];
        $('#faq-list .faq-item').each(function() {
            faqData.push({
                question: $(this).find('.faq-question').val(),
                answer: $(this).find('.faq-answer').val()
            });
        });
        $('#faq-data').val(JSON.stringify(faqData));

        // Collect extra fields data
        var extraFieldsData = [];
        $('#extra-fields-list .extra-field-item').each(function() {
            extraFieldsData.push({
                label: $(this).find('.field-label').val(),
                type: $(this).find('.field-type').val(),
                required: $(this).find('.field-required').is(':checked'),
                options: $(this).find('.field-options').val()
            });
        });
        $('#extra-fields-data').val(JSON.stringify(extraFieldsData));

        // Collect sections data
        var sectionsData = [];
        $('#additional-sections-list .section-item').each(function() {
            var type = $(this).data('type');
            var section = {
                type: type,
                title: $(this).find('.section-title').val()
            };

            if (type === 'about') {
                section.content = $(this).find('.section-content').val();
                section.btn_text = $(this).find('.section-btn-text').val();
                section.btn_url = $(this).find('.section-btn-url').val();
            } else if (type === 'card') {
                section.cards = [];
                $(this).find('.card-item').each(function() {
                    // Use 'select.card-icon-select' to get actual select, not Select2 container
                    var iconVal = $(this).find('select.card-icon-select').val() || $(this).find('select.card-icon').val() || '';
                    section.cards.push({
                        icon: iconVal,
                        title: $(this).find('.card-title').val(),
                        description: $(this).find('.card-desc').val()
                    });
                });
            }

            sectionsData.push(section);
        });
        $('#additional-sections-data').val(JSON.stringify(sectionsData));

        // Collect social links with icon
        var socialLinksData = [];
        $('#social-links-container .social-link-item').each(function() {
            // Use 'select.social-icon-select' to avoid Select2 container div
            var iconVal = $(this).find('select.social-icon-select').val();
            var urlVal = $(this).find('.social-url').val();
            if (urlVal) {
                socialLinksData.push({
                    icon: iconVal,
                    url: urlVal
                });
            }
        });
        $('#social-links-data').val(JSON.stringify(socialLinksData));
    }

    // ================= VENUE IMAGE MEDIA LIBRARY =================
    $('#select-venue-image').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: '<?php echo esc_js(sc_t("event_form.select_venue_image", "Select Venue Image")); ?>',
            button: { text: '<?php echo esc_js(sc_t("event_form.use_this_image", "Use this image")); ?>' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#venue-image-id').val(attachment.id);
            $('#venue-image-preview img').attr('src', attachment.url);
            $('#venue-image-preview').show();
        });
        frame.open();
    });
    $('#remove-venue-image').on('click', function() {
        $('#venue-image-id').val('');
        $('#venue-image-preview').hide();
    });

    // Sponsors & Partners are now loaded from PHP directly (like speakers/organizers)
    // Select2 is initialized by initEventSelect2() in footer
});

// ================= MEDIA SELECTOR HELPER =================
function scSelectMedia(hiddenId, previewId, imgId) {
    var frame = wp.media({
        title: '<?php echo esc_js(sc_t("event_form.select_image", "Select Image")); ?>',
        button: { text: '<?php echo esc_js(sc_t("event_form.use_this_image", "Use this image")); ?>' },
        multiple: false
    });
    frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        document.getElementById(hiddenId).value = attachment.id;
        document.getElementById(previewId).value = attachment.filename || attachment.title;
        var img = document.getElementById(imgId);
        if (img) { img.src = attachment.url; img.style.display = 'block'; }
    });
    frame.open();
}
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>

<!-- Initialize Select2 AFTER footer scripts are loaded -->
<script>
(function($) {
    // Select2 is now loaded from footer, initialize immediately
    function initEventSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            console.error('Select2 library not loaded!');
            return;
        }

        // Categories
        if ($('#event-categories').length && !$('#event-categories').data('select2')) {
            $('#event-categories').select2({
                placeholder: eventCreateTranslations.select_categories,
                allowClear: true,
                width: '100%'
            });
        }

        // Speakers
        if ($('#event-speakers').length && !$('#event-speakers').data('select2')) {
            $('#event-speakers').select2({
                placeholder: eventCreateTranslations.select_speakers,
                allowClear: true,
                width: '100%'
            });
        }

        // Organizers
        if ($('#event-organizers').length && !$('#event-organizers').data('select2')) {
            $('#event-organizers').select2({
                placeholder: eventCreateTranslations.select_organizers,
                allowClear: true,
                width: '100%'
            });
        }

        // Sponsors
        if ($('#event-sponsors').length && !$('#event-sponsors').data('select2')) {
            $('#event-sponsors').select2({
                placeholder: '<?php echo esc_js(sc_t("event_form.search_sponsors", "Search sponsors...")); ?>',
                allowClear: true,
                width: '100%'
            });
        }

        // Partners
        if ($('#event-partners').length && !$('#event-partners').data('select2')) {
            $('#event-partners').select2({
                placeholder: '<?php echo esc_js(sc_t("event_form.search_partners", "Search partners...")); ?>',
                allowClear: true,
                width: '100%'
            });
        }
    }

    // Initialize immediately since Select2 is loaded in footer before this script
    initEventSelect2();

    // ========== ICON SELECT2 FUNCTIONS (must be after Select2 loads) ==========
    // Format icon in select2 dropdown (Select2 v3.x)
    window.formatIconOption = function(item, container, query, escapeMarkup) {
        if (!item.id) return item.text;
        var icon = item.id;
        var text = item.text;
        return '<span style="display:inline-flex;align-items:center;"><i class="fa ' + icon + '" style="width:20px;margin-right:10px;color:var(--primary-color);font-size:15px;"></i>' + text + '</span>';
    };

    window.formatIconSelection = function(item, container) {
        if (!item.id) return item.text;
        var icon = item.id;
        var text = item.text;
        return '<span style="display:inline-flex;align-items:center;"><i class="fa ' + icon + '" style="margin-right:8px;color:var(--primary-color);font-size:14px;"></i>' + text + '</span>';
    };

    // Global function to initialize icon Select2
    window.initIconSelect2 = function($select, options) {
        options = options || {};
        if (typeof $.fn.select2 === 'undefined') {
            console.error('Select2 not loaded');
            return;
        }

        // Destroy existing instance if any
        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        $select.select2({
            formatResult: window.formatIconOption,
            formatSelection: window.formatIconSelection,
            escapeMarkup: function(m) { return m; },
            width: options.width || '180px',
            dropdownAutoWidth: true,
            allowClear: false,
            minimumResultsForSearch: 0
        });

        // Force re-render selection after init
        var currentVal = $select.val();
        if (currentVal) {
            $select.select2('val', currentVal);
        }
    };

    // ========== SOCIAL LINKS HANDLERS ==========
    $('#add-social-link-btn').on('click', function() {
        var uniqueId = Date.now();
        // Build icon options
        var faIcons = [
            'fa-facebook', 'fa-twitter', 'fa-instagram', 'fa-linkedin', 'fa-youtube', 'fa-whatsapp',
            'fa-telegram', 'fa-tiktok', 'fa-pinterest', 'fa-snapchat', 'fa-reddit', 'fa-discord',
            'fa-slack', 'fa-skype', 'fa-github', 'fa-dribbble', 'fa-behance', 'fa-vimeo',
            'fa-soundcloud', 'fa-spotify', 'fa-twitch', 'fa-medium', 'fa-globe', 'fa-link',
            'fa-envelope', 'fa-phone', 'fa-map-marker'
        ];
        var iconOptions = '';
        faIcons.forEach(function(icon) {
            var label = icon.replace('fa-', '').replace(/-/g, ' ');
            label = label.charAt(0).toUpperCase() + label.slice(1);
            iconOptions += '<option value="' + icon + '">' + label + '</option>';
        });

        var html = '<div class="social-link-item" data-id="' + uniqueId + '">' +
            '<select class="form-control social-icon-select">' + iconOptions + '</select>' +
            '<input type="text" class="form-control social-url" placeholder="Enter URL (https://...)" style="flex: 1;">' +
            '<button type="button" class="btn btn-danger btn-sm remove-social-link"><i class="fa fa-trash"></i></button>' +
            '</div>';

        $('#social-links-container').append(html);

        // Initialize Select2 for the new icon select
        var $newSelect = $('#social-links-container .social-link-item').last().find('.social-icon-select');
        window.initIconSelect2($newSelect, {width: '180px'});
    });

    $(document).on('click', '.remove-social-link', function() {
        $(this).closest('.social-link-item').remove();
    });

    // ========== CARD SECTION ICON SELECT2 HANDLERS ==========
    // Re-bind card icon initialization for dynamically added cards
    $(document).on('click', '.section-type-btn[data-section-type="card"]', function() {
        // Wait for the card section to be added, then initialize Select2
        setTimeout(function() {
            var $newSection = $('#additional-sections-list .section-item[data-type="card"]').last();
            $newSection.find('.card-icon-select').each(function() {
                if (!$(this).data('select2')) {
                    window.initIconSelect2($(this), {width: '100%'});
                }
            });
        }, 150);
    });

    $(document).on('click', '.add-card-btn', function() {
        var $section = $(this).closest('.section-item');
        // Wait for the card to be added, then initialize Select2
        setTimeout(function() {
            var $newSelect = $section.find('.cards-container .card-item').last().find('.card-icon-select');
            if ($newSelect.length && !$newSelect.data('select2')) {
                window.initIconSelect2($newSelect, {width: '100%'});
            }
        }, 100);
    });

})(jQuery);
</script>
