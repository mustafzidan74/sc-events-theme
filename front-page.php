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

<?php
/*
 * Redesign hero — the date is the headline.
 *
 * Everything is read from the event rather than written into the markup, so a
 * single-day event, a run that crosses a month boundary, or a free ticket all
 * render correctly without a second template.
 */
if ($next_event):
    $w_start = strtotime($next_event->start_date);
    $w_end = !empty($next_event->end_date) ? strtotime($next_event->end_date) : $w_start;
    $w_url = home_url('/event/' . $next_event->slug);
    $w_img = $next_event->featured_image ? wp_get_attachment_url($next_event->featured_image) : '';
    $w_starts_at = strtotime($next_event->start_date . ' ' . ($next_event->start_time ?: '00:00:00'));
    $w_is_upcoming = $w_starts_at > current_time('timestamp');

    // "7–9" when the run stays inside one month, "28 Nov – 2 Dec" when it does not.
    $w_same_month = date('Y-m', $w_start) === date('Y-m', $w_end);
    if ($w_start === $w_end) {
        $w_big = date_i18n('j', $w_start);
    } elseif ($w_same_month) {
        $w_big = date_i18n('j', $w_start) . '–' . date_i18n('j', $w_end);
    } else {
        $w_big = date_i18n('j M', $w_start) . ' – ' . date_i18n('j M', $w_end);
    }
    $w_small = $w_same_month ? date_i18n('M Y', $w_start) : date_i18n('Y', $w_end);

    // Lowest active ticket price. Zero across the board means the event is free.
    $w_price_label = '';
    if (class_exists('SC_Ticket')) {
        $w_prices = array_map(
            static function ($t) { return (float) $t->price; },
            SC_Ticket::get_by_event($next_event->id) ?: []
        );
        if ($w_prices) {
            $w_min = min($w_prices);
            $w_price_label = $w_min > 0
                ? sprintf(sc_t('frontend.from_price', 'from %s'), sc_currency($w_min))
                : sc_t('frontend.free', 'Free');
        }
    }

    $w_venue = trim(implode('، ', array_filter([
        $next_event->venue_name,
        $next_event->venue_city,
    ])));
?>
<main class="w-main">
    <section class="w-hero">
        <div class="w-hero__copy">
            <span class="w-hero__status">
                <span class="w-hero__pulse" aria-hidden="true"></span>
                <?php echo esc_html(sc_t('frontend.registration_open', 'Registration open')); ?>
                <?php if ($w_venue): ?>· <?php echo esc_html($w_venue); ?><?php endif; ?>
            </span>

            <h1 class="w-hero__date">
                <?php echo esc_html($w_big); ?>
                <span><?php echo esc_html($w_small); ?></span>
            </h1>

            <p class="w-hero__title"><?php echo esc_html($next_event->title); ?></p>

            <div class="w-hero__actions">
                <a class="w-hero__cta" href="<?php echo esc_url($w_url); ?>">
                    <?php echo esc_html(sc_t('frontend.register', 'Register')); ?><?php
                    if ($w_price_label) { echo ' · ' . esc_html($w_price_label); }
                    ?>
                </a>
            </div>

            <?php if ($w_is_upcoming): ?>
            <div class="w-countdown" data-starts="<?php echo esc_attr($w_starts_at); ?>" aria-live="off">
                <?php
                $w_left = max(0, $w_starts_at - current_time('timestamp'));
                $w_units = [
                    'days' => [intdiv($w_left, 86400), sc_t('frontend.days', 'days')],
                    'hrs'  => [intdiv($w_left % 86400, 3600), sc_t('frontend.hours', 'hrs')],
                    'min'  => [intdiv($w_left % 3600, 60), sc_t('frontend.minutes', 'min')],
                ];
                foreach ($w_units as $w_key => $w_unit): ?>
                <div class="w-countdown__unit">
                    <span class="w-countdown__value" data-unit="<?php echo esc_attr($w_key); ?>"><?php echo esc_html($w_unit[0]); ?></span>
                    <span class="w-countdown__label"><?php echo esc_html($w_unit[1]); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="w-hero__media<?php echo $w_img ? '' : ' w-hero__media--empty'; ?>">
            <?php if ($w_img): ?>
                <img src="<?php echo esc_url($w_img); ?>"
                     alt="<?php echo esc_attr($next_event->title); ?>"
                     fetchpriority="high" decoding="async">
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
// Tick the countdown without re-rendering the page. Values come from the
// server, so the first paint is already correct with JavaScript disabled.
(function () {
    var box = document.querySelector('.w-countdown[data-starts]');
    if (!box) { return; }
    var target = parseInt(box.dataset.starts, 10) * 1000;
    var out = {
        days: box.querySelector('[data-unit="days"]'),
        hrs: box.querySelector('[data-unit="hrs"]'),
        min: box.querySelector('[data-unit="min"]')
    };
    function tick() {
        var left = Math.max(0, target - Date.now()) / 1000;
        out.days.textContent = Math.floor(left / 86400);
        out.hrs.textContent = Math.floor((left % 86400) / 3600);
        out.min.textContent = Math.floor((left % 3600) / 60);
    }
    tick();
    setInterval(tick, 30000);
})();
</script>
<?php endif; ?>

