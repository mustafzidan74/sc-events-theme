<?php
/**
 * WhatsApp bulk sending page handlers (template-parts/dashboard/whatsapp-send.php).
 * Engine: inc/whatsapp/wabot-campaigns.php. Event managers.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_wabot_campaign_input() {
    $source = sanitize_key(wp_unslash($_POST['source'] ?? 'event'));
    return array(
        'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'source'           => in_array($source, array('event', 'certificates', 'manual'), true) ? $source : 'event',
        'event_id'         => absint($_POST['event_id'] ?? 0),
        'audience'         => sanitize_text_field(wp_unslash($_POST['audience'] ?? 'all')),
        'manual'           => sanitize_textarea_field(wp_unslash($_POST['manual'] ?? '')),
        'message'          => sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')),
        'interval_seconds' => absint($_POST['interval_seconds'] ?? 45),
        'attach'           => empty($_POST['attach_qr']) ? '' : ($source === 'event' ? 'ticket_qr' : ($source === 'certificates' ? 'certificate_pdf' : '')),
    );
}

function sc_wabot_campaign_row($c) {
    $counts = sc_wabot_campaign_counts((int) $c->id);
    $done = $counts['sent'] + $counts['failed'] + $counts['skipped'] + $counts['cancelled'] + $counts['expired'];
    $left = $counts['queued'] + $counts['pending'] + $counts['sending'];
    return array(
        'id'        => (int) $c->id,
        'title'     => $c->title,
        'source'    => $c->source,
        'status'    => $c->status,
        'reason'    => (string) $c->paused_reason,
        'interval'  => (int) $c->interval_seconds,
        'total'     => (int) $c->total,
        'counts'    => $counts,
        'done'      => $done,
        'eta_min'   => $c->status === 'running' ? sc_wabot_campaign_minutes($left, $c->interval_seconds) : null,
        'next_at'   => $c->next_send_at,
        'created'   => $c->created_at,
        'finished'  => $c->finished_at,
        'by'        => $c->created_by ? (get_userdata((int) $c->created_by)->display_name ?? '') : '',
    );
}

add_action('wp_ajax_sc_wabot_campaign_preview', function () {
    sc_wabot_verify();
    $in = sc_wabot_campaign_input();
    $audience = sc_wabot_build_audience($in['source'], $in['event_id'], $in['audience'], $in['manual']);
    $samples = array();
    foreach (array_slice($audience['rows'], 0, 3) as $r) {
        $samples[] = array('to' => $r['phone'], 'name' => $r['name'], 'text' => sc_wabot_render_message($in['message'], $r['fields']));
    }
    $interval = max(15, min(600, $in['interval_seconds']));
    wp_send_json_success(array(
        'count'      => count($audience['rows']),
        'invalid'    => $audience['invalid'],
        'duplicates' => $audience['duplicates'],
        'opted_out'  => $audience['opted_out'],
        'samples'    => $samples,
        'minutes'    => sc_wabot_campaign_minutes(count($audience['rows']), $interval),
        'numbers'    => count(sc_wabot_candidates(null, 'campaign')),
        'lanes'      => count(sc_wabot_bulk_numbers()),
        'attach'     => $in['attach'],
    ));
});

add_action('wp_ajax_sc_wabot_campaign_create', function () {
    sc_wabot_verify();
    $in = sc_wabot_campaign_input();
    if ($in['title'] === '') {
        wp_send_json_error(array('message' => __('Give the campaign a name.', 'sc_events'), 'field' => 'title'));
    }
    $id = sc_wabot_create_campaign($in);
    if (is_wp_error($id)) {
        wp_send_json_error(array('message' => $id->get_error_message(), 'field' => $id->get_error_code()));
    }
    sc_wabot_campaign_tick(0);
    wp_send_json_success(array('id' => $id));
});

/** Campaign list with progress. Polling it also moves running campaigns along. */
add_action('wp_ajax_sc_wabot_campaigns', function () {
    sc_wabot_verify();
    global $wpdb;
    if (!empty($_POST['tick'])) {
        sc_wabot_campaign_tick(0);
    }
    $rows = array();
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}sc_wa_campaigns ORDER BY FIELD(status, 'running', 'paused') DESC, id DESC LIMIT 30") as $c) {
        $rows[] = sc_wabot_campaign_row($c);
    }
    wp_send_json_success(array('campaigns' => $rows, 'overall' => sc_wabot_overall_state()['state']));
});

