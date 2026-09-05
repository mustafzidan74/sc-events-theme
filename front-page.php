<?php
/**
 * Public Frontend Homepage - Dark & Premium Design
 *
 * 7 Sections: Hero, Stats, Events, Categories, Speakers, Sponsors, Contact
 *
 * @package sc_events
 * @version 2.0.0
 */

// Check if this is the dashboard page
$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
if (strpos($_SERVER['REQUEST_URI'], 'event-manager-dashboard') !== false) {
    return;
}

// ===== SINGLE EVENT HOMEPAGE MODE =====
$homepage_mode = get_option('sc_homepage_mode', 'normal');
$featured_event_id = intval(get_option('sc_featured_event_id', 0));

if ($homepage_mode === 'single_event' && $featured_event_id > 0 && class_exists('SC_Event')) {
    $event = SC_Event::get($featured_event_id);

    if ($event && $event->status === 'publish') {
        get_template_part('template-parts/public/header', 'public');

        $assets_url = get_template_directory_uri() . '/assets/frontend/';
        $event_id = $event->id;

        $platform_primary = get_option('sc_primary_color', '#FF4B36');
        $platform_secondary = get_option('sc_secondary_color', '#1B1E4A');
        $primary_color = !empty($event->calendar_bg_color) ? $event->calendar_bg_color : $platform_primary;
        $secondary_color = !empty($event->calendar_text_color) ? $event->calendar_text_color : $platform_secondary;

        $start_date = $event->start_date;
        $end_date = $event->end_date;
        $start_time = $event->start_time;
        $end_time = $event->end_time;
        $location_type = $event->location_type;

        $location = '';
        if (!empty($event->venue_name)) $location = $event->venue_name;
        elseif (!empty($event->venue_address)) $location = $event->venue_address;
        if (!empty($event->venue_city) && !empty($location)) $location .= ', ' . $event->venue_city;

        $tickets = SC_Ticket::get_by_event($event_id, array('is_active' => null));
        $event_faq = $event->faq;
        $social_links = $event->social_links;
        $additional_sections = $event->additional_sections;

        $event_banner = $event->banner_image;
        $event_logo = $event->logo_image ? wp_get_attachment_url($event->logo_image) : '';
        if (!$event_logo && $event->featured_image) $event_logo = wp_get_attachment_url($event->featured_image);

        $event_facebook = $event_twitter = $event_instagram = $event_linkedin = $event_website = '';
        if (!empty($social_links) && is_array($social_links)) {
            foreach ($social_links as $social) {
                $icon = strtolower($social['icon'] ?? '');
                $url = $social['url'] ?? '';
                if (strpos($icon, 'facebook') !== false) $event_facebook = $url;
                elseif (strpos($icon, 'twitter') !== false) $event_twitter = $url;
                elseif (strpos($icon, 'instagram') !== false) $event_instagram = $url;
                elseif (strpos($icon, 'linkedin') !== false) $event_linkedin = $url;
                elseif (strpos($icon, 'globe') !== false || strpos($icon, 'link') !== false) $event_website = $url;
            }
        }

        $min_price = 0; $max_price = 0; $is_free = true; $has_tickets = !empty($tickets);
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

        $is_past = strtotime($start_date) < strtotime(date('Y-m-d'));
        $is_registered = false;
        if (is_user_logged_in() && class_exists('SC_Attendee')) {
            $user = wp_get_current_user();
            $existing = SC_Attendee::get_by_event($event_id, array('email' => $user->user_email, 'limit' => 1));
            $is_registered = !empty($existing);
        }

        $event_speakers = class_exists('SC_Speaker') ? SC_Speaker::get_by_event($event_id) : array();
        $event_sponsors = class_exists('SC_Sponsor') ? SC_Sponsor::get_by_event($event_id) : array();

        global $wpdb;
        $sc_prefix = $wpdb->prefix . 'sc_';
        $event_halls = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT h.* FROM {$sc_prefix}halls h
             INNER JOIN {$sc_prefix}schedules s ON s.hall_id = h.id
             WHERE s.event_id = %d AND s.is_active = 1 AND h.is_active = 1
             ORDER BY h.sort_order ASC", $event_id
        ));

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

        $template_args = array(
            'event' => $event, 'event_id' => $event_id,
            'event_logo' => $event_logo, 'event_banner' => $event_banner,
            'start_date' => $start_date, 'end_date' => $end_date,
            'start_time' => $start_time, 'end_time' => $end_time,
            'location' => $location, 'location_type' => $location_type,
            'is_free' => $is_free, 'min_price' => $min_price, 'max_price' => $max_price,
            'is_past' => $is_past, 'is_registered' => $is_registered,
            'tickets' => $tickets, 'has_tickets' => $has_tickets,
            'event_faq' => $event_faq,
            'primary_color' => $primary_color, 'secondary_color' => $secondary_color,
            'assets_url' => $assets_url,
            'event_speakers' => $event_speakers, 'event_sponsors' => $event_sponsors,
            'event_halls' => $event_halls, 'gallery_images' => $gallery_images,
            'other_sections' => $other_sections,
            'event_facebook' => $event_facebook, 'event_twitter' => $event_twitter,
            'event_instagram' => $event_instagram, 'event_linkedin' => $event_linkedin,
            'event_website' => $event_website,
        );

        get_template_part('template-parts/event/banner', null, $template_args);
        get_template_part('template-parts/event/content', null, $template_args);
        get_template_part('template-parts/event/schedule', null, $template_args);
        get_template_part('template-parts/event/speakers', null, $template_args);
        get_template_part('template-parts/event/sponsors', null, $template_args);
        get_template_part('template-parts/event/halls', null, $template_args);
        get_template_part('template-parts/event/faq', null, $template_args);
        get_template_part('template-parts/event/gallery', null, $template_args);
        get_template_part('template-parts/event/checkout-modal', null, $template_args);
        get_template_part('template-parts/public/footer', 'public');
        return;
    }
}
// ===== END SINGLE EVENT HOMEPAGE MODE =====

// Platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_description = get_option('sc_platform_description', get_bloginfo('description'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'large') : '';
$platform_phone = get_option('sc_platform_phone', '');
$platform_email = get_option('sc_platform_email', get_option('admin_email'));
$platform_whatsapp = get_option('sc_platform_whatsapp', '');
$platform_address = get_option('sc_platform_address', '');
$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Events data
$upcoming_events = array();
$past_events = array();
$total_events = 0;
$total_attendees = 0;
$total_speakers = 0;
$total_sponsors_partners = 0;

if (class_exists('SC_Event')) {
    $upcoming_events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'), 'upcoming_only' => true,
        'limit' => 6, 'orderby' => 'start_date', 'order' => 'ASC'
    ));
    $past_events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'), 'past_only' => true,
        'limit' => 3, 'orderby' => 'start_date', 'order' => 'DESC'
    ));

    // Stats counts
    global $wpdb;
    $sc_prefix = $wpdb->prefix . 'sc_';
    $total_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sc_prefix}events WHERE status IN ('publish','completed')");
    $total_attendees = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sc_prefix}attendees WHERE status = 'active'");
    $total_speakers = (int) $wpdb->get_var("SELECT COUNT(DISTINCT id) FROM {$sc_prefix}speakers WHERE is_active=1");
    $total_sponsors = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sc_prefix}sponsors WHERE is_active=1");
    $total_partners = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sc_prefix}partners WHERE is_active=1");
    $total_sponsors_partners = $total_sponsors + $total_partners;
}

// Categories
$categories = get_terms(array('taxonomy' => 'sc_event_category', 'hide_empty' => false));
$total_categories = (!is_wp_error($categories)) ? count($categories) : 0;

// Fix category event counts using the custom sc_event_categories pivot table
if (!is_wp_error($categories) && !empty($categories)) {
    global $wpdb;
    $ec_table = $wpdb->prefix . 'sc_event_categories';
    $ev_table  = $wpdb->prefix . 'sc_events';
    $counts = $wpdb->get_results(
        "SELECT ec.category_id, COUNT(DISTINCT ec.event_id) as real_count
         FROM $ec_table ec
         INNER JOIN $ev_table e ON ec.event_id = e.id AND e.status = 'publish'
         GROUP BY ec.category_id"
    );
    $counts_map = array();
    foreach ($counts as $row) {
        $counts_map[(int) $row->category_id] = (int) $row->real_count;
    }
    foreach ($categories as $category) {
        $category->count = isset($counts_map[(int) $category->term_id]) ? $counts_map[(int) $category->term_id] : 0;
    }
}

