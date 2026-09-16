<?php

// First: MySQL session time zone = site time zone, before any theme query runs.
require_once get_template_directory() . '/inc/sc-db-timezone.php';
/**
 * sc_events functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package sc_events
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '2.0.0' );
}

if ( ! defined( 'SC_ASSET_VERSION' ) ) {
	/*
	 * Single cache-busting version for every asset. The header and the footer
	 * both read this, so their script tags cannot drift apart.
	 *
	 * It is derived from the files rather than typed, because a hand-maintained
	 * number only works if someone remembers to raise it — and twice during the
	 * redesign nobody did, leaving visitors with new markup and the previous
	 * stylesheet. Suffixing the release with the newest asset timestamp means a
	 * deploy busts the cache whether or not anyone thought about it.
	 */
	$sc_asset_release = '4.0.0';
	$sc_asset_stamp   = 0;

	$sc_asset_dirs = array(
		'/assets/frontend/css/wisdom',
		'/assets/frontend/js',
		'/assets/admin-dashboard/css',
		'/assets/admin-dashboard/js',
		'/assets/admin-dashboard/bundles',
	);

	foreach ( $sc_asset_dirs as $sc_asset_dir ) {
		foreach ( (array) glob( get_template_directory() . $sc_asset_dir . '/*.{css,js}', GLOB_BRACE ) as $sc_asset_file ) {
			$sc_asset_stamp = max( $sc_asset_stamp, (int) filemtime( $sc_asset_file ) );
		}
	}

	define( 'SC_ASSET_VERSION', $sc_asset_stamp ? $sc_asset_release . '.' . $sc_asset_stamp : $sc_asset_release );

	unset( $sc_asset_release, $sc_asset_stamp, $sc_asset_dirs, $sc_asset_dir, $sc_asset_file );
}

/**
 * Load Modular Architecture System
 *
 * The new modular system allows each feature to be a self-contained module
 * that can be enabled/disabled independently.
 *
 * @since 2.0.0
 */
