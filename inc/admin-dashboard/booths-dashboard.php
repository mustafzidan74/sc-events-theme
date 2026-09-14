<?php
/**
 * Booths — types, booths, bookings and the floor plan for the dashboard pages
 * (template-parts/dashboard/booth*.php).
 *
 * Tables: sc_booth_types, sc_booths, sc_booth_bookings, sc_company_attendees.
 * A booth's status follows its live booking; availability is counted from the
 * booths themselves instead of the old per-type counter, which drifted.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_booths_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
    if (function_exists('sc_module_active') && !sc_module_active('booths')) {
        wp_send_json_error(array('message' => __('The booths module is turned off.', 'sc_events')));
    }
}

/** Booking statuses that hold a booth. */
function sc_booth_live_statuses() {
    return array('pending', 'confirmed', 'active');
}

function sc_booth_categories() {
    return array(
        'standard'  => __('Standard', 'sc_events'),
        'corner'    => __('Corner', 'sc_events'),
        'island'    => __('Island', 'sc_events'),
        'peninsula' => __('Peninsula', 'sc_events'),
        'inline'    => __('Inline', 'sc_events'),
        'custom'    => __('Custom', 'sc_events'),
    );
}

/**
 * Keep the per-type counters (still read by the old API) equal to the real booths.
 */
function sc_booth_types_recount($type_ids) {
    global $wpdb;
    $p = $wpdb->prefix;
    foreach (array_unique(array_filter(array_map('absint', (array) $type_ids))) as $id) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}sc_booth_types SET
                total_quantity = (SELECT COUNT(*) FROM {$p}sc_booths WHERE booth_type_id = %d),
                available_quantity = (SELECT COUNT(*) FROM {$p}sc_booths WHERE booth_type_id = %d AND status = 'available')
             WHERE id = %d",
            $id, $id, $id
        ));
    }
}

/**
 * Point the booth (and the company's booth number) at whatever its bookings say now.
 */
function sc_booth_sync($booth_id) {
    global $wpdb;
    $p = $wpdb->prefix;
    $booth = $wpdb->get_row($wpdb->prepare("SELECT id, booth_type_id, booth_number, status FROM {$p}sc_booths WHERE id = %d", $booth_id));
    if (!$booth) {
        return;
    }
    $live = $wpdb->get_row($wpdb->prepare(
        "SELECT id, company_attendee_id, status FROM {$p}sc_booth_bookings WHERE booth_id = %d AND status IN ('pending', 'confirmed', 'active') ORDER BY FIELD(status, 'active', 'confirmed', 'pending'), id DESC LIMIT 1",
        $booth_id
    ));
    if ($live) {
        $status = array('pending' => 'reserved', 'confirmed' => 'booked', 'active' => 'occupied');
        $wpdb->update($p . 'sc_booths', array('status' => $status[$live->status], 'current_company_id' => (int) $live->company_attendee_id ?: null, 'current_booking_id' => (int) $live->id), array('id' => $booth_id));
        if ($live->company_attendee_id) {
            $wpdb->update($p . 'sc_company_attendees', array('booth_number' => $booth->booth_number), array('id' => (int) $live->company_attendee_id));
        }
    } elseif ($booth->status !== 'unavailable') {
        $wpdb->update($p . 'sc_booths', array('status' => 'available', 'current_company_id' => null, 'current_booking_id' => null), array('id' => $booth_id));
    } else {
        $wpdb->update($p . 'sc_booths', array('current_company_id' => null, 'current_booking_id' => null), array('id' => $booth_id));
    }
    sc_booth_types_recount(array($booth->booth_type_id));
}

/**
 * A company that lost its booth should not keep showing that booth number.
 */
function sc_booth_release_company($company_id, $booth_number) {
    global $wpdb;
    if ($company_id && $booth_number !== '') {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sc_company_attendees SET booth_number = NULL WHERE id = %d AND booth_number = %s",
            $company_id,
            $booth_number
        ));
    }
}

/* ==========================================================================
   Booth types
   ========================================================================== */

