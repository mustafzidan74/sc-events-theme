<?php
/**
 * Badge printing — people list, PDF job and download.
 *
 * Page: template-parts/dashboard/badges.php. PDF: inc/badges/class-sc-badge-pdf.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Badges per PDF; bigger jobs are printed in parts. */
const SC_BADGES_PER_PDF = 500;

function sc_badges_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
}

/**
 * Which kinds of people can get badges on this site.
 */
function sc_badges_views() {
    $views = array('attendee');
    if (sc_module_active('speakers')) {
        $views[] = 'speaker';
    }
    if (sc_module_active('companies')) {
        $views[] = 'company';
    }
    $views[] = 'organizer';
    return $views;
}

function sc_badges_read_filters() {
    $in = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $view = $in('view');
    $ticket = $in('ticket');
    $checkin = $in('checkin');
    return array(
        'event_id' => isset($_POST['event_id']) ? absint($_POST['event_id']) : 0,
        'view'     => in_array($view, sc_badges_views(), true) ? $view : 'attendee',
        'search'   => $in('search'),
        // '' = event tickets only, 'all' = workshop tickets too, a number = that ticket.
        'ticket'   => $ticket === 'all' ? 'all' : absint($ticket),
        'checkin'  => in_array($checkin, array('in', 'out'), true) ? $checkin : '',
        'subtitle' => $in('subtitle'),
    );
}

/**
 * FROM + WHERE for one kind of person. Search and check-in apply where the kind has them.
 *
 * @return array{0:string,1:array} SQL fragment and its prepare() values.
 */
function sc_badges_source($view, $f) {
    global $wpdb;
    $p = $wpdb->prefix;
    $like = $f['search'] !== '' ? '%' . $wpdb->esc_like($f['search']) . '%' : '';
    $checkin = $f['checkin'] === 'in' ? ' AND x.checked_in = 1' : ($f['checkin'] === 'out' ? ' AND x.checked_in = 0' : '');

    switch ($view) {
        case 'speaker':
            $sql = "FROM {$p}sc_speakers x INNER JOIN {$p}sc_event_speakers es ON es.speaker_id = x.id WHERE es.event_id = %d";
            $values = array($f['event_id']);
            if ($like) {
                $sql .= ' AND (x.name LIKE %s OR x.title LIKE %s OR x.company LIKE %s)';
                array_push($values, $like, $like, $like);
            }
            return array($sql, $values);

        case 'organizer':
            $sql = "FROM {$p}sc_organizers x INNER JOIN {$p}sc_event_organizers eo ON eo.organizer_id = x.id WHERE eo.event_id = %d";
            $values = array($f['event_id']);
            if ($like) {
                $sql .= ' AND x.name LIKE %s';
                $values[] = $like;
            }
            return array($sql, $values);

        case 'company':
            $sql = "FROM {$p}sc_company_attendees x WHERE x.event_id = %d AND x.status = 'active' AND x.payment_status = 'success'" . $checkin;
            $values = array($f['event_id']);
            if ($like) {
                $sql .= ' AND (x.company_name LIKE %s OR x.company_name_ar LIKE %s OR x.contact_name LIKE %s OR x.company_code LIKE %s OR x.booth_number LIKE %s)';
                array_push($values, $like, $like, $like, $like, $like);
            }
            return array($sql, $values);

        default:
            // Only people who can actually get in: active and paid.
            $sql = "FROM {$p}sc_attendees x LEFT JOIN {$p}sc_tickets t ON t.id = x.ticket_id WHERE x.event_id = %d AND x.status = 'active' AND x.payment_status = 'success'" . $checkin;
            $values = array($f['event_id']);
            if ($f['ticket'] === 'all') {
                // every ticket
            } elseif ($f['ticket']) {
                $sql .= ' AND x.ticket_id = %d';
                $values[] = $f['ticket'];
            } else {
                // A workshop ticket is a second row for the same person; one badge per person.
                $sql .= ' AND (x.workshop_id IS NULL OR x.workshop_id = 0)';
            }
            if ($like) {
                $sql .= ' AND (x.name LIKE %s OR x.email LIKE %s OR x.phone LIKE %s OR x.ticket_code LIKE %s)';
                array_push($values, $like, $like, $like, $like);
            }
            return array($sql, $values);
    }
}

