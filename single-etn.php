<?php
/**
 * Single Event Template - Eventify Design (Redesigned)
 * Public Frontend Event Details Page
 *
 * @package sc_events
 * @version 3.0.0
 */

// Start the loop
while (have_posts()) : the_post();

// Load header
get_template_part('template-parts/public/header', 'public');

$assets_url = get_template_directory_uri() . '/assets/frontend/';
$event_id = get_the_ID();

// Platform colors (global settings)
$platform_primary = get_option('sc_primary_color', '#FF4B36');
$platform_secondary = get_option('sc_secondary_color', '#1B1E4A');

// Event-specific branding colors (from Branding & Media tab)
// Background Color = primary (var(--ztc-text-text-11))
// Text Color = secondary (var(--ztc-text-text-9))
$event_bg_color = get_post_meta($event_id, 'etn_event_calendar_bg', true);
$event_text_color = get_post_meta($event_id, 'etn_event_calendar_text_color', true);

// Use event colors if set, otherwise fallback to platform colors
$primary_color = !empty($event_bg_color) ? $event_bg_color : $platform_primary;
$secondary_color = !empty($event_text_color) ? $event_text_color : $platform_secondary;

// Get event meta
$start_date = get_post_meta($event_id, 'etn_start_date', true);
$end_date = get_post_meta($event_id, 'etn_end_date', true);
$start_time = get_post_meta($event_id, 'etn_start_time', true);
$end_time = get_post_meta($event_id, 'etn_end_time', true);
$location_data = get_post_meta($event_id, 'etn_event_location', true);
// Handle location - can be string or array
$location = is_array($location_data) ? ($location_data['address'] ?? '') : $location_data;
$location_type = get_post_meta($event_id, 'etn_event_location_type', true);
$google_map = get_post_meta($event_id, 'etn_event_location_map', true);
$tickets = get_post_meta($event_id, 'etn_ticket_variations', true);
$schedules = get_post_meta($event_id, 'etn_event_schedule', true);
$event_faq = get_post_meta($event_id, 'etn_event_faq', true);
$additional_sections = get_post_meta($event_id, 'etn_additional_sections', true);

// Get schedules PDF file
$schedules_file_id = get_post_meta($event_id, 'event_schedules_file_id', true);
$schedules_file_url = $schedules_file_id ? wp_get_attachment_url($schedules_file_id) : '';

// Get event banner - support both etn_event_banner (WP Admin) and event_banner_id (Custom Dashboard)
$event_banner = get_post_meta($event_id, 'etn_event_banner', true);
if (!$event_banner) {
    $event_banner = get_post_meta($event_id, 'event_banner_id', true);
}

// Get event logo (use thumbnail or banner)
$event_logo = get_the_post_thumbnail_url($event_id, 'medium');
if (!$event_logo && $event_banner) {
    $event_logo = wp_get_attachment_url($event_banner);
}

// Get categories
$categories = get_the_terms($event_id, 'etn_category');

// Get speakers - Support both Users (with etn-speaker role) and Custom Post Types (etn-speaker)
$speaker_ids = get_post_meta($event_id, 'etn_event_speaker', true);
$speakers = array();
if (!empty($speaker_ids)) {
    foreach ((array)$speaker_ids as $speaker_id) {
        // First check if it's a user with etn-speaker role
        $user = get_user_by('ID', $speaker_id);
        if ($user && in_array('etn-speaker', (array)$user->roles)) {
            // Get image - try multiple meta keys
            $image_meta = get_user_meta($user->ID, 'image', true);
            $speaker_image = '';
            if ($image_meta) {
                if (filter_var($image_meta, FILTER_VALIDATE_URL)) {
                    $speaker_image = $image_meta;
                } else {
                    $speaker_image = wp_get_attachment_url($image_meta);
                }
            }
            if (empty($speaker_image)) {
                $speaker_image = get_user_meta($user->ID, 'etn_speaker_image', true);
            }
            if (empty($speaker_image)) {
                $speaker_image = get_avatar_url($user->ID, array('size' => 300));
            }

            // Get social media from etn_speaker_socials array
            $social_data = get_user_meta($user->ID, 'etn_speaker_socials', true);
            if (empty($social_data)) {
                $social_data = get_user_meta($user->ID, 'social', true);
            }

            $social_urls = array(
                'facebook' => '',
                'twitter' => '',
                'linkedin' => '',
                'instagram' => '',
                'youtube' => '',
                'github' => ''
            );

            if (is_array($social_data) && !empty($social_data)) {
                foreach ($social_data as $social) {
                    if (is_array($social) && isset($social['icon']) && isset($social['url'])) {
                        $icon = strtolower($social['icon']);
                        $url = $social['url'];

                        if (strpos($icon, 'facebook') !== false) {
                            $social_urls['facebook'] = $url;
                        } elseif (strpos($icon, 'twitter') !== false) {
                            $social_urls['twitter'] = $url;
                        } elseif (strpos($icon, 'linkedin') !== false) {
                            $social_urls['linkedin'] = $url;
                        } elseif (strpos($icon, 'instagram') !== false) {
                            $social_urls['instagram'] = $url;
                        } elseif (strpos($icon, 'youtube') !== false) {
                            $social_urls['youtube'] = $url;
                        } elseif (strpos($icon, 'github') !== false) {
                            $social_urls['github'] = $url;
                        }
                    }
                }
            }

            // Create a speaker object from user data
            $speakers[] = (object) array(
                'ID' => $user->ID,
                'type' => 'user',
                'post_title' => $user->display_name,
                'email' => $user->user_email,
                'bio' => get_user_meta($user->ID, 'etn_speaker_summery', true),
                'designation' => get_user_meta($user->ID, 'etn_speaker_designation', true),
                'company' => get_user_meta($user->ID, 'etn_speaker_company', true),
                'image' => $speaker_image,
                'facebook' => $social_urls['facebook'],
                'twitter' => $social_urls['twitter'],
                'instagram' => $social_urls['instagram'],
                'linkedin' => $social_urls['linkedin'],
                'youtube' => $social_urls['youtube'],
                'github' => $social_urls['github'],
                'website' => get_user_meta($user->ID, 'etn_speaker_website', true),
            );
        } else {
            // Check if it's a custom post type
            $post = get_post($speaker_id);
            if ($post && $post->post_type === 'etn-speaker') {
                $speakers[] = (object) array(
                    'ID' => $post->ID,
                    'type' => 'post',
                    'post_title' => $post->post_title,
                    'email' => get_post_meta($post->ID, 'etn_speaker_email', true),
                    'bio' => get_post_meta($post->ID, 'etn_speaker_summery', true),
                    'designation' => get_post_meta($post->ID, 'etn_speaker_designation', true),
                    'company' => get_post_meta($post->ID, 'etn_speaker_company', true),
                    'image' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
                    'facebook' => get_post_meta($post->ID, 'etn_speaker_facebook', true),
                    'twitter' => get_post_meta($post->ID, 'etn_speaker_twitter', true),
                    'instagram' => get_post_meta($post->ID, 'etn_speaker_instagram', true),
                    'linkedin' => get_post_meta($post->ID, 'etn_speaker_linkedin', true),
                    'website' => get_post_meta($post->ID, 'etn_speaker_website', true),
                );
            }
        }
    }
}

