<?php
/**
 * Automatic messages page handlers (template-parts/dashboard/whatsapp-messages.php).
 * Engine: inc/whatsapp/wabot-notify.php. Event managers.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** What the page shows: settings, type descriptions and the next reminders. */
function sc_notify_page_state() {
    global $wpdb;
    $settings = sc_notify_settings();
    $types = array();
    foreach (sc_notify_types() as $type => $def) {
        $types[$type] = array(
            'label'       => $def['label'],
            'when'        => $def['when'],
            'tags'        => $def['tags'],
            'attach_kind' => $def['attach'] ?? '',
            'bulk'        => !empty($def['bulk']),
            'manual'      => !empty($def['manual']),
            'recipients'  => !empty($def['recipients']),
            'no_email'    => in_array($type, array('support_alert', 'daily_summary'), true),
            'is_default'  => trim($settings['types'][$type]['template']) === trim($def['template']),
        ) + $settings['types'][$type];
    }

    // The next event each reminder will go to.
    $upcoming = array();
    $today = date('Y-m-d', current_time('timestamp'));
    $next = $wpdb->get_row($wpdb->prepare("SELECT id, title, start_date FROM {$wpdb->prefix}sc_events WHERE status = 'publish' AND start_date > %s ORDER BY start_date, id LIMIT 1", $today));
    if ($next) {
        $people = count(sc_wabot_build_audience('event', (int) $next->id, 'everyone')['rows']);
        foreach (array('reminder_day_before' => 1, 'reminder_same_day' => 0) as $type => $days) {
            $day = date('Y-m-d', strtotime($next->start_date) - $days * DAY_IN_SECONDS);
            $flag = get_option('sc_notify_' . $type . '_' . (int) $next->id . '_' . $next->start_date);
            $upcoming[$type] = array(
                'event'   => $next->title,
                'starts'  => date_i18n('l j F Y', strtotime($day)) . ' · ' . date_i18n('g A', strtotime('2000-01-01 ' . sprintf('%02d:00', (int) $settings['types'][$type]['hour']))),
                'people'  => $people,
                'minutes' => (int) ceil($people * $settings['interval'] / 60),
                'created' => $flag && is_numeric($flag) ? (int) $flag : 0,
            );
        }
    }

    return array(
        'email'     => (int) $settings['email'],
        'interval'  => (int) $settings['interval'],
        'types'     => $types,
        'tags'      => sc_notify_tag_labels(),
        'upcoming'  => $upcoming,
        'numbers'   => count(sc_wabot_candidates(null, 'ticket')),
        'alert_people' => count(sc_wabot_recipients()),
        'bulk'      => count(sc_wabot_candidates(null, 'campaign')),
        'send_url'  => home_url('/event-manager-dashboard/whatsapp-send'),
        'wa_url'    => home_url('/event-manager-dashboard/whatsapp'),
    );
}

