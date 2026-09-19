<?php
/**
 * Automatic messages: what the site sends by itself (ticket after registering, reminders, exhibitor
 * badge), each with an on/off switch and a template edited on Messages → Automatic messages
 * (template-parts/dashboard/whatsapp-messages.php).
 *
 * One entry point, sc_notify(): WhatsApp through the outbox (wabot.php), and email too when the
 * "Also send by email" switch is on (site email has been failing, so it starts off).
 * Reminders are slow bulk sends: they become campaigns (wabot-campaigns.php) with a gap.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   Message types and their settings
   ========================================================================== */

function sc_notify_types() {
    $ticket_tags = array('{name}', '{first_name}', '{event}', '{workshop}', '{title}', '{date}', '{time}', '{venue}', '{map_link}', '{ticket_type}', '{ticket_code}', '{ticket_link}', '{site}');
    return array(
        'ticket' => array(
            'label'    => __('Ticket after registering', 'sc_events'),
            'when'     => __('Right after someone registers (free, coupon or paid). “Send the ticket” on an attendee works even when this is off.', 'sc_events'),
            'tags'     => $ticket_tags,
            'attach'   => 'ticket_qr',
            'enabled'  => 1,
            'subject'  => 'تذكرتك: {title}',
            'template' => "أهلاً {first_name} 👋\nتم تأكيد تسجيلك في {title} ✅\n\n📅 {date}\n🕘 {time}\n📍 {venue}\n🎟️ {ticket_type} · كود التذكرة: {ticket_code}\n\nصورة الـ QR دي هي تذكرتك، اعرضها على الباب وقت الدخول.\nالتذكرة كاملة: {ticket_link}\n\n{site}",
        ),
        'reminder_day_before' => array(
            'label'    => __('Reminder the day before', 'sc_events'),
            'when'     => __('The day before an event starts, to everyone registered (event and workshops), sent slowly with a gap like bulk sending.', 'sc_events'),
            'tags'     => array('{name}', '{first_name}', '{event}', '{date}', '{time}', '{venue}', '{map_link}', '{ticket_code}', '{ticket_link}'),
            'attach'   => 'ticket_qr',
            'bulk'     => true,
            'hour'     => 11,
            'enabled'  => 0,
            'subject'  => 'بكرة: {event}',
            'template' => "أهلاً {first_name} 👋\nبنفكّرك إن {event} بكرة إن شاء الله.\n\n📅 {date}\n🕘 {time}\n📍 {venue}\n🗺️ {map_link}\n\nاعرض الـ QR ده على الباب وقت الدخول.\nالتذكرة: {ticket_link}\n\nلو مش عايز رسائل تانية ابعت: إلغاء",
        ),
        'reminder_same_day' => array(
            'label'    => __('Reminder on the morning of the event', 'sc_events'),
            'when'     => __('On the first day of an event, from the hour you set. With a one-minute gap only about 60 people get it per hour, so keep it for small events.', 'sc_events'),
            'tags'     => array('{name}', '{first_name}', '{event}', '{date}', '{time}', '{venue}', '{map_link}', '{ticket_code}', '{ticket_link}'),
            'attach'   => 'ticket_qr',
            'bulk'     => true,
            'hour'     => 7,
            'enabled'  => 0,
            'subject'  => 'النهارده: {event}',
            'template' => "صباح الخير {first_name} ☀️\n{event} النهارده.\n\n🕘 {time}\n📍 {venue}\n🗺️ {map_link}\n\nجهّز الـ QR ده على الباب.\nالتذكرة: {ticket_link}",
        ),
        'certificate_ready' => array(
            'label'    => __('Certificate', 'sc_events'),
            'when'     => __('When someone gets their certificate from My Account. From the dashboard, “Send the certificate” (and the option on the issue page) sends it even when this is off; a batch goes out one by one.', 'sc_events'),
            'tags'     => array('{name}', '{first_name}', '{event}', '{certificate_number}', '{certificate_link}', '{verify_link}', '{site}'),
            'attach'   => 'certificate_pdf',
            'enabled'  => 1,
            'subject'  => 'شهادتك: {event}',
            'template' => "أهلاً {first_name} 🎓\nشهادة حضورك في {event} جاهزة، ومرفقة هنا PDF.\n\nرقم الشهادة: {certificate_number}\nتحميل: {certificate_link}\nأي حد يقدر يتأكد منها هنا: {verify_link}\n\n{site}",
        ),
        'support_received' => array(
            'label'    => __('Contact form: we got your message', 'sc_events'),
            'when'     => __('Right after someone sends the contact form with a phone number.', 'sc_events'),
            'tags'     => array('{name}', '{first_name}', '{subject}', '{site}'),
            'enabled'  => 1,
            'subject'  => 'وصلتنا رسالتك',
            'template' => "أهلاً {first_name}،\nوصلتنا رسالتك بخصوص «{subject}» وهنرد عليك في أقرب وقت.\n\n{site}",
        ),
        'support_alert' => array(
            'label'    => __('Contact form: alert the team', 'sc_events'),
            'when'     => __('Right after a contact form message arrives, to the people listed in Chat alerts on the WhatsApp page.', 'sc_events'),
            'tags'     => array('{name}', '{phone}', '{email}', '{subject}', '{message}', '{link}'),
            'enabled'  => 1,
            'subject'  => 'رسالة جديدة: {subject}',
            'template' => "📩 رسالة جديدة من صفحة التواصل\nمن: {name} · {phone}\n{email}\nالموضوع: {subject}\n\n«{message}»\n\nالرد من هنا: {link}",
        ),
        'daily_summary' => array(
            'label'      => __('Daily summary', 'sc_events'),
            'when'       => __('Once a day at the hour you set: registrations, check-ins, unanswered chats and contact messages, and how WhatsApp sending went.', 'sc_events'),
            'tags'       => array('{summary}', '{date}', '{site}'),
            'hour'       => 21,
            'recipients' => true,
            'enabled'    => 0,
            'subject'    => 'ملخص اليوم',
            'template'   => "📊 ملخص {date} — {site}\n\n{summary}",
        ),
        'password_changed' => array(
            'label'    => __('Password changed', 'sc_events'),
            'when'     => __('Right after a password is changed, from “Forgot password” or My Account, so the owner of the account knows.', 'sc_events'),
            'tags'     => array('{name}', '{first_name}', '{site}'),
            'enabled'  => 1,
            'subject'  => 'تم تغيير كلمة السر',
            'template' => "أهلاً {first_name}،
تم تغيير كلمة السر لحسابك في {site} ✅

لو مش انت اللي غيرتها، كلمنا فوراً.",
        ),
        'exhibitor_badge' => array(
            'label'    => __('Exhibitor badge', 'sc_events'),
            'when'     => __('When you register a company with “Send the badge” ticked, or press “Send the badge” (one company or several from the list). Nothing goes out by itself.', 'sc_events'),
            'tags'     => array('{name}', '{company}', '{event}', '{booth}', '{badge_link}', '{site}'),
            'attach'   => 'company_qr',
            'manual'   => true,
            'enabled'  => 1,
            'subject'  => 'شارة العارض: {event}',
            'template' => "أهلاً {name} 👋\nتم تسجيل {company} في {event} ✅\n🏷️ البوث: {booth}\n\nالـ QR ده هو شارة الدخول، اعرضه على الباب من الموبايل أو مطبوع.\nالشارة كاملة: {badge_link}\n\n{site}",
        ),
    );
}

