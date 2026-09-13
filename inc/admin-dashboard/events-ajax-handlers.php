<?php
/**
 * Events Management AJAX Handlers - Part 2
 * Additional handlers for Create/Update/Delete/Duplicate Events
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if events module is enabled - if not, don't register any AJAX handlers
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('events')) {
    return;
}

/**
 * Migration: Add organizing_company column to sc_events table
 */
function sc_migrate_organizing_company_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_events';
    $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'organizing_company'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN organizing_company TEXT DEFAULT NULL AFTER social_links");
    }
}
sc_migrate_organizing_company_column();

/**
 * Migration: Add ticket_type column to sc_tickets table
 */
function sc_migrate_ticket_type_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_tickets';
    $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'ticket_type'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN ticket_type VARCHAR(20) NOT NULL DEFAULT 'general'");
    }
}
sc_migrate_ticket_type_column();

/**
 * Get Events Page Statistics
 * Returns counts for dashboard stats cards
 */
add_action('wp_ajax_sc_get_events_page_stats', 'sc_get_events_page_stats');
function sc_get_events_page_stats() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Event')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    global $wpdb;

    // Get stats - don't use transient for real-time updates
    $stats = array(
        'published' => SC_Event::count(array('status' => 'publish')),
        'draft' => SC_Event::count(array('status' => 'draft')),
        'completed' => SC_Event::count(array('status' => 'completed')),
        'disabled' => SC_Event::count(array('status' => 'disabled')),
    );

    // Get upcoming events count (published events with start_date >= today)
    $today = date('Y-m-d');
    $stats['upcoming'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events WHERE status = 'publish' AND start_date >= %s",
        $today
    ));

    // Get total tickets sold
    $stats['tickets_sold'] = (int) $wpdb->get_var(
        "SELECT COALESCE(SUM(total_sold), 0) FROM {$wpdb->prefix}sc_events"
    );

    wp_send_json_success($stats);
}

/**
 * Convert 12-hour time format to 24-hour format
 * Converts "07:00 AM" to "07:00" and "05:00 PM" to "17:00"
 *
 * @param string $time Time in 12-hour format (e.g., "07:00 AM")
 * @return string Time in 24-hour format (e.g., "07:00") or original if already 24-hour
 */
function sc_convert_to_24hour($time) {
    if (empty($time)) {
        return '';
    }

    // Check if already in 24-hour format (no AM/PM)
    if (stripos($time, 'AM') === false && stripos($time, 'PM') === false) {
        return $time;
    }

    // Parse and convert
    $parsed = date_parse($time);
    if ($parsed && isset($parsed['hour']) && isset($parsed['minute'])) {
        return sprintf('%02d:%02d', $parsed['hour'], $parsed['minute']);
    }

    // Fallback: try strtotime
    $timestamp = strtotime($time);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }

    return $time;
}

/**
 * Convert 24-hour time format to 12-hour format
 * Converts "17:00" to "05:00 PM"
 *
 * @param string $time Time in 24-hour format (e.g., "17:00")
 * @return string Time in 12-hour format (e.g., "05:00 PM")
 */
function sc_convert_to_12hour($time) {
    if (empty($time)) {
        return '';
    }

    // Check if already in 12-hour format (has AM/PM)
    if (stripos($time, 'AM') !== false || stripos($time, 'PM') !== false) {
        return $time;
    }

    // Parse and convert
    $timestamp = strtotime($time);
    if ($timestamp !== false) {
        return date('h:i A', $timestamp);
    }

    return $time;
}

/**
 * Create or Update Event - Using Custom Tables Only
 */
