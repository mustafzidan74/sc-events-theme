<?php
/**
 * Booth types — one card per type for an event, with its real booth counts.
 * Saved by sc_booth_type_save / sc_booth_type_delete (inc/admin-dashboard/booths-dashboard.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_overview;
$load_wd_list = true;
$load_wd_overview = true;
$p = $wpdb->prefix;

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, COALESCE(e.end_date, e.start_date) >= %s AS current, (SELECT COUNT(*) FROM {$p}sc_booth_types t WHERE t.event_id = e.id) AS types
     FROM {$p}sc_events e WHERE e.status IN ('publish', 'completed', 'draft') ORDER BY e.start_date DESC LIMIT 200",
    current_time('Y-m-d')
));
$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
if (!$event_id) {
    // The event that already has booth types, else the next one coming up.
    foreach ($events as $ev) {
        if ((int) $ev->types) {
            $event_id = (int) $ev->id;
            break;
        }
    }
    foreach ($event_id ? array() : $events as $ev) {
        if ((int) $ev->current) {
            $event_id = (int) $ev->id;
        }
    }
    if (!$event_id && $events) {
        $event_id = (int) $events[0]->id;
    }
}

$types = $event_id ? $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, COUNT(b.id) AS booths, SUM(b.status = 'available') AS open_booths, SUM(b.status IN ('reserved', 'booked', 'occupied')) AS taken
     FROM {$p}sc_booth_types t LEFT JOIN {$p}sc_booths b ON b.booth_type_id = t.id
     WHERE t.event_id = %d GROUP BY t.id ORDER BY t.sort_order, t.base_price DESC, t.name",
    $event_id
)) : array();

$currency = get_option('sc_currency_code', 'EGP');
$categories = sc_booth_categories();
$dashboard_url = home_url('/event-manager-dashboard/');
$money = function ($v) use ($currency) {
    return number_format_i18n((float) $v, (float) $v == floor((float) $v) ? 0 : 2) . ' ' . $currency;
};
$metres = function ($v) {
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
};
$totals = array('booths' => 0, 'open' => 0, 'taken' => 0);
foreach ($types as $t) {
    $totals['booths'] += (int) $t->booths;
    $totals['open'] += (int) $t->open_booths;
    $totals['taken'] += (int) $t->taken;
}
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('booths.booth_types', 'Booth types')); ?><span class="w-page-head__count"><?php echo esc_html(number_format_i18n(count($types))); ?></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('booths.types_sub', 'Sizes and prices you sell. Each booth on the floor belongs to one type and takes its size and price unless you override them.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <?php if ($types): ?><a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'booths?event_id=' . $event_id); ?>"><?php echo esc_html(sc_t('booths.all_booths', 'All booths')); ?></a><?php endif; ?>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'booth-type-create' . ($event_id ? '?event_id=' . $event_id : '')); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('booths.add_type', 'Add booth type')); ?></a>
        </div>
    </div>

    <div class="w-toolbar">
        <select class="form-control w-badges__event" id="event-switch" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
            <?php foreach ($events as $ev): ?>
                <option value="<?php echo (int) $ev->id; ?>" <?php selected($event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($types): ?>
    <div class="w-kpis">
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.booths', 'Booths')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($totals['booths'])); ?></span><span class="w-kpi__sub"><?php echo esc_html(count($types) === 1 ? sc_t('booths.one_type', 'of one type') : sprintf(sc_t('booths.across_types', 'across %s types'), number_format_i18n(count($types)))); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.available', 'Available')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($totals['open'])); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.taken', 'Reserved or booked')); ?></span><span class="w-kpi__value"><?php echo esc_html(number_format_i18n($totals['taken'])); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.sold_share', 'Taken')); ?></span><span class="w-kpi__value"><?php echo esc_html($totals['booths'] ? round($totals['taken'] / $totals['booths'] * 100) . '%' : '—'); ?></span></div>
    </div>
    <?php endif; ?>

    <div class="w-catgrid" id="types">
        <?php if (!$types): ?>
            <div class="w-state">
                <strong><?php echo esc_html(sc_t('booths.no_types', 'No booth types for this event yet')); ?></strong>
                <p><?php echo esc_html(sc_t('booths.no_types_help', 'Start with the sizes you sell — for example a 3 × 3 m standard booth and a 6 × 6 m island — then add the booths on the booths page.')); ?></p>
                <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'booth-type-create' . ($event_id ? '?event_id=' . $event_id : '')); ?>"><?php echo esc_html(sc_t('booths.add_type', 'Add booth type')); ?></a>
            </div>
        <?php endif; ?>
        <?php foreach ($types as $t):
            $inclusions = json_decode((string) $t->inclusions, true);
            $inclusions = is_array($inclusions) ? $inclusions : array();
            $deposit = (float) $t->deposit_percentage > 0 ? $metres($t->deposit_percentage) . '%' : ((float) $t->deposit_amount > 0 ? $money($t->deposit_amount) : '');
            ?>
            <article class="w-cat w-btype" data-id="<?php echo (int) $t->id; ?>">
                <span class="w-cat__bar" style="background:<?php echo esc_attr($t->color ?: '#3B82F6'); ?>"></span>
                <div class="w-cat__body">
                    <h2 class="w-cat__name"><?php echo esc_html($t->name); ?>
                        <?php if (!(int) $t->is_active): ?><span class="w-tag"><?php echo esc_html(sc_t('dashboard_pages.hidden', 'Hidden')); ?></span><?php endif; ?>
                    </h2>
                    <p class="w-sub"><?php echo esc_html(($categories[$t->booth_category] ?? $t->booth_category) . ' · ' . $metres($t->width_meters) . ' × ' . $metres($t->depth_meters) . ' m · ' . $metres((float) $t->width_meters * (float) $t->depth_meters) . ' m²'); ?></p>
                </div>
                <p class="w-btype__price"><strong><?php echo esc_html($money($t->base_price)); ?></strong>
                    <?php if ($deposit): ?><span class="w-sub"><?php echo esc_html(sprintf(sc_t('booths.deposit_x', 'Deposit %s'), $deposit)); ?></span><?php endif; ?>
                </p>
                <?php if ($inclusions): ?>
                    <ul class="w-issue__rules"><?php foreach (array_slice($inclusions, 0, 6) as $item): ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?><?php if (count($inclusions) > 6): ?><li>+<?php echo (int) (count($inclusions) - 6); ?></li><?php endif; ?></ul>
                <?php endif; ?>
                <p class="w-cat__stats"><?php
                    echo esc_html((int) $t->booths
                        ? sprintf(sc_t('booths.type_counts', '%1$s booths · %2$s available · %3$s taken'), number_format_i18n((int) $t->booths), number_format_i18n((int) $t->open_booths), number_format_i18n((int) $t->taken))
                        : sc_t('booths.no_booths_yet', 'No booths of this type yet'));
                ?></p>
                <div class="w-cat__actions">
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($dashboard_url . 'booth-type-edit?id=' . (int) $t->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($dashboard_url . 'booths?event_id=' . $event_id . '&type_id=' . (int) $t->id . ((int) $t->booths ? '' : '&add=1')); ?>"><?php echo esc_html((int) $t->booths ? sc_t('booths.see_booths', 'See booths') : sc_t('booths.add_booths', 'Add booths')); ?></a>
                    <button type="button" class="btn btn-sm btn-link w-tpl__delete" data-delete="<?php echo (int) $t->id; ?>" data-name="<?php echo esc_attr($t->name); ?>" <?php disabled((int) $t->booths > 0); ?> title="<?php echo esc_attr((int) $t->booths ? sc_t('booths.type_in_use', 'Booths use this type') : ''); ?>"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'confirm' => sc_t('booths.confirm_delete_type', 'Delete the booth type %s? This cannot be undone.'),
        'failed'  => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    $('#event-switch').on('change', function () { window.location.href = dashboardUrl + 'booth-types?event_id=' + encodeURIComponent(this.value); });
    $('#types').on('click', '[data-delete]', function () {
        var btn = $(this);
        showDeleteConfirm(L.confirm.replace('%s', btn.data('name'))).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.post(scDashboard.ajaxurl, { action: 'sc_booth_type_delete', nonce: scDashboard.nonce, id: btn.data('delete') })
                .done(function (res) {
                    if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                    showSuccess(res.data.message);
                    btn.closest('.w-btype').remove();
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
