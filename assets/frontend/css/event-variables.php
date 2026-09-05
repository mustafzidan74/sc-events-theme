<?php
/**
 * Dynamic CSS Variables for Events
 * This file generates CSS variables based on WordPress options
 */

// Set proper content type
header("Content-type: text/css; charset=UTF-8");

// Get WordPress functions
require_once('../../../../../wp-load.php');

// Get color settings
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Platform colors (global settings)
$platform_primary = get_option('sc_primary_color', '#FF4B36');
$platform_secondary = get_option('sc_secondary_color', '#1B1E4A');

// Event-specific colors if event_id provided
if ($event_id > 0) {
    $event_bg_color = get_post_meta($event_id, 'sc_event_calendar_bg', true);
    $event_text_color = get_post_meta($event_id, 'sc_event_calendar_text_color', true);

    // Use event colors if set, otherwise fallback to platform colors
    $primary_color = !empty($event_bg_color) ? $event_bg_color : $platform_primary;
    $secondary_color = !empty($event_text_color) ? $event_text_color : $platform_secondary;
} else {
    $primary_color = $platform_primary;
    $secondary_color = $platform_secondary;
}
?>
/* CSS Variables - Event-specific colors with fallback to Platform colors */
:root {
    --ztc-text-text-11: <?php echo esc_attr($primary_color); ?>;
    --ztc-text-text-9: <?php echo esc_attr($secondary_color); ?>;
    --ztc-bg-bg-9: <?php echo esc_attr($secondary_color); ?>;
}
