<?php
/**
 * Public Certificate Pages
 * Provides shortcodes for certificate download and verification
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: Certificate Verification Page
 * Usage: [sc_certificate_verify]
 */
add_shortcode('sc_certificate_verify', 'sc_certificate_verify_shortcode');
function sc_certificate_verify_shortcode($atts) {
    ob_start();
    ?>
    <div class="sc-certificate-verify-container">
        <div class="sc-verify-form-wrapper">
            <h2><?php _e('Verify Certificate', 'sc_events'); ?></h2>
            <p class="sc-verify-description">
                <?php _e('Enter the verification code or certificate number to verify the authenticity of a certificate.', 'sc_events'); ?>
            </p>

            <form id="sc-verify-certificate-form" class="sc-verify-form">
                <div class="sc-form-group">
                    <label for="sc-verify-code"><?php _e('Verification Code or Certificate Number', 'sc_events'); ?></label>
                    <input type="text" id="sc-verify-code" name="code" class="sc-form-control" placeholder="<?php _e('Enter code...', 'sc_events'); ?>" required>
                </div>
                <button type="submit" class="sc-btn sc-btn-primary" id="sc-verify-btn">
                    <span class="sc-btn-text"><?php _e('Verify Certificate', 'sc_events'); ?></span>
                    <span class="sc-btn-loading" style="display: none;">
                        <i class="sc-spinner"></i> <?php _e('Verifying...', 'sc_events'); ?>
                    </span>
                </button>
            </form>

            <div id="sc-verify-result" class="sc-verify-result" style="display: none;"></div>
        </div>
    </div>

    <style>
    .sc-certificate-verify-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 30px 20px;
    }
    .sc-verify-form-wrapper {
        background: #fff;
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .sc-verify-form-wrapper h2 {
        margin-bottom: 10px;
        color: #1a1a1a;
        font-size: 28px;
    }
    .sc-verify-description {
        color: #666;
        margin-bottom: 30px;
    }
    .sc-form-group {
        margin-bottom: 20px;
    }
    .sc-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }
    .sc-form-control {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.2s;
    }
    .sc-form-control:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    .sc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 14px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    .sc-btn-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);
        color: #fff;
    }
    .sc-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .sc-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    .sc-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: sc-spin 0.8s linear infinite;
        margin-right: 8px;
    }
    @keyframes sc-spin {
        to { transform: rotate(360deg); }
    }
    .sc-verify-result {
        margin-top: 30px;
        padding: 25px;
        border-radius: 8px;
    }
    .sc-verify-result.sc-valid {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
    }
    .sc-verify-result.sc-invalid {
        background: #ffebee;
        border-left: 4px solid #f44336;
    }
    .sc-verify-result h3 {
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sc-verify-result.sc-valid h3 {
        color: #2e7d32;
    }
    .sc-verify-result.sc-invalid h3 {
        color: #c62828;
    }
    .sc-certificate-details {
        margin-top: 15px;
    }
    .sc-certificate-details table {
        width: 100%;
        border-collapse: collapse;
    }
    .sc-certificate-details td {
        padding: 10px 0;
        border-bottom: 1px solid rgba(0,0,0,0.08);
    }
    .sc-certificate-details td:first-child {
        font-weight: 500;
        color: #666;
        width: 40%;
    }
    </style>

    <script>
    (function() {
        var form = document.getElementById('sc-verify-certificate-form');
        var resultDiv = document.getElementById('sc-verify-result');
        var btn = document.getElementById('sc-verify-btn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var code = document.getElementById('sc-verify-code').value.trim();
            if (!code) return;

            // Show loading
            btn.querySelector('.sc-btn-text').style.display = 'none';
            btn.querySelector('.sc-btn-loading').style.display = 'inline-flex';
            btn.disabled = true;
            resultDiv.style.display = 'none';

            // Make AJAX request
            var formData = new FormData();
            formData.append('action', 'sc_verify_certificate');
            formData.append('code', code);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.querySelector('.sc-btn-text').style.display = 'inline';
                btn.querySelector('.sc-btn-loading').style.display = 'none';
                btn.disabled = false;

                if (data.success && data.data.valid) {
                    resultDiv.className = 'sc-verify-result sc-valid';
                    resultDiv.innerHTML = '<h3><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> ' + data.data.message + '</h3>' +
                        '<div class="sc-certificate-details"><table>' +
                        '<tr><td><?php _e('Certificate Number', 'sc_events'); ?></td><td>' + escapeHtml(data.data.certificate_number) + '</td></tr>' +
                        '<tr><td><?php _e('Recipient', 'sc_events'); ?></td><td>' + escapeHtml(data.data.attendee_name) + '</td></tr>' +
                        '<tr><td><?php _e('Event', 'sc_events'); ?></td><td>' + escapeHtml(data.data.event_title) + '</td></tr>' +
                        '<tr><td><?php _e('Event Date', 'sc_events'); ?></td><td>' + escapeHtml(data.data.event_date) + '</td></tr>' +
                        '<tr><td><?php _e('Issued Date', 'sc_events'); ?></td><td>' + escapeHtml(data.data.issued_at) + '</td></tr>' +
                        '</table></div>';
                } else {
                    resultDiv.className = 'sc-verify-result sc-invalid';
                    var msg = data.data ? data.data.message : '<?php _e('Verification failed. Please try again.', 'sc_events'); ?>';
                    resultDiv.innerHTML = '<h3><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg> ' + msg + '</h3>';
                }
                resultDiv.style.display = 'block';
            })
            .catch(function(error) {
                btn.querySelector('.sc-btn-text').style.display = 'inline';
                btn.querySelector('.sc-btn-loading').style.display = 'none';
                btn.disabled = false;
                resultDiv.className = 'sc-verify-result sc-invalid';
                resultDiv.innerHTML = '<h3><?php _e('Connection error. Please try again.', 'sc_events'); ?></h3>';
                resultDiv.style.display = 'block';
            });
        });

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: Certificate Download Page
 * Usage: [sc_certificate_download]
 */
