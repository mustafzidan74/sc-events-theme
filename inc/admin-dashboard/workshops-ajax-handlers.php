<?php
/**
 * Workshops AJAX Handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_workshops_verify_request() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

/**
 * Field-level validation for a workshop save. Mirrors the checks in the form's
 * JavaScript so a request that skips the page still can't store bad data.
 *
 * @return array field => message
 */
function sc_workshop_validate($data) {
    global $wpdb;
    $errors = array();

    if (!$data['event_id'] || !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $data['event_id']))) {
        $errors['event_id'] = __('Choose the event this workshop belongs to.', 'sc_events');
    }
    if (trim($data['title']) === '') {
        $errors['title'] = __('Title is required — it shows on the card and the ticket.', 'sc_events');
    } elseif (mb_strlen($data['title']) > 255) {
        $errors['title'] = __('Keep the title under 255 characters.', 'sc_events');
    }

    $date_ok = function ($d) {
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    };
    $time_ok = function ($t) {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
    };

    if ($data['start_date'] === '' || !$date_ok($data['start_date'])) {
        $errors['start_date'] = __('Pick the day the workshop starts.', 'sc_events');
    }
    if ($data['end_date'] !== '' && !$date_ok($data['end_date'])) {
        $errors['end_date'] = __('That end date is not a valid date.', 'sc_events');
    } elseif ($data['end_date'] !== '' && empty($errors['start_date']) && $data['end_date'] < $data['start_date']) {
        $errors['end_date'] = __('Ends before it starts. Check the end date.', 'sc_events');
    }
    foreach (array('start_time', 'end_time') as $t) {
        if ($data[$t] !== '' && !$time_ok($data[$t])) {
            $errors[$t] = __('Use a time like 14:30.', 'sc_events');
        }
    }
    $same_day = $data['end_date'] === '' || $data['end_date'] === $data['start_date'];
    if ($same_day && $data['start_time'] !== '' && $data['end_time'] !== '' && empty($errors['start_time']) && empty($errors['end_time'])
        && substr($data['end_time'], 0, 5) <= substr($data['start_time'], 0, 5)) {
        $errors['end_time'] = __('Ends before it starts. Check the end time.', 'sc_events');
    }

    if (in_array($data['location_type'], array('online', 'hybrid'), true)) {
        if ($data['meeting_link'] === '') {
            $errors['meeting_link'] = __('Online and hybrid workshops need a meeting link.', 'sc_events');
        } elseif (!filter_var($data['meeting_link'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $data['meeting_link'])) {
            // Format check only: wp_http_validate_url() also resolves DNS on every save.
            $errors['meeting_link'] = __('Enter a full link starting with https://', 'sc_events');
        }
    }

    if ($data['total_capacity'] < 0) {
        $errors['total_capacity'] = __('Capacity can’t be negative. Use 0 for unlimited.', 'sc_events');
    }
    if ($data['min_tickets_per_order'] < 1) {
        $errors['min_tickets_per_order'] = __('At least 1.', 'sc_events');
    }
    if ($data['max_tickets_per_order'] < max(1, $data['min_tickets_per_order'])) {
        $errors['max_tickets_per_order'] = __('Must be at least the minimum per order.', 'sc_events');
    }
    if ($data['registration_deadline'] !== '' && strtotime($data['registration_deadline']) === false) {
        $errors['registration_deadline'] = __('That is not a valid date and time.', 'sc_events');
    }

    return $errors;
}

/**
 * Save (create or update) workshop
 */
