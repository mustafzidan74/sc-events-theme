<?php
/**
 * Issue certificates — pick an event or workshop, see who qualifies under its
 * certificate rules, and issue to everyone eligible or to the people you select.
 *
 * Lists sc_certificate_candidates and posts to sc_issue_certificates
 * (inc/admin-dashboard/certificates-dashboard.php).
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
$p = $wpdb->prefix;

$events = $wpdb->get_results("SELECT id, title, start_date FROM {$p}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200");
$workshops = $events ? $wpdb->get_results("SELECT id, event_id, title FROM {$p}sc_workshops WHERE event_id IN (" . implode(',', array_map('intval', wp_list_pluck($events, 'id'))) . ') ORDER BY start_date, title') : array();
$by_event = array();
foreach ($workshops as $w) {
    $by_event[(int) $w->event_id][] = $w;
}
$templates = $wpdb->get_results("SELECT id, name, is_default FROM {$p}sc_certificate_templates WHERE is_active = 1 ORDER BY is_default DESC, name");
$default_template = $templates ? (int) $templates[0]->id : 0;

$scope = '';
if (!empty($_GET['workshop_id'])) {
    $scope = 'workshop:' . absint($_GET['workshop_id']);
} elseif (!empty($_GET['event_id'])) {
    $scope = 'event:' . absint($_GET['event_id']);
} elseif ($events) {
    $scope = 'event:' . (int) $events[0]->id;
}

$dashboard_url = home_url('/event-manager-dashboard/');
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
            <h1><?php echo esc_html(sc_t('dashboard_pages.issue_certificates', 'Issue certificates')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.issue_certificates_sub', 'Choose the event or workshop. Everyone who meets its certificate rules can get one in a single step; you can also pick people yourself.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'certificates'); ?>"><?php echo esc_html(sc_t('dashboard_pages.issued_certificates', 'Issued certificates')); ?></a>
        </div>
    </div>

    <?php if (!$events): ?>
        <div class="w-state"><p class="w-state__text"><?php echo esc_html(sc_t('dashboard_pages.no_events', 'No events yet.')); ?></p></div>
    <?php else: ?>
    <div id="issue-page">
        <section class="w-issue">
            <div class="w-issue__pick">
                <div class="w-field">
                    <label for="scope" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.certificates_for', 'Certificates for')); ?></label>
                    <select class="form-control" id="scope" data-w-filter="scope">
                        <?php foreach ($events as $ev): ?>
                            <optgroup label="<?php echo esc_attr($ev->title); ?>">
                                <option value="event:<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title . ' — ' . sc_t('dashboard_pages.the_event', 'the event')); ?></option>
                                <?php foreach ($by_event[(int) $ev->id] ?? array() as $w): ?>
                                    <option value="workshop:<?php echo (int) $w->id; ?>"><?php echo esc_html(sc_t('dashboard_pages.workshop', 'Workshop') . ': ' . $w->title); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-field">
                    <label for="template" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.template', 'Template')); ?></label>
                    <select class="form-control" id="template">
                        <?php if (!$templates): ?><option value=""><?php echo esc_html(sc_t('dashboard_pages.no_templates', 'No templates yet')); ?></option><?php endif; ?>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo (int) $tpl->id; ?>"><?php echo esc_html($tpl->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="w-field__help" id="template-note"></p>
                </div>
            </div>

            <ul class="w-issue__rules" id="rules" aria-live="polite"></ul>

            <div class="w-issue__go">
                <div>
                    <p class="w-issue__big" id="eligible-line">&nbsp;</p>
                    <label class="w-check-line"><input type="checkbox" id="send-email"> <?php echo esc_html(sc_t('dashboard_pages.send_each_person', 'Send each person their certificate (WhatsApp with the PDF, one by one; email when it is on)')); ?></label>
                </div>
                <button type="button" class="btn btn-primary btn-lg" id="issue-all" disabled><?php echo esc_html(sc_t('dashboard_pages.issue_to_all_eligible', 'Issue to everyone eligible')); ?></button>
            </div>
            <div class="w-issue__progress" id="progress" hidden>
                <span class="w-bar" aria-hidden="true"><span class="w-bar__fill" id="progress-fill" style="width:0"></span></span>
                <span id="progress-text" role="status"></span>
            </div>
        </section>

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
        <div class="w-bulkbar" data-w-bulk hidden></div>
        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table><thead></thead><tbody></tbody></table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php if ($events): ?>
<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var defaultTemplate = <?php echo (int) $default_template; ?>;
    var L = <?php echo $js(array(
        'eligible'     => sc_t('dashboard_pages.eligible', 'Eligible'),
        'waiting'      => sc_t('dashboard_pages.dont_qualify_yet', 'Don’t qualify'),
        'has'          => sc_t('dashboard_pages.have_certificate', 'Have a certificate'),
        'all'          => sc_t('dashboard_pages.all', 'All'),
        'attendee'     => sc_t('dashboard_pages.attendee', 'Attendee'),
        'ticket'       => sc_t('dashboard_pages.ticket', 'Ticket'),
        'checkin'      => sc_t('dashboard_pages.check_in', 'Check-in'),
        'certificate'  => sc_t('dashboard_pages.certificate', 'Certificate'),
        'checkedIn'    => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'notYet'       => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'ready'        => sc_t('dashboard_pages.ready_to_issue', 'Ready to issue'),
        'why'          => array(
            'cancelled'       => sc_t('dashboard_pages.why_cancelled', 'Registration cancelled'),
            'unpaid'          => sc_t('dashboard_pages.why_unpaid', 'Payment not confirmed'),
            'not_checked_in'  => sc_t('dashboard_pages.why_not_checked_in', 'Not checked in'),
            'not_checked_out' => sc_t('dashboard_pages.why_not_checked_out', 'Not checked out'),
            'not_ended'       => sc_t('dashboard_pages.why_not_ended', 'Event hasn’t ended'),
        ),
        'status'       => array(
            'issued'     => sc_t('dashboard_pages.not_downloaded_yet', 'Not downloaded yet'),
            'downloaded' => sc_t('dashboard_pages.downloaded', 'Downloaded'),
            'revoked'    => sc_t('dashboard_pages.revoked', 'Revoked'),
        ),
        'ruleCheckin'  => sc_t('dashboard_pages.rule_checkin', 'People must be checked in'),
        'ruleCheckout' => sc_t('dashboard_pages.rule_checkout', 'People must have checked out'),
        'ruleEnded'    => sc_t('dashboard_pages.rule_ended', 'Only after it ends (%s)'),
        'ruleEndedOk'  => sc_t('dashboard_pages.rule_ended_ok', 'It has ended'),
        'rulePaid'     => sc_t('dashboard_pages.rule_paid', 'Active, confirmed registrations only'),
        'offEvent'     => sc_t('dashboard_pages.certificates_off', 'Certificates are turned off here, so attendees can’t download theirs from My Account. What you issue can still be emailed. Turn them on in the event’s Certificates section.'),
        'noRules'      => sc_t('dashboard_pages.no_extra_rules', 'No check-in required'),
        'eligibleLine' => sc_t('dashboard_pages.n_eligible_line', '%1$s eligible · %2$s already have one'),
        'noneEligible' => sc_t('dashboard_pages.none_eligible', 'Nobody is waiting for a certificate here.'),
        'tplFromScope' => sc_t('dashboard_pages.template_from_settings', 'Set in this event’s certificate settings.'),
        'tplNone'      => sc_t('dashboard_pages.template_not_set', 'No template set in its settings — choose one.'),
        'issueAllN'    => sc_t('dashboard_pages.issue_to_n', 'Issue to %s people'),
        'confirmAll'   => sc_t('dashboard_pages.confirm_issue_all', 'Issue certificates to %1$s people using “%2$s”?'),
        'confirmEmail' => sc_t('dashboard_pages.confirm_issue_all_send', ' Each of them will also get their certificate on WhatsApp.'),
        'issuing'      => sc_t('dashboard_pages.issuing_progress', 'Issuing… %1$s of %2$s'),
        'emailing'     => sc_t('dashboard_pages.emailing_progress', 'Sending emails… %1$s of %2$s'),
        'doneIssued'   => sc_t('dashboard_pages.done_issued', '%s certificates issued.'),
        'doneEmails'   => sc_t('dashboard_pages.done_sent', '%1$s certificates sent, %2$s could not be sent. WhatsApp messages go out one by one.'),
        'issue'        => sc_t('dashboard_pages.issue_certificate', 'Issue certificate'),
        'issueAnyway'  => sc_t('dashboard_pages.issue_anyway', 'Issue anyway'),
        'confirmOverride' => sc_t('dashboard_pages.confirm_override', 'Some of these people don’t meet the rules (%s). Issue their certificates anyway? People with a cancelled or unpaid registration are always skipped.'),
        'openCert'     => sc_t('dashboard_pages.open_in_certificates', 'Find in certificates'),
        'openReg'      => sc_t('dashboard_pages.open_registration', 'Open registration'),
        'skipped'      => sc_t('dashboard_pages.n_skipped', '%s skipped (already had one or didn’t qualify).'),
        'noTemplate'   => sc_t('dashboard_pages.choose_template_first', 'Choose a template first.'),
        'search'       => sc_t('general.search', 'Search'),
        'emptyText'    => sc_t('dashboard_pages.no_attendees_here', 'No registrations here.'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    // The list keeps its filters in the URL; start from the page's chosen scope.
    var q = new URLSearchParams(location.search);
    if (!q.get('scope')) {
        q.set('scope', <?php echo $js($scope); ?>);
        q.delete('event_id'); q.delete('workshop_id');
        history.replaceState(null, '', location.pathname + '?' + q.toString());
    }

    var scope = null, counts = null, busy = false;
    var num = WDList.num;
    function fmt(s) { var args = [].slice.call(arguments, 1); return s.replace(/%(\d)\$s/g, function (m, i) { return args[i - 1]; }).replace('%s', args[0]); }
    function post(data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); }
    function day(d) { return new Date(String(d).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); }
    function templateName() { return $('#template option:selected').text(); }

    function renderSummary(data) {
        scope = data.scope; counts = data.counts;
        var rules = [];
        if (!scope.enabled) { rules.push('<li class="is-warn">' + esc(L.offEvent) + '</li>'); }
        rules.push('<li>' + esc(L.rulePaid) + '</li>');
        rules.push('<li>' + esc(scope.require_checkin ? L.ruleCheckin : L.noRules) + '</li>');
        if (scope.require_checkout) { rules.push('<li>' + esc(L.ruleCheckout) + '</li>'); }
        if (scope.require_ended) { rules.push('<li' + (scope.ended ? '' : ' class="is-warn"') + '>' + esc(scope.ended ? L.ruleEndedOk : fmt(L.ruleEnded, day(scope.end_date))) + '</li>'); }
        $('#rules').html(rules.join(''));
        $('#eligible-line').text(counts.eligible ? fmt(L.eligibleLine, num(counts.eligible), num(counts.has)) : L.noneEligible);
        $('#issue-all').prop('disabled', !counts.eligible || busy).text(counts.eligible ? fmt(L.issueAllN, num(counts.eligible)) : L.issueAllN.replace('%s', '0'));
    }
    var lastScopeKey = null;
    function syncTemplate(data) {
        if (lastScopeKey === list.state().filters.scope) { return; }
        lastScopeKey = list.state().filters.scope;
        var id = data.scope.template_id;
        if (id && $('#template option[value="' + id + '"]').length) { $('#template').val(String(id)); $('#template-note').text(L.tplFromScope); }
        else { $('#template').val(String(defaultTemplate)); $('#template-note').text(L.tplNone); }
    }

    function progress(text, done, total) {
        $('#progress').prop('hidden', false);
        $('#progress-text').text(text);
        $('#progress-fill').css('width', total ? Math.min(100, Math.round(done / total * 100)) + '%' : '0');
    }
    function emailAll(ids) {
        var sent = 0, failed = 0, i = 0;
        function next() {
            if (i >= ids.length) { return $.Deferred().resolve({ sent: sent, failed: failed }).promise(); }
            var chunk = ids.slice(i, i + 50);
            progress(fmt(L.emailing, num(i), num(ids.length)), i, ids.length);
            return post({ action: 'sc_certificates_bulk', op: 'email', ids: chunk, continuing: i > 0 ? 1 : 0 }).then(function (res) {
                if (res.success) { sent += res.data.sent; failed += res.data.failed; } else { failed += chunk.length; }
                i += 50;
                return next();
            });
        }
        return next();
    }
    function finish(issued, ids, skipped) {
        var withEmail = $('#send-email').is(':checked') && ids.length;
        var after = withEmail ? emailAll(ids) : $.Deferred().resolve(null).promise();
        return after.then(function (mail) {
            var msg = fmt(L.doneIssued, num(issued));
            if (skipped) { msg += ' ' + fmt(L.skipped, num(skipped)); }
            if (mail) { msg += ' ' + fmt(L.doneEmails, num(mail.sent), num(mail.failed)); }
            (mail && mail.failed ? showWarning : showSuccess)(msg);
        }).always(function () {
            busy = false;
            $('#progress').prop('hidden', true);
            list.reload();
        });
    }

    $('#issue-all').on('click', function () {
        var template = $('#template').val();
        if (!template) { showError(L.noTemplate); return; }
        var total = counts.eligible;
        showConfirm(fmt(L.confirmAll, num(total), templateName()) + ($('#send-email').is(':checked') ? L.confirmEmail : '')).then(function (r) {
            if (!r.isConfirmed) { return; }
            busy = true;
            $('#issue-all').prop('disabled', true);
            var issued = 0, ids = [];
            (function step() {
                progress(fmt(L.issuing, num(issued), num(total)), issued, total);
                post({ action: 'sc_issue_certificates', scope: list.state().filters.scope, template_id: template, all_eligible: 1 }).done(function (res) {
                    if (!res.success) { busy = false; $('#progress').prop('hidden', true); showError(res.data && res.data.message || L.failed); list.reload(); return; }
                    issued += res.data.issued;
                    ids = ids.concat(res.data.ids);
                    if (res.data.remaining > 0 && res.data.issued > 0) { step(); } else { finish(issued, ids, 0); }
                }).fail(function () { busy = false; $('#progress').prop('hidden', true); showError(L.failed); list.reload(); });
            })();
        });
    });

    function issueSelected(ids, rows) {
        var template = $('#template').val();
        if (!template) { showError(L.noTemplate); return; }
        var reasons = {};
        rows.forEach(function (r) { if (!r.eligible && r.overridable) { r.why.forEach(function (w) { reasons[L.why[w]] = 1; }); } });
        var needOverride = Object.keys(reasons).length > 0;
        var ask = needOverride ? showConfirm(fmt(L.confirmOverride, Object.keys(reasons).join(', '))) : $.Deferred().resolve({ isConfirmed: true }).promise();
        $.when(ask).then(function (r) {
            if (!r.isConfirmed) { return; }
            busy = true;
            progress(fmt(L.issuing, 0, num(ids.length)), 0, ids.length);
            var issued = 0, skipped = 0, newIds = [], i = 0;
            (function step() {
                if (i >= ids.length) { finish(issued, newIds, skipped); return; }
                post({ action: 'sc_issue_certificates', scope: list.state().filters.scope, template_id: template, ids: ids.slice(i, i + 200), override: needOverride ? 1 : 0 }).done(function (res) {
                    if (!res.success) { busy = false; $('#progress').prop('hidden', true); showError(res.data && res.data.message || L.failed); return; }
                    issued += res.data.issued; skipped += res.data.skipped + res.data.failed; newIds = newIds.concat(res.data.ids);
                    i += 200;
                    progress(fmt(L.issuing, num(issued), num(ids.length)), issued, ids.length);
                    step();
                }).fail(function () { busy = false; $('#progress').prop('hidden', true); showError(L.failed); });
            })();
        });
    }

    var list = WDList.create({
        root: document.getElementById('issue-page'),
        action: 'sc_certificate_candidates',
        rowsKey: 'rows',
        filters: ['scope', 'search'],
        fixedFilters: ['scope'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        tabs: [
            { key: 'eligible', label: L.eligible, params: { view: 'eligible' }, countKey: 'eligible' },
            { key: 'waiting', label: L.waiting, params: { view: 'waiting' }, countKey: 'waiting' },
            { key: 'has', label: L.has, params: { view: 'has' }, countKey: 'has' },
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' }
        ],
        emptyText: L.emptyText,
        onData: function (data) { if (data.scope) { syncTemplate(data); renderSummary(data); } },
        columns: [
            {
                label: L.attendee,
                render: function (a) {
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'attendee-edit?id=' + a.id) + '">' + esc(a.name) + '</a><span class="w-sub w-ltr w-truncate">' + esc(a.email) + '</span></div>';
                }
            },
            { label: L.ticket, className: 'w-col-xl', render: function (a) { return '<span class="w-truncate">' + esc(a.ticket || '—') + '</span>'; } },
            {
                label: L.checkin,
                render: function (a) { return a.checked_in ? '<span class="w-tag w-tag--teal">' + esc(L.checkedIn) + '</span>' : '<span class="text-muted">' + esc(L.notYet) + '</span>'; }
            },
            {
                label: L.certificate,
                render: function (a) {
                    if (a.cert) {
                        return '<div class="w-stack"><span class="w-mono w-nowrap">' + esc(a.cert.number) + '</span><span class="w-sub">' + esc(L.status[a.cert.status] || a.cert.status) + ' · ' + esc(day(a.cert.issued_at)) + '</span></div>';
                    }
                    if (a.eligible) { return '<span class="w-tag w-tag--primary">' + esc(L.ready) + '</span>'; }
                    return a.why.map(function (w) { return '<span class="w-tag w-tag--gold">' + esc(L.why[w] || w) + '</span>'; }).join(' ');
                }
            }
        ],
        rowMenu: function (a) {
            var items = [];
            if (!a.cert) {
                items.push({ label: a.eligible ? L.issue : L.issueAnyway, disabled: !a.overridable || busy, onSelect: function () { issueSelected([a.id], [a]); } });
            } else {
                items.push({ label: L.openCert, href: dashboardUrl + 'certificates?search=' + encodeURIComponent(a.cert.number) });
            }
            items.push({ label: L.openReg, href: dashboardUrl + 'attendee-edit?id=' + a.id });
            return items;
        },
        bulkActions: [
            { key: 'issue', label: L.issue, run: function (ids, rows) { issueSelected(ids, rows.filter(Boolean)); } }
        ],
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        }
    });
});
</script>
<?php endif; ?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
