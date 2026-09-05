<?php
/**
 * SC Events Error Logger
 *
 * Comprehensive error logging system for debugging and monitoring
 * - Database logging with severity levels
 * - PHP error/exception capture
 * - Admin log viewer
 * - Log rotation and cleanup
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Error_Logger {

    /**
     * Database table name
     */
    private static $table_name;

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Initialize the logger
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'sc_error_logs';

        // Create table on activation
        add_action('after_switch_theme', array(__CLASS__, 'create_table'));
        add_action('init', array(__CLASS__, 'maybe_create_table'), 1);

        // Register error handlers
        if (get_option('sc_error_logging_enabled', true)) {
            set_error_handler(array(__CLASS__, 'handle_php_error'));
            set_exception_handler(array(__CLASS__, 'handle_exception'));
            register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
        }

        // Admin interface
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('wp_ajax_sc_get_error_logs', array(__CLASS__, 'ajax_get_logs'));
        add_action('wp_ajax_sc_clear_error_logs', array(__CLASS__, 'ajax_clear_logs'));
        add_action('wp_ajax_sc_delete_error_log', array(__CLASS__, 'ajax_delete_log'));

        // Daily cleanup
        add_action('sc_daily_log_cleanup', array(__CLASS__, 'cleanup_old_logs'));
        if (!wp_next_scheduled('sc_daily_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sc_daily_log_cleanup');
        }
    }

    /**
     * Create logs table
     */
    public static function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'sc_error_logs';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT,
            source VARCHAR(255),
            file VARCHAR(500),
            line INT(11),
            user_id BIGINT(20) UNSIGNED,
            ip_address VARCHAR(45),
            request_uri TEXT,
            request_method VARCHAR(10),
            user_agent TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at),
            KEY source (source(191)),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('sc_error_logs_table_version', '1.0.0');
    }

    /**
     * Check and create table if needed
     */
    public static function maybe_create_table() {
        if (get_option('sc_error_logs_table_version', '0') !== '1.0.0') {
            self::create_table();
        }
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $source Source identifier
     */
    public static function log($level, $message, $context = array(), $source = 'sc_events') {
        global $wpdb;

        // Check if logging is enabled
        if (!get_option('sc_error_logging_enabled', true)) {
            return false;
        }

        // Check minimum log level
        $min_level = get_option('sc_min_log_level', self::LEVEL_INFO);
        if (!self::should_log($level, $min_level)) {
            return false;
        }

        // Get caller info
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        $file = isset($caller['file']) ? $caller['file'] : '';
        $line = isset($caller['line']) ? $caller['line'] : 0;

        // Prepare data
        $data = array(
            'level' => $level,
            'message' => self::truncate_message($message, 65535),
            'context' => wp_json_encode($context),
            'source' => $source,
            'file' => self::get_relative_path($file),
            'line' => $line,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => self::get_client_ip(),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert(self::$table_name, $data);

        // Also log to error_log for critical errors
        if (in_array($level, array(self::LEVEL_ERROR, self::LEVEL_CRITICAL))) {
            error_log("SC Events [{$level}]: {$message}");
        }

        return $result !== false;
    }

    /**
     * Shorthand logging methods
     */
    public static function debug($message, $context = array(), $source = 'sc_events') {
        return self::log(self::LEVEL_DEBUG, $message, $context, $source);
    }

    public static function info($message, $context = array(), $source = 'sc_events') {
        return self::log(self::LEVEL_INFO, $message, $context, $source);
    }

    public static function warning($message, $context = array(), $source = 'sc_events') {
        return self::log(self::LEVEL_WARNING, $message, $context, $source);
    }

    public static function error($message, $context = array(), $source = 'sc_events') {
        return self::log(self::LEVEL_ERROR, $message, $context, $source);
    }

    public static function critical($message, $context = array(), $source = 'sc_events') {
        return self::log(self::LEVEL_CRITICAL, $message, $context, $source);
    }

    /**
     * Handle PHP errors
     */
    public static function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Don't log suppressed errors
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Map error types to levels
        $level_map = array(
            E_ERROR => self::LEVEL_CRITICAL,
            E_WARNING => self::LEVEL_WARNING,
            E_PARSE => self::LEVEL_CRITICAL,
            E_NOTICE => self::LEVEL_INFO,
            E_CORE_ERROR => self::LEVEL_CRITICAL,
            E_CORE_WARNING => self::LEVEL_WARNING,
            E_COMPILE_ERROR => self::LEVEL_CRITICAL,
            E_COMPILE_WARNING => self::LEVEL_WARNING,
            E_USER_ERROR => self::LEVEL_ERROR,
            E_USER_WARNING => self::LEVEL_WARNING,
            E_USER_NOTICE => self::LEVEL_INFO,
            E_STRICT => self::LEVEL_DEBUG,
            E_RECOVERABLE_ERROR => self::LEVEL_ERROR,
            E_DEPRECATED => self::LEVEL_DEBUG,
            E_USER_DEPRECATED => self::LEVEL_DEBUG,
        );

        $level = isset($level_map[$errno]) ? $level_map[$errno] : self::LEVEL_ERROR;

        // Only log if it's from our theme
        $theme_dir = get_template_directory();
        if (strpos($errfile, $theme_dir) !== false) {
            self::log($level, $errstr, array(
                'error_type' => $errno,
                'error_type_name' => self::get_error_type_name($errno)
            ), 'php_error');
        }

        // Let PHP handle the error too
        return false;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handle_exception($exception) {
        self::critical($exception->getMessage(), array(
            'exception_class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => self::get_relative_path($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => self::format_trace($exception->getTrace())
        ), 'exception');

        // Re-throw for default handling
        throw $exception;
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handle_shutdown() {
        $error = error_get_last();

        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $theme_dir = get_template_directory();

            if (strpos($error['file'], $theme_dir) !== false) {
                self::critical($error['message'], array(
                    'error_type' => $error['type'],
                    'error_type_name' => self::get_error_type_name($error['type']),
                    'file' => self::get_relative_path($error['file']),
                    'line' => $error['line']
                ), 'fatal_error');
            }
        }
    }

    /**
     * Get logs
     */
    public static function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'level' => '',
            'source' => '',
            'search' => '',
            'user_id' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['level']) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }

        if ($args['source']) {
            $where[] = 'source = %s';
            $values[] = $args['source'];
        }

        if ($args['search']) {
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        $sql = "SELECT * FROM " . self::$table_name . " WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = absint($args['limit']);
        $values[] = absint($args['offset']);

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Count logs
     */
    public static function count_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'level' => '',
            'source' => '',
            'search' => '',
            'user_id' => '',
            'date_from' => '',
            'date_to' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['level']) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }

        if ($args['source']) {
            $where[] = 'source = %s';
            $values[] = $args['source'];
        }

        if ($args['search']) {
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE {$where_sql}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get log statistics
     */
    public static function get_stats() {
        global $wpdb;

        $table = self::$table_name;

        $stats = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'today' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )),
            'by_level' => array(),
            'by_source' => array()
        );

        // By level
        $levels = $wpdb->get_results("SELECT level, COUNT(*) as count FROM {$table} GROUP BY level");
        foreach ($levels as $row) {
            $stats['by_level'][$row->level] = (int) $row->count;
        }

        // By source (top 10)
        $sources = $wpdb->get_results("SELECT source, COUNT(*) as count FROM {$table} GROUP BY source ORDER BY count DESC LIMIT 10");
        foreach ($sources as $row) {
            $stats['by_source'][$row->source] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE " . self::$table_name);
    }

    /**
     * Delete single log
     */
    public static function delete_log($id) {
        global $wpdb;
        return $wpdb->delete(self::$table_name, array('id' => $id), array('%d'));
    }

    /**
     * Cleanup old logs (older than 30 days by default)
     */
    public static function cleanup_old_logs() {
        global $wpdb;

        $days = get_option('sc_log_retention_days', 30);
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table_name . " WHERE created_at < %s",
            $date
        ));
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            __('Error Logs', 'sc_events'),
            __('SC Error Logs', 'sc_events'),
            'manage_options',
            'sc-error-logs',
            array(__CLASS__, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $stats = self::get_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('SC Events Error Logs', 'sc_events'); ?></h1>

            <div class="sc-log-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 24px; font-weight: bold;"><?php echo number_format($stats['total']); ?></div>
                    <div style="color: #666;"><?php _e('Total Logs', 'sc_events'); ?></div>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 24px; font-weight: bold;"><?php echo number_format($stats['today']); ?></div>
                    <div style="color: #666;"><?php _e('Today', 'sc_events'); ?></div>
                </div>
                <?php if (!empty($stats['by_level']['error'])): ?>
                <div class="stat-card" style="background: #fee; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 24px; font-weight: bold; color: #c00;"><?php echo number_format($stats['by_level']['error']); ?></div>
                    <div style="color: #666;"><?php _e('Errors', 'sc_events'); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($stats['by_level']['critical'])): ?>
                <div class="stat-card" style="background: #fcc; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 24px; font-weight: bold; color: #900;"><?php echo number_format($stats['by_level']['critical']); ?></div>
                    <div style="color: #666;"><?php _e('Critical', 'sc_events'); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="sc-log-filters" style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px;">
                <form id="sc-log-filter-form" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Level', 'sc_events'); ?></label>
                        <select name="level" id="log-level">
                            <option value=""><?php _e('All Levels', 'sc_events'); ?></option>
                            <option value="debug"><?php _e('Debug', 'sc_events'); ?></option>
                            <option value="info"><?php _e('Info', 'sc_events'); ?></option>
                            <option value="warning"><?php _e('Warning', 'sc_events'); ?></option>
                            <option value="error"><?php _e('Error', 'sc_events'); ?></option>
                            <option value="critical"><?php _e('Critical', 'sc_events'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Search', 'sc_events'); ?></label>
                        <input type="text" name="search" id="log-search" placeholder="<?php _e('Search logs...', 'sc_events'); ?>">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Date From', 'sc_events'); ?></label>
                        <input type="date" name="date_from" id="log-date-from">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Date To', 'sc_events'); ?></label>
                        <input type="date" name="date_to" id="log-date-to">
                    </div>
                    <button type="submit" class="button button-primary"><?php _e('Filter', 'sc_events'); ?></button>
                    <button type="button" id="sc-clear-logs" class="button" style="margin-left: auto;"><?php _e('Clear All Logs', 'sc_events'); ?></button>
                </form>
            </div>

            <div id="sc-logs-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><?php _e('Level', 'sc_events'); ?></th>
                            <th><?php _e('Message', 'sc_events'); ?></th>
                            <th style="width: 120px;"><?php _e('Source', 'sc_events'); ?></th>
                            <th style="width: 200px;"><?php _e('File', 'sc_events'); ?></th>
                            <th style="width: 150px;"><?php _e('Date', 'sc_events'); ?></th>
                            <th style="width: 80px;"><?php _e('Actions', 'sc_events'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sc-logs-tbody">
                        <tr><td colspan="6" style="text-align: center;"><?php _e('Loading...', 'sc_events'); ?></td></tr>
                    </tbody>
                </table>
                <div id="sc-logs-pagination" style="margin-top: 20px;"></div>
            </div>
        </div>

        <style>
            .sc-log-level {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .sc-log-level-debug { background: #e0e0e0; color: #666; }
            .sc-log-level-info { background: #e3f2fd; color: #1565c0; }
            .sc-log-level-warning { background: #fff3e0; color: #e65100; }
            .sc-log-level-error { background: #ffebee; color: #c62828; }
            .sc-log-level-critical { background: #c62828; color: #fff; }
            .sc-log-context {
                max-height: 100px;
                overflow: auto;
                background: #f5f5f5;
                padding: 5px;
                font-size: 11px;
                margin-top: 5px;
                border-radius: 3px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentPage = 1;
            var perPage = 50;

            function loadLogs() {
                var data = {
                    action: 'sc_get_error_logs',
                    nonce: '<?php echo wp_create_nonce('sc_error_logs'); ?>',
                    level: $('#log-level').val(),
                    search: $('#log-search').val(),
                    date_from: $('#log-date-from').val(),
                    date_to: $('#log-date-to').val(),
                    page: currentPage,
                    per_page: perPage
                };

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        renderLogs(response.data.logs);
                        renderPagination(response.data.total, response.data.pages);
                    }
                });
            }

            function renderLogs(logs) {
                var html = '';
                if (logs.length === 0) {
                    html = '<tr><td colspan="6" style="text-align: center;"><?php _e('No logs found', 'sc_events'); ?></td></tr>';
                } else {
                    $.each(logs, function(i, log) {
                        html += '<tr>';
                        html += '<td><span class="sc-log-level sc-log-level-' + log.level + '">' + log.level + '</span></td>';
                        html += '<td>' + escapeHtml(log.message);
                        if (log.context && log.context !== '[]' && log.context !== 'null') {
                            html += '<div class="sc-log-context"><pre>' + escapeHtml(log.context) + '</pre></div>';
                        }
                        html += '</td>';
                        html += '<td>' + escapeHtml(log.source) + '</td>';
                        html += '<td>' + (log.file ? escapeHtml(log.file) + ':' + log.line : '-') + '</td>';
                        html += '<td>' + log.created_at + '</td>';
                        html += '<td><button class="button button-small sc-delete-log" data-id="' + log.id + '">&times;</button></td>';
                        html += '</tr>';
                    });
                }
                $('#sc-logs-tbody').html(html);
            }

            function renderPagination(total, pages) {
                var html = '';
                if (pages > 1) {
                    html += '<span><?php _e('Page', 'sc_events'); ?> ' + currentPage + ' / ' + pages + ' (' + total + ' <?php _e('logs', 'sc_events'); ?>)</span> ';
                    if (currentPage > 1) {
                        html += '<button class="button" data-page="' + (currentPage - 1) + '">&laquo; <?php _e('Previous', 'sc_events'); ?></button> ';
                    }
                    if (currentPage < pages) {
                        html += '<button class="button" data-page="' + (currentPage + 1) + '"><?php _e('Next', 'sc_events'); ?> &raquo;</button>';
                    }
                }
                $('#sc-logs-pagination').html(html);
            }

            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Load on page load
            loadLogs();

            // Filter form
            $('#sc-log-filter-form').on('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                loadLogs();
            });

            // Pagination
            $(document).on('click', '#sc-logs-pagination button', function() {
                currentPage = $(this).data('page');
                loadLogs();
            });

            // Delete single log
            $(document).on('click', '.sc-delete-log', function() {
                var id = $(this).data('id');
                if (confirm('<?php _e('Delete this log?', 'sc_events'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'sc_delete_error_log',
                        nonce: '<?php echo wp_create_nonce('sc_error_logs'); ?>',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            loadLogs();
                        }
                    });
                }
            });

            // Clear all logs
            $('#sc-clear-logs').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear all logs? This cannot be undone.', 'sc_events'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'sc_clear_error_logs',
                        nonce: '<?php echo wp_create_nonce('sc_error_logs'); ?>'
                    }, function(response) {
                        if (response.success) {
                            loadLogs();
                            location.reload();
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Get logs
     */
    public static function ajax_get_logs() {
        check_ajax_referer('sc_error_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;

        $args = array(
            'level' => isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '',
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        );

        $logs = self::get_logs($args);
        $total = self::count_logs($args);

        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ));
    }

    /**
     * AJAX: Clear logs
     */
    public static function ajax_clear_logs() {
        check_ajax_referer('sc_error_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        self::clear_logs();
        wp_send_json_success();
    }

    /**
     * AJAX: Delete single log
     */
    public static function ajax_delete_log() {
        check_ajax_referer('sc_error_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        self::delete_log($id);
        wp_send_json_success();
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Check if should log based on level
     */
    private static function should_log($level, $min_level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        );

        $current = isset($levels[$level]) ? $levels[$level] : 0;
        $minimum = isset($levels[$min_level]) ? $levels[$min_level] : 0;

        return $current >= $minimum;
    }

    /**
     * Get relative path from theme directory
     */
    private static function get_relative_path($file) {
        $theme_dir = get_template_directory();
        if (strpos($file, $theme_dir) === 0) {
            return str_replace($theme_dir, '', $file);
        }
        return $file;
    }

    /**
     * Truncate message to max length
     */
    private static function truncate_message($message, $max_length) {
        if (strlen($message) > $max_length) {
            return substr($message, 0, $max_length - 3) . '...';
        }
        return $message;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return sanitize_text_field($ip);
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get error type name
     */
    private static function get_error_type_name($type) {
        $types = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );

        return isset($types[$type]) ? $types[$type] : 'UNKNOWN';
    }

    /**
     * Format stack trace
     */
    private static function format_trace($trace) {
        $formatted = array();
        foreach (array_slice($trace, 0, 10) as $i => $frame) {
            $formatted[] = sprintf(
                '#%d %s%s%s() at %s:%d',
                $i,
                isset($frame['class']) ? $frame['class'] : '',
                isset($frame['type']) ? $frame['type'] : '',
                isset($frame['function']) ? $frame['function'] : '',
                isset($frame['file']) ? self::get_relative_path($frame['file']) : 'unknown',
                isset($frame['line']) ? $frame['line'] : 0
            );
        }
        return implode("\n", $formatted);
    }
}

// Initialize logger
SC_Error_Logger::init();

/**
 * Global helper functions
 */
function sc_log_debug($message, $context = array(), $source = 'sc_events') {
    return SC_Error_Logger::debug($message, $context, $source);
}

function sc_log_info($message, $context = array(), $source = 'sc_events') {
    return SC_Error_Logger::info($message, $context, $source);
}

function sc_log_warning($message, $context = array(), $source = 'sc_events') {
    return SC_Error_Logger::warning($message, $context, $source);
}

function sc_log_error($message, $context = array(), $source = 'sc_events') {
    return SC_Error_Logger::error($message, $context, $source);
}

function sc_log_critical($message, $context = array(), $source = 'sc_events') {
    return SC_Error_Logger::critical($message, $context, $source);
}
