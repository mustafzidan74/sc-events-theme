<?php
/**
 * SC Events API Authentication
 *
 * Handles JWT token generation and verification
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_API_Auth {

    /**
     * Secret key for JWT signing
     * @var string
     */
    private $secret_key;

    /**
     * Token expiration time in seconds (24 hours)
     * @var int
     */
    private $token_expiry = 86400;

    /**
     * Refresh token expiration time in seconds (30 days)
     * @var int
     */
    private $refresh_token_expiry = 2592000;

    /**
     * Algorithm used for signing
     * @var string
     */
    private $algorithm = 'HS256';

    /**
     * Constructor
     */
    public function __construct() {
        // Get secret key from options or generate one
        $this->secret_key = $this->getSecretKey();
    }

    /**
     * Get or generate the secret key
     *
     * @return string
     */
    private function getSecretKey() {
        $key = get_option('sc_api_secret_key');

        if (empty($key)) {
            // Generate a new secure key
            $key = wp_generate_password(64, true, true);
            update_option('sc_api_secret_key', $key);
        }

        return $key;
    }

    /**
     * Generate a JWT token
     *
     * @param int    $user_id User ID
     * @param string $role    User role
     * @param array  $extra   Extra claims to include
     * @return string JWT token
     */
    public function generateToken($user_id, $role = 'user', $extra = []) {
        $issued_at = time();
        $expiration = $issued_at + $this->token_expiry;

        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ]));

        $payload_data = array_merge([
            'iss' => home_url(),
            'iat' => $issued_at,
            'exp' => $expiration,
            'sub' => $user_id,
            'role' => $role,
            'jti' => $this->generateJti(),
            // Bumped by revokeAllTokens(); a token from an older version is refused.
            'ver' => (int) get_user_meta($user_id, 'sc_api_token_version', true),
        ], $extra);

        $payload = $this->base64UrlEncode(json_encode($payload_data));

        $signature = $this->sign("$header.$payload");

        return "$header.$payload.$signature";
    }

    /**
     * Generate a refresh token
     *
     * @param int $user_id User ID
     * @return string Refresh token
     */
    public function generateRefreshToken($user_id) {
        $token = wp_generate_password(64, false);
        $expiration = time() + $this->refresh_token_expiry;

        // Store refresh token in database
        update_user_meta($user_id, 'sc_api_refresh_token', $token);
        update_user_meta($user_id, 'sc_api_refresh_token_exp', $expiration);

        return $token;
    }

    /**
     * Verify a JWT token
     *
     * @param string $token JWT token
     * @return array|false Token payload or false if invalid
     */
    public function verifyToken($token) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // Verify signature
        $expected_signature = $this->sign("$header.$payload");
        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        // Decode payload
        $payload_data = json_decode($this->base64UrlDecode($payload), true);

        if (!$payload_data) {
            return false;
        }

        // Check expiration
        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
            return false;
        }

        // Check issuer
        if (isset($payload_data['iss']) && $payload_data['iss'] !== home_url()) {
            return false;
        }

        // Signed out everywhere (password changed or reset) since this token was made.
        // Tokens from before versions existed count as version 0.
        $version = (int) get_user_meta((int) ($payload_data['sub'] ?? 0), 'sc_api_token_version', true);
        if ((int) ($payload_data['ver'] ?? 0) !== $version) {
            return false;
        }

        return $payload_data;
    }

    /**
     * Verify a refresh token
     *
     * @param string $token   Refresh token
     * @param int    $user_id User ID
     * @return bool
     */
    public function verifyRefreshToken($token, $user_id) {
        $stored_token = get_user_meta($user_id, 'sc_api_refresh_token', true);
        $expiration = get_user_meta($user_id, 'sc_api_refresh_token_exp', true);

        if (empty($stored_token) || empty($expiration)) {
            return false;
        }

        if ($expiration < time()) {
            // Token expired, clean up
            $this->revokeRefreshToken($user_id);
            return false;
        }

        return hash_equals($stored_token, $token);
    }

    /**
     * Revoke a refresh token
     *
     * @param int $user_id User ID
     */
    public function revokeRefreshToken($user_id) {
        delete_user_meta($user_id, 'sc_api_refresh_token');
        delete_user_meta($user_id, 'sc_api_refresh_token_exp');
    }

    /**
     * Revoke all tokens for a user (logout from all devices)
     *
     * @param int $user_id User ID
     */
    public function revokeAllTokens($user_id) {
        $this->revokeRefreshToken($user_id);

        // Increment the user's token version to invalidate all existing JWTs
        $version = (int) get_user_meta($user_id, 'sc_api_token_version', true);
        update_user_meta($user_id, 'sc_api_token_version', $version + 1);
    }

    /**
     * Authenticate user with credentials
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return array|WP_Error User data with tokens or error
     */
    public function authenticate($username, $password) {
        // Sanitize input
        $username = sanitize_user($username);

        // Try to authenticate
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return $user;
        }

        // Check if user can access API
        if (!$this->canAccessApi($user)) {
            return new WP_Error('access_denied', 'You do not have permission to access the API');
        }

        return $this->issueTokens($user);
    }

    /**
     * Tokens and user summary for a user who has already proved who they are
     * (password above, or a WhatsApp code in SC_Auth_Endpoint).
     *
     * @param WP_User $user
     * @return array
     */
    public function issueTokens($user) {
        $role = $this->getUserRole($user);
        $access_token = $this->generateToken($user->ID, $role);
        $refresh_token = $this->generateRefreshToken($user->ID);

        // Log the login
        $this->logLogin($user->ID);

        return [
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'role' => $role
            ],
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => $this->token_expiry,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Get the bearer token from request headers
     *
     * @return string|null
     */
    public function getBearerToken() {
        $headers = $this->getAuthorizationHeader();

        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        // Check for token in query parameter (not recommended but supported)
        if (isset($_GET['access_token'])) {
            return sanitize_text_field($_GET['access_token']);
        }

        return null;
    }

    /**
     * Get authorization header
     *
     * @return string|null
     */
    private function getAuthorizationHeader() {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $request_headers = apache_request_headers();
            $request_headers = array_combine(
                array_map('ucwords', array_keys($request_headers)),
                array_values($request_headers)
            );
            if (isset($request_headers['Authorization'])) {
                $headers = trim($request_headers['Authorization']);
            }
        }

        return $headers;
    }

    /**
     * Check if user can access API
     *
     * @param WP_User $user User object
     * @return bool
     */
    public function canAccessApi($user) {
        // Allow administrators always
        if (in_array('administrator', $user->roles)) {
            return true;
        }

        // Allow event managers
        if (in_array('event_manager', $user->roles)) {
            return true;
        }

        // Allow scanners
        if (in_array('event_scanner', $user->roles)) {
            return true;
        }

        // Allow if user has specific capability
        if (user_can($user, 'access_event_api')) {
            return true;
        }

        return false;
    }

    /**
     * Get user role for API
     *
     * @param WP_User $user User object
     * @return string
     */
    private function getUserRole($user) {
        if (in_array('administrator', $user->roles)) {
            return 'admin';
        }
        if (in_array('event_manager', $user->roles)) {
            return 'manager';
        }
        if (in_array('event_scanner', $user->roles)) {
            return 'scanner';
        }
        return 'user';
    }

    /**
     * Log user login
     *
     * @param int $user_id User ID
     */
    private function logLogin($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'scev_login_history';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wpdb->insert($table_name, [
                'user_id' => $user_id,
                'ip_address' => $this->getClientIp(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'login_type' => 'api',
                'login_time' => current_time('mysql'),
                'status' => 'success'
            ]);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function getClientIp() {
        return sc_get_client_ip();
    }

    /**
     * Generate unique JWT ID
     *
     * @return string
     */
    private function generateJti() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Sign data with HMAC
     *
     * @param string $data Data to sign
     * @return string Base64URL encoded signature
     */
    private function sign($data) {
        $signature = hash_hmac('sha256', $data, $this->secret_key, true);
        return $this->base64UrlEncode($signature);
    }

    /**
     * Base64 URL encode
     *
     * @param string $data Data to encode
     * @return string
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     *
     * @param string $data Data to decode
     * @return string
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