// Next event for hero
$next_event = !empty($upcoming_events) ? $upcoming_events[0] : null;

// Top speakers (up to 8)
$top_speakers = array();
if (class_exists('SC_Speaker')) {
    global $wpdb;
    $sc_prefix = $wpdb->prefix . 'sc_';
    $top_speakers = $wpdb->get_results(
        "SELECT s.*, COUNT(es.event_id) as event_count
         FROM {$sc_prefix}speakers s
         LEFT JOIN {$sc_prefix}event_speakers es ON s.id = es.speaker_id
         WHERE s.is_active = 1
         GROUP BY s.id
         ORDER BY event_count DESC, s.id ASC
         LIMIT 8"
    );
}

// Sponsors
$all_sponsors = array();
if (class_exists('SC_Sponsor')) {
    global $wpdb;
    $sc_prefix = $wpdb->prefix . 'sc_';
    $all_sponsors = $wpdb->get_results(
        "SELECT DISTINCT sp.* FROM {$sc_prefix}sponsors sp
         WHERE sp.is_active = 1
         ORDER BY FIELD(sp.tier, 'platinum','gold','silver','bronze'), sp.sort_order ASC
         LIMIT 12"
    );
}

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!--===== SECTION 1: HERO =======-->
<section class="sc-hero sc-grid-bg">
    <!-- Decorative orbs -->
    <div class="sc-orb" style="width: 500px; height: 500px; top: -150px; right: -100px; background: var(--sc-primary);"></div>
    <div class="sc-orb" style="width: 400px; height: 400px; bottom: -100px; left: -100px; background: var(--sc-secondary);"></div>

    <div class="container sc-relative">
        <div class="row align-items-center">
            <!-- Left: Text -->
            <div class="col-lg-6 mb-5 mb-lg-0">
                <div class="sc-hero-content" data-aos="fade-right">
                    <div class="sc-badge-gold mb-3">
                        <i class="fa-solid fa-bolt"></i>
                        <?php echo esc_html(sc_t('frontend.premium_platform', 'Premium Event Platform')); ?>
                    </div>
                    <h1 class="sc-hero-title">
                        <?php echo esc_html($platform_name); ?>
                    </h1>
                    <p class="sc-hero-desc">
                        <?php echo esc_html(
                            wp_trim_words(
                                $platform_description ?: sc_t(
                                    'frontend.hero_description',
                                    'Discover extraordinary events, connect with industry leaders, and create unforgettable experiences.'
                                ),
                                40, // عدد الكلمات
                                '...'
                            )
                        ); ?>
                    </p>                    <div class="sc-hero-actions">
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-gold sc-btn-xl">
                            <?php echo esc_html(sc_t('frontend.browse_events', 'Browse Events')); ?> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="sc-btn sc-btn-outline sc-btn-xl">
                            <?php echo esc_html(sc_t('frontend.contact_us', 'Contact Us')); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right: Next Event Card -->
            <div class="col-lg-5 offset-lg-1">
                <?php if ($next_event):
                    $hero_img = $next_event->featured_image ? wp_get_attachment_url($next_event->featured_image) : '';
                    $event_url = home_url('/event/' . $next_event->slug);
                    $event_location = $next_event->venue_name ?: sc_t('frontend.online', 'Online');
                    $start_date = $next_event->start_date;
                    $event_timestamp = strtotime($start_date . ' ' . ($next_event->start_time ?: '00:00:00'));
                ?>
                <div class="sc-hero-card glass-card" data-aos="fade-left" data-aos-delay="200">
                    <?php if ($hero_img): ?>
                    <div class="sc-hero-card-image">
                        <img src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr($next_event->title); ?>">
                        <div class="sc-hero-card-overlay"></div>
                        <span class="sc-badge-gold sc-hero-card-badge"><?php echo esc_html(sc_t('frontend.next_event', 'Next Event')); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="sc-hero-card-body">
                        <h3 class="sc-hero-card-title"><?php echo esc_html($next_event->title); ?></h3>
                        <div class="sc-hero-card-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?php echo esc_html(date_i18n('M d, Y', strtotime($start_date))); ?></span>
                            <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html(wp_trim_words($event_location, 4)); ?></span>
                        </div>

                        <!-- Mini Countdown -->
                        <div class="event-countdown-mini" data-timestamp="<?php echo esc_attr($event_timestamp); ?>">
                            <div class="countdown-item">
                                <span class="countdown-value days">00</span>
                                <span class="countdown-label"><?php echo esc_html(sc_t('frontend.days', 'Days')); ?></span>
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value hours">00</span>
                                <span class="countdown-label"><?php echo esc_html(sc_t('frontend.hours_short', 'Hrs')); ?></span>
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value minutes">00</span>
                                <span class="countdown-label"><?php echo esc_html(sc_t('frontend.minutes_short', 'Min')); ?></span>
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-value seconds">00</span>
                                <span class="countdown-label"><?php echo esc_html(sc_t('frontend.seconds_short', 'Sec')); ?></span>
                            </div>
                        </div>

                        <a href="<?php echo esc_url($event_url); ?>" class="sc-btn sc-btn-gold" style="width: 100%; margin-top: var(--sc-space-4);">
                            <?php echo esc_html(sc_t('frontend.get_tickets', 'Get Tickets')); ?> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="sc-hero-card glass-card text-center" data-aos="fade-left" data-aos-delay="200" style="padding: var(--sc-space-12);">
                    <?php if ($platform_logo_url): ?>
                    <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" style="max-height: 120px; opacity: 0.7; margin-bottom: var(--sc-space-4);">
                    <?php endif; ?>
                    <p class="sc-text-muted"><?php echo esc_html(sc_t('frontend.new_events_coming', 'New events coming soon!')); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!--===== SECTION 2: STATS =======-->
