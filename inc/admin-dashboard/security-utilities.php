<?php
/**
 * Security Utilities for Event Manager Dashboard
 *
 * Comprehensive security layer including:
 * - Enhanced CSRF protection
 * - SQL injection prevention
 * - XSS protection
 * - Input sanitization
 * - Security headers
 * - Brute force protection
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================
 * ENHANCED CSRF PROTECTION
 * ============================================
 */

/**
 * Generate a secure CSRF token with additional entropy
 *
 * @param string $action Action name for the token
 * @return string The CSRF token
 */
function sc_generate_csrf_token($action = 'sc_dashboard_action') {
    // Use WordPress nonce with additional entropy
    $nonce = wp_create_nonce($action);
    $user_id = get_current_user_id();
    $session_token = wp_get_session_token();

    // Add extra entropy for high-security actions
    $extra = md5($user_id . $session_token . wp_salt('auth'));

    return $nonce . ':' . substr($extra, 0, 8);
}

/**
 * Verify enhanced CSRF token
 *
 * @param string $token The token to verify
 * @param string $action Action name
 * @return bool True if valid
 */
function sc_verify_csrf_token($token, $action = 'sc_dashboard_action') {
    if (empty($token)) {
        return false;
    }

    // Handle both standard nonces and enhanced tokens
    if (strpos($token, ':') !== false) {
        list($nonce, $extra) = explode(':', $token, 2);
    } else {
        $nonce = $token;
    }

    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * Standard AJAX security check - combines nonce and permission check
 *
 * @param string $nonce_action The nonce action to verify
 * @param string $capability Required capability (default: none, checks event_manager)
 * @param string $nonce_key POST key for nonce (default: 'nonce')
 * @return bool|void Returns true if valid, sends JSON error if not
 */
function sc_verify_ajax_request($nonce_action = 'sc_dashboard_nonce', $capability = '', $nonce_key = 'nonce') {
    // Verify nonce
    if (!isset($_POST[$nonce_key]) || !wp_verify_nonce($_POST[$nonce_key], $nonce_action)) {
        wp_send_json_error(array(
            'message' => __('Security check failed. Please refresh the page and try again.', 'sc_events'),
            'code' => 'invalid_nonce'
        ), 403);
    }

    // Check capability if specified
    if (!empty($capability)) {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'sc_events'),
                'code' => 'insufficient_permissions'
            ), 403);
        }
    } else {
        // Default: Check if event manager
        if (class_exists('SC_Event_Manager_Dashboard') && !SC_Event_Manager_Dashboard::is_event_manager()) {
            wp_send_json_error(array(
                'message' => __('Permission denied.', 'sc_events'),
                'code' => 'not_event_manager'
            ), 403);
        }
    }

    return true;
}

/**
 * Double-submit cookie pattern for additional CSRF protection
 * Call this on page load to set the cookie
 */
function sc_set_csrf_cookie() {
    if (!is_user_logged_in()) {
        return;
    }

    $token = wp_create_nonce('sc_csrf_cookie');
    $cookie_name = 'sc_csrf_' . COOKIEHASH;

    if (!isset($_COOKIE[$cookie_name]) || $_COOKIE[$cookie_name] !== $token) {
        setcookie(
            $cookie_name,
            $token,
            array(
                'expires' => time() + DAY_IN_SECONDS,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            )
        );
    }
}
add_action('init', 'sc_set_csrf_cookie');

/**
 * ============================================
 * BRUTE FORCE PROTECTION
 * ============================================
 */

/**
 * Get brute force protection configuration
 */
function sc_get_brute_force_config() {
    return apply_filters('sc_brute_force_config', array(
        'max_attempts' => 5,           // Maximum login attempts
        'lockout_duration' => 900,     // Lockout duration in seconds (15 minutes)
        'attempt_window' => 300,       // Time window to count attempts (5 minutes)
        // Off: the lock is per account now, so a stranger typing a scanner's email wrong must not
        // keep that scanner out for hours on the event day.
        'progressive_lockout' => false, // Increase lockout with each subsequent lockout
        'notify_admin' => true,        // Notify admin of lockouts
        'whitelist_ips' => array(),    // IPs that bypass lockout
    ));
}

