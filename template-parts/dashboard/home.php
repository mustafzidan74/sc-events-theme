<?php
/**
 * Dashboard home — the next event, today's activity, things that need attention,
 * and the latest registrations (overview pattern).
 *
 * All figures are live counts. "Needs attention" only lists problems the data
 * shows right now: failed emails, scanners limited to finished events, and
 * upcoming events or workshops that nobody can register for.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_overview;
$load_wd_overview = true;
$p = $wpdb->prefix;
$dashboard_url = home_url('/event-manager-dashboard/');
$live = "status = 'active' AND payment_status = 'success'";
$today = current_time('Y-m-d');
$now_ts = current_time('timestamp');

// Auto-complete past events (throttled to once per 5 minutes)
if (!get_transient('sc_auto_complete_check')) {
    $rows_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}sc_events SET status = 'completed'
         WHERE status = 'publish'
         AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
        current_time('Y-m-d'), current_time('Y-m-d')
    ));
    set_transient('sc_auto_complete_check', 1, 300);
    if ($rows_updated > 0) {
        delete_transient('sc_dashboard_home_stats_v2');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sc_analytics_%' OR option_name LIKE '_transient_timeout_sc_analytics_%'");
    }
}

$module = function ($key) {
    return !function_exists('sc_module_active') || sc_module_active($key);
};
$table_exists = function ($table) use ($wpdb) {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
};

/* ------------------------------------------------------------ next event */

$next = null;
$featured_id = (int) get_option('sc_featured_event_id', 0);
if ($featured_id) {
    $next = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}sc_events WHERE id = %d AND status = 'publish' AND COALESCE(end_date, start_date) >= %s",
        $featured_id,
        $today
    ));
}
if (!$next) {
    $next = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}sc_events WHERE status = 'publish' AND COALESCE(end_date, start_date) >= %s ORDER BY start_date ASC LIMIT 1",
        $today
    ));
}
$next_stats = null;
if ($next) {
    $next_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COALESCE(SUM(workshop_id IS NULL), 0) AS registered,
                COALESCE(SUM(workshop_id IS NULL AND checked_in = 1), 0) AS checked_in,
                COALESCE(SUM(workshop_id IS NOT NULL), 0) AS workshop_regs,
                COALESCE(SUM(DATE(created_at) = %s), 0) AS today,
                COALESCE(SUM(created_at >= %s), 0) AS week
         FROM {$p}sc_attendees WHERE event_id = %d AND {$live}",
        $today,
        date('Y-m-d 00:00:00', strtotime($today . ' -6 days')),
        (int) $next->id
    ));
    $next_days = (int) floor((strtotime($next->start_date) - strtotime($today)) / DAY_IN_SECONDS);
    $next_workshops = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_workshops WHERE event_id = %d AND status = 'publish'", (int) $next->id));
}

/* ------------------------------------------------------------ activity */

$week_start = date('Y-m-d 00:00:00', strtotime($today . ' -6 days'));
$prev_week_start = date('Y-m-d 00:00:00', strtotime($today . ' -13 days'));
$activity = $wpdb->get_row($wpdb->prepare(
    "SELECT COALESCE(SUM(DATE(created_at) = %s), 0) AS today,
            COALESCE(SUM(created_at >= %s), 0) AS week,
            COALESCE(SUM(created_at >= %s AND created_at < %s), 0) AS prev_week,
            COALESCE(SUM(DATE(checked_in_at) = %s), 0) AS checkins_today
     FROM {$p}sc_attendees WHERE {$live} AND (created_at >= %s OR checked_in_at >= %s)",
    $today,
    $week_start,
    $prev_week_start,
    $week_start,
    $today,
    $prev_week_start,
    $today . ' 00:00:00'
));
$certs_week = $module('certificates') && $table_exists("{$p}sc_certificates")
    ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_certificates WHERE status <> 'revoked' AND created_at >= %s", $week_start))
    : null;

// Registrations per day, last 30 days, all events.
$from = date('Y-m-d', strtotime($today . ' -29 days'));
$per_day = array();
foreach ($wpdb->get_results($wpdb->prepare(
    "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM {$p}sc_attendees WHERE {$live} AND created_at >= %s GROUP BY d",
    $from . ' 00:00:00'
)) as $r) {
    $per_day[$r->d] = (int) $r->n;
}
$trend = array();
for ($d = $from; $d <= $today; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
    $trend[] = array('label' => $d, 'n' => $per_day[$d] ?? 0);
}
$trend_total = array_sum(array_column($trend, 'n'));
$trend_max = max(1, max(array_column($trend, 'n')));

