<?php
/**
 * Coupon Categories AJAX Handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('coupons')) {
    return;
}

/**
 * Internal: verify nonce + permission
 */
function sc_coupon_cat_verify_request() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

/**
 * Get all coupon categories (for dropdowns)
 */
add_action('wp_ajax_sc_get_coupon_categories', 'sc_get_coupon_categories');
function sc_get_coupon_categories() {
    sc_coupon_cat_verify_request();

    $cats = SC_Coupon_Category::get_all(array('with_counts' => true));

    wp_send_json_success(array('categories' => $cats));
}

/**
 * Get single coupon category by ID
 */
add_action('wp_ajax_sc_get_coupon_category', 'sc_get_coupon_category');
function sc_get_coupon_category() {
    sc_coupon_cat_verify_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(array('message' => __('Invalid category ID.', 'sc_events')));
    }

    $cat = SC_Coupon_Category::get($id);
    if (!$cat) {
        wp_send_json_error(array('message' => __('Category not found.', 'sc_events')));
    }

    wp_send_json_success(array('category' => $cat));
}

/**
 * Get paginated coupon categories (with search) for management page
 */
add_action('wp_ajax_sc_get_coupon_categories_paginated', 'sc_get_coupon_categories_paginated');
function sc_get_coupon_categories_paginated() {
    sc_coupon_cat_verify_request();

    $page     = max(1, intval($_POST['page'] ?? 1));
    $per_page = min(200, max(10, intval($_POST['per_page'] ?? 50)));
    $search   = sanitize_text_field($_POST['search'] ?? '');
    $offset   = ($page - 1) * $per_page;

    $args = array(
        'search'      => $search,
        'limit'       => $per_page,
        'offset'      => $offset,
        'with_counts' => true,
    );

    $categories = SC_Coupon_Category::get_all($args);
    $total      = SC_Coupon_Category::count(array('search' => $search));
    $pages      = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

    wp_send_json_success(array(
        'categories'   => $categories,
        'total'        => $total,
        'pages'        => $pages,
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}

/**
 * Save (create or update) coupon category
 */
add_action('wp_ajax_sc_save_coupon_category', 'sc_save_coupon_category');
function sc_save_coupon_category() {
    sc_coupon_cat_verify_request();

    $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $data = array(
        'name'        => isset($_POST['name']) ? $_POST['name'] : '',
        'slug'        => isset($_POST['slug']) ? $_POST['slug'] : '',
        'description' => isset($_POST['description']) ? $_POST['description'] : '',
        'color'       => isset($_POST['color']) ? $_POST['color'] : '#7c1314',
        'sort_order'  => isset($_POST['sort_order']) ? $_POST['sort_order'] : 0,
    );

    if (empty($data['name'])) {
        wp_send_json_error(array('message' => __('Category name is required.', 'sc_events')));
    }

    if ($id) {
        $ok = SC_Coupon_Category::update($id, $data);
        if (!$ok) {
            wp_send_json_error(array('message' => __('Failed to update category.', 'sc_events')));
        }
        wp_send_json_success(array(
            'message' => __('Category updated successfully.', 'sc_events'),
            'id'      => $id,
        ));
    } else {
        $new_id = SC_Coupon_Category::create($data);
        if (!$new_id) {
            wp_send_json_error(array('message' => __('Failed to create category.', 'sc_events')));
        }
        wp_send_json_success(array(
            'message' => __('Category created successfully.', 'sc_events'),
            'id'      => $new_id,
        ));
    }
}

/**
 * Delete coupon category (reassigns its coupons to General first)
 */
add_action('wp_ajax_sc_delete_coupon_category', 'sc_delete_coupon_category');
function sc_delete_coupon_category() {
    sc_coupon_cat_verify_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(array('message' => __('Invalid category ID.', 'sc_events')));
    }

    // Also reassign postmeta-stored category_id (for legacy wp_posts coupons)
    global $wpdb;
    $wpdb->update(
        $wpdb->postmeta,
        array('meta_value' => SC_Coupon_Category::DEFAULT_ID),
        array('meta_key' => 'category_id', 'meta_value' => $id),
        array('%s'),
        array('%s', '%s')
    );

    $result = SC_Coupon_Category::delete($id);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete category.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message' => __('Category deleted. Its coupons were moved to General.', 'sc_events'),
    ));
}
