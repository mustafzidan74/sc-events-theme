<?php
/**
 * Keep MySQL's clock on the site's time zone.
 *
 * The theme stores local times: its code writes current_time('mysql'), and its
 * tables fill created_at/updated_at with MySQL's CURRENT_TIMESTAMP. Those two
 * only agree when the MySQL session uses the same zone as Settings → General,
 * so every request (theme and the SHORTINIT API) sets it once, from WordPress.
 * The offset is taken for "now", which follows daylight saving.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_sync_db_timezone')) {
    function sc_sync_db_timezone() {
        global $wpdb;
        static $done = false;
        if ($done || !isset($wpdb) || !function_exists('get_option')) {
            return;
        }
        $done = true;

        try {
            $zone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $seconds = $zone->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        } catch (Exception $e) {
            return;
        }
        $value = sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
        $wpdb->query($wpdb->prepare('SET time_zone = %s', $value));
    }
}

sc_sync_db_timezone();
// A time zone change in Settings applies to the rest of the same request too.
if (function_exists('add_action')) {
    add_action('update_option_timezone_string', function () {
        global $wpdb;
        $zone = wp_timezone();
        $seconds = $zone->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $wpdb->query($wpdb->prepare('SET time_zone = %s', sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60))));
    });
}
