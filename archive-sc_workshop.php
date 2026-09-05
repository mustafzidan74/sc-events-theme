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
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
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

<section class="sc-section sc-section-alt" style="padding-top: var(--sc-space-12);">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <p style="margin-bottom: var(--sc-space-2);">
                <a href="<?php echo esc_url(home_url('/')); ?>" style="color: var(--sc-text-muted);"><?php echo esc_html(sc_t('frontend.home', 'Home')); ?></a>
                &raquo; <?php echo esc_html(sc_t('frontend.workshops', 'Workshops')); ?>
            </p>
            <h1><?php echo esc_html(sc_t('frontend.all_workshops', 'All Workshops')); ?></h1>
            <p><?php echo esc_html(sc_t('frontend.workshops_subtitle', 'Hands-on sessions and skill-building experiences')); ?></p>
        </div>

        <div class="row mb-4 g-3" data-aos="fade-up">
            <div class="col-md-6">
                <form method="get" action="">
                    <div class="input-group">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" class="form-control sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.search_workshops', 'Search workshops...')); ?>">
                        <button type="submit" class="sc-btn sc-btn-gold"><i class="fa-solid fa-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-6">
                <form method="get" action="" id="event-filter-form">
                    <?php if (!empty($search)): ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>"><?php endif; ?>
                    <select class="form-control sc-input" name="event" onchange="document.getElementById('event-filter-form').submit();">
                        <option value="0"><?php echo esc_html(sc_t('frontend.all_events', 'All Events')); ?></option>
                        <?php foreach ($all_events as $e): ?>
                        <option value="<?php echo (int) $e->id; ?>" <?php selected($event_filter, (int) $e->id); ?>><?php echo esc_html($e->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if (empty($workshops)): ?>
            <div class="text-center" data-aos="fade-up" style="padding: var(--sc-space-12) 0;">
                <i class="fa-solid fa-flask-vial" style="font-size: 64px; color: var(--sc-text-muted); margin-bottom: var(--sc-space-4);"></i>
                <h3><?php echo esc_html(sc_t('frontend.no_workshops_found', 'No workshops found')); ?></h3>
                <p class="sc-text-muted"><?php echo esc_html(sc_t('frontend.no_workshops_match', 'Try adjusting your filters or check back later.')); ?></p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($workshops as $w):
                    $event_title = SC_Workshop::get_event_title($w->event_id);
                    $img = $w->featured_image ? wp_get_attachment_url($w->featured_image) : '';
                    $url = home_url('/workshop/' . $w->slug . '/');
                    $tickets = SC_Ticket::get_by_workshop($w->id, array('is_active' => 1));
                    $min_price = null; $is_free = true;
                    foreach ($tickets as $t) {
                        $p = (float) $t->price;
                        if ($p > 0) { $is_free = false; if ($min_price === null || $p < $min_price) $min_price = $p; }
                    }
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up">
                    <a href="<?php echo esc_url($url); ?>" class="sc-event-card glass-card-static" style="display: block; text-decoration: none; height: 100%; overflow: hidden; border-radius: var(--sc-radius-lg);">
                        <div class="sc-event-card-image" style="height: 200px; overflow: hidden; background: var(--sc-bg-subtle);">
                            <?php if ($img): ?>
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($w->title); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-flask-vial" style="font-size: 48px; color: var(--sc-text-muted);"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="padding: var(--sc-space-5);">
                            <?php if ($event_title): ?>
                            <small class="sc-text-muted"><i class="fa-regular fa-calendar"></i> <?php echo esc_html($event_title); ?></small>
                            <?php endif; ?>
                            <h4 style="margin: var(--sc-space-2) 0; color: var(--sc-text-primary);"><?php echo esc_html($w->title); ?></h4>
                            <p class="sc-text-muted" style="margin-bottom: var(--sc-space-3);">
                                <i class="fa-regular fa-calendar"></i> <?php echo esc_html(date_i18n('M j, Y', strtotime($w->start_date))); ?>
                                <?php if ($w->venue_name): ?>
                                    &middot; <i class="fa-solid fa-location-dot"></i> <?php echo esc_html($w->venue_name); ?>
                                <?php endif; ?>
                            </p>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span class="<?php echo $is_free ? 'sc-badge-success' : 'sc-badge-gold'; ?>">
                                    <?php
                                    if ($is_free) {
                                        echo esc_html(sc_t('frontend.free', 'FREE'));
                                    } else {
                                        echo esc_html(sc_t('frontend.from', 'From')) . ' ' . esc_html(number_format($min_price)) . ' ' . esc_html(sc_t('general.currency_symbol', 'EGP'));
                                    }
                                    ?>
                                </span>
                                <span class="sc-btn sc-btn-outline sc-btn-sm">
                                    <?php echo esc_html(sc_t('frontend.view_details', 'View Details')); ?> <i class="fa-solid fa-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($max_pages > 1): ?>
            <div class="text-center" style="margin-top: var(--sc-space-8);">
                <?php
                echo paginate_links(array(
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $max_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ));
                ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