add_action('wp_ajax_sc_save_workshop', 'sc_save_workshop_handler');
function sc_save_workshop_handler() {
    sc_workshops_verify_request();

    // WordPress slashes $_POST; without this an apostrophe in the title was stored escaped.
    $post = wp_unslash($_POST);
    $id = isset($post['id']) ? absint($post['id']) : 0;
    $existing = $id ? SC_Workshop::get($id) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('This workshop no longer exists.', 'sc_events')));
    }

    $text = function ($key, $default = '') use ($post) {
        return isset($post[$key]) ? trim((string) $post[$key]) : $default;
    };

    $data = array(
        'event_id'                    => absint($text('event_id')),
        'title'                       => $text('title'),
        'slug'                        => $text('slug'),
        'description'                 => $text('description'),
        'excerpt'                     => $text('excerpt'),
        'featured_image'              => absint($text('featured_image')),
        'banner_image'                => absint($text('banner_image')),
        'start_date'                  => $text('start_date'),
        'end_date'                    => $text('end_date'),
        'start_time'                  => $text('start_time'),
        'end_time'                    => $text('end_time'),
        'location_type'               => in_array($text('location_type'), array('offline', 'online', 'hybrid'), true) ? $text('location_type') : 'offline',
        'venue_name'                  => $text('venue_name'),
        'venue_address'               => $text('venue_address'),
        'meeting_link'                => $text('meeting_link'),
        'total_capacity'              => (int) $text('total_capacity', '0'),
        'registration_deadline'       => $text('registration_deadline'),
        'min_tickets_per_order'       => (int) $text('min_tickets_per_order', '1'),
        'max_tickets_per_order'       => (int) $text('max_tickets_per_order', '10'),
        'enable_certificates'         => !empty($post['enable_certificates']) ? 1 : 0,
        'auto_issue_certificate'      => !empty($post['auto_issue_certificate']) ? 1 : 0,
        'certificate_require_checkin' => !empty($post['certificate_require_checkin']) ? 1 : 0,
        'status'                      => $text('status', 'draft'),
    );

    // Only overwrite what the form sent: the timezone isn't on the form, and the
    // template select is left out when no certificate template is active.
    if (isset($post['timezone'])) {
        $data['timezone'] = $text('timezone');
    } elseif (!$existing) {
        $data['timezone'] = wp_timezone_string() ?: 'Africa/Cairo';
    }
    if (isset($post['certificate_template_id'])) {
        $data['certificate_template_id'] = absint($post['certificate_template_id']);
    }

    $errors = sc_workshop_validate($data);
    if ($errors) {
        wp_send_json_error(array(
            'message' => __('Some fields need attention.', 'sc_events'),
            'errors'  => $errors,
        ));
    }
    // The public page shows any stored meeting link, so an in-person workshop keeps none.
    $data['meeting_link'] = $data['location_type'] === 'offline' ? '' : esc_url_raw($data['meeting_link']);

    if ($existing) {
        if (!SC_Workshop::update($id, $data)) {
            wp_send_json_error(array('message' => __('Failed to update workshop.', 'sc_events')));
        }
        // Tickets carry their workshop's event; move them with it when the parent changes.
        if ((int) $existing->event_id !== $data['event_id']) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'sc_tickets', array('event_id' => $data['event_id']), array('workshop_id' => $id), array('%d'), array('%d'));
        }
        $saved = SC_Workshop::get($id);
        wp_send_json_success(array(
            'message'  => __('Workshop saved.', 'sc_events'),
            'id'       => $id,
            'slug'     => $saved ? $saved->slug : '',
            'saved_at' => $saved ? $saved->updated_at : current_time('mysql'),
        ));
    }

    $new_id = SC_Workshop::create($data);
    if (!$new_id) {
        wp_send_json_error(array('message' => __('Failed to create workshop.', 'sc_events')));
    }
    wp_send_json_success(array(
        'message'  => __('Workshop created.', 'sc_events'),
        'id'       => $new_id,
        'redirect' => home_url('/event-manager-dashboard/workshop-edit?id=' . $new_id . '&created=1'),
    ));
}

/**
 * Delete workshop (cascades tickets/attendees/checkins/certificates)
 */
add_action('wp_ajax_sc_delete_workshop', 'sc_delete_workshop_handler');
function sc_delete_workshop_handler() {
    sc_workshops_verify_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(array('message' => __('Invalid workshop ID.', 'sc_events')));
    }

    $ok = SC_Workshop::delete($id);
    if (!$ok) {
        wp_send_json_error(array('message' => __('Failed to delete workshop.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Workshop deleted.', 'sc_events')));
}

/**
 * Get paginated list of workshops
 */
