<?php
/**
 * Client IP lookup shared by the theme and the standalone API (api.php, SHORTINIT).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_get_client_ip')) {
    /**
     * The visitor's IP, for lockouts, throttles and rate limits.
     *
     * Forwarding headers (CF-Connecting-IP, X-Real-IP, X-Forwarded-For) are
     * written by whoever sends the request, so they are only believed when the
     * connection itself comes from a private-network proxy, or when a site
     * behind a public CDN opts in with the sc_trust_proxy_headers filter.
     * Trusting them unconditionally let anyone reset the login lockout by
     * sending a new X-Forwarded-For with every attempt.
     */
    function sc_get_client_ip() {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $remote = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';

        $behind_proxy = $remote !== '0.0.0.0'
            && !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if (!apply_filters('sc_trust_proxy_headers', $behind_proxy, $remote)) {
            return $remote;
        }

        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR') as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }
            $ip = trim(explode(',', (string) $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $remote;
    }
}
