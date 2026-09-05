<?php
/**
 * Speakers Module
 *
 * Manage event speakers, presenters, and panelists
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Speakers_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'speakers';

    /**
     * Module name
     */
    public $name = 'Speakers';

    /**
     * Module description
     */
    public $description = 'Manage event speakers, presenters, and panelists';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Dependencies
     */
    public $dependencies = array('events');

    /**
     * Priority
     */
    public $priority = 25;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load the Speaker class
        add_action('init', array($this, 'load_classes'), 5);

        // Register rewrite rules for speaker profiles
        add_action('init', array($this, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('template_include', array($this, 'load_speaker_template'));

        // Add to event meta box
        add_action('sc_event_meta_box', array($this, 'render_speakers_meta_box'), 30);

        // Save speakers when event is saved
        add_action('save_post_etn', array($this, 'save_event_speakers'), 10, 2);

        // AJAX handlers
        $this->register_ajax('sc_get_speakers', 'ajax_get_speakers');
        $this->register_ajax('sc_get_speaker', 'ajax_get_speaker');
        $this->register_ajax('sc_save_speaker', 'ajax_save_speaker');
        $this->register_ajax('sc_delete_speaker', 'ajax_delete_speaker');
        $this->register_ajax('sc_search_speakers', 'ajax_search_speakers');

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize module
     */
    public function init() {
        // Additional initialization
    }

    /**
     * Load model classes
     */
    public function load_classes() {
        if (!class_exists('SC_Speaker')) {
            $this->load_file('../../inc/database/class-sc-speaker.php');
        }
    }

    /**
     * Register rewrite rules for speaker profile pages
     */
    public function register_rewrite_rules() {
        add_rewrite_rule(
            '^speaker/([^/]+)/?$',
            'index.php?sc_speaker=$matches[1]',
            'top'
        );
    }

    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'sc_speaker';
        return $vars;
    }

    /**
     * Load speaker profile template
     */
    public function load_speaker_template($template) {
        $speaker_slug = get_query_var('sc_speaker');

        if (!$speaker_slug) {
            return $template;
        }

        // Try to load custom template
        $custom_template = $this->get_path('templates/single-speaker.php');
        if (file_exists($custom_template)) {
            return $custom_template;
        }

        // Fallback to theme template
        $theme_template = locate_template('single-speaker.php');
        if ($theme_template) {
            return $theme_template;
        }

        return $template;
    }

    /**
     * Render speakers meta box in event editor
     */
    public function render_speakers_meta_box($post) {
        $event_id = $post->ID;
        $speakers = SC_Speaker::get_by_event($event_id);
        $all_speakers = SC_Speaker::get_all(array('limit' => 100));

        wp_nonce_field('sc_speakers_meta', 'sc_speakers_nonce');
        ?>
        <div class="sc-meta-section">
            <h3><?php _e('Speakers', 'sc_events'); ?></h3>

            <div id="sc-event-speakers" class="sc-speakers-list">
                <?php if (!empty($speakers)) : ?>
                    <?php foreach ($speakers as $speaker) : ?>
                        <div class="sc-speaker-item" data-id="<?php echo esc_attr($speaker->id); ?>">
                            <img src="<?php echo esc_url($speaker->photo_url); ?>" alt="" class="sc-speaker-photo">
                            <div class="sc-speaker-info">
                                <strong><?php echo esc_html($speaker->name); ?></strong>
                                <span><?php echo esc_html($speaker->job_title); ?></span>
                            </div>
                            <button type="button" class="sc-remove-speaker" title="<?php esc_attr_e('Remove', 'sc_events'); ?>">&times;</button>
                            <input type="hidden" name="sc_event_speakers[]" value="<?php echo esc_attr($speaker->id); ?>">
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sc-add-speaker-wrapper">
                <select id="sc-add-speaker-select" class="sc-select2">
                    <option value=""><?php _e('Select a speaker to add...', 'sc_events'); ?></option>
                    <?php foreach ($all_speakers as $speaker) : ?>
                        <option value="<?php echo esc_attr($speaker->id); ?>"
                                data-photo="<?php echo esc_attr($speaker->photo_url); ?>"
                                data-title="<?php echo esc_attr($speaker->job_title); ?>">
                            <?php echo esc_html($speaker->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" id="sc-add-speaker-btn">
                    <?php _e('Add Speaker', 'sc_events'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=sc-speakers&action=new'); ?>" class="button" target="_blank">
                    <?php _e('Create New Speaker', 'sc_events'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Save event speakers
     */
    public function save_event_speakers($post_id, $post) {
        if (!isset($_POST['sc_speakers_nonce']) || !wp_verify_nonce($_POST['sc_speakers_nonce'], 'sc_speakers_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $speakers = isset($_POST['sc_event_speakers']) ? array_map('intval', $_POST['sc_event_speakers']) : array();

        SC_Speaker::sync_event_speakers($post_id, $speakers);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php')) && strpos($hook, 'sc-speakers') === false) {
            return;
        }

        global $post_type;
        if (in_array($hook, array('post.php', 'post-new.php')) && $post_type !== 'etn') {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/speakers';

        // CSS
        if (file_exists($this->get_path('assets/css/speakers-admin.css'))) {
            wp_enqueue_style(
                'sc-speakers-admin',
                $module_url . '/assets/css/speakers-admin.css',
                array(),
                $this->version
            );
        }

        // JS
        if (file_exists($this->get_path('assets/js/speakers-admin.js'))) {
            wp_enqueue_script(
                'sc-speakers-admin',
                $module_url . '/assets/js/speakers-admin.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('sc-speakers-admin', 'scSpeakersConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_speakers_nonce'),
                'i18n' => array(
                    'confirmDelete' => __('Are you sure you want to delete this speaker?', 'sc_events'),
                    'saving' => __('Saving...', 'sc_events'),
                    'saved' => __('Saved', 'sc_events'),
                    'error' => __('An error occurred', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * AJAX: Get all speakers
     */
    public function ajax_get_speakers() {
        check_ajax_referer('sc_speakers_nonce', 'nonce');

        $args = array(
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0),
        );

        $speakers = SC_Speaker::get_all($args);

        wp_send_json_success(array('speakers' => $speakers));
    }

    /**
     * AJAX: Get single speaker
     */
    public function ajax_get_speaker() {
        check_ajax_referer('sc_speakers_nonce', 'nonce');

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid speaker ID', 'sc_events')));
        }

        $speaker = SC_Speaker::get($id);

        if (!$speaker) {
            wp_send_json_error(array('message' => __('Speaker not found', 'sc_events')));
        }

        wp_send_json_success(array('speaker' => $speaker));
    }

    /**
     * AJAX: Save speaker
     */
    public function ajax_save_speaker() {
        check_ajax_referer('sc_speakers_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'job_title' => sanitize_text_field($_POST['job_title'] ?? ''),
            'bio' => wp_kses_post($_POST['bio'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'photo' => intval($_POST['photo'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
        );

        // Handle social links
        if (isset($_POST['social_links']) && is_array($_POST['social_links'])) {
            $social_links = array();
            foreach ($_POST['social_links'] as $key => $value) {
                $social_links[sanitize_key($key)] = esc_url_raw($value);
            }
            $data['social_links'] = $social_links;
        }

        if (empty($data['name'])) {
            wp_send_json_error(array('message' => __('Speaker name is required', 'sc_events')));
        }

        if ($id) {
            $result = SC_Speaker::update($id, $data);
            $message = __('Speaker updated', 'sc_events');
        } else {
            $id = SC_Speaker::create($data);
            $result = $id !== false;
            $message = __('Speaker created', 'sc_events');
        }

        if ($result) {
            $speaker = SC_Speaker::get($id);
            wp_send_json_success(array(
                'message' => $message,
                'speaker' => $speaker,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save speaker', 'sc_events')));
        }
    }

    /**
     * AJAX: Delete speaker
     */
    public function ajax_delete_speaker() {
        check_ajax_referer('sc_speakers_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid speaker ID', 'sc_events')));
        }

        $result = SC_Speaker::delete($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Speaker deleted', 'sc_events')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete speaker', 'sc_events')));
        }
    }

    /**
     * AJAX: Search speakers
     */
    public function ajax_search_speakers() {
        check_ajax_referer('sc_speakers_nonce', 'nonce');

        $search = sanitize_text_field($_POST['search'] ?? '');

        $speakers = SC_Speaker::get_all(array(
            'search' => $search,
            'limit' => 20,
        ));

        $results = array();
        foreach ($speakers as $speaker) {
            $results[] = array(
                'id' => $speaker->id,
                'text' => $speaker->name,
                'name' => $speaker->name,
                'photo_url' => $speaker->photo_url,
                'job_title' => $speaker->job_title,
                'company' => $speaker->company,
            );
        }

        wp_send_json_success(array('results' => $results));
    }

    /**
     * Get speakers for an event
     */
    public function get_event_speakers($event_id) {
        return SC_Speaker::get_by_event($event_id);
    }

    /**
     * Get speaker by ID
     */
    public function get_speaker($id) {
        return SC_Speaker::get($id);
    }

    /**
     * Get speaker by slug
     */
    public function get_speaker_by_slug($slug) {
        return SC_Speaker::get_by_slug($slug);
    }
}

// Register the module
sc_modules()->register_module(new SC_Speakers_Module());
