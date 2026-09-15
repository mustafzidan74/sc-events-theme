<?php
/**
 * WhatsApp page handlers (template-parts/dashboard/whatsapp.php).
 *
 * Connection, numbers and tokens: administrators only; tokens are write-only.
 * Alert list, alert mode and the log: event managers.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_wabot_verify($admin = false) {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('This page has expired. Refresh it and try again.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
    if ($admin && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Only site administrators can change WhatsApp numbers and tokens.', 'sc_events')), 403);
    }
}

/** What the page may see: settings without any secret, plus which secrets exist. */
function sc_wabot_public_state() {
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    $numbers = array();
    foreach ($settings['numbers'] as $key => $n) {
        $numbers[] = array(
            'key'          => $key,
            'label'        => (string) ($n['label'] ?? ''),
            'phone'        => (string) ($n['phone'] ?? ''),
            'enabled'      => !empty($n['enabled']),
            'chat'         => !empty($n['chat']),
            'status'       => (string) ($n['status'] ?? 'unknown'),
            'status_error' => (string) ($n['status_error'] ?? ''),
            'status_at'    => (string) ($n['status_at'] ?? ''),
            'linked'       => !empty($n['linked']),
            'has_token'    => !empty($secrets['numbers'][$key]['token']),
            'has_secret'   => !empty($secrets['numbers'][$key]['webhook_secret']),
            'can_qr'       => !empty($secrets['numbers'][$key]['embed_url']),
            'webhook_url'  => sc_wabot_webhook_url($key),
        );
    }
    return array(
        'base_url'         => (string) $settings['base_url'],
        'has_admin_key'    => !empty($secrets['admin_key']),
        'numbers'          => $numbers,
        'recipients'       => array_values($settings['recipients']),
        'alert_mode'       => $settings['alert_mode'],
        'escalate_minutes' => (int) $settings['escalate_minutes'],
        'chat_replies'     => !empty($settings['chat_replies']),
        'overall'          => sc_wabot_overall_state()['state'],
    );
}

function sc_wabot_secret_input($name) {
    return isset($_POST[$name]) ? trim(sanitize_text_field(wp_unslash($_POST[$name]))) : '';
}

add_action('wp_ajax_sc_wabot_state', function () {
    sc_wabot_verify();
    if (!empty($_POST['refresh'])) {
        sc_wabot_refresh_all();
    }
    wp_send_json_success(sc_wabot_public_state());
});

add_action('wp_ajax_sc_wabot_save_connection', function () {
    sc_wabot_verify(true);
    $url = esc_url_raw(trim(wp_unslash($_POST['base_url'] ?? '')));
    if (!preg_match('#^https://#i', $url) && !apply_filters('sc_wabot_allow_internal_url', false)) {
        wp_send_json_error(array('message' => __('Enter the wa-bot address starting with https://', 'sc_events'), 'field' => 'base_url'));
    }
    $settings = sc_wabot_settings();
    $settings['base_url'] = untrailingslashit($url);
    sc_wabot_save_settings($settings);
    $admin_key = sc_wabot_secret_input('admin_key');
    if ($admin_key !== '') {
        if (!preg_match('/^wabotk_[a-f0-9]{16,}$/i', $admin_key)) {
            wp_send_json_error(array('message' => __('An admin key starts with wabotk_', 'sc_events'), 'field' => 'admin_key'));
        }
        $secrets = sc_wabot_secrets();
        $secrets['admin_key'] = $admin_key;
        sc_wabot_save_secrets($secrets);
    }
    if (!empty($_POST['clear_admin_key'])) {
        $secrets = sc_wabot_secrets();
        unset($secrets['admin_key']);
        sc_wabot_save_secrets($secrets);
    }
    wp_send_json_success(sc_wabot_public_state());
});

/** Add a number whose token was created in the wa-bot dashboard. */
add_action('wp_ajax_sc_wabot_add_number', function () {
    sc_wabot_verify(true);
    $label = sanitize_text_field(wp_unslash($_POST['label'] ?? ''));
    $token = sc_wabot_secret_input('token');
    $webhook_secret = sc_wabot_secret_input('webhook_secret');
    if ($label === '') {
        wp_send_json_error(array('message' => __('Give the number a name.', 'sc_events'), 'field' => 'label'));
    }
    if (!preg_match('/^wabot_[a-f0-9]{16,}$/i', $token)) {
        wp_send_json_error(array('message' => __('A device token starts with wabot_', 'sc_events'), 'field' => 'token'));
    }
    $key = strtolower(wp_generate_password(16, false, false));
    $settings = sc_wabot_settings();
    $settings['numbers'][$key] = array('label' => $label, 'enabled' => 1, 'chat' => empty($settings['numbers']) ? 1 : (int) !empty($_POST['chat']), 'status' => 'unknown', 'linked' => 0);
    sc_wabot_save_settings($settings);
    $secrets = sc_wabot_secrets();
    $secrets['numbers'][$key] = array('token' => $token, 'webhook_secret' => $webhook_secret);
    sc_wabot_save_secrets($secrets);
    $status = sc_wabot_refresh_status($key);
    wp_send_json_success(array('state' => sc_wabot_public_state(), 'key' => $key, 'status' => $status));
});

