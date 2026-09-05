<?php
/**
 * SC Events Database Layer Loader
 *
 * Loads all database model classes and initializes the database
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load database schema and initialization
require_once __DIR__ . '/schema.php';

// Load base model class (must be loaded first)
require_once __DIR__ . '/class-sc-base-model.php';

// Load model classes in dependency order
require_once __DIR__ . '/class-sc-event.php';
require_once __DIR__ . '/class-sc-ticket.php';
require_once __DIR__ . '/class-sc-checkin.php';
require_once __DIR__ . '/class-sc-attendee.php';
require_once __DIR__ . '/class-sc-transaction.php';
require_once __DIR__ . '/class-sc-coupon.php';
require_once __DIR__ . '/class-sc-coupon-category.php';
require_once __DIR__ . '/class-sc-workshop.php';
require_once __DIR__ . '/class-sc-speaker.php';
require_once __DIR__ . '/class-sc-sponsor.php';
require_once __DIR__ . '/class-sc-partner.php';
require_once __DIR__ . '/class-sc-organizer.php';
require_once __DIR__ . '/class-sc-referral.php';
require_once __DIR__ . '/class-sc-user-points.php';
require_once __DIR__ . '/class-sc-certificate-template.php';
require_once __DIR__ . '/class-sc-certificate.php';
require_once __DIR__ . '/class-sc-company-attendee.php';
require_once __DIR__ . '/class-sc-session-attendance.php';

// Load chat system
require_once __DIR__ . '/class-sc-chat.php';

// Load bridge functions (works with both old and new systems)
require_once __DIR__ . '/bridge.php';

// Load performance indexes
require_once __DIR__ . '/indexes.php';

// Load caching system
require_once __DIR__ . '/cache.php';

// Load database optimizer
require_once __DIR__ . '/class-sc-database-optimizer.php';

// Load migration tool (admin only)
if (is_admin()) {
    require_once __DIR__ . '/migration.php';
}

/**
 * Initialize database tables on theme activation
 */
function sc_database_init() {
    // Check if tables exist
    if (!sc_tables_exist()) {
        sc_create_tables();
    }
}
add_action('after_switch_theme', 'sc_database_init');

/**
 * Check and create tables if needed (runs once per version)
 */
function sc_check_database_version() {
    $current_version = '1.0.0';
    $installed_version = get_option('sc_database_version', '0');

    if (version_compare($installed_version, $current_version, '<')) {
        sc_create_tables();
        update_option('sc_database_version', $current_version);
    }
}
add_action('init', 'sc_check_database_version', 1);

/**
 * Admin notice if tables don't exist
 */
function sc_database_admin_notice() {
    if (!sc_tables_exist() && current_user_can('manage_options')) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('SC Events:', 'sc_events'); ?></strong>
                <?php _e('Database tables are missing. Please deactivate and reactivate the theme, or click the button below to create them.', 'sc_events'); ?>
            </p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?action=sc_create_tables'), 'sc_create_tables'); ?>" class="button button-primary">
                    <?php _e('Create Database Tables', 'sc_events'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'sc_database_admin_notice');

/**
 * Handle manual table creation
 */
function sc_handle_create_tables() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'sc_events'));
    }

    check_admin_referer('sc_create_tables');

    sc_create_tables();

    wp_redirect(admin_url('?sc_tables_created=1'));
    exit;
}
add_action('admin_action_sc_create_tables', 'sc_handle_create_tables');

/**
 * Success notice after table creation
 */
function sc_tables_created_notice() {
    if (isset($_GET['sc_tables_created'])) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('SC Events database tables have been created successfully!', 'sc_events'); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'sc_tables_created_notice');

/**
 * Helper function to get all model classes
 *
 * @return array List of model class names
 */
function sc_get_model_classes() {
    return array(
        'SC_Event',
        'SC_Ticket',
        'SC_Attendee',
        'SC_Transaction',
        'SC_Coupon',
        'SC_Checkin',
        'SC_Speaker',
        'SC_Sponsor',
        'SC_Organizer',
        'SC_Referral',
        'SC_User_Points',
        'SC_Certificate_Template',
        'SC_Certificate',
        'SC_Company_Attendee',
    );
}

/**
 * Helper to check if we're using custom tables
 *
 * @return bool
 */
function sc_use_custom_tables() {
    return get_option('sc_use_custom_tables', false) && sc_tables_exist();
}

/**
 * Enable/disable custom tables
 *
 * @param bool $enable Whether to enable custom tables
 */
function sc_set_custom_tables($enable) {
    update_option('sc_use_custom_tables', (bool) $enable);
}
