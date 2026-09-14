<?php
/**
 * Organizers — dashboard list, save and bulk actions.
 *
 * Organizers are the companies or bodies behind an event; events pick them in
 * the event form (sc_event_organizers). The older handlers listed only active
 * organizers and forced every save back to active, so hiding one was impossible.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_organizers_verify_request() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

add_action('wp_ajax_sc_get_organizers_paginated', 'sc_get_organizers_list');
function sc_get_organizers_list() {
    sc_organizers_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;

    $view = in_array($_POST['view'] ?? '', array('all', 'active', 'hidden', 'unlinked'), true) ? sanitize_key($_POST['view']) : 'all';
    $search = trim(sanitize_text_field(wp_unslash($_POST['search'] ?? '')));
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 50)));
    $orderby = ($_POST['orderby'] ?? '') === 'events' ? 'events' : (($_POST['orderby'] ?? '') === 'display_order' ? 'o.display_order' : 'o.name');
    $order = strtolower($_POST['order'] ?? '') === 'desc' ? 'DESC' : 'ASC';

    $events_sql = "(SELECT COUNT(DISTINCT eo.event_id) FROM {$p}sc_event_organizers eo JOIN {$p}sc_events e ON e.id = eo.event_id WHERE eo.organizer_id = o.id)";
    $where = '1=1';
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (o.name LIKE %s OR o.email LIKE %s OR o.website LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    $views = array('all' => '1=1', 'active' => 'o.is_active = 1', 'hidden' => 'o.is_active = 0', 'unlinked' => "$events_sql = 0");
    $prep = function ($sql, $vals) use ($wpdb) {
        return $vals ? $wpdb->prepare($sql, $vals) : $sql;
    };

    $total = (int) $wpdb->get_var($prep("SELECT COUNT(*) FROM {$p}sc_organizers o WHERE $where AND {$views[$view]}", $values));
    $order_sql = $orderby === 'events' ? "events $order, o.name ASC" : "$orderby $order, o.name ASC";
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT o.*, $events_sql AS events FROM {$p}sc_organizers o WHERE $where AND {$views[$view]} ORDER BY $order_sql LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    $latest = array();
    if ($rows) {
        $ids = implode(',', array_map('intval', wp_list_pluck($rows, 'id')));
        foreach ($wpdb->get_results("SELECT eo.organizer_id, e.id, e.title, e.start_date FROM {$p}sc_event_organizers eo JOIN {$p}sc_events e ON e.id = eo.event_id WHERE eo.organizer_id IN ($ids) ORDER BY e.start_date ASC") as $r) {
            $latest[(int) $r->organizer_id] = array('id' => (int) $r->id, 'title' => $r->title);
        }
    }

    $out = array();
    foreach ($rows as $o) {
        $out[] = array(
            'id'        => (int) $o->id,
            'name'      => $o->name,
            'email'     => (string) $o->email,
            'phone'     => (string) $o->phone,
            'website'   => (string) $o->website,
            'logo'      => $o->logo ? (wp_get_attachment_image_url((int) $o->logo, 'thumbnail') ?: '') : '',
            'is_active' => (bool) $o->is_active,
            'order'     => (int) $o->display_order,
            'events'    => (int) $o->events,
            'latest'    => $latest[(int) $o->id] ?? null,
        );
    }

    $response = array('rows' => $out, 'organizers' => $out, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        $c = $wpdb->get_row($prep("SELECT COUNT(*) AS all_rows, COALESCE(SUM(o.is_active = 1), 0) AS active, COALESCE(SUM($events_sql = 0), 0) AS unlinked FROM {$p}sc_organizers o WHERE $where", $values));
        $response['counts'] = array('all' => (int) $c->all_rows, 'active' => (int) $c->active, 'hidden' => (int) $c->all_rows - (int) $c->active, 'unlinked' => (int) $c->unlinked);
    }
    wp_send_json_success($response);
}

add_action('wp_ajax_sc_save_organizer', 'sc_save_organizer_form');
function sc_save_organizer_form() {
    sc_organizers_verify_request();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';
    $in = function ($key) {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    };

    $id = absint($in('organizer_id'));
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('Organizer not found.', 'sc_events')));
    }

    $name = sanitize_text_field($in('name'));
    $email = trim((string) $in('email'));
    $errors = array();
    if ($name === '') {
        $errors['name'] = __('Enter the organizer’s name.', 'sc_events');
    }
    if ($email !== '' && !is_email($email)) {
        $errors['email'] = __('Enter a valid email address.', 'sc_events');
    }
    $url_keys = array('website', 'facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'tiktok');
    foreach ($url_keys as $key) {
        $value = trim((string) $in($key));
        if ($value !== '' && !preg_match('#^https?://\S+\.\S+#i', $value)) {
            $errors[$key] = __('Enter a full link starting with https://', 'sc_events');
        }
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $social = array();
    foreach (array_slice($url_keys, 1) as $key) {
        $value = esc_url_raw(trim((string) $in($key)));
        if ($value !== '') {
            $social[$key] = $value;
        }
    }

    $slug = sanitize_title($existing && $existing->name === $name ? $existing->slug : $name) ?: 'organizer';
    $base = $slug;
    for ($n = 2; $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id <> %d", $slug, $id)); $n++) {
        $slug = $base . '-' . $n;
    }

    $data = array(
        'name'          => $name,
        'slug'          => $slug,
        'email'         => sanitize_email($email),
        'phone'         => sanitize_text_field($in('phone')),
        'website'       => esc_url_raw(trim((string) $in('website'))),
        'description'   => wp_kses_post($in('description')),
        'address'       => sanitize_textarea_field($in('address')),
        'social_links'  => wp_json_encode($social),
        'logo'          => absint($in('logo')) ?: null,
        'display_order' => (int) $in('display_order'),
        'is_active'     => !empty($in('is_active')) ? 1 : 0,
        'updated_at'    => current_time('mysql'),
    );
    if ($existing) {
        $ok = $wpdb->update($table, $data, array('id' => $id)) !== false;
    } else {
        $data['created_at'] = current_time('mysql');
        $ok = (bool) $wpdb->insert($table, $data);
        $id = (int) $wpdb->insert_id;
    }
    if (!$ok) {
        wp_send_json_error(array('message' => __('Failed to save organizer.', 'sc_events')));
    }
    wp_send_json_success(array(
        'message'      => $existing ? __('Organizer saved.', 'sc_events') : __('Organizer created.', 'sc_events'),
        'organizer_id' => $id,
        'redirect'     => $existing ? '' : home_url('/event-manager-dashboard/organizer-edit?id=' . $id . '&created=1'),
    ));
}

add_action('wp_ajax_sc_bulk_organizers', 'sc_bulk_organizers');
function sc_bulk_organizers() {
    sc_organizers_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $op = sanitize_key($_POST['op'] ?? '');
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? array()))));
    if (!$ids) {
        wp_send_json_error(array('message' => __('No organizers selected.', 'sc_events')));
    }
    $in = implode(',', $ids);
    if ($op === 'show' || $op === 'hide') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$p}sc_organizers SET is_active = %d, updated_at = %s WHERE id IN ($in)", $op === 'show' ? 1 : 0, current_time('mysql')));
        wp_send_json_success(array('message' => $op === 'show'
            ? sprintf(_n('%d organizer shown.', '%d organizers shown.', $n, 'sc_events'), $n)
            : sprintf(_n('%d organizer hidden.', '%d organizers hidden.', $n, 'sc_events'), $n)));
    }
    if ($op === 'delete') {
        $wpdb->query("DELETE FROM {$p}sc_event_organizers WHERE organizer_id IN ($in)");
        $n = (int) $wpdb->query("DELETE FROM {$p}sc_organizers WHERE id IN ($in)");
        wp_send_json_success(array('message' => sprintf(_n('%d organizer deleted.', '%d organizers deleted.', $n, 'sc_events'), $n)));
    }
    wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
}
