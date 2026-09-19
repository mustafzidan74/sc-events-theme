<?php
/**
 * Template Name: Events Archive
 * Events Archive Template - Dark & Premium
 * Public Frontend Events Listing Page
 *
 * @package sc_events
 * @version 5.0.0
 */

// Load Custom Tables
if (!class_exists('SC_Event')) {
    get_template_part('404');
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Get filter parameters. With no choice in the address the page opens on what is
// coming up; "All" is its own address (?filter=all).
$filter_param = isset($_GET['filter']) ? sanitize_key(wp_unslash($_GET['filter'])) : '';
$filter = in_array($filter_param, array('all', 'upcoming', 'past'), true) ? $filter_param : 'upcoming';
// `s` is WordPress's own search variable: sending it here hands the request to
// the search template and the page 404s. The form submits `event_s` instead;
// `s` is still read so any link already shared with it keeps working.
$search = isset($_GET['event_s'])
    ? sanitize_text_field($_GET['event_s'])
    : (isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '');
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = get_query_var('paged') ? get_query_var('paged') : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Get categories for filter dropdown (cached)
$event_categories = function_exists('sc_get_cached_event_categories') ? sc_get_cached_event_categories(50) : get_terms(array(
    'taxonomy' => 'sc_event_category',
    'hide_empty' => false,
));
if (is_wp_error($event_categories)) {
    $event_categories = array();
}

// Nothing coming up and nothing chosen: show everything rather than an empty page.
if ($filter_param === '' && $filter === 'upcoming' && !SC_Event::count(array('status' => array('publish', 'completed'), 'upcoming_only' => true))) {
    $filter = 'all';
}

// Build query args for SC_Event::get_all()
$args = array(
    'status'  => array('publish', 'completed'),
    'limit'   => $per_page,
    'offset'  => $offset,
    'orderby' => 'start_date',
    'order'   => 'ASC',
);

// Filter by upcoming/past
if ($filter === 'upcoming') {
    $args['upcoming_only'] = true;
} elseif ($filter === 'past') {
    $args['past_only'] = true;
    $args['order'] = 'DESC';
}

// Search
if (!empty($search)) {
    $args['search'] = $search;
}

// Category filter via custom junction table
if ($category_filter > 0) {
    $args['category_id'] = $category_filter;
}

// Get events from Custom Tables
$events = SC_Event::get_all($args);

// Get total count for pagination
$count_args = array('status' => array('publish', 'completed'));
if ($filter === 'upcoming') {
    $count_args['upcoming_only'] = true;
} elseif ($filter === 'past') {
    $count_args['past_only'] = true;
}
if (!empty($search)) {
    $count_args['search'] = $search;
}
if (!empty($args['category_id'])) {
    $count_args['category_id'] = $args['category_id'];
}
$total = SC_Event::count($count_args);
$max_pages = ceil($total / $per_page);

// Batch-load tickets for all events to avoid N+1 queries
$event_tickets_map = array();
if (!empty($events) && class_exists('SC_Ticket')) {
    $event_ids = array_map(function($e) { return $e->id; }, $events);
    foreach ($event_ids as $eid) {
        $event_tickets_map[$eid] = SC_Ticket::get_by_event($eid, array('is_active' => null));
    }
}

// Batch-load session counts (if sessions module active)
$event_session_counts = array();
if (!empty($events) && class_exists('SC_Session_Attendance')) {
    $event_ids_for_sessions = isset($event_ids) ? $event_ids : array_map(function($e) { return $e->id; }, $events);
    $event_session_counts = SC_Session_Attendance::get_session_counts_for_events($event_ids_for_sessions);
}

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<?php
/*
 * Redesign events listing.
 *
 * Reuses the card and filter pills built for the home page, so a listing and
 * the home read as the same system. Filters stay as links rather than script,
 * which keeps them shareable, crawlable and working without JavaScript.
 */
$w_base = home_url('/events/');
$w_filters = [
    'all'      => sc_t('frontend.all', 'All'),
    'upcoming' => sc_t('frontend.upcoming', 'Upcoming'),
    'past'     => sc_t('frontend.past', 'Past'),
];
?>
<header class="w-page-head">
    <h1><?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?></h1>
    <p><?php echo esc_html(sc_t('frontend.events_lede', 'Congresses, workshops and CME courses for dentists across Egypt and MENA.')); ?></p>
</header>

<section class="w-section">
    <div class="w-toolbar">
        <div class="w-filters">
            <?php foreach ($w_filters as $w_key => $w_label):
                $w_href = add_query_arg('filter', $w_key, $w_base);
                if (!empty($search)) { $w_href = add_query_arg("event_s", $search, $w_href); }
            ?>
            <a href="<?php echo esc_url($w_href); ?>" style="text-decoration:none">
                <button type="button" aria-pressed="<?php echo $filter === $w_key ? 'true' : 'false'; ?>">
                    <?php echo esc_html($w_label); ?>
                </button>
            </a>
            <?php endforeach; ?>
        </div>

        <form class="w-search" method="get" action="<?php echo esc_url($w_base); ?>" role="search">
            <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="color:var(--w-text-3)"></i>
            <input type="search" name="event_s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo esc_attr(sc_t('frontend.search_events', 'Search events…')); ?>"
                   aria-label="<?php echo esc_attr(sc_t('frontend.search_events', 'Search events…')); ?>">
        </form>
    </div>

    <?php if ($events): ?>
    <p class="w-count" style="margin:0 0 var(--w-space-5)">
        <?php printf(esc_html(sc_t('frontend.showing_n_events', 'Showing %s events')), esc_html(number_format_i18n($total))); ?>
    </p>

    <div class="w-events">
        <?php foreach ($events as $w_ev):
            $w_s = strtotime($w_ev->start_date);
            $w_e = !empty($w_ev->end_date) ? strtotime($w_ev->end_date) : $w_s;
            $w_same = date('Y-m', $w_s) === date('Y-m', $w_e);
            $w_big = $w_s === $w_e
                ? date_i18n('j', $w_s)
                : ($w_same ? date_i18n('j', $w_s) . '–' . date_i18n('j', $w_e)
                           : date_i18n('j M', $w_s) . ' – ' . date_i18n('j M', $w_e));
            $w_small = $w_same ? date_i18n('M Y', $w_s) : date_i18n('Y', $w_e);
            $w_img = $w_ev->featured_image ? sc_img($w_ev->featured_image, 'large', '(max-width: 767px) calc(100vw - 40px), 1280px', array('class' => 'w-event__cover')) : '';
            $w_past = $w_e < current_time('timestamp');
        ?>
        <a class="w-event<?php echo $w_img ? '' : ' w-event--empty'; ?>" href="<?php echo esc_url(home_url('/event/' . $w_ev->slug)); ?>">
            <?php if ($w_img): ?>
                <?php echo $w_img; // Built by wp_get_attachment_image(). ?>
            <?php endif; ?>
            <span class="w-event__body">
                <span class="w-event__badges">
                    <?php if ($w_past): ?>
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
                        <span class="w-event__date"><?php echo esc_html($w_big); ?> <span><?php echo esc_html($w_small); ?></span></span>
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

    <?php if ($max_pages > 1):
        $w_links = paginate_links([
            'total'     => $max_pages,
            'current'   => max(1, get_query_var('paged') ?: 1),
            'type'      => 'array',
            'prev_text' => '‹',
            'next_text' => '›',
        ]);
    ?>
    <nav class="w-pagination" aria-label="<?php esc_attr_e('Pagination', 'sc_events'); ?>">
        <?php foreach ((array) $w_links as $w_link) { echo wp_kses_post($w_link); } ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="w-empty">
        <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.no_events_found', 'No events found')); ?></span>
        <p><?php echo esc_html(sc_t('frontend.try_another_filter', 'Try another filter or clear your search.')); ?></p>
        <a class="w-btn w-btn--outline" href="<?php echo esc_url(add_query_arg('filter', 'all', $w_base)); ?>"><?php echo esc_html(sc_t('frontend.all', 'All')); ?></a>
    </div>
    <?php endif; ?>
</section>

<!-- Archive Events Styles -->
<link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/archive-events.css?v=<?php echo esc_attr(wp_get_theme()->get('Version')); ?>" media="all">

<!-- Event Countdown Timer -->
<script src="<?php echo esc_url($assets_url); ?>js/event-countdown.js"></script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
