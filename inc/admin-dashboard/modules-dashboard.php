<?php
/**
 * Module manager — turn feature modules on or off (template-parts/dashboard/module-manager.php).
 *
 * Dependencies come from each module's own $dependencies and are enforced here, not only in
 * the browser. Events, tickets and attendees stay on: registration cannot work without them.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Modules the site cannot run without. */
function sc_locked_modules() {
    return array('events', 'tickets', 'attendees');
}

/**
 * Every registered module with its state, what it needs and what needs it.
 */
function sc_module_catalog() {
    if (!function_exists('sc_modules')) {
        return array();
    }
    $status = sc_modules()->get_all_modules_status();
    $pages = function_exists('sc_get_page_module_requirements') ? sc_get_page_module_requirements() : array();
    $catalog = array();
    foreach ($status as $id => $m) {
        // A module that is off is not loaded, so read its declared dependencies from the file.
        if (!isset($m['dependencies'])) {
            $file = get_template_directory() . '/modules/' . $id . '/module.php';
            if (is_readable($file) && preg_match('/public\s+\$dependencies\s*=\s*array\(([^)]*)\)/', (string) file_get_contents($file), $match)) {
                preg_match_all("/'([a-z0-9_-]+)'/", $match[1], $deps);
                $m['dependencies'] = $deps[1];
            }
        }
        $catalog[$id] = array(
            'id'       => $id,
            'enabled'  => (bool) $m['is_enabled'],
            'locked'   => in_array($id, sc_locked_modules(), true),
            // Only modules that exist count; a dependency on something never built (e.g. venues) is ignored.
            'needs'    => array_values(array_filter((array) ($m['dependencies'] ?? array()), function ($dep) use ($status) { return isset($status[$dep]); })),
            'used_by'  => array(),
            'pages'    => count(array_keys($pages, $id, true)),
        );
    }
    foreach ($catalog as $id => $m) {
        foreach ($m['needs'] as $dep) {
            $catalog[$dep]['used_by'][] = $id;
        }
    }
    return $catalog;
}

add_action('wp_ajax_sc_module_toggle', 'sc_module_toggle');
function sc_module_toggle() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Only site administrators can turn modules on or off.', 'sc_events')), 403);
    }

    $id = isset($_POST['module']) ? sanitize_key(wp_unslash($_POST['module'])) : '';
    $enable = !empty($_POST['enable']);
    $catalog = sc_module_catalog();
    if (!isset($catalog[$id])) {
        wp_send_json_error(array('message' => __('Unknown module.', 'sc_events')));
    }
    $m = $catalog[$id];
    $names = function ($ids) {
        return implode(', ', array_map(function ($x) { return ucfirst($x); }, $ids));
    };

    if (!$enable) {
        if ($m['locked']) {
            wp_send_json_error(array('message' => __('This module is always on: registration and check-in depend on it.', 'sc_events')));
        }
        $blocking = array_values(array_filter($m['used_by'], function ($dep) use ($catalog) { return $catalog[$dep]['enabled']; }));
        if ($blocking) {
            /* translators: %s: module names */
            wp_send_json_error(array('message' => sprintf(__('Turn off %s first — they need this module.', 'sc_events'), $names($blocking)), 'blocking' => $blocking));
        }
    } else {
        $missing = array_values(array_filter($m['needs'], function ($dep) use ($catalog) { return !$catalog[$dep]['enabled']; }));
        if ($missing) {
            /* translators: %s: module names */
            wp_send_json_error(array('message' => sprintf(__('Turn on %s first — this module needs them.', 'sc_events'), $names($missing)), 'missing' => $missing));
        }
    }

    $ok = $enable ? sc_enable_module($id) : sc_disable_module($id);
    if (!$ok) {
        wp_send_json_error(array('message' => __('The module could not be updated.', 'sc_events')));
    }
    wp_send_json_success(array(
        'message' => $enable ? __('Module turned on.', 'sc_events') : __('Module turned off. Its data is kept.', 'sc_events'),
        'enabled' => $enable,
    ));
}