<?php
/*
 * Redesign headline stat and intent tiles.
 *
 * The design states one figure as a sentence rather than a row of stat tiles,
 * then offers four shortcuts into the event. The wording avoids "last year",
 * which these lifetime totals would not support.
 *
 * A tile whose count is zero is dropped: a shortcut into an empty section is
 * worse than no shortcut at all.
 */
$w_counts = ['programme' => 0, 'speakers' => 0, 'workshops' => 0];
if ($next_event) {
    global $wpdb;
    $w_p = $wpdb->prefix . 'sc_';
    $w_counts['programme'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$w_p}schedules WHERE event_id = %d", $next_event->id));
    $w_counts['speakers'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$w_p}event_speakers WHERE event_id = %d", $next_event->id));
    $w_counts['workshops'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$w_p}workshops WHERE event_id = %d AND status = 'publish'", $next_event->id));
}
$w_ev_url = $next_event ? home_url('/event/' . $next_event->slug) : home_url('/events/');

$w_tiles = [];
if ($w_counts['programme']) {
    $w_tiles[] = ['icon' => 'clock', 'label' => sc_t('frontend.programme', 'Programme'),
        'sub' => sprintf(sc_t('frontend.n_sessions', '%d sessions'), $w_counts['programme']),
        'href' => $w_ev_url . '#schedule'];
}
if ($w_counts['speakers']) {
    $w_tiles[] = ['icon' => 'user-group', 'label' => sc_t('frontend.speakers', 'Speakers'),
        'sub' => sprintf(sc_t('frontend.n_faculty', '%d faculty'), $w_counts['speakers']),
        'href' => $w_ev_url . '#speakers'];
}
if ($w_counts['workshops']) {
    $w_tiles[] = ['icon' => 'wrench', 'label' => sc_t('frontend.workshops', 'Workshops'),
        'sub' => sprintf(sc_t('frontend.n_hands_on', '%d hands-on'), $w_counts['workshops']),
        'href' => home_url('/workshops/')];
}
$w_tiles[] = ['icon' => 'ticket', 'label' => sc_t('frontend.my_ticket', 'My ticket'),
    'sub' => sc_t('frontend.badge_certificate', 'E-badge & certificate'),
    'href' => home_url(is_user_logged_in() ? '/my-account/' : '/login/'), 'accent' => true];
?>
<?php if ($total_attendees > 0 || count($w_tiles) > 1): ?>
<section class="w-section">
    <div class="w-intent">
        <?php if ($total_attendees > 0): ?>
        <p class="w-intent__stat">
            <?php printf(
                esc_html(sc_t('frontend.attendees_so_far', '%s dentists have attended so far')),
                '<b>' . esc_html(number_format_i18n((int) $total_attendees)) . '</b>'
            ); ?>
        </p>
        <?php endif; ?>

        <div class="w-intent__tiles">
            <?php foreach ($w_tiles as $w_t): ?>
            <a class="w-tile<?php echo !empty($w_t['accent']) ? ' w-tile--accent' : ''; ?>" href="<?php echo esc_url($w_t['href']); ?>">
                <span class="w-tile__icon"><i class="fa-solid fa-<?php echo esc_attr($w_t['icon']); ?>" aria-hidden="true"></i></span>
                <span class="w-tile__text">
                    <span class="w-tile__label"><?php echo esc_html($w_t['label']); ?></span>
                    <span class="w-tile__sub"><?php echo esc_html($w_t['sub']); ?></span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
