<?php
/**
 * SC Tickets Module
 *
 * Ticket types management for events
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Tickets extends SC_Base_Module {

    protected $id = 'tickets';
    protected $name = 'Tickets';
    protected $description = 'Manage ticket types, pricing, and availability';
    protected $version = '2.0.0';
    protected $dependencies = array('events');
    protected $priority = 2;

    public function register_hooks() {
        add_action('init', array($this, 'register_ajax_handlers'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function init() {
        $this->log('Tickets module initialized');
    }

    public function register_ajax_handlers() {
        $this->register_ajax('create', array($this, 'ajax_create_ticket'));
        $this->register_ajax('update', array($this, 'ajax_update_ticket'));
        $this->register_ajax('delete', array($this, 'ajax_delete_ticket'));
        $this->register_ajax('get_by_event', array($this, 'ajax_get_tickets_by_event'));
        $this->register_ajax('check_availability', array($this, 'ajax_check_availability'));
        $this->register_ajax('update_quantity', array($this, 'ajax_update_quantity'));
    }

    public function register_rest_routes() {
        register_rest_route('sc-events/v1', '/events/(?P<event_id>\d+)/tickets', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_tickets'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('sc-events/v1', '/tickets/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_ticket'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('sc-events/v1', '/tickets/(?P<id>\d+)/availability', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_check_availability'),
            'permission_callback' => '__return_true',
        ));
    }

    // REST Handlers
    public function rest_get_tickets($request) {
        $event_id = $request->get_param('event_id');
        $tickets = SC_Ticket::get_by_event($event_id);
        return new WP_REST_Response(array('tickets' => $tickets), 200);
    }

    public function rest_get_ticket($request) {
        $ticket = SC_Ticket::get($request->get_param('id'));
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        }
        return new WP_REST_Response($ticket, 200);
    }

    public function rest_check_availability($request) {
        $ticket = SC_Ticket::get($request->get_param('id'));
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        }

        $available = $ticket->quantity - $ticket->sold;
        return new WP_REST_Response(array(
            'ticket_id' => $ticket->id,
            'total' => $ticket->quantity,
            'sold' => $ticket->sold,
            'available' => max(0, $available),
            'is_available' => $available > 0 && $ticket->is_active,
        ), 200);
    }

    // AJAX Handlers
    public function ajax_create_ticket() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data = array(
            'event_id' => intval($_POST['event_id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'slug' => sanitize_title($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'min_per_order' => intval($_POST['min_per_order'] ?? 1),
            'max_per_order' => intval($_POST['max_per_order'] ?? 10),
            'sale_start' => sanitize_text_field($_POST['sale_start'] ?? ''),
            'sale_end' => sanitize_text_field($_POST['sale_end'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
        );

        if (empty($data['name']) || empty($data['event_id'])) {
            wp_send_json_error(array('message' => 'Name and Event are required'));
        }

        $ticket_id = SC_Ticket::create($data);

        if ($ticket_id) {
            wp_send_json_success(array(
                'message' => 'Ticket created successfully',
                'ticket_id' => $ticket_id,
                'ticket' => SC_Ticket::get($ticket_id),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create ticket'));
        }
    }

    public function ajax_update_ticket() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        if (!$ticket_id) {
            wp_send_json_error(array('message' => 'Ticket ID required'));
        }

        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'min_per_order' => intval($_POST['min_per_order'] ?? 1),
            'max_per_order' => intval($_POST['max_per_order'] ?? 10),
            'is_active' => intval($_POST['is_active'] ?? 1),
        );

        if (SC_Ticket::update($ticket_id, $data)) {
            wp_send_json_success(array(
                'message' => 'Ticket updated successfully',
                'ticket' => SC_Ticket::get($ticket_id),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update ticket'));
        }
    }

    public function ajax_delete_ticket() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $ticket_id = intval($_POST['ticket_id'] ?? 0);

        if (SC_Ticket::delete($ticket_id)) {
            wp_send_json_success(array('message' => 'Ticket deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete ticket'));
        }
    }

    public function ajax_get_tickets_by_event() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        $event_id = intval($_POST['event_id'] ?? 0);
        $tickets = SC_Ticket::get_by_event($event_id);

        wp_send_json_success(array('tickets' => $tickets));
    }

    public function ajax_check_availability() {
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);

        $ticket = SC_Ticket::get($ticket_id);

        if (!$ticket) {
            wp_send_json_error(array('message' => 'Ticket not found'));
        }

        $available = $ticket->quantity - $ticket->sold;
        $can_purchase = $available >= $quantity && $ticket->is_active;

        wp_send_json_success(array(
            'available' => $available,
            'can_purchase' => $can_purchase,
            'message' => $can_purchase ? 'Available' : 'Not enough tickets available',
        ));
    }

    public function ajax_update_quantity() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);

        if (SC_Ticket::update($ticket_id, array('quantity' => $quantity))) {
            wp_send_json_success(array('message' => 'Quantity updated'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update quantity'));
        }
    }

    // Helper Methods
    public function get_available_tickets($event_id) {
        $tickets = SC_Ticket::get_by_event($event_id);
        return array_filter($tickets, function($ticket) {
            return $ticket->is_active && ($ticket->quantity - $ticket->sold) > 0;
        });
    }

    public function get_ticket_sales_stats($event_id) {
        $tickets = SC_Ticket::get_by_event($event_id);
        $stats = array(
            'total_tickets' => 0,
            'total_sold' => 0,
            'total_revenue' => 0,
            'by_ticket' => array(),
        );

        foreach ($tickets as $ticket) {
            $stats['total_tickets'] += $ticket->quantity;
            $stats['total_sold'] += $ticket->sold;
            $stats['total_revenue'] += $ticket->sold * $ticket->price;
            $stats['by_ticket'][$ticket->id] = array(
                'name' => $ticket->name,
                'sold' => $ticket->sold,
                'revenue' => $ticket->sold * $ticket->price,
            );
        }

        return $stats;
    }
}

// Register the module
sc_modules()->register_module(new SC_Module_Tickets());
