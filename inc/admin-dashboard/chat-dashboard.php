<?php
/**
 * Chat inbox for the dashboard (template-parts/dashboard/chat.php).
 *
 * The list and thread come from here; sending, polling, uploads and close/archive
 * go through SC_Chat's own actions (inc/database/class-sc-chat.php), which are
 * limited to event managers on the organizer side.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_chat_inbox_verify() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
}

function sc_chat_inbox_view_condition($view) {
    $map = array(
        'active'   => " AND c.status = 'active'",
        'unread'   => " AND c.status <> 'archived' AND c.unread_organizer > 0",
        'closed'   => " AND c.status = 'closed'",
        'archived' => " AND c.status = 'archived'",
    );
    return $map[$view] ?? '';
}

add_action('wp_ajax_sc_chat_inbox', 'sc_chat_inbox');
function sc_chat_inbox() {
    sc_chat_inbox_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'active';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $per_page = isset($_POST['per_page']) ? min(100, max(1, absint($_POST['per_page']))) : 30;
    $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;

    $where = '1=1';
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= " AND (c.visitor_name LIKE %s OR c.visitor_email LIKE %s OR c.visitor_phone LIKE %s OR EXISTS (SELECT 1 FROM {$p}sc_chat_messages sm WHERE sm.conversation_id = c.id AND sm.message LIKE %s))";
        array_push($values, $like, $like, $like, $like);
    }
    $count = function ($condition) use ($wpdb, $p, $where, $values) {
        $sql = "SELECT COUNT(*) FROM {$p}sc_conversations c WHERE $where" . $condition;
        return (int) $wpdb->get_var($values ? $wpdb->prepare($sql, $values) : $sql);
    };

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.event_id, c.user_id, c.visitor_name, c.visitor_email, c.visitor_phone, c.status, c.unread_organizer, c.created_at,
                COALESCE(c.last_message_at, c.created_at) AS last_at, e.title AS event_title,
                (SELECT COUNT(*) FROM {$p}sc_chat_messages m WHERE m.conversation_id = c.id) AS message_count,
                (SELECT CONCAT(m.sender_type, '|', m.message_type, '|', LEFT(m.message, 160)) FROM {$p}sc_chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message
         FROM {$p}sc_conversations c LEFT JOIN {$p}sc_events e ON e.id = c.event_id
         WHERE $where" . sc_chat_inbox_view_condition($view) . '
         ORDER BY (c.unread_organizer > 0) DESC, last_at DESC, c.id DESC LIMIT %d OFFSET %d',
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    wp_send_json_success(array(
        'rows'   => array_map('sc_chat_inbox_row', $rows),
        'total'  => $count(sc_chat_inbox_view_condition($view)),
        'page'   => $page,
        'counts' => array(
            'active'   => $count(sc_chat_inbox_view_condition('active')),
            'unread'   => $count(sc_chat_inbox_view_condition('unread')),
            'closed'   => $count(sc_chat_inbox_view_condition('closed')),
            'archived' => $count(sc_chat_inbox_view_condition('archived')),
        ),
    ));
}

function sc_chat_inbox_row($c) {
    $last = explode('|', (string) $c->last_message, 3);
    return array(
        'id'       => (int) $c->id,
        'name'     => (string) $c->visitor_name,
        'email'    => (string) $c->visitor_email,
        'phone'    => (string) $c->visitor_phone,
        'account'  => (bool) $c->user_id,
        'event'    => (string) $c->event_title,
        'status'   => $c->status,
        'unread'   => (int) $c->unread_organizer,
        'messages' => (int) $c->message_count,
        'last'     => $c->last_message !== null ? array('from' => $last[0] ?: 'system', 'type' => $last[1] ?: 'system', 'text' => $last[2] ?? '') : null,
        'last_at'  => $c->last_at,
        'started'  => $c->created_at,
    );
}

add_action('wp_ajax_sc_chat_thread', 'sc_chat_thread');
function sc_chat_thread() {
    sc_chat_inbox_verify();
    global $wpdb;
    $p = $wpdb->prefix;
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $before = isset($_POST['before']) ? absint($_POST['before']) : 0;
    $limit = 100;

    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT c.id, c.event_id, c.user_id, c.visitor_name, c.visitor_email, c.visitor_phone, c.status, c.unread_organizer, c.created_at,
                COALESCE(c.last_message_at, c.created_at) AS last_at, e.title AS event_title, NULL AS last_message,
                (SELECT COUNT(*) FROM {$p}sc_chat_messages m WHERE m.conversation_id = c.id) AS message_count
         FROM {$p}sc_conversations c LEFT JOIN {$p}sc_events e ON e.id = c.event_id WHERE c.id = %d",
        $id
    ));
    if (!$c) {
        wp_send_json_error(array('message' => __('This conversation no longer exists.', 'sc_events')));
    }

    // Newest messages first from the database, shown oldest → newest.
    $messages = array_reverse($wpdb->get_results($wpdb->prepare(
        "SELECT id, message, message_type, file_url, file_name, sender_type, sender_id, is_read, created_at
         FROM {$p}sc_chat_messages WHERE conversation_id = %d" . ($before ? ' AND id < %d' : '') . ' ORDER BY id DESC LIMIT %d',
        $before ? array($id, $before, $limit + 1) : array($id, $limit + 1)
    )));
    $has_more = count($messages) > $limit;
    if ($has_more) {
        array_shift($messages);
    }

    $senders = array();
    foreach ($messages as $m) {
        if ($m->sender_type === 'organizer' && $m->sender_id && !isset($senders[$m->sender_id])) {
            $u = get_userdata((int) $m->sender_id);
            $senders[$m->sender_id] = $u ? $u->display_name : '';
        }
    }

    $registrations = 0;
    if ($c->visitor_email) {
        $registrations = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_attendees WHERE LOWER(email) = LOWER(%s) AND status = 'active'", $c->visitor_email));
    }

    if (!$before && (int) $c->unread_organizer > 0 && function_exists('sc_chat')) {
        sc_chat()->mark_as_read($id, 'organizer');
    }

    wp_send_json_success(array(
        'conversation'  => sc_chat_inbox_row($c) + array('registrations' => $registrations),
        'messages'      => array_map(function ($m) use ($senders) {
            // Closed/reopened notices were stored with empty types (the columns have no "system" value).
            $system = !in_array($m->sender_type, array('visitor', 'organizer'), true);
            return array(
                'id'        => (int) $m->id,
                'from'      => $system ? 'system' : $m->sender_type,
                'type'      => $system ? 'system' : ($m->message_type ?: 'text'),
                'text'      => (string) $m->message,
                'file_url'  => (string) $m->file_url,
                'file_name' => (string) $m->file_name,
                'by'        => $m->sender_type === 'organizer' ? ($senders[$m->sender_id] ?? '') : '',
                'read'      => (bool) $m->is_read,
                'at'        => $m->created_at,
            );
        }, $messages),
        'has_more'      => $has_more,
    ));
}
