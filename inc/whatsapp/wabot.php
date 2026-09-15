<?php
/**
 * WhatsApp through wa-bot (a self-hosted WhatsApp gateway, see wa-bot/docs/AI-INTEGRATION.md).
 *
 * - Numbers: several wa-bot devices, each with its own device token and webhook secret.
 * - Outbox: every message goes through {prefix}sc_wa_outbox and is retried, because wa-bot drops a
 *   send when the number is disconnected.
 * - Chat bridge: a visitor message alerts staff on WhatsApp in the order set on the WhatsApp page
 *   (next person after N minutes without a reply, or everyone at once); a staff reply reaches a
 *   visitor who agreed to WhatsApp; WhatsApp replies come back into the same conversation.
 * - Webhook: POST /wp-json/sc/v1/wabot/{number key}, HMAC-SHA256 over "{timestamp}\n{raw body}".
 *
 * Secrets (device tokens, webhook secrets, admin key) live in the sc_wabot_secrets option and are
 * never sent to a browser. Dashboard handlers: inc/admin-dashboard/whatsapp-dashboard.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

const SC_WABOT_DB_VERSION = '2';

/* ==========================================================================
   Settings and secrets
   ========================================================================== */

function sc_wabot_settings() {
    $saved = get_option('sc_wabot_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), array(
        'base_url'         => 'https://whatsapp.super-coding.com',
        'numbers'          => array(),      // key => label, device_id, phone, enabled, chat, status, status_at, linked
        'recipients'       => array(),      // ordered: name, phone, enabled
        'alert_mode'       => 'escalate',   // escalate | all
        'escalate_minutes' => 10,
        'chat_replies'     => 1,            // staff replies go to visitors who agreed to WhatsApp
    ));
}

function sc_wabot_save_settings($settings) {
    update_option('sc_wabot_settings', $settings, false);
}

function sc_wabot_secrets() {
    $saved = get_option('sc_wabot_secrets', array());
    $saved = is_array($saved) ? $saved : array();
    if (!isset($saved['numbers']) || !is_array($saved['numbers'])) {
        $saved['numbers'] = array();
    }
    return $saved;
}

function sc_wabot_save_secrets($secrets) {
    update_option('sc_wabot_secrets', $secrets, false);
}

/** Egyptian-first normalisation to international digits (201xxxxxxxxx). Empty string when unusable. */
function sc_wabot_phone($raw) {
    $d = preg_replace('/\D/', '', (string) $raw);
    if ($d === '') {
        return '';
    }
    if (strpos($d, '00') === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && strpos($d, '01') === 0) {
        $d = '20' . substr($d, 1);
    } elseif (strlen($d) === 10 && strpos($d, '1') === 0) {
        $d = '20' . $d;
    } elseif (strlen($d) === 13 && strpos($d, '2001') === 0) {
        $d = '20' . substr($d, 3); // "+20 01…" typed with the leading zero
    }
    return (strlen($d) >= 8 && strlen($d) <= 15) ? $d : '';
}

/** Digits of a phone JID ("201…@s.whatsapp.net"); empty for groups and privacy (@lid) ids. */
function sc_wabot_jid_phone($jid) {
    $jid = (string) $jid;
    if (!preg_match('/^(\d{8,15})(?::\d+)?@s\.whatsapp\.net$/', $jid, $m)) {
        return '';
    }
    return $m[1];
}

function sc_wabot_webhook_url($key) {
    return rest_url('sc/v1/wabot/' . rawurlencode($key));
}

/** Numbers that may be used, first the ones marked for chat, connected first. */
function sc_wabot_pick_number($purpose = 'chat') {
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    $candidates = array();
    foreach ($settings['numbers'] as $key => $n) {
        if (empty($n['enabled']) || empty($secrets['numbers'][$key]['token'])) {
            continue;
        }
        if ($purpose === 'chat' && empty($n['chat'])) {
            continue;
        }
        $candidates[$key] = ($n['status'] ?? '') === 'connected' ? 0 : 1;
    }
    if (!$candidates) {
        return null;
    }
    asort($candidates);
    return (string) key($candidates);
}

/* ==========================================================================
   HTTP
   ========================================================================== */

/**
 * Call wa-bot. Returns array('code' => int, 'data' => array) or WP_Error.
 *
 * @param string|null $key Number key, or null to use the admin key.
 */
