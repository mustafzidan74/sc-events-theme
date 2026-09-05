<?php
/**
 * Frontend Dynamic Colors CSS Generator
 * Generates CSS custom properties from dashboard color settings
 * Outputs inline <style> block to override variables.css defaults
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Get colors from dashboard settings
$sc_primary = get_option('sc_primary_color', '#D4AF37');
$sc_secondary = get_option('sc_secondary_color', '#7c3aed');

// Helper: adjust hex brightness by percent (-100 to +100)
if (!function_exists('sc_adjust_brightness')) {
    function sc_adjust_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $rgb = array_map('hexdec', str_split($hex, 2));
        foreach ($rgb as &$c) {
            $c = max(0, min(255, (int)($c + ($percent * 255 / 100))));
        }
        return '#' . implode('', array_map(function($v) {
            return str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
        }, $rgb));
    }
}

// Helper: hex to rgba string
if (!function_exists('sc_hex_to_rgba')) {
    function sc_hex_to_rgba($hex, $alpha = 1) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $rgb = array_map('hexdec', str_split($hex, 2));
        return sprintf('rgba(%d, %d, %d, %s)', $rgb[0], $rgb[1], $rgb[2], $alpha);
    }
}

// Generate variations
$primary_light  = sc_adjust_brightness($sc_primary, 15);
$primary_dark   = sc_adjust_brightness($sc_primary, -15);
$secondary_light = sc_adjust_brightness($sc_secondary, 15);
$secondary_dark  = sc_adjust_brightness($sc_secondary, -15);

// RGBA variants
$p10 = sc_hex_to_rgba($sc_primary, 0.1);
$p20 = sc_hex_to_rgba($sc_primary, 0.2);
$p30 = sc_hex_to_rgba($sc_primary, 0.3);
$p50 = sc_hex_to_rgba($sc_primary, 0.5);
$s10 = sc_hex_to_rgba($sc_secondary, 0.1);
$s20 = sc_hex_to_rgba($sc_secondary, 0.2);
$s30 = sc_hex_to_rgba($sc_secondary, 0.3);

// Gold text gradient needs a lighter version for middle stop
$primary_text_light = sc_adjust_brightness($sc_primary, 30);
?>
<style id="sc-frontend-dynamic-colors">
:root {
    /* Primary Color (from dashboard) */
    --sc-primary: <?php echo esc_attr($sc_primary); ?>;
    --sc-primary-light: <?php echo esc_attr($primary_light); ?>;
    --sc-primary-dark: <?php echo esc_attr($primary_dark); ?>;
    --sc-primary-alpha-10: <?php echo $p10; ?>;
    --sc-primary-alpha-20: <?php echo $p20; ?>;
    --sc-primary-alpha-30: <?php echo $p30; ?>;
    --sc-primary-alpha-50: <?php echo $p50; ?>;

    /* Secondary Color (from dashboard) */
    --sc-secondary: <?php echo esc_attr($sc_secondary); ?>;
    --sc-secondary-light: <?php echo esc_attr($secondary_light); ?>;
    --sc-secondary-dark: <?php echo esc_attr($secondary_dark); ?>;
    --sc-secondary-alpha-10: <?php echo $s10; ?>;
    --sc-secondary-alpha-20: <?php echo $s20; ?>;
    --sc-secondary-alpha-30: <?php echo $s30; ?>;

    /* Gradients */
    --sc-gradient-primary: linear-gradient(135deg, <?php echo esc_attr($sc_primary); ?> 0%, <?php echo esc_attr($primary_light); ?> 100%);
    --sc-gradient-primary-text: linear-gradient(135deg, <?php echo esc_attr($sc_primary); ?> 0%, <?php echo esc_attr($primary_text_light); ?> 50%, <?php echo esc_attr($sc_primary); ?> 100%);
    --sc-gradient-secondary: linear-gradient(135deg, <?php echo esc_attr($sc_secondary); ?> 0%, <?php echo esc_attr($secondary_light); ?> 100%);

    /* Borders */
    --sc-border-primary: <?php echo $p30; ?>;
    --sc-border-primary-strong: <?php echo $p50; ?>;

    /* Glows */
    --sc-glow-primary: 0 0 30px <?php echo sc_hex_to_rgba($sc_primary, 0.15); ?>;
    --sc-glow-primary-strong: 0 0 40px <?php echo sc_hex_to_rgba($sc_primary, 0.25); ?>;
    --sc-glow-secondary: 0 0 30px <?php echo sc_hex_to_rgba($sc_secondary, 0.2); ?>;

    /* Text */
    --sc-text-accent: <?php echo esc_attr($sc_primary); ?>;

    /* Input focus */
    --sc-input-focus-ring: 0 0 0 3px <?php echo $p20; ?>;

    /* Shadow */
    --sc-shadow-primary: 0 4px 14px <?php echo $p30; ?>;

    /* Legacy compat (for old code referencing --primary-color) */
    --primary-color: <?php echo esc_attr($sc_primary); ?>;
    --primary-color-light: <?php echo esc_attr($primary_light); ?>;
    --primary-color-dark: <?php echo esc_attr($primary_dark); ?>;
    --secondary-color: <?php echo esc_attr($sc_secondary); ?>;
    --primary-color2: <?php echo esc_attr($sc_secondary); ?>;
}
</style>
<?php
