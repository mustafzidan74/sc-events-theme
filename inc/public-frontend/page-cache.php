<?php
/**
 * What the page cache (LiteSpeed Cache) may keep, and when it lets go.
 *
 * Visitors who are not signed in get a stored copy of the public pages, so a
 * rush of 5,000 people opening the same link does not run WordPress 5,000
 * times. Signed-in people are never served from the cache (the plugin's own
 * rule). This file adds the theme's part:
 *
 * - pages about one person, a payment or a ticket are never stored;
 * - a registration clears the pages that show seats left, at most once a
 *   minute per page, so a busy day does not empty the cache every second;
 * - editing an event, workshop or ticket clears everything.
 *
 * Nothing here runs unless the plugin is active.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Seconds between two clears of the same page caused by registrations. */
const SC_PAGE_CACHE_PURGE_GAP = 60;

function sc_page_cache_on() {
    return defined('LSCWP_V');
}

/** Never store: accounts, sign-in, payments, tickets, certificates, the dashboard. */
add_action('template_redirect', function () {
    if (!sc_page_cache_on()) {
        return;
    }
    $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $private = is_page(array('my-account', 'login', 'register', 'forgot-password', 'checkout', 'ticket-view', 'payment-success', 'payment-failed'))
        || is_page_template(array('page-certifcate.php', 'page-certifcate-order.php'))
        || get_query_var('dashboard_page')
        || get_query_var('sc_company_ticket')
        || get_query_var('sc_verify_page')
        || strpos($path, '/event-manager-dashboard') !== false
        || isset($_GET['sc_logout'])
        || isset($_GET['sc_scanner_sw']);
    if ($private) {
        do_action('litespeed_control_set_nocache', 'sc_events: personal page');
    }
}, 0);

/**
 * Clear stored copies of these addresses.
 *
 * @param string[] $urls
 * @param int      $gap  Skip an address cleared less than this many seconds ago (0 = always).
 */
function sc_page_cache_purge(array $urls, $gap = 0) {
    if (!sc_page_cache_on()) {
        return;
    }
    foreach (array_unique(array_filter($urls)) as $url) {
        if ($gap) {
            $key = 'sc_pc_purged_' . md5($url);
            if (get_transient($key)) {
                continue;
            }
            set_transient($key, 1, $gap);
        }
        do_action('litespeed_purge_url', $url);
    }
}

function sc_page_cache_purge_all() {
    if (sc_page_cache_on()) {
        do_action('litespeed_purge_all');
    }
}

/** The public pages that show an event's seats: home, listings, the event and its workshops. */
function sc_page_cache_event_urls($event_id, $workshop_id = 0) {
    global $wpdb;
    $urls = array(home_url('/'), home_url('/events/'), home_url('/workshops/'));
    $slug = $event_id ? $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id)) : '';
    if ($slug) {
        $urls[] = home_url('/event/' . $slug);
        $urls[] = home_url('/event/' . $slug . '/');
    }
    if ($workshop_id) {
        $ws = $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$wpdb->prefix}sc_workshops WHERE id = %d", $workshop_id));
        if ($ws) {
            $urls[] = home_url('/workshop/' . $ws);
            $urls[] = home_url('/workshop/' . $ws . '/');
        }
    }
    return $urls;
}

function sc_page_cache_attendee_changed($attendee_id, $attendee = null) {
    if (!sc_page_cache_on()) {
        return;
    }
    global $wpdb;
    if (!is_object($attendee) || !isset($attendee->event_id)) {
        $attendee = $wpdb->get_row($wpdb->prepare("SELECT event_id, workshop_id FROM {$wpdb->prefix}sc_attendees WHERE id = %d", $attendee_id));
    }
    if ($attendee) {
        sc_page_cache_purge(sc_page_cache_event_urls((int) $attendee->event_id, (int) $attendee->workshop_id), SC_PAGE_CACHE_PURGE_GAP);
    }
}
add_action('sc_attendee_created', function ($id) { sc_page_cache_attendee_changed($id); });
add_action('sc_attendee_updated', function ($id) { sc_page_cache_attendee_changed($id); });
add_action('sc_attendee_deleted', function ($id, $attendee = null) { sc_page_cache_attendee_changed($id, $attendee); }, 10, 2);

foreach (array('sc_event_created', 'sc_event_updated', 'sc_event_deleted', 'sc_workshop_created', 'sc_workshop_updated', 'sc_workshop_deleted', 'sc_ticket_created', 'sc_ticket_updated', 'sc_ticket_deleted') as $sc_pc_hook) {
    add_action($sc_pc_hook, 'sc_page_cache_purge_all');
}
unset($sc_pc_hook);

// Speakers, sponsors and the like are saved in dashboard requests; their pages show on the
// home page too, so any dashboard save of them clears everything.
add_action('updated_option', function ($option) {
    if (in_array($option, array('sc_featured_event_id', 'sc_platform_name', 'sc_platform_logo', 'sc_site_language'), true)) {
        sc_page_cache_purge_all();
    }
});

// Any other dashboard save (speakers, sponsors, sessions, settings...) clears everything once
// the request is done. Door scans and public registrations do not match.
add_action('admin_init', function () {
    if (!sc_page_cache_on() || !wp_doing_ajax() || empty($_POST['action'])) {
        return;
    }
    $action = sanitize_key(wp_unslash($_POST['action']));
    if (strpos($action, 'sc_') !== 0 || !preg_match('/(save|update|create|delete|add|edit|remove|toggle|publish|reorder|import)/', $action)) {
        return;
    }
    if (!class_exists('SC_Event_Manager_Dashboard') || !SC_Event_Manager_Dashboard::is_event_manager()) {
        return;
    }
    add_action('shutdown', 'sc_page_cache_purge_all', 1);
});
