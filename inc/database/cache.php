<?php
/**
 * SC Events Caching System
 *
 * Provides object caching for frequently accessed data
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SC Events Cache Class
 */
class SC_Cache {

    /**
     * Cache group name
     */
    const GROUP = 'sc_events';

    /**
     * Default cache expiration (5 minutes)
     */
    const DEFAULT_EXPIRATION = 300;

    /**
     * Long cache expiration (1 hour)
     */
    const LONG_EXPIRATION = 3600;

    /**
     * Short cache expiration (1 minute)
     */
    const SHORT_EXPIRATION = 60;

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group (default: sc_events)
     * @return mixed|false Cached data or false if not found
     */
    public static function get($key, $group = self::GROUP) {
        $cached = wp_cache_get($key, $group);

        if ($cached === false) {
            // Try transient as fallback
            $cached = get_transient(self::GROUP . '_' . $key);
        }

        return $cached;
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration in seconds
     * @param string $group Cache group
     * @return bool Success
     */
    public static function set($key, $data, $expiration = self::DEFAULT_EXPIRATION, $group = self::GROUP) {
        // Set in object cache
        wp_cache_set($key, $data, $group, $expiration);

        // Also set as transient for persistence
        set_transient(self::GROUP . '_' . $key, $data, $expiration);

        return true;
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public static function delete($key, $group = self::GROUP) {
        wp_cache_delete($key, $group);
        delete_transient(self::GROUP . '_' . $key);

        return true;
    }

    /**
     * Clear all SC Events cache
     *
     * @return bool Success
     */
    public static function flush() {
        global $wpdb;

        // Flush object cache group
        wp_cache_flush_group(self::GROUP);

        // Delete all SC Events transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_sc_events_%'
            OR option_name LIKE '_transient_timeout_sc_events_%'
            OR option_name LIKE '_transient_sc_%'"
        );

        return true;
    }

    /**
     * Get or set cached data with callback
     *
     * @param string $key Cache key
     * @param callable $callback Function to generate data if not cached
     * @param int $expiration Expiration in seconds
     * @return mixed Cached or generated data
     */
    public static function remember($key, $callback, $expiration = self::DEFAULT_EXPIRATION) {
        $cached = self::get($key);

        if ($cached !== false) {
            return $cached;
        }

        $data = call_user_func($callback);
        self::set($key, $data, $expiration);

        return $data;
    }

    // ========================================
    // SPECIFIC CACHE METHODS
    // ========================================

    /**
     * Get cached event
     *
     * @param int $id Event ID
     * @return object|false
     */
    public static function get_event($id) {
        return self::get('event_' . $id);
    }

    /**
     * Cache event
     *
     * @param int $id Event ID
     * @param object $event Event object
     */
    public static function set_event($id, $event) {
        self::set('event_' . $id, $event, self::DEFAULT_EXPIRATION);

        // Also cache by slug
        if (!empty($event->slug)) {
            self::set('event_slug_' . $event->slug, $event, self::DEFAULT_EXPIRATION);
        }
    }

    /**
     * Clear event cache
     *
     * @param int $id Event ID
     * @param string $slug Event slug (optional)
     */
    public static function clear_event($id, $slug = '') {
        self::delete('event_' . $id);

        if ($slug) {
            self::delete('event_slug_' . $slug);
        }

        // Clear related caches
        self::delete('events_list');
        self::delete('events_upcoming');
        self::delete('events_count');
    }

    /**
     * Get cached attendee
     *
     * @param int $id Attendee ID
     * @return object|false
     */
    public static function get_attendee($id) {
        return self::get('attendee_' . $id);
    }

    /**
     * Cache attendee
     *
     * @param int $id Attendee ID
     * @param object $attendee Attendee object
     */
    public static function set_attendee($id, $attendee) {
        self::set('attendee_' . $id, $attendee, self::SHORT_EXPIRATION);
    }

    /**
     * Clear attendee cache
     *
     * @param int $id Attendee ID
     * @param int $event_id Event ID (optional)
     */
    public static function clear_attendee($id, $event_id = 0) {
        self::delete('attendee_' . $id);

        if ($event_id) {
            self::delete('event_attendees_' . $event_id);
            self::delete('event_stats_' . $event_id);
        }
    }

    /**
     * Get cached dashboard stats
     *
     * @return array|false
     */
    public static function get_dashboard_stats() {
        return self::get('dashboard_stats');
    }

    /**
     * Cache dashboard stats
     *
     * @param array $stats Stats array
     */
    public static function set_dashboard_stats($stats) {
        self::set('dashboard_stats', $stats, self::DEFAULT_EXPIRATION);
    }

    /**
     * Clear dashboard stats cache
     */
    public static function clear_dashboard_stats() {
        self::delete('dashboard_stats');
        self::delete('sc_reports_dashboard_stats');
    }
}

// ========================================
// CACHE INVALIDATION HOOKS
// ========================================

/**
 * Clear event cache when event is created/updated/deleted
 */
add_action('sc_event_created', function($event_id) {
    SC_Cache::clear_event($event_id);
    SC_Cache::clear_dashboard_stats();
});

add_action('sc_event_updated', function($event_id, $data = array()) {
    $slug = isset($data['slug']) ? $data['slug'] : '';
    SC_Cache::clear_event($event_id, $slug);
    SC_Cache::clear_dashboard_stats();
});

add_action('sc_event_deleted', function($event_id, $event = null) {
    $slug = $event ? $event->slug : '';
    SC_Cache::clear_event($event_id, $slug);
    SC_Cache::clear_dashboard_stats();
});

/**
 * Clear attendee cache when attendee is created/updated/deleted
 */
add_action('sc_attendee_created', function($attendee_id, $data = array()) {
    $event_id = isset($data['event_id']) ? $data['event_id'] : 0;
    SC_Cache::clear_attendee($attendee_id, $event_id);
    SC_Cache::clear_dashboard_stats();
});

add_action('sc_attendee_updated', function($attendee_id, $data = array()) {
    $event_id = isset($data['event_id']) ? $data['event_id'] : 0;
    SC_Cache::clear_attendee($attendee_id, $event_id);
    SC_Cache::clear_dashboard_stats();
});

add_action('sc_attendee_deleted', function($attendee_id, $attendee = null) {
    $event_id = $attendee ? $attendee->event_id : 0;
    SC_Cache::clear_attendee($attendee_id, $event_id);
    SC_Cache::clear_dashboard_stats();
});

/**
 * Clear ticket cache when ticket changes
 */
add_action('sc_ticket_created', function($ticket_id, $data = array()) {
    $event_id = isset($data['event_id']) ? $data['event_id'] : 0;
    if ($event_id) {
        SC_Cache::delete('event_tickets_' . $event_id);
    }
});

add_action('sc_ticket_updated', function($ticket_id, $data = array()) {
    $event_id = isset($data['event_id']) ? $data['event_id'] : 0;
    if ($event_id) {
        SC_Cache::delete('event_tickets_' . $event_id);
    }
});

add_action('sc_ticket_deleted', function($ticket_id, $ticket = null) {
    if ($ticket && $ticket->event_id) {
        SC_Cache::delete('event_tickets_' . $ticket->event_id);
    }
});