/*
 * Redesign event grid.
 *
 * The card mirrors the hero — an ink panel with the date carrying the weight
 * and the cover behind a scrim — so a listing and the page it leads to read as
 * the same system. The first card spans the row.
 *
 * The categories section that used to sit below this is gone: categories are
 * now a submenu under Events in the header, and repeating them here duplicated
 * navigation the design does not have.
 */
$w_all_events = array_merge($upcoming_events ?: [], $past_events ?: []);
?>
<?php if ($w_all_events): ?>
<!--===== EVENTS =======-->
<section class="w-section" id="events">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?></h2>
        <a class="w-section__link" href="<?php echo esc_url(home_url('/events/')); ?>">
            <?php echo esc_html(sc_t('frontend.see_all', 'See all')); ?>
        </a>
    </div>

    <div class="w-events">
        <?php foreach (array_slice($w_all_events, 0, 5) as $w_ev):
            $w_ev_start = strtotime($w_ev->start_date);
            $w_ev_end = !empty($w_ev->end_date) ? strtotime($w_ev->end_date) : $w_ev_start;
            $w_ev_same = date('Y-m', $w_ev_start) === date('Y-m', $w_ev_end);
            $w_ev_big = $w_ev_start === $w_ev_end
                ? date_i18n('j', $w_ev_start)
                : ($w_ev_same ? date_i18n('j', $w_ev_start) . '–' . date_i18n('j', $w_ev_end)
                              : date_i18n('j M', $w_ev_start) . ' – ' . date_i18n('j M', $w_ev_end));
            $w_ev_small = $w_ev_same ? date_i18n('M Y', $w_ev_start) : date_i18n('Y', $w_ev_end);
            $w_ev_img = $w_ev->featured_image ? wp_get_attachment_url($w_ev->featured_image) : '';
            $w_ev_past = $w_ev_end < current_time('timestamp');
        ?>
        <a class="w-event<?php echo $w_ev_img ? '' : ' w-event--empty'; ?>" href="<?php echo esc_url(home_url('/event/' . $w_ev->slug)); ?>">
            <?php if ($w_ev_img): ?>
                <img class="w-event__cover" src="<?php echo esc_url($w_ev_img); ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="w-event__body">
                <span class="w-event__badges">
                    <?php if ($w_ev_past): ?>
                        <span class="w-event__badge"><?php echo esc_html(sc_t('frontend.past', 'Past')); ?></span>
                    <?php else: ?>
                        <span class="w-event__badge">
                            <span class="w-hero__pulse" aria-hidden="true"></span>
                            <?php echo esc_html(sc_t('frontend.registration_open', 'Registration open')); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($w_ev->venue_city)): ?>
                        <span class="w-event__badge"><?php echo esc_html($w_ev->venue_city); ?></span>
                    <?php endif; ?>
                </span>

                <span class="w-event__foot">
                    <span class="w-event__text">
                        <span class="w-event__date"><?php echo esc_html($w_ev_big); ?> <span><?php echo esc_html($w_ev_small); ?></span></span>
                        <span class="w-event__title"><?php echo esc_html($w_ev->title); ?></span>
                        <?php if (!empty($w_ev->venue_name)): ?>
                            <span class="w-event__meta"><?php echo esc_html($w_ev->venue_name); ?></span>
                        <?php endif; ?>
                    </span>
                </span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
/*
 * Redesign speaker rail.
 *
 * The home leads with the next event, so the rail shows that event's line-up.
 * If the event has no speakers attached yet it falls back to the platform's
 * most-booked speakers rather than rendering an empty section.
 */
