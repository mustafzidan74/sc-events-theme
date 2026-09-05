<?php
/**
 * Dashboard Certificate Template Create Page
 * Redirects to Visual Builder
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Redirect to Visual Builder
wp_redirect(home_url('/event-manager-dashboard/certificate-visual-builder'));
exit;
