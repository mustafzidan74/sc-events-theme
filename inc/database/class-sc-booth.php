<?php
/**
 * SC Booth Model Class
 *
 * Data Access Layer for Booths table (Individual exhibition booths)
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booth {

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
            self::$table = $wpdb->prefix . 'sc_booths';
        }
        return self::$table;
    }

    /**
     * Get single booth by ID
     *
     * @param int $id Booth ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $booth = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($booth) {
            $booth = self::hydrate($booth);
        }

        return $booth;
    }

    /**
     * Get booth by number within event
     *
     * @param string $booth_number Booth number
     * @param int $event_id Event ID
     * @return object|null
     */
    public static function get_by_number($booth_number, $event_id) {
        global $wpdb;
        $table = self::get_table();

        $booth = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE booth_number = %s AND event_id = %d",
            $booth_number,
            $event_id
        ));

        if ($booth) {
            $booth = self::hydrate($booth);
        }

        return $booth;
    }

    /**
     * Get booths by event
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'        => null,
            'booth_type_id' => null,
            'zone_id'       => null,
            'floor_level'   => null,
            'is_featured'   => null,
            'orderby'       => 'booth_number',
            'order'         => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('event_id = %d');
        $values = array($event_id);

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where[] = 'status IN (' . implode(',', $placeholders) . ')';
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['booth_type_id']) {
            $where[] = 'booth_type_id = %d';
            $values[] = $args['booth_type_id'];
        }

        if ($args['zone_id']) {
            $where[] = 'zone_id = %d';
            $values[] = $args['zone_id'];
        }

        if ($args['floor_level'] !== null) {
            $where[] = 'floor_level = %d';
            $values[] = $args['floor_level'];
        }

        if ($args['is_featured'] !== null) {
            $where[] = 'is_featured = %d';
            $values[] = $args['is_featured'] ? 1 : 0;
        }

        $orderby = in_array($args['orderby'], array('booth_number', 'status', 'position_x', 'position_y', 'created_at'))
            ? $args['orderby'] : 'booth_number';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY $orderby $order";
        $query = $wpdb->prepare($query, $values);

        $booths = $wpdb->get_results($query);

        return array_map(array('self', 'hydrate'), $booths);
    }

    /**
     * Get list of booths with filtering and pagination
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_list($args = array()) {
        global $wpdb;
        $table = self::get_table();
        $types_table = $wpdb->prefix . 'sc_booth_types';
        $events_table = $wpdb->prefix . 'sc_events';
        $companies_table = $wpdb->prefix . 'sc_company_attendees';

        $defaults = array(
            'event_id'      => null,
            'status'        => null,
            'booth_type_id' => null,
            'zone_id'       => null,
            'floor_level'   => null,
            'is_featured'   => null,
            'search'        => null,
            'orderby'       => 'booth_number',
            'order'         => 'ASC',
            'limit'         => 50,
            'offset'        => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'b.event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where[] = 'b.status IN (' . implode(',', $placeholders) . ')';
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'b.status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['booth_type_id']) {
            $where[] = 'b.booth_type_id = %d';
            $values[] = $args['booth_type_id'];
        }

        if ($args['zone_id']) {
            $where[] = 'b.zone_id = %d';
            $values[] = $args['zone_id'];
        }

        if ($args['floor_level'] !== null) {
            $where[] = 'b.floor_level = %d';
            $values[] = $args['floor_level'];
        }

        if ($args['is_featured'] !== null) {
            $where[] = 'b.is_featured = %d';
            $values[] = $args['is_featured'] ? 1 : 0;
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(b.booth_number LIKE %s OR b.booth_name LIKE %s OR b.booth_name_ar LIKE %s OR c.company_name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $allowed_orderby = array('booth_number', 'status', 'floor_level', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? 'b.' . $args['orderby'] : 'b.booth_number';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Count total
        $count_query = "SELECT COUNT(*) FROM $table b
                        LEFT JOIN $companies_table c ON b.current_company_id = c.id
                        WHERE " . implode(' AND ', $where);
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, $values);
        }
        $total = intval($wpdb->get_var($count_query));

        // Get items with related info
        $query = "SELECT b.*,
                         t.name as type_name,
                         t.size_code,
                         t.booth_category,
                         t.base_price as type_base_price,
                         t.width_meters as type_width,
                         t.depth_meters as type_depth,
                         e.title as event_title,
                         c.company_name,
                         c.company_name_ar,
                         c.contact_name
                  FROM $table b
                  LEFT JOIN $types_table t ON b.booth_type_id = t.id
                  LEFT JOIN $events_table e ON b.event_id = e.id
                  LEFT JOIN $companies_table c ON b.current_company_id = c.id
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY $orderby $order
                  LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $query = $wpdb->prepare($query, $values);
        $items = $wpdb->get_results($query);

        return array(
            'items' => array_map(array('self', 'hydrate'), $items),
            'total' => $total,
        );
    }

    /**
     * Get available booths for an event
     *
     * @param int $event_id Event ID
     * @param array $args Additional filters
     * @return array
     */
    public static function get_available($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        $args['status'] = 'available';
        return self::get_by_event($event_id, $args);
    }

    /**
     * Create new booth
     *
     * @param array $data Booth data
     * @return int|false New ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        // Validate booth number uniqueness within event
        if (!empty($data['booth_number']) && !empty($data['event_id'])) {
            $existing = self::get_by_number($data['booth_number'], $data['event_id']);
            if ($existing) {
                return false; // Booth number already exists
            }
        }

        // Handle JSON fields
        if (isset($data['special_features']) && is_array($data['special_features'])) {
            $data['special_features'] = wp_json_encode($data['special_features']);
        }
        if (isset($data['neighbors']) && is_array($data['neighbors'])) {
            $data['neighbors'] = wp_json_encode($data['neighbors']);
        }

        $defaults = array(
            'event_id'           => 0,
            'venue_id'           => null,
            'zone_id'            => null,
            'booth_type_id'      => 0,
            'booth_number'       => '',
            'booth_name'         => null,
            'booth_name_ar'      => null,
            'position_x'         => 0,
            'position_y'         => 0,
            'rotation'           => 0,
            'floor_level'        => 1,
            'custom_width'       => null,
            'custom_depth'       => null,
            'custom_price'       => null,
            'status'             => 'available',
            'is_featured'        => 0,
            'current_company_id' => null,
            'current_booking_id' => null,
            'has_electricity'    => 1,
            'has_water'          => 0,
            'has_wifi'           => 1,
            'power_outlets'      => 2,
            'max_power_kw'       => 3.00,
            'special_features'   => null,
            'neighbors'          => null,
            'notes'              => null,
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update booth
     *
     * @param int $id Booth ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        // Handle JSON fields
        if (isset($data['special_features']) && is_array($data['special_features'])) {
            $data['special_features'] = wp_json_encode($data['special_features']);
        }
        if (isset($data['neighbors']) && is_array($data['neighbors'])) {
            $data['neighbors'] = wp_json_encode($data['neighbors']);
        }

        // Validate booth number uniqueness if changed
        if (isset($data['booth_number'])) {
            $existing = self::get($id);
            if ($existing && $data['booth_number'] !== $existing->booth_number) {
                $duplicate = self::get_by_number($data['booth_number'], $existing->event_id);
                if ($duplicate && $duplicate->id !== $id) {
                    return false; // Booth number already exists
                }
            }
        }

        $result = $wpdb->update($table, $data, array('id' => $id));

        return $result !== false;
    }

    /**
     * Delete booth
     *
     * @param int $id Booth ID
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        // Check if booth has active bookings
        $bookings_table = $wpdb->prefix . 'sc_booth_bookings';
        $active_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE booth_id = %d AND status NOT IN ('cancelled', 'completed')",
            $id
        ));

        if ($active_bookings > 0) {
            return false; // Cannot delete, active bookings exist
        }

        $result = $wpdb->delete($table, array('id' => $id));

        return $result !== false;
    }

    /**
     * Update booth status
     *
     * @param int $id Booth ID
     * @param string $status New status
     * @param int|null $company_id Company ID (for booking)
     * @param int|null $booking_id Booking ID
     * @return bool
     */
    public static function update_status($id, $status, $company_id = null, $booking_id = null) {
        $data = array('status' => $status);

        if ($status === 'available') {
            $data['current_company_id'] = null;
            $data['current_booking_id'] = null;
        } elseif ($company_id !== null) {
            $data['current_company_id'] = $company_id;
            if ($booking_id !== null) {
                $data['current_booking_id'] = $booking_id;
            }
        }

        return self::update($id, $data);
    }

    /**
     * Bulk create booths from pattern
     *
     * @param int $event_id Event ID
     * @param int $booth_type_id Booth type ID
     * @param string $prefix Booth number prefix (e.g., 'A')
     * @param int $start Start number
     * @param int $count Number of booths to create
     * @param array $common_data Common data for all booths
     * @return array Created booth IDs
     */
    public static function bulk_create($event_id, $booth_type_id, $prefix, $start, $count, $common_data = array()) {
        $created_ids = array();

        for ($i = 0; $i < $count; $i++) {
            $booth_number = $prefix . ($start + $i);

            $data = array_merge($common_data, array(
                'event_id'      => $event_id,
                'booth_type_id' => $booth_type_id,
                'booth_number'  => $booth_number,
            ));

            $id = self::create($data);
            if ($id) {
                $created_ids[] = $id;
            }
        }

        return $created_ids;
    }

    /**
     * Get statistics for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_stats($event_id) {
        global $wpdb;
        $table = self::get_table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured
             FROM $table
             WHERE event_id = %d",
            $event_id
        ));

        return array(
            'total'       => intval($stats->total ?? 0),
            'available'   => intval($stats->available ?? 0),
            'reserved'    => intval($stats->reserved ?? 0),
            'booked'      => intval($stats->booked ?? 0),
            'occupied'    => intval($stats->occupied ?? 0),
            'unavailable' => intval($stats->unavailable ?? 0),
            'featured'    => intval($stats->featured ?? 0),
        );
    }

    /**
     * Get floor plan data for visualization
     *
     * @param int $event_id Event ID
     * @param int|null $floor_level Optional floor filter
     * @return array
     */
    public static function get_floor_plan_data($event_id, $floor_level = null) {
        global $wpdb;
        $table = self::get_table();
        $types_table = $wpdb->prefix . 'sc_booth_types';
        $companies_table = $wpdb->prefix . 'sc_company_attendees';

        $where = 'b.event_id = %d';
        $values = array($event_id);

        if ($floor_level !== null) {
            $where .= ' AND b.floor_level = %d';
            $values[] = $floor_level;
        }

        $query = $wpdb->prepare(
            "SELECT b.id, b.booth_number, b.booth_name, b.status, b.is_featured,
                    b.position_x, b.position_y, b.rotation, b.floor_level,
                    COALESCE(b.custom_width, t.width_meters) as width,
                    COALESCE(b.custom_depth, t.depth_meters) as depth,
                    t.color, t.booth_category,
                    c.company_name, c.company_logo
             FROM $table b
             LEFT JOIN $types_table t ON b.booth_type_id = t.id
             LEFT JOIN $companies_table c ON b.current_company_id = c.id
             WHERE $where
             ORDER BY b.floor_level, b.booth_number",
            $values
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get booth statuses
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'available'   => array(
                'label'    => __('Available', 'sc_events'),
                'label_ar' => 'متاح',
                'color'    => '#22c55e',
            ),
            'reserved'    => array(
                'label'    => __('Reserved', 'sc_events'),
                'label_ar' => 'محجوز مؤقتاً',
                'color'    => '#f59e0b',
            ),
            'booked'      => array(
                'label'    => __('Booked', 'sc_events'),
                'label_ar' => 'محجوز',
                'color'    => '#3b82f6',
            ),
            'occupied'    => array(
                'label'    => __('Occupied', 'sc_events'),
                'label_ar' => 'مشغول',
                'color'    => '#8b5cf6',
            ),
            'unavailable' => array(
                'label'    => __('Unavailable', 'sc_events'),
                'label_ar' => 'غير متاح',
                'color'    => '#ef4444',
            ),
        );
    }

    /**
     * Hydrate object with additional data
     *
     * @param object $booth Booth object
     * @return object
     */
    private static function hydrate($booth) {
        if (!$booth) {
            return $booth;
        }

        // Decode JSON fields
        if (!empty($booth->special_features)) {
            $booth->special_features = json_decode($booth->special_features, true);
        } else {
            $booth->special_features = array();
        }

        if (!empty($booth->neighbors)) {
            $booth->neighbors = json_decode($booth->neighbors, true);
        } else {
            $booth->neighbors = array();
        }

        // Calculate effective dimensions
        $booth->effective_width = floatval($booth->custom_width ?? $booth->type_width ?? 3);
        $booth->effective_depth = floatval($booth->custom_depth ?? $booth->type_depth ?? 3);
        $booth->effective_area = $booth->effective_width * $booth->effective_depth;

        // Calculate effective price
        $booth->effective_price = floatval($booth->custom_price ?? $booth->type_base_price ?? 0);

        // Is available for booking
        $booth->is_available = $booth->status === 'available';

        // Get status info
        $statuses = self::get_statuses();
        if (isset($statuses[$booth->status])) {
            $booth->status_label = $statuses[$booth->status]['label'];
            $booth->status_color = $statuses[$booth->status]['color'];
        }

        return $booth;
    }
}
