<?php
/**
 * Speakers, Organizers & Categories AJAX Handlers
 * Using Custom Tables (sc_speakers, sc_organizers)
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if speakers module is enabled before registering AJAX handlers
$speakers_module_active = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('speakers');

// ==========================================
// SPEAKERS HANDLERS (Custom Tables)
// ==========================================

if ($speakers_module_active):

/**
 * Get all speakers from custom table
 */
add_action('wp_ajax_sc_get_speakers', 'sc_get_speakers');
function sc_get_speakers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';

    $speakers = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");

    $speakers_data = array();
    foreach ($speakers as $speaker) {
        $social_links = json_decode($speaker->social_links, true) ?: array();
        $photo_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : '';

        $speakers_data[] = array(
            'ID' => $speaker->id,
            'name' => $speaker->name,
            'title' => $speaker->title,
            'email' => $speaker->email,
            'phone' => $speaker->phone,
            'bio' => $speaker->bio,
            'company' => $speaker->company,
            'website' => $speaker->website,
            'image_url' => $photo_url,
            'social_links' => $social_links,
            'facebook' => $social_links['facebook'] ?? '',
            'twitter' => $social_links['twitter'] ?? '',
            'linkedin' => $social_links['linkedin'] ?? '',
            'instagram' => $social_links['instagram'] ?? '',
            'youtube' => $social_links['youtube'] ?? '',
            'github' => $social_links['github'] ?? '',
            'tiktok' => $social_links['tiktok'] ?? '',
            'events_count' => $speaker->events_count
        );
    }

    wp_send_json_success(array('speakers' => $speakers_data));
}

/**
 * Get single speaker from custom table
 */
add_action('wp_ajax_sc_get_speaker', 'sc_get_speaker');
function sc_get_speaker() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $speaker_id = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';

    $speaker = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $speaker_id
    ));

    if (!$speaker) {
        wp_send_json_error(array('message' => __('Speaker not found.', 'sc_events')));
    }

    $social_links = json_decode($speaker->social_links, true) ?: array();
    $photo_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : '';

    $speaker_data = array(
        'ID' => $speaker->id,
        'name' => $speaker->name,
        'title' => $speaker->title,
        'email' => $speaker->email,
        'phone' => $speaker->phone,
        'bio' => $speaker->bio,
        'company' => $speaker->company,
        'website' => $speaker->website,
        'image_url' => $photo_url,
        'social_links' => $social_links,
        'facebook' => $social_links['facebook'] ?? '',
        'twitter' => $social_links['twitter'] ?? '',
        'linkedin' => $social_links['linkedin'] ?? '',
        'instagram' => $social_links['instagram'] ?? '',
        'youtube' => $social_links['youtube'] ?? '',
        'github' => $social_links['github'] ?? '',
        'tiktok' => $social_links['tiktok'] ?? '',
        'whatsapp' => $social_links['whatsapp'] ?? '',
        'snapchat' => $social_links['snapchat'] ?? ''
    );

    wp_send_json_success(array('speaker' => $speaker_data));
}

/**
 * Save speaker to custom table.
 *
 * Text is unslashed before sanitising (quotes were stored as \" and \'), the
 * form's status and order are honoured, a removed photo is cleared, social
 * networks the form doesn't show are kept, and an existing slug only changes
 * when a new one is posted.
 */
