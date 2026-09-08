<?php
/**
 * Register Custom Post Types
 *
 * SC Events - Independent Event Management System
 * No external plugin dependencies
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Event Post Type (sc_event)
 * Fully independent - no Eventin dependency
 */
function sc_register_event_post_type() {
    $labels = array(
        'name'               => __('Events', 'sc_events'),
        'singular_name'      => __('Event', 'sc_events'),
        'add_new'            => __('Add New', 'sc_events'),
        'add_new_item'       => __('Add New Event', 'sc_events'),
        'edit_item'          => __('Edit Event', 'sc_events'),
        'new_item'           => __('New Event', 'sc_events'),
        'view_item'          => __('View Event', 'sc_events'),
        'search_items'       => __('Search Events', 'sc_events'),
        'not_found'          => __('No events found', 'sc_events'),
        'not_found_in_trash' => __('No events found in trash', 'sc_events'),
        'menu_name'          => __('Events', 'sc_events'),
    );

    $args = array(
        'labels'              => $labels,
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => false, // Managed via custom dashboard
        'show_in_nav_menus'   => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'has_archive'         => true,
        'query_var'           => true,
        'can_export'          => true,
        'rewrite'             => array('slug' => 'event', 'with_front' => false),
        'capability_type'     => 'post',
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
        'show_in_rest'        => true,
    );

    register_post_type('sc_event', $args);

    // Register Event Category Taxonomy
    $cat_labels = array(
        'name'              => __('Event Categories', 'sc_events'),
        'singular_name'     => __('Event Category', 'sc_events'),
        'search_items'      => __('Search Categories', 'sc_events'),
        'all_items'         => __('All Categories', 'sc_events'),
        'edit_item'         => __('Edit Category', 'sc_events'),
        'update_item'       => __('Update Category', 'sc_events'),
        'add_new_item'      => __('Add New Category', 'sc_events'),
        'new_item_name'     => __('New Category Name', 'sc_events'),
        'menu_name'         => __('Categories', 'sc_events'),
    );

    register_taxonomy('sc_event_category', 'sc_event', array(
        'hierarchical'      => true,
        'labels'            => $cat_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'event-category'),
        'show_in_rest'      => true,
    ));

    // Register Event Tags Taxonomy
    register_taxonomy('sc_event_tag', 'sc_event', array(
        'hierarchical'      => false,
        'labels'            => array(
            'name'          => __('Event Tags', 'sc_events'),
            'singular_name' => __('Event Tag', 'sc_events'),
        ),
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'event-tag'),
        'show_in_rest'      => true,
    ));
}
add_action('init', 'sc_register_event_post_type', 5);

/**
 * Flush rewrite rules when needed
 * Version-based to ensure rules are updated after changes
 */
function sc_flush_event_rewrite_rules() {
    $rules_version = 'v7'; // Increment this to force flush
    if (get_option('sc_event_rewrite_version') !== $rules_version) {
        flush_rewrite_rules();
        update_option('sc_event_rewrite_version', $rules_version);
    }
}
add_action('init', 'sc_flush_event_rewrite_rules', 99);

/**
 * Register custom query var for Custom Tables events
 */
function sc_register_event_query_vars($vars) {
    $vars[] = 'sc_event_slug';
    $vars[] = 'sc_custom_event';
    return $vars;
}
add_filter('query_vars', 'sc_register_event_query_vars');

/**
 * Intercept requests for Custom Tables events early
 * This runs before WordPress processes the query
 */
function sc_parse_custom_event_request($wp) {
    // Check if this is an event URL
    $request = trim($wp->request, '/');

    // Match event/slug pattern
    if (preg_match('#^event/([^/]+)/?$#', $request, $matches)) {
        $slug = $matches[1];

        // Skip feed, page, attachment patterns
        if (in_array($slug, array('feed', 'page', 'attachment'))) {
            return;
        }

        // First check if it exists in wp_posts
        $wp_event = get_page_by_path($slug, OBJECT, 'sc_event');
        if ($wp_event) {
            return; // Let WordPress handle it normally
        }

        // Check if it exists in Custom Tables
        if (class_exists('SC_Event')) {
            $event = SC_Event::get_by_slug($slug);
            if ($event) {
                // Set our custom query var
                $wp->query_vars['sc_custom_event'] = $slug;
                $wp->query_vars['sc_event_slug'] = $slug;
            }
        }
    }
}
add_action('parse_request', 'sc_parse_custom_event_request', 1);

/**
 * Handle Custom Tables event template loading via template_include filter
 * This runs AFTER WordPress determines the template, allowing us to override 404
 */
function sc_custom_event_template_include($template) {
    // Check URL directly. The query string has to come off first, or a link
    // carrying campaign parameters would never match and would 404.
    $request = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
    $base = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
    if ($base) {
        $request = preg_replace('#^' . preg_quote($base, '#') . '/?#', '', $request);
    }

    // Match event/slug pattern
    if (!preg_match('#^event/([^/]+)/?$#', $request, $matches)) {
        return $template;
    }

    $slug = $matches[1];

    // Skip WordPress internal patterns
    if (in_array($slug, array('feed', 'page', 'attachment', 'embed', 'trackback'))) {
        return $template;
    }

    // Check if exists in wp_posts (let WordPress handle it)
    $wp_event = get_page_by_path($slug, OBJECT, 'sc_event');
    if ($wp_event) {
        return $template;
    }

    // Check Custom Tables
    if (class_exists('SC_Event')) {
        $event = SC_Event::get_by_slug($slug);
        if ($event) {
            // Reset 404 status
            global $wp_query;
            $wp_query->is_404 = false;
            $wp_query->is_singular = true;
            $wp_query->is_single = true;
            status_header(200);

            // Set query var for template
            set_query_var('sc_event_slug', $slug);

            // Return our custom template
            return get_template_directory() . '/single-sc_event.php';
        }
    }

    return $template;
}
add_filter('template_include', 'sc_custom_event_template_include', 99);

