<?php
/**
 * Reset Event Manager Role (Run Once)
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reset Event Manager Role
 * This function removes and recreates the role with updated capabilities
 */
function sc_reset_event_manager_role() {
    // Remove existing role
    remove_role('event_manager');

    // Recreate with fresh capabilities
    add_role(
        'event_manager',
        __('Event Manager', 'sc_events'),
        array(
            'read' => true,
        )
    );

    // Trigger the capability granting
    do_action('admin_init');

    update_option('sc_event_manager_role_reset', 'done');
}

// Check if we need to reset the role
if (get_option('sc_event_manager_role_reset') != 'done') {
    add_action('init', 'sc_reset_event_manager_role', 1);
}