add_action('wp_ajax_sc_create_or_update_event', 'sc_create_or_update_event');
function sc_create_or_update_event() {
    // Escalate only genuine errors. PHP 8 raises warnings, notices and
    // deprecations routinely inside core upload and image code; turning those
    // into exceptions aborted every event save that carried an image.
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        if ($errno & (E_USER_ERROR | E_RECOVERABLE_ERROR)) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return false; // hand back to the normal handler so it still gets logged
    });

    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
        }

        // Check permissions
        if (!SC_Event_Manager_Dashboard::is_event_manager()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
        }

        // Verify custom tables are available
        if (!class_exists('SC_Event') || !class_exists('SC_Ticket')) {
            wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
        }

    global $wpdb;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $event_title = isset($_POST['event_title']) ? sanitize_text_field(wp_unslash($_POST['event_title'])) : '';

    // Accept both 'slug' and 'event_slug' for permalink
    $event_slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    if (empty($event_slug) && isset($_POST['event_slug'])) {
        $event_slug = sanitize_title(wp_unslash($_POST['event_slug']));
    }

    // Accept both 'description' (from wp_editor) and 'event_description'
    $event_description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
    if (empty($event_description) && isset($_POST['event_description'])) {
        $event_description = wp_kses_post(wp_unslash($_POST['event_description']));
    }

    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
    $start_time = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
    $end_time = isset($_POST['end_time']) ? sanitize_text_field(wp_unslash($_POST['end_time'])) : '';

    // Accept 'venue_address', 'venue', or 'address' for venue location
    $venue = isset($_POST['venue_address']) ? sanitize_text_field(wp_unslash($_POST['venue_address'])) : '';
    if (empty($venue) && isset($_POST['venue'])) {
        $venue = sanitize_text_field(wp_unslash($_POST['venue']));
    }
    if (empty($venue) && isset($_POST['address'])) {
        $venue = sanitize_text_field(wp_unslash($_POST['address']));
    }
    $venue_name = isset($_POST['venue_name']) ? sanitize_text_field(wp_unslash($_POST['venue_name'])) : $venue;
    $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 0;
    $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'draft';
    if (!in_array($status, array('draft', 'publish', 'private', 'cancelled', 'completed', 'disabled'), true)) {
        $status = 'draft';
    }

    // New fields
    $all_day_event = !empty($_POST['all_day_event']) ? 1 : 0;
    $default_timezone = get_option('timezone_string', 'UTC') ?: 'UTC';
    $timezone = isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : $default_timezone;

    // Accept both 'location_type' and 'event_type' (from new form)
    // Database enum: 'offline', 'online', 'hybrid'
    if (isset($_POST['event_type'])) {
        $event_type_val = sanitize_text_field(wp_unslash($_POST['event_type']));
        $location_type = ($event_type_val === 'online') ? 'online' : 'offline';
    } elseif (isset($_POST['location_type'])) {
        $location_type_val = sanitize_text_field(wp_unslash($_POST['location_type']));
        // Accept 'physical' as alias for 'offline'
        if ($location_type_val === 'physical') {
            $location_type = 'offline';
        } elseif (in_array($location_type_val, ['offline', 'online', 'hybrid'])) {
            $location_type = $location_type_val;
        } else {
            $location_type = 'offline';
        }
    } else {
        $location_type = 'offline';
    }

    $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
    $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
    $meeting_link = isset($_POST['meeting_link']) ? esc_url_raw(wp_unslash($_POST['meeting_link'])) : '';

    $field_errors = array();
    if (empty($event_title)) {
        $field_errors['event_title'] = __('Event title is required.', 'sc_events');
    }
    if (empty($start_date) || !strtotime($start_date)) {
        $field_errors['start_date'] = __('Event start date is required.', 'sc_events');
    } elseif (!empty($end_date) && strtotime($end_date) && $end_date < $start_date) {
        $field_errors['end_date'] = __('The event ends before it starts.', 'sc_events');
    }
    if ($event_slug !== '') {
        $slug_owner = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_events WHERE slug = %s AND id <> %d LIMIT 1",
            $event_slug,
            $event_id
        ));
        if ($slug_owner) {
            $field_errors['slug'] = __('Another event already uses this web address.', 'sc_events');
        }
    }
    if ($field_errors) {
        wp_send_json_error(array('message' => reset($field_errors), 'errors' => $field_errors));
    }

    // SECURITY: If updating, verify user can access this event
    if ($event_id > 0) {
        $existing_event = SC_Event::get($event_id);
        if (!$existing_event) {
            wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
        }
        // Check ownership
        if (!current_user_can('manage_options') && $existing_event->author_id != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
        }
    }

    // Handle featured image upload
    $featured_image_id = 0;
    if (isset($_FILES['event_image']) && !empty($_FILES['event_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('event_image', 0);
        if (!is_wp_error($attachment_id)) {
            $featured_image_id = $attachment_id;
        }
    }

    if (!$featured_image_id && !empty($_POST['featured_image'])) {
        $featured_image_id = absint($_POST['featured_image']);
    }

    // Handle brand logo upload (or use existing)
    $logo_image_id = 0;
    if (isset($_FILES['brand_logo']) && !empty($_FILES['brand_logo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('brand_logo', 0);
        if (!is_wp_error($attachment_id)) {
            $logo_image_id = $attachment_id;
        }
    } elseif (isset($_POST['existing_logo_image']) && !empty($_POST['existing_logo_image'])) {
        $logo_image_id = absint($_POST['existing_logo_image']);
    }

    // Handle event banner upload (or use existing)
    $banner_image_id = 0;
    if (isset($_FILES['event_banner']) && !empty($_FILES['event_banner']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('event_banner', 0);
        if (!is_wp_error($attachment_id)) {
            $banner_image_id = $attachment_id;
        }
    } elseif (isset($_POST['existing_banner_image']) && !empty($_POST['existing_banner_image'])) {
        $banner_image_id = absint($_POST['existing_banner_image']);
    }

    // Handle venue image (media library)
    $venue_image_id = 0;
    if (isset($_POST['venue_image']) && !empty($_POST['venue_image'])) {
        $venue_image_id = absint($_POST['venue_image']);
    }

    // Handle schedules PDF file upload
    $schedules_file_id = 0;
    if (isset($_FILES['schedules_file']) && !empty($_FILES['schedules_file']['name'])) {
        if ($_FILES['schedules_file']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            // Validate file type (PDF only) - case insensitive
            $file_type = wp_check_filetype($_FILES['schedules_file']['name']);
            $ext = strtolower($file_type['ext'] ?? '');
            $mime = strtolower($_FILES['schedules_file']['type']);

            if ($ext === 'pdf' || $mime === 'application/pdf') {
                $attachment_id = media_handle_upload('schedules_file', 0);
                if (!is_wp_error($attachment_id)) {
                    $schedules_file_id = $attachment_id;
                }
            }
        }
    }

    // Handle remove schedules file request
    $remove_schedules_file = isset($_POST['remove_schedules_file']) && $_POST['remove_schedules_file'] === '1';

    // Prepare calendar colors
    $calendar_bg = isset($_POST['calendar_bg_color']) ? $_POST['calendar_bg_color'] :
                   (isset($_POST['brand_primary_color']) ? $_POST['brand_primary_color'] : '#007bff');
    $calendar_text = isset($_POST['calendar_text_color']) ? $_POST['calendar_text_color'] :
                     (isset($_POST['brand_secondary_color']) ? $_POST['brand_secondary_color'] : '#ffffff');

    // Get registration deadline
    $registration_deadline = isset($_POST['registration_deadline']) ? sanitize_text_field(wp_unslash($_POST['registration_deadline'])) : $start_date;

    // Get min/max tickets per order
    $min_ticket = isset($_POST['min_ticket']) ? intval($_POST['min_ticket']) : 1;
    $max_ticket = isset($_POST['max_ticket']) ? intval($_POST['max_ticket']) : 10;

    // Get attendance tracking setting - accept both '1' (checkbox) and 'yes' (legacy)
    $attendance_tracking = 0;
    if (isset($_POST['attendance_tracking'])) {
        $att_val = $_POST['attendance_tracking'];
        $attendance_tracking = ($att_val === '1' || $att_val === 'yes' || $att_val === 1) ? 1 : 0;
    }

    // Get certificate settings
    $enable_certificates = !empty($_POST['enable_certificates']) ? 1 : 0;
    $certificate_template_id = isset($_POST['certificate_template_id']) ? intval($_POST['certificate_template_id']) : 0;

    // Certificate issuance method: 'auto' or 'manual'
    $certificate_issue_method = isset($_POST['certificate_issue_method']) ? sanitize_text_field(wp_unslash($_POST['certificate_issue_method'])) : 'auto';
    $auto_issue_certificate = ($certificate_issue_method === 'auto') ? 1 : 0;

    // Certificate requirements
    $certificate_require_checkin = !empty($_POST['certificate_require_checkin']) ? 1 : 0;
    $certificate_require_checkout = !empty($_POST['certificate_require_checkout']) ? 1 : 0;
    $certificate_require_event_ended = !empty($_POST['certificate_require_event_ended']) ? 1 : 0;

    // Prepare JSON data - using wp_unslash for security
    $faq = array();
    if (isset($_POST['faq_data'])) {
        $faq_json = wp_unslash($_POST['faq_data']);
        $decoded = json_decode($faq_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Sanitize each FAQ item
            $faq = array_map(function($item) {
                return array(
                    'question' => isset($item['question']) ? sanitize_text_field($item['question']) : '',
                    'answer' => isset($item['answer']) ? wp_kses_post($item['answer']) : ''
                );
            }, $decoded);
        }
    }

    $extra_fields = array();
    if (isset($_POST['extra_fields_data'])) {
        $extra_fields_json = wp_unslash($_POST['extra_fields_data']);
        $decoded = json_decode($extra_fields_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Sanitize each extra field
            $extra_fields = array_map(function($field) {
                return array(
                    'label' => isset($field['label']) ? sanitize_text_field($field['label']) : '',
                    'type' => isset($field['type']) ? sanitize_key($field['type']) : 'text',
                    'required' => isset($field['required']) ? (bool)$field['required'] : false,
                    'options' => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                    'default' => isset($field['default']) ? sanitize_text_field($field['default']) : ''
                );
            }, $decoded);
        }
    }

    $additional_sections = array();
    if (isset($_POST['additional_sections_data'])) {
        $sections_json = wp_unslash($_POST['additional_sections_data']);
        $decoded = json_decode($sections_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Sanitize each section - accept both naming conventions
            $additional_sections = array_map(function($section) {
                // Support both 'heading' and 'title' field names
                $heading = isset($section['heading']) ? $section['heading'] : (isset($section['title']) ? $section['title'] : '');
                // Support both 'button_text' and 'btn_text'
                $btn_text = isset($section['button_text']) ? $section['button_text'] : (isset($section['btn_text']) ? $section['btn_text'] : '');
                // Support both 'button_url' and 'btn_url'
                $btn_url = isset($section['button_url']) ? $section['button_url'] : (isset($section['btn_url']) ? $section['btn_url'] : '');

                return array(
                    'id' => isset($section['id']) ? sanitize_key($section['id']) : uniqid('section_'),
                    'type' => isset($section['type']) ? sanitize_key($section['type']) : 'content',
                    'heading' => sanitize_text_field($heading),
                    'content' => isset($section['content']) ? wp_kses_post($section['content']) : '',
                    'button_text' => sanitize_text_field($btn_text),
                    'button_url' => esc_url_raw($btn_url),
                    'main_image' => isset($section['main_image']) ? esc_url_raw($section['main_image']) : '',
                    'images' => isset($section['images']) && is_array($section['images']) ? array_map('intval', $section['images']) : array(),
                    'cards' => isset($section['cards']) && is_array($section['cards']) ? array_map(function($card) {
                        return array(
                            'icon' => isset($card['icon']) ? sanitize_text_field($card['icon']) : '',
                            'image' => isset($card['image']) ? absint($card['image']) : 0,
                            'title' => isset($card['title']) ? sanitize_text_field($card['title']) : '',
                            'description' => isset($card['description']) ? wp_kses_post($card['description']) : ''
                        );
                    }, $section['cards']) : array()
                );
            }, $decoded);
        }
    }

    // Schedules are now managed via sc_schedules table (not inline JSON)
    // Keep existing schedule data for backward compatibility
    $schedule = array();

    $social_links = array();
    // Accept both 'social_links' (from form) and 'social_links_data'
    $social_links_json = '';
    if (isset($_POST['social_links'])) {
        $social_links_json = wp_unslash($_POST['social_links']);
    } elseif (isset($_POST['social_links_data'])) {
        $social_links_json = wp_unslash($_POST['social_links_data']);
    }
    if (!empty($social_links_json)) {
        $decoded = json_decode($social_links_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $link) {
                $url = is_array($link) && isset($link['url']) ? esc_url_raw(trim($link['url'])) : '';
                if ($url !== '') {
                    $social_links[] = array(
                        'icon' => sanitize_text_field($link['icon'] ?? ''),
                        'url'  => $url,
                    );
                }
            }
        }
    }

    // Build organizing company JSON
    $organizing_company = array();
    $oc_name = sanitize_text_field(wp_unslash($_POST['oc_name'] ?? ''));
    if (!empty($oc_name)) {
        $organizing_company = array(
            'name'        => $oc_name,
            'logo'        => intval($_POST['oc_logo'] ?? 0) ?: null,
            'banner'      => intval($_POST['oc_banner'] ?? 0) ?: null,
            'description' => wp_kses_post(wp_unslash($_POST['oc_description'] ?? '')),
            'website'     => esc_url_raw(wp_unslash($_POST['oc_website'] ?? '')),
            'phone'       => sanitize_text_field(wp_unslash($_POST['oc_phone'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['oc_email'] ?? '')),
            'whatsapp'    => sanitize_text_field(wp_unslash($_POST['oc_whatsapp'] ?? '')),
            'social'      => array(
                'facebook'  => esc_url_raw(wp_unslash($_POST['oc_facebook'] ?? '')),
                'twitter'   => esc_url_raw(wp_unslash($_POST['oc_twitter'] ?? '')),
                'instagram' => esc_url_raw(wp_unslash($_POST['oc_instagram'] ?? '')),
                'linkedin'  => esc_url_raw(wp_unslash($_POST['oc_linkedin'] ?? '')),
            ),
        );
    }

    // Prepare event data for custom table
    $event_data = array(
        'title'                  => $event_title,
        'slug'                   => $event_slug,
        'description'            => $event_description,
        'start_date'             => $start_date,
        'end_date'               => $end_date ?: $start_date,
        'start_time'             => $start_time,
        'end_time'               => $end_time,
        'timezone'               => $timezone,
        'location_type'          => $location_type,
        'venue_name'             => $venue_name,
        'venue_address'          => $venue,
        'venue_city'             => $city,
        'venue_country'          => $country,
        'meeting_link'           => $meeting_link,
        'google_maps_url'        => isset($_POST['google_maps_url']) ? esc_url_raw(trim(wp_unslash($_POST['google_maps_url']))) : '',
        'total_capacity'         => $capacity,
        'min_tickets_per_order'  => $min_ticket,
        'max_tickets_per_order'  => $max_ticket,
        'registration_deadline'  => $registration_deadline,
        'all_day_event'          => $all_day_event,
        'attendance_tracking'    => $attendance_tracking,
        'enable_certificates'         => $enable_certificates,
        'certificate_template_id'     => $certificate_template_id,
        'auto_issue_certificate'      => $auto_issue_certificate,
        'certificate_require_checkin' => $certificate_require_checkin,
        'certificate_require_checkout'=> $certificate_require_checkout,
        'certificate_require_event_ended' => $certificate_require_event_ended,
        'calendar_bg_color'      => sanitize_hex_color($calendar_bg),
        'calendar_text_color'    => sanitize_hex_color($calendar_text),
        'status'                 => $status,
        'faq'                    => $faq,
        'schedule'               => $schedule,
        'additional_sections'    => $additional_sections,
        'social_links'           => $social_links,
        'organizing_company'     => !empty($organizing_company) ? json_encode($organizing_company, JSON_UNESCAPED_UNICODE) : null,
        'extra_fields'           => $extra_fields,
        'author_id'              => get_current_user_id(),
    );

    // On update, leave alone every column this request does not carry. The edit form
    // has no controls for several of them, and filling them with defaults wiped values
    // set elsewhere (API, imports): timezone, capacity, deadline, order limits, city.
    if ($event_id > 0) {
        $only_if_posted = array(
            'timezone'              => 'timezone',
            'total_capacity'        => 'capacity',
            'registration_deadline' => 'registration_deadline',
            'min_tickets_per_order' => 'min_ticket',
            'max_tickets_per_order' => 'max_ticket',
            'venue_city'            => 'city',
            'venue_country'         => 'country',
            'all_day_event'         => 'all_day_event',
            'google_maps_url'       => 'google_maps_url',
            'meeting_link'          => 'meeting_link',
            'calendar_bg_color'     => array('calendar_bg_color', 'brand_primary_color'),
            'calendar_text_color'   => array('calendar_text_color', 'brand_secondary_color'),
            'faq'                   => 'faq_data',
            'extra_fields'          => 'extra_fields_data',
            'additional_sections'   => 'additional_sections_data',
            'social_links'          => array('social_links', 'social_links_data'),
            'certificate_template_id' => 'certificate_template_id',
        );
        foreach ($only_if_posted as $column => $keys) {
            $posted = false;
            foreach ((array) $keys as $key) {
                $posted = $posted || isset($_POST[$key]);
            }
            if (!$posted) {
                unset($event_data[$column]);
            }
        }
        // Certificate settings render only while the module is on.
        if (!isset($_POST['certificate_issue_method'])) {
            unset(
                $event_data['enable_certificates'],
                $event_data['auto_issue_certificate'],
                $event_data['certificate_require_checkin'],
                $event_data['certificate_require_checkout'],
                $event_data['certificate_require_event_ended']
            );
        }
        // Schedules live in sc_schedules; the author stays whoever created the event.
        unset($event_data['schedule'], $event_data['author_id']);
    }

    // Add image IDs if uploaded
    if ($featured_image_id > 0) {
        $event_data['featured_image'] = $featured_image_id;
    }
    if ($logo_image_id > 0) {
        $event_data['logo_image'] = $logo_image_id;
    }
    if ($banner_image_id > 0) {
        $event_data['banner_image'] = $banner_image_id;
    }
    if ($venue_image_id > 0) {
        $event_data['venue_image'] = $venue_image_id;
    }

    if ($event_id > 0) {
        // A media picker posts an empty value when its image was removed.
        $pickers = array(
            'featured_image' => array('featured_image', $featured_image_id),
            'logo_image'     => array('existing_logo_image', $logo_image_id),
            'banner_image'   => array('existing_banner_image', $banner_image_id),
            'venue_image'    => array('venue_image', $venue_image_id),
        );
        foreach ($pickers as $column => $picker) {
            if (isset($_POST[$picker[0]]) && !$picker[1] && absint($_POST[$picker[0]]) === 0) {
                $event_data[$column] = 0;
            }
        }
        if (isset($_POST['oc_name']) && $oc_name === '') {
            $event_data['organizing_company'] = '';
        }
        if ($event_slug === '') {
            unset($event_data['slug']);
        }
    }

    // An empty value where the stored one is NULL is not a change; leave NULL alone
    // so a save without edits writes nothing new.
    if ($event_id > 0) {
        $stored_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id), ARRAY_A);
        foreach ($event_data as $column => $value) {
            if ($stored_row && array_key_exists($column, $stored_row) && $stored_row[$column] === null
                && ($value === '' || $value === 0 || $value === null || $value === array())) {
                unset($event_data[$column]);
            }
        }
    }

    // Add schedules file ID if uploaded
    if ($schedules_file_id > 0) {
        $event_data['schedules_file'] = $schedules_file_id;
    }
    // Remove schedules file if requested
    if ($remove_schedules_file && !$schedules_file_id) {
        $event_data['schedules_file'] = 0;
    }

    if ($event_id > 0) {
        // Update existing event
        $result = SC_Event::update($event_id, $event_data);
        if ($result) {
            $message = __('Event updated successfully.', 'sc_events');
        } else {
            wp_send_json_error(array('message' => __('Failed to update event.', 'sc_events')));
        }
    } else {
        // Create new event
        $event_id = SC_Event::create($event_data);
        if ($event_id) {
            $message = __('Event created successfully.', 'sc_events');
            do_action('sc_event_created', $event_id, $event_data['title'], $event_data);
        } else {
            global $wpdb;
            $db_error = $wpdb->last_error ? ' DB Error: ' . $wpdb->last_error : '';
            wp_send_json_error(array('message' => __('Failed to create event.', 'sc_events') . $db_error));
        }
    }

    // Save Tickets data to sc_tickets table.
    // Tickets are updated in place by id. Deleting and re-inserting them on every save
    // reset each ticket's sold counter, orphaned attendees that point at the old ids, and
    // turned workshop tickets into event tickets. Workshop tickets are managed on the
    // workshop form and never touched here.
    if (isset($_POST['tickets_data'])) {
        $tickets_json = wp_unslash($_POST['tickets_data']);
        $tickets = json_decode($tickets_json, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tickets)) {
            global $wpdb;
            $tickets_table = $wpdb->prefix . 'sc_tickets';
            $existing = array();
            foreach ($wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$tickets_table} WHERE event_id = %d AND (workshop_id IS NULL OR workshop_id = 0)",
                $event_id
            )) as $row) {
                $existing[(int) $row->id] = $row->name;
            }
            $kept = array();
            $truthy = function ($v) {
                return $v === true || $v === 'true' || $v === 1 || $v === '1';
            };

            $sort_order = 0;
            foreach ($tickets as $ticket) {
                if (!is_array($ticket)) {
                    continue;
                }
                $ticket_name = isset($ticket['name']) ? $ticket['name'] : '';
                $ticket_price = isset($ticket['price']) ? floatval($ticket['price']) : 0;
                // Accept both 'qty' and 'quantity' field names
                $ticket_qty = isset($ticket['qty']) ? intval($ticket['qty']) : (isset($ticket['quantity']) ? intval($ticket['quantity']) : -1);
                // Accept both 'min_qty' and 'min_per_order'
                $ticket_min = isset($ticket['min_qty']) ? intval($ticket['min_qty']) : (isset($ticket['min_per_order']) ? intval($ticket['min_per_order']) : 1);
                // Accept both 'max_qty' and 'max_per_order'
                $ticket_max = isset($ticket['max_qty']) ? intval($ticket['max_qty']) : (isset($ticket['max_per_order']) ? intval($ticket['max_per_order']) : 10);
                $ticket_desc = isset($ticket['description']) ? $ticket['description'] : '';
                // Accept 'status', 'is_active' (boolean or string)
                $ticket_status = 'active';
                if (isset($ticket['status'])) {
                    $ticket_status = $ticket['status'];
                } elseif (isset($ticket['is_active'])) {
                    $ticket_status = $truthy($ticket['is_active']) ? 'active' : 'inactive';
                }
                // Handle use_coupons_only
                if (isset($ticket['use_coupons'])) {
                    $use_coupons = $ticket['use_coupons'] ? 1 : 0;
                } elseif (isset($ticket['use_coupons_only'])) {
                    $use_coupons = $truthy($ticket['use_coupons_only']) ? 1 : 0;
                } else {
                    $use_coupons = !empty($ticket['enable_coupons']) && $ticket['enable_coupons'] !== 'false' ? 1 : 0;
                }

                $ticket_data = array(
                    'event_id'      => $event_id,
                    'name'          => sanitize_text_field($ticket_name),
                    'description'   => sanitize_textarea_field($ticket_desc),
                    'price'         => $ticket_price,
                    'quantity'      => $ticket_qty,
                    'min_per_order' => $ticket_min,
                    'max_per_order' => $ticket_max,
                    'status'        => $ticket_status,
                    'sort_order'    => $sort_order++,
                    'sale_start'    => isset($ticket['sale_start']) ? $ticket['sale_start'] : null,
                    'sale_end'      => isset($ticket['sale_end']) ? $ticket['sale_end'] : null,
                    'use_coupons'   => $use_coupons,
                );
                if (isset($ticket['ticket_type'])) {
                    $ticket_data['ticket_type'] = sanitize_text_field($ticket['ticket_type']);
                }

                // Match an existing event ticket: by id, else by exact name (older forms dropped the id on edit).
                $match = isset($ticket['id']) ? (int) $ticket['id'] : 0;
                if (!$match || !isset($existing[$match]) || isset($kept[$match])) {
                    $match = 0;
                    foreach ($existing as $existing_id => $existing_name) {
                        if (!isset($kept[$existing_id]) && $existing_name === $ticket_data['name']) {
                            $match = $existing_id;
                            break;
                        }
                    }
                }

                if ($match) {
                    // The sold counter belongs to registrations, not to the form.
                    SC_Ticket::update($match, $ticket_data);
                    $kept[$match] = true;
                } else {
                    $ticket_data['sold'] = 0;
                    if (!isset($ticket_data['ticket_type'])) {
                        $ticket_data['ticket_type'] = 'general';
                    }
                    $new_ticket_id = SC_Ticket::create($ticket_data);
                    if ($new_ticket_id) {
                        $kept[(int) $new_ticket_id] = true;
                    }
                }
            }

            // Tickets removed in the form: delete unused ones; switch off any that people hold.
            foreach (array_diff_key($existing, $kept) as $removed_id => $removed_name) {
                $held = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE ticket_id = %d",
                    $removed_id
                ));
                if ($held > 0) {
                    SC_Ticket::update($removed_id, array('is_active' => 0));
                } else {
                    SC_Ticket::delete($removed_id);
                }
            }

            // Update event capacity from tickets
            SC_Event::update_stats($event_id);
        }
    }

    // Save Speakers
    if (isset($_POST['speakers']) && is_array($_POST['speakers'])) {
        $speakers = array_map('intval', $_POST['speakers']);
        $speakers = array_filter($speakers); // Remove zeros
        if (class_exists('SC_Speaker')) {
            SC_Speaker::sync_event_speakers($event_id, $speakers);
        }
    } elseif (!isset($_POST['speakers']) && (!function_exists('sc_is_module_enabled') || sc_is_module_enabled('speakers'))) {
        // No speakers field means clear all speakers
        if (class_exists('SC_Speaker')) {
            SC_Speaker::sync_event_speakers($event_id, array());
        }
    }

    // Save Organizers
    if (isset($_POST['organizers']) && is_array($_POST['organizers'])) {
        $organizers = array_map('intval', $_POST['organizers']);
        $organizers = array_filter($organizers); // Remove zeros
        if (class_exists('SC_Organizer')) {
            SC_Organizer::sync_event_organizers($event_id, $organizers);
        }
    } elseif (!isset($_POST['organizers'])) {
        // No organizers field means clear all organizers
        if (class_exists('SC_Organizer')) {
            SC_Organizer::sync_event_organizers($event_id, array());
        }
    }

    // Save Categories
    global $wpdb;
    $cat_table = $wpdb->prefix . 'sc_event_categories';

    // First delete existing categories for this event
    $wpdb->delete($cat_table, array('event_id' => $event_id), array('%d'));

    // Then add new categories
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $categories = array_map('intval', $_POST['categories']);
        $categories = array_filter($categories);
        $sort_order = 0;
        foreach ($categories as $cat_id) {
            $wpdb->insert($cat_table, array(
                'event_id' => $event_id,
                'category_id' => $cat_id,
                'sort_order' => $sort_order++
            ), array('%d', '%d', '%d'));
        }
    }

    // Sync sponsors (name="sponsors[]" sends array directly)
    $pivot_sponsors = $wpdb->prefix . 'sc_event_sponsors';
    $sync_sponsors = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sponsors') || isset($_POST['sponsors']);
    if ($sync_sponsors) {
        $wpdb->delete($pivot_sponsors, array('event_id' => $event_id), array('%d'));
    }
    if ($sync_sponsors && !empty($_POST['sponsors']) && is_array($_POST['sponsors'])) {
        foreach ($_POST['sponsors'] as $index => $sponsor_id) {
            $wpdb->insert($pivot_sponsors, array(
                'event_id' => $event_id,
                'sponsor_id' => intval($sponsor_id),
                'sort_order' => $index
            ), array('%d', '%d', '%d'));
        }
    }

    // Sync partners (name="partners[]" sends array directly)
    $pivot_partners = $wpdb->prefix . 'sc_event_partners';
    $sync_partners = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('partners') || isset($_POST['partners']);
    if ($sync_partners) {
        $wpdb->delete($pivot_partners, array('event_id' => $event_id), array('%d'));
    }
    if ($sync_partners && !empty($_POST['partners']) && is_array($_POST['partners'])) {
        foreach ($_POST['partners'] as $index => $partner_id) {
            $wpdb->insert($pivot_partners, array(
                'event_id' => $event_id,
                'partner_id' => intval($partner_id),
                'sort_order' => $index
            ), array('%d', '%d', '%d'));
        }
    }

    $saved_event = SC_Event::get($event_id);
    wp_send_json_success(array(
        'message'  => $message,
        'event_id' => $event_id,
        'slug'     => $saved_event ? $saved_event->slug : '',
        'redirect' => home_url('/event-manager-dashboard/event-edit?id=' . $event_id . '&created=1'),
    ));

    } catch (Exception $e) {
        restore_error_handler();
        wp_send_json_error(array('message' => __('Server error: ', 'sc_events') . $e->getMessage()));
    } catch (Error $e) {
        restore_error_handler();
        wp_send_json_error(array('message' => __('Server error: ', 'sc_events') . $e->getMessage()));
    }

    restore_error_handler();
}

