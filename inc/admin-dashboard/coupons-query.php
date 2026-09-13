<?php
/**
 * Coupons query — the one filter set behind the coupons list, its tab counts
 * and the CSV export.
 *
 * Coupons live as sc_coupon posts (code = post_title, active = publish,
 * inactive = draft) with their settings in postmeta; sc_find_coupon() reads
 * the same data at registration. The scev_sc_coupons table is an old copy
 * that is no longer written, so nothing here reads it.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read and whitelist list filters from a request array.
 */
function sc_coupons_read_filters($src) {
    $get = function ($key) use ($src) {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
    };
    $views = array('all', 'unused', 'used', 'full', 'expired', 'inactive');
    $event = $get('event_id');
    return array(
        'search'      => strtoupper(trim($get('search'))),
        'event_id'    => $event === 'none' ? 'none' : absint($event),
        'category_id' => absint($get('category_id')),
        'view'        => in_array($get('view'), $views, true) ? $get('view') : 'all',
        'orderby'     => in_array($get('orderby'), array('code', 'uses', 'created'), true) ? $get('orderby') : 'created',
        'order'       => strtolower($get('order')) === 'asc' ? 'ASC' : 'DESC',
    );
}

/**
 * FROM + WHERE for the filters. $with_view = false leaves the tab condition out (for counts).
 *
 * @return array [sql_from_where, values]
 */
function sc_coupons_from_where($f, $with_view = true) {
    global $wpdb;
    $pm = $wpdb->postmeta;
    $sql = "FROM {$wpdb->posts} p
        LEFT JOIN $pm u ON u.post_id = p.ID AND u.meta_key = 'usage_count'
        LEFT JOIN $pm l ON l.post_id = p.ID AND l.meta_key = 'usage_limit'
        LEFT JOIN $pm x ON x.post_id = p.ID AND x.meta_key = 'expiry_date'
        LEFT JOIN $pm e ON e.post_id = p.ID AND e.meta_key = 'event_id'
        LEFT JOIN $pm c ON c.post_id = p.ID AND c.meta_key = 'category_id'
        WHERE p.post_type = 'sc_coupon' AND p.post_status IN ('publish', 'draft')";
    $values = array();

    if ($f['search'] !== '') {
        $sql .= ' AND p.post_title LIKE %s';
        $values[] = '%' . $wpdb->esc_like($f['search']) . '%';
    }
    if ($f['event_id'] === 'none') {
        $sql .= " AND (e.meta_value IS NULL OR e.meta_value IN ('', '0'))";
    } elseif ($f['event_id']) {
        $sql .= ' AND e.meta_value = %s';
        $values[] = (string) $f['event_id'];
    }
    if ($f['category_id']) {
        // Coupons without a category belong to General.
        if ($f['category_id'] === SC_Coupon_Category::DEFAULT_ID) {
            $sql .= " AND (c.meta_value IS NULL OR c.meta_value IN ('', '0', %s))";
        } else {
            $sql .= ' AND c.meta_value = %s';
        }
        $values[] = (string) $f['category_id'];
    }
    if ($with_view && $f['view'] !== 'all') {
        $sql .= ' AND ' . sc_coupons_view_condition($f['view']);
        if (in_array($f['view'], array('unused', 'expired'), true)) {
            $values[] = current_time('Y-m-d');
        }
    }
    return array($sql, $values);
}

/**
 * SQL condition for one tab. unused / expired take the date as one %s.
 */
function sc_coupons_view_condition($view) {
    $uses = 'COALESCE(CAST(u.meta_value AS UNSIGNED), 0)';
    switch ($view) {
        case 'unused':
            return "(p.post_status = 'publish' AND $uses = 0 AND (x.meta_value IS NULL OR x.meta_value = '' OR LEFT(x.meta_value, 10) >= %s))";
        case 'used':
            return "$uses > 0";
        case 'full':
            return "(CAST(l.meta_value AS UNSIGNED) > 0 AND $uses >= CAST(l.meta_value AS UNSIGNED))";
        case 'expired':
            return "(x.meta_value IS NOT NULL AND x.meta_value <> '' AND LEFT(x.meta_value, 10) < %s)";
        case 'inactive':
            return "p.post_status = 'draft'";
    }
    return '1=1';
}

/**
 * Tab counts. The aggregate scans every matching coupon (~2s for 80k), so it is
 * cached briefly and the cache is dropped whenever the dashboard changes coupons.
 */
