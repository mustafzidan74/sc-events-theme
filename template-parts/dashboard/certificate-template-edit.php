<?php
/**
 * Dashboard Certificate Template Edit Page
 * Redirects to Visual Builder
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get template ID from URL
$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect to Visual Builder
if ($template_id) {
    wp_redirect(home_url('/event-manager-dashboard/certificate-visual-builder?template_id=' . $template_id));
} else {
    wp_redirect(home_url('/event-manager-dashboard/certificate-visual-builder'));
}
exit;
