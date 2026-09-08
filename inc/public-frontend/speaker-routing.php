<?php
/**
 * Speaker URLs
 *
 * Speakers live in the custom table, not in wp_posts — all 89 of them have a
 * row and none has a post. WordPress therefore cannot route to them on its
 * own, so `speaker/{slug}` is intercepted the same way `event/{slug}` is.
 *
 * The listing at /speakers is an ordinary page carrying the archive template,
 * which is how /events and /workshops already work.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Claim speaker/{slug} when the slug matches a row in the custom table.
 */
function sc_parse_custom_speaker_request($wp) {
    $request = trim($wp->request, '/');

    if (!preg_match('#^speaker/([^/]+)/?$#', $request, $matches)) {
        return;
    }

    $slug = $matches[1];
    if (in_array($slug, array('feed', 'page', 'attachment'), true)) {
        return;
    }

    // A real post wins, so nothing breaks if speakers are ever migrated.
    if (get_page_by_path($slug, OBJECT, 'sc_speaker')) {
        return;
    }

    if (class_exists('SC_Speaker') && SC_Speaker::get_by_slug($slug)) {
        $wp->query_vars['sc_speaker_slug'] = $slug;
    }
}
add_action('parse_request', 'sc_parse_custom_speaker_request', 1);

/**
 * Serve the single-speaker template for a claimed URL, and clear the 404 that
 * WordPress set when it could not find a post.
 */
function sc_custom_speaker_template_include($template) {
    $slug = get_query_var('sc_speaker_slug');
    if (!$slug) {
        return $template;
    }

    $found = get_template_directory() . '/single-sc_speaker.php';
    if (!file_exists($found)) {
        return $template;
    }

    status_header(200);
    global $wp_query;
    $wp_query->is_404 = false;

    return $found;
}
add_filter('template_include', 'sc_custom_speaker_template_include', 99);

/**
 * Register the query var so get_query_var() can see it.
 */
function sc_register_speaker_query_vars($vars) {
    $vars[] = 'sc_speaker_slug';
    return $vars;
}
add_filter('query_vars', 'sc_register_speaker_query_vars');

/**
 * Permalink for a speaker row.
 *
 * @param object $speaker Row from the speakers table.
 * @return string
 */
function sc_speaker_permalink($speaker) {
    return home_url('/speaker/' . $speaker->slug);
}
