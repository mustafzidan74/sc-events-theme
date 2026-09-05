<?php
/**
 * Event Category Archive Template
 * Public Frontend Category Page - Eventify Design
 *
 * @package sc_events
 * @version 2.0.0
 */

$assets_url = get_template_directory_uri() . '/assets/frontend/';

$term = get_queried_object();
$category_image = get_term_meta($term->term_id, 'etn_cat_image', true);

// Get filters
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Get all categories for filter
$categories = get_terms(array(
    'taxonomy' => 'etn_category',
    'hide_empty' => true
));

// Build query args
$args = array(
    'post_type' => 'etn',
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
    'meta_key' => 'etn_start_date',
    'orderby' => 'meta_value',
    'order' => 'ASC',
    'tax_query' => array(
        array(
            'taxonomy' => 'etn_category',
            'field' => 'term_id',
            'terms' => $term->term_id
        )
    )
);

// Filter by upcoming/past
if ($filter === 'upcoming') {
    $args['meta_query'][] = array(
        'key' => 'etn_start_date',
        'value' => date('Y-m-d'),
        'compare' => '>=',
        'type' => 'DATE'
    );
} elseif ($filter === 'past') {
    $args['meta_query'][] = array(
        'key' => 'etn_start_date',
        'value' => date('Y-m-d'),
        'compare' => '<',
        'type' => 'DATE'
    );
    $args['order'] = 'DESC';
}

// Search filter
if (!empty($search)) {
    $args['s'] = $search;
}

$events = new WP_Query($args);

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!--===== INNER PAGE HEADER =======-->
<div class="inner-page-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 m-auto">
                <div class="heading1 text-center">
                    <h1><?php echo esc_html($term->name); ?></h1>
                    <div class="space20"></div>
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
                    <i class="fa-solid fa-angle-right"></i>
                    <a href="<?php echo esc_url(get_post_type_archive_link('etn')); ?>"><?php esc_html_e('Events', 'sc_events'); ?></a>
                    <i class="fa-solid fa-angle-right"></i>
                    <span><?php echo esc_html($term->name); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<!--===== INNER PAGE HEADER ENDS =======-->