add_action('wp_ajax_sc_booth_type_save', 'sc_booth_type_save');
function sc_booth_type_save() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $num = function ($key) {
        return isset($_POST[$key]) && $_POST[$key] !== '' ? round(max(0, (float) $_POST[$key]), 2) : 0.0;
    };

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_booth_types WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('This booth type no longer exists.', 'sc_events')));
    }

    $errors = array();
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    if (!$event_id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_events WHERE id = %d", $event_id))) {
        $errors['event_id'] = __('Choose the event.', 'sc_events');
    }
    $name = $in('name');
    if ($name === '') {
        $errors['name'] = __('Enter a name.', 'sc_events');
    }
    $width = $num('width_meters');
    $depth = $num('depth_meters');
    if ($width <= 0 || $width > 999) {
        $errors['width_meters'] = __('Enter the width in metres.', 'sc_events');
    }
    if ($depth <= 0 || $depth > 999) {
        $errors['depth_meters'] = __('Enter the depth in metres.', 'sc_events');
    }
    $deposit_mode = $in('deposit_mode') === 'percent' ? 'percent' : 'amount';
    $deposit = $num('deposit');
    if ($deposit_mode === 'percent' && $deposit > 100) {
        $errors['deposit'] = __('A percentage cannot be over 100.', 'sc_events');
    }
    if ($existing && $event_id && (int) $existing->event_id !== $event_id && $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_booths WHERE booth_type_id = %d", $id))) {
        $errors['event_id'] = __('This type already has booths, so it has to stay on its event.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors));
    }

    $inclusions = array();
    foreach ((array) (isset($_POST['inclusions']) ? wp_unslash($_POST['inclusions']) : array()) as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $inclusions[] = $item;
        }
    }
    $category = $in('booth_category');
    $color = sanitize_hex_color($in('color'));

    $data = array(
        'event_id'           => $event_id,
        'name'               => $name,
        'name_ar'            => $in('name_ar'),
        'description'        => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
        'booth_category'     => array_key_exists($category, sc_booth_categories()) ? $category : 'standard',
        'width_meters'       => $width,
        'depth_meters'       => $depth,
        'area_sqm'           => round($width * $depth, 2),
        'size_code'          => rtrim(rtrim(number_format($width, 2, '.', ''), '0'), '.') . 'x' . rtrim(rtrim(number_format($depth, 2, '.', ''), '0'), '.'),
        'base_price'         => $num('base_price'),
        'deposit_amount'     => $deposit_mode === 'amount' ? $deposit : 0,
        'deposit_percentage' => $deposit_mode === 'percent' ? $deposit : 0,
        'inclusions'         => $inclusions ? wp_json_encode($inclusions) : null,
        'is_active'          => !empty($_POST['is_active']) ? 1 : 0,
        'color'              => $color ?: '#3B82F6',
    );

    if ($existing) {
        $wpdb->update($p . 'sc_booth_types', $data, array('id' => $id));
    } else {
        $slug = sanitize_title($name) ?: 'booth';
        $base = $slug;
        for ($i = 2; $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_booth_types WHERE slug = %s AND event_id = %d", $slug, $event_id)); $i++) {
            $slug = $base . '-' . $i;
        }
        $data['slug'] = $slug;
        $data['total_quantity'] = 0;
        $data['available_quantity'] = 0;
        $wpdb->insert($p . 'sc_booth_types', $data);
        $id = (int) $wpdb->insert_id;
    }
    if (!$id) {
        wp_send_json_error(array('message' => __('The booth type could not be saved.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message'  => $existing ? __('Booth type saved.', 'sc_events') : __('Booth type created.', 'sc_events'),
        'id'       => $id,
        'redirect' => $existing ? '' : home_url('/event-manager-dashboard/booth-type-edit?id=' . $id),
    ));
}

add_action('wp_ajax_sc_booth_type_delete', 'sc_booth_type_delete');
function sc_booth_type_delete() {
    sc_booths_verify_request();
    global $wpdb;
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $used = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booths WHERE booth_type_id = %d", $id));
    if ($used) {
        /* translators: %d: number of booths */
        wp_send_json_error(array('message' => sprintf(_n('%d booth uses this type. Move or delete it first.', '%d booths use this type. Move or delete them first.', $used, 'sc_events'), $used)));
    }
    if (!$wpdb->delete($wpdb->prefix . 'sc_booth_types', array('id' => $id))) {
        wp_send_json_error(array('message' => __('This booth type no longer exists.', 'sc_events')));
    }
    wp_send_json_success(array('message' => __('Booth type deleted.', 'sc_events')));
}

/* ==========================================================================
   Booths
   ========================================================================== */

function sc_booths_read_filters() {
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $view = $in('view');
    return array(
        'event_id' => isset($_POST['event_id']) ? absint($_POST['event_id']) : 0,
        'type_id'  => isset($_POST['type_id']) ? absint($_POST['type_id']) : 0,
        'search'   => $in('search'),
        'view'     => in_array($view, array('all', 'available', 'taken', 'unavailable'), true) ? $view : 'all',
        'orderby'  => in_array($in('orderby'), array('number', 'status', 'price'), true) ? $in('orderby') : 'number',
        'order'    => $in('order') === 'desc' ? 'DESC' : 'ASC',
    );
}

function sc_booths_where($f, $with_view = true) {
    global $wpdb;
    $sql = 'b.event_id = %d';
    $values = array($f['event_id']);
    if ($f['type_id']) {
        $sql .= ' AND b.booth_type_id = %d';
        $values[] = $f['type_id'];
    }
    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $sql .= ' AND (b.booth_number LIKE %s OR b.booth_name LIKE %s OR c.company_name LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    if ($with_view) {
        $sql .= sc_booths_view_condition($f['view']);
    }
    return array($sql, $values);
}

function sc_booths_view_condition($view) {
    $map = array(
        'available'   => " AND b.status = 'available'",
        'taken'       => " AND b.status IN ('reserved', 'booked', 'occupied')",
        'unavailable' => " AND b.status = 'unavailable'",
    );
    return $map[$view] ?? '';
}

