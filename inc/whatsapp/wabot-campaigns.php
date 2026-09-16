<?php
/**
 * WhatsApp bulk sending ("campaigns"): one message per person, sent slowly with a gap between
 * messages so the numbers look like people, not a blast. Progress and a per-person log live in
 * {prefix}sc_wa_outbox (campaign_id); each message rolls over to the other connected numbers when
 * one cannot send (sc_wabot_process_one in wabot.php).
 *
 * The worker runs from WP-Cron every minute and, while someone has the campaigns page open, from
 * the page's progress polling. A campaign pauses itself when no number is connected.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Placeholders a message may use. */
function sc_wabot_placeholders() {
    return array(
        '{name}'             => __('Full name', 'sc_events'),
        '{first_name}'       => __('First name', 'sc_events'),
        '{event}'            => __('Event title', 'sc_events'),
        '{date}'             => __('Event date', 'sc_events'),
        '{time}'             => __('Start time', 'sc_events'),
        '{venue}'            => __('Venue', 'sc_events'),
        '{map_link}'         => __('Map link', 'sc_events'),
        '{ticket_code}'      => __('Ticket code', 'sc_events'),
        '{ticket_link}'      => __('Ticket / e-badge link', 'sc_events'),
        '{certificate_link}' => __('Certificate download link', 'sc_events'),
    );
}

/**
 * The people a campaign goes to, with their fields filled.
 *
 * @return array{rows: array, invalid: int, duplicates: int, opted_out: int}
 */
function sc_wabot_build_audience($source, $event_id, $audience, $manual = '') {
    global $wpdb;
    $p = $wpdb->prefix;
    $raw = array();
    $event_title = '';
    $event_fields = array('{date}' => '', '{time}' => '', '{venue}' => '', '{map_link}' => '');
    if ($event_id && class_exists('SC_Event')) {
        $ev = SC_Event::get((int) $event_id);
        $event_title = $ev ? $ev->title : '';
        if ($ev && function_exists('sc_notify_place_fields')) {
            $event_fields = sc_notify_place_fields($ev);
        }
    }

    if ($source === 'event' && $event_id) {
        $where = "a.event_id = %d AND a.status = 'active' AND a.payment_status = 'success'";
        if (strpos($audience, 'workshop:') === 0) {
            $where .= $wpdb->prepare(' AND a.workshop_id = %d', (int) substr($audience, 9));
        } elseif ($audience === 'everyone') {
            // Event and workshop registrations; a person's main event ticket comes first.
        } else {
            $where .= ' AND (a.workshop_id IS NULL OR a.workshop_id = 0)';
            if ($audience === 'checked_in') {
                $where .= ' AND a.checked_in = 1';
            } elseif ($audience === 'not_checked_in') {
                $where .= ' AND a.checked_in = 0';
            }
        }
        foreach ($wpdb->get_results($wpdb->prepare("SELECT a.id, a.name, a.phone, a.ticket_code FROM {$p}sc_attendees a WHERE $where ORDER BY (a.workshop_id IS NULL OR a.workshop_id = 0) DESC, a.id", (int) $event_id)) as $a) {
            $raw[] = array('name' => $a->name, 'phone' => $a->phone, 'context_id' => (int) $a->id, 'ticket_code' => $a->ticket_code,
                'ticket_link' => home_url('/ticket-view/?attendee_id=' . (int) $a->id . '&ticket_code=' . rawurlencode($a->ticket_code)), 'certificate_link' => '');
        }
    } elseif ($source === 'certificates' && $event_id) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.attendee_name, c.certificate_number, c.verification_code, a.phone, a.name, a.id AS attendee_id, a.ticket_code
             FROM {$p}sc_certificates c JOIN {$p}sc_attendees a ON a.id = c.attendee_id
             WHERE c.event_id = %d AND c.status IN ('issued', 'downloaded')" . ($audience === 'not_downloaded' ? " AND c.status = 'issued'" : '') . " ORDER BY c.id",
            (int) $event_id
        ));
        foreach ($rows as $c) {
            $raw[] = array('name' => $c->attendee_name ?: $c->name, 'phone' => $c->phone, 'context_id' => (int) $c->id,
                'certificate_link' => add_query_arg(array('action' => 'sc_download_certificate', 'id' => (int) $c->id, 'token' => wp_hash($c->verification_code . $c->certificate_number)), admin_url('admin-ajax.php')),
                'ticket_link' => home_url('/ticket-view/?attendee_id=' . (int) $c->attendee_id . '&ticket_code=' . rawurlencode($c->ticket_code)));
        }
    } elseif ($source === 'manual') {
        foreach (preg_split('/\r\n|\r|\n/', (string) $manual) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // "Name, phone" or just a phone.
            $parts = array_map('trim', preg_split('/[,\t;]/', $line));
            $phone = array_pop($parts);
            $raw[] = array('name' => implode(' ', $parts), 'phone' => $phone, 'context_id' => null, 'ticket_link' => '', 'certificate_link' => '');
        }
    }

    $opt_outs = sc_wabot_opt_outs();
    $seen = array();
    $out = array();
    $invalid = $dupes = $opted = 0;
    foreach ($raw as $r) {
        $phone = sc_wabot_phone($r['phone']);
        if ($phone === '') {
            $invalid++;
            continue;
        }
        if (isset($seen[$phone])) {
            $dupes++;
            continue;
        }
        $seen[$phone] = true;
        if (isset($opt_outs[$phone])) {
            $opted++;
            continue;
        }
        $name = trim(wp_strip_all_tags((string) $r['name']));
        $out[] = array('phone' => $phone, 'name' => $name, 'context_id' => $r['context_id'], 'fields' => array(
            '{name}' => $name, '{first_name}' => $name !== '' ? preg_split('/\s+/u', $name)[0] : '', '{event}' => $event_title,
            '{ticket_code}' => (string) ($r['ticket_code'] ?? ''), '{ticket_link}' => $r['ticket_link'], '{certificate_link}' => $r['certificate_link'],
        ) + $event_fields);
    }
    return array('rows' => $out, 'invalid' => $invalid, 'duplicates' => $dupes, 'opted_out' => $opted, 'event_title' => $event_title);
}

