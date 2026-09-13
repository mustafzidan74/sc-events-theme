<?php
/**
 * Scanner team — list pattern over sc_get_scanners_paginated.
 *
 * Shows, for every scanner account, which events it may scan (and whether
 * those events are over) and how many check-ins it has made. Several accounts
 * can be given access to an event in one step.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list;
$load_wd_list = true;

$today = current_time('Y-m-d');
// Events a scanner can be pointed at: the ones still to come first, then recent past ones.
$access_events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, COALESCE(end_date, start_date) >= %s AS current
     FROM {$wpdb->prefix}sc_events
     WHERE status IN ('publish', 'completed', 'draft')
     ORDER BY current DESC, start_date DESC
     LIMIT 50",
    $today
));

$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$t = array(
    'title'    => sc_t('dashboard_pages.scanner_team', 'Scanner team'),
    'subtitle' => sc_t('dashboard_pages.scanner_team_subtitle', 'Accounts that can only open the scanner. Each one scans the events you give it.'),
    'add'      => sc_t('dashboard_pages.add_scanner', 'Add scanner'),
    'search'   => sc_t('dashboard_pages.search_scanners', 'Search name, email or phone'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($t['title']); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'scanner'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.scanner', 'Scanner')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'scanner-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="scanners-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>

        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
        </div>

        <div class="w-chips" data-w-chips hidden></div>
        <div class="w-bulkbar" data-w-bulk hidden></div>

        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table>
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>

</div>
</div>

<!-- Give access to events -->
<div class="modal fade" id="accessModal" tabindex="-1" role="dialog" aria-labelledby="access-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="access-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="access-title"><?php echo esc_html(sc_t('dashboard_pages.give_access', 'Give access to events')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="w-field__help" id="access-count"></p>
                    <fieldset class="w-field">
                        <legend class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.events', 'Events')); ?></legend>
                        <div class="w-checklist">
                            <?php foreach ($access_events as $ev): ?>
                            <label class="w-checklist__item">
                                <input type="checkbox" name="event_ids[]" value="<?php echo (int) $ev->id; ?>">
                                <span><strong><?php echo esc_html($ev->title); ?></strong><span class="w-sub w-ltr"><?php echo esc_html(mysql2date('j M Y', $ev->start_date)); ?><?php echo (int) $ev->current ? '' : ' · ' . esc_html(sc_t('dashboard_pages.ended', 'Ended')); ?></span></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="w-field mt-3">
                        <legend class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.existing_access', 'What they already have')); ?></legend>
                        <div class="w-choice w-choice--stack">
                            <label class="w-choice__item"><input type="radio" name="mode" value="replace" checked><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.access_replace', 'Replace it')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.access_replace_help', 'They scan only the events ticked above.')); ?></span></span></span></label>
                            <label class="w-choice__item"><input type="radio" name="mode" value="add"><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.access_add', 'Keep it and add these')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.access_add_help', 'Accounts with full access stay unchanged.')); ?></span></span></span></label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="access-save"><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'all'          => sc_t('dashboard_pages.all', 'All'),
        'ready'        => sc_t('dashboard_pages.scanners_ready', 'Can scan upcoming events'),
        'ended'        => sc_t('dashboard_pages.scanners_ended', 'Only ended events'),
        'full'         => sc_t('dashboard_pages.full_access', 'Full access'),
        'scanner'      => sc_t('dashboard_pages.scanner_account', 'Scanner'),
        'phone'        => sc_t('general.phone', 'Phone'),
        'access'       => sc_t('dashboard_pages.can_scan', 'Can scan'),
        'checkins'     => sc_t('dashboard_pages.checkins_made', 'Check-ins'),
        'added'        => sc_t('dashboard_pages.added', 'Added'),
        'everything'   => sc_t('dashboard_pages.every_event', 'Every event'),
        'ended_tag'    => sc_t('dashboard_pages.ended', 'Ended'),
        'sessions'     => sc_t('dashboard_pages.n_sessions', '%d sessions'),
        'last'         => sc_t('dashboard_pages.last_on', 'last %s'),
        'never'        => sc_t('dashboard_pages.none_yet', 'None yet'),
        'edit'         => sc_t('dashboard_pages.edit', 'Edit'),
        'openScanner'  => sc_t('dashboard_pages.give_access', 'Give access to events'),
        'remove'       => sc_t('dashboard_pages.remove_scanner', 'Remove scanner'),
        'bulkAccess'   => sc_t('dashboard_pages.give_access_short', 'Give access'),
        'bulkRemove'   => sc_t('dashboard_pages.remove', 'Remove'),
        'search'       => sc_t('general.search', 'Search'),
        'emptyText'    => sc_t('dashboard_pages.no_scanners', 'No scanner accounts yet.'),
        'forN'         => sc_t('dashboard_pages.for_n_scanners', 'For %d scanners.'),
        'chooseEvent'  => sc_t('dashboard_pages.choose_one_event', 'Choose at least one event.'),
        'confirmRemove'=> sc_t('dashboard_pages.confirm_remove_scanner', 'Remove scanner access for %s? The account stays, as a regular site user, and its past check-ins are kept.'),
        'confirmRemoveN'=> sc_t('dashboard_pages.confirm_remove_scanners', 'Remove scanner access for %d accounts? The accounts stay, as regular site users, and their past check-ins are kept.'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : '')).toUpperCase();
    }
    function dateOnly(v) {
        if (!v) { return ''; }
        var d = new Date(v.replace(' ', 'T'));
        return isNaN(d) ? v : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    function post(data) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) });
    }

    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        key: 'M15 7a4 4 0 1 1-3.9 5H9v2H7v2H4v-3l6.1-6.1A4 4 0 0 1 15 7zM16 9h.01',
        userx: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17 8l5 5M22 8l-5 5'
    };

    var list = WDList.create({
        root: document.getElementById('scanners-list'),
        action: 'sc_get_scanners_paginated',
        rowsKey: 'scanners',
        filters: ['search'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'registered', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'ended', label: L.ended, params: { view: 'ended' }, countKey: 'ended' },
            { key: 'ready', label: L.ready, params: { view: 'ready' }, countKey: 'ready' },
            { key: 'full', label: L.full, params: { view: 'full' }, countKey: 'full' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.scanner, sort: 'name',
                render: function (s) {
                    return '<div class="w-person"><span class="w-person__avatar" data-tone="' + (s.id % 4) + '" aria-hidden="true">' + esc(initials(s.name)) + '</span>' +
                        '<span class="w-person__text"><a class="w-row-title" href="' + esc(dashboardUrl + 'scanner-edit?id=' + s.id) + '">' + esc(s.name) + '</a>' +
                        '<span class="w-sub w-ltr">' + esc(s.email) + '</span></span></div>';
                }
            },
            {
                label: L.phone, className: 'w-col-xl',
                render: function (s) { return s.phone ? '<span class="w-ltr">' + esc(s.phone) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.access,
                render: function (s) {
                    if (s.full) { return '<span class="w-tag w-tag--teal">' + esc(L.everything) + '</span>'; }
                    return '<div class="w-stack">' + s.events.map(function (e) {
                        var extra = e.sessions.length ? ' · ' + L.sessions.replace('%d', e.sessions.length) : '';
                        return '<span class="w-access' + (e.ended ? ' is-ended' : '') + '">' + esc(e.title + extra) + (e.ended ? ' <span class="w-tag">' + esc(L.ended_tag) + '</span>' : '') + '</span>';
                    }).join('') + '</div>';
                }
            },
            {
                label: L.checkins, sort: 'checkins',
                render: function (s) {
                    return '<div class="w-stack"><span class="w-num">' + WDList.num(s.checkins) + '</span><span class="w-sub">' +
                        esc(s.last_checkin ? L.last.replace('%s', dateOnly(s.last_checkin)) : L.never) + '</span></div>';
                }
            },
            {
                label: L.added, sort: 'registered', className: 'w-col-xl',
                render: function (s) { return '<span class="w-ltr">' + esc(dateOnly(s.registered)) + '</span>'; }
            }
        ],
        rowMenu: function (s) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'scanner-edit?id=' + s.id },
                { label: L.openScanner, icon: ICON.key, onSelect: function () { openAccess([s.id]); } },
                { separator: true },
                { label: L.remove, icon: ICON.userx, danger: true, onSelect: function () { removeScanners([s.id], s.name); } }
            ];
        },
        bulkActions: [
            { key: 'access', label: L.bulkAccess, icon: ICON.key, run: function (ids) { openAccess(ids); } },
            { key: 'remove', label: L.bulkRemove, icon: ICON.userx, danger: true, run: function (ids) { removeScanners(ids); } }
        ],
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        }
    });

    /* ---------------------------------------------------------- access */

    var $modal = $('#accessModal');
    var form = document.getElementById('access-form');
    var targetIds = [];

    function openAccess(ids) {
        targetIds = ids.slice();
        form.reset();
        $(form).find('.w-field__error').remove();
        $(form).find('.has-error').removeClass('has-error');
        $('#access-count').text(L.forN.replace('%d', ids.length));
        $modal.modal('show');
    }

    $(form).on('submit', function (e) {
        e.preventDefault();
        var events = $(form).find('[name="event_ids[]"]:checked').map(function () { return this.value; }).get();
        $(form).find('.w-field__error').remove();
        if (!events.length) {
            $(form).find('.w-checklist').closest('.w-field').addClass('has-error').append($('<p class="w-field__error">').text(L.chooseEvent));
            return;
        }
        var $btn = $('#access-save').prop('disabled', true);
        post({ action: 'sc_bulk_scanner_access', user_ids: targetIds, event_ids: events, mode: $(form).find('[name="mode"]:checked').val() })
            .done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $modal.modal('hide');
                    showSuccess(res.data.message);
                    list.reload();
                } else {
                    showError(res.data && res.data.message ? res.data.message : L.failed);
                }
            })
            .fail(function () { $btn.prop('disabled', false); showError(L.failed); });
    });

    /* ---------------------------------------------------------- remove */

    function removeScanners(ids, name) {
        var ask = ids.length === 1 && name ? L.confirmRemove.replace('%s', name) : L.confirmRemoveN.replace('%d', ids.length);
        showDeleteConfirm(ask).then(function (r) {
            if (!r.isConfirmed) { return; }
            var jobs = ids.map(function (id) { return post({ action: 'sc_delete_scanner', user_id: id }); });
            $.when.apply($, jobs).always(function () {
                list.reload();
            });
        });
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
