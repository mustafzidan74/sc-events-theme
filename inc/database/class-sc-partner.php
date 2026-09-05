<?php
/**
 * SC Partner Model Class
 *
 * Data Access Layer for Partners table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Partner {

    /**
     * Table name
     */
    private static $table;

    /**
     * Get table name
     */
    public static function get_table() {
        global $wpdb;
        if (!self::$table) {
            self::$table = $wpdb->prefix . 'sc_partners';
        }
        return self::$table;
    }

    /**
     * Get pivot table name
     */
    public static function get_pivot_table() {
        global $wpdb;
        return $wpdb->prefix . 'sc_event_partners';
    }

    /**
     * Get single partner by ID
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($partner) {
            $partner = self::hydrate($partner);
        }

        return $partner;
    }

    /**
     * Get partner by slug
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($partner) {
            $partner = self::hydrate($partner);
        }

        return $partner;
    }

    /**
     * Get partners for an event
     */
    public static function get_by_event($event_id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $partners = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, es.tier_override, es.sort_order as event_sort_order
            FROM $table s
            INNER JOIN $pivot es ON s.id = es.partner_id
            WHERE es.event_id = %d
            ORDER BY
                FIELD(COALESCE(es.tier_override, s.tier), 'platinum', 'gold', 'silver', 'bronze'),
                es.sort_order ASC, s.name ASC",
            $event_id
        ));

        foreach ($partners as &$partner) {
            $partner = self::hydrate($partner);
        }

        return $partners;
    }

    /**
     * Get all partners with filters
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'   => 'active',
            'tier'     => null,
            'search'   => null,
            'event_id' => null,
            'orderby'  => 'sort_order',
            'order'    => 'ASC',
            'limit'    => 50,
            'offset'   => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();
        $join = '';

        if ($args['status'] === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where[] = 's.is_active = 0';
        }

        if ($args['tier']) {
            $where[] = 's.tier = %s';
            $values[] = $args['tier'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(s.name LIKE %s OR s.email LIKE %s OR s.website LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        if ($args['event_id']) {
            $pivot = self::get_pivot_table();
            $join = "INNER JOIN $pivot es ON s.id = es.partner_id AND es.event_id = " . intval($args['event_id']);
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'name', 'tier', 'sort_order', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sort_order';
        $orderby = 's.' . $orderby;

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT s.* FROM $table s $join WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $partners = $wpdb->get_results($wpdb->prepare($sql, $values));

        foreach ($partners as &$partner) {
            $partner = self::hydrate($partner);
        }

        return $partners;
    }

    /**
     * Count partners with filters
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'   => 'active',
            'tier'     => null,
            'search'   => null,
            'event_id' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();
        $join = '';

        if ($args['status'] === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where[] = 's.is_active = 0';
        }

        if ($args['tier']) {
            $where[] = 's.tier = %s';
            $values[] = $args['tier'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(s.name LIKE %s OR s.email LIKE %s OR s.website LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        if ($args['event_id']) {
            $pivot = self::get_pivot_table();
            $join = "INNER JOIN $pivot es ON s.id = es.partner_id AND es.event_id = " . intval($args['event_id']);
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($values)) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table s $join WHERE $where_clause",
                $values
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table s $join WHERE $where_clause");
    }

    /**
     * Create new partner
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        if (empty($data['slug'])) {
            $data['slug'] = self::generate_unique_slug($data['name']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $partner_id = $wpdb->insert_id;
            do_action('sc_partner_created', $partner_id, $data);
            return $partner_id;
        }

        return false;
    }

    /**
     * Update partner
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result !== false) {
            do_action('sc_partner_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete partner
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $partner = self::get($id);
        if (!$partner) {
            return false;
        }

        // Remove from all events
        $wpdb->delete($pivot, array('partner_id' => $id), array('%d'));

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_partner_deleted', $id, $partner);
            return true;
        }

        return false;
    }

    /**
     * Attach partner to event
     */
    public static function attach_to_event($partner_id, $event_id, $tier_override = null, $sort_order = 0) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pivot WHERE partner_id = %d AND event_id = %d",
            $partner_id,
            $event_id
        ));

        if ($exists) {
            return $wpdb->update(
                $pivot,
                array('tier_override' => $tier_override, 'sort_order' => $sort_order),
                array('partner_id' => $partner_id, 'event_id' => $event_id)
            ) !== false;
        }

        return $wpdb->insert($pivot, array(
            'partner_id'    => $partner_id,
            'event_id'      => $event_id,
            'tier_override' => $tier_override,
            'sort_order'    => $sort_order,
        )) !== false;
    }

    /**
     * Detach partner from event
     */
    public static function detach_from_event($partner_id, $event_id) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        return $wpdb->delete($pivot, array(
            'partner_id' => $partner_id,
            'event_id'   => $event_id,
        )) !== false;
    }

    /**
     * Sync partners for an event
     */
    public static function sync_event_partners($event_id, $partners) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        $wpdb->delete($pivot, array('event_id' => $event_id), array('%d'));

        $sort_order = 0;
        foreach ($partners as $key => $value) {
            if (is_array($value)) {
                $partner_id = $key;
                $tier_override = isset($value['tier_override']) ? $value['tier_override'] : null;
                $order = isset($value['sort_order']) ? intval($value['sort_order']) : $sort_order;
            } else {
                $partner_id = intval($value);
                $tier_override = null;
                $order = $sort_order;
            }

            if ($partner_id > 0) {
                self::attach_to_event($partner_id, $event_id, $tier_override, $order);
            }
            $sort_order++;
        }

        return true;
    }

    /**
     * Get event count for a partner
     */
    public static function get_events_count($partner_id) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pivot WHERE partner_id = %d",
            $partner_id
        ));
    }

    /**
     * Generate unique slug
     */
    public static function generate_unique_slug($name) {
        global $wpdb;
        $table = self::get_table();

        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s", $slug))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get tier display label
     */
    public static function get_tier_label($tier) {
        $labels = array(
            'platinum' => __('Platinum', 'sc_events'),
            'gold'     => __('Gold', 'sc_events'),
            'silver'   => __('Silver', 'sc_events'),
            'bronze'   => __('Bronze', 'sc_events'),
        );
        return isset($labels[$tier]) ? $labels[$tier] : ucfirst($tier);
    }

    /**
     * Prepare data for insert/update
     */
    private static function prepare_data($data) {
        $prepared = array();

        // Integer fields
        $int_fields = array('logo', 'sort_order', 'is_active');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('name', 'slug', 'phone', 'tier');
        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Validate tier
        if (isset($prepared['tier'])) {
            $allowed_tiers = array('platinum', 'gold', 'silver', 'bronze');
            if (!in_array($prepared['tier'], $allowed_tiers)) {
                $prepared['tier'] = 'bronze';
            }
        }

        // Email field
        if (isset($data['email'])) {
            $prepared['email'] = sanitize_email($data['email']);
        }

        // URL field
        if (isset($data['website'])) {
            $prepared['website'] = esc_url_raw($data['website']);
        }

        // Text fields
        if (isset($data['description'])) {
            $prepared['description'] = wp_kses_post($data['description']);
        }

        return $prepared;
    }

    /**
     * Hydrate partner object
     */
    private static function hydrate($partner) {
        // Logo URL
        if (!empty($partner->logo)) {
            $partner->logo_url = wp_get_attachment_image_url($partner->logo, 'medium');
            $partner->logo_url_large = wp_get_attachment_image_url($partner->logo, 'large');
        } else {
            $partner->logo_url = '';
            $partner->logo_url_large = '';
        }

        // Tier label
        $partner->tier_label = self::get_tier_label($partner->tier);

        return $partner;
    }
}