/* ------------------------------------------------------------ attention */

$attention = array();

// Emails the mail plugin could not send (WP Mail SMTP logs each failure).
$mail_log = "{$p}wpmailsmtp_debug_events";
if ($table_exists($mail_log)) {
    $mail = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS n, MAX(created_at) AS last_at FROM {$mail_log} WHERE event_type = 0 AND created_at >= %s", date('Y-m-d H:i:s', current_time('timestamp') - 7 * DAY_IN_SECONDS)));
    if ($mail && (int) $mail->n > 0) {
        $attention[] = array(
            'tone'  => 'red',
            'icon'  => 'fa-envelope',
            'title' => sprintf(sc_t('dashboard_pages.attn_mail', '%s emails failed to send in the last 7 days'), number_format_i18n((int) $mail->n)),
            'text'  => sprintf(sc_t('dashboard_pages.attn_mail_text', 'Latest failure %s ago. Registration confirmations and tickets may not be arriving.'), human_time_diff(strtotime($mail->last_at), current_time('timestamp'))),
            'href'  => admin_url('admin.php?page=wp-mail-smtp-tools&tab=debug-events'),
            'link'  => sc_t('dashboard_pages.attn_mail_link', 'Open the mail log'),
        );
    }
}

// Scanner accounts whose permissions only cover events that are over.
if ($table_exists("{$p}sc_scanner_permissions")) {
    $scopes = $wpdb->get_results(
        "SELECT sp.user_id, sp.access_type,
                COALESCE(e1.id, e2.id, e3.id) AS event_id,
                COALESCE(e1.end_date, e1.start_date, e2.end_date, e2.start_date, e3.end_date, e3.start_date) AS last_day
         FROM {$p}sc_scanner_permissions sp
         LEFT JOIN {$p}sc_events e1 ON e1.id = sp.event_id
         LEFT JOIN {$p}sc_sessions s ON s.id = sp.session_id
         LEFT JOIN {$p}sc_events e2 ON e2.id = s.event_id
         LEFT JOIN {$p}sc_workshops w ON w.id = sp.workshop_id
         LEFT JOIN {$p}sc_events e3 ON e3.id = w.event_id"
    );
    $usable = array();
    foreach ($scopes as $s) {
        $uid = (int) $s->user_id;
        $usable[$uid] = ($usable[$uid] ?? false) || $s->access_type === 'full' || ($s->last_day && $s->last_day >= $today);
    }
    $stuck = count(array_filter($usable, function ($ok) { return !$ok; }));
    if ($stuck > 0) {
        $attention[] = array(
            'tone'  => 'gold',
            'icon'  => 'fa-qrcode',
            'title' => sprintf(sc_t('dashboard_pages.attn_scanners', '%s scanner accounts can only scan events that have ended'), number_format_i18n($stuck)),
            'text'  => $next ? sprintf(sc_t('dashboard_pages.attn_scanners_text', 'Give them access to %s before the doors open.'), $next->title) : '',
            'href'  => $dashboard_url . 'scanners',
            'link'  => sc_t('dashboard_pages.attn_scanners_link', 'Scanner team'),
        );
    }
}

// Upcoming published events without a ticket on sale.
foreach ($wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title FROM {$p}sc_events e
     WHERE e.status = 'publish' AND COALESCE(e.end_date, e.start_date) >= %s
       AND NOT EXISTS (SELECT 1 FROM {$p}sc_tickets t WHERE t.event_id = e.id AND (t.workshop_id IS NULL OR t.workshop_id = 0) AND t.is_active = 1)",
    $today
)) as $e) {
    $attention[] = array(
        'tone'  => 'red',
        'icon'  => 'fa-ticket',
        'title' => sprintf(sc_t('dashboard_pages.attn_event_tickets', '%s has no ticket on sale'), $e->title),
        'text'  => sc_t('dashboard_pages.attn_event_tickets_text', 'Nobody can register until one is added.'),
        'href'  => $dashboard_url . 'event-edit?id=' . (int) $e->id . '#tickets',
        'link'  => sc_t('dashboard_pages.add_ticket', 'Add ticket'),
    );
}

