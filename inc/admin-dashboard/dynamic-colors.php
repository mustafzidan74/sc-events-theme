<?php
/**
 * Dynamic Colors CSS Generator
 * Generates custom CSS based on platform primary and secondary colors
 *
 * @package sc_events
 */

// Get colors from settings
$primary_color = get_option('sc_primary_color', '#667eea');
$secondary_color = get_option('sc_secondary_color', '#764ba2');

// Helper function to adjust brightness
function adjust_color_brightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $rgb = array_map('hexdec', str_split($hex, 2));

    foreach ($rgb as &$color) {
        $color = max(0, min(255, $color + ($percent * 255 / 100)));
    }

    return '#' . implode('', array_map(function($val) {
        return str_pad(dechex($val), 2, '0', STR_PAD_LEFT);
    }, $rgb));
}

// Generate color variations
$primary_light = adjust_color_brightness($primary_color, 20);
$primary_dark = adjust_color_brightness($primary_color, -20);
$secondary_light = adjust_color_brightness($secondary_color, 20);
$secondary_dark = adjust_color_brightness($secondary_color, -20);

// Convert hex to rgba
function hex_to_rgba($hex, $alpha = 1) {
    $hex = ltrim($hex, '#');
    $rgb = array_map('hexdec', str_split($hex, 2));
    return sprintf('rgba(%d, %d, %d, %s)', $rgb[0], $rgb[1], $rgb[2], $alpha);
}

$primary_rgba_10 = hex_to_rgba($primary_color, 0.1);
$primary_rgba_20 = hex_to_rgba($primary_color, 0.2);
$primary_rgba_30 = hex_to_rgba($primary_color, 0.3);
$secondary_rgba_10 = hex_to_rgba($secondary_color, 0.1);
$secondary_rgba_20 = hex_to_rgba($secondary_color, 0.2);

// Dark mode: higher opacity for visibility on dark backgrounds
$primary_rgba_10_dark = hex_to_rgba($primary_color, 0.15);
$primary_rgba_20_dark = hex_to_rgba($primary_color, 0.25);
$primary_rgba_30_dark = hex_to_rgba($primary_color, 0.35);
$secondary_rgba_10_dark = hex_to_rgba($secondary_color, 0.15);
$secondary_rgba_20_dark = hex_to_rgba($secondary_color, 0.25);

// Extract RGB for CSS usage
$primary_hex = ltrim($primary_color, '#');
$primary_rgb_parts = array_map('hexdec', str_split($primary_hex, 2));
$primary_rgb_str = implode(', ', $primary_rgb_parts);
?>
<style id="sc-dynamic-colors">
:root {
    /* Primary Color System */
    --primary-color: <?php echo esc_attr($primary_color); ?>;
    --primary-color-light: <?php echo esc_attr($primary_light); ?>;
    --primary-color-dark: <?php echo esc_attr($primary_dark); ?>;

    /* Secondary Color System (Hover) */
    --secondary-color: <?php echo esc_attr($secondary_color); ?>;
    --primary-color2: <?php echo esc_attr($secondary_color); ?>;
    --secondary-color-light: <?php echo esc_attr($secondary_light); ?>;
    --secondary-color-dark: <?php echo esc_attr($secondary_dark); ?>;

    /* RGBA Variants */
    --primary-rgba-10: <?php echo $primary_rgba_10; ?>;
    --primary-rgba-20: <?php echo $primary_rgba_20; ?>;
    --primary-rgba-30: <?php echo $primary_rgba_30; ?>;
    --secondary-rgba-10: <?php echo $secondary_rgba_10; ?>;
    --secondary-rgba-20: <?php echo $secondary_rgba_20; ?>;

    /* RGB for dynamic alpha in CSS */
    --primary-color-rgb: <?php echo $primary_rgb_str; ?>;

    /* Gradients */
    --primary-gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --primary-gradient-hover: linear-gradient(135deg, var(--primary-color-dark) 0%, var(--secondary-color-dark) 100%);
}

/* ===================================
   DARK MODE - Increased RGBA Opacity
   =================================== */
[data-theme="dark"] {
    --primary-rgba-10: <?php echo $primary_rgba_10_dark; ?>;
    --primary-rgba-20: <?php echo $primary_rgba_20_dark; ?>;
    --primary-rgba-30: <?php echo $primary_rgba_30_dark; ?>;
    --secondary-rgba-10: <?php echo $secondary_rgba_10_dark; ?>;
    --secondary-rgba-20: <?php echo $secondary_rgba_20_dark; ?>;
}

[data-theme="dark"][class*="theme-"] {
    --primary-rgba-10: <?php echo $primary_rgba_10_dark; ?> !important;
    --primary-rgba-20: <?php echo $primary_rgba_20_dark; ?> !important;
    --primary-rgba-30: <?php echo $primary_rgba_30_dark; ?> !important;
}

/* ===================================
   THEME CYAN OVERRIDE (Dynamic Colors)
   =================================== */
[class*="theme-"] {
    --primary-color: <?php echo esc_attr($primary_color); ?> !important;
    --primary-color2: <?php echo esc_attr($primary_dark); ?> !important;
    --primary-color3: <?php echo esc_attr($primary_light); ?> !important;
    --secondary-color: <?php echo esc_attr($secondary_color); ?> !important;
    --secondary-color2: <?php echo esc_attr($secondary_light); ?> !important;
    --link-color: <?php echo esc_attr($primary_color); ?> !important;
    --primary-gradient: linear-gradient(45deg, <?php echo esc_attr($primary_color); ?>, <?php echo esc_attr($secondary_color); ?>) !important;
}

/* ===================================
   BUTTONS - Primary & Hover Colors
   =================================== */