/** Tag → what it means, for the chips on the page. */
function sc_notify_tag_labels() {
    return array(
        '{name}'        => __('Full name', 'sc_events'),
        '{first_name}'  => __('First name', 'sc_events'),
        '{event}'       => __('Event title', 'sc_events'),
        '{workshop}'    => __('Workshop title (empty for event tickets)', 'sc_events'),
        '{title}'       => __('Workshop title, or the event title', 'sc_events'),
        '{date}'        => __('Date', 'sc_events'),
        '{time}'        => __('Start time', 'sc_events'),
        '{venue}'       => __('Venue', 'sc_events'),
        '{map_link}'    => __('Map link', 'sc_events'),
        '{ticket_type}' => __('Ticket type', 'sc_events'),
        '{ticket_code}' => __('Ticket code', 'sc_events'),
        '{ticket_link}' => __('Ticket / e-badge link', 'sc_events'),
        '{company}'     => __('Company name', 'sc_events'),
        '{booth}'       => __('Booth', 'sc_events'),
        '{badge_link}'  => __('Badge link', 'sc_events'),
        '{site}'        => __('Site name', 'sc_events'),
        '{certificate_number}' => __('Certificate number', 'sc_events'),
        '{certificate_link}'   => __('Certificate download link', 'sc_events'),
        '{verify_link}' => __('Verification page', 'sc_events'),
        '{subject}'     => __('Message subject', 'sc_events'),
        '{message}'     => __('Message text', 'sc_events'),
        '{phone}'       => __('Phone', 'sc_events'),
        '{email}'       => __('Email', 'sc_events'),
        '{link}'        => __('Dashboard link', 'sc_events'),
        '{summary}'     => __('The day’s numbers', 'sc_events'),
    );
}

