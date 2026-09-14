<?php
/**
 * Push notifications (Firebase Cloud Messaging) for template-parts/dashboard/notifications.php.
 *
 * Device tokens are registered by the mobile app outside this theme (sc_fcm_tokens).
 * Sending still uses the legacy server-key endpoint; Google has retired it, so a send
 * is only counted when Google answers with a success — otherwise it is logged as failed.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create FCM tokens and notifications log tables if they don't exist (once, not every request).
 */
function sc_migrate_notifications_tables() {
    if (get_option('sc_notifications_tables_v') === '1') {
        return;
    }
    global $wpdb;

    $tokens_table = $wpdb->prefix . 'sc_fcm_tokens';
    $log_table    = $wpdb->prefix . 'sc_notifications_log';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tokens_table)) !== $tokens_table) {
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

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $log_table)) !== $log_table) {
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
    update_option('sc_notifications_tables_v', '1', true);
}
add_action('init', 'sc_migrate_notifications_tables');

/**
 * Device tokens for an audience: every device, or devices of people registered for an event.
 */
function sc_push_tokens($event_id = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_fcm_tokens';
    if ($event_id) {
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT t.token FROM $table t INNER JOIN {$wpdb->prefix}sc_attendees a ON t.user_id = a.user_id WHERE a.event_id = %d AND a.status = 'active'",
            $event_id
        ));
    }
    return $wpdb->get_col("SELECT token FROM $table");
}

/**
 * Send FCM push notification to device tokens
 *
 * @param string   $title    Notification title
 * @param string   $body     Notification body
 * @param array    $data     Extra data payload
 * @param int|null $eventId  If set, only send to attendees of this event
 * @return int Number of devices Google accepted the message for
 */
function sc_send_fcm_notification(string $title, string $body, array $data = [], ?int $eventId = null): int {
    $serverKey = get_option('sc_fcm_server_key', '');
    if (empty($serverKey)) {
        return 0;
    }

    global $wpdb;
    $tokens = sc_push_tokens($eventId);
    if (empty($tokens)) {
        return 0;
    }

    $sent = 0;
    $failed = 0;
    foreach (array_chunk($tokens, 500) as $batch) {
        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'registration_ids' => $batch,
                'notification'     => ['title' => $title, 'body' => $body],
                'data'             => array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
            ]),
            'timeout' => 30,
        ]);

        // Count what Google says it delivered, not that the request left the server.
        $result = is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200 ? null : json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($result) && isset($result['success'])) {
            $sent += (int) $result['success'];
            $failed += (int) ($result['failure'] ?? 0);
        } else {
            $failed += count($batch);
        }
    }

    $wpdb->insert($wpdb->prefix . 'sc_notifications_log', [
        'title'           => $title,
        'body'            => $body,
        'event_id'        => $eventId,
        'recipient_count' => $sent,
        'sent_by'         => get_current_user_id(),
        'status'          => $sent ? ($failed ? 'partial' : 'sent') : 'failed',
        'created_at'      => current_time('mysql'),
    ]);

    return $sent;
}

/**
 * Hook: notify every device when an event is created — only when switched on.
 * It used to fire for every new event, drafts and tests included.
 */
add_action('sc_event_created', function ($event_id, $title_or_data = '', $event_data = []) {
    if (get_option('sc_push_on_new_event', '0') !== '1') {
        return;
    }
    if (is_array($title_or_data)) {
        $title = $title_or_data['title'] ?? 'New Event';
    } else {
        $title = !empty($title_or_data) ? (string) $title_or_data : 'New Event';
    }
    $body = sc_t('new_event_notification_body', 'A new event has been added. Check it out!');
    sc_send_fcm_notification($title, $body, ['event_id' => (string) $event_id]);
}, 10, 3);

/* ==========================================================================
   Dashboard
   ========================================================================== */

function sc_push_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')], 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')], 403);
    }
}