function sc_wabot_request($key, $method, $path, $body = null, $timeout = 15) {
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    $token = $key === null ? ($secrets['admin_key'] ?? '') : ($secrets['numbers'][$key]['token'] ?? '');
    if ($token === '') {
        return new WP_Error('sc_wabot_no_token', __('No token saved for this number.', 'sc_events'));
    }
    $base = untrailingslashit((string) $settings['base_url']);
    if (!preg_match('#^https?://#i', $base)) {
        return new WP_Error('sc_wabot_no_url', __('The wa-bot address is not set.', 'sc_events'));
    }
    $args = array(
        'method'  => $method,
        'timeout' => $timeout,
        'headers' => array('Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'),
    );
    if ($body !== null) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = wp_json_encode($body);
    }
    // wa-bot runs on its own public server; refuse internal addresses unless a local test allows it.
    $response = apply_filters('sc_wabot_allow_internal_url', false)
        ? wp_remote_request($base . $path, $args)
        : wp_safe_remote_request($base . $path, $args);
    if (is_wp_error($response)) {
        return $response;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return array('code' => (int) wp_remote_retrieve_response_code($response), 'data' => is_array($data) ? $data : array());
}

/**
 * Change a few fields of one number. Status checks and webhooks run alongside page saves, so this
 * re-reads the stored settings first and never brings back a number that was removed meanwhile.
 */
function sc_wabot_patch_number($key, $fields) {
    wp_cache_delete('sc_wabot_settings', 'options');
    $settings = sc_wabot_settings();
    if (!isset($settings['numbers'][$key])) {
        return false;
    }
    $settings['numbers'][$key] = array_merge($settings['numbers'][$key], $fields);
    sc_wabot_save_settings($settings);
    return true;
}

/** Ask wa-bot for one number's status and remember it. */
function sc_wabot_refresh_status($key) {
    if (!isset(sc_wabot_settings()['numbers'][$key])) {
        return null;
    }
    $fields = array();
    $result = sc_wabot_request($key, 'GET', '/api/status', null, 10);
    if (is_wp_error($result)) {
        $status = 'unreachable';
        $error = $result->get_error_message();
    } elseif ($result['code'] === 401) {
        $status = 'token_rejected';
        $error = $result['data']['error'] ?? '';
    } elseif ($result['code'] >= 400) {
        $status = 'error';
        $error = $result['data']['error'] ?? ('HTTP ' . $result['code']);
    } else {
        $status = sanitize_key($result['data']['status'] ?? 'unknown');
        $error = '';
        if (!empty($result['data']['device']['phone'])) {
            $fields['phone'] = preg_replace('/\D/', '', $result['data']['device']['phone']);
        }
        if (!empty($result['data']['device']['id'])) {
            $fields['device_id'] = sanitize_text_field($result['data']['device']['id']);
        }
    }
    $fields['status'] = $status;
    $fields['status_error'] = $error ? mb_substr(wp_strip_all_tags($error), 0, 160) : '';
    $fields['status_at'] = current_time('mysql');
    return sc_wabot_patch_number($key, $fields) ? $status : null;
}

function sc_wabot_refresh_all() {
    foreach (array_keys(sc_wabot_settings()['numbers']) as $key) {
        sc_wabot_refresh_status($key);
    }
}

/**
 * Overall state for the top-bar chip: off (nothing set up), ok, partial, down, unknown.
 */
function sc_wabot_overall_state() {
    $settings = sc_wabot_settings();
    $enabled = array_filter($settings['numbers'], function ($n) { return !empty($n['enabled']); });
    if (!$enabled) {
        return array('state' => 'off', 'numbers' => array());
    }
    $connected = 0;
    $stale = false;
    foreach ($enabled as $n) {
        if (($n['status'] ?? '') === 'connected') {
            $connected++;
        }
        if (empty($n['status_at']) || strtotime($n['status_at']) < current_time('timestamp') - 20 * MINUTE_IN_SECONDS) {
            $stale = true;
        }
    }
    $state = $connected === count($enabled) ? 'ok' : ($connected > 0 ? 'partial' : 'down');
    if ($stale && $state === 'ok') {
        $state = 'unknown';
    }
    return array('state' => $state, 'numbers' => $enabled, 'connected' => $connected);
}

/* ==========================================================================
   Tables
   ========================================================================== */