<!--===== FILTER AREA STARTS =======-->
<div class="event-filter-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="filter-wrapper">
                    <div class="row align-items-center g-4">
                        <!-- Filter Buttons -->
                        <div class="col-lg-5 col-md-12">
                            <div class="filter-buttons-group">
                                <a href="<?php echo esc_url(add_query_arg('filter', 'all', remove_query_arg('paged'))); ?>"
                                   class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <?php esc_html_e('All Events', 'sc_events'); ?>
                                </a>
                                <a href="<?php echo esc_url(add_query_arg('filter', 'upcoming', remove_query_arg('paged'))); ?>"
                                   class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                                    <i class="fa-solid fa-clock"></i>
                                    <?php esc_html_e('Upcoming', 'sc_events'); ?>
                                </a>
                                <a href="<?php echo esc_url(add_query_arg('filter', 'past', remove_query_arg('paged'))); ?>"
                                   class="filter-btn <?php echo $filter === 'past' ? 'active' : ''; ?>">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <?php esc_html_e('Past', 'sc_events'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div class="col-lg-3 col-md-6">
                            <div class="category-select-wrapper">
                                <i class="fa-solid fa-folder"></i>
                                <select class="category-select" id="category-filter" onchange="window.location.href=this.value">
                                    <option value="<?php echo esc_url(get_post_type_archive_link('etn')); ?>">
                                        <?php esc_html_e('All Categories', 'sc_events'); ?>
                                    </option>
                                    <?php if (!is_wp_error($categories)):
                                        foreach ($categories as $cat):
                                    ?>
                                    <option value="<?php echo esc_url(get_term_link($cat)); ?>" <?php selected($term->term_id, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                                    </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-lg-4 col-md-6">
                            <form action="" method="get" class="search-form-wrapper">
                                <input type="text" name="s" class="search-input" value="<?php echo esc_attr($search); ?>"
                                       placeholder="<?php esc_attr_e('Search events...', 'sc_events'); ?>">
                                <?php if ($filter !== 'all'): ?>
                                <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                                <?php endif; ?>
                                <button type="submit" class="search-btn">
                                    <i class="fa-solid fa-search"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($search)): ?>
                    <div class="active-filters">
                        <span class="filter-label"><?php esc_html_e('Active filters:', 'sc_events'); ?></span>
                        <span class="filter-tag">
                            <i class="fa-solid fa-search"></i>
                            <?php echo esc_html($search); ?>
                            <a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="remove-filter"><i class="fa-solid fa-times"></i></a>
                        </span>
                        <a href="<?php echo esc_url(get_term_link($term)); ?>" class="clear-all-btn">
                            <i class="fa-solid fa-refresh"></i>
                            <?php esc_html_e('Clear All', 'sc_events'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--===== FILTER AREA ENDS =======-->

<!--===== EVENT AREA STARTS =======-->
<div class="event-team-area">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 text-center">
                <div class="event-count-info mb-4">
                    <h2 class="mb-2"><?php echo esc_html($term->name); ?></h2>
                    <p class="text-muted"><?php printf(_n('Found %d event', 'Found %d events', $events->found_posts, 'sc_events'), $events->found_posts); ?></p>
                </div>
            </div>
        </div>

        <?php if ($events->have_posts()): ?>
        <div class="event-widget-area">
            <?php
            $event_count = 0;
            while ($events->have_posts()): $events->the_post();
                $event_id = get_the_ID();
                $start_date = get_post_meta($event_id, 'etn_start_date', true);
                $end_date = get_post_meta($event_id, 'etn_end_date', true);
                $start_time = get_post_meta($event_id, 'etn_start_time', true);
                $end_time = get_post_meta($event_id, 'etn_end_time', true);
                $location_raw = get_post_meta($event_id, 'etn_event_location', true);
                $location = is_array($location_raw) ? ($location_raw['address'] ?? '') : $location_raw;

                $is_past = strtotime($start_date) < strtotime(date('Y-m-d'));
                $event_count++;

                // Alternate layout
                $is_odd = ($event_count % 2 === 1);

                // Calculate timestamp for countdown
                $event_datetime = $start_date . ' ' . $start_time;
                $event_timestamp = strtotime($event_datetime);

                // Get tickets info
                $total_tickets = intval(get_post_meta($event_id, 'etn_total_avaiilable_tickets', true));
                // Count sold tickets from etn-attendee posts
                $sold_tickets = 0;
                $attendees = get_posts(array(
                    'post_type' => 'etn-attendee',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array('key' => 'etn_event_id', 'value' => $event_id)
                    ),
                    'fields' => 'ids'
                ));
                foreach ($attendees as $att_id) {
                    $qty = (int) get_post_meta($att_id, 'ticket_qty', true);
                    $sold_tickets += $qty > 0 ? $qty : 1;
                }
            ?>
            <div class="row">
                <div class="col-lg-10 m-auto">
                    <div class="event2-boxarea box1 <?php echo $is_past ? 'past-event' : ''; ?> <?php echo $is_odd ? 'event-row-normal' : ''; ?>">
                        <h1 class="active"><?php echo str_pad($event_count, 2, '0', STR_PAD_LEFT); ?></h1>
                        <div class="row align-items-center">
                            <div class="col-lg-6">
                                <div class="img1">
                                    <?php if (has_post_thumbnail()): ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium_large', array('alt' => get_the_title())); ?>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <img src="<?php echo esc_url($assets_url); ?>img/all-images/event/event-img4.png" alt="<?php the_title_attribute(); ?>">
                                    </a>
                                    <?php endif; ?>
                                    <span class="event-status-badge <?php echo $is_past ? 'past' : 'upcoming'; ?>"><?php echo $is_past ? esc_html__('Past', 'sc_events') : esc_html__('Upcoming', 'sc_events'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="content-area">
                                    <a href="<?php the_permalink(); ?>" class="head"><?php the_title(); ?></a>
                                    <div class="space16"></div>
                                    <ul class="event-meta-list">
                                        <li>
                                            <i class="fa-regular fa-calendar"></i>
                                            <?php
                                            if ($end_date && $end_date !== $start_date) {
                                                echo esc_html(date_i18n('d M', strtotime($start_date)) . ' - ' . date_i18n('d M, Y', strtotime($end_date)));
                                            } else {
                                                echo esc_html(date_i18n('d M, Y', strtotime($start_date)));
                                            }
                                            ?>
                                        </li>
                                        <li>
                                            <i class="fa-regular fa-clock"></i>
                                            <?php echo esc_html($start_time); ?>
                                            <?php if ($end_time): ?> - <?php echo esc_html($end_time); ?><?php endif; ?>
                                        </li>
                                        <li>
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?php echo esc_html($location ? wp_trim_words($location, 5) : __('Online', 'sc_events')); ?>
                                        </li>
                                        <?php if ($total_tickets > 0): ?>
                                        <li>
                                            <i class="fa-solid fa-ticket"></i>
                                            <?php printf(__('%d / %d Sold', 'sc_events'), $sold_tickets, $total_tickets); ?>
                                        </li>
                                        <?php endif; ?>
                                    </ul>

                                    <?php if (!$is_past): ?>
                                    <div class="space20"></div>
                                    <div class="event-countdown-mini" data-timestamp="<?php echo esc_attr($event_timestamp); ?>">
                                        <div class="countdown-item">
                                            <span class="countdown-value days">00</span>
                                            <span class="countdown-label"><?php esc_html_e('Days', 'sc_events'); ?></span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value hours">00</span>
                                            <span class="countdown-label"><?php esc_html_e('Hours', 'sc_events'); ?></span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value minutes">00</span>
                                            <span class="countdown-label"><?php esc_html_e('Min', 'sc_events'); ?></span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value seconds">00</span>
                                            <span class="countdown-label"><?php esc_html_e('Sec', 'sc_events'); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="space20"></div>
                                    <div class="btn-area1">
                                        <a href="<?php the_permalink(); ?>" class="vl-btn1">
                                            <span class="demo"><?php echo $is_past ? esc_html__('View Details', 'sc_events') : esc_html__('Purchase Ticket Now', 'sc_events'); ?></span>
                                        </a>
                                    </div>
                                </div>
                                <div class="space30 d-lg-none d-block"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space48"></div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <!-- Pagination -->
        <?php if ($events->max_num_pages > 1): ?>
        <div class="space60"></div>
        <div class="pagination-area">
            <nav aria-label="Page navigation">
                <?php
                $pagination = paginate_links(array(
                    'total' => $events->max_num_pages,
                    'current' => max(1, get_query_var('paged')),
                    'prev_text' => '<i class="fa-solid fa-angle-left"></i>',
                    'next_text' => '<i class="fa-solid fa-angle-right"></i>',
                    'type' => 'array'
                ));

                if ($pagination):
                ?>
                <ul class="pagination justify-content-center">
                    <?php foreach ($pagination as $page): ?>
                    <li class="page-item <?php echo strpos($page, 'current') !== false ? 'active' : ''; ?>">
                        <?php echo str_replace('page-numbers', 'page-link', $page); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No Events Found -->
        <div class="row">
            <div class="col-lg-8 m-auto">
                <div class="no-events-found text-center">
                    <i class="fa-regular fa-calendar-xmark fa-4x text-muted mb-4"></i>
                    <h3><?php esc_html_e('No Events Found', 'sc_events'); ?></h3>
                    <p class="text-muted mb-4"><?php esc_html_e('There are no events in this category at the moment. Check back later!', 'sc_events'); ?></p>
                    <a href="<?php echo esc_url(get_post_type_archive_link('etn')); ?>" class="vl-btn1">
                        <span class="demo"><?php esc_html_e('View All Events', 'sc_events'); ?></span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<!--===== EVENT AREA ENDS =======-->

<style>
/* Archive Page Custom Styles */
.event2-boxarea .img1 {
    position: relative;
    overflow: hidden;
    border-radius: 10px;
}
.event2-boxarea .img1 img {
    width: 100%;
    height: 300px;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.event2-boxarea:hover .img1 img {
    transform: scale(1.05);
}
.past-event {
    opacity: 0.85;
}
.past-event .img1 img {
    filter: grayscale(30%);
}
.event-count-info h2 {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a2e;
}
.event-count-info p {
    font-size: 16px;
}

/* Filter Section Styles */
.event-filter-section {
    padding: 40px 0;
    background: #fff;
}
.filter-wrapper {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    padding: 30px 35px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.06);
}
.filter-buttons-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    background: #fff;
    color: #555;
    border: 2px solid #e9ecef;
}
.filter-btn:hover {
    background: #f8f9fa;
    border-color: var(--ztc-text-text-11);
    color: var(--ztc-text-text-11);
}
.filter-btn.active {
    background: var(--ztc-text-text-11);
    border-color: var(--ztc-text-text-11);
    color: #fff;
}
.filter-btn i {
    font-size: 14px;
}
.category-select-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.category-select-wrapper > i {
    position: absolute;
    left: 18px;
    color: var(--ztc-text-text-11);
    font-size: 14px;
    z-index: 1;
}
.category-select {
    width: 100%;
    padding: 14px 20px 14px 45px;
    border: 2px solid #e9ecef;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 500;
    color: #555;
    background: #fff;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 18px center;
    transition: all 0.3s ease;
}
.category-select:focus {
    outline: none;
    border-color: var(--ztc-text-text-11);
}
.nice-select.category-select {
    display: flex;
    align-items: center;
}
.search-form-wrapper {
    display: flex;
    border-radius: 50px;
    overflow: hidden;
    border: 2px solid #e9ecef;
    background: #fff;
    transition: all 0.3s ease;
}
.search-form-wrapper:focus-within {
    border-color: var(--ztc-text-text-11);
}
.search-input {
    flex: 1;
    padding: 14px 20px;
    border: none;
    font-size: 14px;
    background: transparent;
}
.search-input:focus {
    outline: none;
}
.search-btn {
    padding: 14px 22px;
    background: var(--ztc-text-text-11);
    border: none;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
}
.search-btn:hover {
    background: var(--ztc-bg-bg-9);
}
.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}
.filter-label {
    color: #888;
    font-size: 14px;
}
.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--ztc-text-text-11);
    color: #fff;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 500;
}
.filter-tag i {
    font-size: 12px;
    opacity: 0.8;
}
.remove-filter {
    color: #fff;
    opacity: 0.8;
    margin-left: 4px;
    transition: opacity 0.3s;
}
.remove-filter:hover {
    opacity: 1;
    color: #fff;
}
.clear-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    color: #888;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid #ddd;
}
.clear-all-btn:hover {
    background: #f8f9fa;
    color: #555;
    border-color: #ccc;
}

