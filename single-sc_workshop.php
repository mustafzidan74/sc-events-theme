<?php
/**
 * Single workshop.
 *
 * Shares the event page's furniture — same hero, same ticket panel, same
 * sticky bar — because a workshop is a smaller event and a visitor moving
 * between the two should not have to relearn the page. What differs is the
 * seat count, which is the deciding fact for a hands-on session, and the link
 * back to the congress it belongs to.
 *
 * The ticket buttons keep the class and data attributes the checkout script
 * binds to, workshop_id included.
 *
 * @package sc_events
 */

if (!class_exists('SC_Workshop')) {
    status_header(404);
    nocache_headers();
    get_template_part('404');
    exit;
}

$workshop_slug = get_query_var('sc_workshop');
if (empty($workshop_slug)) {
    global $post;
    if ($post && $post->post_type === 'sc_workshop') {
        $workshop_slug = $post->post_name;
    }
}
if (empty($workshop_slug)) {
    $queried = get_queried_object();
    if ($queried && isset($queried->post_type) && $queried->post_type === 'sc_workshop') {
        $workshop_slug = $queried->post_name;
    }
}
// Last resort: read it off the path. The query string is stripped first, or a
// link carrying campaign parameters would never match.
if (empty($workshop_slug) && !empty($_SERVER['REQUEST_URI'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
    if (preg_match('#/workshop/([^/]+)/?$#', $path, $m)) {
        $workshop_slug = sanitize_title($m[1]);
    }
}

$workshop = $workshop_slug ? SC_Workshop::get_by_slug($workshop_slug) : null;

// Drafts, private, cancelled and disabled workshops stay hidden from visitors;
// event managers still see them, which is how the dashboard's Preview works.
if ($workshop && !in_array($workshop->status, array('publish', 'completed'), true)
    && !(class_exists('SC_Event_Manager_Dashboard') && SC_Event_Manager_Dashboard::is_event_manager())) {
    $workshop = null;
}

if (!$workshop) {
    status_header(404);
    nocache_headers();
    get_template_part('404');
    exit;
}

status_header(200);
global $wp_query;
if (isset($wp_query)) {
    $wp_query->is_404 = false;
    $wp_query->is_singular = true;
}

// The rewrite rule does not always survive a flush, in which case the slug came
// off the URL above and no query var was ever set. Publish it now, before the
// header runs, so anything downstream can tell it is looking at a workshop.
set_query_var('sc_workshop', $workshop->slug);

get_template_part('template-parts/public/header', 'public');

$assets_url  = get_template_directory_uri() . '/assets/frontend/';
$workshop_id = $workshop->id;
$event       = SC_Event::get($workshop->event_id);
$tickets     = SC_Ticket::get_by_workshop($workshop_id, ['is_active' => null]);

$hero_bg = sc_img($workshop->banner_image ?: $workshop->featured_image, 'full', '100vw', array(
    'class'         => 'w-ev__bg',
    'loading'       => false,
    'fetchpriority' => 'high',
));

$pricing     = sc_ticket_pricing($tickets);
$has_tickets = $pricing['has_tickets'];
$is_free     = $pricing['is_free'];
$min_price   = $pricing['min_price'];

$end_dt = $workshop->end_date ?: $workshop->start_date;
if ($workshop->end_time) { $end_dt .= ' ' . $workshop->end_time; }
$is_past = strtotime($end_dt) < current_time('timestamp');

$is_registered = false;
if (is_user_logged_in() && class_exists('SC_Attendee')) {
    global $wpdb;
    $att_table = $wpdb->prefix . 'sc_attendees';
    $is_registered = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$att_table} WHERE workshop_id = %d AND user_id = %d AND status != 'cancelled' LIMIT 1",
        $workshop_id,
        get_current_user_id()
    ));
}

$capacity  = (int) $workshop->total_capacity;
$sold      = (int) $workshop->total_sold;
$remaining = $capacity > 0 ? max(0, $capacity - $sold) : null;

$big_date = sc_date_range($workshop->start_date, $workshop->end_date);

$start_ts  = strtotime($workshop->start_date . ' ' . ($workshop->start_time ?: '00:00:00'));
$days_left = (int) ceil(($start_ts - current_time('timestamp')) / DAY_IN_SECONDS);

$where = [];
if ($workshop->location_type === 'online') {
    $where[] = sc_t('frontend.online_workshop', 'Online');
} elseif ($workshop->venue_name) {
    $where[] = $workshop->venue_name;
}
if ($workshop->start_time) {
    $clock = date_i18n('H:i', strtotime($workshop->start_time));
    if ($workshop->end_time) { $clock .= '–' . date_i18n('H:i', strtotime($workshop->end_time)); }
    $where[] = $clock;
}

$currency = sc_t('general.currency_symbol', 'EGP');

$live_gateways = sc_live_gateways();

$show_buy = $has_tickets && !$is_past && !$is_registered;
?>

