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

// Get filter parameters
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
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
<section class="sc-page-header">
    <div class="container">
        <nav class="sc-breadcrumb" data-aos="fade-up">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sc_t('frontend.home', 'Home')); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></span>
        </nav>
        <h1 data-aos="fade-up" data-aos-delay="100">
            <?php
            if ($filter === 'upcoming') {
                echo esc_html(sc_t('frontend.upcoming_events', 'Upcoming Events'));
            } elseif ($filter === 'past') {
                echo esc_html(sc_t('frontend.past_events', 'Past Events'));
            } else {
                echo esc_html(sc_t('frontend.all_events', 'All Events'));
            }
            ?>
        </h1>
        <p data-aos="fade-up" data-aos-delay="150"><?php echo esc_html(sc_t('frontend.events_subtitle', 'Discover conferences, workshops, and networking opportunities')); ?></p>
    </div>
</section>

<!-- Filter Bar -->
<section class="sc-section" style="padding-top: var(--sc-space-8); padding-bottom: 0;">
    <div class="container">
        <div class="sc-filter-bar glass-card-static" data-aos="fade-up">
            <div class="sc-filter-row">
                <!-- Filter Pills -->
                <div class="sc-filter-pills">
                    <a href="<?php echo esc_url(add_query_arg('filter', 'all', remove_query_arg('paged'))); ?>"
                       class="sc-filter-pill <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                        <?php echo esc_html(sc_t('frontend.all', 'All')); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('filter', 'upcoming', remove_query_arg('paged'))); ?>"
                       class="sc-filter-pill <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clock"></i>
                        <?php echo esc_html(sc_t('frontend.upcoming', 'Upcoming')); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('filter', 'past', remove_query_arg('paged'))); ?>"
                       class="sc-filter-pill <?php echo $filter === 'past' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-check-circle"></i>
                        <?php echo esc_html(sc_t('frontend.past', 'Past')); ?>
                    </a>
                </div>

                <!-- Category Dropdown -->
                <?php if (!empty($event_categories)): ?>
                <div class="sc-filter-select">
                    <select class="sc-select" id="category-filter" onchange="window.location.href=this.value">
                        <option value="<?php echo esc_url(remove_query_arg(array('category', 'paged'))); ?>"><?php echo esc_html(sc_t('frontend.all_categories', 'All Categories')); ?></option>
                        <?php foreach ($event_categories as $cat): ?>
                        <option value="<?php echo esc_url(add_query_arg('category', $cat->term_id, remove_query_arg('paged'))); ?>" <?php selected($category_filter, $cat->term_id); ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Search -->
                <div class="sc-filter-search">
                    <form action="" method="get" class="sc-search-form">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php echo esc_attr(sc_t('frontend.search_events', 'Search events...')); ?>">
                        <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <?php endif; ?>
                        <?php if ($category_filter > 0): ?>
                        <input type="hidden" name="category" value="<?php echo esc_attr($category_filter); ?>">
                        <?php endif; ?>
                        <button type="submit"><?php echo esc_html(sc_t('frontend.search', 'Search')); ?></button>
                    </form>
                </div>
            </div>

            <!-- Active Filters -->
            <?php if (!empty($search) || $category_filter > 0): ?>
            <div class="sc-active-filters">
                <span class="sc-active-filters-label"><?php echo esc_html(sc_t('frontend.active', 'Active:')); ?></span>
                <?php if (!empty($search)): ?>
                <span class="sc-filter-tag">
                    <i class="fa-solid fa-search"></i>
                    <?php echo esc_html($search); ?>
                    <a href="<?php echo esc_url(remove_query_arg('s')); ?>"><i class="fa-solid fa-xmark"></i></a>
                </span>
                <?php endif; ?>
                <?php if ($category_filter > 0):
                    $active_cat = get_term($category_filter, 'sc_event_category');
                    if ($active_cat && !is_wp_error($active_cat)):
                ?>
                <span class="sc-filter-tag">
                    <i class="fa-solid fa-tag"></i>
                    <?php echo esc_html($active_cat->name); ?>
                    <a href="<?php echo esc_url(remove_query_arg('category')); ?>"><i class="fa-solid fa-xmark"></i></a>
                </span>
                <?php endif; endif; ?>
                <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-filter-clear">
                    <i class="fa-solid fa-rotate-right"></i>
                    <?php echo esc_html(sc_t('frontend.clear_all', 'Clear All')); ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Results Count -->
            <div class="sc-results-count">
                <?php printf(esc_html(sc_t('frontend.showing_events', 'Showing %d events')), $total); ?>
            </div>
        </div>
    </div>
</section>

