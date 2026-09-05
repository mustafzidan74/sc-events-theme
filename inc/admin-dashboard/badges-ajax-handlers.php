<?php
/**
 * Badges AJAX Handlers
 * Badge/Lanyard printing for events
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// GET PEOPLE FOR BADGE PRINTING
// ==========================================
add_action('wp_ajax_sc_get_badge_people', 'sc_get_badge_people_handler');
function sc_get_badge_people_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Event ID is required.', 'sc_events')));
    }

    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Get attendees (active with successful payment)
    $attendees_raw = array();
    if (class_exists('SC_Attendee')) {
        $attendees_raw = SC_Attendee::get_by_event($event_id, array(
            'status' => 'active',
            'limit' => 5000,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
    }

    $attendees = array();
    foreach ($attendees_raw as $att) {
        $company = '';
        if (!empty($att->extra_fields)) {
            $extra = $att->extra_fields;
            if (is_string($extra)) {
                $extra = json_decode($extra, true);
            }
            if (is_array($extra)) {
                foreach ($extra as $field) {
                    $field_name = '';
                    if (isset($field['name'])) $field_name = $field['name'];
                    elseif (isset($field['label'])) $field_name = $field['label'];
                    elseif (isset($field['key'])) $field_name = $field['key'];

                    if (stripos($field_name, 'company') !== false || stripos($field_name, 'شركة') !== false || stripos($field_name, 'organization') !== false) {
                        $company = isset($field['value']) ? $field['value'] : '';
                        break;
                    }
                }
            }
        }

        $ticket_name = isset($att->ticket_name) ? $att->ticket_name : '';
        $is_vip = (stripos($ticket_name, 'vip') !== false);

        $attendees[] = array(
            'id' => $att->id,
            'name' => $att->name ?? '',
            'email' => $att->email ?? '',
            'ticket_name' => $ticket_name,
            'ticket_code' => $att->ticket_code ?? '',
            'company' => $company,
            'is_vip' => $is_vip,
            'checked_in' => !empty($att->checked_in),
            'payment_status' => $att->payment_status ?? '',
        );
    }

    // Get speakers (if module enabled)
    $speakers = array();
    if (sc_module_active('speakers') && class_exists('SC_Speaker')) {
        $speakers_raw = SC_Speaker::get_by_event($event_id);
        foreach ($speakers_raw as $spk) {
            $photo_url = '';
            if (!empty($spk->photo)) {
                $photo_url = wp_get_attachment_url($spk->photo);
            }
            $speakers[] = array(
                'id' => $spk->id,
                'name' => $spk->name ?? '',
                'title' => $spk->title ?? '',
                'company' => $spk->company ?? '',
                'photo_url' => $photo_url ?: '',
            );
        }
    }

    // Get organizers
    $organizers = array();
    if (class_exists('SC_Organizer')) {
        $organizers_raw = SC_Organizer::get_by_event($event_id);
        foreach ($organizers_raw as $org) {
            $logo_url = '';
            if (!empty($org->logo)) {
                $logo_url = wp_get_attachment_url($org->logo);
            }
            $organizers[] = array(
                'id' => $org->id,
                'name' => $org->name ?? '',
                'logo_url' => $logo_url ?: '',
            );
        }
    }

    wp_send_json_success(array(
        'attendees' => $attendees,
        'speakers' => $speakers,
        'organizers' => $organizers,
        'event' => array(
            'id' => $event->id,
            'title' => $event->title ?? '',
            'start_date' => $event->start_date ?? '',
            'end_date' => $event->end_date ?? '',
            'venue_name' => $event->venue_name ?? '',
        ),
        'counts' => array(
            'attendees' => count($attendees),
            'speakers' => count($speakers),
            'organizers' => count($organizers),
        ),
    ));
}

// ==========================================
// PREPARE BADGES (save config, return download URL)
// ==========================================
add_action('wp_ajax_sc_prepare_badges', 'sc_prepare_badges_handler');
function sc_prepare_badges_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $design = isset($_POST['design']) ? sanitize_text_field($_POST['design']) : 'corporate';
    $badge_size = isset($_POST['badge_size']) ? sanitize_text_field($_POST['badge_size']) : 'standard';
    $primary_color = isset($_POST['primary_color']) ? sanitize_hex_color($_POST['primary_color']) : '#1a73e8';
    $logo_url = isset($_POST['logo_url']) ? esc_url_raw($_POST['logo_url']) : '';
    $include_qr = isset($_POST['include_qr']) ? filter_var($_POST['include_qr'], FILTER_VALIDATE_BOOLEAN) : true;
    $include_event = isset($_POST['include_event_name']) ? filter_var($_POST['include_event_name'], FILTER_VALIDATE_BOOLEAN) : true;
    $layout_mode = isset($_POST['layout_mode']) ? sanitize_text_field($_POST['layout_mode']) : 'grid';
    $people_json = isset($_POST['people']) ? stripslashes($_POST['people']) : '[]';

    $people = json_decode($people_json, true);
    if (empty($people) || !is_array($people)) {
        wp_send_json_error(array('message' => __('No people selected.', 'sc_events')));
    }

    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Validate design
    if (!in_array($design, array('corporate', 'modern', 'elegant'))) {
        $design = 'corporate';
    }
    if (!in_array($badge_size, array('standard', 'id_card'))) {
        $badge_size = 'standard';
    }
    if (!in_array($layout_mode, array('grid', 'single'))) {
        $layout_mode = 'grid';
    }

    // Build badge data
    $badges = array();
    foreach ($people as $person) {
        if (!isset($person['id']) || !isset($person['type'])) continue;

        $id = intval($person['id']);
        $type = sanitize_text_field($person['type']);

        if ($type === 'attendee' && class_exists('SC_Attendee')) {
            $att = SC_Attendee::get($id);
            if (!$att) continue;

            $company = '';
            if (!empty($att->extra_fields)) {
                $extra = $att->extra_fields;
                if (is_string($extra)) $extra = json_decode($extra, true);
                if (is_array($extra)) {
                    foreach ($extra as $field) {
                        $fn = $field['name'] ?? $field['label'] ?? $field['key'] ?? '';
                        if (stripos($fn, 'company') !== false || stripos($fn, 'شركة') !== false || stripos($fn, 'organization') !== false) {
                            $company = $field['value'] ?? '';
                            break;
                        }
                    }
                }
            }

            $ticket_name = $att->ticket_name ?? '';
            $is_vip = (stripos($ticket_name, 'vip') !== false);

            $badges[] = array(
                'type' => $is_vip ? 'vip' : 'attendee',
                'name' => $att->name ?? '',
                'subtitle' => $company,
                'detail' => $ticket_name,
                'qr_data' => $att->ticket_code ?? '',
                'photo_url' => '',
            );
        } elseif ($type === 'speaker' && class_exists('SC_Speaker')) {
            $spk = SC_Speaker::get($id);
            if (!$spk) continue;

            $photo_url = '';
            if (!empty($spk->photo)) {
                $photo_url = wp_get_attachment_url($spk->photo);
            }

            $badges[] = array(
                'type' => 'speaker',
                'name' => $spk->name ?? '',
                'subtitle' => $spk->title ?? '',
                'detail' => $spk->company ?? '',
                'qr_data' => 'SPK-' . $spk->id . '-' . $event_id,
                'photo_url' => $photo_url ?: '',
            );
        } elseif ($type === 'organizer' && class_exists('SC_Organizer')) {
            $org = SC_Organizer::get($id);
            if (!$org) continue;

            $logo = '';
            if (!empty($org->logo)) {
                $logo = wp_get_attachment_url($org->logo);
            }

            $badges[] = array(
                'type' => 'organizer',
                'name' => $org->name ?? '',
                'subtitle' => '',
                'detail' => '',
                'qr_data' => 'ORG-' . $org->id . '-' . $event_id,
                'photo_url' => $logo ?: '',
            );
        }
    }

    if (empty($badges)) {
        wp_send_json_error(array('message' => __('No valid people found.', 'sc_events')));
    }

    // Save config in transient
    $token = wp_generate_password(32, false);
    $config = array(
        'event' => array(
            'id' => $event->id,
            'title' => $event->title ?? '',
            'start_date' => $event->start_date ?? '',
            'venue_name' => $event->venue_name ?? '',
        ),
        'badges' => $badges,
        'design' => $design,
        'badge_size' => $badge_size,
        'primary_color' => $primary_color ?: '#1a73e8',
        'logo_url' => $logo_url,
        'include_qr' => $include_qr,
        'include_event' => $include_event,
        'layout_mode' => $layout_mode,
    );

    set_transient('sc_badge_job_' . $token, $config, 300); // 5 minutes

    $download_url = admin_url('admin-ajax.php') . '?action=sc_download_badges&token=' . $token;

    wp_send_json_success(array(
        'download_url' => $download_url,
        'count' => count($badges),
    ));
}

// ==========================================
// DOWNLOAD BADGES PDF (GET request, streams PDF)
// ==========================================
add_action('wp_ajax_sc_download_badges', 'sc_download_badges_handler');
function sc_download_badges_handler() {
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    if (empty($token)) {
        wp_die(__('Invalid request.', 'sc_events'));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(__('Permission denied.', 'sc_events'));
    }

    $config = get_transient('sc_badge_job_' . $token);
    if (!$config) {
        wp_die(__('Badge generation expired. Please try again.', 'sc_events'));
    }

    delete_transient('sc_badge_job_' . $token);

    require_once get_template_directory() . '/inc/badges/class-sc-badge-pdf.php';

    $generator = new SC_Badge_PDF($config);
    $generator->generate('D');
    exit;
}
