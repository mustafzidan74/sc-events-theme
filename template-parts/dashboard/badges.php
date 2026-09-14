<?php
/**
 * Badge printing — who to print (list pattern over sc_badges_people) and how it looks,
 * then a PDF from sc_badges_prepare (inc/admin-dashboard/badges-ajax-handlers.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_form;
$load_wd_list = true;
$load_wd_form = true;
$p = $wpdb->prefix;

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, logo_image, extra_fields, COALESCE(end_date, start_date) >= %s AS current FROM {$p}sc_events
     WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200",
    current_time('Y-m-d')
));
$event_ids = array_map('intval', wp_list_pluck($events, 'id'));
$tickets = $event_ids ? $wpdb->get_results("SELECT id, event_id, workshop_id, name FROM {$p}sc_tickets WHERE event_id IN (" . implode(',', $event_ids) . ') ORDER BY sort_order, id') : array();

// The event people are most likely printing for: the next one still running, else the latest.
$default_event = 0;
foreach ($events as $ev) {
    if ((int) $ev->current) {
        $default_event = (int) $ev->id;
    }
}
if (!$default_event && $events) {
    $default_event = (int) $events[0]->id;
}

$event_data = array();
foreach ($events as $ev) {
    $questions = json_decode((string) $ev->extra_fields, true);
    $labels = array();
    foreach (is_array($questions) ? $questions : array() as $q) {
        if (is_array($q) && !empty($q['label'])) {
            $labels[] = (string) $q['label'];
        }
    }
    $event_data[(int) $ev->id] = array(
        'title'     => $ev->title,
        'questions' => $labels,
        'logo'      => $ev->logo_image ? array('id' => (int) $ev->logo_image, 'url' => (string) wp_get_attachment_image_url((int) $ev->logo_image, 'medium')) : null,
        'tickets'   => array(),
    );
}
foreach ($tickets as $t) {
    $event_data[(int) $t->event_id]['tickets'][] = array('id' => (int) $t->id, 'name' => $t->name, 'workshop' => (int) $t->workshop_id > 0);
}

$views = sc_badges_views();
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
            <h1><?php echo esc_html(sc_t('badges.page_title', 'Badges')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('badges.subtitle', 'Print name badges for attendees, speakers, exhibitors and organizers. Attendee and exhibitor badges carry the QR code the scanner reads.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <button type="button" class="btn btn-primary" data-print-all><i class="fa fa-file-pdf-o" aria-hidden="true"></i> <span data-print-label><?php echo esc_html(sc_t('badges.download_pdf', 'Download PDF')); ?></span></button>
        </div>
    </div>

    <div class="w-form-layout w-form-layout--noseq w-badges">
        <div class="w-form-main" id="badge-people">
            <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('badges.who', 'Who to print')); ?>"></div>
            <div class="w-toolbar">
                <select class="form-control w-badges__event" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
                    <?php if (!$events): ?><option value=""><?php echo esc_html(sc_t('dashboard_pages.no_events', 'No events yet')); ?></option><?php endif; ?>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="w-search">
                    <span class="sr-only"><?php echo esc_html(sc_t('general.search', 'Search')); ?></span>
                    <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                    <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('badges.search', 'Search name, email or code')); ?>" autocomplete="off">
                    <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
                </label>
                <select class="form-control" data-w-filter="ticket" id="ticket-filter" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.ticket', 'Ticket')); ?>"></select>
                <select class="form-control" data-w-filter="checkin" id="checkin-filter" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.check_in', 'Check-in')); ?>">
                    <option value=""><?php echo esc_html(sc_t('badges.everyone', 'Checked in or not')); ?></option>
                    <option value="out"><?php echo esc_html(sc_t('dashboard_pages.not_checked_in', 'Not checked in')); ?></option>
                    <option value="in"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></option>
                </select>
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

        <aside class="w-form-aside w-badges__aside" id="badge-settings">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('badges.preview', 'Preview')); ?></span>
                <div class="w-bprev-wrap"><div class="w-bprev" id="badge-preview" aria-hidden="true"></div></div>
                <p class="w-field__help mt-2 mb-0" id="preview-note"></p>
            </div>

            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('badges.print', 'Print')); ?></span>
                <p class="w-badges__count" id="print-count"></p>
                <div class="w-field" id="part-field" hidden>
                    <label for="print-part"><?php echo esc_html(sc_t('badges.part', 'Part')); ?></label>
                    <select class="form-control" id="print-part"></select>
                    <p class="w-field__help"><?php echo esc_html(sc_t('badges.part_help', 'Big lists come in PDFs of 500 badges so they open and print reliably.')); ?></p>
                </div>
                <button type="button" class="btn btn-primary btn-block" data-print-all><i class="fa fa-file-pdf-o" aria-hidden="true"></i> <span data-print-label><?php echo esc_html(sc_t('badges.download_pdf', 'Download PDF')); ?></span></button>
                <p class="w-field__help mt-2 mb-0"><?php echo esc_html(sc_t('badges.tick_help', 'To print only some people, tick them in the list and choose Print ticked.')); ?></p>
            </div>

            <details class="w-badges__more" id="badge-design" open>
            <summary class="w-badges__summary"><?php echo esc_html(sc_t('badges.design_settings', 'Design, content and paper')); ?></summary>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('badges.design', 'Design')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup" aria-label="<?php echo esc_attr(sc_t('badges.design', 'Design')); ?>">
                    <?php foreach (array(
                        'corporate' => array(sc_t('badges.corporate', 'Corporate'), sc_t('badges.corporate_help', 'Colour bar with your logo, name centred')),
                        'modern'    => array(sc_t('badges.modern', 'Modern'), sc_t('badges.modern_help', 'Side stripe in the badge-type colour')),
                        'elegant'   => array(sc_t('badges.elegant', 'Elegant'), sc_t('badges.elegant_help', 'Full colour with photo or initials')),
                    ) as $value => $label): ?>
                        <label class="w-choice__item"><input type="radio" name="design" value="<?php echo esc_attr($value); ?>"><span class="w-choice__box"><span><?php echo esc_html($label[0]); ?><span class="w-choice__sub"><?php echo esc_html($label[1]); ?></span></span></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('badges.content', 'Content')); ?></span>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="badge-subtitle"><?php echo esc_html(sc_t('badges.under_name', 'Under the attendee name')); ?></label>
                        <select class="form-control" id="badge-subtitle" name="subtitle"></select>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('badges.event_logo', 'Logo')); ?></span>
                        <div class="w-badges__logo">
                            <span class="w-logo-thumb" id="logo-thumb"><img src="" alt="" hidden><span class="w-logo-thumb--empty" data-none><?php echo esc_html(sc_t('badges.no_logo', 'None')); ?></span></span>
                            <button type="button" class="btn btn-sm btn-secondary" id="logo-choose"><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                            <button type="button" class="btn btn-sm btn-secondary" id="logo-remove" hidden><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                        </div>
                        <p class="w-field__help"><?php echo esc_html(sc_t('badges.logo_help', 'Starts as the event logo.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="badge-color"><?php echo esc_html(sc_t('badges.primary_color', 'Colour')); ?></label>
                        <input type="color" class="form-control w-color" id="badge-color" name="primary_color" value="#1a73e8">
                    </div>
                    <label class="w-switch"><input type="checkbox" name="include_qr" value="1"><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('badges.include_qr', 'QR code')); ?></strong><span><?php echo esc_html(sc_t('badges.include_qr_help', 'Attendees and exhibitors only — the scanner reads these.')); ?></span></span></label>
                    <label class="w-switch"><input type="checkbox" name="include_event" value="1"><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('badges.include_event', 'Event name')); ?></strong></span></label>
                    <label class="w-switch" data-elegant-only><input type="checkbox" name="include_photos" value="1"><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('badges.include_photos', 'Photos and logos')); ?></strong><span><?php echo esc_html(sc_t('badges.include_photos_help', 'Speaker photos and company logos in the circle.')); ?></span></span></label>
                </div>
            </div>

            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('badges.paper', 'Paper')); ?></span>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup" aria-label="<?php echo esc_attr(sc_t('badges.badge_size', 'Badge size')); ?>">
                        <label class="w-choice__item"><input type="radio" name="badge_size" value="standard"><span class="w-choice__box"><span><?php echo esc_html(sc_t('badges.standard', 'Lanyard')); ?><span class="w-choice__sub">102 × 76 mm</span></span></span></label>
                        <label class="w-choice__item"><input type="radio" name="badge_size" value="id_card"><span class="w-choice__box"><span><?php echo esc_html(sc_t('badges.id_card', 'ID card')); ?><span class="w-choice__sub">86 × 54 mm</span></span></span></label>
                    </div>
                    <div class="w-choice" role="radiogroup" aria-label="<?php echo esc_attr(sc_t('badges.layout', 'Print layout')); ?>">
                        <label class="w-choice__item"><input type="radio" name="layout_mode" value="grid"><span class="w-choice__box"><span><?php echo esc_html(sc_t('badges.grid', '6 per A4')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('badges.grid_help', 'With cut lines')); ?></span></span></span></label>
                        <label class="w-choice__item"><input type="radio" name="layout_mode" value="single"><span class="w-choice__box"><span><?php echo esc_html(sc_t('badges.single', '1 per page')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('badges.single_help', 'Badge printers')); ?></span></span></span></label>
                    </div>
                </div>
            </div>
            </details>
        </aside>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var events = <?php echo $js($event_data); ?>;
    var views = <?php echo $js($views); ?>;
    var perPdf = <?php echo (int) SC_BADGES_PER_PDF; ?>;
    var L = <?php echo $js(array(
        'views'      => array('attendee' => sc_t('badges.attendees', 'Attendees'), 'speaker' => sc_t('badges.speakers', 'Speakers'), 'company' => sc_t('badges.exhibitors', 'Exhibitors'), 'organizer' => sc_t('badges.organizers', 'Organizers')),
        'types'      => array('attendee' => 'ATTENDEE', 'vip' => 'VIP', 'speaker' => 'SPEAKER', 'exhibitor' => 'EXHIBITOR', 'organizer' => 'ORGANIZER'),
        'name'       => sc_t('badges.name', 'Name'),
        'onBadge'    => sc_t('badges.on_badge', 'Under the name'),
        'type'       => sc_t('badges.type', 'Badge'),
        'checkin'    => sc_t('dashboard_pages.check_in', 'Check-in'),
        'in'         => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'notYet'     => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'noQr'       => sc_t('badges.no_qr', 'No QR'),
        'workshop'   => sc_t('dashboard_pages.workshop', 'Workshop'),
        'eventTickets' => sc_t('badges.event_tickets', 'Event tickets (one badge each)'),
        'allTickets' => sc_t('badges.all_tickets', 'All tickets, workshops too'),
        'ticketLine' => sc_t('badges.ticket_name', 'Ticket name'),
        'nothing'    => sc_t('badges.nothing', 'Nothing'),
        'answer'     => sc_t('badges.answer_to', 'Answer: %s'),
        'printAll'   => sc_t('badges.print_all', 'Download %s badges'),
        'printOne'   => sc_t('badges.print_one', 'Download 1 badge'),
        'printPart'  => sc_t('badges.print_part', 'Download part %1$d of %2$d'),
        'countAll'   => sc_t('badges.count_all', '%1$s %2$s match the filters.'),
        'partOpt'    => sc_t('badges.part_option', 'Part %1$d — badges %2$s–%3$s'),
        'printTicked' => sc_t('badges.print_ticked', 'Print ticked'),
        'tooMany'    => sc_t('badges.too_many', 'Tick at most 500 people at a time, or print by part.'),
        'nobody'     => sc_t('badges.nobody', 'Nobody to print. Change the filters.'),
        'generating' => sc_t('badges.generating', 'Preparing the PDF…'),
        'ready'      => sc_t('badges.ready', 'PDF ready — %d badges. Your download is starting.'),
        'sample'     => sc_t('badges.sample', 'Sample badge — tick or search to see a real one.'),
        'showing'    => sc_t('badges.showing', 'Showing %s.'),
        'qrOff'      => sc_t('badges.qr_off', 'Speakers and organizers print without a QR code: the scanner only reads tickets and company badges.'),
        'emptyText'  => sc_t('badges.empty', 'Nobody here for this event yet.'),
        'search'     => sc_t('general.search', 'Search'),
        'chooseLogo' => sc_t('badges.event_logo', 'Logo'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    /* ------------------------------------------------------------ settings */

    var STORE = 'scBadgeSettings';
    var defaults = { design: 'corporate', badge_size: 'standard', layout_mode: 'grid', primary_color: '#1a73e8', include_qr: true, include_event: true, include_photos: true, subtitle: 'ticket' };
    var settings = $.extend({}, defaults);
    try { $.extend(settings, JSON.parse(localStorage.getItem(STORE) || '{}')); } catch (x) { /* storage blocked */ }
    var logo = null; // {id, url} — per event, not remembered
    var list = null;
    var lastRows = [];
    var ticked = [];
    var aside = $('#badge-settings');

    function writeSettings() {
        aside.find('input[name="design"][value="' + settings.design + '"]').prop('checked', true);
        aside.find('input[name="badge_size"][value="' + settings.badge_size + '"]').prop('checked', true);
        aside.find('input[name="layout_mode"][value="' + settings.layout_mode + '"]').prop('checked', true);
        $('#badge-color').val(/^#[0-9a-f]{6}$/i.test(settings.primary_color) ? settings.primary_color : defaults.primary_color);
        ['include_qr', 'include_event', 'include_photos'].forEach(function (k) { aside.find('input[name="' + k + '"]').prop('checked', !!settings[k]); });
    }
    function readSettings() {
        settings.design = aside.find('input[name="design"]:checked').val() || 'corporate';
        settings.badge_size = aside.find('input[name="badge_size"]:checked').val() || 'standard';
        settings.layout_mode = aside.find('input[name="layout_mode"]:checked').val() || 'grid';
        settings.primary_color = $('#badge-color').val();
        ['include_qr', 'include_event', 'include_photos'].forEach(function (k) { settings[k] = aside.find('input[name="' + k + '"]').is(':checked'); });
        settings.subtitle = $('#badge-subtitle').val() || '';
        try { localStorage.setItem(STORE, JSON.stringify(settings)); } catch (x) { /* storage blocked */ }
    }
    writeSettings();
    // Phones: preview and print first, the design settings folded away.
    if (window.matchMedia('(max-width: 991.98px)').matches) { document.getElementById('badge-design').removeAttribute('open'); }

    function setLogo(value) {
        logo = value && value.id ? value : null;
        $('#logo-thumb img').attr('src', logo ? logo.url : '').prop('hidden', !logo);
        $('#logo-thumb [data-none]').prop('hidden', !!logo);
        $('#logo-remove').prop('hidden', !logo);
        renderPreview();
    }
    var frame = null;
    $('#logo-choose').on('click', function () {
        if (!window.wp || !wp.media) { return; }
        if (!frame) {
            frame = wp.media({ title: L.chooseLogo, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                setLogo({ id: att.id, url: att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url });
            });
        }
        frame.open();
    });
    $('#logo-remove').on('click', function () { setLogo(null); });

    /* --------------------------------------------------------- event bits */

    function currentEvent() { return events[(list ? list.state().filters.event_id : '') || initialEvent] || null; }

    function fillEventControls(eventId, keepTicket) {
        var ev = events[eventId];
        var ticketSel = $('#ticket-filter');
        var current = keepTicket ? ticketSel.val() : '';
        var opts = '<option value="">' + esc(L.eventTickets) + '</option><option value="all">' + esc(L.allTickets) + '</option>';
        (ev ? ev.tickets : []).forEach(function (t) {
            opts += '<option value="' + t.id + '">' + esc((t.workshop ? L.workshop + ': ' : '') + t.name) + '</option>';
        });
        ticketSel.html(opts).val(current);

        var sub = $('#badge-subtitle');
        var subOpts = '<option value="ticket">' + esc(L.ticketLine) + '</option><option value="">' + esc(L.nothing) + '</option>';
        (ev ? ev.questions : []).forEach(function (q) { subOpts += '<option value="' + esc(q) + '">' + esc(L.answer.replace('%s', q)) + '</option>'; });
        sub.html(subOpts);
        sub.val(sub.find('option').filter(function () { return this.value === settings.subtitle; }).length ? settings.subtitle : 'ticket');
        setLogo(ev && ev.logo && ev.logo.url ? ev.logo : null);
    }

    // Open on the most likely event rather than an empty page.
    var initialEvent = <?php echo (int) $default_event; ?>;
    (function () {
        var q = new URLSearchParams(location.search);
        if (!q.get('event_id') || !events[q.get('event_id')]) {
            if (!initialEvent) { return; }
            q.set('event_id', initialEvent);
            history.replaceState(null, '', location.pathname + '?' + q.toString());
        } else {
            initialEvent = +q.get('event_id');
        }
        fillEventControls(initialEvent, false);
        if (q.get('ticket')) { $('#ticket-filter').val(q.get('ticket')); }
    })();

    /* --------------------------------------------------------------- list */

    function tone(s) { var h = 0; for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) % 4; } return h; }
    function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?'; }
    var TAG = { attendee: '', vip: 'w-tag--gold', speaker: 'w-tag--teal', exhibitor: 'w-tag--primary', organizer: '' };
    list = WDList.create({
        root: document.getElementById('badge-people'),
        action: 'sc_badges_people',
        rowsKey: 'rows',
        filters: ['event_id', 'search', 'ticket', 'checkin'],
        fixedFilters: ['event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        tabs: views.map(function (v) { return { key: v, label: L.views[v], params: { view: v }, countKey: v }; }),
        extraParams: function () { return { subtitle: $('#badge-subtitle').val() || '' }; },
        emptyText: L.emptyText,
        onFiltersChange: function (f) {
            if (f.event_id && +f.event_id !== +($('#ticket-filter').data('event') || 0)) {
                $('#ticket-filter').data('event', +f.event_id);
                fillEventControls(+f.event_id, true);
            }
        },
        onData: function (data, api) {
            var view = api.state().tab;
            lastRows = data.rows || [];
            ticked = ticked.filter(function (r) { return r._view === view; });
            $('#ticket-filter').prop('hidden', view !== 'attendee');
            $('#checkin-filter').prop('hidden', view === 'speaker' || view === 'organizer');
            renderPrint();
            renderPreview();
        },
        columns: [
            {
                label: L.name,
                render: function (r) {
                    var pic = r.photo
                        ? '<span class="w-person__avatar w-badges__pic"><img src="' + esc(r.photo) + '" alt=""></span>'
                        : '<span class="w-person__avatar" data-tone="' + tone(r.name) + '">' + esc(initials(r.name)) + '</span>';
                    return '<div class="w-person">' + pic + '<span class="w-person__text"><span class="w-row-title">' + esc(r.name) + '</span>' +
                        (r.meta ? '<span class="w-sub w-ltr w-truncate">' + esc(r.meta) + '</span>' : '') + '</span></div>';
                }
            },
            {
                label: L.onBadge,
                render: function (r) {
                    if (!r.sub && !r.detail) { return '<span class="text-muted">—</span>'; }
                    return '<div class="w-stack"><span class="w-truncate">' + esc(r.sub || r.detail) + '</span>' + (r.sub && r.detail ? '<span class="w-sub w-truncate">' + esc(r.detail) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.type,
                render: function (r) {
                    return '<div class="w-stack w-nowrap"><span class="w-tag ' + TAG[r.badge] + '">' + esc(L.types[r.badge] || r.badge) + '</span>' +
                        (r.workshop ? '<span class="w-sub">' + esc(L.workshop) + '</span>' : (!r.qr ? '<span class="w-sub">' + esc(L.noQr) + '</span>' : '')) + '</div>';
                }
            },
            {
                label: L.checkin, className: 'w-col-xl',
                render: function (r) {
                    if (r.checked_in === null) { return '<span class="text-muted">—</span>'; }
                    return r.checked_in ? '<span class="w-tag w-tag--teal">' + esc(L.in) + '</span>' : '<span class="text-muted">' + esc(L.notYet) + '</span>';
                }
            }
        ],
        bulkActions: [
            {
                key: 'print', label: L.printTicked, icon: 'M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z',
                run: function (ids) {
                    if (ids.length > perPdf) { showError(L.tooMany); return; }
                    print(ids, 1);
                }
            }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.ticket) { chips.push({ label: L.ticketLine, value: $('#ticket-filter option:selected').text(), clear: function (l) { l.setFilter('ticket', ''); } }); }
            if (f.checkin) { chips.push({ label: L.checkin, value: $('#checkin-filter option:selected').text(), clear: function (l) { l.setFilter('checkin', ''); } }); }
            return chips;
        }
    });

    // Preview follows the first ticked row.
    $('#badge-people').on('change', '[data-w-check], [data-w-check-all]', function () {
        var view = list.state().tab;
        setTimeout(function () {
            var ids = $('#badge-people [data-w-check]:checked').map(function () { return this.value; }).get();
            ticked = lastRows.filter(function (r) { return ids.indexOf(String(r.id)) > -1; }).map(function (r) { return $.extend({ _view: view }, r); });
            renderPreview();
        });
    });

    /* ------------------------------------------------------------- print */

    function renderPrint() {
        var data = list.data();
        var total = data ? data.total : 0;
        var parts = Math.ceil(total / perPdf);
        var view = list.state().tab;
        $('#print-count').text(L.countAll.replace('%1$s', WDList.num(total)).replace('%2$s', String(L.views[view] || '').toLowerCase()));
        var partSel = $('#print-part');
        var keep = Math.min(+partSel.val() || 1, Math.max(parts, 1));
        $('#part-field').prop('hidden', parts < 2);
        var opts = '';
        for (var i = 1; i <= parts; i++) {
            opts += '<option value="' + i + '">' + esc(L.partOpt.replace('%1$d', i).replace('%2$s', WDList.num((i - 1) * perPdf + 1)).replace('%3$s', WDList.num(Math.min(i * perPdf, total)))) + '</option>';
        }
        partSel.html(opts).val(keep);
        var label = parts > 1 ? L.printPart.replace('%1$d', keep).replace('%2$d', parts) : (total === 1 ? L.printOne : L.printAll.replace('%s', WDList.num(total)));
        $('[data-print-label]').text(label);
        $('[data-print-all]').prop('disabled', !total);
    }
    $('#print-part').on('change', renderPrint);

    var busy = false;
    function print(ids, part) {
        if (busy) { return; }
        readSettings();
        var p = list.params();
        var body = $.extend({}, p, settings, {
            action: 'sc_badges_prepare', nonce: scDashboard.nonce, view: list.state().tab, part: part,
            logo_id: logo ? logo.id : '', ids: ids,
            include_qr: settings.include_qr ? '1' : '0', include_event: settings.include_event ? '1' : '0', include_photos: settings.include_photos ? '1' : '0'
        });
        busy = true;
        var btns = $('[data-print-all]').prop('disabled', true);
        if (window.toastr) { toastr.info(L.generating); }
        $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: body })
            .done(function (res) {
                if (!res.success) { showError(res.data && res.data.message ? res.data.message : L.failed); return; }
                if (window.toastr) { toastr.success(L.ready.replace('%d', res.data.count)); }
                // The PDF is sent as an attachment, so the page stays put.
                window.location.href = res.data.download_url;
            })
            .fail(function () { showError(L.failed); })
            .always(function () { busy = false; btns.prop('disabled', false); renderPrint(); });
    }
    $('[data-print-all]').on('click', function () {
        var data = list.data();
        if (!data || !data.total) { showError(L.nobody); return; }
        print([], +$('#print-part').val() || 1);
    });

    /* ----------------------------------------------------------- preview */

    var qrCache = {};
    function qrImg(code) {
        if (!code || !window.QRCode || !QRCode.toDataURL) { return ''; }
        if (!qrCache[code]) {
            var sync = true;
            // The callback can run straight away; only re-render when it comes later.
            QRCode.toDataURL(code, { width: 160, margin: 0 }, function (err, url) { if (!err) { qrCache[code] = url; if (!sync) { renderPreview(); } } });
            sync = false;
        }
        return qrCache[code];
    }
    var TYPE_COLOR = { attendee: '#3498db', vip: '#f39c12', speaker: '#2ecc71', exhibitor: '#16a085', organizer: '#9b59b6' };

    function renderPreview() {
        if (!list) { return; } // still setting up
        readSettings();
        var ev = currentEvent();
        var row = ticked[0] || lastRows[0] || null;
        var r = row || { name: 'Nour Hassan', sub: 'Cairo University', detail: 'Congress ticket', badge: 'attendee', qr: 'SAMPLE', photo: '' };
        var design = settings.design;
        var color = settings.primary_color;
        var tc = TYPE_COLOR[r.badge] || TYPE_COLOR.attendee;
        var qr = settings.include_qr && r.qr ? qrImg(r.qr) : '';
        var qrHtml = settings.include_qr && r.qr ? '<span class="w-bprev__qr">' + (qr ? '<img src="' + qr + '" alt="">' : '') + '</span>' : '';
        var evHtml = settings.include_event && ev ? '<span class="w-bprev__event">' + esc(ev.title) + '</span>' : '';
        var label = '<span class="w-bprev__type" style="' + (design === 'elegant' ? 'color:' + tc : 'background:' + tc) + '">' + esc(L.types[r.badge] || '') + '</span>';
        var logoHtml = logo ? '<img class="w-bprev__logo" src="' + esc(logo.url) + '" alt="">' : '';
        var html = '';

        if (design === 'modern') {
            html = '<span class="w-bprev__strip" style="background:' + tc + '"></span>' + logoHtml +
                '<span class="w-bprev__name">' + esc(r.name) + '</span>' +
                (r.sub ? '<span class="w-bprev__sub">' + esc(r.sub) + '</span>' : '') +
                (r.detail ? '<span class="w-bprev__detail">' + esc(r.detail) + '</span>' : '') + label + qrHtml + evHtml;
        } else if (design === 'elegant') {
            var pic = settings.include_photos && r.photo ? '<img src="' + esc(r.photo) + '" alt="">' : esc(initials(r.name));
            html = '<span class="w-bprev__circle" style="color:' + color + '">' + pic + '</span>' +
                '<span class="w-bprev__name">' + esc(r.name) + '</span>' +
                (r.sub ? '<span class="w-bprev__sub">' + esc(r.sub) + '</span>' : '') + label + qrHtml + evHtml;
        } else {
            html = '<span class="w-bprev__bar" style="background:' + color + '">' + logoHtml + '</span>' +
                '<span class="w-bprev__name">' + esc(r.name) + '</span>' +
                (r.sub ? '<span class="w-bprev__sub">' + esc(r.sub) + '</span>' : '') + label + qrHtml + evHtml;
        }
        $('#badge-preview').attr('class', 'w-bprev w-bprev--' + design + ' w-bprev--' + settings.badge_size)
            .css('background', design === 'elegant' ? color : '').html(html);
        $('[data-elegant-only]').prop('hidden', design !== 'elegant');

        var view = list ? list.state().tab : 'attendee';
        var note = row ? L.showing.replace('%s', r.name) : L.sample;
        if (settings.include_qr && (view === 'speaker' || view === 'organizer')) { note += ' ' + L.qrOff; }
        $('#preview-note').text(note);
    }

    aside.on('change input', 'input', function () { renderPreview(); });
    $('#badge-subtitle').on('change', function () { readSettings(); list.reload(true); });
    renderPreview();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