add_shortcode('sc_certificate_download', 'sc_certificate_download_shortcode');
function sc_certificate_download_shortcode($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="sc-certificate-login-required">
            <p>' . __('Please log in to access your certificates.', 'sc_events') . '</p>
            <a href="' . wp_login_url(get_permalink()) . '" class="sc-btn sc-btn-primary">' . __('Log In', 'sc_events') . '</a>
        </div>';
    }

    $user_id = get_current_user_id();

    // Get user's certificates via their attendee records
    global $wpdb;
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $certificates_table = $wpdb->prefix . 'sc_certificates';

    $certificates = $wpdb->get_results($wpdb->prepare("
        SELECT c.*, t.name as template_name
        FROM {$certificates_table} c
        LEFT JOIN {$wpdb->prefix}sc_certificate_templates t ON c.template_id = t.id
        WHERE c.attendee_id IN (
            SELECT id FROM {$attendees_table} WHERE user_id = %d
        )
        ORDER BY c.issued_at DESC
    ", $user_id));

    ob_start();
    ?>
    <div class="sc-my-certificates">
        <h2><?php _e('My Certificates', 'sc_events'); ?></h2>

        <?php if (empty($certificates)): ?>
            <div class="sc-no-certificates">
                <p><?php _e('You don\'t have any certificates yet.', 'sc_events'); ?></p>
            </div>
        <?php else: ?>
            <div class="sc-certificates-list">
                <?php foreach ($certificates as $cert): ?>
                    <div class="sc-certificate-card <?php echo $cert->status !== 'issued' ? 'sc-revoked' : ''; ?>">
                        <div class="sc-cert-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                            </svg>
                        </div>
                        <div class="sc-cert-info">
                            <h3><?php echo esc_html($cert->event_title); ?></h3>
                            <p class="sc-cert-number"><?php echo esc_html($cert->certificate_number); ?></p>
                            <p class="sc-cert-date"><?php echo sprintf(__('Issued: %s', 'sc_events'), date_i18n(get_option('date_format'), strtotime($cert->issued_at))); ?></p>
                            <?php if ($cert->status === 'revoked'): ?>
                                <span class="sc-cert-status sc-status-revoked"><?php _e('Revoked', 'sc_events'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sc-cert-actions">
                            <?php if ($cert->status === 'issued'): ?>
                                <?php
                                $download_token = wp_hash($cert->verification_code . $cert->certificate_number);
                                $download_url = add_query_arg(array(
                                    'action' => 'sc_download_certificate',
                                    'id' => $cert->id,
                                    'token' => $download_token
                                ), admin_url('admin-ajax.php'));
                                ?>
                                <a href="<?php echo esc_url($download_url); ?>" class="sc-btn sc-btn-primary" target="_blank">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                    </svg>
                                    <?php _e('Download', 'sc_events'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .sc-my-certificates {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .sc-my-certificates h2 {
        margin-bottom: 30px;
        color: #1a1a1a;
    }
    .sc-no-certificates {
        text-align: center;
        padding: 60px 20px;
        background: #f9f9f9;
        border-radius: 12px;
        color: #666;
    }
    .sc-certificates-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .sc-certificate-card {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 25px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    .sc-certificate-card:hover {
        transform: translateY(-2px);
    }
    .sc-certificate-card.sc-revoked {
        opacity: 0.6;
        background: #f5f5f5;
    }
    .sc-cert-icon {
        flex-shrink: 0;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }
    .sc-certificate-card.sc-revoked .sc-cert-icon {
        background: #ccc;
    }
    .sc-cert-info {
        flex: 1;
    }
    .sc-cert-info h3 {
        margin: 0 0 5px 0;
        font-size: 18px;
        color: #1a1a1a;
    }
    .sc-cert-number {
        margin: 0;
        font-family: monospace;
        font-size: 14px;
        color: var(--primary-color);
    }
    .sc-cert-date {
        margin: 5px 0 0 0;
        font-size: 13px;
        color: #888;
    }
    .sc-cert-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
    }
    .sc-status-revoked {
        background: #ffebee;
        color: #c62828;
    }
    .sc-cert-actions {
        flex-shrink: 0;
    }
    .sc-cert-actions .sc-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        font-size: 14px;
    }
    .sc-certificate-login-required {
        text-align: center;
        padding: 60px 20px;
        background: #f9f9f9;
        border-radius: 12px;
    }
    .sc-certificate-login-required .sc-btn {
        display: inline-block;
        padding: 12px 30px;
        margin-top: 15px;
        text-decoration: none;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);
        color: #fff;
        border-radius: 8px;
    }
    @media (max-width: 600px) {
        .sc-certificate-card {
            flex-direction: column;
            text-align: center;
        }
        .sc-cert-actions {
            width: 100%;
        }
        .sc-cert-actions .sc-btn {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Handle direct certificate download via URL
 */
add_action('template_redirect', 'sc_handle_certificate_download_url');
function sc_handle_certificate_download_url() {
    if (!isset($_GET['sc_certificate']) || !isset($_GET['token'])) {
        return;
    }

    $certificate_id = intval($_GET['sc_certificate']);
    $token = sanitize_text_field($_GET['token']);

    if (!$certificate_id || !$token) {
        wp_die(__('Invalid certificate request.', 'sc_events'));
    }

    $certificate = SC_Certificate::get($certificate_id);
    if (!$certificate) {
        wp_die(__('Certificate not found.', 'sc_events'));
    }

    // Verify token
    $expected_token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);
    if (!hash_equals($expected_token, $token)) {
        wp_die(__('Invalid certificate token.', 'sc_events'));
    }

    if (!in_array($certificate['status'], array('issued', 'downloaded'), true)) {
        wp_die(__('This certificate is not valid for download.', 'sc_events'));
    }

    // Record download
    SC_Certificate::record_download($certificate_id);

    // Check if PDF generator is available
    $pdf_class = get_template_directory() . '/inc/certificates/class-sc-certificate-pdf.php';
    if (file_exists($pdf_class)) {
        require_once $pdf_class;

        // Generate and stream PDF
        try {
            SC_Certificate_PDF::download($certificate_id);
        } catch (Exception $e) {
            // Fallback to HTML if PDF generation fails
            sc_output_certificate_html($certificate);
        }
    } else {
        // Fallback to HTML output
        sc_output_certificate_html($certificate);
    }
    exit;
}

/**
 * Output certificate as HTML (fallback when PDF not available)
 */
function sc_output_certificate_html($certificate) {
    // Generate and output certificate
    $template = SC_Certificate_Template::get($certificate['template_id']);
    if (!$template) {
        wp_die(__('Certificate template not found.', 'sc_events'));
    }

    // Prepare data
    $data = array(
        'attendee_name' => $certificate['attendee_name'],
        'event_title' => $certificate['event_title'],
        'event_date' => $certificate['event_date'],
        'certificate_number' => $certificate['certificate_number'],
        'verification_code' => $certificate['verification_code'],
        'issue_date' => date_i18n(get_option('date_format'), strtotime($certificate['issued_at'])),
        'qr_code' => ''
    );

    $html = SC_Certificate_Template::render($template, $data);

    // Output as HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($certificate['certificate_number']); ?> - Certificate</title>
        <style>
            @media print {
                body { margin: 0; }
                .print-hide { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="print-hide" style="padding: 20px; text-align: center; background: #f5f5f5; border-bottom: 1px solid #ddd;">
            <button onclick="window.print()" style="padding: 10px 30px; background: var(--primary-color); color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                Print Certificate
            </button>
        </div>
        <?php echo $html; ?>
    </body>
    </html>
    <?php
}

/**
 * Register verification page endpoint
 */
add_action('init', 'sc_register_certificate_endpoints');
function sc_register_certificate_endpoints() {
    add_rewrite_rule(
        '^certificate/verify/([^/]+)/?$',
        'index.php?sc_verify_code=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^certificate/download/([0-9]+)/([^/]+)/?$',
        'index.php?sc_certificate=$matches[1]&token=$matches[2]',
        'top'
    );
}

add_filter('query_vars', 'sc_certificate_query_vars');
function sc_certificate_query_vars($vars) {
    $vars[] = 'sc_verify_code';
    $vars[] = 'sc_certificate';
    $vars[] = 'token';
    return $vars;
}