// Get organizers - Support both Users (with etn-organizer role) and Custom Post Types (etn-organizer)
$organizer_ids = get_post_meta($event_id, 'etn_event_organizer', true);
$organizers = array();
if (!empty($organizer_ids)) {
    foreach ((array)$organizer_ids as $organizer_id) {
        // First check if it's a user with etn-organizer role
        $user = get_user_by('ID', $organizer_id);
        if ($user && in_array('etn-organizer', (array)$user->roles)) {
            // Get image - try multiple meta keys
            $image_meta = get_user_meta($user->ID, 'image', true);
            $org_image = '';
            if ($image_meta) {
                if (filter_var($image_meta, FILTER_VALIDATE_URL)) {
                    $org_image = $image_meta;
                } else {
                    $org_image = wp_get_attachment_url($image_meta);
                }
            }
            if (empty($org_image)) {
                $org_image = get_user_meta($user->ID, 'company_logo', true);
            }
            if (empty($org_image)) {
                $org_image = get_user_meta($user->ID, 'etn_speaker_company_logo', true);
            }
            if (empty($org_image)) {
                $org_image = get_avatar_url($user->ID, array('size' => 300));
            }

            // Get website - try multiple meta keys
            $org_website = get_user_meta($user->ID, 'etn_speaker_url', true);
            if (empty($org_website)) {
                $org_website = get_user_meta($user->ID, 'company_url', true);
            }
            if (empty($org_website)) {
                $org_website = get_user_meta($user->ID, 'etn_organizer_website', true);
            }

            // Create an organizer object from user data
            $organizers[] = (object) array(
                'ID' => $user->ID,
                'type' => 'user',
                'post_title' => $user->display_name,
                'designation' => get_user_meta($user->ID, 'etn_organizer_designation', true),
                'description' => get_user_meta($user->ID, 'etn_speaker_summery', true),
                'image' => $org_image,
                'email' => $user->user_email,
                'phone' => get_user_meta($user->ID, 'etn_organizer_phone', true),
                'facebook' => get_user_meta($user->ID, 'etn_organizer_facebook', true),
                'twitter' => get_user_meta($user->ID, 'etn_organizer_twitter', true),
                'linkedin' => get_user_meta($user->ID, 'etn_organizer_linkedin', true),
                'website' => $org_website,
            );
        } else {
            // Check if it's a custom post type
            $post = get_post($organizer_id);
            if ($post && $post->post_type === 'etn-organizer') {
                $organizers[] = (object) array(
                    'ID' => $post->ID,
                    'type' => 'post',
                    'post_title' => $post->post_title,
                    'designation' => get_post_meta($post->ID, 'etn_organizer_designation', true),
                    'description' => get_post_meta($post->ID, 'etn_organizer_summery', true),
                    'image' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
                    'email' => get_post_meta($post->ID, 'etn_organizer_email', true),
                    'phone' => get_post_meta($post->ID, 'etn_organizer_phone', true),
                    'facebook' => get_post_meta($post->ID, 'etn_organizer_facebook', true),
                    'twitter' => get_post_meta($post->ID, 'etn_organizer_twitter', true),
                    'linkedin' => get_post_meta($post->ID, 'etn_organizer_linkedin', true),
                    'website' => get_post_meta($post->ID, 'etn_organizer_website', true),
                );
            }
        }
    }
}

// Calculate ticket price range
$min_price = 0;
$max_price = 0;
$is_free = true;
$has_tickets = !empty($tickets);

if ($has_tickets) {
    foreach ($tickets as $ticket) {
        $price = floatval($ticket['etn_ticket_price'] ?? 0);
        $use_coupons = $ticket['etn_use_coupons'] ?? $ticket['use_coupons'] ?? false;

        if ($price > 0 && !$use_coupons) {
            $is_free = false;
            if ($min_price == 0 || $price < $min_price) $min_price = $price;
            if ($price > $max_price) $max_price = $price;
        }
    }
}

// Check if event is past
$is_past = strtotime($end_date) < strtotime(date('Y-m-d'));

// Check if user is registered
$is_registered = false;
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    $existing = new WP_Query(array(
        'post_type' => 'etn-attendee',
        'post_status' => 'publish',
        'meta_query' => array(
            array('key' => 'etn_event_id', 'value' => $event_id),
            array('key' => 'etn_email', 'value' => $user->user_email)
        ),
        'posts_per_page' => 1
    ));
    $is_registered = $existing->have_posts();
}

// Event social links - Get from Branding Social Media Links
$event_socials = get_post_meta($event_id, 'etn_event_socials', true);
$event_facebook = '';
$event_twitter = '';
$event_instagram = '';
$event_linkedin = '';
$event_website = '';

