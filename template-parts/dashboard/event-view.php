<?php
/**
 * Event overview — read-only summary of one event (overview pattern).
 *
 * Every figure is counted from the attendees, tickets, workshops and
 * certificates tables at page load; nothing comes from cached totals.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$event_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$event = $event_id && class_exists('SC_Event') ? SC_Event::get($event_id) : null;
if (!$event) {
    wp_safe_redirect(home_url('/event-manager-dashboard/events'));
    exit;
}

global $wpdb, $load_wd_overview;
$load_wd_overview = true;
$p = $wpdb->prefix;
$dashboard_url = home_url('/event-manager-dashboard/');
$live = "status = 'active' AND payment_status = 'success'";

/* ------------------------------------------------------------------ figures */

$totals = $wpdb->get_row($wpdb->prepare(
    "SELECT COALESCE(SUM(workshop_id IS NULL), 0) AS registered,
            COALESCE(SUM(workshop_id IS NULL AND checked_in = 1), 0) AS checked_in,
            COALESCE(SUM(workshop_id IS NOT NULL), 0) AS workshop_regs,
            COALESCE(SUM(amount_paid), 0) AS revenue,
            MIN(created_at) AS first_at, MAX(created_at) AS last_at
     FROM {$p}sc_attendees WHERE event_id = %d AND {$live}",
    $event_id
));
$registered = (int) $totals->registered;
$checked_in = (int) $totals->checked_in;

$today = current_time('Y-m-d');
$end_date = $event->end_date ?: $event->start_date;
$days_to_start = (int) floor((strtotime($event->start_date) - strtotime($today)) / DAY_IN_SECONDS);
$started = $days_to_start <= 0;
$ended = $end_date < $today;

// Registrations over time: daily for up to 60 days, weekly beyond that.
$trend = array();
$trend_unit = 'day';
if ($totals->first_at) {
    $last_day = min($today, max(substr($totals->last_at, 0, 10), $end_date));
    $span = (int) round((strtotime($last_day) - strtotime(substr($totals->first_at, 0, 10))) / DAY_IN_SECONDS) + 1;
    if ($span <= 60) {
        $from = date('Y-m-d', strtotime($last_day . ' -' . (max($span, 14) - 1) . ' days'));
        $counts = array();
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM {$p}sc_attendees WHERE event_id = %d AND {$live} AND created_at >= %s GROUP BY d",
            $event_id,
            $from . ' 00:00:00'
        )) as $r) {
            $counts[$r->d] = (int) $r->n;
        }
        for ($d = $from; $d <= $last_day; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $trend[] = array('label' => $d, 'n' => $counts[$d] ?? 0);
        }
    } else {
        $trend_unit = 'week';
        $weeks = min(26, (int) ceil($span / 7));
        $from = date('Y-m-d', strtotime($last_day . ' -' . ($weeks * 7 - 1) . ' days'));
        $counts = array();
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT FLOOR(DATEDIFF(DATE(created_at), %s) / 7) AS w, COUNT(*) AS n FROM {$p}sc_attendees WHERE event_id = %d AND {$live} AND created_at >= %s GROUP BY w",
            $from,
            $event_id,
            $from . ' 00:00:00'
        )) as $r) {
            $counts[(int) $r->w] = (int) $r->n;
        }
        for ($w = 0; $w < $weeks; $w++) {
            $trend[] = array('label' => date('Y-m-d', strtotime($from . ' +' . ($w * 7) . ' days')), 'n' => $counts[$w] ?? 0);
        }
    }
}
$trend_max = $trend ? max(1, max(array_column($trend, 'n'))) : 1;

// First check-in per person, by day, once the event has started.
$checkin_days = array();
if ($started && $checked_in) {
    $checkin_days = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(checked_in_at) AS d, COUNT(*) AS n FROM {$p}sc_attendees
         WHERE event_id = %d AND workshop_id IS NULL AND {$live} AND checked_in = 1 AND checked_in_at IS NOT NULL
         GROUP BY d ORDER BY d",
        $event_id
    ));
}

// Tickets with their live holders; registrations on tickets that were since removed are counted apart.
$tickets = $wpdb->get_results($wpdb->prepare(
    "SELECT t.id, t.name, t.price, t.quantity, t.is_active, t.enable_coupons, t.ticket_type,
            (SELECT COUNT(*) FROM {$p}sc_attendees a WHERE a.ticket_id = t.id AND a.event_id = t.event_id AND a.workshop_id IS NULL AND a.{$live}) AS holders
     FROM {$p}sc_tickets t WHERE t.event_id = %d AND (t.workshop_id IS NULL OR t.workshop_id = 0) ORDER BY t.sort_order, t.id",
    $event_id
));
$held_on_current = array_sum(array_map('intval', wp_list_pluck($tickets, 'holders')));
$orphaned = max(0, $registered - $held_on_current);