/**
 * Get Event for Editing - Using Custom Tables Only
 */
add_action('wp_ajax_sc_get_event_for_edit', 'sc_get_event_for_edit');
function sc_get_event_for_edit() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Verify custom tables are available
    if (!class_exists('SC_Event') || !class_exists('SC_Ticket')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    // Get event from custom table
    $event = SC_Event::get($event_id);

    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // SECURITY: Verify user can access this event
    if (!current_user_can('manage_options') && $event->author_id != get_current_user_id()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get tickets from custom table
    $tickets = SC_Ticket::get_by_event($event_id, array('is_active' => null));
    $tickets_array = array();
    foreach ($tickets as $ticket) {
        $tickets_array[] = array(
            'id'          => $ticket->id,
            'name'        => $ticket->name,
            'description' => $ticket->description,
            'price'       => $ticket->price,
            'qty'         => $ticket->quantity,
            'sold'        => $ticket->sold,
            'min_qty'     => $ticket->min_per_order,
            'max_qty'     => $ticket->max_per_order,
            'status'      => $ticket->status,
            'sale_start'  => $ticket->sale_start,
            'sale_end'    => $ticket->sale_end,
        );
    }

    // Get image URLs
    $image_url = $event->featured_image ? wp_get_attachment_url($event->featured_image) : '';
    $brand_logo_url = $event->logo_image ? wp_get_attachment_url($event->logo_image) : '';
    $banner_url = $event->banner_image ? wp_get_attachment_url($event->banner_image) : '';
    $schedules_file_url = $event->schedules_file ? wp_get_attachment_url($event->schedules_file) : '';

    // Get speakers and organizers IDs
    $speaker_ids = array();
    if (class_exists('SC_Speaker')) {
        $speakers = SC_Speaker::get_by_event($event_id);
        foreach ($speakers as $speaker) {
            $speaker_ids[] = $speaker->id;
        }
    }

    $organizer_ids = array();
    if (class_exists('SC_Organizer')) {
        $organizers = SC_Organizer::get_by_event($event_id);
        foreach ($organizers as $organizer) {
            $organizer_ids[] = $organizer->id;
        }
    }

    // Get categories
    global $wpdb;
    $category_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT category_id FROM {$wpdb->prefix}sc_event_categories WHERE event_id = %d ORDER BY sort_order ASC",
        $event_id
    ));

    // Get venue image URL
    $venue_image_url = $event->venue_image ? wp_get_attachment_url($event->venue_image) : '';

    // Get sponsors
    $sponsor_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT sponsor_id FROM {$wpdb->prefix}sc_event_sponsors WHERE event_id = %d ORDER BY sort_order ASC",
        $event_id
    ));

    // Get partners
    $partner_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT partner_id FROM {$wpdb->prefix}sc_event_partners WHERE event_id = %d ORDER BY sort_order ASC",
        $event_id
    ));

    // Convert start_time and end_time to 24-hour format for HTML input
    $start_time = $event->start_time ? sc_convert_to_24hour($event->start_time) : '';
    $end_time = $event->end_time ? sc_convert_to_24hour($event->end_time) : '';

    $event_data = array(
        'ID'                    => $event->id,
        'title'                 => $event->title,
        'slug'                  => $event->slug,
        'description'           => $event->description,
        'start_date'            => $event->start_date,
        'end_date'              => $event->end_date,
        'start_time'            => $start_time,
        'end_time'              => $end_time,
        'venue'                 => $event->venue_name,
        'capacity'              => $event->total_capacity,
        'status'                => $event->status,
        'image_url'             => $image_url,
        'all_day_event'         => $event->all_day_event,
        'timezone'              => $event->timezone,
        'location_type'         => $event->location_type,
        'meeting_link'          => $event->meeting_link,
        'faq'                   => $event->faq ?: array(),
        'extra_fields'          => $event->extra_fields ?: array(),
        'additional_sections'   => $event->additional_sections ?: array(),
        'brand_primary_color'   => $event->calendar_bg_color ?: '#007bff',
        'brand_secondary_color' => $event->calendar_text_color ?: '#ffffff',
        'brand_logo_url'        => $brand_logo_url,
        'banner_url'            => $banner_url,
        'tickets'               => $tickets_array,
        'schedules'             => $event->schedule ?: array(),
        'social_links'          => $event->social_links ?: array(),
        'attendance_tracking'   => $event->attendance_tracking ? 'yes' : 'no',
        'enable_certificates'   => $event->enable_certificates ?? 0,
        'certificate_template_id' => $event->certificate_template_id ?? 0,
        'auto_issue_certificate' => $event->auto_issue_certificate ?? 0,
        'city'                  => $event->venue_city,
        'country'               => $event->venue_country,
        'schedules_file_url'    => $schedules_file_url,
        'venue_image'           => $event->venue_image ?? 0,
        'venue_image_url'       => $venue_image_url,
        'speakers'              => $speaker_ids,
        'organizers'            => $organizer_ids,
        'categories'            => $category_ids,
        'sponsors'              => array_map('intval', $sponsor_ids),
        'partners'              => array_map('intval', $partner_ids),
    );

    wp_send_json_success(array('event' => $event_data));
}

