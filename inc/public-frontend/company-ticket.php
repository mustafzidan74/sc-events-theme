<?php
/**
 * Company badge page — /company-ticket/{company_code}/.
 *
 * The dashboard's "View badge" button and the badge emails link here; the page
 * didn't exist, so every link was a 404. The company code in the URL is the key
 * (it is only sent to the company). The QR holds the code itself, which the
 * attendance scanner checks in.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    add_rewrite_rule('^company-ticket/([A-Za-z0-9-]+)/?$', 'index.php?sc_company_ticket=$matches[1]', 'top');
    if (get_option('sc_company_ticket_rules_version') !== '1') {
        flush_rewrite_rules(false);
        update_option('sc_company_ticket_rules_version', '1');
    }
}, 20);

add_filter('query_vars', function ($vars) {
    $vars[] = 'sc_company_ticket';
    return $vars;
});

add_action('template_redirect', 'sc_render_company_ticket_page', 1);
function sc_render_company_ticket_page() {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) get_query_var('sc_company_ticket')));
    if ($code === '') {
        return;
    }
    global $wpdb, $wp_query;
    $company = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, e.title AS event_title, e.start_date, e.end_date, e.venue_name
         FROM {$wpdb->prefix}sc_company_attendees c LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = c.event_id
         WHERE c.company_code = %s LIMIT 1",
        $code
    ));
    if (!$company) {
        $wp_query->set_404();
        status_header(404);
        return;
    }
    $wp_query->is_404 = false;
    status_header(200);
    nocache_headers();
    add_filter('wp_robots', 'wp_robots_no_robots');
    add_filter('wp_title', function () use ($company) {
        return $company->company_name . ' | ';
    }, 99);

    $logo = $company->company_logo ? wp_get_attachment_image_url((int) $company->company_logo, 'medium') : '';
    $dates = $company->start_date ? date_i18n('j M Y', strtotime($company->start_date)) . ($company->end_date && $company->end_date !== $company->start_date ? ' – ' . date_i18n('j M Y', strtotime($company->end_date)) : '') : '';
    $cancelled = $company->status !== 'active';

    get_template_part('template-parts/public/header', 'public');
    ?>
    <main class="w-cbadge">
        <p class="w-cbadge__hint"><?php echo esc_html($cancelled
            ? sc_t('frontend.company_badge_cancelled', 'This company registration has been cancelled.')
            : sc_t('frontend.company_badge_hint', 'Show this badge at the entrance. Keep it on your phone or print it.')); ?></p>

        <article class="w-card w-cbadge__card<?php echo $cancelled ? ' is-cancelled' : ''; ?>">
            <header class="w-cbadge__head">
                <span class="w-cbadge__label"><?php echo esc_html(sc_t('frontend.exhibitor', 'Exhibitor')); ?></span>
                <span class="w-cbadge__event"><?php echo esc_html($company->event_title); ?></span>
            </header>
            <div class="w-cbadge__body">
                <?php if ($logo): ?><img class="w-cbadge__logo" src="<?php echo esc_url($logo); ?>" alt=""><?php endif; ?>
                <h1 class="w-cbadge__name"><?php echo esc_html($company->company_name); ?></h1>
                <?php if ($company->company_name_ar): ?><p class="w-cbadge__name-ar" dir="rtl"><?php echo esc_html($company->company_name_ar); ?></p><?php endif; ?>
                <?php if ($company->contact_name): ?><p class="w-cbadge__contact"><?php echo esc_html(trim($company->contact_name . ($company->contact_title ? ' · ' . $company->contact_title : ''))); ?></p><?php endif; ?>

                <div class="w-cbadge__qr" id="company-qr" data-code="<?php echo esc_attr($company->company_code); ?>" role="img" aria-label="<?php echo esc_attr(sc_t('frontend.qr_code', 'QR code')); ?>"></div>
                <p class="w-cbadge__code"><?php echo esc_html($company->company_code); ?></p>

                <dl class="w-cbadge__facts">
                    <?php if ($company->booth_number): ?><div><dt><?php echo esc_html(sc_t('frontend.booth', 'Booth')); ?></dt><dd><?php echo esc_html($company->booth_number); ?></dd></div><?php endif; ?>
                    <?php if ($company->sponsorship_level): ?><div><dt><?php echo esc_html(sc_t('frontend.sponsorship', 'Sponsorship')); ?></dt><dd><?php echo esc_html(ucfirst($company->sponsorship_level)); ?></dd></div><?php endif; ?>
                    <?php if ($dates): ?><div><dt><?php echo esc_html(sc_t('frontend.dates', 'Dates')); ?></dt><dd><?php echo esc_html($dates); ?></dd></div><?php endif; ?>
                    <?php if ($company->venue_name): ?><div><dt><?php echo esc_html(sc_t('frontend.venue', 'Venue')); ?></dt><dd><?php echo esc_html($company->venue_name); ?></dd></div><?php endif; ?>
                </dl>
            </div>
        </article>

        <button type="button" class="w-btn w-btn--ghost w-cbadge__print" onclick="window.print()"><?php echo esc_html(sc_t('frontend.print_badge', 'Print badge')); ?></button>
    </main>

    <style>
    .w-cbadge { max-width: 460px; margin: 0 auto; padding: var(--w-space-8) var(--w-space-4) var(--w-space-12); display: flex; flex-direction: column; gap: var(--w-space-4); }
    .w-cbadge__hint { margin: 0; text-align: center; color: var(--w-text-2); }
    .w-cbadge__card { overflow: hidden; padding: 0; }
    .w-cbadge__card.is-cancelled { opacity: .55; filter: grayscale(1); }
    .w-cbadge__head { display: flex; flex-direction: column; gap: 2px; padding: var(--w-space-4) var(--w-space-5); background: var(--w-primary); color: var(--w-on-primary); }
    .w-cbadge__label { font-size: var(--w-small-size); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
    .w-cbadge__event { font-weight: 600; }
    .w-cbadge__body { display: flex; flex-direction: column; align-items: center; gap: var(--w-space-2); padding: var(--w-space-6) var(--w-space-5); text-align: center; }
    .w-cbadge__logo { max-width: 160px; max-height: 80px; object-fit: contain; margin-bottom: var(--w-space-2); }
    .w-cbadge__name { margin: 0; font-size: var(--w-h2-size); line-height: var(--w-h2-lh); font-weight: var(--w-h2-weight); }
    .w-cbadge__name-ar, .w-cbadge__contact { margin: 0; color: var(--w-text-2); }
    .w-cbadge__qr { width: 220px; height: 220px; margin-top: var(--w-space-4); padding: 10px; background: #fff; border-radius: var(--w-radius-md); }
    .w-cbadge__qr img, .w-cbadge__qr canvas { width: 100% !important; height: 100% !important; }
    .w-cbadge__code { margin: 0; font-family: var(--w-font-mono); font-size: var(--w-small-size); letter-spacing: .06em; color: var(--w-text-2); }
    .w-cbadge__facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--w-space-3) var(--w-space-4); width: 100%; margin: var(--w-space-4) 0 0; padding-top: var(--w-space-4); border-top: 1px solid var(--w-border); text-align: start; }
    .w-cbadge__facts dt { margin: 0; font-size: var(--w-small-size); color: var(--w-text-3); }
    .w-cbadge__facts dd { margin: 0; font-weight: 600; }
    .w-cbadge__print { align-self: center; }
    @media print {
        header, footer, .w-cbadge__hint, .w-cbadge__print, [class*="chat"], [class*="whatsapp"] { display: none !important; }
        .w-cbadge { padding: 0; }
    }
    </style>
    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/admin-dashboard/vendor/qrcode.min.js'); ?>"></script>
    <script>
    (function () {
        var box = document.getElementById('company-qr');
        if (!box || typeof QRCode === 'undefined' || !QRCode.toDataURL) { return; }
        QRCode.toDataURL(box.getAttribute('data-code'), { width: 400, margin: 1 }, function (err, url) {
            if (err) { return; }
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            box.appendChild(img);
        });
    })();
    </script>
    <?php
    get_template_part('template-parts/public/footer', 'public');
    exit;
}