$w_speakers = [];
if ($next_event && class_exists('SC_Speaker')) {
    $w_speakers = SC_Speaker::get_by_event($next_event->id) ?: [];
}
if (!$w_speakers) {
    $w_speakers = $top_speakers ?: [];
}
?>
<?php if ($w_speakers): ?>
<section class="w-section">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.whos_speaking', "Who's speaking")); ?></h2>
        <div class="w-rail__nav">
            <button type="button" class="w-rail__btn" data-rail="prev" aria-label="<?php esc_attr_e('Previous', 'sc_events'); ?>">
                <i class="fa-solid fa-chevron-left w-arrow" aria-hidden="true"></i>
            </button>
            <button type="button" class="w-rail__btn" data-rail="next" aria-label="<?php esc_attr_e('Next', 'sc_events'); ?>">
                <i class="fa-solid fa-chevron-right w-arrow" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="w-rail" id="w-speaker-rail">
        <?php foreach ($w_speakers as $w_sp):
            $w_photo = '';
            if (!empty($w_sp->photo)) {
                $w_photo = is_numeric($w_sp->photo) ? wp_get_attachment_url($w_sp->photo) : $w_sp->photo;
            }
            // Initials for the designed fallback: first letter of the first two
            // words, skipping honorifics so "Dr Mohamed Elzohairy" reads "ME".
            $w_words = preg_split('/\s+/', trim(preg_replace('/^(dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?)\s+/i', '', $w_sp->name)));
            $w_initials = mb_strtoupper(mb_substr($w_words[0] ?? '', 0, 1) . mb_substr($w_words[1] ?? '', 0, 1));
        ?>
        <a class="w-speaker" href="<?php echo esc_url(home_url('/event/' . $next_event->slug . '#speakers')); ?>">
            <?php if ($w_photo): ?>
                <img src="<?php echo esc_url($w_photo); ?>" alt="<?php echo esc_attr($w_sp->name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <span class="w-speaker__initials" aria-hidden="true"><?php echo esc_html($w_initials); ?></span>
            <?php endif; ?>
            <span class="w-speaker__reveal">
                <span class="w-speaker__name"><?php echo esc_html($w_sp->name); ?></span>
                <?php if (!empty($w_sp->title)): ?>
                    <span class="w-speaker__topic"><?php echo esc_html($w_sp->title); ?></span>
                <?php endif; ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<script>
// Rail arrows scroll by one card width plus its gap.
(function () {
    var rail = document.getElementById('w-speaker-rail');
    if (!rail) { return; }
    var head = rail.previousElementSibling;
    head.querySelectorAll('[data-rail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = rail.firstElementChild;
            if (!card) { return; }
            var step = card.getBoundingClientRect().width + 14;
            var dir = btn.dataset.rail === 'next' ? 1 : -1;
            // Right-to-left pages scroll in the opposite direction.
            if (getComputedStyle(rail).direction === 'rtl') { dir *= -1; }
            rail.scrollBy({ left: step * dir, behavior: 'smooth' });
        });
    });
})();
</script>
<?php endif; ?>
<?php
/*
 * Redesign workshops and ticket picker.
 *
 * Both read from the event the home leads with, and each section disappears
 * entirely when that event has nothing to show, rather than rendering an
 * empty shell.
 */
$w_workshops = [];
$w_tickets = [];
if ($next_event) {
    global $wpdb;
    $w_p = $wpdb->prefix . 'sc_';
    $w_workshops = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$w_p}workshops WHERE event_id = %d AND status = 'publish' ORDER BY start_date ASC, start_time ASC",
        $next_event->id
    ));
    if (class_exists('SC_Ticket')) {
        $w_tickets = SC_Ticket::get_by_event($next_event->id) ?: [];
    }
}
?>