function sc_wabot_install() {
    if (get_option('sc_wabot_db_version') === SC_WABOT_DB_VERSION) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}sc_wa_outbox (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        number_key varchar(40) NOT NULL,
        to_phone varchar(32) NOT NULL,
        body text NOT NULL,
        context varchar(40) NOT NULL DEFAULT '',
        context_id bigint(20) unsigned DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
        next_attempt_at datetime DEFAULT NULL,
        expires_at datetime DEFAULT NULL,
        wa_message_id varchar(120) DEFAULT NULL,
        wa_jid varchar(120) DEFAULT NULL,
        delivery varchar(20) DEFAULT NULL,
        error varchar(255) DEFAULT NULL,
        campaign_id bigint(20) unsigned DEFAULT NULL,
        recipient_name varchar(190) DEFAULT NULL,
        used_number varchar(40) DEFAULT NULL,
        tried_numbers varchar(255) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY status_next (status,next_attempt_at),
        KEY wa_message_id (wa_message_id),
        KEY context (context,context_id),
        KEY campaign (campaign_id,status)
    ) $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}sc_wa_campaigns (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(190) NOT NULL,
        source varchar(40) NOT NULL DEFAULT 'manual',
        event_id bigint(20) unsigned DEFAULT NULL,
        audience varchar(60) NOT NULL DEFAULT '',
        message text NOT NULL,
        interval_seconds smallint(5) unsigned NOT NULL DEFAULT 45,
        status varchar(20) NOT NULL DEFAULT 'running',
        paused_reason varchar(190) DEFAULT NULL,
        total int(10) unsigned NOT NULL DEFAULT 0,
        next_send_at datetime DEFAULT NULL,
        created_by bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL,
        started_at datetime DEFAULT NULL,
        finished_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY status (status)
    ) $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}sc_wa_inbound (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        number_key varchar(40) NOT NULL,
        delivery_id varchar(80) NOT NULL,
        event varchar(40) NOT NULL,
        from_jid varchar(120) DEFAULT NULL,
        push_name varchar(190) DEFAULT NULL,
        type varchar(20) DEFAULT NULL,
        text text,
        conversation_id bigint(20) unsigned DEFAULT NULL,
        outcome varchar(30) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY delivery (number_key,delivery_id),
        KEY outcome (outcome,created_at)
    ) $charset;");

    $conv = $wpdb->prefix . 'sc_conversations';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conv))) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM $conv");
        $add = array(
            'wa_opt_in'     => "ADD COLUMN wa_opt_in tinyint(1) NOT NULL DEFAULT 0",
            'wa_jid'        => "ADD COLUMN wa_jid varchar(120) DEFAULT NULL",
            'wa_alert_step' => "ADD COLUMN wa_alert_step tinyint(3) unsigned NOT NULL DEFAULT 0",
            'wa_alerted_at' => "ADD COLUMN wa_alerted_at datetime DEFAULT NULL",
        );
        foreach ($add as $col => $sql) {
            if (!in_array($col, $cols, true)) {
                $wpdb->query("ALTER TABLE $conv $sql");
            }
        }
    }
    update_option('sc_wabot_db_version', SC_WABOT_DB_VERSION, false);
}
add_action('init', 'sc_wabot_install', 5);

/* ==========================================================================
   Outbox
   ========================================================================== */

/**
 * Queue a WhatsApp text. It is tried at the end of this request and retried by cron.
 *
 * @return int|false Outbox id.
 */
function sc_wabot_enqueue($number_key, $phone, $text, $context = '', $context_id = null, $ttl_minutes = 1440) {
    global $wpdb;
    $phone = sc_wabot_phone($phone);
    if (!$number_key || $phone === '' || trim($text) === '') {
        return false;
    }
    $now = current_time('mysql');
    $ok = $wpdb->insert($wpdb->prefix . 'sc_wa_outbox', array(
        'number_key'      => $number_key,
        'to_phone'        => $phone,
        'body'            => mb_substr($text, 0, 4000),
        'context'         => $context,
        'context_id'      => $context_id,
        'status'          => 'pending',
        'next_attempt_at' => $now,
        'expires_at'      => date('Y-m-d H:i:s', current_time('timestamp') + $ttl_minutes * MINUTE_IN_SECONDS),
        'created_at'      => $now,
    ));
    if (!$ok) {
        return false;
    }
    $id = (int) $wpdb->insert_id;
    sc_wabot_send_after_response($id);
    return $id;
}

/** Send once the visitor or staff member already has their answer. */
function sc_wabot_send_after_response($id) {
    static $queued = array();
    $queued[] = (int) $id;
    if (count($queued) > 1) {
        return;
    }
    register_shutdown_function(function () use (&$queued) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        foreach ($queued as $outbox_id) {
            sc_wabot_process_one($outbox_id);
        }
    });
}

/**
 * Numbers to try for a message, in order: the one it was queued on, then the other usable numbers
 * (connected first). Chat messages prefer chat numbers; campaign messages use numbers allowed for bulk.
 */