.btn-primary,
.btn.btn-primary {
    background: var(--primary-gradient) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
}

.btn-primary:hover,
.btn.btn-primary:hover {
    background: var(--primary-gradient-hover) !important;
    border-color: var(--secondary-color) !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px var(--primary-rgba-30) !important;
}

.btn-success {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-color-dark)) !important;
    border-color: var(--primary-color) !important;
}

.btn-success:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--secondary-color-dark)) !important;
    border-color: var(--secondary-color) !important;
}

/* ===================================
   LINKS
   =================================== */
a {
    color: var(--primary-color) !important;
}

a:hover {
    color: var(--secondary-color) !important;
}

/* ===================================
   NAVBAR
   =================================== */
.navbar {
    background: var(--primary-gradient) !important;
}

.navbar .navbar-brand a {
    color: #fff !important;
}

/* ===================================
   SIDEBAR
   =================================== */
.sidebar .user-account {
    background: var(--primary-gradient) !important;
}

#main-menu li.active > a,
#main-menu li > a:hover {
    color: #fff !important;
    background: var(--primary-rgba-10) !important;
}

#main-menu li.active > a::before,
#main-menu li > a:hover::before {
    background: var(--primary-gradient) !important;
}

/* ===================================
   CARDS & STATISTICS
   =================================== */
/* .card.number-chart {
    background: var(--primary-gradient) !important;
}

.card.number-chart:hover {
    background: var(--primary-gradient-hover) !important;
    transform: translateY(-5px);
}

.card.number-chart:nth-child(1) {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
}

.card.number-chart:nth-child(2) {
    background: linear-gradient(135deg, var(--primary-color-light) 0%, var(--primary-color) 100%) !important;
}

.card.number-chart:nth-child(3) {
    background: linear-gradient(135deg, var(--secondary-color-light) 0%, var(--secondary-color) 100%) !important;
}

.card.number-chart:nth-child(4) {
    background: linear-gradient(135deg, var(--primary-color-dark) 0%, var(--secondary-color-dark) 100%) !important;
} */

/* ===================================
   BADGES
   =================================== */
.badge-primary {
    background: var(--primary-gradient) !important;
}

.badge-success {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light)) !important;
}

/* ===================================
   PROGRESS BARS
   =================================== */
.progress-bar {
    background: var(--primary-gradient) !important;
}

/* ===================================
   FORMS
   =================================== */
.form-control:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 0.2rem var(--primary-rgba-20) !important;
}

input[type="checkbox"]:checked,
input[type="radio"]:checked {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
}

/* ===================================
   TABS & NAVIGATION
   =================================== */
.nav-tabs .nav-link.active,
.list-group-item.active {
    background: var(--primary-gradient) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
}

.nav-tabs .nav-link:hover,
.list-group-item:hover {
    color: var(--secondary-color) !important;
    background: var(--primary-rgba-10) !important;
}

/* ===================================
   ALERTS (Settings Page)
   =================================== */
.settings-alert.alert-success {
    background: linear-gradient(135deg, var(--primary-rgba-10) 0%, var(--primary-rgba-20) 100%) !important;
    color: var(--primary-color) !important;
    border-left-color: var(--primary-color) !important;
}

/* ===================================
   TABLES
   =================================== */
.table thead th {
    background: var(--primary-rgba-10) !important;
    color: var(--primary-color-dark) !important;
}

.table tbody tr:hover {
    background: var(--primary-rgba-10) !important;
}

[data-theme="dark"] .table thead th {
    color: var(--primary-color-light) !important;
}

/* ===================================
   BREADCRUMB
   =================================== */
.breadcrumb-item.active {
    color: var(--primary-color) !important;
}

.breadcrumb-item a:hover {
    color: var(--secondary-color) !important;
}

/* ===================================
   DROPDOWN MENUS
   =================================== */
.dropdown-menu .dropdown-item:hover {
    background: var(--primary-rgba-10) !important;
    color: var(--primary-color) !important;
}

/* ===================================
   PAGINATION
   =================================== */
.page-item.active .page-link {
    background: var(--primary-gradient) !important;
    border-color: var(--primary-color) !important;
}

.page-link:hover {
    background: var(--secondary-rgba-10) !important;
    color: var(--secondary-color) !important;
}

/* ===================================
   CHARTS (Optional - if you use C3/D3)
   =================================== */
.c3-chart-arc path {
    stroke: var(--primary-color) !important;
}

/* ===================================
   LOADING SPINNER
   =================================== */
.page-loader-wrapper .loader {
    color: var(--primary-color) !important;
}

/* ===================================
   METISMENU (Sidebar Menu)
   =================================== */
.metismenu > li.active > a {
    background: var(--primary-gradient) !important;
    color: #fff !important;
}

/* ===================================
   ICON COLORS
   =================================== */
.text-primary,
.icon-primary {
    color: var(--primary-color) !important;
}

.text-secondary,
.icon-secondary {
    color: var(--secondary-color) !important;
}

/* ===================================
   HOVER EFFECTS
   =================================== */
.hover-primary:hover {
    background: var(--primary-rgba-10) !important;
    color: var(--primary-color) !important;
}

.hover-secondary:hover {
    background: var(--secondary-rgba-10) !important;
    color: var(--secondary-color) !important;
}

/* ===================================
   RIPPLE EFFECT (Optional Animation)
   =================================== */
@keyframes ripple-primary {
    0% {
        box-shadow: 0 0 0 0 var(--primary-rgba-30);
    }
    100% {
        box-shadow: 0 0 0 20px rgba(0, 0, 0, 0);
    }
}

.btn-primary:active,
.btn-success:active {
    animation: ripple-primary 0.6s ease-out;
}
</style>
<?php