<section class="sc-stats-section">
    <div class="container">
        <div class="sc-stats-strip" data-aos="fade-up">
            <div class="sc-stat-item">
                <span class="sc-stat-number" data-count="<?php echo $total_events; ?>">0</span>
                <span class="sc-stat-label"><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></span>
            </div>
            <div class="sc-stat-divider"></div>
            <div class="sc-stat-item">
                <span class="sc-stat-number" data-count="<?php echo $total_attendees; ?>">0</span>
                <span class="sc-stat-label"><?php echo esc_html(sc_t('frontend.attendees', 'Attendees')); ?></span>
            </div>
            <div class="sc-stat-divider"></div>
            <div class="sc-stat-item">
                <span class="sc-stat-number" data-count="<?php echo $total_speakers; ?>">0</span>
                <span class="sc-stat-label"><?php echo esc_html(sc_t('frontend.speakers', 'Speakers')); ?></span>
            </div>
            <div class="sc-stat-divider"></div>
            <div class="sc-stat-item">
                <span class="sc-stat-number" data-count="<?php echo $total_sponsors_partners; ?>">0</span>
                <span class="sc-stat-label"><?php echo esc_html(sc_t('frontend.sponsors_partners', 'Sponsors & Partners')); ?></span>
            </div>
        </div>
    </div>
</section>

