<?php
/**
 * SC Workshop Model Class
 *
 * Workshops are independent ticketed sub-units that belong to an event.
 * Each workshop has its own tickets, attendees, scanner, and certificates.
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Workshop {

    private static $table;

    public static function get_table() {
        global $wpdb;
        if (!self::$table) {
            self::$table = $wpdb->prefix . 'sc_workshops';
        }
        return self::$table;
    }

    public static function get($id) {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug));
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_event($event_id, $args = array()) {
        $args = wp_parse_args($args, array(
            'event_id' => (int) $event_id,
            'limit'    => -1,
            'orderby'  => 'start_date',
            'order'    => 'ASC',
        ));
        return self::get_all($args);
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id' => null,
            'status'   => null,
            'search'   => null,
            'orderby'  => 'start_date',
            'order'    => 'ASC',
            'limit'    => 50,
            'offset'   => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['event_id'])) {
            $where[] = 'event_id = %d';
            $values[] = (int) $args['event_id'];
        }

        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $allowed_orderby = array('id', 'title', 'slug', 'start_date', 'end_date', 'status', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'start_date';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order";

        if ($args['limit'] > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = (int) $args['limit'];
            $values[] = (int) $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql);
        foreach ($rows as &$row) {
            $row = self::hydrate($row);
        }
        return $rows;
    }

    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $where = array('1=1');
        $values = array();

        if (!empty($args['event_id'])) {
            $where[] = 'event_id = %d';
            $values[] = (int) $args['event_id'];
        }
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s)';
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

    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $prepared = self::prepare_data($data);

        if (empty($prepared['title']) || empty($prepared['event_id'])) {
            return false;
        }

        if (empty($prepared['slug'])) {
            $prepared['slug'] = self::generate_unique_slug($prepared['title']);
        } else {
            $prepared['slug'] = self::generate_unique_slug($prepared['slug']);
        }

        if (empty($prepared['author_id'])) {
            $prepared['author_id'] = get_current_user_id();
        }

        $prepared['created_at'] = current_time('mysql');
        $prepared['updated_at'] = current_time('mysql');

        $ok = $wpdb->insert($table, $prepared);
        if ($ok) {
            $id = (int) $wpdb->insert_id;
            do_action('sc_workshop_created', $id, $prepared);
            return $id;
        }
        return false;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $prepared = self::prepare_data($data);

        if (isset($prepared['slug']) && !empty($prepared['slug'])) {
            $existing = self::get_by_slug($prepared['slug']);
            if ($existing && (int) $existing->id !== (int) $id) {
                $prepared['slug'] = self::generate_unique_slug($prepared['slug'], $id);
            }
        }

        $prepared['updated_at'] = current_time('mysql');

        $ok = $wpdb->update($table, $prepared, array('id' => (int) $id)) !== false;
        if ($ok) {
            do_action('sc_workshop_updated', (int) $id, $prepared);
        }
        return $ok;
    }

    /**
     * Delete workshop and cascade delete its tickets, attendees, checkins, certificates.
     */
    public static function delete($id) {
        global $wpdb;
        $id = (int) $id;
        $table = self::get_table();

        $tickets_table       = $wpdb->prefix . 'sc_tickets';
        $attendees_table     = $wpdb->prefix . 'sc_attendees';
        $checkins_table      = $wpdb->prefix . 'sc_checkins';
        $certificates_table  = $wpdb->prefix . 'sc_certificates';

        $wpdb->delete($checkins_table,     array('workshop_id' => $id), array('%d'));
        $wpdb->delete($certificates_table, array('workshop_id' => $id), array('%d'));
        $wpdb->delete($attendees_table,    array('workshop_id' => $id), array('%d'));
        $wpdb->delete($tickets_table,      array('workshop_id' => $id), array('%d'));

        $ok = $wpdb->delete($table, array('id' => $id), array('%d')) !== false;
        if ($ok) {
            do_action('sc_workshop_deleted', $id);
        }
        return $ok;
    }

    /**
     * Update cached stats (sold, revenue, checked_in)
     */
    public static function update_stats($id) {
        global $wpdb;
        $id = (int) $id;
        $table = self::get_table();
        $attendees_table = $wpdb->prefix . 'sc_attendees';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_sold,
                COALESCE(SUM(amount_paid), 0) AS total_revenue,
                SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) AS total_checked_in
             FROM $attendees_table
             WHERE workshop_id = %d AND payment_status = 'success' AND status = 'active'",
            $id
        ));

        if ($stats) {
            $wpdb->update($table, array(
                'total_sold'       => (int) $stats->total_sold,
                'total_revenue'    => (float) $stats->total_revenue,
                'total_checked_in' => (int) $stats->total_checked_in,
            ), array('id' => $id), array('%d', '%f', '%d'), array('%d'));
        }
    }

    /**
     * Get parent event title for display
     */
    public static function get_event_title($event_id) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'sc_events';
        return $wpdb->get_var($wpdb->prepare("SELECT title FROM $events_table WHERE id = %d", (int) $event_id));
    }

    /**
     * Sanitize and prepare data for insert/update
     */
    private static function prepare_data($data) {
        $prepared = array();

        $int_fields = array(
            'event_id', 'featured_image', 'banner_image', 'total_capacity',
            'min_tickets_per_order', 'max_tickets_per_order',
            'enable_certificates', 'certificate_template_id', 'auto_issue_certificate',
            'certificate_require_checkin', 'author_id'
        );
        foreach ($int_fields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $prepared[$f] = (int) $data[$f];
            } elseif (isset($data[$f]) && $data[$f] === '') {
                $prepared[$f] = null;
            }
        }

        $string_fields = array(
            'title', 'slug', 'timezone', 'venue_name', 'meeting_link', 'status'
        );
        foreach ($string_fields as $f) {
            if (isset($data[$f])) {
                $prepared[$f] = sanitize_text_field($data[$f]);
            }
        }

        if (isset($data['description'])) {
            $prepared['description'] = wp_kses_post($data['description']);
        }
        if (isset($data['excerpt'])) {
            $prepared['excerpt'] = sanitize_textarea_field($data['excerpt']);
        }
        if (isset($data['venue_address'])) {
            $prepared['venue_address'] = sanitize_textarea_field($data['venue_address']);
        }

        // Date/time
        foreach (array('start_date', 'end_date') as $f) {
            if (isset($data[$f])) {
                $prepared[$f] = $data[$f] ? sanitize_text_field($data[$f]) : null;
            }
        }
        foreach (array('start_time', 'end_time') as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $val = sanitize_text_field($data[$f]);
                // Convert "07:00 AM" to "07:00:00" if needed
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
                    $ts = strtotime($val);
                    if ($ts !== false) $val = date('H:i:s', $ts);
                }
                $prepared[$f] = $val;
            } elseif (isset($data[$f])) {
                $prepared[$f] = null;
            }
        }
        if (isset($data['registration_deadline'])) {
            $prepared['registration_deadline'] = $data['registration_deadline']
                ? date('Y-m-d H:i:s', strtotime($data['registration_deadline']))
                : null;
        }

        // Location type
        if (isset($data['location_type'])) {
            $val = sanitize_text_field($data['location_type']);
            if (in_array($val, array('offline', 'online', 'hybrid'), true)) {
                $prepared['location_type'] = $val;
            }
        }

        // Status validation
        if (isset($prepared['status'])) {
            $allowed = array('draft', 'publish', 'private', 'cancelled', 'completed', 'disabled');
            if (!in_array($prepared['status'], $allowed, true)) {
                $prepared['status'] = 'draft';
            }
        }

        // JSON fields
        if (isset($data['extra_fields'])) {
            $prepared['extra_fields'] = is_array($data['extra_fields'])
                ? wp_json_encode($data['extra_fields'])
                : $data['extra_fields'];
        }

        return $prepared;
    }

    private static function generate_unique_slug($base, $exclude_id = 0) {
        global $wpdb;
        $table = self::get_table();
        $slug = sanitize_title($base);
        if (empty($slug)) {
            $slug = 'workshop-' . time();
        }
        $original = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s AND id != %d LIMIT 1",
            $slug, (int) $exclude_id
        ))) {
            $i++;
            $slug = $original . '-' . $i;
        }
        return $slug;
    }

    private static function hydrate($row) {
        if (!$row) return $row;

        // Decode JSON fields
        if (!empty($row->extra_fields)) {
            $decoded = json_decode($row->extra_fields, true);
            $row->extra_fields_decoded = is_array($decoded) ? $decoded : array();
        } else {
            $row->extra_fields_decoded = array();
        }

        // Add convenience flags
        $row->is_past = !empty($row->end_date)
            ? strtotime($row->end_date) < strtotime(current_time('Y-m-d'))
            : (!empty($row->start_date) && strtotime($row->start_date) < strtotime(current_time('Y-m-d')));

        return $row;
    }
}