function sc_wabot_candidates($preferred, $context, $exclude = array()) {
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    $bulk = strpos((string) $context, 'campaign') === 0;
    $scored = array();
    foreach ($settings['numbers'] as $key => $n) {
        if (in_array($key, $exclude, true) || empty($n['enabled']) || empty($secrets['numbers'][$key]['token'])) {
            continue;
        }
        if ($bulk && isset($n['bulk']) && !$n['bulk']) {
            continue;
        }
        $score = ($n['status'] ?? '') === 'connected' ? 0 : 10;
        if ($key === $preferred) {
            $score -= 5;
        }
        if (!$bulk && !empty($n['chat'])) {
            $score -= 1;
        }
        if (in_array($n['status'] ?? '', array('banned', 'logged_out', 'token_rejected', 'deleted'), true)) {
            $score += 50; // last resort only
        }
        $scored[$key] = $score;
    }
    asort($scored);
    return array_keys($scored);
}

function sc_wabot_process_one($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_wa_outbox';
    // Claim the row so cron and a request never send it twice.
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE $table SET status = 'sending', updated_at = %s WHERE id = %d AND status = 'pending'",
        current_time('mysql'), $id
    ));
    if (!$claimed) {
        return;
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $now = current_time('timestamp');
    $update = array('attempts' => min(255, (int) $row->attempts + 1), 'updated_at' => current_time('mysql'));

    if ($row->expires_at && strtotime($row->expires_at) < $now) {
        $wpdb->update($table, $update + array('status' => 'expired', 'error' => 'Not sent in time'), array('id' => $id));
        return;
    }

    $tried = array();
    $retry = false;
    $errors = array();
    foreach (sc_wabot_candidates($row->number_key, $row->context) as $key) {
        $tried[] = $key;
        $result = sc_wabot_request($key, 'POST', '/api/send-text', array('to' => $row->to_phone, 'text' => $row->body), 20);
        if (is_wp_error($result)) {
            // wa-bot itself is unreachable: every number lives there, so wait and retry.
            $errors[] = $result->get_error_message();
            $retry = true;
            break;
        }
        $code = $result['code'];
        $data = $result['data'];
        if ($code === 200 && !empty($data['ok'])) {
            $update += array('status' => 'sent', 'error' => null, 'used_number' => $key,
                'wa_message_id' => sanitize_text_field($data['id'] ?? ''), 'wa_jid' => sanitize_text_field($data['jid'] ?? ''));
            break;
        }
        if ($code === 200 && !empty($data['skipped'])) {
            $update += array('status' => 'skipped', 'error' => 'This number is not on WhatsApp', 'used_number' => $key);
            break;
        }
        if ($code === 400) {
            $update += array('status' => 'failed', 'error' => mb_substr((string) ($data['error'] ?? 'Rejected'), 0, 250), 'used_number' => $key);
            break;
        }
        // The number could not send (token rejected, rate limited, disconnected): try the next number.
        $errors[] = (sc_wabot_settings()['numbers'][$key]['label'] ?? $key) . ': ' . (string) ($data['error'] ?? ('HTTP ' . $code));
        if ($code === 401) {
            sc_wabot_patch_number($key, array('status' => 'token_rejected', 'status_at' => current_time('mysql'), 'status_error' => mb_substr((string) ($data['error'] ?? ''), 0, 160)));
        } elseif ($code >= 500) {
            sc_wabot_patch_number($key, array('status' => 'disconnected', 'status_at' => current_time('mysql'), 'status_error' => mb_substr((string) ($data['error'] ?? ''), 0, 160)));
        }
        $retry = true;
    }
    if (!isset($update['status']) && !$retry) {
        $retry = true;
        $errors[] = 'No WhatsApp number is available';
    }
    $update['tried_numbers'] = mb_substr(implode(',', $tried), 0, 255);
    if (isset($update['status'])) {
        $retry = false;
    } else {
        $update['error'] = mb_substr(implode(' | ', $errors), 0, 250);
    }
    if ($retry) {
        $delays = array(1, 5, 15, 30, 60, 120);
        $attempt = $update['attempts'];
        if ($attempt > count($delays)) {
            $update['status'] = 'failed';
        } else {
            $update['status'] = 'pending';
            $update['next_attempt_at'] = date('Y-m-d H:i:s', $now + $delays[$attempt - 1] * MINUTE_IN_SECONDS);
        }
    }
    $wpdb->update($table, $update, array('id' => $id));

    if (($update['status'] ?? '') === 'sent' && $row->context === 'chat_reply' && !empty($update['wa_jid'])) {
        $wpdb->update($wpdb->prefix . 'sc_conversations', array('wa_jid' => $update['wa_jid']), array('id' => (int) $row->context_id));
    }
}

