<?php
/**
 * Single Workshop Template - Public Frontend
 * Distinct hands-on workshop layout (different from event pages)
 *
 * @package sc_events
 * @version 1.1.0
 */

if (!class_exists('SC_Workshop')) {
    get_template_part('404');
    exit;
}

// Resolve workshop slug
$workshop_slug = get_query_var('sc_workshop');
if (empty($workshop_slug)) {
    global $post;
    if ($post && $post->post_type === 'sc_workshop') {
        $workshop_slug = $post->post_name;
    }
}
if (empty($workshop_slug)) {
    $queried_object = get_queried_object();
    if ($queried_object && isset($queried_object->post_type) && $queried_object->post_type === 'sc_workshop') {
        $workshop_slug = $queried_object->post_name;
    }
}
// Fallback: parse from URL
if (empty($workshop_slug) && !empty($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/workshop/([^/?]+)/?#', $_SERVER['REQUEST_URI'], $m)) {
        $workshop_slug = sanitize_title($m[1]);
    }
}

$workshop = $workshop_slug ? SC_Workshop::get_by_slug($workshop_slug) : null;

if (!$workshop) {
    get_template_part('404');
    exit;
}

// Reset 404 status if WP set it
status_header(200);
global $wp_query;
if (isset($wp_query)) { $wp_query->is_404 = false; $wp_query->is_singular = true; }

get_template_part('template-parts/public/header', 'public');

$assets_url = get_template_directory_uri() . '/assets/frontend/';
$workshop_id = $workshop->id;
$event = SC_Event::get($workshop->event_id);
$tickets = SC_Ticket::get_by_workshop($workshop_id, array('is_active' => null));

$banner_url   = $workshop->banner_image   ? wp_get_attachment_image_url($workshop->banner_image, 'full')   : '';
$featured_url = $workshop->featured_image ? wp_get_attachment_url($workshop->featured_image) : '';
$event_image  = $event && $event->featured_image ? wp_get_attachment_url($event->featured_image) : '';

// Tickets analysis
$has_tickets = false;
$min_price = 0; $is_free = true;
foreach ($tickets as $t) {
    if (!$t->is_active) continue;
    $has_tickets = true;
    $p = (float) $t->price;
    if ($p > 0) {
        $is_free = false;
        if ($min_price == 0 || $p < $min_price) $min_price = $p;
    }
}

// Past?
$end_dt = $workshop->end_date ?: $workshop->start_date;
if ($workshop->end_time) $end_dt .= ' ' . $workshop->end_time;
$is_past = strtotime($end_dt) < time();

// Registered?
$is_registered = false;
if (is_user_logged_in() && class_exists('SC_Attendee')) {
    global $wpdb;
    $att_table = $wpdb->prefix . 'sc_attendees';
    $is_registered = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$att_table} WHERE workshop_id = %d AND user_id = %d AND status != 'cancelled' LIMIT 1",
        $workshop_id, get_current_user_id()
    ));
}

// Spots remaining
$capacity = (int) $workshop->total_capacity;
$sold     = (int) $workshop->total_sold;
$remaining = $capacity > 0 ? max(0, $capacity - $sold) : null;

$primary_color = get_option('sc_primary_color', '#7c1314');
?>

