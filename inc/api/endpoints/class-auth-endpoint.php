<?php
/**
 * SC Events API Auth Endpoint
 *
 * Handles authentication operations (login, logout, token refresh, etc.)
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Auth_Endpoint extends SC_Base_Endpoint {

    /**
     * Auth handler
     * @var SC_API_Auth
     */
    private $auth;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new SC_API_Auth();
    }

    /**
     * POST /auth/login
     * User login
     */
    public function login() {
        $this->validate([
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:6'
        ]);

        $username = $this->input('username');
        $password = $this->input('password');

        $result = $this->auth->authenticate($username, $password);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            // Map WordPress error codes to appropriate responses
            switch ($error_code) {
                case 'invalid_username':
                case 'invalid_email':
                    SC_API_Response::error('Invalid username or email', 401, null, 'INVALID_CREDENTIALS');
                    break;
                case 'incorrect_password':
                    SC_API_Response::error('Incorrect password', 401, null, 'INVALID_CREDENTIALS');
                    break;
                case 'access_denied':
                    SC_API_Response::forbidden('You do not have permission to access the API');
                    break;
                default:
                    SC_API_Response::error($result->get_error_message(), 401, null, 'AUTH_FAILED');
            }
        }

        SC_API_Response::success($result, 'Login successful');
    }

    /**
     * POST /auth/logout
     * User logout
     */
    public function logout() {
        $user_id = $this->userId();

        if ($user_id) {
            $this->auth->revokeRefreshToken($user_id);
        }

        SC_API_Response::success(null, 'Logged out successfully');
    }

    /**
     * POST /auth/refresh
     * Refresh access token
     */
    public function refresh() {
        $this->validate([
            'refresh_token' => 'required|string'
        ]);

        $refresh_token = $this->input('refresh_token');
        $user_id = $this->userId();

        if (!$user_id) {
            SC_API_Response::unauthorized('Invalid session');
        }

        if (!$this->auth->verifyRefreshToken($refresh_token, $user_id)) {
            SC_API_Response::unauthorized('Invalid or expired refresh token');
        }

        // Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            SC_API_Response::unauthorized('User not found');
        }

        // Generate new tokens
        $role = $this->getUserRole($user);
        $access_token = $this->auth->generateToken($user_id, $role);
        $new_refresh_token = $this->auth->generateRefreshToken($user_id);

        SC_API_Response::success([
            'access_token' => $access_token,
            'refresh_token' => $new_refresh_token,
            'expires_in' => 86400,
            'token_type' => 'Bearer'
        ], 'Token refreshed successfully');
    }

    /**
     * GET /auth/me
     * Get current user profile
     */
    public function me() {
        $user_id = $this->userId();
        $user = get_userdata($user_id);

        if (!$user) {
            SC_API_Response::notFound('User not found');
        }

        SC_API_Response::success($this->formatUser($user), 'User profile retrieved');
    }

    /**
     * PUT /auth/profile
     * Update user profile
     */
    public function updateProfile() {
        $user_id = $this->userId();

        $this->validate([
            'display_name' => 'string|max:100',
            'email' => 'email',
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50'
        ]);

        $data = $this->sanitize($this->input(), [
            'display_name' => 'text',
            'email' => 'email',
            'first_name' => 'text',
            'last_name' => 'text'
        ]);

        // Check if email is being changed and is unique
        if (!empty($data['email'])) {
            $existing = get_user_by('email', $data['email']);
            if ($existing && $existing->ID !== $user_id) {
                SC_API_Response::validationError([
                    'email' => ['This email is already in use.']
                ]);
            }
        }

        // Prepare update data
        $update_data = ['ID' => $user_id];

        if (!empty($data['display_name'])) {
            $update_data['display_name'] = $data['display_name'];
        }
        if (!empty($data['email'])) {
            $update_data['user_email'] = $data['email'];
        }
        if (!empty($data['first_name'])) {
            $update_data['first_name'] = $data['first_name'];
        }
        if (!empty($data['last_name'])) {
            $update_data['last_name'] = $data['last_name'];
        }

        // Update user
        $result = wp_update_user($update_data);

        if (is_wp_error($result)) {
            SC_API_Response::error($result->get_error_message(), 400);
        }

        $user = get_userdata($user_id);
        SC_API_Response::success($this->formatUser($user), 'Profile updated successfully');
    }

    /**
     * PUT /auth/password
     * Change password
     */
    public function changePassword() {
        $user_id = $this->userId();

        $this->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'new_password_confirmation' => 'required|string|same:new_password'
        ]);

        $current_password = $this->input('current_password');
        $new_password = $this->input('new_password');

        // Verify current password
        $user = get_userdata($user_id);
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            SC_API_Response::validationError([
                'current_password' => ['Current password is incorrect.']
            ]);
        }

        // Update password
        wp_set_password($new_password, $user_id);

        // Revoke all tokens (force re-login on all devices)
        $this->auth->revokeAllTokens($user_id);

        // Generate new tokens for this session
        $role = $this->getUserRole($user);
        $access_token = $this->auth->generateToken($user_id, $role);
        $refresh_token = $this->auth->generateRefreshToken($user_id);

        SC_API_Response::success([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => 86400,
            'token_type' => 'Bearer'
        ], 'Password changed successfully');
    }

    /**
     * POST /auth/register
     * Register new user (if registration is enabled)
     */
    public function register() {
        // Check if registration is allowed
        if (!get_option('users_can_register')) {
            SC_API_Response::forbidden('User registration is disabled');
        }

        $this->validate([
            'username' => 'required|string|min:3|max:60',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|same:password',
            'display_name' => 'string|max:100'
        ]);

        $data = $this->sanitize($this->input(), [
            'username' => 'text',
            'email' => 'email',
            'password' => 'raw',
            'display_name' => 'text'
        ]);

        // Check if username exists
        if (username_exists($data['username'])) {
            SC_API_Response::validationError([
                'username' => ['This username is already taken.']
            ]);
        }

        // Check if email exists
        if (email_exists($data['email'])) {
            SC_API_Response::validationError([
                'email' => ['This email is already registered.']
            ]);
        }

        // Create user
        $user_id = wp_create_user($data['username'], $data['password'], $data['email']);

        if (is_wp_error($user_id)) {
            SC_API_Response::error($user_id->get_error_message(), 400);
        }

        // Update display name if provided
        if (!empty($data['display_name'])) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $data['display_name']
            ]);
        }

        // Get user and generate tokens
        $user = get_userdata($user_id);
        $role = $this->getUserRole($user);
        $access_token = $this->auth->generateToken($user_id, $role);
        $refresh_token = $this->auth->generateRefreshToken($user_id);

        SC_API_Response::created([
            'user' => $this->formatUser($user),
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => 86400,
            'token_type' => 'Bearer'
        ], 'Registration successful');
    }

    /**
     * POST /auth/forgot-password
     * Request password reset
     */
    public function forgotPassword() {
        $this->validate([
            'email' => 'required|email'
        ]);

        $email = sanitize_email($this->input('email'));
        $user = get_user_by('email', $email);

        // Always return success to prevent email enumeration
        if (!$user) {
            SC_API_Response::success(null, 'If that email exists, a reset link has been sent.');
        }

        // Generate reset key
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            SC_API_Response::success(null, 'If that email exists, a reset link has been sent.');
        }

        // Send reset email
        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

        $message = sprintf(
            "Someone requested a password reset for your account.\n\n" .
            "If this was you, click the link below to reset your password:\n%s\n\n" .
            "If you didn't request this, you can ignore this email.",
            $reset_url
        );

        $sent = wp_mail(
            $email,
            'Password Reset Request',
            $message
        );

        SC_API_Response::success(null, 'If that email exists, a reset link has been sent.');
    }

    /**
     * POST /auth/reset-password
     * Reset password with token
     */
    public function resetPassword() {
        $this->validate([
            'email' => 'required|email',
            'key' => 'required|string',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|same:password'
        ]);

        $email = sanitize_email($this->input('email'));
        $key = sanitize_text_field($this->input('key'));
        $password = $this->input('password');

        $user = get_user_by('email', $email);

        if (!$user) {
            SC_API_Response::error('Invalid reset request', 400);
        }

        // Verify reset key
        $check = check_password_reset_key($key, $user->user_login);

        if (is_wp_error($check)) {
            SC_API_Response::error('Invalid or expired reset key', 400);
        }

        // Reset password
        reset_password($user, $password);

        SC_API_Response::success(null, 'Password has been reset successfully. You can now login.');
    }

    /**
     * Format user data for response
     *
     * @param WP_User $user User object
     * @return array
     */
    private function formatUser($user) {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $this->getUserRole($user),
            'avatar_url' => get_avatar_url($user->ID, ['size' => 96]),
            'registered_at' => $this->formatDate($user->user_registered)
        ];
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
}