add_action('wp_ajax_sc_wabot_campaign_action', function () {
    sc_wabot_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $id = absint($_POST['id'] ?? 0);
    $op = sanitize_key(wp_unslash($_POST['op'] ?? ''));
    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_wa_campaigns WHERE id = %d", $id));
    if (!$c) {
        wp_send_json_error(array('message' => __('Campaign not found.', 'sc_events')));
    }
    $now = current_time('mysql');
    switch ($op) {
        case 'pause':
            $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'paused', 'paused_reason' => __('Paused by you.', 'sc_events')), array('id' => $id));
            break;
        case 'resume':
            sc_wabot_refresh_all();
            $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'running', 'paused_reason' => null, 'finished_at' => null, 'next_send_at' => null), array('id' => $id));
            sc_wabot_campaign_tick(0);
            break;
        case 'cancel':
            $wpdb->query($wpdb->prepare("UPDATE {$p}sc_wa_outbox SET status = 'cancelled', updated_at = %s WHERE campaign_id = %d AND status IN ('queued', 'pending')", $now, $id));
            $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'cancelled', 'finished_at' => $now, 'next_send_at' => null), array('id' => $id));
            break;
        case 'retry_failed':
            $n = $wpdb->query($wpdb->prepare(
                "UPDATE {$p}sc_wa_outbox SET status = 'queued', attempts = 0, tried_numbers = NULL, error = NULL, next_attempt_at = NULL, updated_at = %s
                 WHERE campaign_id = %d AND status IN ('failed', 'expired', 'cancelled')",
                $now, $id
            ));
            if ($n) {
                $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'running', 'paused_reason' => null, 'finished_at' => null, 'next_send_at' => null), array('id' => $id));
                sc_wabot_campaign_tick(0);
            }
            break;
        default:
            wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
    }
    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_wa_campaigns WHERE id = %d", $id));
    wp_send_json_success(sc_wabot_campaign_row($c));
});

/** Who got it and who did not. */
add_action('wp_ajax_sc_wabot_campaign_log', function () {
    sc_wabot_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $id = absint($_POST['id'] ?? 0);
    $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
    $page = max(1, absint($_POST['page'] ?? 1));
    $per = 50;
    $where = $wpdb->prepare('campaign_id = %d', $id);
    $filters = array(
        'sent'     => "status = 'sent'",
        'read'     => "status = 'sent' AND delivery IN ('read', 'played')",
        'problem'  => "status IN ('failed', 'skipped', 'expired', 'cancelled')",
        'waiting'  => "status IN ('queued', 'pending', 'sending')",
    );
    if (isset($filters[$status])) {
        $where .= ' AND ' . $filters[$status];
    }
    $q = trim(sanitize_text_field(wp_unslash($_POST['q'] ?? '')));
    if ($q !== '') {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $where .= $wpdb->prepare(' AND (recipient_name LIKE %s OR to_phone LIKE %s)', $like, $like);
    }
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}sc_wa_outbox WHERE $where");
    $labels = wp_list_pluck(sc_wabot_settings()['numbers'], 'label');
    $rows = array();
    foreach ($wpdb->get_results($wpdb->prepare("SELECT id, recipient_name, to_phone, status, delivery, used_number, attempts, error, updated_at, body FROM {$p}sc_wa_outbox WHERE $where ORDER BY id LIMIT %d OFFSET %d", $per, ($page - 1) * $per)) as $r) {
        $rows[] = array(
            'id' => (int) $r->id, 'name' => $r->recipient_name, 'to' => $r->to_phone, 'status' => $r->status, 'delivery' => $r->delivery,
            'number' => $r->used_number ? ($labels[$r->used_number] ?? '—') : '', 'attempts' => (int) $r->attempts, 'error' => $r->error,
            'at' => $r->updated_at, 'text' => mb_substr($r->body, 0, 300),
        );
    }
    wp_send_json_success(array('rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per))));
});

/** Send selected people again (one by one, through the campaign's gap). */
add_action('wp_ajax_sc_wabot_campaign_resend', function () {
    sc_wabot_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $id = absint($_POST['id'] ?? 0);
    $rows = array_filter(array_map('absint', (array) json_decode(wp_unslash($_POST['rows'] ?? '[]'), true)));
    if (!$rows) {
        wp_send_json_error(array('message' => __('Choose who to send again.', 'sc_events')));
    }
    $n = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}sc_wa_outbox SET status = 'queued', attempts = 0, tried_numbers = NULL, error = NULL, delivery = NULL, next_attempt_at = NULL, updated_at = %s
         WHERE campaign_id = %d AND status NOT IN ('queued', 'pending', 'sending') AND id IN (" . implode(',', $rows) . ')',
        current_time('mysql'), $id
    ));
    if ($n) {
        $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'running', 'paused_reason' => null, 'finished_at' => null, 'next_send_at' => null), array('id' => $id));
        sc_wabot_campaign_tick(0);
    }
    wp_send_json_success(array('queued' => (int) $n));
});

/** Workshops of an event, for the audience picker. */
add_action('wp_ajax_sc_wabot_event_workshops', function () {
    sc_wabot_verify();
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sc_workshops WHERE event_id = %d AND status <> 'cancelled' ORDER BY start_date, title", absint($_POST['event_id'] ?? 0)));
    wp_send_json_success(array_map(function ($w) { return array('id' => (int) $w->id, 'title' => $w->title); }, $rows));
});