<!-- Workshop Hero (banner background + overlay) -->
<?php $hero_bg = $banner_url ?: $featured_url; ?>
<section class="sc-workshop-hero <?php echo $hero_bg ? 'has-bg' : ''; ?>" <?php if ($hero_bg): ?>style="background-image: url('<?php echo esc_url($hero_bg); ?>');"<?php endif; ?>>
    <div class="sc-workshop-hero-overlay"></div>
    <div class="container sc-relative">
        <div class="sc-workshop-hero-content">
            <span class="sc-workshop-tag">
                <i class="fa-solid fa-flask-vial"></i>
                <?php echo esc_html(sc_t('frontend.workshop_label', 'Workshop')); ?>
            </span>
            <h1><?php echo esc_html($workshop->title); ?></h1>
            <?php if (!empty($workshop->excerpt)): ?>
            <p class="sc-workshop-tagline"><?php echo esc_html($workshop->excerpt); ?></p>
            <?php endif; ?>

            <div class="sc-workshop-meta-row">
                <span><i class="fa-regular fa-calendar"></i> <?php echo esc_html(date_i18n('M j, Y', strtotime($workshop->start_date))); ?></span>
                <?php if ($workshop->start_time): ?>
                <span><i class="fa-regular fa-clock"></i> <?php
                    echo esc_html(date('g:i A', strtotime($workshop->start_time)));
                    if ($workshop->end_time) echo ' – ' . esc_html(date('g:i A', strtotime($workshop->end_time)));
                ?></span>
                <?php endif; ?>
                <?php if ($workshop->venue_name): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html($workshop->venue_name); ?></span>
                <?php elseif ($workshop->location_type === 'online'): ?>
                <span><i class="fa-solid fa-video"></i> <?php echo esc_html(sc_t('frontend.online_workshop', 'Online Workshop')); ?></span>
                <?php endif; ?>
                <?php if ($capacity > 0): ?>
                <span><i class="fa-solid fa-users"></i> <?php printf('%d / %d %s', $sold, $capacity, esc_html(sc_t('frontend.seats', 'seats'))); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Parent Event Card -->
<?php if ($event): ?>
<section class="sc-workshop-parent-section">
    <div class="container">
        <a href="<?php echo esc_url(home_url('/event/' . $event->slug . '/')); ?>" class="sc-parent-event-card">
            <?php if ($event_image): ?>
            <div class="sc-parent-event-img" style="background-image: url('<?php echo esc_url($event_image); ?>');"></div>
            <?php else: ?>
            <div class="sc-parent-event-img sc-parent-event-img-placeholder">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <?php endif; ?>
            <div class="sc-parent-event-body">
                <span class="sc-parent-event-label">
                    <i class="fa-solid fa-link"></i>
                    <?php echo esc_html(sc_t('frontend.part_of_event', 'Part of Event')); ?>
                </span>
                <h3><?php echo esc_html($event->title); ?></h3>
                <p>
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo esc_html(date_i18n('F j, Y', strtotime($event->start_date))); ?>
                    <?php if ($event->venue_name): ?>
                    &nbsp; <i class="fa-solid fa-location-dot"></i> <?php echo esc_html($event->venue_name); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="sc-parent-event-arrow">
                <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- Two-column: What you'll learn + Booking sidebar -->