function sc_notify_settings() {
    $saved = get_option('sc_notify_settings', array());
    $saved = is_array($saved) ? $saved : array();
    $out = array(
        'email'    => !empty($saved['email']) ? 1 : 0,
        'interval' => isset($saved['interval']) ? max(30, min(120, (int) $saved['interval'])) : 60,
        'types'    => array(),
    );
    foreach (sc_notify_types() as $type => $def) {
        $s = isset($saved['types'][$type]) && is_array($saved['types'][$type]) ? $saved['types'][$type] : array();
        $out['types'][$type] = array(
            'enabled'  => isset($s['enabled']) ? (int) !empty($s['enabled']) : (int) $def['enabled'],
            'attach'   => isset($s['attach']) ? (int) !empty($s['attach']) : 1,
            'template' => isset($s['template']) && trim($s['template']) !== '' ? (string) $s['template'] : $def['template'],
            'subject'  => isset($s['subject']) && trim($s['subject']) !== '' ? (string) $s['subject'] : $def['subject'],
            'hour'     => isset($def['hour']) ? (isset($s['hour']) ? max(0, min(23, (int) $s['hour'])) : $def['hour']) : null,
            'to'       => !empty($def['recipients']) ? (string) ($s['to'] ?? '') : null,
        );
    }
    return $out;
}

function sc_notify_save_settings($settings) {
    update_option('sc_notify_settings', $settings, false);
}

/* ==========================================================================
   Fields
   ========================================================================== */

/** "7 October 2026", "7–9 October 2026", "30 September – 2 October 2026". */
function sc_notify_date_range($start, $end = '') {
    $s = $start ? strtotime($start) : false;
    if (!$s) {
        return '';
    }
    $e = $end ? strtotime($end) : false;
    if (!$e || date('Y-m-d', $e) <= date('Y-m-d', $s)) {
        return date_i18n('j F Y', $s);
    }
    if (date('Y-m', $s) === date('Y-m', $e)) {
        return date_i18n('j', $s) . '–' . date_i18n('j F Y', $e);
    }
    if (date('Y', $s) === date('Y', $e)) {
        return date_i18n('j F', $s) . ' – ' . date_i18n('j F Y', $e);
    }
    return date_i18n('j F Y', $s) . ' – ' . date_i18n('j F Y', $e);
}

/** "Name, address", without repeating the name when the address already holds it. */
function sc_notify_venue($name, $address) {
    $name = trim((string) $name);
    $address = trim((string) $address);
    if ($address === '' || ($name !== '' && mb_stripos($address, $name) !== false && mb_strlen($address) <= mb_strlen($name) + 2)) {
        return $name;
    }
    if ($name === '' || mb_stripos($address, $name) !== false) {
        return $address;
    }
    return $name . ', ' . $address;
}

/** Date, time, venue and map for an event or workshop row. */
function sc_notify_place_fields($row, $event = null) {
    $venue = sc_notify_venue($row->venue_name ?? '', $row->venue_address ?? '');
    if ($venue === '' && $event) {
        $venue = sc_notify_venue($event->venue_name, $event->venue_address);
    }
    if (($row->location_type ?? '') === 'online' && !empty($row->meeting_link)) {
        $venue = $venue !== '' ? $venue : 'Online';
    }
    $map = (string) ($row->google_maps_url ?? ($event->google_maps_url ?? ''));
    $time = !empty($row->start_time) ? date_i18n('g:i A', strtotime('2000-01-01 ' . $row->start_time)) : '';
    return array(
        '{date}'     => sc_notify_date_range($row->start_date ?? '', $row->end_date ?? ''),
        '{time}'     => $time,
        '{venue}'    => $venue,
        '{map_link}' => esc_url_raw($map),
    );
}

function sc_notify_first_name($name) {
    $name = trim((string) $name);
    return $name === '' ? '' : preg_split('/\s+/u', $name)[0];
}