<!--===== SECTION 3: UPCOMING EVENTS =======-->
<?php if (!empty($upcoming_events) || !empty($past_events)): ?>
<section class="sc-section sc-section-alt" id="events">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.upcoming_events', 'Upcoming Events')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.discover_next', 'Discover the next extraordinary experiences waiting for you')); ?></p>
        </div>

        <div class="row g-4">
            <?php
            $display_events = !empty($upcoming_events) ? $upcoming_events : $past_events;
            $delay = 0;
            foreach (array_slice($display_events, 0, 6) as $event):
                $event_url = home_url('/event/' . $event->slug);
                $event_img = $event->featured_image ? wp_get_attachment_url($event->featured_image) : '';
                $start_date = $event->start_date;
                $event_location = $event->venue_name ?: sc_t('frontend.online', 'Online');
                $is_upcoming = strtotime($start_date) >= strtotime(date('Y-m-d'));

                // Ticket pricing
                $ev_min_price = 0; $ev_is_free = true;
                if (class_exists('SC_Ticket')) {
                    $ev_tickets = SC_Ticket::get_by_event($event->id);
                    foreach ($ev_tickets as $t) {
                        $p = floatval($t->price ?? 0);
                        if ($p > 0) { $ev_is_free = false; if ($ev_min_price == 0 || $p < $ev_min_price) $ev_min_price = $p; }
                    }
                }
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <a href="<?php echo esc_url($event_url); ?>" class="sc-event-card d-block">
                    <div class="card-image">
                        <?php if ($event_img): ?>
                        <img src="<?php echo esc_url($event_img); ?>" alt="<?php echo esc_attr($event->title); ?>">
                        <?php else: ?>
                        <div style="width:100%;height:100%;background:var(--sc-bg-surface);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-solid fa-calendar-days" style="font-size:3rem;color:var(--sc-text-muted);"></i>
                        </div>
                        <?php endif; ?>
                        <div class="card-date-badge">
                            <?php echo date_i18n('M', strtotime($start_date)); ?><br>
                            <?php echo date_i18n('d', strtotime($start_date)); ?>
                        </div>
                        <div class="card-status">
                            <span class="event-status-badge <?php echo $is_upcoming ? 'upcoming' : 'past'; ?>">
                                <?php echo $is_upcoming ? sc_t('frontend.upcoming', 'Upcoming') : sc_t('frontend.past', 'Past'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="card-title"><?php echo esc_html($event->title); ?></h4>
                        <div class="card-meta">
                            <div class="card-meta-item">
                                <i class="fa-regular fa-calendar"></i>
                                <?php echo esc_html(date_i18n('F d, Y', strtotime($start_date))); ?>
                            </div>
                            <div class="card-meta-item">
                                <i class="fa-solid fa-location-dot"></i>
                                <?php echo esc_html(wp_trim_words($event_location, 5)); ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <span class="card-price">
                                <?php if ($ev_is_free): ?>
                                    <span class="free-tag"><?php echo esc_html(sc_t('frontend.free', 'Free')); ?></span>
                                <?php else: ?>
                                    <?php echo esc_html(number_format($ev_min_price)); ?> <?php echo esc_html(sc_t('general.currency_symbol', 'EGP')); ?>
                                <?php endif; ?>
                            </span>
                            <span class="sc-btn sc-btn-sm sc-btn-ghost"><?php echo esc_html(sc_t('frontend.details', 'Details')); ?> <i class="fa-solid fa-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php $delay += 100; endforeach; ?>
        </div>

        <div class="text-center" style="margin-top: var(--sc-space-10);" data-aos="fade-up">
            <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-outline sc-btn-lg">
                <?php echo esc_html(sc_t('frontend.view_all_events', 'View All Events')); ?> <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!--===== SECTION 4: CATEGORIES =======-->
<?php if (!is_wp_error($categories) && !empty($categories)): ?>
<section class="sc-section" id="categories">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.event_categories', 'Event Categories')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.find_events_interests', 'Find events that match your interests')); ?></p>
        </div>

        <div class="row g-4">
            <?php $delay = 0; foreach ($categories as $category):
                $cat_icon = get_term_meta($category->term_id, 'sc_cat_icon', true);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <a href="<?php echo esc_url(get_term_link($category)); ?>" class="sc-category-card glass-card d-block text-center">
                    <div class="sc-icon-circle sc-icon-circle-lg" style="margin: 0 auto var(--sc-space-4);">
                        <?php if ($cat_icon): ?>
                            <i class="<?php echo esc_attr($cat_icon); ?>"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-calendar-days"></i>
                        <?php endif; ?>
                    </div>
                    <h5 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-2);"><?php echo esc_html($category->name); ?></h5>
                    <p style="font-size: var(--sc-text-sm); color: var(--sc-text-muted); margin-bottom: var(--sc-space-3);">
                        <?php echo esc_html($category->description ?: sc_t('frontend.browse_category', 'Browse events in this category')); ?>
                    </p>
                    <span class="sc-badge-gold">
                        <?php echo $category->count . ' ' . ($category->count == 1 ? sc_t('frontend.event_singular', 'Event') : sc_t('frontend.event_plural', 'Events')); ?>
                    </span>
                </a>
            </div>
            <?php $delay += 100; endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!--===== SECTION 5: SPEAKERS SPOTLIGHT (Tabbed by Event) =======-->
