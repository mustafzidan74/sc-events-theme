<?php
/**
 * SC Events Custom Database Schema
 *
 * High-performance custom tables for events management
 * Replaces slow wp_postmeta queries with optimized direct queries
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database version for migrations
 */
define('SC_DB_VERSION', '2.4.0');

/**
 * Check if a database table exists (prepared statement)
 *
 * @param string $table_name Full table name including prefix
 * @return bool
 */
function sc_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
}

/**
 * Get table names with WordPress prefix
 */
function sc_get_table_names() {
    global $wpdb;

    return array(
        'events'        => $wpdb->prefix . 'sc_events',
        'tickets'       => $wpdb->prefix . 'sc_tickets',
        'attendees'     => $wpdb->prefix . 'sc_attendees',
        'transactions'  => $wpdb->prefix . 'sc_transactions',
        'coupons'           => $wpdb->prefix . 'sc_coupons',
        'coupon_categories' => $wpdb->prefix . 'sc_coupon_categories',
        'workshops'         => $wpdb->prefix . 'sc_workshops',
        'speakers'      => $wpdb->prefix . 'sc_speakers',
        'organizers'    => $wpdb->prefix . 'sc_organizers',
        'event_speakers'    => $wpdb->prefix . 'sc_event_speakers',
        'event_organizers'  => $wpdb->prefix . 'sc_event_organizers',
        'event_categories'  => $wpdb->prefix . 'sc_event_categories',
        'checkins'      => $wpdb->prefix . 'sc_checkins',
        'referrals'     => $wpdb->prefix . 'sc_referrals',
        'user_points'   => $wpdb->prefix . 'sc_user_points',
        'certificate_templates' => $wpdb->prefix . 'sc_certificate_templates',
        'certificates'  => $wpdb->prefix . 'sc_certificates',
        // Payment Gateway Tables
        'payment_gateways'  => $wpdb->prefix . 'sc_payment_gateways',
        'payments'          => $wpdb->prefix . 'sc_payments',
        // Company Attendees Tables
        'company_attendees' => $wpdb->prefix . 'sc_company_attendees',
        'company_checkins'  => $wpdb->prefix . 'sc_company_checkins',
        // Chat System Tables
        'conversations'     => $wpdb->prefix . 'sc_conversations',
        'chat_messages'     => $wpdb->prefix . 'sc_chat_messages',
        // Booth Management Tables (Exhibitions)
        'booth_types'       => $wpdb->prefix . 'sc_booth_types',
        'booths'            => $wpdb->prefix . 'sc_booths',
        'booth_bookings'    => $wpdb->prefix . 'sc_booth_bookings',
        'booth_visits'      => $wpdb->prefix . 'sc_booth_visits',
        // Saudi Integration Tables
        'invoices'          => $wpdb->prefix . 'sc_invoices',
        'sms_logs'          => $wpdb->prefix . 'sc_sms_logs',
        // Module System Table
        'module_settings'       => $wpdb->prefix . 'sc_module_settings',
        // Sessions Module Tables
        'sessions'              => $wpdb->prefix . 'sc_sessions',
        'session_speakers'      => $wpdb->prefix . 'sc_session_speakers',
        'session_registrations' => $wpdb->prefix . 'sc_session_registrations',
        'session_attendance'    => $wpdb->prefix . 'sc_session_attendance',
        // Scanner Permissions
        'scanner_permissions'   => $wpdb->prefix . 'sc_scanner_permissions',
        // Sponsors Module Tables
        'sponsors'              => $wpdb->prefix . 'sc_sponsors',
        'event_sponsors'        => $wpdb->prefix . 'sc_event_sponsors',
        // Halls & Schedules
        'halls'                 => $wpdb->prefix . 'sc_halls',
        'schedules'             => $wpdb->prefix . 'sc_schedules',
        'schedule_favorites'    => $wpdb->prefix . 'sc_schedule_favorites',
    );
}

/**
 * Create all custom tables
 */
