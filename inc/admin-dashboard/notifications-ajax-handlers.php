<?php
/**
 * Push Notifications AJAX Handlers
 * Firebase Cloud Messaging (FCM) integration
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create FCM tokens and notifications log tables if they don't exist
 */
function sc_migrate_notifications_tables() {
    global $wpdb;

    $tokens_table = $wpdb->prefix . 'sc_fcm_tokens';
    $log_table    = $wpdb->prefix . 'sc_notifications_log';

    if ($wpdb->get_var("SHOW TABLES LIKE '$tokens_table'") !== $tokens_table) {
        $wpdb->query("CREATE TABLE `$tokens_table` (
          `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` bigint(20) UNSIGNED DEFAULT NULL,
          `token` varchar(255) NOT NULL,
          `platform` varchar(20) DEFAULT 'android',
          `created_at` datetime NOT NULL,
          `updated_at` datetime NOT NULL,
          PRIMARY KEY  (`id`),
          UNIQUE KEY `token` (`token`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") !== $log_table) {
        $wpdb->query("CREATE TABLE `$log_table` (
          `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `body` text NOT NULL,
          `event_id` bigint(20) UNSIGNED DEFAULT NULL,
          `recipient_count` int(11) DEFAULT 0,
          `sent_by` bigint(20) UNSIGNED DEFAULT NULL,
          `status` varchar(20) DEFAULT 'sent',
          `created_at` datetime NOT NULL,
          PRIMARY KEY  (`id`),
          KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
add_action('init', 'sc_migrate_notifications_tables');

/**
 * Send FCM push notification to device tokens
 *
 * @param string   $title    Notification title
 * @param string   $body     Notification body
 * @param array    $data     Extra data payload
 * @param int|null $eventId  If set, only send to attendees of this event
 * @return int Number of tokens notified
 */
function sc_send_fcm_notification(string $title, string $body, array $data = [], ?int $eventId = null): int {
    $serverKey = get_option('sc_fcm_server_key', '');
    if (empty($serverKey)) return 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_fcm_tokens';

    if ($eventId) {
        $attendees_table = $wpdb->prefix . 'sc_attendees';
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT t.token FROM $table t
             INNER JOIN $attendees_table a ON t.user_id = a.user_id
             WHERE a.event_id = %d",
            $eventId
        ));
    } else {
        $tokens = $wpdb->get_col("SELECT token FROM $table");
    }

    if (empty($tokens)) return 0;

    $sent = 0;
    foreach (array_chunk($tokens, 500) as $batch) {
        $payload = json_encode([
            'registration_ids' => $batch,
            'notification'     => ['title' => $title, 'body' => $body],
            'data'             => array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
        ]);

        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $payload,
            'timeout' => 30,
        ]);

        if (!is_wp_error($response)) {
            $sent += count($batch);
        }
    }

    // Log notification
    $wpdb->insert($wpdb->prefix . 'sc_notifications_log', [
        'title'           => $title,
        'body'            => $body,
        'event_id'        => $eventId,
        'recipient_count' => $sent,
        'sent_by'         => get_current_user_id(),
        'status'          => 'sent',
        'created_at'      => current_time('mysql'),
    ]);

    return $sent;
}

/**
 * Hook: auto-send notification when a new event is created
 */
add_action('sc_event_created', function($event_id, $title_or_data = '', $event_data = []) {
    // Handle both: do_action('sc_event_created', $id, $title, $data) and do_action('sc_event_created', $id, $data)
    if (is_array($title_or_data)) {
        $title = $title_or_data['title'] ?? 'New Event';
    } else {
        $title = !empty($title_or_data) ? (string) $title_or_data : 'New Event';
    }
    $body = sc_t('new_event_notification_body', 'A new event has been added. Check it out!');
    sc_send_fcm_notification($title, $body, ['event_id' => (string) $event_id]);
}, 10, 3);

// ==========================================
// AJAX: Send Custom Notification
// ==========================================
add_action('wp_ajax_sc_send_notification', 'sc_send_notification_handler');
function sc_send_notification_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')]);
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')]);
    }

    $title   = sanitize_text_field($_POST['notif_title'] ?? '');
    $body    = sanitize_textarea_field($_POST['notif_body'] ?? '');
    $target  = sanitize_text_field($_POST['notif_target'] ?? 'all');
    $eventId = ($target === 'event' && !empty($_POST['notif_event_id'])) ? (int) $_POST['notif_event_id'] : null;

    if (empty($title) || empty($body)) {
        wp_send_json_error(['message' => __('Title and message are required.', 'sc_events')]);
    }

    $count = sc_send_fcm_notification($title, $body, [], $eventId);

    wp_send_json_success([
        'message' => sprintf(__('Notification sent to %d device(s).', 'sc_events'), $count),
        'count'   => $count,
    ]);
}

// ==========================================
// AJAX: Save FCM Server Key
// ==========================================
add_action('wp_ajax_sc_save_fcm_key', 'sc_save_fcm_key_handler');
function sc_save_fcm_key_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')]);
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')]);
    }

    $key = sanitize_text_field($_POST['fcm_server_key'] ?? '');
    update_option('sc_fcm_server_key', $key);

    wp_send_json_success(['message' => __('FCM Server Key saved.', 'sc_events')]);
}

// ==========================================
// AJAX: Get Notifications History (paginated)
// ==========================================
add_action('wp_ajax_sc_get_notifications_history', 'sc_get_notifications_history_handler');
function sc_get_notifications_history_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')]);
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')]);
    }

    global $wpdb;
    $log_table    = $wpdb->prefix . 'sc_notifications_log';
    $events_table = $wpdb->prefix . 'sc_events';

    $page     = max(1, (int) ($_POST['page'] ?? 1));
    $per_page = 20;
    $offset   = ($page - 1) * $per_page;

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT n.*, e.title as event_title, u.display_name as sender_name
         FROM $log_table n
         LEFT JOIN $events_table e ON n.event_id = e.id
         LEFT JOIN {$wpdb->users} u ON n.sent_by = u.ID
         ORDER BY n.created_at DESC
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    wp_send_json_success([
        'items'      => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'last_page'  => max(1, ceil($total / $per_page)),
    ]);
}

// ==========================================
// AJAX: Get Notifications Stats
// ==========================================
add_action('wp_ajax_sc_get_notifications_stats', 'sc_get_notifications_stats_handler');
function sc_get_notifications_stats_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')]);
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')]);
    }

    global $wpdb;
    $tokens_table = $wpdb->prefix . 'sc_fcm_tokens';
    $log_table    = $wpdb->prefix . 'sc_notifications_log';
    $today        = date('Y-m-d');

    $total_tokens  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table");
    $sent_today    = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(recipient_count), 0) FROM $log_table WHERE DATE(created_at) = %s",
        $today
    ));
    $total_sent    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
    $has_fcm_key   = !empty(get_option('sc_fcm_server_key', ''));

    wp_send_json_success([
        'total_tokens' => $total_tokens,
        'sent_today'   => $sent_today,
        'total_sent'   => $total_sent,
        'has_fcm_key'  => $has_fcm_key,
    ]);
}
