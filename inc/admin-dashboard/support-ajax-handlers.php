<?php
/**
 * Support messages — the contact form's inbox.
 *
 * Public: sc_submit_contact_form (page-contact.php, front page). Dashboard:
 * sc_support_list / sc_support_bulk for template-parts/dashboard/support.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create support messages table on theme activation
 */
function sc_create_support_messages_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50) DEFAULT NULL,
        subject varchar(255) NOT NULL,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'sc_create_support_messages_table');

// Make sure the table exists once, instead of a SHOW TABLES query on every page load.
add_action('init', function () {
    if (get_option('sc_support_table_v') !== '1') {
        sc_create_support_messages_table();
        update_option('sc_support_table_v', '1', true);
    }
});

/**
 * Submit contact form (public)
 */
function sc_submit_contact_form() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_public_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Bots fill every field, people never see this one.
    if (!empty($_POST['contact_website'])) {
        wp_send_json_success(array('message' => __('Your message has been sent successfully! We will get back to you soon.', 'sc_events')));
    }

    // At most 5 messages per visitor per 15 minutes.
    $ip = function_exists('sc_get_client_ip') ? sc_get_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
    $throttle_key = 'sc_contact_' . md5($ip . wp_salt('auth'));
    $sent = (int) get_transient($throttle_key);
    if ($sent >= 5) {
        wp_send_json_error(array('message' => __('You have sent several messages already. Please wait a few minutes and try again.', 'sc_events')));
    }

    $name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';
    $email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '';
    $phone = isset($_POST['contact_phone']) ? sanitize_text_field(wp_unslash($_POST['contact_phone'])) : '';
    $subject = isset($_POST['contact_subject']) ? sanitize_text_field(wp_unslash($_POST['contact_subject'])) : '';
    $message = isset($_POST['contact_message']) ? sanitize_textarea_field(wp_unslash($_POST['contact_message'])) : '';

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'sc_events')));
    }

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'sc_events')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    $result = $wpdb->insert(
        $table_name,
        array(
            'name' => mb_substr($name, 0, 255),
            'email' => $email,
            'phone' => mb_substr($phone, 0, 50),
            'subject' => mb_substr($subject, 0, 255),
            'message' => mb_substr($message, 0, 10000),
            'status' => 'new',
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save message. Please try again.', 'sc_events')));
    }
    set_transient($throttle_key, $sent + 1, 15 * MINUTE_IN_SECONDS);

    // Send email notification to admin
    $admin_email = get_option('sc_platform_email', get_option('admin_email'));
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    $email_subject = sprintf(__('[%s] New Contact Message: %s', 'sc_events'), $platform_name, $subject);
    $email_body = sprintf(
        __("New contact message received:\n\nName: %s\nEmail: %s\nPhone: %s\nSubject: %s\n\nMessage:\n%s", 'sc_events'),
        $name,
        $email,
        $phone ?: 'N/A',
        $subject,
        $message
    );

    wp_mail($admin_email, $email_subject, $email_body, array('Reply-To: ' . $name . ' <' . $email . '>'));

    wp_send_json_success(array('message' => __('Your message has been sent successfully! We will get back to you soon.', 'sc_events')));
}
add_action('wp_ajax_sc_submit_contact_form', 'sc_submit_contact_form');
add_action('wp_ajax_nopriv_sc_submit_contact_form', 'sc_submit_contact_form');

/* ==========================================================================
   Dashboard
   ========================================================================== */

function sc_support_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
}

function sc_support_view_condition($view) {
    $map = array(
        'new'       => " AND s.status = 'new'",
        'contacted' => " AND s.status = 'contacted'",
        'resolved'  => " AND s.status = 'resolved'",
    );
    return $map[$view] ?? '';
}