function sc_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $tables = sc_get_table_names();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // ========================================
    // 1. EVENTS TABLE
    // ========================================
    $sql_events = "CREATE TABLE {$tables['events']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_post_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Link to wp_posts for backward compatibility',
        title varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description longtext,
        excerpt text,
        featured_image bigint(20) UNSIGNED DEFAULT NULL,
        logo_image bigint(20) UNSIGNED DEFAULT NULL,
        banner_image bigint(20) UNSIGNED DEFAULT NULL,

        -- Date & Time
        start_date date NOT NULL,
        end_date date NOT NULL,
        start_time time DEFAULT NULL,
        end_time time DEFAULT NULL,
        timezone varchar(100) DEFAULT 'Africa/Cairo',
        all_day_event tinyint(1) DEFAULT 0,

        -- Location
        location_type enum('offline', 'online', 'hybrid') DEFAULT 'offline',
        venue_name varchar(255) DEFAULT NULL,
        venue_address text DEFAULT NULL,
        venue_city varchar(100) DEFAULT NULL,
        venue_country varchar(100) DEFAULT NULL,
        venue_lat decimal(10, 8) DEFAULT NULL,
        venue_lng decimal(11, 8) DEFAULT NULL,
        google_maps_url varchar(2048) DEFAULT NULL COMMENT 'Google Maps share URL (any format: short or full)',
        meeting_link varchar(500) DEFAULT NULL,

        -- Capacity & Registration
        total_capacity int(11) DEFAULT 0,
        registration_deadline datetime DEFAULT NULL,
        min_tickets_per_order int(11) DEFAULT 1,
        max_tickets_per_order int(11) DEFAULT 10,

        -- Content
        faq longtext COMMENT 'JSON array of FAQ items',
        schedule longtext COMMENT 'JSON array of schedule items',
        additional_sections longtext COMMENT 'JSON array of custom sections',
        social_links longtext COMMENT 'JSON array of social media links',

        -- Extra Fields for Attendees
        extra_fields longtext COMMENT 'JSON array of custom form fields',

        -- Files
        schedules_file bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for PDF schedule file',

        -- Settings
        attendance_tracking tinyint(1) DEFAULT 0,
        calendar_bg_color varchar(7) DEFAULT '#667eea',
        calendar_text_color varchar(7) DEFAULT '#ffffff',

        -- Certificate Settings
        enable_certificates tinyint(1) DEFAULT 0,
        certificate_template_id bigint(20) UNSIGNED DEFAULT NULL,
        auto_issue_certificate tinyint(1) DEFAULT 0 COMMENT 'Auto issue after check-in',
        certificate_require_checkin tinyint(1) DEFAULT 1 COMMENT 'Require check-in for certificate',
        certificate_require_checkout tinyint(1) DEFAULT 0 COMMENT 'Require checkout for certificate',
        certificate_require_event_ended tinyint(1) DEFAULT 0 COMMENT 'Only show certificate after event ended',

        -- Status
        status enum('draft', 'publish', 'private', 'cancelled', 'completed', 'disabled') DEFAULT 'draft',

        -- Stats (cached for performance)
        total_sold int(11) DEFAULT 0,
        total_revenue decimal(12, 2) DEFAULT 0.00,
        total_checked_in int(11) DEFAULT 0,

        -- Metadata
        author_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY wp_post_id (wp_post_id),
        KEY status (status),
        KEY start_date (start_date),
        KEY end_date (end_date),
        KEY author_id (author_id),
        KEY location_type (location_type),
        KEY status_date (status, start_date)
    ) $charset_collate;";

    dbDelta($sql_events);

    // ========================================
    // 2. TICKETS TABLE
    // ========================================
    $sql_tickets = "CREATE TABLE {$tables['tickets']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,

        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,

        price decimal(10, 2) DEFAULT 0.00,
        quantity int(11) DEFAULT 0 COMMENT 'Total available tickets',
        sold int(11) DEFAULT 0,

        min_per_order int(11) DEFAULT 1,
        max_per_order int(11) DEFAULT 10,

        -- Availability Period
        sale_start datetime DEFAULT NULL,
        sale_end datetime DEFAULT NULL,

        -- Settings
        enable_coupons tinyint(1) DEFAULT 1,
        is_active tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY slug (slug),
        KEY is_active (is_active),
        KEY event_active (event_id, is_active)
    ) $charset_collate;";

    dbDelta($sql_tickets);

    // ========================================
    // 3. ATTENDEES TABLE
    // ========================================
    $sql_attendees = "CREATE TABLE {$tables['attendees']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_post_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Link to etn-attendee post for backward compatibility',

        event_id bigint(20) UNSIGNED NOT NULL,
        ticket_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID if registered',

        -- Contact Info
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50) DEFAULT NULL,

        -- Ticket Details
        ticket_code varchar(50) NOT NULL COMMENT 'Unique ticket identifier',
        ticket_name varchar(255) NOT NULL,
        ticket_price decimal(10, 2) DEFAULT 0.00,

        -- Payment
        payment_status enum('pending', 'success', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
        payment_method varchar(50) DEFAULT NULL COMMENT 'free, coupon, woocommerce, paymob, fawry',
        amount_paid decimal(10, 2) DEFAULT 0.00,
        coupon_code varchar(50) DEFAULT NULL,
        coupon_discount decimal(10, 2) DEFAULT 0.00,

        -- Check-in
        checked_in tinyint(1) DEFAULT 0,
        checked_in_at datetime DEFAULT NULL,
        checked_in_by bigint(20) UNSIGNED DEFAULT NULL,

        -- Extra Fields
        extra_fields longtext COMMENT 'JSON of custom field values',

        -- Security
        edit_token varchar(64) DEFAULT NULL COMMENT 'Token for self-service editing',
        qr_data longtext COMMENT 'JSON encoded QR code data',

        -- Communication
        email_sent tinyint(1) DEFAULT 0,
        email_sent_at datetime DEFAULT NULL,

        -- Notes
        notes text,

        -- Status
        status enum('active', 'cancelled', 'transferred') DEFAULT 'active',

        -- References
        order_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WooCommerce order ID',
        transaction_id bigint(20) UNSIGNED DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY ticket_code (ticket_code),
        KEY wp_post_id (wp_post_id),
        KEY event_id (event_id),
        KEY ticket_id (ticket_id),
        KEY user_id (user_id),
        KEY email (email),
        KEY phone (phone),
        KEY payment_status (payment_status),
        KEY checked_in (checked_in),
        KEY status (status),
        KEY order_id (order_id),
        KEY event_status (event_id, status),
        KEY event_payment (event_id, payment_status),
        KEY user_event (user_id, event_id)
    ) $charset_collate;";

    dbDelta($sql_attendees);

    // ========================================
    // 4. TRANSACTIONS TABLE
    // ========================================
    $sql_transactions = "CREATE TABLE {$tables['transactions']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        attendee_id bigint(20) UNSIGNED DEFAULT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Amount
        amount decimal(10, 2) NOT NULL,
        currency varchar(3) DEFAULT 'EGP',

        -- Payment Details
        payment_method varchar(50) NOT NULL,
        payment_gateway varchar(50) DEFAULT NULL,
        gateway_transaction_id varchar(255) DEFAULT NULL,
        gateway_response longtext COMMENT 'JSON response from payment gateway',

        -- Status
        status enum('pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',

        -- Refund
        refund_amount decimal(10, 2) DEFAULT 0.00,
        refunded_at datetime DEFAULT NULL,
        refund_reason text,

        -- Metadata
        ip_address varchar(45) DEFAULT NULL,
        user_agent text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY attendee_id (attendee_id),
        KEY event_id (event_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY payment_method (payment_method),
        KEY gateway_transaction_id (gateway_transaction_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql_transactions);

    // ========================================
    // 5. COUPONS TABLE
    // ========================================
    $sql_coupons = "CREATE TABLE {$tables['coupons']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_post_id bigint(20) UNSIGNED DEFAULT NULL,

        code varchar(50) NOT NULL,
        description text,

        -- Discount
        discount_type enum('percentage', 'fixed') DEFAULT 'percentage',
        discount_value decimal(10, 2) NOT NULL,

        -- Restrictions
        event_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = applies to all events',
        category_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK to sc_coupon_categories.id',
        ticket_ids longtext COMMENT 'JSON array of ticket IDs, NULL = all tickets',
        min_amount decimal(10, 2) DEFAULT 0.00,
        max_discount decimal(10, 2) DEFAULT NULL,

        -- Limits
        usage_limit int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
        usage_count int(11) DEFAULT 0,
        per_user_limit int(11) DEFAULT 1,

        -- Validity
        start_date datetime DEFAULT NULL,
        expiry_date datetime DEFAULT NULL,

        -- Status
        is_active tinyint(1) DEFAULT 1,

        created_by bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY wp_post_id (wp_post_id),
        KEY event_id (event_id),
        KEY category_id (category_id),
        KEY is_active (is_active),
        KEY expiry_date (expiry_date)
    ) $charset_collate;";

    dbDelta($sql_coupons);

    // ========================================
    // 5b. COUPON CATEGORIES TABLE
    // ========================================
    $sql_coupon_categories = "CREATE TABLE {$tables['coupon_categories']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        slug varchar(100) NOT NULL,
        description text DEFAULT NULL,
        color varchar(7) DEFAULT '#7c1314',
        sort_order int(11) DEFAULT 0,
        is_protected tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY sort_order (sort_order)
    ) $charset_collate;";

    dbDelta($sql_coupon_categories);

    // Seed default General category and ensure all coupons reference it
    $coupon_cat_table = $tables['coupon_categories'];
    $coupons_table = $tables['coupons'];
    $existing = $wpdb->get_var("SELECT id FROM {$coupon_cat_table} WHERE id = 1");
    if (!$existing) {
        $wpdb->query("INSERT INTO {$coupon_cat_table} (id, name, slug, description, color, is_protected) VALUES (1, 'General', 'general', 'Default category', '#7c1314', 1)");
    }
    $wpdb->query("UPDATE {$coupons_table} SET category_id = 1 WHERE category_id IS NULL");

    // ========================================
    // 5c. WORKSHOPS TABLE (Independent ticketed sub-units of an event)
    // ========================================
    $sql_workshops = "CREATE TABLE {$tables['workshops']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL COMMENT 'Parent event',
        title varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description longtext,
        excerpt text,
        featured_image bigint(20) UNSIGNED DEFAULT NULL,
        banner_image bigint(20) UNSIGNED DEFAULT NULL,
        start_date date NOT NULL,
        end_date date DEFAULT NULL,
        start_time time DEFAULT NULL,
        end_time time DEFAULT NULL,
        timezone varchar(100) DEFAULT 'Africa/Cairo',
        location_type enum('offline','online','hybrid') DEFAULT 'offline',
        venue_name varchar(255) DEFAULT NULL,
        venue_address text DEFAULT NULL,
        meeting_link varchar(500) DEFAULT NULL,
        total_capacity int(11) DEFAULT 0,
        registration_deadline datetime DEFAULT NULL,
        min_tickets_per_order int(11) DEFAULT 1,
        max_tickets_per_order int(11) DEFAULT 10,
        extra_fields longtext,
        enable_certificates tinyint(1) DEFAULT 0,
        certificate_template_id bigint(20) UNSIGNED DEFAULT NULL,
        auto_issue_certificate tinyint(1) DEFAULT 0,
        certificate_require_checkin tinyint(1) DEFAULT 1,
        status enum('draft','publish','private','cancelled','completed','disabled') DEFAULT 'draft',
        total_sold int(11) DEFAULT 0,
        total_revenue decimal(12, 2) DEFAULT 0.00,
        total_checked_in int(11) DEFAULT 0,
        author_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY event_id (event_id),
        KEY status (status),
        KEY start_date (start_date),
        KEY status_event (status, event_id)
    ) $charset_collate;";

    dbDelta($sql_workshops);

    // Add workshop_id column to existing tables if missing (idempotent migration for upgrades)
    $tickets_table      = $tables['tickets'];
    $attendees_table    = $tables['attendees'];
    $checkins_table     = $tables['checkins'];
    $certificates_table = $tables['certificates'];
    $scanner_perms_table = $tables['scanner_permissions'];

    foreach (array($tickets_table, $attendees_table, $checkins_table, $certificates_table, $scanner_perms_table) as $tbl) {
        $col_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'workshop_id'",
            $tbl
        ));
        if (!$col_exists) {
            $wpdb->query("ALTER TABLE $tbl ADD COLUMN workshop_id bigint(20) UNSIGNED DEFAULT NULL AFTER event_id, ADD KEY workshop_id (workshop_id)");
        }
    }

    // ========================================
    // 6. SPEAKERS TABLE
    // ========================================
    $sql_speakers = "CREATE TABLE {$tables['speakers']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_post_id bigint(20) UNSIGNED DEFAULT NULL,

        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        title varchar(255) DEFAULT NULL COMMENT 'Job title',
        company varchar(255) DEFAULT NULL,
        bio text,
        photo bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID',

        -- Contact
        email varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        website varchar(255) DEFAULT NULL,

        -- Social
        social_links longtext COMMENT 'JSON array of social media links',

        -- Stats
        events_count int(11) DEFAULT 0,

        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY wp_post_id (wp_post_id),
        KEY is_active (is_active)
    ) $charset_collate;";

    dbDelta($sql_speakers);

    // ========================================
    // 7. ORGANIZERS TABLE
    // ========================================
    $sql_organizers = "CREATE TABLE {$tables['organizers']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_post_id bigint(20) UNSIGNED DEFAULT NULL,

        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,
        logo bigint(20) UNSIGNED DEFAULT NULL,

        -- Contact
        email varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        website varchar(255) DEFAULT NULL,
        address text,

        -- Social
        social_links longtext COMMENT 'JSON array',

        -- Stats
        events_count int(11) DEFAULT 0,

        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY wp_post_id (wp_post_id),
        KEY is_active (is_active)
    ) $charset_collate;";

    dbDelta($sql_organizers);

    // ========================================
    // 8. EVENT-SPEAKERS JUNCTION TABLE
    // ========================================
    $sql_event_speakers = "CREATE TABLE {$tables['event_speakers']} (
        event_id bigint(20) UNSIGNED NOT NULL,
        speaker_id bigint(20) UNSIGNED NOT NULL,
        sort_order int(11) DEFAULT 0,
        role varchar(100) DEFAULT NULL COMMENT 'keynote, panelist, moderator',

        PRIMARY KEY (event_id, speaker_id),
        KEY speaker_id (speaker_id)
    ) $charset_collate;";

    dbDelta($sql_event_speakers);

    // ========================================
    // 9. EVENT-ORGANIZERS JUNCTION TABLE
    // ========================================
    $sql_event_organizers = "CREATE TABLE {$tables['event_organizers']} (
        event_id bigint(20) UNSIGNED NOT NULL,
        organizer_id bigint(20) UNSIGNED NOT NULL,
        role varchar(100) DEFAULT 'organizer' COMMENT 'Role: organizer, sponsor, etc.',
        sort_order int(11) DEFAULT 0,
        is_primary tinyint(1) DEFAULT 0,

        PRIMARY KEY (event_id, organizer_id),
        KEY organizer_id (organizer_id)
    ) $charset_collate;";

    dbDelta($sql_event_organizers);

    // ========================================
    // 9.5 EVENT-CATEGORIES JUNCTION TABLE
    // ========================================
    $sql_event_categories = "CREATE TABLE {$tables['event_categories']} (
        event_id bigint(20) UNSIGNED NOT NULL,
        category_id bigint(20) UNSIGNED NOT NULL COMMENT 'WordPress term_id from sc_event_category taxonomy',
        sort_order int(11) DEFAULT 0,

        PRIMARY KEY (event_id, category_id),
        KEY category_id (category_id)
    ) $charset_collate;";

    dbDelta($sql_event_categories);

    // ========================================
    // 10. CHECK-INS LOG TABLE
    // ========================================
    $sql_checkins = "CREATE TABLE {$tables['checkins']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        attendee_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,

        action enum('checkin', 'checkout', 'manual_checkin', 'manual_checkout') DEFAULT 'checkin',

        scanned_by bigint(20) UNSIGNED DEFAULT NULL,
        scan_method varchar(50) DEFAULT 'qr' COMMENT 'qr, manual, app',
        device_info varchar(255) DEFAULT NULL,

        notes text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY attendee_id (attendee_id),
        KEY event_id (event_id),
        KEY created_at (created_at),
        KEY event_date (event_id, created_at)
    ) $charset_collate;";

    dbDelta($sql_checkins);

    // ========================================
    // 11. REFERRALS TABLE (for future referral system)
    // ========================================
    $sql_referrals = "CREATE TABLE {$tables['referrals']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        referrer_id bigint(20) UNSIGNED NOT NULL COMMENT 'User who referred',
        referred_id bigint(20) UNSIGNED NOT NULL COMMENT 'User who was referred',

        referral_code varchar(50) NOT NULL,

        -- Rewards
        referrer_reward decimal(10, 2) DEFAULT 0.00,
        referred_reward decimal(10, 2) DEFAULT 0.00,
        reward_type enum('credit', 'discount', 'points') DEFAULT 'credit',

        -- Status
        status enum('pending', 'completed', 'cancelled') DEFAULT 'pending',
        completed_at datetime DEFAULT NULL,

        -- Tracking
        attendee_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'First purchase by referred user',

        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY referrer_id (referrer_id),
        KEY referred_id (referred_id),
        KEY referral_code (referral_code),
        KEY status (status)
    ) $charset_collate;";

    dbDelta($sql_referrals);

    // ========================================
    // 12. USER POINTS TABLE (for gamification)
    // ========================================
    $sql_user_points = "CREATE TABLE {$tables['user_points']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        user_id bigint(20) UNSIGNED NOT NULL,

        -- Points
        points int(11) NOT NULL DEFAULT 0,
        action varchar(50) NOT NULL COMMENT 'registration, checkin, referral, review',
        description varchar(255) DEFAULT NULL,

        -- Reference
        reference_type varchar(50) DEFAULT NULL COMMENT 'event, attendee, referral',
        reference_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Expiry (for promotional points)
        expires_at datetime DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY expires_at (expires_at),
        KEY user_action (user_id, action)
    ) $charset_collate;";

    dbDelta($sql_user_points);

    // ========================================
    // 13. CERTIFICATE TEMPLATES TABLE
    // ========================================
    $sql_certificate_templates = "CREATE TABLE {$tables['certificate_templates']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,
        design_mode varchar(10) DEFAULT 'classic',
        background_image bigint(20) UNSIGNED DEFAULT NULL,
        html_template longtext,
        css_styles longtext,
        elements_config longtext,
        orientation varchar(10) DEFAULT 'landscape',
        paper_size varchar(20) DEFAULT 'A4',
        width_mm int(11) DEFAULT 297,
        height_mm int(11) DEFAULT 210,
        is_default tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_by bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY is_default (is_default),
        KEY is_active (is_active)
    ) $charset_collate;";

    dbDelta($sql_certificate_templates);

    // ========================================
    // 14. CERTIFICATES (ISSUED) TABLE
    // ========================================
    $sql_certificates = "CREATE TABLE {$tables['certificates']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        -- Reference
        certificate_number varchar(50) NOT NULL COMMENT 'Unique certificate number',
        verification_code varchar(32) NOT NULL COMMENT 'Code for verification',
        idempotency_key varchar(64) DEFAULT NULL COMMENT 'Unique key to prevent duplicate issuance',

        -- Relations
        template_id bigint(20) UNSIGNED NOT NULL,
        attendee_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,

        -- Certificate Data (cached for PDF generation)
        attendee_name varchar(255) NOT NULL,
        event_title varchar(255) NOT NULL,
        event_date date NOT NULL,
        custom_data longtext COMMENT 'JSON of additional placeholder values',

        -- PDF Storage
        pdf_file varchar(255) DEFAULT NULL COMMENT 'Path to generated PDF',
        pdf_generated_at datetime DEFAULT NULL,

        -- Status
        status enum('issued', 'downloaded', 'revoked') DEFAULT 'issued',
        revoked_at datetime DEFAULT NULL,
        revoke_reason text,

        -- Tracking
        issued_by bigint(20) UNSIGNED NOT NULL,
        issued_at datetime DEFAULT CURRENT_TIMESTAMP,
        downloaded_at datetime DEFAULT NULL,
        download_count int(11) DEFAULT 0,
        last_download_ip varchar(45) DEFAULT NULL,

        -- Email
        email_sent tinyint(1) DEFAULT 0,
        email_sent_at datetime DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY certificate_number (certificate_number),
        UNIQUE KEY verification_code (verification_code),
        UNIQUE KEY idempotency_key (idempotency_key),
        UNIQUE KEY attendee_event (attendee_id, event_id),
        KEY template_id (template_id),
        KEY event_id (event_id),
        KEY status (status),
        KEY issued_at (issued_at)
    ) $charset_collate;";

    dbDelta($sql_certificates);

    // ========================================
    // 15. PAYMENT GATEWAYS TABLE
    // ========================================
    $sql_payment_gateways = "CREATE TABLE {$tables['payment_gateways']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        gateway_code varchar(50) NOT NULL COMMENT 'paymob, stripe, myfatoorah, kashier',
        gateway_name varchar(100) NOT NULL,

        is_enabled tinyint(1) DEFAULT 0,
        is_test_mode tinyint(1) DEFAULT 1,

        settings longtext COMMENT 'JSON: API keys, merchant IDs, etc.',

        display_order int(11) DEFAULT 0,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY gateway_code (gateway_code),
        KEY is_enabled (is_enabled)
    ) $charset_collate;";

    dbDelta($sql_payment_gateways);

    // ========================================
    // 16. PAYMENTS TABLE
    // ========================================
    $sql_payments = "CREATE TABLE {$tables['payments']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        payment_ref varchar(100) NOT NULL COMMENT 'Unique payment reference',

        gateway_code varchar(50) NOT NULL,
        gateway_transaction_id varchar(255) DEFAULT NULL,
        gateway_order_id varchar(255) DEFAULT NULL COMMENT 'Gateway order/session ID',

        event_id bigint(20) UNSIGNED NOT NULL,
        ticket_id bigint(20) UNSIGNED NOT NULL,
        attendee_id bigint(20) UNSIGNED DEFAULT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Amount
        amount decimal(10,2) NOT NULL,
        currency varchar(10) NOT NULL DEFAULT 'EGP',

        -- Status
        status enum('pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',

        -- Payer Info
        payer_name varchar(255) DEFAULT NULL,
        payer_email varchar(255) DEFAULT NULL,
        payer_phone varchar(50) DEFAULT NULL,

        -- Extra Data
        metadata longtext COMMENT 'JSON: extra fields, quantities, coupon, etc.',
        gateway_response longtext COMMENT 'JSON: full gateway response',

        -- Refund Info
        refund_amount decimal(10,2) DEFAULT 0.00,
        refunded_at datetime DEFAULT NULL,
        refund_reason text,

        -- Timestamps
        paid_at datetime DEFAULT NULL,
        expires_at datetime DEFAULT NULL COMMENT 'Payment session expiry',

        -- Tracking
        ip_address varchar(45) DEFAULT NULL,
        user_agent text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY payment_ref (payment_ref),
        KEY gateway_code (gateway_code),
        KEY gateway_transaction_id (gateway_transaction_id),
        KEY event_id (event_id),
        KEY ticket_id (ticket_id),
        KEY attendee_id (attendee_id),
        KEY status (status),
        KEY payer_email (payer_email),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql_payments);

    // ========================================
    // 17. COMPANY ATTENDEES TABLE
    // ========================================
    $sql_company_attendees = "CREATE TABLE {$tables['company_attendees']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        event_id bigint(20) UNSIGNED NOT NULL,
        ticket_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Company Information
        company_name varchar(255) NOT NULL,
        company_name_ar varchar(255) DEFAULT NULL,
        company_logo bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID',
        industry varchar(100) DEFAULT NULL,
        company_size enum('1-10', '11-50', '51-200', '201-500', '500+') DEFAULT NULL,
        website varchar(255) DEFAULT NULL,

        -- Contact Person
        contact_name varchar(255) DEFAULT NULL,
        contact_title varchar(100) DEFAULT NULL,
        contact_email varchar(255) NOT NULL,
        contact_phone varchar(50) NOT NULL,

        -- Address
        country varchar(100) DEFAULT NULL,
        city varchar(100) DEFAULT NULL,
        address text DEFAULT NULL,

        -- Booth/Sponsorship Info
        booth_number varchar(50) DEFAULT NULL,
        sponsorship_level varchar(100) DEFAULT NULL,

        -- Registration
        company_code varchar(50) NOT NULL COMMENT 'Format: COMP-XXXX-XXXX',
        qr_data longtext COMMENT 'JSON encoded QR code data',

        -- Payment
        payment_status enum('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
        payment_method varchar(50) DEFAULT NULL,
        payment_id bigint(20) UNSIGNED DEFAULT NULL,
        amount_paid decimal(10,2) DEFAULT 0.00,

        -- Check-in
        checked_in tinyint(1) DEFAULT 0,
        checked_in_at datetime DEFAULT NULL,
        checked_in_by bigint(20) UNSIGNED DEFAULT NULL,

        -- Custom Fields
        extra_fields longtext COMMENT 'JSON',
        social_media longtext COMMENT 'JSON array of social media links',
        products longtext COMMENT 'JSON array of products',

        -- Meta
        status enum('active', 'cancelled') DEFAULT 'active',
        notes text,

        -- Communication
        email_sent tinyint(1) DEFAULT 0,
        email_sent_at datetime DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY company_code (company_code),
        KEY event_id (event_id),
        KEY contact_email (contact_email),
        KEY payment_status (payment_status),
        KEY checked_in (checked_in),
        KEY status (status),
        KEY event_status (event_id, status)
    ) $charset_collate;";

    dbDelta($sql_company_attendees);

    // ========================================
    // 18. COMPANY CHECK-INS LOG TABLE
    // ========================================
    $sql_company_checkins = "CREATE TABLE {$tables['company_checkins']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        company_attendee_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,

        action enum('check_in', 'check_out') NOT NULL,
        method varchar(50) DEFAULT 'manual' COMMENT 'qr, manual',

        scanned_by bigint(20) UNSIGNED DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        user_agent text,

        notes text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY company_attendee_id (company_attendee_id),
        KEY event_id (event_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql_company_checkins);

    // ========================================
    // 19. CONVERSATIONS TABLE (Chat System)
    // ========================================
    $sql_conversations = "CREATE TABLE {$tables['conversations']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        event_id bigint(20) UNSIGNED NOT NULL COMMENT 'The event this conversation belongs to',
        visitor_token varchar(64) NOT NULL COMMENT 'Unique identifier for the visitor',
        user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID if logged in',

        -- Visitor Information
        visitor_name varchar(100) DEFAULT NULL,
        visitor_email varchar(255) DEFAULT NULL,
        visitor_phone varchar(50) DEFAULT NULL,

        -- Status
        status enum('active', 'closed', 'archived') DEFAULT 'active',

        -- Unread Counters
        unread_organizer int(11) DEFAULT 0 COMMENT 'Unread messages for organizer',
        unread_visitor int(11) DEFAULT 0 COMMENT 'Unread messages for visitor',

        -- Timestamps
        last_message_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY visitor_token (visitor_token),
        KEY user_id (user_id),
        KEY status (status),
        KEY last_message_at (last_message_at),
        KEY event_visitor (event_id, visitor_token)
    ) $charset_collate;";

    dbDelta($sql_conversations);

    // ========================================
    // 20. CHAT MESSAGES TABLE
    // ========================================
    $sql_chat_messages = "CREATE TABLE {$tables['chat_messages']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        conversation_id bigint(20) UNSIGNED NOT NULL,
        sender_type enum('visitor', 'organizer') NOT NULL,
        sender_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID for organizer, NULL for visitor',

        -- Message Content
        message text NOT NULL,
        message_type enum('text', 'image', 'file') DEFAULT 'text',
        attachment_url varchar(500) DEFAULT NULL,

        -- Status
        is_read tinyint(1) DEFAULT 0,
        read_at datetime DEFAULT NULL,

        -- Timestamps
        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY conversation_id (conversation_id),
        KEY sender_type (sender_type),
        KEY is_read (is_read),
        KEY created_at (created_at),
        KEY conversation_created (conversation_id, created_at)
    ) $charset_collate;";

    dbDelta($sql_chat_messages);

    // ========================================
    // 21. BOOTH TYPES TABLE (Exhibition Booth Sizes/Packages)
    // ========================================
    $sql_booth_types = "CREATE TABLE {$tables['booth_types']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,

        -- Basic Info
        name varchar(255) NOT NULL,
        name_ar varchar(255) DEFAULT NULL,
        slug varchar(100) NOT NULL,
        description text,
        description_ar text,

        -- Dimensions
        size_code varchar(50) NOT NULL COMMENT 'e.g., 3x3, 6x6, 9x9',
        width_meters decimal(5,2) NOT NULL DEFAULT 3.00,
        depth_meters decimal(5,2) NOT NULL DEFAULT 3.00,
        area_sqm decimal(8,2) GENERATED ALWAYS AS (width_meters * depth_meters) STORED,

        -- Category
        booth_category enum('standard', 'corner', 'island', 'peninsula', 'inline', 'custom') DEFAULT 'standard',

        -- Pricing
        base_price decimal(12,2) NOT NULL DEFAULT 0.00,
        deposit_amount decimal(12,2) DEFAULT 0.00 COMMENT 'Required deposit',
        deposit_percentage decimal(5,2) DEFAULT 0.00 COMMENT 'Deposit as percentage of base_price',
        price_per_sqm decimal(10,2) DEFAULT 0.00 COMMENT 'Alternative pricing by square meter',

        -- Package Inclusions (JSON array)
        inclusions longtext COMMENT 'JSON: furniture, signage, electricity, etc.',

        -- Availability
        total_quantity int(11) NOT NULL DEFAULT 0 COMMENT 'Total booths of this type',
        available_quantity int(11) NOT NULL DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,

        -- Visual
        color varchar(7) DEFAULT '#3B82F6',
        icon varchar(50) DEFAULT 'fa-store',
        image bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for booth image',

        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY event_slug (event_id, slug),
        KEY event_id (event_id),
        KEY booth_category (booth_category),
        KEY is_active (is_active),
        KEY size_code (size_code)
    ) $charset_collate;";

    dbDelta($sql_booth_types);

    // ========================================
    // 30. BOOTHS TABLE (Individual Exhibition Booths)
    // ========================================
    $sql_booths = "CREATE TABLE {$tables['booths']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,
        venue_id bigint(20) UNSIGNED DEFAULT NULL,
        zone_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Exhibition zone in venue',
        booth_type_id bigint(20) UNSIGNED NOT NULL,

        -- Identification
        booth_number varchar(50) NOT NULL COMMENT 'e.g., A1, B12, C5',
        booth_name varchar(255) DEFAULT NULL,
        booth_name_ar varchar(255) DEFAULT NULL,

        -- Location on Floor Plan
        position_x int(11) DEFAULT 0,
        position_y int(11) DEFAULT 0,
        rotation int(11) DEFAULT 0 COMMENT 'Rotation in degrees',
        floor_level int(11) DEFAULT 1,

        -- Custom Dimensions (override booth_type if set)
        custom_width decimal(5,2) DEFAULT NULL,
        custom_depth decimal(5,2) DEFAULT NULL,

        -- Pricing (override booth_type if set)
        custom_price decimal(12,2) DEFAULT NULL,

        -- Status
        status enum('available', 'reserved', 'booked', 'occupied', 'unavailable') DEFAULT 'available',
        is_featured tinyint(1) DEFAULT 0 COMMENT 'Featured/premium location',

        -- Current Occupant (quick reference)
        current_company_id bigint(20) UNSIGNED DEFAULT NULL,
        current_booking_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Amenities & Features
        has_electricity tinyint(1) DEFAULT 1,
        has_water tinyint(1) DEFAULT 0,
        has_wifi tinyint(1) DEFAULT 1,
        power_outlets int(11) DEFAULT 2,
        max_power_kw decimal(5,2) DEFAULT 3.00,
        special_features longtext COMMENT 'JSON array of special features',

        -- Neighbor booths (for corner premium calculation)
        neighbors longtext COMMENT 'JSON: adjacent booth IDs',

        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY event_booth_number (event_id, booth_number),
        KEY event_id (event_id),
        KEY venue_id (venue_id),
        KEY zone_id (zone_id),
        KEY booth_type_id (booth_type_id),
        KEY status (status),
        KEY current_company_id (current_company_id),
        KEY floor_level (floor_level)
    ) $charset_collate;";

    dbDelta($sql_booths);

    // ========================================
    // 31. BOOTH BOOKINGS TABLE (Reservations & Contracts)
    // ========================================
    $sql_booth_bookings = "CREATE TABLE {$tables['booth_bookings']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,
        booth_id bigint(20) UNSIGNED NOT NULL,
        company_attendee_id bigint(20) UNSIGNED NOT NULL,

        -- Booking Reference
        booking_ref varchar(50) NOT NULL COMMENT 'Format: BK-XXXXXX',

        -- Booking Period
        booking_date datetime NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,

        -- Pricing
        base_price decimal(12,2) NOT NULL DEFAULT 0.00,
        extras_price decimal(12,2) DEFAULT 0.00 COMMENT 'Additional services',
        discount_amount decimal(12,2) DEFAULT 0.00,
        tax_amount decimal(12,2) DEFAULT 0.00,
        total_amount decimal(12,2) NOT NULL DEFAULT 0.00,

        -- Payment Split (Deposit System)
        deposit_required decimal(12,2) DEFAULT 0.00,
        deposit_paid decimal(12,2) DEFAULT 0.00,
        deposit_paid_at datetime DEFAULT NULL,
        deposit_payment_id bigint(20) UNSIGNED DEFAULT NULL,
        balance_due decimal(12,2) DEFAULT 0.00,
        balance_paid decimal(12,2) DEFAULT 0.00,
        balance_paid_at datetime DEFAULT NULL,
        balance_payment_id bigint(20) UNSIGNED DEFAULT NULL,
        balance_due_date date DEFAULT NULL,

        -- Payment Status
        payment_status enum('pending', 'deposit_paid', 'fully_paid', 'overdue', 'refunded', 'cancelled') DEFAULT 'pending',

        -- Contract
        contract_signed tinyint(1) DEFAULT 0,
        contract_signed_at datetime DEFAULT NULL,
        contract_file bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for signed contract',
        contract_signer_name varchar(255) DEFAULT NULL,
        contract_signer_title varchar(100) DEFAULT NULL,
        terms_accepted tinyint(1) DEFAULT 0,
        terms_accepted_at datetime DEFAULT NULL,

        -- Commercial Registry (Saudi requirement)
        commercial_registry_no varchar(50) DEFAULT NULL,
        vat_number varchar(50) DEFAULT NULL,
        company_legal_name varchar(255) DEFAULT NULL,

        -- ZATCA Invoice (Saudi e-invoicing)
        zatca_invoice_id varchar(100) DEFAULT NULL,
        zatca_invoice_uuid varchar(100) DEFAULT NULL,
        zatca_invoice_hash varchar(255) DEFAULT NULL,
        zatca_qr_code longtext,

        -- Extras & Add-ons
        selected_extras longtext COMMENT 'JSON array of selected extras',
        special_requests text,

        -- Booking Status
        status enum('pending', 'confirmed', 'active', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
        confirmed_at datetime DEFAULT NULL,
        confirmed_by bigint(20) UNSIGNED DEFAULT NULL,
        cancelled_at datetime DEFAULT NULL,
        cancelled_by bigint(20) UNSIGNED DEFAULT NULL,
        cancellation_reason text,

        -- Check-in/out
        setup_date date DEFAULT NULL COMMENT 'Booth setup date before event',
        teardown_date date DEFAULT NULL COMMENT 'Booth teardown date after event',
        checked_in tinyint(1) DEFAULT 0,
        checked_in_at datetime DEFAULT NULL,
        checked_out tinyint(1) DEFAULT 0,
        checked_out_at datetime DEFAULT NULL,

        notes text,
        internal_notes text COMMENT 'Staff only notes',

        created_by bigint(20) UNSIGNED DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY booking_ref (booking_ref),
        KEY event_id (event_id),
        KEY booth_id (booth_id),
        KEY company_attendee_id (company_attendee_id),
        KEY payment_status (payment_status),
        KEY status (status),
        KEY start_date (start_date),
        KEY end_date (end_date),
        KEY commercial_registry_no (commercial_registry_no),
        KEY zatca_invoice_id (zatca_invoice_id)
    ) $charset_collate;";

    dbDelta($sql_booth_bookings);

    // ========================================
    // 32. BOOTH VISITS TABLE (Visitor Statistics)
    // ========================================
    $sql_booth_visits = "CREATE TABLE {$tables['booth_visits']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,
        booth_id bigint(20) UNSIGNED NOT NULL,
        booking_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Visitor Info
        attendee_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Individual attendee if tracked',
        visitor_type enum('attendee', 'anonymous', 'vip', 'buyer', 'press') DEFAULT 'attendee',

        -- Visit Details
        visit_date date NOT NULL,
        check_in_time datetime NOT NULL,
        check_out_time datetime DEFAULT NULL,
        duration_minutes int(11) DEFAULT NULL COMMENT 'Calculated on checkout',

        -- Interaction
        interaction_type enum('browse', 'inquiry', 'meeting', 'demo', 'purchase') DEFAULT 'browse',
        interest_level enum('low', 'medium', 'high', 'hot_lead') DEFAULT 'medium',
        notes text,

        -- Lead Capture
        lead_captured tinyint(1) DEFAULT 0,
        lead_data longtext COMMENT 'JSON: captured lead information',

        -- Scan Method
        scan_method enum('qr', 'nfc', 'manual', 'badge') DEFAULT 'qr',
        scanned_by bigint(20) UNSIGNED DEFAULT NULL,
        device_id varchar(100) DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY booth_id (booth_id),
        KEY booking_id (booking_id),
        KEY attendee_id (attendee_id),
        KEY visit_date (visit_date),
        KEY check_in_time (check_in_time),
        KEY booth_date (booth_id, visit_date)
    ) $charset_collate;";

    dbDelta($sql_booth_visits);

    // ========================================
    // 33. INVOICES TABLE (ZATCA E-Invoicing)
    // ========================================
    $sql_invoices = "CREATE TABLE {$tables['invoices']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        invoice_number varchar(50) NOT NULL,
        order_id bigint(20) UNSIGNED DEFAULT NULL,
        event_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Customer Info
        customer_name varchar(255) NOT NULL,
        customer_email varchar(255) DEFAULT NULL,
        customer_phone varchar(50) DEFAULT NULL,
        customer_vat varchar(50) DEFAULT NULL,

        -- Amounts
        subtotal decimal(12, 2) NOT NULL DEFAULT 0.00,
        vat_rate decimal(5, 2) DEFAULT 15.00,
        vat_amount decimal(12, 2) NOT NULL DEFAULT 0.00,
        total decimal(12, 2) NOT NULL DEFAULT 0.00,
        currency varchar(3) DEFAULT 'SAR',

        -- Items
        items longtext COMMENT 'JSON array of invoice items',

        -- ZATCA QR
        qr_code varchar(500) DEFAULT NULL,
        qr_data text,

        -- Files
        pdf_url varchar(500) DEFAULT NULL,

        -- Status
        status enum('draft', 'issued', 'paid', 'cancelled') DEFAULT 'issued',
        issued_at datetime DEFAULT NULL,
        cancelled_at datetime DEFAULT NULL,
        cancel_reason text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY invoice_number (invoice_number),
        KEY order_id (order_id),
        KEY event_id (event_id),
        KEY status (status),
        KEY issued_at (issued_at)
    ) $charset_collate;";

    dbDelta($sql_invoices);

    // ========================================
    // 42. SMS LOGS TABLE
    // ========================================
    $sql_sms_logs = "CREATE TABLE {$tables['sms_logs']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        phone varchar(50) NOT NULL,
        message text NOT NULL,
        provider varchar(50) NOT NULL,

        status enum('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
        message_id varchar(255) DEFAULT NULL,
        error text,

        event_id bigint(20) UNSIGNED DEFAULT NULL,
        attendee_id bigint(20) UNSIGNED DEFAULT NULL,
        type varchar(50) DEFAULT 'general',

        sent_by bigint(20) UNSIGNED DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY phone (phone),
        KEY provider (provider),
        KEY status (status),
        KEY event_id (event_id),
        KEY type (type),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql_sms_logs);

    // ========================================
    // 35. MODULE SETTINGS TABLE (Module System)
    // ========================================
    $sql_module_settings = "CREATE TABLE {$tables['module_settings']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        module_id varchar(100) NOT NULL COMMENT 'Unique module identifier',

        -- Status
        is_enabled tinyint(1) DEFAULT 1 COMMENT 'Whether module is enabled',

        -- Settings
        settings longtext COMMENT 'JSON: Module-specific settings',

        -- Metadata
        enabled_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User who enabled/disabled',
        enabled_at datetime DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY module_id (module_id),
        KEY is_enabled (is_enabled)
    ) $charset_collate;";

    dbDelta($sql_module_settings);

    // ========================================
    // 36. SESSIONS TABLE
    // ========================================
    $sql_sessions = "CREATE TABLE {$tables['sessions']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,

        title varchar(255) NOT NULL,
        description text,

        -- Type & Track
        session_type enum('lecture','workshop','panel','keynote','break','networking','exhibition','poster','symposium','hands_on','other') DEFAULT 'lecture',
        track varchar(100) DEFAULT NULL,

        -- Date & Time
        session_date date NOT NULL,
        start_time datetime NOT NULL,
        end_time datetime DEFAULT NULL,
        duration_minutes int(11) DEFAULT NULL,

        -- Location
        hall_name varchar(255) DEFAULT NULL,
        venue_id bigint(20) UNSIGNED DEFAULT NULL,

        -- Capacity
        capacity int(11) DEFAULT 0,
        registered_count int(11) DEFAULT 0,
        attended_count int(11) DEFAULT 0,

        -- CME (Continuing Medical Education)
        cme_hours decimal(5,2) DEFAULT 0.00,
        cme_category varchar(100) DEFAULT NULL,

        -- Certificate
        enable_certificate tinyint(1) DEFAULT 0,
        certificate_template_id bigint(20) UNSIGNED DEFAULT NULL,
        min_attendance_percentage decimal(5,2) DEFAULT 80.00,

        -- Speakers (cached count)
        speakers_count int(11) DEFAULT 0,

        -- Status
        status enum('draft','published','live','ended','cancelled') DEFAULT 'draft',
        is_published tinyint(1) DEFAULT 0,

        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY session_date (session_date),
        KEY status (status),
        KEY event_date (event_id, session_date, is_published),
        KEY event_track (event_id, track, is_published)
    ) $charset_collate;";

    dbDelta($sql_sessions);

    // ========================================
    // 37. SESSION SPEAKERS TABLE
    // ========================================
    $sql_session_speakers = "CREATE TABLE {$tables['session_speakers']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id bigint(20) UNSIGNED NOT NULL,
        speaker_id bigint(20) UNSIGNED NOT NULL,

        role varchar(100) DEFAULT 'speaker' COMMENT 'speaker, moderator, panelist, keynote',
        presentation_title varchar(255) DEFAULT NULL,
        sort_order int(11) DEFAULT 0,

        PRIMARY KEY (id),
        UNIQUE KEY session_speaker (session_id, speaker_id),
        KEY speaker_id (speaker_id)
    ) $charset_collate;";

    dbDelta($sql_session_speakers);

    // ========================================
    // 38. SESSION REGISTRATIONS TABLE
    // ========================================
    $sql_session_registrations = "CREATE TABLE {$tables['session_registrations']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id bigint(20) UNSIGNED NOT NULL,
        attendee_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,

        registration_code varchar(50) NOT NULL COMMENT 'Unique per-session ticket code',
        qr_code longtext COMMENT 'JSON: QR data for this session',

        status enum('registered','waitlisted','cancelled') DEFAULT 'registered',
        waitlist_position int(11) DEFAULT NULL,

        registered_at datetime DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY registration_code (registration_code),
        UNIQUE KEY session_attendee (session_id, attendee_id),
        KEY attendee_id (attendee_id),
        KEY event_id (event_id),
        KEY status (status)
    ) $charset_collate;";

    dbDelta($sql_session_registrations);

    // ========================================
    // 39. SESSION ATTENDANCE TABLE
    // ========================================
    $sql_session_attendance = "CREATE TABLE {$tables['session_attendance']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id bigint(20) UNSIGNED NOT NULL,
        attendee_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,

        -- Check-in/out
        check_in_time datetime DEFAULT NULL,
        check_out_time datetime DEFAULT NULL,

        -- Attendance calculation
        attendance_minutes int(11) DEFAULT 0,
        attendance_percentage decimal(5,2) DEFAULT 0.00,

        -- CME
        earned_cme_hours decimal(5,2) DEFAULT 0.00,

        -- Scan info
        checked_in_by bigint(20) UNSIGNED DEFAULT NULL,
        scan_method varchar(50) DEFAULT 'qr' COMMENT 'qr, manual, app',
        device_info varchar(255) DEFAULT NULL,

        -- Certificate
        certificate_eligible tinyint(1) DEFAULT 0,
        certificate_issued tinyint(1) DEFAULT 0,

        notes text,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY session_attendee (session_id, attendee_id),
        KEY attendee_id (attendee_id),
        KEY event_id (event_id),
        KEY check_in_time (check_in_time),
        KEY session_checkin (session_id, check_in_time)
    ) $charset_collate;";

    dbDelta($sql_session_attendance);

    // ========================================
    // SCANNER PERMISSIONS TABLE
    // ========================================
    $sql_scanner_permissions = "CREATE TABLE {$tables['scanner_permissions']} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        access_type varchar(10) NOT NULL DEFAULT 'full',
        event_id bigint(20) UNSIGNED DEFAULT NULL,
        session_id bigint(20) UNSIGNED DEFAULT NULL,
        created_by bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY event_id (event_id),
        KEY session_id (session_id)
    ) $charset_collate;";

    dbDelta($sql_scanner_permissions);

    // Update database version
    update_option('sc_db_version', SC_DB_VERSION);

    return true;
}

