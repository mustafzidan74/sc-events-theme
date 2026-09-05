<?php
/**
 * SC Events API Auth Middleware
 *
 * Handles authentication verification for protected routes
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Auth_Middleware {

    /**
     * Auth handler instance
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
     * Handle the middleware
     *
     * @param array $request Request data
     * @return array|null User payload or null
     */
    public function handle($request) {
        $token = $this->auth->getBearerToken();

        if (!$token) {
            return null;
        }

        $payload = $this->auth->verifyToken($token);

        if (!$payload) {
            return null;
        }

        return $payload;
    }

    /**
     * Verify token and return payload
     *
     * @return array|null
     */
    public function verify() {
        $token = $this->auth->getBearerToken();

        if (!$token) {
            SC_API_Response::unauthorized('No authentication token provided');
        }

        $payload = $this->auth->verifyToken($token);

        if (!$payload) {
            SC_API_Response::unauthorized('Invalid or expired token');
        }

        return $payload;
    }

    /**
     * Check if user has required role
     *
     * @param array  $user          User payload
     * @param string $required_role Required role
     * @return bool
     */
    public function hasRole($user, $required_role) {
        if (!$user || !isset($user['role'])) {
            return false;
        }

        $role_hierarchy = [
            'user' => 1,
            'scanner' => 2,
            'manager' => 3,
            'admin' => 4
        ];

        $user_level = isset($role_hierarchy[$user['role']]) ? $role_hierarchy[$user['role']] : 0;
        $required_level = isset($role_hierarchy[$required_role]) ? $role_hierarchy[$required_role] : 999;

        return $user_level >= $required_level;
    }

    /**
     * Require specific role
     *
     * @param array  $user          User payload
     * @param string $required_role Required role
     */
    public function requireRole($user, $required_role) {
        if (!$this->hasRole($user, $required_role)) {
            SC_API_Response::forbidden('Insufficient permissions. Required role: ' . $required_role);
        }
    }

    /**
     * Check if user can access resource
     *
     * @param array $user        User payload
     * @param int   $resource_id Resource ID
     * @param string $type       Resource type
     * @return bool
     */
    public function canAccess($user, $resource_id, $type = 'event') {
        // Admins and managers can access everything
        if ($this->hasRole($user, 'manager')) {
            return true;
        }

        // Check ownership or specific permissions
        // This would be extended based on your business logic
        return false;
    }

    /**
     * Get current user ID from token
     *
     * @return int|null
     */
    public function getCurrentUserId() {
        $token = $this->auth->getBearerToken();

        if (!$token) {
            return null;
        }

        $payload = $this->auth->verifyToken($token);

        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        return (int) $payload['sub'];
    }
}
