<?php
/**
 * Certificate repair — administrators only.
 *
 * Diagnose one issued certificate, or re-point every certificate of an event to
 * the right template and refresh their copied fields (attendee name/email,
 * event title/date). Certificate numbers and verification codes never change.
 * Handlers: sc_diagnose_certificate, sc_rebuild_event_summary,
 * sc_rebuild_event_certificates (certificates-ajax-handlers.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// A bulk tool that rewrites issued certificates: administrators only.
if (!current_user_can('manage_options')) {
    wp_die(__('Only administrators can use the certificate rebuild tool.', 'sc_events'));
}

global $wpdb, $load_wd_form;
$load_wd_form = true;

$events = $wpdb->get_results(
    "SELECT id, title, start_date FROM {$wpdb->prefix}sc_events
     WHERE status IN ('publish', 'completed')
     ORDER BY start_date DESC, id DESC"
);
$templates = SC_Certificate_Template::get_all(array('orderby' => 'name', 'order' => 'ASC'));
$dashboard_url = home_url('/event-manager-dashboard/');

$page_title = sc_t('dashboard_pages.rebuild_certificates', 'Repair certificates');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($page_title); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('cert_rebuild.sub', 'For certificates that show the wrong event design or out-of-date names. Certificate numbers and verification codes never change.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-outline-secondary" href="<?php echo esc_url($dashboard_url . 'certificates'); ?>"><?php echo esc_html(sc_t('cert_rebuild.back', 'Issued certificates')); ?></a>
        </div>
    </div>

    <div class="w-rebuild">
        <p class="w-rebuild__warn" role="note">
            <strong><?php echo esc_html(sc_t('cert_rebuild.warn_title', 'Administrators only.')); ?></strong>
            <?php echo esc_html(sc_t('cert_rebuild.warn', 'Rebuilding rewrites every certificate of the chosen event at once, and people see the change the next time they download. Check the summary first.')); ?>
        </p>

        <section class="w-section" aria-labelledby="rb-diagnose">
            <div class="w-section__head">
                <h2 id="rb-diagnose"><?php echo esc_html(sc_t('cert_rebuild.diagnose', 'Check one certificate')); ?></h2>
                <span class="w-section__hint"><?php echo esc_html(sc_t('cert_rebuild.diagnose_hint', 'Use the number after ?id= in a certificate download link.')); ?></span>
            </div>
            <form class="w-rebuild__row" id="diagnose-form" novalidate>
                <div class="w-field">
                    <label class="w-field__label" for="diagnose-cert-id"><?php echo esc_html(sc_t('cert_rebuild.cert_id', 'Certificate ID')); ?></label>
                    <input type="number" min="1" inputmode="numeric" class="form-control" id="diagnose-cert-id" placeholder="3027">
                </div>
                <button type="submit" class="btn btn-primary" id="diagnose-btn"><?php echo esc_html(sc_t('cert_rebuild.check', 'Check')); ?></button>
            </form>
            <div id="diagnose-result" class="w-rebuild__out" aria-live="polite" hidden></div>
        </section>

        <section class="w-section" aria-labelledby="rb-bulk">
            <div class="w-section__head">
                <h2 id="rb-bulk"><?php echo esc_html(sc_t('cert_rebuild.bulk', 'Rebuild an event\'s certificates')); ?></h2>
            </div>

            <div class="w-rebuild__row">
                <div class="w-field">
                    <label class="w-field__label" for="rebuild-event"><?php echo esc_html(sc_t('cert_rebuild.step1', '1. Event')); ?></label>
                    <select class="form-control" id="rebuild-event">
                        <option value=""><?php echo esc_html(sc_t('cert_rebuild.pick_event', 'Choose an event')); ?></option>
                        <?php foreach ($events as $ev): ?>
                            <option value="<?php echo esc_attr($ev->id); ?>"><?php echo esc_html($ev->title . (!empty($ev->start_date) ? ' · ' . mysql2date('j M Y', $ev->start_date) : '') . ' (#' . $ev->id . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="summary-block" class="w-rebuild__summary" hidden>
                <div id="summary-content" aria-live="polite"></div>

                <div class="w-rebuild__row">
                    <div class="w-field">
                        <label class="w-field__label" for="rebuild-template"><?php echo esc_html(sc_t('cert_rebuild.step2', '2. Template these certificates should use')); ?></label>
                        <select class="form-control" id="rebuild-template">
                            <option value=""><?php echo esc_html(sc_t('cert_rebuild.keep_template', 'Keep each certificate\'s template — only refresh names, title and date')); ?></option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?php echo esc_attr($tpl->id); ?>"><?php echo esc_html($tpl->name . ' (#' . $tpl->id . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-danger" id="rebuild-btn"><?php echo esc_html(sc_t('cert_rebuild.rebuild', 'Rebuild certificates')); ?></button>
                </div>
                <div id="rebuild-result" aria-live="polite"></div>
            </div>
        </section>
    </div>

</div>
</div>

<style>
.w-rebuild { display: flex; flex-direction: column; gap: 16px; max-width: 960px; }
.w-rebuild__warn { margin: 0; padding: 12px 16px; border-radius: var(--w-radius-md); background: var(--w-warning-soft); color: var(--w-warning); font-size: 13.5px; }
.w-rebuild__row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
.w-rebuild__row .w-field { flex: 1 1 320px; }
.w-rebuild__out, .w-rebuild__summary { display: flex; flex-direction: column; gap: 14px; margin-top: 16px; }
.w-rebuild__out[hidden], .w-rebuild__summary[hidden] { display: none; }
.w-rebuild__facts { display: grid; grid-template-columns: minmax(120px, max-content) 1fr; gap: 8px 20px; margin: 0; }
.w-rebuild__facts dt { color: var(--w-text-3); font-size: 13px; font-weight: 500; }
.w-rebuild__facts dd { margin: 0; overflow-wrap: anywhere; }
.w-rebuild__msg { margin: 0; padding: 10px 14px; border-radius: var(--w-radius-md); font-size: 13.5px; }
.w-rebuild__msg--ok { background: var(--w-teal-soft); color: var(--w-teal-text); }
.w-rebuild__msg--warn { background: var(--w-warning-soft); color: var(--w-warning); }
.w-rebuild__msg--error { background: var(--w-danger-soft); color: var(--w-danger); }
@media (max-width: 575.98px) { .w-rebuild__facts { grid-template-columns: 1fr; gap: 2px; } .w-rebuild__facts dd { margin-bottom: 8px; } .w-rebuild__row .btn { width: 100%; } }
</style>

<script>
jQuery(function ($) {
    'use strict';
    var ajaxurl = scDashboard.ajaxurl;
    var nonce = scDashboard.nonce;

    function msg(tone, text) {
        return $('<p class="w-rebuild__msg">').addClass('w-rebuild__msg--' + tone).text(text);
    }

    function facts(rows) {
        var $dl = $('<dl class="w-rebuild__facts">');
        rows.forEach(function (r) {
            if (!r) { return; }
            $dl.append($('<dt>').text(r[0]), $('<dd dir="auto">').append(r[1]));
        });
        return $dl;
    }

    function ref(id, title) {
        return (title || '—') + (id ? ' (#' + id + ')' : '');
    }

    function busy($btn, on, label) {
        if (on) { $btn.data('label', $btn.text()).prop('disabled', true).text(label); }
        else { $btn.prop('disabled', false).text($btn.data('label')); }
    }

    // ---- Check one certificate ----
    $('#diagnose-form').on('submit', function (e) {
        e.preventDefault();
        var id = parseInt($('#diagnose-cert-id').val(), 10);
        var $out = $('#diagnose-result').prop('hidden', false).empty();
        if (!id) { $out.append(msg('error', 'Enter a certificate ID.')); $('#diagnose-cert-id').trigger('focus'); return; }
        var $btn = $('#diagnose-btn');
        busy($btn, true, 'Checking…');
        $.post(ajaxurl, { action: 'sc_diagnose_certificate', nonce: nonce, cert_id: id }).done(function (res) {
            if (!res || !res.success) { $out.append(msg('error', (res && res.data && res.data.message) || 'Could not check this certificate.')); return; }
            var d = res.data, c = d.cert || {};
            $out.append(
                d.template_event_mismatch
                    ? msg('warn', 'The template belongs to a different event. Rebuild this event\'s certificates with the right template below.')
                    : msg('ok', 'The template matches the event.'),
                facts([
                    ['Certificate', ref(c.id, c.certificate_number)],
                    ['Event', ref(c.event_id, d.event_title) + (d.event_start ? ' · starts ' + d.event_start : '')],
                    ['Template', ref(c.template_id, d.template_name)],
                    d.template_event_id ? ['Template made for', ref(d.template_event_id, d.template_event_title)] : null,
                    ['Attendee', ref(c.attendee_id, c.attendee_name) + (d.attendee_email ? ' · ' + d.attendee_email : '')],
                    ['Issued', c.issued_at || '—'],
                    ['Status', c.status || '—']
                ])
            );
            if (c.event_id && $('#rebuild-event option[value="' + c.event_id + '"]').length) {
                $out.append($('<button type="button" class="btn btn-outline-secondary btn-sm align-self-start">').text('Open this event below').on('click', function () {
                    $('#rebuild-event').val(String(c.event_id)).trigger('change');
                    document.getElementById('rb-bulk').scrollIntoView({ behavior: 'smooth' });
                }));
            }
        }).fail(function (xhr) {
            $out.append(msg('error', 'Request failed (' + xhr.status + ').'));
        }).always(function () { busy($btn, false); });
    });

    // ---- Summary for an event ----
    var summary = null;
    $('#rebuild-event').on('change', function (e, keepResult) {
        var id = parseInt($(this).val(), 10);
        summary = null;
        if (!keepResult) { $('#rebuild-result').empty(); }
        $('#summary-block').prop('hidden', !id);
        if (!id) { return; }
        var $c = $('#summary-content').empty().append($('<p class="w-sub mb-0">').text('Loading…'));
        $.post(ajaxurl, { action: 'sc_rebuild_event_summary', nonce: nonce, event_id: id }).done(function (res) {
            $c.empty();
            if (!res || !res.success) { $c.append(msg('error', (res && res.data && res.data.message) || 'Could not load this event.')); return; }
            summary = res.data;
            if (!summary.total) {
                $c.append(msg('warn', 'This event has no issued certificates.'));
                $('#rebuild-btn').prop('disabled', true);
                return;
            }
            $('#rebuild-btn').prop('disabled', false);
            var $tbody = $('<tbody>');
            summary.templates.forEach(function (t) {
                $('<tr>').append(
                    $('<td dir="auto">').text(t.template_name ? ref(t.template_id, t.template_name) : 'Deleted template (#' + t.template_id + ')'),
                    $('<td class="w-num">').text(Number(t.cert_count).toLocaleString())
                ).appendTo($tbody);
            });
            $c.append(
                $('<p class="mb-0">').text(Number(summary.total).toLocaleString() + ' certificates issued, using these templates:'),
                $('<div class="w-table-scroll">').append($('<table class="w-table">').append('<thead><tr><th scope="col">Template</th><th scope="col" class="w-num">Certificates</th></tr></thead>', $tbody)),
                summary.templates.length > 1 ? msg('warn', 'Certificates of this event use more than one template. Pick the right one in step 2 to make them all the same.') : ''
            );
        }).fail(function (xhr) {
            $c.empty().append(msg('error', 'Request failed (' + xhr.status + ').'));
        });
    });

    // ---- Rebuild ----
    $('#rebuild-btn').on('click', function () {
        if (!summary) { return; }
        var templateId = parseInt($('#rebuild-template').val(), 10) || 0;
        var templateName = templateId ? $('#rebuild-template option:selected').text() : '';
        Swal.fire({
            icon: 'warning',
            title: 'Rebuild ' + Number(summary.total).toLocaleString() + ' certificates?',
            text: templateId
                ? 'Every certificate of "' + summary.event_title + '" will use "' + templateName + '", with names, title and date refreshed.'
                : 'Names, event title and date on every certificate of "' + summary.event_title + '" will be refreshed. Templates stay as they are.',
            showCancelButton: true,
            confirmButtonText: 'Rebuild',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B42318',
            focusCancel: true
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            var $btn = $('#rebuild-btn');
            var $out = $('#rebuild-result').empty().append(msg('warn', 'Working… events with many certificates can take a minute. Keep this page open.'));
            busy($btn, true, 'Rebuilding…');
            $.post(ajaxurl, { action: 'sc_rebuild_event_certificates', nonce: nonce, event_id: summary.event_id, template_id: templateId }).done(function (res) {
                $out.empty();
                if (!res || !res.success) { $out.append(msg('error', (res && res.data && res.data.message) || 'Rebuild failed.')); return; }
                var d = res.data;
                $out.append(msg('ok', 'Done. ' + Number(d.updated).toLocaleString() + ' certificates updated, ' + Number(d.cache_cleared).toLocaleString() + ' cached files cleared' + (d.skipped > 0 ? ', ' + d.skipped + ' skipped because their attendee or event no longer exists' : '') + '.'));
                $('#rebuild-event').trigger('change', [true]);
            }).fail(function (xhr) {
                $out.empty().append(msg('error', 'Request failed (' + xhr.status + '). Some certificates may already be updated; load the summary again before retrying.'));
            }).always(function () { busy($btn, false); });
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