/** The best phone we have for an attendee: the registration, then the account. */
function sc_notify_attendee_phone($attendee) {
    foreach (array($attendee->phone ?? '') as $raw) {
        if (sc_wabot_phone($raw) !== '') {
            return sc_wabot_phone($raw);
        }
    }
    if (!empty($attendee->user_id)) {
        foreach (array('phone', 'billing_phone') as $key) {
            $p = sc_wabot_phone(get_user_meta((int) $attendee->user_id, $key, true));
            if ($p !== '') {
                return $p;
            }
        }
    }
    return '';
}

function sc_notify_ticket_link($attendee) {
    return home_url('/ticket-view/?attendee_id=' . (int) $attendee->id . '&ticket_code=' . rawurlencode($attendee->ticket_code));
}

function sc_notify_attendee_fields($attendee) {
    $event = class_exists('SC_Event') ? SC_Event::get((int) $attendee->event_id) : null;
    $workshop = !empty($attendee->workshop_id) && class_exists('SC_Workshop') ? SC_Workshop::get((int) $attendee->workshop_id) : null;
    $place = $workshop ? sc_notify_place_fields($workshop, $event) : ($event ? sc_notify_place_fields($event) : array('{date}' => '', '{time}' => '', '{venue}' => '', '{map_link}' => ''));
    $name = trim(wp_strip_all_tags((string) $attendee->name));
    return array(
        '{name}'        => $name,
        '{first_name}'  => sc_notify_first_name($name),
        '{event}'       => $event ? $event->title : '',
        '{workshop}'    => $workshop ? $workshop->title : '',
        '{title}'       => $workshop ? $workshop->title : ($event ? $event->title : ''),
        '{ticket_type}' => (string) $attendee->ticket_name,
        '{ticket_code}' => (string) $attendee->ticket_code,
        '{ticket_link}' => sc_notify_ticket_link($attendee),
    ) + $place;
}

/* ==========================================================================
   Sending
   ========================================================================== */

/**
 * Send one automatic message.
 *
 * @param string $type One of sc_notify_types().
 * @param array  $args phone, email, name, fields, context_id, media_ref, force (send even when the
 *                     type is off: a person pressed a button), email_now (send the email in this
 *                     request and report the result), delay_seconds (space out a batch), button_url.
 * @return array whatsapp: outbox id|false, email: true|false|null (null = not tried), reason.
 */
function sc_notify($type, $args) {
    $types = sc_notify_types();
    $result = array('whatsapp' => false, 'email' => null, 'reason' => '');
    if (!isset($types[$type])) {
        $result['reason'] = 'unknown_type';
        return $result;
    }
    $settings = sc_notify_settings();
    $cfg = $settings['types'][$type];
    if (empty($args['force']) && empty($cfg['enabled'])) {
        $result['reason'] = 'off';
        return $result;
    }
    $fields = (array) ($args['fields'] ?? array()) + array('{site}' => wp_specialchars_decode(get_option('sc_platform_name', get_bloginfo('name')), ENT_QUOTES));
    $text = sc_wabot_render_message($cfg['template'], $fields);

    $phone = sc_wabot_phone($args['phone'] ?? '');
    if ($phone === '') {
        $result['reason'] = 'no_phone';
    } else {
        $number = sc_wabot_turn_number($type);
        if (!$number) {
            $result['reason'] = 'no_number';
        } else {
            $media = !empty($cfg['attach']) && !empty($args['media_ref']) ? (string) $args['media_ref'] : '';
            $result['whatsapp'] = sc_wabot_enqueue($number, $phone, $text, $type, $args['context_id'] ?? null, 1440, $media, (string) ($args['name'] ?? ''), (int) ($args['delay_seconds'] ?? 0));
        }
    }

    $email = sanitize_email($args['email'] ?? '');
    if (!empty($settings['email']) && is_email($email)) {
        $subject = wp_strip_all_tags(sc_wabot_render_message($cfg['subject'], $fields));
        $html = sc_notify_email_html($text, (string) ($args['button_url'] ?? ''), (string) ($args['button_label'] ?? ''));
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if (!empty($args['email_now'])) {
            $result['email'] = (bool) wp_mail($email, $subject, $html, $headers);
        } else {
            // A slow mail server must not hold up the person's page.
            $result['email'] = true;
            sc_notify_mail_after_response($email, $subject, $html, $headers, $args['on_email_sent'] ?? null);
        }
    }
    return $result;
}

