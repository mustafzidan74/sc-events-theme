<?php
/**
 * SC Events API Log Middleware
 *
 * Logs API requests for monitoring and debugging
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Log_Middleware {

    /**
     * Enable logging
     * @var bool
     */
    private $enabled = true;

    /**
     * Log file path
     * @var string
     */
    private $log_file;

    /**
     * Request start time
     * @var float
     */
    private $start_time;

    /**
     * Constructor
     */
    public function __construct() {
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
        $this->log_file = WP_CONTENT_DIR . '/api-logs/api-' . date('Y-m-d') . '.log';
        $this->start_time = microtime(true);
    }

    /**
     * Handle the middleware
     *
     * @param array $request Request data
     */
    public function handle($request) {
        if (!$this->enabled) {
            return;
        }

        // Register shutdown function to log after response
        register_shutdown_function([$this, 'logRequest'], $request);
    }

    /**
     * Log the request
     *
     * @param array $request Request data
     */
    public function logRequest($request) {
        if (!$this->enabled) {
            return;
        }

        $duration = round((microtime(true) - $this->start_time) * 1000, 2);
        $response_code = http_response_code();

        $log_entry = [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'method' => isset($request['method']) ? $request['method'] : $_SERVER['REQUEST_METHOD'],
            'uri' => isset($request['uri']) ? $request['uri'] : $_SERVER['REQUEST_URI'],
            'ip' => isset($request['ip']) ? $request['ip'] : SC_API_Rate_Limiter::getClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 100) : '',
            'response_code' => $response_code,
            'duration_ms' => $duration,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ];

        // Add user ID if authenticated
        if (isset($request['user_id'])) {
            $log_entry['user_id'] = $request['user_id'];
        }

        $this->writeLog($log_entry);
    }

    /**
     * Write to log file
     *
     * @param array $entry Log entry
     */
    private function writeLog($entry) {
        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);

            // Create .htaccess to protect logs
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }

        // Format log line
        $line = sprintf(
            "[%s] %s %s | %d | %sms | %sMB | %s\n",
            $entry['timestamp'],
            $entry['method'],
            $entry['uri'],
            $entry['response_code'],
            $entry['duration_ms'],
            $entry['memory_mb'],
            $entry['ip']
        );

        // Append to log file
        error_log($line, 3, $this->log_file);

        // Rotate logs if too large (> 10MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 10 * 1024 * 1024) {
            $this->rotateLog();
        }
    }

    /**
     * Rotate log file
     */
    private function rotateLog() {
        $archive = $this->log_file . '.' . time() . '.gz';

        // Compress old log
        $content = file_get_contents($this->log_file);
        $gzipped = gzencode($content, 9);
        file_put_contents($archive, $gzipped);

        // Clear current log
        file_put_contents($this->log_file, '');

        // Clean up old archives (keep last 7 days)
        $this->cleanOldLogs();
    }

    /**
     * Clean old log files
     */
    private function cleanOldLogs() {
        $log_dir = dirname($this->log_file);
        $files = glob($log_dir . '/api-*.log.*.gz');

        $cutoff = time() - (7 * 24 * 60 * 60); // 7 days ago

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    /**
     * Log an error
     *
     * @param string $message Error message
     * @param array  $context Additional context
     */
    public static function error($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/api-logs/api-errors-' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $entry = sprintf(
            "[%s] ERROR: %s | Context: %s\n",
            gmdate('Y-m-d H:i:s'),
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        error_log($entry, 3, $log_file);
    }

    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array  $context Additional context
     */
    public static function info($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/api-logs/api-info-' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $entry = sprintf(
            "[%s] INFO: %s | Context: %s\n",
            gmdate('Y-m-d H:i:s'),
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        error_log($entry, 3, $log_file);
    }
}
