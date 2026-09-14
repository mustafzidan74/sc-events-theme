<?php
/**
 * SC Certificate Template Model Class
 *
 * Data Access Layer for Certificate Templates table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Certificate_Template {

    /**
     * Table name
     */
    private static $table;

    /**
     * Default placeholders available in templates
     */
    public static $placeholders = array(
        '{attendee_name}'       => 'Attendee full name',
        '{event_title}'         => 'Event title',
        '{event_date}'          => 'Event date (formatted)',
        '{event_start_date}'    => 'Event start date',
        '{event_end_date}'      => 'Event end date',
        '{event_location}'      => 'Event venue/location',
        '{certificate_number}'  => 'Unique certificate number',
        '{issue_date}'          => 'Date certificate was issued',
        '{verification_code}'   => 'Verification code',
        '{verification_url}'    => 'Full URL to verify certificate',
        '{qr_code}'             => 'QR code image for verification',
        '{ticket_name}'         => 'Ticket type name',
        '{attendee_email}'      => 'Attendee email',
        '{organizer_name}'      => 'Event organizer name',
        '{current_year}'        => 'Current year',
    );

    /**
     * Get table name
     */
    public static function get_table() {
        global $wpdb;
        if (!self::$table) {
            self::$table = $wpdb->prefix . 'sc_certificate_templates';
        }
        return self::$table;
    }

    /**
     * Get single template by ID
     *
     * @param int $id Template ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($template) {
            $template = self::hydrate($template);
        }

        return $template;
    }

    /**
     * Get template by slug
     *
     * @param string $slug Template slug
     * @return object|null
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($template) {
            $template = self::hydrate($template);
        }

        return $template;
    }

    /**
     * Get default template
     *
     * @return object|null
     */
    public static function get_default() {
        global $wpdb;
        $table = self::get_table();

        $template = $wpdb->get_row(
            "SELECT * FROM $table WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        );

        if (!$template) {
            // Fallback to any active template
            $template = $wpdb->get_row(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
            );
        }

        if ($template) {
            $template = self::hydrate($template);
        }

        return $template;
    }

    /**
     * Get all templates with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'is_active'   => null,
            'design_mode' => null,
            'search'      => null,
            'orderby'     => 'name',
            'order'       => 'ASC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        if (!empty($args['design_mode'])) {
            $where[] = 'design_mode = %s';
            $values[] = $args['design_mode'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'name', 'created_at', 'is_default');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $templates = $wpdb->get_results($sql);

        foreach ($templates as &$template) {
            $template = self::hydrate($template);
        }

        return $templates;
    }

    /**
     * Count templates
     *
     * @param array $args Filter arguments
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'is_active'   => null,
            'design_mode' => null,
            'search'      => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        if (!empty($args['design_mode'])) {
            $where[] = 'design_mode = %s';
            $values[] = $args['design_mode'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create new template
     *
     * @param array $data Template data
     * @return int|false Template ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        if (empty($data['created_by'])) {
            $data['created_by'] = get_current_user_id();
        }

        // Generate slug
        if (empty($data['slug'])) {
            $data['slug'] = self::generate_unique_slug($data['name']);
        }

        // If this is set as default, unset others
        if (!empty($data['is_default'])) {
            $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $template_id = $wpdb->insert_id;
            do_action('sc_certificate_template_created', $template_id, $data);
            return $template_id;
        }

        return false;
    }

    /**
     * Update template
     *
     * @param int $id Template ID
     * @param array $data Template data
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['updated_at'] = current_time('mysql');

        // If this is set as default, unset others
        if (!empty($data['is_default'])) {
            $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
        }

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result !== false) {
            do_action('sc_certificate_template_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete template
     *
     * @param int $id Template ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        $template = self::get($id);
        if (!$template) {
            return false;
        }

        // Check if any certificates use this template
        $certificates_table = $wpdb->prefix . 'sc_certificates';
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $certificates_table WHERE template_id = %d",
            $id
        ));

        if ($in_use > 0) {
            return new WP_Error('template_in_use', 'Cannot delete template that has issued certificates.');
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_certificate_template_deleted', $id, $template);
            return true;
        }

        return false;
    }

    /**
     * Duplicate template
     *
     * @param int $id Template ID
     * @return int|false New template ID or false
     */
    public static function duplicate($id) {
        $template = self::get($id);
        if (!$template) {
            return false;
        }

        $data = array(
            'name'             => $template->name . ' (Copy)',
            'description'      => $template->description,
            'background_image' => $template->background_image,
            'html_template'    => $template->html_template,
            'css_styles'       => $template->css_styles,
            'orientation'      => $template->orientation,
            'paper_size'       => $template->paper_size,
            'width_mm'         => $template->width_mm,
            'height_mm'        => $template->height_mm,
            'is_default'       => 0,
            'is_active'        => 1,
        );

        $new_id = self::create($data);
        // create() doesn't know the visual builder's columns; copy the design too.
        if ($new_id && isset($template->design_mode)) {
            global $wpdb;
            $wpdb->update(self::get_table(), array('design_mode' => $template->design_mode, 'elements_config' => $template->elements_config), array('id' => (int) $new_id));
        }
        return $new_id;
    }

    /**
     * Generate unique slug
     *
     * @param string $name Template name
     * @return string
     */
    public static function generate_unique_slug($name) {
        global $wpdb;
        $table = self::get_table();

        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s", $slug))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get default HTML template
     *
     * @return string
     */
    public static function get_default_html_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .certificate {
            width: 297mm;
            height: 210mm;
            position: relative;
            font-family: "Georgia", serif;
            background: #fff;
            overflow: hidden;
        }
        .certificate-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }
        .certificate-content {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 40mm 30mm;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .certificate-title {
            font-size: 42pt;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 10mm;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .certificate-subtitle {
            font-size: 18pt;
            color: #4a5568;
            margin-bottom: 15mm;
        }
        .attendee-name {
            font-size: 36pt;
            font-weight: bold;
            color: #2c5282;
            margin-bottom: 10mm;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5mm;
            display: inline-block;
        }
        .certificate-text {
            font-size: 14pt;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 15mm;
        }
        .event-title {
            font-size: 20pt;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5mm;
        }
        .event-date {
            font-size: 14pt;
            color: #718096;
            margin-bottom: 15mm;
        }
        .certificate-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
        }
        .qr-code {
            width: 25mm;
            height: 25mm;
        }
        .certificate-number {
            font-size: 10pt;
            color: #a0aec0;
        }
        .signature-area {
            text-align: center;
        }
        .signature-line {
            width: 60mm;
            border-top: 1px solid #4a5568;
            margin-bottom: 2mm;
        }
        .signature-name {
            font-size: 12pt;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-content">
            <h1 class="certificate-title">Certificate of Attendance</h1>
            <p class="certificate-subtitle">This is to certify that</p>

            <div class="attendee-name">{attendee_name}</div>

            <p class="certificate-text">
                has successfully attended and completed
            </p>

            <div class="event-title">{event_title}</div>
            <div class="event-date">{event_date}</div>

            <div class="certificate-footer">
                <div class="qr-section">
                    {qr_code}
                    <div class="certificate-number">Certificate No: {certificate_number}</div>
                </div>

                <div class="signature-area">
                    <div class="signature-line"></div>
                    <div class="signature-name">{organizer_name}</div>
                </div>

                <div class="issue-info">
                    <div class="certificate-number">Issued: {issue_date}</div>
                    <div class="certificate-number">Verify: {verification_url}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Render template with data
     *
     * @param object|array $template Template object or array
     * @param array $data Placeholder values
     * @return string Rendered HTML
     */
    public static function render($template, $data) {
        // Support both object and array
        $is_array = is_array($template);

        $html_template = $is_array ? ($template['html_template'] ?? '') : ($template->html_template ?? '');
        $background_image = $is_array ? ($template['background_image'] ?? '') : ($template->background_image ?? '');
        $css_styles = $is_array ? ($template['css_styles'] ?? '') : ($template->css_styles ?? '');

        $html = $html_template;

        // Handle Mustache-style conditional blocks: {{#var}}...{{/var}}
        // If the variable is truthy, keep the inner content; otherwise remove the block
        $html = preg_replace_callback('/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s', function($matches) use ($data) {
            $key = $matches[1];
            $content = $matches[2];
            if (!empty($data[$key])) {
                return $content;
            }
            return '';
        }, $html);

        // Handle inverted blocks: {{^var}}...{{/var}} (show if falsy)
        $html = preg_replace_callback('/\{\{\^(\w+)\}\}(.*?)\{\{\/\1\}\}/s', function($matches) use ($data) {
            $key = $matches[1];
            $content = $matches[2];
            if (empty($data[$key])) {
                return $content;
            }
            return '';
        }, $html);

        // Replace all placeholders
        foreach ($data as $placeholder => $value) {
            // Support both {{placeholder}} and {placeholder} formats
            $html = str_replace('{{' . $placeholder . '}}', $value, $html);
            $html = str_replace('{' . $placeholder . '}', $value, $html);
        }

        // Clean up any remaining unmatched mustache tags
        $html = preg_replace('/\{\{[#^\/]?\w+\}\}/', '', $html);

        // Add background image if set
        if (!empty($background_image)) {
            // Check if it's a URL or attachment ID
            if (is_numeric($background_image)) {
                $bg_url = wp_get_attachment_url($background_image);
            } else {
                $bg_url = $background_image;
            }

            if ($bg_url) {
                $html = str_replace(
                    '<div class="certificate">',
                    '<div class="certificate"><img src="' . esc_url($bg_url) . '" class="certificate-bg" alt="">',
                    $html
                );
            }
        }

        // Add custom CSS if set
        if (!empty($css_styles)) {
            $html = str_replace('</style>', $css_styles . '</style>', $html);
        }

        return $html;
    }

    /**
     * Prepare data for insert/update
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private static function prepare_data($data) {
        $prepared = array();

        // Integer fields
        $int_fields = array('background_image', 'width_mm', 'height_mm', 'is_default', 'is_active', 'created_by');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('name', 'slug', 'orientation', 'paper_size');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Text fields (allow HTML)
        $text_fields = array('description', 'html_template', 'css_styles');

        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = $data[$field]; // Keep HTML as is for templates
            }
        }

        return $prepared;
    }

    /**
     * Hydrate template object
     *
     * @param object $template Raw template
     * @return object Hydrated template
     */
    private static function hydrate($template) {
        // Background image URL
        if ($template->background_image) {
            $template->background_image_url = wp_get_attachment_url($template->background_image);
            $template->background_image_thumb = wp_get_attachment_image_url($template->background_image, 'medium');
        } else {
            $template->background_image_url = null;
            $template->background_image_thumb = null;
        }

        // Paper dimensions
        $template->dimensions = array(
            'width'  => $template->width_mm,
            'height' => $template->height_mm,
        );

        // Preview URL
        $template->preview_url = add_query_arg(array(
            'action'      => 'sc_preview_certificate_template',
            'template_id' => $template->id,
            'nonce'       => wp_create_nonce('preview_certificate_' . $template->id),
        ), admin_url('admin-ajax.php'));

        return $template;
    }

    /**
     * Get available paper sizes
     *
     * @return array
     */
    public static function get_paper_sizes() {
        return array(
            'A4' => array(
                'name'      => 'A4',
                'landscape' => array('width' => 297, 'height' => 210),
                'portrait'  => array('width' => 210, 'height' => 297),
            ),
            'Letter' => array(
                'name'      => 'Letter',
                'landscape' => array('width' => 279, 'height' => 216),
                'portrait'  => array('width' => 216, 'height' => 279),
            ),
            'A3' => array(
                'name'      => 'A3',
                'landscape' => array('width' => 420, 'height' => 297),
                'portrait'  => array('width' => 297, 'height' => 420),
            ),
            'Custom' => array(
                'name'      => 'Custom',
                'landscape' => array('width' => 297, 'height' => 210),
                'portrait'  => array('width' => 210, 'height' => 297),
            ),
        );
    }
}