add_action('wp_ajax_sc_booths_list', 'sc_booths_list');
function sc_booths_list() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $f = sc_booths_read_filters();
    $per_page = isset($_POST['per_page']) ? min(200, max(1, absint($_POST['per_page']))) : 50;
    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
    $from = "FROM {$p}sc_booths b LEFT JOIN {$p}sc_booth_types t ON t.id = b.booth_type_id LEFT JOIN {$p}sc_company_attendees c ON c.id = b.current_company_id LEFT JOIN {$p}sc_booth_bookings bk ON bk.id = b.current_booking_id";
    list($where, $values) = sc_booths_where($f);
    $order = array(
        'number' => 'LENGTH(b.booth_number) ' . $f['order'] . ', b.booth_number ' . $f['order'],
        'status' => "FIELD(b.status, 'available', 'reserved', 'booked', 'occupied', 'unavailable') " . $f['order'],
        'price'  => 'COALESCE(b.custom_price, t.base_price) ' . $f['order'],
    );

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT b.id, b.booth_number, b.booth_name, b.status, b.is_featured, b.floor_level, b.custom_price, b.custom_width, b.custom_depth,
                b.has_electricity, b.has_water, b.has_wifi, b.current_booking_id,
                t.id AS type_id, t.name AS type_name, t.color, t.width_meters, t.depth_meters, t.base_price,
                c.id AS company_id, c.company_name, c.company_logo, bk.booking_ref, bk.status AS booking_status, bk.payment_status
         $from WHERE $where ORDER BY {$order[$f['orderby']]}, b.id LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from WHERE $where", $values));

    $out = array(
        'rows' => array_map(function ($r) {
            $w = (float) ($r->custom_width ?: $r->width_meters);
            $d = (float) ($r->custom_depth ?: $r->depth_meters);
            return array(
                'id'         => (int) $r->id,
                'number'     => $r->booth_number,
                'name'       => (string) $r->booth_name,
                'status'     => $r->status,
                'featured'   => (bool) $r->is_featured,
                'floor'      => (int) $r->floor_level,
                'type_id'    => (int) $r->type_id,
                'type'       => (string) $r->type_name,
                'color'      => $r->color ?: '#3B82F6',
                'size'       => $w && $d ? array('w' => $w, 'd' => $d, 'area' => round($w * $d, 2)) : null,
                'price'      => (float) ($r->custom_price !== null ? $r->custom_price : $r->base_price),
                'custom_price' => $r->custom_price !== null,
                'amenities'  => array_keys(array_filter(array('power' => $r->has_electricity, 'water' => $r->has_water, 'wifi' => $r->has_wifi))),
                'company'    => $r->company_id ? array('id' => (int) $r->company_id, 'name' => $r->company_name, 'logo' => $r->company_logo ? (wp_get_attachment_image_url((int) $r->company_logo, 'thumbnail') ?: '') : '') : null,
                'booking'    => $r->current_booking_id ? array('id' => (int) $r->current_booking_id, 'ref' => $r->booking_ref, 'status' => $r->booking_status, 'payment' => $r->payment_status) : null,
            );
        }, $rows),
        'total' => $total,
    );

    if (!empty($_POST['with_counts'])) {
        list($base_where, $base_values) = sc_booths_where($f, false);
        $out['counts'] = array();
        foreach (array('all', 'available', 'taken', 'unavailable') as $view) {
            $out['counts'][$view] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from WHERE $base_where" . sc_booths_view_condition($view), $base_values));
        }
        $out['stats'] = $f['event_id'] ? sc_booths_event_stats($f['event_id']) : null;
    }
    wp_send_json_success($out);
}

function sc_booths_event_stats($event_id) {
    global $wpdb;
    $p = $wpdb->prefix;
    $booths = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) total, SUM(status = 'available') available, SUM(status IN ('reserved', 'booked', 'occupied')) taken,
                COALESCE(SUM(COALESCE(b.custom_width, t.width_meters) * COALESCE(b.custom_depth, t.depth_meters)), 0) area
         FROM {$p}sc_booths b LEFT JOIN {$p}sc_booth_types t ON t.id = b.booth_type_id WHERE b.event_id = %d",
        $event_id
    ));
    $money = $wpdb->get_row($wpdb->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) booked, COALESCE(SUM(deposit_paid + balance_paid), 0) collected FROM {$p}sc_booth_bookings WHERE event_id = %d AND status NOT IN ('cancelled', 'no_show')",
        $event_id
    ));
    return array(
        'total'     => (int) $booths->total,
        'available' => (int) $booths->available,
        'taken'     => (int) $booths->taken,
        'area'      => round((float) $booths->area, 1),
        'booked'    => (float) $money->booked,
        'collected' => (float) $money->collected,
    );
}