function sc_wabot_process_due() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_wa_outbox';
    // Rows stuck in "sending" (request died mid-send) go back after 10 minutes.
    $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'pending' WHERE status = 'sending' AND updated_at < %s", date('Y-m-d H:i:s', current_time('timestamp') - 600)));
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE status = 'pending' AND campaign_id IS NULL AND next_attempt_at <= %s ORDER BY id LIMIT 25", current_time('mysql')));
    foreach ($ids as $id) {
        sc_wabot_process_one((int) $id);
    }
}

/* ==========================================================================
   Cron
   ========================================================================== */

add_filter('cron_schedules', function ($schedules) {
    $schedules['sc_every_minute'] = array('interval' => 60, 'display' => 'Every minute');
    $schedules['sc_every_five_minutes'] = array('interval' => 300, 'display' => 'Every five minutes');
    return $schedules;
});

add_action('init', function () {
    if (!sc_wabot_settings()['numbers']) {
        foreach (array('sc_wabot_tick', 'sc_wabot_status_tick') as $hook) {
            if (wp_next_scheduled($hook)) {
                wp_clear_scheduled_hook($hook);
            }
        }
        return;
    }
    if (!wp_next_scheduled('sc_wabot_tick')) {
        wp_schedule_event(time() + 60, 'sc_every_minute', 'sc_wabot_tick');
    }
    if (!wp_next_scheduled('sc_wabot_status_tick')) {
        wp_schedule_event(time() + 120, 'sc_every_five_minutes', 'sc_wabot_status_tick');
    }
});
add_action('sc_wabot_tick', function () {
    sc_wabot_process_due();
    sc_wabot_escalate_alerts();
    if (function_exists('sc_wabot_campaign_tick')) {
        sc_wabot_campaign_tick();
    }
});
add_action('sc_wabot_status_tick', 'sc_wabot_refresh_all');

/* ==========================================================================
   Chat bridge
   ========================================================================== */

/** Set while a WhatsApp message is being written into the chat, so it is not sent back out. */
$GLOBALS['sc_wabot_inbound'] = false;

function sc_wabot_recipients() {
    $out = array();
    foreach (sc_wabot_settings()['recipients'] as $r) {
        $phone = sc_wabot_phone($r['phone'] ?? '');
        if (!empty($r['enabled']) && $phone !== '') {
            $out[] = array('name' => (string) ($r['name'] ?? ''), 'phone' => $phone);
        }
    }
    return $out;
}

function sc_wabot_alert_text($conversation, $message) {
    $event = '';
    if (!empty($conversation->event_id) && class_exists('SC_Event')) {
        $ev = SC_Event::get((int) $conversation->event_id);
        $event = $ev ? $ev->title : '';
    }
    $lines = array('💬 رسالة جديدة في شات الموقع');
    $who = trim(($conversation->visitor_name ?: 'زائر') . (sc_wabot_phone($conversation->visitor_phone) ? ' · +' . sc_wabot_phone($conversation->visitor_phone) : ''));
    $lines[] = 'من: ' . $who;
    if ($event) {
        $lines[] = 'الفعالية: ' . $event;
    }
    $lines[] = '';
    $lines[] = '«' . mb_substr(trim(wp_strip_all_tags($message)), 0, 400) . '»';
    $lines[] = '';
    $lines[] = 'الرد من هنا: ' . home_url('/event-manager-dashboard/chat?conversation=' . (int) $conversation->id);
    return implode("\n", $lines);
}

/** Alert the next person (or everyone) about a conversation waiting for staff. */
function sc_wabot_alert($conversation_id, $message, $escalating = false) {
    global $wpdb;
    $number = sc_wabot_pick_number('chat');
    $recipients = sc_wabot_recipients();
    if (!$number || !$recipients) {
        return;
    }
    $table = $wpdb->prefix . 'sc_conversations';
    $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $conversation_id));
    if (!$conv) {
        return;
    }
    $settings = sc_wabot_settings();
    $now = current_time('timestamp');
    $step = (int) $conv->wa_alert_step;
    $recent = $conv->wa_alerted_at && strtotime($conv->wa_alerted_at) > $now - max(1, (int) $settings['escalate_minutes']) * MINUTE_IN_SECONDS;

    if (!$escalating && $step > 0 && $recent) {
        return; // someone was just told about this conversation
    }
    if (!$escalating) {
        $step = 0; // a fresh visitor message starts again with the first person
    }
    $text = sc_wabot_alert_text($conv, $message);

    if ($settings['alert_mode'] === 'all') {
        if ($escalating) {
            return;
        }
        foreach ($recipients as $r) {
            sc_wabot_enqueue($number, $r['phone'], $text, 'chat_alert', $conv->id, 60);
        }
        $step = count($recipients);
    } else {
        if (!isset($recipients[$step])) {
            return;
        }
        sc_wabot_enqueue($number, $recipients[$step]['phone'], $text, 'chat_alert', $conv->id, 60);
        $step++;
    }
    $wpdb->update($table, array('wa_alert_step' => $step, 'wa_alerted_at' => current_time('mysql')), array('id' => $conv->id));
}

