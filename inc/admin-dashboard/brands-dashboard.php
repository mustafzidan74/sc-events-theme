<?php
/**
 * Sponsors and partners — one implementation for both dashboard lists and forms.
 *
 * The two were copies of each other. Each module registers its own actions
 * (sc_get_sponsors_paginated, sc_save_sponsor, … and the partner equivalents)
 * with sc_brands_register(), so a module switched off registers nothing.
 *
 * The older save handlers forced every record back to active, never stored the
 * sponsor's title, and allowed no Diamond tier although the public pages show it.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $type sponsor|partner
 * @return array|null
 */
function sc_brand_config($type) {
    global $wpdb;
    $p = $wpdb->prefix;
    $configs = array(
        'sponsor' => array(
            'type' => 'sponsor', 'table' => "{$p}sc_sponsors", 'pivot' => "{$p}sc_event_sponsors", 'fk' => 'sponsor_id',
            'has_title' => true, 'has_tiers' => true,
        ),
        'partner' => array(
            'type' => 'partner', 'table' => "{$p}sc_partners", 'pivot' => "{$p}sc_event_partners", 'fk' => 'partner_id',
            'has_title' => false, 'has_tiers' => false,
        ),
    );
    return $configs[$type] ?? null;
}

function sc_brand_tiers() {
    return array('diamond', 'platinum', 'gold', 'silver', 'bronze');
}

function sc_brands_register($type) {
    $plural = $type . 's';
    add_action("wp_ajax_sc_get_{$plural}_paginated", function () use ($type) { sc_brands_list($type); });
    add_action("wp_ajax_sc_save_{$type}", function () use ($type) { sc_brands_save($type); });
    add_action("wp_ajax_sc_bulk_{$plural}", function () use ($type) { sc_brands_bulk($type); });
}

function sc_brands_verify_request() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

function sc_brands_list($type) {
    sc_brands_verify_request();
    global $wpdb;
    $c = sc_brand_config($type);
    $p = $wpdb->prefix;

    $view = in_array($_POST['view'] ?? '', array('all', 'active', 'hidden', 'upcoming', 'unlinked'), true) ? sanitize_key($_POST['view']) : 'all';
    $search = trim(sanitize_text_field(wp_unslash($_POST['search'] ?? '')));
    $event_id = absint($_POST['event_id'] ?? 0);
    $tier = in_array($_POST['tier'] ?? '', sc_brand_tiers(), true) ? sanitize_key($_POST['tier']) : '';
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 50)));
    $today = current_time('Y-m-d');

    $events_sql = "(SELECT COUNT(DISTINCT pv.event_id) FROM {$c['pivot']} pv JOIN {$p}sc_events e ON e.id = pv.event_id WHERE pv.{$c['fk']} = b.id)";
    $upcoming_sql = "EXISTS (SELECT 1 FROM {$c['pivot']} pu JOIN {$p}sc_events eu ON eu.id = pu.event_id WHERE pu.{$c['fk']} = b.id AND COALESCE(eu.end_date, eu.start_date) >= '" . esc_sql($today) . "')";
    $where = '1=1';
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (b.name LIKE %s OR b.email LIKE %s OR b.website LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    if ($event_id) {
        $where .= " AND EXISTS (SELECT 1 FROM {$c['pivot']} pe WHERE pe.{$c['fk']} = b.id AND pe.event_id = %d)";
        $values[] = $event_id;
    }
    if ($tier && $c['has_tiers']) {
        $where .= ' AND b.tier = %s';
        $values[] = $tier;
    }
    $views = array('all' => '1=1', 'active' => 'b.is_active = 1', 'hidden' => 'b.is_active = 0', 'upcoming' => $upcoming_sql, 'unlinked' => "$events_sql = 0");
    $prep = function ($sql, $vals) use ($wpdb) {
        return $vals ? $wpdb->prepare($sql, $vals) : $sql;
    };

    $total = (int) $wpdb->get_var($prep("SELECT COUNT(*) FROM {$c['table']} b WHERE $where AND {$views[$view]}", $values));
    $tier_order = $c['has_tiers'] ? "FIELD(b.tier, 'diamond', 'platinum', 'gold', 'silver', 'bronze'), " : '';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, $events_sql AS events FROM {$c['table']} b WHERE $where AND {$views[$view]} ORDER BY {$tier_order}b.sort_order ASC, b.name ASC LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    $latest = array();
    if ($rows) {
        $ids = implode(',', array_map('intval', wp_list_pluck($rows, 'id')));
        foreach ($wpdb->get_results("SELECT pv.{$c['fk']} AS bid, e.title, COALESCE(e.end_date, e.start_date) >= '" . esc_sql($today) . "' AS upcoming FROM {$c['pivot']} pv JOIN {$p}sc_events e ON e.id = pv.event_id WHERE pv.{$c['fk']} IN ($ids) ORDER BY e.start_date ASC") as $r) {
            $latest[(int) $r->bid] = array('title' => $r->title, 'upcoming' => (bool) $r->upcoming);
        }
    }

    $out = array();
    foreach ($rows as $b) {
        $out[] = array(
            'id'        => (int) $b->id,
            'name'      => $b->name,
            'title'     => $c['has_title'] ? (string) $b->title : '',
            'tier'      => $c['has_tiers'] ? (string) $b->tier : '',
            'website'   => (string) $b->website,
            'email'     => (string) $b->email,
            'logo'      => $b->logo ? (wp_get_attachment_image_url((int) $b->logo, 'medium') ?: '') : '',
            'is_active' => (bool) $b->is_active,
            'order'     => (int) $b->sort_order,
            'events'    => (int) $b->events,
            'latest'    => $latest[(int) $b->id] ?? null,
        );
    }

    $response = array('rows' => $out, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        $cnt = $wpdb->get_row($prep("SELECT COUNT(*) AS all_rows, COALESCE(SUM(b.is_active = 1), 0) AS active, COALESCE(SUM($upcoming_sql), 0) AS upcoming, COALESCE(SUM($events_sql = 0), 0) AS unlinked FROM {$c['table']} b WHERE $where", $values));
        $response['counts'] = array(
            'all' => (int) $cnt->all_rows, 'active' => (int) $cnt->active, 'hidden' => (int) $cnt->all_rows - (int) $cnt->active,
            'upcoming' => (int) $cnt->upcoming, 'unlinked' => (int) $cnt->unlinked,
        );
    }
    wp_send_json_success($response);
}

