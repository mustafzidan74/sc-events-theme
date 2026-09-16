<?php
/**
 * Template Name: Workshops Archive
 * Public Workshops Listing Page
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!class_exists('SC_Workshop')) {
    get_template_part('404');
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';

$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
// Avoids WordPress's own `s` variable, which hands the request to the search
// template and 404s the page. `s` is still read for links already shared.
$search = isset($_GET['workshop_s'])
    ? sanitize_text_field($_GET['workshop_s'])
    : (isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '');
$event_filter = isset($_GET['event']) ? intval($_GET['event']) : 0;
$page = get_query_var('paged') ? get_query_var('paged') : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

$args = array(
    'status'  => array('publish', 'completed'),
    'limit'   => $per_page,
    'offset'  => $offset,
    'orderby' => 'start_date',
    'order'   => 'ASC',
);
if (!empty($search))     $args['search'] = $search;
if ($event_filter > 0)   $args['event_id'] = $event_filter;

$workshops = SC_Workshop::get_all($args);
$total = SC_Workshop::count($args);
$max_pages = (int) ceil($total / $per_page);

// Events for filter dropdown
$all_events = SC_Event::get_all(array(
    'status'  => array('publish', 'completed'),
    'limit'   => 200,
    'orderby' => 'start_date',
    'order'   => 'DESC',
));

get_template_part('template-parts/public/header', 'public');
?>
<?php
/*
 * Redesign workshops listing.
 *
 * Uses the same wide card as the home rail, laid out as a grid instead. The
 * event filter stays a link list so it is shareable and works without script.
 */
$w_base = home_url('/workshops/');
?>
<header class="w-page-head">
    <h1><?php echo esc_html(sc_t('frontend.hands_on_workshops', 'Hands-on workshops')); ?></h1>
    <p><?php echo esc_html(sc_t('frontend.workshops_page_lede', 'Small groups, taught at the bench. Seats are limited and go with the congress ticket.')); ?></p>
</header>

<section class="w-section">
    <div class="w-toolbar">
        <?php if ($all_events): ?>
        <div class="w-filters">
            <a href="<?php echo esc_url($w_base); ?>" style="text-decoration:none">
                <button type="button" aria-pressed="<?php echo $event_filter ? 'false' : 'true'; ?>">
                    <?php echo esc_html(sc_t('frontend.all', 'All')); ?>
                </button>
            </a>
            <?php foreach (array_slice($all_events, 0, 4) as $w_ev): ?>
            <a href="<?php echo esc_url(add_query_arg('event', $w_ev->id, $w_base)); ?>" style="text-decoration:none">
                <button type="button" aria-pressed="<?php echo (int) $event_filter === (int) $w_ev->id ? 'true' : 'false'; ?>">
                    <?php echo esc_html(wp_trim_words($w_ev->title, 3, '')); ?>
                </button>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form class="w-search" method="get" action="<?php echo esc_url($w_base); ?>" role="search">
            <?php if ($event_filter): ?>
                <input type="hidden" name="event" value="<?php echo esc_attr($event_filter); ?>">
            <?php endif; ?>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="color:var(--w-text-3)"></i>
            <input type="search" name="workshop_s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo esc_attr(sc_t('frontend.search_workshops', 'Search workshops…')); ?>"
                   aria-label="<?php echo esc_attr(sc_t('frontend.search_workshops', 'Search workshops…')); ?>">
        </form>
    </div>

    <?php if ($workshops): ?>
    <p class="w-count" style="margin:0 0 var(--w-space-5)">
        <?php printf(esc_html(sc_t('frontend.showing_n_workshops', 'Showing %s workshops')), esc_html(number_format_i18n($total))); ?>
    </p>

    <div class="w-workshops">
        <?php foreach ($workshops as $w_ws):
            $w_img = !empty($w_ws->featured_image)
                ? sc_img($w_ws->featured_image, 'medium', '104px', array(), 400)
                : '';
            $w_left = max(0, (int) $w_ws->total_capacity - (int) $w_ws->total_sold);
        ?>
        <a class="w-workshop" href="<?php echo esc_url(home_url('/workshop/' . $w_ws->slug)); ?>">
            <span class="w-workshop__thumb">
                <?php if ($w_img): ?>
                    <?php echo $w_img; // Built by wp_get_attachment_image(). ?>
                <?php endif; ?>
            </span>
            <span class="w-workshop__body">
                <span class="w-workshop__title"><?php echo esc_html($w_ws->title); ?></span>
                <span class="w-workshop__meta">
                    <?php echo esc_html(date_i18n('j M Y', strtotime($w_ws->start_date))); ?><?php
                    if (!empty($w_ws->start_time)) { echo ' · ' . esc_html(date_i18n('H:i', strtotime($w_ws->start_time))); }
                    ?>
                </span>
                <?php if (!empty($w_ws->venue_name)): ?>
                    <span class="w-workshop__meta"><?php echo esc_html($w_ws->venue_name); ?></span>
                <?php endif; ?>
                <span class="w-workshop__foot">
                    <?php if ($w_left > 0): ?>
                        <span class="w-tag w-tag--teal"><?php echo esc_html(sprintf(sc_t('frontend.seats_left', '%s seats left'), number_format_i18n($w_left))); ?></span>
                    <?php else: ?>
                        <span class="w-tag"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?></span>
                    <?php endif; ?>
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
        <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.no_workshops_found', 'No workshops found')); ?></span>
        <p><?php echo esc_html(sc_t('frontend.try_another_filter', 'Try another filter or clear your search.')); ?></p>
        <a class="w-btn w-btn--outline" href="<?php echo esc_url($w_base); ?>"><?php echo esc_html(sc_t('frontend.all', 'All')); ?></a>
    </div>
    <?php endif; ?>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
