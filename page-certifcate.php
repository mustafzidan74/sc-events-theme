<?php
/**
 * Template Name: Certificate Page
 * Certificate View Page - Full Screen Display
 *
 * @package sc_events
 * @version 1.0.0
 */

// Get parameters
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$attendee_id = isset($_GET['attandee_id']) ? intval($_GET['attandee_id']) : 0;

// Validate parameters
if (!$event_id || !$attendee_id) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Get attendee data
$attendee = get_post($attendee_id);
if (!$attendee || $attendee->post_type !== 'etn-attendee') {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Get attendee info
$attendee_name = get_post_meta($attendee_id, 'etn_name', true);
$attendee_email = get_post_meta($attendee_id, 'etn_email', true);
$attendee_event_id = get_post_meta($attendee_id, 'etn_event_id', true);

// Verify event matches
if ($attendee_event_id != $event_id) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Get event data
$event = get_post($event_id);
if (!$event) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$event_title = $event->post_title;

// If no attendee name, try to get from user
if (empty($attendee_name) && is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $attendee_name = $current_user->display_name;
}

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<style>
/* Certificate Page Styles */
.certificate-page-section {
    min-height: 100vh;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    padding: 40px 0;
}

.certificate-page-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.certificate-page-header {
    text-align: center;
    margin-bottom: 30px;
}

.certificate-page-header h1 {
    color: #ffffff;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.certificate-page-header p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
}

.certificate-display-wrapper {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.certificate-full-container {
    position: relative;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
}

.certificate-full-container img {
    width: 100%;
    height: auto;
    display: block;
}

.certificate-name-overlay {
    position: absolute;
    top: 55%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ffffff;
    font-size: clamp(1.5rem, 2.5vw, 3.5rem);
    font-weight: 700;
    text-align: center;
    text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
    width: 80%;
    font-family: 'Georgia', serif;
    pointer-events: none;
}
.certificate-actions-bar {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.certificate-actions-bar .vl-btn1 {
    min-width: 200px;
}

.btn-back-account {
    background: transparent !important;
    border: 2px solid rgba(255, 255, 255, 0.5) !important;
}

.btn-back-account:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: #ffffff !important;
}

/* Mobile Zoom for Download */
.mobile-zoom-active {
    width: 1400px !important;
    min-width: 1400px !important;
    overflow-x: auto;
}

.mobile-zoom-active .certificate-full-container {
    max-width: 1200px;
    width: 1200px;
}

.certificate-page-header {
    margin-top: 110px;
}

/* Responsive */
@media (max-width: 768px) {
    .certificate-page-section {
        padding: 20px 0;
    }
    
    .certificate-page-header h1 {
        font-size: 1.8rem;
    }
    
    .certificate-display-wrapper {
        padding: 15px;
    }
    
    .certificate-actions-bar {
        flex-direction: column;
        align-items: center;
    }
    
    .certificate-actions-bar .vl-btn1 {
        width: 100%;
        max-width: 300px;
    }
    .certificate-name-overlay {
        font-size: 8px;
    }
}

/* Loading Overlay */
.download-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 99999;
    flex-direction: column;
}

.download-loading-overlay.active {
    display: flex;
}

.download-loading-overlay .spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.download-loading-overlay .loading-text {
    color: #ffffff;
    margin-top: 20px;
    font-size: 1.2rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<!--===== CERTIFICATE PAGE =======-->
<div class="certificate-page-section">
    <div class="certificate-page-container">
        <!-- Header -->
        <div class="certificate-page-header">
            <h1><i class="fa-solid fa-certificate me-2"></i><?php esc_html_e('Certificate of Attendance', 'sc_events'); ?></h1>
            <p><?php echo esc_html($event_title); ?></p>
        </div>

        <!-- Certificate Display -->
        <div class="certificate-display-wrapper">
            <div id="certificate-full-container" class="certificate-full-container">
                <img src="<?php echo get_template_directory_uri(); ?>/certficate/certificate.jpg" alt="Certificate" class="certificate-bg-image">
                <div class="certificate-name-overlay"><?php echo esc_html($attendee_name ?: $attendee_email); ?></div>
            </div>

            <!-- Actions -->
            <div class="certificate-actions-bar">
                <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="vl-btn1 btn-back-account">
                    <span class="demo"><i class="fa-solid fa-arrow-left me-2"></i><?php esc_html_e('Back to My Account', 'sc_events'); ?></span>
                </a>
                <button type="button" id="download-certificate-btn" class="vl-btn1">
                    <span class="demo"><i class="fa-solid fa-file-pdf me-2"></i><?php esc_html_e('Download as PDF', 'sc_events'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="download-loading-overlay" id="download-loading">
    <div class="spinner"></div>
    <div class="loading-text"><?php esc_html_e('Generating Certificate...', 'sc_events'); ?></div>
</div>

<!-- Scripts for PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
jQuery(document).ready(function($) {
    $('#download-certificate-btn').on('click', function() {
        var $btn = $(this);
        var $container = $('#certificate-full-container');
        var $wrapper = $('.certificate-page-container');
        var $loading = $('#download-loading');
        var originalHtml = $btn.html();
        
        // Show loading
        $loading.addClass('active');
        $btn.prop('disabled', true);
        
        // Check if mobile
        var isMobile = window.innerWidth < 992;
        var originalBodyWidth = document.body.style.width;
        var originalBodyOverflow = document.body.style.overflow;
        var originalHtmlWidth = document.documentElement.style.width;
        var scrollPos = window.scrollY;
        
        // Mobile: Expand viewport for better quality
        if (isMobile) {
            document.documentElement.style.width = '1400px';
            document.body.style.width = '1400px';
            document.body.style.overflow = 'hidden';
            $wrapper.addClass('mobile-zoom-active');
            
            // Force reflow
            $container[0].offsetHeight;
        }
        
        // Small delay for reflow
        setTimeout(function() {
            html2canvas($container[0], {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null,
                width: $container[0].scrollWidth,
                height: $container[0].scrollHeight,
                windowWidth: 1400
            }).then(function(canvas) {
                var { jsPDF } = window.jspdf;
                
                var imgWidth = canvas.width;
                var imgHeight = canvas.height;
                
                // Create PDF in landscape orientation
                var pdf = new jsPDF({
                    orientation: imgWidth > imgHeight ? 'landscape' : 'portrait',
                    unit: 'px',
                    format: [imgWidth, imgHeight]
                });
                
                var imgData = canvas.toDataURL('image/jpeg', 1.0);
                pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
                
                // Download the PDF
                var userName = '<?php echo esc_js($attendee_name ?: $attendee_email); ?>';
                var eventName = '<?php echo esc_js($event_title); ?>';
                var fileName = 'Certificate-' + userName.replace(/[^a-z0-9]/gi, '-') + '-' + eventName.replace(/[^a-z0-9]/gi, '-').substring(0, 20) + '.pdf';
                pdf.save(fileName);
                
                // Reset mobile zoom
                if (isMobile) {
                    document.documentElement.style.width = originalHtmlWidth;
                    document.body.style.width = originalBodyWidth;
                    document.body.style.overflow = originalBodyOverflow;
                    $wrapper.removeClass('mobile-zoom-active');
                    window.scrollTo(0, scrollPos);
                }
                
                // Hide loading
                $loading.removeClass('active');
                $btn.prop('disabled', false);
                
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo esc_js(__('Downloaded!', 'sc_events')); ?>',
                    text: '<?php echo esc_js(__('Your certificate has been downloaded successfully.', 'sc_events')); ?>',
                    timer: 2000,
                    showConfirmButton: false
                });
                
            }).catch(function(error) {
                console.error('Error generating PDF:', error);
                
                // Reset mobile zoom on error
                if (isMobile) {
                    document.documentElement.style.width = originalHtmlWidth;
                    document.body.style.width = originalBodyWidth;
                    document.body.style.overflow = originalBodyOverflow;
                    $wrapper.removeClass('mobile-zoom-active');
                    window.scrollTo(0, scrollPos);
                }
                
                $loading.removeClass('active');
                $btn.prop('disabled', false);
                
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo esc_js(__('Error', 'sc_events')); ?>',
                    text: '<?php echo esc_js(__('Could not generate PDF. Please try again.', 'sc_events')); ?>'
                });
            });
        }, isMobile ? 500 : 100);
    });
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