function sc_wabot_render_message($template, $fields) {
    $lines = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $template) as $line) {
        // A line whose placeholders are all empty and that says nothing without them ("📍 {venue}",
        // "Booth: {booth}") is left out. "Hi {first_name}" stays as "Hi".
        if (preg_match_all('/\{[a-z_]+\}/', $line, $m)) {
            $used = array_intersect($m[0], array_keys($fields));
            $filled = array_filter($used, function ($k) use ($fields) { return trim((string) $fields[$k]) !== ''; });
            if ($used && count($used) === count($m[0]) && !$filled) {
                $rest = trim(preg_replace('/\{[a-z_]+\}/', '', $line));
                if (!preg_match('/[\p{L}\p{N}]/u', $rest) || preg_match('/[:：]$/u', $rest)) {
                    continue;
                }
            }
        }
        // Two spaces left where an empty placeholder was become one.
        $lines[] = preg_replace('/(?<=\S) {2,}(?=\S)/u', ' ', strtr($line, $fields));
    }
    // Collapse blank lines left behind.
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
}

/**
 * Create a campaign and queue one message per person. Returns the campaign id or WP_Error.
 */
function sc_wabot_create_campaign($args) {
    global $wpdb;
    $p = $wpdb->prefix;
    $message = trim((string) $args['message']);
    if ($message === '') {
        return new WP_Error('message', __('Write the message.', 'sc_events'));
    }
    $audience = sc_wabot_build_audience($args['source'], (int) $args['event_id'], (string) $args['audience'], (string) ($args['manual'] ?? ''));
    if (!$audience['rows']) {
        return new WP_Error('audience', __('Nobody to send to: no valid WhatsApp numbers in this list.', 'sc_events'));
    }
    $number = sc_wabot_candidates(null, 'campaign')[0] ?? null;
    if (!$number) {
        return new WP_Error('number', __('Add a WhatsApp number allowed for bulk sending first.', 'sc_events'));
    }
    $interval = max(15, min(600, (int) $args['interval_seconds']));
    // Each person's own file can go with the message: ticket QR for event lists (row = attendee),
    // certificate PDF for certificate holders (row = certificate).
    $attach = '';
    if (($args['attach'] ?? '') === 'ticket_qr' && $args['source'] === 'event') {
        $attach = 'ticket_qr';
    } elseif (($args['attach'] ?? '') === 'certificate_pdf' && $args['source'] === 'certificates') {
        $attach = 'certificate_pdf';
    }
    $now = current_time('mysql');
    $wpdb->insert("{$p}sc_wa_campaigns", array(
        'title'            => mb_substr(sanitize_text_field($args['title']), 0, 190),
        'source'           => $args['source'],
        'event_id'         => (int) $args['event_id'] ?: null,
        'audience'         => mb_substr((string) $args['audience'], 0, 60),
        'message'          => $message,
        'interval_seconds' => $interval,
        'attach'           => $attach,
        'status'           => 'running',
        'total'            => count($audience['rows']),
        'created_by'       => get_current_user_id(),
        'created_at'       => $now,
        'started_at'       => $now,
    ));
    $campaign_id = (int) $wpdb->insert_id;
    if (!$campaign_id) {
        return new WP_Error('db', __('Could not create the campaign.', 'sc_events'));
    }
    // Queue in chunks; nothing is sent until the worker releases a row.
    foreach (array_chunk($audience['rows'], 200) as $chunk) {
        $values = array();
        $placeholders = array();
        foreach ($chunk as $r) {
            $body = sc_wabot_render_message($message, $r['fields']);
            if ($body === '') {
                continue;
            }
            $placeholders[] = '(%s, %s, %s, %s, %d, %s, %d, %s, %s, %s)';
            array_push($values, $number, $r['phone'], $body, 'campaign', $r['context_id'] ?: 0, 'queued', $campaign_id, mb_substr($r['name'], 0, 190),
                $attach && $r['context_id'] ? $attach . ':' . (int) $r['context_id'] : null, $now);
        }
        if (!$placeholders) {
            continue;
        }
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$p}sc_wa_outbox (number_key, to_phone, body, context, context_id, status, campaign_id, recipient_name, media_ref, created_at) VALUES " . implode(',', $placeholders),
            $values
        ));
    }
    return $campaign_id;
}