$workshops = $wpdb->get_results($wpdb->prepare(
    "SELECT w.id, w.title, w.start_date, w.start_time, w.total_capacity, w.status,
            (SELECT COUNT(*) FROM {$p}sc_attendees a WHERE a.workshop_id = w.id AND a.{$live}) AS registered,
            (SELECT COUNT(*) FROM {$p}sc_tickets t WHERE t.workshop_id = w.id) AS tickets
     FROM {$p}sc_workshops w WHERE w.event_id = %d ORDER BY w.start_date, w.start_time, w.title",
    $event_id
));

$recent = $wpdb->get_results($wpdb->prepare(
    "SELECT a.id, a.name, a.ticket_name, a.created_at, a.checked_in, w.title AS workshop
     FROM {$p}sc_attendees a LEFT JOIN {$p}sc_workshops w ON w.id = a.workshop_id
     WHERE a.event_id = %d AND a.{$live} ORDER BY a.created_at DESC, a.id DESC LIMIT 8",
    $event_id
));

$count_links = function ($pivot) use ($wpdb, $p, $event_id) {
    $table = "{$p}sc_event_{$pivot}";
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $event_id));
};
$links_count = array(
    'speakers' => $count_links('speakers'),
    'sponsors' => $count_links('sponsors'),
    'partners' => $count_links('partners'),
);
$sessions = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_schedules WHERE event_id = %d AND is_active = 1", $event_id));
$certificates = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_certificates WHERE event_id = %d AND workshop_id IS NULL AND status <> 'revoked'", $event_id));

/* ------------------------------------------------------------------ labels */

$status_labels = array(
    'publish'   => sc_t('dashboard_pages.status_published', 'Published'),
    'draft'     => sc_t('dashboard_pages.status_draft', 'Draft'),
    'completed' => sc_t('dashboard_pages.status_completed', 'Completed'),
    'disabled'  => sc_t('dashboard_pages.status_disabled', 'Disabled'),
    'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled'),
    'private'   => sc_t('dashboard_pages.status_private', 'Private'),
);
$status_tone = array('publish' => 'teal', 'cancelled' => 'red', 'disabled' => 'red');
if ($ended) {
    $when = sc_t('dashboard_pages.ended', 'Ended');
} elseif ($started) {
    $when = sc_t('dashboard_pages.happening_now', 'Happening now');
} elseif ($days_to_start === 1) {
    $when = sc_t('dashboard_pages.tomorrow', 'Tomorrow');
} else {
    $when = sprintf(sc_t('dashboard_pages.in_n_days', 'In %d days'), $days_to_start);
}

