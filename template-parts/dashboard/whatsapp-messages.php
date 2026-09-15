<?php
/**
 * Automatic messages — what the site sends by itself on WhatsApp (and email when it's on): the ticket
 * after registering, reminders before an event, the exhibitor badge. Each has a switch and a template.
 * Handlers: inc/admin-dashboard/whatsapp-notify-dashboard.php; engine: inc/whatsapp/wabot-notify.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_form;
$load_wd_form = true;
$state = sc_notify_page_state();

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.whatsapp_messages', 'Automatic messages')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('whatsapp.auto_sub', 'What the site sends by itself on WhatsApp: the ticket with its QR code after registering, reminders before an event, and exhibitor badges.')); ?></p>
        </div>
    </div>

    <div class="w-wam">
        <div id="wam-warn"></div>

        <section class="w-section" aria-labelledby="wam-delivery-title">
            <div class="w-section__head">
                <h2 id="wam-delivery-title"><?php echo esc_html(sc_t('whatsapp.delivery', 'Delivery')); ?></h2>
            </div>
            <form id="wam-delivery" class="w-wam__form" novalidate>
                <label class="w-switch">
                    <input type="checkbox" name="email" value="1">
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('whatsapp.also_email', 'Also send by email')); ?></strong><span><?php echo esc_html(sc_t('whatsapp.also_email_sub', 'The same message goes to the email on file. Turn it on once the site’s email is working again.')); ?></span></span>
                </label>
                <div class="w-field w-wam__gap">
                    <label class="w-field__label" for="wam-interval"><?php echo esc_html(sc_t('whatsapp.reminder_gap', 'Gap between reminder messages')); ?></label>
                    <select class="form-control" id="wam-interval" name="interval">
                        <option value="30"><?php echo esc_html(sc_t('whatsapp.gap_30', '30 seconds — fast, only for numbers used daily')); ?></option>
                        <option value="45"><?php echo esc_html(sc_t('whatsapp.gap_45', '45 seconds')); ?></option>
                        <option value="60"><?php echo esc_html(sc_t('whatsapp.gap_60', '1 minute — recommended')); ?></option>
                        <option value="90"><?php echo esc_html(sc_t('whatsapp.gap_90', '1.5 minutes')); ?></option>
                        <option value="120"><?php echo esc_html(sc_t('whatsapp.gap_120', '2 minutes — safest for a new number')); ?></option>
                    </select>
                    <p class="w-field__help"><?php echo esc_html(sc_t('whatsapp.reminder_gap_help', 'Tickets and badges go right away, one at a time. Reminders go to many people, so they are sent slowly like bulk sending and show up on “Send on WhatsApp”.')); ?></p>
                </div>
                <div class="w-wam__actions">
                    <button type="submit" class="btn btn-primary"><?php echo esc_html(sc_t('general.save', 'Save')); ?></button>
                    <p class="w-wam__msg" role="alert" hidden></p>
                </div>
            </form>
        </section>

        <div id="wam-types" class="w-wam__types"></div>
    </div>

</div>
</div>

<style>
.w-wam { display: flex; flex-direction: column; gap: 16px; max-width: 1040px; }
.w-wam [hidden] { display: none !important; }
.w-wam__form { display: flex; flex-direction: column; gap: 14px; }
.w-wam__gap { max-width: 420px; }
.w-wam__types { display: flex; flex-direction: column; gap: 16px; }
.w-wam__head { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.w-wam__head h2 { margin: 0; }
.w-wam__head .w-switch { margin: 0 0 0 auto; }
[dir="rtl"] .w-wam__head .w-switch { margin: 0 auto 0 0; }
.w-wam__when { margin: 4px 0 0; color: var(--w-text-2); font-size: 13.5px; }
.w-wam__grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 18px; align-items: start; }
.w-wam__chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.w-wam__chips .w-chipbtn { font-family: var(--w-font-mono, monospace); font-size: 12px; }
.w-wam__row { display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center; }
.w-wam__row .w-switch { margin: 0; }
.w-wam__hour { max-width: 180px; }
.w-wam__actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.w-wam__msg { margin: 0; font-size: 13px; font-weight: 500; }
.w-wam__msg.is-error { color: var(--w-danger); }
.w-wam__msg.is-ok { color: var(--w-teal-text); }
.w-wam__phone { background: #EFE7DD; border-radius: var(--w-radius-md); padding: 14px; min-height: 120px; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.w-wam__phone small { align-self: stretch; color: #54656F; font-size: 12px; }
.w-wam__bubble { background: #D9FDD3; color: #111B21; border-radius: 8px 0 8px 8px; padding: 6px; max-width: 320px; width: 100%; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); }
.w-wam__bubble img { display: block; width: 100%; max-width: 100%; border-radius: 6px; background: #fff; }
.w-wam__bubble p { margin: 6px 4px 2px; white-space: pre-line; overflow-wrap: anywhere; font-size: 13.5px; line-height: 1.45; }
.w-wam__next { margin: 0; padding: 10px 12px; border-radius: var(--w-radius-md); background: var(--w-surface-2); font-size: 13px; }
.w-wam__warn { margin: 0; padding: 10px 14px; border-radius: var(--w-radius-md); background: var(--w-warning-soft); color: var(--w-warning); font-size: 13.5px; }
.w-wam__warn a { color: inherit; text-decoration: underline; }
@media (max-width: 991.98px) {
    .w-wam__grid { grid-template-columns: minmax(0, 1fr); }
    .w-wam__head .w-switch, [dir="rtl"] .w-wam__head .w-switch { margin: 0; width: 100%; }
}
</style>

<script>
jQuery(function ($) {
    'use strict';
    var state = <?php echo wp_json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var ajax = scDashboard.ajaxurl, nonce = scDashboard.nonce;
    var HOURS = [];
    for (var h = 5; h <= 20; h++) { HOURS.push(h); }

    function post(action, data) { return $.post(ajax, $.extend({ action: action, nonce: nonce }, data || {})); }
    function say($scope, text, ok) {
        $scope.find('.w-wam__msg').first().text(text || '').toggleClass('is-error', !ok).toggleClass('is-ok', !!ok).prop('hidden', !text);
    }
    function errorOf(xhr) { var r = xhr && xhr.responseJSON; return (r && r.data && r.data.message) || 'Could not reach the site. Try again.'; }
    function hourLabel(h) { var d = new Date(2000, 0, 1, h); return d.toLocaleTimeString([], { hour: 'numeric' }); }
    function num(n) { return Number(n || 0).toLocaleString(); }
    function duration(min) { return min >= 120 ? (Math.round(min / 6) / 10) + ' hours' : min + ' minutes'; }

    function renderWarn() {
        var $w = $('#wam-warn').empty();
        if (!state.numbers) {
            $w.append($('<p class="w-wam__warn">').append('No WhatsApp number is set up for these messages, so nothing goes out on WhatsApp. ', $('<a>').attr('href', state.wa_url).text('Set up a number')));
        }
    }

    function renderDelivery() {
        var $f = $('#wam-delivery');
        $f.find('input[name=email]').prop('checked', !!state.email);
        $('#wam-interval').val(String(state.interval));
    }

    function card(type, t) {
        var id = 'wam-' + type;
        var $s = $('<section class="w-section">').attr({ id: id, 'data-type': type, 'aria-labelledby': id + '-title' });
        var $head = $('<div class="w-wam__head">').appendTo($s);
        var $title = $('<div>').appendTo($head);
        $('<h2>').attr('id', id + '-title').text(t.label).appendTo($title);
        $('<p class="w-wam__when">').text(t.when).appendTo($title);
        if (t.manual) {
            $('<span class="w-tag">').text('Sent when you ask').appendTo($head);
        } else {
            var $sw = $('<label class="w-switch">').appendTo($head);
            $('<input type="checkbox" data-field="enabled">').prop('checked', !!t.enabled).appendTo($sw);
            $sw.append('<span class="w-switch__track" aria-hidden="true"></span>', $('<span class="w-switch__text">').append($('<strong>').text(t.enabled ? 'On' : 'Off')));
        }

        if (t.bulk && state.upcoming[type]) {
            var u = state.upcoming[type];
            var line = t.enabled
                ? (u.created ? 'Already started for ' + u.event + '. Follow it on “Send on WhatsApp”.' : 'Next: ' + u.event + ' — starts ' + u.starts + ' · ' + num(u.people) + ' people · about ' + duration(u.minutes) + '.')
                : 'Off. If on, it would go to ' + num(u.people) + ' people for ' + u.event + ' from ' + u.starts + ', taking about ' + duration(u.minutes) + '.';
            var $next = $('<p class="w-wam__next">').text(line).appendTo($s);
            if (u.created) { $next.append(' ', $('<a>').attr('href', state.send_url).text('Open')); }
        }

        var $grid = $('<div class="w-wam__grid">').appendTo($s);
        var $form = $('<form class="w-wam__form" novalidate>').appendTo($grid);
        var $f1 = $('<div class="w-field">').appendTo($form);
        $('<label class="w-field__label">').attr('for', id + '-tpl').text('Message').appendTo($f1);
        $('<textarea class="form-control" rows="11" dir="auto" maxlength="1000" data-field="template">').attr('id', id + '-tpl').val(t.template).appendTo($f1);
        var $chips = $('<div class="w-wam__chips">').appendTo($f1);
        t.tags.forEach(function (tag) { $('<button type="button" class="w-chipbtn">').attr({ 'data-insert': tag, title: state.tags[tag] || '' }).text(tag).appendTo($chips); });
        $('<p class="w-field__help">').text('A line whose tags are all empty (say, no venue) is left out.').appendTo($f1);

        var $f2 = $('<div class="w-field wam-subject">').prop('hidden', !state.email).appendTo($form);
        $('<label class="w-field__label">').attr('for', id + '-subj').text('Email subject').appendTo($f2);
        $('<input class="form-control" maxlength="190" dir="auto" data-field="subject">').attr('id', id + '-subj').val(t.subject).appendTo($f2);

        var $row = $('<div class="w-wam__row">').appendTo($form);
        if (t.attach_kind) {
            var $at = $('<label class="w-switch">').appendTo($row);
            $('<input type="checkbox" data-field="attach">').prop('checked', !!t.attach).appendTo($at);
            $at.append('<span class="w-switch__track" aria-hidden="true"></span>', $('<span class="w-switch__text">').append($('<strong>').text(t.attach_kind === 'company_qr' ? 'Attach the badge QR image' : 'Attach the ticket QR image')));
        }
        if (t.hour !== null && t.hour !== undefined) {
            var $hf = $('<div class="w-field w-wam__hour">').appendTo($row);
            $('<label class="w-field__label">').attr('for', id + '-hour').text('Start at').appendTo($hf);
            var $sel = $('<select class="form-control" data-field="hour">').attr('id', id + '-hour').appendTo($hf);
            HOURS.concat(HOURS.indexOf(+t.hour) === -1 ? [+t.hour] : []).sort(function (a, b) { return a - b; }).forEach(function (h) { $sel.append(new Option(hourLabel(h), h)); });
            $sel.val(String(t.hour));
        }

        var $acts = $('<div class="w-wam__actions">').appendTo($form);
        $('<button type="submit" class="btn btn-primary">').text('Save').appendTo($acts);
        $('<button type="button" class="btn btn-outline-secondary" data-act="test">').text('Send me a test').appendTo($acts);
        if (!t.is_default) { $('<button type="button" class="btn btn-link" data-act="restore">').text('Restore default').appendTo($acts); }
        $('<p class="w-wam__msg" role="alert" hidden>').appendTo($acts);

        var $prev = $('<div>').appendTo($grid);
        $('<div class="w-wam__phone" aria-live="polite">').append($('<small>').text('Preview')).appendTo($prev);
        return $s;
    }

    function renderTypes() {
        var $box = $('#wam-types').empty();
        Object.keys(state.types).forEach(function (type) {
            $box.append(card(type, state.types[type]));
            preview(type);
        });
    }

    function values($s) {
        var d = { type: $s.data('type') };
        $s.find('[data-field]').each(function () {
            var $in = $(this), f = $in.data('field');
            d[f] = $in.is(':checkbox') ? ($in.is(':checked') ? 1 : 0) : $in.val();
        });
        return d;
    }

    var timers = {};
    function preview(type) {
        var $s = $('#wam-' + type), d = values($s);
        post('sc_notify_preview', d).done(function (r) {
            var $ph = $s.find('.w-wam__phone').empty();
            if (!r.success) { $ph.append($('<small>').text(r.data.message)); return; }
            $ph.append($('<small>').text('Preview with ' + r.data.who + '’s details'));
            var $b = $('<div class="w-wam__bubble">').appendTo($ph);
            if (r.data.image) { $('<img alt="">').attr('src', r.data.image).appendTo($b); }
            $('<p dir="auto">').text(r.data.text).appendTo($b);
        });
    }

    $('#wam-types').on('input change', '[data-field]', function () {
        var $s = $(this).closest('section'), type = $s.data('type');
        say($s, '');
        if ($(this).data('field') === 'enabled') {
            var on = $(this).is(':checked');
            $(this).closest('label').find('strong').text(on ? 'On' : 'Off');
            post('sc_notify_save', { scope: 'type', type: type, enabled: on ? 1 : 0 }).done(function (r) {
                if (r.success) { state = r.data; renderTypesKeep(type, on ? 'Turned on.' : 'Turned off.'); }
                else { say($s, r.data.message, false); }
            }).fail(function (x) { say($s, errorOf(x), false); });
            return;
        }
        clearTimeout(timers[type]);
        timers[type] = setTimeout(function () { preview(type); }, 400);
    });

    // Re-render after a save but keep what is being typed in other cards.
    function renderTypesKeep(type, message) {
        var drafts = {};
        $('#wam-types section').each(function () {
            var t = $(this).data('type');
            if (t !== type) { drafts[t] = values($(this)); }
        });
        renderTypes();
        Object.keys(drafts).forEach(function (t) {
            var $s = $('#wam-' + t);
            $.each(drafts[t], function (f, v) {
                var $in = $s.find('[data-field="' + f + '"]');
                if ($in.is(':checkbox')) { $in.prop('checked', !!v); } else if ($in.length) { $in.val(v); }
            });
        });
        if (message) { say($('#wam-' + type), message, true); }
    }

    $('#wam-types').on('click', '[data-insert]', function () {
        var el = $(this).closest('.w-field').find('textarea')[0], tag = $(this).data('insert');
        var s = el.selectionStart || el.value.length, e = el.selectionEnd || el.value.length;
        el.value = el.value.slice(0, s) + tag + el.value.slice(e);
        el.focus(); el.selectionStart = el.selectionEnd = s + tag.length;
        $(el).trigger('input');
    });

    $('#wam-types').on('submit', 'form', function (e) {
        e.preventDefault();
        var $s = $(this).closest('section'), type = $s.data('type'), $b = $(this).find('[type=submit]').prop('disabled', true);
        var d = values($s);
        delete d.enabled;
        post('sc_notify_save', $.extend({ scope: 'type' }, d)).done(function (r) {
            if (!r.success) { say($s, r.data.message, false); return; }
            state = r.data; renderTypesKeep(type, 'Saved.');
        }).fail(function (x) { say($s, errorOf(x), false); }).always(function () { $b.prop('disabled', false); });
    });

    $('#wam-types').on('click', '[data-act]', function () {
        var $s = $(this).closest('section'), type = $s.data('type'), act = $(this).data('act');
        if (act === 'restore') {
            Swal.fire({ icon: 'question', title: 'Restore the default message?', text: 'Your changes to this message are replaced.', showCancelButton: true, confirmButtonText: 'Restore' }).then(function (res) {
                if (!res.isConfirmed) { return; }
                post('sc_notify_save', { scope: 'type', type: type, restore: 1 }).done(function (r) { if (r.success) { state = r.data; renderTypesKeep(type, 'Default restored.'); } });
            });
            return;
        }
        var d = values($s);
        Swal.fire({ title: 'Send a test', input: 'tel', inputLabel: 'Your WhatsApp number', inputPlaceholder: '01012345678', showCancelButton: true, confirmButtonText: 'Send',
            text: 'It is filled with a real registration’s details, as in the preview. The message is not saved.',
            preConfirm: function (phone) {
                return post('sc_notify_test', $.extend({}, d, { phone: phone })).then(function (r) {
                    if (!r.success) { Swal.showValidationMessage(r.data.message); return false; }
                    return r.data.message;
                }, function (x) { Swal.showValidationMessage(errorOf(x)); return false; });
            }
        }).then(function (res) { if (res.isConfirmed) { say($s, res.value, true); } });
    });

    $('#wam-delivery').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('[type=submit]').prop('disabled', true);
        post('sc_notify_save', { scope: 'delivery', email: $f.find('input[name=email]').is(':checked') ? 1 : 0, interval: $('#wam-interval').val() }).done(function (r) {
            if (!r.success) { say($f, r.data.message, false); return; }
            state = r.data;
            $('.wam-subject').prop('hidden', !state.email);
            renderTypesKeep('', '');
            say($f, 'Saved.', true);
        }).fail(function (x) { say($f, errorOf(x), false); }).always(function () { $b.prop('disabled', false); });
    });

    renderWarn();
    renderDelivery();
    renderTypes();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