/** Counts per status for a campaign. */
function sc_wabot_campaign_counts($campaign_id) {
    global $wpdb;
    $counts = array('queued' => 0, 'pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled' => 0, 'expired' => 0);
    foreach ($wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) n FROM {$wpdb->prefix}sc_wa_outbox WHERE campaign_id = %d GROUP BY status", $campaign_id)) as $r) {
        $counts[$r->status] = (int) $r->n;
    }
    $counts['read'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_wa_outbox WHERE campaign_id = %d AND delivery IN ('read', 'played')", $campaign_id));
    $counts['delivered'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_wa_outbox WHERE campaign_id = %d AND delivery IN ('delivered', 'read', 'played')", $campaign_id));
    return $counts;
}

/**
 * Numbers that may send campaign messages right now. Status checks and webhooks change a
 * number's state from other requests, so the stored settings are read fresh.
 */
function sc_wabot_bulk_numbers() {
    wp_cache_delete('sc_wabot_settings', 'options');
    $settings = sc_wabot_settings();
    return array_values(array_filter(sc_wabot_candidates(null, 'campaign'), function ($k) use ($settings) {
        return ($settings['numbers'][$k]['status'] ?? '') === 'connected';
    }));
}

/** Campaign messages one number may send in a day; a wall of messages from one number gets it banned. */
function sc_wabot_bulk_daily_cap() {
    return max(1, (int) apply_filters('sc_wabot_bulk_daily_cap', 1000));
}

/** Minutes a campaign of $people takes when every connected number sends at $gap seconds. */
function sc_wabot_campaign_minutes($people, $gap) {
    $lanes = max(1, count(sc_wabot_bulk_numbers()));
    return (int) ceil($people * max(15, (int) $gap) / $lanes / 60);
}

/** Take a number's next turn; its following turn is one gap (±20%) later. */
function sc_wabot_lane_claim($key, $gap) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_wa_lanes';
    $now = time();
    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (number_key, next_at) VALUES (%s, 0)", $key));
    $next = $now + (int) round(max(15, (int) $gap) * (mt_rand(80, 120) / 100));
    return (bool) $wpdb->query($wpdb->prepare("UPDATE $table SET next_at = %d WHERE number_key = %s AND next_at <= %d", $next, $key, $now));
}

/** Give back a turn that sent nothing. */
function sc_wabot_lane_release($key) {
    global $wpdb;
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sc_wa_lanes SET next_at = %d WHERE number_key = %s", time(), $key));
}