// Parse social links from etn_event_socials array
if (!empty($event_socials) && is_array($event_socials)) {
    foreach ($event_socials as $social) {
        if (empty($social['etn_social_icon']) || empty($social['etn_social_url'])) {
            continue;
        }

        $icon = strtolower($social['etn_social_icon']);
        $url = $social['etn_social_url'];

        if (strpos($icon, 'facebook') !== false) {
            $event_facebook = $url;
        } elseif (strpos($icon, 'twitter') !== false) {
            $event_twitter = $url;
        } elseif (strpos($icon, 'instagram') !== false) {
            $event_instagram = $url;
        } elseif (strpos($icon, 'linkedin') !== false) {
            $event_linkedin = $url;
        } elseif (strpos($icon, 'globe') !== false || strpos($icon, 'link') !== false) {
            $event_website = $url;
        }
    }
}
?>

<!--===== EVENT BANNER SECTION =======-->
<div class="event-banner-section">
    <div class="container">
        <div class="event-banner-wrapper">
            <!-- Left Side: Logo, Title, Dates, Social Media -->
            <div class="event-banner-left">
                <?php if ($event_logo): ?>
                <div class="event-logo" data-aos="fade-right" data-aos-duration="800">
                    <img src="<?php echo esc_url($event_logo); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                </div>
                <?php endif; ?>

                <div class="event-main-info" data-aos="fade-right" data-aos-duration="900">
                    <h1 class="event-title"><?php the_title(); ?></h1>

                    <div class="event-dates">
                        <div class="date-item">
                            <i class="fa-solid fa-calendar-days"></i>
                            <div class="date-info">
                                <span class="date-label"><?php esc_html_e('Start Date', 'sc_events'); ?></span>
                                <span class="date-value"><?php echo esc_html(date_i18n('F d, Y', strtotime($start_date))); ?></span>
                            </div>
                        </div>

                        <?php if ($end_date && $end_date !== $start_date): ?>
                        <div class="date-item">
                            <i class="fa-solid fa-calendar-check"></i>
                            <div class="date-info">
                                <span class="date-label"><?php esc_html_e('End Date', 'sc_events'); ?></span>
                                <span class="date-value"><?php echo esc_html(date_i18n('F d, Y', strtotime($end_date))); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Countdown Timer -->
                    <?php
                    $event_datetime = strtotime($start_date . ' ' . ($start_time ?: '00:00:00'));
                    $current_time = current_time('timestamp');
                    if ($event_datetime > $current_time):
                    ?>
                    <div class="event-countdown" data-event-date="<?php echo esc_attr(date('Y-m-d H:i:s', $event_datetime)); ?>">
                        <div class="countdown-item">
                            <div class="countdown-value" id="days">00</div>
                            <div class="countdown-label"><?php esc_html_e('Days', 'sc_events'); ?></div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="hours">00</div>
                            <div class="countdown-label"><?php esc_html_e('Hours', 'sc_events'); ?></div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="minutes">00</div>
                            <div class="countdown-label"><?php esc_html_e('Minutes', 'sc_events'); ?></div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="seconds">00</div>
                            <div class="countdown-label"><?php esc_html_e('Seconds', 'sc_events'); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($schedules_file_url): ?>
                    <div class="download-schedule-btn" data-aos="fade-up" data-aos-duration="1000">
                        <a href="<?php echo esc_url($schedules_file_url); ?>" target="_blank" class="vl-btn1 download-schedule">
                            <i class="fa-solid fa-file-pdf"></i> <?php esc_html_e('Download Schedule', 'sc_events'); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="event-social-links">
                        <?php if ($event_facebook): ?>
                        <a href="<?php echo esc_url($event_facebook); ?>" target="_blank" class="social-link facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($event_twitter): ?>
                        <a href="<?php echo esc_url($event_twitter); ?>" target="_blank" class="social-link twitter">
                            <i class="fa-brands fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($event_instagram): ?>
                        <a href="<?php echo esc_url($event_instagram); ?>" target="_blank" class="social-link instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($event_linkedin): ?>
                        <a href="<?php echo esc_url($event_linkedin); ?>" target="_blank" class="social-link linkedin">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($event_website): ?>
                        <a href="<?php echo esc_url($event_website); ?>" target="_blank" class="social-link website">
                            <i class="fa-solid fa-globe"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Time, Location, Categories -->
            <div class="event-banner-right" data-aos="fade-left" data-aos-duration="1000">
                <div class="event-quick-info">
                    <div class="quick-info-item">
                        <div class="info-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <span class="info-label"><?php esc_html_e('Time', 'sc_events'); ?></span>
                            <span class="info-value"><?php echo esc_html($start_time); ?><?php if ($end_time): ?> - <?php echo esc_html($end_time); ?><?php endif; ?></span>
                        </div>
                    </div>

                    <?php if ($location): ?>
                    <div class="quick-info-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="info-content">
                            <span class="info-label"><?php esc_html_e('Location', 'sc_events'); ?></span>
                            <span class="info-value"><?php echo esc_html($location); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="quick-info-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-tag"></i>
                        </div>
                        <div class="info-content">
                            <span class="info-label"><?php esc_html_e('Price', 'sc_events'); ?></span>
                            <span class="info-value price-value">
                                <?php if ($is_free): ?>
                                    <?php esc_html_e('Free', 'sc_events'); ?>
                                <?php elseif ($min_price == $max_price): ?>
                                    <?php echo esc_html(number_format($min_price)); ?> EGP
                                <?php else: ?>
                                    <?php echo esc_html(number_format($min_price)); ?> - <?php echo esc_html(number_format($max_price)); ?> EGP
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                    <div class="quick-info-item categories-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-folder"></i>
                        </div>
                        <div class="info-content">
                            <span class="info-label"><?php esc_html_e('Categories', 'sc_events'); ?></span>
                            <div class="category-tags">
                                <?php foreach ($categories as $category): ?>
                                <a href="<?php echo esc_url(get_term_link($category)); ?>" class="category-tag">
                                    <?php echo esc_html($category->name); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Status Badge -->
                <?php if ($is_past): ?>
                <div class="event-status-badge status-ended">
                    <i class="fa-solid fa-circle-info"></i>
                    <?php esc_html_e('This event has ended', 'sc_events'); ?>
                </div>
                <?php elseif ($is_registered): ?>
                <div class="event-status-badge status-registered">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php esc_html_e('You are registered!', 'sc_events'); ?>
                    <a href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php esc_html_e('View Tickets', 'sc_events'); ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--===== EVENT BANNER ENDS =======-->

