<?php
/**
 * Event report — registrations, check-in, tickets, workshops and certificates for one event.
 * Numbers: sc_report_event_data() in inc/admin-dashboard/reports-dashboard.php (definitions there).
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
    "SELECT id, title, status, COALESCE(end_date, start_date) >= %s AS current FROM {$p}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200",
    current_time('Y-m-d')
));
$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
if (!$event_id) {
    $event_id = (int) get_option('sc_featured_event_id', 0);
}
$ids = array_map('intval', wp_list_pluck($events, 'id'));
if (!in_array($event_id, $ids, true)) {
    $event_id = 0;
    foreach ($events as $ev) {
        if ((int) $ev->current) {
            $event_id = (int) $ev->id;
        }
    }
    $event_id = $event_id ?: ($ids[0] ?? 0);
}
$data = $event_id ? sc_report_event_data($event_id, !empty($_GET['fresh'])) : null;
$dashboard_url = home_url('/event-manager-dashboard/');
$currency = get_option('sc_currency_code', 'EGP');

$n = function ($v) { return number_format_i18n((int) $v); };
$pct = function ($part, $whole) { return $whole ? round($part / $whole * 100) : 0; };
$date = function ($d, $format = 'j M Y') { return $d ? mysql2date($format, $d) : '—'; };

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.reports', 'Reports')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('reports.sub', 'How one event is doing: who registered, who came, and what they signed up for. Registered means active with a confirmed payment; workshop seats are counted separately.')); ?></p>
        </div>
        <?php if ($data): ?>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . $event_id); ?>"><?php echo esc_html(sc_t('reports.attendee_list', 'Attendee list')); ?></a>
            <a class="btn btn-secondary" href="<?php echo esc_url(add_query_arg(array('action' => 'sc_report_export', 'event_id' => $event_id, 'nonce' => wp_create_nonce('sc_dashboard_nonce')), admin_url('admin-ajax.php'))); ?>"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?></a>
        </div>
        <?php endif; ?>
    </div>

    <div class="w-toolbar w-report__bar">
        <select class="form-control w-badges__event" id="report-event" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
            <?php foreach ($events as $ev): ?>
                <option value="<?php echo (int) $ev->id; ?>" <?php selected($event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($data): ?>
            <span class="w-sub"><?php echo esc_html(sprintf(sc_t('reports.updated', 'Figures from %1$s (%2$s).'), mysql2date('j M, H:i', $data['generated']), $data['timezone'])); ?>
                <a href="<?php echo esc_url(add_query_arg(array('event_id' => $event_id, 'fresh' => 1), $dashboard_url . 'reports')); ?>"><?php echo esc_html(sc_t('reports.refresh', 'Refresh')); ?></a></span>
        <?php endif; ?>
    </div>

    <?php if (!$data): ?>
        <div class="w-state"><p><?php echo esc_html(sc_t('reports.no_events', 'There are no events to report on yet.')); ?></p></div>
    <?php else:
        $t = $data['totals'];
        $deleted = array_sum(array_column($data['tickets'], 'deleted_ticket'));
        ?>

        <?php if ($t['duplicates'] || $deleted || $t['unpaid']): ?>
        <ul class="w-report__notes">
            <?php if ($t['unpaid']): ?><li><?php echo esc_html(sprintf(sc_t('reports.note_unpaid', '%s more registrations are waiting for payment and are not counted as registered.'), $n($t['unpaid']))); ?></li><?php endif; ?>
            <?php if ($t['duplicates']): ?><li><?php echo esc_html(sprintf(sc_t('reports.note_duplicates', '%s registrations share an email with another registration for this event (people who registered twice).'), $n($t['duplicates']))); ?></li><?php endif; ?>
            <?php if ($deleted): ?><li><?php echo esc_html(sprintf(sc_t('reports.note_deleted_tickets', '%s registrations belong to a ticket that has since been deleted or re-created; they are grouped by the ticket name saved with the registration.'), $n($deleted))); ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>

        <div class="w-kpis w-report__kpis">
            <div class="w-kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('reports.registered', 'Registered')); ?></span>
                <span class="w-kpi__value"><?php echo esc_html($n($t['registered'])); ?></span>
                <span class="w-kpi__sub"><?php echo esc_html($data['event']['capacity'] ? sprintf(sc_t('reports.of_capacity', '%1$s%% of %2$s places'), $pct($t['registered'], $data['event']['capacity']), $n($data['event']['capacity'])) : sprintf(sc_t('reports.since', 'Since %s'), $date($t['first_registration'], 'j M'))); ?></span>
            </div>
            <div class="w-kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('reports.checked_in', 'Checked in')); ?></span>
                <span class="w-kpi__value"><?php echo esc_html($n($t['checked_in'])); ?></span>
                <span class="w-kpi__sub"><?php echo esc_html(sprintf(sc_t('reports.checkin_rate', '%s%% of registered'), number_format_i18n($t['checkin_rate'], 1))); ?></span>
            </div>
            <div class="w-kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('reports.workshop_seats', 'Workshop seats')); ?></span>
                <span class="w-kpi__value"><?php echo esc_html($n($t['workshop_seats'])); ?></span>
                <span class="w-kpi__sub"><?php echo esc_html(sprintf(sc_t('reports.n_workshops', '%1$s workshops · %2$s checked in'), $n(count($data['workshops'])), $n($t['workshop_checked_in']))); ?></span>
            </div>
            <div class="w-kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('reports.certificates', 'Certificates')); ?></span>
                <span class="w-kpi__value"><?php echo esc_html($n($t['certificates'])); ?></span>
                <span class="w-kpi__sub"><?php echo esc_html($t['checked_in'] ? sprintf(sc_t('reports.cert_share', '%1$s%% of those who came · %2$s downloaded'), $pct($t['certificates'], $t['checked_in']), $n($t['certificates_downloaded'])) : sprintf(sc_t('reports.n_downloaded', '%s downloaded'), $n($t['certificates_downloaded']))); ?></span>
            </div>
            <div class="w-kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('reports.revenue', 'Revenue')); ?></span>
                <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n($t['revenue'], $t['revenue'] == floor($t['revenue']) ? 0 : 2) . ' ' . $currency); ?></span>
                <span class="w-kpi__sub"><?php echo esc_html($t['revenue'] > 0 ? sprintf(sc_t('reports.coupon_share', '%s%% registered with a coupon'), $pct($t['with_coupon'], $t['registered'])) : sc_t('reports.all_free', 'Every registration was free or by coupon')); ?></span>
            </div>
        </div>

        <div class="w-report__grid">
            <section class="w-section w-report__wide" aria-labelledby="r-days">
                <div class="w-section__head">
                    <h2 id="r-days"><?php echo esc_html(sc_t('reports.by_day', 'Registrations by day')); ?></h2>
                    <?php if ($data['days']):
                        $peak = array_reduce($data['days'], function ($c, $d) { return !$c || $d['n'] > $c['n'] ? $d : $c; });
                        ?>
                        <span class="w-section__hint"><?php echo esc_html(sprintf(sc_t('reports.busiest_day', 'Busiest: %1$s (%2$s)'), $date($peak['d'], 'j M Y'), $n($peak['n']))); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!$data['days']): ?>
                    <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_registrations', 'No registrations yet.')); ?></p>
                <?php else:
                    $max = max(array_column($data['days'], 'n')) ?: 1;
                    $count = count($data['days']);
                    ?>
                    <div class="w-bars" role="img" aria-label="<?php echo esc_attr(sprintf(sc_t('reports.by_day_aria', '%1$s registrations over %2$s days'), $n($t['registered']), $n($count))); ?>">
                        <?php foreach ($data['days'] as $d): ?>
                            <span class="w-bars__bar" style="height:<?php echo esc_attr(max($d['n'] ? 2 : 0, round($d['n'] / $max * 100, 1))); ?>%" title="<?php echo esc_attr(mysql2date('j M Y', $d['d']) . ': ' . $n($d['n'])); ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-bars__axis"><span><?php echo esc_html($date($data['days'][0]['d'], 'j M Y')); ?></span><?php if ($count > 2): ?><span><?php echo esc_html($date($data['days'][intdiv($count, 2)]['d'], 'j M')); ?></span><?php endif; ?><span><?php echo esc_html($date($data['days'][$count - 1]['d'], 'j M Y')); ?></span></div>
                <?php endif; ?>
            </section>

            <section class="w-section w-report__wide" aria-labelledby="r-hours">
                <div class="w-section__head"><h2 id="r-hours"><?php echo esc_html(sc_t('reports.by_hour', 'Arrivals by hour')); ?></h2><span class="w-section__hint"><?php echo esc_html(sprintf(sc_t('reports.hours_tz', 'Scan times, %s'), $data['timezone'])); ?></span></div>
                <?php if (!$data['checkin_days']): ?>
                    <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_checkins', 'Nobody has checked in yet.')); ?></p>
                <?php else:
                    $all_hours = array();
                    foreach ($data['checkin_days'] as $hours) { $all_hours = array_merge($all_hours, array_keys($hours)); }
                    $h_from = min($all_hours);
                    $h_to = max($all_hours);
                    $h_max = max(array_map('max', $data['checkin_days'])) ?: 1;
                    ?>
                    <div class="w-heat" style="--cols:<?php echo (int) ($h_to - $h_from + 1); ?>">
                        <?php foreach ($data['checkin_days'] as $day => $hours): ?>
                            <span class="w-heat__day"><?php echo esc_html(mysql2date('D j M', $day)); ?> <span class="w-sub"><?php echo esc_html($n(array_sum($hours))); ?></span></span>
                            <?php for ($h = $h_from; $h <= $h_to; $h++): $v = $hours[$h] ?? 0; ?>
                                <span class="w-heat__cell" style="--a:<?php echo esc_attr($v ? round(0.12 + 0.88 * $v / $h_max, 2) : 0); ?>" title="<?php echo esc_attr(sprintf('%s %02d:00 — %s', mysql2date('j M', $day), $h, $n($v))); ?>"></span>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                        <span></span>
                        <?php for ($h = $h_from; $h <= $h_to; $h++): ?><span class="w-heat__hour"><?php echo esc_html(($h - $h_from) % 2 === 0 ? sprintf('%02d', $h) : ''); ?></span><?php endfor; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="w-section" aria-labelledby="r-tickets">
                <div class="w-section__head"><h2 id="r-tickets"><?php echo esc_html(sc_t('reports.tickets', 'Tickets')); ?></h2></div>
                <?php if (!$data['tickets']): ?>
                    <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_registrations', 'No registrations yet.')); ?></p>
                <?php else: ?>
                <div class="w-table-scroll"><table class="w-table w-report__table">
                    <thead><tr><th scope="col"><?php echo esc_html(sc_t('reports.ticket', 'Ticket')); ?></th><th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.registered', 'Registered')); ?></th><th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.came', 'Came')); ?></th><?php if ($t['revenue'] > 0): ?><th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.revenue', 'Revenue')); ?></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($data['tickets'] as $row): ?>
                        <tr>
                            <td><span class="w-truncate" dir="auto"><?php echo esc_html($row['name']); ?></span></td>
                            <td class="w-num"><?php echo esc_html($n($row['registered'])); ?> <span class="w-sub"><?php echo esc_html($pct($row['registered'], $t['registered'])); ?>%</span></td>
                            <td class="w-num"><?php echo esc_html($n($row['checked_in'])); ?> <span class="w-sub"><?php echo esc_html($pct($row['checked_in'], $row['registered'])); ?>%</span></td>
                            <?php if ($t['revenue'] > 0): ?><td class="w-num w-ltr"><?php echo esc_html(number_format_i18n($row['revenue'])); ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </section>

            <section class="w-section" aria-labelledby="r-how">
                <div class="w-section__head"><h2 id="r-how"><?php echo esc_html(sc_t('reports.how', 'How they registered')); ?></h2></div>
                <?php
                $method_labels = array('coupon' => sc_t('reports.m_coupon', 'Coupon'), 'free' => sc_t('reports.m_free', 'Free ticket'), 'paymob' => 'Paymob', 'kashier' => 'Kashier', 'stripe' => 'Stripe', 'myfatoorah' => 'MyFatoorah', 'manual' => sc_t('reports.m_manual', 'Added by staff'), 'cash' => sc_t('reports.m_cash', 'Cash'), 'unknown' => sc_t('reports.m_unknown', 'Not recorded'));
                if (!$data['methods']): ?>
                    <p class="w-sub mb-0"><?php echo esc_html(sc_t('reports.no_registrations', 'No registrations yet.')); ?></p>
                <?php else: ?>
                    <ul class="w-meters">
                        <?php foreach ($data['methods'] as $m): ?>
                            <li class="w-meter">
                                <div class="w-meter__row"><span><?php echo esc_html($method_labels[$m['method']] ?? ucfirst($m['method'])); ?></span><strong><?php echo esc_html($n($m['n']) . ' · ' . $pct($m['n'], $t['registered']) . '%'); ?></strong></div>
                                <div class="w-meter__track"><div class="w-meter__fill" style="width:<?php echo esc_attr($pct($m['n'], $t['registered'])); ?>%"></div></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($data['workshops']): ?>
            <section class="w-section w-report__wide" aria-labelledby="r-ws">
                <div class="w-section__head"><h2 id="r-ws"><?php echo esc_html(sc_t('reports.workshops', 'Workshops')); ?></h2></div>
                <div class="w-table-scroll"><table class="w-table w-report__table">
                    <thead><tr><th scope="col"><?php echo esc_html(sc_t('reports.workshop', 'Workshop')); ?></th><th scope="col"><?php echo esc_html(sc_t('reports.date', 'Date')); ?></th><th scope="col" class="w-report__meter-col"><?php echo esc_html(sc_t('reports.seats_taken', 'Seats taken')); ?></th><th scope="col" class="w-num"><?php echo esc_html(sc_t('reports.came', 'Came')); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($data['workshops'] as $w): ?>
                        <tr>
                            <td><a class="w-truncate" dir="auto" href="<?php echo esc_url($dashboard_url . 'workshop-attendees?workshop_id=' . $w['id']); ?>"><?php echo esc_html($w['title']); ?></a></td>
                            <td class="w-nowrap"><?php echo esc_html($date($w['start'], 'j M')); ?></td>
                            <td class="w-report__meter-col">
                                <div class="w-meter__row"><span><?php echo esc_html($n($w['registered']) . ($w['capacity'] ? ' / ' . $n($w['capacity']) : '')); ?></span><strong><?php echo esc_html($w['capacity'] ? $pct($w['registered'], $w['capacity']) . '%' : ''); ?></strong></div>
                                <?php if ($w['capacity']): ?><div class="w-meter__track"><div class="w-meter__fill<?php echo $w['registered'] >= $w['capacity'] ? ' is-high' : ''; ?>" style="width:<?php echo esc_attr(min(100, $pct($w['registered'], $w['capacity']))); ?>%"></div></div><?php endif; ?>
                            </td>
                            <td class="w-num"><?php echo esc_html($n($w['checked_in'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var url = <?php echo wp_json_encode($dashboard_url . 'reports'); ?>;
    $('#report-event').on('change', function () { window.location.href = url + '?event_id=' + encodeURIComponent(this.value); });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