function sc_badges_columns($view) {
    switch ($view) {
        case 'speaker':
            return 'x.id, x.name, x.title, x.company, x.photo, x.email';
        case 'organizer':
            return 'x.id, x.name, x.logo, x.email';
        case 'company':
            return 'x.id, x.company_name, x.company_name_ar, x.company_logo, x.company_code, x.booth_number, x.sponsorship_level, x.contact_name, x.checked_in';
        default:
            return 'x.id, x.name, x.email, x.ticket_code, x.checked_in, x.extra_fields, x.workshop_id, COALESCE(t.name, x.ticket_name) AS ticket';
    }
}

/**
 * One row, shaped for both the table and the PDF.
 */
function sc_badges_row($view, $r, $subtitle_key) {
    $image = function ($id) {
        return $id ? (wp_get_attachment_image_url((int) $id, 'medium') ?: '') : '';
    };
    // Speaker text came in slashed on older saves.
    $clean = function ($text) {
        return trim(wp_unslash((string) $text));
    };

    switch ($view) {
        case 'speaker':
            return array(
                'id' => (int) $r->id, 'badge' => 'speaker', 'name' => $clean($r->name),
                'sub' => $clean($r->title), 'detail' => $clean($r->company), 'meta' => (string) $r->email,
                // The scanner reads tickets and company badges only; a QR here would not scan.
                'qr' => '', 'photo' => $image($r->photo), 'checked_in' => null,
            );
        case 'organizer':
            return array(
                'id' => (int) $r->id, 'badge' => 'organizer', 'name' => $clean($r->name),
                'sub' => '', 'detail' => '', 'meta' => (string) $r->email,
                'qr' => '', 'photo' => $image($r->logo), 'checked_in' => null,
            );
        case 'company':
            return array(
                'id' => (int) $r->id, 'badge' => 'exhibitor', 'name' => (string) $r->company_name,
                /* translators: %s: booth number */
                'sub' => $r->booth_number !== '' && $r->booth_number !== null ? sprintf(__('Booth %s', 'sc_events'), $r->booth_number) : (string) $r->contact_name,
                'detail' => ucfirst((string) $r->sponsorship_level), 'meta' => (string) $r->company_code,
                'qr' => (string) $r->company_code, 'photo' => $image($r->company_logo), 'checked_in' => (bool) $r->checked_in,
            );
        default:
            $answers = json_decode((string) $r->extra_fields, true);
            $sub = '';
            if ($subtitle_key !== '' && $subtitle_key !== 'ticket' && is_array($answers)) {
                foreach ($answers as $label => $value) {
                    if (is_scalar($value) && strcasecmp(trim((string) $label), $subtitle_key) === 0) {
                        $sub = trim((string) $value);
                        break;
                    }
                }
            }
            $ticket = (string) $r->ticket;
            return array(
                'id' => (int) $r->id, 'badge' => stripos($ticket, 'vip') !== false ? 'vip' : 'attendee', 'name' => (string) $r->name,
                'sub' => $subtitle_key === 'ticket' ? $ticket : $sub, 'detail' => $subtitle_key === 'ticket' ? '' : $ticket,
                'meta' => (string) $r->email, 'ticket' => $ticket, 'workshop' => (int) $r->workshop_id > 0,
                'qr' => (string) $r->ticket_code, 'photo' => '', 'checked_in' => (bool) $r->checked_in,
            );
    }
}

/**
 * Rows for one kind of person.
 *
 * @param int[] $ids Limit to these ids (the ticked rows); empty = everyone matching.
 */
function sc_badges_fetch($f, $ids, $limit, $offset) {
    global $wpdb;
    list($sql, $values) = sc_badges_source($f['view'], $f);
    if ($ids) {
        $sql .= ' AND x.id IN (' . implode(',', array_map('absint', $ids)) . ')';
    }
    $order = $f['view'] === 'company' ? 'x.company_name' : 'x.name';
    $rows = $wpdb->get_results($wpdb->prepare('SELECT ' . sc_badges_columns($f['view']) . " $sql ORDER BY $order ASC, x.id ASC LIMIT %d OFFSET %d", array_merge($values, array($limit, $offset))));
    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $sql", $values));
    return array(array_map(function ($r) use ($f) { return sc_badges_row($f['view'], $r, $f['subtitle']); }, $rows), $total);
}