add_action('wp_ajax_sc_save_speaker', 'sc_save_speaker');
function sc_save_speaker() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_speaker_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_speaker_nonce'])), 'sc_speaker_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        $nonce_valid = true;
    }
    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';

    // Read a field under either naming convention ("speaker_x" first).
    $field = function ($key, $sanitize = 'sanitize_text_field') {
        foreach (array('speaker_' . $key, $key) as $k) {
            if (isset($_POST[$k])) {
                return call_user_func($sanitize, wp_unslash($_POST[$k]));
            }
        }
        return null;
    };

    $speaker_id = isset($_POST['speaker_id']) ? absint($_POST['speaker_id']) : 0;
    $existing = null;
    if ($speaker_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $speaker_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Speaker not found.', 'sc_events')));
        }
    }

    $name    = (string) $field('name');
    $email   = (string) $field('email', 'sanitize_email');
    $raw_email = isset($_POST['email']) ? trim((string) wp_unslash($_POST['email'])) : '';
    $website = (string) $field('website', 'esc_url_raw');
    $title   = $field('title');
    if ($title === null) {
        $title = $field('designation');
    }

    $errors = array();
    if ($name === '') {
        $errors['name'] = __('Speaker name is required.', 'sc_events');
    }
    if ($raw_email !== '' && !is_email($raw_email)) {
        $errors['email'] = __('Enter a valid email address.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $data = array(
        'name'       => $name,
        'title'      => (string) $title,
        'company'    => (string) $field('company'),
        'email'      => $email,
        'phone'      => (string) $field('phone'),
        'bio'        => (string) $field('bio', 'wp_kses_post'),
        'website'    => $website,
        'updated_at' => current_time('mysql'),
    );

    if (isset($_POST['is_active'])) {
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
    } elseif (!$existing) {
        $data['is_active'] = 1;
    }
    if (isset($_POST['display_order']) && $_POST['display_order'] !== '') {
        $data['display_order'] = max(0, intval($_POST['display_order']));
    }

    // Social links: start from what is stored so networks this form doesn't show survive.
    $social = $existing && $existing->social_links ? json_decode($existing->social_links, true) : array();
    $social = is_array($social) ? $social : array();
    $platforms = array('facebook', 'twitter', 'linkedin', 'instagram', 'youtube', 'github', 'tiktok', 'whatsapp', 'snapchat', 'pinterest', 'tumblr', 'reddit', 'medium', 'vimeo');
    foreach ($platforms as $platform) {
        foreach (array('speaker_' . $platform, $platform) as $k) {
            if (isset($_POST[$k])) {
                $url = esc_url_raw(trim((string) wp_unslash($_POST[$k])));
                if ($url === '') {
                    unset($social[$platform]);
                } else {
                    $social[$platform] = $url;
                }
                break;
            }
        }
    }
    $data['social_links'] = wp_json_encode($social);

    // Photo: an uploaded file, a media-library id, or an empty value to clear it.
    if (isset($_FILES['speaker_image']) && !empty($_FILES['speaker_image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attachment_id = media_handle_upload('speaker_image', 0);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message(), 'errors' => array('photo' => $attachment_id->get_error_message())));
        }
        $data['photo'] = (int) $attachment_id;
    } else {
        foreach (array('photo', 'image') as $k) {
            if (isset($_POST[$k])) {
                $photo = absint($_POST[$k]);
                if ($photo > 0) {
                    $data['photo'] = $photo;
                } elseif ($existing && $k === 'photo') {
                    $data['photo'] = null;
                }
                break;
            }
        }
    }

    // Slug: a posted one wins; new speakers get one from the name; existing ones keep theirs.
    $wanted = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    if ($wanted === '' && (!$existing || !$existing->slug)) {
        $wanted = sanitize_title($name);
    }
    if ($wanted !== '') {
        $slug = $wanted;
        $n = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND id != %d", $slug, $speaker_id))) {
            if (isset($_POST['slug']) && $_POST['slug'] !== '' && $slug === $wanted) {
                wp_send_json_error(array('message' => __('Another speaker already uses this web address.', 'sc_events'), 'errors' => array('slug' => __('Another speaker already uses this web address.', 'sc_events'))));
            }
            $slug = $wanted . '-' . ($n++);
        }
        $data['slug'] = $slug;
    }

    if ($existing) {
        $result = $wpdb->update($table, $data, array('id' => $speaker_id));
        $message = __('Speaker updated successfully.', 'sc_events');
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $speaker_id = (int) $wpdb->insert_id;
        $message = __('Speaker created successfully.', 'sc_events');
    }
    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save speaker.', 'sc_events')));
    }

    $slug_now = $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$table} WHERE id = %d", $speaker_id));
    wp_send_json_success(array(
        'message'    => $message,
        'speaker_id' => $speaker_id,
        'slug'       => $slug_now,
        'redirect'   => home_url('/event-manager-dashboard/speaker-edit?id=' . $speaker_id . '&created=1'),
    ));
}

/**
 * Delete speaker from custom table
 */