<!-- Events Grid -->
<section class="sc-section">
    <div class="container">
        <?php if (!empty($events)): ?>
        <div class="row g-4">
            <?php
            $delay = 0;
            foreach ($events as $event):
                $event_id = $event->id;
                $start_date = $event->start_date;
                $end_date = $event->end_date;
                $start_time = $event->start_time;
                $end_time = $event->end_time;

                // Build location string
                $location = '';
                if (!empty($event->venue_name)) {
                    $location = $event->venue_name;
                } elseif (!empty($event->venue_address)) {
                    $location = $event->venue_address;
                } elseif ($event->location_type === 'online') {
                    $location = sc_t('frontend.online', 'Online');
                }

                $ev_end_dt = ($end_date ?: $start_date) . ($end_time ? ' ' . $end_time : ' 23:59:59');
                $is_past = strtotime($ev_end_dt) < time();

                // Get featured image
                $thumbnail_url = '';
                if ($event->featured_image) {
                    $thumbnail_url = wp_get_attachment_image_url($event->featured_image, 'medium_large');
                }
                if (!$thumbnail_url && $event->banner_image) {
                    $thumbnail_url = wp_get_attachment_image_url($event->banner_image, 'medium_large');
                }

                // Event URL
                $event_url = home_url('/event/' . $event->slug);

                // Get tickets for price (from batch-loaded map)
                $tickets = isset($event_tickets_map[$event_id]) ? $event_tickets_map[$event_id] : array();
                $min_price = 0;
                $is_free = true;
                if (!empty($tickets)) {
                    foreach ($tickets as $ticket) {
                        $price = floatval($ticket->price ?? 0);
                        if ($price > 0) {
                            $is_free = false;
                            if ($min_price == 0 || $price < $min_price) $min_price = $price;
                        }
                    }
                }

                // Event branding
                $event_bg = $event->calendar_bg_color;
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <a href="<?php echo esc_url($event_url); ?>" class="sc-event-card glass-card">
                    <div class="sc-event-card-image">
                        <?php if ($thumbnail_url): ?>
                        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($event->title); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="sc-event-card-placeholder">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <?php endif; ?>
                        <div class="sc-event-card-overlay"></div>

                        <!-- Date Badge -->
                        <div class="sc-event-card-date" <?php if ($event_bg): ?>style="background: <?php echo esc_attr($event_bg); ?>"<?php endif; ?>>
                            <span class="sc-event-card-day"><?php echo esc_html(date('d', strtotime($start_date))); ?></span>
                            <span class="sc-event-card-month"><?php echo esc_html(date_i18n('M', strtotime($start_date))); ?></span>
                        </div>

                        <!-- Status Badge -->
                        <div class="sc-event-card-status <?php echo $is_past ? 'past' : 'upcoming'; ?>">
                            <?php echo $is_past ? esc_html(sc_t('frontend.past', 'Past')) : esc_html(sc_t('frontend.upcoming', 'Upcoming')); ?>
                        </div>

                        <!-- Favorite Button -->
                        <button class="sc-favorite-btn" data-event-id="<?php echo esc_attr($event_id); ?>" title="<?php echo esc_attr(sc_t('frontend.add_to_favorites', 'Add to favorites')); ?>" aria-label="<?php echo esc_attr(sc_t('frontend.toggle_favorite', 'Toggle favorite')); ?>">
                            <i class="fa-regular fa-heart"></i>
                            <i class="fa-solid fa-heart"></i>
                        </button>
                    </div>

                    <div class="sc-event-card-body">
                        <h3 class="sc-event-card-title"><?php echo esc_html($event->title); ?></h3>

                        <div class="sc-event-card-meta">
                            <span>
                                <i class="fa-regular fa-clock"></i>
                                <?php
                                if ($start_time) {
                                    echo esc_html(date('g:i A', strtotime($start_time)));
                                } else {
                                    echo esc_html(sc_t('frontend.tba', 'TBA'));
                                }
                                ?>
                            </span>
                            <span>
                                <i class="fa-solid fa-location-dot"></i>
                                <?php echo esc_html($location ? wp_trim_words($location, 4) : sc_t('frontend.tba', 'TBA')); ?>
                            </span>
                            <?php
                            $sc_session_count = isset($event_session_counts[$event_id]) ? $event_session_counts[$event_id] : 0;
                            if ($sc_session_count > 0):
                            ?>
                            <span>
                                <i class="fa-solid fa-chalkboard-user"></i>
                                <?php echo esc_html($sc_session_count . ' ' . ($sc_session_count === 1 ? sc_t('frontend.session', 'Session') : sc_t('frontend.sessions', 'Sessions'))); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="sc-event-card-footer">
                            <span class="sc-event-card-price">
                                <?php if ($is_free): ?>
                                    <?php echo esc_html(sc_t('frontend.free', 'Free')); ?>
                                <?php else: ?>
                                    <?php echo esc_html(sc_t('frontend.from', 'From')); ?> <?php echo esc_html(number_format($min_price)); ?> <?php echo esc_html(sc_t('general.currency_symbol', 'EGP')); ?>
                                <?php endif; ?>
                            </span>
                            <span class="sc-event-card-arrow">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php $delay += 80; if ($delay > 320) $delay = 0; endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($max_pages > 1): ?>
        <div class="sc-pagination" data-aos="fade-up">
            <?php
            $pagination = paginate_links(array(
                'total' => $max_pages,
                'current' => $page,
                'prev_text' => '<i class="fa-solid fa-chevron-left"></i>',
                'next_text' => '<i class="fa-solid fa-chevron-right"></i>',
                'type' => 'array'
            ));

            if ($pagination):
                foreach ($pagination as $page_link):
                    $is_current = strpos($page_link, 'current') !== false;
            ?>
                <span class="<?php echo $is_current ? 'active' : ''; ?>"><?php echo $page_link; ?></span>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No Events Found -->
        <div class="sc-empty-state" data-aos="fade-up">
            <div class="sc-empty-icon">
                <i class="fa-regular fa-calendar-xmark"></i>
            </div>
            <h3><?php echo esc_html(sc_t('frontend.no_events_found', 'No Events Found')); ?></h3>
            <p><?php echo esc_html(sc_t('frontend.no_events_message', 'We couldn\'t find any events matching your criteria. Try adjusting your filters or check back later.')); ?></p>
            <?php if ($filter !== 'all' || !empty($search) || $category_filter > 0): ?>
            <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-outline">
                <i class="fa-solid fa-rotate-right"></i>
                <?php echo esc_html(sc_t('frontend.clear_filters', 'Clear Filters')); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Archive Events Styles -->
<link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/archive-events.css?v=<?php echo esc_attr(wp_get_theme()->get('Version')); ?>" media="all">

<!-- Event Countdown Timer -->
<script src="<?php echo esc_url($assets_url); ?>js/event-countdown.js"></script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