/**
 * Delete Event - Using Custom Tables Only
 */
add_action('wp_ajax_sc_delete_event', 'sc_delete_event');
function sc_delete_event() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Event')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    // Get event and verify ownership
    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    if (!current_user_can('manage_options') && $event->author_id != get_current_user_id()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    // Check if event has any attendees (tickets sold)
    $attendees_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE event_id = %d",
        $event_id
    ));

    if ($attendees_count > 0) {
        // Event has attendees - disable instead of delete
        $result = SC_Event::update($event_id, array('status' => 'disabled'));

        if ($result !== false) {
            delete_transient('sc_events_page_stats');
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Event has %d attendees. It has been disabled instead of deleted to preserve data.', 'sc_events'),
                    $attendees_count
                ),
                'action' => 'disabled'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to disable event.', 'sc_events')));
        }
    } else {
        // No attendees - safe to delete
        $result = SC_Event::delete($event_id);

        if ($result) {
            delete_transient('sc_events_page_stats');
            wp_send_json_success(array(
                'message' => __('Event deleted successfully.', 'sc_events'),
                'action' => 'deleted'
            ));
        } else {
            $error_msg = __('Failed to delete event.', 'sc_events');
            if ($wpdb->last_error) {
                error_log('SC Event Delete Error: ' . $wpdb->last_error);
            }
            wp_send_json_error(array('message' => $error_msg));
        }
    }
}