add_action('wp_ajax_sc_delete_speaker', 'sc_delete_speaker');
function sc_delete_speaker() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $speaker_id = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;

    if (!$speaker_id) {
        wp_send_json_error(array('message' => __('Invalid speaker ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';
    $pivot = $wpdb->prefix . 'sc_event_speakers';

    // Remove from all events first
    $wpdb->delete($pivot, array('speaker_id' => $speaker_id), array('%d'));

    // Delete speaker
    $result = $wpdb->delete($table, array('id' => $speaker_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete speaker.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Speaker deleted successfully.', 'sc_events')));
}

/**
 * Speakers list (list pattern): one page plus tab counts.
 * Tabs: on the site (active), hidden, in an upcoming event, in no event.
 */
add_action('wp_ajax_sc_get_speakers_paginated', 'sc_get_speakers_paginated');
function sc_get_speakers_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $s_table = $wpdb->prefix . 'sc_speakers';
    $pivot   = $wpdb->prefix . 'sc_event_speakers';
    $events  = $wpdb->prefix . 'sc_events';
    $today   = current_time('Y-m-d');

    $page     = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
    $per_page = min(200, max(10, absint(wp_unslash($_POST['per_page'] ?? 25))));
    $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $event_id = absint(wp_unslash($_POST['event_id'] ?? 0));
    $view     = sanitize_key(wp_unslash($_POST['view'] ?? 'active'));

    $where  = array('1=1');
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(s.name LIKE %s OR s.title LIKE %s OR s.company LIKE %s OR s.email LIKE %s)';
        array_push($values, $like, $like, $like, $like);
    }
    if ($event_id) {
        $where[] = "s.id IN (SELECT speaker_id FROM {$pivot} WHERE event_id = %d)";
        $values[] = $event_id;
    }
    $base = implode(' AND ', $where);

    $upcoming = $wpdb->prepare(
        "EXISTS (SELECT 1 FROM {$pivot} p JOIN {$events} e ON e.id = p.event_id WHERE p.speaker_id = s.id AND e.status = 'publish' AND COALESCE(e.end_date, e.start_date) >= %s)",
        $today
    );
    $views = array(
        'active'   => 's.is_active = 1',
        'hidden'   => 's.is_active = 0',
        'upcoming' => $upcoming,
        'unlinked' => "NOT EXISTS (SELECT 1 FROM {$pivot} p WHERE p.speaker_id = s.id)",
    );
    $view = isset($views[$view]) ? $view : 'active';
    $where_sql = $base . ' AND ' . $views[$view];

    $sortable = array('name' => 's.name', 'display_order' => 's.display_order', 'events' => 'events_count_live', 'created_at' => 's.created_at');
    $orderby  = sanitize_key(wp_unslash($_POST['orderby'] ?? 'name'));
    $orderby  = isset($sortable[$orderby]) ? $orderby : 'name';
    $order    = sanitize_key(wp_unslash($_POST['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

    $prepare = function ($sql, $args) use ($wpdb) {
        return $args ? $wpdb->prepare($sql, $args) : $sql;
    };
    $total = (int) $wpdb->get_var($prepare("SELECT COUNT(*) FROM {$s_table} s WHERE {$where_sql}", $values));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.name, s.slug, s.title, s.company, s.email, s.phone, s.photo, s.is_active, s.display_order, s.social_links, s.website,
                (SELECT COUNT(*) FROM {$pivot} p WHERE p.speaker_id = s.id) AS events_count_live
         FROM {$s_table} s WHERE {$where_sql}
         ORDER BY {$sortable[$orderby]} {$order}, s.name ASC
         LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    // The latest event each speaker on this page is linked to.
    $latest = array();
    $ids = array_map('intval', wp_list_pluck($rows, 'id'));
    if ($ids) {
        foreach ($wpdb->get_results(
            "SELECT p.speaker_id, e.id, e.title, e.start_date, COALESCE(e.end_date, e.start_date) AS last_day
             FROM {$pivot} p JOIN {$events} e ON e.id = p.event_id
             WHERE p.speaker_id IN (" . implode(',', $ids) . ')
             ORDER BY e.start_date DESC'
        ) as $r) {
            if (!isset($latest[(int) $r->speaker_id])) {
                $latest[(int) $r->speaker_id] = array('id' => (int) $r->id, 'title' => $r->title, 'upcoming' => $r->last_day >= $today);
            }
        }
    }

    $out = array();
    foreach ($rows as $r) {
        $social = json_decode((string) $r->social_links, true);
        $out[] = array(
            'id'            => (int) $r->id,
            'name'          => $r->name,
            'slug'          => $r->slug,
            'url'           => home_url('/speaker/' . $r->slug . '/'),
            'title'         => $r->title,
            'company'       => $r->company,
            'email'         => $r->email,
            'phone'         => $r->phone,
            'photo'         => $r->photo ? (wp_get_attachment_image_url((int) $r->photo, 'thumbnail') ?: '') : '',
            'is_active'     => (bool) $r->is_active,
            'display_order' => (int) $r->display_order,
            'events'        => (int) $r->events_count_live,
            'latest_event'  => $latest[(int) $r->id] ?? null,
            'links'         => (is_array($social) ? count(array_filter($social)) : 0) + ($r->website ? 1 : 0),
        );
    }

    $response = array('speakers' => $out, 'total' => $total);
    if (!empty($_POST['with_counts'])) {
        $parts = array('COUNT(*) AS `all`');
        foreach ($views as $k => $cond) {
            $parts[] = "COALESCE(SUM({$cond}), 0) AS `{$k}`";
        }
        $response['counts'] = array_map('intval', (array) $wpdb->get_row($prepare('SELECT ' . implode(', ', $parts) . " FROM {$s_table} s WHERE {$base}", $values), ARRAY_A));
    }
    wp_send_json_success($response);
}

/**
 * Bulk: show on site, hide, or delete speakers.
 */
add_action('wp_ajax_sc_bulk_speakers', 'sc_bulk_speakers');
function sc_bulk_speakers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
    global $wpdb;
    $ids = array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['ids'] ?? array()))));
    $op  = sanitize_key(wp_unslash($_POST['op'] ?? ''));
    if (!$ids || !in_array($op, array('show', 'hide', 'delete'), true)) {
        wp_send_json_error(array('message' => __('Nothing to do.', 'sc_events')));
    }
    $in = implode(',', $ids);
    if ($op === 'delete') {
        $wpdb->query("DELETE FROM {$wpdb->prefix}sc_event_speakers WHERE speaker_id IN ({$in})");
        $n = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}sc_speakers WHERE id IN ({$in})");
        $message = sprintf(_n('%d speaker deleted.', '%d speakers deleted.', $n, 'sc_events'), $n);
    } else {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sc_speakers SET is_active = %d, updated_at = %s WHERE id IN ({$in})", $op === 'show' ? 1 : 0, current_time('mysql')));
        $message = $op === 'show'
            ? sprintf(_n('%d speaker shown on the site.', '%d speakers shown on the site.', $n, 'sc_events'), $n)
            : sprintf(_n('%d speaker hidden from the site.', '%d speakers hidden from the site.', $n, 'sc_events'), $n);
    }
    wp_send_json_success(array('message' => $message));
}

endif; // End speakers module check

// ==========================================
// ORGANIZERS HANDLERS (Custom Tables)
// Organizers are part of Events module, always available if events is enabled
// ==========================================

/**
 * Get all organizers from custom table
 */
add_action('wp_ajax_sc_get_organizers', 'sc_get_organizers');
function sc_get_organizers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $organizers = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");

    $organizers_data = array();
    foreach ($organizers as $organizer) {
        $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';

        $organizers_data[] = array(
            'ID' => $organizer->id,
            'name' => $organizer->name,
            'email' => $organizer->email,
            'phone' => $organizer->phone,
            'website' => $organizer->website,
            'description' => $organizer->description,
            'address' => $organizer->address,
            'logo_url' => $logo_url,
            'events_count' => $organizer->events_count
        );
    }

    wp_send_json_success(array('organizers' => $organizers_data));
}