add_action('wp_ajax_sc_booth_save', 'sc_booth_save');
function sc_booth_save() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $opt_num = function ($key, $max) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            return null;
        }
        return round(min($max, max(0, (float) $_POST[$key])), 2);
    };

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_booths WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('This booth no longer exists.', 'sc_events')));
    }

    $errors = array();
    $type_id = isset($_POST['booth_type_id']) ? absint($_POST['booth_type_id']) : 0;
    $type = $type_id ? $wpdb->get_row($wpdb->prepare("SELECT id, event_id FROM {$p}sc_booth_types WHERE id = %d", $type_id)) : null;
    if (!$type) {
        $errors['booth_type_id'] = __('Choose the booth type.', 'sc_events');
    }
    $event_id = $type ? (int) $type->event_id : 0;
    $number = strtoupper(preg_replace('/\s+/', '', $in('booth_number')));
    if ($number === '') {
        $errors['booth_number'] = __('Enter the booth number.', 'sc_events');
    } elseif (strlen($number) > 50) {
        $errors['booth_number'] = __('Keep the booth number under 50 characters.', 'sc_events');
    } elseif ($event_id && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_booths WHERE event_id = %d AND booth_number = %s AND id <> %d", $event_id, $number, $id))) {
        /* translators: %s: booth number */
        $errors['booth_number'] = sprintf(__('Booth %s already exists for this event.', 'sc_events'), $number);
    }
    if ($existing && $event_id && (int) $existing->event_id !== $event_id && $existing->current_booking_id) {
        $errors['booth_type_id'] = __('This booth is booked, so its type has to stay on the same event.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors));
    }

    $data = array(
        'event_id'        => $event_id,
        'booth_type_id'   => $type_id,
        'booth_number'    => $number,
        'booth_name'      => $in('booth_name'),
        'booth_name_ar'   => $in('booth_name_ar'),
        'floor_level'     => max(0, min(20, isset($_POST['floor_level']) ? (int) $_POST['floor_level'] : 1)),
        'custom_width'    => $opt_num('custom_width', 999),
        'custom_depth'    => $opt_num('custom_depth', 999),
        'custom_price'    => $opt_num('custom_price', 9999999999),
        'is_featured'     => !empty($_POST['is_featured']) ? 1 : 0,
        'has_electricity' => !empty($_POST['has_electricity']) ? 1 : 0,
        'has_water'       => !empty($_POST['has_water']) ? 1 : 0,
        'has_wifi'        => !empty($_POST['has_wifi']) ? 1 : 0,
        'power_outlets'   => max(0, min(99, isset($_POST['power_outlets']) ? (int) $_POST['power_outlets'] : 0)),
        'max_power_kw'    => $opt_num('max_power_kw', 999) ?? 0,
        'notes'           => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
    );
    // Only "open" and "closed" are set by hand; reserved/booked/occupied come from bookings.
    $closed = $in('availability') === 'unavailable';

    if ($existing) {
        if (!$existing->current_booking_id) {
            $data['status'] = $closed ? 'unavailable' : 'available';
        }
        $wpdb->update($p . 'sc_booths', $data, array('id' => $id));
        if ($existing->booth_number !== $number && $existing->current_company_id) {
            $wpdb->update($p . 'sc_company_attendees', array('booth_number' => $number), array('id' => (int) $existing->current_company_id, 'booth_number' => $existing->booth_number));
        }
        sc_booth_types_recount(array($existing->booth_type_id, $type_id));
    } else {
        $data['status'] = $closed ? 'unavailable' : 'available';
        $wpdb->insert($p . 'sc_booths', $data);
        $id = (int) $wpdb->insert_id;
        sc_booth_types_recount(array($type_id));
    }
    if (!$id) {
        wp_send_json_error(array('message' => __('The booth could not be saved.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message'  => $existing ? __('Booth saved.', 'sc_events') : __('Booth created.', 'sc_events'),
        'id'       => $id,
        'redirect' => $existing ? '' : home_url('/event-manager-dashboard/booth-edit?id=' . $id),
    ));
}

add_action('wp_ajax_sc_booths_generate', 'sc_booths_generate');
function sc_booths_generate() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $type_id = isset($_POST['booth_type_id']) ? absint($_POST['booth_type_id']) : 0;
    $type = $type_id ? $wpdb->get_row($wpdb->prepare("SELECT id, event_id FROM {$p}sc_booth_types WHERE id = %d", $type_id)) : null;
    $prefix = isset($_POST['prefix']) ? strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', wp_unslash($_POST['prefix']))) : '';
    $start = isset($_POST['start']) ? max(0, (int) $_POST['start']) : 1;
    $count = isset($_POST['count']) ? (int) $_POST['count'] : 0;
    $pad = !empty($_POST['pad']) ? strlen((string) ($start + $count - 1)) : 0;
    $floor = isset($_POST['floor_level']) ? max(0, min(20, (int) $_POST['floor_level'])) : 1;

    $errors = array();
    if (!$type) {
        $errors['booth_type_id'] = __('Choose the booth type.', 'sc_events');
    }
    if ($count < 1 || $count > 200) {
        $errors['count'] = __('Add between 1 and 200 booths at a time.', 'sc_events');
    }
    if (strlen($prefix) > 10) {
        $errors['prefix'] = __('Keep the prefix under 10 characters.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors));
    }

    $created = 0;
    $skipped = array();
    for ($i = 0; $i < $count; $i++) {
        $number = $prefix . ($pad ? str_pad((string) ($start + $i), $pad, '0', STR_PAD_LEFT) : ($start + $i));
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_booths WHERE event_id = %d AND booth_number = %s", $type->event_id, $number))) {
            $skipped[] = $number;
            continue;
        }
        $created += (int) $wpdb->insert($p . 'sc_booths', array(
            'event_id' => (int) $type->event_id, 'booth_type_id' => $type_id, 'booth_number' => $number,
            'floor_level' => $floor, 'status' => 'available', 'has_electricity' => 1, 'has_wifi' => 1, 'power_outlets' => 2, 'max_power_kw' => 3,
        ));
    }
    sc_booth_types_recount(array($type_id));

    /* translators: %d: number of booths */
    $message = sprintf(_n('%d booth added.', '%d booths added.', $created, 'sc_events'), $created);
    if ($skipped) {
        /* translators: %s: booth numbers */
        $message .= ' ' . sprintf(__('Skipped numbers that already exist: %s.', 'sc_events'), implode(', ', array_slice($skipped, 0, 10)) . (count($skipped) > 10 ? '…' : ''));
    }
    wp_send_json_success(array('message' => $message, 'created' => $created, 'skipped' => count($skipped)));
}

add_action('wp_ajax_sc_booths_bulk', 'sc_booths_bulk');
function sc_booths_bulk() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    $ids = isset($_POST['ids']) ? array_filter(array_map('absint', (array) wp_unslash($_POST['ids']))) : array();
    if (!$ids || !in_array($op, array('open', 'close', 'delete'), true)) {
        wp_send_json_error(array('message' => __('Nothing to do.', 'sc_events')));
    }
    $list = implode(',', $ids);
    $booths = $wpdb->get_results("SELECT id, booth_type_id, current_booking_id FROM {$p}sc_booths WHERE id IN ($list)");
    $done = 0;
    $held = 0;
    foreach ($booths as $b) {
        // A booth with a live booking is left alone: cancel the booking first.
        if ($b->current_booking_id && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_booth_bookings WHERE id = %d AND status IN ('pending', 'confirmed', 'active')", $b->current_booking_id))) {
            $held++;
            continue;
        }
        if ($op === 'delete') {
            if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_booth_bookings WHERE booth_id = %d", $b->id))) {
                $held++; // keep the booking history attached
                continue;
            }
            $done += (int) $wpdb->delete($p . 'sc_booths', array('id' => $b->id));
        } else {
            $wpdb->update($p . 'sc_booths', array('status' => $op === 'close' ? 'unavailable' : 'available', 'current_company_id' => null, 'current_booking_id' => null), array('id' => $b->id));
            $done++;
        }
    }
    sc_booth_types_recount(wp_list_pluck($booths, 'booth_type_id'));

    $messages = array(
        /* translators: %d: number of booths */
        'open'   => _n('%d booth opened for booking.', '%d booths opened for booking.', $done, 'sc_events'),
        /* translators: %d: number of booths */
        'close'  => _n('%d booth closed.', '%d booths closed.', $done, 'sc_events'),
        /* translators: %d: number of booths */
        'delete' => _n('%d booth deleted.', '%d booths deleted.', $done, 'sc_events'),
    );
    $message = sprintf($messages[$op], $done);
    if ($held) {
        $message .= ' ' . ($op === 'delete'
            /* translators: %d: number of booths */
            ? sprintf(_n('%d has bookings and was kept.', '%d have bookings and were kept.', $held, 'sc_events'), $held)
            /* translators: %d: number of booths */
            : sprintf(_n('%d is booked and was left as it is.', '%d are booked and were left as they are.', $held, 'sc_events'), $held));
    }
    wp_send_json_success(array('message' => $message, 'done' => $done, 'failed' => $held));
}

