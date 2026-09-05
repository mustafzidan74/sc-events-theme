<?php
/**
 * SC Organizer Model Class
 *
 * Data Access Layer for Organizers table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Organizer {

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
            self::$table = $wpdb->prefix . 'sc_organizers';
        }
        return self::$table;
    }

    /**
     * Get pivot table name
     */
    public static function get_pivot_table() {
        global $wpdb;
        return $wpdb->prefix . 'sc_event_organizers';
    }

    /**
     * Get single organizer by ID
     *
     * @param int $id Organizer ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $organizer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($organizer) {
            $organizer = self::hydrate($organizer);
        }

        return $organizer;
    }

    /**
     * Get organizer by slug
     *
     * @param string $slug Organizer slug
     * @return object|null
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        $organizer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($organizer) {
            $organizer = self::hydrate($organizer);
        }

        return $organizer;
    }

    /**
     * Get organizer by user ID
     *
     * @param int $user_id User ID
     * @return object|null
     */
    public static function get_by_user($user_id) {
        global $wpdb;
        $table = self::get_table();

        $organizer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));

        if ($organizer) {
            $organizer = self::hydrate($organizer);
        }

        return $organizer;
    }

    /**
     * Get organizers for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_by_event($event_id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $organizers = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, eo.role, eo.sort_order
            FROM $table o
            INNER JOIN $pivot eo ON o.id = eo.organizer_id
            WHERE eo.event_id = %d
            ORDER BY eo.sort_order ASC, o.name ASC",
            $event_id
        ));

        foreach ($organizers as &$organizer) {
            $organizer = self::hydrate($organizer);
        }

        return $organizers;
    }

    /**
     * Get all organizers with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'  => 'active',
            'search'  => null,
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 50,
            'offset'  => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR description LIKE %s OR email LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'name', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $organizers = $wpdb->get_results($sql);

        foreach ($organizers as &$organizer) {
            $organizer = self::hydrate($organizer);
        }

        return $organizers;
    }

    /**
     * Create new organizer
     *
     * @param array $data Organizer data
     * @return int|false Organizer ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate slug
        if (empty($data['slug'])) {
            $data['slug'] = self::generate_unique_slug($data['name']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $organizer_id = $wpdb->insert_id;
            do_action('sc_organizer_created', $organizer_id, $data);
            return $organizer_id;
        }

        return false;
    }

    /**
     * Update organizer
     *
     * @param int $id Organizer ID
     * @param array $data Organizer data
     * @return bool Success
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
            do_action('sc_organizer_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete organizer
     *
     * @param int $id Organizer ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $organizer = self::get($id);
        if (!$organizer) {
            return false;
        }

        // Remove from all events
        $wpdb->delete($pivot, array('organizer_id' => $id), array('%d'));

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_organizer_deleted', $id, $organizer);
            return true;
        }

        return false;
    }

    /**
     * Attach organizer to event
     *
     * @param int $organizer_id Organizer ID
     * @param int $event_id Event ID
     * @param string $role Organizer role
     * @param int $sort_order Sort order
     * @return bool Success
     */
    public static function attach_to_event($organizer_id, $event_id, $role = 'organizer', $sort_order = 0) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        // Check if already attached (use COUNT instead of id column)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pivot WHERE organizer_id = %d AND event_id = %d",
            $organizer_id,
            $event_id
        ));

        if ($exists) {
            return $wpdb->update(
                $pivot,
                array('role' => $role, 'sort_order' => $sort_order),
                array('organizer_id' => $organizer_id, 'event_id' => $event_id)
            ) !== false;
        }

        return $wpdb->insert($pivot, array(
            'organizer_id' => $organizer_id,
            'event_id'     => $event_id,
            'role'         => $role,
            'sort_order'   => $sort_order,
        )) !== false;
    }

    /**
     * Detach organizer from event
     *
     * @param int $organizer_id Organizer ID
     * @param int $event_id Event ID
     * @return bool Success
     */
    public static function detach_from_event($organizer_id, $event_id) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        return $wpdb->delete($pivot, array(
            'organizer_id' => $organizer_id,
            'event_id'     => $event_id,
        )) !== false;
    }

    /**
     * Sync organizers for an event (remove all and add new)
     *
     * @param int $event_id Event ID
     * @param array $organizers Array of organizer IDs or [id => ['role' => '', 'sort_order' => 0]]
     * @return bool Success
     */
    public static function sync_event_organizers($event_id, $organizers) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        // Remove all current organizers
        $wpdb->delete($pivot, array('event_id' => $event_id), array('%d'));

        // Add new organizers
        $sort_order = 0;
        foreach ($organizers as $key => $value) {
            // Support both simple array [1, 2, 3] and associative array [id => data]
            if (is_array($value)) {
                $organizer_id = $key;
                $role = isset($value['role']) ? $value['role'] : 'organizer';
                $order = isset($value['sort_order']) ? intval($value['sort_order']) : $sort_order;
            } else {
                $organizer_id = intval($value);
                $role = 'organizer';
                $order = $sort_order;
            }

            if ($organizer_id > 0) {
                self::attach_to_event($organizer_id, $event_id, $role, $order);
            }
            $sort_order++;
        }

        return true;
    }

    /**
     * Generate unique slug
     *
     * @param string $name Organizer name
     * @return string
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
     * Prepare data for insert/update
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private static function prepare_data($data) {
        $prepared = array();

        // Integer fields
        $int_fields = array('logo', 'user_id');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('name', 'slug', 'email', 'phone', 'website', 'address', 'status');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
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

        // JSON fields
        if (isset($data['social_links'])) {
            if (is_array($data['social_links'])) {
                $prepared['social_links'] = wp_json_encode($data['social_links']);
            } else {
                $prepared['social_links'] = $data['social_links'];
            }
        }

        return $prepared;
    }

    /**
     * Hydrate organizer object
     *
     * @param object $organizer Raw organizer
     * @return object Hydrated organizer
     */
    private static function hydrate($organizer) {
        // Decode JSON fields
        if (!empty($organizer->social_links)) {
            $decoded = json_decode($organizer->social_links, true);
            $organizer->social_links = is_array($decoded) ? $decoded : array();
        } else {
            $organizer->social_links = array();
        }

        // Logo URL
        if ($organizer->logo) {
            $organizer->logo_url = wp_get_attachment_image_url($organizer->logo, 'medium');
            $organizer->logo_url_large = wp_get_attachment_image_url($organizer->logo, 'large');
        } else {
            $organizer->logo_url = null;
            $organizer->logo_url_large = null;
        }

        // Profile URL
        $organizer->profile_url = home_url('/organizer/' . $organizer->slug);

        return $organizer;
    }
}
