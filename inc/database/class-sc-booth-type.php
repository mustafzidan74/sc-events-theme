<?php
/**
 * SC Booth Type Model Class
 *
 * Data Access Layer for Booth Types table (Exhibition booth sizes/packages)
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booth_Type {

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
            self::$table = $wpdb->prefix . 'sc_booth_types';
        }
        return self::$table;
    }

    /**
     * Get single booth type by ID
     *
     * @param int $id Booth Type ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($type) {
            $type = self::hydrate($type);
        }

        return $type;
    }

    /**
     * Get booth type by slug
     *
     * @param string $slug Booth type slug
     * @param int $event_id Event ID
     * @return object|null
     */
    public static function get_by_slug($slug, $event_id) {
        global $wpdb;
        $table = self::get_table();

        $type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s AND event_id = %d",
            $slug,
            $event_id
        ));

        if ($type) {
            $type = self::hydrate($type);
        }

        return $type;
    }

    /**
     * Get booth types by event
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'is_active' => null,
            'category'  => null,
            'orderby'   => 'sort_order',
            'order'     => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('event_id = %d');
        $values = array($event_id);

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        if ($args['category']) {
            $where[] = 'booth_category = %s';
            $values[] = $args['category'];
        }

        $orderby = in_array($args['orderby'], array('sort_order', 'name', 'base_price', 'area_sqm', 'created_at'))
            ? $args['orderby'] : 'sort_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY $orderby $order";
        $query = $wpdb->prepare($query, $values);

        $types = $wpdb->get_results($query);

        return array_map(array('self', 'hydrate'), $types);
    }

    /**
     * Get list of booth types with filtering
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_list($args = array()) {
        global $wpdb;
        $table = self::get_table();
        $events_table = $wpdb->prefix . 'sc_events';

        $defaults = array(
            'event_id'  => null,
            'is_active' => null,
            'category'  => null,
            'search'    => null,
            'orderby'   => 'sort_order',
            'order'     => 'ASC',
            'limit'     => 50,
            'offset'    => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 't.event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['is_active'] !== null) {
            $where[] = 't.is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        if ($args['category']) {
            $where[] = 't.booth_category = %s';
            $values[] = $args['category'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(t.name LIKE %s OR t.name_ar LIKE %s OR t.size_code LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $orderby = in_array($args['orderby'], array('sort_order', 'name', 'base_price', 'area_sqm', 'created_at'))
            ? 't.' . $args['orderby'] : 't.sort_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Count total
        $count_query = "SELECT COUNT(*) FROM $table t WHERE " . implode(' AND ', $where);
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, $values);
        }
        $total = intval($wpdb->get_var($count_query));

        // Get items with event info
        $query = "SELECT t.*, e.title as event_title
                  FROM $table t
                  LEFT JOIN $events_table e ON t.event_id = e.id
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
     * Create new booth type
     *
     * @param array $data Booth type data
     * @return int|false New ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }

        // Ensure unique slug within event
        $data['slug'] = self::unique_slug($data['slug'], $data['event_id']);

        // Set available_quantity to total_quantity if not set
        if (!isset($data['available_quantity']) && isset($data['total_quantity'])) {
            $data['available_quantity'] = $data['total_quantity'];
        }

        // Handle JSON fields
        if (isset($data['inclusions']) && is_array($data['inclusions'])) {
            $data['inclusions'] = wp_json_encode($data['inclusions']);
        }

        $defaults = array(
            'event_id'           => 0,
            'name'               => '',
            'name_ar'            => null,
            'slug'               => '',
            'description'        => null,
            'description_ar'     => null,
            'size_code'          => '3x3',
            'width_meters'       => 3.00,
            'depth_meters'       => 3.00,
            'booth_category'     => 'standard',
            'base_price'         => 0.00,
            'deposit_amount'     => 0.00,
            'deposit_percentage' => 0.00,
            'price_per_sqm'      => 0.00,
            'inclusions'         => null,
            'total_quantity'     => 0,
            'available_quantity' => 0,
            'is_active'          => 1,
            'color'              => '#3B82F6',
            'icon'               => 'fa-store',
            'image'              => null,
            'sort_order'         => 0,
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update booth type
     *
     * @param int $id Booth Type ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        // Handle JSON fields
        if (isset($data['inclusions']) && is_array($data['inclusions'])) {
            $data['inclusions'] = wp_json_encode($data['inclusions']);
        }

        // Update slug if name changed and slug not provided
        if (isset($data['name']) && !isset($data['slug'])) {
            $existing = self::get($id);
            if ($existing) {
                $new_slug = sanitize_title($data['name']);
                if ($new_slug !== $existing->slug) {
                    $data['slug'] = self::unique_slug($new_slug, $existing->event_id, $id);
                }
            }
        }

        $result = $wpdb->update($table, $data, array('id' => $id));

        return $result !== false;
    }

    /**
     * Delete booth type
     *
     * @param int $id Booth Type ID
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        // Check if booths are using this type
        $booths_table = $wpdb->prefix . 'sc_booths';
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $booths_table WHERE booth_type_id = %d",
            $id
        ));

        if ($in_use > 0) {
            return false; // Cannot delete, booths are using this type
        }

        $result = $wpdb->delete($table, array('id' => $id));

        return $result !== false;
    }

    /**
     * Update availability count
     *
     * @param int $id Booth Type ID
     * @param int $delta Change in quantity (negative for decrease)
     * @return bool
     */
    public static function update_availability($id, $delta) {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET available_quantity = GREATEST(0, available_quantity + %d) WHERE id = %d",
            $delta,
            $id
        ));

        return $result !== false;
    }

    /**
     * Get categories with counts for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_categories_with_counts($event_id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT booth_category,
                    COUNT(*) as type_count,
                    SUM(total_quantity) as total_booths,
                    SUM(available_quantity) as available_booths
             FROM $table
             WHERE event_id = %d AND is_active = 1
             GROUP BY booth_category
             ORDER BY booth_category",
            $event_id
        ));
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
                COUNT(*) as total_types,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types,
                SUM(total_quantity) as total_booths,
                SUM(available_quantity) as available_booths,
                SUM(total_quantity - available_quantity) as booked_booths,
                MIN(base_price) as min_price,
                MAX(base_price) as max_price,
                AVG(base_price) as avg_price
             FROM $table
             WHERE event_id = %d",
            $event_id
        ));

        return array(
            'total_types'      => intval($stats->total_types ?? 0),
            'active_types'     => intval($stats->active_types ?? 0),
            'total_booths'     => intval($stats->total_booths ?? 0),
            'available_booths' => intval($stats->available_booths ?? 0),
            'booked_booths'    => intval($stats->booked_booths ?? 0),
            'min_price'        => floatval($stats->min_price ?? 0),
            'max_price'        => floatval($stats->max_price ?? 0),
            'avg_price'        => floatval($stats->avg_price ?? 0),
        );
    }

    /**
     * Generate unique slug
     *
     * @param string $slug Base slug
     * @param int $event_id Event ID
     * @param int $exclude_id ID to exclude (for updates)
     * @return string
     */
    private static function unique_slug($slug, $event_id, $exclude_id = 0) {
        global $wpdb;
        $table = self::get_table();

        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND event_id = %d AND id != %d",
                $slug,
                $event_id,
                $exclude_id
            ));

            if (!$exists) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Hydrate object with additional data
     *
     * @param object $type Booth type object
     * @return object
     */
    private static function hydrate($type) {
        if (!$type) {
            return $type;
        }

        // Decode JSON fields
        if (!empty($type->inclusions)) {
            $type->inclusions = json_decode($type->inclusions, true);
        } else {
            $type->inclusions = array();
        }

        // Calculate computed fields
        $type->area_sqm_computed = floatval($type->width_meters) * floatval($type->depth_meters);

        // Effective deposit amount
        if (floatval($type->deposit_percentage) > 0) {
            $type->effective_deposit = floatval($type->base_price) * floatval($type->deposit_percentage) / 100;
        } else {
            $type->effective_deposit = floatval($type->deposit_amount);
        }

        // Check availability
        $type->is_available = intval($type->available_quantity) > 0 && intval($type->is_active) === 1;

        return $type;
    }

    /**
     * Get booth categories
     *
     * @return array
     */
    public static function get_booth_categories() {
        return array(
            'standard'   => array(
                'label'    => __('Standard', 'sc_events'),
                'label_ar' => 'عادي',
                'icon'     => 'fa-square',
            ),
            'corner'     => array(
                'label'    => __('Corner', 'sc_events'),
                'label_ar' => 'ركني',
                'icon'     => 'fa-border-top-left',
            ),
            'island'     => array(
                'label'    => __('Island', 'sc_events'),
                'label_ar' => 'جزيرة',
                'icon'     => 'fa-border-all',
            ),
            'peninsula'  => array(
                'label'    => __('Peninsula', 'sc_events'),
                'label_ar' => 'شبه جزيرة',
                'icon'     => 'fa-border-center-v',
            ),
            'inline'     => array(
                'label'    => __('Inline', 'sc_events'),
                'label_ar' => 'صف',
                'icon'     => 'fa-grip-lines',
            ),
            'custom'     => array(
                'label'    => __('Custom', 'sc_events'),
                'label_ar' => 'مخصص',
                'icon'     => 'fa-cogs',
            ),
        );
    }

    /**
     * Get common booth sizes
     *
     * @return array
     */
    public static function get_common_sizes() {
        return array(
            '3x3'  => array('width' => 3, 'depth' => 3, 'label' => '3x3m (9m²)'),
            '3x4'  => array('width' => 3, 'depth' => 4, 'label' => '3x4m (12m²)'),
            '4x4'  => array('width' => 4, 'depth' => 4, 'label' => '4x4m (16m²)'),
            '6x3'  => array('width' => 6, 'depth' => 3, 'label' => '6x3m (18m²)'),
            '6x6'  => array('width' => 6, 'depth' => 6, 'label' => '6x6m (36m²)'),
            '9x6'  => array('width' => 9, 'depth' => 6, 'label' => '9x6m (54m²)'),
            '9x9'  => array('width' => 9, 'depth' => 9, 'label' => '9x9m (81m²)'),
            '12x9' => array('width' => 12, 'depth' => 9, 'label' => '12x9m (108m²)'),
        );
    }
}