add_action('wp_ajax_sc_support_list', 'sc_support_list');
function sc_support_list() {
    sc_support_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'new';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $order = isset($_POST['order']) && $_POST['order'] === 'asc' ? 'ASC' : 'DESC';
    $per_page = isset($_POST['per_page']) ? min(200, max(1, absint($_POST['per_page']))) : 50;
    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;

    $where = '1=1';
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (s.name LIKE %s OR s.email LIKE %s OR s.phone LIKE %s OR s.subject LIKE %s OR s.message LIKE %s)';
        array_push($values, $like, $like, $like, $like, $like);
    }
    $prep = function ($sql) use ($wpdb, $values) {
        return $values ? $wpdb->prepare($sql, $values) : $sql;
    };

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.* FROM {$p}sc_support_messages s WHERE $where" . sc_support_view_condition($view) . " ORDER BY s.created_at $order, s.id $order LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    // Who is writing: registrations and certificates under the same email.
    $emails = array_values(array_unique(array_filter(array_map('strtolower', wp_list_pluck($rows, 'email')))));
    $known = array();
    if ($emails) {
        $in = implode(',', array_fill(0, count($emails), '%s'));
        foreach ($wpdb->get_results($wpdb->prepare("SELECT LOWER(email) email, COUNT(*) n, MAX(created_at) latest FROM {$p}sc_attendees WHERE LOWER(email) IN ($in) AND status = 'active' GROUP BY LOWER(email)", $emails)) as $k) {
            $known[$k->email]['registrations'] = (int) $k->n;
        }
        $cert_table = $p . 'sc_certificates';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cert_table))) {
            foreach ($wpdb->get_results($wpdb->prepare("SELECT LOWER(a.email) email, COUNT(*) n FROM $cert_table c JOIN {$p}sc_attendees a ON a.id = c.attendee_id WHERE LOWER(a.email) IN ($in) AND c.status <> 'revoked' GROUP BY LOWER(a.email)", $emails)) as $k) {
                $known[$k->email]['certificates'] = (int) $k->n;
            }
        }
    }

    $out = array(
        'rows' => array_map(function ($r) use ($known) {
            $key = strtolower((string) $r->email);
            return array(
                'id'            => (int) $r->id,
                'name'          => (string) $r->name,
                'email'         => (string) $r->email,
                'phone'         => (string) $r->phone,
                'subject'       => (string) $r->subject,
                'message'       => (string) $r->message,
                'status'        => in_array($r->status, array('new', 'contacted', 'resolved'), true) ? $r->status : 'new',
                'created'       => $r->created_at,
                'updated'       => $r->updated_at,
                'registrations' => $known[$key]['registrations'] ?? 0,
                'certificates'  => $known[$key]['certificates'] ?? 0,
            );
        }, $rows),
        'total' => (int) $wpdb->get_var($prep("SELECT COUNT(*) FROM {$p}sc_support_messages s WHERE $where" . sc_support_view_condition($view))),
    );
    if (!empty($_POST['with_counts'])) {
        $out['counts'] = array();
        foreach (array('new', 'contacted', 'resolved', 'all') as $v) {
            $out['counts'][$v] = (int) $wpdb->get_var($prep("SELECT COUNT(*) FROM {$p}sc_support_messages s WHERE $where" . sc_support_view_condition($v)));
        }
    }
    wp_send_json_success($out);
}

add_action('wp_ajax_sc_support_bulk', 'sc_support_bulk');
function sc_support_bulk() {
    sc_support_verify_request();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_support_messages';
    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    $ids = isset($_POST['ids']) ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['ids'])))) : array();
    if (!$ids || !in_array($op, array('new', 'contacted', 'resolved', 'delete'), true)) {
        wp_send_json_error(array('message' => __('Nothing to do.', 'sc_events')));
    }
    $in = implode(',', $ids);
    if ($op === 'delete') {
        $done = (int) $wpdb->query("DELETE FROM $table WHERE id IN ($in)");
        /* translators: %d: number of messages */
        $message = sprintf(_n('%d message deleted.', '%d messages deleted.', $done, 'sc_events'), $done);
    } else {
        $done = (int) $wpdb->query($wpdb->prepare("UPDATE $table SET status = %s WHERE id IN ($in)", $op));
        $labels = array(
            /* translators: %d: number of messages */
            'new'       => _n('%d message marked as new.', '%d messages marked as new.', count($ids), 'sc_events'),
            /* translators: %d: number of messages */
            'contacted' => _n('%d message marked as replied.', '%d messages marked as replied.', count($ids), 'sc_events'),
            /* translators: %d: number of messages */
            'resolved'  => _n('%d message marked as resolved.', '%d messages marked as resolved.', count($ids), 'sc_events'),
        );
        $message = sprintf($labels[$op], count($ids));
    }
    wp_send_json_success(array('message' => $message, 'done' => $done));
}
