<?php
/**
 * Badge PDF Generator using TCPDF
 *
 * Generates conference-style badge/lanyard cards with QR codes
 * Supports multiple designs, badge types, and print layouts
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/vendor/autoload.php';

class SC_Badge_PDF {

    private $config;
    private $event;
    private $badges;
    private $design;
    private $badge_width;
    private $badge_height;
    private $primary_color;
    private $logo_path;
    private $measure;

    // Badge dimensions in mm
    const SIZE_STANDARD = array(101.6, 76.2);  // 4" x 3"
    const SIZE_ID_CARD  = array(85.6, 54.0);   // Standard ID card

    // A4 dimensions
    const A4_WIDTH  = 210;
    const A4_HEIGHT = 297;

    // Type colors (RGB arrays)
    const TYPE_COLORS = array(
        'attendee'  => array(52, 152, 219),
        'vip'       => array(243, 156, 18),
        'speaker'   => array(46, 204, 113),
        'organizer' => array(155, 89, 182),
        'exhibitor' => array(22, 160, 133),
    );

    // Type labels
    const TYPE_LABELS = array(
        'attendee'  => 'ATTENDEE',
        'vip'       => 'VIP',
        'speaker'   => 'SPEAKER',
        'organizer' => 'ORGANIZER',
        'exhibitor' => 'EXHIBITOR',
    );

    public function __construct($config) {
        $this->config = $config;
        $this->event = $config['event'];
        $this->badges = $config['badges'];
        $this->design = $config['design'] ?? 'corporate';
        $this->primary_color = $this->hex_to_rgb($config['primary_color'] ?? '#1a73e8');

        // Set badge dimensions
        if (($config['badge_size'] ?? 'standard') === 'id_card') {
            $this->badge_width = self::SIZE_ID_CARD[0];
            $this->badge_height = self::SIZE_ID_CARD[1];
        } else {
            $this->badge_width = self::SIZE_STANDARD[0];
            $this->badge_height = self::SIZE_STANDARD[1];
        }

        // Resolve logo path
        $this->logo_path = '';
        if (!empty($config['logo_url'])) {
            $this->logo_path = $this->get_local_image_path($config['logo_url']);
        }
    }

    /**
     * Generate PDF
     *
     * @param string $output_mode 'D' for download, 'I' for inline, 'F' for file, 'S' for string
     * @param string $filepath File path for 'F' mode
     */
    public function generate($output_mode = 'D', $filepath = '') {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Badges - ' . ($this->event['title'] ?? 'Event'));

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $layout_mode = $this->config['layout_mode'] ?? 'grid';

        if ($layout_mode === 'single') {
            $this->render_single_layout($pdf);
        } else {
            $this->render_grid_layout($pdf);
        }

        $filename = 'badges-' . sanitize_title($this->event['title'] ?? 'event') . (!empty($this->config['part']) ? '-part-' . (int) $this->config['part'] : '') . '.pdf';

        if ($output_mode === 'F' && !empty($filepath)) {
            return $pdf->Output($filepath, 'F');
        }

        return $pdf->Output($filename, $output_mode);
    }

    /**
     * Grid layout: 2 columns x 3 rows = 6 badges per A4 page
     */
    private function render_grid_layout($pdf) {
        $gap = 3;
        $col_count = 2;
        $row_count = 3;
        $badges_per_page = $col_count * $row_count;

        $total_width = $col_count * $this->badge_width + ($col_count - 1) * $gap;
        $total_height = $row_count * $this->badge_height + ($row_count - 1) * $gap;
        $margin_x = (self::A4_WIDTH - $total_width) / 2;
        $margin_y = (self::A4_HEIGHT - $total_height) / 2;

        foreach (array_chunk($this->badges, $badges_per_page) as $page_badges) {
            $pdf->AddPage();

            // Draw cut lines
            $this->draw_cut_lines($pdf, $margin_x, $margin_y, $gap, $col_count, $row_count);

            foreach ($page_badges as $index => $badge) {
                $row = floor($index / $col_count);
                $col = $index % $col_count;

                $x = $margin_x + $col * ($this->badge_width + $gap);
                $y = $margin_y + $row * ($this->badge_height + $gap);

                $this->render_badge($pdf, $badge, $x, $y);
            }
        }
    }

    /**
     * Single layout: 1 badge centered per page
     */
    private function render_single_layout($pdf) {
        foreach ($this->badges as $badge) {
            $pdf->AddPage();

            $x = (self::A4_WIDTH - $this->badge_width) / 2;
            $y = (self::A4_HEIGHT - $this->badge_height) / 2;

            $this->render_badge($pdf, $badge, $x, $y);
        }
    }

    /**
     * Draw cut guide lines on page
     */
    private function draw_cut_lines($pdf, $margin_x, $margin_y, $gap, $col_count, $row_count) {
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineStyle(array('dash' => '2,2', 'width' => 0.15, 'color' => array(200, 200, 200)));

        $line_ext = 5; // Extension beyond badge area

        // Vertical cut lines
        for ($c = 0; $c <= $col_count; $c++) {
            $lx = $margin_x + $c * ($this->badge_width + $gap) - $gap / 2;
            if ($c === 0) $lx = $margin_x;
            if ($c === $col_count) $lx = $margin_x + $col_count * $this->badge_width + ($col_count - 1) * $gap;

            $pdf->Line(
                $lx,
                $margin_y - $line_ext,
                $lx,
                $margin_y + $row_count * $this->badge_height + ($row_count - 1) * $gap + $line_ext
            );
        }

        // Horizontal cut lines
        for ($r = 0; $r <= $row_count; $r++) {
            $ly = $margin_y + $r * ($this->badge_height + $gap) - $gap / 2;
            if ($r === 0) $ly = $margin_y;
            if ($r === $row_count) $ly = $margin_y + $row_count * $this->badge_height + ($row_count - 1) * $gap;

            $pdf->Line(
                $margin_x - $line_ext,
                $ly,
                $margin_x + $col_count * $this->badge_width + ($col_count - 1) * $gap + $line_ext,
                $ly
            );
        }

        // Reset line style
        $pdf->SetLineStyle(array('dash' => 0, 'width' => 0.2));
    }

    /**
     * Render a single badge - dispatches to design method
     */
    private function render_badge($pdf, $badge, $x, $y) {
        $w = $this->badge_width;
        $h = $this->badge_height;

        // Light border
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineStyle(array('dash' => 0, 'width' => 0.2, 'color' => array(220, 220, 220)));
        $pdf->Rect($x, $y, $w, $h, 'D');

        // Get type color
        $type = $badge['type'] ?? 'attendee';
        $type_color = self::TYPE_COLORS[$type] ?? self::TYPE_COLORS['attendee'];

        switch ($this->design) {
            case 'modern':
                $this->render_design_modern($pdf, $badge, $x, $y, $w, $h, $type_color);
                break;
            case 'elegant':
                $this->render_design_elegant($pdf, $badge, $x, $y, $w, $h, $type_color);
                break;
            case 'corporate':
            default:
                $this->render_design_corporate($pdf, $badge, $x, $y, $w, $h, $type_color);
                break;
        }
    }

    // =========================================================================
    // DESIGN 1: CORPORATE - Clean professional with top color bar
    // =========================================================================
    private function render_design_corporate($pdf, $badge, $x, $y, $w, $h, $type_color) {
        $bar_height = 8;

        // Top color bar
        $pdf->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $pdf->Rect($x, $y, $w, $bar_height, 'F');

        // Logo in color bar
        if ($this->logo_path) {
            $logo_h = $bar_height - 2;
            $this->safe_image($pdf, $this->logo_path, $x + 3, $y + 1, 0, $logo_h);
        }

        // Person name - centered
        $name = $badge['name'] ?? '';
        $name_y = $y + $bar_height + ($h - $bar_height) * 0.2;
        $font_size = $this->calculate_font_size($name, $w - 10, 16, 10);
        $pdf->SetFont('dejavusans', 'B', $font_size);
        $pdf->SetTextColor(33, 33, 33);
        $pdf->SetXY($x + 2, $name_y);
        $pdf->Cell($w - 4, 7, $name, 0, 0, 'C');

        // Subtitle
        $subtitle = $badge['subtitle'] ?? '';
        if (!empty($subtitle)) {
            $sub_size = min(9, $font_size - 3);
            $pdf->SetFont('dejavusans', '', $sub_size);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($x + 2, $name_y + 8);
            $pdf->Cell($w - 4, 5, $subtitle, 0, 0, 'C');
        }

        // Type badge (bottom-left)
        $type = $badge['type'] ?? 'attendee';
        $label = self::TYPE_LABELS[$type] ?? 'ATTENDEE';
        $label_y = $y + $h - 18;

        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetFillColor($type_color[0], $type_color[1], $type_color[2]);
        $pdf->SetTextColor(255, 255, 255);
        $label_w = max($pdf->GetStringWidth($label) + 6, 22);
        $pdf->SetXY($x + 5, $label_y);
        $pdf->Cell($label_w, 5.5, $label, 0, 0, 'C', true);

        // QR Code (bottom-right)
        $this->render_qr_code($pdf, $badge, $x + $w - 20, $y + $h - 20, 16);

        // Event name at bottom
        $this->render_event_name($pdf, $x, $y + $h - 6, $w);
    }

    // =========================================================================
    // DESIGN 2: MODERN - Left accent strip, asymmetric layout
    // =========================================================================
    private function render_design_modern($pdf, $badge, $x, $y, $w, $h, $type_color) {
        $strip_width = 6;

        // Left color strip using type color
        $pdf->SetFillColor($type_color[0], $type_color[1], $type_color[2]);
        $pdf->Rect($x, $y, $strip_width, $h, 'F');

        // Content area starts after strip
        $cx = $x + $strip_width + 4;
        $cw = $w - $strip_width - 8;

        // Person name - left aligned
        $name = $badge['name'] ?? '';
        $name_y = $y + $h * 0.18;
        $font_size = $this->calculate_font_size($name, $cw, 15, 10);
        $pdf->SetFont('dejavusans', 'B', $font_size);
        $pdf->SetTextColor(33, 33, 33);
        $pdf->SetXY($cx, $name_y);
        $pdf->Cell($cw, 7, $name, 0, 0, 'L');

        // Subtitle
        $subtitle = $badge['subtitle'] ?? '';
        if (!empty($subtitle)) {
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($cx, $name_y + 8);
            $pdf->Cell($cw, 5, $subtitle, 0, 0, 'L');
        }

        // Detail (ticket name)
        $detail = $badge['detail'] ?? '';
        if (!empty($detail)) {
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY($cx, $name_y + 14);
            $pdf->Cell($cw, 4, $detail, 0, 0, 'L');
        }

        // Type badge (bottom-left of content area)
        $type = $badge['type'] ?? 'attendee';
        $label = self::TYPE_LABELS[$type] ?? 'ATTENDEE';
        $label_y = $y + $h - 18;

        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetFillColor($type_color[0], $type_color[1], $type_color[2]);
        $pdf->SetTextColor(255, 255, 255);
        $label_w = max($pdf->GetStringWidth($label) + 6, 20);
        $pdf->SetXY($cx, $label_y);
        $pdf->Cell($label_w, 5, $label, 0, 0, 'C', true);

        // QR Code (bottom-right)
        $this->render_qr_code($pdf, $badge, $x + $w - 20, $y + $h - 20, 16);

        // Logo (bottom-right, above QR if space)
        if ($this->logo_path) {
            $this->safe_image($pdf, $this->logo_path, $x + $w - 18, $y + 3, 0, 6);
        }

        // Event name at bottom
        $this->render_event_name($pdf, $cx, $y + $h - 6, $cw - 18);
    }

    // =========================================================================
    // DESIGN 3: ELEGANT - Full color background, white text, photo circle
    // =========================================================================
    private function render_design_elegant($pdf, $badge, $x, $y, $w, $h, $type_color) {
        // Full background fill
        $pdf->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $pdf->Rect($x, $y, $w, $h, 'F');

        // Photo or initials circle
        $circle_r = min($w, $h) * 0.13;
        $circle_cx = $x + $w / 2;
        $circle_cy = $y + $h * 0.25;

        $photo_path = '';
        if (!empty($badge['photo_url'])) {
            $photo_path = $this->get_local_image_path($badge['photo_url']);
        }

        $photo_drawn = false;
        if ($photo_path && file_exists($photo_path)) {
            // Clip circle for photo
            $pdf->StartTransform();
            $pdf->Circle($circle_cx, $circle_cy, $circle_r, 0, 360, '', array(), array(255, 255, 255));
            $img_size = $circle_r * 2;
            $photo_drawn = $this->safe_image($pdf, $photo_path, $circle_cx - $circle_r, $circle_cy - $circle_r, $img_size, $img_size, 'CM');
            $pdf->StopTransform();

            if ($photo_drawn) {
                // White circle border
                $pdf->SetDrawColor(255, 255, 255);
                $pdf->SetLineStyle(array('width' => 0.8, 'color' => array(255, 255, 255)));
                $pdf->Circle($circle_cx, $circle_cy, $circle_r);
            }
        }
        if (!$photo_drawn) {
            // Initials circle
            $this->render_initials_circle($pdf, $badge['name'] ?? '', $circle_cx, $circle_cy, $circle_r);
        }

        // Person name - white, centered
        $name = $badge['name'] ?? '';
        $name_y = $circle_cy + $circle_r + 3;
        $font_size = $this->calculate_font_size($name, $w - 10, 14, 9);
        $pdf->SetFont('dejavusans', 'B', $font_size);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x + 2, $name_y);
        $pdf->Cell($w - 4, 6, $name, 0, 0, 'C');

        // Subtitle - semi-transparent white
        $subtitle = $badge['subtitle'] ?? '';
        if (!empty($subtitle)) {
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x + 2, $name_y + 7);
            $pdf->Cell($w - 4, 4, $subtitle, 0, 0, 'C');
        }

        // Type badge with white background (bottom-left)
        $type = $badge['type'] ?? 'attendee';
        $label = self::TYPE_LABELS[$type] ?? 'ATTENDEE';
        $label_y = $y + $h - 16;

        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor($type_color[0], $type_color[1], $type_color[2]);
        $label_w = max($pdf->GetStringWidth($label) + 6, 20);
        $pdf->SetXY($x + 5, $label_y);
        $pdf->Cell($label_w, 5, $label, 0, 0, 'C', true);

        // QR Code with white background (bottom-right)
        $qr_size = 14;
        $qr_x = $x + $w - $qr_size - 5;
        $qr_y = $y + $h - $qr_size - 5;

        if (($this->config['include_qr'] ?? true) && !empty($badge['qr_data'])) {
            // White background for QR
            $pdf->SetFillColor(255, 255, 255);
            $qr_pad = 1.5;
            $pdf->Rect($qr_x - $qr_pad, $qr_y - $qr_pad, $qr_size + 2 * $qr_pad, $qr_size + 2 * $qr_pad, 'F');

            $style = array(
                'border' => false,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => array(255, 255, 255),
            );
            $pdf->write2DBarcode($badge['qr_data'], 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, $style, 'N');
        }

        // Event name at bottom - white
        if ($this->config['include_event'] ?? true) {
            $event_title = $this->event['title'] ?? '';
            if (!empty($event_title)) {
                $pdf->SetFont('dejavusans', '', 6);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY($x, $y + $h - 5);
                $pdf->Cell($w, 3, $event_title, 0, 0, 'C');
            }
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Render QR code on badge
     */
    private function render_qr_code($pdf, $badge, $qr_x, $qr_y, $qr_size) {
        if (!($this->config['include_qr'] ?? true)) return;
        if (empty($badge['qr_data'])) return;

        $style = array(
            'border' => false,
            'vpadding' => 0,
            'hpadding' => 0,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
        );
        $pdf->write2DBarcode($badge['qr_data'], 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, $style, 'N');
    }

    /**
     * Render event name at bottom of badge
     */
    private function render_event_name($pdf, $x, $y, $w) {
        if (!($this->config['include_event'] ?? true)) return;

        $event_title = $this->event['title'] ?? '';
        if (empty($event_title)) return;

        $pdf->SetFont('dejavusans', '', 6);
        $pdf->SetTextColor(160, 160, 160);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 3, $event_title, 0, 0, 'C');
    }

    /**
     * Render initials circle (when no photo available)
     */
    private function render_initials_circle($pdf, $name, $cx, $cy, $radius) {
        $initials = '';
        $parts = explode(' ', trim($name));
        foreach (array_slice($parts, 0, 2) as $part) {
            $char = mb_strtoupper(mb_substr(trim($part), 0, 1, 'UTF-8'), 'UTF-8');
            if (!empty($char)) {
                $initials .= $char;
            }
        }
        if (empty($initials)) $initials = '?';

        // White circle with slight transparency
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Circle($cx, $cy, $radius, 0, 360, 'F');

        // Initials text
        $pdf->SetFont('dejavusans', 'B', $radius * 0.9);
        $pdf->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $text_w = $pdf->GetStringWidth($initials);
        $pdf->SetXY($cx - $text_w / 2, $cy - $radius * 0.35);
        $pdf->Cell($text_w, $radius * 0.7, $initials, 0, 0, 'C');
    }

    /**
     * Calculate font size to fit text in given width
     */
    private function calculate_font_size($text, $max_width, $max_size, $min_size) {
        // One measuring document for the whole job; a new TCPDF per badge made big jobs crawl.
        if (!$this->measure) {
            $this->measure = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $this->measure->setPrintHeader(false);
            $this->measure->setPrintFooter(false);
        }
        $pdf_temp = $this->measure;

        for ($size = $max_size; $size >= $min_size; $size--) {
            $pdf_temp->SetFont('dejavusans', 'B', $size);
            if ($pdf_temp->GetStringWidth($text) <= $max_width) {
                return $size;
            }
        }

        return $min_size;
    }

    /**
     * Convert hex color to RGB array
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Safely place an image on the PDF, handling PNG alpha channel issues
     */
    private function safe_image($pdf, $path, $x, $y, $w = 0, $h = 0, $fit = '') {
        if (empty($path) || !file_exists($path)) return false;

        // If PNG with alpha channel, try to flatten it
        $path = $this->flatten_png_alpha($path);
        if (empty($path)) return false;

        try {
            if (!empty($fit)) {
                $pdf->Image($path, $x, $y, $w, $h, '', '', '', true, 300, '', false, false, 0, $fit);
            } else {
                $pdf->Image($path, $x, $y, $w, $h);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Flatten PNG alpha channel to avoid TCPDF GD requirement
     * Returns path to a safe image (original or converted)
     */
    private function flatten_png_alpha($path) {
        if (empty($path) || !file_exists($path)) return '';

        $info = @getimagesize($path);
        if (!$info) return '';

        // Not a PNG - safe to use directly
        if ($info[2] !== IMAGETYPE_PNG) return $path;

        // Check if PNG has alpha channel (color type 4 or 6)
        $has_alpha = false;
        $fp = @fopen($path, 'rb');
        if ($fp) {
            // Read the IHDR chunk to check color type
            $header = @fread($fp, 26);
            if (strlen($header) >= 26) {
                $color_type = ord($header[25]);
                $has_alpha = ($color_type === 4 || $color_type === 6);
            }
            fclose($fp);
        }

        if (!$has_alpha) return $path;

        // Has alpha - try to flatten with GD
        if (!function_exists('imagecreatefrompng')) {
            // No GD available, skip this image
            return '';
        }

        $src = @imagecreatefrompng($path);
        if (!$src) return '';

        $width = imagesx($src);
        $height = imagesy($src);

        $dst = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);
        imagedestroy($src);

        // Save as JPEG to temp file
        $temp_dir = get_temp_dir();
        $temp_file = $temp_dir . 'sc_badge_img_' . md5($path) . '.jpg';
        imagejpeg($dst, $temp_file, 90);
        imagedestroy($dst);

        if (file_exists($temp_file)) {
            return $temp_file;
        }

        return '';
    }

    /**
     * Get local file path from URL
     */
    private function get_local_image_path($url) {
        if (empty($url)) return '';

        if (file_exists($url)) {
            return $url;
        }

        // Try wp_upload_dir based resolution
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $upload_path = $upload_dir['basedir'];

        if (strpos($url, $upload_url) === 0) {
            $local_path = str_replace($upload_url, $upload_path, $url);
            $local_path = str_replace('/', DIRECTORY_SEPARATOR, $local_path);
            if (file_exists($local_path)) {
                return $local_path;
            }
        }

        // Try site_url based resolution
        $site_url = site_url();
        $site_path = ABSPATH;

        if (strpos($url, $site_url) === 0) {
            $local_path = str_replace($site_url, $site_path, $url);
            $local_path = str_replace('/', DIRECTORY_SEPARATOR, $local_path);
            if (file_exists($local_path)) {
                return $local_path;
            }
        }

        // Try WordPress attachment ID lookup
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            $local_path = get_attached_file($attachment_id);
            if ($local_path && file_exists($local_path)) {
                return $local_path;
            }
        }

        // Try extracting path from URL directly (handles localhost/XAMPP)
        $parsed = parse_url($url);
        if (!empty($parsed['path'])) {
            // e.g. /events/wp-content/uploads/2026/02/logo.png -> C:\xampp\htdocs\events\wp-content\uploads\2026\02\logo.png
            $doc_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
            if ($doc_root) {
                $local_path = $doc_root . $parsed['path'];
                $local_path = str_replace('/', DIRECTORY_SEPARATOR, $local_path);
                if (file_exists($local_path)) {
                    return $local_path;
                }
            }
        }

        return '';
    }
}
