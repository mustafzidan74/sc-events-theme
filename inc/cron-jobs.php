<?php
/**
 * SC Events Cron Jobs
 *
 * Scheduled tasks for maintenance and automation
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom cron schedules
 */
add_filter('cron_schedules', function($schedules) {
    // Weekly schedule
    $schedules['sc_weekly'] = array(
        'interval' => WEEK_IN_SECONDS,
        'display'  => __('Once Weekly', 'sc_events'),
    );

    // Twice daily
    $schedules['sc_twicedaily'] = array(
        'interval' => 12 * HOUR_IN_SECONDS,
        'display'  => __('Twice Daily', 'sc_events'),
    );

    return $schedules;
});

/**
 * Schedule cron events on theme activation
 */
function sc_schedule_cron_events() {
    // Daily: Clean expired transients
    if (!wp_next_scheduled('sc_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'sc_daily_cleanup');
    }

    // Weekly: Optimize database tables
    if (!wp_next_scheduled('sc_weekly_optimization')) {
        wp_schedule_event(time(), 'sc_weekly', 'sc_weekly_optimization');
    }

    // Hourly: Update event statistics
    if (!wp_next_scheduled('sc_hourly_stats_update')) {
        wp_schedule_event(time(), 'hourly', 'sc_hourly_stats_update');
    }

    // Twice daily: Send reminder emails
    if (!wp_next_scheduled('sc_event_reminders')) {
        wp_schedule_event(time(), 'sc_twicedaily', 'sc_event_reminders');
    }

    // Daily: Mark past events as completed
    if (!wp_next_scheduled('sc_mark_completed_events')) {
        wp_schedule_event(time(), 'daily', 'sc_mark_completed_events');
    }
}
add_action('after_switch_theme', 'sc_schedule_cron_events');
add_action('admin_init', 'sc_schedule_cron_events');

/**
 * Unschedule cron events on theme deactivation
 */
function sc_unschedule_cron_events() {
    wp_clear_scheduled_hook('sc_daily_cleanup');
    wp_clear_scheduled_hook('sc_weekly_optimization');
    wp_clear_scheduled_hook('sc_hourly_stats_update');
    wp_clear_scheduled_hook('sc_event_reminders');
    wp_clear_scheduled_hook('sc_mark_completed_events');
}
add_action('switch_theme', 'sc_unschedule_cron_events');

// ========================================
// CRON JOB HANDLERS
// ========================================

/**
 * Daily cleanup task
 * - Clean expired transients
 * - Delete old logs
 * - Clean orphaned data
 */
add_action('sc_daily_cleanup', function() {
    global $wpdb;

    // Delete expired SC transients
    $wpdb->query(
        "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
        WHERE a.option_name LIKE '_transient_sc_%'
        AND a.option_name NOT LIKE '_transient_timeout_%'
        AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
        AND b.option_value < UNIX_TIMESTAMP()"
    );

    // Clean old check-in logs (older than 90 days)
    $tables = sc_get_table_names();
    if (isset($tables['checkins'])) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['checkins']} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }

    // Log cleanup completion
    update_option('sc_last_cleanup', current_time('mysql'));
});

/**
 * Weekly optimization task
 * - Optimize database tables
 * - Analyze tables for query optimization
 */
add_action('sc_weekly_optimization', function() {
    // Optimize tables
    if (function_exists('sc_optimize_tables')) {
        sc_optimize_tables();
    }

    // Analyze tables
    if (function_exists('sc_analyze_tables')) {
        sc_analyze_tables();
    }

    // Clear all caches
    if (class_exists('SC_Cache')) {
        SC_Cache::flush();
    }

    // Log optimization completion
    update_option('sc_last_optimization', current_time('mysql'));
});

/**
 * Hourly stats update
 * - Recalculate event statistics
 * - Update cached counts
 */
add_action('sc_hourly_stats_update', function() {
    global $wpdb;

    $tables = sc_get_table_names();

    // Update total_sold and total_revenue for each event
    $wpdb->query(
        "UPDATE {$tables['events']} e
        SET
            total_sold = (
                SELECT COUNT(*) FROM {$tables['attendees']} a
                WHERE a.event_id = e.id
                AND a.status = 'active'
                AND a.payment_status IN ('success', 'completed')
            ),
            total_revenue = (
                SELECT COALESCE(SUM(amount_paid), 0) FROM {$tables['attendees']} a
                WHERE a.event_id = e.id
                AND a.status = 'active'
                AND a.payment_status IN ('success', 'completed')
            ),
            total_checked_in = (
                SELECT COUNT(*) FROM {$tables['attendees']} a
                WHERE a.event_id = e.id
                AND a.status = 'active'
                AND a.checked_in = 1
            )
        WHERE e.status = 'publish'"
    );

    // Update ticket sold counts
    $wpdb->query(
        "UPDATE {$tables['tickets']} t
        SET sold = (
            SELECT COUNT(*) FROM {$tables['attendees']} a
            WHERE a.ticket_id = t.id
            AND a.status = 'active'
            AND a.payment_status IN ('success', 'completed')
        )"
    );

    // Clear dashboard cache
    if (class_exists('SC_Cache')) {
        SC_Cache::clear_dashboard_stats();
    }

    update_option('sc_last_stats_update', current_time('mysql'));
});

/**
 * Send event reminder emails
 * - Events starting in 24 hours
 * - Events starting in 1 hour
 */