<?php
// Build event → speakers map
$events_with_speakers = array();
if (class_exists('SC_Event') && class_exists('SC_Speaker')) {
    global $wpdb;
    $sc_prefix = $wpdb->prefix . 'sc_';
    $events_data = $wpdb->get_results(
        "SELECT e.id, e.title, e.slug, COUNT(es.speaker_id) as speaker_count
         FROM {$sc_prefix}events e
         INNER JOIN {$sc_prefix}event_speakers es ON e.id = es.event_id
         WHERE e.status IN ('publish','completed')
         GROUP BY e.id
         HAVING speaker_count > 0
         ORDER BY e.start_date DESC"
    );
    foreach ($events_data as $ev) {
        $event_speakers_list = SC_Speaker::get_by_event($ev->id);
        if (!empty($event_speakers_list)) {
            $events_with_speakers[] = (object) array(
                'id' => $ev->id,
                'title' => $ev->title,
                'slug' => $ev->slug,
                'speakers' => array_slice($event_speakers_list, 0, 12),
            );
        }
    }
}
?>
<?php if (!empty($events_with_speakers)): ?>
<section class="sc-section sc-section-alt" id="speakers">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.featured_speakers', 'Featured Speakers')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.learn_from_experts', 'Learn from world-class experts and industry leaders')); ?></p>
        </div>

        <!-- Event Tabs -->
        <div class="sc-speaker-tabs" data-aos="fade-up">
            <?php foreach ($events_with_speakers as $idx => $ev): ?>
            <button class="sc-speaker-tab <?php echo $idx === 0 ? 'active' : ''; ?>" data-target="sc-speakers-event-<?php echo (int) $ev->id; ?>">
                <?php echo esc_html($ev->title); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Tab Panels -->
        <?php foreach ($events_with_speakers as $idx => $ev): ?>
        <div class="sc-speaker-tab-panel <?php echo $idx === 0 ? 'active' : ''; ?>" id="sc-speakers-event-<?php echo (int) $ev->id; ?>">
            <div class="row g-4">
                <?php $delay = 0; foreach ($ev->speakers as $speaker):
                    $photo_url = '';
                    if (!empty($speaker->photo)) {
                        $photo_url = is_numeric($speaker->photo) ? wp_get_attachment_url($speaker->photo) : $speaker->photo;
                    }
                    $social = !empty($speaker->social_links) ? (is_array($speaker->social_links) ? $speaker->social_links : json_decode($speaker->social_links, true)) : array();
                ?>
                <div class="col-lg-3 col-md-4 col-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="sc-speaker-card text-center">
                        <div class="sc-speaker-photo">
                            <?php if ($photo_url): ?>
                            <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($speaker->name); ?>">
                            <?php else: ?>
                            <div class="sc-speaker-placeholder">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <?php endif; ?>
                            <div class="sc-speaker-overlay">
                                <?php if (!empty($social)): ?>
                                <div class="sc-social-links" style="justify-content: center;">
                                    <?php if (!empty($social['facebook'])): ?>
                                    <a href="<?php echo esc_url($social['facebook']); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-facebook-f"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($social['twitter'])): ?>
                                    <a href="<?php echo esc_url($social['twitter']); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-twitter"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($social['linkedin'])): ?>
                                    <a href="<?php echo esc_url($social['linkedin']); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-linkedin-in"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <h6 class="sc-speaker-name"><?php echo esc_html($speaker->name); ?></h6>
                        <span class="sc-speaker-title"><?php echo esc_html($speaker->title ?? ''); ?></span>
                    </div>
                </div>
                <?php $delay += 80; endforeach; ?>
            </div>

            <div class="text-center" style="margin-top: var(--sc-space-8);">
                <a href="<?php echo esc_url(home_url('/event/' . $ev->slug . '/#speakers')); ?>" class="sc-btn sc-btn-outline sc-btn-lg">
                    <?php echo esc_html(sc_t('frontend.view_all_speakers', 'View All Speakers')); ?> <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