function sc_brands_save($type) {
    sc_brands_verify_request();
    global $wpdb;
    $c = sc_brand_config($type);
    $in = function ($key) {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    };

    $id = absint($in('id'));
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$c['table']} WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('Not found.', 'sc_events')));
    }

    $name = sanitize_text_field($in('name'));
    $email = trim((string) $in('email'));
    $website = trim((string) $in('website'));
    $errors = array();
    if ($name === '') {
        $errors['name'] = __('Enter the name.', 'sc_events');
    }
    if ($email !== '' && !is_email($email)) {
        $errors['email'] = __('Enter a valid email address.', 'sc_events');
    }
    if ($website !== '' && !preg_match('#^https?://\S+\.\S+#i', $website)) {
        $errors['website'] = __('Enter a full link starting with https://', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $slug = sanitize_title($existing && $existing->name === $name ? $existing->slug : $name) ?: $type;
    $base = $slug;
    for ($n = 2; $wpdb->get_var($wpdb->prepare("SELECT id FROM {$c['table']} WHERE slug = %s AND id <> %d", $slug, $id)); $n++) {
        $slug = $base . '-' . $n;
    }

    $data = array(
        'name'        => $name,
        'slug'        => $slug,
        'email'       => sanitize_email($email),
        'phone'       => sanitize_text_field($in('phone')),
        'website'     => esc_url_raw($website),
        'description' => wp_kses_post($in('description')),
        'logo'        => absint($in('logo')) ?: null,
        'sort_order'  => (int) $in('sort_order'),
        'is_active'   => !empty($in('is_active')) ? 1 : 0,
        'updated_at'  => current_time('mysql'),
    );
    if ($c['has_title']) {
        $data['title'] = sanitize_text_field($in('title'));
    }
    if ($c['has_tiers']) {
        $data['tier'] = in_array($in('tier'), sc_brand_tiers(), true) ? $in('tier') : 'bronze';
    }
    if ($existing) {
        $ok = $wpdb->update($c['table'], $data, array('id' => $id)) !== false;
    } else {
        $data['created_at'] = current_time('mysql');
        $ok = (bool) $wpdb->insert($c['table'], $data);
        $id = (int) $wpdb->insert_id;
    }
    if (!$ok) {
        wp_send_json_error(array('message' => __('Could not save.', 'sc_events')));
    }
    wp_send_json_success(array(
        'message'  => $existing ? __('Saved.', 'sc_events') : __('Created.', 'sc_events'),
        'id'       => $id,
        'redirect' => $existing ? '' : home_url('/event-manager-dashboard/' . $type . '-edit?id=' . $id . '&created=1'),
    ));
}

function sc_brands_bulk($type) {
    sc_brands_verify_request();
    global $wpdb;
    $c = sc_brand_config($type);
    $op = sanitize_key($_POST['op'] ?? '');
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? array()))));
    if (!$ids) {
        wp_send_json_error(array('message' => __('Nothing selected.', 'sc_events')));
    }
    $in = implode(',', $ids);
    if ($op === 'show' || $op === 'hide') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$c['table']} SET is_active = %d, updated_at = %s WHERE id IN ($in)", $op === 'show' ? 1 : 0, current_time('mysql')));
        wp_send_json_success(array('message' => $op === 'show'
            ? sprintf(_n('%d made active.', '%d made active.', $n, 'sc_events'), $n)
            : sprintf(_n('%d hidden.', '%d hidden.', $n, 'sc_events'), $n)));
    }
    if ($op === 'delete') {
        $wpdb->query("DELETE FROM {$c['pivot']} WHERE {$c['fk']} IN ($in)");
        $n = (int) $wpdb->query("DELETE FROM {$c['table']} WHERE id IN ($in)");
        wp_send_json_success(array('message' => sprintf(_n('%d deleted.', '%d deleted.', $n, 'sc_events'), $n)));
    }
    wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
}
