<?php
/**
 * Event Category Taxonomy Template - Dark & Premium
 * Custom Tables Version
 *
 * @package sc_events
 * @version 5.1.0
 */

// Load Custom Tables
if (!class_exists('SC_Event')) {
    get_template_part('404');
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Get category term from query
$term = get_queried_object();
$category_name = $term ? $term->name : __('Events', 'sc_events');
$category_description = $term ? $term->description : '';

// Get filter parameters
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$page = get_query_var('paged') ? get_query_var('paged') : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Build query args for SC_Event::get_all()
$args = array(
    'status'  => 'publish',
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
if ($term) {
    $args['category_id'] = $term->term_id;
}

// Get events from Custom Tables
$events = SC_Event::get_all($args);

// Get total count for pagination
$count_args = array('status' => 'publish');
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

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<section class="sc-page-header">
    <div class="container">
        <nav class="sc-breadcrumb" data-aos="fade-up">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="<?php echo esc_url(home_url('/events/')); ?>"><?php esc_html_e('Events', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php echo esc_html($category_name); ?></span>
        </nav>
        <h1 data-aos="fade-up" data-aos-delay="100"><?php echo esc_html($category_name); ?></h1>
        <?php if (!empty($category_description)): ?>
        <p data-aos="fade-up" data-aos-delay="150"><?php echo esc_html($category_description); ?></p>
        <?php endif; ?>
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
                        <?php esc_html_e('All', 'sc_events'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('filter', 'upcoming', remove_query_arg('paged'))); ?>"
                       class="sc-filter-pill <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clock"></i>
                        <?php esc_html_e('Upcoming', 'sc_events'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('filter', 'past', remove_query_arg('paged'))); ?>"
                       class="sc-filter-pill <?php echo $filter === 'past' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-check-circle"></i>
                        <?php esc_html_e('Past', 'sc_events'); ?>
                    </a>
                </div>

                <!-- Search -->
                <div class="sc-filter-search">
                    <form action="" method="get" class="sc-search-form">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php esc_attr_e('Search events...', 'sc_events'); ?>">
                        <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <?php endif; ?>
                        <button type="submit"><?php esc_html_e('Search', 'sc_events'); ?></button>
                    </form>
                </div>
            </div>

            <!-- Active Filters -->
            <?php if (!empty($search)): ?>
            <div class="sc-active-filters">
                <span class="sc-active-filters-label"><?php esc_html_e('Active:', 'sc_events'); ?></span>
                <span class="sc-filter-tag">
                    <i class="fa-solid fa-search"></i>
                    <?php echo esc_html($search); ?>
                    <a href="<?php echo esc_url(remove_query_arg('s')); ?>"><i class="fa-solid fa-xmark"></i></a>
                </span>
            </div>
            <?php endif; ?>

            <!-- Results Count -->
            <div class="sc-results-count">
                <?php printf(esc_html__('Showing %d events', 'sc_events'), $total); ?>
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
                    $location = __('Online', 'sc_events');
                }

                $is_past = strtotime($start_date) < strtotime(date('Y-m-d'));

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

                // Get tickets for price
                $tickets = SC_Ticket::get_by_event($event_id, array('is_active' => null));
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
                            <?php echo $is_past ? esc_html__('Past', 'sc_events') : esc_html__('Upcoming', 'sc_events'); ?>
                        </div>
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
                                    esc_html_e('TBA', 'sc_events');
                                }
                                ?>
                            </span>
                            <span>
                                <i class="fa-solid fa-location-dot"></i>
                                <?php echo esc_html($location ? wp_trim_words($location, 4) : __('TBA', 'sc_events')); ?>
                            </span>
                        </div>

                        <div class="sc-event-card-footer">
                            <span class="sc-event-card-price">
                                <?php if ($is_free): ?>
                                    <?php esc_html_e('Free', 'sc_events'); ?>
                                <?php else: ?>
                                    <?php esc_html_e('From', 'sc_events'); ?> <?php echo esc_html(number_format($min_price)); ?> EGP
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
            <h3><?php esc_html_e('No Events Found', 'sc_events'); ?></h3>
            <p><?php esc_html_e('There are no events in this category at the moment. Check back later!', 'sc_events'); ?></p>
            <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-outline">
                <i class="fa-solid fa-arrow-left"></i>
                <?php esc_html_e('View All Events', 'sc_events'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Archive Events Styles -->
<link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/archive-events.css?v=<?php echo esc_attr(wp_get_theme()->get('Version')); ?>" media="all">

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
