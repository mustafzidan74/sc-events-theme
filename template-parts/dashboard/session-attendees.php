<?php
/**
 * Session attendees — who registered for or attended one session, with
 * check-in by ticket code, check-in / check-out per person and CME earned.
 *
 * Lists sc_get_session_registrations; check-in and check-out use
 * sc_session_checkin / sc_session_checkout (the same calls the scanner makes).
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
$p = $wpdb->prefix;
$session_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$session = $session_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT s.*, e.title AS event_title FROM {$p}sc_sessions s LEFT JOIN {$p}sc_events e ON e.id = s.event_id WHERE s.id = %d",
    $session_id
)) : null;
if (!$session) {
    wp_safe_redirect(home_url('/event-manager-dashboard/sessions'));
    exit;
}
$load_wd_list = true;
$load_wd_overview = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$time = function ($dt) {
    return $dt ? substr((string) $dt, 11, 5) : '';
};
$when = array_filter(array(
    $session->session_date ? date_i18n('D j M Y', strtotime($session->session_date)) : '',
    trim($time($session->start_time) . ($session->end_time ? '–' . $time($session->end_time) : ''), '–'),
    $session->hall_name,
));
$cme = (float) $session->cme_hours ?: (float) $session->cme_credits;
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
            <p class="w-page-head__eyebrow"><a href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $session->event_id); ?>"><?php echo esc_html($session->event_title); ?></a></p>
            <h1><?php echo esc_html($session->title); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(implode(' · ', $when)); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'session-edit?id=' . $session_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit_session', 'Edit session')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'scanner?event_id=' . (int) $session->event_id . '&session_id=' . $session_id); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.scan_this_session', 'Scan this session')); ?></a>
        </div>
    </div>

    <div class="w-kpis">
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.people', 'People')); ?></span>
            <span class="w-kpi__value" data-kpi="all">—</span>
            <span class="w-kpi__sub"><?php echo esc_html($session->capacity ? sprintf(sc_t('dashboard_pages.capacity_n', 'Capacity %s'), number_format_i18n((int) $session->capacity)) : sc_t('dashboard_pages.no_capacity', 'No capacity set')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span>
            <span class="w-kpi__value" data-kpi="in">—</span>
            <span class="w-kpi__sub"><?php echo esc_html($session->require_registration ? sc_t('dashboard_pages.registration_required', 'Registration required') : sc_t('dashboard_pages.walk_ins_welcome', 'Any ticket holder can walk in')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.checked_out', 'Checked out')); ?></span>
            <span class="w-kpi__value" data-kpi="out">—</span>
            <span class="w-kpi__sub"><?php echo esc_html($session->require_checkout ? sc_t('dashboard_pages.checkout_required', 'Check-out required') : sc_t('dashboard_pages.checkout_optional', 'Check-out optional')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.cme_earned', 'CME hours earned')); ?></span>
            <span class="w-kpi__value" data-kpi="cme">—</span>
            <span class="w-kpi__sub"><?php echo esc_html($cme ? sprintf(sc_t('dashboard_pages.cme_per_person', '%s per person'), $cme) : sc_t('dashboard_pages.no_cme', 'No CME for this session')); ?></span>
        </div>
    </div>

    <form class="w-inline-scan" id="ticket-form" novalidate>
        <label for="ticket-code" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.check_in_by_ticket', 'Check someone in by ticket code')); ?></label>
        <div class="w-inline-scan__row">
            <input type="text" class="form-control w-mono w-ltr" id="ticket-code" autocomplete="off" spellcheck="false" placeholder="SC1234ABCD">
            <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('dashboard_pages.check_in', 'Check in')); ?></button>
        </div>
    </form>

    <div id="session-people">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('dashboard_pages.attendees', 'Attendees')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_attendees', 'Search name, email, phone or ticket code')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_attendees', 'Search name, email, phone or ticket code')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
        </div>
        <div class="w-chips" data-w-chips hidden></div>
        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table><thead></thead><tbody></tbody></table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var sessionId = <?php echo (int) $session_id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'all'        => sc_t('dashboard_pages.all', 'All'),
        'in'         => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'not_yet'    => sc_t('dashboard_pages.not_checked_in', 'Not checked in'),
        'out'        => sc_t('dashboard_pages.checked_out', 'Checked out'),
        'attendee'   => sc_t('dashboard_pages.attendee', 'Attendee'),
        'ticket'     => sc_t('dashboard_pages.ticket', 'Ticket'),
        'attendance' => sc_t('dashboard_pages.attendance', 'Attendance'),
        'cme'        => sc_t('dashboard_pages.cme', 'CME'),
        'registered' => sc_t('dashboard_pages.registered', 'Registered'),
        'walkIn'     => sc_t('dashboard_pages.walk_in', 'Walked in'),
        'notYet'     => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'minutes'    => sc_t('dashboard_pages.n_minutes', '%s min'),
        'doIn'       => sc_t('dashboard_pages.check_in', 'Check in'),
        'doOut'      => sc_t('dashboard_pages.check_out', 'Check out'),
        'edit'       => sc_t('dashboard_pages.open_registration', 'Open registration'),
        'already'    => sc_t('dashboard_pages.already_checked_in', '%s was already checked in.'),
        'done'       => sc_t('dashboard_pages.checked_in_name', '%s checked in.'),
        'enterCode'  => sc_t('dashboard_pages.enter_ticket_code', 'Enter a ticket code.'),
        'search'     => sc_t('general.search', 'Search'),
        'emptyText'  => sc_t('dashboard_pages.no_session_people', 'Nobody has registered or checked in yet. Scan tickets at the door, or check people in by ticket code above.'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function post(data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce, session_id: sessionId }, data) }); }
    function time(dt) { return dt ? String(dt).slice(11, 16) : ''; }
    function checkIn(data, name) {
        return post($.extend({ action: 'sc_session_checkin', scan_method: 'manual' }, data)).done(function (res) {
            if (res.success) {
                var who = res.data.attendee_name || name || "";
                showSuccess((res.data.already_checked_in ? L.already : L.done).replace('%s', who));
                list.reload(true);
            } else {
                showError(res.data && res.data.message ? res.data.message : L.failed);
            }
        }).fail(function () { showError(L.failed); });
    }

    $('#ticket-form').on('submit', function (e) {
        e.preventDefault();
        var code = $.trim($('#ticket-code').val());
        if (!code) { showError(L.enterCode); return; }
        checkIn({ ticket_id: code }).done(function (res) { if (res.success) { $('#ticket-code').val('').trigger('focus'); } });
    });

    var list = WDList.create({
        root: document.getElementById('session-people'),
        action: 'sc_get_session_registrations',
        rowsKey: 'rows',
        filters: ['search'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        extraParams: function () { return { session_id: sessionId }; },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'in', label: L.in, params: { view: 'in' }, countKey: 'in' },
            { key: 'not_yet', label: L.not_yet, params: { view: 'not_yet' }, countKey: 'not_yet' },
            { key: 'out', label: L.out, params: { view: 'out' }, countKey: 'out' }
        ],
        emptyText: L.emptyText,
        onData: function (data) {
            if (!data.counts) { return; }
            ['all', 'in', 'out'].forEach(function (k) { $('[data-kpi="' + k + '"]').text(WDList.num(data.counts[k])); });
            $('[data-kpi="cme"]').text(data.counts.cme);
        },
        columns: [
            {
                label: L.attendee,
                render: function (a) {
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'attendee-edit?id=' + a.id) + '">' + esc(a.name) + '</a><span class="w-sub w-ltr w-truncate">' + esc([a.email, a.phone].filter(Boolean).join(' · ')) + '</span></div>';
                }
            },
            {
                label: L.ticket, className: 'w-col-xl',
                render: function (a) { return '<div class="w-stack"><span class="w-mono w-nowrap">' + esc(a.ticket_code) + '</span><span class="w-sub">' + esc(a.registered ? L.registered : L.walkIn) + '</span></div>'; }
            },
            {
                label: L.attendance,
                render: function (a) {
                    if (!a.in_at) { return '<span class="text-muted">' + esc(L.notYet) + '</span>'; }
                    var line = time(a.in_at) + (a.out_at ? ' – ' + time(a.out_at) : '');
                    var sub = a.minutes !== null && a.out_at ? L.minutes.replace('%s', a.minutes) + (a.percent !== null ? ' · ' + a.percent + '%' : '') : '';
                    return '<div class="w-stack"><span class="w-tag ' + (a.out_at ? '' : 'w-tag--teal') + ' w-ltr">' + esc(line) + '</span>' + (sub ? '<span class="w-sub">' + esc(sub) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.cme,
                render: function (a) { return a.cme ? '<span class="w-num">' + esc(a.cme) + '</span>' : '<span class="text-muted">—</span>'; }
            }
        ],
        rowMenu: function (a) {
            return [
                !a.in_at
                    ? { label: L.doIn, onSelect: function () { checkIn({ attendee_id: a.id }, a.name); } }
                    : { label: L.doOut, disabled: !!a.out_at, onSelect: function () {
                        post({ action: 'sc_session_checkout', attendee_id: a.id }).done(function (res) {
                            if (res.success) { showSuccess(res.data.message || L.out); list.reload(true); }
                            else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                        }).fail(function () { showError(L.failed); });
                    } },
                { label: L.edit, href: dashboardUrl + 'attendee-edit?id=' + a.id }
            ];
        },
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