if (file_exists(get_template_directory() . '/modules/core/init.php')) {
	require get_template_directory() . '/modules/core/init.php';
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function sc_events_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on sc_events, use a find and replace
		* to change 'sc_events' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'sc_events', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support( 'title-tag' );

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__( 'Primary', 'sc_events' ),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'sc_events_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action( 'after_setup_theme', 'sc_events_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function sc_events_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'sc_events_content_width', 640 );
}
add_action( 'after_setup_theme', 'sc_events_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function sc_events_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'sc_events' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'sc_events' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'sc_events_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function sc_events_scripts() {
	wp_enqueue_style( 'sc_events-style', get_stylesheet_uri(), array(), _S_VERSION );
	wp_style_add_data( 'sc_events-style', 'rtl', 'replace' );

	wp_enqueue_script( 'sc_events-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'sc_events_scripts' );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * TGM Plugin Activation
 */
require get_template_directory() . '/inc/tgm/tgm-config.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Utility functions for common operations.
 */
require get_template_directory() . '/inc/utilities.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}

/**
 * Custom Database Layer (Load before other includes)
 * This provides direct database access for better performance
 */
require get_template_directory() . '/inc/database/loader.php';

/**
 * Localization & RTL Support
 * Provides translation functions, Hijri calendar, and RTL utilities
 */
require get_template_directory() . '/inc/localization.php';

/**
 * Event Manager Dashboard
 */
require get_template_directory() . '/inc/custom-post-types.php';
require get_template_directory() . '/inc/admin-dashboard/module-helpers.php'; // Module status helper functions - MUST load before dashboard-init
require get_template_directory() . '/inc/admin-dashboard/security-utilities.php'; // Load early - provides sc_verify_ajax_request()
require get_template_directory() . '/inc/class-sc-asset-manager.php'; // Asset minification & optimization
require get_template_directory() . '/inc/admin-dashboard/dashboard-init.php';

// Modular AJAX Handlers (Organized by section)
require get_template_directory() . '/inc/admin-dashboard/auth-ajax-handlers.php';
// SMTP via theme code DISABLED — using WP Mail SMTP plugin instead.
// To re-enable: uncomment the two lines below and disable WP Mail SMTP plugin.
// require get_template_directory() . '/inc/admin-dashboard/smtp-config.php';
// require get_template_directory() . '/inc/admin-dashboard/smtp-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/settings-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/pages-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/coupons-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/coupon-categories-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/workshops-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/customers-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/events-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/speakers-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/attendees-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/support-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/attendance-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/scanner-offline.php';
require get_template_directory() . '/inc/admin-dashboard/certificates-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/company-attendees-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/sessions-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/scanners-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/badges-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/booths-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/chat-dashboard.php';
require get_template_directory() . '/inc/whatsapp/wabot.php';
require get_template_directory() . '/inc/whatsapp/wabot-campaigns.php';
require get_template_directory() . '/inc/whatsapp/wabot-notify.php';
require get_template_directory() . '/inc/auth/sc-otp.php';
require get_template_directory() . '/inc/auth/sc-otp-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/whatsapp-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/whatsapp-campaigns-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/whatsapp-notify-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/modules-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/settings-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/reports-dashboard.php';
require get_template_directory() . '/inc/admin-dashboard/sponsors-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/partners-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/halls-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/schedules-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/notifications-ajax-handlers.php';
require get_template_directory() . '/inc/admin-dashboard/reset-role.php';
require get_template_directory() . '/inc/admin-dashboard/permissions.php';
require get_template_directory() . '/inc/admin-dashboard/performance-optimizations.php';

/**
 * Public Frontend AJAX Handlers
 */
require get_template_directory() . '/inc/public-frontend/public-ajax-handlers.php';
require get_template_directory() . '/inc/public-frontend/logout.php';
require get_template_directory() . '/inc/public-frontend/visitor.php';

/**
 * Chat Widget Frontend Integration
 */
require get_template_directory() . '/inc/public-frontend/chat-widget-init.php';

/**
 * Public Certificate Handlers (Shortcodes & Download)
 */
require get_template_directory() . '/inc/public-frontend/certificate-public.php';
require get_template_directory() . '/inc/public-frontend/company-ticket.php';

/**
 * Speaker URLs — speakers live in the custom table, so WordPress cannot route
 * to them without help.
 */
require get_template_directory() . '/inc/public-frontend/view-helpers.php';
require get_template_directory() . '/inc/public-frontend/speaker-routing.php';

/**
 * Scanner REST API
 */
require get_template_directory() . '/inc/rest-api/scanner-api.php';

/**
 * Error Logging System
 * Database-backed logging with admin viewer
 */
require get_template_directory() . '/inc/class-sc-error-logger.php';

/**
 * Activity Logging System
 * Tracks user actions and system events for auditing
 */
require get_template_directory() . '/inc/class-sc-activity-logger.php';

/**
 * Two-Factor Authentication
 * TOTP, Email verification, and backup codes
 */
require get_template_directory() . '/inc/class-sc-two-factor-auth.php';

/**
 * Payment Gateways System
 */
require get_template_directory() . '/inc/payment-gateways/payment-gateways-init.php';

/**
 * Cron Jobs
 */
require get_template_directory() . '/inc/cron-jobs.php';

/**
 * Custom Template Routing for SC Events
 * Routes sc_event post type to appropriate templates
 */
add_filter('template_include', 'sc_custom_event_templates', 100);
function sc_custom_event_templates($template) {
    global $post;

    if (!$post) {
        return $template;
    }

    // Single event template
    if ($post->post_type === 'sc_event' && is_singular('sc_event')) {
        $theme_template = get_template_directory() . '/single-sc_event.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }

    // Event archive template
    if (is_post_type_archive('sc_event')) {
        $theme_template = get_template_directory() . '/archive-sc_event.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }

    // Event category taxonomy template
    if (is_tax('sc_event_category')) {
        $theme_template = get_template_directory() . '/taxonomy-sc_event_category.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }

    // Single workshop template
    if ($post->post_type === 'sc_workshop' && is_singular('sc_workshop')) {
        $theme_template = get_template_directory() . '/single-sc_workshop.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }

    // Workshop archive template
    if (is_post_type_archive('sc_workshop')) {
        $theme_template = get_template_directory() . '/archive-sc_workshop.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }

    return $template;
}

/**
 * Register Custom Post Type for Workshops
 */
function sc_register_workshop_post_type() {
    $labels = array(
        'name'               => _x('Workshops', 'post type general name', 'sc_events'),
        'singular_name'      => _x('Workshop', 'post type singular name', 'sc_events'),
        'menu_name'          => _x('Workshops', 'admin menu', 'sc_events'),
        'name_admin_bar'     => _x('Workshop', 'add new on admin bar', 'sc_events'),
        'all_items'          => __('All Workshops', 'sc_events'),
        'search_items'       => __('Search Workshops', 'sc_events'),
        'not_found'          => __('No workshops found.', 'sc_events'),
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'workshop', 'with_front' => false),
        'capability_type'    => 'post',
        'has_archive'        => 'workshops',
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
    );
    register_post_type('sc_workshop', $args);
}
add_action('init', 'sc_register_workshop_post_type');

/**
 * Resolve workshop slug → SC_Workshop without requiring a wp_post row
 * (workshops are in custom table only). Hook into pre_get_posts to allow lookup.
 */
add_action('init', function() {
    add_rewrite_rule('^workshop/([^/]+)/?$', 'index.php?sc_workshop=$matches[1]', 'top');
    add_rewrite_rule('^workshops/?$', 'index.php?post_type=sc_workshop', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'sc_workshop';
    return $vars;
});

// When a workshop slug is requested but no wp_post exists, still serve our template
add_action('template_redirect', function() {
    global $wp_query;

    // Detect workshop request (either via query var, or by URL pattern as fallback)
    $slug = get_query_var('sc_workshop');
    if (empty($slug) && !empty($_SERVER['REQUEST_URI'])) {
        if (preg_match('#/workshop/([^/?]+)/?#', $_SERVER['REQUEST_URI'], $m)) {
            $slug = sanitize_title($m[1]);
        }
    }

    if (!empty($slug) && class_exists('SC_Workshop')) {
        $w = SC_Workshop::get_by_slug($slug);
        if ($w) {
            // Reset 404 flag in case WP set it because there's no wp_post
            status_header(200);
            if (isset($wp_query)) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
            }
            include get_template_directory() . '/single-sc_workshop.php';
            exit;
        }
    }

    // Workshops archive — handle /workshops/ even if rewrite rules not flushed yet
    if (!empty($_SERVER['REQUEST_URI']) && preg_match('#^/[^/]*/?workshops/?($|\?)#', $_SERVER['REQUEST_URI']) !== false) {
        // template_include filter will handle this if post_type_archive is detected
    }
}, 1);

// Force flush rewrite rules when version changes (theme load hook)
add_action('init', function() {
    if (get_option('sc_workshops_rules_version') !== '1') {
        flush_rewrite_rules(false);
        update_option('sc_workshops_rules_version', '1');
    }
}, 999);

/**
 * Register Custom Post Type for Coupons
 */
function sc_register_coupon_post_type() {
	$labels = array(
		'name'               => _x( 'Coupons', 'post type general name', 'sc_events' ),
		'singular_name'      => _x( 'Coupon', 'post type singular name', 'sc_events' ),
		'menu_name'          => _x( 'Coupons', 'admin menu', 'sc_events' ),
		'name_admin_bar'     => _x( 'Coupon', 'add new on admin bar', 'sc_events' ),
		'add_new'            => _x( 'Add New', 'coupon', 'sc_events' ),
		'add_new_item'       => __( 'Add New Coupon', 'sc_events' ),
		'new_item'           => __( 'New Coupon', 'sc_events' ),
		'edit_item'          => __( 'Edit Coupon', 'sc_events' ),
		'view_item'          => __( 'View Coupon', 'sc_events' ),
		'all_items'          => __( 'All Coupons', 'sc_events' ),
		'search_items'       => __( 'Search Coupons', 'sc_events' ),
		'not_found'          => __( 'No coupons found.', 'sc_events' ),
		'not_found_in_trash' => __( 'No coupons found in Trash.', 'sc_events' )
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => false,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'coupon' ),
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title' )
	);

	register_post_type( 'sc_coupon', $args );
}
add_action( 'init', 'sc_register_coupon_post_type' );

/**
 * Enqueue template-specific styles
 */
function sc_enqueue_template_styles() {
	// Archive pages
	if (is_archive() && !is_post_type_archive('sc_event')) {
		wp_enqueue_style(
			'sc-archive-style',
			get_template_directory_uri() . '/assets/css/archive.css',
			array(),
			'1.0.0'
		);
	}

	// Single post pages
	if (is_single() && !is_singular('sc_event')) {
		wp_enqueue_style(
			'sc-single-style',
			get_template_directory_uri() . '/assets/css/single.css',
			array(),
			'1.0.0'
		);
	}

	// Page template
	if (is_page() && !is_page(array('login', 'register', 'my-account', 'contact', 'forgot-password', 'checkout', 'payment-success', 'payment-failed', 'ticket-view', 'events'))) {
		wp_enqueue_style(
			'sc-page-style',
			get_template_directory_uri() . '/assets/css/page.css',
			array(),
			'1.0.0'
		);
	}

	// Blog index page
	if (is_home() || (is_front_page() && is_home())) {
		wp_enqueue_style(
			'sc-index-style',
			get_template_directory_uri() . '/assets/css/index.css',
			array(),
			'1.0.0'
		);
	}
}
add_action('wp_enqueue_scripts', 'sc_enqueue_template_styles');

/**
 * Theme Activation: Create Privacy Policy Page Automatically
 */
function sc_events_create_privacy_policy_page() {
	// Check if Privacy Policy page already exists
	$privacy_page = get_page_by_path('privacy-policy');

	if (!$privacy_page) {
		// Privacy Policy content
		$privacy_content = '<h2>Privacy Policy</h2>

<p>Your privacy is important to us. This privacy policy explains what personal data we collect and how we use it.</p>

<h3>Information We Collect</h3>
<p>When you use our event booking system, we may collect the following information:</p>
<ul>
	<li>Name and contact information (email address, phone number)</li>
	<li>Event registration details</li>
	<li>Payment information (processed securely through our payment gateway)</li>
	<li>Any additional information you provide during registration</li>
</ul>

<h3>How We Use Your Information</h3>
<p>We use your personal information to:</p>
<ul>
	<li>Process your event registrations and ticket purchases</li>
	<li>Send you event confirmations and updates</li>
	<li>Communicate with you about events and services</li>
	<li>Improve our services and user experience</li>
</ul>

<h3>Data Security</h3>
<p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction.</p>

<h3>Your Rights</h3>
<p>You have the right to:</p>
<ul>
	<li>Access your personal data</li>
	<li>Request correction of your data</li>
	<li>Request deletion of your data</li>
	<li>Object to processing of your data</li>
</ul>

<h3>Contact Us</h3>
<p>If you have any questions about this privacy policy, please contact us.</p>';

		// Create the page
		$privacy_page_data = array(
			'post_title'    => 'Privacy Policy',
			'post_content'  => $privacy_content,
			'post_status'   => 'publish',
			'post_type'     => 'page',
			'post_author'   => 1,
			'post_name'     => 'privacy-policy',
			'comment_status' => 'closed',
			'ping_status'   => 'closed',
		);

		$page_id = wp_insert_post($privacy_page_data);

		if ($page_id && !is_wp_error($page_id)) {
			// Set as WordPress privacy policy page
			update_option('wp_page_for_privacy_policy', $page_id);

			// Log success
			error_log('SC Events: Privacy Policy page created successfully (ID: ' . $page_id . ')');
		}
	}
}
add_action('after_switch_theme', 'sc_events_create_privacy_policy_page');

/**
 * Create Forgot Password page programmatically
 */
function sc_events_create_forgot_password_page() {
	// Check if page already exists
	$existing_page = get_page_by_path('forgot-password');

	if ($existing_page) {
		return; // Page already exists
	}

	// Create page
	$page_data = array(
		'post_title'     => 'Forgot Password',
		'post_content'   => '',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_author'    => 1,
		'post_name'      => 'forgot-password',
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'page_template'  => 'page-forgot-password.php'
	);

	$page_id = wp_insert_post($page_data);

	if ($page_id && !is_wp_error($page_id)) {
		// Set page template
		update_post_meta($page_id, '_wp_page_template', 'page-forgot-password.php');
		error_log('SC Events: Forgot Password page created successfully (ID: ' . $page_id . ')');
	}
}
add_action('after_switch_theme', 'sc_events_create_forgot_password_page');
add_action('init', 'sc_events_create_forgot_password_page'); // Also run on init to ensure page exists

/**
 * Auto-create Ticket View page
 */
function sc_events_create_ticket_view_page() {
	// Check if page already exists
	$existing_page = get_page_by_path('ticket-view');

	if ($existing_page) {
		return; // Page already exists
	}

	// Create page
	$page_data = array(
		'post_title'     => 'Ticket View',
		'post_content'   => '',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_author'    => 1,
		'post_name'      => 'ticket-view',
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'page_template'  => 'page-ticket-view.php'
	);

	$page_id = wp_insert_post($page_data);

	if ($page_id && !is_wp_error($page_id)) {
		// Set page template
		update_post_meta($page_id, '_wp_page_template', 'page-ticket-view.php');
		error_log('SC Events: Ticket View page created successfully (ID: ' . $page_id . ')');
	}
}
add_action('after_switch_theme', 'sc_events_create_ticket_view_page');
add_action('init', 'sc_events_create_ticket_view_page');

/**
 * Disable REST API and block-editor assets on public pages when not logged in
 * This prevents "You are not currently logged in" console errors
 */
function sc_events_disable_rest_api_on_auth_pages() {
	// Skip if in admin dashboard
	if (is_admin() || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/event-manager-dashboard/') !== false)) {
		return;
	}

	// If user is not logged in, dequeue REST API scripts on all frontend pages
	if (!is_user_logged_in()) {
		// Dequeue REST API scripts
		wp_dequeue_script('wp-api-fetch');
		wp_deregister_script('wp-api-fetch');

		// Dequeue block editor assets
		wp_dequeue_script('wp-block-library');
		wp_dequeue_style('wp-block-library');

		// Dequeue other WP scripts that might trigger REST API calls
		wp_dequeue_script('wp-api-request');
		wp_dequeue_script('wp-api');
		wp_dequeue_script('wp-data');
		wp_dequeue_script('wp-preferences');
		wp_dequeue_script('wp-preferences-persistence');

		// Remove REST API link from head
		remove_action('wp_head', 'rest_output_link_wp_head', 10);
		remove_action('template_redirect', 'rest_output_link_header', 11);
	}
}
add_action('wp_enqueue_scripts', 'sc_events_disable_rest_api_on_auth_pages', 100);

/**
 * Add script to suppress REST API errors in console on all public pages
 */
function sc_events_suppress_rest_api_errors() {
	// Skip if in admin dashboard
	if (is_admin() || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/event-manager-dashboard/') !== false && is_user_logged_in())) {
		return;
	}

	// Apply suppression script for non-logged-in users on all frontend pages
	if (!is_user_logged_in()) {
		?>
		<script>
		// Suppress REST API authentication errors
		window.addEventListener('unhandledrejection', function(event) {
			if (event.reason && (event.reason.code === 'rest_not_logged_in' ||
			                     event.reason.code === 'rest_cookie_invalid_nonce' ||
			                     (event.reason.message && (event.reason.message.includes('not currently logged in') ||
			                                                event.reason.message.includes('You are not authenticated'))))) {
				event.preventDefault();
			}
		});

		// Override console.error to suppress REST API errors
		(function() {
			const originalError = console.error;
			console.error = function() {
				const message = arguments[0];
				if (typeof message === 'string' &&
				    (message.includes('rest_not_logged_in') ||
				     message.includes('not currently logged in') ||
				     message.includes('not authenticated') ||
				     message.includes('401') && message.includes('wp-json'))) {
					return;
				}
				originalError.apply(console, arguments);
			};
		})();

		// Override console.warn to suppress REST API and Google Maps warnings
		(function() {
			const originalWarn = console.warn;
			console.warn = function() {
				const message = arguments[0];
				if (typeof message === 'string' &&
				    (message.includes('rest_not_logged_in') ||
				     message.includes('not currently logged in') ||
				     message.includes('wp-json') && message.includes('401') ||
				     message.includes('Google Maps') ||
				     message.includes('loading-async') ||
				     message.includes('js-api-loader'))) {
					return;
				}
				originalWarn.apply(console, arguments);
			};
		})();
		</script>
		<?php
	}
}
add_action('wp_footer', 'sc_events_suppress_rest_api_errors', 999);

/**
 * Resized copies are saved as WebP.
 *
 * A speaker photo uploaded as GIF or PNG made a 768-pixel copy of 370–860 KB;
 * as WebP it is a fraction of that, and WebP keeps transparency, so logos stay
 * clear. The uploaded file and its "-scaled"/"-rotated" stand-in keep their
 * format: the certificate and badge PDFs read those, and TCPDF cannot read WebP.
 * Resized copies of a GIF were never animated, so nothing is lost there.
 */
add_filter('image_editor_output_format', function ($formats, $filename = null, $mime_type = null) {
    // WordPress asks about the upload itself (a file already on disk) before
    // deciding to convert it, and names the stand-ins it makes for it.
    if ($filename && (file_exists($filename) || preg_match('/-(?:scaled|rotated)\.[a-z0-9]+$/i', (string) $filename))) {
        return $formats;
    }
    static $webp = null;
    if ($webp === null) {
        $webp = wp_image_editor_supports(array('mime_type' => 'image/webp'));
    }
    if ($webp) {
        foreach (array('image/jpeg', 'image/png', 'image/gif') as $type) {
            $formats[$type] = 'image/webp';
        }
    }
    return $formats;
}, 10, 3);

add_filter('wp_editor_set_quality', function($quality, $mime_type) {
    if ($mime_type === 'image/png') {
        return 9; // Max PNG compression (lossless)
    }
    return $quality;
}, 10, 2);

/**
 * Performance: Add resource hints for external CDN domains
 */
add_filter('wp_resource_hints', function($urls, $relation_type) {
    if ($relation_type === 'preconnect') {
        $urls[] = array(
            'href' => 'https://cdn.jsdelivr.net',
            'crossorigin' => 'anonymous',
        );
        $urls[] = array(
            'href' => 'https://unpkg.com',
            'crossorigin' => 'anonymous',
        );
        $urls[] = array(
            'href' => 'https://fonts.googleapis.com',
            'crossorigin' => 'anonymous',
        );
        $urls[] = array(
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        );
    }
    return $urls;
}, 10, 2);

/**
 * Performance: Add lazy loading to all images and iframes
 */
add_filter('wp_lazy_loading_enabled', '__return_true');

/**
 * Performance: Add loading="lazy" to content images
 */
add_filter('wp_get_attachment_image_attributes', function($attr) {
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
});

/**
 * Performance: Cache platform settings to avoid repeated get_option calls
 */
function sc_get_platform_settings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = array(
        'name'        => get_option('sc_platform_name', get_bloginfo('name')),
        'description' => get_option('sc_platform_description', get_bloginfo('description')),
        'logo_id'     => get_option('sc_platform_logo'),
        'phone'       => get_option('sc_platform_phone', ''),
        'email'       => get_option('sc_platform_email', get_option('admin_email')),
        'whatsapp'    => get_option('sc_platform_whatsapp', ''),
        'address'     => get_option('sc_platform_address', ''),
        'website'     => get_option('sc_platform_website', home_url()),
        'facebook'    => get_option('sc_platform_facebook', ''),
        'twitter'     => get_option('sc_platform_twitter', ''),
        'instagram'   => get_option('sc_platform_instagram', ''),
        'linkedin'    => get_option('sc_platform_linkedin', ''),
        'primary_color'   => get_option('sc_primary_color', '#667eea'),
        'secondary_color' => get_option('sc_secondary_color', '#764ba2'),
        'default_theme'   => get_option('sc_default_theme', 'dark'),
    );

    $settings['logo_url'] = $settings['logo_id'] ? wp_get_attachment_image_url($settings['logo_id'], 'full') : '';

    return $settings;
}

/**
 * Performance: Cache event categories with transient
 */
function sc_get_cached_event_categories($number = 10) {
    $cache_key = 'sc_event_categories_' . $number;
    $categories = get_transient($cache_key);

    if ($categories === false) {
        $categories = get_terms(array(
            'taxonomy' => 'sc_event_category',
            'hide_empty' => false,
            'number' => $number,
        ));

        if (is_wp_error($categories)) {
            $categories = array();
        }

        set_transient($cache_key, $categories, 5 * MINUTE_IN_SECONDS);
    }

    return $categories;
}

/**
 * Clear category cache when terms are modified
 */
add_action('created_sc_event_category', function() { delete_transient('sc_event_categories_10'); });
add_action('edited_sc_event_category', function() { delete_transient('sc_event_categories_10'); });
add_action('delete_sc_event_category', function() { delete_transient('sc_event_categories_10'); });