<section class="sc-workshop-content">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="sc-workshop-section">
                    <h2><i class="fa-solid fa-graduation-cap"></i> <?php echo esc_html(sc_t('frontend.about_workshop', 'About This Workshop')); ?></h2>
                    <div class="sc-workshop-description">
                        <?php echo wp_kses_post($workshop->description); ?>
                    </div>
                </div>

                <?php if ($workshop->venue_address || $workshop->meeting_link): ?>
                <div class="sc-workshop-section">
                    <h3><i class="fa-solid fa-map-pin"></i> <?php echo esc_html(sc_t('frontend.location_details', 'Location Details')); ?></h3>
                    <?php if ($workshop->venue_name): ?>
                    <p><strong><?php echo esc_html($workshop->venue_name); ?></strong></p>
                    <?php endif; ?>
                    <?php if ($workshop->venue_address): ?>
                    <p class="sc-text-muted"><?php echo esc_html($workshop->venue_address); ?></p>
                    <?php endif; ?>
                    <?php if ($workshop->meeting_link): ?>
                    <p>
                        <i class="fa-solid fa-video"></i>
                        <a href="<?php echo esc_url($workshop->meeting_link); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('frontend.join_online', 'Join Online')); ?></a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Booking Sidebar -->
            <div class="col-lg-5">
                <div class="sc-workshop-booking" id="tickets">
                    <div class="sc-workshop-booking-header">
                        <i class="fa-solid fa-bookmark"></i>
                        <h3><?php echo esc_html(sc_t('frontend.book_your_spot', 'Book Your Spot')); ?></h3>
                    </div>

                    <?php if ($is_past): ?>
                    <div class="sc-workshop-status sc-workshop-status-ended">
                        <i class="fa-solid fa-circle-info"></i>
                        <?php echo esc_html(sc_t('frontend.workshop_ended', 'This workshop has ended')); ?>
                    </div>

                    <?php elseif ($is_registered): ?>
                    <div class="sc-workshop-status sc-workshop-status-registered">
                        <i class="fa-solid fa-circle-check"></i>
                        <strong><?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered!')); ?></strong>
                        <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="sc-btn sc-btn-outline sc-btn-sm" style="margin-top: 12px;">
                            <?php echo esc_html(sc_t('frontend.view_tickets', 'View My Tickets')); ?>
                        </a>
                    </div>

                    <?php elseif ($capacity > 0 && $remaining === 0): ?>
                    <div class="sc-workshop-status sc-workshop-status-soldout">
                        <i class="fa-solid fa-times-circle"></i>
                        <?php echo esc_html(sc_t('frontend.workshop_full', 'This workshop is fully booked')); ?>
                    </div>

                    <?php elseif (!$has_tickets): ?>
                    <div class="sc-workshop-status sc-workshop-status-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <?php echo esc_html(sc_t('frontend.no_tickets', 'Booking not available yet. Check back soon!')); ?>
                    </div>

                    <?php else: ?>

                    <?php if ($capacity > 0 && $remaining !== null && $remaining < 10): ?>
                    <div class="sc-workshop-low-spots">
                        <i class="fa-solid fa-fire"></i>
                        <?php printf(esc_html(sc_t('frontend.only_x_left', 'Only %d spots left!')), $remaining); ?>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($tickets as $ticket):
                        if (!$ticket->is_active) continue;
                        $tprice = (float) $ticket->price;
                        $tqty = $ticket->quantity > 0 ? $ticket->quantity - $ticket->sold : -1;
                        $enable_coupons = !empty($ticket->enable_coupons);
                    ?>
                    <div class="sc-workshop-ticket">
                        <div class="sc-workshop-ticket-info">
                            <h4><?php echo esc_html($ticket->name); ?></h4>
                            <?php if ($ticket->description): ?>
                            <p class="sc-text-muted"><?php echo esc_html($ticket->description); ?></p>
                            <?php endif; ?>
                            <div class="sc-workshop-ticket-price">
                                <?php if ($tprice == 0): ?>
                                <span class="sc-price-free"><?php echo esc_html(sc_t('frontend.free', 'FREE')); ?></span>
                                <?php else: ?>
                                <span class="sc-price-amount"><?php echo esc_html(number_format($tprice)); ?></span>
                                <span class="sc-price-currency"><?php echo esc_html(sc_t('general.currency_symbol', 'EGP')); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($tqty === 0): ?>
                            <button class="sc-btn sc-btn-ghost" disabled style="width: 100%; opacity: 0.5;"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold Out')); ?></button>
                        <?php elseif ($tprice == 0 && $enable_coupons): ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-register-coupon" style="width: 100%;"
                                data-workshop-id="<?php echo (int) $workshop_id; ?>"
                                data-event-id="<?php echo (int) $workshop->event_id; ?>"
                                data-ticket-id="<?php echo (int) $ticket->id; ?>"
                                data-ticket-name="<?php echo esc_attr($ticket->name); ?>">
                                <i class="fa-solid fa-key"></i> <?php echo esc_html(sc_t('frontend.register_with_coupon', 'Register with Coupon')); ?>
                            </button>
                        <?php elseif ($tprice == 0): ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-register-free" style="width: 100%;"
                                data-workshop-id="<?php echo (int) $workshop_id; ?>"
                                data-event-id="<?php echo (int) $workshop->event_id; ?>"
                                data-ticket-id="<?php echo (int) $ticket->id; ?>"
                                data-ticket-name="<?php echo esc_attr($ticket->name); ?>">
                                <i class="fa-solid fa-check"></i> <?php echo esc_html(sc_t('frontend.register_free', 'Register Free')); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-buy-ticket" style="width: 100%;"
                                data-workshop-id="<?php echo (int) $workshop_id; ?>"
                                data-event-id="<?php echo (int) $workshop->event_id; ?>"
                                data-ticket-id="<?php echo (int) $ticket->id; ?>"
                                data-ticket-name="<?php echo esc_attr($ticket->name); ?>"
                                data-ticket-price="<?php echo esc_attr($tprice); ?>"
                                data-min-qty="<?php echo (int) ($ticket->min_per_order ?? 1); ?>"
                                data-max-qty="<?php echo (int) ($ticket->max_per_order ?? 10); ?>">
                                <i class="fa-solid fa-cart-shopping"></i> <?php echo esc_html(sc_t('frontend.buy_now', 'Buy Now')); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Workshop-specific styles -->
