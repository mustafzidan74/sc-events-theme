<?php
/**
 * Push notifications (Firebase Cloud Messaging) for template-parts/dashboard/notifications.php.
 *
 * Device tokens are registered by the mobile app outside this theme (sc_fcm_tokens).
 * Sending uses FCM HTTP v1 with a Firebase service account kept outside public_html
 * (see sc_fcm_credentials_path). A send only counts devices Google accepted.
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
 * مسار ملف الـ Service Account بتاع Firebase — برّه public_html عشان محدش
 * يوصله من النت. ممكن يتغيّر من wp-config بـ SC_FCM_CREDENTIALS.
 */
function sc_fcm_credentials_path(): string {
    if (defined('SC_FCM_CREDENTIALS')) {
        return SC_FCM_CREDENTIALS;
    }
    // ABSPATH = /home/<user>/domains/<site>/public_html/ → /home/<user>
    return dirname(ABSPATH, 3) . '/private/firebase-service-account.json';
}

function sc_fcm_service_account(): ?array {
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    $path = sc_fcm_credentials_path();
    if (!is_readable($path)) {
        return $cache = null;
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || empty($data['private_key']) || empty($data['client_email']) || empty($data['project_id'])) {
        return $cache = null;
    }

    return $cache = $data;
}

function sc_fcm_b64url(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * توكن OAuth لـ FCM HTTP v1. بيتخزّن ساعة إلا دقيقتين عشان مانطلبش
 * واحد جديد مع كل إشعار.
 */
function sc_fcm_access_token(): ?string {
    $cached = get_transient('sc_fcm_access_token');
    if ($cached) {
        return $cached;
    }

    $sa = sc_fcm_service_account();
    if (!$sa) {
        return null;
    }

    $tokenUri = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $now      = time();
    $header   = sc_fcm_b64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims   = sc_fcm_b64url(wp_json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => $tokenUri,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key || !openssl_sign("$header.$claims", $signature, $key, OPENSSL_ALGO_SHA256)) {
        return null;
    }

    $response = wp_remote_post($tokenUri, [
        'body'    => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => "$header.$claims." . sc_fcm_b64url($signature),
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) {
        return null;
    }

    set_transient('sc_fcm_access_token', $body['access_token'], max(60, (int) ($body['expires_in'] ?? 3600) - 120));
    return $body['access_token'];
}

/**
 * Push can be sent: a valid service account is in place.
 */
function sc_push_connected(): bool {
    return sc_fcm_service_account() !== null;
}

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
 * Send FCM push notification to device tokens (FCM HTTP v1)
 *
 * كان بيبعت على fcm.googleapis.com/fcm/send بـ server key — جوجل شالت
 * الـ API ده (بيرجّع 404)، وكمان كان بيعدّ الإرسال "ناجح" لمجرد إن الطلب
 * اتبعت. دلوقتي بيبعت على HTTP v1 بالـ Service Account، وبيعدّ بس اللي
 * جوجل رجّعتله 200، وبيشيل التوكنات الميتة.
 *
 * @param string   $title    Notification title
 * @param string   $body     Notification body
 * @param array    $data     Extra data payload
 * @param int|null $eventId  If set, only send to attendees of this event
 * @return int Number of devices Google accepted
 */
function sc_send_fcm_notification(string $title, string $body, array $data = [], ?int $eventId = null): int {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_fcm_tokens';
    $tokens = sc_push_tokens($eventId);

    $sa          = sc_fcm_service_account();
    $accessToken = $sa ? sc_fcm_access_token() : null;
    $sent        = 0;
    $failed      = 0;

    if ($accessToken && !empty($tokens)) {
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send';

        // قيم data في HTTP v1 لازم تكون نصوص
        $payloadData = array_map('strval', array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']));

        foreach ($tokens as $token) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => (object) $payloadData,
                    'android'      => ['priority' => 'high', 'notification' => ['sound' => 'default']],
                    'apns'         => ['payload' => ['aps' => ['sound' => 'default']]],
                ]]),
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                $failed++;
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                $sent++;
                continue;
            }
            $failed++;

            // التطبيق اتمسح أو التوكن اتغيّر — نشيله عشان مايتحسبش تاني
            $error  = json_decode(wp_remote_retrieve_body($response), true);
            $reason = $error['error']['details'][0]['errorCode'] ?? ($error['error']['status'] ?? '');
            if ($code === 404 || $reason === 'UNREGISTERED') {
                $wpdb->delete($table, ['token' => $token]);
            }
        }
    }

    $wpdb->insert($wpdb->prefix . 'sc_notifications_log', [
        'title'           => $title,
        'body'            => $body,
        'event_id'        => $eventId,
        'recipient_count' => $sent,
        'sent_by'         => get_current_user_id(),
        'status'          => $sent > 0 ? ($failed ? 'partial' : 'sent') : 'failed',
        'created_at'      => current_time('mysql'),
    ]);

    return $sent;
}

/**
 * Hook: notify every device when a published event is created (switch on the page; on unless turned off).
 * Creating an event from the dashboard fires sc_event_created twice (SC_Event::create and the
 * AJAX handler), so each event is only announced once per request. Drafts are skipped.
 */
add_action('sc_event_created', function ($event_id, $title_or_data = '', $event_data = []) {
    static $announced = [];
    if (get_option('sc_push_on_new_event', '1') !== '1' || isset($announced[(int) $event_id])) {
        return;
    }
    $fields = is_array($title_or_data) ? $title_or_data : (is_array($event_data) ? $event_data : []);
    if (isset($fields['status']) && $fields['status'] !== 'publish') {
        return;
    }
    $announced[(int) $event_id] = true;

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
    if (!sc_push_connected()) {
        wp_send_json_error(['message' => __('Push notifications are not connected yet, so nothing was sent.', 'sc_events')]);
    }
    $devices = count(sc_push_tokens($eventId));
    if (!$devices) {
        wp_send_json_error(['message' => __('No devices in this audience have the app with notifications turned on.', 'sc_events')]);
    }

    $count = sc_send_fcm_notification($title, $body, [], $eventId);
    if (!$count) {
        wp_send_json_error(['message' => __('Firebase did not accept the notification, so nothing was delivered. Check the service account in Firebase.', 'sc_events')]);
    }

    wp_send_json_success([
        /* translators: 1: devices reached, 2: devices in the audience */
        'message' => sprintf(__('Delivered to %1$d of %2$d devices.', 'sc_events'), $count, $devices),
        'count'   => $count,
    ]);
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
