<?php
/**
 * Chat Module
 *
 * Real-time messaging system for visitor-organizer communication
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Chat_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'chat';

    /**
     * Module name
     */
    public $name = 'Chat';

    /**
     * Module description
     */
    public $description = 'Real-time messaging system for visitor-organizer communication';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Priority
     */
    public $priority = 30;

    /**
     * Chat instance
     */
    private $chat = null;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load the main Chat class
        add_action('init', array($this, 'init_chat'), 5);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Add chat widget to frontend
        add_action('wp_footer', array($this, 'render_chat_widget'));

        // Dashboard menu
        add_action('sc_dashboard_menu', array($this, 'add_dashboard_menu'));

        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize module
     */
    public function init() {
        // Additional initialization if needed
    }

    /**
     * Initialize chat system
     */
    public function init_chat() {
        // Load the class if not already loaded
        if (!class_exists('SC_Chat')) {
            $this->load_file('../../inc/database/class-sc-chat.php');
        }

        // Get the chat instance
        if (function_exists('sc_chat')) {
            $this->chat = sc_chat();
        }
    }

    /**
     * Get chat instance
     */
    public function get_chat() {
        if (!$this->chat && function_exists('sc_chat')) {
            $this->chat = sc_chat();
        }
        return $this->chat;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on public pages
        if (is_admin()) {
            return;
        }

        // Check if chat is enabled
        if (!$this->is_chat_enabled()) {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/chat';

        // Chat widget CSS
        if (file_exists($this->get_path('assets/css/chat-widget.css'))) {
            wp_enqueue_style(
                'sc-chat-widget',
                $module_url . '/assets/css/chat-widget.css',
                array(),
                $this->version
            );
        }

        // Chat widget JS
        if (file_exists($this->get_path('assets/js/chat-widget.js'))) {
            wp_enqueue_script(
                'sc-chat-widget',
                $module_url . '/assets/js/chat-widget.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('sc-chat-widget', 'scChatConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_chat_nonce'),
                'eventId' => $this->get_current_event_id(),
                'isLoggedIn' => is_user_logged_in(),
                // No visitor token here: pages are stored by the page cache and shared between
                // visitors. The widget keeps its own copy (localStorage) and the cookie travels by itself.
                'i18n' => array(
                    'title' => __('Chat with us', 'sc_events'),
                    'placeholder' => __('Type your message...', 'sc_events'),
                    'send' => __('Send', 'sc_events'),
                    'close' => __('Close', 'sc_events'),
                    'minimize' => __('Minimize', 'sc_events'),
                    'nameLabel' => __('Your Name', 'sc_events'),
                    'emailLabel' => __('Your Email', 'sc_events'),
                    'startChat' => __('Start Chat', 'sc_events'),
                    'sending' => __('Sending...', 'sc_events'),
                    'error' => __('Error sending message', 'sc_events'),
                    'closed' => __('This conversation is closed', 'sc_events'),
                    'reopen' => __('Reopen', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on dashboard pages
        if (strpos($hook, 'sc-') === false) {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/chat';

        // Admin chat CSS
        if (file_exists($this->get_path('assets/css/chat-admin.css'))) {
            wp_enqueue_style(
                'sc-chat-admin',
                $module_url . '/assets/css/chat-admin.css',
                array(),
                $this->version
            );
        }

        // Admin chat JS
        if (file_exists($this->get_path('assets/js/chat-admin.js'))) {
            wp_enqueue_script(
                'sc-chat-admin',
                $module_url . '/assets/js/chat-admin.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('sc-chat-admin', 'scChatAdminConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_chat_nonce'),
                'pollInterval' => 5000,
                'i18n' => array(
                    'noConversations' => __('No conversations', 'sc_events'),
                    'loading' => __('Loading...', 'sc_events'),
                    'send' => __('Send', 'sc_events'),
                    'close' => __('Close Conversation', 'sc_events'),
                    'archive' => __('Archive', 'sc_events'),
                    'reopen' => __('Reopen', 'sc_events'),
                    'confirmClose' => __('Are you sure you want to close this conversation?', 'sc_events'),
                    'confirmArchive' => __('Are you sure you want to archive this conversation?', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * Check if chat is enabled
     */
    private function is_chat_enabled() {
        return get_option('sc_chat_enabled', true);
    }

    /**
     * Get current event ID
     */
    private function get_current_event_id() {
        global $post;

        if (is_singular('etn')) {
            return $post->ID;
        }

        return 0;
    }

    /**
     * Render chat widget
     */
    public function render_chat_widget() {
        if (is_admin() || !$this->is_chat_enabled()) {
            return;
        }

        // Load widget template
        $template = $this->get_path('templates/chat-widget.php');
        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Add dashboard menu item
     */
    public function add_dashboard_menu($menu_items) {
        $chat = $this->get_chat();
        $unread_count = $chat ? $chat->get_total_unread_count() : 0;

        $menu_items['chat'] = array(
            'title' => __('Messages', 'sc_events'),
            'icon' => 'chat',
            'url' => admin_url('admin.php?page=sc-chat'),
            'badge' => $unread_count > 0 ? $unread_count : null,
            'priority' => 30,
        );

        return $menu_items;
    }

    /**
     * Get chat statistics
     */
    public function get_stats($event_id = null) {
        $chat = $this->get_chat();
        if (!$chat) {
            return array(
                'total' => 0,
                'active' => 0,
                'unread' => 0,
            );
        }

        return $chat->get_chat_stats($event_id);
    }

    /**
     * Get unread count
     */
    public function get_unread_count() {
        $chat = $this->get_chat();
        return $chat ? $chat->get_total_unread_count() : 0;
    }

    /**
     * Get conversations for an event
     */
    public function get_conversations($event_id, $status = 'all', $limit = 50, $offset = 0) {
        $chat = $this->get_chat();
        if (!$chat) {
            return array();
        }

        return $chat->get_event_conversations($event_id, $status, $limit, $offset);
    }

    /**
     * Get messages for a conversation
     */
    public function get_messages($conversation_id, $limit = 100, $offset = 0) {
        $chat = $this->get_chat();
        if (!$chat) {
            return array();
        }

        return $chat->get_messages($conversation_id, $limit, $offset);
    }
}

// Register the module
sc_modules()->register_module(new SC_Chat_Module());