/**
 * Check if IP is currently locked out
 *
 * @param string|null $ip IP address (defaults to current client IP)
 * @return array|false False if not locked, array with lockout info if locked
 */
function sc_is_ip_locked_out($ip = null) {
    if ($ip === null) {
        $ip = sc_get_client_ip();
    }

    $config = sc_get_brute_force_config();

    // Check whitelist
    if (in_array($ip, $config['whitelist_ips'])) {
        return false;
    }

    $lockout_key = 'sc_lockout_' . md5($ip);
    $lockout_data = get_transient($lockout_key);

    if ($lockout_data && isset($lockout_data['locked_until'])) {
        if (time() < $lockout_data['locked_until']) {
            return array(
                'locked' => true,
                'remaining' => $lockout_data['locked_until'] - time(),
                'attempts' => $lockout_data['attempts'] ?? 0,
                'lockout_count' => $lockout_data['lockout_count'] ?? 1,
            );
        }
    }

    return false;
}

/**
 * Record a failed login attempt
 *
 * @param string|null $ip IP address
 * @param string $username Attempted username
 * @return array Current attempt data
 */
function sc_record_failed_login($ip = null, $username = '') {
    if ($ip === null) {
        $ip = sc_get_client_ip();
    }

    $config = sc_get_brute_force_config();
    $attempt_key = 'sc_attempts_' . md5($ip);
    $lockout_key = 'sc_lockout_' . md5($ip);

    // Get current attempts
    $attempts = get_transient($attempt_key);
    if (!$attempts) {
        $attempts = array(
            'count' => 0,
            'first_attempt' => time(),
            'usernames' => array(),
        );
    }

    // Increment attempt count
    $attempts['count']++;
    $attempts['last_attempt'] = time();
    $attempts['usernames'][] = sanitize_user($username);
    $attempts['usernames'] = array_unique(array_slice($attempts['usernames'], -10)); // Keep last 10

    // Save attempts
    set_transient($attempt_key, $attempts, $config['attempt_window']);

    // Check if lockout threshold reached
    if ($attempts['count'] >= $config['max_attempts']) {
        // Get previous lockout count for progressive lockout
        $prev_lockout = get_transient($lockout_key);
        $lockout_count = ($prev_lockout && isset($prev_lockout['lockout_count']))
            ? $prev_lockout['lockout_count'] + 1
            : 1;

        // Calculate lockout duration (progressive if enabled)
        $lockout_duration = $config['lockout_duration'];
        if ($config['progressive_lockout']) {
            $lockout_duration = min($lockout_duration * $lockout_count, 86400); // Max 24 hours
        }

        // Set lockout
        $lockout_data = array(
            'locked_until' => time() + $lockout_duration,
            'attempts' => $attempts['count'],
            'lockout_count' => $lockout_count,
            'ip' => $ip,
            'usernames' => $attempts['usernames'],
        );
        set_transient($lockout_key, $lockout_data, $lockout_duration + 3600);

        // Clear attempts counter
        delete_transient($attempt_key);

        // Notify admin if configured
        if ($config['notify_admin'] && $lockout_count >= 3) {
            sc_notify_admin_of_lockout($lockout_data);
        }

        // Log the lockout
        error_log(sprintf(
            '[SC Security] IP %s locked out for %d minutes after %d failed attempts. Lockout #%d',
            $ip,
            $lockout_duration / 60,
            $attempts['count'],
            $lockout_count
        ));

        return array(
            'locked' => true,
            'duration' => $lockout_duration,
            'lockout_count' => $lockout_count,
        );
    }

    return array(
        'locked' => false,
        'attempts' => $attempts['count'],
        'remaining_attempts' => $config['max_attempts'] - $attempts['count'],
    );
}

/**
 * Record successful login (clears failed attempts)
 *
 * @param string|null $ip IP address
 */
function sc_record_successful_login($ip = null) {
    if ($ip === null) {
        $ip = sc_get_client_ip();
    }

    $attempt_key = 'sc_attempts_' . md5($ip);
    delete_transient($attempt_key);
}

