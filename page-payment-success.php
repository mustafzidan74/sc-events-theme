<?php
/**
 * Template Name: Payment Success
 * Displays after successful payment completion
 *
 * @package sc_events
 * @version 1.4.0
 */

// Get parameters
$payment_ref = isset($_GET['payment_ref']) ? sanitize_text_field($_GET['payment_ref']) : '';
$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

// Get payment details
$payment = null;
$attendee = null;
$event = null;
$ticket = null;

if ($payment_ref && class_exists('SC_Payment_Gateway')) {
    $payment = SC_Payment_Gateway::get_payment_by_ref($payment_ref);

    if ($payment) {
        // If payment is still processing, try to verify
        if ($payment->status === 'processing' && class_exists('SC_Payment_Gateway_Manager')) {
            $gateway = SC_Payment_Gateway_Manager::get_gateway($payment->gateway_code);
            if ($gateway) {
                $result = $gateway->verify_payment($payment_ref);
                if (!empty($result['status']) && $result['status'] === 'completed') {
                    // Reload payment
                    $payment = SC_Payment_Gateway::get_payment_by_ref($payment_ref);
                }
            }
        }

        // Get attendee if exists
        if ($payment->attendee_id && class_exists('SC_Attendee')) {
            $attendee = SC_Attendee::get($payment->attendee_id);
        }

        // Get event
        if ($payment->event_id && class_exists('SC_Event')) {
            $event = SC_Event::get($payment->event_id);
        }

        // Get ticket
        if ($payment->ticket_id && class_exists('SC_Ticket')) {
            $ticket = SC_Ticket::get($payment->ticket_id);
        }
    }
}

// Platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'medium') : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('Payment Successful', 'sc_events'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/variables.css">
    <?php require_once get_template_directory() . '/inc/public-frontend/frontend-dynamic-colors.php'; ?>
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/light-theme.css">

    <!-- Payment Pages Styles -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/payment-pages.css">

    <style>
        body { font-family: 'Inter', sans-serif; background: var(--sc-bg-primary, #0a0a0f); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
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
    <div style="position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;" aria-hidden="true">
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:600px;height:600px;top:-10%;left:-10%;background:var(--sc-primary);animation:sc-orb-float-1 20s ease-in-out infinite;"></div>
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:500px;height:500px;bottom:-10%;right:-10%;background:var(--sc-secondary);animation:sc-orb-float-2 25s ease-in-out infinite;"></div>
    </div>

    <div class="confetti" id="confetti"></div>

    <div class="success-container">
        <?php if ($payment && ($payment->status === 'completed' || $attendee)): ?>
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1><?php _e('Payment Successful!', 'sc_events'); ?></h1>
                <p><?php _e('Thank you for your registration', 'sc_events'); ?></p>
            </div>

            <div class="success-body">
                <div class="order-details">
                    <?php if ($event): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Event', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo esc_html($event->title); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($ticket): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Ticket', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo esc_html($ticket->name); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Amount', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo sc_format_price($payment->amount); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Reference', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo esc_html($payment->payment_ref); ?></span>
                    </div>

                    <?php if ($attendee): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Ticket Code', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo esc_html($attendee->ticket_code); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <p class="text-muted mb-4">
                    <?php _e('A confirmation email has been sent to your email address with your ticket details.', 'sc_events'); ?>
                </p>

                <?php
                // Show session registrations if any
                $ps_sessions = array();
                if ($attendee && class_exists('SC_Session_Attendance')) {
                    $ps_sessions = SC_Session_Attendance::get_attendee_summary($attendee->id, $payment->event_id);
                }
                if (!empty($ps_sessions)):
                ?>
                <div class="order-details" style="margin-bottom: 20px;">
                    <div style="font-weight: 600; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-chalkboard-user" style="color: var(--sc-primary);"></i>
                        <?php printf(__('Your Sessions (%d)', 'sc_events'), count($ps_sessions)); ?>
                    </div>
                    <?php
                    $ps_date = '';
                    foreach ($ps_sessions as $ps):
                        if ($ps->session_date !== $ps_date):
                            $ps_date = $ps->session_date;
                    ?>
                    <div style="font-size: 11px; font-weight: 700; color: var(--sc-primary); text-transform: uppercase; padding: 6px 0 2px; margin-top: 4px;">
                        <?php echo esc_html(date_i18n('l, M d', strtotime($ps_date))); ?>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php echo esc_html(date('g:i A', strtotime($ps->start_time))); ?></span>
                        <span class="detail-value">
                            <?php echo esc_html($ps->title); ?>
                            <?php if ($ps->hall_name): ?>
                            <small style="opacity: 0.6;"> — <?php echo esc_html($ps->hall_name); ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div style="font-size: 12px; color: var(--sc-text-muted, #888); margin-top: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle"></i>
                        <?php _e("You've been automatically registered for all sessions.", 'sc_events'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="btn-group-custom">
                    <?php if ($attendee): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('attendee_id' => $attendee->id, 'ticket_code' => $attendee->ticket_code), home_url('/ticket-view/'))); ?>" class="btn-custom btn-primary-custom">
                        <i class="fas fa-ticket-alt"></i>
                        <?php _e('View My Ticket', 'sc_events'); ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-custom btn-secondary-custom">
                        <i class="fas fa-home"></i>
                        <?php _e('Back to Home', 'sc_events'); ?>
                    </a>
                </div>
            </div>

        <?php elseif ($payment && $payment->status === 'processing'): ?>
            <div class="success-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="success-icon">
                    <i class="fas fa-clock" style="color: #f59e0b;"></i>
                </div>
                <h1><?php _e('Payment Processing', 'sc_events'); ?></h1>
                <p><?php _e('Your payment is being processed', 'sc_events'); ?></p>
            </div>

            <div class="success-body">
                <div class="processing-message">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    <?php _e('Please wait while we confirm your payment. This page will automatically refresh.', 'sc_events'); ?>
                </div>

                <div class="order-details">
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Reference', 'sc_events'); ?></span>
                        <span class="detail-value"><?php echo esc_html($payment->payment_ref); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Status', 'sc_events'); ?></span>
                        <span class="detail-value"><?php _e('Processing...', 'sc_events'); ?></span>
                    </div>
                </div>

                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-custom btn-secondary-custom">
                    <i class="fas fa-home"></i>
                    <?php _e('Back to Home', 'sc_events'); ?>
                </a>
            </div>

            <script>
                // Auto-refresh for processing payments
                setTimeout(function() {
                    location.reload();
                }, 5000);
            </script>

        <?php else: ?>
            <div class="success-header" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                <div class="success-icon">
                    <i class="fas fa-question" style="color: #6c757d;"></i>
                </div>
                <h1><?php _e('Payment Not Found', 'sc_events'); ?></h1>
                <p><?php _e('We could not find your payment details', 'sc_events'); ?></p>
            </div>

            <div class="success-body">
                <p class="text-muted mb-4">
                    <?php _e('If you made a payment, please check your email for confirmation or contact support.', 'sc_events'); ?>
                </p>

                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-custom btn-secondary-custom">
                    <i class="fas fa-home"></i>
                    <?php _e('Back to Home', 'sc_events'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($payment && $payment->status === 'completed'): ?>
    <script>
        // Confetti animation for successful payments
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            const colors = ['var(--sc-primary)', 'var(--sc-secondary)', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];

            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: absolute;
                    width: ${Math.random() * 10 + 5}px;
                    height: ${Math.random() * 10 + 5}px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    left: ${Math.random() * 100}%;
                    top: -20px;
                    opacity: ${Math.random() * 0.7 + 0.3};
                    border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                    animation: fall ${Math.random() * 3 + 2}s linear forwards;
                `;
                confettiContainer.appendChild(confetti);
            }

            const style = document.createElement('style');
            style.textContent = `
                @keyframes fall {
                    to {
                        transform: translateY(100vh) rotate(720deg);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            setTimeout(() => {
                confettiContainer.innerHTML = '';
            }, 5000);
        }

        createConfetti();
    </script>
    <?php endif; ?>

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