<style>
.sc-workshop-hero {
    position: relative;
    background: linear-gradient(135deg, var(--sc-primary, #7c1314) 0%, var(--sc-primary-dark, #5a0d0e) 100%);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    color: #fff;
    padding: 100px 0 80px;
    margin-top: 0;
    min-height: 380px;
    overflow: hidden;
}
.sc-workshop-hero.has-bg {
    background-color: #1a1a2e;
}
.sc-workshop-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(124,19,20,0.85) 0%, rgba(20,10,15,0.78) 60%, rgba(20,10,15,0.65) 100%);
    z-index: 1;
}
.sc-workshop-hero .sc-relative,
.sc-workshop-hero .container { position: relative; z-index: 2; }
.sc-workshop-hero-content { max-width: 800px; }
.sc-workshop-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.18); backdrop-filter: blur(10px);
    padding: 8px 16px; border: 1px solid rgba(255,255,255,0.25);
    border-radius: 999px; font-size: 0.85rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 20px;
    color: #fff;
}
.sc-workshop-hero h1 {
    font-size: 3rem; margin: 0 0 16px; line-height: 1.15;
    color: #ffffff !important;
    text-shadow: 0 2px 12px rgba(0,0,0,0.5);
}
.sc-workshop-tagline {
    font-size: 1.2rem; margin: 0 0 28px; max-width: 700px;
    color: #ffffff !important;
    opacity: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,0.5);
}
.sc-workshop-meta-row {
    display: flex; flex-wrap: wrap; gap: 24px;
    font-size: 0.95rem;
}
.sc-workshop-meta-row span {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(0,0,0,0.45); backdrop-filter: blur(10px);
    padding: 8px 14px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    color: #ffffff !important;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
}
.sc-workshop-meta-row span,
.sc-workshop-meta-row i { color: #ffffff !important; opacity: 1; }

@media (max-width: 768px) {
    .sc-workshop-hero { padding: 60px 0 50px; min-height: 280px; }
    .sc-workshop-hero h1 { font-size: 1.85rem; }
    .sc-workshop-tagline { font-size: 1rem; }
    .sc-workshop-meta-row { gap: 10px; }
    .sc-workshop-meta-row span { font-size: 0.85rem; padding: 6px 10px; }
}

/* Parent event card */
.sc-workshop-parent-section { padding: 32px 0; background: var(--sc-bg-subtle, #f5f5f7); }
.sc-parent-event-card {
    display: flex; align-items: center; gap: 20px;
    background: #fff; border-radius: 12px; padding: 16px;
    text-decoration: none; color: inherit;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.sc-parent-event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    text-decoration: none; color: inherit;
}
.sc-parent-event-img {
    width: 100px; height: 100px; border-radius: 10px;
    background-size: cover; background-position: center;
    flex-shrink: 0; background-color: var(--sc-bg-subtle, #f5f5f7);
}
.sc-parent-event-img-placeholder {
    display: flex; align-items: center; justify-content: center;
    color: var(--sc-text-muted, #999); font-size: 32px;
}
.sc-parent-event-body { flex: 1; min-width: 0; }
.sc-parent-event-label {
    display: inline-flex; align-items: center; gap: 6px;
    color: var(--sc-primary, #7c1314); font-size: 0.8rem;
    font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
    margin-bottom: 4px;
}
.sc-parent-event-body h3 { margin: 0 0 4px; font-size: 1.15rem; color: var(--sc-text-primary, #1a1a2e); }
.sc-parent-event-body p { margin: 0; color: var(--sc-text-muted, #666); font-size: 0.9rem; }
.sc-parent-event-arrow {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--sc-primary, #7c1314); color: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: transform 0.2s;
}
.sc-parent-event-card:hover .sc-parent-event-arrow { transform: translateX(4px); }

/* Workshop content */
.sc-workshop-content { padding: 60px 0; }
.sc-workshop-section { margin-bottom: 40px; }
.sc-workshop-section h2 {
    font-size: 1.75rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
    color: var(--sc-text-primary, #1a1a2e);
}
.sc-workshop-section h2 i { color: var(--sc-primary, #7c1314); }
.sc-workshop-section h3 {
    font-size: 1.25rem; margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
    color: var(--sc-text-primary, #1a1a2e);
}
.sc-workshop-section h3 i { color: var(--sc-primary, #7c1314); }
.sc-workshop-description {
    font-size: 1rem; line-height: 1.8;
    color: var(--sc-text-secondary, #444);
}
.sc-workshop-description p { margin-bottom: 16px; }

/* Booking box */
.sc-workshop-booking {
    background: #fff; border-radius: 16px;
    padding: 28px; box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    border: 1px solid var(--sc-border, #eee);
    position: sticky; top: 90px;
}
.sc-workshop-booking-header {
    display: flex; align-items: center; gap: 12px;
    padding-bottom: 16px; margin-bottom: 20px;
    border-bottom: 2px solid var(--sc-border, #f0f0f0);
}
.sc-workshop-booking-header i {
    width: 40px; height: 40px; border-radius: 10px;
    background: var(--sc-primary, #7c1314); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.sc-workshop-booking-header h3 { margin: 0; font-size: 1.25rem; }
.sc-workshop-status {
    padding: 16px; border-radius: 10px; text-align: center;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.sc-workshop-status i { font-size: 32px; }
.sc-workshop-status-ended { background: rgba(150,150,150,0.1); color: #666; }
.sc-workshop-status-ended i { color: #888; }
.sc-workshop-status-registered { background: rgba(40,167,69,0.1); color: #155724; }
.sc-workshop-status-registered i { color: #28a745; }
.sc-workshop-status-soldout { background: rgba(220,53,69,0.1); color: #721c24; }
.sc-workshop-status-soldout i { color: #dc3545; }
.sc-workshop-status-info { background: rgba(23,162,184,0.1); color: #0c5460; }
.sc-workshop-status-info i { color: #17a2b8; }

.sc-workshop-low-spots {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: #fff; padding: 10px 16px; border-radius: 8px;
    font-size: 0.9rem; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 16px;
}
.sc-workshop-low-spots i { animation: sc-pulse 1.5s infinite; }
@keyframes sc-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }

.sc-workshop-ticket {
    border: 1px solid var(--sc-border, #eee);
    border-radius: 12px; padding: 20px; margin-bottom: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.sc-workshop-ticket:hover {
    border-color: var(--sc-primary, #7c1314);
    box-shadow: 0 4px 16px rgba(124,19,20,0.1);
}
.sc-workshop-ticket-info { margin-bottom: 16px; }
.sc-workshop-ticket-info h4 { margin: 0 0 6px; font-size: 1.1rem; color: var(--sc-text-primary, #1a1a2e); }
.sc-workshop-ticket-info p { margin: 0 0 12px; font-size: 0.9rem; }
.sc-workshop-ticket-price {
    display: flex; align-items: baseline; gap: 6px;
    font-weight: 700; color: var(--sc-primary, #7c1314);
}
.sc-price-amount { font-size: 1.6rem; }
.sc-price-currency { font-size: 0.95rem; opacity: 0.8; }
.sc-price-free { font-size: 1.4rem; color: #28a745; }

@media (max-width: 991px) {
    .sc-workshop-booking { position: static; margin-top: 32px; }
}
</style>

<?php
// Reuse the existing event checkout modal (the JS reads workshop_id from button data attribute)
$template_args = array(
    'event'         => $event,
    'event_id'      => $workshop->event_id,
    'workshop_id'   => $workshop_id,
    'tickets'       => $tickets,
    'has_tickets'   => $has_tickets,
    'is_past'       => $is_past,
    'is_registered' => $is_registered,
    'is_free'       => $is_free,
    'min_price'     => $min_price,
    'max_price'     => $min_price,
    'assets_url'    => $assets_url,
    'primary_color' => $primary_color,
    'secondary_color' => get_option('sc_secondary_color', '#1B1E4A'),
);
get_template_part('template-parts/event/checkout-modal', null, $template_args);
?>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