/** Cron: conversations still waiting after N minutes go to the next person in the list. */
function sc_wabot_escalate_alerts() {
    global $wpdb;
    $settings = sc_wabot_settings();
    $count = count(sc_wabot_recipients());
    if ($settings['alert_mode'] !== 'escalate' || $count < 2) {
        return;
    }
    $p = $wpdb->prefix;
    $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - max(1, (int) $settings['escalate_minutes']) * MINUTE_IN_SECONDS);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id,
                (SELECT m.message FROM {$p}sc_chat_messages m WHERE m.conversation_id = c.id AND m.sender_type = 'visitor' ORDER BY m.id DESC LIMIT 1) AS last_visitor_text
         FROM {$p}sc_conversations c
         WHERE c.status = 'active' AND c.wa_alert_step BETWEEN 1 AND %d AND c.wa_alerted_at <= %s
           AND NOT EXISTS (SELECT 1 FROM {$p}sc_chat_messages o WHERE o.conversation_id = c.id AND o.sender_type = 'organizer' AND o.created_at >= c.wa_alerted_at)
         LIMIT 20",
        $count - 1, $cutoff
    ));
    foreach ($rows as $r) {
        sc_wabot_alert((int) $r->id, (string) $r->last_visitor_text, true);
    }
}

/** Every chat message passes here (fired from SC_Chat::send_message). */
function sc_wabot_on_chat_message($conversation_id, $message) {
    if (!$message || $message->message_type === 'system' || !sc_wabot_settings()['numbers']) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_conversations';

    if ($message->sender_type === 'visitor') {
        $text = $message->message_type === 'text' ? $message->message : '[' . __('file', 'sc_events') . '] ' . ($message->file_name ?: '');
        sc_wabot_alert((int) $conversation_id, $text);
        return;
    }

    if ($message->sender_type !== 'organizer') {
        return;
    }
    // Staff answered: the alert chain for this conversation is done.
    $wpdb->update($table, array('wa_alert_step' => 0, 'wa_alerted_at' => null), array('id' => $conversation_id));

    if (!empty($GLOBALS['sc_wabot_inbound']) || empty(sc_wabot_settings()['chat_replies'])) {
        return; // written from the business phone itself, or replies off
    }
    $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $conversation_id));
    if (!$conv || empty($conv->wa_opt_in)) {
        return;
    }
    $to = sc_wabot_jid_phone($conv->wa_jid) ?: sc_wabot_phone($conv->visitor_phone);
    $number = sc_wabot_pick_number('chat');
    if (!$to || !$number) {
        return;
    }
    $platform = get_option('sc_platform_name', get_bloginfo('name'));
    $body = $message->message_type === 'text'
        ? $message->message
        : trim(($message->message ? $message->message . "\n" : '') . ($message->file_url ?: ''));
    sc_wabot_enqueue($number, $to, $platform . ":\n" . $body, 'chat_reply', (int) $conversation_id, 1440);
}
add_action('sc_chat_message_saved', 'sc_wabot_on_chat_message', 10, 2);

/** No email when WhatsApp already covers it: staff alerts, or the reply of a visitor who chose WhatsApp. */
add_filter('sc_chat_email_notification', function ($send, $conversation, $sender_type) {
    if (!sc_wabot_pick_number('chat')) {
        return $send;
    }
    if ($sender_type === 'visitor') {
        return sc_wabot_recipients() ? false : $send;
    }
    if (!empty(sc_wabot_settings()['chat_replies']) && !empty($conversation->wa_opt_in) && (sc_wabot_phone($conversation->visitor_phone) || sc_wabot_jid_phone($conversation->wa_jid))) {
        return false;
    }
    return $send;
}, 10, 3);

/* ==========================================================================
   Webhook
   ========================================================================== */