/**
 * Check if tables exist
 */
function sc_tables_exist() {
    global $wpdb;
    $tables = sc_get_table_names();

    $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tables['events']));
    return !empty($result);
}

/**
 * Drop all custom tables (for development/reset only)
 */
function sc_drop_tables() {
    global $wpdb;
    $tables = sc_get_table_names();

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    delete_option('sc_db_version');
}

/**
 * Ensure venue_image column exists in events table
 * Runs independently of SC_DB_VERSION
 */
function sc_ensure_venue_image_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_events';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'venue_image'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN venue_image bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for venue photo' AFTER venue_lng");
    }
}

/**
 * Initialize database on theme activation
 */
function sc_init_database() {
    $installed_version = get_option('sc_db_version', '0');

    if (version_compare($installed_version, SC_DB_VERSION, '<')) {
        sc_create_tables();
        sc_run_migrations();
    }

    // Ensure all standalone tables exist (safe - each checks SHOW TABLES before creating)
    sc_ensure_scanner_permissions_table();
    sc_ensure_sponsors_tables();
    sc_ensure_partners_tables();
    sc_ensure_favorites_table();
    sc_ensure_halls_table();
    sc_ensure_schedules_table();
    sc_ensure_schedule_favorites_table();

    // Ensure venue_image column exists in events table
    sc_ensure_venue_image_column();
}
add_action('after_switch_theme', 'sc_init_database');
add_action('admin_init', 'sc_init_database');
add_action('init', 'sc_init_database', 5); // Run early on all pages for dashboard

