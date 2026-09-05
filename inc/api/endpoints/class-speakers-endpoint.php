<?php
/**
 * SC Events API - Speakers Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Speakers_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $event_speakers_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_speakers';
        $this->event_speakers_table = $wpdb->prefix . 'sc_event_speakers';
    }

    /**
     * GET /speakers - All speakers (with optional event filter)
     */
    public function index() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if ($event_id) {
            // Get speakers for specific event
            $speakers = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, es.role, es.sort_order
                 FROM {$this->table} s
                 JOIN {$this->event_speakers_table} es ON s.id = es.speaker_id
                 WHERE es.event_id = %d AND s.is_active = 1
                 ORDER BY es.sort_order ASC",
                $event_id
            ));
        } else {
            // Get all active speakers
            $speakers = $wpdb->get_results(
                "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
            );
        }

        $data = array_map(function($speaker) {
            return $this->formatSpeaker($speaker);
        }, $speakers);

        SC_API_Response::success($data);
    }

    /**
     * GET /speakers/{id} - Single speaker
     */
    public function show() {
        global $wpdb;

        $id = (int) $this->param('id');

        $speaker = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND is_active = 1",
            $id
        ));

        if (!$speaker) {
            SC_API_Response::notFound('المتحدث غير موجود');
        }

        // Get speaker's events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.title, e.start_date, es.role
             FROM {$wpdb->prefix}sc_events e
             JOIN {$this->event_speakers_table} es ON e.id = es.event_id
             WHERE es.speaker_id = %d AND e.status = 'publish'
             ORDER BY e.start_date DESC",
            $id
        ));

        $data = $this->formatSpeaker($speaker);
        $data['events'] = array_map(function($e) {
            return [
                'id' => (int) $e->id,
                'title' => $e->title,
                'start_date' => $e->start_date,
                'role' => $e->role
            ];
        }, $events);

        SC_API_Response::success($data);
    }

    /**
     * Format speaker data
     */
    private function formatSpeaker($speaker) {
        return [
            'id' => (int) $speaker->id,
            'name' => $speaker->name,
            'slug' => $speaker->slug,
            'title' => $speaker->title,
            'company' => $speaker->company,
            'bio' => $speaker->bio,
            'photo' => $speaker->photo ? wp_get_attachment_url($speaker->photo) : null,
            'email' => $speaker->email,
            'website' => $speaker->website,
            'role' => $speaker->role ?? null,
            'social_links' => json_decode($speaker->social_links, true) ?: [],
        ];
    }
}