/** Seconds until the first of these numbers may send again. */
function sc_wabot_lane_wait($keys) {
    global $wpdb;
    if (!$keys) {
        return 0;
    }
    $in = implode(',', array_fill(0, count($keys), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT number_key, next_at FROM {$wpdb->prefix}sc_wa_lanes WHERE number_key IN ($in)", $keys), OBJECT_K);
    $wait = PHP_INT_MAX;
    foreach ($keys as $k) {
        $wait = min($wait, isset($rows[$k]) ? (int) $rows[$k]->next_at - time() : 0);
    }
    return max(0, $wait);
}

/**
 * Send campaign messages from every connected number at once, each number at its own pace.
 *
 * Cron runs this once a minute; it keeps going for most of that minute, so a 30-second gap really
 * means two messages a minute per number, and four numbers send four times as fast as one.
 * Turns are claimed in the database, so two overlapping runs never double a number's pace or send
 * the same message twice. Running campaigns take turns, so a reminder is not stuck behind a long list.
 *
 * @param int|null $window Seconds to keep sending. Dashboard requests pass 0: one round, no waiting.
 */
function sc_wabot_campaign_tick($window = null) {
    global $wpdb;
    $p = $wpdb->prefix;
    $campaigns = $wpdb->get_col("SELECT id FROM {$p}sc_wa_campaigns WHERE status = 'running' ORDER BY id LIMIT 20");
    if (!$campaigns) {
        return;
    }
    $now = current_time('mysql');
    $now_ts = current_time('timestamp');
    $numbers = sc_wabot_bulk_numbers();
    $live = array();

    foreach ($campaigns as $cid) {
        // Stuck "sending" rows (a request died mid-send) go back after 10 minutes.
        $wpdb->query($wpdb->prepare("UPDATE {$p}sc_wa_outbox SET status = 'pending', next_attempt_at = %s WHERE campaign_id = %d AND status = 'sending' AND updated_at < %s", $now, $cid, date('Y-m-d H:i:s', $now_ts - 600)));

        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_wa_outbox WHERE campaign_id = %d AND status IN ('queued', 'pending', 'sending')", $cid));
        if ($open === 0) {
            $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'done', 'finished_at' => $now, 'next_send_at' => null), array('id' => $cid));
            continue;
        }
        if (!$numbers) {
            $wpdb->update("{$p}sc_wa_campaigns", array('status' => 'paused', 'paused_reason' => __('No WhatsApp number is connected. Resume after reconnecting.', 'sc_events')), array('id' => $cid));
            continue;
        }
        $live[] = (int) $cid;
    }
    if (!$live) {
        return;
    }

    $stop = time() + max(0, (int) ($window === null ? apply_filters('sc_wabot_campaign_window', 50) : $window));
    $cap = sc_wabot_bulk_daily_cap();
    $day_start = current_time('Y-m-d') . ' 00:00:00';
    $sent_today = array();
    $turn = 0;

    while (true) {
        $numbers = sc_wabot_bulk_numbers();
        $open = 0;
        foreach ($numbers as $key) {
            if (!isset($sent_today[$key])) {
                $sent_today[$key] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$p}sc_wa_outbox WHERE used_number = %s AND campaign_id IS NOT NULL AND status = 'sent' AND updated_at >= %s",
                    $key, $day_start
                ));
            }
            if ($sent_today[$key] >= $cap) {
                continue;
            }
            $open++;

            // The next campaign in turn that has a message ready: a due retry first, then the next person.
            $row = null;
            for ($i = 0; $i < count($live) && !$row; $i++) {
                $cid = $live[($turn + $i) % count($live)];
                $at = current_time('mysql');
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT o.id, c.interval_seconds FROM {$p}sc_wa_outbox o
                     JOIN {$p}sc_wa_campaigns c ON c.id = o.campaign_id AND c.status = 'running'
                     WHERE o.campaign_id = %d AND (o.status = 'queued' OR (o.status = 'pending' AND o.next_attempt_at <= %s))
                     ORDER BY o.status = 'pending' DESC, o.next_attempt_at, o.id LIMIT 1",
                    $cid, $at
                ));
            }
            if (!$row) {
                break 2;
            }
            $turn++;
            if (!sc_wabot_lane_claim($key, $row->interval_seconds)) {
                continue;
            }
            // Reserve the message for this number. The reservation time is in the future, so a run
            // looking at the same moment no longer sees it as ready; a run that dies leaves it for
            // the stuck-row sweep.
            $at = current_time('mysql');
            $taken = $wpdb->query($wpdb->prepare(
                "UPDATE {$p}sc_wa_outbox SET status = 'pending', number_key = %s, next_attempt_at = %s
                 WHERE id = %d AND (status = 'queued' OR (status = 'pending' AND next_attempt_at <= %s))",
                $key, date('Y-m-d H:i:s', current_time('timestamp') + 600), $row->id, $at
            ));
            if (!$taken || !sc_wabot_process_one((int) $row->id, $key)) {
                sc_wabot_lane_release($key);
                continue;
            }
            if ($wpdb->get_var($wpdb->prepare("SELECT status FROM {$p}sc_wa_outbox WHERE id = %d", $row->id)) === 'sent') {
                $sent_today[$key]++;
            }
        }
        if (!$open) {
            break;
        }
        $wait = sc_wabot_lane_wait($numbers);
        if (time() + max(1, $wait) >= $stop) {
            break;
        }
        sleep(max(1, $wait));
    }
}
