<?php
/**
 * SC Speaker Model Class
 *
 * Data Access Layer for Speakers table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Speaker {

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
            self::$table = $wpdb->prefix . 'sc_speakers';
        }
        return self::$table;
    }

    /**
     * Get pivot table name
     */
    public static function get_pivot_table() {
        global $wpdb;
        return $wpdb->prefix . 'sc_event_speakers';
    }

    /**
     * Get single speaker by ID
     *
     * @param int $id Speaker ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $speaker = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($speaker) {
            $speaker = self::hydrate($speaker);
        }

        return $speaker;
    }

    /**
     * Get speaker by slug
     *
     * @param string $slug Speaker slug
     * @return object|null
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        $speaker = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($speaker) {
            $speaker = self::hydrate($speaker);
        }

        return $speaker;
    }

    /**
     * Get speakers for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_by_event($event_id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $speakers = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, es.role, es.sort_order
            FROM $table s
            INNER JOIN $pivot es ON s.id = es.speaker_id
            WHERE es.event_id = %d
            ORDER BY es.sort_order ASC, s.name ASC",
            $event_id
        ));

        foreach ($speakers as &$speaker) {
            $speaker = self::hydrate($speaker);
        }

        return $speakers;
    }

    /**
     * Get all speakers with filters
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
            $where[] = '(name LIKE %s OR bio LIKE %s OR company LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'name', 'company', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $speakers = $wpdb->get_results($sql);

        foreach ($speakers as &$speaker) {
            $speaker = self::hydrate($speaker);
        }

        return $speakers;
    }

    /**
     * Create new speaker
     *
     * @param array $data Speaker data
     * @return int|false Speaker ID or false
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
            $speaker_id = $wpdb->insert_id;
            do_action('sc_speaker_created', $speaker_id, $data);
            return $speaker_id;
        }

        return false;
    }

    /**
     * Update speaker
     *
     * @param int $id Speaker ID
     * @param array $data Speaker data
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
            do_action('sc_speaker_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete speaker
     *
     * @param int $id Speaker ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();
        $pivot = self::get_pivot_table();

        $speaker = self::get($id);
        if (!$speaker) {
            return false;
        }

        // Remove from all events
        $wpdb->delete($pivot, array('speaker_id' => $id), array('%d'));

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_speaker_deleted', $id, $speaker);
            return true;
        }

        return false;
    }

    /**
     * Attach speaker to event
     *
     * @param int $speaker_id Speaker ID
     * @param int $event_id Event ID
     * @param string $role Speaker role
     * @param int $sort_order Sort order
     * @return bool Success
     */
    public static function attach_to_event($speaker_id, $event_id, $role = 'speaker', $sort_order = 0) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        // Check if already attached (use COUNT instead of id column)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pivot WHERE speaker_id = %d AND event_id = %d",
            $speaker_id,
            $event_id
        ));

        if ($exists) {
            // Update role and order
            return $wpdb->update(
                $pivot,
                array('role' => $role, 'sort_order' => $sort_order),
                array('speaker_id' => $speaker_id, 'event_id' => $event_id)
            ) !== false;
        }

        return $wpdb->insert($pivot, array(
            'speaker_id' => $speaker_id,
            'event_id'   => $event_id,
            'role'       => $role,
            'sort_order' => $sort_order,
        )) !== false;
    }

    /**
     * Detach speaker from event
     *
     * @param int $speaker_id Speaker ID
     * @param int $event_id Event ID
     * @return bool Success
     */
    public static function detach_from_event($speaker_id, $event_id) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        return $wpdb->delete($pivot, array(
            'speaker_id' => $speaker_id,
            'event_id'   => $event_id,
        )) !== false;
    }

    /**
     * Sync speakers for an event
     *
     * @param int $event_id Event ID
     * @param array $speakers Array of speaker IDs or [id => ['role' => '', 'sort_order' => 0]]
     * @return bool Success
     */
    public static function sync_event_speakers($event_id, $speakers) {
        global $wpdb;
        $pivot = self::get_pivot_table();

        // Remove all current speakers
        $wpdb->delete($pivot, array('event_id' => $event_id), array('%d'));

        // Add new speakers
        $sort_order = 0;
        foreach ($speakers as $key => $value) {
            // Support both simple array [1, 2, 3] and associative array [id => data]
            if (is_array($value)) {
                $speaker_id = $key;
                $role = isset($value['role']) ? $value['role'] : 'speaker';
                $order = isset($value['sort_order']) ? intval($value['sort_order']) : $sort_order;
            } else {
                $speaker_id = intval($value);
                $role = 'speaker';
                $order = $sort_order;
            }

            if ($speaker_id > 0) {
                self::attach_to_event($speaker_id, $event_id, $role, $order);
            }
            $sort_order++;
        }

        return true;
    }

    /**
     * Generate unique slug
     *
     * @param string $name Speaker name
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
        $int_fields = array('photo', 'user_id');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('name', 'slug', 'email', 'phone', 'company', 'job_title', 'website', 'status');

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
        if (isset($data['bio'])) {
            $prepared['bio'] = wp_kses_post($data['bio']);
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
     * Hydrate speaker object
     *
     * @param object $speaker Raw speaker
     * @return object Hydrated speaker
     */
    private static function hydrate($speaker) {
        // Decode JSON fields
        if (!empty($speaker->social_links)) {
            $decoded = json_decode($speaker->social_links, true);
            $speaker->social_links = is_array($decoded) ? $decoded : array();
        } else {
            $speaker->social_links = array();
        }

        // Photo URL
        if ($speaker->photo) {
            $speaker->photo_url = wp_get_attachment_image_url($speaker->photo, 'medium');
            $speaker->photo_url_large = wp_get_attachment_image_url($speaker->photo, 'large');
        } else {
            // Default avatar
            $speaker->photo_url = get_avatar_url($speaker->email, array('size' => 300));
            $speaker->photo_url_large = get_avatar_url($speaker->email, array('size' => 600));
        }

        // Profile URL
        $speaker->profile_url = home_url('/speaker/' . $speaker->slug);

        return $speaker;
    }
}
