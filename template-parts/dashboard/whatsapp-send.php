<?php
/**
 * Send on WhatsApp — bulk messages to event attendees, certificate holders or a pasted list,
 * sent one by one with a gap, with live progress and a per-person log.
 * Handlers: inc/admin-dashboard/whatsapp-campaigns-dashboard.php; engine: inc/whatsapp/wabot-campaigns.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_form, $load_wd_list;
$load_wd_form = true;
$load_wd_list = true;

$events = $wpdb->get_results("SELECT id, title, start_date FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') ORDER BY start_date DESC, id DESC");
$featured = (int) get_option('sc_featured_event_id', 0);
$pre_source = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : 'event';
$pre_event = isset($_GET['event_id']) ? absint($_GET['event_id']) : $featured;
$has_numbers = (bool) sc_wabot_candidates(null, 'campaign');

$templates = array(
    'event'        => "مرحباً د. {first_name}،\nنذكّرك بـ {event}.\nتذكرتك والبادج الخاص بك: {ticket_link}\n\nللإلغاء من هذه الرسائل اكتب: إلغاء",
    'certificates' => "مرحباً د. {first_name}،\nشهادة حضورك في {event} جاهزة للتحميل:\n{certificate_link}\n\nللإلغاء من هذه الرسائل اكتب: إلغاء",
    'manual'       => "مرحباً {first_name}،\n\n\nللإلغاء من هذه الرسائل اكتب: إلغاء",
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('whatsapp.send_title', 'Send on WhatsApp')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('whatsapp.send_sub', 'Messages go out one at a time with a gap between them, so your numbers are not flagged. If a number cannot send, the next connected number takes over.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-outline-secondary" href="<?php echo esc_url(home_url('/event-manager-dashboard/whatsapp')); ?>"><?php echo esc_html(sc_t('whatsapp.numbers_settings', 'Numbers and settings')); ?></a>
        </div>
    </div>

    <div class="w-was">
        <?php if (!$has_numbers): ?>
            <p class="w-was__warn"><?php echo esc_html(sc_t('whatsapp.no_bulk_numbers', 'No WhatsApp number is allowed for bulk sending yet. Add one on the WhatsApp page first.')); ?></p>
        <?php endif; ?>

        <!-- Campaigns -->
        <section class="w-section" aria-labelledby="was-list-title">
            <div class="w-section__head">
                <h2 id="was-list-title"><?php echo esc_html(sc_t('whatsapp.campaigns', 'Campaigns')); ?></h2>
                <button type="button" class="btn btn-primary btn-sm" id="was-new-toggle" aria-expanded="false" aria-controls="was-new"><?php echo esc_html(sc_t('whatsapp.new_campaign', 'New campaign')); ?></button>
            </div>
            <div id="was-list" class="w-was__list" aria-live="polite"><p class="w-sub mb-0"><?php echo esc_html(sc_t('general.loading', 'Loading…')); ?></p></div>
        </section>

        <!-- New campaign -->
        <section class="w-section" id="was-new" aria-labelledby="was-new-title" hidden>
            <div class="w-section__head">
                <h2 id="was-new-title"><?php echo esc_html(sc_t('whatsapp.new_campaign', 'New campaign')); ?></h2>
            </div>
            <form id="was-form" class="w-was__form" novalidate>
                <div class="w-field">
                    <label class="w-field__label" for="was-title"><?php echo esc_html(sc_t('whatsapp.campaign_name', 'Name (only you see it)')); ?></label>
                    <input class="form-control" id="was-title" name="title" maxlength="190" placeholder="<?php echo esc_attr(sc_t('whatsapp.campaign_name_ph', 'IDC certificates ready')); ?>">
                </div>

                <div class="w-field">
                    <span class="w-field__label"><?php echo esc_html(sc_t('whatsapp.send_to', 'Send to')); ?></span>
                    <div class="w-choice" role="radiogroup">
                        <label class="w-choice__item"><input type="radio" name="source" value="event" <?php checked($pre_source !== 'certificates' && $pre_source !== 'manual'); ?>><span class="w-choice__box"><?php echo esc_html(sc_t('whatsapp.src_event', 'Event attendees')); ?></span></label>
                        <label class="w-choice__item"><input type="radio" name="source" value="certificates" <?php checked($pre_source, 'certificates'); ?>><span class="w-choice__box"><?php echo esc_html(sc_t('whatsapp.src_certificates', 'Certificate holders')); ?></span></label>
                        <label class="w-choice__item"><input type="radio" name="source" value="manual" <?php checked($pre_source, 'manual'); ?>><span class="w-choice__box"><?php echo esc_html(sc_t('whatsapp.src_manual', 'A pasted list')); ?></span></label>
                    </div>
                </div>

                <div class="w-fields w-fields--2" id="was-event-fields">
                    <div class="w-field">
                        <label class="w-field__label" for="was-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
                        <select class="form-control" id="was-event" name="event_id">
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo esc_attr($ev->id); ?>" <?php selected($pre_event, (int) $ev->id); ?>><?php echo esc_html($ev->title . ($ev->start_date ? ' · ' . mysql2date('j M Y', $ev->start_date) : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label class="w-field__label" for="was-audience"><?php echo esc_html(sc_t('whatsapp.who', 'Who')); ?></label>
                        <select class="form-control" id="was-audience" name="audience"></select>
                    </div>
                </div>

                <div class="w-field" id="was-manual-field" hidden>
                    <label class="w-field__label" for="was-manual"><?php echo esc_html(sc_t('whatsapp.manual_list', 'One person per line: name, phone')); ?></label>
                    <textarea class="form-control w-ltr" id="was-manual" name="manual" rows="6" placeholder="Ahmed Ali, 01012345678&#10;01198765432"></textarea>
                </div>

                <div class="w-field">
                    <label class="w-field__label" for="was-message"><?php echo esc_html(sc_t('whatsapp.message', 'Message')); ?> <span class="w-field__count" id="was-count"></span></label>
                    <textarea class="form-control" id="was-message" name="message" rows="7" dir="auto"></textarea>
                    <div class="w-was__chips" aria-label="<?php echo esc_attr(sc_t('whatsapp.insert', 'Insert')); ?>">
                        <?php foreach (sc_wabot_placeholders() as $tag => $label): ?>
                            <button type="button" class="w-chipbtn" data-insert="<?php echo esc_attr($tag); ?>" title="<?php echo esc_attr($label); ?>"><?php echo esc_html($tag); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <p class="w-field__help"><?php echo esc_html(sc_t('whatsapp.message_help', 'Each person gets their own name and links. Keep the opt-out line: people who reply "إلغاء" or "STOP" are left out of future campaigns.')); ?></p>
                </div>

                <label class="w-switch" id="was-attach-field">
                    <input type="checkbox" id="was-attach" name="attach_qr" value="1">
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('whatsapp.attach_qr', 'Attach each person’s ticket QR')); ?></strong><span><?php echo esc_html(sc_t('whatsapp.attach_qr_sub', 'The message goes as the caption of a QR image they can show at the door.')); ?></span></span>
                </label>

                <div class="w-field w-was__gap">
                    <label class="w-field__label" for="was-interval"><?php echo esc_html(sc_t('whatsapp.gap', 'Gap between messages')); ?></label>
                    <select class="form-control" id="was-interval" name="interval_seconds">
                        <option value="30"><?php echo esc_html(sc_t('whatsapp.gap_30', '30 seconds — fast, only for numbers used daily')); ?></option>
                        <option value="45"><?php echo esc_html(sc_t('whatsapp.gap_45', '45 seconds')); ?></option>
                        <option value="60" selected><?php echo esc_html(sc_t('whatsapp.gap_60', '1 minute — recommended')); ?></option>
                        <option value="90"><?php echo esc_html(sc_t('whatsapp.gap_90', '1.5 minutes')); ?></option>
                        <option value="120"><?php echo esc_html(sc_t('whatsapp.gap_120', '2 minutes — safest for a new number')); ?></option>
                    </select>
                </div>

                <div class="w-was__formactions">
                    <button type="button" class="btn btn-outline-secondary" id="was-preview"><?php echo esc_html(sc_t('whatsapp.preview', 'Preview')); ?></button>
                    <p class="w-was__msg" role="alert" hidden></p>
                </div>

                <div class="w-was__preview" id="was-preview-box" hidden aria-live="polite"></div>
            </form>
        </section>
    </div>

</div>
</div>

<style>
.w-was { display: flex; flex-direction: column; gap: 16px; max-width: 1040px; }
.w-was [hidden] { display: none !important; }
.w-was__warn { margin: 0; padding: 12px 14px; border-radius: var(--w-radius-md); background: var(--w-warning-soft); color: var(--w-warning); font-size: 13.5px; }
.w-was__form { display: flex; flex-direction: column; gap: 16px; }
.w-was__gap { max-width: 420px; }
.w-was__chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.w-was__chips .w-chipbtn { font-family: var(--w-font-mono, monospace); font-size: 12px; }
.w-was__formactions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.w-was__msg { margin: 0; font-size: 13px; font-weight: 500; color: var(--w-danger); }
.w-was__preview { border: 1px solid var(--w-border); border-radius: var(--w-radius-md); padding: 16px; display: flex; flex-direction: column; gap: 12px; background: var(--w-surface-2); }
.w-was__stats { display: flex; flex-wrap: wrap; gap: 8px 18px; font-size: 14px; }
.w-was__stats strong { font-size: 18px; }
.w-was__bubbles { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 10px; }
.w-was__bubble { background: #DCF8C6; color: #111; border-radius: 10px 10px 2px 10px; padding: 10px 12px; font-size: 13.5px; white-space: pre-line; overflow-wrap: anywhere; box-shadow: var(--w-shadow-1); }
.w-was__bubble small { display: block; color: #555; margin-bottom: 4px; }
.w-was__list { display: flex; flex-direction: column; gap: 12px; }
.w-was__camp { border: 1px solid var(--w-border); border-radius: var(--w-radius-md); padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; background: var(--w-surface); }
.w-was__camptop { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.w-was__camptitle { font-weight: 600; }
.w-was__campmeta { color: var(--w-text-2); font-size: 13px; }
.w-was__campacts { margin-inline-start: auto; display: flex; flex-wrap: wrap; gap: 6px; }
.w-was__bar { display: flex; height: 10px; border-radius: 99px; overflow: hidden; background: var(--w-surface-2); }
.w-was__bar span { display: block; height: 100%; transition: width .4s ease; }
.w-was__bar .is-sent { background: #12B76A; }
.w-was__bar .is-problem { background: var(--w-danger); }
.w-was__bar .is-skipped { background: var(--w-text-3); }
.w-was__bar .is-stopped { background: var(--w-border-strong); }
.w-was__nums { display: flex; flex-wrap: wrap; gap: 4px 16px; font-size: 13px; color: var(--w-text-2); }
.w-was__nums b { color: var(--w-text); }
.w-was__reason { margin: 0; font-size: 13px; color: var(--w-warning); }
.w-was__log { border-top: 1px solid var(--w-border); padding-top: 12px; display: flex; flex-direction: column; gap: 10px; }
.w-was__logbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.w-was__logbar .form-control { max-width: 240px; height: 34px; }
.w-was__logtable td { font-size: 13px; vertical-align: top; }
.w-was__pager { display: flex; gap: 8px; align-items: center; font-size: 13px; }
@media (max-width: 767.98px) {
    .w-was__campacts { margin-inline-start: 0; }
    .w-dash .w-was__logtable tbody tr { display: block; padding: 10px 0; }
    .w-dash .w-was__logtable td { display: block !important; padding: 2px 0 !important; border: 0; text-align: start; }
    .w-dash .w-was__logtable td::before { content: none !important; display: none !important; }
}
</style>

<script>
jQuery(function ($) {
    'use strict';
    var ajax = scDashboard.ajaxurl, nonce = scDashboard.nonce;
    var templates = <?php echo wp_json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var dashboard = <?php echo wp_json_encode(home_url('/event-manager-dashboard/')); ?>;
    var STATUS = { running: 'Sending', paused: 'Paused', done: 'Finished', cancelled: 'Stopped' };
    var ROW = { queued: 'Waiting', pending: 'Waiting to retry', sending: 'Sending', sent: 'Sent', failed: 'Failed', skipped: 'Not on WhatsApp', cancelled: 'Stopped', expired: 'Not sent in time' };
    var openLogs = {};
    var messageTouched = false;

    function post(action, data) { return $.post(ajax, $.extend({ action: action, nonce: nonce }, data || {})); }
    function when(s) { if (!s) { return ''; } var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }
    function num(n) { return Number(n || 0).toLocaleString(); }

    /* ---------------- form ---------------- */

    function source() { return $('input[name=source]:checked').val(); }

    function fillAudience() {
        var $a = $('#was-audience').empty();
        if (source() === 'certificates') {
            $a.append(new Option('Everyone with a certificate', 'all'), new Option('Only those who have not downloaded it', 'not_downloaded'));
            return;
        }
        $a.append(new Option('All registered for the event', 'all'), new Option('Everyone: event and workshop registrations', 'everyone'), new Option('Came (checked in)', 'checked_in'), new Option('Did not come', 'not_checked_in'));
        post('sc_wabot_event_workshops', { event_id: $('#was-event').val() }).done(function (r) {
            if (!r.success || !r.data.length) { return; }
            var $g = $('<optgroup label="Workshop">').appendTo($a);
            r.data.forEach(function (w) { $g.append(new Option(w.title, 'workshop:' + w.id)); });
        });
    }

    function syncSource() {
        var s = source();
        $('#was-event-fields').prop('hidden', s === 'manual');
        $('#was-manual-field').prop('hidden', s !== 'manual');
        $('#was-attach-field').prop('hidden', s !== 'event');
        if (s !== 'event') { $('#was-attach').prop('checked', false); }
        if (!messageTouched || !$('#was-message').val().trim()) { $('#was-message').val(templates[s] || ''); messageTouched = false; }
        fillAudience();
        $('#was-preview-box').prop('hidden', true).empty();
        countChars();
    }
    function countChars() { $('#was-count').text($('#was-message').val().length + ' characters'); }

    $('input[name=source]').on('change', syncSource);
    $('#was-event').on('change', function () { fillAudience(); $('#was-preview-box').prop('hidden', true); });
    $('#was-message').on('input', function () { messageTouched = true; countChars(); $('#was-preview-box').prop('hidden', true); });
    $('#was-audience, #was-manual, #was-attach').on('change input', function () { $('#was-preview-box').prop('hidden', true); });
    // The gap only changes how long it takes: keep the preview, refresh its estimate.
    $('#was-interval').on('change', function () { if (!$('#was-preview-box').prop('hidden')) { $('#was-preview').trigger('click'); } });
    $('.w-was__chips').on('click', '[data-insert]', function () {
        var el = document.getElementById('was-message'), tag = $(this).data('insert');
        var s = el.selectionStart || el.value.length, e = el.selectionEnd || el.value.length;
        el.value = el.value.slice(0, s) + tag + el.value.slice(e);
        el.focus(); el.selectionStart = el.selectionEnd = s + tag.length;
        messageTouched = true; countChars();
    });

    $('#was-new-toggle').on('click', function () {
        var open = $('#was-new').prop('hidden');
        $('#was-new').prop('hidden', !open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
        if (open) { $('#was-title').trigger('focus'); document.getElementById('was-new').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    function formData() {
        return { title: $('#was-title').val(), source: source(), event_id: $('#was-event').val(), audience: $('#was-audience').val(), manual: $('#was-manual').val(), message: $('#was-message').val(), interval_seconds: $('#was-interval').val(), attach_qr: $('#was-attach').is(':checked') && source() === 'event' ? 1 : 0 };
    }
    function say(text) { $('.w-was__msg').text(text || '').prop('hidden', !text); }

    $('#was-preview').on('click', function () {
        say('');
        var d = formData();
        if (!d.message.trim()) { say('Write the message first.'); return; }
        var $b = $(this).prop('disabled', true);
        post('sc_wabot_campaign_preview', d).done(function (r) {
            if (!r.success) { say(r.data.message); return; }
            var p = r.data, $box = $('#was-preview-box').empty().prop('hidden', false);
            var $stats = $('<div class="w-was__stats">').appendTo($box);
            $stats.append($('<span>').append($('<strong>').text(num(p.count)), ' will get it'));
            if (p.invalid) { $stats.append($('<span>').text(num(p.invalid) + ' without a valid number')); }
            if (p.duplicates) { $stats.append($('<span>').text(num(p.duplicates) + ' duplicate numbers sent once')); }
            if (p.opted_out) { $stats.append($('<span>').text(num(p.opted_out) + ' asked to stop')); }
            if (p.attach) { $stats.append($('<span>').text('each with their ticket QR')); }
            $stats.append($('<span>').text('about ' + (p.minutes >= 120 ? (Math.round(p.minutes / 6) / 10) + ' hours' : p.minutes + ' minutes')));
            var $bub = $('<div class="w-was__bubbles">').appendTo($box);
            p.samples.forEach(function (s) { $bub.append($('<div class="w-was__bubble" dir="auto">').append($('<small class="w-ltr">').text((s.name ? s.name + ' · ' : '') + '+' + s.to)).append(document.createTextNode(s.text))); });
            if (!p.count) { $box.append($('<p class="w-was__warn">').text('Nobody in this list has a valid WhatsApp number.')); return; }
            if (!p.numbers) { $box.append($('<p class="w-was__warn">').text('No WhatsApp number is allowed for bulk sending.')); return; }
            $('<button type="button" class="btn btn-primary align-self-start" id="was-start">').text('Start sending to ' + num(p.count)).appendTo($box).on('click', function () { start(p); });
        }).fail(function () { say('Could not reach the site. Try again.'); }).always(function () { $b.prop('disabled', false); });
    });

    function start(p) {
        var d = formData();
        if (!d.title.trim()) { say('Give the campaign a name.'); $('#was-title').trigger('focus'); return; }
        Swal.fire({ icon: 'question', title: 'Send to ' + num(p.count) + ' people?', text: 'One message every ' + $('#was-interval option:selected').text().split(' —')[0] + '. You can pause or stop at any time.', showCancelButton: true, confirmButtonText: 'Start', cancelButtonText: 'Cancel' })
            .then(function (res) {
                if (!res.isConfirmed) { return; }
                post('sc_wabot_campaign_create', d).done(function (r) {
                    if (!r.success) { say(r.data.message); return; }
                    $('#was-form')[0].reset(); messageTouched = false; syncSource();
                    $('#was-new').prop('hidden', true); $('#was-new-toggle').attr('aria-expanded', 'false');
                    openLogs[r.data.id] = { status: '', page: 1, q: '' };
                    load(true);
                    document.getElementById('was-list-title').scrollIntoView({ behavior: 'smooth' });
                });
            });
    }

    /* ---------------- campaigns ---------------- */

    var timer = null;
    function load(tick) {
        clearTimeout(timer);
        return post('sc_wabot_campaigns', { tick: tick ? 1 : 0 }).done(function (r) {
            if (!r.success) { return; }
            render(r.data.campaigns);
            var running = r.data.campaigns.some(function (c) { return c.status === 'running'; });
            timer = setTimeout(function () { load(running); }, running ? 5000 : 30000);
        }).fail(function () { timer = setTimeout(function () { load(false); }, 15000); });
    }

    function render(list) {
        var $list = $('#was-list');
        if (!list.length) { $list.empty().append($('<p class="w-sub mb-0">').text('No campaigns yet.')); return; }
        $list.find('.w-sub').remove();
        var keep = {};
        list.forEach(function (c) {
            keep[c.id] = true;
            var $c = $list.children('[data-id="' + c.id + '"]');
            if (!$c.length) {
                $c = $('<article class="w-was__camp">').attr('data-id', c.id);
                $c.append('<div class="w-was__camptop"></div><div class="w-was__bar" role="progressbar" aria-valuemin="0"></div><div class="w-was__nums"></div><p class="w-was__reason" hidden></p>');
                $list.append($c);
            }
            var n = c.counts, total = Math.max(1, c.total);
            var $top = $c.find('.w-was__camptop').empty();
            $top.append($('<span class="w-was__camptitle">').text(c.title),
                $('<span class="w-tag">').addClass(c.status === 'running' ? 'w-tag--teal' : (c.status === 'paused' ? 'w-tag--gold' : '')).text(STATUS[c.status] || c.status),
                $('<span class="w-was__campmeta">').text(when(c.created) + (c.by ? ' · ' + c.by : '') + ' · every ' + c.interval + 's'));
            var $acts = $('<span class="w-was__campacts">').appendTo($top);
            if (c.status === 'running') { $acts.append(btn('pause', 'Pause')); }
            if (c.status === 'paused') { $acts.append(btn('resume', 'Resume', true)); }
            if (c.status === 'running' || c.status === 'paused') { $acts.append(btn('cancel', 'Stop')); }
            if ((n.failed + n.expired + n.cancelled) > 0 && c.status !== 'running') { $acts.append(btn('retry_failed', (n.failed + n.expired) > 0 ? 'Send the failed again' : 'Send the remaining')); }
            $acts.append(btn('log', openLogs[c.id] ? 'Hide list' : 'Who got it'));

            var problems = n.failed + n.expired;
            $c.find('.w-was__bar').attr({ 'aria-valuemax': c.total, 'aria-valuenow': c.done, 'aria-label': num(c.done) + ' of ' + num(c.total) }).empty().append(
                $('<span class="is-sent">').css('width', (n.sent / total * 100) + '%'),
                $('<span class="is-problem">').css('width', (problems / total * 100) + '%'),
                $('<span class="is-skipped">').css('width', (n.skipped / total * 100) + '%'),
                $('<span class="is-stopped">').css('width', (n.cancelled / total * 100) + '%'));
            var left = n.queued + n.pending + n.sending;
            var $nums = $c.find('.w-was__nums').empty();
            $nums.append($('<span>').append($('<b>').text(num(n.sent)), ' of ' + num(c.total) + ' sent'));
            if (n.delivered) { $nums.append($('<span>').append($('<b>').text(num(n.delivered)), ' delivered')); }
            if (n.read) { $nums.append($('<span>').append($('<b>').text(num(n.read)), ' read')); }
            if (problems) { $nums.append($('<span>').append($('<b>').text(num(problems)), ' failed')); }
            if (n.skipped) { $nums.append($('<span>').append($('<b>').text(num(n.skipped)), ' not on WhatsApp')); }
            if (n.cancelled) { $nums.append($('<span>').append($('<b>').text(num(n.cancelled)), ' stopped before sending')); }
            if (left && c.status === 'running') { $nums.append($('<span>').text(num(left) + ' left · about ' + c.eta_min + ' min')); }
            if (c.status === 'done' && c.finished) { $nums.append($('<span>').text('Finished ' + when(c.finished))); }
            $c.find('.w-was__reason').text(c.status === 'paused' ? c.reason : '').prop('hidden', !(c.status === 'paused' && c.reason));

            if (openLogs[c.id]) { loadLog(c.id, $c); } else { $c.find('.w-was__log').remove(); }
        });
        $list.children('[data-id]').each(function () { if (!keep[$(this).data('id')]) { $(this).remove(); } });
    }

    function btn(op, label, primary) {
        return $('<button type="button" class="btn btn-sm">').addClass(primary ? 'btn-primary' : 'btn-outline-secondary').attr('data-op', op).text(label);
    }

    $('#was-list').on('click', '[data-op]', function () {
        var $c = $(this).closest('.w-was__camp'), id = $c.data('id'), op = $(this).data('op');
        if (op === 'log') {
            if (openLogs[id]) { delete openLogs[id]; $c.find('.w-was__log').remove(); $(this).text('Who got it'); }
            else { openLogs[id] = { status: '', page: 1, q: '' }; $(this).text('Hide list'); loadLog(id, $c); }
            return;
        }
        var go = function () { post('sc_wabot_campaign_action', { id: id, op: op }).done(function () { load(false); }); };
        if (op === 'cancel') {
            Swal.fire({ icon: 'warning', title: 'Stop this campaign?', text: 'People not reached yet will not get the message. Already sent messages stay sent.', showCancelButton: true, confirmButtonText: 'Stop', confirmButtonColor: '#B42318' })
                .then(function (r) { if (r.isConfirmed) { go(); } });
        } else { go(); }
    });

    /* ---------------- per-person log ---------------- */

    function loadLog(id, $c) {
        var st = openLogs[id];
        var $log = $c.find('.w-was__log');
        if (!$log.length) {
            $log = $('<div class="w-was__log">').appendTo($c);
            var $bar = $('<div class="w-was__logbar">').appendTo($log);
            var $tabs = $('<div class="w-tabs" role="tablist">').appendTo($bar);
            [['', 'All'], ['sent', 'Sent'], ['read', 'Read'], ['problem', 'Not sent'], ['waiting', 'Waiting']].forEach(function (t) {
                $('<button type="button" class="w-tab" role="tab">').attr('data-filter', t[0]).attr('aria-selected', st.status === t[0] ? 'true' : 'false').text(t[1]).appendTo($tabs);
            });
            $('<input type="search" class="form-control" placeholder="Search name or phone">').appendTo($bar);
            $('<button type="button" class="btn btn-sm btn-primary" data-resend disabled>').text('Send selected again').appendTo($bar);
            $log.append('<div class="w-table-scroll"><table class="w-table w-was__logtable"><thead><tr><th class="w-table__check"><input type="checkbox" data-all aria-label="Select all"></th><th>Person</th><th>Status</th><th>Number used</th><th>When</th></tr></thead><tbody></tbody></table></div><div class="w-was__pager"></div>');
        }
        $log.find('[data-filter]').each(function () { $(this).attr('aria-selected', $(this).data('filter') === st.status ? 'true' : 'false'); });
        // A newer request while one is loading (filter, search, page) runs right after it.
        if ($log.data('busy')) { $log.data('again', true); return; }
        $log.data('busy', true);
        post('sc_wabot_campaign_log', { id: id, status: st.status, page: st.page, q: st.q }).done(function (r) {
            if (!r.success) { return; }
            var checked = {};
            $log.find('tbody input:checked').each(function () { checked[this.value] = true; });
            var $tb = $log.find('tbody').empty();
            if (!r.data.rows.length) { $tb.append($('<tr>').append($('<td colspan="5" class="w-sub">').text('Nobody here.'))); }
            r.data.rows.forEach(function (row) {
                var canResend = ['queued', 'pending', 'sending'].indexOf(row.status) === -1;
                var status = ROW[row.status] || row.status;
                if (row.status === 'sent' && row.delivery) { status += ' · ' + ({ server_ack: 'sent', delivered: 'delivered', read: 'read', played: 'read' }[row.delivery] || row.delivery); }
                $tb.append($('<tr>').append(
                    $('<td class="w-table__check">').append(canResend ? $('<input type="checkbox">').val(row.id).prop('checked', !!checked[row.id]).attr('aria-label', 'Select ' + (row.name || row.to)) : ''),
                    $('<td>').append($('<div dir="auto">').text(row.name || '—'), $('<div class="w-sub w-ltr">').text('+' + row.to)),
                    $('<td>').append($('<span class="w-tag">').addClass(row.status === 'sent' ? 'w-tag--teal' : (['failed', 'expired', 'cancelled'].indexOf(row.status) > -1 ? 'w-tag--red' : '')).text(status), row.error && row.status !== 'sent' ? $('<div class="w-sub">').text(row.error) : ''),
                    $('<td>').text(row.number || '—'),
                    $('<td class="w-nowrap">').text(row.status === 'queued' ? '' : when(row.at))
                ));
            });
            syncResend($log);
            var $pg = $log.find('.w-was__pager').empty();
            if (r.data.pages > 1) {
                $pg.append($('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="-1">').text('Previous').prop('disabled', r.data.page <= 1),
                    $('<span>').text('Page ' + r.data.page + ' of ' + r.data.pages + ' · ' + num(r.data.total) + ' people'),
                    $('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="1">').text('Next').prop('disabled', r.data.page >= r.data.pages));
            } else {
                $pg.text(num(r.data.total) + ' people');
            }
        }).always(function () {
            $log.data('busy', false);
            if ($log.data('again')) { $log.data('again', false); loadLog(id, $c); }
        });
    }

    function syncResend($log) { $log.find('[data-resend]').prop('disabled', !$log.find('tbody input:checked').length); }

    $('#was-list').on('click', '.w-was__log [data-filter]', function () {
        var $c = $(this).closest('.w-was__camp'), id = $c.data('id');
        openLogs[id].status = String($(this).data('filter')); openLogs[id].page = 1; loadLog(id, $c);
    }).on('input', '.w-was__log input[type=search]', function () {
        var $c = $(this).closest('.w-was__camp'), id = $c.data('id'), v = this.value;
        clearTimeout($c.data('qt'));
        $c.data('qt', setTimeout(function () { openLogs[id].q = v; openLogs[id].page = 1; loadLog(id, $c); }, 350));
    }).on('click', '.w-was__log [data-page]', function () {
        var $c = $(this).closest('.w-was__camp'), id = $c.data('id');
        openLogs[id].page += +$(this).data('page'); loadLog(id, $c);
    }).on('change', '.w-was__log tbody input, .w-was__log [data-all]', function () {
        var $log = $(this).closest('.w-was__log');
        if ($(this).is('[data-all]')) { $log.find('tbody input[type=checkbox]').prop('checked', this.checked); }
        syncResend($log);
    }).on('click', '.w-was__log [data-resend]', function () {
        var $c = $(this).closest('.w-was__camp'), id = $c.data('id');
        var rows = $c.find('.w-was__log tbody input:checked').map(function () { return +this.value; }).get();
        post('sc_wabot_campaign_resend', { id: id, rows: JSON.stringify(rows) }).done(function (r) {
            if (r.success) { Swal.fire({ icon: 'success', title: r.data.queued + ' queued again', timer: 1800, showConfirmButton: false }); load(true); }
        });
    });

    syncSource();
    load(true);
    <?php if (isset($_GET['source'])): ?>$('#was-new-toggle').trigger('click');<?php endif; ?>
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