add_action('rest_api_init', function () {
    register_rest_route('sc/v1', '/wabot/(?P<key>[a-z0-9]{8,40})', array(
        'methods'             => 'POST',
        'callback'            => 'sc_wabot_webhook',
        // Authenticated by the wa-bot signature inside the callback.
        'permission_callback' => '__return_true',
    ));
});

function sc_wabot_webhook(WP_REST_Request $request) {
    $key = (string) $request['key'];
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    $secret = $secrets['numbers'][$key]['webhook_secret'] ?? '';
    if (!isset($settings['numbers'][$key]) || $secret === '') {
        return new WP_REST_Response(array('ok' => false), 404);
    }

    $raw = $request->get_body();
    $timestamp = (string) $request->get_header('x_wabot_timestamp');
    $signature = strtolower((string) $request->get_header('x_wabot_signature'));
    $delivery = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->get_header('x_wabot_delivery')), 0, 80);
    if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
        || !hash_equals(hash_hmac('sha256', $timestamp . "\n" . $raw, $secret), $signature)) {
        return new WP_REST_Response(array('ok' => false, 'error' => 'bad signature'), 401);
    }

    $payload = json_decode($raw, true);
    $event = is_array($payload) ? (string) ($payload['event'] ?? '') : '';
    if ($event === '') {
        return new WP_REST_Response(array('ok' => false), 400);
    }

    global $wpdb;
    $inbound = $wpdb->prefix . 'sc_wa_inbound';
    // Retries carry the same delivery id: handle each once.
    $logged = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $inbound (number_key, delivery_id, event, created_at) VALUES (%s, %s, %s, %s)",
        $key, $delivery !== '' ? $delivery : wp_generate_uuid4(), $event, current_time('mysql')
    ));
    if (!$logged) {
        return new WP_REST_Response(array('ok' => true, 'duplicate' => true), 200);
    }
    $log_id = (int) $wpdb->insert_id;

    switch ($event) {
        case 'message.received':
        case 'message.sent':
            sc_wabot_handle_message($key, $event, (array) ($payload['message'] ?? $payload), $log_id);
            break;
        case 'message.receipt':
            $r = (array) ($payload['receipt'] ?? array());
            if (!empty($r['messageId']) && !empty($r['status'])) {
                $wpdb->update($wpdb->prefix . 'sc_wa_outbox', array('delivery' => sanitize_key($r['status'])), array('wa_message_id' => sanitize_text_field($r['messageId'])));
            }
            $wpdb->update($inbound, array('outcome' => 'receipt'), array('id' => $log_id));
            break;
        case 'device.connected':
        case 'device.disconnected':
        case 'device.banned':
        case 'device.logged_out':
        case 'device.reconnect_gave_up':
        case 'device.at_risk':
        case 'device.deleted':
            $map = array('device.connected' => 'connected', 'device.disconnected' => 'disconnected', 'device.banned' => 'banned',
                'device.logged_out' => 'logged_out', 'device.reconnect_gave_up' => 'disconnected', 'device.at_risk' => 'at_risk', 'device.deleted' => 'deleted');
            $fields = array(
                'status'       => $map[$event],
                'status_at'    => current_time('mysql'),
                'status_error' => mb_substr(sanitize_text_field((string) ($payload['reason'] ?? '')), 0, 160),
            );
            if ($event === 'device.connected' && !empty($payload['phone'])) {
                $fields['phone'] = preg_replace('/\D/', '', (string) $payload['phone']);
            }
            sc_wabot_patch_number($key, $fields);
            $wpdb->update($inbound, array('outcome' => 'status'), array('id' => $log_id));
            break;
        default:
            $wpdb->update($inbound, array('outcome' => 'ignored'), array('id' => $log_id));
    }
    return new WP_REST_Response(array('ok' => true), 200);
}

