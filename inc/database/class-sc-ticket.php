<?php
/**
 * SC Ticket Model Class
 *
 * Data Access Layer for Tickets table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Ticket {

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
            self::$table = $wpdb->prefix . 'sc_tickets';
        }
        return self::$table;
    }

    /**
     * Get single ticket by ID
     *
     * @param int $id Ticket ID
     * @return object|null Ticket object or null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($ticket) {
            $ticket = self::hydrate($ticket);
        }

        return $ticket;
    }

    /**
     * Get all tickets with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of ticket objects
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'    => null,
            'workshop_id' => null,
            'status'      => null,
            'is_active'   => null,
            'orderby'     => 'id',
            'order'       => 'DESC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['workshop_id']) {
            $where[] = 'workshop_id = %d';
            $values[] = $args['workshop_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = array('id', 'name', 'price', 'event_id', 'sort_order', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $tickets = $wpdb->get_results($sql);

        foreach ($tickets as &$ticket) {
            $ticket = self::hydrate($ticket);
        }

        return $tickets;
    }

    /**
     * Count tickets
     *
     * @param array $args Filter arguments
     * @return int Count
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $where = array('1=1');
        $values = array();

        if (!empty($args['event_id'])) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if (isset($args['is_active'])) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get tickets by event ID
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array Array of ticket objects
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'is_active' => true,
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

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = array('id', 'name', 'price', 'sort_order', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sort_order';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order",
            $values
        );

        $tickets = $wpdb->get_results($sql);

        foreach ($tickets as &$ticket) {
            $ticket = self::hydrate($ticket);
        }

        return $tickets;
    }

    /**
     * Get available tickets for an event (with stock)
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_available($event_id) {
        global $wpdb;
        $table = self::get_table();

        $sql = $wpdb->prepare(
            "SELECT * FROM $table
            WHERE event_id = %d
            AND is_active = 1
            AND (quantity = -1 OR (quantity - sold) > 0)
            AND (sale_start IS NULL OR sale_start <= %s)
            AND (sale_end IS NULL OR sale_end >= %s)
            ORDER BY sort_order ASC",
            $event_id,
            current_time('mysql'),
            current_time('mysql')
        );

        $tickets = $wpdb->get_results($sql);

        foreach ($tickets as &$ticket) {
            $ticket = self::hydrate($ticket);
        }

        return $tickets;
    }

    /**
     * Create new ticket
     *
     * @param array $data Ticket data
     * @return int|false Ticket ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate slug if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = self::generate_unique_slug($data['name'], $data['event_id']);
        }

        // Set sort order if not provided
        if (!isset($data['sort_order'])) {
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM $table WHERE event_id = %d",
                $data['event_id']
            ));
            $data['sort_order'] = ($max_order !== null) ? $max_order + 1 : 0;
        }

        $result = $wpdb->insert($table, $data);

        // Debug: Log any database errors
        if (!$result && $wpdb->last_error) {
            error_log('SC_Ticket::create() failed: ' . $wpdb->last_error);
            error_log('Data: ' . print_r($data, true));
        }

        if ($result) {
            $ticket_id = $wpdb->insert_id;
            do_action('sc_ticket_created', $ticket_id, $data);
            return $ticket_id;
        }

        return false;
    }

    /**
     * Update ticket
     *
     * @param int $id Ticket ID
     * @param array $data Ticket data
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
            do_action('sc_ticket_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete ticket
     *
     * @param int $id Ticket ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        $ticket = self::get($id);
        if (!$ticket) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_ticket_deleted', $id, $ticket);
            return true;
        }

        return false;
    }

    /**
     * Delete all tickets for an event
     *
     * @param int $event_id Event ID
     * @return int Number of deleted tickets
     */
    public static function delete_by_event($event_id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->delete($table, array('event_id' => $event_id), array('%d'));
    }

    /**
     * Get tickets for a workshop
     */
    public static function get_by_workshop($workshop_id, $args = array()) {
        $args['workshop_id'] = (int) $workshop_id;
        $args['event_id'] = null; // ensure we don't filter on event_id when listing workshop tickets
        if (!isset($args['orderby'])) $args['orderby'] = 'sort_order';
        if (!isset($args['order'])) $args['order'] = 'ASC';
        if (!isset($args['limit'])) $args['limit'] = 100;
        return self::get_all($args);
    }

    /**
     * Delete all tickets for a workshop
     */
    public static function delete_by_workshop($workshop_id) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->delete($table, array('workshop_id' => (int) $workshop_id), array('%d'));
    }

    /**
     * Increment sold count
     *
     * @param int $id Ticket ID
     * @param int $quantity Quantity to add
     * @return bool Success
     */
    public static function increment_sold($id, $quantity = 1) {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET sold = sold + %d, updated_at = %s WHERE id = %d",
            $quantity,
            current_time('mysql'),
            $id
        ));

        return $result !== false;
    }

    /**
     * Decrement sold count
     *
     * @param int $id Ticket ID
     * @param int $quantity Quantity to subtract
     * @return bool Success
     */
    public static function decrement_sold($id, $quantity = 1) {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET sold = GREATEST(0, sold - %d), updated_at = %s WHERE id = %d",
            $quantity,
            current_time('mysql'),
            $id
        ));

        return $result !== false;
    }

    /**
     * Check if ticket is available
     *
     * @param int $id Ticket ID
     * @param int $quantity Required quantity
     * @return bool|string True if available, error message otherwise
     */
    public static function is_available($id, $quantity = 1) {
        $ticket = self::get($id);

        if (!$ticket) {
            return __('Ticket not found.', 'sc_events');
        }

        if (!$ticket->is_active) {
            return __('This ticket is not available.', 'sc_events');
        }

        // Check stock
        if ($ticket->quantity > 0 && ($ticket->quantity - $ticket->sold) < $quantity) {
            return __('Not enough tickets available.', 'sc_events');
        }

        // Check sale dates
        $now = current_time('mysql');
        if ($ticket->sale_start && strtotime($ticket->sale_start) > strtotime($now)) {
            return __('Ticket sales have not started yet.', 'sc_events');
        }

        if ($ticket->sale_end && strtotime($ticket->sale_end) < strtotime($now)) {
            return __('Ticket sales have ended.', 'sc_events');
        }

        // Check min/max per order
        if ($ticket->min_per_order && $quantity < $ticket->min_per_order) {
            return sprintf(__('Minimum %d tickets per order.', 'sc_events'), $ticket->min_per_order);
        }

        if ($ticket->max_per_order && $quantity > $ticket->max_per_order) {
            return sprintf(__('Maximum %d tickets per order.', 'sc_events'), $ticket->max_per_order);
        }

        return true;
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
        $int_fields = array(
            'event_id', 'workshop_id', 'quantity', 'sold', 'min_per_order', 'max_per_order',
            'sort_order', 'allow_guests'
        );

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // Float fields
        $float_fields = array('price', 'early_bird_price');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('name', 'description', 'ticket_type');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Handle is_active field (accept status string or is_active boolean)
        if (isset($data['status'])) {
            $prepared['is_active'] = ($data['status'] === 'active' || $data['status'] === true || $data['status'] == 1) ? 1 : 0;
        } elseif (isset($data['is_active'])) {
            $prepared['is_active'] = ($data['is_active'] === true || $data['is_active'] === 'true' || $data['is_active'] == 1) ? 1 : 0;
        }

        // Handle enable_coupons field (from use_coupons)
        if (isset($data['use_coupons'])) {
            $prepared['enable_coupons'] = $data['use_coupons'] ? 1 : 0;
        } elseif (isset($data['enable_coupons'])) {
            $prepared['enable_coupons'] = $data['enable_coupons'] ? 1 : 0;
        }

        // Datetime fields
        $datetime_fields = array('sale_start', 'sale_end', 'early_bird_end');

        foreach ($datetime_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $prepared[$field] = date('Y-m-d H:i:s', strtotime($data[$field]));
            } elseif (array_key_exists($field, $data) && empty($data[$field])) {
                $prepared[$field] = null;
            }
        }

        // JSON fields
        if (isset($data['extra_fields'])) {
            if (is_array($data['extra_fields'])) {
                $prepared['extra_fields'] = wp_json_encode($data['extra_fields']);
            } else {
                $prepared['extra_fields'] = $data['extra_fields'];
            }
        }

        return $prepared;
    }

    /**
     * Generate unique slug for ticket
     *
     * @param string $name Ticket name
     * @param int $event_id Event ID
     * @return string Unique slug
     */
    private static function generate_unique_slug($name, $event_id) {
        global $wpdb;
        $table = self::get_table();

        // Create base slug from name
        $slug = sanitize_title($name);
        if (empty($slug)) {
            $slug = 'ticket';
        }

        // Check if slug exists for this event
        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s AND event_id = %d",
            $slug, $event_id
        ))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Hydrate ticket object
     *
     * @param object $ticket Raw ticket object
     * @return object Hydrated ticket
     */
    private static function hydrate($ticket) {
        // Decode JSON fields
        if (!empty($ticket->extra_fields)) {
            $decoded = json_decode($ticket->extra_fields, true);
            $ticket->extra_fields = is_array($decoded) ? $decoded : array();
        } else {
            $ticket->extra_fields = array();
        }

        // Add computed properties
        $ticket->available = $ticket->quantity < 0 ? PHP_INT_MAX : max(0, $ticket->quantity - $ticket->sold);
        $ticket->is_unlimited = $ticket->quantity < 0;
        $ticket->is_sold_out = !$ticket->is_unlimited && $ticket->available <= 0;

        // Check if on sale (use is_active instead of status)
        $now = current_time('mysql');
        $is_active = isset($ticket->is_active) ? $ticket->is_active : (isset($ticket->status) ? ($ticket->status === 'active') : true);
        $ticket->is_on_sale = $is_active
            && (!$ticket->sale_start || strtotime($ticket->sale_start) <= strtotime($now))
            && (!$ticket->sale_end || strtotime($ticket->sale_end) >= strtotime($now));

        // Check early bird (handle missing early_bird_price and early_bird_end)
        $early_bird_price = isset($ticket->early_bird_price) ? floatval($ticket->early_bird_price) : 0;
        $early_bird_end = isset($ticket->early_bird_end) ? $ticket->early_bird_end : null;
        $ticket->is_early_bird = $early_bird_price > 0
            && $early_bird_end
            && strtotime($early_bird_end) >= strtotime($now);

        // Current price
        $ticket->current_price = $ticket->is_early_bird ? $early_bird_price : $ticket->price;

        // Formatted price using dynamic currency
        $ticket->price_formatted = sc_format_price($ticket->current_price);

        return $ticket;
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get the event for this ticket
     *
     * @param int $ticket_id Ticket ID
     * @return object|null SC_Event object or null
     */
    public static function get_event($ticket_id) {
        $ticket = self::get($ticket_id);
        if (!$ticket || !$ticket->event_id) {
            return null;
        }
        return SC_Event::get($ticket->event_id);
    }

    /**
     * Get all attendees for this ticket
     *
     * @param int $ticket_id Ticket ID
     * @param array $args Optional arguments
     * @return array Array of SC_Attendee objects
     */
    public static function get_attendees($ticket_id, $args = array()) {
        $ticket = self::get($ticket_id);
        if (!$ticket) {
            return array();
        }
        $args['ticket_id'] = $ticket_id;
        return SC_Attendee::get_by_event($ticket->event_id, $args);
    }

    /**
     * Get sold count for this ticket
     *
     * @param int $ticket_id Ticket ID
     * @return int Number of sold tickets
     */
    public static function get_sold_count($ticket_id) {
        $ticket = self::get($ticket_id);
        return $ticket ? (int) $ticket->sold : 0;
    }

    /**
     * Get attendee count for this ticket (confirmed payments only)
     *
     * @param int $ticket_id Ticket ID
     * @return int Number of attendees
     */
    public static function get_attendee_count($ticket_id) {
        return SC_Attendee::count(array(
            'ticket_id' => $ticket_id,
            'payment_status' => 'success',
            'status' => 'active'
        ));
    }
}