<main class="w-ev">
    <div class="w-ev__head">
        <nav class="w-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'sc_events'); ?>">
            <a href="<?php echo esc_url(home_url('/workshops/')); ?>"><?php echo esc_html(sc_t('frontend.workshops', 'Workshops')); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php echo esc_html($workshop->title); ?></span>
        </nav>

        <section class="w-ev__hero">
            <?php if ($hero_bg): ?>
                <?php echo $hero_bg; // Built by wp_get_attachment_image(). ?>
            <?php endif; ?>

            <div class="w-ev__badges">
                <span class="w-ev__badge">
                    <i class="fa-solid fa-flask-vial" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.workshop_label', 'Workshop')); ?>
                </span>

                <?php if ($is_past): ?>
                    <span class="w-ev__badge"><?php echo esc_html(sc_t('frontend.past', 'Past')); ?></span>
                <?php elseif ($is_registered): ?>
                    <span class="w-ev__badge">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered')); ?>
                    </span>
                <?php elseif ($remaining !== null && $remaining === 0): ?>
                    <span class="w-ev__badge"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?></span>
                <?php elseif ($remaining !== null): ?>
                    <span class="w-ev__badge">
                        <span class="w-ev__pulse" aria-hidden="true"></span>
                        <?php printf(
                            esc_html(sc_t('frontend.n_seats_left', '%s seats left')),
                            esc_html(number_format_i18n($remaining))
                        ); ?>
                    </span>
                <?php elseif ($has_tickets): ?>
                    <span class="w-ev__badge">
                        <span class="w-ev__pulse" aria-hidden="true"></span>
                        <?php echo esc_html(sc_t('frontend.registration_open', 'Registration open')); ?>
                    </span>
                <?php endif; ?>

                <?php if (!$is_past && $days_left > 0): ?>
                    <span class="w-ev__badge"><?php printf(
                        esc_html(sc_t('frontend.n_days_left', '%s days left')),
                        esc_html(number_format_i18n($days_left))
                    ); ?></span>
                <?php endif; ?>
            </div>

            <div class="w-ev__foot">
                <div class="w-ev__say">
                    <span class="w-ev__date"><?php echo esc_html($big_date); ?></span>
                    <h1 class="w-ev__title"><?php echo esc_html($workshop->title); ?></h1>
                    <?php if ($where): ?>
                        <p class="w-ev__where"><?php echo esc_html(implode(' · ', $where)); ?></p>
                    <?php endif; ?>
                </div>

                <div class="w-ev__acts">
                    <button class="w-ev__act" type="button" data-share
                            data-title="<?php echo esc_attr($workshop->title); ?>"
                            aria-label="<?php echo esc_attr(sc_t('frontend.share', 'Share')); ?>">
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>

        <?php if ($event): ?>
        <div class="w-ev__tiles">
            <a class="w-ev__tile" href="<?php echo esc_url(home_url('/event/' . $event->slug . '/')); ?>">
                <span class="w-ev__tile-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span>
                <span class="w-ev__tile-text">
                    <span class="w-ev__tile-label"><?php echo esc_html($event->title); ?></span>
                    <span class="w-ev__tile-sub"><?php echo esc_html(sc_t('frontend.part_of_event', 'This workshop is part of')); ?></span>
                </span>
            </a>

            <?php if ($show_buy): ?>
            <a class="w-ev__tile w-ev__tile--buy" href="#tickets">
                <span class="w-ev__tile-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></span>
                <span class="w-ev__tile-text">
                    <span class="w-ev__tile-label"><?php echo esc_html(sc_t('frontend.tickets', 'Seats')); ?></span>
                    <span class="w-ev__tile-sub"><?php echo $is_free
                        ? esc_html(sc_t('frontend.book_now', 'Book your place'))
                        : esc_html(sprintf(sc_t('frontend.from_price', 'From %s'), sc_currency($min_price))); ?></span>
                </span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($workshop->description)): ?>
    <section class="w-ev__section" id="about">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.about_workshop', 'About this workshop')); ?></h2>
        <div class="w-prose w-prose--clamped" data-clamp>
            <?php echo wp_kses_post(wpautop($workshop->description)); ?>
        </div>
        <button class="w-btn w-btn--outline w-btn--sm" type="button" data-clamp-toggle
                data-more="<?php echo esc_attr(sc_t('frontend.read_more', 'Read more')); ?>"
                data-less="<?php echo esc_attr(sc_t('frontend.read_less', 'Read less')); ?>"
                style="align-self:flex-start">
            <?php echo esc_html(sc_t('frontend.read_more', 'Read more')); ?>
        </button>
    </section>
    <?php endif; ?>

    <?php if ($workshop->venue_name || $workshop->venue_address || $workshop->meeting_link): ?>
    <section class="w-ev__section" id="venue">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.venue', 'Where')); ?></h2>
        <div class="w-venue">
            <div class="w-venue__say">
                <?php if ($workshop->venue_name): ?>
                    <h3 class="w-venue__name"><?php echo esc_html($workshop->venue_name); ?></h3>
                <?php endif; ?>

                <?php if ($workshop->venue_address): ?>
                    <p class="w-ev__lead" style="font-size:1rem;margin:0"><?php echo esc_html($workshop->venue_address); ?></p>
                <?php endif; ?>

                <?php if ($capacity > 0): ?>
                <div class="w-venue__halls">
                    <span class="w-venue__hall"><?php printf(
                        esc_html(sc_t('frontend.d_seats', '%s seats')),
                        esc_html(number_format_i18n($capacity))
                    ); ?></span>
                    <?php if ($remaining !== null): ?>
                    <span class="w-venue__hall"><?php printf(
                        esc_html(sc_t('frontend.n_taken', '%s taken')),
                        esc_html(number_format_i18n($sold))
                    ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($workshop->meeting_link): ?>
                <a class="w-btn w-btn--outline" href="<?php echo esc_url($workshop->meeting_link); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-video" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.join_online', 'Join online')); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_buy): ?>
    <section class="w-ev__buy" id="tickets">
        <div class="w-ev__section" style="gap:var(--w-space-3)">
            <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.book_your_seat', 'Book your seat.')); ?></h2>
            <?php if ($remaining !== null): ?>
            <p class="w-ev__lead" style="font-size:1rem">
                <?php printf(
                    esc_html(sc_t('frontend.n_seats_left_of', '%s seats left of %s.')),
                    esc_html(number_format_i18n($remaining)),
                    esc_html(number_format_i18n($capacity))
                ); ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($live_gateways): ?>
        <p class="w-ev__pay">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <span><?php echo esc_html(implode(' · ', $live_gateways)); ?></span>
        </p>
        <?php endif; ?>

        <div class="w-ev__tickets">
            <?php foreach ($tickets as $ticket): ?>
                <?php get_template_part('template-parts/public/ticket-row', null, [
                    'ticket'      => $ticket,
                    'event_id'    => $workshop->event_id,
                    'workshop_id' => $workshop_id,
                    'currency'    => $currency,
                    'buy_label'   => sc_t('frontend.get_ticket', 'Take a seat'),
                ]); ?>
            <?php endforeach; ?>
        </div>

        <div id="coupon-form-wrapper" class="w-ev__coupon sc-coupon-area" style="display: none;">
            <span class="w-label"><?php echo esc_html(sc_t('frontend.have_a_coupon', 'Have a coupon?')); ?></span>
            <div class="w-ev__coupon-row">
                <input type="text" id="coupon-code" class="w-input sc-input"
                       placeholder="<?php echo esc_attr(sc_t('frontend.enter_code', 'Enter code')); ?>">
                <button class="w-btn w-btn--outline btn-apply-coupon" data-event-id="<?php echo (int) $workshop->event_id; ?>">
                    <?php echo esc_html(sc_t('frontend.apply', 'Apply')); ?>
                </button>
            </div>
        </div>
    </section>
    <?php elseif ($is_registered): ?>
    <section class="w-ev__buy" id="tickets">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered')); ?></h2>
        <p class="w-ev__lead" style="font-size:1rem">
            <?php echo esc_html(sc_t('frontend.registered_lede', 'Your ticket and e-badge are in your account.')); ?>
        </p>
        <a class="w-btn w-btn--lg" href="<?php echo esc_url(home_url('/my-account/')); ?>" style="align-self:flex-start">
            <?php echo esc_html(sc_t('frontend.view_tickets', 'View my ticket')); ?>
        </a>
    </section>
    <?php endif; ?>
</main>

<?php if ($show_buy): ?>
<div class="w-ev__sticky">
    <span class="w-ev__sticky-text">
        <strong><?php echo esc_html($workshop->title); ?></strong>
        <?php if (!$is_free): ?>
        <span><?php echo esc_html(sprintf(sc_t('frontend.from_price', 'From %s'), sc_currency($min_price))); ?></span>
        <?php endif; ?>
    </span>
    <a class="w-btn" href="#tickets"><?php echo esc_html(sc_t('frontend.get_ticket', 'Take a seat')); ?></a>
</div>
<?php endif; ?>

<?php
// The event checkout modal is reused as-is; its script reads workshop_id off
// the button that opened it.
$template_args = [
    'event'           => $event,
    'event_id'        => $workshop->event_id,
    'workshop_id'     => $workshop_id,
    'tickets'         => $tickets,
    'has_tickets'     => $has_tickets,
    'is_past'         => $is_past,
    'is_registered'   => $is_registered,
    'is_free'         => $is_free,
    'min_price'       => $min_price,
    'max_price'       => $min_price,
    'assets_url'      => $assets_url,
    'primary_color'   => get_option('sc_primary_color', '#7c1314'),
    'secondary_color' => get_option('sc_secondary_color', '#1B1E4A'),
];
get_template_part('template-parts/event/checkout-modal', null, $template_args);

get_template_part('template-parts/public/footer', 'public');