$date_range = mysql2date('D j M Y', $event->start_date);
if ($end_date !== $event->start_date) {
    $date_range .= ' – ' . mysql2date('D j M Y', $end_date);
}
$clock = '';
if ($event->start_time && $event->start_time !== '00:00:00') {
    $clock = substr($event->start_time, 0, 5) . ($event->end_time && $event->end_time !== '00:00:00' ? '–' . substr($event->end_time, 0, 5) : '');
}
$place = $event->location_type === 'online' ? sc_t('dashboard_pages.online', 'Online') : trim(implode(', ', array_filter(array($event->venue_name, $event->venue_city))));
$currency = get_option('sc_currency_code', 'EGP');
$public_url = home_url('/event/' . $event->slug . '/');
$pct = function ($part, $whole) {
    return $whole > 0 ? (int) round($part / $whole * 100) : 0;
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($event->title); ?></h1>
            <div class="w-ov-meta">
                <span class="w-tag<?php echo isset($status_tone[$event->status]) ? ' w-tag--' . esc_attr($status_tone[$event->status]) : ''; ?>"><?php echo esc_html($status_labels[$event->status] ?? $event->status); ?></span>
                <span><i class="fa fa-calendar" aria-hidden="true"></i><span class="w-ltr"><?php echo esc_html($date_range . ($clock ? ' · ' . $clock : '')); ?></span> · <?php echo esc_html($when); ?></span>
                <?php if ($place): ?><span><i class="fa fa-map-marker" aria-hidden="true"></i><?php echo esc_html($place); ?></span><?php endif; ?>
            </div>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.view_on_site', 'View on site')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id); ?>"><i class="fa fa-pencil" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.edit_event', 'Edit event')); ?></a>
        </div>
    </div>

    <div class="w-kpis">
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n($registered)); ?></span>
            <span class="w-kpi__sub"><?php echo $totals->last_at ? esc_html(sprintf(sc_t('dashboard_pages.last_registration', 'Latest %s'), human_time_diff(strtotime($totals->last_at), current_time('timestamp')) . ' ' . sc_t('dashboard_pages.ago', 'ago'))) : esc_html(sc_t('dashboard_pages.no_registrations', 'No registrations yet')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n($checked_in)); ?></span>
            <span class="w-kpi__sub"><?php echo $registered ? '<strong class="w-ltr">' . esc_html($pct($checked_in, $registered)) . '%</strong> ' . esc_html(sc_t('dashboard_pages.of_registered', 'of registered')) : '—'; ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.in_workshops', 'In workshops')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n((int) $totals->workshop_regs)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html(sprintf(sc_t('dashboard_pages.n_workshops', '%d workshops'), count($workshops))); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.revenue', 'Revenue')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n((float) $totals->revenue)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html($currency); ?></span>
        </div>
    </div>

    <div class="w-ov-grid">
        <div class="w-ov-col">

            <section class="w-panel" aria-labelledby="trend-title">
                <div class="w-panel__head">
                    <h2 id="trend-title"><?php echo esc_html(sc_t('dashboard_pages.registrations', 'Registrations')); ?></h2>
                    <span class="w-panel__hint"><?php echo esc_html($trend_unit === 'week' ? sc_t('dashboard_pages.per_week', 'Per week') : sc_t('dashboard_pages.per_day', 'Per day')); ?></span>
                </div>
                <?php if ($trend): ?>
                    <div class="w-bars" role="img" aria-label="<?php echo esc_attr(sprintf(sc_t('dashboard_pages.trend_aria', '%1$s registrations between %2$s and %3$s'), number_format_i18n(array_sum(array_column($trend, 'n'))), $trend[0]['label'], end($trend)['label'])); ?>">
                        <?php foreach ($trend as $bar): ?>
                            <span class="w-bars__col<?php echo $bar['n'] ? '' : ' is-zero'; ?>">
                                <span class="w-bars__bar" style="height:<?php echo esc_attr(max(1.5, $bar['n'] / $trend_max * 100)); ?>%"></span>
                                <span class="w-bars__tip"><?php echo esc_html(mysql2date('j M', $bar['label']) . ': ' . number_format_i18n($bar['n'])); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-bars__axis w-ltr">
                        <span><?php echo esc_html(mysql2date('j M', $trend[0]['label'])); ?></span>
                        <span><?php echo esc_html(mysql2date('j M', end($trend)['label'])); ?></span>
                    </div>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_registrations', 'No registrations yet')); ?></p>
                <?php endif; ?>
            </section>

            <?php if ($checkin_days): ?>
            <section class="w-panel" aria-labelledby="checkin-title">
                <div class="w-panel__head">
                    <h2 id="checkin-title"><?php echo esc_html(sc_t('dashboard_pages.checkins_by_day', 'Check-ins by day')); ?></h2>
                    <span class="w-panel__hint"><?php echo esc_html(sc_t('dashboard_pages.first_scan_only', 'First scan per person')); ?></span>
                </div>
                <?php foreach ($checkin_days as $day): $share = $pct((int) $day->n, $registered); ?>
                <div class="w-meter">
                    <div class="w-meter__row"><span><?php echo esc_html(mysql2date('D j M', $day->d)); ?></span><strong class="w-ltr"><?php echo esc_html(number_format_i18n((int) $day->n)); ?> · <?php echo esc_html($share); ?>%</strong></div>
                    <div class="w-meter__track"><div class="w-meter__fill" style="width:<?php echo esc_attr($share); ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section class="w-panel" aria-labelledby="tickets-title">
                <div class="w-panel__head">
                    <h2 id="tickets-title"><?php echo esc_html(sc_t('dashboard_pages.tickets', 'Tickets')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id . '#tickets'); ?>"><?php echo esc_html(sc_t('dashboard_pages.manage', 'Manage')); ?> →</a>
                </div>
                <?php if ($tickets): ?>
                <div class="w-subtable">
                    <table>
                        <thead><tr>
                            <th><?php echo esc_html(sc_t('dashboard_pages.ticket', 'Ticket')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.price', 'Price')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong><?php echo esc_html($t->name); ?></strong></td>
                                <td class="w-ltr"><?php
                                    if ((int) $t->enable_coupons && (float) $t->price <= 0) {
                                        echo '<span class="w-tag w-tag--gold">' . esc_html(sc_t('dashboard_pages.coupon_only_short', 'Coupon')) . '</span>';
                                    } elseif ((float) $t->price > 0) {
                                        echo esc_html(number_format_i18n((float) $t->price, 2) . ' ' . $currency);
                                    } else {
                                        echo esc_html(sc_t('general.free', 'Free'));
                                    }
                                ?></td>
                                <td class="w-ltr"><?php echo esc_html(number_format_i18n((int) $t->holders) . ' / ' . ((int) $t->quantity > 0 ? number_format_i18n((int) $t->quantity) : sc_t('dashboard_pages.unlimited', 'unlimited'))); ?></td>
                                <td><?php echo (int) $t->is_active ? '<span class="w-tag w-tag--teal">' . esc_html(sc_t('dashboard_pages.ticket_on_sale', 'On sale')) . '</span>' : '<span class="w-tag">' . esc_html(sc_t('dashboard_pages.ticket_off_sale', 'Off sale')) . '</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_event_tickets', 'No tickets yet — without one, nobody can register.')); ?></p>
                <?php endif; ?>
                <?php if ($orphaned > 0): ?>
                    <p class="w-panel__note"><?php echo esc_html(sprintf(sc_t('dashboard_pages.orphaned_registrations', '%s people registered with tickets that no longer exist, so they are not counted in the rows above. Their registrations are still valid.'), number_format_i18n($orphaned))); ?></p>
                <?php endif; ?>
            </section>

            <section class="w-panel" aria-labelledby="workshops-title">
                <div class="w-panel__head">
                    <h2 id="workshops-title"><?php echo esc_html(sc_t('dashboard_pages.workshops', 'Workshops')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'workshops?event_id=' . $event_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_all', 'View all')); ?> →</a>
                </div>
                <?php if ($workshops): ?>
                <div class="w-subtable">
                    <table>
                        <thead><tr>
                            <th><?php echo esc_html(sc_t('dashboard_pages.workshop', 'Workshop')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.date', 'Date')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.seats', 'Seats')); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($workshops as $w): $cap = (int) $w->total_capacity; $reg = (int) $w->registered; ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($dashboard_url . 'workshop-edit?id=' . (int) $w->id); ?>"><strong><?php echo esc_html($w->title); ?></strong></a>
                                    <?php if (!(int) $w->tickets): ?> <span class="w-tag w-tag--red"><?php echo esc_html(sc_t('dashboard_pages.no_tickets_short', 'No tickets')); ?></span><?php endif; ?>
                                    <?php if ($w->status !== 'publish'): ?> <span class="w-tag"><?php echo esc_html($status_labels[$w->status] ?? $w->status); ?></span><?php endif; ?>
                                </td>
                                <td class="w-ltr"><?php echo esc_html(mysql2date('D j M', $w->start_date) . ($w->start_time ? ' · ' . substr($w->start_time, 0, 5) : '')); ?></td>
                                <td class="w-ltr"><?php echo esc_html(number_format_i18n($reg) . ($cap > 0 ? ' / ' . number_format_i18n($cap) : '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_workshops_for_event', 'No workshops for this event.')); ?></p>
                <?php endif; ?>
            </section>

            <section class="w-panel" aria-labelledby="recent-title">
                <div class="w-panel__head">
                    <h2 id="recent-title"><?php echo esc_html(sc_t('dashboard_pages.recent_registrations', 'Latest registrations')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . $event_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_all', 'View all')); ?> →</a>
                </div>
                <?php if ($recent): ?>
                <ul class="w-rows">
                    <?php foreach ($recent as $a):
                        $parts = preg_split('/\s+/', trim((string) $a->name));
                        $initials = strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
                    ?>
                    <li class="w-rows__item">
                        <span class="w-person__avatar" data-tone="<?php echo (int) $a->id % 4; ?>" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                        <span class="w-rows__main">
                            <strong><?php echo esc_html($a->name); ?></strong>
                            <span class="w-sub"><?php echo esc_html($a->workshop ? $a->workshop : $a->ticket_name); ?></span>
                        </span>
                        <span class="w-rows__meta">
                            <?php echo esc_html(human_time_diff(strtotime($a->created_at), current_time('timestamp')) . ' ' . sc_t('dashboard_pages.ago', 'ago')); ?>
                            <?php if ((int) $a->checked_in): ?><br><span class="w-state-dot w-state-dot--on"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span><?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_registrations', 'No registrations yet')); ?></p>
                <?php endif; ?>
            </section>
        </div>

        <div class="w-ov-col">
            <section class="w-panel" aria-labelledby="actions-title">
                <div class="w-panel__head"><h2 id="actions-title"><?php echo esc_html(sc_t('dashboard_pages.quick_actions', 'Quick actions')); ?></h2></div>
                <div class="w-links">
                    <a href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . $event_id); ?>"><i class="fa fa-users" aria-hidden="true"></i><?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'attendee-add?event_id=' . $event_id); ?>"><i class="fa fa-user-plus" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.add_attendee', 'Add attendee')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'scanner?event_id=' . $event_id); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.scanner', 'Scanner')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'workshops?event_id=' . $event_id); ?>"><i class="fa fa-flask" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.workshops', 'Workshops')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'schedules'); ?>"><i class="fa fa-clock-o" aria-hidden="true"></i><?php echo esc_html(sprintf(sc_t('dashboard_pages.schedule_n', 'Schedule (%s)'), number_format_i18n($sessions))); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'reports?event_id=' . $event_id); ?>"><i class="fa fa-bar-chart" aria-hidden="true"></i><?php echo esc_html(sc_t('nav.reports', 'Reports')); ?></a>
                </div>
            </section>

            <section class="w-panel" aria-labelledby="details-title">
                <div class="w-panel__head">
                    <h2 id="details-title"><?php echo esc_html(sc_t('dashboard_pages.event_details', 'Details')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?> →</a>
                </div>
                <dl class="w-kv">
                    <dt><?php echo esc_html(sc_t('dashboard_pages.url', 'Web address')); ?></dt>
                    <dd class="w-ltr"><a href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener">/event/<?php echo esc_html($event->slug); ?>/</a></dd>
                    <dt><?php echo esc_html(sc_t('dashboard_pages.venue_type', 'Where')); ?></dt>
                    <dd><?php echo esc_html(array('offline' => sc_t('dashboard_pages.in_person', 'In person'), 'online' => sc_t('dashboard_pages.online', 'Online'), 'hybrid' => sc_t('dashboard_pages.hybrid', 'Hybrid'))[$event->location_type] ?? $event->location_type); ?><?php echo $event->venue_address && $event->venue_address !== $event->venue_name ? '<span class="w-sub">' . esc_html($event->venue_address) . '</span>' : ''; ?></dd>
                    <dt><?php echo esc_html(sc_t('dashboard_pages.attendance_tracking', 'Time inside')); ?></dt>
                    <dd><?php echo esc_html((int) $event->attendance_tracking ? sc_t('dashboard_pages.tracked', 'Tracked') : sc_t('dashboard_pages.not_tracked', 'Not tracked')); ?></dd>
                    <dt><?php echo esc_html(sc_t('dashboard_pages.section_certificates', 'Certificates')); ?></dt>
                    <dd><?php echo esc_html((int) $event->enable_certificates
                        ? sprintf(sc_t('dashboard_pages.certificates_on_n', 'On · %s issued'), number_format_i18n($certificates)) . ((int) $event->auto_issue_certificate ? ' · ' . sc_t('dashboard_pages.issue_auto', 'automatic') : '')
                        : sc_t('dashboard_pages.off', 'Off')); ?></dd>
                    <dt><?php echo esc_html(sc_t('dashboard_pages.created', 'Created')); ?></dt>
                    <dd class="w-ltr"><?php echo esc_html(mysql2date('j M Y', $event->created_at)); ?></dd>
                </dl>
            </section>

            <section class="w-panel" aria-labelledby="people-title">
                <div class="w-panel__head"><h2 id="people-title"><?php echo esc_html(sc_t('dashboard_pages.on_the_page', 'On the event page')); ?></h2></div>
                <div class="w-counts">
                    <a href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id . '#people'); ?>"><strong class="w-ltr"><?php echo esc_html(number_format_i18n($links_count['speakers'])); ?></strong><span><?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></span></a>
                    <a href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id . '#sponsors'); ?>"><strong class="w-ltr"><?php echo esc_html(number_format_i18n($links_count['sponsors'])); ?></strong><span><?php echo esc_html(sc_t('nav.sponsors', 'Sponsors')); ?></span></a>
                    <a href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . $event_id . '#sponsors'); ?>"><strong class="w-ltr"><?php echo esc_html(number_format_i18n($links_count['partners'])); ?></strong><span><?php echo esc_html(sc_t('nav.partners', 'Partners')); ?></span></a>
                </div>
            </section>
        </div>
    </div>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