function sc_notify_mail_after_response($to, $subject, $html, $headers, $on_sent = null) {
    static $mails = array();
    $mails[] = compact('to', 'subject', 'html', 'headers', 'on_sent');
    if (count($mails) > 1) {
        return;
    }
    register_shutdown_function(function () use (&$mails) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        foreach ($mails as $m) {
            if (wp_mail($m['to'], $m['subject'], $m['html'], $m['headers']) && is_callable($m['on_sent'])) {
                call_user_func($m['on_sent']);
            }
        }
    });
}

/** The WhatsApp text as a simple email: paragraphs, clickable links, one button. */
function sc_notify_email_html($text, $button_url = '', $button_label = '') {
    $paras = array();
    foreach (preg_split("/\n{2,}/", trim($text)) as $block) {
        $paras[] = '<p style="margin:0 0 14px">' . make_clickable(nl2br(esc_html($block))) . '</p>';
    }
    $button = $button_url ? '<p style="margin:18px 0"><a href="' . esc_url($button_url) . '" style="display:inline-block;padding:12px 24px;background:#7a1f1f;color:#fff;text-decoration:none;border-radius:8px">' . esc_html($button_label ?: __('Open', 'sc_events')) . '</a></p>' : '';
    return '<div dir="auto" style="font-family:Arial,Tahoma,sans-serif;line-height:1.7;color:#222;max-width:560px;margin:0 auto">' . implode('', $paras) . $button . '</div>';
}

/**
 * Ticket for one attendee: automatic after registering, or forced from the dashboard.
 *
 * @return array sc_notify() result, plus reason 'not_found' / 'not_confirmed' / 'recent'.
 */
function sc_notify_ticket($attendee_id, $force = false, $args = array()) {
    global $wpdb;
    $attendee = class_exists('SC_Attendee') ? SC_Attendee::get((int) $attendee_id) : null;
    if (!$attendee || $attendee->status !== 'active') {
        return array('whatsapp' => false, 'email' => null, 'reason' => 'not_found');
    }
    if (!in_array($attendee->payment_status, array('success', 'completed'), true)) {
        return array('whatsapp' => false, 'email' => null, 'reason' => 'not_confirmed');
    }
    if (!$force) {
        // Payment callbacks can arrive twice (redirect and webhook): one ticket is enough.
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_wa_outbox WHERE context = 'ticket' AND context_id = %d AND created_at > %s LIMIT 1",
            (int) $attendee->id, date('Y-m-d H:i:s', current_time('timestamp') - 30 * MINUTE_IN_SECONDS)
        ));
        if ($recent) {
            return array('whatsapp' => false, 'email' => null, 'reason' => 'recent');
        }
    }
    $link = sc_notify_ticket_link($attendee);
    return sc_notify('ticket', $args + array(
        'force'         => $force,
        'phone'         => sc_notify_attendee_phone($attendee),
        'email'         => $attendee->email,
        'name'          => $attendee->name,
        'fields'        => sc_notify_attendee_fields($attendee),
        'context_id'    => (int) $attendee->id,
        'media_ref'     => 'ticket_qr:' . (int) $attendee->id,
        'button_url'    => $link,
        'button_label'  => __('Open your ticket', 'sc_events'),
        'on_email_sent' => function () use ($attendee) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'sc_attendees', array('email_sent' => 1, 'email_sent_at' => current_time('mysql')), array('id' => (int) $attendee->id));
        },
    ));
}

/** Exhibitor badge for one company. */
function sc_notify_company_badge($company_id, $force = false, $args = array()) {
    global $wpdb;
    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, e.title AS event_title FROM {$wpdb->prefix}sc_company_attendees c LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = c.event_id WHERE c.id = %d",
        (int) $company_id
    ));
    if (!$c || $c->status !== 'active') {
        return array('whatsapp' => false, 'email' => null, 'reason' => 'not_found');
    }
    $link = home_url('/company-ticket/' . rawurlencode($c->company_code) . '/');
    return sc_notify('exhibitor_badge', $args + array(
        'force'         => $force,
        'phone'         => $c->contact_phone,
        'email'         => $c->contact_email,
        'name'          => $c->contact_name ?: $c->company_name,
        'fields'        => array(
            '{name}'       => $c->contact_name ?: $c->company_name,
            '{company}'    => $c->company_name,
            '{event}'      => (string) $c->event_title,
            '{booth}'      => (string) $c->booth_number,
            '{badge_link}' => $link,
        ),
        'context_id'    => (int) $c->id,
        'media_ref'     => 'company_qr:' . (int) $c->id,
        'button_url'    => $link,
        'button_label'  => __('Open your badge', 'sc_events'),
        'on_email_sent' => function () use ($c) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'sc_company_attendees', array('email_sent' => 1, 'email_sent_at' => current_time('mysql')), array('id' => (int) $c->id));
        },
    ));
}

