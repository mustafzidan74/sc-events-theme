<?php
/**
 * Data the event form needs, loaded before the page header.
 *
 * In:  $event (object|null).
 * Out: $form_data (array) — option lists, selections, tickets, stats — consumed
 *      by partials/event-form.php and passed to event-form.js.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$event_id = !empty($event) ? (int) $event->id : 0;

$module_on = function ($key) {
    return !function_exists('sc_is_module_enabled') || sc_is_module_enabled($key);
};
$table_exists = function ($table) use ($wpdb) {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
};

/**
 * Options for a picker: active rows plus anything already linked (even if inactive),
 * so saving never silently drops a link. Selected ids keep their stored order.
 */
$picker = function ($table, $pivot, $key, $label_sql, $sub_sql, $order_sql) use ($wpdb, $event_id, $table_exists) {
    if (!$table_exists($table)) {
        return array('options' => array(), 'selected' => array());
    }
    $selected = array();
    if ($event_id && $table_exists($pivot)) {
        $selected = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT {$key} FROM {$pivot} WHERE event_id = %d ORDER BY sort_order ASC",
            $event_id
        )));
    }
    $where = 'is_active = 1';
    if ($selected) {
        $where .= ' OR id IN (' . implode(',', $selected) . ')';
    }
    $rows = $wpdb->get_results("SELECT id, {$label_sql} AS label, {$sub_sql} AS sub, is_active FROM {$table} WHERE {$where} ORDER BY {$order_sql}");
    return array(
        'options'  => array_map(function ($r) {
            return array('id' => (int) $r->id, 'label' => (string) $r->label, 'sub' => (string) $r->sub, 'inactive' => !(int) $r->is_active);
        }, $rows),
        'selected' => $selected,
    );
};

$form_data = array(
    'modules' => array(
        'speakers'     => $module_on('speakers'),
        'sponsors'     => $module_on('sponsors'),
        'partners'     => $module_on('partners'),
        'certificates' => $module_on('certificates'),
    ),
);

$form_data['speakers'] = $form_data['modules']['speakers']
    ? $picker("{$p}sc_speakers", "{$p}sc_event_speakers", 'speaker_id', 'name', "CONCAT_WS(' · ', NULLIF(title, ''), NULLIF(company, ''))", 'name ASC')
    : array('options' => array(), 'selected' => array());
$form_data['organizers'] = $picker("{$p}sc_organizers", "{$p}sc_event_organizers", 'organizer_id', 'name', "''", 'name ASC');
$form_data['sponsors'] = $form_data['modules']['sponsors']
    ? $picker("{$p}sc_sponsors", "{$p}sc_event_sponsors", 'sponsor_id', 'name', 'tier', "FIELD(tier, 'platinum', 'gold', 'silver', 'bronze'), sort_order ASC, name ASC")
    : array('options' => array(), 'selected' => array());
$form_data['partners'] = $form_data['modules']['partners']
    ? $picker("{$p}sc_partners", "{$p}sc_event_partners", 'partner_id', 'name', 'tier', 'sort_order ASC, name ASC')
    : array('options' => array(), 'selected' => array());

// Categories are WordPress terms stored by term id in sc_event_categories.
$terms = taxonomy_exists('sc_event_category') ? get_terms(array('taxonomy' => 'sc_event_category', 'hide_empty' => false, 'orderby' => 'name')) : array();
$form_data['categories'] = array(
    'options'  => is_wp_error($terms) ? array() : array_map(function ($t) {
        return array('id' => (int) $t->term_id, 'label' => $t->name, 'sub' => '');
    }, $terms),
    'selected' => $event_id ? array_map('intval', $wpdb->get_col($wpdb->prepare(
        "SELECT category_id FROM {$p}sc_event_categories WHERE event_id = %d ORDER BY sort_order ASC",
        $event_id
    ))) : array(),
);

$form_data['cert_templates'] = array();
if ($form_data['modules']['certificates'] && class_exists('SC_Certificate_Template') && $table_exists("{$p}sc_certificate_templates")) {
    foreach (SC_Certificate_Template::get_all(array('is_active' => 1, 'limit' => 200)) as $tpl) {
        $form_data['cert_templates'][] = array('id' => (int) $tpl->id, 'name' => $tpl->name);
    }
}

// Event tickets only; workshop tickets belong to their workshops.
$form_data['tickets'] = array();
if ($event_id) {
    $held = array();
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT ticket_id, COUNT(*) AS n FROM {$p}sc_attendees
         WHERE event_id = %d AND workshop_id IS NULL AND status = 'active' AND payment_status = 'success'
         GROUP BY ticket_id",
        $event_id
    )) as $row) {
        $held[(int) $row->ticket_id] = (int) $row->n;
    }
    foreach (SC_Ticket::get_by_event($event_id, array('is_active' => null)) as $t) {
        if (!empty($t->workshop_id)) {
            continue;
        }
        $form_data['tickets'][] = array(
            'id'             => (int) $t->id,
            'name'           => $t->name,
            'description'    => (string) $t->description,
            'price'          => (float) $t->price,
            'quantity'       => (int) $t->quantity,
            'min_per_order'  => (int) $t->min_per_order,
            'max_per_order'  => (int) $t->max_per_order,
            'sale_start'     => $t->sale_start ? substr($t->sale_start, 0, 16) : '',
            'sale_end'       => $t->sale_end ? substr($t->sale_end, 0, 16) : '',
            'is_active'      => (bool) $t->is_active,
            'coupon_only'    => (bool) $t->enable_coupons,
            'ticket_type'    => $t->ticket_type ?: 'general',
            'registered'     => $held[(int) $t->id] ?? 0,
        );
    }
}

// Live figures for the aside.
$form_data['stats'] = array('registered' => 0, 'workshop_registrations' => 0, 'checked_in' => 0, 'revenue' => 0.0, 'attendee_rows' => 0, 'workshops' => 0, 'sessions' => 0);
if ($event_id) {
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS attendee_rows,
                COALESCE(SUM(workshop_id IS NULL AND status = 'active' AND payment_status = 'success'), 0) AS registered,
                COALESCE(SUM(workshop_id IS NOT NULL AND status = 'active' AND payment_status = 'success'), 0) AS workshop_registrations,
                COALESCE(SUM(workshop_id IS NULL AND checked_in = 1 AND status = 'active' AND payment_status = 'success'), 0) AS checked_in,
                COALESCE(SUM(CASE WHEN status = 'active' AND payment_status = 'success' THEN amount_paid END), 0) AS revenue
         FROM {$p}sc_attendees WHERE event_id = %d",
        $event_id
    ));
    $form_data['stats'] = array(
        'attendee_rows'          => (int) $row->attendee_rows,
        'registered'             => (int) $row->registered,
        'workshop_registrations' => (int) $row->workshop_registrations,
        'checked_in'             => (int) $row->checked_in,
        'revenue'                => (float) $row->revenue,
        'workshops'              => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_workshops WHERE event_id = %d", $event_id)),
        'sessions'               => $table_exists("{$p}sc_schedules") ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_schedules WHERE event_id = %d AND is_active = 1", $event_id)) : 0,
    );
}
