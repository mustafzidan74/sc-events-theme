<?php
/**
 * SC Events Module
 *
 * Core events management functionality
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Events extends SC_Base_Module {

    /**
     * Module ID
     */
    protected $id = 'events';

    /**
     * Module name
     */
    protected $name = 'Events';

    /**
     * Module description
     */
    protected $description = 'Core event management - create, edit, and manage events';

    /**
     * Module version
     */
    protected $version = '2.0.0';

    /**
     * Module dependencies
     */
    protected $dependencies = array();

    /**
     * Module priority (core module, load first)
     */
    protected $priority = 1;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load model (from existing database layer for now)
        // In future, model will be moved here

        // Register AJAX handlers
        add_action('init', array($this, 'load_ajax_handlers'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Initialize module
     */
    public function init() {
        // Module-specific initialization
        $this->log('Events module initialized');
    }

    /**
     * Load AJAX handlers
     */
    public function load_ajax_handlers() {
        // AJAX handlers are currently in inc/admin-dashboard/events-ajax-handlers.php
        // They will be migrated here gradually

        // Register new module-specific AJAX actions
        $this->register_ajax('get_stats', array($this, 'ajax_get_stats'));
        $this->register_ajax('duplicate', array($this, 'ajax_duplicate_event'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        if (!$this->is_events_page()) {
            return;
        }

        $this->enqueue_assets('admin');
    }

    /**
     * Check if current page is events-related
     */
    private function is_events_page() {
        if (!is_admin()) {
            return false;
        }

        $screen = get_current_screen();
        return $screen && (
            strpos($screen->id, 'sc_event') !== false ||
            strpos($screen->id, 'events') !== false
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('sc-events/v1', '/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_events'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));

        register_rest_route('sc-events/v1', '/events/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_event'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }

    /**
     * REST permission check
     */
    public function rest_permission_check() {
        return current_user_can('edit_posts');
    }

    /**
     * REST: Get all events
     */
    public function rest_get_events($request) {
        $args = array(
            'status' => $request->get_param('status') ?: 'publish',
            'limit' => $request->get_param('per_page') ?: 20,
            'offset' => $request->get_param('offset') ?: 0,
        );

        $events = SC_Event::get_all($args);
        $total = SC_Event::count(array('status' => $args['status']));

        return new WP_REST_Response(array(
            'events' => $events,
            'total' => $total,
        ), 200);
    }

    /**
     * REST: Get single event
     */
    public function rest_get_event($request) {
        $event = SC_Event::get($request->get_param('id'));

        if (!$event) {
            return new WP_Error('not_found', 'Event not found', array('status' => 404));
        }

        return new WP_REST_Response($event, 200);
    }

    /**
     * AJAX: Get event statistics
     */
    public function ajax_get_stats() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stats = array(
            'total' => SC_Event::count(),
            'published' => SC_Event::count(array('status' => 'publish')),
            'draft' => SC_Event::count(array('status' => 'draft')),
            'upcoming' => $this->count_upcoming_events(),
        );

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Duplicate event
     */
    public function ajax_duplicate_event() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Invalid event ID'));
        }

        $new_id = $this->duplicate_event($event_id);

        if ($new_id) {
            wp_send_json_success(array(
                'message' => 'Event duplicated successfully',
                'new_id' => $new_id,
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to duplicate event'));
        }
    }

    /**
     * Count upcoming events
     */
    private function count_upcoming_events() {
        global $wpdb;
        $table = SC_Event::get_table();
        $today = current_time('Y-m-d');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'publish' AND start_date >= %s",
            $today
        ));
    }

    /**
     * Duplicate an event
     *
     * @param int $event_id Original event ID
     * @return int|false New event ID or false
     */
    public function duplicate_event($event_id) {
        $event = SC_Event::get($event_id);

        if (!$event) {
            return false;
        }

        // Prepare new event data
        $new_data = (array) $event;

        // Remove ID and modify some fields
        unset($new_data['id']);
        unset($new_data['wp_post_id']);
        $new_data['title'] = $event->title . ' (Copy)';
        $new_data['slug'] = sanitize_title($new_data['title']);
        $new_data['status'] = 'draft';
        $new_data['total_sold'] = 0;
        $new_data['total_revenue'] = 0;
        $new_data['total_checked_in'] = 0;
        $new_data['created_at'] = current_time('mysql');
        $new_data['updated_at'] = current_time('mysql');

        return SC_Event::create($new_data);
    }

    /**
     * Get event with related data
     *
     * @param int $event_id Event ID
     * @return object|null
     */
    public function get_event_full($event_id) {
        $event = SC_Event::get($event_id);

        if (!$event) {
            return null;
        }

        // Get related data
        $event->tickets = SC_Ticket::get_by_event($event_id);
        $event->speakers = SC_Speaker::get_by_event($event_id);
        $event->organizers = SC_Organizer::get_by_event($event_id);

        return $event;
    }

    /**
     * Get upcoming events
     *
     * @param int $limit Number of events
     * @return array
     */
    public function get_upcoming_events($limit = 10) {
        return SC_Event::get_all(array(
            'status' => 'publish',
            'upcoming_only' => true,
            'orderby' => 'start_date',
            'order' => 'ASC',
            'limit' => $limit,
        ));
    }

    /**
     * Get past events
     *
     * @param int $limit Number of events
     * @return array
     */
    public function get_past_events($limit = 10) {
        return SC_Event::get_all(array(
            'status' => 'publish',
            'past_only' => true,
            'orderby' => 'start_date',
            'order' => 'DESC',
            'limit' => $limit,
        ));
    }
}

// Register the module
sc_modules()->register_module(new SC_Module_Events());