/**
 * Notify admin of repeated lockouts
 *
 * @param array $lockout_data Lockout information
 */
function sc_notify_admin_of_lockout($lockout_data) {
    // At most one alert every 6 hours: brute-force bursts from many IPs
    // used to queue one email per lockout (1,300+ alerts in 6 weeks).
    if (get_transient('sc_lockout_alert_sent')) {
        return;
    }
    set_transient('sc_lockout_alert_sent', 1, 6 * HOUR_IN_SECONDS);

    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');

    $subject = sprintf(__('[%s] Security Alert: Multiple Login Lockouts', 'sc_events'), $site_name);

    $message = sprintf(
        __("Security Alert\n\nIP Address: %s\nLockout Count: %d\nAttempted Usernames: %s\n\nThis IP has been locked out multiple times due to failed login attempts.\n\nIf this is a legitimate user, you can whitelist their IP in the security settings.\n\nTime: %s", 'sc_events'),
        $lockout_data['ip'],
        $lockout_data['lockout_count'],
        implode(', ', $lockout_data['usernames']),
        current_time('mysql')
    );

    // Use email queue if available
    if (function_exists('sc_queue_notification')) {
        sc_queue_notification($admin_email, $subject, nl2br($message), 'security_alert');
    } else {
        wp_mail($admin_email, $subject, $message);
    }
}

/**
 * Clear lockout for an IP (admin function)
 *
 * @param string $ip IP address to unlock
 * @return bool Success
 */
function sc_clear_ip_lockout($ip) {
    if (!current_user_can('administrator')) {
        return false;
    }

    $attempt_key = 'sc_attempts_' . md5($ip);
    $lockout_key = 'sc_lockout_' . md5($ip);

    delete_transient($attempt_key);
    delete_transient($lockout_key);

    return true;
}

/**
 * What wrong passwords are counted against: the account being tried (its email, however it was
 * typed), not the IP. At the venue the whole audience and the scanner team share one Wi-Fi
 * address, and a few typos must not lock everybody out. The sc_*_ip_* helpers take this key in
 * place of an IP.
 */
function sc_login_lock_key($username) {
    $username = trim((string) $username);
    $user = is_email($username) ? get_user_by('email', $username) : get_user_by('login', $username);
    return 'account:' . strtolower($user ? $user->user_email : $username);
}

/**
 * Hook into WordPress login to apply brute force protection
 */
add_filter('authenticate', 'sc_check_brute_force_on_login', 30, 3);
function sc_check_brute_force_on_login($user, $username, $password) {
    // Skip if already an error or no username
    if (is_wp_error($user) || empty($username)) {
        return $user;
    }

    // Check if this account is locked out
    $lockout = sc_is_ip_locked_out(sc_login_lock_key($username));
    if ($lockout) {
        return new WP_Error(
            'sc_locked_out',
            sprintf(
                __('Too many failed login attempts. Please try again in %d minutes.', 'sc_events'),
                ceil($lockout['remaining'] / 60)
            )
        );
    }

    return $user;
}

/**
 * Record failed login attempts
 */
add_action('wp_login_failed', 'sc_handle_failed_login');
function sc_handle_failed_login($username) {
    sc_record_failed_login(sc_login_lock_key($username), $username);
}

/**
 * Clear attempts on successful login
 */
add_action('wp_login', 'sc_handle_successful_login', 10, 2);
function sc_handle_successful_login($user_login, $user) {
    sc_record_successful_login(sc_login_lock_key($user->user_email));
}

/**
 * ============================================
 * INPUT SANITIZATION HELPERS
 * ============================================
 */

/**
 * Sanitize and validate an integer with range checking
 *
 * @param mixed $value Value to sanitize
 * @param int $min Minimum allowed value
 * @param int $max Maximum allowed value
 * @param int $default Default if invalid
 * @return int Sanitized integer
 */
function sc_sanitize_int($value, $min = null, $max = null, $default = 0) {
    $value = intval($value);

    if ($min !== null && $value < $min) {
        return $default;
    }

    if ($max !== null && $value > $max) {
        return $default;
    }

    return $value;
}

