<?php
/**
 * SC Events Activity Logger
 *
 * Tracks user activities and system events for auditing
 * - User actions (login, logout, profile changes)
 * - Event management (create, update, delete)
 * - Attendee operations (registration, check-in)
 * - Payment activities
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Activity_Logger {

    /**
     * Database table name
     */
    private static $table_name;

    /**
     * Activity types
     */
    const TYPE_USER = 'user';
    const TYPE_EVENT = 'event';
    const TYPE_ATTENDEE = 'attendee';
    const TYPE_PAYMENT = 'payment';
    const TYPE_CHECKIN = 'checkin';
    const TYPE_SYSTEM = 'system';
    const TYPE_SECURITY = 'security';

    /**
     * Initialize the logger
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'sc_activity_logs';

        // Create table
        add_action('after_switch_theme', array(__CLASS__, 'create_table'));
        add_action('init', array(__CLASS__, 'maybe_create_table'), 1);

        // Hook into WordPress actions
        self::register_hooks();

        // Admin interface
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('wp_ajax_sc_get_activity_logs', array(__CLASS__, 'ajax_get_logs'));
        add_action('wp_ajax_sc_export_activity_logs', array(__CLASS__, 'ajax_export_logs'));

        // Dashboard widget
        add_action('sc_dashboard_after_stats', array(__CLASS__, 'render_dashboard_widget'));

        // Daily cleanup
        add_action('sc_daily_activity_cleanup', array(__CLASS__, 'cleanup_old_logs'));
        if (!wp_next_scheduled('sc_daily_activity_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sc_daily_activity_cleanup');
        }
    }

    /**
     * Create activity logs table
     */
    public static function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'sc_activity_logs';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED,
            user_name VARCHAR(255),
            user_email VARCHAR(255),
            activity_type VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            object_type VARCHAR(50),
            object_id BIGINT(20) UNSIGNED,
            object_name VARCHAR(255),
            description TEXT,
            old_value LONGTEXT,
            new_value LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_uri TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY action (action),
            KEY object_type_id (object_type, object_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('sc_activity_logs_table_version', '1.0.0');
    }

    /**
     * Check and create table if needed
     */
    public static function maybe_create_table() {
        if (get_option('sc_activity_logs_table_version', '0') !== '1.0.0') {
            self::create_table();
        }
    }

    /**
     * Register WordPress hooks for automatic logging
     */
    private static function register_hooks() {
        // User actions
        add_action('wp_login', array(__CLASS__, 'log_user_login'), 10, 2);
        add_action('wp_logout', array(__CLASS__, 'log_user_logout'));
        add_action('user_register', array(__CLASS__, 'log_user_register'));
        add_action('profile_update', array(__CLASS__, 'log_profile_update'), 10, 2);
        add_action('wp_login_failed', array(__CLASS__, 'log_login_failed'));

        // Event actions (custom hooks)
        add_action('sc_event_created', array(__CLASS__, 'log_event_created'), 10, 2);
        add_action('sc_event_updated', array(__CLASS__, 'log_event_updated'), 10, 2);
        add_action('sc_event_deleted', array(__CLASS__, 'log_event_deleted'), 10, 2);
        add_action('sc_event_published', array(__CLASS__, 'log_event_published'), 10, 2);

        // Attendee actions
        add_action('sc_attendee_registered', array(__CLASS__, 'log_attendee_registered'), 10, 2);
        add_action('sc_attendee_updated', array(__CLASS__, 'log_attendee_updated'), 10, 3);
        add_action('sc_attendee_cancelled', array(__CLASS__, 'log_attendee_cancelled'), 10, 2);

        // Check-in actions
        add_action('sc_attendee_checked_in', array(__CLASS__, 'log_checkin'), 10, 2);
        add_action('sc_attendee_checked_out', array(__CLASS__, 'log_checkout'), 10, 2);

        // Payment actions
        add_action('sc_payment_completed', array(__CLASS__, 'log_payment_completed'), 10, 2);
        add_action('sc_payment_failed', array(__CLASS__, 'log_payment_failed'), 10, 2);
        add_action('sc_payment_refunded', array(__CLASS__, 'log_payment_refunded'), 10, 2);

        // Settings changes
        add_action('update_option', array(__CLASS__, 'log_option_update'), 10, 3);
    }

    /**
     * Log an activity
     *
     * @param string $type Activity type
     * @param string $action Action performed
     * @param array $data Additional data
     * @return bool|int
     */
    public static function log($type, $action, $data = array()) {
        global $wpdb;

        $user = wp_get_current_user();

        $log_data = array(
            'user_id' => $user->ID ?: null,
            'user_name' => $user->ID ? $user->display_name : ($data['user_name'] ?? 'Guest'),
            'user_email' => $user->ID ? $user->user_email : ($data['user_email'] ?? ''),
            'activity_type' => $type,
            'action' => $action,
            'object_type' => $data['object_type'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'object_name' => $data['object_name'] ?? null,
            'description' => $data['description'] ?? null,
            'old_value' => isset($data['old_value']) ? wp_json_encode($data['old_value']) : null,
            'new_value' => isset($data['new_value']) ? wp_json_encode($data['new_value']) : null,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 500) : '',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert(self::$table_name, $log_data);

        return $result ? $wpdb->insert_id : false;
    }

    // ========================================
    // USER ACTIVITY HANDLERS
    // ========================================

    public static function log_user_login($user_login, $user) {
        self::log(self::TYPE_USER, 'login', array(
            'object_type' => 'user',
            'object_id' => $user->ID,
            'object_name' => $user->display_name,
            'description' => sprintf('User "%s" logged in', $user->user_login)
        ));
    }

    public static function log_user_logout() {
        $user = wp_get_current_user();
        if ($user->ID) {
            self::log(self::TYPE_USER, 'logout', array(
                'object_type' => 'user',
                'object_id' => $user->ID,
                'object_name' => $user->display_name,
                'description' => sprintf('User "%s" logged out', $user->user_login)
            ));
        }
    }

    public static function log_user_register($user_id) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            self::log(self::TYPE_USER, 'register', array(
                'object_type' => 'user',
                'object_id' => $user_id,
                'object_name' => $user->display_name,
                'description' => sprintf('New user registered: %s (%s)', $user->user_login, $user->user_email)
            ));
        }
    }

    public static function log_profile_update($user_id, $old_user_data) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            self::log(self::TYPE_USER, 'profile_update', array(
                'object_type' => 'user',
                'object_id' => $user_id,
                'object_name' => $user->display_name,
                'description' => sprintf('User "%s" profile updated', $user->user_login)
            ));
        }
    }

    public static function log_login_failed($username) {
        self::log(self::TYPE_SECURITY, 'login_failed', array(
            'object_type' => 'user',
            'object_name' => $username,
            'description' => sprintf('Failed login attempt for user: %s', $username)
        ));
    }

    // ========================================
    // EVENT ACTIVITY HANDLERS
    // ========================================

    public static function log_event_created($event_id, $event_data) {
        self::log(self::TYPE_EVENT, 'created', array(
            'object_type' => 'event',
            'object_id' => $event_id,
            'object_name' => $event_data['title'] ?? 'Unknown',
            'description' => sprintf('Event created: %s', $event_data['title'] ?? 'Unknown'),
            'new_value' => $event_data
        ));
    }

    public static function log_event_updated($event_id, $data, $new_data = null) {
        // Support both (id, old, new) and (id, new) signatures
        if ($new_data === null) {
            $new_data = $data;
            $old_data = array();
        } else {
            $old_data = $data;
        }
        $event_name = $new_data['title'] ?? $old_data['title'] ?? 'Unknown';
        self::log(self::TYPE_EVENT, 'updated', array(
            'object_type' => 'event',
            'object_id' => $event_id,
            'object_name' => $event_name,
            'description' => sprintf('Event updated: %s', $event_name),
            'old_value' => $old_data,
            'new_value' => $new_data
        ));
    }

    public static function log_event_deleted($event_id, $event_data) {
        // Handle both object and array
        $title = is_object($event_data) ? ($event_data->title ?? 'Unknown') : ($event_data['title'] ?? 'Unknown');
        self::log(self::TYPE_EVENT, 'deleted', array(
            'object_type' => 'event',
            'object_id' => $event_id,
            'object_name' => $title,
            'description' => sprintf('Event deleted: %s', $title),
            'old_value' => $event_data
        ));
    }

    public static function log_event_published($event_id, $event_data) {
        self::log(self::TYPE_EVENT, 'published', array(
            'object_type' => 'event',
            'object_id' => $event_id,
            'object_name' => $event_data['title'] ?? 'Unknown',
            'description' => sprintf('Event published: %s', $event_data['title'] ?? 'Unknown')
        ));
    }

    // ========================================
    // ATTENDEE ACTIVITY HANDLERS
    // ========================================

    public static function log_attendee_registered($attendee_id, $attendee_data) {
        self::log(self::TYPE_ATTENDEE, 'registered', array(
            'object_type' => 'attendee',
            'object_id' => $attendee_id,
            'object_name' => $attendee_data['name'] ?? 'Unknown',
            'description' => sprintf('Attendee registered: %s for event #%d',
                $attendee_data['name'] ?? 'Unknown',
                $attendee_data['event_id'] ?? 0
            ),
            'new_value' => $attendee_data
        ));
    }

    public static function log_attendee_updated($attendee_id, $old_data, $new_data) {
        self::log(self::TYPE_ATTENDEE, 'updated', array(
            'object_type' => 'attendee',
            'object_id' => $attendee_id,
            'object_name' => $new_data['name'] ?? $old_data['name'] ?? 'Unknown',
            'description' => sprintf('Attendee updated: %s', $new_data['name'] ?? 'Unknown'),
            'old_value' => $old_data,
            'new_value' => $new_data
        ));
    }

    public static function log_attendee_cancelled($attendee_id, $attendee_data) {
        self::log(self::TYPE_ATTENDEE, 'cancelled', array(
            'object_type' => 'attendee',
            'object_id' => $attendee_id,
            'object_name' => $attendee_data['name'] ?? 'Unknown',
            'description' => sprintf('Registration cancelled: %s', $attendee_data['name'] ?? 'Unknown'),
            'old_value' => $attendee_data
        ));
    }

    // ========================================
    // CHECK-IN ACTIVITY HANDLERS
    // ========================================

    public static function log_checkin($attendee_id, $attendee_data) {
        self::log(self::TYPE_CHECKIN, 'checked_in', array(
            'object_type' => 'attendee',
            'object_id' => $attendee_id,
            'object_name' => $attendee_data['name'] ?? 'Unknown',
            'description' => sprintf('Check-in: %s (Ticket: %s)',
                $attendee_data['name'] ?? 'Unknown',
                $attendee_data['ticket_code'] ?? 'N/A'
            )
        ));
    }

    public static function log_checkout($attendee_id, $attendee_data) {
        self::log(self::TYPE_CHECKIN, 'checked_out', array(
            'object_type' => 'attendee',
            'object_id' => $attendee_id,
            'object_name' => $attendee_data['name'] ?? 'Unknown',
            'description' => sprintf('Check-out: %s', $attendee_data['name'] ?? 'Unknown')
        ));
    }

    // ========================================
    // PAYMENT ACTIVITY HANDLERS
    // ========================================

    public static function log_payment_completed($transaction_id, $transaction_data) {
        self::log(self::TYPE_PAYMENT, 'completed', array(
            'object_type' => 'transaction',
            'object_id' => $transaction_id,
            'object_name' => $transaction_data['transaction_id'] ?? 'Unknown',
            'description' => sprintf('Payment completed: %s %s',
                $transaction_data['amount'] ?? 0,
                $transaction_data['currency'] ?? 'SAR'
            ),
            'new_value' => $transaction_data
        ));
    }

    public static function log_payment_failed($transaction_id, $transaction_data) {
        self::log(self::TYPE_PAYMENT, 'failed', array(
            'object_type' => 'transaction',
            'object_id' => $transaction_id,
            'object_name' => $transaction_data['transaction_id'] ?? 'Unknown',
            'description' => sprintf('Payment failed: %s', $transaction_data['error_message'] ?? 'Unknown error'),
            'new_value' => $transaction_data
        ));
    }

    public static function log_payment_refunded($transaction_id, $transaction_data) {
        self::log(self::TYPE_PAYMENT, 'refunded', array(
            'object_type' => 'transaction',
            'object_id' => $transaction_id,
            'object_name' => $transaction_data['transaction_id'] ?? 'Unknown',
            'description' => sprintf('Payment refunded: %s %s',
                $transaction_data['amount'] ?? 0,
                $transaction_data['currency'] ?? 'SAR'
            ),
            'new_value' => $transaction_data
        ));
    }

    // ========================================
    // SETTINGS ACTIVITY HANDLER
    // ========================================

    public static function log_option_update($option, $old_value, $new_value) {
        // Only log SC Events options
        if (strpos($option, 'sc_') !== 0) {
            return;
        }

        // Skip transients and internal options
        if (strpos($option, '_transient') !== false || strpos($option, '_cache') !== false) {
            return;
        }

        self::log(self::TYPE_SYSTEM, 'settings_updated', array(
            'object_type' => 'option',
            'object_name' => $option,
            'description' => sprintf('Setting "%s" updated', $option),
            'old_value' => $old_value,
            'new_value' => $new_value
        ));
    }

    // ========================================
    // QUERY METHODS
    // ========================================

    /**
     * Get activity logs
     */
    public static function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'user_id' => '',
            'activity_type' => '',
            'action' => '',
            'object_type' => '',
            'object_id' => '',
            'search' => '',
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

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if ($args['activity_type']) {
            $where[] = 'activity_type = %s';
            $values[] = $args['activity_type'];
        }

        if ($args['action']) {
            $where[] = 'action = %s';
            $values[] = $args['action'];
        }

        if ($args['object_type']) {
            $where[] = 'object_type = %s';
            $values[] = $args['object_type'];
        }

        if ($args['object_id']) {
            $where[] = 'object_id = %d';
            $values[] = absint($args['object_id']);
        }

        if ($args['search']) {
            $where[] = '(description LIKE %s OR object_name LIKE %s OR user_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
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
            'user_id' => '',
            'activity_type' => '',
            'action' => '',
            'object_type' => '',
            'object_id' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if ($args['activity_type']) {
            $where[] = 'activity_type = %s';
            $values[] = $args['activity_type'];
        }

        if ($args['search']) {
            $where[] = '(description LIKE %s OR object_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $like;
            $values[] = $like;
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
     * Get recent activities for dashboard
     */
    public static function get_recent($limit = 10) {
        return self::get_logs(array('limit' => $limit));
    }

    /**
     * Get user activity history
     */
    public static function get_user_activity($user_id, $limit = 50) {
        return self::get_logs(array(
            'user_id' => $user_id,
            'limit' => $limit
        ));
    }

    /**
     * Get object activity history
     */
    public static function get_object_activity($object_type, $object_id, $limit = 50) {
        return self::get_logs(array(
            'object_type' => $object_type,
            'object_id' => $object_id,
            'limit' => $limit
        ));
    }

    /**
     * Cleanup old logs
     */
    public static function cleanup_old_logs() {
        global $wpdb;

        $days = get_option('sc_activity_log_retention_days', 90);
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table_name . " WHERE created_at < %s",
            $date
        ));
    }

    // ========================================
    // ADMIN INTERFACE
    // ========================================

    public static function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            __('Activity Logs', 'sc_events'),
            __('SC Activity Logs', 'sc_events'),
            'manage_options',
            'sc-activity-logs',
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function render_admin_page() {
        $is_rtl = is_rtl();
        ?>
        <div class="wrap" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
            <h1><?php _e('Activity Logs', 'sc_events'); ?></h1>

            <div class="sc-activity-filters" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <form id="sc-activity-filter-form" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Activity Type', 'sc_events'); ?></label>
                        <select name="activity_type" id="activity-type">
                            <option value=""><?php _e('All Types', 'sc_events'); ?></option>
                            <option value="user"><?php _e('User', 'sc_events'); ?></option>
                            <option value="event"><?php _e('Event', 'sc_events'); ?></option>
                            <option value="attendee"><?php _e('Attendee', 'sc_events'); ?></option>
                            <option value="payment"><?php _e('Payment', 'sc_events'); ?></option>
                            <option value="checkin"><?php _e('Check-in', 'sc_events'); ?></option>
                            <option value="system"><?php _e('System', 'sc_events'); ?></option>
                            <option value="security"><?php _e('Security', 'sc_events'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Search', 'sc_events'); ?></label>
                        <input type="text" name="search" id="activity-search" placeholder="<?php _e('Search...', 'sc_events'); ?>">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Date From', 'sc_events'); ?></label>
                        <input type="date" name="date_from" id="activity-date-from">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Date To', 'sc_events'); ?></label>
                        <input type="date" name="date_to" id="activity-date-to">
                    </div>
                    <button type="submit" class="button button-primary"><?php _e('Filter', 'sc_events'); ?></button>
                    <button type="button" id="sc-export-logs" class="button"><?php _e('Export CSV', 'sc_events'); ?></button>
                </form>
            </div>

            <div id="sc-activity-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?php _e('Date', 'sc_events'); ?></th>
                            <th style="width: 100px;"><?php _e('Type', 'sc_events'); ?></th>
                            <th style="width: 100px;"><?php _e('Action', 'sc_events'); ?></th>
                            <th style="width: 150px;"><?php _e('User', 'sc_events'); ?></th>
                            <th><?php _e('Description', 'sc_events'); ?></th>
                            <th style="width: 120px;"><?php _e('IP Address', 'sc_events'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sc-activity-tbody">
                        <tr><td colspan="6" style="text-align: center;"><?php _e('Loading...', 'sc_events'); ?></td></tr>
                    </tbody>
                </table>
                <div id="sc-activity-pagination" style="margin-top: 20px;"></div>
            </div>
        </div>

        <style>
            .sc-activity-type {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .sc-activity-type-user { background: #e3f2fd; color: #1565c0; }
            .sc-activity-type-event { background: #e8f5e9; color: #2e7d32; }
            .sc-activity-type-attendee { background: #fff3e0; color: #e65100; }
            .sc-activity-type-payment { background: #f3e5f5; color: #7b1fa2; }
            .sc-activity-type-checkin { background: #e0f2f1; color: #00695c; }
            .sc-activity-type-system { background: #e0e0e0; color: #424242; }
            .sc-activity-type-security { background: #ffebee; color: #c62828; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentPage = 1;
            var perPage = 50;

            function loadActivities() {
                var data = {
                    action: 'sc_get_activity_logs',
                    nonce: '<?php echo wp_create_nonce('sc_activity_logs'); ?>',
                    activity_type: $('#activity-type').val(),
                    search: $('#activity-search').val(),
                    date_from: $('#activity-date-from').val(),
                    date_to: $('#activity-date-to').val(),
                    page: currentPage,
                    per_page: perPage
                };

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        renderActivities(response.data.logs);
                        renderPagination(response.data.total, response.data.pages);
                    }
                });
            }

            function renderActivities(logs) {
                var html = '';
                if (logs.length === 0) {
                    html = '<tr><td colspan="6" style="text-align: center;"><?php _e('No activities found', 'sc_events'); ?></td></tr>';
                } else {
                    $.each(logs, function(i, log) {
                        html += '<tr>';
                        html += '<td>' + log.created_at + '</td>';
                        html += '<td><span class="sc-activity-type sc-activity-type-' + log.activity_type + '">' + log.activity_type + '</span></td>';
                        html += '<td>' + log.action + '</td>';
                        html += '<td>' + (log.user_name || '<?php _e('Guest', 'sc_events'); ?>') + '</td>';
                        html += '<td>' + (log.description || '-') + '</td>';
                        html += '<td>' + (log.ip_address || '-') + '</td>';
                        html += '</tr>';
                    });
                }
                $('#sc-activity-tbody').html(html);
            }

            function renderPagination(total, pages) {
                var html = '';
                if (pages > 1) {
                    html += '<span><?php _e('Page', 'sc_events'); ?> ' + currentPage + ' / ' + pages + ' (' + total + ' <?php _e('records', 'sc_events'); ?>)</span> ';
                    if (currentPage > 1) {
                        html += '<button class="button" data-page="' + (currentPage - 1) + '">&laquo; <?php _e('Previous', 'sc_events'); ?></button> ';
                    }
                    if (currentPage < pages) {
                        html += '<button class="button" data-page="' + (currentPage + 1) + '"><?php _e('Next', 'sc_events'); ?> &raquo;</button>';
                    }
                }
                $('#sc-activity-pagination').html(html);
            }

            loadActivities();

            $('#sc-activity-filter-form').on('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                loadActivities();
            });

            $(document).on('click', '#sc-activity-pagination button', function() {
                currentPage = $(this).data('page');
                loadActivities();
            });

            $('#sc-export-logs').on('click', function() {
                var params = new URLSearchParams({
                    action: 'sc_export_activity_logs',
                    nonce: '<?php echo wp_create_nonce('sc_activity_logs'); ?>',
                    activity_type: $('#activity-type').val(),
                    search: $('#activity-search').val(),
                    date_from: $('#activity-date-from').val(),
                    date_to: $('#activity-date-to').val()
                });
                window.location.href = ajaxurl + '?' + params.toString();
            });
        });
        </script>
        <?php
    }

    /**
     * Render dashboard widget
     */
    public static function render_dashboard_widget() {
        $recent = self::get_recent(5);
        if (empty($recent)) {
            return;
        }
        ?>
        <div class="sc-dashboard-widget sc-activity-widget">
            <h3><?php _e('Recent Activity', 'sc_events'); ?></h3>
            <div class="activity-list">
                <?php foreach ($recent as $activity): ?>
                <div class="activity-item" style="padding: 10px 0; border-bottom: 1px solid #eee;">
                    <span class="sc-activity-type sc-activity-type-<?php echo esc_attr($activity->activity_type); ?>" style="font-size: 10px;">
                        <?php echo esc_html($activity->activity_type); ?>
                    </span>
                    <span style="margin: 0 10px;"><?php echo esc_html($activity->description); ?></span>
                    <span style="color: #999; font-size: 12px;"><?php echo esc_html($activity->created_at); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get activity logs
     */
    public static function ajax_get_logs() {
        check_ajax_referer('sc_activity_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;

        $args = array(
            'activity_type' => isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : '',
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
     * AJAX: Export logs as CSV
     */
    public static function ajax_export_logs() {
        check_admin_referer('sc_activity_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $args = array(
            'activity_type' => isset($_GET['activity_type']) ? sanitize_text_field($_GET['activity_type']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'limit' => 10000
        );

        $logs = self::get_logs($args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=activity-logs-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header
        fputcsv($output, array('Date', 'Type', 'Action', 'User', 'Description', 'IP Address'));

        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->activity_type,
                $log->action,
                $log->user_name,
                $log->description,
                $log->ip_address
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Get client IP
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
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
}

// Initialize
SC_Activity_Logger::init();

/**
 * Helper function for logging activities
 */
function sc_log_activity($type, $action, $data = array()) {
    return SC_Activity_Logger::log($type, $action, $data);
}
