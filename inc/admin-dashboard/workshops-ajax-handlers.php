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
 * Save (create or update) workshop
 */
add_action('wp_ajax_sc_save_workshop', 'sc_save_workshop_handler');
function sc_save_workshop_handler() {
    sc_workshops_verify_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Parent event is required.', 'sc_events')));
    }

    $data = array(
        'event_id'                    => $event_id,
        'title'                       => isset($_POST['title']) ? $_POST['title'] : '',
        'slug'                        => isset($_POST['slug']) ? $_POST['slug'] : '',
        'description'                 => isset($_POST['description']) ? wp_unslash($_POST['description']) : '',
        'excerpt'                     => isset($_POST['excerpt']) ? $_POST['excerpt'] : '',
        'featured_image'              => isset($_POST['featured_image']) ? intval($_POST['featured_image']) : 0,
        'banner_image'                => isset($_POST['banner_image']) ? intval($_POST['banner_image']) : 0,
        'start_date'                  => isset($_POST['start_date']) ? $_POST['start_date'] : '',
        'end_date'                    => isset($_POST['end_date']) ? $_POST['end_date'] : '',
        'start_time'                  => isset($_POST['start_time']) ? $_POST['start_time'] : '',
        'end_time'                    => isset($_POST['end_time']) ? $_POST['end_time'] : '',
        'timezone'                    => isset($_POST['timezone']) ? $_POST['timezone'] : 'Africa/Cairo',
        'location_type'               => isset($_POST['location_type']) ? $_POST['location_type'] : 'offline',
        'venue_name'                  => isset($_POST['venue_name']) ? $_POST['venue_name'] : '',
        'venue_address'               => isset($_POST['venue_address']) ? $_POST['venue_address'] : '',
        'meeting_link'                => isset($_POST['meeting_link']) ? $_POST['meeting_link'] : '',
        'total_capacity'              => isset($_POST['total_capacity']) ? intval($_POST['total_capacity']) : 0,
        'registration_deadline'       => isset($_POST['registration_deadline']) ? $_POST['registration_deadline'] : '',
        'min_tickets_per_order'       => isset($_POST['min_tickets_per_order']) ? intval($_POST['min_tickets_per_order']) : 1,
        'max_tickets_per_order'       => isset($_POST['max_tickets_per_order']) ? intval($_POST['max_tickets_per_order']) : 10,
        'enable_certificates'         => isset($_POST['enable_certificates']) ? 1 : 0,
        'certificate_template_id'     => isset($_POST['certificate_template_id']) ? intval($_POST['certificate_template_id']) : 0,
        'auto_issue_certificate'      => isset($_POST['auto_issue_certificate']) ? 1 : 0,
        'certificate_require_checkin' => isset($_POST['certificate_require_checkin']) ? 1 : 0,
        'status'                      => isset($_POST['status']) ? $_POST['status'] : 'draft',
    );

    if (empty($data['title'])) {
        wp_send_json_error(array('message' => __('Title is required.', 'sc_events')));
    }
    if (empty($data['start_date'])) {
        wp_send_json_error(array('message' => __('Start date is required.', 'sc_events')));
    }

    if ($id) {
        $ok = SC_Workshop::update($id, $data);
        if (!$ok) {
            wp_send_json_error(array('message' => __('Failed to update workshop.', 'sc_events')));
        }
        wp_send_json_success(array(
            'message'     => __('Workshop updated successfully.', 'sc_events'),
            'id'          => $id,
            'redirect'    => home_url('/event-manager-dashboard/workshop-edit?id=' . $id),
        ));
    } else {
        $new_id = SC_Workshop::create($data);
        if (!$new_id) {
            wp_send_json_error(array('message' => __('Failed to create workshop.', 'sc_events')));
        }
        wp_send_json_success(array(
            'message'  => __('Workshop created successfully.', 'sc_events'),
            'id'       => $new_id,
            'redirect' => home_url('/event-manager-dashboard/workshop-edit?id=' . $new_id),
        ));
    }
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

    $data = array(
        'workshop_id'    => $workshop_id,
        'event_id'       => $event_id,
        'name'           => sanitize_text_field($_POST['name'] ?? ''),
        'slug'           => sanitize_title($_POST['name'] ?? '') . '-' . $workshop_id,
        'description'    => sanitize_textarea_field($_POST['description'] ?? ''),
        'price'          => floatval($_POST['price'] ?? 0),
        'quantity'       => intval($_POST['quantity'] ?? 0),
        'min_per_order'  => intval($_POST['min_per_order'] ?? 1),
        'max_per_order'  => intval($_POST['max_per_order'] ?? 10),
        'is_active'      => !empty($_POST['is_active']) ? 1 : 0,
        'enable_coupons' => !empty($_POST['enable_coupons']) ? 1 : 0,
    );

    if (empty($data['name'])) {
        wp_send_json_error(array('message' => 'Ticket name is required.'));
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
