<?php
/**
 * SC Events API CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing headers
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_CORS_Middleware {

    /**
     * Allowed origins
     * @var array
     */
    private $allowed_origins = ['*'];

    /**
     * Allowed methods
     * @var array
     */
    private $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Allowed headers
     * @var array
     */
    private $allowed_headers = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'X-API-Key'
    ];

    /**
     * Exposed headers
     * @var array
     */
    private $exposed_headers = [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-API-Version'
    ];

    /**
     * Max age for preflight cache (in seconds)
     * @var int
     */
    private $max_age = 86400;

    /**
     * Allow credentials
     * @var bool
     */
    private $allow_credentials = true;

    /**
     * Constructor
     */
    public function __construct() {
        // Load allowed origins from options
        $custom_origins = get_option('sc_api_allowed_origins', '');
        if (!empty($custom_origins)) {
            $this->allowed_origins = array_map('trim', explode(',', $custom_origins));
        }
    }

    /**
     * Handle the middleware
     *
     * @param array $request Request data
     */
    public function handle($request) {
        $this->setHeaders();
    }

    /**
     * Set CORS headers
     */
    private function setHeaders() {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            header('Access-Control-Allow-Origin: ' . ($this->allowed_origins[0] === '*' ? '*' : $origin));
        }

        // Set other CORS headers
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowed_methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowed_headers));
        header('Access-Control-Expose-Headers: ' . implode(', ', $this->exposed_headers));
        header('Access-Control-Max-Age: ' . $this->max_age);

        if ($this->allow_credentials && $this->allowed_origins[0] !== '*') {
            header('Access-Control-Allow-Credentials: true');
        }
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin Request origin
     * @return bool
     */
    private function isOriginAllowed($origin) {
        if (empty($origin)) {
            return true; // Allow same-origin requests
        }

        if (in_array('*', $this->allowed_origins)) {
            return true;
        }

        return in_array($origin, $this->allowed_origins);
    }

    /**
     * Set allowed origins
     *
     * @param array $origins Allowed origins
     * @return self
     */
    public function setAllowedOrigins($origins) {
        $this->allowed_origins = $origins;
        return $this;
    }

    /**
     * Set allowed methods
     *
     * @param array $methods Allowed methods
     * @return self
     */
    public function setAllowedMethods($methods) {
        $this->allowed_methods = $methods;
        return $this;
    }

    /**
     * Set allowed headers
     *
     * @param array $headers Allowed headers
     * @return self
     */
    public function setAllowedHeaders($headers) {
        $this->allowed_headers = $headers;
        return $this;
    }
}