/**
 * Get single organizer from custom table
 */
add_action('wp_ajax_sc_get_organizer', 'sc_get_organizer');
function sc_get_organizer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $organizer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $organizer_id
    ));

    if (!$organizer) {
        wp_send_json_error(array('message' => __('Organizer not found.', 'sc_events')));
    }

    $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';
    $social_links = json_decode($organizer->social_links, true) ?: array();

    $organizer_data = array(
        'ID' => $organizer->id,
        'name' => $organizer->name,
        'email' => $organizer->email,
        'phone' => $organizer->phone,
        'website' => $organizer->website,
        'description' => $organizer->description,
        'address' => $organizer->address,
        'logo_url' => $logo_url,
        'social_links' => $social_links,
        'facebook' => $social_links['facebook'] ?? '',
        'twitter' => $social_links['twitter'] ?? '',
        'linkedin' => $social_links['linkedin'] ?? '',
        'instagram' => $social_links['instagram'] ?? ''
    );

    wp_send_json_success(array('organizer' => $organizer_data));
}

/**
 * Save organizer to custom table
 */
add_action('wp_ajax_sc_save_organizer', 'sc_save_organizer');
function sc_save_organizer() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_organizer_nonce']) && wp_verify_nonce($_POST['sc_organizer_nonce'], 'sc_organizer_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        $nonce_valid = true;
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    // Support both field naming conventions
    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;
    $organizer_name = isset($_POST['organizer_name']) ? sanitize_text_field($_POST['organizer_name']) : (isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '');
    $organizer_email = isset($_POST['organizer_email']) ? sanitize_email($_POST['organizer_email']) : (isset($_POST['email']) ? sanitize_email($_POST['email']) : '');
    $organizer_phone = isset($_POST['organizer_phone']) ? sanitize_text_field($_POST['organizer_phone']) : (isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '');
    $organizer_website = isset($_POST['organizer_website']) ? esc_url_raw($_POST['organizer_website']) : (isset($_POST['website']) ? esc_url_raw($_POST['website']) : '');
    $organizer_description = isset($_POST['organizer_description']) ? wp_kses_post($_POST['organizer_description']) : (isset($_POST['description']) ? wp_kses_post($_POST['description']) : '');
    $organizer_address = isset($_POST['organizer_address']) ? sanitize_textarea_field($_POST['organizer_address']) : (isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '');
    if (empty($organizer_name)) {
        wp_send_json_error(array('message' => __('Organizer name is required.', 'sc_events')));
    }

    // Check logo - support both file upload and media library ID
    $has_logo_file = isset($_FILES['organizer_logo']) && !empty($_FILES['organizer_logo']['name']);
    $has_logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0;
    if (!$has_logo_id) {
        $has_logo_id = isset($_POST['image']) && intval($_POST['image']) > 0;
    }

    // Get existing organizer logo if updating
    $existing_logo = 0;
    if ($organizer_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT logo FROM $table WHERE id = %d", $organizer_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Organizer not found.', 'sc_events')));
        }
        $existing_logo = intval($existing->logo);
    }

    // Build social links array - support both naming conventions
    $social_links = array();
    $social_platforms = array('facebook', 'twitter', 'linkedin', 'instagram', 'youtube');
    foreach ($social_platforms as $platform) {
        $url = isset($_POST['organizer_' . $platform]) ? esc_url_raw($_POST['organizer_' . $platform]) : '';
        if (empty($url)) {
            $url = isset($_POST[$platform]) ? esc_url_raw($_POST[$platform]) : '';
        }
        if (!empty($url)) {
            $social_links[$platform] = $url;
        }
    }

    // Handle logo - either file upload or media library ID
    $logo_id = 0;
    if ($has_logo_file) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('organizer_logo', 0);
        if (!is_wp_error($attachment_id)) {
            $logo_id = $attachment_id;
        }
    } elseif ($has_logo_id) {
        $logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0 ? intval($_POST['logo']) : intval($_POST['image']);
    }

    // Generate slug
    $slug = sanitize_title($organizer_name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $organizer_id))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $data = array(
        'name' => $organizer_name,
        'slug' => $slug,
        'email' => $organizer_email,
        'phone' => $organizer_phone,
        'website' => $organizer_website,
        'description' => $organizer_description,
        'address' => $organizer_address,
        'social_links' => wp_json_encode($social_links),
        'is_active' => 1,
        'updated_at' => current_time('mysql')
    );

    if ($logo_id > 0) {
        $data['logo'] = $logo_id;
    }

    if ($organizer_id > 0) {
        // Update existing organizer
        $result = $wpdb->update($table, $data, array('id' => $organizer_id));
        $message = __('Organizer updated successfully.', 'sc_events');
    } else {
        // Create new organizer
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $organizer_id = $wpdb->insert_id;
        $message = __('Organizer created successfully.', 'sc_events');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save organizer.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'organizer_id' => $organizer_id));
}

