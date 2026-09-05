<?php
/**
 * Template Name: Payment Failed
 * Displays when payment fails or is cancelled
 *
 * @package sc_events
 * @version 1.4.0
 */

// Get parameters
$payment_ref = isset($_GET['payment_ref']) ? sanitize_text_field($_GET['payment_ref']) : '';
$error = isset($_GET['error']) ? sanitize_text_field(urldecode($_GET['error'])) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'failed';

// Get payment details if available
$payment = null;
$event = null;

if ($payment_ref && class_exists('SC_Payment_Gateway')) {
    $payment = SC_Payment_Gateway::get_payment_by_ref($payment_ref);

    if ($payment && class_exists('SC_Event')) {
        $event = SC_Event::get($payment->event_id);
    }
}

// Platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'medium') : '';

// Determine title and message based on status
$is_cancelled = $status === 'cancelled';
$title = $is_cancelled ? __('Payment Cancelled', 'sc_events') : __('Payment Failed', 'sc_events');
$subtitle = $is_cancelled
    ? __('Your payment was cancelled', 'sc_events')
    : __('We could not process your payment', 'sc_events');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?></title>

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

    <div class="failed-container">
        <div class="failed-header <?php echo $is_cancelled ? 'cancelled' : ''; ?>">
            <div class="failed-icon">
                <i class="fas <?php echo $is_cancelled ? 'fa-ban' : 'fa-times'; ?>"></i>
            </div>
            <h1><?php echo esc_html($title); ?></h1>
            <p><?php echo esc_html($subtitle); ?></p>
        </div>

        <div class="failed-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_cancelled): ?>
                <p class="help-text">
                    <?php _e('Don\'t worry, your registration was not completed and you have not been charged. Please try again or use a different payment method.', 'sc_events'); ?>
                </p>

                <div class="help-list">
                    <h4><i class="fas fa-lightbulb me-2"></i><?php _e('Common reasons for payment failure:', 'sc_events'); ?></h4>
                    <ul>
                        <li><?php _e('Insufficient funds in your account', 'sc_events'); ?></li>
                        <li><?php _e('Incorrect card details entered', 'sc_events'); ?></li>
                        <li><?php _e('Card expired or blocked for online transactions', 'sc_events'); ?></li>
                        <li><?php _e('Transaction declined by your bank', 'sc_events'); ?></li>
                        <li><?php _e('Network or connection issues', 'sc_events'); ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <p class="help-text">
                    <?php _e('You cancelled the payment process. If this was unintentional, you can try again below.', 'sc_events'); ?>
                </p>
            <?php endif; ?>

            <div class="btn-group-custom">
                <?php if ($event): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('event_id' => $event->id), home_url('/checkout/'))); ?>" class="btn-custom btn-primary-custom">
                        <i class="fas fa-redo"></i>
                        <?php _e('Try Again', 'sc_events'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-custom btn-secondary-custom">
                    <i class="fas fa-home"></i>
                    <?php _e('Back to Home', 'sc_events'); ?>
                </a>
            </div>

            <div class="support-link">
                <?php _e('Need help?', 'sc_events'); ?>
                <a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php _e('Contact Support', 'sc_events'); ?></a>
            </div>
        </div>
    </div>

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