/* ==========================================================================
   Floor plan
   ========================================================================== */

function sc_floor_plan_hall($event_id) {
    $hall = get_option('sc_floor_plan_' . (int) $event_id);
    return array(
        'width' => isset($hall['width']) ? max(10, min(500, (int) $hall['width'])) : 60,
        'depth' => isset($hall['depth']) ? max(10, min(500, (int) $hall['depth'])) : 40,
    );
}

add_action('wp_ajax_sc_floor_plan_data', 'sc_floor_plan_data');
function sc_floor_plan_data() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT b.id, b.booth_number, b.booth_name, b.status, b.floor_level, b.position_x, b.position_y, b.rotation,
                COALESCE(b.custom_width, t.width_meters, 3) w, COALESCE(b.custom_depth, t.depth_meters, 3) d,
                t.name type_name, t.color, c.company_name, bk.booking_ref, bk.id booking_id
         FROM {$p}sc_booths b LEFT JOIN {$p}sc_booth_types t ON t.id = b.booth_type_id
         LEFT JOIN {$p}sc_company_attendees c ON c.id = b.current_company_id
         LEFT JOIN {$p}sc_booth_bookings bk ON bk.id = b.current_booking_id
         WHERE b.event_id = %d ORDER BY b.floor_level, LENGTH(b.booth_number), b.booth_number",
        $event_id
    ));
    wp_send_json_success(array(
        'hall'   => sc_floor_plan_hall($event_id),
        'booths' => array_map(function ($r) {
            return array(
                'id' => (int) $r->id, 'number' => $r->booth_number, 'name' => (string) $r->booth_name, 'status' => $r->status,
                'floor' => (int) $r->floor_level, 'w' => (float) $r->w, 'd' => (float) $r->d, 'rotation' => (int) $r->rotation % 180 ? 90 : 0,
                // Positions are stored in decimetres; a booth at 0,0 has not been placed yet.
                'x' => (int) $r->position_x / 10, 'y' => (int) $r->position_y / 10, 'placed' => (int) $r->position_x > 0 || (int) $r->position_y > 0,
                'type' => (string) $r->type_name, 'color' => $r->color ?: '#3B82F6', 'company' => (string) $r->company_name, 'ref' => (string) $r->booking_ref, 'booking_id' => (int) $r->booking_id,
            );
        }, $rows),
    ));
}

add_action('wp_ajax_sc_floor_plan_save', 'sc_floor_plan_save');
function sc_floor_plan_save() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Choose the event.', 'sc_events')));
    }
    if (isset($_POST['hall_width'], $_POST['hall_depth'])) {
        update_option('sc_floor_plan_' . $event_id, array('width' => max(10, min(500, (int) $_POST['hall_width'])), 'depth' => max(10, min(500, (int) $_POST['hall_depth']))), false);
    }
    $positions = isset($_POST['positions']) ? json_decode(wp_unslash((string) $_POST['positions']), true) : array();
    $saved = 0;
    foreach (is_array($positions) ? $positions : array() as $pos) {
        if (!is_array($pos) || empty($pos['id'])) {
            continue;
        }
        $saved += (int) (false !== $wpdb->query($wpdb->prepare(
            "UPDATE {$p}sc_booths SET position_x = %d, position_y = %d, rotation = %d WHERE id = %d AND event_id = %d",
            max(0, min(5000, (int) round((float) ($pos['x'] ?? 0) * 10))),
            max(0, min(5000, (int) round((float) ($pos['y'] ?? 0) * 10))),
            !empty($pos['rotation']) ? 90 : 0,
            absint($pos['id']),
            $event_id
        )));
    }
    wp_send_json_success(array('message' => __('Floor plan saved.', 'sc_events'), 'saved' => $saved));
}

/* ==========================================================================
   Bookings
   ========================================================================== */

function sc_booth_bookings_read_filters() {
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $view = $in('view');
    $payment = $in('payment');
    return array(
        'event_id' => isset($_POST['event_id']) ? absint($_POST['event_id']) : 0,
        'search'   => $in('search'),
        'view'     => in_array($view, array('all', 'pending', 'confirmed', 'active', 'done', 'cancelled'), true) ? $view : 'all',
        'payment'  => in_array($payment, array('unpaid', 'deposit_paid', 'fully_paid', 'overdue'), true) ? $payment : '',
        'orderby'  => in_array($in('orderby'), array('created', 'total', 'booth'), true) ? $in('orderby') : 'created',
        'order'    => $in('order') === 'asc' ? 'ASC' : 'DESC',
    );
}