/**
 * Create scanner_permissions table if it doesn't exist
 * This runs independently of SC_DB_VERSION to avoid full dbDelta re-run
 */
function sc_ensure_scanner_permissions_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_scanner_permissions';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return; // Already exists
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        access_type varchar(10) NOT NULL DEFAULT 'full',
        event_id bigint(20) UNSIGNED DEFAULT NULL,
        session_id bigint(20) UNSIGNED DEFAULT NULL,
        created_by bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY event_id (event_id),
        KEY session_id (session_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create sponsors tables if they don't exist
 * Runs independently of SC_DB_VERSION to avoid full dbDelta re-run
 */
function sc_ensure_sponsors_tables() {
    global $wpdb;
    $sponsors_table = $wpdb->prefix . 'sc_sponsors';
    $pivot_table = $wpdb->prefix . 'sc_event_sponsors';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create main sponsors table if missing
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sponsors_table)) !== $sponsors_table) {
        $sql_sponsors = "CREATE TABLE $sponsors_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            logo bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID',
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            tier varchar(20) DEFAULT 'bronze' COMMENT 'platinum, gold, silver, bronze',
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY tier (tier),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        dbDelta($sql_sponsors);
    }

    // Ensure email/phone columns exist (may be missing on tables created before these were added)
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sponsors_table)) === $sponsors_table) {
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $sponsors_table");
        if (!in_array('email', $columns)) {
            $wpdb->query("ALTER TABLE $sponsors_table ADD COLUMN email varchar(255) DEFAULT NULL AFTER logo");
        }
        if (!in_array('phone', $columns)) {
            $wpdb->query("ALTER TABLE $sponsors_table ADD COLUMN phone varchar(50) DEFAULT NULL AFTER email");
        }
        // Set NULL values to empty string for display consistency
        $wpdb->query("UPDATE $sponsors_table SET email = '' WHERE email IS NULL");
        $wpdb->query("UPDATE $sponsors_table SET phone = '' WHERE phone IS NULL");
    }

    // Create pivot table if missing (check separately!)
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pivot_table)) !== $pivot_table) {
        $sql_pivot = "CREATE TABLE $pivot_table (
            event_id bigint(20) UNSIGNED NOT NULL,
            sponsor_id bigint(20) UNSIGNED NOT NULL,
            tier_override varchar(20) DEFAULT NULL COMMENT 'Override sponsor tier for this event',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (event_id, sponsor_id),
            KEY sponsor_id (sponsor_id)
        ) $charset_collate;";
        dbDelta($sql_pivot);
    }
}