/** Real data to fill a type's template for a preview or a test. */
function sc_notify_sample($type) {
    global $wpdb;
    $p = $wpdb->prefix;
    if ($type === 'certificate_ready') {
        $c = $wpdb->get_row("SELECT c.*, a.phone FROM {$p}sc_certificates c LEFT JOIN {$p}sc_attendees a ON a.id = c.attendee_id WHERE c.status <> 'revoked' ORDER BY c.id DESC LIMIT 1");
        if (!$c) {
            return null;
        }
        return array(
            'who'    => $c->attendee_name,
            'fields' => array(
                '{name}' => $c->attendee_name, '{first_name}' => sc_notify_first_name($c->attendee_name), '{event}' => $c->event_title,
                '{certificate_number}' => $c->certificate_number,
                '{certificate_link}' => add_query_arg(array('action' => 'sc_download_certificate', 'id' => (int) $c->id, 'token' => wp_hash($c->verification_code . $c->certificate_number)), admin_url('admin-ajax.php')),
                '{verify_link}' => class_exists('SC_Certificate') ? SC_Certificate::get_verification_url($c->verification_code) : '',
            ),
            'media'  => 'certificate_pdf:' . (int) $c->id,
            'file'   => 'certificate-' . $c->certificate_number . '.pdf',
        );
    }
    if ($type === 'support_received' || $type === 'support_alert') {
        $m = $wpdb->get_row("SELECT * FROM {$p}sc_support_messages ORDER BY id DESC LIMIT 1");
        if (!$m) {
            return null;
        }
        $phone = sc_wabot_phone($m->phone);
        return array('who' => $m->name, 'media' => '', 'fields' => array(
            '{name}' => $m->name, '{first_name}' => sc_notify_first_name($m->name), '{subject}' => $m->subject,
            '{phone}' => $phone !== '' ? '+' . $phone : '', '{email}' => $m->email, '{message}' => mb_substr(trim($m->message), 0, 500),
            '{link}' => home_url('/event-manager-dashboard/support?message=' . (int) $m->id),
        ));
    }
    if ($type === 'daily_summary') {
        $now = current_time('timestamp');
        return array('who' => __('today', 'sc_events'), 'media' => '', 'fields' => array(
            '{summary}' => sc_notify_summary_text(date('Y-m-d', $now)), '{date}' => date_i18n('l j F', $now),
        ));
    }
    if ($type === 'exhibitor_badge') {
        $id = (int) $wpdb->get_var("SELECT id FROM {$p}sc_company_attendees WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        if (!$id) {
            return null;
        }
        $c = $wpdb->get_row($wpdb->prepare("SELECT c.*, e.title AS event_title FROM {$p}sc_company_attendees c LEFT JOIN {$p}sc_events e ON e.id = c.event_id WHERE c.id = %d", $id));
        return array(
            'who'    => $c->company_name,
            'fields' => array('{name}' => $c->contact_name ?: $c->company_name, '{company}' => $c->company_name, '{event}' => (string) $c->event_title,
                '{booth}' => (string) $c->booth_number, '{badge_link}' => home_url('/company-ticket/' . rawurlencode($c->company_code) . '/')),
            'media'  => 'company_qr:' . $id,
        );
    }
    // Someone registered for the next event, else the latest registration.
    $today = date('Y-m-d', current_time('timestamp'));
    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT a.id FROM {$p}sc_attendees a JOIN {$p}sc_events e ON e.id = a.event_id
         WHERE a.status = 'active' AND a.payment_status = 'success' AND e.start_date >= %s ORDER BY e.start_date, a.id DESC LIMIT 1",
        $today
    ));
    if (!$id) {
        $id = (int) $wpdb->get_var("SELECT id FROM {$p}sc_attendees WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    }
    $a = $id ? SC_Attendee::get($id) : null;
    if (!$a) {
        return null;
    }
    return array('who' => $a->name, 'fields' => sc_notify_attendee_fields($a), 'media' => 'ticket_qr:' . $id);
}

function sc_notify_type_input() {
    $type = sanitize_key(wp_unslash($_POST['type'] ?? ''));
    if (!isset(sc_notify_types()[$type])) {
        wp_send_json_error(array('message' => __('Unknown message.', 'sc_events')));
    }
    return $type;
}

add_action('wp_ajax_sc_notify_state', function () {
    sc_wabot_verify();
    wp_send_json_success(sc_notify_page_state());
});

add_action('wp_ajax_sc_notify_save', function () {
    sc_wabot_verify();
    $settings = sc_notify_settings();
    $scope = sanitize_key(wp_unslash($_POST['scope'] ?? 'type'));

    if ($scope === 'delivery') {
        $settings['email'] = !empty($_POST['email']) && $_POST['email'] !== '0' ? 1 : 0;
        $settings['interval'] = max(30, min(120, absint($_POST['interval'] ?? 60)));
    } else {
        $type = sc_notify_type_input();
        $def = sc_notify_types()[$type];
        $t = &$settings['types'][$type];
        if (isset($_POST['enabled'])) {
            $t['enabled'] = !empty($_POST['enabled']) && $_POST['enabled'] !== '0' ? 1 : 0;
        }
        if (isset($_POST['attach'])) {
            $t['attach'] = !empty($_POST['attach']) && $_POST['attach'] !== '0' ? 1 : 0;
        }
        if (isset($_POST['template'])) {
            $template = trim(sanitize_textarea_field(wp_unslash($_POST['template'])));
            if ($template === '') {
                wp_send_json_error(array('message' => __('The message can’t be empty. Use “Restore default” to start again.', 'sc_events'), 'field' => 'template'));
            }
            if (mb_strlen($template) > 1000) {
                wp_send_json_error(array('message' => __('Keep the message under 1,000 characters.', 'sc_events'), 'field' => 'template'));
            }
            $t['template'] = $template;
        }
        if (isset($_POST['subject'])) {
            $subject = trim(sanitize_text_field(wp_unslash($_POST['subject'])));
            $t['subject'] = $subject !== '' ? mb_substr($subject, 0, 190) : $def['subject'];
        }
        if (isset($_POST['hour']) && isset($def['hour'])) {
            $t['hour'] = max(0, min(23, absint($_POST['hour'])));
        }
        if (isset($_POST['to']) && !empty($def['recipients'])) {
            $numbers = array();
            foreach (preg_split('/[\r\n,;]+/', sanitize_textarea_field(wp_unslash($_POST['to']))) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $phone = sc_wabot_phone($line);
                if ($phone === '') {
                    wp_send_json_error(array('message' => sprintf(__('“%s” is not a valid WhatsApp number.', 'sc_events'), trim($line)), 'field' => 'to'));
                }
                $numbers[$phone] = '+' . $phone;
            }
            $t['to'] = implode("\n", $numbers);
        }
        if (!empty($_POST['restore'])) {
            $t['template'] = $def['template'];
            $t['subject'] = $def['subject'];
        }
        unset($t);
    }
    sc_notify_save_settings($settings);
    wp_send_json_success(sc_notify_page_state());
});

add_action('wp_ajax_sc_notify_preview', function () {
    sc_wabot_verify();
    $type = sc_notify_type_input();
    $template = isset($_POST['template']) ? sanitize_textarea_field(wp_unslash($_POST['template'])) : sc_notify_settings()['types'][$type]['template'];
    $sample = sc_notify_sample($type);
    if (!$sample) {
        wp_send_json_error(array('message' => __('There is nobody registered yet to preview with.', 'sc_events')));
    }
    $fields = $sample['fields'] + array('{site}' => wp_specialchars_decode(get_option('sc_platform_name', get_bloginfo('name')), ENT_QUOTES));
    $out = array('who' => $sample['who'], 'text' => sc_wabot_render_message($template, $fields), 'image' => '', 'file' => '');
    if (!empty($sample['file'])) {
        // A PDF is not built for every keystroke: the preview shows its name.
        if (!empty($_POST['attach']) && $_POST['attach'] !== '0') {
            $out['file'] = $sample['file'];
        }
    } elseif (!empty($_POST['attach']) && $_POST['attach'] !== '0' && $sample['media'] !== '') {
        $file = sc_wabot_media($sample['media']);
        if (!is_wp_error($file)) {
            $out['image'] = 'data:' . $file['mime'] . ';base64,' . base64_encode($file['bytes']);
        }
    }
    wp_send_json_success($out);
});

/** Send the current message, filled with a real registration, to a phone typed on the page. */
add_action('wp_ajax_sc_notify_test', function () {
    sc_wabot_verify();
    $type = sc_notify_type_input();
    $phone = sc_wabot_phone(sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
    if ($phone === '') {
        wp_send_json_error(array('message' => __('Enter a valid WhatsApp number.', 'sc_events')));
    }
    if (get_transient('sc_notify_test_' . get_current_user_id())) {
        wp_send_json_error(array('message' => __('Wait a few seconds between tests.', 'sc_events')));
    }
    $cfg = sc_notify_settings()['types'][$type];
    $template = isset($_POST['template']) ? sanitize_textarea_field(wp_unslash($_POST['template'])) : $cfg['template'];
    $attach = isset($_POST['attach']) ? (!empty($_POST['attach']) && $_POST['attach'] !== '0') : !empty($cfg['attach']);
    $sample = sc_notify_sample($type);
    if (!$sample) {
        wp_send_json_error(array('message' => __('There is nobody registered yet to fill the message with.', 'sc_events')));
    }
    $number = sc_wabot_candidates(null, $type)[0] ?? null;
    if (!$number) {
        wp_send_json_error(array('message' => __('No WhatsApp number is set up for these messages.', 'sc_events')));
    }
    $fields = $sample['fields'] + array('{site}' => wp_specialchars_decode(get_option('sc_platform_name', get_bloginfo('name')), ENT_QUOTES));
    set_transient('sc_notify_test_' . get_current_user_id(), 1, 10);
    $id = sc_wabot_enqueue($number, $phone, sc_wabot_render_message($template, $fields), 'test', null, 30, $attach ? (string) $sample['media'] : '');
    if (!$id) {
        wp_send_json_error(array('message' => __('Could not queue the test.', 'sc_events')));
    }
    wp_send_json_success(array('message' => sprintf(__('Test on its way to +%s, filled with %s’s details.', 'sc_events'), $phone, $sample['who'])));
});