function sc_booth_bookings_where($f, $with_view = true) {
    global $wpdb;
    $sql = '1=1';
    $values = array();
    if ($f['event_id']) {
        $sql .= ' AND bk.event_id = %d';
        $values[] = $f['event_id'];
    }
    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $sql .= ' AND (bk.booking_ref LIKE %s OR c.company_name LIKE %s OR b.booth_number LIKE %s OR bk.company_legal_name LIKE %s)';
        array_push($values, $like, $like, $like, $like);
    }
    if ($f['payment'] === 'unpaid') {
        $sql .= " AND bk.payment_status = 'pending'";
    } elseif ($f['payment']) {
        $sql .= ' AND bk.payment_status = %s';
        $values[] = $f['payment'];
    }
    if ($with_view) {
        $sql .= sc_booth_bookings_view_condition($f['view']);
    }
    return array($sql, $values);
}

function sc_booth_bookings_view_condition($view) {
    $map = array(
        'pending'   => " AND bk.status = 'pending'",
        'confirmed' => " AND bk.status = 'confirmed'",
        'active'    => " AND bk.status = 'active'",
        'done'      => " AND bk.status = 'completed'",
        'cancelled' => " AND bk.status IN ('cancelled', 'no_show')",
    );
    return $map[$view] ?? '';
}

function sc_booth_booking_row($r) {
    $currency = get_option('sc_currency_code', 'EGP');
    return array(
        'id'        => (int) $r->id,
        'ref'       => $r->booking_ref,
        'status'    => $r->status,
        'payment'   => $r->payment_status,
        'booth'     => array('id' => (int) $r->booth_id, 'number' => (string) $r->booth_number, 'type' => (string) $r->type_name, 'color' => $r->color ?: '#3B82F6'),
        'company'   => array('id' => (int) $r->company_attendee_id, 'name' => (string) $r->company_name, 'contact' => (string) $r->contact_name, 'email' => (string) $r->contact_email),
        'event'     => (string) $r->event_title,
        'total'     => (float) $r->total_amount,
        'paid'      => (float) $r->deposit_paid + (float) $r->balance_paid,
        'due'       => max(0, (float) $r->total_amount - (float) $r->deposit_paid - (float) $r->balance_paid),
        'due_date'  => $r->balance_due_date,
        'currency'  => $currency,
        'checked_in_at' => $r->checked_in ? $r->checked_in_at : null,
        'created'   => $r->booking_date ?: $r->created_at,
    );
}

add_action('wp_ajax_sc_booth_bookings_list', 'sc_booth_bookings_list');
function sc_booth_bookings_list() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $f = sc_booth_bookings_read_filters();
    $per_page = isset($_POST['per_page']) ? min(200, max(1, absint($_POST['per_page']))) : 50;
    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
    $from = "FROM {$p}sc_booth_bookings bk LEFT JOIN {$p}sc_booths b ON b.id = bk.booth_id LEFT JOIN {$p}sc_booth_types t ON t.id = b.booth_type_id
             LEFT JOIN {$p}sc_company_attendees c ON c.id = bk.company_attendee_id LEFT JOIN {$p}sc_events e ON e.id = bk.event_id";
    list($where, $values) = sc_booth_bookings_where($f);
    $order = array(
        'created' => 'bk.created_at ' . $f['order'],
        'total'   => 'bk.total_amount ' . $f['order'],
        'booth'   => 'LENGTH(b.booth_number) ' . $f['order'] . ', b.booth_number ' . $f['order'],
    );
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT bk.*, b.booth_number, t.name type_name, t.color, c.company_name, c.contact_name, c.contact_email, e.title event_title
         $from WHERE $where ORDER BY {$order[$f['orderby']]}, bk.id DESC LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    $out = array(
        'rows'  => array_map('sc_booth_booking_row', $rows),
        'total' => (int) $wpdb->get_var($values ? $wpdb->prepare("SELECT COUNT(*) $from WHERE $where", $values) : "SELECT COUNT(*) $from WHERE $where"),
    );
    if (!empty($_POST['with_counts'])) {
        list($base_where, $base_values) = sc_booth_bookings_where($f, false);
        $out['counts'] = array();
        foreach (array('all', 'pending', 'confirmed', 'active', 'done', 'cancelled') as $view) {
            $sql = "SELECT COUNT(*) $from WHERE $base_where" . sc_booth_bookings_view_condition($view);
            $out['counts'][$view] = (int) $wpdb->get_var($base_values ? $wpdb->prepare($sql, $base_values) : $sql);
        }
    }
    wp_send_json_success($out);
}

/**
 * Payment status from the money, not from a dropdown.
 */
function sc_booth_payment_status($total, $paid, $due_date) {
    if ($total > 0 && $paid >= $total - 0.005) {
        return 'fully_paid';
    }
    if ($paid > 0) {
        return 'deposit_paid';
    }
    if ($due_date && $due_date < current_time('Y-m-d') && $total > 0) {
        return 'overdue';
    }
    return 'pending';
}

