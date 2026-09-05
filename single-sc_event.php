<?php
/**
 * Single Event Template - Custom Tables Version
 * Public Frontend Event Details Page
 *
 * Refactored to use template parts for better maintainability
 *
 * @package sc_events
 * @version 5.0.0
 */

// Get event from URL slug or post
$event_slug = get_query_var('sc_event'); // Post type query var
$event = null;
$wp_post = null;

// If no query var, try to get from global post
if (empty($event_slug)) {
    global $post;
    if ($post && $post->post_type === 'sc_event') {
        $event_slug = $post->post_name;
        $wp_post = $post;
    }
}

// Also try getting from the queried object
if (empty($event_slug)) {
    $queried_object = get_queried_object();
    if ($queried_object && isset($queried_object->post_type) && $queried_object->post_type === 'sc_event') {
        $event_slug = $queried_object->post_name;
        $wp_post = $queried_object;
    }
}

// Try to get event from Custom Tables first
if (class_exists('SC_Event') && !empty($event_slug)) {
    $event = SC_Event::get_by_slug($event_slug);
}

// If not found in custom tables, try to get from wp_posts and create a compatible object
if (!$event && !empty($event_slug)) {
    if (!$wp_post) {
        $wp_post = get_page_by_path($event_slug, OBJECT, 'sc_event');
    }

    if ($wp_post) {
        // Create a compatible event object from wp_post meta
        $event = (object) array(
            'id' => $wp_post->ID,
            'wp_post_id' => $wp_post->ID,
            'title' => $wp_post->post_title,
            'slug' => $wp_post->post_name,
            'description' => $wp_post->post_content,
            'excerpt' => $wp_post->post_excerpt,
            'featured_image' => get_post_thumbnail_id($wp_post->ID),
            'logo_image' => get_post_meta($wp_post->ID, '_event_logo', true),
            'banner_image' => get_post_meta($wp_post->ID, '_event_banner', true),
            'start_date' => get_post_meta($wp_post->ID, '_event_start_date', true) ?: get_post_meta($wp_post->ID, 'etn_start_date', true),
            'end_date' => get_post_meta($wp_post->ID, '_event_end_date', true) ?: get_post_meta($wp_post->ID, 'etn_end_date', true),
            'start_time' => get_post_meta($wp_post->ID, '_event_start_time', true) ?: get_post_meta($wp_post->ID, 'etn_start_time', true),
            'end_time' => get_post_meta($wp_post->ID, '_event_end_time', true) ?: get_post_meta($wp_post->ID, 'etn_end_time', true),
            'location_type' => get_post_meta($wp_post->ID, '_event_location_type', true) ?: 'offline',
            'venue_name' => get_post_meta($wp_post->ID, '_event_venue_name', true) ?: get_post_meta($wp_post->ID, 'etn_event_location', true),
            'venue_address' => get_post_meta($wp_post->ID, '_event_venue_address', true),
            'venue_city' => get_post_meta($wp_post->ID, '_event_venue_city', true),
            'meeting_link' => get_post_meta($wp_post->ID, '_event_meeting_link', true),
            'calendar_bg_color' => get_post_meta($wp_post->ID, '_event_calendar_bg_color', true),
            'calendar_text_color' => get_post_meta($wp_post->ID, '_event_calendar_text_color', true),
            'schedule' => json_decode(get_post_meta($wp_post->ID, '_event_schedule', true), true) ?: array(),
            'faq' => json_decode(get_post_meta($wp_post->ID, '_event_faq', true), true) ?: array(),
            'additional_sections' => json_decode(get_post_meta($wp_post->ID, '_event_additional_sections', true), true) ?: array(),
            'social_links' => json_decode(get_post_meta($wp_post->ID, '_event_social_links', true), true) ?: array(),
            'status' => $wp_post->post_status,
        );
    }
}

if (!$event) {
    get_template_part('404');
    exit;
}

// Load header
get_template_part('template-parts/public/header', 'public');

$assets_url = get_template_directory_uri() . '/assets/frontend/';
$event_id = $event->id;

// Platform colors (global settings)
$platform_primary = get_option('sc_primary_color', '#FF4B36');
$platform_secondary = get_option('sc_secondary_color', '#1B1E4A');

// Event-specific branding colors
$event_bg_color = $event->calendar_bg_color;
$event_text_color = $event->calendar_text_color;

// Use event colors if set, otherwise fallback to platform colors
$primary_color = !empty($event_bg_color) ? $event_bg_color : $platform_primary;
$secondary_color = !empty($event_text_color) ? $event_text_color : $platform_secondary;

// Get event data
$start_date = $event->start_date;
$end_date = $event->end_date;
$start_time = $event->start_time;
$end_time = $event->end_time;
$location_type = $event->location_type;
$venue_name = $event->venue_name;
$venue_address = $event->venue_address;
$venue_city = $event->venue_city;
$meeting_link = $event->meeting_link;

// Build location string
$location = '';
if (!empty($venue_name)) {
    $location = $venue_name;
} elseif (!empty($venue_address)) {
    $location = $venue_address;
}
if (!empty($venue_city) && !empty($location)) {
    $location .= ', ' . $venue_city;
}

// Get tickets from Custom Tables
$tickets = SC_Ticket::get_by_event($event_id, array('is_active' => null));

// Get FAQ, additional sections from JSON fields
$event_faq = $event->faq;
$additional_sections = $event->additional_sections;
$social_links = $event->social_links;

// Get speakers from custom tables
$event_speakers = array();
if (class_exists('SC_Speaker')) {
    $event_speakers = SC_Speaker::get_by_event($event_id);
}

// Get sponsors from custom tables
$event_sponsors = array();
if (class_exists('SC_Sponsor')) {
    $event_sponsors = SC_Sponsor::get_by_event($event_id);
}