/**
 * Certificate with its PDF. $args may carry delay_seconds for a batch.
 */
function sc_notify_certificate($certificate_id, $force = false, $args = array()) {
    global $wpdb;
    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, a.phone, a.email, a.user_id FROM {$wpdb->prefix}sc_certificates c LEFT JOIN {$wpdb->prefix}sc_attendees a ON a.id = c.attendee_id WHERE c.id = %d",
        (int) $certificate_id
    ));
    if (!$c || $c->status === 'revoked') {
        return array('whatsapp' => false, 'email' => null, 'reason' => 'not_found');
    }
    $download = add_query_arg(array('action' => 'sc_download_certificate', 'id' => (int) $c->id, 'token' => wp_hash($c->verification_code . $c->certificate_number)), admin_url('admin-ajax.php'));
    $verify = class_exists('SC_Certificate') ? SC_Certificate::get_verification_url($c->verification_code) : '';
    return sc_notify('certificate_ready', $args + array(
        'force'         => $force,
        'phone'         => sc_notify_attendee_phone($c),
        'email'         => $c->email,
        'name'          => $c->attendee_name,
        'fields'        => array(
            '{name}' => $c->attendee_name, '{first_name}' => sc_notify_first_name($c->attendee_name), '{event}' => $c->event_title,
            '{certificate_number}' => $c->certificate_number, '{certificate_link}' => $download, '{verify_link}' => $verify,
        ),
        'context_id'    => (int) $c->id,
        'media_ref'     => 'certificate_pdf:' . (int) $c->id,
        'button_url'    => $download,
        'button_label'  => __('Download your certificate', 'sc_events'),
        'on_email_sent' => function () use ($c) {
            SC_Certificate::mark_email_sent((int) $c->id);
        },
    ));
}

/** A contact form message: thank the sender and alert the team. */
function sc_notify_support_message($message_id) {
    global $wpdb;
    $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_support_messages WHERE id = %d", (int) $message_id));
    if (!$m) {
        return;
    }
    $phone = sc_wabot_phone($m->phone);
    $fields = array('{name}' => $m->name, '{first_name}' => sc_notify_first_name($m->name), '{subject}' => $m->subject);
    if ($phone !== '') {
        sc_notify('support_received', array('phone' => $phone, 'name' => $m->name, 'fields' => $fields, 'context_id' => (int) $m->id));
    }
    $alert = $fields + array(
        '{phone}'   => $phone !== '' ? '+' . $phone : '',
        '{email}'   => $m->email,
        '{message}' => mb_substr(trim($m->message), 0, 500),
        '{link}'    => home_url('/event-manager-dashboard/support?message=' . (int) $m->id),
    );
    foreach (sc_wabot_recipients() as $r) {
        sc_notify('support_alert', array('phone' => $r['phone'], 'name' => $r['name'], 'fields' => $alert, 'context_id' => (int) $m->id));
    }
}