/**
 * Register Attendee Post Type (sc_attendee)
 * Fully independent - no Eventin dependency
 * Used for QR Scanner compatibility
 */
function sc_register_attendee_post_type() {
    $labels = array(
        'name'               => __('Attendees', 'sc_events'),
        'singular_name'      => __('Attendee', 'sc_events'),
        'add_new'            => __('Add New', 'sc_events'),
        'add_new_item'       => __('Add New Attendee', 'sc_events'),
        'edit_item'          => __('Edit Attendee', 'sc_events'),
        'new_item'           => __('New Attendee', 'sc_events'),
        'view_item'          => __('View Attendee', 'sc_events'),
        'search_items'       => __('Search Attendees', 'sc_events'),
        'not_found'          => __('No attendees found', 'sc_events'),
        'not_found_in_trash' => __('No attendees found in trash', 'sc_events'),
        'menu_name'          => __('Attendees', 'sc_events'),
    );

    $args = array(
        'labels'              => $labels,
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // Managed via custom dashboard
        'show_in_nav_menus'   => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'query_var'           => true,
        'can_export'          => true,
        'capability_type'     => 'post',
        'supports'            => array('title'),
        'show_in_rest'        => true, // Required for Scanner REST API
    );

    register_post_type('sc_attendee', $args);
}
add_action('init', 'sc_register_attendee_post_type', 5);

/**
 * Register Speaker Post Type
 */
function sc_register_speaker_post_type() {
    $labels = array(
        'name'               => __('Speakers', 'sc_events'),
        'singular_name'      => __('Speaker', 'sc_events'),
        'add_new'            => __('Add New', 'sc_events'),
        'add_new_item'       => __('Add New Speaker', 'sc_events'),
        'edit_item'          => __('Edit Speaker', 'sc_events'),
        'new_item'           => __('New Speaker', 'sc_events'),
        'view_item'          => __('View Speaker', 'sc_events'),
        'search_items'       => __('Search Speakers', 'sc_events'),
        'not_found'          => __('No speakers found', 'sc_events'),
        'not_found_in_trash' => __('No speakers found in trash', 'sc_events'),
        'parent_item_colon'  => __('Parent Speaker:', 'sc_events'),
        'menu_name'          => __('Speakers', 'sc_events'),
    );

    $args = array(
        'labels'              => $labels,
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => false, // Hide from admin menu (managed via dashboard)
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'publicly_queryable'  => true,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'query_var'           => true,
        'can_export'          => true,
        'rewrite'             => array('slug' => 'speaker'),
        'capability_type'     => 'post',
        'supports'            => array('title', 'editor', 'thumbnail')
    );

    register_post_type('sc_speaker', $args);
}
add_action('init', 'sc_register_speaker_post_type');

/**
 * Register Organizer Post Type
 */
function sc_register_organizer_post_type() {
    $labels = array(
        'name'               => __('Organizers', 'sc_events'),
        'singular_name'      => __('Organizer', 'sc_events'),
        'add_new'            => __('Add New', 'sc_events'),
        'add_new_item'       => __('Add New Organizer', 'sc_events'),
        'edit_item'          => __('Edit Organizer', 'sc_events'),
        'new_item'           => __('New Organizer', 'sc_events'),
        'view_item'          => __('View Organizer', 'sc_events'),
        'search_items'       => __('Search Organizers', 'sc_events'),
        'not_found'          => __('No organizers found', 'sc_events'),
        'not_found_in_trash' => __('No organizers found in trash', 'sc_events'),
        'parent_item_colon'  => __('Parent Organizer:', 'sc_events'),
        'menu_name'          => __('Organizers', 'sc_events'),
    );

    $args = array(
        'labels'              => $labels,
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => false, // Hide from admin menu (managed via dashboard)
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'publicly_queryable'  => true,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'query_var'           => true,
        'can_export'          => true,
        'rewrite'             => array('slug' => 'organizer'),
        'capability_type'     => 'post',
        'supports'            => array('title', 'editor', 'thumbnail')
    );

    register_post_type('sc_organizer', $args);
}
add_action('init', 'sc_register_organizer_post_type');

/**
 * Register Contact Messages Post Type
 */
function sc_register_contact_post_type() {
    $labels = array(
        'name'               => __('Contact Messages', 'sc_events'),
        'singular_name'      => __('Contact Message', 'sc_events'),
        'add_new'            => __('Add New', 'sc_events'),
        'add_new_item'       => __('Add New Message', 'sc_events'),
        'edit_item'          => __('Edit Message', 'sc_events'),
        'new_item'           => __('New Message', 'sc_events'),
        'view_item'          => __('View Message', 'sc_events'),
        'search_items'       => __('Search Messages', 'sc_events'),
        'not_found'          => __('No messages found', 'sc_events'),
        'not_found_in_trash' => __('No messages found in trash', 'sc_events'),
        'menu_name'          => __('Contact Messages', 'sc_events'),
    );

    $args = array(
        'labels'              => $labels,
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => false,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'query_var'           => false,
        'can_export'          => true,
        'capability_type'     => 'post',
        'supports'            => array('title', 'editor')
    );

    register_post_type('sc_contact', $args);
}
add_action('init', 'sc_register_contact_post_type');