/**
 * Update Event Status (Enable/Disable)
 */
add_action('wp_ajax_sc_update_event_status', 'sc_update_event_status');
function sc_update_event_status() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Event')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    // Validate status
    $allowed_statuses = array('publish', 'draft', 'disabled', 'completed');
    if (!in_array($new_status, $allowed_statuses)) {
        wp_send_json_error(array('message' => __('Invalid status.', 'sc_events')));
    }

    // Get event and verify ownership
    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    if (!current_user_can('manage_options') && $event->author_id != get_current_user_id()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Update status
    $result = SC_Event::update($event_id, array('status' => $new_status));

    if ($result !== false) {
        delete_transient('sc_events_page_stats');

        $status_messages = array(
            'publish' => __('Event published successfully.', 'sc_events'),
            'draft' => __('Event saved as draft.', 'sc_events'),
            'disabled' => __('Event disabled successfully.', 'sc_events'),
            'completed' => __('Event marked as completed.', 'sc_events'),
        );

        wp_send_json_success(array(
            'message' => $status_messages[$new_status] ?? __('Event status updated.', 'sc_events'),
            'new_status' => $new_status
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to update event status.', 'sc_events')));
    }
}

/**
 * Duplicate Event - Using Custom Tables Only
 */
add_action('wp_ajax_sc_duplicate_event', 'sc_duplicate_event');
function sc_duplicate_event() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Event') || !class_exists('SC_Ticket')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    // Get original event
    $original = SC_Event::get($event_id);
    if (!$original) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Verify ownership
    if (!current_user_can('manage_options') && $original->author_id != get_current_user_id()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Create duplicate event
    $new_event_data = array(
        'title'                 => $original->title . ' (Copy)',
        'slug'                  => '',
        'description'           => $original->description,
        'start_date'            => $original->start_date,
        'end_date'              => $original->end_date,
        'start_time'            => $original->start_time,
        'end_time'              => $original->end_time,
        'timezone'              => $original->timezone,
        'location_type'         => $original->location_type,
        'venue_name'            => $original->venue_name,
        'venue_address'         => $original->venue_address,
        'venue_city'            => $original->venue_city,
        'venue_country'         => $original->venue_country,
        'meeting_link'          => $original->meeting_link,
        'total_capacity'        => $original->total_capacity,
        'featured_image'        => $original->featured_image,
        'logo_image'            => $original->logo_image,
        'banner_image'          => $original->banner_image,
        'calendar_bg_color'     => $original->calendar_bg_color,
        'calendar_text_color'   => $original->calendar_text_color,
        'faq'                   => $original->faq,
        'schedule'              => $original->schedule,
        'additional_sections'   => $original->additional_sections,
        'social_links'          => $original->social_links,
        'extra_fields'          => $original->extra_fields,
        'all_day_event'         => $original->all_day_event,
        'attendance_tracking'   => $original->attendance_tracking,
        'enable_certificates'   => $original->enable_certificates ?? 0,
        'certificate_template_id' => $original->certificate_template_id ?? 0,
        'auto_issue_certificate' => $original->auto_issue_certificate ?? 0,
        'status'                => 'draft',
        'author_id'             => get_current_user_id(),
    );

    $new_event_id = SC_Event::create($new_event_data);

    if (!$new_event_id) {
        wp_send_json_error(array('message' => __('Failed to duplicate event.', 'sc_events')));
    }

    // Copy tickets
    $tickets = SC_Ticket::get_by_event($event_id, array('is_active' => null));
    foreach ($tickets as $ticket) {
        // Workshop tickets stay with their workshops; a copied event has none.
        if (!empty($ticket->workshop_id)) {
            continue;
        }
        SC_Ticket::create(array(
            'event_id'      => $new_event_id,
            'name'          => $ticket->name,
            'description'   => $ticket->description,
            'price'         => $ticket->price,
            'quantity'      => $ticket->quantity,
            'sold'          => 0,
            'min_per_order' => $ticket->min_per_order,
            'max_per_order' => $ticket->max_per_order,
            'status'        => $ticket->status,
            'sort_order'    => $ticket->sort_order,
        ));
    }

    wp_send_json_success(array(
        'message' => __('Event duplicated successfully.', 'sc_events'),
        'new_event_id' => $new_event_id
    ));
}

/**
 * Get Event Details (for modal view) - Using Custom Tables Only
 */
add_action('wp_ajax_sc_get_event_details', 'sc_get_event_details');
function sc_get_event_details() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Event') || !class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    $event = SC_Event::get($event_id);

    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // SECURITY: Verify user can view this event
    if (!current_user_can('manage_options') && $event->author_id != get_current_user_id()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Count tickets sold from custom table
    $tickets_sold = SC_Attendee::count(array('event_id' => $event_id));

    // Get image URL
    $image_url = $event->featured_image ? wp_get_attachment_url($event->featured_image) : '';

    // Build HTML
    $html = '<div class="row">';

    if ($image_url) {
        $html .= '<div class="col-md-4">';
        $html .= '<img src="' . esc_url($image_url) . '" class="img-fluid rounded" alt="' . esc_attr($event->title) . '">';
        $html .= '</div>';
        $html .= '<div class="col-md-8">';
    } else {
        $html .= '<div class="col-md-12">';
    }

    $html .= '<h3>' . esc_html($event->title) . '</h3>';

    if ($event->description) {
        $html .= '<div class="mb-3">' . wpautop($event->description) . '</div>';
    }

    $html .= '<table class="table table-bordered">';
    $html .= '<tr><th style="width: 30%;">Start Date</th><td>' . ($event->start_date ? date('F j, Y', strtotime($event->start_date)) : 'Not set') . '</td></tr>';
    $html .= '<tr><th>End Date</th><td>' . ($event->end_date ? date('F j, Y', strtotime($event->end_date)) : 'Not set') . '</td></tr>';
    $html .= '<tr><th>Start Time</th><td>' . ($event->start_time ? date('g:i A', strtotime($event->start_time)) : 'Not set') . '</td></tr>';
    $html .= '<tr><th>End Time</th><td>' . ($event->end_time ? date('g:i A', strtotime($event->end_time)) : 'Not set') . '</td></tr>';
    $html .= '<tr><th>Venue</th><td>' . ($event->venue_name ?: 'Not set') . '</td></tr>';
    $html .= '<tr><th>Capacity</th><td>' . ($event->total_capacity ?: 'Unlimited') . '</td></tr>';
    $html .= '<tr><th>Tickets Sold</th><td><span class="badge badge-info">' . ($tickets_sold ?: 0) . '</span></td></tr>';
    $html .= '<tr><th>Status</th><td>';

    switch ($event->status) {
        case 'publish':
            $html .= '<span class="badge badge-success">Published</span>';
            break;
        case 'draft':
            $html .= '<span class="badge badge-warning">Draft</span>';
            break;
        default:
            $html .= '<span class="badge badge-secondary">' . ucfirst($event->status) . '</span>';
    }

    $html .= '</td></tr>';
    $html .= '</table>';

    // Event page link - use slug
    $event_url = home_url('/event/' . $event->slug);
    $html .= '<div class="mt-3">';
    $html .= '<a href="' . esc_url($event_url) . '" target="_blank" class="btn btn-primary"><i class="fa fa-external-link"></i> View Event Page</a>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';

    wp_send_json_success(array('html' => $html));
}

/**
 * =========================================
 * ATTENDEES MANAGEMENT AJAX HANDLERS
 * Using Custom Tables Only
 * =========================================
 */

/**
 * Get Event Attendees - Using Custom Tables Only
 */
add_action('wp_ajax_sc_get_event_attendees', 'sc_get_event_attendees');
function sc_get_event_attendees() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Attendee') || !class_exists('SC_Event') || !class_exists('SC_Ticket')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    // Get event from custom table
    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    $attendees_data = array();
    $used_count = 0;
    $unused_count = 0;

    // Get attendees from custom table
    $sc_attendees = SC_Attendee::get_by_event($event_id, array(
        'limit' => 10000,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    ));

    // Cache tickets
    $tickets_cache = array();

    foreach ($sc_attendees as $attendee) {
        // Get ticket name
        $ticket_type = '';
        if ($attendee->ticket_id) {
            if (!isset($tickets_cache[$attendee->ticket_id])) {
                $ticket = SC_Ticket::get($attendee->ticket_id);
                if ($ticket) {
                    $tickets_cache[$attendee->ticket_id] = $ticket;
                }
            }
            if (isset($tickets_cache[$attendee->ticket_id])) {
                $ticket_type = $tickets_cache[$attendee->ticket_id]->name;
            }
        }

        // Count stats
        if ($attendee->checked_in) {
            $used_count++;
        } else {
            $unused_count++;
        }

        // Status badge
        $status_badge = '';
        if ($attendee->payment_status === 'success') {
            $status_badge = '<span class="badge badge-success">Confirmed</span>';
        } elseif ($attendee->payment_status === 'pending') {
            $status_badge = '<span class="badge badge-warning">Pending</span>';
        } else {
            $status_badge = '<span class="badge badge-secondary">' . ucfirst($attendee->payment_status) . '</span>';
        }

        $attendees_data[] = array(
            'ID' => $attendee->id,
            'name' => $attendee->name ?: 'N/A',
            'email' => $attendee->email ?: 'N/A',
            'phone' => $attendee->phone,
            'ticket_type' => $ticket_type,
            'status' => $attendee->payment_status,
            'status_badge' => $status_badge,
            'ticket_status' => $attendee->checked_in ? 'used' : 'unused',
            'date' => $attendee->created_at
        );
    }

    // Calculate stats
    $total_attendees = count($attendees_data);
    $remaining = $event->total_capacity ? ($event->total_capacity - $total_attendees) : 'Unlimited';

    $stats = array(
        'total' => $total_attendees,
        'used' => $used_count,
        'unused' => $unused_count,
        'remaining' => $remaining
    );

    wp_send_json_success(array(
        'attendees' => $attendees_data,
        'stats' => $stats
    ));
}

/**
 * Export Event Attendees to CSV - Using Custom Tables Only
 */
add_action('wp_ajax_sc_export_event_attendees', 'sc_export_event_attendees');
function sc_export_event_attendees() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Attendee') || !class_exists('SC_Event') || !class_exists('SC_Ticket')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Build CSV
    $csv_data = "\xEF\xBB\xBF"; // UTF-8 BOM
    $csv_data .= "Name,Email,Phone,Ticket Type,Status,Checked In,Registration Date\n";
    $attendee_count = 0;

    // Get attendees from custom table
    $sc_attendees = SC_Attendee::get_by_event($event_id, array(
        'limit' => 100000,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    ));

    // Cache tickets
    $tickets_cache = array();

    foreach ($sc_attendees as $attendee) {
        // Get ticket name
        $ticket_type = 'General';
        if ($attendee->ticket_id) {
            if (!isset($tickets_cache[$attendee->ticket_id])) {
                $ticket = SC_Ticket::get($attendee->ticket_id);
                if ($ticket) {
                    $tickets_cache[$attendee->ticket_id] = $ticket;
                }
            }
            if (isset($tickets_cache[$attendee->ticket_id])) {
                $ticket_type = $tickets_cache[$attendee->ticket_id]->name;
            }
        }

        $csv_data .= sprintf(
            "%s,%s,%s,%s,%s,%s,%s\n",
            str_replace(',', ' ', $attendee->name),
            $attendee->email,
            $attendee->phone ?: 'N/A',
            str_replace(',', ' ', $ticket_type),
            ucfirst($attendee->payment_status),
            $attendee->checked_in ? 'Yes' : 'No',
            $attendee->created_at
        );
        $attendee_count++;
    }

    $filename = 'attendees-' . sanitize_title($event->title) . '-' . date('Y-m-d') . '.csv';

    wp_send_json_success(array(
        'csv' => $csv_data,
        'filename' => $filename,
        'message' => sprintf(__('Exported %d attendees successfully.', 'sc_events'), $attendee_count)
    ));
}

