<?php
/**
 * SC Events Data Migration Script
 *
 * Migrates data from Eventin (wp_posts + wp_postmeta) to custom SC tables
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Migration Class
 */
class SC_Data_Migration {

    /**
     * Migration status option key
     */
    const STATUS_OPTION = 'sc_migration_status';

    /**
     * Get migration status
     *
     * @return array
     */
    public static function get_status() {
        return get_option(self::STATUS_OPTION, array(
            'started'   => false,
            'completed' => false,
            'progress'  => array(),
            'errors'    => array(),
            'stats'     => array(),
        ));
    }

    /**
     * Update migration status
     *
     * @param array $data Status data to merge
     */
    public static function update_status($data) {
        $status = self::get_status();
        $status = array_merge($status, $data);
        update_option(self::STATUS_OPTION, $status);
    }

    /**
     * Run full migration
     *
     * @param bool $dry_run If true, don't actually migrate, just report what would happen
     * @return array Results
     */
    public static function run($dry_run = false) {
        global $wpdb;

        // Ensure tables exist
        if (!sc_tables_exist()) {
            sc_create_tables();
        }

        $results = array(
            'dry_run'  => $dry_run,
            'started'  => current_time('mysql'),
            'events'   => array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array()),
            'tickets'  => array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array()),
            'attendees'=> array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array()),
            'speakers' => array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array()),
        );

        self::update_status(array('started' => true, 'completed' => false));

        // Migrate in order of dependencies
        $results['events'] = self::migrate_events($dry_run);
        $results['tickets'] = self::migrate_tickets($dry_run);
        $results['attendees'] = self::migrate_attendees($dry_run);
        $results['speakers'] = self::migrate_speakers($dry_run);

        $results['completed'] = current_time('mysql');

        self::update_status(array(
            'completed' => true,
            'stats'     => $results,
        ));

        return $results;
    }

    /**
     * Migrate events from etn post type
     *
     * @param bool $dry_run
     * @return array
     */
    public static function migrate_events($dry_run = false) {
        global $wpdb;

        $result = array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array());
        $table = SC_Event::get_table();

        // Get all Eventin events
        $events = get_posts(array(
            'post_type'      => 'etn',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
        ));

        $result['total'] = count($events);

        foreach ($events as $event) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE wp_post_id = %d",
                $event->ID
            ));

            if ($exists) {
                $result['skipped']++;
                continue;
            }

            // Get event meta
            $meta = get_post_meta($event->ID);

            // Prepare event data
            $event_data = array(
                'wp_post_id'          => $event->ID,
                'title'               => $event->post_title,
                'slug'                => $event->post_name,
                'description'         => $event->post_content,
                'excerpt'             => $event->post_excerpt,
                'status'              => $event->post_status,
                'author_id'           => $event->post_author,
                'start_date'          => self::get_meta($meta, 'etn_start_date'),
                'end_date'            => self::get_meta($meta, 'etn_end_date'),
                'start_time'          => self::get_meta($meta, 'etn_start_time'),
                'end_time'            => self::get_meta($meta, 'etn_end_time'),
                'timezone'            => self::get_meta($meta, 'event_timezone'),
                'all_day_event'       => self::get_meta($meta, 'etn_all_day_event') ? 1 : 0,
                'venue_name'          => self::get_meta($meta, 'etn_event_location'),
                'venue_address'       => self::get_meta($meta, 'etn_event_address'),
                'location_type'       => self::get_meta($meta, 'etn_event_location_type', 'venue'),
                'meeting_link'        => self::get_meta($meta, 'etn_zoom_meeting_url') ?: self::get_meta($meta, 'etn_meeting_url'),
                'featured_image'      => get_post_thumbnail_id($event->ID),
                'logo_image'          => self::get_meta($meta, 'etn_event_logo'),
                'banner_image'        => self::get_meta($meta, 'etn_event_banner'),
                'total_capacity'      => intval(self::get_meta($meta, 'etn_total_seat', 0)),
                'total_sold'          => intval(self::get_meta($meta, 'etn_sold_tickets', 0)),
                'registration_deadline' => self::get_meta($meta, 'etn_registration_deadline'),
                'calendar_bg_color'   => self::get_meta($meta, 'etn_calendar_bg_color'),
                'calendar_text_color' => self::get_meta($meta, 'etn_calendar_text_color'),
                'faq'                 => self::get_meta($meta, 'etn_event_faq', array()),
                'schedule'            => self::get_meta($meta, 'etn_event_schedule', array()),
                'social_links'        => self::get_meta($meta, 'etn_event_socials', array()),
                'extra_fields'        => self::get_meta($meta, 'attendee_extra_fields', array()),
                'attendance_tracking' => self::get_meta($meta, 'etn_enable_attendance') ? 1 : 0,
            );

            // Convert JSON fields
            foreach (array('faq', 'schedule', 'social_links', 'extra_fields') as $field) {
                if (!is_array($event_data[$field])) {
                    $event_data[$field] = maybe_unserialize($event_data[$field]);
                }
            }

            if (!$dry_run) {
                $event_id = SC_Event::create($event_data);

                if ($event_id) {
                    // Store mapping
                    update_post_meta($event->ID, '_sc_event_id', $event_id);
                    $result['migrated']++;
                } else {
                    $result['errors'][] = "Failed to migrate event {$event->ID}: " . $wpdb->last_error;
                }
            } else {
                $result['migrated']++;
            }
        }

        return $result;
    }

    /**
     * Migrate tickets from event meta
     *
     * @param bool $dry_run
     * @return array
     */
    public static function migrate_tickets($dry_run = false) {
        global $wpdb;

        $result = array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array());
        $events_table = SC_Event::get_table();
        $tickets_table = SC_Ticket::get_table();

        // Get all events with their WP post IDs
        $events = $wpdb->get_results("SELECT id, wp_post_id FROM $events_table WHERE wp_post_id > 0");

        foreach ($events as $event) {
            // Get ticket variations from Eventin meta
            $ticket_variations = get_post_meta($event->wp_post_id, 'etn_ticket_variations', true);

            if (empty($ticket_variations) || !is_array($ticket_variations)) {
                continue;
            }

            foreach ($ticket_variations as $index => $ticket) {
                $result['total']++;

                // Check if ticket already exists for this event
                $ticket_name = isset($ticket['etn_ticket_name']) ? $ticket['etn_ticket_name'] : '';
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $tickets_table WHERE event_id = %d AND name = %s",
                    $event->id,
                    $ticket_name
                ));

                if ($exists) {
                    $result['skipped']++;
                    continue;
                }

                $ticket_data = array(
                    'event_id'        => $event->id,
                    'name'            => $ticket_name,
                    'slug'            => sanitize_title($ticket_name),
                    'description'     => isset($ticket['etn_ticket_description']) ? $ticket['etn_ticket_description'] : '',
                    'price'           => floatval(isset($ticket['etn_ticket_price']) ? $ticket['etn_ticket_price'] : 0),
                    'quantity'        => intval(isset($ticket['etn_ticket_qty']) ? $ticket['etn_ticket_qty'] : 0),
                    'sold'            => intval(isset($ticket['etn_sold_tickets']) ? $ticket['etn_sold_tickets'] : 0),
                    'min_per_order'   => intval(isset($ticket['etn_min_ticket']) ? $ticket['etn_min_ticket'] : 1),
                    'max_per_order'   => intval(isset($ticket['etn_max_ticket']) ? $ticket['etn_max_ticket'] : 10),
                    'is_active'       => 1,
                    'sort_order'      => $index,
                );

                if (!$dry_run) {
                    $ticket_id = SC_Ticket::create($ticket_data);

                    if ($ticket_id) {
                        $result['migrated']++;
                    } else {
                        $result['errors'][] = "Failed to migrate ticket for event {$event->id}: " . $wpdb->last_error;
                    }
                } else {
                    $result['migrated']++;
                }
            }
        }

        return $result;
    }

    /**
     * Migrate attendees from etn-attendee post type
     *
     * @param bool $dry_run
     * @return array
     */
    public static function migrate_attendees($dry_run = false) {
        global $wpdb;

        $result = array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array());
        $table = SC_Attendee::get_table();
        $events_table = SC_Event::get_table();
        $tickets_table = SC_Ticket::get_table();

        // Get all Eventin attendees
        $attendees = get_posts(array(
            'post_type'      => 'etn-attendee',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ));

        $result['total'] = count($attendees);

        foreach ($attendees as $attendee) {
            // Check if already migrated
            $wp_post_id = $attendee->ID;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE ticket_code = %s OR id = (SELECT id FROM $table t JOIN {$wpdb->postmeta} pm ON pm.meta_value = t.id WHERE pm.post_id = %d AND pm.meta_key = '_sc_attendee_id' LIMIT 1)",
                get_post_meta($wp_post_id, 'etn_unique_ticket_id', true),
                $wp_post_id
            ));

            if ($exists) {
                $result['skipped']++;
                continue;
            }

            // Get attendee meta
            $meta = get_post_meta($wp_post_id);

            // Get event ID from custom table
            $wp_event_id = self::get_meta($meta, 'etn_event_id');
            $sc_event_id = 0;
            if ($wp_event_id) {
                $sc_event_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $events_table WHERE wp_post_id = %d",
                    $wp_event_id
                ));
            }

            // Get ticket ID
            $ticket_name = self::get_meta($meta, 'etn_ticket_name');
            $sc_ticket_id = 0;
            if ($sc_event_id && $ticket_name) {
                $sc_ticket_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $tickets_table WHERE event_id = %d AND name = %s",
                    $sc_event_id,
                    $ticket_name
                ));
            }

            // Determine payment status
            $payment_status = 'pending';
            $etn_status = self::get_meta($meta, 'etn_status');
            $etn_info_status = self::get_meta($meta, 'etn_attendee_info_status');
            if ($etn_status === 'success' || $etn_info_status === 'success' || $etn_info_status === 'purchased') {
                $payment_status = 'success';
            } elseif ($etn_status === 'failed') {
                $payment_status = 'failed';
            }

            // Build full name
            $first_name = self::get_meta($meta, 'etn_name') ?: self::get_meta($meta, 'etn_first_name');
            $last_name = self::get_meta($meta, 'etn_last_name');
            $full_name = trim($first_name . ' ' . $last_name);
            if (empty($full_name)) {
                $full_name = 'Unknown';
            }

            // Get ticket name
            $ticket_name = self::get_meta($meta, 'etn_ticket_name', 'General');

            $attendee_data = array(
                'wp_post_id'      => $wp_post_id,
                'event_id'        => $sc_event_id,
                'ticket_id'       => $sc_ticket_id,
                'user_id'         => $attendee->post_author > 0 ? $attendee->post_author : null,
                'name'            => $full_name,
                'email'           => self::get_meta($meta, 'etn_email'),
                'phone'           => self::get_meta($meta, 'etn_phone'),
                'ticket_code'     => self::get_meta($meta, 'etn_unique_ticket_id') ?: SC_Attendee::generate_ticket_code(),
                'ticket_name'     => $ticket_name,
                'ticket_price'    => floatval(self::get_meta($meta, 'etn_ticket_price', 0)),
                'payment_status'  => $payment_status,
                'payment_method'  => self::get_meta($meta, 'etn_payment_method'),
                'amount_paid'     => floatval(self::get_meta($meta, 'etn_ticket_price', 0)),
                'checked_in'      => self::get_meta($meta, 'etn_attendee_checked_in') ? 1 : 0,
                'checked_in_at'   => self::get_meta($meta, 'etn_check_in_time') ?: null,
                'extra_fields'    => self::get_meta($meta, 'etn_attendee_extra_field', array()),
                'status'          => $attendee->post_status === 'publish' ? 'active' : 'cancelled',
            );

            // Handle extra fields
            if (!is_array($attendee_data['extra_fields'])) {
                $attendee_data['extra_fields'] = maybe_unserialize($attendee_data['extra_fields']);
            }

            if (!$dry_run) {
                // Use direct insert to avoid triggering ticket count updates
                $insert_data = SC_Attendee::get_table();
                $attendee_data['created_at'] = $attendee->post_date;
                $attendee_data['updated_at'] = $attendee->post_modified;

                // Convert extra_fields to JSON
                if (is_array($attendee_data['extra_fields'])) {
                    $attendee_data['extra_fields'] = wp_json_encode($attendee_data['extra_fields']);
                }

                $inserted = $wpdb->insert($table, $attendee_data);

                if ($inserted) {
                    $attendee_id = $wpdb->insert_id;
                    update_post_meta($wp_post_id, '_sc_attendee_id', $attendee_id);
                    $result['migrated']++;
                } else {
                    $result['errors'][] = "Failed to migrate attendee {$wp_post_id}: " . $wpdb->last_error;
                }
            } else {
                $result['migrated']++;
            }
        }

        return $result;
    }

    /**
     * Migrate speakers from etn_speaker post type
     *
     * @param bool $dry_run
     * @return array
     */
    public static function migrate_speakers($dry_run = false) {
        global $wpdb;

        $result = array('total' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array());
        $table = SC_Speaker::get_table();
        $pivot_table = SC_Speaker::get_pivot_table();
        $events_table = SC_Event::get_table();

        // Get all Eventin speakers
        $speakers = get_posts(array(
            'post_type'      => 'etn_speaker',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
        ));

        $result['total'] = count($speakers);

        foreach ($speakers as $speaker) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s",
                $speaker->post_name
            ));

            if ($exists) {
                $result['skipped']++;

                // But still migrate speaker-event relationships
                self::migrate_speaker_events($exists, $speaker->ID, $events_table, $pivot_table, $dry_run);
                continue;
            }

            $meta = get_post_meta($speaker->ID);

            $speaker_data = array(
                'name'         => $speaker->post_title,
                'slug'         => $speaker->post_name,
                'bio'          => $speaker->post_content,
                'email'        => self::get_meta($meta, 'etn_speaker_email'),
                'phone'        => self::get_meta($meta, 'etn_speaker_phone'),
                'company'      => self::get_meta($meta, 'etn_speaker_company'),
                'job_title'    => self::get_meta($meta, 'etn_speaker_designation'),
                'website'      => self::get_meta($meta, 'etn_speaker_website'),
                'photo'        => get_post_thumbnail_id($speaker->ID),
                'social_links' => array(
                    'facebook'  => self::get_meta($meta, 'etn_speaker_facebook'),
                    'twitter'   => self::get_meta($meta, 'etn_speaker_twitter'),
                    'linkedin'  => self::get_meta($meta, 'etn_speaker_linkedin'),
                    'instagram' => self::get_meta($meta, 'etn_speaker_instagram'),
                ),
                'status'       => $speaker->post_status === 'publish' ? 'active' : 'inactive',
            );

            if (!$dry_run) {
                $speaker_id = SC_Speaker::create($speaker_data);

                if ($speaker_id) {
                    update_post_meta($speaker->ID, '_sc_speaker_id', $speaker_id);

                    // Migrate speaker-event relationships
                    self::migrate_speaker_events($speaker_id, $speaker->ID, $events_table, $pivot_table, $dry_run);

                    $result['migrated']++;
                } else {
                    $result['errors'][] = "Failed to migrate speaker {$speaker->ID}: " . $wpdb->last_error;
                }
            } else {
                $result['migrated']++;
            }
        }

        return $result;
    }

    /**
     * Migrate speaker-event relationships
     *
     * @param int $speaker_id SC Speaker ID
     * @param int $wp_speaker_id WordPress speaker post ID
     * @param string $events_table Events table name
     * @param string $pivot_table Pivot table name
     * @param bool $dry_run
     */
    private static function migrate_speaker_events($speaker_id, $wp_speaker_id, $events_table, $pivot_table, $dry_run) {
        global $wpdb;

        // Get events that have this speaker
        $events_with_speaker = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'etn_event_speaker'
            AND (meta_value LIKE %s OR meta_value LIKE %s)",
            '%"' . $wp_speaker_id . '"%',
            '%i:' . $wp_speaker_id . ';%'
        ));

        foreach ($events_with_speaker as $wp_event_id) {
            // Get SC event ID
            $sc_event_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $events_table WHERE wp_post_id = %d",
                $wp_event_id
            ));

            if (!$sc_event_id) {
                continue;
            }

            // Check if relationship exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $pivot_table WHERE speaker_id = %d AND event_id = %d",
                $speaker_id,
                $sc_event_id
            ));

            if (!$exists && !$dry_run) {
                SC_Speaker::attach_to_event($speaker_id, $sc_event_id);
            }
        }
    }

    /**
     * Helper to get meta value
     *
     * @param array $meta Meta array from get_post_meta
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed
     */
    private static function get_meta($meta, $key, $default = '') {
        if (isset($meta[$key][0])) {
            $value = maybe_unserialize($meta[$key][0]);
            return $value !== '' ? $value : $default;
        }
        return $default;
    }

    /**
     * Rollback migration (delete all data from custom tables)
     *
     * @return bool
     */
    public static function rollback() {
        global $wpdb;

        $tables = sc_get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }

        // Remove migration status
        delete_option(self::STATUS_OPTION);

        // Remove mapping meta from posts
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sc_event_id', '_sc_attendee_id', '_sc_speaker_id')");

        return true;
    }
}

