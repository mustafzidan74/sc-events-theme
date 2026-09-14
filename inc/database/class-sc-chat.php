<?php
/**
 * SC Events Chat System Class
 *
 * Real-time chat system for event inquiries
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chat System Class
 */
class SC_Chat {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Table names
     */
    private $conversations_table;
    private $messages_table;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->conversations_table = $wpdb->prefix . 'sc_conversations';
        $this->messages_table = $wpdb->prefix . 'sc_chat_messages';

        // Run migration to add user_id column if needed
        $this->maybe_add_user_id_column();

        // Register AJAX handlers
        $this->register_ajax_handlers();
    }

    /**
     * Add user_id column to conversations table if it doesn't exist
     */
    private function maybe_add_user_id_column() {
        global $wpdb;

        // Check if column exists (cached check)
        $cache_key = 'sc_chat_user_id_column_exists';
        $column_exists = get_transient($cache_key);

        if ($column_exists === false) {
            $result = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$this->conversations_table} LIKE %s",
                'user_id'
            ));

            if (empty($result)) {
                $wpdb->query("ALTER TABLE {$this->conversations_table} ADD COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID if logged in' AFTER visitor_token");
                $wpdb->query("ALTER TABLE {$this->conversations_table} ADD INDEX user_id (user_id)");
            }

            set_transient($cache_key, 'yes', DAY_IN_SECONDS);
        }
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Public (visitor) actions
        add_action('wp_ajax_sc_chat_start', array($this, 'ajax_start_conversation'));
        add_action('wp_ajax_nopriv_sc_chat_start', array($this, 'ajax_start_conversation'));

        add_action('wp_ajax_sc_chat_send', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_sc_chat_send', array($this, 'ajax_send_message'));

        add_action('wp_ajax_sc_chat_load', array($this, 'ajax_load_messages'));
        add_action('wp_ajax_nopriv_sc_chat_load', array($this, 'ajax_load_messages'));

        add_action('wp_ajax_sc_chat_check', array($this, 'ajax_check_conversation'));
        add_action('wp_ajax_nopriv_sc_chat_check', array($this, 'ajax_check_conversation'));

        add_action('wp_ajax_sc_chat_mark_read', array($this, 'ajax_mark_as_read'));
        add_action('wp_ajax_nopriv_sc_chat_mark_read', array($this, 'ajax_mark_as_read'));

        // Dashboard (organizer) actions
        add_action('wp_ajax_sc_chat_get_conversations', array($this, 'ajax_get_conversations'));
        add_action('wp_ajax_sc_chat_get_conversation_messages', array($this, 'ajax_get_conversation_messages'));
        add_action('wp_ajax_sc_chat_close_conversation', array($this, 'ajax_close_conversation'));
        add_action('wp_ajax_sc_chat_get_unread_count', array($this, 'ajax_get_unread_count'));

        // File upload (both visitor and organizer)
        add_action('wp_ajax_sc_chat_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_nopriv_sc_chat_upload_file', array($this, 'ajax_upload_file'));

        // Visitor close conversation
        add_action('wp_ajax_sc_chat_visitor_close', array($this, 'ajax_visitor_close_conversation'));
        add_action('wp_ajax_nopriv_sc_chat_visitor_close', array($this, 'ajax_visitor_close_conversation'));

        // Visitor reopen conversation
        add_action('wp_ajax_sc_chat_visitor_reopen', array($this, 'ajax_visitor_reopen_conversation'));
        add_action('wp_ajax_nopriv_sc_chat_visitor_reopen', array($this, 'ajax_visitor_reopen_conversation'));

        // Archive conversation
        add_action('wp_ajax_sc_chat_archive_conversation', array($this, 'ajax_archive_conversation'));

        // Restore conversation from archive
        add_action('wp_ajax_sc_chat_restore_conversation', array($this, 'ajax_restore_conversation'));

        // Reopen closed conversation
        add_action('wp_ajax_sc_chat_reopen_conversation', array($this, 'ajax_reopen_conversation'));
    }

    // =========================================
    // RATE LIMITING
    // =========================================

    /**
     * Rate limit settings
     */
    private $rate_limits = array(
        'message' => array('limit' => 10, 'window' => 60),      // 10 messages per minute
        'conversation' => array('limit' => 3, 'window' => 300), // 3 new conversations per 5 minutes
        'file_upload' => array('limit' => 5, 'window' => 300),  // 5 file uploads per 5 minutes
    );

    /**
     * Check if action is rate limited
     *
     * @param string $action The action type (message, conversation, file_upload)
     * @param string $identifier Unique identifier (IP or visitor_token)
     * @return bool|array True if allowed, array with error info if limited
     */
    private function check_rate_limit($action, $identifier = null) {
        if (!isset($this->rate_limits[$action])) {
            return true;
        }

        // Get identifier (use IP + visitor token for better tracking)
        if (!$identifier) {
            $ip = $this->get_client_ip();
            $identifier = md5($ip . wp_salt('auth'));
        }

        $limit = $this->rate_limits[$action]['limit'];
        $window = $this->rate_limits[$action]['window'];
        $transient_key = 'sc_rate_' . $action . '_' . $identifier;

        // Get current count
        $data = get_transient($transient_key);

        if ($data === false) {
            // First request in window
            set_transient($transient_key, array('count' => 1, 'started' => time()), $window);
            return true;
        }

        // Check if limit exceeded
        if ($data['count'] >= $limit) {
            $remaining = $window - (time() - $data['started']);
            return array(
                'limited' => true,
                'remaining_seconds' => max(0, $remaining),
                'limit' => $limit,
                'window' => $window,
            );
        }

        // Increment count
        $data['count']++;
        set_transient($transient_key, $data, $window - (time() - $data['started']));

        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

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

    // =========================================
    // VISITOR TOKEN MANAGEMENT
    // =========================================

    /**
     * Generate a unique visitor token
     */
    public function generate_visitor_token() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get or create visitor token from cookie
     */
    public function get_visitor_token() {
        // The same token for the whole request. A new token on every call meant the
        // conversation was saved with one token and the browser kept another, so a
        // guest's second message was refused.
        if ($this->request_token) {
            return $this->request_token;
        }

        $cookie = isset($_COOKIE['sc_chat_visitor_token']) ? sanitize_text_field(wp_unslash($_COOKIE['sc_chat_visitor_token'])) : '';
        if (preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            return $this->request_token = $cookie;
        }

        $token = $this->generate_visitor_token();

        // Set cookie for 30 days. The widget keeps its own copy, so scripts never need to read it.
        if (!headers_sent()) {
            setcookie('sc_chat_visitor_token', $token, array(
                'expires'  => time() + (30 * DAY_IN_SECONDS),
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        }
        $_COOKIE['sc_chat_visitor_token'] = $token;

        return $this->request_token = $token;
    }

    /** Token chosen for this request (see get_visitor_token). */
    private $request_token = '';

    /**
     * Organizer actions are for event managers, not every logged-in account.
     */
    private function is_organizer() {
        return is_user_logged_in() && class_exists('SC_Event_Manager_Dashboard') && SC_Event_Manager_Dashboard::is_event_manager();
    }

    /**
     * Only these two sides exist; anything else is refused.
     */
    private function party($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, array('visitor', 'organizer'), true) ? $value : '';
    }

    // =========================================
    // CONVERSATION MANAGEMENT
    // =========================================

    /**
     * Get or create a conversation (Global chat - not tied to specific event)
     * If user is logged in, uses user_id. Otherwise uses visitor_token.
     */
    public function get_or_create_conversation($event_id, $visitor_data = array()) {
        global $wpdb;

        $visitor_token = $this->get_visitor_token();
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $conversation = null;

        // If logged in, search by user_id first
        if ($user_id) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table}
                 WHERE user_id = %d AND status != 'archived'
                 ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
        }

        // If not found and not logged in (or no user conversation), search by visitor_token
        if (!$conversation) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table}
                 WHERE visitor_token = %s AND status != 'archived'
                 ORDER BY created_at DESC LIMIT 1",
                $visitor_token
            ));

            // If found by token and user is now logged in, link it to their account
            if ($conversation && $user_id && empty($conversation->user_id)) {
                $wpdb->update(
                    $this->conversations_table,
                    array('user_id' => $user_id),
                    array('id' => $conversation->id)
                );
            }
        }

        if ($conversation) {
            // Reopen closed conversation
            if ($conversation->status === 'closed') {
                $wpdb->update(
                    $this->conversations_table,
                    array('status' => 'active'),
                    array('id' => $conversation->id)
                );
                $conversation->status = 'active';
            }
            return $conversation;
        }

        // Get user info if logged in
        $name = sanitize_text_field($visitor_data['name'] ?? '');
        $email = sanitize_email($visitor_data['email'] ?? '');

        if ($user_id) {
            $current_user = wp_get_current_user();
            if (empty($name)) {
                $name = $current_user->display_name;
            }
            if (empty($email)) {
                $email = $current_user->user_email;
            }
        }

        // Create new conversation
        $wpdb->insert($this->conversations_table, array(
            'event_id' => $event_id,
            'visitor_token' => $visitor_token,
            'user_id' => $user_id,
            'visitor_name' => $name,
            'visitor_email' => $email,
            'visitor_phone' => sanitize_text_field($visitor_data['phone'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ));

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->conversations_table} WHERE id = %d",
            $wpdb->insert_id
        ));
    }

    /**
     * Get conversation by ID
     */
    public function get_conversation($conversation_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->conversations_table} WHERE id = %d",
            $conversation_id
        ));
    }

    /**
     * Get conversations for an event
     */
    public function get_event_conversations($event_id, $status = 'all', $limit = 50, $offset = 0) {
        global $wpdb;

        $where = "event_id = %d";
        $params = array($event_id);

        if ($status !== 'all') {
            $where .= " AND status = %s";
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$this->messages_table} WHERE conversation_id = c.id) as message_count,
                    (SELECT message FROM {$this->messages_table} WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
             FROM {$this->conversations_table} c
             WHERE $where
             ORDER BY c.last_message_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ));
    }

    /**
     * Close a conversation
     */
    public function close_conversation($conversation_id, $closed_by = 'organizer') {
        global $wpdb;

        // Add system message about closure
        $close_message = ($closed_by === 'organizer')
            ? __('This conversation has been closed by the organizer.', 'sc_events')
            : __('This conversation has been closed by the visitor.', 'sc_events');

        $this->send_message(
            $conversation_id,
            $close_message,
            'system',
            null,
            'system'
        );

        return $wpdb->update(
            $this->conversations_table,
            array('status' => 'closed'),
            array('id' => $conversation_id)
        );
    }

    /**
     * Get chat statistics for event/dashboard (cached)
     */
    public function get_chat_stats($event_id = null) {
        $cache_key = 'sc_chat_stats_' . ($event_id ?: 'all');
        $stats = wp_cache_get($cache_key, 'sc_chat');

        if ($stats === false) {
            global $wpdb;

            $where = $event_id ? $wpdb->prepare("WHERE event_id = %d", $event_id) : "";

            $stats = array(
                'total' => $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$this->conversations_table} $where"
                ),
                'active' => $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$this->conversations_table} $where" .
                    ($event_id ? " AND status = 'active'" : " WHERE status = 'active'")
                ),
                'unread' => $wpdb->get_var(
                    "SELECT SUM(unread_organizer) FROM {$this->conversations_table} $where"
                ),
            );

            // Cache for 30 seconds (short TTL for real-time data)
            wp_cache_set($cache_key, $stats, 'sc_chat', 30);
        }

        return $stats;
    }

    /**
     * Invalidate chat stats cache
     */
    private function invalidate_stats_cache($event_id = null) {
        wp_cache_delete('sc_chat_stats_' . ($event_id ?: 'all'), 'sc_chat');
        wp_cache_delete('sc_chat_stats_all', 'sc_chat');
        wp_cache_delete('sc_chat_unread_count', 'sc_chat');
    }

    /**
     * Get total unread count for all events (for dashboard header) - cached
     */
    public function get_total_unread_count() {
        $cache_key = 'sc_chat_unread_count';
        $count = wp_cache_get($cache_key, 'sc_chat');

        if ($count === false) {
            global $wpdb;

            $count = (int) $wpdb->get_var(
                "SELECT SUM(unread_organizer) FROM {$this->conversations_table} WHERE status = 'active'"
            );

            // Cache for 15 seconds (very short for real-time notifications)
            wp_cache_set($cache_key, $count, 'sc_chat', 15);
        }

        return $count;
    }

    // =========================================
    // MESSAGE MANAGEMENT
    // =========================================

    /**
     * Send a message
     */
    public function send_message($conversation_id, $message, $sender_type = 'visitor', $sender_id = null, $message_type = 'text', $file_url = null, $file_name = null) {
        global $wpdb;

        // Build message data
        $message_data = array(
            'conversation_id' => $conversation_id,
            'sender_type' => $sender_type,
            'sender_id' => $sender_id,
            'message' => $message_type === 'system' ? $message : sanitize_textarea_field($message),
            'message_type' => $message_type,
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        );

        // Add file data if present
        if ($file_url) {
            $message_data['file_url'] = esc_url_raw($file_url);
            $message_data['file_name'] = sanitize_file_name($file_name);
        }

        // Insert message
        $result = $wpdb->insert($this->messages_table, $message_data);

        if (!$result) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Update conversation (skip for system messages)
        if ($message_type !== 'system') {
            $unread_field = ($sender_type === 'visitor') ? 'unread_organizer' : 'unread_visitor';
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->conversations_table}
                 SET {$unread_field} = {$unread_field} + 1,
                     last_message_at = %s,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                current_time('mysql'),
                $conversation_id
            ));

            // Send email notification (skip for files)
            if ($message_type === 'text') {
                $this->send_notification($conversation_id, $message, $sender_type);
            }

            // Invalidate cache
            $this->invalidate_stats_cache();
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->messages_table} WHERE id = %d",
            $message_id
        ));
    }

    /**
     * Get messages for a conversation
     */
    public function get_messages($conversation_id, $limit = 100, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->messages_table}
             WHERE conversation_id = %d
             ORDER BY created_at ASC
             LIMIT %d OFFSET %d",
            $conversation_id,
            $limit,
            $offset
        ));
    }

    /**
     * Get new messages (for polling)
     */
    public function get_new_messages($conversation_id, $last_id = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->messages_table}
             WHERE conversation_id = %d AND id > %d
             ORDER BY created_at ASC",
            $conversation_id,
            $last_id
        ));
    }

    /**
     * Mark messages as read
     */
    public function mark_as_read($conversation_id, $reader_type = 'visitor') {
        global $wpdb;

        // Determine sender type (opposite of reader)
        $sender_type = ($reader_type === 'visitor') ? 'organizer' : 'visitor';

        // Update messages
        $wpdb->update(
            $this->messages_table,
            array(
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ),
            array(
                'conversation_id' => $conversation_id,
                'sender_type' => $sender_type,
                'is_read' => 0,
            )
        );

        // Reset unread counter
        $unread_field = ($reader_type === 'visitor') ? 'unread_visitor' : 'unread_organizer';
        $wpdb->update(
            $this->conversations_table,
            array($unread_field => 0),
            array('id' => $conversation_id)
        );

        // Invalidate cache after updating unread count
        $this->invalidate_stats_cache();

        return true;
    }

    // =========================================
    // ACCESS CONTROL
    // =========================================

    /**
     * Check if user can access conversation
     */
    public function can_access_conversation($conversation_id, $accessor_type = 'visitor') {
        $conversation = $this->get_conversation($conversation_id);

        if (!$conversation) {
            return false;
        }

        // Logged-in users can always access their own conversations
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (!empty($conversation->user_id) && (int) $conversation->user_id === $user_id) {
                return true;
            }
            // Event managers can access any conversation
            if ($accessor_type === 'organizer' && $this->is_organizer()) {
                return true;
            }
        }

        if ($accessor_type === 'visitor') {
            $visitor_token = $this->get_visitor_token();
            return $conversation->visitor_token === $visitor_token;
        }

        return false;
    }

    // =========================================
    // NOTIFICATIONS
    // =========================================

    /**
     * Send email notification
     */
    private function send_notification($conversation_id, $message, $sender_type) {
        $conversation = $this->get_conversation($conversation_id);

        if (!$conversation) {
            return;
        }

        // Get event details
        $event = null;
        if (class_exists('SC_Event')) {
            $event = SC_Event::get($conversation->event_id);
        }
        $event_title = $event ? $event->title : 'Event';

        if ($sender_type === 'visitor') {
            // Notify organizer
            $admin_email = get_option('admin_email');
            $subject = sprintf(__('[%s] New Chat Message', 'sc_events'), $event_title);
            $body = sprintf(
                __("You have a new message from %s:\n\n%s\n\nView the conversation in your dashboard.", 'sc_events'),
                $conversation->visitor_name ?: __('Visitor', 'sc_events'),
                $message
            );
            wp_mail($admin_email, $subject, $body);
        } else {
            // Notify visitor (if email provided)
            if (!empty($conversation->visitor_email)) {
                $platform_name = get_option('sc_platform_name', get_bloginfo('name'));
                $subject = sprintf(__('[%s] New Reply to Your Inquiry', 'sc_events'), $platform_name);
                $body = sprintf(
                    __("You have a new reply regarding %s:\n\n%s\n\nVisit the event page to continue the conversation.", 'sc_events'),
                    $event_title,
                    $message
                );
                wp_mail($conversation->visitor_email, $subject, $body);
            }
        }
    }

    // =========================================
    // AJAX HANDLERS - VISITOR
    // =========================================

    /**
     * AJAX: Start new conversation
     */
    public function ajax_start_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        // Rate limiting for new conversations
        $rate_check = $this->check_rate_limit('conversation');
        if (is_array($rate_check) && $rate_check['limited']) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Too many requests. Please wait %d seconds.', 'sc_events'),
                    $rate_check['remaining_seconds']
                ),
                'rate_limited' => true,
                'wait_seconds' => $rate_check['remaining_seconds']
            ));
        }

        // Honeypot check - bots fill this hidden field, humans don't
        $honeypot = sanitize_text_field($_POST['website'] ?? '');
        if (!empty($honeypot)) {
            // Log potential bot attempt
            error_log('SC Chat: Bot detected via honeypot. IP: ' . $this->get_client_ip());
            // Return fake success to not reveal detection
            wp_send_json_success(array(
                'conversation_id' => 0,
                'visitor_token' => '',
                'messages' => array(),
            ));
        }

        // event_id can be 0 for global chat
        $event_id = intval($_POST['event_id'] ?? 0);
        $visitor_data = array(
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
        );
        $initial_message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        // Get or create conversation (event_id can be 0 for global chat)
        $conversation = $this->get_or_create_conversation($event_id, $visitor_data);

        // Send initial message
        if (!empty($initial_message)) {
            $this->send_message($conversation->id, $initial_message, 'visitor');
        }

        // Get all messages
        $messages = $this->get_messages($conversation->id);

        wp_send_json_success(array(
            'conversation_id' => $conversation->id,
            'visitor_token' => $this->get_visitor_token(),
            'messages' => $messages,
        ));
    }

    /**
     * AJAX: Send message
     */
    public function ajax_send_message() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $sender_type = $this->party($_POST['sender_type'] ?? 'visitor');

        if (!$conversation_id || empty($message) || !$sender_type) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        // Rate limiting for visitors only (not organizers)
        if ($sender_type === 'visitor') {
            $rate_check = $this->check_rate_limit('message');
            if (is_array($rate_check) && $rate_check['limited']) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Too many messages. Please wait %d seconds.', 'sc_events'),
                        $rate_check['remaining_seconds']
                    ),
                    'rate_limited' => true,
                    'wait_seconds' => $rate_check['remaining_seconds']
                ));
            }
        }

        // Check access
        if (!$this->can_access_conversation($conversation_id, $sender_type)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $sender_id = ($sender_type === 'organizer') ? get_current_user_id() : null;
        $result = $this->send_message($conversation_id, $message, $sender_type, $sender_id);

        if ($result) {
            wp_send_json_success(array('message' => $result));
        } else {
            wp_send_json_error(array('message' => __('Failed to send message', 'sc_events')));
        }
    }

    /**
     * AJAX: Load messages (polling)
     */
    public function ajax_load_messages() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $last_id = intval($_POST['last_id'] ?? 0);
        $reader_type = $this->party($_POST['reader_type'] ?? 'visitor');

        if (!$conversation_id || !$reader_type) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        // Only the visitor who owns the conversation, or an event manager, may read it.
        if (!$this->can_access_conversation($conversation_id, $reader_type)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $messages = $this->get_new_messages($conversation_id, $last_id);

        // Mark as read
        if (!empty($messages)) {
            $this->mark_as_read($conversation_id, $reader_type);
        }

        wp_send_json_success(array('messages' => $messages));
    }

    /**
     * AJAX: Check for existing conversation
     * Supports both logged-in users (by user_id) and guests (by visitor_token)
     */
    public function ajax_check_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $visitor_token = sanitize_text_field($_POST['visitor_token'] ?? '');
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        global $wpdb;
        $conversation = null;

        // If logged in, search by user_id first
        if ($user_id) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table}
                 WHERE user_id = %d AND status != 'archived'
                 ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
        }

        // If not found, search by visitor_token
        if (!$conversation && $visitor_token) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table}
                 WHERE visitor_token = %s AND status != 'archived'
                 ORDER BY created_at DESC LIMIT 1",
                $visitor_token
            ));

            // If found by token and user is now logged in, link it to their account
            if ($conversation && $user_id && empty($conversation->user_id)) {
                $wpdb->update(
                    $this->conversations_table,
                    array('user_id' => $user_id),
                    array('id' => $conversation->id)
                );
            }
        }

        if ($conversation) {
            // Sync visitor_token to current cookie so can_access_conversation() works
            $current_token = $this->get_visitor_token();
            if ($conversation->visitor_token !== $current_token) {
                $wpdb->update(
                    $this->conversations_table,
                    array('visitor_token' => $current_token),
                    array('id' => $conversation->id)
                );
            }

            $messages = $this->get_messages($conversation->id);
            wp_send_json_success(array(
                'conversation_id' => $conversation->id,
                'messages' => $messages,
                'status' => $conversation->status,
                'is_logged_in' => !empty($user_id),
            ));
        } else {
            wp_send_json_success(array(
                'conversation_id' => null,
                'is_logged_in' => !empty($user_id),
            ));
        }
    }

    /**
     * AJAX: Mark as read
     */
    public function ajax_mark_as_read() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $reader_type = $this->party($_POST['reader_type'] ?? 'visitor');

        if (!$conversation_id || !$reader_type) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        if (!$this->can_access_conversation($conversation_id, $reader_type)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $this->mark_as_read($conversation_id, $reader_type);

        wp_send_json_success();
    }

    // =========================================
    // AJAX HANDLERS - DASHBOARD
    // =========================================

    /**
     * AJAX: Get conversations list for dashboard
     */
    public function ajax_get_conversations() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $status = sanitize_text_field($_POST['status'] ?? 'all');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Global chat - get all conversations
        global $wpdb;
        $where = ($status !== 'all') ? $wpdb->prepare(" WHERE c.status = %s", $status) : "";

        $conversations = $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$this->messages_table} WHERE conversation_id = c.id) as message_count,
                    (SELECT message FROM {$this->messages_table} WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
             FROM {$this->conversations_table} c
             $where
             ORDER BY c.last_message_at DESC
             LIMIT $per_page OFFSET $offset"
        );

        foreach ($conversations as $c) {
            unset($c->visitor_token);
        }

        wp_send_json_success(array(
            'conversations' => $conversations,
            'stats' => $this->get_chat_stats(),
        ));
    }

    /**
     * AJAX: Get messages for a specific conversation (dashboard)
     */
    public function ajax_get_conversation_messages() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        $conversation = $this->get_conversation($conversation_id);
        if (!$conversation) {
            wp_send_json_error(array('message' => __('Conversation not found', 'sc_events')));
        }
        unset($conversation->visitor_token);
        $messages = $this->get_messages($conversation_id);

        // Mark as read by organizer
        $this->mark_as_read($conversation_id, 'organizer');

        wp_send_json_success(array(
            'conversation' => $conversation,
            'messages' => $messages,
        ));
    }

    /**
     * AJAX: Close conversation (dashboard)
     */
    public function ajax_close_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        $this->close_conversation($conversation_id);

        wp_send_json_success(array('message' => __('Conversation closed', 'sc_events')));
    }

    /**
     * AJAX: Get unread count (for header badge)
     */
    public function ajax_get_unread_count() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        wp_send_json_success(array(
            'unread' => $this->get_total_unread_count(),
        ));
    }

    // =========================================
    // FILE UPLOAD VALIDATION
    // =========================================

    /**
     * Allowed file types with their extensions and magic bytes
     */
    private $allowed_file_types = array(
        'image/jpeg' => array(
            'extensions' => array('jpg', 'jpeg'),
            'magic' => array("\xFF\xD8\xFF"),
        ),
        'image/png' => array(
            'extensions' => array('png'),
            'magic' => array("\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"),
        ),
        'image/gif' => array(
            'extensions' => array('gif'),
            'magic' => array("GIF87a", "GIF89a"),
        ),
        'image/webp' => array(
            'extensions' => array('webp'),
            'magic' => array("RIFF"),
        ),
        'application/pdf' => array(
            'extensions' => array('pdf'),
            'magic' => array("%PDF"),
        ),
        'application/msword' => array(
            'extensions' => array('doc'),
            'magic' => array("\xD0\xCF\x11\xE0"),
        ),
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => array(
            'extensions' => array('docx'),
            'magic' => array("PK\x03\x04"),
        ),
        'application/vnd.ms-excel' => array(
            'extensions' => array('xls'),
            'magic' => array("\xD0\xCF\x11\xE0"),
        ),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => array(
            'extensions' => array('xlsx'),
            'magic' => array("PK\x03\x04"),
        ),
        'text/plain' => array(
            'extensions' => array('txt'),
            'magic' => null, // No magic bytes for text files
        ),
    );

    /**
     * Validate uploaded file with enhanced security checks
     *
     * @param array $file The $_FILES array element
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_uploaded_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed', 'sc_events'));
        }

        // Max file size: 5MB
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File size exceeds 5MB limit', 'sc_events'));
        }

        // Get file extension (lowercase)
        $filename = sanitize_file_name($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check for double extensions (security risk)
        $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
        if (preg_match('/\.(php|phtml|php3|php4|php5|phar|exe|sh|bat|cmd|js|html|htm)$/i', $name_without_ext)) {
            return new WP_Error('double_extension', __('Invalid file name', 'sc_events'));
        }

        // Verify extension is allowed
        $allowed_extensions = array();
        foreach ($this->allowed_file_types as $type_info) {
            $allowed_extensions = array_merge($allowed_extensions, $type_info['extensions']);
        }

        if (!in_array($ext, $allowed_extensions)) {
            return new WP_Error('invalid_extension', __('File type not allowed', 'sc_events'));
        }

        // Get actual MIME type using WordPress
        $wp_filetype = wp_check_filetype($filename);
        $mime_type = $wp_filetype['type'];

        // Fallback to finfo if available
        if (empty($mime_type) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }

        // Verify MIME type is allowed
        if (!array_key_exists($mime_type, $this->allowed_file_types)) {
            return new WP_Error('invalid_mime', __('File type not allowed', 'sc_events'));
        }

        // Verify extension matches MIME type
        $type_info = $this->allowed_file_types[$mime_type];
        if (!in_array($ext, $type_info['extensions'])) {
            return new WP_Error('mime_mismatch', __('File extension does not match content', 'sc_events'));
        }

        // Verify magic bytes (file signature)
        if ($type_info['magic'] !== null) {
            $handle = fopen($file['tmp_name'], 'rb');
            if ($handle) {
                $header = fread($handle, 16);
                fclose($handle);

                $valid_magic = false;
                foreach ($type_info['magic'] as $magic) {
                    if (substr($header, 0, strlen($magic)) === $magic) {
                        $valid_magic = true;
                        break;
                    }
                }

                if (!$valid_magic) {
                    return new WP_Error('invalid_signature', __('File content does not match type', 'sc_events'));
                }
            }
        }

        // For images, verify it's a valid image
        if (strpos($mime_type, 'image/') === 0 && $ext !== 'webp') {
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                return new WP_Error('invalid_image', __('Invalid image file', 'sc_events'));
            }
        }

        // Store validated MIME type for later use
        $file['validated_mime'] = $mime_type;

        return true;
    }

    /**
     * Get validated MIME type from file
     */
    private function get_validated_mime($file) {
        $wp_filetype = wp_check_filetype(sanitize_file_name($file['name']));
        $mime_type = $wp_filetype['type'];

        if (empty($mime_type) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }

        return $mime_type;
    }

    // =========================================
    // FILE UPLOAD HANDLER
    // =========================================

    /**
     * AJAX: Upload file for chat
     */
    public function ajax_upload_file() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $sender_type = $this->party($_POST['sender_type'] ?? 'visitor');

        if (!$conversation_id || !$sender_type) {
            wp_send_json_error(array('message' => __('Invalid conversation', 'sc_events')));
        }

        // Rate limiting for file uploads (visitors only)
        if ($sender_type === 'visitor') {
            $rate_check = $this->check_rate_limit('file_upload');
            if (is_array($rate_check) && $rate_check['limited']) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Too many file uploads. Please wait %d seconds.', 'sc_events'),
                        $rate_check['remaining_seconds']
                    ),
                    'rate_limited' => true,
                    'wait_seconds' => $rate_check['remaining_seconds']
                ));
            }
        }

        // Check access
        if (!$this->can_access_conversation($conversation_id, $sender_type)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'sc_events')));
        }

        $file = $_FILES['file'];

        // Enhanced file validation
        $validation_result = $this->validate_uploaded_file($file);
        if (is_wp_error($validation_result)) {
            wp_send_json_error(array('message' => $validation_result->get_error_message()));
        }

        // Create upload directory
        $upload_dir = wp_upload_dir();
        $chat_dir = $upload_dir['basedir'] . '/sc-chat-files/' . date('Y/m');

        if (!file_exists($chat_dir)) {
            wp_mkdir_p($chat_dir);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'chat_' . $conversation_id . '_' . time() . '_' . wp_generate_password(8, false) . '.' . $ext;
        $destination = $chat_dir . '/' . $new_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            wp_send_json_error(array('message' => __('Failed to upload file', 'sc_events')));
        }

        // Get file URL
        $file_url = $upload_dir['baseurl'] . '/sc-chat-files/' . date('Y/m') . '/' . $new_filename;

        // Get validated MIME type
        $mime_type = $this->get_validated_mime($file);

        // Determine message type based on file type
        $is_image = strpos($mime_type, 'image/') === 0;
        $message_type = $is_image ? 'image' : 'file';

        // Create message with file
        $sender_id = ($sender_type === 'organizer') ? get_current_user_id() : null;
        $message_text = $is_image ? __('Sent an image', 'sc_events') : __('Sent a file', 'sc_events');

        $message = $this->send_message(
            $conversation_id,
            $message_text,
            $sender_type,
            $sender_id,
            $message_type,
            $file_url,
            $file['name']
        );

        if ($message) {
            wp_send_json_success(array(
                'message' => $message,
                'file_url' => $file_url,
                'file_name' => $file['name'],
                'is_image' => $is_image
            ));
        } else {
            // Clean up file if message failed
            @unlink($destination);
            wp_send_json_error(array('message' => __('Failed to send message', 'sc_events')));
        }
    }

    /**
     * AJAX: Visitor close conversation
     */
    public function ajax_visitor_close_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        // Check visitor access
        if (!$this->can_access_conversation($conversation_id, 'visitor')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $this->close_conversation($conversation_id, 'visitor');

        wp_send_json_success(array('message' => __('Conversation closed', 'sc_events')));
    }

    /**
     * AJAX: Visitor reopen conversation
     */
    public function ajax_visitor_reopen_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        // Check visitor access
        if (!$this->can_access_conversation($conversation_id, 'visitor')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        // Reopen conversation with system message
        $this->visitor_reopen_conversation($conversation_id);

        wp_send_json_success(array('message' => __('Conversation reopened', 'sc_events')));
    }

    /**
     * Visitor reopen a closed conversation
     */
    public function visitor_reopen_conversation($conversation_id) {
        global $wpdb;

        // Add system message about reopening
        $this->send_message(
            $conversation_id,
            __('This conversation has been reopened by the visitor.', 'sc_events'),
            'system',
            null,
            'system'
        );

        return $wpdb->update(
            $this->conversations_table,
            array('status' => 'active'),
            array('id' => $conversation_id)
        );
    }

    /**
     * Archive a conversation
     */
    public function archive_conversation($conversation_id) {
        global $wpdb;

        return $wpdb->update(
            $this->conversations_table,
            array('status' => 'archived'),
            array('id' => $conversation_id)
        );
    }

    /**
     * Restore a conversation from archive
     */
    public function restore_conversation($conversation_id) {
        global $wpdb;

        return $wpdb->update(
            $this->conversations_table,
            array('status' => 'active'),
            array('id' => $conversation_id)
        );
    }

    /**
     * AJAX: Archive conversation (dashboard)
     */
    public function ajax_archive_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        $this->archive_conversation($conversation_id);

        wp_send_json_success(array('message' => __('Conversation archived', 'sc_events')));
    }

    /**
     * AJAX: Restore conversation from archive (dashboard)
     */
    public function ajax_restore_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        $this->restore_conversation($conversation_id);

        wp_send_json_success(array('message' => __('Conversation restored', 'sc_events')));
    }

    /**
     * Reopen a closed conversation
     */
    public function reopen_conversation($conversation_id) {
        global $wpdb;

        // Add system message about reopening
        $this->send_message(
            $conversation_id,
            __('This conversation has been reopened by the organizer.', 'sc_events'),
            'system',
            null,
            'system'
        );

        return $wpdb->update(
            $this->conversations_table,
            array('status' => 'active'),
            array('id' => $conversation_id)
        );
    }

    /**
     * AJAX: Reopen closed conversation (dashboard)
     */
    public function ajax_reopen_conversation() {
        check_ajax_referer('sc_chat_nonce', 'nonce');

        if (!$this->is_organizer()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')), 403);
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'sc_events')));
        }

        $this->reopen_conversation($conversation_id);

        wp_send_json_success(array('message' => __('Conversation reopened', 'sc_events')));
    }
}

// Initialize chat system
function sc_chat() {
    return SC_Chat::get_instance();
}

// Initialize immediately (file is loaded from functions.php after plugins_loaded)
sc_chat();