add_action('wp_ajax_sc_get_workshops_paginated', 'sc_get_workshops_paginated_handler');
function sc_get_workshops_paginated_handler() {
    sc_workshops_verify_request();

    $page     = max(1, intval($_POST['page'] ?? 1));
    $per_page = min(200, max(10, intval($_POST['per_page'] ?? 20)));
    $search   = sanitize_text_field($_POST['search'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $status   = sanitize_text_field($_POST['status'] ?? '');
    $offset   = ($page - 1) * $per_page;

    $args = array(
        'limit'   => $per_page,
        'offset'  => $offset,
        'search'  => $search,
        'orderby' => 'start_date',
        'order'   => 'DESC',
    );
    if ($event_id) $args['event_id'] = $event_id;
    if ($status) $args['status'] = $status;

    $workshops = SC_Workshop::get_all($args);
    $total     = SC_Workshop::count($args);
    $pages     = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

    // Enrich with event titles
    $rows = array();
    foreach ($workshops as $w) {
        $rows[] = array(
            'id'              => (int) $w->id,
            'title'           => $w->title,
            'slug'            => $w->slug,
            'event_id'        => (int) $w->event_id,
            'event_title'     => SC_Workshop::get_event_title($w->event_id),
            'start_date'      => $w->start_date,
            'end_date'        => $w->end_date,
            'status'          => $w->status,
            'total_sold'      => (int) $w->total_sold,
            'total_capacity'  => (int) $w->total_capacity,
            'total_checked_in'=> (int) $w->total_checked_in,
        );
    }

    wp_send_json_success(array(
        'workshops'    => $rows,
        'total'        => $total,
        'pages'        => $pages,
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}

/**
 * Get all workshops for a specific event (for dropdowns)
 */
add_action('wp_ajax_sc_get_workshops_for_event', 'sc_get_workshops_for_event_handler');
function sc_get_workshops_for_event_handler() {
    sc_workshops_verify_request();

    $event_id = intval($_POST['event_id'] ?? 0);
    if (!$event_id) {
        wp_send_json_success(array('workshops' => array()));
    }

    $workshops = SC_Workshop::get_by_event($event_id, array(
        'status' => array('publish', 'draft', 'completed'),
    ));

    wp_send_json_success(array('workshops' => $workshops));
}

/**
 * Tickets of one workshop, for re-rendering the form's ticket table.
 */
add_action('wp_ajax_sc_get_workshop_tickets', 'sc_get_workshop_tickets_handler');
function sc_get_workshop_tickets_handler() {
    sc_workshops_verify_request();
    $workshop_id = absint($_POST['workshop_id'] ?? 0);
    $rows = array();
    foreach ($workshop_id ? SC_Ticket::get_by_workshop($workshop_id) : array() as $t) {
        $rows[] = array(
            'id' => (int) $t->id, 'name' => $t->name, 'description' => $t->description, 'price' => (float) $t->price,
            'quantity' => (int) $t->quantity, 'sold' => (int) $t->sold, 'min_per_order' => (int) $t->min_per_order,
            'max_per_order' => (int) $t->max_per_order, 'is_active' => (int) $t->is_active, 'enable_coupons' => (int) $t->enable_coupons,
        );
    }
    wp_send_json_success(array('tickets' => $rows));
}

/**
 * Get a single ticket (for workshop edit modal)
 */
add_action('wp_ajax_sc_get_ticket', 'sc_get_ticket_handler');
function sc_get_ticket_handler() {
    sc_workshops_verify_request();
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    if (!$ticket_id) {
        wp_send_json_error(array('message' => 'Invalid ticket ID'));
    }
    $ticket = SC_Ticket::get($ticket_id);
    if (!$ticket) {
        wp_send_json_error(array('message' => 'Ticket not found'));
    }
    wp_send_json_success(array('ticket' => $ticket));
}

/**
 * Save workshop ticket (create/update)
 */
add_action('wp_ajax_sc_save_workshop_ticket', 'sc_save_workshop_ticket_handler');
function sc_save_workshop_ticket_handler() {
    sc_workshops_verify_request();

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $workshop_id = intval($_POST['workshop_id'] ?? 0);
    $event_id = intval($_POST['event_id'] ?? 0);

    if (!$workshop_id) {
        wp_send_json_error(array('message' => 'Workshop ID is required.'));
    }

    $post = wp_unslash($_POST);
    $data = array(
        'workshop_id'    => $workshop_id,
        'event_id'       => $event_id,
        'name'           => sanitize_text_field($post['name'] ?? ''),
        'slug'           => sanitize_title($post['name'] ?? '') . '-' . $workshop_id,
        'description'    => sanitize_textarea_field($post['description'] ?? ''),
        'price'          => floatval($post['price'] ?? 0),
        'quantity'       => intval($post['quantity'] ?? 0),
        'min_per_order'  => intval($post['min_per_order'] ?? 1),
        'max_per_order'  => intval($post['max_per_order'] ?? 10),
        'is_active'      => !empty($post['is_active']) ? 1 : 0,
        'enable_coupons' => !empty($post['enable_coupons']) ? 1 : 0,
    );

    $errors = array();
    if ($data['name'] === '') {
        $errors['name'] = __('Give the ticket a name.', 'sc_events');
    }
    if ($data['price'] < 0) {
        $errors['price'] = __('Price can’t be negative. Use 0 for free.', 'sc_events');
    }
    if ($data['quantity'] < 0) {
        $errors['quantity'] = __('Use 0 for unlimited.', 'sc_events');
    }
    if ($data['min_per_order'] < 1) {
        $errors['min_per_order'] = __('At least 1.', 'sc_events');
    }
    if ($data['max_per_order'] < max(1, $data['min_per_order'])) {
        $errors['max_per_order'] = __('Must be at least the minimum.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => __('Some fields need attention.', 'sc_events'), 'errors' => $errors));
    }

    if ($ticket_id) {
        $ok = SC_Ticket::update($ticket_id, $data);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Failed to update ticket.'));
        }
        wp_send_json_success(array('message' => 'Ticket updated.', 'id' => $ticket_id));
    } else {
        $new_id = SC_Ticket::create($data);
        if (!$new_id) {
            wp_send_json_error(array('message' => 'Failed to create ticket.'));
        }
        wp_send_json_success(array('message' => 'Ticket created.', 'id' => $new_id));
    }
}

/**
 * Delete workshop ticket
 */
add_action('wp_ajax_sc_delete_workshop_ticket', 'sc_delete_workshop_ticket_handler');
function sc_delete_workshop_ticket_handler() {
    sc_workshops_verify_request();
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    if (!$ticket_id) {
        wp_send_json_error(array('message' => 'Invalid ticket ID'));
    }
    $ok = SC_Ticket::delete($ticket_id);
    if (!$ok) {
        wp_send_json_error(array('message' => 'Failed to delete ticket'));
    }
    wp_send_json_success(array('message' => 'Ticket deleted'));
}