add_action('wp_ajax_sc_booth_booking_save', 'sc_booth_booking_save');
function sc_booth_booking_save() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $money = function ($key) {
        return isset($_POST[$key]) && $_POST[$key] !== '' ? round(max(0, (float) $_POST[$key]), 2) : 0.0;
    };
    $date = function ($key) use ($in) {
        $v = $in($key);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    };

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_booth_bookings WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('This booking no longer exists.', 'sc_events')));
    }

    $errors = array();
    $booth_id = isset($_POST['booth_id']) ? absint($_POST['booth_id']) : 0;
    $booth = $booth_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_booths WHERE id = %d", $booth_id)) : null;
    if (!$booth) {
        $errors['booth_id'] = __('Choose the booth.', 'sc_events');
    }
    $company_id = isset($_POST['company_attendee_id']) ? absint($_POST['company_attendee_id']) : 0;
    $company = $company_id ? $wpdb->get_row($wpdb->prepare("SELECT id, event_id, company_name FROM {$p}sc_company_attendees WHERE id = %d", $company_id)) : null;
    if (!$company) {
        $errors['company_attendee_id'] = __('Choose the company.', 'sc_events');
    } elseif ($booth && (int) $company->event_id !== (int) $booth->event_id) {
        $errors['company_attendee_id'] = __('This company is registered for a different event than the booth.', 'sc_events');
    }
    $status = $in('status');
    if (!in_array($status, array('pending', 'confirmed', 'active', 'completed', 'cancelled', 'no_show'), true)) {
        $status = $existing ? $existing->status : 'pending';
    }
    if ($booth && in_array($status, sc_booth_live_statuses(), true)) {
        if ($booth->status === 'unavailable' && (!$existing || (int) $existing->booth_id !== $booth_id)) {
            $errors['booth_id'] = __('This booth is closed. Open it on the booths page first.', 'sc_events');
        }
        $clash = $wpdb->get_var($wpdb->prepare("SELECT booking_ref FROM {$p}sc_booth_bookings WHERE booth_id = %d AND id <> %d AND status IN ('pending', 'confirmed', 'active')", $booth_id, $id));
        if ($clash) {
            /* translators: %s: booking reference */
            $errors['booth_id'] = sprintf(__('This booth is already held by booking %s.', 'sc_events'), $clash);
        }
    }
    $base = $money('base_price');
    $extras = $money('extras_price');
    $discount = $money('discount_amount');
    $tax = $money('tax_amount');
    $total = round(max(0, $base + $extras - $discount + $tax), 2);
    $deposit_paid = $money('deposit_paid');
    $balance_paid = $money('balance_paid');
    if ($discount > $base + $extras) {
        $errors['discount_amount'] = __('The discount is bigger than the price.', 'sc_events');
    }
    $setup = $date('setup_date');
    $teardown = $date('teardown_date');
    if ($setup && $teardown && $teardown < $setup) {
        $errors['teardown_date'] = __('Teardown cannot be before setup.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors));
    }

    $event = $wpdb->get_row($wpdb->prepare("SELECT start_date, end_date FROM {$p}sc_events WHERE id = %d", $booth->event_id));
    $now = current_time('mysql');
    $user = get_current_user_id();
    $due_date = $date('balance_due_date');
    $data = array(
        'event_id'               => (int) $booth->event_id,
        'booth_id'               => $booth_id,
        'company_attendee_id'    => $company_id,
        'start_date'             => $date('start_date') ?: ($event ? substr((string) $event->start_date, 0, 10) : null),
        'end_date'               => $date('end_date') ?: ($event ? substr((string) ($event->end_date ?: $event->start_date), 0, 10) : null),
        'base_price'             => $base,
        'extras_price'           => $extras,
        'discount_amount'        => $discount,
        'tax_amount'             => $tax,
        'total_amount'           => $total,
        'deposit_required'       => $money('deposit_required'),
        'deposit_paid'           => $deposit_paid,
        'balance_paid'           => $balance_paid,
        'balance_due'            => round(max(0, $total - $deposit_paid - $balance_paid), 2),
        'balance_due_date'       => $due_date,
        'payment_status'         => in_array($status, array('cancelled', 'no_show'), true) && !$deposit_paid && !$balance_paid ? 'cancelled' : sc_booth_payment_status($total, $deposit_paid + $balance_paid, $due_date),
        'company_legal_name'     => $in('company_legal_name'),
        'commercial_registry_no' => $in('commercial_registry_no'),
        'vat_number'             => $in('vat_number'),
        'contract_signed'        => !empty($_POST['contract_signed']) ? 1 : 0,
        'contract_signer_name'   => $in('contract_signer_name'),
        'contract_signer_title'  => $in('contract_signer_title'),
        'setup_date'             => $setup,
        'teardown_date'          => $teardown,
        'special_requests'       => isset($_POST['special_requests']) ? sanitize_textarea_field(wp_unslash($_POST['special_requests'])) : '',
        'notes'                  => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
        'internal_notes'         => isset($_POST['internal_notes']) ? sanitize_textarea_field(wp_unslash($_POST['internal_notes'])) : '',
        'status'                 => $status,
    );
    // Timestamps follow the status and money the first time they happen.
    $was = $existing ? $existing : (object) array('status' => '', 'contract_signed' => 0, 'deposit_paid' => 0, 'balance_paid' => 0, 'confirmed_at' => null, 'checked_in' => 0);
    if ($data['contract_signed'] && !$was->contract_signed) {
        $data['contract_signed_at'] = $now;
    }
    if ($deposit_paid > (float) $was->deposit_paid) {
        $data['deposit_paid_at'] = $now;
    }
    if ($balance_paid > (float) $was->balance_paid) {
        $data['balance_paid_at'] = $now;
    }
    if (in_array($status, array('confirmed', 'active', 'completed'), true) && empty($was->confirmed_at)) {
        $data['confirmed_at'] = $now;
        $data['confirmed_by'] = $user;
    }
    if ($status === 'active' && !(int) $was->checked_in) {
        $data['checked_in'] = 1;
        $data['checked_in_at'] = $now;
    }
    if (in_array($status, array('cancelled', 'no_show'), true) && !in_array($was->status, array('cancelled', 'no_show'), true)) {
        $data['cancelled_at'] = $now;
        $data['cancelled_by'] = $user;
        $data['cancellation_reason'] = $in('cancellation_reason');
    }

    if ($existing) {
        $wpdb->update($p . 'sc_booth_bookings', $data, array('id' => $id));
    } else {
        do {
            $ref = 'BK-' . strtoupper(wp_generate_password(8, false));
        } while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_booth_bookings WHERE booking_ref = %s", $ref)));
        $data['booking_ref'] = $ref;
        $data['booking_date'] = $now;
        $data['created_by'] = $user;
        $wpdb->insert($p . 'sc_booth_bookings', $data);
        $id = (int) $wpdb->insert_id;
    }
    if (!$id) {
        wp_send_json_error(array('message' => __('The booking could not be saved.', 'sc_events')));
    }

    if ($existing && ((int) $existing->booth_id !== $booth_id || (int) $existing->company_attendee_id !== $company_id || !in_array($status, sc_booth_live_statuses(), true))) {
        $old_number = (string) $wpdb->get_var($wpdb->prepare("SELECT booth_number FROM {$p}sc_booths WHERE id = %d", $existing->booth_id));
        if ($status !== 'completed') {
            sc_booth_release_company((int) $existing->company_attendee_id, $old_number);
        }
        if ((int) $existing->booth_id !== $booth_id) {
            sc_booth_sync((int) $existing->booth_id);
        }
    }
    sc_booth_sync($booth_id);

    wp_send_json_success(array(
        'message'  => $existing ? __('Booking saved.', 'sc_events') : __('Booking created.', 'sc_events'),
        'id'       => $id,
        'redirect' => $existing ? '' : home_url('/event-manager-dashboard/booth-booking-edit?id=' . $id),
    ));
}