/**
 * Sanitize a float with range checking
 *
 * @param mixed $value Value to sanitize
 * @param float $min Minimum allowed value
 * @param float $max Maximum allowed value
 * @param float $default Default if invalid
 * @return float Sanitized float
 */
function sc_sanitize_float($value, $min = null, $max = null, $default = 0.0) {
    $value = floatval($value);

    if ($min !== null && $value < $min) {
        return $default;
    }

    if ($max !== null && $value > $max) {
        return $default;
    }

    return $value;
}

/**
 * Sanitize email with validation
 *
 * @param string $email Email to sanitize
 * @return string|false Sanitized email or false if invalid
 */
function sc_sanitize_email($email) {
    $email = sanitize_email($email);

    if (!is_email($email)) {
        return false;
    }

    return $email;
}

/**
 * Sanitize phone number
 *
 * @param string $phone Phone number to sanitize
 * @return string Sanitized phone number
 */
function sc_sanitize_phone($phone) {
    // Remove all non-numeric characters except + at the beginning
    $phone = preg_replace('/[^\d+]/', '', $phone);

    // Ensure + is only at the beginning
    if (strpos($phone, '+') !== false && strpos($phone, '+') !== 0) {
        $phone = str_replace('+', '', $phone);
    }

    return $phone;
}

/**
 * Sanitize date string
 *
 * @param string $date Date string
 * @param string $format Expected format (default: Y-m-d)
 * @return string|false Sanitized date or false if invalid
 */
function sc_sanitize_date($date, $format = 'Y-m-d') {
    $date = sanitize_text_field($date);

    $datetime = DateTime::createFromFormat($format, $date);
    if (!$datetime || $datetime->format($format) !== $date) {
        return false;
    }

    return $date;
}

/**
 * Sanitize array of IDs
 *
 * @param array $ids Array of IDs
 * @return array Sanitized array of positive integers
 */
function sc_sanitize_id_array($ids) {
    if (!is_array($ids)) {
        return array();
    }

    return array_filter(array_map('absint', $ids));
}

/**
 * Sanitize value against a whitelist
 *
 * @param mixed $value Value to check
 * @param array $allowed Allowed values
 * @param mixed $default Default if not in whitelist
 * @return mixed Sanitized value
 */
function sc_sanitize_whitelist($value, $allowed, $default = null) {
    if (in_array($value, $allowed, true)) {
        return $value;
    }

    return $default !== null ? $default : (isset($allowed[0]) ? $allowed[0] : null);
}

/**
 * Sanitize HTML content (allows safe tags)
 *
 * @param string $content HTML content
 * @param string $context Context: 'basic', 'post', 'strip'
 * @return string Sanitized HTML
 */
function sc_sanitize_html($content, $context = 'basic') {
    switch ($context) {
        case 'strip':
            return wp_strip_all_tags($content);

        case 'post':
            return wp_kses_post($content);

        case 'basic':
        default:
            $allowed = array(
                'a' => array('href' => array(), 'title' => array(), 'target' => array()),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'b' => array(),
                'i' => array(),
                'p' => array(),
                'span' => array('class' => array()),
            );
            return wp_kses($content, $allowed);
    }
}

/**
 * ============================================
 * XSS PROTECTION UTILITIES
 * ============================================
 */

/**
 * Escape output for HTML context
 *
 * @param string $value Value to escape
 * @return string Escaped value
 */
function sc_esc_html($value) {
    return esc_html($value);
}

/**
 * Escape output for HTML attribute context
 *
 * @param string $value Value to escape
 * @return string Escaped value
 */
function sc_esc_attr($value) {
    return esc_attr($value);
}

/**
 * Escape URL
 *
 * @param string $url URL to escape
 * @param array $protocols Allowed protocols
 * @return string Escaped URL
 */
function sc_esc_url($url, $protocols = null) {
    if ($protocols === null) {
        $protocols = array('http', 'https', 'mailto', 'tel');
    }

    return esc_url($url, $protocols);
}