// Upcoming published workshops with no ticket at all.
$no_ticket_workshops = $wpdb->get_results($wpdb->prepare(
    "SELECT w.id, w.title FROM {$p}sc_workshops w
     WHERE w.status = 'publish' AND COALESCE(w.end_date, w.start_date) >= %s
       AND NOT EXISTS (SELECT 1 FROM {$p}sc_tickets t WHERE t.workshop_id = w.id)
     ORDER BY w.start_date",
    $today
));
if ($no_ticket_workshops) {
    $attention[] = array(
        'tone'  => 'gold',
        'icon'  => 'fa-flask',
        'title' => sprintf(_n('%s published workshop has no ticket', '%s published workshops have no ticket', count($no_ticket_workshops), 'sc_events'), number_format_i18n(count($no_ticket_workshops))),
        'text'  => implode(', ', array_slice(wp_list_pluck($no_ticket_workshops, 'title'), 0, 4)) . (count($no_ticket_workshops) > 4 ? '…' : ''),
        'href'  => count($no_ticket_workshops) === 1 ? $dashboard_url . 'workshop-edit?id=' . (int) $no_ticket_workshops[0]->id . '#tickets' : $dashboard_url . 'workshops',
        'link'  => sc_t('dashboard_pages.attn_workshops_link', 'Open workshops'),
    );
}

/* ------------------------------------------------------------ lists */

$upcoming = $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, e.start_date, e.end_date, e.status,
            (SELECT COUNT(*) FROM {$p}sc_attendees a WHERE a.event_id = e.id AND a.workshop_id IS NULL AND a.{$live}) AS registered
     FROM {$p}sc_events e
     WHERE e.status IN ('publish', 'draft') AND COALESCE(e.end_date, e.start_date) >= %s
     ORDER BY e.start_date ASC LIMIT 6",
    $today
));

$recent = $wpdb->get_results(
    "SELECT a.id, a.name, a.created_at, a.checked_in, e.title AS event_title, w.title AS workshop
     FROM {$p}sc_attendees a
     LEFT JOIN {$p}sc_events e ON e.id = a.event_id
     LEFT JOIN {$p}sc_workshops w ON w.id = a.workshop_id
     WHERE a.status = 'active' AND a.payment_status = 'success'
     ORDER BY a.created_at DESC, a.id DESC LIMIT 8"
);

