<?php
/**
 * Template Name: Ticket View
 * Custom Ticket Display Page - Print & Download Ready
 *
 * @package sc_events
 * @version 1.0.0
 */

// Get attendee ID from URL parameter
$attendee_id = isset($_GET['attendee_id']) ? intval($_GET['attendee_id']) : 0;
// Support both 'ticket_code' (new) and 'code' (old) parameters for backwards compatibility
$ticket_code = isset($_GET['ticket_code']) ? sanitize_text_field($_GET['ticket_code']) : '';
if (empty($ticket_code) && isset($_GET['code'])) {
    $ticket_code = sanitize_text_field($_GET['code']);
}

if (!$attendee_id) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Get attendee from Custom Tables
$attendee = null;
if (class_exists('SC_Attendee')) {
    $attendee = SC_Attendee::get($attendee_id);
}

if (!$attendee) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Verify access - must be admin, owner, or have valid ticket code
$user_id = get_current_user_id();
$is_admin = current_user_can('administrator');
$is_owner = ($user_id && $attendee->user_id && $user_id == $attendee->user_id);
$valid_token = ($ticket_code && $attendee->ticket_code && $ticket_code === $attendee->ticket_code);

if (!$is_admin && !$is_owner && !$valid_token) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Attendee data from Custom Tables
$name = $attendee->name;
$email = $attendee->email;
$phone = $attendee->phone;
$ticket_id = $attendee->ticket_code;
$ticket_name = $attendee->ticket_name;
$ticket_price = $attendee->amount_paid;
$status = $attendee->payment_status;
$payment_type = $attendee->payment_method;
$coupon_used = $attendee->coupon_code;
$check_in_time = $attendee->checked_in_at;
$created_time = $attendee->created_at;

// Get event details from Custom Tables
$event = null;
$event_title = '';
$event_location = '';
$event_start = '';
$event_end = '';
$event_start_time = '';
$event_end_time = '';

if (class_exists('SC_Event')) {
    $event = SC_Event::get($attendee->event_id);
    if ($event) {
        $event_title = $event->title;
        $event_location = $event->venue_name;
        $event_start = $event->start_date;
        $event_end = $event->end_date;
        $event_start_time = $event->start_time ? date('H:i', strtotime($event->start_time)) : '';
        $event_end_time = $event->end_time ? date('H:i', strtotime($event->end_time)) : '';
    }
}

// Session registrations for this attendee
$session_registrations = array();
if (class_exists('SC_Session_Attendance') && $attendee) {
    $session_registrations = SC_Session_Attendance::get_attendee_summary($attendee->id, $attendee->event_id);
}

// Platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'medium') : '';

// Format dates
$start_date_formatted = $event_start ? date('d M Y', strtotime($event_start)) : '';
$end_date_formatted = $event_end ? date('d M Y', strtotime($event_end)) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($event_title); ?> - Ticket</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme Variables & Dynamic Colors -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/variables.css">
    <?php require_once get_template_directory() . '/inc/public-frontend/frontend-dynamic-colors.php'; ?>
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/light-theme.css">

    <!-- Ticket View Page Styles -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/ticket-view-page.css">

    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--sc-bg-primary, #0a0a0f); min-height: 100vh; padding: 40px 20px; margin: 0; }
        @keyframes sc-orb-float-1 { 0%,100% { transform: translate(0,0); } 33% { transform: translate(80px,50px); } 66% { transform: translate(-30px,80px); } }
        @keyframes sc-orb-float-2 { 0%,100% { transform: translate(0,0); } 33% { transform: translate(-60px,-40px); } 66% { transform: translate(40px,-60px); } }
    </style>

    <?php $default_theme = get_option('sc_default_theme', 'dark'); ?>
    <script>
    (function(){
        var saved = localStorage.getItem('sc_theme');
        var theme = saved || '<?php echo esc_js($default_theme); ?>';
        document.documentElement.className = 'sc-' + theme + '-theme';
    })();
    </script>