/** The numbers for the daily summary, as plain lines. */
function sc_notify_summary_text($date) {
    global $wpdb;
    $p = $wpdb->prefix;
    $from = $date . ' 00:00:00';
    $to = $date . ' 23:59:59';
    $lines = array();

    $reg = $wpdb->get_results($wpdb->prepare(
        "SELECT a.event_id, e.title, COUNT(*) AS n FROM {$p}sc_attendees a JOIN {$p}sc_events e ON e.id = a.event_id
         WHERE a.created_at BETWEEN %s AND %s AND a.status = 'active' GROUP BY a.event_id, e.title ORDER BY n DESC LIMIT 6",
        $from, $to
    ));
    $total = array_sum(array_map('intval', wp_list_pluck($reg, 'n')));
    $lines[] = '🎟️ تسجيلات جديدة: ' . number_format_i18n($total);
    foreach ($reg as $r) {
        $all = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_attendees WHERE event_id = %d AND status = 'active' AND payment_status IN ('success', 'completed')", $r->event_id));
        $lines[] = '• ' . $r->title . ': ' . number_format_i18n($r->n) . ' (الإجمالي ' . number_format_i18n($all) . ')';
    }
    $checkins = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_attendees WHERE checked_in_at BETWEEN %s AND %s", $from, $to));
    if ($checkins) {
        $lines[] = '✅ دخول: ' . number_format_i18n($checkins);
    }
    $companies = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_company_attendees WHERE created_at BETWEEN %s AND %s", $from, $to));
    if ($companies) {
        $lines[] = '🏢 شركات جديدة: ' . number_format_i18n($companies);
    }
    $lines[] = '';
    $waiting = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}sc_conversations c WHERE c.status = 'active'
         AND (SELECT m.sender_type FROM {$p}sc_chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) = 'visitor'"
    );
    $lines[] = '💬 شات مستني رد: ' . number_format_i18n($waiting);
    $support = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}sc_support_messages WHERE status = 'new'");
    $lines[] = '📩 رسائل تواصل جديدة: ' . number_format_i18n($support);

    $wa = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(status = 'sent') AS sent, SUM(status IN ('failed', 'expired')) AS failed FROM {$p}sc_wa_outbox WHERE created_at BETWEEN %s AND %s AND context <> 'daily_summary'",
        $from, $to
    ));
    $state = sc_wabot_overall_state();
    $lines[] = '';
    $lines[] = '📱 واتساب: اتبعت ' . number_format_i18n((int) $wa->sent) . ' · فشل ' . number_format_i18n((int) $wa->failed)
        . ' · الأرقام المتصلة ' . (int) ($state['connected'] ?? 0) . '/' . count($state['numbers']);
    return implode("\n", $lines);
}

/** Who gets the summary: the numbers typed on the card, else the chat alert list. */
function sc_notify_summary_recipients() {
    $to = (string) sc_notify_settings()['types']['daily_summary']['to'];
    $out = array();
    foreach (preg_split('/[\r\n,;]+/', $to) as $raw) {
        $phone = sc_wabot_phone($raw);
        if ($phone !== '') {
            $out[$phone] = array('name' => '', 'phone' => $phone);
        }
    }
    return $out ? array_values($out) : sc_wabot_recipients();
}

function sc_notify_daily_summary_tick() {
    $cfg = sc_notify_settings()['types']['daily_summary'];
    $now = current_time('timestamp');
    $date = date('Y-m-d', $now);
    if (empty($cfg['enabled']) || (int) date('G', $now) < (int) $cfg['hour'] || get_option('sc_notify_summary_sent') === $date) {
        return;
    }
    update_option('sc_notify_summary_sent', $date, false);
    $fields = array('{summary}' => sc_notify_summary_text($date), '{date}' => date_i18n('l j F', $now));
    foreach (sc_notify_summary_recipients() as $r) {
        sc_notify('daily_summary', array('phone' => $r['phone'], 'name' => $r['name'], 'fields' => $fields));
    }
}
add_action('sc_wabot_tick', 'sc_notify_daily_summary_tick', 30);

/** Tell the account owner their password was just changed. */
function sc_notify_password_changed($user_id) {
    $user = get_userdata((int) $user_id);
    if (!$user) {
        return array('whatsapp' => false, 'email' => null, 'reason' => 'not_found');
    }
    $phone = sc_wabot_phone(get_user_meta($user->ID, 'phone', true)) ?: sc_wabot_phone(get_user_meta($user->ID, 'billing_phone', true));
    return sc_notify('password_changed', array(
        'phone'  => $phone,
        'email'  => $user->user_email,
        'name'   => $user->display_name,
        'fields' => array('{name}' => $user->display_name, '{first_name}' => sc_notify_first_name($user->display_name)),
    ));
}

