<?php
/**
 * WhatsApp code UI for the auth pages: texts, styles and script (assets/frontend/js/wisdom-otp.js).
 * Versioned by file time so a changed script is never served from an old cache.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$sc_otp_dir = get_template_directory();
$sc_otp_uri = get_template_directory_uri();
$sc_otp_text = array(
    'resend'    => sc_t('frontend.otp_resend', 'Send a new code'),
    'resend_in' => sc_t('frontend.otp_resend_in', 'New code in %s s'),
    'mismatch'  => sc_t('frontend.passwords_mismatch', 'The passwords don’t match.'),
    'terms'     => sc_t('frontend.agree_terms_required', 'Please agree to the Terms and Privacy policy to continue.'),
    'network'   => sc_t('frontend.network_error', 'Could not reach the site. Check your connection and try again.'),
);
?>
<link rel="stylesheet" href="<?php echo esc_url($sc_otp_uri . '/assets/frontend/css/wisdom/otp.css?v=' . filemtime($sc_otp_dir . '/assets/frontend/css/wisdom/otp.css')); ?>">
<script>window.scOtpText = <?php echo wp_json_encode($sc_otp_text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
<script defer src="<?php echo esc_url($sc_otp_uri . '/assets/frontend/js/wisdom-otp.js?v=' . filemtime($sc_otp_dir . '/assets/frontend/js/wisdom-otp.js')); ?>"></script>