function sc_coupons_counts($f) {
    global $wpdb;
    $key = 'sc_cpn_counts_' . md5(wp_json_encode(array($f['search'], $f['event_id'], $f['category_id'], current_time('Y-m-d'), get_option('sc_coupons_cache_v', 0))));
    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }
    list($from, $values) = sc_coupons_from_where($f, false);
    $today = current_time('Y-m-d');
    $sql = 'SELECT COUNT(*) AS all_rows,
            COALESCE(SUM(' . sc_coupons_view_condition('unused') . '), 0) AS unused,
            COALESCE(SUM(' . sc_coupons_view_condition('used') . '), 0) AS used,
            COALESCE(SUM(' . sc_coupons_view_condition('full') . '), 0) AS full_rows,
            COALESCE(SUM(' . sc_coupons_view_condition('expired') . '), 0) AS expired,
            COALESCE(SUM(' . sc_coupons_view_condition('inactive') . "), 0) AS inactive
            $from";
    $row = $wpdb->get_row($wpdb->prepare($sql, array_merge(array($today, $today), $values)));
    $counts = array(
        'all'      => (int) ($row->all_rows ?? 0),
        'unused'   => (int) ($row->unused ?? 0),
        'used'     => (int) ($row->used ?? 0),
        'full'     => (int) ($row->full_rows ?? 0),
        'expired'  => (int) ($row->expired ?? 0),
        'inactive' => (int) ($row->inactive ?? 0),
    );
    set_transient($key, $counts, 5 * MINUTE_IN_SECONDS);
    return $counts;
}

/**
 * Drop cached counts after any dashboard change to coupons.
 */
function sc_coupons_bump_cache() {
    update_option('sc_coupons_cache_v', microtime(true), false);
}

/**
 * One page of coupon ids for the filters.
 */
function sc_coupons_page_ids($f, $limit, $offset) {
    global $wpdb;
    list($from, $values) = sc_coupons_from_where($f);
    $order = array(
        'code'    => 'p.post_title',
        'uses'    => 'COALESCE(CAST(u.meta_value AS UNSIGNED), 0)',
        'created' => 'p.ID',
    )[$f['orderby']];
    $values[] = (int) $limit;
    $values[] = (int) $offset;
    return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT p.ID $from ORDER BY $order {$f['order']}, p.ID DESC LIMIT %d OFFSET %d", $values)));
}

/**
 * Full row data for coupon ids, in the given order.
 *
 * @param bool $with_users Include up to three attendees who used each code.
 */
function sc_coupons_rows($ids, $with_users = true) {
    global $wpdb;
    if (!$ids) {
        return array();
    }
    $in = implode(',', array_map('intval', $ids));
    $posts = $wpdb->get_results("SELECT ID, post_title, post_status, post_content, post_date FROM {$wpdb->posts} WHERE ID IN ($in)", OBJECT_K);
    $meta = array();
    foreach ($wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($in) AND meta_key IN ('discount_type', 'discount_value', 'usage_limit', 'usage_count', 'expiry_date', 'event_id', 'category_id', 'ticket_type_filter')") as $m) {
        $meta[(int) $m->post_id][$m->meta_key] = $m->meta_value;
    }

    $event_ids = array_filter(array_map(function ($m) { return (int) ($m['event_id'] ?? 0); }, $meta));
    $events = $event_ids ? $wpdb->get_results('SELECT id, title FROM ' . $wpdb->prefix . 'sc_events WHERE id IN (' . implode(',', array_unique($event_ids)) . ')', OBJECT_K) : array();
    $categories = $wpdb->get_results('SELECT id, name, color FROM ' . $wpdb->prefix . 'sc_coupon_categories', OBJECT_K);

    $users = array();
    if ($with_users) {
        $codes = array_map(function ($p) { return $p->post_title; }, $posts);
        $placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $found = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, coupon_code FROM {$wpdb->prefix}sc_attendees WHERE coupon_code IN ($placeholders) AND status = 'active' ORDER BY id DESC",
            array_values($codes)
        ));
        foreach ($found as $a) {
            $users[strtoupper($a->coupon_code)][] = array('id' => (int) $a->id, 'name' => $a->name);
        }
    }

    $today = current_time('Y-m-d');
    $out = array();
    foreach ($ids as $id) {
        if (!isset($posts[$id])) {
            continue;
        }
        $p = $posts[$id];
        $m = $meta[$id] ?? array();
        $uses = (int) ($m['usage_count'] ?? 0);
        $limit = (int) ($m['usage_limit'] ?? 0);
        $expiry = trim((string) ($m['expiry_date'] ?? ''));
        $cat_id = (int) ($m['category_id'] ?? 0) ?: SC_Coupon_Category::DEFAULT_ID;
        $event_id = (int) ($m['event_id'] ?? 0);
        $type = ($m['discount_type'] ?? '') === 'percentage' ? 'percentage' : 'fixed';

        if ($p->post_status === 'draft') {
            $state = 'inactive';
        } elseif ($expiry !== '' && substr($expiry, 0, 10) < $today) {
            $state = 'expired';
        } elseif ($limit > 0 && $uses >= $limit) {
            $state = 'full';
        } else {
            $state = 'active';
        }
        $who = $users[strtoupper($p->post_title)] ?? array();

        $out[] = array(
            'id'             => (int) $id,
            'code'           => $p->post_title,
            'note'           => $p->post_content,
            'state'          => $state,
            'discount_type'  => $type,
            'discount_value' => (float) ($m['discount_value'] ?? 0),
            'event_id'       => $event_id,
            'event_title'    => $event_id && isset($events[$event_id]) ? $events[$event_id]->title : '',
            'category_id'    => $cat_id,
            'category'       => isset($categories[$cat_id]) ? $categories[$cat_id]->name : 'General',
            'category_color' => isset($categories[$cat_id]) ? $categories[$cat_id]->color : '#7c1314',
            'ticket_filter'  => in_array($m['ticket_type_filter'] ?? '', array('general', 'competitor'), true) ? $m['ticket_type_filter'] : 'all',
            'usage_limit'    => $limit,
            'usage_count'    => $uses,
            'expiry_date'    => $expiry !== '' ? substr($expiry, 0, 10) : '',
            'created'        => $p->post_date,
            'used_by'        => array_slice($who, 0, 3),
            'used_by_total'  => count($who),
        );
    }
    return $out;
}