add_action('wp_ajax_sc_booth_booking_action', 'sc_booth_booking_action');
function sc_booth_booking_action() {
    sc_booths_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $bk = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sc_booth_bookings WHERE id = %d", $id)) : null;
    if (!$bk) {
        wp_send_json_error(array('message' => __('This booking no longer exists.', 'sc_events')));
    }
    $now = current_time('mysql');
    $user = get_current_user_id();
    $data = array();

    switch ($op) {
        case 'confirm':
            if ($bk->status !== 'pending') {
                wp_send_json_error(array('message' => __('Only pending bookings can be confirmed.', 'sc_events')));
            }
            $data = array('status' => 'confirmed', 'confirmed_at' => $now, 'confirmed_by' => $user);
            $message = __('Booking confirmed.', 'sc_events');
            break;
        case 'check_in':
            if (!in_array($bk->status, array('pending', 'confirmed'), true)) {
                wp_send_json_error(array('message' => __('Only pending or confirmed bookings can be checked in.', 'sc_events')));
            }
            $data = array('status' => 'active', 'checked_in' => 1, 'checked_in_at' => $now);
            if (!$bk->confirmed_at) {
                $data['confirmed_at'] = $now;
                $data['confirmed_by'] = $user;
            }
            $message = __('Exhibitor checked in at the booth.', 'sc_events');
            break;
        case 'check_out':
            if ($bk->status !== 'active') {
                wp_send_json_error(array('message' => __('Only checked-in bookings can be checked out.', 'sc_events')));
            }
            $data = array('status' => 'completed', 'checked_out' => 1, 'checked_out_at' => $now);
            $message = __('Booth handed back. The booking is complete.', 'sc_events');
            break;
        case 'cancel':
            if (!in_array($bk->status, sc_booth_live_statuses(), true)) {
                wp_send_json_error(array('message' => __('This booking is not active.', 'sc_events')));
            }
            $data = array(
                'status' => 'cancelled', 'cancelled_at' => $now, 'cancelled_by' => $user,
                'cancellation_reason' => isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '',
            );
            if (!(float) $bk->deposit_paid && !(float) $bk->balance_paid) {
                $data['payment_status'] = 'cancelled';
            }
            $message = __('Booking cancelled. The booth is free again.', 'sc_events');
            break;
        case 'payment':
            $amount = isset($_POST['amount']) ? round((float) $_POST['amount'], 2) : 0;
            $due = max(0, (float) $bk->total_amount - (float) $bk->deposit_paid - (float) $bk->balance_paid);
            if ($amount <= 0) {
                wp_send_json_error(array('message' => __('Enter the amount received.', 'sc_events'), 'errors' => array('amount' => __('Enter the amount received.', 'sc_events'))));
            }
            if ($amount > $due + 0.005) {
                /* translators: %s: amount still due */
                wp_send_json_error(array('message' => sprintf(__('That is more than the %s still due.', 'sc_events'), number_format_i18n($due, 2)), 'errors' => array('amount' => sprintf(__('That is more than the %s still due.', 'sc_events'), number_format_i18n($due, 2)))));
            }
            if (!(float) $bk->deposit_paid) {
                $data = array('deposit_paid' => $amount, 'deposit_paid_at' => $now);
            } else {
                $data = array('balance_paid' => round((float) $bk->balance_paid + $amount, 2), 'balance_paid_at' => $now);
            }
            $paid = (float) ($data['deposit_paid'] ?? $bk->deposit_paid) + (float) ($data['balance_paid'] ?? $bk->balance_paid);
            $data['balance_due'] = round(max(0, (float) $bk->total_amount - $paid), 2);
            $data['payment_status'] = sc_booth_payment_status((float) $bk->total_amount, $paid, $bk->balance_due_date);
            $message = __('Payment recorded.', 'sc_events');
            break;
        default:
            wp_send_json_error(array('message' => __('Nothing to do.', 'sc_events')));
    }

    $wpdb->update($p . 'sc_booth_bookings', $data, array('id' => $id));
    if ($op === 'cancel') {
        sc_booth_release_company((int) $bk->company_attendee_id, (string) $wpdb->get_var($wpdb->prepare("SELECT booth_number FROM {$p}sc_booths WHERE id = %d", $bk->booth_id)));
    }
    sc_booth_sync((int) $bk->booth_id);
    wp_send_json_success(array('message' => $message));
}