// Get halls used in this event's schedules
global $wpdb;
$sc_prefix = $wpdb->prefix . 'sc_';
$event_halls = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT h.* FROM {$sc_prefix}halls h
     INNER JOIN {$sc_prefix}schedules s ON s.hall_id = h.id
     WHERE s.event_id = %d AND s.is_active = 1 AND h.is_active = 1
     ORDER BY h.sort_order ASC",
    $event_id
));

// Extract gallery images from additional_sections
$gallery_images = array();
$other_sections = array();
if (!empty($additional_sections) && is_array($additional_sections)) {
    foreach ($additional_sections as $section) {
        if (($section['type'] ?? '') === 'image_slider') {
            $gallery_images = array_merge($gallery_images, $section['images'] ?? array());
        } else {
            $other_sections[] = $section;
        }
    }
}

// Get featured image
$event_banner = $event->banner_image;
$event_logo = $event->logo_image ? wp_get_attachment_url($event->logo_image) : '';
if (!$event_logo && $event->featured_image) {
    $event_logo = wp_get_attachment_url($event->featured_image);
}

// Social links are passed as raw array to template

// Calculate ticket price range
$min_price = 0;
$max_price = 0;
$is_free = true;
$has_tickets = !empty($tickets);

if ($has_tickets) {
    foreach ($tickets as $ticket) {
        $price = floatval($ticket->price ?? 0);
        if ($price > 0) {
            $is_free = false;
            if ($min_price == 0 || $price < $min_price) $min_price = $price;
            if ($price > $max_price) $max_price = $price;
        }
    }
}

// Check if event is past (use end_date + end_time for accurate check)
$event_end_date = $end_date ?: $start_date;
$event_end_datetime = $event_end_date;
if ($end_time) {
    $event_end_datetime = $event_end_date . ' ' . $end_time;
}
$is_past = strtotime($event_end_datetime) < time();

// Check if user is registered (active registration only)
$is_registered = false;
if (is_user_logged_in() && class_exists('SC_Attendee')) {
    $user = wp_get_current_user();
    $existing = SC_Attendee::get_by_email_and_event($user->user_email, $event_id);
    $is_registered = !empty($existing);
}

// Prepare template args for reuse
$template_args = array(
    'event'           => $event,
    'event_id'        => $event_id,
    'event_logo'      => $event_logo,
    'event_banner'    => $event_banner,
    'start_date'      => $start_date,
    'end_date'        => $end_date,
    'start_time'      => $start_time,
    'end_time'        => $end_time,
    'location'        => $location,
    'location_type'   => $location_type,
    'google_maps_url' => $event->google_maps_url ?? '',
    'is_free'         => $is_free,
    'min_price'       => $min_price,
    'max_price'       => $max_price,
    'is_past'         => $is_past,
    'is_registered'   => $is_registered,
    'tickets'         => $tickets,
    'has_tickets'     => $has_tickets,
    'event_faq'       => $event_faq,
    'primary_color'   => $primary_color,
    'secondary_color' => $secondary_color,
    'assets_url'      => $assets_url,
    // New sections
    'event_speakers'  => $event_speakers,
    'event_sponsors'  => $event_sponsors,
    'event_halls'     => $event_halls,
    'gallery_images'  => $gallery_images,
    'other_sections'  => $other_sections,
    'schedules_file'  => !empty($event->schedules_file) ? $event->schedules_file : 0,
    // Social links (raw array)
    'social_links'    => $social_links,
    // Organizing company
    'organizing_company' => is_array($event->organizing_company ?? null) ? $event->organizing_company : (is_string($event->organizing_company ?? null) ? (json_decode($event->organizing_company, true) ?: null) : null),
);

// Load Banner Section
get_template_part('template-parts/event/banner', null, $template_args);

// Load Content Section (Description + Tickets Sidebar)
get_template_part('template-parts/event/content', null, $template_args);

// Load Schedule Section (from sc_schedules table)
get_template_part('template-parts/event/schedule', null, $template_args);

// Load Speakers Section
get_template_part('template-parts/event/speakers', null, $template_args);

// Load Sponsors Section
get_template_part('template-parts/event/sponsors', null, $template_args);

// Load Organizing Company Section
get_template_part('template-parts/event/organizing-company', null, $template_args);

// Load Halls/Venues Section
get_template_part('template-parts/event/halls', null, $template_args);

// Load FAQ Section (if has FAQs)
get_template_part('template-parts/event/faq', null, $template_args);

// Load Gallery & Additional Sections
get_template_part('template-parts/event/gallery', null, $template_args);

// Load Checkout Modal and Footer Assets
get_template_part('template-parts/event/checkout-modal', null, $template_args);

// Floating Register CTA (appears on scroll past tickets)
if (!$is_past && $has_tickets && !$is_registered):
?>
<div class="sc-floating-cta" id="sc-floating-cta">
    <div class="container">
        <div class="sc-floating-cta-inner">
            <div class="sc-floating-cta-info">
                <strong><?php echo esc_html($event->title); ?></strong>
                <span>
                    <?php if ($is_free): ?>
                        <?php echo esc_html(sc_t('frontend.free', 'Free')); ?>
                    <?php else: ?>
                        <?php echo esc_html(sc_t('frontend.from', 'From')); ?> <?php echo esc_html(number_format($min_price)); ?> <?php echo esc_html(sc_t('general.currency_symbol', 'EGP')); ?>
                    <?php endif; ?>
                </span>
            </div>
            <a href="#tickets" class="sc-btn sc-btn-gold sc-btn-sm">
                <?php echo esc_html(sc_t('frontend.register_now', 'Register Now')); ?> <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
<?php endif;

// Load footer
get_template_part('template-parts/public/footer', 'public');
