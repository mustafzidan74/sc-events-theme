<?php
/**
 * SC Events API Base Endpoint
 *
 * Base class for all API endpoints with common functionality
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Base_Endpoint {

    /**
     * Request data
     * @var array
     */
    protected $request = [];

    /**
     * Route parameters
     * @var array
     */
    protected $params = [];

    /**
     * Authenticated user data
     * @var array|null
     */
    protected $user = null;

    /**
     * Set request data
     *
     * @param array $request Request data
     */
    public function setRequest($request) {
        $this->request = $request;
    }

    /**
     * Set route parameters
     *
     * @param array $params Route parameters
     */
    public function setParams($params) {
        $this->params = $params;
    }

    /**
     * Set authenticated user
     *
     * @param array|null $user User data
     */
    public function setUser($user) {
        $this->user = $user;
    }

    /**
     * Get request body data
     *
     * @param string|null $key     Specific key to get
     * @param mixed       $default Default value if key not found
     * @return mixed
     */
    protected function input($key = null, $default = null) {
        $body = isset($this->request['body']) ? $this->request['body'] : [];

        if ($key === null) {
            return $body;
        }

        return isset($body[$key]) ? $body[$key] : $default;
    }

    /**
     * Get query parameter
     *
     * @param string|null $key     Specific key to get
     * @param mixed       $default Default value if key not found
     * @return mixed
     */
    protected function query($key = null, $default = null) {
        $query = isset($this->request['query']) ? $this->request['query'] : [];

        if ($key === null) {
            return $query;
        }

        return isset($query[$key]) ? $query[$key] : $default;
    }

    /**
     * Get route parameter
     *
     * @param string $key     Parameter key
     * @param mixed  $default Default value
     * @return mixed
     */
    protected function param($key, $default = null) {
        return isset($this->params[$key]) ? $this->params[$key] : $default;
    }

    /**
     * Get request header
     *
     * @param string $key Header name
     * @return string|null
     */
    protected function header($key) {
        $headers = isset($this->request['headers']) ? $this->request['headers'] : [];
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $key))));
        return isset($headers[$key]) ? $headers[$key] : null;
    }

    /**
     * Get current user ID
     *
     * @return int|null
     */
    protected function userId() {
        return isset($this->user['sub']) ? (int) $this->user['sub'] : null;
    }

    /**
     * Get current user role
     *
     * @return string|null
     */
    protected function userRole() {
        return isset($this->user['role']) ? $this->user['role'] : null;
    }

    /**
     * Check if user has role
     *
     * @param string $role Required role
     * @return bool
     */
    protected function hasRole($role) {
        $role_hierarchy = ['user' => 1, 'scanner' => 2, 'manager' => 3, 'admin' => 4];
        $user_level = isset($role_hierarchy[$this->userRole()]) ? $role_hierarchy[$this->userRole()] : 0;
        $required_level = isset($role_hierarchy[$role]) ? $role_hierarchy[$role] : 999;
        return $user_level >= $required_level;
    }

    /**
     * Validate request data
     *
     * @param array $rules Validation rules
     * @return bool
     */
    protected function validate($rules) {
        $validator = SC_API_Validator::make($this->input(), $rules);

        if ($validator->fails()) {
            SC_API_Response::validationError($validator->getReadableErrors());
        }

        return true;
    }

    /**
     * Get pagination parameters
     *
     * @return array
     */
    protected function getPagination() {
        $page = max(1, (int) $this->query('page', 1));
        $per_page = min(100, max(1, (int) $this->query('per_page', 10)));

        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];
    }

    /**
     * Get sorting parameters
     *
     * @param array $allowed Allowed sort fields
     * @param string $default_field Default sort field
     * @param string $default_order Default sort order
     * @return array
     */
    protected function getSorting($allowed = [], $default_field = 'id', $default_order = 'DESC') {
        $sort_by = $this->query('sort_by', $default_field);
        $sort_order = strtoupper($this->query('sort_order', $default_order));

        // Validate sort field
        if (!empty($allowed) && !in_array($sort_by, $allowed)) {
            $sort_by = $default_field;
        }

        // Validate sort order
        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = $default_order;
        }

        return [
            'field' => $sort_by,
            'order' => $sort_order
        ];
    }

    /**
     * Get filter parameters
     *
     * @param array $allowed Allowed filter fields
     * @return array
     */
    protected function getFilters($allowed = []) {
        $filters = [];
        $query = $this->query();

        foreach ($query as $key => $value) {
            // Skip pagination and sorting params
            if (in_array($key, ['page', 'per_page', 'sort_by', 'sort_order', 'route'])) {
                continue;
            }

            // Check if allowed
            if (!empty($allowed) && !in_array($key, $allowed)) {
                continue;
            }

            $filters[$key] = sanitize_text_field($value);
        }

        return $filters;
    }

    /**
     * Sanitize input array
     *
     * @param array $data Data to sanitize
     * @param array $rules Sanitization rules (field => type)
     * @return array
     */
    protected function sanitize($data, $rules = []) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $type = isset($rules[$key]) ? $rules[$key] : 'text';

            switch ($type) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'int':
                case 'integer':
                    $sanitized[$key] = (int) $value;
                    break;
                case 'float':
                case 'decimal':
                    $sanitized[$key] = (float) $value;
                    break;
                case 'bool':
                case 'boolean':
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'html':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'array':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                    break;
                case 'raw':
                    $sanitized[$key] = $value;
                    break;
                case 'text':
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Format date for response
     *
     * @param string $date Date string
     * @param string $format Output format
     * @return string|null
     */
    protected function formatDate($date, $format = 'c') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return gmdate($format, $timestamp);
    }

    /**
     * Health check endpoint
     */
    public function health() {
        SC_API_Response::success([
            'status' => 'healthy',
            'timestamp' => gmdate('c'),
            'version' => SC_API_VERSION
        ], 'API is healthy');
    }

    /**
     * Version endpoint
     */
    public function version() {
        SC_API_Response::success([
            'api_version' => SC_API_VERSION,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version')
        ], 'Version information');
    }
}