<!--===== EVENT CONTENT SECTION =======-->
<div class="event-content-section sp1">
    <div class="container">
        <div class="row">
            <!-- Left Side: Description -->
            <div class="col-lg-8">
                <div class="event-description-area" data-aos="fade-up" data-aos-duration="800">
                    <!-- Featured Image - Event Banner -->
                    <?php if ($event_banner):
                        $banner_url = wp_get_attachment_image_url($event_banner, 'large');
                    ?>
                    <div class="event-featured-image">
                        <img src="<?php echo esc_url($banner_url); ?>" alt="<?php the_title_attribute(); ?>" class="w-100" loading="lazy">
                    </div>
                    <div class="space30"></div>
                    <?php endif; ?>

                    <div class="section-heading">
                        <h2><?php esc_html_e('About This Event', 'sc_events'); ?></h2>
                    </div>
                    <div class="space20"></div>

                    <div class="event-content-text">
                        <?php the_content(); ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Ticket Booking Details -->
            <div class="col-lg-4">
                <div class="event-booking-sidebar" data-aos="fade-up" data-aos-duration="900">
                    <?php if ($has_tickets && !$is_past && !$is_registered): ?>
                    <div class="tickets-box">
                        <div class="tickets-header">
                            <h4><i class="fa-solid fa-ticket"></i> <?php esc_html_e('Get Your Tickets', 'sc_events'); ?></h4>
                        </div>
                        <div class="tickets-body">
                            <?php foreach ($tickets as $ticket):
                                $ticket_name = $ticket['etn_ticket_name'] ?? '';
                                $ticket_price = floatval($ticket['etn_ticket_price'] ?? 0);
                                $ticket_qty = intval($ticket['etn_avaiilable_tickets'] ?? 0);
                                $ticket_slug = $ticket['etn_ticket_slug'] ?? '';
                                $ticket_description = $ticket['etn_ticket_description'] ?? '';
                                $use_coupons = $ticket['etn_use_coupons'] ?? $ticket['use_coupons'] ?? false;

                                if (empty($ticket_name)) continue;
                            ?>
                            <div class="ticket-card">
                                <div class="ticket-info">
                                    <h5><?php echo esc_html($ticket_name); ?></h5>
                                    <?php if ($ticket_description): ?>
                                    <p class="ticket-description"><?php echo esc_html($ticket_description); ?></p>
                                    <?php endif; ?>
                                    <?php if ($ticket_qty > 0): ?>
                                    <span class="ticket-availability">
                                        <i class="fa-solid fa-ticket"></i>
                                        <?php printf(__('%d available', 'sc_events'), $ticket_qty); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-action">
                                    <?php if ($use_coupons): ?>
                                        <span class="ticket-price coupon-only"><?php esc_html_e('Coupon Only', 'sc_events'); ?></span>
                                        <button type="button" class="ticket-btn btn-show-coupon-form" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_slug); ?>">
                                            <i class="fa-solid fa-tag"></i> <?php esc_html_e('Apply Coupon', 'sc_events'); ?>
                                        </button>
                                    <?php elseif ($ticket_price == 0): ?>
                                        <span class="ticket-price free"><?php esc_html_e('FREE', 'sc_events'); ?></span>
                                        <button type="button" class="ticket-btn btn-register-free" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_slug); ?>">
                                            <i class="fa-solid fa-check"></i> <?php esc_html_e('Register', 'sc_events'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="ticket-price"><?php echo esc_html(number_format($ticket_price)); ?> EGP</span>
                                        <button type="button" class="ticket-btn btn-buy-ticket" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_slug); ?>" data-ticket-name="<?php echo esc_attr($ticket_name); ?>" data-ticket-price="<?php echo esc_attr($ticket_price); ?>">
                                            <i class="fa-solid fa-cart-shopping"></i> <?php esc_html_e('Buy Now', 'sc_events'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Coupon Form -->
                            <div id="coupon-form-wrapper" class="coupon-form-area" style="display: none;">
                                <h6><?php esc_html_e('Enter Coupon Code', 'sc_events'); ?></h6>
                                <div class="coupon-input-group">
                                    <input type="text" id="coupon-code" placeholder="<?php esc_attr_e('Coupon code', 'sc_events'); ?>">
                                    <button class="btn-apply-coupon" data-event-id="<?php echo esc_attr($event_id); ?>">
                                        <?php esc_html_e('Apply', 'sc_events'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Event Details Summary when no tickets available -->
                    <div class="event-details-box">
                        <div class="details-header">
                            <h4><i class="fa-solid fa-info-circle"></i> <?php esc_html_e('Event Details', 'sc_events'); ?></h4>
                        </div>
                        <div class="details-body">
                            <div class="detail-item">
                                <i class="fa-regular fa-calendar"></i>
                                <div>
                                    <span class="detail-label"><?php esc_html_e('Date', 'sc_events'); ?></span>
                                    <span class="detail-value"><?php echo esc_html(date_i18n('d M Y', strtotime($start_date))); ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fa-regular fa-clock"></i>
                                <div>
                                    <span class="detail-label"><?php esc_html_e('Time', 'sc_events'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($start_time); ?><?php if ($end_time): ?> - <?php echo esc_html($end_time); ?><?php endif; ?></span>
                                </div>
                            </div>
                            <?php if ($location): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-location-dot"></i>
                                <div>
                                    <span class="detail-label"><?php esc_html_e('Location', 'sc_events'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($location); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Map Widget -->
                    <?php if ($google_map): ?>
                    <div class="map-widget">
                        <h4><i class="fa-solid fa-map-location-dot"></i> <?php esc_html_e('Event Location', 'sc_events'); ?></h4>
                        <div class="map-wrapper">
                            <iframe src="<?php echo esc_url($google_map); ?>" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--===== EVENT CONTENT ENDS =======-->

<!--===== SPEAKERS SECTION =======-->
<?php if (!empty($speakers)): ?>
<div class="team7-section-area sp1">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 m-auto">
                <div class="team-header space-margin60 heading10 text-center">
                    <h2 class="text-anime-style-3"><?php esc_html_e('Event Speakers', 'sc_events'); ?></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="team-slider-area7 owl-carousel">
                    <?php foreach ($speakers as $speaker):
                        // Use properties from the normalized speaker object
                        $speaker_image = $speaker->image;
                        $speaker_designation = $speaker->designation;
                        $speaker_email = $speaker->email ?? '';
                        $speaker_bio = $speaker->bio ?? '';
                        $speaker_facebook = $speaker->facebook ?? '';
                        $speaker_twitter = $speaker->twitter ?? '';
                        $speaker_linkedin = $speaker->linkedin ?? '';
                        $speaker_instagram = $speaker->instagram ?? '';

                        // Debug: Check social media data
                        // echo '<!-- Speaker: ' . esc_html($speaker->post_title) . ' | FB: ' . esc_html($speaker_facebook) . ' | TW: ' . esc_html($speaker_twitter) . ' | LI: ' . esc_html($speaker_linkedin) . ' | IG: ' . esc_html($speaker_instagram) . ' -->';
                    ?>
                    <div class="team-widget-boxarea">
                        <div class="img1 image-anime">
                            <?php if ($speaker_image): ?>
                            <img src="<?php echo esc_url($speaker_image); ?>" alt="<?php echo esc_attr($speaker->post_title); ?>" loading="lazy">
                            <?php else: ?>
                            <img src="<?php echo esc_url($assets_url); ?>img/all-images/team/team-img24.png" alt="" loading="lazy">
                            <?php endif; ?>
                            <ul>
                                <?php if ($speaker_facebook): ?>
                                <li><a href="<?php echo esc_url($speaker_facebook); ?>" target="_blank"><i class="fa-brands fa-facebook-f"></i></a></li>
                                <?php endif; ?>
                                <?php if ($speaker_linkedin): ?>
                                <li><a href="<?php echo esc_url($speaker_linkedin); ?>" target="_blank"><i class="fa-brands fa-linkedin-in"></i></a></li>
                                <?php endif; ?>
                                <?php if ($speaker_instagram): ?>
                                <li><a href="<?php echo esc_url($speaker_instagram); ?>" target="_blank"><i class="fa-brands fa-instagram"></i></a></li>
                                <?php endif; ?>
                                <?php if ($speaker_twitter): ?>
                                <li class="<?php echo (!$speaker_instagram) ? 'm-0' : ''; ?>"><a href="<?php echo esc_url($speaker_twitter); ?>" target="_blank"><i class="fa-brands fa-twitter"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="space20"></div>
                        <div class="text-area">
                            <h4><?php echo esc_html($speaker->post_title); ?></h4>
                            <?php if ($speaker_designation): ?>
                            <div class="space16"></div>
                            <p><?php echo esc_html($speaker_designation); ?></p>
                            <?php endif; ?>
                            <?php if ($speaker_email): ?>
                            <div class="space10"></div>
                            <p class="speaker-email"><?php echo esc_html($speaker_email); ?></p>
                            <?php endif; ?>
                            <?php if ($speaker_bio): ?>
                            <div class="space10"></div>
                            <p class="speaker-bio"><?php echo esc_html(wp_trim_words($speaker_bio, 20)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--===== SPEAKERS ENDS =======-->

<!--===== ORGANIZERS SECTION =======-->
<?php if (!empty($organizers)): ?>
<div class="brands7-section-area sp2">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 m-auto">
                <div class="brand-header heading10 space-margin60 text-center">
                    <h2 class="text-anime-style-3"><?php esc_html_e('Event Organizers', 'sc_events'); ?></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <?php
            $aos_duration = 800;
            foreach ($organizers as $organizer):
                $org_image = $organizer->image ?? '';
                $org_name = $organizer->post_title ?? '';
                $org_designation = $organizer->designation ?? '';
                $org_email = $organizer->email ?? '';
                $org_website = $organizer->website ?? '';
                $org_description = $organizer->description ?? '';
                $organizer_index = 'organizer-' . $organizer->ID;
            ?>
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-duration="<?php echo $aos_duration; ?>">
                <div class="brand-box organizer-logo-box" data-bs-toggle="modal" data-bs-target="#<?php echo esc_attr($organizer_index); ?>" style="cursor: pointer;">
                    <?php if ($org_image): ?>
                        <img src="<?php echo esc_url($org_image); ?>" alt="<?php echo esc_attr($org_name); ?>" loading="lazy">
                    <?php else: ?>
                        <img src="<?php echo esc_url($assets_url); ?>img/elements/brand-img1.png" alt="" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $aos_duration += 100;
            endforeach; ?>
        </div>
    </div>
</div>

<!-- Organizer Modals -->
<?php foreach ($organizers as $organizer):
    $org_image = $organizer->image ?? '';
    $org_name = $organizer->post_title ?? '';
    $org_designation = $organizer->designation ?? '';
    $org_email = $organizer->email ?? '';
    $org_website = $organizer->website ?? '';
    $org_description = $organizer->description ?? '';
    $organizer_index = 'organizer-' . $organizer->ID;
?>
<div class="modal fade organizer-modal" id="<?php echo esc_attr($organizer_index); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr($organizer_index); ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="organizer-modal-content">
                    <div class="organizer-logo-large">
                        <?php if ($org_image): ?>
                            <img src="<?php echo esc_url($org_image); ?>" alt="<?php echo esc_attr($org_name); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <h3><?php echo esc_html($org_name); ?></h3>
                    <?php if ($org_designation): ?>
                        <p class="organizer-designation"><?php echo esc_html($org_designation); ?></p>
                    <?php endif; ?>

                    <div class="organizer-details">
                        <?php if ($org_email): ?>
                        <div class="organizer-detail-item">
                            <i class="fa-solid fa-envelope"></i>
                            <div>
                                <strong><?php esc_html_e('Email', 'sc_events'); ?></strong>
                                <p><a href="mailto:<?php echo esc_attr($org_email); ?>"><?php echo esc_html($org_email); ?></a></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($org_website): ?>
                        <div class="organizer-detail-item">
                            <i class="fa-solid fa-globe"></i>
                            <div>
                                <strong><?php esc_html_e('Company URL', 'sc_events'); ?></strong>
                                <p><a href="<?php echo esc_url($org_website); ?>" target="_blank"><?php echo esc_html($org_website); ?></a></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($org_description): ?>
                        <div class="organizer-detail-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                <strong><?php esc_html_e('Description', 'sc_events'); ?></strong>
                                <p><?php echo esc_html($org_description); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<!--===== ORGANIZERS ENDS =======-->

<!--===== ADDITIONAL SECTIONS =======-->
<?php if (!empty($additional_sections) && is_array($additional_sections)): ?>
<?php foreach ($additional_sections as $section_index => $section):
    $section_type = $section['type'] ?? 'image_slider';

    // ABOUT SECTION
    if ($section_type === 'about'):
        $heading = $section['heading'] ?? '';
        // Support both new (description) and old (paragraph1/paragraph2) formats
        $description = '';
        if (isset($section['description'])) {
            $description = $section['description'];
        } elseif (isset($section['paragraph1']) || isset($section['paragraph2'])) {
            $description = ($section['paragraph1'] ?? '') . ($section['paragraph2'] ? '<br><br>' . $section['paragraph2'] : '');
        }
        $button_text = $section['button_text'] ?? '';
        $button_link = $section['button_link'] ?? '';
        $main_image = $section['main_image'] ?? '';

        if (!$heading && !$description) continue;
?>
<!--===== ABOUT Section =======-->
<div class="about6-section-area sp1">
    <div class="container">
        <div class="row align-items-center">
            <?php if ($main_image): ?>
            <div class="col-lg-6">
                <div class="img1 reveal image-anime">
                    <img src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($heading); ?>" loading="lazy" />
                </div>
            </div>
            <?php endif; ?>
            <div class="col-lg-<?php echo $main_image ? '6' : '12'; ?>">
                <div class="about6-header heading9">
                    <?php if ($heading): ?>
                    <h2 class="text-anime-style-3"><?php echo esc_html($heading); ?></h2>
                    <?php endif; ?>
                    <?php if ($description): ?>
                    <div class="space16"></div>
                    <div data-aos="fade-left" data-aos-duration="900"><?php echo wp_kses_post($description); ?></div>
                    <?php endif; ?>
                    <?php if ($button_text && $button_link): ?>
                    <div class="space32"></div>
                    <div class="btn-area1" data-aos="fade-left" data-aos-duration="1200">
                        <span class="vl-btn6"><?php echo esc_html($button_text); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--===== ABOUT Section Ends =======-->
<?php
    // CARD SECTION
    elseif ($section_type === 'card'):
        $heading = $section['heading'] ?? '';
        $cards = $section['cards'] ?? array();

        if (!$heading && empty($cards)) continue;
?>
<!--===== Card Section =======-->
<div class="attent6-section-area sp2">
    <div class="container">
        <?php if ($heading): ?>
        <div class="row">
            <div class="col-lg-6 m-auto">
                <div class="attent-heading heading9 text-center space-margin60">
                    <h2 class="text-anime-style-3"><?php echo esc_html($heading); ?></h2>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <?php
            $aos_duration = 800;
            foreach ($cards as $card):
                $card_icon = $card['icon'] ?? '';
                $card_title = $card['title'] ?? '';
                $card_description = $card['description'] ?? '';

                if (!$card_title) continue;
            ?>
            <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-duration="<?php echo $aos_duration; ?>">
                <div class="skils-widget-boxarea">
                    <?php if ($card_icon): ?>
                    <div class="icons">
                        <img src="<?php echo esc_url($card_icon); ?>" alt="<?php echo esc_attr($card_title); ?>" loading="lazy" />
                    </div>
                    <div class="space32"></div>
                    <?php endif; ?>
                    <div class="content-area">
                        <a><?php echo esc_html($card_title); ?></a>
                        <?php if ($card_description): ?>
                        <div class="space16"></div>
                        <p><?php echo esc_html($card_description); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
                $aos_duration += 200;
            endforeach;
            ?>
        </div>
    </div>
</div>
<!--===== Card Section Ends =======-->
<?php
    // IMAGE GRID SECTION
    elseif ($section_type === 'image_grid'):
        $heading = $section['heading'] ?? '';
        $images = $section['images'] ?? array();

        if (empty($images) || !is_array($images)) continue;
?>
<!--===== Grid Section =======-->
<div class="brands7-section-area sp2">
    <div class="container">
        <?php if ($heading): ?>
        <div class="row">
            <div class="col-lg-8 m-auto">
                <div class="brand-header heading9 space-margin60 text-center">
                    <h2 class="text-anime-style-3"><?php echo esc_html($heading); ?></h2>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <?php
            $aos_duration = 800;
            foreach ($images as $image_url):
            ?>
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-duration="<?php echo $aos_duration; ?>">
                <div class="brand-box">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($heading); ?>" loading="lazy" />
                </div>
            </div>
            <?php
                $aos_duration += 100;
                if ($aos_duration > 1200) $aos_duration = 800;
            endforeach;
            ?>
        </div>
    </div>
</div>
<!--===== Grid Section Ends =======-->
<?php
    // IMAGE SLIDER SECTION (Default/Legacy)
    else:
        $section_title = $section['title'] ?? '';
        $images = $section['images'] ?? array();

        if (empty($images) || !is_array($images)) continue;
?>
<div class="memory1-section-area sp1">
    <div class="container">
        <?php if ($section_title): ?>
        <div class="row">
            <div class="col-lg-6 m-auto">
                <div class="memory-header text-center heading2 space-margin60">
                    <h2 class="text-anime-style-3"><?php echo esc_html($section_title); ?></h2>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-12">
                <div class="additional-section-slider-<?php echo $section_index; ?> owl-carousel">
                    <?php foreach ($images as $img_index => $image_url): ?>
                    <div class="memory-boxarea">
                        <div class="img1 image-anime">
                            <a href="<?php echo esc_url($image_url); ?>" data-fancybox="section-gallery-<?php echo $section_index; ?>" data-caption="<?php echo esc_attr($section_title); ?>">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($section_title); ?>" loading="lazy" />
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    endif;
endforeach;
?>
<?php endif; ?>
<!--===== ADDITIONAL SECTIONS ENDS =======-->

<!--===== EVENT SCHEDULE SECTION =======-->
<?php if (!empty($schedules) && is_array($schedules)):
    // Group schedules by date
    $schedules_by_date = array();
    foreach ($schedules as $schedule) {
        $date = $schedule['etn_schedule_date'] ?? '';
        if (!$date) continue;
        if (!isset($schedules_by_date[$date])) {
            $schedules_by_date[$date] = array();
        }
        $schedules_by_date[$date][] = $schedule;
    }

    if (!empty($schedules_by_date)):
?>
<div class="event1-section-area sp1">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 m-auto">
                <div class="event-header heading2 space-margin60 text-center">
                    <h2 class="text-anime-style-3"><?php esc_html_e('Our Events Schedule Plan', 'sc_events'); ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div data-aos="fade-up" data-aos-duration="900">
                    <ul class="nav nav-pills space-margin60" id="pills-tab" role="tablist">
                        <?php
                        $day_counter = 1;
                        foreach ($schedules_by_date as $date => $day_schedules):
                            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                            $day_num = $date_obj ? $date_obj->format('d') : '';
                            $month = $date_obj ? $date_obj->format('M') : '';
                            $year = $date_obj ? $date_obj->format('Y') : '';
                            $is_active = ($day_counter === 1) ? 'active' : '';
                            $tab_id = 'day-' . $day_counter;
                        ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $is_active; ?>" id="<?php echo $tab_id; ?>-tab" data-bs-toggle="pill" data-bs-target="#<?php echo $tab_id; ?>" type="button" role="tab" aria-controls="<?php echo $tab_id; ?>" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                                <span class="day"><?php printf(__('Day %02d', 'sc_events'), $day_counter); ?></span>
                                <span class="vl-flex">
                                    <span class="cal"><?php echo $day_num; ?></span>
                                    <span class="date"><?php echo strtoupper($month); ?> <br /> <?php echo $year; ?></span>
                                </span>
                            </button>
                        </li>
                        <?php
                            $day_counter++;
                        endforeach;
                        ?>
                    </ul>
                </div>

                <div class="tab-content" id="pills-tabContent">
                    <?php
                    $day_counter = 1;
                    foreach ($schedules_by_date as $date => $day_schedules):
                        $is_active = ($day_counter === 1) ? 'show active' : '';
                        $tab_id = 'day-' . $day_counter;
                    ?>
                    <div class="tab-pane fade <?php echo $is_active; ?>" id="<?php echo $tab_id; ?>" role="tabpanel" aria-labelledby="<?php echo $tab_id; ?>-tab" tabindex="0">
                        <?php foreach ($day_schedules as $schedule_index => $schedule):
                            $topic = $schedule['etn_schedule_topic'] ?? '';
                            $start_time = $schedule['etn_schedule_start_time'] ?? '';
                            $end_time = $schedule['etn_schedule_end_time'] ?? '';
                            $room = $schedule['etn_schedule_room'] ?? '';
                            $description = $schedule['etn_schedule_objective'] ?? '';

                            // Get speaker name - either from selected speaker or manual entry
                            $schedule_speaker_name = '';
                            if (!empty($schedule['etn_schedule_speaker']) && is_array($schedule['etn_schedule_speaker'])) {
                                $speaker_id = intval($schedule['etn_schedule_speaker'][0]);
                                if ($speaker_id > 0) {
                                    // Check if user speaker
                                    $speaker_user = get_user_by('ID', $speaker_id);
                                    if ($speaker_user) {
                                        $schedule_speaker_name = $speaker_user->display_name;
                                    } else {
                                        // Check if post speaker
                                        $speaker_post = get_post($speaker_id);
                                        if ($speaker_post) {
                                            $schedule_speaker_name = $speaker_post->post_title;
                                        }
                                    }
                                }
                            }
                            // Fallback to manual speaker name
                            if (empty($schedule_speaker_name) && !empty($schedule['etn_schedule_speaker_name'])) {
                                $schedule_speaker_name = $schedule['etn_schedule_speaker_name'];
                            }
                        ?>
                        <?php if ($schedule_index > 0): ?><div class="space30"></div><?php endif; ?>
                        <div class="tabs-widget-boxarea" data-aos="fade-up" data-aos-duration="<?php echo 800 + ($schedule_index * 200); ?>">
                            <div class="row align-items-center">
                                <div class="col-lg-4">
                                    <div class="img1">
                                        <?php if ($event_logo): ?>
                                        <img src="<?php echo esc_url($event_logo); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                                        <?php else: ?>
                                        <img src="<?php echo esc_url($assets_url); ?>img/all-images/event/event-img1.png" alt="" loading="lazy" />
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="content-area">
                                        <ul>
                                            <?php if ($start_time || $end_time): ?>
                                            <li>
                                                <span><img src="<?php echo esc_url($assets_url); ?>img/icons/clock1.svg" alt="" loading="lazy" />
                                                <?php echo esc_html($start_time); ?>
                                                <?php if ($end_time): ?> - <?php echo esc_html($end_time); ?><?php endif; ?>
                                                </span>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($room): ?>
                                            <li>
                                                <span><img src="<?php echo esc_url($assets_url); ?>img/icons/location1.svg" alt="" loading="lazy" /> <?php echo esc_html($room); ?></span>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($schedule_speaker_name): ?>
                                            <li>
                                                <span><i class="fa-solid fa-user" style="margin-right: 8px; color: var(--ztc-text-text-11);"></i> <?php echo esc_html($schedule_speaker_name); ?></span>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                        <div class="space20"></div>
                                        <h4 class="head"><?php echo esc_html($topic); ?></h4>
                                        <?php if ($description): ?>
                                        <div class="space16"></div>
                                        <p><?php echo esc_html($description); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                        $day_counter++;
                    endforeach;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    endif;
endif;
?>
<!--===== EVENT SCHEDULE ENDS =======-->

<!--===== FAQ AREA STARTS =======-->
<?php if (!empty($event_faq) && is_array($event_faq)):
    // Split FAQs into two columns
    $total_faqs = count($event_faq);
    $half = ceil($total_faqs / 2);
    $left_faqs = array_slice($event_faq, 0, $half);
    $right_faqs = array_slice($event_faq, $half);
?>
<div class="faq-inner-section-area sp1">
    <div class="container">
        <div class="row">
            <div class="col-lg-7 m-auto">
                <div class="heading2 text-center space-margin60">
                    <h2><?php esc_html_e('Frequently Asked Question', 'sc_events'); ?></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-11">
                <div class="faq-widget-area">
                    <div class="faq-section-area">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="accordian-area">
                                    <div class="accordion" id="accordionLeft">
                                        <?php foreach ($left_faqs as $index => $faq):
                                            $collapse_id = 'collapseLeft' . $index;
                                            $is_first = ($index === 0);
                                        ?>
                                        <?php if ($index > 0): ?><div class="space20"></div><?php endif; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>"><?php echo esc_html($faq['etn_faq_question'] ?? $faq['etn_faq_title'] ?? ''); ?></button>
                                            </h2>
                                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" data-bs-parent="#accordionLeft">
                                                <div class="accordion-body">
                                                    <?php echo wp_kses_post($faq['etn_faq_answer'] ?? $faq['etn_faq_content'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="accordian-area">
                                    <div class="accordion" id="accordionRight">
                                        <?php foreach ($right_faqs as $index => $faq):
                                            $collapse_id = 'collapseRight' . $index;
                                        ?>
                                        <?php if ($index > 0): ?><div class="space20"></div><?php endif; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="false" aria-controls="<?php echo $collapse_id; ?>"><?php echo esc_html($faq['etn_faq_question'] ?? $faq['etn_faq_title'] ?? ''); ?></button>
                                            </h2>
                                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionRight">
                                                <div class="accordion-body">
                                                    <?php echo wp_kses_post($faq['etn_faq_answer'] ?? $faq['etn_faq_content'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--===== FAQ AREA ENDS =======-->

<!-- Checkout Modal -->
<div class="modal fade" id="checkout-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php esc_html_e('Complete Your Purchase', 'sc_events'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="checkout-form">
                <div class="modal-body">
                    <input type="hidden" id="checkout-event-id" name="event_id">
                    <input type="hidden" id="checkout-ticket-id" name="ticket_id">
                    <input type="hidden" id="applied-coupon" name="coupon_code">

                    <div class="ticket-summary bg-light p-3 rounded mb-4">
                        <div class="d-flex justify-content-between">
                            <span><?php esc_html_e('Ticket:', 'sc_events'); ?></span>
                            <span id="checkout-ticket-name" class="fw-bold"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><?php esc_html_e('Price:', 'sc_events'); ?></span>
                            <span id="checkout-ticket-price" class="fw-bold" style="color: var(--ztc-text-text-11);"></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold"><?php esc_html_e('Total:', 'sc_events'); ?></span>
                            <span id="final-price" class="fw-bold h5 mb-0" style="color: var(--ztc-text-text-11);"></span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label"><?php esc_html_e('Quantity', 'sc_events'); ?></label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary qty-btn-minus">-</button>
                            <input type="number" class="form-control text-center qty-input" name="quantity" value="1" min="1" max="10">
                            <button type="button" class="btn btn-outline-secondary qty-btn-plus">+</button>
                        </div>
                    </div>

                    <div class="coupon-section mb-3">
                        <label class="form-label"><?php esc_html_e('Have a coupon?', 'sc_events'); ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="modal-coupon-code" placeholder="<?php esc_attr_e('Enter code', 'sc_events'); ?>">
                            <button type="button" class="btn btn-outline-primary btn-apply-modal-coupon"><?php esc_html_e('Apply', 'sc_events'); ?></button>
                        </div>
                    </div>

                    <!-- Additional Information Container -->
                    <div id="checkout-extra-fields-container"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e('Cancel', 'sc_events'); ?></button>
                    <button type="submit" class="vl-btn1"><?php esc_html_e('Proceed to Payment', 'sc_events'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extra Custom Fields Modal -->
<div class="modal fade" id="extraFieldsModal" tabindex="-1" aria-labelledby="extraFieldsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="extraFieldsModalLabel"><?php esc_html_e('Additional Information Required', 'sc_events'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="extraFieldsForm">
                <div class="modal-body">
                    <p class="text-muted mb-3"><?php esc_html_e('Please fill in the following information to complete your registration:', 'sc_events'); ?></p>
                    <div id="extraFieldsContainer">
                        <!-- Fields will be dynamically inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e('Cancel', 'sc_events'); ?></button>
                    <button type="submit" class="vl-btn1" id="submitExtraFields"><?php esc_html_e('Continue', 'sc_events'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Event Extra Fields Data (Hidden) -->
<script type="application/json" id="event-extra-fields-data">
<?php
$event_extra_fields = get_post_meta($event_id, 'attendee_extra_fields', true);
echo wp_json_encode($event_extra_fields ?: []);
?>
</script>

<!-- Event ID for Registration Check -->
<script type="application/json" id="event-data">
<?php
echo wp_json_encode(array(
    'event_id' => $event_id,
    'is_registered' => $is_registered
));
?>
</script>

<!-- Event CSS Variables - Dynamic colors from Branding settings -->
<style>
:root {
    --ztc-text-text-11: <?php echo esc_attr($primary_color); ?>;
    --ztc-text-text-9: <?php echo esc_attr($secondary_color); ?>;
    --ztc-bg-bg-9: <?php echo esc_attr($secondary_color); ?>;
}
</style>

<!-- Event Single Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/single-event.css">

<!-- Fancybox JS -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

<!-- Event Single Page JavaScript -->
<script src="<?php echo esc_url($assets_url); ?>js/single-event.js"></script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');

endwhile; // End the loop
?>

<style>
    .vl-btn6 {
  background: var(--ztc-text-text-11) !important;
}

.vl-btn6:hover {
  background: var(--ztc-bg-bg-9) !important;
}

.skils-widget-boxarea {
  text-align: center;
}

.attent6-section-area .skils-widget-boxarea .content-area p {
  color: #777;
}

/* Download Schedule Button */
.download-schedule-btn {
    margin-top: 20px;
}
.download-schedule-btn .download-schedule {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 28px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.download-schedule-btn .download-schedule i {
    font-size: 18px;
}
.download-schedule-btn .download-schedule:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
</style>