/** Create a new wa-bot device with the admin key; the page then shows its QR to scan. */
add_action('wp_ajax_sc_wabot_link_number', function () {
    sc_wabot_verify(true);
    $label = sanitize_text_field(wp_unslash($_POST['label'] ?? ''));
    if ($label === '') {
        wp_send_json_error(array('message' => __('Give the number a name.', 'sc_events'), 'field' => 'label'));
    }
    if (empty(sc_wabot_secrets()['admin_key'])) {
        wp_send_json_error(array('message' => __('Save the wa-bot admin key first.', 'sc_events')));
    }
    $key = strtolower(wp_generate_password(16, false, false));
    $result = sc_wabot_request(null, 'POST', '/api/admin/devices', array(
        'name'       => mb_substr(get_option('sc_platform_name', 'Wisdom') . ' — ' . $label, 0, 100),
        'notes'      => 'Created from ' . home_url('/'),
        'webhookUrl' => sc_wabot_webhook_url($key),
    ), 20);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => sprintf(__('Could not reach wa-bot: %s', 'sc_events'), $result->get_error_message())));
    }
    if ($result['code'] !== 201 || empty($result['data']['token'])) {
        wp_send_json_error(array('message' => sprintf(__('wa-bot refused: %s', 'sc_events'), $result['data']['error'] ?? ('HTTP ' . $result['code']))));
    }
    $d = $result['data'];
    $settings = sc_wabot_settings();
    $settings['numbers'][$key] = array(
        'label' => $label, 'enabled' => 1, 'chat' => empty($settings['numbers']) ? 1 : 0, 'linked' => 1,
        'device_id' => sanitize_text_field($d['device']['id'] ?? ''), 'status' => sanitize_key($d['device']['status'] ?? 'qr'), 'status_at' => current_time('mysql'),
    );
    sc_wabot_save_settings($settings);
    $secrets = sc_wabot_secrets();
    $secrets['numbers'][$key] = array(
        'token'          => sanitize_text_field($d['token']),
        'webhook_secret' => sanitize_text_field($d['webhookSecret'] ?? ''),
        'embed_url'      => esc_url_raw($d['device']['embedUrl'] ?? ''),
    );
    sc_wabot_save_secrets($secrets);
    wp_send_json_success(array('state' => sc_wabot_public_state(), 'key' => $key));
});

/** The QR page for a number created from here (administrators only; the URL carries wa-bot's embed key). */
add_action('wp_ajax_sc_wabot_qr', function () {
    sc_wabot_verify(true);
    $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
    $url = sc_wabot_secrets()['numbers'][$key]['embed_url'] ?? '';
    if (!$url) {
        wp_send_json_error(array('message' => __('This number was added with a token, so scan its QR in the wa-bot dashboard.', 'sc_events')));
    }
    wp_send_json_success(array('url' => $url));
});

add_action('wp_ajax_sc_wabot_update_number', function () {
    sc_wabot_verify(true);
    $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
    $settings = sc_wabot_settings();
    if (!isset($settings['numbers'][$key])) {
        wp_send_json_error(array('message' => __('Number not found.', 'sc_events')));
    }
    $n = &$settings['numbers'][$key];
    if (isset($_POST['label'])) {
        $label = sanitize_text_field(wp_unslash($_POST['label']));
        if ($label === '') {
            wp_send_json_error(array('message' => __('Give the number a name.', 'sc_events'), 'field' => 'label'));
        }
        $n['label'] = $label;
    }
    foreach (array('enabled', 'chat') as $flag) {
        if (isset($_POST[$flag])) {
            $n[$flag] = (!empty($_POST[$flag]) && $_POST[$flag] !== '0') ? 1 : 0;
        }
    }
    unset($n);
    $secrets = sc_wabot_secrets();
    $token = sc_wabot_secret_input('token');
    if ($token !== '') {
        if (!preg_match('/^wabot_[a-f0-9]{16,}$/i', $token)) {
            wp_send_json_error(array('message' => __('A device token starts with wabot_', 'sc_events'), 'field' => 'token'));
        }
        $secrets['numbers'][$key]['token'] = $token;
    }
    $webhook_secret = sc_wabot_secret_input('webhook_secret');
    if ($webhook_secret !== '') {
        $secrets['numbers'][$key]['webhook_secret'] = $webhook_secret;
    }
    sc_wabot_save_settings($settings);
    sc_wabot_save_secrets($secrets);
    if ($token !== '') {
        sc_wabot_refresh_status($key);
    }
    wp_send_json_success(sc_wabot_public_state());
});