add_action('wp_ajax_sc_send_notification', 'sc_send_notification_handler');
function sc_send_notification_handler() {
    sc_push_verify_request();

    $title   = sanitize_text_field(wp_unslash($_POST['notif_title'] ?? ''));
    $body    = sanitize_textarea_field(wp_unslash($_POST['notif_body'] ?? ''));
    $target  = sanitize_key(wp_unslash($_POST['notif_target'] ?? 'all'));
    $eventId = ($target === 'event' && !empty($_POST['notif_event_id'])) ? absint($_POST['notif_event_id']) : null;

    $errors = [];
    if ($title === '') {
        $errors['notif_title'] = __('Enter a title.', 'sc_events');
    } elseif (mb_strlen($title) > 65) {
        $errors['notif_title'] = __('Keep the title under 65 characters so phones show it in full.', 'sc_events');
    }
    if ($body === '') {
        $errors['notif_body'] = __('Enter the message.', 'sc_events');
    } elseif (mb_strlen($body) > 240) {
        $errors['notif_body'] = __('Keep the message under 240 characters.', 'sc_events');
    }
    if ($target === 'event' && !$eventId) {
        $errors['notif_event_id'] = __('Choose the event.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(['message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors]);
    }
    if (get_option('sc_fcm_server_key', '') === '') {
        wp_send_json_error(['message' => __('Push notifications are not connected yet, so nothing was sent.', 'sc_events')]);
    }
    $devices = count(sc_push_tokens($eventId));
    if (!$devices) {
        wp_send_json_error(['message' => __('No devices in this audience have the app with notifications turned on.', 'sc_events')]);
    }

    $count = sc_send_fcm_notification($title, $body, [], $eventId);
    if (!$count) {
        wp_send_json_error(['message' => __('Firebase did not accept the notification. Check the connection settings; nothing was delivered.', 'sc_events')]);
    }

    wp_send_json_success([
        /* translators: 1: devices reached, 2: devices in the audience */
        'message' => sprintf(__('Delivered to %1$d of %2$d devices.', 'sc_events'), $count, $devices),
        'count'   => $count,
    ]);
}

add_action('wp_ajax_sc_save_fcm_key', 'sc_save_fcm_key_handler');
function sc_save_fcm_key_handler() {
    sc_push_verify_request();
    $key = sanitize_text_field(wp_unslash($_POST['fcm_server_key'] ?? ''));
    update_option('sc_fcm_server_key', $key, false);
    wp_send_json_success(['message' => $key === '' ? __('Key removed.', 'sc_events') : __('Key saved.', 'sc_events')]);
}

add_action('wp_ajax_sc_push_settings', 'sc_push_settings_handler');
function sc_push_settings_handler() {
    sc_push_verify_request();
    update_option('sc_push_on_new_event', !empty($_POST['on_new_event']) ? '1' : '0', false);
    wp_send_json_success(['message' => __('Saved.', 'sc_events')]);
}

add_action('wp_ajax_sc_push_audience', 'sc_push_audience_handler');
function sc_push_audience_handler() {
    sc_push_verify_request();
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    wp_send_json_success(['devices' => count(sc_push_tokens($event_id ?: null))]);
}

add_action('wp_ajax_sc_get_notifications_history', 'sc_get_notifications_history_handler');
function sc_get_notifications_history_handler() {
    sc_push_verify_request();

    global $wpdb;
    $log_table = $wpdb->prefix . 'sc_notifications_log';
    $per_page  = isset($_POST['per_page']) ? min(100, max(1, absint($_POST['per_page']))) : 25;
    $page      = max(1, absint($_POST['page'] ?? 1));
    $search    = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));

    $where = '1=1';
    $values = [];
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where = '(n.title LIKE %s OR n.body LIKE %s)';
        $values = [$like, $like];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT n.id, n.title, n.body, n.event_id, n.recipient_count, n.status, n.created_at, e.title AS event_title, u.display_name AS sender_name
         FROM $log_table n
         LEFT JOIN {$wpdb->prefix}sc_events e ON n.event_id = e.id
         LEFT JOIN {$wpdb->users} u ON n.sent_by = u.ID
         WHERE $where ORDER BY n.created_at DESC, n.id DESC LIMIT %d OFFSET %d",
        array_merge($values, [$per_page, ($page - 1) * $per_page])
    ));
    $count_sql = "SELECT COUNT(*) FROM $log_table n WHERE $where";

    wp_send_json_success([
        'rows'  => array_map(function ($r) {
            return [
                'id'      => (int) $r->id,
                'title'   => (string) $r->title,
                'body'    => (string) $r->body,
                'event'   => $r->event_id ? ((string) $r->event_title ?: '#' . $r->event_id) : '',
                'devices' => (int) $r->recipient_count,
                'status'  => (string) $r->status,
                'by'      => (string) $r->sender_name,
                'at'      => $r->created_at,
            ];
        }, $rows),
        'total' => (int) $wpdb->get_var($values ? $wpdb->prepare($count_sql, $values) : $count_sql),
    ]);
}
