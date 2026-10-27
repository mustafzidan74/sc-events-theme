<?php
/**
 * "Download your certificate again" bar.
 *
 * Shown under the public header to signed-in people who hold a certificate for
 * the event named in the sc_certificate_notice option, e.g.
 * array('event_id' => 5, 'key' => '2026-10-27'). A certificate drops out of the
 * bar once its owner downloads it while signed in; the bar goes when none are
 * left or when they close it. A new key starts the bar over for everyone; an
 * empty option turns it off.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The notice in force: array('event_id' => int, 'key' => string), or null.
 */
function sc_certificate_notice() {
    $notice = get_option('sc_certificate_notice');
    $key = is_array($notice) ? sanitize_key($notice['key'] ?? '') : '';
    if (!$key || empty($notice['event_id'])) {
        return null;
    }
    return array('event_id' => (int) $notice['event_id'], 'key' => $key);
}

function sc_certificate_notice_meta_key($notice) {
    return 'sc_cert_notice_' . $notice['key'];
}

/**
 * The user's live certificates for an event, congress first. Tickets carry user_id when the
 * person registered, else only their email (as on My Account).
 *
 * @return object[] id, verification_code, certificate_number, workshop_id, workshop_title
 */
function sc_certificate_notice_certificates($event_id, $user) {
    global $wpdb;
    $p = $wpdb->prefix;
    // An account without an email must not match the tickets that have none.
    $owner = $user->user_email ? '(a.user_id = %d OR a.email = %s)' : 'a.user_id = %d';
    $values = $user->user_email ? array($event_id, $user->ID, $user->user_email) : array($event_id, $user->ID);
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.verification_code, c.certificate_number, c.workshop_id, w.title AS workshop_title
         FROM {$p}sc_certificates c
         JOIN {$p}sc_attendees a ON a.id = c.attendee_id
         LEFT JOIN {$p}sc_workshops w ON w.id = c.workshop_id
         WHERE c.event_id = %d AND c.status <> 'revoked' AND a.status = 'active' AND $owner
         ORDER BY c.workshop_id IS NOT NULL AND c.workshop_id <> 0, c.id",
        $values
    ));
}

/**
 * The user's certificates still to download under the notice, each with its title and link.
 *
 * @return array[] id, title, url
 */
function sc_certificate_notice_items($user) {
    $notice = sc_certificate_notice();
    if (!$notice || !$user || !$user->ID) {
        return array();
    }
    $state = get_user_meta($user->ID, sc_certificate_notice_meta_key($notice), true);
    if ($state === 'closed') {
        return array();
    }
    $done = is_array($state) ? array_map('intval', $state) : array();

    $items = array();
    foreach (sc_certificate_notice_certificates($notice['event_id'], $user) as $c) {
        if (in_array((int) $c->id, $done, true)) {
            continue;
        }
        $items[] = array(
            'id'    => (int) $c->id,
            'title' => !$c->workshop_id ? sc_t('frontend.cert_notice_congress', 'Congress certificate')
                : ($c->workshop_title ?: sc_t('frontend.cert_notice_workshop', 'Workshop certificate')),
            'url'   => add_query_arg(array(
                'action' => 'sc_download_certificate',
                'id'     => (int) $c->id,
                'token'  => wp_hash($c->verification_code . $c->certificate_number),
            ), admin_url('admin-ajax.php')),
        );
    }
    return $items;
}

/**
 * A download by the signed-in owner takes that certificate out of their bar. Other
 * downloads (a manager's from the dashboard, other events) are not recorded.
 */
add_action('sc_certificate_downloaded', 'sc_certificate_notice_mark_downloaded');
function sc_certificate_notice_mark_downloaded($certificate_id) {
    $notice = sc_certificate_notice();
    $user = wp_get_current_user();
    if (!$notice || !$user->ID) {
        return;
    }
    $meta_key = sc_certificate_notice_meta_key($notice);
    $state = get_user_meta($user->ID, $meta_key, true);
    if ($state === 'closed') {
        return;
    }
    $mine = array_map('intval', wp_list_pluck(sc_certificate_notice_certificates($notice['event_id'], $user), 'id'));
    $done = is_array($state) ? array_map('intval', $state) : array();
    if (in_array((int) $certificate_id, $mine, true) && !in_array((int) $certificate_id, $done, true)) {
        $done[] = (int) $certificate_id;
        update_user_meta($user->ID, $meta_key, $done);
    }
}

/**
 * The reader closed the bar.
 */
add_action('wp_ajax_sc_certificate_notice_close', 'sc_certificate_notice_close');
function sc_certificate_notice_close() {
    check_ajax_referer('sc_certificate_notice', 'nonce');
    $notice = sc_certificate_notice();
    if ($notice) {
        update_user_meta(get_current_user_id(), sc_certificate_notice_meta_key($notice), 'closed');
    }
    wp_send_json_success();
}