</head>
<body class="sc-<?php echo esc_attr($default_theme); ?>-theme">
    <!-- Gradient Orbs Background -->
    <div style="position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;" aria-hidden="true">
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:600px;height:600px;top:-10%;left:-10%;background:var(--sc-primary);animation:sc-orb-float-1 20s ease-in-out infinite;"></div>
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:500px;height:500px;bottom:-10%;right:-10%;background:var(--sc-secondary);animation:sc-orb-float-2 25s ease-in-out infinite;"></div>
    </div>

    <div class="ticket-container">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <?php if ($platform_logo_url): ?>
                <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" class="platform-logo">
            <?php endif; ?>
            <h1 class="event-title"><?php echo esc_html($event_title); ?></h1>
            <div class="ticket-id">TICKET ID: <?php echo esc_html($ticket_id); ?></div>
        </div>

        <!-- Ticket Body -->
        <div class="ticket-body">
            <!-- QR Code Section - Moved to top -->
            <div class="qr-section">
                <div class="section-title" style="justify-content: center; margin-bottom: 20px;">
                    <i class="fas fa-qrcode"></i>
                    <span>Scan to Verify Ticket</span>
                </div>
                <div class="qr-code" style="min-height: 210px; display: flex; align-items: center; justify-content: center;">
                    <img id="attendee-qr-image" src="" alt="QR Code" style="max-width: 200px; height: auto; margin: 0 auto; display: block; border: 1px solid #ccc; padding: 5px; background: white;">
                </div>
                <div class="qr-hint">
                    <i class="fas fa-info-circle"></i> Show this QR code at the event entrance
                </div>
            </div>

            <!-- Attendee Information -->
            <div class="ticket-section">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    <span>Attendee Information</span>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo esc_html($name); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo esc_html($email); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo esc_html($phone ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Ticket Type</div>
                        <div class="info-value"><?php echo esc_html($ticket_name ?: 'Register'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Event Information -->
            <div class="ticket-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Event Details</span>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Start Date</div>
                        <div class="info-value"><?php echo esc_html($start_date_formatted); ?> @ <?php echo esc_html($event_start_time); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">End Date</div>
                        <div class="info-value"><?php echo esc_html($end_date_formatted); ?> @ <?php echo esc_html($event_end_time); ?></div>
                    </div>
                    <?php if ($event_location): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Location</div>
                        <div class="info-value"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($event_location); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($session_registrations)): ?>
            <!-- Session Schedule -->
            <div class="ticket-section">
                <div class="section-title">
                    <i class="fas fa-chalkboard-user"></i>
                    <span><?php printf(__('Your Sessions (%d)', 'sc_events'), count($session_registrations)); ?></span>
                </div>

                <div class="sc-ticket-sessions">
                    <?php
                    $current_sess_date = '';
                    foreach ($session_registrations as $sr):
                        if ($sr->session_date !== $current_sess_date):
                            $current_sess_date = $sr->session_date;
                    ?>
                    <div class="sc-sess-date-header">
                        <i class="fas fa-calendar"></i>
                        <?php echo esc_html(date_i18n('l, M d', strtotime($current_sess_date))); ?>
                    </div>
                    <?php endif; ?>

                    <div class="sc-sess-item <?php echo $sr->registration_status === 'waitlisted' ? 'sc-sess-waitlisted' : ''; ?>">
                        <div class="sc-sess-time">
                            <?php echo esc_html(date('g:i A', strtotime($sr->start_time))); ?>
                            <?php if ($sr->end_time): ?>
                            <small>- <?php echo esc_html(date('g:i A', strtotime($sr->end_time))); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="sc-sess-details">
                            <div class="sc-sess-title-line">
                                <strong><?php echo esc_html($sr->title); ?></strong>
                                <?php if ($sr->registration_status === 'waitlisted'): ?>
                                <span class="sc-sess-badge waitlisted"><?php esc_html_e('Waitlisted', 'sc_events'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($sr->hall_name): ?>
                            <small><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($sr->hall_name); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($sr->qr_code && $sr->registration_status !== 'waitlisted'): ?>
                        <div class="sc-sess-qr" data-qr="<?php echo esc_attr($sr->qr_code); ?>">
                            <canvas width="70" height="70"></canvas>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 12px; font-size: 12px; color: var(--sc-text-muted, #888); display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-info-circle"></i>
                    <?php esc_html_e('Show the session QR code at the session entrance for check-in.', 'sc_events'); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons no-print">
                <button onclick="downloadTicketAsPDF()" class="btn-custom btn-print" id="download-btn">
                    <i class="fas fa-download"></i>
                    Download Ticket
                </button>
                <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="btn-custom btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to My Account
                </a>
            </div>
        </div>
    </div>

    <!-- QRCode.js Library (same as dashboard) -->
    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/admin-dashboard/vendor/qrcode.min.js'); ?>"></script>

    <!-- html2canvas Library for capturing HTML as image -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- jsPDF Library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// Generate QR Code using QRCode.toDataURL
(function() {
    const attendeeId = "<?php echo esc_js($attendee_id); ?>";
    const ticketId = "<?php echo esc_js($ticket_id); ?>";
    const dashboardUrl = "<?php echo esc_js(home_url('/event-manager-dashboard/')); ?>";

    setTimeout(function() {
        if (typeof QRCode !== 'undefined' && QRCode.toDataURL) {
            const qrImage = document.getElementById('attendee-qr-image');
            const verifyUrl = dashboardUrl + 'attendees?action=verify&id=' + attendeeId + '&ticket=' + encodeURIComponent(ticketId);

            if (qrImage) {
                QRCode.toDataURL(verifyUrl, function(err, url) {
                    if (!err && url) {
                        qrImage.src = url;

                        // Mark QR as loaded
                        qrImage.onload = function() {
                            qrImage.setAttribute('data-loaded', '1');
                        };

                    } else {
                        console.error('QR Code generation error:', err);
                        qrImage.outerHTML = '<div style="color: #999; padding: 20px;">Could not generate QR code</div>';
                    }
                });
            }
        } else {
            console.error('QRCode library not loaded');
            const qrImage = document.getElementById('attendee-qr-image');
            if (qrImage) {
                qrImage.outerHTML = '<div style="color: #999; padding: 20px;">QR Code library not available</div>';
            }
        }
    }, 100);

    // Generate session QR codes
    setTimeout(function() {
        if (typeof QRCode !== 'undefined' && QRCode.toDataURL) {
            document.querySelectorAll('.sc-sess-qr').forEach(function(el) {
                var qrData = el.getAttribute('data-qr');
                if (!qrData) return;
                var canvas = el.querySelector('canvas');
                QRCode.toDataURL(qrData, { width: 70, margin: 1, errorCorrectionLevel: 'M' }, function(err, url) {
                    if (!err && url) {
                        var img = document.createElement('img');
                        img.src = url;
                        img.style.width = '70px';
                        img.style.height = '70px';
                        img.style.borderRadius = '6px';
                        img.alt = 'Session QR';
                        if (canvas) canvas.replaceWith(img);
                    }
                });
            });
        }
    }, 200);
})();

// PDF Download Function
function downloadTicketAsPDF() {
    const qrImg = document.getElementById('attendee-qr-image');

    // Ensure QR is fully loaded
    if (qrImg && (!qrImg.src || qrImg.src === '' || !qrImg.complete || !qrImg.src.startsWith('data:'))) {
        alert("Please wait a moment for the QR code to load...");
        return;
    }

    const downloadBtn = document.getElementById('download-btn');
    const actionButtons = document.querySelector('.action-buttons');

    // Hide buttons before capture
    if (actionButtons) actionButtons.style.display = 'none';

    // Disable button during generation
    if (downloadBtn) {
        downloadBtn.disabled = true;
        downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
    }

    // Store QR data URL
    const qrDataUrl = qrImg.src;
    const ticketContainer = document.querySelector('.ticket-container');
    const qrCodeBox = document.querySelector('.qr-code');
    const body = document.body;

    // Save original styles for mobile/tablet
    const originalContainerStyle = ticketContainer.style.cssText;
    const originalBodyStyle = body.style.cssText;
    const isMobile = window.innerWidth < 900;

    // Force desktop size for consistent PDF on mobile/tablet
    if (isMobile) {
        ticketContainer.style.width = '900px';
        ticketContainer.style.maxWidth = '900px';
        ticketContainer.style.margin = '0';
        body.style.overflow = 'hidden';
    }

    // Wait for reflow then capture
    setTimeout(function() {
        // Get positions for calculating QR placement in PDF
        const containerRect = ticketContainer.getBoundingClientRect();
        const qrBoxRect = qrCodeBox.getBoundingClientRect();

        // Calculate relative position of QR box within container
        const qrRelativeX = qrBoxRect.left - containerRect.left;
        const qrRelativeY = qrBoxRect.top - containerRect.top;
        const qrBoxWidth = qrBoxRect.width;
        const qrBoxHeight = qrBoxRect.height;

        // Capture page first, then overlay QR
        html2canvas(ticketContainer, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
            backgroundColor: '#0a0a0f',
            ignoreElements: function(element) {
                // Ignore the QR image - we'll add it separately
                return element.id === 'attendee-qr-image';
            }
        }).then(function(canvas) {

            const imgWidth = 210; // A4 width in mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            const imgData = canvas.toDataURL('image/png');

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');

            // Add the main ticket image
            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);

            // Calculate QR position based on .qr-code box position
            const scale = imgWidth / containerRect.width;
            const qrX = (qrRelativeX * scale) + ((qrBoxWidth * scale - 35) / 2); // Center in box
            const qrY = (qrRelativeY * scale) + ((qrBoxHeight * scale - 35) / 2); // Center in box
            const qrSize = 35; // mm

            // Add QR code image at the exact position
            pdf.addImage(qrDataUrl, 'PNG', qrX, qrY, qrSize, qrSize);

            const ticketId = "<?php echo esc_js($ticket_id); ?>";
            pdf.save('Ticket-' + ticketId + '.pdf');

            // Restore original styles for mobile/tablet
            if (isMobile) {
                ticketContainer.style.cssText = originalContainerStyle;
                body.style.cssText = originalBodyStyle;
            }

            // Restore buttons
            if (actionButtons) actionButtons.style.display = '';

            if (downloadBtn) {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download Ticket';
            }

        }).catch(function(error) {
            console.error('Error generating PDF:', error);
            alert('Failed to generate PDF. Please try again.');

            // Restore original styles for mobile/tablet
            if (isMobile) {
                ticketContainer.style.cssText = originalContainerStyle;
                body.style.cssText = originalBodyStyle;
            }

            if (actionButtons) actionButtons.style.display = '';

            if (downloadBtn) {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download Ticket';
            }
        });
    }, 100); // End setTimeout
}
</script>

    <script>
    (function(){
        var saved = localStorage.getItem('sc_theme');
        if (saved) {
            document.body.className = document.body.className.replace(/sc-(dark|light)-theme/g, '');
            document.body.classList.add('sc-' + saved + '-theme');
        }
    })();
    </script>

</body>
</html>
