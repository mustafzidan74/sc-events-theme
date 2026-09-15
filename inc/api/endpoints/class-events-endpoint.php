<?php
/**
 * SC Events API - Events Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Events_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $tickets_table;
    private $categories_table;
    private $speakers_table;
    private $organizers_table;
    private $event_speakers_table;
    private $event_organizers_table;
    private $event_categories_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_events';
        $this->tickets_table = $wpdb->prefix . 'sc_tickets';
        $this->categories_table = $wpdb->prefix . 'sc_event_categories';
        $this->speakers_table = $wpdb->prefix . 'sc_speakers';
        $this->organizers_table = $wpdb->prefix . 'sc_organizers';
        $this->event_speakers_table = $wpdb->prefix . 'sc_event_speakers';
        $this->event_organizers_table = $wpdb->prefix . 'sc_event_organizers';
        $this->event_categories_table = $wpdb->prefix . 'sc_event_categories';
    }

    /**
     * GET /events - Published events with filters
     */
    public function index() {
        global $wpdb;

        $page = (int) ($this->query('page') ?: 1);
        $per_page = min((int) ($this->query('per_page') ?: 10), 50);
        $offset = ($page - 1) * $per_page;

        // Filters
        $category_id = (int) $this->query('category_id');
        $search = $this->query('search');
        $sort = $this->query('sort') ?: 'upcoming'; // upcoming, past, newest

        // Base query - only published events
        $where = "e.status = 'publish'";
        $params = [];

        // Category filter
        if ($category_id) {
            $where .= " AND e.id IN (SELECT event_id FROM {$this->event_categories_table} WHERE category_id = %d)";
            $params[] = $category_id;
        }

        // Search
        if ($search) {
            $where .= " AND (e.title LIKE %s OR e.description LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Sort
        $today = current_time('Y-m-d');
        switch ($sort) {
            case 'past':
                $where .= " AND e.end_date < %s";
                $params[] = $today;
                $order = "e.start_date DESC";
                break;
            case 'newest':
                $order = "e.created_at DESC";
                break;
            case 'upcoming':
            default:
                $where .= " AND e.end_date >= %s";
                $params[] = $today;
                $order = "e.start_date ASC";
                break;
        }

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->table} e WHERE {$where}";
        $total = (int) $wpdb->get_var(
            $params ? $wpdb->prepare($count_sql, ...$params) : $count_sql
        );

        // Get events
        $sql = "SELECT e.* FROM {$this->table} e WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $events = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        // Format events
        $data = array_map(function($event) {
            return $this->formatEventList($event);
        }, $events);

        SC_API_Response::paginated($data, $total, $page, $per_page);
    }

    /**
     * GET /events/{id} - Event details
     */
    public function show() {
        global $wpdb;

        $id = (int) $this->param('id');

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND status = 'publish'",
            $id
        ));

        if (!$event) {
            SC_API_Response::notFound('الفعالية غير موجودة');
        }

        $data = $this->formatEventDetail($event);

        SC_API_Response::success($data);
    }

    /**
     * Format event for list
     */
    private function formatEventList($event) {
        return [
            'id' => (int) $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'excerpt' => $event->excerpt ?: mb_substr(strip_tags($event->description), 0, 150) . '...',
            'featured_image' => $this->getImageUrl($event->featured_image),
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'location_type' => $event->location_type,
            'venue_name' => $event->venue_name,
            'venue_city' => $event->venue_city,
            'total_capacity' => (int) $event->total_capacity,
            'total_sold' => (int) $event->total_sold,
            'categories' => $this->getEventCategories($event->id),
        ];
    }

    /**
     * Format event for detail view
     */
    private function formatEventDetail($event) {
        global $wpdb;

        // Parse JSON fields
        $extra_fields = json_decode($event->extra_fields, true) ?: [];
        $faq = json_decode($event->faq, true) ?: [];
        $schedule = json_decode($event->schedule, true) ?: [];
        $social_links = json_decode($event->social_links, true) ?: [];

        return [
            'id' => (int) $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'excerpt' => $event->excerpt,

            // Images
            'featured_image' => $this->getImageUrl($event->featured_image),
            'logo_image' => $this->getImageUrl($event->logo_image),
            'banner_image' => $this->getImageUrl($event->banner_image),

            // Date & Time
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'timezone' => $event->timezone,
            'all_day_event' => (bool) $event->all_day_event,

            // Location
            'location_type' => $event->location_type,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'venue_city' => $event->venue_city,
            'venue_country' => $event->venue_country,
            'venue_lat' => $event->venue_lat ? (float) $event->venue_lat : null,
            'venue_lng' => $event->venue_lng ? (float) $event->venue_lng : null,
            'meeting_link' => $event->meeting_link,

            // Capacity
            'total_capacity' => (int) $event->total_capacity,
            'total_sold' => (int) $event->total_sold,
            'available_spots' => max(0, (int) $event->total_capacity - (int) $event->total_sold),
            'registration_deadline' => $event->registration_deadline,
            'min_tickets_per_order' => (int) $event->min_tickets_per_order,
            'max_tickets_per_order' => (int) $event->max_tickets_per_order,

            // Extra data
            'extra_fields' => $extra_fields,
            'faq' => $faq,
            'schedule' => $schedule,
            'social_links' => $social_links,

            // Certificate
            'enable_certificates' => (bool) $event->enable_certificates,

            // Relations
            'categories' => $this->getEventCategories($event->id),
            'tickets' => $this->getEventTickets($event->id),
            'speakers' => $this->getEventSpeakers($event->id),
            'organizers' => $this->getEventOrganizers($event->id),
        ];
    }

    /**
     * Get image URL from attachment ID
     */
    private function getImageUrl($attachment_id) {
        if (!$attachment_id) return null;
        return wp_get_attachment_url($attachment_id) ?: null;
    }

    /**
     * Get event categories
     */
    private function getEventCategories($event_id) {
        global $wpdb;

        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             JOIN {$this->event_categories_table} ec ON ec.category_id = t.term_id
             WHERE ec.event_id = %d AND tt.taxonomy = 'sc_event_category'
             ORDER BY ec.sort_order ASC",
            $event_id
        ));

        return array_map(function($cat) {
            return [
                'id' => (int) $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug
            ];
        }, $categories);
    }

    /**
     * Get event tickets
     */
    private function getEventTickets($event_id) {
        global $wpdb;

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tickets_table} WHERE event_id = %d AND is_active = 1 ORDER BY sort_order ASC",
            $event_id
        ));

        return array_map(function($ticket) {
            $available = (int) $ticket->quantity - (int) $ticket->sold;
            return [
                'id' => (int) $ticket->id,
                'name' => $ticket->name,
                'description' => $ticket->description,
                'price' => (float) $ticket->price,
                'quantity' => (int) $ticket->quantity,
                'sold' => (int) $ticket->sold,
                'available' => max(0, $available),
                'min_per_order' => (int) $ticket->min_per_order,
                'max_per_order' => (int) $ticket->max_per_order,
                'sale_start' => $ticket->sale_start,
                'sale_end' => $ticket->sale_end,
                'is_available' => $available > 0 && $this->isTicketOnSale($ticket),
            ];
        }, $tickets);
    }

    /**
     * Check if ticket is currently on sale
     */
    private function isTicketOnSale($ticket) {
        $now = current_time('mysql');

        if ($ticket->sale_start && $now < $ticket->sale_start) {
            return false;
        }

        if ($ticket->sale_end && $now > $ticket->sale_end) {
            return false;
        }

        return true;
    }

    /**
     * Get event speakers
     */
    private function getEventSpeakers($event_id) {
        global $wpdb;

        $speakers = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, es.role, es.sort_order
             FROM {$this->speakers_table} s
             JOIN {$this->event_speakers_table} es ON s.id = es.speaker_id
             WHERE es.event_id = %d AND s.is_active = 1
             ORDER BY es.sort_order ASC",
            $event_id
        ));

        return array_map(function($speaker) {
            return [
                'id' => (int) $speaker->id,
                'name' => $speaker->name,
                'title' => $speaker->title,
                'company' => $speaker->company,
                'bio' => $speaker->bio,
                'photo' => $this->getImageUrl($speaker->photo),
                'role' => $speaker->role,
                'social_links' => json_decode($speaker->social_links, true) ?: [],
            ];
        }, $speakers);
    }

    /**
     * Get event organizers
     */
    private function getEventOrganizers($event_id) {
        global $wpdb;

        $organizers = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, eo.role, eo.is_primary, eo.sort_order
             FROM {$this->organizers_table} o
             JOIN {$this->event_organizers_table} eo ON o.id = eo.organizer_id
             WHERE eo.event_id = %d AND o.is_active = 1
             ORDER BY eo.is_primary DESC, eo.sort_order ASC",
            $event_id
        ));

        return array_map(function($org) {
            return [
                'id' => (int) $org->id,
                'name' => $org->name,
                'description' => $org->description,
                'logo' => $this->getImageUrl($org->logo),
                'website' => $org->website,
                'role' => $org->role,
                'is_primary' => (bool) $org->is_primary,
            ];
        }, $organizers);
    }
}
