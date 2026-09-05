<?php
/**
 * TGM Plugin Activation Configuration
 *
 * Automatically prompt users to install required plugins when theme is activated
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Include the TGM_Plugin_Activation class.
 */
require_once dirname(__FILE__) . '/class-tgm-plugin-activation.php';

add_action('tgmpa_register', 'sc_events_register_required_plugins');

/**
 * Register the required plugins for this theme.
 */
function sc_events_register_required_plugins() {
    $plugins = array(
        // SC Events uses its own custom payment system - no WooCommerce required
        // Add any required plugins here if needed in the future
    );

    $config = array(
        'id'           => 'sc_events',
        'default_path' => '',
        'menu'         => 'tgmpa-install-plugins',
        'parent_slug'  => 'themes.php',
        'capability'   => 'edit_theme_options',
        'has_notices'  => true,
        'dismissable'  => true,
        'dismiss_msg'  => '',
        'is_automatic' => false,
        'message'      => '',
        'strings'      => array(
            'page_title'                      => __('Install Required Plugins', 'sc_events'),
            'menu_title'                      => __('Install Plugins', 'sc_events'),
            'installing'                      => __('Installing Plugin: %s', 'sc_events'),
            'updating'                        => __('Updating Plugin: %s', 'sc_events'),
            'oops'                            => __('Something went wrong with the plugin API.', 'sc_events'),
            'notice_can_install_required'     => _n_noop(
                'This theme requires the following plugin: %1$s.',
                'This theme requires the following plugins: %1$s.',
                'sc_events'
            ),
            'notice_can_install_recommended'  => _n_noop(
                'This theme recommends the following plugin: %1$s.',
                'This theme recommends the following plugins: %1$s.',
                'sc_events'
            ),
            'notice_ask_to_update'            => _n_noop(
                'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
                'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
                'sc_events'
            ),
            'notice_ask_to_update_maybe'      => _n_noop(
                'There is an update available for: %1$s.',
                'There are updates available for the following plugins: %1$s.',
                'sc_events'
            ),
            'notice_can_activate_required'    => _n_noop(
                'The following required plugin is currently inactive: %1$s.',
                'The following required plugins are currently inactive: %1$s.',
                'sc_events'
            ),
            'notice_can_activate_recommended' => _n_noop(
                'The following recommended plugin is currently inactive: %1$s.',
                'The following recommended plugins are currently inactive: %1$s.',
                'sc_events'
            ),
            'install_link'                    => _n_noop(
                'Begin installing plugin',
                'Begin installing plugins',
                'sc_events'
            ),
            'update_link'                     => _n_noop(
                'Begin updating plugin',
                'Begin updating plugins',
                'sc_events'
            ),
            'activate_link'                   => _n_noop(
                'Begin activating plugin',
                'Begin activating plugins',
                'sc_events'
            ),
            'return'                          => __('Return to Required Plugins Installer', 'sc_events'),
            'plugin_activated'                => __('Plugin activated successfully.', 'sc_events'),
            'activated_successfully'          => __('The following plugin was activated successfully:', 'sc_events'),
            'plugin_already_active'           => __('No action taken. Plugin %1$s was already active.', 'sc_events'),
            'plugin_needs_higher_version'     => __('Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.', 'sc_events'),
            'complete'                        => __('All plugins installed and activated successfully. %1$s', 'sc_events'),
            'dismiss'                         => __('Dismiss this notice', 'sc_events'),
            'notice_cannot_install_activate'  => __('There are one or more required or recommended plugins to install, update or activate.', 'sc_events'),
            'contact_admin'                   => __('Please contact the administrator of this site for help.', 'sc_events'),
            'nag_type'                        => 'updated',
        ),
    );

    tgmpa($plugins, $config);
}