/**
 * Delete organizer from custom table
 */
add_action('wp_ajax_sc_delete_organizer', 'sc_delete_organizer');
function sc_delete_organizer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;

    if (!$organizer_id) {
        wp_send_json_error(array('message' => __('Invalid organizer ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';
    $pivot = $wpdb->prefix . 'sc_event_organizers';

    // Remove from all events first
    $wpdb->delete($pivot, array('organizer_id' => $organizer_id), array('%d'));

    // Delete organizer
    $result = $wpdb->delete($table, array('id' => $organizer_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete organizer.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Organizer deleted successfully.', 'sc_events')));
}

/**
 * Get organizers with pagination
 */
add_action('wp_ajax_sc_get_organizers_paginated', 'sc_get_organizers_paginated');
function sc_get_organizers_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset = ($page - 1) * $per_page;

    $where = "WHERE is_active = 1";
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)", $search_like, $search_like);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
    $organizers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table $where ORDER BY name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get events count for each organizer from the pivot table (only count existing events)
    $pivot_table = $wpdb->prefix . 'sc_event_organizers';
    $organizer_ids = array_column($organizers, 'id');
    $events_counts = array();
    if (!empty($organizer_ids)) {
        // Sanitize and prepare organizer IDs
        $organizer_ids = array_map('intval', $organizer_ids);
        $organizer_ids = array_filter($organizer_ids);

        if (!empty($organizer_ids)) {
            $events_table = $wpdb->prefix . 'sc_events';
            // Use prepared statement with placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($organizer_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT eo.organizer_id, COUNT(DISTINCT eo.event_id) as count
                FROM $pivot_table eo
                INNER JOIN $events_table e ON eo.event_id = e.id
                WHERE eo.organizer_id IN ($placeholders)
                GROUP BY eo.organizer_id
            ", $organizer_ids));
            foreach ($counts as $row) {
                $events_counts[$row->organizer_id] = intval($row->count);
            }
        }
    }

    $organizers_data = array();
    foreach ($organizers as $organizer) {
        $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';

        $organizers_data[] = array(
            'ID' => $organizer->id,
            'name' => $organizer->name,
            'email' => $organizer->email,
            'phone' => $organizer->phone,
            'website' => $organizer->website,
            'description' => $organizer->description,
            'logo_url' => $logo_url,
            'events_count' => isset($events_counts[$organizer->id]) ? $events_counts[$organizer->id] : 0
        );
    }

    wp_send_json_success(array(
        'organizers' => $organizers_data,
        'total' => intval($total),
        'pages' => ceil($total / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    ));
}

// ==========================================
// CATEGORIES HANDLERS
// ==========================================

/**
 * Get all categories
 */
add_action('wp_ajax_sc_get_categories', 'sc_get_categories');
function sc_get_categories() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $categories = get_terms(array(
        'taxonomy' => 'sc_event_category',
        'hide_empty' => false
    ));

    if (is_wp_error($categories)) {
        wp_send_json_error(array('message' => $categories->get_error_message()));
    }

    $categories_with_color = array();
    foreach ($categories as $category) {
        $category_data = (array) $category;
        $color = get_term_meta($category->term_id, 'color', true);
        $category_data['color'] = $color ?: '#52C41A';
        $categories_with_color[] = $category_data;
    }

    wp_send_json_success(array('categories' => $categories_with_color));
}

/**
 * Get single category
 */
add_action('wp_ajax_sc_get_category', 'sc_get_category');
function sc_get_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category = get_term($category_id, 'sc_event_category');

    if (is_wp_error($category)) {
        wp_send_json_error(array('message' => __('Category not found.', 'sc_events')));
    }

    $color = get_term_meta($category_id, 'color', true);
    $category_data = (array) $category;
    $category_data['color'] = $color ?: '#52C41A';

    wp_send_json_success(array('category' => $category_data));
}

/**
 * Save category
 */
add_action('wp_ajax_sc_save_category', 'sc_save_category');
function sc_save_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    $category_slug = isset($_POST['category_slug']) ? sanitize_title($_POST['category_slug']) : '';
    $category_description = isset($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : '';
    $category_color = isset($_POST['category_color']) ? sanitize_hex_color($_POST['category_color']) : '';

    if (empty($category_name)) {
        wp_send_json_error(array('message' => __('Category name is required.', 'sc_events')));
    }

    $args = array('description' => $category_description);

    if (!empty($category_slug)) {
        $args['slug'] = $category_slug;
    }

    if ($category_id > 0) {
        $result = wp_update_term($category_id, 'sc_event_category', array_merge($args, array('name' => $category_name)));
        $message = __('Category updated successfully.', 'sc_events');

        if (!is_wp_error($result)) {
            $term_id = $result['term_id'];
            if (!empty($category_color)) {
                update_term_meta($term_id, 'color', $category_color);
            }
        }
    } else {
        $result = wp_insert_term($category_name, 'sc_event_category', $args);
        $message = __('Category created successfully.', 'sc_events');

        if (!is_wp_error($result)) {
            $term_id = $result['term_id'];
            if (!empty($category_color)) {
                update_term_meta($term_id, 'color', $category_color);
            }
        }
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('message' => $message));
}

/**
 * Delete category
 */
add_action('wp_ajax_sc_delete_category', 'sc_delete_category');
function sc_delete_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    if (!$category_id) {
        wp_send_json_error(array('message' => __('Invalid category ID.', 'sc_events')));
    }

    $result = wp_delete_term($category_id, 'sc_event_category');

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('message' => __('Category deleted successfully.', 'sc_events')));
}

