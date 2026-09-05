<?php
/**
 * Chat Widget Frontend Integration
 *
 * Handles loading the chat widget on event pages
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Don't load chat widget if chat module is disabled
if (function_exists('sc_module_active') && !sc_module_active('chat')) {
    return;
}

/**
 * Chat Widget Frontend Class
 */
class SC_Chat_Widget_Frontend {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'output_chat_config'), 5);
    }

    /**
     * Check if chat should be loaded on this page
     */
    private function should_load_chat() {
        // Check if chat is enabled globally
        $chat_enabled = get_option('sc_enable_chat', true);
        if (!$chat_enabled) {
            return false;
        }

        // Don't load on admin pages
        if (is_admin()) {
            return false;
        }

        // Don't load on dashboard pages (event-manager-dashboard)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (is_page_template('page-dashboard.php')
            || strpos($request_uri, 'event-manager-dashboard') !== false
            || strpos($request_uri, '/dashboard') !== false) {
            return false;
        }

        // Load on ALL frontend pages (global chat)
        return true;
    }

    /**
     * Check if current page is a Custom Tables event
     */
    private function is_custom_table_event() {
        $request = trim($_SERVER['REQUEST_URI'], '/');
        $base = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
        if ($base) {
            $request = preg_replace('#^' . preg_quote($base, '#') . '/?#', '', $request);
        }

        if (preg_match('#^event/([^/]+)/?$#', $request, $matches)) {
            $slug = $matches[1];
            if (in_array($slug, array('feed', 'page', 'attachment', 'embed', 'trackback'))) {
                return false;
            }

            // Check if exists in Custom Tables
            if (class_exists('SC_Event')) {
                $event = SC_Event::get_by_slug($slug);
                return !empty($event);
            }
        }

        return false;
    }

    /**
     * Get event slug from URL
     */
    private function get_event_slug_from_url() {
        $request = trim($_SERVER['REQUEST_URI'], '/');
        $base = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
        if ($base) {
            $request = preg_replace('#^' . preg_quote($base, '#') . '/?#', '', $request);
        }

        if (preg_match('#^event/([^/]+)/?$#', $request, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Get current event ID
     */
    private function get_event_id() {
        // From single event page (WordPress post type)
        if (is_singular('sc_event')) {
            return get_the_ID();
        }

        // From Custom Tables event via URL
        $slug = $this->get_event_slug_from_url();
        if ($slug && class_exists('SC_Event')) {
            $event = SC_Event::get_by_slug($slug);
            if ($event) {
                return $event->id;
            }
        }

        // From URL parameter
        if (isset($_GET['event_id'])) {
            return intval($_GET['event_id']);
        }

        // From shortcode attribute (check post meta)
        global $post;
        if ($post) {
            $event_id = get_post_meta($post->ID, '_sc_event_id', true);
            if ($event_id) {
                return intval($event_id);
            }
        }

        return 0;
    }

    /**
     * Enqueue chat widget scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->should_load_chat()) {
            return;
        }

        $theme_url = get_template_directory_uri();
        $version = '1.2.0.' . time(); // Cache bust

        // Enqueue CSS
        wp_enqueue_style(
            'sc-chat-widget',
            $theme_url . '/assets/css/chat-widget.css',
            array(),
            $version
        );

        // Enqueue JS
        wp_enqueue_script(
            'sc-chat-widget',
            $theme_url . '/assets/js/chat-widget.js',
            array(),
            $version,
            true
        );
    }

    /**
     * Output chat configuration in footer
     */
    public function output_chat_config() {
        if (!$this->should_load_chat()) {
            return;
        }

        // Try to get event ID (0 for global/general chat)
        $event_id = $this->get_event_id();
        $event_title = '';

        // Get event details if on event page
        if ($event_id) {
            $event = null;
            if (class_exists('SC_Event')) {
                $event = SC_Event::get($event_id);
            }

            if ($event) {
                $event_title = $event->title;
            } else {
                $post = get_post($event_id);
                if ($post) {
                    $event_title = $post->post_title;
                }
            }
        } else {
            // Global chat - use site name
            $event_title = get_bloginfo('name') . ' - ' . __('General Support', 'sc_events');
        }

        // Get theme colors
        $primary_color = get_option('sc_primary_color', '#667eea');
        $secondary_color = get_option('sc_secondary_color', '#764ba2');

        // Create nonce
        $nonce = wp_create_nonce('sc_chat_nonce');

        // Get user info if logged in
        $is_logged_in = is_user_logged_in();
        $user_name = '';
        $user_email = '';
        if ($is_logged_in) {
            $current_user = wp_get_current_user();
            $user_name = $current_user->display_name;
            $user_email = $current_user->user_email;
        }

        ?>
        <script>
            var scChatConfig = {
                eventId: <?php echo intval($event_id); ?>,
                eventTitle: <?php echo json_encode($event_title); ?>,
                ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo json_encode($nonce); ?>,
                primaryColor: <?php echo json_encode($primary_color); ?>,
                secondaryColor: <?php echo json_encode($secondary_color); ?>,
                isLoggedIn: <?php echo $is_logged_in ? 'true' : 'false'; ?>,
                userName: <?php echo json_encode($user_name); ?>,
                userEmail: <?php echo json_encode($user_email); ?>
            };
        </script>
        <?php
    }
}

// Initialize
SC_Chat_Widget_Frontend::get_instance();

/**
 * Helper function to manually add chat widget to a page
 *
 * Usage: sc_render_chat_widget($event_id);
 */
function sc_render_chat_widget($event_id) {
    if (!$event_id) {
        return;
    }

    // Get event details from Custom Tables
    $event = null;
    $event_title = '';
    if (class_exists('SC_Event')) {
        $event = SC_Event::get($event_id);
    }

    if ($event) {
        $event_title = $event->title;
    } else {
        $post = get_post($event_id);
        if ($post) {
            $event_title = $post->post_title;
        }
    }

    // Get theme colors
    $primary_color = get_option('sc_primary_color', '#667eea');
    $secondary_color = get_option('sc_secondary_color', '#764ba2');

    // Create nonce
    $nonce = wp_create_nonce('sc_chat_nonce');

    // Enqueue assets
    $theme_url = get_template_directory_uri();
    wp_enqueue_style('sc-chat-widget', $theme_url . '/assets/css/chat-widget.css');
    wp_enqueue_script('sc-chat-widget', $theme_url . '/assets/js/chat-widget.js', array(), '1.0.0', true);

    // Output config
    ?>
    <script>
        var scChatConfig = {
            eventId: <?php echo intval($event_id); ?>,
            eventTitle: <?php echo json_encode($event_title); ?>,
            ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo json_encode($nonce); ?>,
            primaryColor: <?php echo json_encode($primary_color); ?>,
            secondaryColor: <?php echo json_encode($secondary_color); ?>
        };
    </script>
    <?php
}

/**
 * Shortcode to add chat widget to any page
 *
 * Usage: [sc_chat event_id="123"]
 */
function sc_chat_shortcode($atts) {
    $atts = shortcode_atts(array(
        'event_id' => 0,
    ), $atts, 'sc_chat');

    $event_id = intval($atts['event_id']);

    if (!$event_id) {
        return '';
    }

    ob_start();
    sc_render_chat_widget($event_id);
    return ob_get_clean();
}
add_shortcode('sc_chat', 'sc_chat_shortcode');