/** Forget a number here. The device stays in wa-bot. */
add_action('wp_ajax_sc_wabot_remove_number', function () {
    sc_wabot_verify(true);
    $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
    $settings = sc_wabot_settings();
    $secrets = sc_wabot_secrets();
    unset($settings['numbers'][$key], $secrets['numbers'][$key]);
    sc_wabot_save_settings($settings);
    sc_wabot_save_secrets($secrets);
    wp_send_json_success(sc_wabot_public_state());
});

add_action('wp_ajax_sc_wabot_save_alerts', function () {
    sc_wabot_verify();
    $rows = isset($_POST['recipients']) ? json_decode(wp_unslash($_POST['recipients']), true) : array();
    $recipients = array();
    $errors = array();
    foreach ((array) $rows as $i => $r) {
        $name = sanitize_text_field((string) ($r['name'] ?? ''));
        $phone_raw = sanitize_text_field((string) ($r['phone'] ?? ''));
        if ($name === '' && $phone_raw === '') {
            continue;
        }
        $phone = sc_wabot_phone($phone_raw);
        if ($phone === '') {
            $errors[] = $i;
            continue;
        }
        $recipients[] = array('name' => $name, 'phone' => $phone, 'enabled' => (int) !empty($r['enabled']));
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Some phone numbers are not valid. Use the full number, for example 01012345678 or +201012345678.', 'sc_events'), 'rows' => $errors));
    }
    $settings = sc_wabot_settings();
    $settings['recipients'] = array_slice($recipients, 0, 20);
    $settings['alert_mode'] = ($_POST['alert_mode'] ?? '') === 'all' ? 'all' : 'escalate';
    $settings['escalate_minutes'] = max(2, min(120, absint($_POST['escalate_minutes'] ?? 10)));
    $settings['chat_replies'] = (int) !empty($_POST['chat_replies']);
    sc_wabot_save_settings($settings);
    wp_send_json_success(sc_wabot_public_state());
});

/** Send a test message now (not queued), so the result shows on the page. */
add_action('wp_ajax_sc_wabot_test', function () {
    sc_wabot_verify(true);
    $key = sanitize_key(wp_unslash($_POST['key'] ?? ''));
    $phone = sc_wabot_phone(sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
    if (!isset(sc_wabot_settings()['numbers'][$key])) {
        wp_send_json_error(array('message' => __('Number not found.', 'sc_events')));
    }
    if ($phone === '') {
        wp_send_json_error(array('message' => __('Enter the phone number that should receive the test.', 'sc_events'), 'field' => 'phone'));
    }
    $text = sprintf("✅ %s\n%s", get_option('sc_platform_name', 'Wisdom'), 'رسالة تجربة من لوحة التحكم — الواتساب متوصل.');
    $result = sc_wabot_request($key, 'POST', '/api/send-text', array('to' => $phone, 'text' => $text), 20);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => sprintf(__('Could not reach wa-bot: %s', 'sc_events'), $result->get_error_message())));
    }
    if (!empty($result['data']['ok'])) {
        wp_send_json_success(array('message' => __('Sent. Check WhatsApp on that phone.', 'sc_events')));
    }
    if (!empty($result['data']['skipped'])) {
        wp_send_json_error(array('message' => __('That number is not on WhatsApp.', 'sc_events')));
    }
    $error = (string) ($result['data']['error'] ?? ('HTTP ' . $result['code']));
    wp_send_json_error(array('message' => $result['code'] === 401 ? __('wa-bot rejected the token of this number.', 'sc_events') : sprintf(__('Not sent: %s', 'sc_events'), $error)));
});

add_action('wp_ajax_sc_wabot_log', function () {
    sc_wabot_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $labels = wp_list_pluck(sc_wabot_settings()['numbers'], 'label');
    $out = array();
    foreach ($wpdb->get_results("SELECT id, number_key, to_phone, body, context, context_id, status, attempts, delivery, error, created_at, updated_at FROM {$p}sc_wa_outbox ORDER BY id DESC LIMIT 60") as $r) {
        $out[] = array(
            'id' => (int) $r->id, 'number' => $labels[$r->number_key] ?? '—', 'to' => $r->to_phone,
            'text' => mb_substr($r->body, 0, 160), 'context' => $r->context, 'conversation' => $r->context_id ? (int) $r->context_id : null,
            'status' => $r->status, 'delivery' => $r->delivery, 'attempts' => (int) $r->attempts, 'error' => $r->error, 'at' => $r->updated_at ?: $r->created_at,
        );
    }
    $in = array();
    foreach ($wpdb->get_results("SELECT number_key, from_jid, push_name, type, text, created_at FROM {$p}sc_wa_inbound WHERE outcome = 'unmatched' ORDER BY id DESC LIMIT 30") as $r) {
        $in[] = array('number' => $labels[$r->number_key] ?? '—', 'from' => sc_wabot_jid_phone($r->from_jid) ?: __('hidden number', 'sc_events'), 'name' => $r->push_name, 'type' => $r->type, 'text' => mb_substr((string) $r->text, 0, 200), 'at' => $r->created_at);
    }
    wp_send_json_success(array('outbox' => $out, 'unmatched' => $in));
});