add_action('wp_ajax_sc_badges_people', 'sc_badges_people');
function sc_badges_people() {
    sc_badges_verify_request();
    global $wpdb;
    $f = sc_badges_read_filters();
    $per_page = isset($_POST['per_page']) ? min(200, max(1, absint($_POST['per_page']))) : 50;
    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;

    if (!$f['event_id']) {
        wp_send_json_success(array('rows' => array(), 'total' => 0, 'counts' => array_fill_keys(sc_badges_views(), 0)));
    }

    list($rows, $total) = sc_badges_fetch($f, array(), $per_page, ($page - 1) * $per_page);
    $out = array('rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        $out['counts'] = array();
        foreach (sc_badges_views() as $view) {
            list($sql, $values) = sc_badges_source($view, $f);
            $out['counts'][$view] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $sql", $values));
        }
    }
    wp_send_json_success($out);
}

add_action('wp_ajax_sc_badges_prepare', 'sc_badges_prepare');
function sc_badges_prepare() {
    sc_badges_verify_request();
    $f = sc_badges_read_filters();
    $in = function ($key, $default = '') {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
    };

    $event = $f['event_id'] ? SC_Event::get($f['event_id']) : null;
    if (!$event) {
        wp_send_json_error(array('message' => __('Choose the event first.', 'sc_events')));
    }

    $ids = isset($_POST['ids']) ? array_filter(array_map('absint', (array) wp_unslash($_POST['ids']))) : array();
    $part = isset($_POST['part']) ? max(1, absint($_POST['part'])) : 1;
    list($people, $total) = sc_badges_fetch($f, $ids, SC_BADGES_PER_PDF, ($part - 1) * SC_BADGES_PER_PDF);
    if (!$people) {
        wp_send_json_error(array('message' => __('Nobody to print. Change the filters or tick some people.', 'sc_events')));
    }

    $logo_id = absint($in('logo_id'));
    $include_photos = $in('include_photos', '1') === '1';
    $badges = array_map(function ($row) use ($include_photos) {
        return array(
            'type'      => $row['badge'],
            'name'      => $row['name'],
            'subtitle'  => $row['sub'],
            'detail'    => $row['detail'],
            'qr_data'   => $row['qr'],
            'photo_url' => $include_photos ? $row['photo'] : '',
        );
    }, $people);

    $token = wp_generate_password(32, false);
    set_transient('sc_badge_job_' . $token, array(
        'user'          => get_current_user_id(),
        'event'         => array('id' => (int) $event->id, 'title' => (string) $event->title, 'start_date' => (string) $event->start_date, 'venue_name' => (string) $event->venue_name),
        'badges'        => $badges,
        'design'        => in_array($in('design'), array('corporate', 'modern', 'elegant'), true) ? $in('design') : 'corporate',
        'badge_size'    => $in('badge_size') === 'id_card' ? 'id_card' : 'standard',
        'layout_mode'   => $in('layout_mode') === 'single' ? 'single' : 'grid',
        'primary_color' => sanitize_hex_color($in('primary_color')) ?: '#1a73e8',
        'logo_url'      => $logo_id ? (string) get_attached_file($logo_id) : '',
        'include_qr'    => $in('include_qr', '1') === '1',
        'include_event' => $in('include_event', '1') === '1',
        'part'          => $total > SC_BADGES_PER_PDF ? $part : 0,
    ), 10 * MINUTE_IN_SECONDS);

    wp_send_json_success(array(
        'download_url' => add_query_arg(array('action' => 'sc_download_badges', 'token' => $token), admin_url('admin-ajax.php')),
        'count'        => count($badges),
        'total'        => $total,
        'parts'        => (int) ceil($total / SC_BADGES_PER_PDF),
    ));
}

add_action('wp_ajax_sc_download_badges', 'sc_download_badges_handler');
function sc_download_badges_handler() {
    $token = isset($_GET['token']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['token'])) : '';
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(esc_html__('Permission denied.', 'sc_events'), 403);
    }
    $config = $token ? get_transient('sc_badge_job_' . $token) : false;
    if (!$config || (int) ($config['user'] ?? 0) !== get_current_user_id()) {
        wp_die(esc_html__('This badge file has expired. Go back and print again.', 'sc_events'));
    }
    delete_transient('sc_badge_job_' . $token);

    require_once get_template_directory() . '/inc/badges/class-sc-badge-pdf.php';
    $generator = new SC_Badge_PDF($config);
    $generator->generate('D');
    exit;
}
