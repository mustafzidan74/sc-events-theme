<?php
/**
 * Direct AJAX Test - Simulates exactly what the browser does
 * Access: http://localhost/events/wp-content/themes/sc_events/test-ajax-direct.php
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/events/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Please login as admin.');
}

$nonce = wp_create_nonce('sc_dashboard_nonce');
$ajax_url = admin_url('admin-ajax.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct AJAX Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; overflow: auto; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 10px 20px; font-size: 16px; margin: 10px 0; cursor: pointer; }
        #result { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Direct AJAX Test</h1>

    <p><strong>AJAX URL:</strong> <?php echo $ajax_url; ?></p>
    <p><strong>Nonce:</strong> <?php echo $nonce; ?></p>

    <div>
        <label>Search term: <input type="text" id="search-term" value="mah"></label>
        <button onclick="testAjax()">Test AJAX Search</button>
    </div>

    <div id="result"></div>

    <script>
    function testAjax() {
        var searchTerm = document.getElementById('search-term').value;
        var resultDiv = document.getElementById('result');

        resultDiv.innerHTML = '<p>Sending AJAX request...</p>';

        console.log('=== Starting AJAX Test ===');
        console.log('Search term:', searchTerm);
        console.log('AJAX URL:', '<?php echo $ajax_url; ?>');
        console.log('Nonce:', '<?php echo $nonce; ?>');

        $.ajax({
            url: '<?php echo $ajax_url; ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sc_search_users',
                nonce: '<?php echo $nonce; ?>',
                search: searchTerm
            },
            success: function(response) {
                console.log('=== AJAX Response ===');
                console.log('Full response:', response);

                var html = '<h2>AJAX Response</h2>';
                html += '<p><strong>Success:</strong> ' + response.success + '</p>';
                html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';

                if (response.success && response.data) {
                    var users = Array.isArray(response.data) ? response.data : (response.data.results || []);
                    html += '<p class="success"><strong>Users found: ' + users.length + '</strong></p>';

                    if (response.data.debug) {
                        html += '<h3>Debug Info:</h3>';
                        html += '<p>Search term received: ' + response.data.debug.search_term + '</p>';
                        html += '<p>WP Users found: ' + response.data.debug.wp_users_found + '</p>';
                        html += '<p>SQL Query:</p>';
                        html += '<pre style="font-size: 11px;">' + response.data.debug.sql_query + '</pre>';
                    }

                    if (users.length > 0) {
                        html += '<h3>Users:</h3>';
                        html += '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
                        html += '<tr><th>ID</th><th>Name</th><th>Email</th></tr>';
                        users.forEach(function(user) {
                            html += '<tr><td>' + user.ID + '</td><td>' + user.display_name + '</td><td>' + user.user_email + '</td></tr>';
                        });
                        html += '</table>';
                    }
                } else if (response.data && response.data.message) {
                    html += '<p class="error">Error: ' + response.data.message + '</p>';
                }

                resultDiv.innerHTML = html;
            },
            error: function(xhr, status, error) {
                console.log('=== AJAX Error ===');
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('Response:', xhr.responseText);

                resultDiv.innerHTML = '<h2 class="error">AJAX Error</h2>' +
                    '<p>Status: ' + status + '</p>' +
                    '<p>Error: ' + error + '</p>' +
                    '<pre>' + xhr.responseText + '</pre>';
            }
        });
    }
    </script>
</body>
</html>
