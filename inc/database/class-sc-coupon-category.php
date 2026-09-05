<?php
/**
 * SC Coupon Category Model Class
 *
 * Data Access Layer for Coupon Categories table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Coupon_Category {

    /**
     * Default category ID (General — protected, never deleted)
     */
    const DEFAULT_ID = 1;

    private static $table;

    /**
     * Get table name
     */
    public static function get_table() {
        global $wpdb;
        if (!self::$table) {
            self::$table = $wpdb->prefix . 'sc_coupon_categories';
        }
        return self::$table;
    }

    /**
     * Get single category by ID
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Get single category by slug
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug));
    }

    /**
     * Get default (General) category
     */
    public static function get_default() {
        return self::get(self::DEFAULT_ID);
    }

    /**
     * Get all categories
     *
     * @param array $args Filter args (search, limit, offset, with_counts)
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();
        $coupons_table = $wpdb->prefix . 'sc_coupons';

        $defaults = array(
            'search'      => null,
            'orderby'     => 'sort_order',
            'order'       => 'ASC',
            'limit'       => -1,
            'offset'      => 0,
            'with_counts' => false,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR slug LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $allowed_orderby = array('id', 'name', 'slug', 'sort_order', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sort_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $where_clause = implode(' AND ', $where);

        if ($args['with_counts']) {
            $sql = "SELECT c.*, COALESCE(cnt.coupon_count, 0) AS coupon_count
                    FROM $table c
                    LEFT JOIN (SELECT category_id, COUNT(*) AS coupon_count FROM $coupons_table GROUP BY category_id) cnt
                        ON c.id = cnt.category_id
                    WHERE $where_clause
                    ORDER BY c.$orderby $order, c.name ASC";
        } else {
            $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order, name ASC";
        }

        if ($args['limit'] > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = (int) $args['limit'];
            $values[] = (int) $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Count categories (with optional search)
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR slug LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get coupon count for a category
     */
    public static function get_coupon_count($category_id) {
        global $wpdb;
        $coupons_table = $wpdb->prefix . 'sc_coupons';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $coupons_table WHERE category_id = %d",
            $category_id
        ));
    }

    /**
     * Create new category
     *
     * @return int|false Category ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $prepared = self::prepare_data($data);

        if (empty($prepared['name'])) {
            return false;
        }

        // Auto-generate slug if missing
        if (empty($prepared['slug'])) {
            $prepared['slug'] = self::generate_unique_slug($prepared['name']);
        } else {
            $prepared['slug'] = self::generate_unique_slug($prepared['slug']);
        }

        $prepared['created_at'] = current_time('mysql');
        $prepared['updated_at'] = current_time('mysql');

        $result = $wpdb->insert($table, $prepared);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update category
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $prepared = self::prepare_data($data);

        // Don't allow flipping protected status
        unset($prepared['is_protected']);

        // Slug uniqueness if changed
        if (isset($prepared['slug']) && !empty($prepared['slug'])) {
            $existing = self::get_by_slug($prepared['slug']);
            if ($existing && (int) $existing->id !== (int) $id) {
                $prepared['slug'] = self::generate_unique_slug($prepared['slug'], $id);
            }
        }

        $prepared['updated_at'] = current_time('mysql');

        return $wpdb->update($table, $prepared, array('id' => (int) $id)) !== false;
    }

    /**
     * Delete category. If protected, refuses. Otherwise reassigns coupons to General.
     *
     * @return bool|WP_Error
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();
        $coupons_table = $wpdb->prefix . 'sc_coupons';

        $id = (int) $id;
        $category = self::get($id);

        if (!$category) {
            return new WP_Error('not_found', __('Category not found.', 'sc_events'));
        }

        if (!empty($category->is_protected) || $id === self::DEFAULT_ID) {
            return new WP_Error('protected', __('This category is protected and cannot be deleted.', 'sc_events'));
        }

        // Reassign coupons to General
        $wpdb->update(
            $coupons_table,
            array('category_id' => self::DEFAULT_ID),
            array('category_id' => $id),
            array('%d'),
            array('%d')
        );

        // Delete category
        return $wpdb->delete($table, array('id' => $id), array('%d')) !== false;
    }

    /**
     * Sanitize and prepare data for insert/update
     */
    private static function prepare_data($data) {
        $prepared = array();

        if (isset($data['name'])) {
            $prepared['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['slug'])) {
            $prepared['slug'] = sanitize_title($data['slug']);
        }
        if (isset($data['description'])) {
            $prepared['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['color'])) {
            $color = sanitize_text_field($data['color']);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#7c1314';
            }
            $prepared['color'] = $color;
        }
        if (isset($data['sort_order'])) {
            $prepared['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_protected'])) {
            $prepared['is_protected'] = (int) (bool) $data['is_protected'];
        }

        return $prepared;
    }

    /**
     * Generate unique slug, optionally excluding a specific ID
     */
    private static function generate_unique_slug($base, $exclude_id = 0) {
        global $wpdb;
        $table = self::get_table();

        $slug = sanitize_title($base);
        if (empty($slug)) {
            $slug = 'category-' . time();
        }

        $original = $slug;
        $counter = 1;

        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND id != %d LIMIT 1",
                $slug,
                (int) $exclude_id
            ));
            if (!$existing) {
                break;
            }
            $counter++;
            $slug = $original . '-' . $counter;
        }

        return $slug;
    }
}
