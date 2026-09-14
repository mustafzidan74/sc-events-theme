<?php
/**
 * Certificate PDF Generator using TCPDF
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include TCPDF
require_once get_template_directory() . '/vendor/autoload.php';

/**
 * SC Certificate PDF Generator Class
 */
class SC_Certificate_PDF {

    /**
     * Certificate data
     */
    private $certificate;

    /**
     * Template data
     */
    private $template;

    /**
     * Event data
     */
    private $event;

    /**
     * Attendee data
     */
    private $attendee;

    /**
     * Constructor
     *
     * @param int $certificate_id Certificate ID
     */
    public function __construct($certificate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_certificates';

        $this->certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $certificate_id
        ), ARRAY_A);

        if (!$this->certificate) {
            throw new Exception('Certificate not found');
        }

        // Get template
        $this->template = SC_Certificate_Template::get($this->certificate['template_id']);
        if (!$this->template) {
            throw new Exception('Certificate template not found');
        }

        // Get event
        $this->event = SC_Event::get($this->certificate['event_id']);
        if (!$this->event) {
            throw new Exception('Event not found');
        }

        // Get attendee
        $this->attendee = SC_Attendee::get($this->certificate['attendee_id']);
        if (!$this->attendee) {
            throw new Exception('Attendee not found');
        }
    }

    /**
     * Generate PDF
     *
     * @param string $output_mode 'I' for inline, 'D' for download, 'F' for save to file, 'S' for string
     * @param string $file_path Optional file path for 'F' mode
     * @return mixed PDF output based on mode
     */
    public function generate($output_mode = 'I', $file_path = '') {
        // Check if template uses visual mode
        $design_mode = isset($this->template->design_mode) ? $this->template->design_mode : 'classic';

        if ($design_mode === 'visual' && !empty($this->template->elements_config)) {
            return $this->generate_visual_pdf($output_mode, $file_path);
        }

        return $this->generate_classic_pdf($output_mode, $file_path);
    }

    /**
     * Generate PDF using classic HTML template mode
     *
     * @param string $output_mode Output mode
     * @param string $file_path Optional file path
     * @return mixed PDF output
     */
    private function generate_classic_pdf($output_mode = 'I', $file_path = '') {
        // Get paper settings from template
        $paper_size = !empty($this->template->paper_size) ? $this->template->paper_size : 'A4';
        $orientation = !empty($this->template->orientation) ? strtoupper(substr($this->template->orientation, 0, 1)) : 'L';

        // Create new PDF document
        $pdf = new TCPDF($orientation, 'mm', $paper_size, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Certificate - ' . $this->certificate['certificate_number']);
        $pdf->SetSubject('Event Certificate');
        $pdf->SetKeywords('certificate, event, ' . $this->event->title);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins to 0 for full-page design
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        // Add a page
        $pdf->AddPage();

        // Add background image if set
        if (!empty($this->template->background_image_url)) {
            $bg_url = $this->template->background_image_url;

            // Get page dimensions
            $page_width = $pdf->getPageWidth();
            $page_height = $pdf->getPageHeight();

            // Try to add background image
            $bg_path = $this->get_local_image_path($bg_url);
            if ($bg_path && file_exists($bg_path)) {
                $pdf->Image($bg_path, 0, 0, $page_width, $page_height, '', '', '', false, 300, '', false, false, 0);
            }
        }

        // Render HTML content
        $html_content = $this->render_certificate_html();

        // Add custom styles
        $css = $this->get_certificate_css();

        // Combine CSS and HTML
        $full_html = '<style>' . $css . '</style>' . $html_content;

        // Set font
        $pdf->SetFont('dejavusans', '', 12);

        // Write HTML content
        $pdf->writeHTML($full_html, true, false, true, false, '');

        // Add QR code for verification
        $this->add_qr_code($pdf);

        // Output PDF
        $filename = 'certificate-' . $this->certificate['certificate_number'] . '.pdf';

        if ($output_mode === 'F' && !empty($file_path)) {
            return $pdf->Output($file_path, 'F');
        }

        return $pdf->Output($filename, $output_mode);
    }

    /**
     * Generate PDF using visual design mode
     *
     * @param string $output_mode Output mode
     * @param string $file_path Optional file path
     * @return mixed PDF output
     */
    private function generate_visual_pdf($output_mode = 'I', $file_path = '') {
        // Parse elements config
        $config = json_decode($this->template->elements_config, true);
        if (!$config) {
            // Fallback to classic mode
            return $this->generate_classic_pdf($output_mode, $file_path);
        }

        // Get canvas dimensions from config
        $canvas_width = isset($config['canvas_width']) ? floatval($config['canvas_width']) : 1123;
        $canvas_height = isset($config['canvas_height']) ? floatval($config['canvas_height']) : 794;
        $elements = isset($config['elements']) ? $config['elements'] : array();

        // Get paper settings
        $paper_size = !empty($this->template->paper_size) ? $this->template->paper_size : 'A4';
        $orientation = !empty($this->template->orientation) ? strtoupper(substr($this->template->orientation, 0, 1)) : 'L';

        // Create new PDF document
        $pdf = new TCPDF($orientation, 'mm', $paper_size, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Certificate - ' . $this->certificate['certificate_number']);
        $pdf->SetSubject('Event Certificate');
        $pdf->SetKeywords('certificate, event, ' . $this->event->title);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins to 0 for full-page design
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        // Add a page
        $pdf->AddPage();

        // Get page dimensions in mm
        $page_width = $pdf->getPageWidth();
        $page_height = $pdf->getPageHeight();

        // Calculate scale factors (canvas pixels to PDF mm)
        $scale_x = $page_width / $canvas_width;
        $scale_y = $page_height / $canvas_height;

        // Add background image if set
        $background_image = isset($config['background_image']) ? $config['background_image'] : null;
        if (!$background_image && !empty($this->template->background_image_url)) {
            $background_image = $this->template->background_image_url;
        }

        if ($background_image) {
            $bg_path = $this->get_local_image_path($background_image);
            if ($bg_path && file_exists($bg_path)) {
                $pdf->Image($bg_path, 0, 0, $page_width, $page_height, '', '', '', false, 300, '', false, false, 0);
            }
        }

        // Get placeholder values
        $placeholders = $this->get_placeholder_values();

        // Render each element
        foreach ($elements as $element) {
            $this->render_visual_element($pdf, $element, $placeholders, $scale_x, $scale_y, $page_width, $page_height);
        }

        // Output PDF
        $filename = 'certificate-' . $this->certificate['certificate_number'] . '.pdf';

        if ($output_mode === 'F' && !empty($file_path)) {
            return $pdf->Output($file_path, 'F');
        }

        return $pdf->Output($filename, $output_mode);
    }

    /**
     * Render a single visual element to PDF
     *
     * @param TCPDF $pdf PDF instance
     * @param array $element Element configuration
     * @param array $placeholders Placeholder values
     * @param float $scale_x X scale factor
     * @param float $scale_y Y scale factor
     * @param float $page_width Page width in mm
     * @param float $page_height Page height in mm
     */
    private function render_visual_element($pdf, $element, $placeholders, $scale_x, $scale_y, $page_width, $page_height) {
        $type = isset($element['type']) ? $element['type'] : '';

        // Calculate position and size in mm
        $x = isset($element['x']) ? floatval($element['x']) * $scale_x : 0;
        $y = isset($element['y']) ? floatval($element['y']) * $scale_y : 0;
        $width = isset($element['width']) ? floatval($element['width']) * $scale_x : 50;
        $height = isset($element['height']) ? floatval($element['height']) * $scale_y : 20;

        // Get text content based on element type
        $content = $this->get_element_content($element, $placeholders);

        // Handle different element types
        switch ($type) {
            case 'qr_code':
                // Add QR code at element position
                $qr_size = min($width, $height);
                $verification_url = home_url('/certificate-verify/' . $this->certificate['verification_code'] . '/');

                $style = array(
                    'border' => false,
                    'vpadding' => 0,
                    'hpadding' => 0,
                    'fgcolor' => array(0, 0, 0),
                    'bgcolor' => array(255, 255, 255),
                );

                $pdf->write2DBarcode($verification_url, 'QRCODE,H', $x, $y, $qr_size, $qr_size, $style, 'N');
                break;

            case 'custom_image':
                if (!empty($element['imageUrl'])) {
                    $img_path = $this->get_local_image_path($element['imageUrl']);
                    if ($img_path && file_exists($img_path)) {
                        $pdf->Image($img_path, $x, $y, $width, $height, '', '', '', false, 300);
                    }
                }
                break;

            case 'line':
                // Draw a line/rectangle
                $color = !empty($element['backgroundColor']) ? $element['backgroundColor'] : '#333333';
                $rgb = $this->hex_to_rgb($color);

                $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
                $pdf->Rect($x, $y, $width, $height, 'F');
                break;

            default:
                // Text elements
                if (empty($content)) {
                    return;
                }

                // Get text styles
                $font_size = isset($element['fontSize']) ? floatval($element['fontSize']) * 0.75 : 12; // Convert px to pt (approx)
                $font_weight = isset($element['fontWeight']) ? $element['fontWeight'] : 'normal';
                $font_style = ($font_weight === 'bold') ? 'B' : '';
                $color = !empty($element['color']) ? $element['color'] : '#000000';
                $align = isset($element['textAlign']) ? strtoupper(substr($element['textAlign'], 0, 1)) : 'C';

                // Map alignment
                $align_map = array('L' => 'L', 'C' => 'C', 'R' => 'R');
                $align = isset($align_map[$align]) ? $align_map[$align] : 'C';

                // Set font
                $pdf->SetFont('dejavusans', $font_style, $font_size);

                // Set text color
                $rgb = $this->hex_to_rgb($color);
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);

                // Draw text cell
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, $height, $content, 0, 0, $align, false, '', 0, false, 'T', 'M');
                break;
        }
    }

    /**
     * Get content for an element based on its type
     *
     * @param array $element Element configuration
     * @param array $placeholders Placeholder values
     * @return string Content text
     */
    private function get_element_content($element, $placeholders) {
        $type = isset($element['type']) ? $element['type'] : '';

        switch ($type) {
            case 'attendee_name':
                return $placeholders['attendee_name'];
            case 'attendee_email':
                return $placeholders['attendee_email'];
            case 'event_title':
                return $placeholders['event_name'];
            case 'event_date':
                return $placeholders['event_date'];
            case 'event_location':
                return $placeholders['event_location'];
            case 'certificate_number':
                return $placeholders['certificate_number'];
            case 'issue_date':
                return $placeholders['issue_date'];
            case 'verification_code':
                return $placeholders['verification_code'];
            case 'organizer_name':
                return $placeholders['organizer_name'];
            case 'ticket_name':
                return $placeholders['ticket_name'];
            case 'custom_text':
                return isset($element['content']) ? $element['content'] : '';
            default:
                return '';
        }
    }

    /**
     * Convert hex color to RGB array
     *
     * @param string $hex Hex color code
     * @return array RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    /**
     * Render certificate HTML with placeholders replaced
     *
     * @return string Rendered HTML
     */
    private function render_certificate_html() {
        $html = !empty($this->template->html_template) ? $this->template->html_template : '';

        // Get all placeholder values
        $placeholders = $this->get_placeholder_values();

        // Replace placeholders
        foreach ($placeholders as $placeholder => $value) {
            $html = str_replace('{{' . $placeholder . '}}', $value, $html);
        }

        return $html;
    }

    /**
     * Get certificate CSS
     *
     * @return string CSS styles
     */
    private function get_certificate_css() {
        $css = !empty($this->template->css_styles) ? $this->template->css_styles : '';

        // Add default styles for PDF rendering
        $default_css = '
            body {
                font-family: dejavusans, sans-serif;
                margin: 0;
                padding: 0;
            }
            .certificate-container {
                text-align: center;
                padding: 20mm;
            }
            .certificate-title {
                font-size: 36pt;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 10mm;
            }
            .certificate-subtitle {
                font-size: 18pt;
                color: #7f8c8d;
                margin-bottom: 15mm;
            }
            .attendee-name {
                font-size: 28pt;
                font-weight: bold;
                color: #e74c3c;
                margin: 10mm 0;
            }
            .event-name {
                font-size: 20pt;
                color: #2c3e50;
                margin: 5mm 0;
            }
            .event-date {
                font-size: 14pt;
                color: #7f8c8d;
            }
            .certificate-number {
                font-size: 10pt;
                color: #95a5a6;
                margin-top: 15mm;
            }
        ';

        return $default_css . "\n" . $css;
    }

    /**
     * Get all placeholder values
     *
     * @return array Placeholder => Value pairs
     */
    private function get_placeholder_values() {
        $event = $this->event;
        $attendee = $this->attendee;
        $certificate = $this->certificate;

        // Format dates
        $event_date = '';
        if (!empty($event->start_date)) {
            $event_date = date_i18n(get_option('date_format'), strtotime($event->start_date));
            if (!empty($event->end_date) && $event->end_date !== $event->start_date) {
                $event_date .= ' - ' . date_i18n(get_option('date_format'), strtotime($event->end_date));
            }
        }

        $issue_date = !empty($certificate['issued_at'])
            ? date_i18n(get_option('date_format'), strtotime($certificate['issued_at']))
            : date_i18n(get_option('date_format'));

        // Get organizer name
        $organizer_name = '';
        if (!empty($event->organizer_id)) {
            global $wpdb;
            $organizer = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}sc_organizers WHERE id = %d",
                $event->organizer_id
            ));
            if ($organizer) {
                $organizer_name = $organizer->name;
            }
        }

        // Get venue name
        $venue_name = !empty($event->venue_name) ? $event->venue_name : '';

        // Build verification URL
        $verification_url = home_url('/certificate-verify/' . $certificate['verification_code'] . '/');

        return array(
            // Attendee placeholders
            'attendee_name' => $attendee->name,
            'attendee_email' => $attendee->email,
            'attendee_phone' => !empty($attendee->phone) ? $attendee->phone : '',
            'ticket_name' => !empty($attendee->ticket_name) ? $attendee->ticket_name : '',

            // Event placeholders
            'event_name' => $event->title,
            'event_date' => $event_date,
            'event_start_date' => !empty($event->start_date) ? date_i18n(get_option('date_format'), strtotime($event->start_date)) : '',
            'event_end_date' => !empty($event->end_date) ? date_i18n(get_option('date_format'), strtotime($event->end_date)) : '',
            'event_location' => $venue_name,
            'event_venue' => $venue_name,
            'organizer_name' => $organizer_name,

            // Certificate placeholders
            'certificate_number' => $certificate['certificate_number'],
            'issue_date' => $issue_date,
            'verification_code' => $certificate['verification_code'],
            'verification_url' => $verification_url,
            'verification_qr' => '', // QR is added separately

            // Platform placeholders
            'platform_name' => get_bloginfo('name'),
            'platform_url' => home_url(),
            'current_date' => date_i18n(get_option('date_format')),
            'current_year' => date('Y'),
        );
    }

    /**
     * Add QR code to PDF
     *
     * @param TCPDF $pdf PDF instance
     */
    private function add_qr_code($pdf) {
        // Build verification URL
        $verification_url = home_url('/certificate-verify/' . $this->certificate['verification_code'] . '/');

        // Get page dimensions
        $page_width = $pdf->getPageWidth();
        $page_height = $pdf->getPageHeight();

        // QR code size and position (bottom right corner)
        $qr_size = 25;
        $margin = 10;
        $x = $page_width - $qr_size - $margin;
        $y = $page_height - $qr_size - $margin;

        // Add QR code
        $style = array(
            'border' => false,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
            'module_width' => 1,
            'module_height' => 1
        );

        $pdf->write2DBarcode($verification_url, 'QRCODE,H', $x, $y, $qr_size, $qr_size, $style, 'N');
    }

    /**
     * Get local path for image URL
     *
     * @param string $url Image URL
     * @return string|false Local file path or false
     */
    private function get_local_image_path($url) {
        // If it's already a local path
        if (file_exists($url)) {
            return $url;
        }

        // Try to convert URL to local path
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $upload_path = $upload_dir['basedir'];

        if (strpos($url, $upload_url) === 0) {
            $local_path = str_replace($upload_url, $upload_path, $url);
            if (file_exists($local_path)) {
                return $local_path;
            }
        }

        // Try site URL
        $site_url = site_url();
        $site_path = ABSPATH;

        if (strpos($url, $site_url) === 0) {
            $local_path = str_replace($site_url, $site_path, $url);
            if (file_exists($local_path)) {
                return $local_path;
            }
        }

        return false;
    }

    /**
     * Static method to generate PDF for a certificate
     *
     * @param int $certificate_id Certificate ID
     * @param string $output_mode Output mode
     * @return mixed PDF output
     */
    /**
     * Show a template as a PDF filled with sample values, without issuing anything.
     *
     * @param int $template_id Template ID
     */
    public static function preview_template($template_id) {
        $template = SC_Certificate_Template::get($template_id);
        if (!$template) {
            wp_die(esc_html__('Certificate template not found.', 'sc_events'));
        }
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sc_events WHERE certificate_template_id = %d ORDER BY start_date DESC LIMIT 1",
            (int) $template_id
        ));
        $generator = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $generator->template = $template;
        $generator->event = $event ?: (object) array('title' => 'Sample Event', 'start_date' => current_time('Y-m-d'), 'end_date' => current_time('Y-m-d'), 'venue_name' => 'Cairo', 'organizer_id' => 0);
        $generator->attendee = (object) array('name' => 'Dr. Sample Attendee Name', 'email' => 'attendee@example.com', 'phone' => '', 'ticket_name' => 'General');
        $generator->certificate = array(
            'id'                 => 0,
            'certificate_number' => 'CERT-' . current_time('Y') . '-SAMPLE',
            'verification_code'  => 'SAMPLE000000',
            'issued_at'          => current_time('mysql'),
            'event_id'           => $event ? (int) $event->id : 0,
            'template_id'        => (int) $template_id,
        );
        try {
            $generator->generate('I');
        } catch (Exception $e) {
            wp_die(esc_html($e->getMessage()));
        }
        exit;
    }

    public static function generate_pdf($certificate_id, $output_mode = 'I') {
        $generator = new self($certificate_id);
        return $generator->generate($output_mode);
    }

    /**
     * Stream PDF to browser for download
     *
     * @param int $certificate_id Certificate ID
     */
    public static function download($certificate_id) {
        try {
            $generator = new self($certificate_id);
            $generator->generate('D');
            exit;
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }

    /**
     * Display PDF inline in browser
     *
     * @param int $certificate_id Certificate ID
     */
    public static function inline($certificate_id) {
        try {
            $generator = new self($certificate_id);
            $generator->generate('I');
            exit;
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }

    /**
     * Get PDF as base64 string
     *
     * @param int $certificate_id Certificate ID
     * @return string Base64 encoded PDF
     */
    public static function get_base64($certificate_id) {
        try {
            $generator = new self($certificate_id);
            $pdf_content = $generator->generate('S');
            return base64_encode($pdf_content);
        } catch (Exception $e) {
            return '';
        }
    }
}
