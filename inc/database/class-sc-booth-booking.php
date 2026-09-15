<?php
/**
 * SC Booth Booking Model Class
 *
 * Data Access Layer for Booth Bookings table (Reservations & Contracts)
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booth_Booking {

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
            self::$table = $wpdb->prefix . 'sc_booth_bookings';
        }
        return self::$table;
    }

    /**
     * Get single booking by ID
     *
     * @param int $id Booking ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($booking) {
            $booking = self::hydrate($booking);
        }

        return $booking;
    }

    /**
     * Get booking by reference
     *
     * @param string $booking_ref Booking reference
     * @return object|null
     */
    public static function get_by_ref($booking_ref) {
        global $wpdb;
        $table = self::get_table();

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE booking_ref = %s",
            $booking_ref
        ));

        if ($booking) {
            $booking = self::hydrate($booking);
        }

        return $booking;
    }

    /**
     * Get bookings by company
     *
     * @param int $company_id Company Attendee ID
     * @return array
     */
    public static function get_by_company($company_id) {
        global $wpdb;
        $table = self::get_table();

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE company_attendee_id = %d ORDER BY created_at DESC",
            $company_id
        ));

        return array_map(array('self', 'hydrate'), $bookings);
    }

    /**
     * Get bookings by booth
     *
     * @param int $booth_id Booth ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_booth($booth_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status' => null,
            'limit'  => 50,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('booth_id = %d');
        $values = array($booth_id);

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

        $values[] = $args['limit'];

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT %d";
        $query = $wpdb->prepare($query, $values);

        $bookings = $wpdb->get_results($query);

        return array_map(array('self', 'hydrate'), $bookings);
    }

    /**
     * Get list of bookings with filtering
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_list($args = array()) {
        global $wpdb;
        $table = self::get_table();
        $booths_table = $wpdb->prefix . 'sc_booths';
        $companies_table = $wpdb->prefix . 'sc_company_attendees';
        $events_table = $wpdb->prefix . 'sc_events';

        $defaults = array(
            'event_id'       => null,
            'booth_id'       => null,
            'company_id'     => null,
            'status'         => null,
            'payment_status' => null,
            'search'         => null,
            'date_from'      => null,
            'date_to'        => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'bk.event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['booth_id']) {
            $where[] = 'bk.booth_id = %d';
            $values[] = $args['booth_id'];
        }

        if ($args['company_id']) {
            $where[] = 'bk.company_attendee_id = %d';
            $values[] = $args['company_id'];
        }

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where[] = 'bk.status IN (' . implode(',', $placeholders) . ')';
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'bk.status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['payment_status']) {
            if (is_array($args['payment_status'])) {
                $placeholders = array_fill(0, count($args['payment_status']), '%s');
                $where[] = 'bk.payment_status IN (' . implode(',', $placeholders) . ')';
                $values = array_merge($values, $args['payment_status']);
            } else {
                $where[] = 'bk.payment_status = %s';
                $values[] = $args['payment_status'];
            }
        }

        if ($args['date_from']) {
            $where[] = 'bk.start_date >= %s';
            $values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'bk.end_date <= %s';
            $values[] = $args['date_to'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(bk.booking_ref LIKE %s OR c.company_name LIKE %s OR b.booth_number LIKE %s OR bk.commercial_registry_no LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $allowed_orderby = array('created_at', 'start_date', 'total_amount', 'status', 'payment_status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? 'bk.' . $args['orderby'] : 'bk.created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Count total
        $count_query = "SELECT COUNT(*) FROM $table bk
                        LEFT JOIN $booths_table b ON bk.booth_id = b.id
                        LEFT JOIN $companies_table c ON bk.company_attendee_id = c.id
                        WHERE " . implode(' AND ', $where);
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, $values);
        }
        $total = intval($wpdb->get_var($count_query));

        // Get items with related info
        $query = "SELECT bk.*,
                         b.booth_number,
                         b.booth_name,
                         c.company_name,
                         c.company_name_ar,
                         c.contact_name,
                         c.contact_email,
                         c.contact_phone,
                         e.title as event_title
                  FROM $table bk
                  LEFT JOIN $booths_table b ON bk.booth_id = b.id
                  LEFT JOIN $companies_table c ON bk.company_attendee_id = c.id
                  LEFT JOIN $events_table e ON bk.event_id = e.id
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
     * Create new booking
     *
     * @param array $data Booking data
     * @return int|false New ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        // Generate booking reference
        if (empty($data['booking_ref'])) {
            $data['booking_ref'] = self::generate_booking_ref();
        }

        // Set booking date if not provided
        if (empty($data['booking_date'])) {
            $data['booking_date'] = current_time('mysql');
        }

        // Calculate balance due
        if (!isset($data['balance_due']) && isset($data['total_amount'])) {
            $deposit_paid = floatval($data['deposit_paid'] ?? 0);
            $data['balance_due'] = floatval($data['total_amount']) - $deposit_paid;
        }

        // Handle JSON fields
        if (isset($data['selected_extras']) && is_array($data['selected_extras'])) {
            $data['selected_extras'] = wp_json_encode($data['selected_extras']);
        }
        if (isset($data['zatca_qr_code']) && is_array($data['zatca_qr_code'])) {
            $data['zatca_qr_code'] = wp_json_encode($data['zatca_qr_code']);
        }

        $defaults = array(
            'event_id'              => 0,
            'booth_id'              => 0,
            'company_attendee_id'   => 0,
            'booking_ref'           => '',
            'booking_date'          => current_time('mysql'),
            'start_date'            => '',
            'end_date'              => '',
            'base_price'            => 0.00,
            'extras_price'          => 0.00,
            'discount_amount'       => 0.00,
            'tax_amount'            => 0.00,
            'total_amount'          => 0.00,
            'deposit_required'      => 0.00,
            'deposit_paid'          => 0.00,
            'balance_due'           => 0.00,
            'balance_paid'          => 0.00,
            'payment_status'        => 'pending',
            'contract_signed'       => 0,
            'terms_accepted'        => 0,
            'status'                => 'pending',
            'created_by'            => get_current_user_id(),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        $booking_id = $wpdb->insert_id;

        // Update booth status
        if (!empty($data['booth_id'])) {
            SC_Booth::update_status(
                $data['booth_id'],
                'booked',
                $data['company_attendee_id'],
                $booking_id
            );

            // Update booth type availability
            $booth = SC_Booth::get($data['booth_id']);
            if ($booth && !empty($booth->booth_type_id)) {
                SC_Booth_Type::update_availability($booth->booth_type_id, -1);
            }
        }

        return $booking_id;
    }

    /**
     * Update booking
     *
     * @param int $id Booking ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        // Handle JSON fields
        if (isset($data['selected_extras']) && is_array($data['selected_extras'])) {
            $data['selected_extras'] = wp_json_encode($data['selected_extras']);
        }

        // Recalculate balance if payments changed
        if (isset($data['deposit_paid']) || isset($data['balance_paid'])) {
            $existing = self::get($id);
            if ($existing) {
                $deposit_paid = floatval($data['deposit_paid'] ?? $existing->deposit_paid);
                $balance_paid = floatval($data['balance_paid'] ?? $existing->balance_paid);
                $data['balance_due'] = floatval($existing->total_amount) - $deposit_paid - $balance_paid;

                // Update payment status
                if ($deposit_paid + $balance_paid >= floatval($existing->total_amount)) {
                    $data['payment_status'] = 'fully_paid';
                } elseif ($deposit_paid > 0) {
                    $data['payment_status'] = 'deposit_paid';
                }
            }
        }

        $result = $wpdb->update($table, $data, array('id' => $id));

        return $result !== false;
    }

    /**
     * Cancel booking
     *
     * @param int $id Booking ID
     * @param string $reason Cancellation reason
     * @param int|null $cancelled_by User ID
     * @return bool
     */
    public static function cancel($id, $reason = '', $cancelled_by = null) {
        global $wpdb;

        $booking = self::get($id);
        if (!$booking) {
            return false;
        }

        $result = self::update($id, array(
            'status'              => 'cancelled',
            'cancelled_at'        => current_time('mysql'),
            'cancelled_by'        => $cancelled_by ?? get_current_user_id(),
            'cancellation_reason' => $reason,
        ));

        if ($result) {
            // Update booth status back to available
            SC_Booth::update_status($booking->booth_id, 'available');

            // Restore booth type availability
            $booth = SC_Booth::get($booking->booth_id);
            if ($booth && !empty($booth->booth_type_id)) {
                SC_Booth_Type::update_availability($booth->booth_type_id, 1);
            }
        }

        return $result;
    }

    /**
     * Confirm booking
     *
     * @param int $id Booking ID
     * @param int|null $confirmed_by User ID
     * @return bool
     */
    public static function confirm($id, $confirmed_by = null) {
        return self::update($id, array(
            'status'       => 'confirmed',
            'confirmed_at' => current_time('mysql'),
            'confirmed_by' => $confirmed_by ?? get_current_user_id(),
        ));
    }

    /**
     * Record deposit payment
     *
     * @param int $id Booking ID
     * @param float $amount Amount paid
     * @param int|null $payment_id Payment record ID
     * @return bool
     */
    public static function record_deposit($id, $amount, $payment_id = null) {
        $data = array(
            'deposit_paid'       => $amount,
            'deposit_paid_at'    => current_time('mysql'),
            'payment_status'     => 'deposit_paid',
        );

        if ($payment_id) {
            $data['deposit_payment_id'] = $payment_id;
        }

        return self::update($id, $data);
    }

    /**
     * Record balance payment
     *
     * @param int $id Booking ID
     * @param float $amount Amount paid
     * @param int|null $payment_id Payment record ID
     * @return bool
     */
    public static function record_balance_payment($id, $amount, $payment_id = null) {
        $booking = self::get($id);
        if (!$booking) {
            return false;
        }

        $new_balance_paid = floatval($booking->balance_paid) + $amount;

        $data = array(
            'balance_paid'    => $new_balance_paid,
            'balance_paid_at' => current_time('mysql'),
        );

        if ($payment_id) {
            $data['balance_payment_id'] = $payment_id;
        }

        // Check if fully paid
        $total_paid = floatval($booking->deposit_paid) + $new_balance_paid;
        if ($total_paid >= floatval($booking->total_amount)) {
            $data['payment_status'] = 'fully_paid';
        }

        return self::update($id, $data);
    }

    /**
     * Sign contract
     *
     * @param int $id Booking ID
     * @param string $signer_name Signer name
     * @param string $signer_title Signer title
     * @param int|null $contract_file Attachment ID of signed contract
     * @return bool
     */
    public static function sign_contract($id, $signer_name, $signer_title = '', $contract_file = null) {
        $data = array(
            'contract_signed'       => 1,
            'contract_signed_at'    => current_time('mysql'),
            'contract_signer_name'  => $signer_name,
            'contract_signer_title' => $signer_title,
        );

        if ($contract_file) {
            $data['contract_file'] = $contract_file;
        }

        return self::update($id, $data);
    }

    /**
     * Accept terms
     *
     * @param int $id Booking ID
     * @return bool
     */
    public static function accept_terms($id) {
        return self::update($id, array(
            'terms_accepted'    => 1,
            'terms_accepted_at' => current_time('mysql'),
        ));
    }

    /**
     * Check in exhibitor
     *
     * @param int $id Booking ID
     * @return bool
     */
    public static function check_in($id) {
        $result = self::update($id, array(
            'status'        => 'active',
            'checked_in'    => 1,
            'checked_in_at' => current_time('mysql'),
        ));

        if ($result) {
            $booking = self::get($id);
            if ($booking) {
                SC_Booth::update_status($booking->booth_id, 'occupied', $booking->company_attendee_id, $id);
            }
        }

        return $result;
    }

    /**
     * Check out exhibitor
     *
     * @param int $id Booking ID
     * @return bool
     */
    public static function check_out($id) {
        $result = self::update($id, array(
            'status'         => 'completed',
            'checked_out'    => 1,
            'checked_out_at' => current_time('mysql'),
        ));

        if ($result) {
            $booking = self::get($id);
            if ($booking) {
                SC_Booth::update_status($booking->booth_id, 'available');
            }
        }

        return $result;
    }

    /**
     * Get overdue bookings
     *
     * @param int|null $event_id Optional event filter
     * @return array
     */
    public static function get_overdue($event_id = null) {
        global $wpdb;
        $table = self::get_table();

        $where = "balance_due_date < CURDATE() AND payment_status NOT IN ('fully_paid', 'refunded', 'cancelled') AND status != 'cancelled'";
        $values = array();

        if ($event_id) {
            $where .= ' AND event_id = %d';
            $values[] = $event_id;
        }

        $query = "SELECT * FROM $table WHERE $where ORDER BY balance_due_date ASC";

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $bookings = $wpdb->get_results($query);

        // Mark as overdue
        foreach ($bookings as $booking) {
            if ($booking->payment_status !== 'overdue') {
                self::update($booking->id, array('payment_status' => 'overdue'));
            }
        }

        return array_map(array('self', 'hydrate'), $bookings);
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
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN payment_status = 'fully_paid' THEN 1 ELSE 0 END) as fully_paid,
                SUM(CASE WHEN payment_status = 'deposit_paid' THEN 1 ELSE 0 END) as deposit_paid,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                SUM(total_amount) as total_revenue,
                SUM(deposit_paid + balance_paid) as collected_revenue,
                SUM(balance_due) as pending_revenue
             FROM $table
             WHERE event_id = %d AND status != 'cancelled'",
            $event_id
        ));

        return array(
            'total'             => intval($stats->total ?? 0),
            'pending'           => intval($stats->pending ?? 0),
            'confirmed'         => intval($stats->confirmed ?? 0),
            'active'            => intval($stats->active ?? 0),
            'completed'         => intval($stats->completed ?? 0),
            'cancelled'         => intval($stats->cancelled ?? 0),
            'fully_paid'        => intval($stats->fully_paid ?? 0),
            'deposit_paid'      => intval($stats->deposit_paid ?? 0),
            'overdue'           => intval($stats->overdue ?? 0),
            'total_revenue'     => floatval($stats->total_revenue ?? 0),
            'collected_revenue' => floatval($stats->collected_revenue ?? 0),
            'pending_revenue'   => floatval($stats->pending_revenue ?? 0),
        );
    }

    /**
     * Generate unique booking reference
     *
     * @return string
     */
    private static function generate_booking_ref() {
        global $wpdb;
        $table = self::get_table();

        do {
            $ref = 'BK-' . strtoupper(wp_generate_password(8, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE booking_ref = %s",
                $ref
            ));
        } while ($exists);

        return $ref;
    }

    /**
     * Get booking statuses
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'pending'   => array(
                'label'    => __('Pending', 'sc_events'),
                'label_ar' => 'قيد الانتظار',
                'color'    => '#f59e0b',
            ),
            'confirmed' => array(
                'label'    => __('Confirmed', 'sc_events'),
                'label_ar' => 'مؤكد',
                'color'    => '#3b82f6',
            ),
            'active'    => array(
                'label'    => __('Active', 'sc_events'),
                'label_ar' => 'نشط',
                'color'    => '#22c55e',
            ),
            'completed' => array(
                'label'    => __('Completed', 'sc_events'),
                'label_ar' => 'مكتمل',
                'color'    => '#6b7280',
            ),
            'cancelled' => array(
                'label'    => __('Cancelled', 'sc_events'),
                'label_ar' => 'ملغى',
                'color'    => '#ef4444',
            ),
            'no_show'   => array(
                'label'    => __('No Show', 'sc_events'),
                'label_ar' => 'لم يحضر',
                'color'    => '#9ca3af',
            ),
        );
    }

    /**
     * Get payment statuses
     *
     * @return array
     */
    public static function get_payment_statuses() {
        return array(
            'pending'      => array(
                'label'    => __('Pending', 'sc_events'),
                'label_ar' => 'قيد الانتظار',
                'color'    => '#f59e0b',
            ),
            'deposit_paid' => array(
                'label'    => __('Deposit Paid', 'sc_events'),
                'label_ar' => 'العربون مدفوع',
                'color'    => '#3b82f6',
            ),
            'fully_paid'   => array(
                'label'    => __('Fully Paid', 'sc_events'),
                'label_ar' => 'مدفوع بالكامل',
                'color'    => '#22c55e',
            ),
            'overdue'      => array(
                'label'    => __('Overdue', 'sc_events'),
                'label_ar' => 'متأخر',
                'color'    => '#ef4444',
            ),
            'refunded'     => array(
                'label'    => __('Refunded', 'sc_events'),
                'label_ar' => 'مسترد',
                'color'    => '#9ca3af',
            ),
            'cancelled'    => array(
                'label'    => __('Cancelled', 'sc_events'),
                'label_ar' => 'ملغى',
                'color'    => '#6b7280',
            ),
        );
    }

    /**
     * Hydrate object with additional data
     *
     * @param object $booking Booking object
     * @return object
     */
    private static function hydrate($booking) {
        if (!$booking) {
            return $booking;
        }

        // Decode JSON fields
        if (!empty($booking->selected_extras)) {
            $booking->selected_extras = json_decode($booking->selected_extras, true);
        } else {
            $booking->selected_extras = array();
        }

        // Calculate total paid
        $booking->total_paid = floatval($booking->deposit_paid) + floatval($booking->balance_paid);

        // Calculate remaining balance
        $booking->remaining_balance = floatval($booking->total_amount) - $booking->total_paid;

        // Payment progress percentage
        if (floatval($booking->total_amount) > 0) {
            $booking->payment_progress = round(($booking->total_paid / floatval($booking->total_amount)) * 100);
        } else {
            $booking->payment_progress = 0;
        }

        // Is overdue
        $booking->is_overdue = !empty($booking->balance_due_date) &&
                               strtotime($booking->balance_due_date) < current_time('timestamp') &&
                               $booking->remaining_balance > 0 &&
                               $booking->status !== 'cancelled';

        // Get status info
        $statuses = self::get_statuses();
        if (isset($statuses[$booking->status])) {
            $booking->status_label = $statuses[$booking->status]['label'];
            $booking->status_color = $statuses[$booking->status]['color'];
        }

        $payment_statuses = self::get_payment_statuses();
        if (isset($payment_statuses[$booking->payment_status])) {
            $booking->payment_status_label = $payment_statuses[$booking->payment_status]['label'];
            $booking->payment_status_color = $payment_statuses[$booking->payment_status]['color'];
        }

        return $booking;
    }
}