/**
 * Resend Email to Attendee - Using Custom Tables Only
 */
add_action('wp_ajax_sc_resend_attendee_email', 'sc_resend_attendee_email');
function sc_resend_attendee_email() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Invalid attendee ID.', 'sc_events')));
    }

    // Get attendee from custom table
    $attendee = SC_Attendee::get($attendee_id);
    if (!$attendee) {
        wp_send_json_error(array('message' => __('Attendee not found.', 'sc_events')));
    }

    // Get event from custom table
    $event = SC_Event::get($attendee->event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Send email
    $subject = sprintf(__('Confirmation: %s', 'sc_events'), $event->title);
    $message = sprintf(
        __("Hello %s,\n\nThank you for registering for %s.\n\nEvent Details:\n- Event: %s\n- Date: %s\n\nWe look forward to seeing you!\n\nBest regards,\n%s", 'sc_events'),
        $attendee->name,
        $event->title,
        $event->title,
        $event->start_date,
        get_bloginfo('name')
    );

    $sent = wp_mail($attendee->email, $subject, $message);

    if ($sent) {
        wp_send_json_success(array('message' => __('Email sent successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to send email.', 'sc_events')));
    }
}

/**
 * Delete Attendee - Using Custom Tables Only
 */
add_action('wp_ajax_sc_delete_attendee', 'sc_delete_attendee');
function sc_delete_attendee() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => __('Custom tables not available.', 'sc_events')));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Invalid attendee ID.', 'sc_events')));
    }

    $result = SC_Attendee::delete($attendee_id);

    if ($result) {
        wp_send_json_success(array('message' => __('Attendee deleted successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to delete attendee.', 'sc_events')));
    }
}

