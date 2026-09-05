<?php
/**
 * Workshop Scanner Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
if (!$workshop_id) {
    wp_redirect(home_url('/event-manager-dashboard/workshops'));
    exit;
}

$workshop = SC_Workshop::get($workshop_id);
if (!$workshop) {
    wp_die('Workshop not found.');
}

$page_title = 'Scanner: ' . esc_html($workshop->title);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-8">
                <h2><?php echo $page_title; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/workshops'); ?>">Workshops</a></li>
                    <li class="breadcrumb-item active">Scanner</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="header"><h2><i class="fa fa-qrcode"></i> QR Scanner</h2></div>
                <div class="body">
                    <div id="qr-reader" style="width:100%;max-width:500px;margin:0 auto;border:2px solid #ddd;border-radius:8px;overflow:hidden;"></div>
                    <div class="text-center mt-3">
                        <button class="btn btn-primary" id="start-scan"><i class="fa fa-play"></i> Start Scanner</button>
                        <button class="btn btn-secondary" id="stop-scan" style="display:none;"><i class="fa fa-stop"></i> Stop</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="header"><h2><i class="fa fa-keyboard"></i> Manual Entry</h2></div>
                <div class="body">
                    <div class="input-group">
                        <input type="text" id="manual-ticket-code" class="form-control" placeholder="Enter ticket code...">
                        <div class="input-group-append">
                            <button class="btn btn-primary" id="manual-scan-btn"><i class="fa fa-check"></i> Check In</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="header"><h2><i class="fa fa-info-circle"></i> Result</h2></div>
                <div class="body" id="scan-result">
                    <p class="text-muted">Scan a QR code or enter a ticket code...</p>
                </div>
            </div>

            <div class="card">
                <div class="header"><h2><i class="fa fa-history"></i> Recent Scans</h2></div>
                <div class="body">
                    <ul id="recent-scans" class="list-group list-group-flush">
                        <li class="list-group-item text-muted">No recent scans</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
jQuery(document).ready(function($) {
    'use strict';

    const workshopId = <?php echo (int) $workshop_id; ?>;
    let html5QrCode = null;
    let scanning = false;
    let lastScannedCode = '';
    let lastScanTime = 0;

    function escapeHtml(t) {
        if (!t) return '';
        const m = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(t).replace(/[&<>"']/g, c => m[c]);
    }

    function extractTicketCode(text) {
        // Try parsing JSON QR (workshop format: {"type":"attendee","code":"...","event":1,"workshop":2})
        try {
            const data = JSON.parse(text);
            if (data && data.code) return data.code;
        } catch(e) {}
        // Try URL parameters
        const m = text.match(/ticket_code=([^&\s]+)/i);
        if (m) return decodeURIComponent(m[1]);
        // Otherwise treat as raw code
        return text.trim();
    }

    function showResult(html, type) {
        const cls = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-info');
        $('#scan-result').html('<div class="alert ' + cls + ' mb-0">' + html + '</div>');
    }

    function addRecentScan(html, type) {
        const $list = $('#recent-scans');
        if ($list.find('.text-muted').length) $list.empty();
        const timestamp = new Date().toLocaleTimeString();
        const itemCls = type === 'success' ? 'list-group-item-success' : 'list-group-item-danger';
        $list.prepend('<li class="list-group-item ' + itemCls + '"><small class="text-muted float-right">' + timestamp + '</small>' + html + '</li>');
        if ($list.children().length > 20) $list.children().last().remove();
    }

    function processCode(code) {
        const ticketCode = extractTicketCode(code);
        if (!ticketCode) {
            showResult('<i class="fa fa-times"></i> Invalid QR code', 'error');
            return;
        }

        // Debounce: avoid double-scan within 2s
        const now = Date.now();
        if (ticketCode === lastScannedCode && (now - lastScanTime) < 2000) return;
        lastScannedCode = ticketCode;
        lastScanTime = now;

        showResult('<i class="fa fa-spinner fa-spin"></i> Validating ticket...', 'info');

        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: {
                action: 'sc_scan_and_checkin',
                nonce: scDashboard.nonce,
                ticket_id: ticketCode,
                workshop_id: workshopId
            }
        }).done(function(resp) {
            if (resp.success) {
                const d = resp.data;
                const att = d.attendee || {};
                const action = d.action_type === 'check_out' ? 'CHECKED OUT' : 'CHECKED IN';
                const html = '<h4><i class="fa fa-check-circle"></i> ' + action + '</h4>' +
                    '<strong>' + escapeHtml(att.name || '') + '</strong><br>' +
                    'Email: ' + escapeHtml(att.email || '-') + '<br>' +
                    'Phone: ' + escapeHtml(att.phone || '-') + '<br>' +
                    'Ticket: ' + escapeHtml(att.ticket_type || '-');
                showResult(html, 'success');
                addRecentScan('<i class="fa fa-check"></i> ' + escapeHtml(att.name || '?') + ' — ' + action, 'success');
                if (typeof beep === 'function') beep();
                else if ('vibrate' in navigator) navigator.vibrate(150);
            } else {
                const msg = resp.data.message || 'Validation failed';
                showResult('<i class="fa fa-times"></i> ' + escapeHtml(msg), 'error');
                addRecentScan('<i class="fa fa-times"></i> ' + escapeHtml(ticketCode) + ' — ' + escapeHtml(msg), 'error');
                if ('vibrate' in navigator) navigator.vibrate([50, 100, 50]);
            }
        }).fail(function() {
            showResult('<i class="fa fa-times"></i> Connection error', 'error');
        });
    }

    $('#start-scan').on('click', function() {
        if (scanning) return;
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("qr-reader");
        }
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            function(decodedText) { processCode(decodedText); }
        ).then(function() {
            scanning = true;
            $('#start-scan').hide();
            $('#stop-scan').show();
        }).catch(function(err) {
            alert('Camera access failed: ' + err);
        });
    });

    $('#stop-scan').on('click', function() {
        if (html5QrCode && scanning) {
            html5QrCode.stop().then(function() {
                scanning = false;
                $('#start-scan').show();
                $('#stop-scan').hide();
            });
        }
    });

    $('#manual-scan-btn').on('click', function() {
        const code = $('#manual-ticket-code').val().trim();
        if (code) {
            processCode(code);
            $('#manual-ticket-code').val('').focus();
        }
    });

    $('#manual-ticket-code').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#manual-scan-btn').click();
        }
    });
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