/**
 * Create Partners tables (standalone - safe to call anytime)
 */
function sc_ensure_partners_tables() {
    global $wpdb;
    $partners_table = $wpdb->prefix . 'sc_partners';
    $pivot_table = $wpdb->prefix . 'sc_event_partners';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create main partners table if missing
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $partners_table)) !== $partners_table) {
        $sql_partners = "CREATE TABLE $partners_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            logo bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID',
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            tier varchar(20) DEFAULT 'bronze' COMMENT 'platinum, gold, silver, bronze',
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY tier (tier),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        dbDelta($sql_partners);
    }

    // Create pivot table if missing (check separately!)
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pivot_table)) !== $pivot_table) {
        $sql_pivot = "CREATE TABLE $pivot_table (
            event_id bigint(20) UNSIGNED NOT NULL,
            partner_id bigint(20) UNSIGNED NOT NULL,
            tier_override varchar(20) DEFAULT NULL COMMENT 'Override partner tier for this event',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (event_id, partner_id),
            KEY partner_id (partner_id)
        ) $charset_collate;";
        dbDelta($sql_pivot);
    }
}

/**
 * Create Favorites table (standalone - safe to call anytime)
 */
function sc_ensure_favorites_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_favorites';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        event_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_event (user_id, event_id),
        KEY event_id (event_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create Halls table (standalone - safe to call anytime)
 */
function sc_ensure_halls_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_halls';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text DEFAULT NULL,
        image bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID',
        capacity int(11) DEFAULT NULL,
        location varchar(255) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY is_active (is_active),
        KEY sort_order (sort_order)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create Schedules table (standalone - safe to call anytime)
 */
function sc_ensure_schedules_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id bigint(20) UNSIGNED NOT NULL,
        hall_id bigint(20) UNSIGNED DEFAULT NULL,
        title varchar(255) NOT NULL,
        description text DEFAULT NULL,
        speaker_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'From sc_speakers',
        speaker_name varchar(255) DEFAULT NULL COMMENT 'Manual name if no speaker_id',
        schedule_date date NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        sort_order int(11) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY event_id (event_id),
        KEY hall_id (hall_id),
        KEY speaker_id (speaker_id),
        KEY schedule_date (schedule_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create Schedule Favorites table (standalone - safe to call anytime)
 */
function sc_ensure_schedule_favorites_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedule_favorites';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        schedule_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_schedule (user_id, schedule_id),
        KEY schedule_id (schedule_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Run database migrations to add missing columns
 */
function sc_run_migrations() {
    global $wpdb;
    $tables = sc_get_table_names();

    // Add schedules_file column to events table if missing
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['events']} LIKE 'schedules_file'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['events']} ADD COLUMN schedules_file bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for PDF schedule file' AFTER extra_fields");
    }

    // Add role column to event_organizers table if missing
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['event_organizers']} LIKE 'role'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['event_organizers']} ADD COLUMN role varchar(100) DEFAULT 'organizer' COMMENT 'Role: organizer, sponsor, etc.' AFTER organizer_id");
    }

    // Migration 1.4.0: Add visual builder columns to certificate_templates
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['certificate_templates']} LIKE 'design_mode'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['certificate_templates']} ADD COLUMN design_mode enum('classic', 'visual') DEFAULT 'classic' AFTER description");
        $wpdb->query("ALTER TABLE {$tables['certificate_templates']} ADD COLUMN elements_config longtext COMMENT 'JSON: element positions for visual builder' AFTER css_styles");
    }

    // Migration 1.4.1: Add idempotency_key to certificates table to prevent duplicate issuance
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['certificates']} LIKE 'idempotency_key'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['certificates']} ADD COLUMN idempotency_key varchar(64) DEFAULT NULL COMMENT 'Unique key to prevent duplicate certificate issuance' AFTER verification_code");
        $wpdb->query("ALTER TABLE {$tables['certificates']} ADD UNIQUE KEY idempotency_key (idempotency_key)");
    }

    // Migration 1.4.2: Add social_media and products columns to company_attendees table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['company_attendees']} LIKE 'social_media'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['company_attendees']} ADD COLUMN social_media longtext COMMENT 'JSON array of social media links' AFTER extra_fields");
        $wpdb->query("ALTER TABLE {$tables['company_attendees']} ADD COLUMN products longtext COMMENT 'JSON array of products' AFTER social_media");
    }

    // Migration 1.4.2: Make contact_name nullable in company_attendees (only run once)
    $migration_key = 'sc_migration_1_4_2_company_attendees';
    if (!get_option($migration_key)) {
        // Update any NULL contact_phone values to empty string before making it NOT NULL
        $wpdb->query("UPDATE {$tables['company_attendees']} SET contact_phone = '' WHERE contact_phone IS NULL");
        $wpdb->query("ALTER TABLE {$tables['company_attendees']} MODIFY contact_name varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$tables['company_attendees']} MODIFY contact_phone varchar(50) NOT NULL DEFAULT ''");
        update_option($migration_key, true);
    }

    // Initialize default payment gateways if table is empty
    $gateway_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['payment_gateways']}");
    if ($gateway_count == 0) {
        sc_insert_default_payment_gateways();
    }

    // Migration 1.4.3: Optimize chat tables with additional indexes
    $migration_key = 'sc_migration_1_4_3_chat_indexes';
    if (!get_option($migration_key)) {
        // Add file_url and file_name columns to chat_messages if missing
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['chat_messages']} LIKE 'file_url'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$tables['chat_messages']} ADD COLUMN file_url varchar(500) DEFAULT NULL AFTER attachment_url");
            $wpdb->query("ALTER TABLE {$tables['chat_messages']} ADD COLUMN file_name varchar(255) DEFAULT NULL AFTER file_url");
        }

        // Add composite index for unread message queries
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$tables['chat_messages']} WHERE Key_name = 'conv_sender_read'");
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE {$tables['chat_messages']} ADD INDEX conv_sender_read (conversation_id, sender_type, is_read)");
        }

        // Add index for visitor email lookup
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$tables['conversations']} WHERE Key_name = 'visitor_email'");
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE {$tables['conversations']} ADD INDEX visitor_email (visitor_email)");
        }

        // Add composite index for dashboard queries (status + unread)
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$tables['conversations']} WHERE Key_name = 'status_unread'");
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE {$tables['conversations']} ADD INDEX status_unread (status, unread_organizer)");
        }

        update_option($migration_key, true);
    }

    // Migration 1.6.2: Add certificate_require_event_ended column to events table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['events']} LIKE 'certificate_require_event_ended'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['events']} ADD COLUMN certificate_require_event_ended tinyint(1) DEFAULT 0 COMMENT 'Only show certificate after event ended' AFTER certificate_require_checkout");
    }

    // Migration 1.7.0: Add venue_image column to events table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['events']} LIKE 'venue_image'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['events']} ADD COLUMN venue_image bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for venue photo' AFTER venue_lng");
    }

    // Migration: Add google_maps_url column to events table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['events']} LIKE 'google_maps_url'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$tables['events']} ADD COLUMN google_maps_url varchar(2048) DEFAULT NULL COMMENT 'Google Maps share URL' AFTER venue_lng");
    }

    // Migration: Ensure paper_size column exists on certificate_templates (enum may fail with dbDelta)
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tables['certificate_templates'])) === $tables['certificate_templates']) {
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['certificate_templates']} LIKE 'paper_size'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$tables['certificate_templates']} ADD COLUMN paper_size varchar(20) DEFAULT 'A4' AFTER orientation");
        }
        // Ensure existing rows have a paper_size value
        $wpdb->query("UPDATE {$tables['certificate_templates']} SET paper_size = 'A4' WHERE paper_size IS NULL OR paper_size = ''");
    }

    // Ensure partners tables exist
    sc_ensure_partners_tables();

    // Ensure favorites table exists
    sc_ensure_favorites_table();
}