/**
 * Escape for JavaScript context
 *
 * @param string $value Value to escape
 * @return string Escaped value for JS
 */
function sc_esc_js($value) {
    return esc_js($value);
}

/**
 * Safe JSON encode for embedding in HTML
 *
 * @param mixed $data Data to encode
 * @return string JSON string safe for HTML embedding
 */
function sc_json_encode_safe($data) {
    $json = wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return $json !== false ? $json : '{}';
}

/**
 * ============================================
 * SQL INJECTION PREVENTION
 * ============================================
 */

/**
 * Safely prepare a LIKE clause for SQL
 *
 * @param string $value Value for LIKE search
 * @param string $type Type: 'both', 'left', 'right'
 * @return string Prepared LIKE value (use with %s in prepare)
 */
function sc_prepare_like($value, $type = 'both') {
    global $wpdb;

    $value = $wpdb->esc_like($value);

    switch ($type) {
        case 'left':
            return '%' . $value;
        case 'right':
            return $value . '%';
        case 'both':
        default:
            return '%' . $value . '%';
    }
}

/**
 * Safely prepare IN clause for SQL
 *
 * @param array $values Array of values
 * @param string $type Value type: 'int', 'string'
 * @return string Prepared IN clause values
 */
function sc_prepare_in_clause($values, $type = 'int') {
    global $wpdb;

    if (!is_array($values) || empty($values)) {
        return "''"; // Return empty string to prevent SQL errors
    }

    if ($type === 'int') {
        $values = array_map('intval', $values);
        return implode(',', $values);
    }

    // String type - properly escape each value
    $escaped = array();
    foreach ($values as $value) {
        $escaped[] = $wpdb->prepare('%s', $value);
    }

    return implode(',', $escaped);
}

/**
 * Validate and sanitize ORDER BY clause
 *
 * @param string $orderby Requested order by column
 * @param array $allowed_columns Whitelist of allowed columns
 * @param string $default Default column
 * @return string Safe column name
 */
function sc_sanitize_orderby($orderby, $allowed_columns, $default = 'ID') {
    $orderby = sanitize_key($orderby);

    if (in_array($orderby, $allowed_columns, true)) {
        return $orderby;
    }

    return $default;
}

/**
 * Validate ORDER direction
 *
 * @param string $order Order direction
 * @return string 'ASC' or 'DESC'
 */
function sc_sanitize_order($order) {
    return strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
}

/**
 * ============================================
 * SECURITY HEADERS
 * ============================================
 */

/**
 * Add security headers to responses
 */
add_action('send_headers', 'sc_add_security_headers');
function sc_add_security_headers() {
    // Apply security headers to all frontend and dashboard pages
    if (is_admin() && !wp_doing_ajax()) {
        return; // Skip WordPress admin pages (they have their own headers)
    }

    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Enable XSS filter
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions policy — allow camera/microphone for self (needed for QR scanner),
    // disable geolocation. Empty parentheses block all origins; (self) allows our own.
    header('Permissions-Policy: geolocation=(), microphone=(self), camera=(self)');

    // Content Security Policy (restrictive but allows necessary resources)
    // Customize based on your needs
    if (apply_filters('sc_enable_csp_header', false)) {
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-ancestors 'self';";

        header('Content-Security-Policy: ' . $csp);
    }
}

/**
 * Check if current page is a dashboard page
 *
 * @return bool
 */
function sc_is_dashboard_page() {
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($current_url, 'event-manager-dashboard') !== false;
}

/**
 * Add security headers to AJAX responses
 */
add_action('admin_init', 'sc_add_ajax_security_headers', 1);
function sc_add_ajax_security_headers() {
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        return;
    }

    $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';

    // Only apply to our actions
    if (strpos($action, 'sc_') !== 0 && strpos($action, 'event_manager') !== 0) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

/**
 * ============================================
 * ADDITIONAL SECURITY UTILITIES
 * ============================================
 */

/**
 * Generate a secure random token
 *
 * @param int $length Token length
 * @return string Random token
 */
function sc_generate_secure_token($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    }

    return wp_generate_password($length, false);
}

