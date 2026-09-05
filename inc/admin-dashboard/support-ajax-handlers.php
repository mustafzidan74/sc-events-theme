<?php
/**
 * Support Messages AJAX Handlers
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

// Also run on init to ensure table exists
function sc_ensure_support_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        sc_create_support_messages_table();
    }
}
add_action('init', 'sc_ensure_support_table_exists');

/**
 * Submit contact form (public)
 */
function sc_submit_contact_form() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_public_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Validate required fields
    $name = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
    $email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
    $phone = isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '';
    $subject = isset($_POST['contact_subject']) ? sanitize_text_field($_POST['contact_subject']) : '';
    $message = isset($_POST['contact_message']) ? sanitize_textarea_field($_POST['contact_message']) : '';

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
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'status' => 'new',
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save message. Please try again.', 'sc_events')));
    }

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

    wp_mail($admin_email, $email_subject, $email_body);

    wp_send_json_success(array('message' => __('Your message has been sent successfully! We will get back to you soon.', 'sc_events')));
}
add_action('wp_ajax_sc_submit_contact_form', 'sc_submit_contact_form');
add_action('wp_ajax_nopriv_sc_submit_contact_form', 'sc_submit_contact_form');

/**
 * Get support messages (dashboard)
 */
function sc_get_support_messages() {
    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $where = array('1=1');
    $params = array();

    if (!empty($status)) {
        $where[] = 'status = %s';
        $params[] = $status;
    }

    if (!empty($search)) {
        $where[] = '(name LIKE %s OR email LIKE %s OR subject LIKE %s)';
        $search_param = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = implode(' AND ', $where);

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
    if (!empty($params)) {
        $count_sql = $wpdb->prepare($count_sql, $params);
    }
    $total = $wpdb->get_var($count_sql);

    // Get messages
    $sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;

    $messages = $wpdb->get_results($wpdb->prepare($sql, $params));

    wp_send_json_success(array(
        'messages' => $messages,
        'total' => $total,
        'total_pages' => ceil($total / $per_page),
        'current_page' => $page
    ));
}
add_action('wp_ajax_sc_get_support_messages', 'sc_get_support_messages');

/**
 * Get single support message
 */
function sc_get_support_message() {
    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(array('message' => __('Invalid message ID.', 'sc_events')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

    if (!$message) {
        wp_send_json_error(array('message' => __('Message not found.', 'sc_events')));
    }

    wp_send_json_success($message);
}
add_action('wp_ajax_sc_get_support_message', 'sc_get_support_message');

/**
 * Update support message status
 */
function sc_update_support_status() {
    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if (!$id || !in_array($status, array('new', 'contacted', 'resolved'))) {
        wp_send_json_error(array('message' => __('Invalid data.', 'sc_events')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    $result = $wpdb->update(
        $table_name,
        array('status' => $status),
        array('id' => $id),
        array('%s'),
        array('%d')
    );

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to update status.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Status updated successfully.', 'sc_events')));
}
add_action('wp_ajax_sc_update_support_status', 'sc_update_support_status');

/**
 * Delete support message
 */
function sc_delete_support_message() {
    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(array('message' => __('Invalid message ID.', 'sc_events')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_messages';

    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to delete message.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Message deleted successfully.', 'sc_events')));
}
add_action('wp_ajax_sc_delete_support_message', 'sc_delete_support_message');
