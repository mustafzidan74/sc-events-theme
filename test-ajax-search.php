<?php
/**
 * Direct Test for User Search AJAX Handler
 * Access: http://localhost/events/wp-content/themes/sc_events/test-ajax-search.php?search=mah
 */

// Load WordPress
require_once($_SERVER['DOCUMENT_ROOT'] . '/events/wp-load.php');

// Check if admin
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as admin.');
}

$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : 'mah';

echo '<html><head><title>Test AJAX Search</title>';
echo '<style>body { font-family: Arial; padding: 20px; } pre { background: #f5f5f5; padding: 15px; overflow: auto; } .success { color: green; } .error { color: red; }</style></head><body>';

echo '<h1>Test User Search (simulating AJAX)</h1>';
echo '<p>Search term: <strong>' . esc_html($search) . '</strong></p>';

// Test 1: Check if handler file is loaded
echo '<h2>1. Check if handler file exists</h2>';
$handler_file = get_template_directory() . '/inc/admin-dashboard/attendees-ajax-handlers.php';
if (file_exists($handler_file)) {
    echo '<p class="success">Handler file exists: ' . $handler_file . '</p>';
} else {
    echo '<p class="error">Handler file NOT found!</p>';
}

// Test 2: Check if function exists
echo '<h2>2. Check if function is registered</h2>';
if (function_exists('sc_search_users_handler')) {
    echo '<p class="success">Function sc_search_users_handler() exists</p>';
} else {
    echo '<p class="error">Function sc_search_users_handler() NOT found!</p>';
}

// Test 3: Check module status
echo '<h2>3. Check module status</h2>';
if (function_exists('sc_is_module_enabled')) {
    $attendees_enabled = sc_is_module_enabled('attendees');
    echo '<p>Attendees module enabled: <strong>' . ($attendees_enabled ? 'YES' : 'NO') . '</strong></p>';
} else {
    echo '<p>sc_is_module_enabled function not found</p>';
}

// Test 4: Check permissions
echo '<h2>4. Check permissions</h2>';
if (class_exists('SC_Event_Manager_Dashboard')) {
    $is_manager = SC_Event_Manager_Dashboard::is_event_manager();
    echo '<p>Is event manager: <strong>' . ($is_manager ? 'YES' : 'NO') . '</strong></p>';
} else {
    echo '<p class="error">SC_Event_Manager_Dashboard class not found!</p>';
}

// Test 5: Run the exact same query as the AJAX handler
echo '<h2>5. Run WP_User_Query (same as AJAX handler)</h2>';

global $wpdb;

$user_args = array(
    'role' => 'subscriber',
    'search' => '*' . $search . '*',
    'search_columns' => array('user_login', 'user_email', 'user_nicename', 'display_name'),
    'number' => 15,
    'orderby' => 'display_name',
    'order' => 'ASC'
);

echo '<p>Query args:</p>';
echo '<pre>' . print_r($user_args, true) . '</pre>';

$user_query = new WP_User_Query($user_args);
$users = $user_query->get_results();

echo '<p>SQL Query:</p>';
echo '<pre>' . esc_html($user_query->request) . '</pre>';

echo '<p>Users found: <strong>' . count($users) . '</strong></p>';
echo '<p>Total (all pages): <strong>' . $user_query->get_total() . '</strong></p>';

if (count($users) > 0) {
    echo '<h3>Results:</h3>';
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr><th>ID</th><th>Login</th><th>Email</th><th>Display Name</th><th>Roles</th></tr>';
    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . $user->ID . '</td>';
        echo '<td>' . esc_html($user->user_login) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . implode(', ', $user->roles) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">No users found!</p>';

    // Debug: Check what roles exist
    echo '<h3>Debug: All subscribers in system</h3>';
    $all_subs = get_users(array('role' => 'subscriber'));
    echo '<p>Total subscribers: ' . count($all_subs) . '</p>';
    foreach ($all_subs as $sub) {
        echo '<p>- ' . $sub->ID . ': ' . $sub->user_login . ' (' . $sub->user_email . ') - ' . $sub->display_name . '</p>';
    }
}

// Test 6: Check sc_attendees table
echo '<h2>6. Check sc_attendees table</h2>';
$attendees_table = $wpdb->prefix . 'sc_attendees';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$attendees_table}'");

if ($table_exists) {
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $attendees = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT name, email, phone
        FROM {$attendees_table}
        WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)
        AND email IS NOT NULL AND email != ''
        ORDER BY name ASC
        LIMIT 15
    ", $search_like, $search_like, $search_like));

    echo '<p>Attendees matching "' . esc_html($search) . '": <strong>' . count($attendees) . '</strong></p>';
    if (count($attendees) > 0) {
        echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
        echo '<tr><th>Name</th><th>Email</th><th>Phone</th></tr>';
        foreach ($attendees as $att) {
            echo '<tr>';
            echo '<td>' . esc_html($att->name) . '</td>';
            echo '<td>' . esc_html($att->email) . '</td>';
            echo '<td>' . esc_html($att->phone) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} else {
    echo '<p class="error">Table ' . $attendees_table . ' not found!</p>';
}

// Test 7: Simulate full AJAX response
echo '<h2>7. Simulated AJAX Response</h2>';
$result = array();
$seen_emails = array();

foreach ($users as $user) {
    $phone = get_user_meta($user->ID, 'phone', true) ?: get_user_meta($user->ID, 'billing_phone', true);
    $company = get_user_meta($user->ID, 'company', true) ?: get_user_meta($user->ID, 'billing_company', true);

    $result[] = array(
        'ID' => $user->ID,
        'display_name' => $user->display_name,
        'user_email' => $user->user_email,
        'phone' => $phone ?: '',
        'company' => $company ?: ''
    );
    $seen_emails[strtolower($user->user_email)] = true;
}

echo '<p>Final result count: <strong>' . count($result) . '</strong></p>';
echo '<pre>' . json_encode(array('success' => true, 'data' => array('results' => $result)), JSON_PRETTY_PRINT) . '</pre>';

echo '</body></html>';