/** A short sentence for the dashboard about what a send did. */
function sc_notify_result_message($result, $who = '') {
    $parts = array();
    if ($result['whatsapp']) {
        $parts[] = __('WhatsApp is on its way', 'sc_events');
    }
    if ($result['email'] === true) {
        $parts[] = __('email sent', 'sc_events');
    } elseif ($result['email'] === false) {
        $parts[] = __('the email could not be sent (check the site’s mail settings)', 'sc_events');
    }
    if (!$result['whatsapp']) {
        $why = array(
            'no_phone'  => __('no valid WhatsApp number on file', 'sc_events'),
            'no_number' => __('no WhatsApp number is set up for tickets', 'sc_events'),
        );
        if (isset($why[$result['reason']])) {
            $parts[] = sprintf(__('not sent on WhatsApp: %s', 'sc_events'), $why[$result['reason']]);
        }
    }
    $text = implode('; ', $parts);
    return $text !== '' ? ($who !== '' ? $who . ': ' : '') . $text . '.' : '';
}

/* ==========================================================================
   Reminders: bulk, so they run as campaigns with a gap
   ========================================================================== */

function sc_notify_reminders_tick() {
    if (get_transient('sc_notify_reminder_check') || !function_exists('sc_wabot_create_campaign')) {
        return;
    }
    set_transient('sc_notify_reminder_check', 1, 5 * MINUTE_IN_SECONDS);
    global $wpdb;
    $settings = sc_notify_settings();
    $now_ts = current_time('timestamp');
    $types = sc_notify_types();

    foreach (array('reminder_day_before' => 1, 'reminder_same_day' => 0) as $type => $days) {
        $cfg = $settings['types'][$type];
        $hour = (int) date('G', $now_ts);
        // Start from the set hour; don't start a new one late in the evening.
        if (empty($cfg['enabled']) || $hour < (int) $cfg['hour'] || $hour >= 21) {
            continue;
        }
        $date = date('Y-m-d', $now_ts + $days * DAY_IN_SECONDS);
        $events = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' AND start_date = %s", $date));
        foreach ($events as $event) {
            $flag = 'sc_notify_' . $type . '_' . (int) $event->id . '_' . $date;
            if (get_option($flag)) {
                continue;
            }
            $campaign = sc_wabot_create_campaign(array(
                'title'            => sprintf('%s — %s', $types[$type]['label'], $event->title),
                'source'           => 'event',
                'event_id'         => (int) $event->id,
                'audience'         => 'everyone',
                'message'          => $cfg['template'],
                'interval_seconds' => $settings['interval'],
                'attach'           => !empty($cfg['attach']) ? 'ticket_qr' : '',
            ));
            // Remember it either way (also "nobody to send to"), so it is never created twice.
            update_option($flag, is_wp_error($campaign) ? $campaign->get_error_code() : (int) $campaign, false);
            if (!empty($settings['email'])) {
                wp_schedule_single_event(time() + 30, 'sc_notify_reminder_emails', array($type, (int) $event->id, 0));
            }
        }
    }
}
add_action('sc_wabot_tick', 'sc_notify_reminders_tick', 20);

/** Reminder emails in small batches, when email is on. */
add_action('sc_notify_reminder_emails', function ($type, $event_id, $after_id) {
    global $wpdb;
    $settings = sc_notify_settings();
    if (empty($settings['email']) || !isset($settings['types'][$type])) {
        return;
    }
    $cfg = $settings['types'][$type];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_attendees WHERE event_id = %d AND id > %d AND status = 'active' AND payment_status IN ('success', 'completed') AND email <> '' ORDER BY id LIMIT 40",
        (int) $event_id, (int) $after_id
    ));
    $seen = get_transient('sc_notify_reminder_emails_' . $type . '_' . $event_id);
    $seen = is_array($seen) ? $seen : array();
    $last = (int) $after_id;
    foreach ($rows as $a) {
        $last = (int) $a->id;
        $key = strtolower($a->email);
        if (isset($seen[$key]) || !is_email($a->email)) {
            continue;
        }
        $seen[$key] = 1;
        $fields = sc_notify_attendee_fields($a) + array('{site}' => get_option('sc_platform_name', get_bloginfo('name')));
        wp_mail($a->email, wp_strip_all_tags(sc_wabot_render_message($cfg['subject'], $fields)),
            sc_notify_email_html(sc_wabot_render_message($cfg['template'], $fields), sc_notify_ticket_link($a), __('Open your ticket', 'sc_events')),
            array('Content-Type: text/html; charset=UTF-8'));
    }
    set_transient('sc_notify_reminder_emails_' . $type . '_' . $event_id, $seen, 2 * DAY_IN_SECONDS);
    if (count($rows) === 40) {
        wp_schedule_single_event(time() + 60, 'sc_notify_reminder_emails', array($type, (int) $event_id, $last));
    }
}, 10, 3);