/**
 * Admin page for migration
 */
function sc_add_migration_admin_page() {
    add_submenu_page(
        'tools.php',
        __('SC Events Migration', 'sc_events'),
        __('SC Migration', 'sc_events'),
        'manage_options',
        'sc-migration',
        'sc_migration_admin_page'
    );
}
add_action('admin_menu', 'sc_add_migration_admin_page');

/**
 * Migration admin page content
 */
function sc_migration_admin_page() {
    // Handle form submissions
    if (isset($_POST['sc_migrate']) && check_admin_referer('sc_migration_action')) {
        $dry_run = isset($_POST['dry_run']);
        $results = SC_Data_Migration::run($dry_run);
        ?>
        <div class="notice notice-success">
            <p><strong><?php echo $dry_run ? __('Dry Run Completed', 'sc_events') : __('Migration Completed', 'sc_events'); ?></strong></p>
        </div>
        <?php
    }

    if (isset($_POST['sc_rollback']) && check_admin_referer('sc_migration_action')) {
        SC_Data_Migration::rollback();
        ?>
        <div class="notice notice-warning">
            <p><strong><?php _e('Rollback Completed - All custom table data has been cleared.', 'sc_events'); ?></strong></p>
        </div>
        <?php
    }

    $status = SC_Data_Migration::get_status();
    ?>
    <div class="wrap">
        <h1><?php _e('SC Events Data Migration', 'sc_events'); ?></h1>

        <div class="card">
            <h2><?php _e('Migration Status', 'sc_events'); ?></h2>
            <p>
                <strong><?php _e('Tables Exist:', 'sc_events'); ?></strong>
                <?php echo sc_tables_exist() ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:red;">✗ No</span>'; ?>
            </p>
            <p>
                <strong><?php _e('Migration Completed:', 'sc_events'); ?></strong>
                <?php echo !empty($status['completed']) ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:orange;">✗ Not yet</span>'; ?>
            </p>

            <?php if (!empty($status['stats'])): ?>
            <h3><?php _e('Last Migration Results', 'sc_events'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'sc_events'); ?></th>
                        <th><?php _e('Total', 'sc_events'); ?></th>
                        <th><?php _e('Migrated', 'sc_events'); ?></th>
                        <th><?php _e('Skipped', 'sc_events'); ?></th>
                        <th><?php _e('Errors', 'sc_events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array('events', 'tickets', 'attendees', 'speakers') as $type): ?>
                    <?php if (isset($status['stats'][$type])): ?>
                    <tr>
                        <td><?php echo ucfirst($type); ?></td>
                        <td><?php echo $status['stats'][$type]['total']; ?></td>
                        <td><?php echo $status['stats'][$type]['migrated']; ?></td>
                        <td><?php echo $status['stats'][$type]['skipped']; ?></td>
                        <td><?php echo count($status['stats'][$type]['errors']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2><?php _e('Run Migration', 'sc_events'); ?></h2>
            <p><?php _e('This will migrate data from Eventin (wp_posts/wp_postmeta) to custom SC tables.', 'sc_events'); ?></p>
            <form method="post">
                <?php wp_nonce_field('sc_migration_action'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        <?php _e('Dry Run (preview only, no actual changes)', 'sc_events'); ?>
                    </label>
                </p>
                <p>
                    <button type="submit" name="sc_migrate" class="button button-primary">
                        <?php _e('Run Migration', 'sc_events'); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="card" style="border-left: 4px solid #dc3232;">
            <h2><?php _e('Danger Zone', 'sc_events'); ?></h2>
            <p><?php _e('Rollback will delete ALL data from custom SC tables. This cannot be undone!', 'sc_events'); ?></p>
            <form method="post" onsubmit="return confirm('<?php _e('Are you sure? This will delete all migrated data!', 'sc_events'); ?>');">
                <?php wp_nonce_field('sc_migration_action'); ?>
                <p>
                    <button type="submit" name="sc_rollback" class="button" style="color:#dc3232;">
                        <?php _e('Rollback Migration', 'sc_events'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler for migration
 */
function sc_ajax_run_migration() {
    check_ajax_referer('sc_migration_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
    $results = SC_Data_Migration::run($dry_run);

    wp_send_json_success($results);
}
add_action('wp_ajax_sc_run_migration', 'sc_ajax_run_migration');
