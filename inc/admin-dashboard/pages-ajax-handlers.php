<?php
/**
 * Pages AJAX Handlers
 *
 * Content pages (Privacy Policy, Terms & Conditions) handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create Content Page (Privacy Policy or Terms & Conditions)
 */
add_action('wp_ajax_sc_create_content_page', 'sc_create_content_page_handler');
function sc_create_content_page_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('You do not have permission to create pages.', 'sc_events')));
    }

    $page_type = sanitize_text_field($_POST['page_type']);

    if ($page_type === 'privacy') {
        $privacy_page = get_page_by_path('privacy-policy');

        if ($privacy_page) {
            wp_send_json_error(array('message' => __('Privacy Policy page already exists.', 'sc_events')));
        }

        $content = sc_get_privacy_policy_template();

        $page_data = array(
            'post_title' => 'Privacy Policy',
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'post_name' => 'privacy-policy',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        );

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(array('message' => __('Failed to create Privacy Policy page.', 'sc_events')));
        }

        update_option('wp_page_for_privacy_policy', $page_id);

        wp_send_json_success(array(
            'message' => __('Privacy Policy page created successfully!', 'sc_events'),
            'page_id' => $page_id
        ));

    } elseif ($page_type === 'terms') {
        $terms_page = get_page_by_path('terms-and-conditions');

        if ($terms_page) {
            wp_send_json_error(array('message' => __('Terms & Conditions page already exists.', 'sc_events')));
        }

        $content = sc_get_terms_template();

        $page_data = array(
            'post_title' => 'Terms and Conditions',
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'post_name' => 'terms-and-conditions',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        );

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(array('message' => __('Failed to create Terms & Conditions page.', 'sc_events')));
        }

        wp_send_json_success(array(
            'message' => __('Terms & Conditions page created successfully!', 'sc_events'),
            'page_id' => $page_id
        ));

    } else {
        wp_send_json_error(array('message' => __('Invalid page type.', 'sc_events')));
    }
}

/**
 * Get Page Content for Editing
 */
add_action('wp_ajax_sc_get_page_content', 'sc_get_page_content_handler');
function sc_get_page_content_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;

    if (!$page_id) {
        wp_send_json_error(array('message' => __('Invalid page ID.', 'sc_events')));
    }

    $page = get_post($page_id);

    if (!$page || $page->post_type !== 'page') {
        wp_send_json_error(array('message' => __('Page not found.', 'sc_events')));
    }

    wp_send_json_success(array(
        'page_id' => $page_id,
        'title' => $page->post_title,
        'content' => $page->post_content
    ));
}

/**
 * Update Page Content
 */
add_action('wp_ajax_sc_update_page_content', 'sc_update_page_content_handler');
function sc_update_page_content_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

    if (!$page_id) {
        wp_send_json_error(array('message' => __('Invalid page ID.', 'sc_events')));
    }

    $page = get_post($page_id);

    if (!$page || $page->post_type !== 'page') {
        wp_send_json_error(array('message' => __('Page not found.', 'sc_events')));
    }

    $result = wp_update_post(array(
        'ID' => $page_id,
        'post_title' => $title,
        'post_content' => $content
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => __('Failed to update page.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message' => __('Page updated successfully!', 'sc_events'),
        'page_id' => $page_id
    ));
}

/**
 * Get Privacy Policy Template
 */
function sc_get_privacy_policy_template() {
    return '<h2>Privacy Policy</h2>

<p>Your privacy is important to us. This privacy policy explains what personal data we collect and how we use it.</p>

<h3>Information We Collect</h3>
<p>When you use our event booking system, we may collect the following information:</p>
<ul>
    <li>Name and contact information (email address, phone number)</li>
    <li>Event registration details</li>
    <li>Payment information (processed securely through our payment gateway)</li>
    <li>Any additional information you provide during registration</li>
</ul>

<h3>How We Use Your Information</h3>
<p>We use your personal information to:</p>
<ul>
    <li>Process your event registrations and ticket purchases</li>
    <li>Send you event confirmations and updates</li>
    <li>Communicate with you about events and services</li>
    <li>Improve our services and user experience</li>
</ul>

<h3>Data Security</h3>
<p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction.</p>

<h3>Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access your personal data</li>
    <li>Request correction of your data</li>
    <li>Request deletion of your data</li>
    <li>Object to processing of your data</li>
</ul>

<h3>Contact Us</h3>
<p>If you have any questions about this privacy policy, please contact us.</p>';
}

/**
 * Get Terms & Conditions Template
 */
function sc_get_terms_template() {
    return '<h2>Terms and Conditions</h2>

<p>Welcome to our event platform. By using our services, you agree to the following terms and conditions.</p>

<h3>Use of Service</h3>
<p>Our platform provides event registration and ticketing services. You agree to use our services only for lawful purposes and in accordance with these terms.</p>

<h3>Event Registration</h3>
<ul>
    <li>You must provide accurate and complete information when registering for events</li>
    <li>You are responsible for maintaining the confidentiality of your account</li>
    <li>One registration per person unless otherwise specified</li>
    <li>Registration confirmation will be sent to your email address</li>
</ul>

<h3>Ticket Purchases</h3>
<ul>
    <li>All ticket sales are final unless the event is cancelled</li>
    <li>Prices are displayed in the local currency</li>
    <li>Payment must be completed to secure your registration</li>
    <li>Tickets are non-transferable unless stated otherwise</li>
</ul>

<h3>Cancellations and Refunds</h3>
<p>If an event is cancelled by the organizer, you will be entitled to a full refund. Refunds for other reasons are subject to the event organizer\'s policy.</p>

<h3>Prohibited Activities</h3>
<p>You may not:</p>
<ul>
    <li>Resell tickets for profit without authorization</li>
    <li>Use our platform for fraudulent purposes</li>
    <li>Attempt to gain unauthorized access to our systems</li>
    <li>Violate any applicable laws or regulations</li>
</ul>

<h3>Limitation of Liability</h3>
<p>We provide our services "as is" and make no warranties about their availability or accuracy. We are not liable for any damages arising from your use of our platform.</p>

<h3>Changes to Terms</h3>
<p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting to the website.</p>

<h3>Contact</h3>
<p>If you have questions about these terms, please contact us.</p>';
}