<?php if ($w_workshops): ?>
<!--===== WORKSHOPS =======-->
<section class="w-section">
    <div class="w-section__head">
        <div>
            <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.hands_on_workshops', 'Hands-on workshops')); ?></h2>
            <p style="margin:8px 0 0;font-size:1.0625rem;color:var(--w-text-2)">
                <?php echo esc_html(sc_t('frontend.workshops_lede', 'Limited seats, taught at the bench.')); ?>
            </p>
        </div>
        <a class="w-section__link" href="<?php echo esc_url(home_url('/workshops/')); ?>">
            <?php echo esc_html(sc_t('frontend.see_all', 'See all')); ?>
        </a>
    </div>

    <div class="w-rail">
        <?php foreach ($w_workshops as $w_ws):
            $w_ws_img = !empty($w_ws->featured_image)
                ? (is_numeric($w_ws->featured_image) ? wp_get_attachment_url($w_ws->featured_image) : $w_ws->featured_image)
                : '';
            $w_left = max(0, (int) $w_ws->total_capacity - (int) $w_ws->total_sold);
        ?>
        <a class="w-workshop" href="<?php echo esc_url(home_url('/workshop/' . $w_ws->slug)); ?>">
            <span class="w-workshop__thumb">
                <?php if ($w_ws_img): ?>
                    <img src="<?php echo esc_url($w_ws_img); ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
            </span>
            <span class="w-workshop__body">
                <span class="w-workshop__title"><?php echo esc_html($w_ws->title); ?></span>
                <span class="w-workshop__meta"><?php echo esc_html(date_i18n('j M', strtotime($w_ws->start_date))); ?><?php
                    if (!empty($w_ws->start_time)) { echo ' · ' . esc_html(date_i18n('H:i', strtotime($w_ws->start_time))); }
                ?></span>
                <span class="w-workshop__foot">
                    <?php if ($w_left > 0): ?>
                        <span class="w-tag w-tag--teal"><?php echo esc_html(sprintf(sc_t('frontend.seats_left', '%d seats left'), $w_left)); ?></span>
                    <?php else: ?>
                        <span class="w-tag"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?></span>
                    <?php endif; ?>
                </span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($w_tickets): ?>
<!--===== TICKET PICKER =======-->
<section class="w-section">
    <div class="w-tickets">
        <div class="w-tickets__copy">
            <h2 class="w-tickets__title"><?php echo esc_html(sc_t('frontend.pick_your_ticket', "Pick your ticket. That's it.")); ?></h2>
            <p class="w-tickets__lede"><?php echo esc_html($next_event->title); ?></p>
            <div class="w-tickets__facts">
                <span><?php echo esc_html(date_i18n('j M Y', strtotime($next_event->start_date))); ?></span>
                <?php if (!empty($next_event->venue_name)): ?>
                    <span>· <?php echo esc_html($next_event->venue_name); ?></span>
                <?php endif; ?>
            </div>
            <a class="w-tickets__cta" href="<?php echo esc_url(home_url('/event/' . $next_event->slug)); ?>">
                <?php echo esc_html(sc_t('frontend.register', 'Register')); ?>
            </a>
        </div>

        <div class="w-tickets__list">
            <?php foreach ($w_tickets as $w_tk):
                $w_qty = (int) $w_tk->quantity;
                $w_sold = (int) $w_tk->sold;
                $w_left = $w_qty > 0 ? max(0, $w_qty - $w_sold) : null;
                $w_out = $w_left === 0;
                $w_price = (float) $w_tk->price;
            ?>
            <a class="w-ticket<?php echo $w_out ? ' w-ticket--soldout' : ''; ?>"
               href="<?php echo esc_url(home_url('/event/' . $next_event->slug . '#tickets')); ?>">
                <span>
                    <span class="w-ticket__name"><?php echo esc_html($w_tk->name); ?></span>
                    <?php if ($w_out): ?>
                        <span class="w-ticket__note"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?></span>
                    <?php elseif ($w_left !== null): ?>
                        <span class="w-ticket__note"><?php echo esc_html(sprintf(sc_t('frontend.seats_left', '%d seats left'), $w_left)); ?></span>
                    <?php endif; ?>
                </span>
                <span class="w-ticket__price">
                    <?php echo $w_price > 0 ? esc_html(sc_currency($w_price)) : esc_html(sc_t('frontend.free', 'Free')); ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
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
