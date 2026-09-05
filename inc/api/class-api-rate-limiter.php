<?php
/**
 * SC Events API Rate Limiter
 *
 * Prevents API abuse by limiting request rates
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_API_Rate_Limiter {

    /**
     * Maximum requests per window
     * @var int
     */
    private $max_requests = 100;

    /**
     * Time window in seconds
     * @var int
     */
    private $window = 60;

    /**
     * Prefix for transient keys
     * @var string
     */
    private $prefix = 'sc_api_rate_';

    /**
     * Constructor
     *
     * @param int $max_requests Maximum requests per window
     * @param int $window       Time window in seconds
     */
    public function __construct($max_requests = 100, $window = 60) {
        $this->max_requests = $max_requests;
        $this->window = $window;
    }

    /**
     * Check if request is allowed
     *
     * @param string      $identifier Unique identifier (IP, user ID, etc.)
     * @param string|null $endpoint   Specific endpoint (for per-endpoint limits)
     * @return bool
     */
    public function check($identifier, $endpoint = null) {
        $key = $this->getKey($identifier, $endpoint);
        $data = $this->getData($key);

        // Check if we're in a new window
        if ($data['start'] + $this->window < time()) {
            $this->reset($key);
            return true;
        }

        // Check if limit exceeded
        if ($data['count'] >= $this->max_requests) {
            return false;
        }

        // Increment counter
        $this->increment($key, $data);

        return true;
    }

    /**
     * Get rate limit info for headers
     *
     * @param string      $identifier Unique identifier
     * @param string|null $endpoint   Specific endpoint
     * @return array
     */
    public function getInfo($identifier, $endpoint = null) {
        $key = $this->getKey($identifier, $endpoint);
        $data = $this->getData($key);

        $remaining = max(0, $this->max_requests - $data['count']);
        $reset = $data['start'] + $this->window;

        return [
            'limit' => $this->max_requests,
            'remaining' => $remaining,
            'reset' => $reset
        ];
    }

    /**
     * Set rate limit headers
     *
     * @param string      $identifier Unique identifier
     * @param string|null $endpoint   Specific endpoint
     */
    public function setHeaders($identifier, $endpoint = null) {
        $info = $this->getInfo($identifier, $endpoint);

        header('X-RateLimit-Limit: ' . $info['limit']);
        header('X-RateLimit-Remaining: ' . $info['remaining']);
        header('X-RateLimit-Reset: ' . $info['reset']);
    }

    /**
     * Get time until reset
     *
     * @param string      $identifier Unique identifier
     * @param string|null $endpoint   Specific endpoint
     * @return int Seconds until reset
     */
    public function getRetryAfter($identifier, $endpoint = null) {
        $key = $this->getKey($identifier, $endpoint);
        $data = $this->getData($key);

        return max(0, ($data['start'] + $this->window) - time());
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $key Transient key
     */
    public function reset($key) {
        $data = [
            'count' => 1,
            'start' => time()
        ];
        set_transient($key, $data, $this->window);
    }

    /**
     * Clear all rate limits for an identifier
     *
     * @param string $identifier Unique identifier
     */
    public function clear($identifier) {
        global $wpdb;

        $pattern = $wpdb->esc_like($this->prefix . md5($identifier)) . '%';
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            delete_transient($key);
        }
    }

    /**
     * Get transient key
     *
     * @param string      $identifier Unique identifier
     * @param string|null $endpoint   Specific endpoint
     * @return string
     */
    private function getKey($identifier, $endpoint = null) {
        $key = $this->prefix . md5($identifier);
        if ($endpoint) {
            $key .= '_' . md5($endpoint);
        }
        return $key;
    }

    /**
     * Get stored data
     *
     * @param string $key Transient key
     * @return array
     */
    private function getData($key) {
        $data = get_transient($key);

        if (!$data || !is_array($data)) {
            $data = [
                'count' => 0,
                'start' => time()
            ];
        }

        return $data;
    }

    /**
     * Increment request counter
     *
     * @param string $key  Transient key
     * @param array  $data Current data
     */
    private function increment($key, $data) {
        $data['count']++;
        $remaining_time = ($data['start'] + $this->window) - time();
        set_transient($key, $data, max(1, $remaining_time));
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public static function getClientIp() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Create rate limiter with specific settings
     *
     * @param string $type Rate limiter type
     * @return SC_API_Rate_Limiter
     */
    public static function create($type = 'default') {
        $settings = [
            'default' => [100, 60],       // 100 requests per minute
            'auth' => [10, 60],           // 10 login attempts per minute
            'write' => [30, 60],          // 30 write operations per minute
            'export' => [5, 60],          // 5 exports per minute
            'upload' => [10, 60],         // 10 uploads per minute
            'scanner' => [120, 60],       // 120 scans per minute
            'strict' => [20, 60],         // 20 requests per minute
            'relaxed' => [300, 60],       // 300 requests per minute
        ];

        $config = isset($settings[$type]) ? $settings[$type] : $settings['default'];
        return new self($config[0], $config[1]);
    }
}
