<?php
/**
 * SC Events API - Organizers Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Organizers_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $event_organizers_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_organizers';
        $this->event_organizers_table = $wpdb->prefix . 'sc_event_organizers';
    }

    /**
     * GET /organizers - All organizers (with optional event filter)
     */
    public function index() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if ($event_id) {
            // Get organizers for specific event
            $organizers = $wpdb->get_results($wpdb->prepare(
                "SELECT o.*, eo.role, eo.is_primary, eo.sort_order
                 FROM {$this->table} o
                 JOIN {$this->event_organizers_table} eo ON o.id = eo.organizer_id
                 WHERE eo.event_id = %d AND o.is_active = 1
                 ORDER BY eo.is_primary DESC, eo.sort_order ASC",
                $event_id
            ));
        } else {
            // Get all active organizers
            $organizers = $wpdb->get_results(
                "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
            );
        }

        $data = array_map(function($org) {
            return $this->formatOrganizer($org);
        }, $organizers);

        SC_API_Response::success($data);
    }

    /**
     * GET /organizers/{id} - Single organizer
     */
    public function show() {
        global $wpdb;

        $id = (int) $this->param('id');

        $organizer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND is_active = 1",
            $id
        ));

        if (!$organizer) {
            SC_API_Response::notFound('المنظم غير موجود');
        }

        // Get organizer's events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.title, e.start_date, eo.role, eo.is_primary
             FROM {$wpdb->prefix}sc_events e
             JOIN {$this->event_organizers_table} eo ON e.id = eo.event_id
             WHERE eo.organizer_id = %d AND e.status = 'publish'
             ORDER BY e.start_date DESC",
            $id
        ));

        $data = $this->formatOrganizer($organizer);
        $data['events'] = array_map(function($e) {
            return [
                'id' => (int) $e->id,
                'title' => $e->title,
                'start_date' => $e->start_date,
                'role' => $e->role,
                'is_primary' => (bool) $e->is_primary
            ];
        }, $events);

        SC_API_Response::success($data);
    }

    /**
     * Format organizer data
     */
    private function formatOrganizer($org) {
        return [
            'id' => (int) $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'description' => $org->description,
            'logo' => $org->logo ? wp_get_attachment_url($org->logo) : null,
            'email' => $org->email,
            'phone' => $org->phone,
            'website' => $org->website,
            'address' => $org->address,
            'role' => $org->role ?? 'organizer',
            'is_primary' => isset($org->is_primary) ? (bool) $org->is_primary : false,
            'social_links' => json_decode($org->social_links, true) ?: [],
        ];
    }
}