add_action('sc_event_reminders', function() {
    global $wpdb;

    $tables = sc_get_table_names();

    // Get events starting in ~24 hours
    $tomorrow_start = date('Y-m-d H:i:s', strtotime('+23 hours'));
    $tomorrow_end = date('Y-m-d H:i:s', strtotime('+25 hours'));

    $upcoming_events = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, start_date, start_time, venue_name
        FROM {$tables['events']}
        WHERE status = 'publish'
        AND CONCAT(start_date, ' ', COALESCE(start_time, '00:00:00')) BETWEEN %s AND %s",
        $tomorrow_start,
        $tomorrow_end
    ));

    foreach ($upcoming_events as $event) {
        // Check if reminder already sent
        $reminder_sent = get_option('sc_reminder_sent_24h_' . $event->id);
        if ($reminder_sent) {
            continue;
        }

        // Get attendees for this event
        $attendees = $wpdb->get_results($wpdb->prepare(
            "SELECT email, name FROM {$tables['attendees']}
            WHERE event_id = %d
            AND status = 'active'
            AND payment_status IN ('success', 'completed')",
            $event->id
        ));

        foreach ($attendees as $attendee) {
            sc_send_event_reminder_email($attendee, $event, '24h');
        }

        // Mark reminder as sent
        update_option('sc_reminder_sent_24h_' . $event->id, time());
    }
});

/**
 * Send reminder email
 *
 * @param object $attendee Attendee data
 * @param object $event Event data
 * @param string $type Reminder type (24h, 1h)
 */
function sc_send_event_reminder_email($attendee, $event, $type = '24h') {
    $subject = sprintf(
        __('Reminder: %s is coming up!', 'sc_events'),
        $event->title
    );

    $event_datetime = $event->start_date;
    if ($event->start_time) {
        $event_datetime .= ' ' . date('H:i', strtotime($event->start_time));
    }

    $message = sprintf(
        __("Hi %s,\n\nThis is a friendly reminder that %s is coming up!\n\nDate & Time: %s\nLocation: %s\n\nWe look forward to seeing you there!\n\nBest regards,\n%s", 'sc_events'),
        $attendee->name,
        $event->title,
        $event_datetime,
        $event->venue_name ?: __('Online', 'sc_events'),
        get_bloginfo('name')
    );

    wp_mail($attendee->email, $subject, $message);
}

/**
 * Mark past events as completed
 */
add_action('sc_mark_completed_events', function() {
    global $wpdb;

    $tables = sc_get_table_names();

    // Mark events that ended as completed (handles NULL end_date for single-day events)
    $today = date('Y-m-d');
    $wpdb->query($wpdb->prepare(
        "UPDATE {$tables['events']}
        SET status = 'completed'
        WHERE status = 'publish'
        AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
        $today, $today
    ));
});

// ========================================
// ADMIN INTERFACE FOR CRON STATUS
// ========================================

/**
 * Add cron status to admin
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        __('SC Cron Status', 'sc_events'),
        __('SC Cron Jobs', 'sc_events'),
        'manage_options',
        'sc-cron-status',
        'sc_cron_status_page'
    );
});

/**
 * Cron status page
 */
function sc_cron_status_page() {
    // Handle manual run
    if (isset($_POST['sc_run_cron']) && check_admin_referer('sc_run_cron')) {
        $job = sanitize_text_field($_POST['sc_run_cron']);
        do_action($job);
        echo '<div class="notice notice-success"><p>' . sprintf(__('Ran: %s', 'sc_events'), $job) . '</p></div>';
    }

    $cron_jobs = array(
        'sc_daily_cleanup' => __('Daily Cleanup', 'sc_events'),
        'sc_weekly_optimization' => __('Weekly Optimization', 'sc_events'),
        'sc_hourly_stats_update' => __('Hourly Stats Update', 'sc_events'),
        'sc_event_reminders' => __('Event Reminders', 'sc_events'),
        'sc_mark_completed_events' => __('Mark Completed Events', 'sc_events'),
    );

    ?>
    <div class="wrap">
        <h1><?php _e('SC Events Cron Jobs', 'sc_events'); ?></h1>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Job', 'sc_events'); ?></th>
                    <th><?php _e('Next Run', 'sc_events'); ?></th>
                    <th><?php _e('Last Run', 'sc_events'); ?></th>
                    <th><?php _e('Action', 'sc_events'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cron_jobs as $hook => $name): ?>
                <tr>
                    <td><strong><?php echo esc_html($name); ?></strong></td>
                    <td>
                        <?php
                        $next = wp_next_scheduled($hook);
                        echo $next ? date_i18n('Y-m-d H:i:s', $next) : __('Not scheduled', 'sc_events');
                        ?>
                    </td>
                    <td>
                        <?php
                        $last_key = str_replace('sc_', 'sc_last_', $hook);
                        $last = get_option($last_key);
                        echo $last ?: __('Never', 'sc_events');
                        ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('sc_run_cron'); ?>
                            <input type="hidden" name="sc_run_cron" value="<?php echo esc_attr($hook); ?>">
                            <button type="submit" class="button button-small">
                                <?php _e('Run Now', 'sc_events'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php _e('Last Operations', 'sc_events'); ?></h2>
        <ul>
            <li><strong><?php _e('Last Cleanup:', 'sc_events'); ?></strong> <?php echo get_option('sc_last_cleanup', __('Never', 'sc_events')); ?></li>
            <li><strong><?php _e('Last Optimization:', 'sc_events'); ?></strong> <?php echo get_option('sc_last_optimization', __('Never', 'sc_events')); ?></li>
            <li><strong><?php _e('Last Stats Update:', 'sc_events'); ?></strong> <?php echo get_option('sc_last_stats_update', __('Never', 'sc_events')); ?></li>
        </ul>
    </div>
    <?php
}