.sc-speaker-tabs { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 32px; }
.sc-speaker-tab { background: transparent; border: 1px solid rgba(255,255,255,0.15); color: var(--sc-text-primary, inherit); padding: 10px 20px; border-radius: 999px; cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
.sc-speaker-tab:hover { background: rgba(255,255,255,0.05); }
.sc-speaker-tab.active { background: var(--sc-primary, #7c1314); color: #fff; border-color: var(--sc-primary, #7c1314); }
.sc-speaker-tab-panel { display: none; }
.sc-speaker-tab-panel.active { display: block; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sc-speaker-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.getAttribute('data-target');
            document.querySelectorAll('.sc-speaker-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.sc-speaker-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            this.classList.add('active');
            var panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });
});
</script>
<?php endif; ?>

<!--===== SECTION 6: ORGANIZATIONS (Sponsors carousel) =======-->
<?php if (!empty($all_sponsors)): ?>
<section class="sc-section" id="sponsors">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.our_organizations', 'Our Organizations')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.organizations_subtitle', 'Meet the organizations powering our events')); ?></p>
        </div>

        <div class="sc-organizations-wrapper" data-aos="fade-up">
            <div class="swiper sc-organizations-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($all_sponsors as $sponsor):
                        $logo_url = '';
                        if (!empty($sponsor->logo)) {
                            $logo_url = is_numeric($sponsor->logo) ? wp_get_attachment_url($sponsor->logo) : $sponsor->logo;
                        }
                        $website = !empty($sponsor->website) ? $sponsor->website : '#';
                        $sponsor_title = !empty($sponsor->title) ? $sponsor->title : '';
                    ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url($website); ?>" <?php echo $website !== '#' ? 'target="_blank" rel="noopener"' : ''; ?> class="sc-org-card">
                            <div class="sc-org-photo">
                                <?php if ($logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($sponsor->name); ?>">
                                <?php else: ?>
                                <div class="sc-org-placeholder"><i class="fa-solid fa-building"></i></div>
                                <?php endif; ?>
                            </div>
                            <h6 class="sc-org-name"><?php echo esc_html($sponsor->name); ?></h6>
                            <?php if ($sponsor_title): ?>
                            <span class="sc-org-title"><?php echo esc_html($sponsor_title); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </div>
</section>

<style>
.sc-organizations-wrapper { position: relative; padding: 0 40px; }
.sc-organizations-swiper { padding: 20px 0 50px; }
.sc-org-card { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 16px; text-decoration: none; transition: transform 0.3s; }
.sc-org-card:hover { transform: translateY(-4px); text-decoration: none; }
.sc-org-photo { width: 160px; height: 160px; border-radius: 50%; overflow: hidden; background: #f5f5f5; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(0,0,0,0.1); margin-bottom: 16px; border: 4px solid #fff; }
.sc-org-photo img { width: 100%; height: 100%; object-fit: cover; }
.sc-org-placeholder { font-size: 3rem; color: #ccc; }
.sc-org-name { font-size: 1.05rem; font-weight: 700; color: var(--sc-text-primary, #1a1a2e); margin: 0 0 4px; }
.sc-org-title { font-size: 0.85rem; color: var(--sc-text-muted, #666); }
.sc-organizations-wrapper .swiper-button-next,
.sc-organizations-wrapper .swiper-button-prev { color: var(--sc-primary, #7c1314); background: #fff; width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.sc-organizations-wrapper .swiper-button-next::after,
.sc-organizations-wrapper .swiper-button-prev::after { font-size: 16px; font-weight: 700; }
.sc-organizations-wrapper .swiper-pagination-bullet-active { background: var(--sc-primary, #7c1314); }
@media (max-width: 640px) { .sc-org-photo { width: 130px; height: 130px; } }
</style>

<!-- Swiper assets (loaded once) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var orgEl = document.querySelector('.sc-organizations-swiper');
    if (orgEl && typeof Swiper !== 'undefined') {
        new Swiper(orgEl, {
            slidesPerView: 2,
            spaceBetween: 24,
            loop: true,
            autoplay: { delay: 2500, disableOnInteraction: false },
            pagination: { el: orgEl.querySelector('.swiper-pagination'), clickable: true },
            navigation: { nextEl: orgEl.parentElement.querySelector('.swiper-button-next'), prevEl: orgEl.parentElement.querySelector('.swiper-button-prev') },
            breakpoints: {
                480: { slidesPerView: 3 },
                768: { slidesPerView: 4 },
                1024: { slidesPerView: 5 },
                1280: { slidesPerView: 6 }
            }
        });
    }
});
</script>
<?php endif; ?>

<!--===== SECTION 7: CONTACT =======-->
<section class="sc-section sc-section-alt" id="contact">
    <div class="container">
        <div class="row align-items-start g-5">
            <!-- Contact Form -->
            <div class="col-lg-7" data-aos="fade-right">
                <div class="glass-card-static">
                    <h3 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-6);">
                        <?php echo esc_html(sc_t('frontend.get_in_touch', 'Get In Touch')); ?>
                    </h3>
                    <form id="homepage-contact-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="sc-label"><?php echo esc_html(sc_t('frontend.name', 'Name')); ?></label>
                                <input type="text" name="contact_name" class="sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.your_name', 'Your Name')); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="sc-label"><?php echo esc_html(sc_t('frontend.phone', 'Phone')); ?></label>
                                <input type="text" name="contact_phone" class="sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.your_phone', 'Your Phone')); ?>">
                            </div>
                            <div class="col-12">
                                <label class="sc-label"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                                <input type="email" name="contact_email" class="sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.your_email', 'Your Email')); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="sc-label"><?php echo esc_html(sc_t('frontend.subject', 'Subject')); ?></label>
                                <input type="text" name="contact_subject" class="sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.subject', 'Subject')); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="sc-label"><?php echo esc_html(sc_t('frontend.message', 'Message')); ?></label>
                                <textarea name="contact_message" class="sc-input sc-textarea" placeholder="<?php echo esc_attr(sc_t('frontend.your_message', 'Your Message')); ?>" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="sc-btn sc-btn-gold sc-btn-lg" style="margin-top: var(--sc-space-2);">
                                    <span class="btn-text"><?php echo esc_html(sc_t('frontend.send_message', 'Send Message')); ?></span>
                                    <span class="btn-loading" style="display:none;"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                </button>
                            </div>
                            <div class="col-12">
                                <div id="contact-form-alert" style="display:none; margin-top: var(--sc-space-3);"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-5" data-aos="fade-left">
                <h3 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-2);">
                    <?php echo esc_html(sc_t('frontend.contact_information', 'Contact Information')); ?>
                </h3>
                <p style="color: var(--sc-text-muted); margin-bottom: var(--sc-space-8);">
                    <?php echo esc_html(sc_t('frontend.help_reach_out', "We're here to help! Reach out to us through any of these channels.")); ?>
                </p>

                <?php if ($platform_email): ?>
                <div class="sc-contact-info-item glass-card" style="margin-bottom: var(--sc-space-4);">
                    <div class="sc-icon-circle">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div>
                        <span class="sc-text-muted" style="font-size: var(--sc-text-xs); text-transform: uppercase; letter-spacing: 0.1em;"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></span>
                        <a href="mailto:<?php echo esc_attr($platform_email); ?>" style="display: block; color: var(--sc-text-primary);"><?php echo esc_html($platform_email); ?></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($platform_phone): ?>
                <div class="sc-contact-info-item glass-card" style="margin-bottom: var(--sc-space-4);">
                    <div class="sc-icon-circle">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div>
                        <span class="sc-text-muted" style="font-size: var(--sc-text-xs); text-transform: uppercase; letter-spacing: 0.1em;"><?php echo esc_html(sc_t('frontend.phone', 'Phone')); ?></span>
                        <a href="tel:<?php echo esc_attr($platform_phone); ?>" style="display: block; color: var(--sc-text-primary);"><?php echo esc_html($platform_phone); ?></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($platform_whatsapp): ?>
                <div class="sc-contact-info-item glass-card" style="margin-bottom: var(--sc-space-4);">
                    <div class="sc-icon-circle" style="background: rgba(37, 211, 102, 0.1); color: #25D366;">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div>
                        <span class="sc-text-muted" style="font-size: var(--sc-text-xs); text-transform: uppercase; letter-spacing: 0.1em;"><?php echo esc_html(sc_t('frontend.whatsapp', 'WhatsApp')); ?></span>
                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $platform_whatsapp)); ?>" target="_blank" style="display: block; color: var(--sc-text-primary);"><?php echo esc_html($platform_whatsapp); ?></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($platform_address): ?>
                <div class="sc-contact-info-item glass-card" style="margin-bottom: var(--sc-space-4);">
                    <div class="sc-icon-circle">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div>
                        <span class="sc-text-muted" style="font-size: var(--sc-text-xs); text-transform: uppercase; letter-spacing: 0.1em;"><?php echo esc_html(sc_t('frontend.address', 'Address')); ?></span>
                        <span style="display: block; color: var(--sc-text-primary);"><?php echo esc_html($platform_address); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
wp_enqueue_script('sc-front-page', get_template_directory_uri() . '/assets/frontend/js/front-page.js', array(), '1.0.0', true);
get_template_part('template-parts/public/footer', 'public');
?>