.no-events-found {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 60px 30px;
}
.pagination .page-item .page-link {
    color: #333;
    border: none;
    margin: 0 5px;
    border-radius: 5px;
    padding: 10px 18px;
}
.pagination .page-item.active .page-link,
.pagination .page-item .page-link:hover {
    background: var(--ztc-text-text-11);
    color: #fff;
}

@media (max-width: 991px) {
    .filter-buttons-group {
        justify-content: center;
        margin-bottom: 10px;
    }
}
@media (max-width: 767px) {
    .filter-wrapper {
        padding: 20px;
    }
    .filter-btn {
        padding: 10px 16px;
        font-size: 13px;
    }
    .active-filters {
        justify-content: center;
    }
    .event2-boxarea .img1 img {
        height: 220px;
    }
    .content-area .head {
        font-size: 20px;
    }
}
</style>

<script>
// Countdown Timer for Events
document.addEventListener('DOMContentLoaded', function() {
    var countdowns = document.querySelectorAll('.event-countdown-mini');

    countdowns.forEach(function(countdown) {
        var timestamp = parseInt(countdown.getAttribute('data-timestamp')) * 1000;

        function updateCountdown() {
            var now = new Date().getTime();
            var distance = timestamp - now;

            if (distance < 0) {
                countdown.innerHTML = '<div class="event-ended-info"><i class="fa-solid fa-check-circle"></i> <?php echo esc_js(__("Event Started", "sc_events")); ?></div>';
                return;
            }

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdown.querySelector('.days').textContent = days.toString().padStart(2, '0');
            countdown.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
            countdown.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
            countdown.querySelector('.seconds').textContent = seconds.toString().padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    });
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
