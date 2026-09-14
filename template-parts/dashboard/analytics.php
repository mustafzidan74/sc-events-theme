<?php
/**
 * Analytics — every event side by side, and registrations per month across the platform.
 * Numbers: sc_report_overview_data() in inc/admin-dashboard/reports-dashboard.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list, $load_wd_overview;
$load_wd_list = true;
$load_wd_overview = true;

$data = sc_report_overview_data(!empty($_GET['fresh']));
$dashboard_url = home_url('/event-manager-dashboard/');
$currency = get_option('sc_currency_code', 'EGP');
$n = function ($v) { return number_format_i18n((int) $v); };

$with_people = array_values(array_filter($data['events'], function ($e) { return $e['registered'] > 0; }));
$total_reg = array_sum(array_column($data['events'], 'registered'));
$total_in = array_sum(array_column($data['events'], 'checked_in'));
$past = array_values(array_filter($with_people, function ($e) { return $e['checked_in'] > 0; }));
$avg_rate = $past ? round(array_sum(array_column($past, 'checkin_rate')) / count($past), 1) : null;
$total_certs = array_sum(array_column($data['events'], 'certificates'));
$total_revenue = array_sum(array_column($data['events'], 'revenue'));
$status_labels = array('publish' => sc_t('reports.upcoming', 'Published'), 'completed' => sc_t('reports.completed', 'Completed'), 'draft' => sc_t('reports.draft', 'Draft'));

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.analytics', 'Analytics')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('analytics.sub', 'All events side by side. Open an event\'s report for its days, tickets and workshops.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <span class="w-sub"><?php echo esc_html(sprintf(sc_t('reports.updated_short', 'Figures from %s'), mysql2date('j M, H:i', $data['generated']))); ?>
                <a href="<?php echo esc_url($dashboard_url . 'analytics?fresh=1'); ?>"><?php echo esc_html(sc_t('reports.refresh', 'Refresh')); ?></a></span>
        </div>
    </div>

    <div class="w-kpis">
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('analytics.registered_all', 'Registered, all events')); ?></span><span class="w-kpi__value"><?php echo esc_html($n($total_reg)); ?></span><span class="w-kpi__sub"><?php echo esc_html(sprintf(sc_t('analytics.across', 'across %s events'), $n(count($with_people)))); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('analytics.came', 'Came')); ?></span><span class="w-kpi__value"><?php echo esc_html($n($total_in)); ?></span><span class="w-kpi__sub"><?php echo esc_html($avg_rate !== null ? sprintf(sc_t('analytics.avg_rate', 'Average check-in rate %s%%'), number_format_i18n($avg_rate, 1)) : '—'); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('reports.certificates', 'Certificates')); ?></span><span class="w-kpi__value"><?php echo esc_html($n($total_certs)); ?></span><span class="w-kpi__sub"><?php echo esc_html($total_in ? sprintf(sc_t('analytics.cert_share', '%s%% of people who came'), round($total_certs / $total_in * 100)) : '—'); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('reports.revenue', 'Revenue')); ?></span><span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n($total_revenue) . ' ' . $currency); ?></span><span class="w-kpi__sub"><?php echo esc_html($total_revenue > 0 ? '' : sc_t('reports.all_free_all', 'All registrations so far were free or by coupon')); ?></span></div>
    </div>

    <section class="w-section" aria-labelledby="a-events">
        <div class="w-section__head"><h2 id="a-events"><?php echo esc_html(sc_t('analytics.events', 'Events')); ?></h2></div>
        <?php if (!$data['events']): ?>
            <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_events', 'There are no events to report on yet.')); ?></p>
        <?php else:
            $max_reg = max(array_column($data['events'], 'registered')) ?: 1; ?>
            <div class="w-table-scroll"><table class="w-table w-report__table w-analytics__table">
                <thead><tr>
                    <th scope="col"><?php echo esc_html(sc_t('events.event', 'Event')); ?></th>
                    <th scope="col" class="w-analytics__barcol"><?php echo esc_html(sc_t('analytics.registered_came', 'Registered and came')); ?></th>
                    <th scope="col" class="w-num"><?php echo esc_html(sc_t('analytics.rate', 'Check-in rate')); ?></th>
                    <th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.workshop_seats', 'Workshop seats')); ?></th>
                    <th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.certificates', 'Certificates')); ?></th>
                    <?php if ($total_revenue > 0): ?><th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.revenue', 'Revenue')); ?></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($data['events'] as $e): ?>
                    <tr>
                        <td>
                            <div class="w-stack">
                                <a class="w-row-title w-truncate" dir="auto" href="<?php echo esc_url($dashboard_url . 'reports?event_id=' . $e['id']); ?>"><?php echo esc_html($e['title']); ?></a>
                                <span class="w-sub w-nowrap"><?php echo esc_html(($e['start'] ? mysql2date('j M Y', $e['start']) : '—') . ' · ' . ($status_labels[$e['status']] ?? $e['status'])); ?></span>
                            </div>
                        </td>
                        <td class="w-analytics__barcol">
                            <div class="w-duo" title="<?php echo esc_attr(sprintf(sc_t('analytics.duo_title', '%1$s registered, %2$s came'), $n($e['registered']), $n($e['checked_in']))); ?>">
                                <span class="w-duo__reg" style="width:<?php echo esc_attr(round($e['registered'] / $max_reg * 100, 1)); ?>%"><span class="w-duo__in" style="width:<?php echo esc_attr($e['registered'] ? round($e['checked_in'] / $e['registered'] * 100, 1) : 0); ?>%"></span></span>
                            </div>
                            <span class="w-sub w-nowrap"><?php echo esc_html($n($e['registered']) . ' / ' . $n($e['checked_in'])); ?></span>
                        </td>
                        <td class="w-num"><?php echo esc_html($e['checkin_rate'] !== null && $e['checked_in'] ? number_format_i18n($e['checkin_rate'], 1) . '%' : '—'); ?></td>
                        <td class="w-num"><?php echo esc_html($e['workshop_seats'] ? $n($e['workshop_seats']) : '—'); ?></td>
                        <td class="w-num"><?php echo esc_html($e['certificates'] ? $n($e['certificates']) : '—'); ?><?php if ($e['certificates']): ?> <span class="w-sub"><?php echo esc_html(sprintf(sc_t('analytics.downloaded_n', '%s downloaded'), $n($e['certificates_downloaded']))); ?></span><?php endif; ?></td>
                        <?php if ($total_revenue > 0): ?><td class="w-num w-ltr"><?php echo esc_html(number_format_i18n($e['revenue'])); ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="w-sub mt-2 mb-0"><span class="w-duo__key w-duo__key--reg"></span><?php echo esc_html(sc_t('reports.registered', 'Registered')); ?> <span class="w-duo__key w-duo__key--in"></span><?php echo esc_html(sc_t('analytics.came', 'Came')); ?></p>
        <?php endif; ?>
    </section>

    <section class="w-section" aria-labelledby="a-months">
        <div class="w-section__head"><h2 id="a-months"><?php echo esc_html(sc_t('analytics.by_month', 'Registrations by month')); ?></h2></div>
        <?php if (!$data['months']): ?>
            <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_registrations', 'No registrations yet.')); ?></p>
        <?php else:
            $m_max = max(array_column($data['months'], 'n')) ?: 1; ?>
            <div class="w-bars w-bars--months" role="img" aria-label="<?php echo esc_attr(sc_t('analytics.by_month', 'Registrations by month')); ?>">
                <?php foreach ($data['months'] as $m): ?>
                    <span class="w-bars__col">
                        <span class="w-bars__value"><?php echo esc_html($m['n'] ? $n($m['n']) : ''); ?></span>
                        <span class="w-bars__track"><span class="w-bars__bar" style="height:<?php echo esc_attr(max($m['n'] ? 2 : 0, round($m['n'] / $m_max * 100, 1))); ?>%" title="<?php echo esc_attr(mysql2date('F Y', $m['m'] . '-01') . ': ' . $n($m['n'])); ?>"></span></span>
                        <span class="w-bars__label"><?php echo esc_html(mysql2date('M y', $m['m'] . '-01')); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