/**
 * Verify request origin (simple CORS check)
 *
 * @return bool True if request is from same origin
 */
function sc_verify_request_origin() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    $site_url = site_url();
    $site_host = parse_url($site_url, PHP_URL_HOST);

    if (!empty($origin)) {
        $origin_host = parse_url($origin, PHP_URL_HOST);
        return $origin_host === $site_host;
    }

    if (!empty($referer)) {
        $referer_host = parse_url($referer, PHP_URL_HOST);
        return $referer_host === $site_host;
    }

    // No origin or referer - could be direct request
    return true;
}

/**
 * Log security event
 *
 * @param string $event_type Event type
 * @param string $message Event message
 * @param array $context Additional context
 */
function sc_log_security_event($event_type, $message, $context = array()) {
    $log_entry = sprintf(
        '[SC Security] [%s] %s | IP: %s | User: %d | Context: %s',
        strtoupper($event_type),
        $message,
        sc_get_client_ip(),
        get_current_user_id(),
        wp_json_encode($context)
    );

    error_log($log_entry);

    // Optionally store in database for audit trail
    if (apply_filters('sc_store_security_logs', false)) {
        sc_store_security_log($event_type, $message, $context);
    }
}

/**
 * Store security log in database
 *
 * @param string $event_type Event type
 * @param string $message Message
 * @param array $context Context
 */
function sc_store_security_log($event_type, $message, $context) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sc_security_logs';

    // Create table if not exists (lazy creation)
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            message text NOT NULL,
            ip_address varchar(45),
            user_id bigint(20) unsigned,
            context longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $wpdb->insert(
        $table_name,
        array(
            'event_type' => sanitize_key($event_type),
            'message' => sanitize_text_field($message),
            'ip_address' => sc_get_client_ip(),
            'user_id' => get_current_user_id(),
            'context' => wp_json_encode($context),
            'created_at' => current_time('mysql'),
        ),
        array('%s', '%s', '%s', '%d', '%s', '%s')
    );
}

/**
 * Validate file upload security
 *
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array|WP_Error Validated file info or error
 */
function sc_validate_file_upload($file, $allowed_types = array(), $max_size = 0) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => __('File exceeds server upload limit.', 'sc_events'),
            UPLOAD_ERR_FORM_SIZE => __('File exceeds form upload limit.', 'sc_events'),
            UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'sc_events'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'sc_events'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder.', 'sc_events'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'sc_events'),
            UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'sc_events'),
        );

        $message = isset($error_messages[$file['error']])
            ? $error_messages[$file['error']]
            : __('Unknown upload error.', 'sc_events');

        return new WP_Error('upload_error', $message);
    }

    // Check file size
    if ($max_size > 0 && $file['size'] > $max_size) {
        return new WP_Error(
            'file_too_large',
            sprintf(__('File size exceeds maximum allowed size of %s.', 'sc_events'), size_format($max_size))
        );
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actual_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!empty($allowed_types) && !in_array($actual_type, $allowed_types, true)) {
        return new WP_Error(
            'invalid_file_type',
            __('File type is not allowed.', 'sc_events')
        );
    }

    // Check for PHP in file content (prevent upload of disguised PHP files)
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if (preg_match('/<\?php|<\?=/i', $content)) {
        return new WP_Error(
            'suspicious_content',
            __('File contains suspicious content.', 'sc_events')
        );
    }

    return array(
        'name' => sanitize_file_name($file['name']),
        'type' => $actual_type,
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name'],
    );
}

/**
 * Sanitize filename for security
 *
 * @param string $filename Original filename
 * @return string Sanitized filename
 */
function sc_sanitize_filename($filename) {
    // Remove any path components
    $filename = basename($filename);

    // Use WordPress sanitization
    $filename = sanitize_file_name($filename);

    // Remove potentially dangerous extensions
    $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cmd');
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $dangerous_extensions, true)) {
        $filename .= '.blocked';
    }

    return $filename;
}

// sc_get_client_ip() lives in inc/sc-client-ip.php so the SHORTINIT API (api.php) can use it too.
require_once dirname(__DIR__) . '/sc-client-ip.php';