/**
 * Events list: one page of rows plus the tab counts.
 *
 * Registrations, check-ins and revenue are counted from the attendees table.
 * Ticket "sold" counters are not used: saves used to reset them.
 */
add_action('wp_ajax_sc_get_events_paginated', 'sc_get_events_paginated');
function sc_get_events_paginated() {
    global $wpdb;

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $events_table    = $wpdb->prefix . 'sc_events';
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $workshops_table = $wpdb->prefix . 'sc_workshops';
    $tickets_table   = $wpdb->prefix . 'sc_tickets';

    $page        = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
    $per_page    = min(200, max(10, absint(wp_unslash($_POST['per_page'] ?? 25))));
    $search      = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $view        = sanitize_key(wp_unslash($_POST['view'] ?? 'all'));
    $category_id = absint(wp_unslash($_POST['category_id'] ?? 0));
    $today       = current_time('Y-m-d');

    // Filters shared by the rows and every tab count.
    $where  = array('1=1');
    $values = array();
    if ($search !== '') {
        $like    = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(e.title LIKE %s OR e.venue_name LIKE %s OR e.venue_city LIKE %s OR e.venue_address LIKE %s)';
        $values  = array_merge($values, array($like, $like, $like, $like));
    }
    if ($category_id) {
        $where[]  = "e.id IN (SELECT event_id FROM {$wpdb->prefix}sc_event_categories WHERE category_id = %d)";
        $values[] = $category_id;
    }
    $base_where = implode(' AND ', $where);

    // Each tab is a fixed SQL condition; "All" leaves out disabled events, as before.
    $past_date = 'COALESCE(e.end_date, e.start_date)';
    $views = array(
        'all'       => "e.status <> 'disabled'",
        'upcoming'  => $wpdb->prepare("e.status = 'publish' AND {$past_date} >= %s", $today),
        'past'      => $wpdb->prepare("e.status IN ('publish','completed') AND {$past_date} < %s", $today),
        'draft'     => "e.status = 'draft'",
        'completed' => "e.status = 'completed'",
        'disabled'  => "e.status IN ('disabled','cancelled','private')",
    );
    $view = isset($views[$view]) ? $view : 'all';
    $where_sql = $base_where . ' AND ' . $views[$view];

    $sortable  = array('title' => 'e.title', 'start_date' => 'e.start_date', 'created_at' => 'e.created_at');
    $orderby   = sanitize_key(wp_unslash($_POST['orderby'] ?? 'start_date'));
    $orderby   = isset($sortable[$orderby]) ? $orderby : 'start_date';
    $order     = sanitize_key(wp_unslash($_POST['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $order_sql = $sortable[$orderby] . ' ' . $order . ', e.id DESC';

    $prepare = function ($sql, $args) use ($wpdb) {
        return $args ? $wpdb->prepare($sql, $args) : $sql;
    };

    $total = (int) $wpdb->get_var($prepare("SELECT COUNT(*) FROM {$events_table} e WHERE {$where_sql}", $values));

    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.title, e.slug, e.status, e.start_date, e.end_date, e.start_time, e.end_time, e.all_day_event,
                e.location_type, e.venue_name, e.venue_city, e.featured_image, e.created_at
         FROM {$events_table} e WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    $ids = array_map('intval', wp_list_pluck($events, 'id'));
    $stats = $workshops = $tickets = array();
    if ($ids) {
        $in = implode(',', $ids);
        foreach ($wpdb->get_results(
            "SELECT event_id,
                    COUNT(*) AS all_rows,
                    SUM(workshop_id IS NULL AND status = 'active' AND payment_status = 'success') AS registered,
                    SUM(workshop_id IS NOT NULL AND status = 'active' AND payment_status = 'success') AS workshop_registrations,
                    SUM(workshop_id IS NULL AND checked_in = 1 AND status = 'active' AND payment_status = 'success') AS checked_in,
                    COALESCE(SUM(CASE WHEN status = 'active' AND payment_status = 'success' THEN amount_paid END), 0) AS revenue
             FROM {$attendees_table} WHERE event_id IN ({$in}) GROUP BY event_id"
        ) as $row) {
            $stats[(int) $row->event_id] = $row;
        }
        foreach ($wpdb->get_results("SELECT event_id, COUNT(*) AS n FROM {$workshops_table} WHERE event_id IN ({$in}) GROUP BY event_id") as $row) {
            $workshops[(int) $row->event_id] = (int) $row->n;
        }
        foreach ($wpdb->get_results("SELECT event_id, COUNT(*) AS n FROM {$tickets_table} WHERE event_id IN ({$in}) AND (workshop_id IS NULL OR workshop_id = 0) AND is_active = 1 GROUP BY event_id") as $row) {
            $tickets[(int) $row->event_id] = (int) $row->n;
        }
    }

    $rows = array();
    foreach ($events as $event) {
        $id = (int) $event->id;
        $s  = $stats[$id] ?? null;
        $rows[] = array(
            'id'                     => $id,
            'title'                  => $event->title,
            'slug'                   => $event->slug,
            'url'                    => home_url('/event/' . $event->slug . '/'),
            'status'                 => $event->status,
            'start_date'             => $event->start_date,
            'end_date'               => $event->end_date,
            'start_time'             => $event->start_time,
            'end_time'               => $event->end_time,
            'all_day'                => (bool) $event->all_day_event,
            'location_type'          => $event->location_type,
            'venue_name'             => $event->venue_name,
            'venue_city'             => $event->venue_city,
            'thumb'                  => $event->featured_image ? (wp_get_attachment_image_url((int) $event->featured_image, 'thumbnail') ?: '') : '',
            'created_at'             => $event->created_at,
            'attendee_rows'          => $s ? (int) $s->all_rows : 0,
            'registered'             => $s ? (int) $s->registered : 0,
            'workshop_registrations' => $s ? (int) $s->workshop_registrations : 0,
            'checked_in'             => $s ? (int) $s->checked_in : 0,
            'revenue'                => $s ? (float) $s->revenue : 0.0,
            'workshops'              => $workshops[$id] ?? 0,
            'tickets'                => $tickets[$id] ?? 0,
        );
    }

    $response = array(
        'events'       => $rows,
        'total'        => $total,
        'pages'        => (int) ceil($total / $per_page),
        'current_page' => $page,
    );

    if (!empty($_POST['with_counts'])) {
        $parts = array();
        foreach ($views as $key => $condition) {
            $parts[] = "COALESCE(SUM({$condition}), 0) AS `{$key}`";
        }
        $counts = $wpdb->get_row($prepare("SELECT " . implode(', ', $parts) . " FROM {$events_table} e WHERE {$base_where}", $values), ARRAY_A);
        $response['counts'] = array_map('intval', $counts ?: array_fill_keys(array_keys($views), 0));
    }

    wp_send_json_success($response);
}

/**
 * Upload Section Images (for Additional Sections)
 * With improved error handling and support for compressed images
 */
add_action('wp_ajax_sc_upload_section_images', 'sc_upload_section_images');
function sc_upload_section_images() {
    // Security: Nonce verification
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(__('فشل التحقق الأمني.', 'sc_events'));
    }

    // Security: Check user capability
    if (!current_user_can('upload_files')) {
        wp_send_json_error(__('ليس لديك صلاحية رفع الملفات.', 'sc_events'));
    }

    // Security: Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(__('ليس لديك الصلاحية.', 'sc_events'));
    }

    if (empty($_FILES['files'])) {
        wp_send_json_error(__('لم يتم اختيار أي ملفات.', 'sc_events'));
    }

    // Security: Allowed file types (images only)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_file_size = 10 * 1024 * 1024; // 10MB max per file (increased since client compresses)

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $uploaded_urls = [];
    $errors = [];
    $files = $_FILES['files'];
    $file_count = count($files['name']);

    // Security: Limit number of files per upload
    if ($file_count > 10) {
        wp_send_json_error(__('الحد الأقصى 10 ملفات في كل مرة.', 'sc_events'));
    }

    for ($i = 0; $i < $file_count; $i++) {
        $filename = sanitize_file_name($files['name'][$i]);

        // Check for upload errors
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE   => 'حجم الملف أكبر من الحد المسموح في السيرفر',
                UPLOAD_ERR_FORM_SIZE  => 'حجم الملف أكبر من الحد المسموح',
                UPLOAD_ERR_PARTIAL    => 'لم يكتمل رفع الملف',
                UPLOAD_ERR_NO_FILE    => 'لم يتم اختيار ملف',
                UPLOAD_ERR_NO_TMP_DIR => 'مشكلة في السيرفر: لا يوجد مجلد مؤقت',
                UPLOAD_ERR_CANT_WRITE => 'مشكلة في السيرفر: لا يمكن الكتابة',
                UPLOAD_ERR_EXTENSION  => 'امتداد الملف غير مسموح',
            );
            $error_code = $files['error'][$i];
            $errors[] = $filename . ': ' . ($error_messages[$error_code] ?? 'خطأ غير معروف');
            continue;
        }

        // Security: Validate file type
        $file_type = wp_check_filetype($files['name'][$i]);
        if (!in_array($files['type'][$i], $allowed_types, true) || !$file_type['ext']) {
            $errors[] = $filename . ': نوع الملف غير مسموح (صور فقط)';
            continue;
        }

        // Security: Validate file size
        if ($files['size'][$i] > $max_file_size) {
            $size_mb = round($files['size'][$i] / 1024 / 1024, 1);
            $errors[] = $filename . ': حجم الملف كبير جداً (' . $size_mb . 'MB)';
            continue;
        }

        // Create a file array for media_handle_sideload
        $file_array = [
            'name' => $filename,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];

        // Move uploaded file to temp location
        $temp_file = wp_tempnam($file_array['name']);
        if (!move_uploaded_file($file_array['tmp_name'], $temp_file)) {
            $errors[] = $filename . ': فشل نقل الملف المؤقت';
            continue;
        }

        // Upload to media library
        $attachment_id = media_handle_sideload(array(
            'name' => $file_array['name'],
            'type' => $file_array['type'],
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => $file_array['size']
        ), 0);

        if (is_wp_error($attachment_id)) {
            $errors[] = $filename . ': ' . $attachment_id->get_error_message();
            @unlink($temp_file);
            continue;
        }

        // Store both ID and URL for the frontend
        $uploaded_urls[] = array(
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        );

        // Clean up temp file
        @unlink($temp_file);
    }

    // Return results
    if (empty($uploaded_urls)) {
        $error_message = __('فشل رفع جميع الصور.', 'sc_events');
        if (!empty($errors)) {
            $error_message .= ' ' . implode(' | ', array_slice($errors, 0, 3));
        }
        wp_send_json_error($error_message);
    }

    // Success - also include warnings if some files failed
    if (!empty($errors) && count($errors) < $file_count) {
        // Some succeeded, some failed - still return success with the uploaded URLs
        // The errors will be logged but we return what we could upload
        error_log('SC Events - Partial upload errors: ' . implode(', ', $errors));
    }

    wp_send_json_success($uploaded_urls);
}
