<?php
/**
 * SC Events Two-Factor Authentication
 *
 * Provides 2FA security for event managers
 * - TOTP (Google Authenticator, Authy)
 * - Email verification codes
 * - Backup codes
 * - User settings interface
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Two_Factor_Auth {

    /**
     * TOTP settings
     */
    const TOTP_DIGITS = 6;
    const TOTP_PERIOD = 30;
    const TOTP_ALGORITHM = 'sha1';
    const CODE_EXPIRY = 300; // 5 minutes for email codes
    const BACKUP_CODES_COUNT = 10;

    /**
     * Meta keys
     */
    const META_2FA_ENABLED = 'sc_2fa_enabled';
    const META_2FA_METHOD = 'sc_2fa_method';
    const META_TOTP_SECRET = 'sc_2fa_totp_secret';
    const META_BACKUP_CODES = 'sc_2fa_backup_codes';
    const META_EMAIL_CODE = 'sc_2fa_email_code';
    const META_EMAIL_CODE_EXPIRY = 'sc_2fa_email_code_expiry';

    /**
     * Initialize
     */
    public static function init() {
        // Hook into login process
        add_action('wp_authenticate', array(__CLASS__, 'check_2fa_required'), 100, 2);
        add_filter('authenticate', array(__CLASS__, 'validate_2fa'), 100, 3);

        // AJAX handlers
        add_action('wp_ajax_sc_setup_2fa', array(__CLASS__, 'ajax_setup_2fa'));
        add_action('wp_ajax_sc_verify_2fa_setup', array(__CLASS__, 'ajax_verify_2fa_setup'));
        add_action('wp_ajax_sc_disable_2fa', array(__CLASS__, 'ajax_disable_2fa'));
        add_action('wp_ajax_sc_regenerate_backup_codes', array(__CLASS__, 'ajax_regenerate_backup_codes'));
        add_action('wp_ajax_nopriv_sc_verify_2fa_login', array(__CLASS__, 'ajax_verify_2fa_login'));
        add_action('wp_ajax_nopriv_sc_send_email_code', array(__CLASS__, 'ajax_send_email_code'));

        // Settings page
        add_action('sc_dashboard_security_settings', array(__CLASS__, 'render_settings'));

        // Enqueue scripts
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_login_scripts'));
    }

    /**
     * Check if 2FA is enabled for user
     */
    public static function is_enabled($user_id) {
        return (bool) get_user_meta($user_id, self::META_2FA_ENABLED, true);
    }

    /**
     * Get 2FA method for user
     */
    public static function get_method($user_id) {
        return get_user_meta($user_id, self::META_2FA_METHOD, true) ?: 'totp';
    }

    /**
     * Generate TOTP secret
     */
    public static function generate_secret($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * Generate TOTP code
     */
    public static function generate_totp($secret, $time = null) {
        if ($time === null) {
            $time = time();
        }

        $counter = floor($time / self::TOTP_PERIOD);
        $secret_decoded = self::base32_decode($secret);

        $counter_packed = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac(self::TOTP_ALGORITHM, $counter_packed, $secret_decoded, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, self::TOTP_DIGITS);

        return str_pad($code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify TOTP code
     */
    public static function verify_totp($secret, $code, $window = 1) {
        $time = time();

        for ($i = -$window; $i <= $window; $i++) {
            $check_time = $time + ($i * self::TOTP_PERIOD);
            $expected = self::generate_totp($secret, $check_time);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Base32 decode
     */
    private static function base32_decode($input) {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);

        $bits = '';
        for ($i = 0; $i < strlen($input); $i++) {
            $bits .= str_pad(decbin(strpos($map, $input[$i])), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }

        return $output;
    }

    /**
     * Generate otpauth URI for QR code
     */
    public static function get_otpauth_uri($secret, $user_email) {
        $issuer = get_bloginfo('name');
        $issuer = rawurlencode($issuer);
        $account = rawurlencode($user_email);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            $issuer,
            $account,
            $secret,
            $issuer,
            strtoupper(self::TOTP_ALGORITHM),
            self::TOTP_DIGITS,
            self::TOTP_PERIOD
        );
    }

    /**
     * Generate backup codes
     */
    public static function generate_backup_codes($count = null) {
        if ($count === null) {
            $count = self::BACKUP_CODES_COUNT;
        }

        $codes = array();
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Verify backup code
     */
    public static function verify_backup_code($user_id, $code) {
        $codes = get_user_meta($user_id, self::META_BACKUP_CODES, true);
        if (!is_array($codes)) {
            return false;
        }

        $code = strtoupper(trim($code));
        $key = array_search($code, $codes);

        if ($key !== false) {
            // Remove used code
            unset($codes[$key]);
            update_user_meta($user_id, self::META_BACKUP_CODES, array_values($codes));
            return true;
        }

        return false;
    }

    /**
     * Send email verification code
     */
    public static function send_email_code($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + self::CODE_EXPIRY;

        update_user_meta($user_id, self::META_EMAIL_CODE, wp_hash_password($code));
        update_user_meta($user_id, self::META_EMAIL_CODE_EXPIRY, $expiry);

        $subject = sprintf(__('[%s] Your verification code', 'sc_events'), get_bloginfo('name'));
        $message = sprintf(
            __("Your verification code is: %s\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this code, please ignore this email.", 'sc_events'),
            $code
        );

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Verify email code
     */
    public static function verify_email_code($user_id, $code) {
        $stored_hash = get_user_meta($user_id, self::META_EMAIL_CODE, true);
        $expiry = get_user_meta($user_id, self::META_EMAIL_CODE_EXPIRY, true);

        if (!$stored_hash || !$expiry || time() > $expiry) {
            return false;
        }

        if (wp_check_password($code, $stored_hash)) {
            // Clear code after use
            delete_user_meta($user_id, self::META_EMAIL_CODE);
            delete_user_meta($user_id, self::META_EMAIL_CODE_EXPIRY);
            return true;
        }

        return false;
    }

    /**
     * Enable 2FA for user
     */
    public static function enable($user_id, $method = 'totp', $secret = null) {
        update_user_meta($user_id, self::META_2FA_ENABLED, true);
        update_user_meta($user_id, self::META_2FA_METHOD, $method);

        if ($method === 'totp' && $secret) {
            update_user_meta($user_id, self::META_TOTP_SECRET, $secret);
        }

        // Generate backup codes
        $backup_codes = self::generate_backup_codes();
        update_user_meta($user_id, self::META_BACKUP_CODES, $backup_codes);

        // Log activity
        if (function_exists('sc_log_activity')) {
            sc_log_activity('security', '2fa_enabled', array(
                'object_type' => 'user',
                'object_id' => $user_id,
                'description' => sprintf('2FA enabled using %s method', $method)
            ));
        }

        return $backup_codes;
    }

    /**
     * Disable 2FA for user
     */
    public static function disable($user_id) {
        delete_user_meta($user_id, self::META_2FA_ENABLED);
        delete_user_meta($user_id, self::META_2FA_METHOD);
        delete_user_meta($user_id, self::META_TOTP_SECRET);
        delete_user_meta($user_id, self::META_BACKUP_CODES);
        delete_user_meta($user_id, self::META_EMAIL_CODE);
        delete_user_meta($user_id, self::META_EMAIL_CODE_EXPIRY);

        // Log activity
        if (function_exists('sc_log_activity')) {
            sc_log_activity('security', '2fa_disabled', array(
                'object_type' => 'user',
                'object_id' => $user_id,
                'description' => '2FA disabled'
            ));
        }

        return true;
    }

    /**
     * Verify 2FA code
     */
    public static function verify($user_id, $code) {
        if (!self::is_enabled($user_id)) {
            return true;
        }

        $method = self::get_method($user_id);
        $code = trim($code);

        // Check backup code first
        if (strlen($code) === 8 && self::verify_backup_code($user_id, $code)) {
            return true;
        }

        switch ($method) {
            case 'totp':
                $secret = get_user_meta($user_id, self::META_TOTP_SECRET, true);
                return self::verify_totp($secret, $code);

            case 'email':
                return self::verify_email_code($user_id, $code);

            default:
                return false;
        }
    }

    // ========================================
    // LOGIN INTEGRATION
    // ========================================

    /**
     * Check if 2FA required during login
     */
    public static function check_2fa_required(&$username, &$password) {
        // Store for later use in validate_2fa
        if (isset($_POST['sc_2fa_code'])) {
            return;
        }

        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }

        if ($user && self::is_enabled($user->ID)) {
            // Store pending user for 2FA validation
            set_transient('sc_2fa_pending_' . md5($username), $user->ID, 300);
        }
    }

    /**
     * Validate 2FA during authentication
     */
    public static function validate_2fa($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        if (!$user || !isset($user->ID)) {
            return $user;
        }

        // Skip if 2FA not enabled
        if (!self::is_enabled($user->ID)) {
            return $user;
        }

        // Check if 2FA code provided
        if (isset($_POST['sc_2fa_code'])) {
            $code = sanitize_text_field($_POST['sc_2fa_code']);

            if (self::verify($user->ID, $code)) {
                // Clear pending
                delete_transient('sc_2fa_pending_' . md5($username));
                return $user;
            } else {
                return new WP_Error('2fa_invalid', __('Invalid verification code.', 'sc_events'));
            }
        }

        // 2FA required but no code provided - return special error
        return new WP_Error('2fa_required', __('Verification code required.', 'sc_events'), array(
            'user_id' => $user->ID,
            'method' => self::get_method($user->ID)
        ));
    }

    // ========================================
    // AJAX HANDLERS
    // ========================================

    /**
     * AJAX: Setup 2FA
     */
    public static function ajax_setup_2fa() {
        check_ajax_referer('sc_2fa_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Not logged in', 'sc_events'));
        }

        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'totp';

        if ($method === 'totp') {
            $secret = self::generate_secret();
            $user = wp_get_current_user();
            $qr_uri = self::get_otpauth_uri($secret, $user->user_email);

            // Store temporarily
            set_transient('sc_2fa_setup_' . $user_id, $secret, 600);

            wp_send_json_success(array(
                'method' => 'totp',
                'secret' => $secret,
                'qr_uri' => $qr_uri,
                'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_uri)
            ));
        } elseif ($method === 'email') {
            // Send test code
            self::send_email_code($user_id);
            set_transient('sc_2fa_setup_' . $user_id, 'email', 600);

            wp_send_json_success(array(
                'method' => 'email',
                'message' => __('Verification code sent to your email', 'sc_events')
            ));
        }

        wp_send_json_error(__('Invalid method', 'sc_events'));
    }

    /**
     * AJAX: Verify 2FA setup
     */
    public static function ajax_verify_2fa_setup() {
        check_ajax_referer('sc_2fa_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Not logged in', 'sc_events'));
        }

        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'totp';
        $stored = get_transient('sc_2fa_setup_' . $user_id);

        if (!$stored) {
            wp_send_json_error(__('Setup session expired. Please try again.', 'sc_events'));
        }

        $verified = false;

        if ($method === 'totp') {
            $verified = self::verify_totp($stored, $code);
        } elseif ($method === 'email') {
            $verified = self::verify_email_code($user_id, $code);
        }

        if ($verified) {
            $backup_codes = self::enable($user_id, $method, $method === 'totp' ? $stored : null);
            delete_transient('sc_2fa_setup_' . $user_id);

            wp_send_json_success(array(
                'message' => __('Two-factor authentication enabled successfully!', 'sc_events'),
                'backup_codes' => $backup_codes
            ));
        }

        wp_send_json_error(__('Invalid verification code. Please try again.', 'sc_events'));
    }

    /**
     * AJAX: Disable 2FA
     */
    public static function ajax_disable_2fa() {
        check_ajax_referer('sc_2fa_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Not logged in', 'sc_events'));
        }

        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $user = wp_get_current_user();

        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_send_json_error(__('Invalid password', 'sc_events'));
        }

        self::disable($user_id);

        wp_send_json_success(array(
            'message' => __('Two-factor authentication disabled', 'sc_events')
        ));
    }

    /**
     * AJAX: Regenerate backup codes
     */
    public static function ajax_regenerate_backup_codes() {
        check_ajax_referer('sc_2fa_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !self::is_enabled($user_id)) {
            wp_send_json_error(__('2FA not enabled', 'sc_events'));
        }

        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $user = wp_get_current_user();

        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_send_json_error(__('Invalid password', 'sc_events'));
        }

        $codes = self::generate_backup_codes();
        update_user_meta($user_id, self::META_BACKUP_CODES, $codes);

        wp_send_json_success(array(
            'backup_codes' => $codes,
            'message' => __('Backup codes regenerated. Old codes are no longer valid.', 'sc_events')
        ));
    }

    /**
     * AJAX: Verify 2FA during login (for AJAX login)
     */
    public static function ajax_verify_2fa_login() {
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';

        $user_id = get_transient('sc_2fa_pending_' . md5($username));

        if (!$user_id) {
            wp_send_json_error(__('Session expired. Please login again.', 'sc_events'));
        }

        if (self::verify($user_id, $code)) {
            delete_transient('sc_2fa_pending_' . md5($username));

            // Log the user in
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            wp_send_json_success(array(
                'redirect' => admin_url()
            ));
        }

        wp_send_json_error(__('Invalid verification code', 'sc_events'));
    }

    /**
     * AJAX: Send email code for login
     */
    public static function ajax_send_email_code() {
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $user_id = get_transient('sc_2fa_pending_' . md5($username));

        if (!$user_id) {
            wp_send_json_error(__('Session expired. Please login again.', 'sc_events'));
        }

        if (self::send_email_code($user_id)) {
            wp_send_json_success(array(
                'message' => __('Verification code sent to your email', 'sc_events')
            ));
        }

        wp_send_json_error(__('Failed to send email', 'sc_events'));
    }

    // ========================================
    // SETTINGS INTERFACE
    // ========================================

    /**
     * Render 2FA settings
     */
    public static function render_settings() {
        $user_id = get_current_user_id();
        $is_enabled = self::is_enabled($user_id);
        $method = self::get_method($user_id);
        $backup_codes = get_user_meta($user_id, self::META_BACKUP_CODES, true) ?: array();
        $nonce = wp_create_nonce('sc_2fa_nonce');
        ?>
        <div class="sc-2fa-settings" style="background: #fff; padding: 25px; border-radius: 10px; margin: 20px 0;">
            <h3><?php _e('Two-Factor Authentication', 'sc_events'); ?></h3>
            <p class="description"><?php _e('Add an extra layer of security to your account', 'sc_events'); ?></p>

            <?php if ($is_enabled): ?>
                <div class="sc-2fa-status" style="padding: 15px; background: #e8f5e9; border-radius: 5px; margin: 15px 0;">
                    <strong style="color: #2e7d32;"><?php _e('2FA is enabled', 'sc_events'); ?></strong>
                    <span style="margin-left: 10px;">
                        (<?php echo $method === 'totp' ? __('Authenticator App', 'sc_events') : __('Email', 'sc_events'); ?>)
                    </span>
                </div>

                <div class="sc-backup-codes" style="margin: 20px 0;">
                    <h4><?php _e('Backup Codes', 'sc_events'); ?></h4>
                    <p class="description"><?php _e('Save these codes in a safe place. Each code can only be used once.', 'sc_events'); ?></p>
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace;">
                        <?php if (count($backup_codes) > 0): ?>
                            <?php echo implode(' &nbsp; ', $backup_codes); ?>
                        <?php else: ?>
                            <em><?php _e('No backup codes remaining', 'sc_events'); ?></em>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: 10px;">
                        <strong><?php echo count($backup_codes); ?></strong> <?php _e('codes remaining', 'sc_events'); ?>
                    </p>
                    <button type="button" class="button" id="sc-regenerate-codes"><?php _e('Regenerate Codes', 'sc_events'); ?></button>
                </div>

                <hr style="margin: 20px 0;">

                <button type="button" class="button button-link-delete" id="sc-disable-2fa"><?php _e('Disable 2FA', 'sc_events'); ?></button>

            <?php else: ?>
                <div class="sc-2fa-setup">
                    <h4><?php _e('Choose verification method', 'sc_events'); ?></h4>

                    <div class="sc-2fa-methods" style="display: flex; gap: 20px; margin: 20px 0;">
                        <label style="display: block; padding: 20px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; flex: 1;">
                            <input type="radio" name="sc_2fa_method" value="totp" checked style="margin-right: 10px;">
                            <strong><?php _e('Authenticator App', 'sc_events'); ?></strong>
                            <p style="margin: 10px 0 0; color: #666; font-size: 13px;">
                                <?php _e('Use Google Authenticator, Authy, or similar app', 'sc_events'); ?>
                            </p>
                        </label>

                        <label style="display: block; padding: 20px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; flex: 1;">
                            <input type="radio" name="sc_2fa_method" value="email" style="margin-right: 10px;">
                            <strong><?php _e('Email Verification', 'sc_events'); ?></strong>
                            <p style="margin: 10px 0 0; color: #666; font-size: 13px;">
                                <?php _e('Receive verification code via email', 'sc_events'); ?>
                            </p>
                        </label>
                    </div>

                    <button type="button" class="button button-primary" id="sc-setup-2fa"><?php _e('Setup 2FA', 'sc_events'); ?></button>
                </div>

                <div id="sc-2fa-setup-form" style="display: none; margin-top: 20px;">
                    <!-- Will be populated by JavaScript -->
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo $nonce; ?>';

            // Setup 2FA
            $('#sc-setup-2fa').on('click', function() {
                var method = $('input[name="sc_2fa_method"]:checked').val();
                var btn = $(this);
                btn.prop('disabled', true).text('<?php _e('Loading...', 'sc_events'); ?>');

                $.post(ajaxurl, {
                    action: 'sc_setup_2fa',
                    nonce: nonce,
                    method: method
                }, function(response) {
                    btn.prop('disabled', false).text('<?php _e('Setup 2FA', 'sc_events'); ?>');

                    if (response.success) {
                        var html = '';
                        if (method === 'totp') {
                            html = '<div style="text-align: center;">';
                            html += '<h4><?php _e('Scan this QR code with your authenticator app', 'sc_events'); ?></h4>';
                            html += '<img src="' + response.data.qr_url + '" style="max-width: 200px;">';
                            html += '<p style="margin-top: 15px;"><strong><?php _e('Or enter this code manually:', 'sc_events'); ?></strong><br>';
                            html += '<code style="font-size: 14px;">' + response.data.secret + '</code></p>';
                            html += '</div>';
                        } else {
                            html = '<p><?php _e('A verification code has been sent to your email.', 'sc_events'); ?></p>';
                        }

                        html += '<div style="margin-top: 20px;">';
                        html += '<label><?php _e('Enter verification code:', 'sc_events'); ?></label><br>';
                        html += '<input type="text" id="sc-2fa-code" maxlength="6" style="font-size: 18px; letter-spacing: 5px; width: 150px; text-align: center; margin: 10px 0;">';
                        html += '<br><button type="button" class="button button-primary" id="sc-verify-2fa"><?php _e('Verify & Enable', 'sc_events'); ?></button>';
                        html += '</div>';

                        $('#sc-2fa-setup-form').html(html).show();
                        $('.sc-2fa-setup').hide();
                    } else {
                        alert(response.data || '<?php _e('Error', 'sc_events'); ?>');
                    }
                });
            });

            // Verify setup
            $(document).on('click', '#sc-verify-2fa', function() {
                var code = $('#sc-2fa-code').val();
                var method = $('input[name="sc_2fa_method"]:checked').val();

                if (code.length < 6) {
                    alert('<?php _e('Please enter a valid code', 'sc_events'); ?>');
                    return;
                }

                $(this).prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'sc_verify_2fa_setup',
                    nonce: nonce,
                    code: code,
                    method: method
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data || '<?php _e('Invalid code', 'sc_events'); ?>');
                        $('#sc-verify-2fa').prop('disabled', false);
                    }
                });
            });

            // Disable 2FA
            $('#sc-disable-2fa').on('click', function() {
                var password = prompt('<?php _e('Enter your password to disable 2FA:', 'sc_events'); ?>');
                if (!password) return;

                $.post(ajaxurl, {
                    action: 'sc_disable_2fa',
                    nonce: nonce,
                    password: password
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data || '<?php _e('Error', 'sc_events'); ?>');
                    }
                });
            });

            // Regenerate codes
            $('#sc-regenerate-codes').on('click', function() {
                var password = prompt('<?php _e('Enter your password to regenerate backup codes:', 'sc_events'); ?>');
                if (!password) return;

                $.post(ajaxurl, {
                    action: 'sc_regenerate_backup_codes',
                    nonce: nonce,
                    password: password
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data || '<?php _e('Error', 'sc_events'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Enqueue login scripts
     */
    public static function enqueue_login_scripts() {
        // Add custom styles/scripts for 2FA on login page if needed
    }
}

// Initialize
SC_Two_Factor_Auth::init();