/**
 * Get categories with pagination
 */
add_action('wp_ajax_sc_get_categories_paginated', 'sc_get_categories_paginated');
function sc_get_categories_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $args = array(
        'taxonomy' => 'sc_event_category',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page
    );

    if (!empty($search)) {
        $args['search'] = $search;
    }

    $count_args = $args;
    unset($count_args['number']);
    unset($count_args['offset']);
    $all_categories = get_terms($count_args);
    $total_categories = is_array($all_categories) ? count($all_categories) : 0;

    $categories = get_terms($args);

    if (is_wp_error($categories)) {
        wp_send_json_error(array('message' => $categories->get_error_message()));
    }

    // Get events count for each category from the pivot table (only count existing events)
    global $wpdb;
    $pivot_table = $wpdb->prefix . 'sc_event_categories';
    $events_table = $wpdb->prefix . 'sc_events';
    $category_ids = wp_list_pluck($categories, 'term_id');
    $events_counts = array();
    if (!empty($category_ids)) {
        // Sanitize and prepare category IDs
        $category_ids = array_map('intval', $category_ids);
        $category_ids = array_filter($category_ids);

        if (!empty($category_ids)) {
            // Use prepared statement with placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT ec.category_id, COUNT(DISTINCT ec.event_id) as count
                FROM $pivot_table ec
                INNER JOIN $events_table e ON ec.event_id = e.id
                WHERE ec.category_id IN ($placeholders)
                GROUP BY ec.category_id
            ", $category_ids));
            foreach ($counts as $row) {
                $events_counts[$row->category_id] = intval($row->count);
            }
        }
    }

    $categories_data = array();
    foreach ($categories as $category) {
        $category_data = array(
            'term_id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => isset($events_counts[$category->term_id]) ? $events_counts[$category->term_id] : 0
        );

        $color = get_term_meta($category->term_id, 'color', true);
        $category_data['color'] = $color ?: '#52C41A';

        $categories_data[] = $category_data;
    }

    $total_pages = ceil($total_categories / $per_page);

    wp_send_json_success(array(
        'categories' => $categories_data,
        'total' => $total_categories,
        'pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ));
}