/**
 * Insert default payment gateway records
 */
function sc_insert_default_payment_gateways() {
    global $wpdb;
    $tables = sc_get_table_names();

    $gateways = array(
        array(
            'gateway_code' => 'paymob',
            'gateway_name' => 'Paymob',
            'is_enabled' => 0,
            'is_test_mode' => 1,
            'settings' => json_encode(array(
                'api_key' => '',
                'integration_id' => '',
                'iframe_id' => '',
                'hmac_secret' => '',
            )),
            'display_order' => 1,
        ),
        array(
            'gateway_code' => 'stripe',
            'gateway_name' => 'Stripe',
            'is_enabled' => 0,
            'is_test_mode' => 1,
            'settings' => json_encode(array(
                'publishable_key' => '',
                'secret_key' => '',
                'webhook_secret' => '',
            )),
            'display_order' => 2,
        ),
        array(
            'gateway_code' => 'myfatoorah',
            'gateway_name' => 'MyFatoorah',
            'is_enabled' => 0,
            'is_test_mode' => 1,
            'settings' => json_encode(array(
                'api_key' => '',
                'country_iso' => 'KWT',
            )),
            'display_order' => 3,
        ),
        array(
            'gateway_code' => 'kashier',
            'gateway_name' => 'Kashier',
            'is_enabled' => 0,
            'is_test_mode' => 1,
            'settings' => json_encode(array(
                'merchant_id' => '',
                'api_key' => '',
                'secret_key' => '',
            )),
            'display_order' => 4,
        ),
    );

    foreach ($gateways as $gateway) {
        $wpdb->insert($tables['payment_gateways'], $gateway);
    }
}

/**
 * Force run migrations (can be called manually)
 */
function sc_force_migrations() {
    delete_option('sc_db_version');
    sc_init_database();
}
