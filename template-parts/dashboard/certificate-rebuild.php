<?php
/**
 * Dashboard – Certificate Rebuild Tool
 *
 * Bulk-fix issued certificates whose template assignment is wrong, or whose
 * denormalized fields (attendee_name, event_title, event_date) drifted from the
 * source rows. Used when certs end up rendering with the wrong event branding
 * because they were issued against a template that belongs to a different event.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Only administrators (not event managers) — this is a destructive bulk tool.
if (!current_user_can('manage_options')) {
    wp_die(__('Only administrators can use the certificate rebuild tool.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.rebuild_certificates', 'Rebuild Certificates');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

global $wpdb;
$events = $wpdb->get_results(
    "SELECT id, title, start_date FROM {$wpdb->prefix}sc_events
     WHERE status IN ('publish', 'completed')
     ORDER BY start_date DESC, id DESC"
);

$templates = SC_Certificate_Template::get_all(array('orderby' => 'name', 'order' => 'ASC'));
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-8 col-md-8 col-sm-12">
                    <h2>Rebuild Certificates</h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item">Certificates</li>
                        <li class="breadcrumb-item active">Rebuild</li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/certificates'); ?>" class="btn btn-secondary mb-1">
                            <i class="fa fa-arrow-left"></i> Back to Certificates
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warning banner -->
        <div class="alert alert-warning">
            <strong><i class="fa fa-exclamation-triangle"></i> أداة خطرة — للأدمن فقط</strong>
            <p class="mb-0 mt-1">
                هذه الأداة تعيد بناء بيانات الشهادات المُصدرة بشكل جماعي. استخدمها فقط عندما تكون الشهادات مرتبطة بقالب خاطئ أو
                عندما تظهر بيانات قديمة. عملية إعادة البناء <strong>لا تُغيّر</strong> رقم الشهادة أو كود التحقق.
            </p>
        </div>

        <!-- Lookup section: diagnose a single cert by ID -->
        <div class="card">
            <div class="header">
                <h2><i class="fa fa-search"></i> Diagnose Single Certificate</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">
                    Paste a cert ID (e.g. from the broken download URL <code>?id=3027</code>) to see what event/template it's tied to.
                </p>
            </div>
            <div class="body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label>Certificate ID</label>
                        <input type="number" class="form-control" id="diagnose-cert-id" placeholder="e.g. 3027">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-info btn-block" id="diagnose-btn">
                            <i class="fa fa-stethoscope"></i> Diagnose
                        </button>
                    </div>
                </div>
                <div id="diagnose-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>

        <!-- Bulk rebuild section -->
        <div class="card">
            <div class="header">
                <h2><i class="fa fa-wrench"></i> Bulk Rebuild by Event</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">
                    Pick an event, see what's currently assigned, and (optionally) reassign all of its certificates to the correct template.
                </p>
            </div>
            <div class="body">
                <!-- Step 1: pick event -->
                <div class="row align-items-end mb-3">
                    <div class="col-md-6">
                        <label><strong>Step 1.</strong> Select Event</label>
                        <select class="form-control" id="rebuild-event">
                            <option value="">— Select event —</option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>">
                                    [#<?php echo (int) $ev->id; ?>]
                                    <?php echo esc_html($ev->title); ?>
                                    <?php if (!empty($ev->start_date)): ?>
                                        (<?php echo esc_html(date('Y-m-d', strtotime($ev->start_date))); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-info btn-block" id="load-summary-btn" disabled>
                            <i class="fa fa-search"></i> Load Summary
                        </button>
                    </div>
                </div>

                <!-- Step 2: summary + target template -->
                <div id="summary-block" style="display:none;">
                    <hr>
                    <div id="summary-content"></div>

                    <div class="row align-items-end mt-3">
                        <div class="col-md-6">
                            <label><strong>Step 2.</strong> Target Template (the correct one for this event)</label>
                            <select class="form-control" id="rebuild-template">
                                <option value="">— Keep current template (only refresh data) —</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo (int) $tpl->id; ?>">
                                        [#<?php echo (int) $tpl->id; ?>]
                                        <?php echo esc_html($tpl->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-warning btn-block" id="rebuild-btn">
                                <i class="fa fa-wrench"></i> Rebuild All
                            </button>
                        </div>
                    </div>

                    <div id="rebuild-result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var nonce = scDashboard.nonce;
    var ajaxurl = scDashboard.ajaxurl;

    // ===== Diagnose single cert =====
    $('#diagnose-btn').on('click', function() {
        var certId = parseInt($('#diagnose-cert-id').val(), 10);
        if (!certId) {
            alert('Please enter a certificate ID');
            return;
        }
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        var $out = $('#diagnose-result').show().html('<div class="text-center text-muted">Loading…</div>');

        $.post(ajaxurl, {
            action: 'sc_diagnose_certificate',
            nonce: nonce,
            cert_id: certId
        }).done(function(res) {
            if (!res.success) {
                $out.html('<div class="alert alert-danger">' + (res.data && res.data.message ? res.data.message : 'Error') + '</div>');
                return;
            }
            var d = res.data;
            var mismatchBadge = d.template_event_mismatch
                ? '<span class="badge badge-danger ml-2"><i class="fa fa-exclamation-triangle"></i> Template belongs to a different event!</span>'
                : '<span class="badge badge-success ml-2"><i class="fa fa-check"></i> Template matches event</span>';
            var html = '<div class="alert alert-info">'
                + '<h5 class="mb-2">Certificate #' + d.cert.id + ' (<code>' + (d.cert.certificate_number || '—') + '</code>)</h5>'
                + '<table class="table table-sm mb-0"><tbody>'
                + '<tr><th style="width:200px">Cert Event</th><td>[#' + d.cert.event_id + '] ' + escapeHtml(d.event_title) + ' <small class="text-muted">(starts ' + (d.event_start || '—') + ')</small></td></tr>'
                + '<tr><th>Cert Template</th><td>[#' + d.cert.template_id + '] ' + escapeHtml(d.template_name || '—') + ' ' + mismatchBadge + '</td></tr>'
                + (d.template_event_id
                    ? '<tr><th>Template\'s Native Event</th><td>[#' + d.template_event_id + '] ' + escapeHtml(d.template_event_title || '—') + '</td></tr>'
                    : '')
                + '<tr><th>Attendee</th><td>[#' + d.cert.attendee_id + '] ' + escapeHtml(d.cert.attendee_name || '') + ' &lt;' + escapeHtml(d.attendee_email || '') + '&gt;</td></tr>'
                + '<tr><th>Issued At</th><td>' + (d.cert.issued_at || '—') + '</td></tr>'
                + '<tr><th>Status</th><td>' + (d.cert.status || '—') + '</td></tr>'
                + '</tbody></table>'
                + '</div>';
            $out.html(html);
        }).fail(function(xhr) {
            $out.html('<div class="alert alert-danger">Request failed: ' + xhr.status + '</div>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-stethoscope"></i> Diagnose');
        });
    });

    // ===== Bulk rebuild =====
    $('#rebuild-event').on('change', function() {
        $('#load-summary-btn').prop('disabled', !$(this).val());
        $('#summary-block').hide();
    });

    $('#load-summary-btn').on('click', function() {
        var eventId = parseInt($('#rebuild-event').val(), 10);
        if (!eventId) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

        $.post(ajaxurl, {
            action: 'sc_rebuild_event_summary',
            nonce: nonce,
            event_id: eventId
        }).done(function(res) {
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : 'Error');
                return;
            }
            renderSummary(res.data);
            $('#summary-block').show();
            $('#rebuild-result').empty();
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Load Summary');
        });
    });

    function renderSummary(d) {
        var rows = '';
        d.templates.forEach(function(t) {
            rows += '<tr>'
                + '<td>[#' + t.template_id + '] ' + escapeHtml(t.template_name || '<em>(template deleted)</em>') + '</td>'
                + '<td><strong>' + t.cert_count + '</strong></td>'
                + '</tr>';
        });
        var hint = d.templates.length > 1
            ? '<div class="alert alert-warning mt-2 mb-0" style="font-size:13px;"><i class="fa fa-exclamation-triangle"></i> هذا الإيفنت لديه شهادات مرتبطة بأكثر من قالب. اختر القالب الصحيح في الخطوة 2 ليتم توحيدها.</div>'
                : '';
        var html = '<h5>Event: <code>[#' + d.event_id + ']</code> ' + escapeHtml(d.event_title) + '</h5>'
            + '<p class="text-muted">Total certificates: <strong>' + d.total + '</strong></p>'
            + '<div class="table-responsive"><table class="table table-bordered table-sm">'
            + '<thead class="thead-light"><tr><th>Template currently used</th><th>Cert count</th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table></div>'
            + hint;
        $('#summary-content').html(html);
    }

    $('#rebuild-btn').on('click', function() {
        var eventId = parseInt($('#rebuild-event').val(), 10);
        var templateId = parseInt($('#rebuild-template').val(), 10) || 0;

        var msg = templateId
            ? 'سيتم إعادة بناء كل شهادات هذا الإيفنت وربطها بالقالب المختار.\nمتابعة؟'
            : 'سيتم تحديث البيانات (اسم الحضور / عنوان الإيفنت / التاريخ) فقط دون تغيير القالب.\nمتابعة؟';
        if (!confirm(msg)) return;

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Rebuilding...');
        var $out = $('#rebuild-result').html('<div class="alert alert-info">Working… (this may take a minute for events with many attendees)</div>');

        $.post(ajaxurl, {
            action: 'sc_rebuild_event_certificates',
            nonce: nonce,
            event_id: eventId,
            template_id: templateId
        }).done(function(res) {
            if (!res.success) {
                $out.html('<div class="alert alert-danger">' + (res.data && res.data.message ? res.data.message : 'Error') + '</div>');
                return;
            }
            var d = res.data;
            $out.html('<div class="alert alert-success">'
                + '<strong><i class="fa fa-check"></i> Done</strong><br>'
                + 'Certificates updated: <strong>' + d.updated + '</strong><br>'
                + 'Cache files cleared: <strong>' + d.cache_cleared + '</strong><br>'
                + (d.skipped > 0 ? 'Skipped (no attendee/event found): <strong>' + d.skipped + '</strong><br>' : '')
                + '</div>');
        }).fail(function(xhr) {
            $out.html('<div class="alert alert-danger">Request failed: ' + xhr.status + '</div>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-wrench"></i> Rebuild All');
        });
    });

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