$user = wp_get_current_user();
$hour = (int) current_time('G');
$greeting = $hour < 12 ? sc_t('dashboard_pages.good_morning', 'Good morning') : ($hour < 18 ? sc_t('dashboard_pages.good_afternoon', 'Good afternoon') : sc_t('dashboard_pages.good_evening', 'Good evening'));
$ago = function ($datetime) use ($now_ts) {
    return sprintf(sc_t('dashboard_pages.time_ago', '%s ago'), human_time_diff(strtotime($datetime), $now_ts));
};
$delta = (int) $activity->week - (int) $activity->prev_week;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($greeting . ', ' . ($user->first_name ?: $user->display_name)); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(date_i18n('l j F Y', $now_ts)); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'scanner'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.scanner', 'Scanner')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'event-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.create_event', 'Create event')); ?></a>
        </div>
    </div>

    <?php if ($next): ?>
    <section class="w-hero" aria-labelledby="next-title">
        <div class="w-hero__main">
            <span class="w-hero__eyebrow">
                <?php
                if ($next_days > 1) {
                    echo esc_html(sprintf(sc_t('dashboard_pages.next_event_in', 'Next event · in %d days'), $next_days));
                } elseif ($next_days === 1) {
                    echo esc_html(sc_t('dashboard_pages.next_event_tomorrow', 'Next event · tomorrow'));
                } else {
                    echo esc_html(sc_t('dashboard_pages.happening_now', 'Happening now'));
                }
                ?>
            </span>
            <h2 id="next-title"><a href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $next->id); ?>"><?php echo esc_html($next->title); ?></a></h2>
            <p class="w-hero__meta w-ltr"><?php
                $range = mysql2date('D j M', $next->start_date);
                if ($next->end_date && $next->end_date !== $next->start_date) {
                    $range .= ' – ' . mysql2date('D j M Y', $next->end_date);
                } else {
                    $range .= ' ' . mysql2date('Y', $next->start_date);
                }
                echo esc_html($range . ($next->venue_name ? ' · ' . $next->venue_name : ''));
            ?></p>
            <div class="w-hero__actions">
                <a class="btn btn-primary btn-sm" href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $next->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.event_overview', 'Overview')); ?></a>
                <a class="btn btn-secondary btn-sm" href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . (int) $next->id); ?>"><?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?></a>
                <a class="btn btn-secondary btn-sm" href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . (int) $next->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
            </div>
        </div>
        <div class="w-hero__figures">
            <span class="w-hero__fig"><strong class="w-ltr"><?php echo esc_html(number_format_i18n((int) $next_stats->registered)); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span></span>
            <span class="w-hero__fig"><strong class="w-ltr"><?php echo esc_html(number_format_i18n((int) $next_stats->week)); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.last_7_days', 'Last 7 days')); ?></span></span>
            <span class="w-hero__fig"><strong class="w-ltr"><?php echo esc_html(number_format_i18n((int) $next_stats->workshop_regs)); ?></strong><span><?php echo esc_html(sprintf(sc_t('dashboard_pages.in_n_workshops', 'In %d workshops'), $next_workshops)); ?></span></span>
            <?php if ($next_days <= 0): ?>
            <span class="w-hero__fig"><strong class="w-ltr"><?php echo esc_html(number_format_i18n((int) $next_stats->checked_in)); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span></span>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="w-kpis">
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.registrations_today', 'Registrations today')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n((int) $activity->today)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All events')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.last_7_days', 'Last 7 days')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n((int) $activity->week)); ?></span>
            <span class="w-kpi__sub"><?php
                if ($delta === 0) {
                    echo esc_html(sc_t('dashboard_pages.same_as_week_before', 'Same as the week before'));
                } else {
                    echo '<strong class="w-ltr">' . esc_html(($delta > 0 ? '+' : '−') . number_format_i18n(abs($delta))) . '</strong> ' . esc_html(sc_t('dashboard_pages.vs_week_before', 'vs the week before'));
                }
            ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.checkins_today', 'Check-ins today')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n((int) $activity->checkins_today)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html(sc_t('dashboard_pages.first_scan_only', 'First scan per person')); ?></span>
        </div>
        <?php if ($certs_week !== null): ?>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.certificates_issued', 'Certificates issued')); ?></span>
            <span class="w-kpi__value w-ltr"><?php echo esc_html(number_format_i18n($certs_week)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html(sc_t('dashboard_pages.last_7_days', 'Last 7 days')); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="w-ov-grid">
        <div class="w-ov-col">
            <?php if ($attention): ?>
            <section class="w-panel" aria-labelledby="attn-title">
                <div class="w-panel__head">
                    <h2 id="attn-title"><?php echo esc_html(sc_t('dashboard_pages.needs_attention', 'Needs attention')); ?></h2>
                    <span class="w-panel__hint w-ltr"><?php echo esc_html(number_format_i18n(count($attention))); ?></span>
                </div>
                <ul class="w-attn">
                    <?php foreach ($attention as $item): ?>
                    <li class="w-attn__item w-attn__item--<?php echo esc_attr($item['tone']); ?>">
                        <span class="w-attn__icon" aria-hidden="true"><i class="fa <?php echo esc_attr($item['icon']); ?>"></i></span>
                        <span class="w-attn__body">
                            <strong><?php echo esc_html($item['title']); ?></strong>
                            <?php if ($item['text']): ?><span><?php echo esc_html($item['text']); ?></span><?php endif; ?>
                        </span>
                        <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['link']); ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <section class="w-panel" aria-labelledby="trend-title">
                <div class="w-panel__head">
                    <h2 id="trend-title"><?php echo esc_html(sc_t('dashboard_pages.registrations', 'Registrations')); ?></h2>
                    <span class="w-panel__hint"><?php echo esc_html(sprintf(sc_t('dashboard_pages.last_30_days_n', 'Last 30 days · %s'), number_format_i18n($trend_total))); ?></span>
                </div>
                <div class="w-bars" role="img" aria-label="<?php echo esc_attr(sprintf(sc_t('dashboard_pages.trend_aria', '%1$s registrations between %2$s and %3$s'), number_format_i18n($trend_total), $trend[0]['label'], end($trend)['label'])); ?>">
                    <?php foreach ($trend as $bar): ?>
                        <span class="w-bars__col<?php echo $bar['n'] ? '' : ' is-zero'; ?>">
                            <span class="w-bars__bar" style="height:<?php echo esc_attr(max(1.5, $bar['n'] / $trend_max * 100)); ?>%"></span>
                            <span class="w-bars__tip"><?php echo esc_html(mysql2date('D j M', $bar['label']) . ': ' . number_format_i18n($bar['n'])); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="w-bars__axis w-ltr">
                    <span><?php echo esc_html(mysql2date('j M', $trend[0]['label'])); ?></span>
                    <span><?php echo esc_html(sc_t('dashboard_pages.today', 'Today')); ?></span>
                </div>
            </section>

            <section class="w-panel" aria-labelledby="recent-title">
                <div class="w-panel__head">
                    <h2 id="recent-title"><?php echo esc_html(sc_t('dashboard_pages.recent_registrations', 'Latest registrations')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'attendees'); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_all', 'View all')); ?> →</a>
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
                            <span class="w-sub"><?php echo esc_html($a->workshop ? $a->workshop : ($a->event_title ?: '—')); ?></span>
                        </span>
                        <span class="w-rows__meta"><?php echo esc_html($ago($a->created_at)); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_registrations', 'No registrations yet')); ?></p>
                <?php endif; ?>
            </section>
        </div>

        <div class="w-ov-col">
            <section class="w-panel" aria-labelledby="upcoming-title">
                <div class="w-panel__head">
                    <h2 id="upcoming-title"><?php echo esc_html(sc_t('dashboard_pages.upcoming_events', 'Upcoming events')); ?></h2>
                    <a class="w-panel__link" href="<?php echo esc_url($dashboard_url . 'events?view=upcoming'); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_all', 'View all')); ?> →</a>
                </div>
                <?php if ($upcoming): ?>
                <ul class="w-rows">
                    <?php foreach ($upcoming as $e): ?>
                    <li class="w-rows__item">
                        <span class="w-date-badge w-ltr" aria-hidden="true"><strong><?php echo esc_html(mysql2date('j', $e->start_date)); ?></strong><span><?php echo esc_html(mysql2date('M', $e->start_date)); ?></span></span>
                        <span class="w-rows__main">
                            <a href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $e->id); ?>"><strong><?php echo esc_html($e->title); ?></strong></a>
                            <span class="w-sub"><?php echo esc_html(sprintf(sc_t('dashboard_pages.n_registered', '%s registered'), number_format_i18n((int) $e->registered))); ?><?php echo $e->status === 'draft' ? ' · ' . esc_html(sc_t('dashboard_pages.status_draft', 'Draft')) : ''; ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="w-panel__empty"><?php echo esc_html(sc_t('dashboard_pages.no_upcoming_events', 'No upcoming events.')); ?></p>
                <?php endif; ?>
            </section>

            <section class="w-panel" aria-labelledby="actions-title">
                <div class="w-panel__head"><h2 id="actions-title"><?php echo esc_html(sc_t('dashboard_pages.quick_actions', 'Quick actions')); ?></h2></div>
                <div class="w-links">
                    <a href="<?php echo esc_url($dashboard_url . 'attendee-add'); ?>"><i class="fa fa-user-plus" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.add_attendee', 'Add attendee')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'attendees'); ?>"><i class="fa fa-users" aria-hidden="true"></i><?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?></a>
                    <?php if ($module('certificates')): ?>
                    <a href="<?php echo esc_url($dashboard_url . 'certificate-issue'); ?>"><i class="fa fa-certificate" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.issue_certificates', 'Issue certificates')); ?></a>
                    <?php endif; ?>
                    <?php if ($module('coupons')): ?>
                    <a href="<?php echo esc_url($dashboard_url . 'coupon-create'); ?>"><i class="fa fa-tag" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.create_coupon', 'Create coupon')); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($dashboard_url . 'workshops'); ?>"><i class="fa fa-flask" aria-hidden="true"></i><?php echo esc_html(sc_t('dashboard_pages.workshops', 'Workshops')); ?></a>
                    <a href="<?php echo esc_url($dashboard_url . 'reports'); ?>"><i class="fa fa-bar-chart" aria-hidden="true"></i><?php echo esc_html(sc_t('nav.reports', 'Reports')); ?></a>
                </div>
            </section>
        </div>
    </div>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