/** A WhatsApp message in or out of the business number: attach it to its chat conversation. */
function sc_wabot_handle_message($key, $event, $m, $log_id) {
    global $wpdb;
    $inbound = $wpdb->prefix . 'sc_wa_inbound';
    $outgoing = $event === 'message.sent';
    $jid = (string) ($outgoing ? ($m['to'] ?? '') : ($m['from'] ?? ''));
    $type = sanitize_key((string) ($m['type'] ?? 'text'));
    $text = isset($m['text']) ? sanitize_textarea_field((string) $m['text']) : '';
    $log = array('from_jid' => mb_substr(sanitize_text_field($jid), 0, 120), 'push_name' => mb_substr(sanitize_text_field((string) ($m['pushName'] ?? '')), 0, 190), 'type' => $type, 'text' => mb_substr($text, 0, 2000));

    if (!empty($m['isGroup']) || strpos($jid, '@g.us') !== false) {
        $wpdb->update($inbound, $log + array('outcome' => 'group'), array('id' => $log_id));
        return;
    }
    // Only messages typed on the business phone itself are staff replies; our own API sends are echoes.
    if ($outgoing && empty($m['fromSync'])) {
        $wpdb->update($inbound, $log + array('outcome' => 'echo'), array('id' => $log_id));
        return;
    }
    if (!$outgoing && !empty($m['fromMe'])) {
        $wpdb->update($inbound, $log + array('outcome' => 'echo'), array('id' => $log_id));
        return;
    }

    $phone = sc_wabot_jid_phone($jid);
    if (!$outgoing && sc_wabot_is_stop_word($text)) {
        sc_wabot_opt_out($phone ?: $jid, true);
        $wpdb->update($inbound, $log + array('outcome' => 'opt_out'), array('id' => $log_id));
        return;
    }
    if (!$outgoing && $phone && in_array($phone, array_column(sc_wabot_recipients(), 'phone'), true)) {
        $wpdb->update($inbound, $log + array('outcome' => 'staff'), array('id' => $log_id));
        return;
    }

    $conv = sc_wabot_find_conversation($jid, $phone);
    if (!$conv) {
        $wpdb->update($inbound, $log + array('outcome' => 'unmatched'), array('id' => $log_id));
        return;
    }
    if ($text === '') {
        $labels = array('image' => 'a photo', 'video' => 'a video', 'audio' => 'an audio clip', 'voice' => 'a voice note', 'document' => 'a file', 'sticker' => 'a sticker', 'location' => 'a location', 'contact' => 'a contact');
        $text = sprintf('[%s — open WhatsApp on the business phone to see it]', sprintf('Sent %s on WhatsApp', $labels[$type] ?? 'a message'));
    }

    $GLOBALS['sc_wabot_inbound'] = true;
    if (!$outgoing) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'sc_conversations', array('wa_opt_in' => 1, 'wa_jid' => $log['from_jid'], 'status' => 'active'), array('id' => $conv->id));
    }
    $saved = function_exists('sc_chat') ? sc_chat()->send_message((int) $conv->id, $text, $outgoing ? 'organizer' : 'visitor') : false;
    $GLOBALS['sc_wabot_inbound'] = false;
    $wpdb->update($inbound, $log + array('outcome' => $saved ? ($outgoing ? 'staff_reply' : 'matched') : 'error', 'conversation_id' => (int) $conv->id), array('id' => $log_id));
}

function sc_wabot_find_conversation($jid, $phone) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_conversations';
    if ($jid !== '') {
        $conv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE wa_jid = %s AND status <> 'archived' ORDER BY COALESCE(last_message_at, created_at) DESC LIMIT 1", $jid));
        if ($conv) {
            return $conv;
        }
    }
    if ($phone === '') {
        return null;
    }
    // Stored phones are free text: compare on the normalised form, newest conversations first.
    $since = date('Y-m-d H:i:s', current_time('timestamp') - 60 * DAY_IN_SECONDS);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE status <> 'archived' AND visitor_phone <> '' AND COALESCE(last_message_at, created_at) >= %s
         AND visitor_phone LIKE %s ORDER BY COALESCE(last_message_at, created_at) DESC LIMIT 20",
        $since, '%' . $wpdb->esc_like(substr($phone, -9)) . '%'
    ));
    foreach ($rows as $row) {
        if (sc_wabot_phone($row->visitor_phone) === $phone) {
            return $row;
        }
    }
    return null;
}

/* ==========================================================================
   Opt-out
   ========================================================================== */

function sc_wabot_is_stop_word($text) {
    $t = mb_strtolower(trim(preg_replace('/[\s\p{P}]+/u', ' ', (string) $text)));
    return in_array($t, array('stop', 'unsubscribe', 'الغاء', 'إلغاء', 'ايقاف', 'إيقاف', 'الغاء الاشتراك', 'إلغاء الاشتراك', 'stop messages'), true);
}

/** People who asked not to get bulk messages (phone digits or a hidden @lid id). */
function sc_wabot_opt_outs() {
    $list = get_option('sc_wa_opt_outs', array());
    return is_array($list) ? $list : array();
}

function sc_wabot_opt_out($id, $out = true) {
    $id = (string) $id;
    if ($id === '') {
        return;
    }
    $list = sc_wabot_opt_outs();
    if ($out) {
        $list[$id] = current_time('mysql');
    } else {
        unset($list[$id]);
    }
    update_option('sc_wa_opt_outs', $list, false);
}
