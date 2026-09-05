<?php
/**
 * Dashboard Visual Certificate Builder Page
 * Drag & Drop Certificate Designer
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.visual_certificate_builder', 'Visual Certificate Builder'),
    'certificates' => sc_t('dashboard_pages.certificates', 'Certificates'),
    'visual_builder' => sc_t('dashboard_pages.visual_builder', 'Visual Builder'),
    'preview' => sc_t('dashboard_pages.preview', 'Preview'),
    'save_template' => sc_t('dashboard_pages.save_template', 'Save Template'),
    'elements' => sc_t('dashboard_pages.elements', 'Elements'),
    'template' => sc_t('dashboard_pages.template', 'Template'),
    'edit_template' => sc_t('dashboard_pages.edit_template', 'Edit Template'),
    'new_template' => sc_t('dashboard_pages.new_template', '-- New Template --'),
    'template_name' => sc_t('dashboard_pages.template_name', 'Template Name'),
    'my_certificate' => sc_t('dashboard_pages.my_certificate', 'My Certificate'),
    'design_canvas' => sc_t('dashboard_pages.design_canvas', 'Design Canvas'),
    'upload_background_text' => sc_t('dashboard_pages.upload_background_text', 'Upload a background image'),
    'upload_background' => sc_t('dashboard_pages.upload_background', 'Upload Background'),
    'canvas_settings' => sc_t('dashboard_pages.canvas_settings', 'Canvas Settings'),
    'paper_size' => sc_t('dashboard_pages.paper_size', 'Paper Size'),
    'orientation' => sc_t('dashboard_pages.orientation', 'Orientation'),
    'landscape' => sc_t('dashboard_pages.landscape', 'Landscape'),
    'portrait' => sc_t('dashboard_pages.portrait', 'Portrait'),
    'background' => sc_t('dashboard_pages.background', 'Background'),
    'image_url_placeholder' => sc_t('dashboard_pages.image_url_placeholder', 'Image URL or upload'),
    'element_properties' => sc_t('dashboard_pages.element_properties', 'Element Properties'),
    'delete_element' => sc_t('dashboard_pages.delete_element', 'Delete Element'),
    'element_type' => sc_t('dashboard_pages.element_type', 'Element Type'),
    'content' => sc_t('dashboard_pages.content', 'Content'),
    'image' => sc_t('dashboard_pages.image', 'Image'),
    'x_position' => sc_t('dashboard_pages.x_position', 'X Position'),
    'y_position' => sc_t('dashboard_pages.y_position', 'Y Position'),
    'width' => sc_t('dashboard_pages.width', 'Width'),
    'height' => sc_t('dashboard_pages.height', 'Height'),
    'font_size' => sc_t('dashboard_pages.font_size', 'Font Size (px)'),
    'font_family' => sc_t('dashboard_pages.font_family', 'Font Family'),
    'font_weight' => sc_t('dashboard_pages.font_weight', 'Font Weight'),
    'normal' => sc_t('dashboard_pages.normal', 'Normal'),
    'bold' => sc_t('dashboard_pages.bold', 'Bold'),
    'light' => sc_t('dashboard_pages.light', 'Light'),
    'text_color' => sc_t('dashboard_pages.text_color', 'Text Color'),
    'text_align' => sc_t('dashboard_pages.text_align', 'Text Align'),
    'bg_color' => sc_t('dashboard_pages.bg_color', 'Background Color'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    // Element labels
    'attendee_name' => sc_t('dashboard_pages.el_attendee_name', 'Attendee Name'),
    'attendee_email' => sc_t('dashboard_pages.el_attendee_email', 'Attendee Email'),
    'event_title' => sc_t('dashboard_pages.el_event_title', 'Event Title'),
    'event_date' => sc_t('dashboard_pages.el_event_date', 'Event Date'),
    'event_location' => sc_t('dashboard_pages.el_event_location', 'Event Location'),
    'certificate_number' => sc_t('dashboard_pages.el_certificate_number', 'Certificate Number'),
    'issue_date' => sc_t('dashboard_pages.el_issue_date', 'Issue Date'),
    'verification_code' => sc_t('dashboard_pages.el_verification_code', 'Verification Code'),
    'qr_code' => sc_t('dashboard_pages.el_qr_code', 'QR Code'),
    'organizer_name' => sc_t('dashboard_pages.el_organizer_name', 'Organizer Name'),
    'ticket_type' => sc_t('dashboard_pages.el_ticket_type', 'Ticket Type'),
    'custom_text' => sc_t('dashboard_pages.el_custom_text', 'Custom Text'),
    'custom_image' => sc_t('dashboard_pages.el_custom_image', 'Custom Image'),
    'decorative_line' => sc_t('dashboard_pages.el_decorative_line', 'Decorative Line'),
    // Quick Help
    'quick_help' => sc_t('dashboard_pages.quick_help', 'Quick Help'),
    'help_drag_elements' => sc_t('dashboard_pages.help_drag_elements', 'Drag elements from left panel'),
    'help_reposition' => sc_t('dashboard_pages.help_reposition', 'Drag to reposition on canvas'),
    'help_resize' => sc_t('dashboard_pages.help_resize', 'Drag corners to resize'),
    'help_click_edit' => sc_t('dashboard_pages.help_click_edit', 'Click to select & edit'),
    'help_delete' => sc_t('dashboard_pages.help_delete', 'Press Delete to remove'),
    // Modals
    'certificate_preview' => sc_t('dashboard_pages.certificate_preview', 'Certificate Preview'),
    'close' => sc_t('dashboard_pages.close', 'Close'),
    'download_sample_pdf' => sc_t('dashboard_pages.download_sample_pdf', 'Download Sample PDF'),
    'save_certificate_template' => sc_t('dashboard_pages.save_certificate_template', 'Save Certificate Template'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'set_as_default' => sc_t('dashboard_pages.set_as_default', 'Set as Default Template'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // JavaScript messages
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'save_success' => sc_t('dashboard_pages.save_success', 'Template saved successfully!'),
    'save_error' => sc_t('dashboard_pages.save_error', 'Failed to save template'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'error' => sc_t('dashboard_pages.error', 'Error'),
    'confirm_delete_element' => sc_t('dashboard_pages.confirm_delete_element', 'Are you sure you want to delete this element?'),
    'template_name_required' => sc_t('dashboard_pages.template_name_required', 'Please enter a template name'),
    'uploading' => sc_t('dashboard_pages.uploading', 'Uploading...'),
    'upload_success' => sc_t('dashboard_pages.upload_success', 'Image uploaded successfully'),
    'upload_error' => sc_t('dashboard_pages.upload_error', 'Failed to upload image'),
    'generating_preview' => sc_t('dashboard_pages.generating_preview', 'Generating preview...'),
    'preview_error' => sc_t('dashboard_pages.preview_error', 'Failed to generate preview'),
    'downloading_pdf' => sc_t('dashboard_pages.downloading_pdf', 'Downloading PDF...'),
    'download_error' => sc_t('dashboard_pages.download_error', 'Failed to download PDF'),
    'no_elements' => sc_t('dashboard_pages.no_elements', 'No elements added yet'),
    'drop_here' => sc_t('dashboard_pages.drop_here', 'Drop element here'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Check if editing existing template
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
$template = null;
$elements_config = null;

if ($template_id) {
    $template = SC_Certificate_Template::get($template_id);
    if ($template && !empty($template->elements_config)) {
        $elements_config = json_decode($template->elements_config, true);
    }
}

// Get all templates for the dropdown
$templates = SC_Certificate_Template::get_all(array('is_active' => 1));

// Available element types (widgets)
$element_types = array(
    'attendee_name' => array(
        'label' => $t['attendee_name'],
        'icon' => 'fa-user',
        'placeholder' => '{attendee_name}',
        'default_style' => array(
            'fontSize' => 32,
            'fontWeight' => 'bold',
            'color' => '#000000',
            'textAlign' => 'center'
        )
    ),
    'attendee_email' => array(
        'label' => $t['attendee_email'],
        'icon' => 'fa-envelope',
        'placeholder' => '{attendee_email}',
        'default_style' => array(
            'fontSize' => 14,
            'color' => '#666666'
        )
    ),
    'event_title' => array(
        'label' => $t['event_title'],
        'icon' => 'fa-calendar',
        'placeholder' => '{event_title}',
        'default_style' => array(
            'fontSize' => 24,
            'fontWeight' => 'bold',
            'color' => '#333333'
        )
    ),
    'event_date' => array(
        'label' => $t['event_date'],
        'icon' => 'fa-clock-o',
        'placeholder' => '{event_date}',
        'default_style' => array(
            'fontSize' => 16,
            'color' => '#666666'
        )
    ),
    'event_location' => array(
        'label' => $t['event_location'],
        'icon' => 'fa-map-marker',
        'placeholder' => '{event_location}',
        'default_style' => array(
            'fontSize' => 14,
            'color' => '#666666'
        )
    ),
    'certificate_number' => array(
        'label' => $t['certificate_number'],
        'icon' => 'fa-hashtag',
        'placeholder' => '{certificate_number}',
        'default_style' => array(
            'fontSize' => 12,
            'color' => '#999999'
        )
    ),
    'issue_date' => array(
        'label' => $t['issue_date'],
        'icon' => 'fa-calendar-check-o',
        'placeholder' => '{issue_date}',
        'default_style' => array(
            'fontSize' => 12,
            'color' => '#666666'
        )
    ),
    'verification_code' => array(
        'label' => $t['verification_code'],
        'icon' => 'fa-key',
        'placeholder' => '{verification_code}',
        'default_style' => array(
            'fontSize' => 10,
            'color' => '#999999'
        )
    ),
    'qr_code' => array(
        'label' => $t['qr_code'],
        'icon' => 'fa-qrcode',
        'placeholder' => '{qr_code}',
        'is_image' => true,
        'default_style' => array(
            'width' => 100,
            'height' => 100
        )
    ),
    'organizer_name' => array(
        'label' => $t['organizer_name'],
        'icon' => 'fa-building',
        'placeholder' => '{organizer_name}',
        'default_style' => array(
            'fontSize' => 14,
            'color' => '#333333'
        )
    ),
    'ticket_name' => array(
        'label' => $t['ticket_type'],
        'icon' => 'fa-ticket',
        'placeholder' => '{ticket_name}',
        'default_style' => array(
            'fontSize' => 14,
            'color' => '#666666'
        )
    ),
    'custom_text' => array(
        'label' => $t['custom_text'],
        'icon' => 'fa-font',
        'placeholder' => '',
        'editable_content' => true,
        'default_style' => array(
            'fontSize' => 16,
            'color' => '#333333'
        )
    ),
    'custom_image' => array(
        'label' => $t['custom_image'],
        'icon' => 'fa-image',
        'is_image' => true,
        'uploadable' => true,
        'default_style' => array(
            'width' => 150,
            'height' => 100
        )
    ),
    'line' => array(
        'label' => $t['decorative_line'],
        'icon' => 'fa-minus',
        'is_shape' => true,
        'default_style' => array(
            'width' => 200,
            'height' => 2,
            'backgroundColor' => '#333333'
        )
    )
);

// Paper sizes
$paper_sizes = SC_Certificate_Template::get_paper_sizes();
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- Main Content -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['page_title']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/certificate-templates'); ?>"><?php echo $t['certificates']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['visual_builder']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <button type="button" class="btn btn-info mr-2" id="preview-certificate-btn">
                                <i class="fa fa-eye"></i> <?php echo $t['preview']; ?>
                            </button>
                            <button type="button" class="btn btn-primary" id="save-visual-template-btn">
                                <i class="fa fa-save"></i> <?php echo $t['save_template']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <!-- Left Panel - Element Widgets -->
            <div class="col-lg-2 col-md-3">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-puzzle-piece"></i> <?php echo $t['elements']; ?></h2>
                    </div>
                    <div class="body p-2" id="elements-palette">
                        <?php foreach ($element_types as $type => $config): ?>
                            <div class="widget-item" data-type="<?php echo esc_attr($type); ?>" draggable="true">
                                <i class="fa <?php echo esc_attr($config['icon']); ?>"></i>
                                <span><?php echo esc_html($config['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Template Selection -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-file-o"></i> <?php echo $t['template']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label><?php echo $t['edit_template']; ?></label>
                            <select class="form-control" id="template-select">
                                <option value=""><?php echo $t['new_template']; ?></option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo $tpl->id; ?>" <?php selected($template_id, $tpl->id); ?>>
                                        <?php echo esc_html($tpl->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $t['template_name']; ?></label>
                            <input type="text" class="form-control" id="template-name" value="<?php echo $template ? esc_attr($template->name) : ''; ?>" placeholder="<?php echo esc_attr($t['my_certificate']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center - Canvas -->
            <div class="col-lg-7 col-md-6">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-object-group"></i> <?php echo $t['design_canvas']; ?></h2>
                        <ul class="header-dropdown">
                            <li>
                                <select class="form-control form-control-sm" id="canvas-zoom" style="width: auto;">
                                    <option value="0.5">50%</option>
                                    <option value="0.75" selected>75%</option>
                                    <option value="1">100%</option>
                                    <option value="1.25">125%</option>
                                </select>
                            </li>
                        </ul>
                    </div>
                    <div class="body" style="background: #e9ecef; overflow: auto; min-height: 600px;" id="canvas-container">
                        <!-- Canvas wrapper for zoom -->
                        <div id="canvas-wrapper" style="transform-origin: top left;">
                            <!-- Certificate Canvas -->
                            <div id="certificate-canvas" class="certificate-canvas"
                                 style="width: 1123px; height: 794px; background: white; position: relative; margin: 20px auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">

                                <!-- Background Image Layer -->
                                <div id="background-layer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
                                    <img id="background-image" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <div id="background-placeholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border: 2px dashed #dee2e6;">
                                        <div class="text-center text-muted">
                                            <i class="fa fa-image" style="font-size: 48px;"></i>
                                            <p class="mt-2"><?php echo $t['upload_background_text']; ?></p>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="upload-background-btn">
                                                <i class="fa fa-upload"></i> <?php echo $t['upload_background']; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Elements Layer -->
                                <div id="elements-layer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;">
                                    <!-- Draggable elements will be placed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Properties -->
            <div class="col-lg-3 col-md-3">
                <!-- Canvas Settings -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-sliders"></i> <?php echo $t['canvas_settings']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label><?php echo $t['paper_size']; ?></label>
                            <select class="form-control" id="paper-size">
                                <option value="A4">A4 (297mm x 210mm)</option>
                                <option value="Letter">Letter (279mm x 216mm)</option>
                                <option value="A3">A3 (420mm x 297mm)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $t['orientation']; ?></label>
                            <select class="form-control" id="orientation">
                                <option value="landscape" selected><?php echo $t['landscape']; ?></option>
                                <option value="portrait"><?php echo $t['portrait']; ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $t['background']; ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="background-url" placeholder="<?php echo esc_attr($t['image_url_placeholder']); ?>" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary" id="upload-bg-btn">
                                        <i class="fa fa-upload"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" id="remove-bg-btn">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="background-file-input" accept="image/*" style="display: none;">
                        </div>
                    </div>
                </div>

                <!-- Element Properties Panel -->
                <div class="card" id="element-properties-panel" style="display: none;">
                    <div class="header">
                        <h2><i class="fa fa-cog"></i> <?php echo $t['element_properties']; ?></h2>
                        <ul class="header-dropdown">
                            <li>
                                <button type="button" class="btn btn-sm btn-danger" id="delete-element-btn" title="<?php echo esc_attr($t['delete_element']); ?>">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label><?php echo $t['element_type']; ?></label>
                            <input type="text" class="form-control" id="prop-element-type" readonly>
                        </div>

                        <!-- Content (for custom text) -->
                        <div class="form-group" id="prop-content-group" style="display: none;">
                            <label><?php echo $t['content']; ?></label>
                            <textarea class="form-control" id="prop-content" rows="2"></textarea>
                        </div>

                        <!-- Image URL (for custom image) -->
                        <div class="form-group" id="prop-image-group" style="display: none;">
                            <label><?php echo $t['image']; ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="prop-image-url" placeholder="<?php echo esc_attr($t['image_url_placeholder']); ?>" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary" id="upload-element-image-btn">
                                        <i class="fa fa-upload"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="element-image-file-input" accept="image/*" style="display: none;">
                        </div>

                        <!-- Position -->
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?php echo $t['x_position']; ?></label>
                                    <input type="number" class="form-control" id="prop-x" min="0">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?php echo $t['y_position']; ?></label>
                                    <input type="number" class="form-control" id="prop-y" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Size -->
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?php echo $t['width']; ?></label>
                                    <input type="number" class="form-control" id="prop-width" min="10">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?php echo $t['height']; ?></label>
                                    <input type="number" class="form-control" id="prop-height" min="10">
                                </div>
                            </div>
                        </div>

                        <!-- Text Properties -->
                        <div id="text-properties">
                            <div class="form-group">
                                <label><?php echo $t['font_size']; ?></label>
                                <input type="number" class="form-control" id="prop-font-size" min="8" max="200" value="16">
                            </div>
                            <div class="form-group">
                                <label><?php echo $t['font_family']; ?></label>
                                <select class="form-control" id="prop-font-family">
                                    <option value="Arial, sans-serif">Arial</option>
                                    <option value="Georgia, serif">Georgia</option>
                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                    <option value="'Courier New', monospace">Courier New</option>
                                    <option value="Verdana, sans-serif">Verdana</option>
                                    <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                    <option value="Impact, sans-serif">Impact</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo $t['font_weight']; ?></label>
                                <select class="form-control" id="prop-font-weight">
                                    <option value="normal"><?php echo $t['normal']; ?></option>
                                    <option value="bold"><?php echo $t['bold']; ?></option>
                                    <option value="lighter"><?php echo $t['light']; ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo $t['text_color']; ?></label>
                                <input type="color" class="form-control" id="prop-color" value="#000000" style="height: 38px;">
                            </div>
                            <div class="form-group">
                                <label><?php echo $t['text_align']; ?></label>
                                <div class="btn-group btn-group-sm w-100" role="group">
                                    <button type="button" class="btn btn-outline-secondary text-align-btn" data-align="left"><i class="fa fa-align-left"></i></button>
                                    <button type="button" class="btn btn-outline-secondary text-align-btn active" data-align="center"><i class="fa fa-align-center"></i></button>
                                    <button type="button" class="btn btn-outline-secondary text-align-btn" data-align="right"><i class="fa fa-align-right"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- Shape Properties -->
                        <div id="shape-properties" style="display: none;">
                            <div class="form-group">
                                <label><?php echo $t['bg_color']; ?></label>
                                <input type="color" class="form-control" id="prop-bg-color" value="#333333" style="height: 38px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Help -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-question-circle"></i> <?php echo $t['quick_help']; ?></h2>
                    </div>
                    <div class="body">
                        <ul class="list-unstyled mb-0" style="font-size: 12px;">
                            <li><i class="fa fa-hand-pointer-o text-primary"></i> <?php echo $t['help_drag_elements']; ?></li>
                            <li><i class="fa fa-arrows text-primary"></i> <?php echo $t['help_reposition']; ?></li>
                            <li><i class="fa fa-expand text-primary"></i> <?php echo $t['help_resize']; ?></li>
                            <li><i class="fa fa-mouse-pointer text-primary"></i> <?php echo $t['help_click_edit']; ?></li>
                            <li><i class="fa fa-trash text-danger"></i> <?php echo $t['help_delete']; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-eye"></i> <?php echo $t['certificate_preview']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr($t['close']); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background: #e9ecef; text-align: center;">
                <div id="preview-content" style="display: inline-block; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15);"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['close']; ?></button>
                <button type="button" class="btn btn-success" id="download-preview-btn">
                    <i class="fa fa-download"></i> <?php echo $t['download_sample_pdf']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Save Modal -->
<div class="modal fade" id="saveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-save"></i> <?php echo $t['save_certificate_template']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr($t['close']); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo $t['template_name']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                    <input type="text" class="form-control" id="save-template-name" required>
                </div>
                <div class="form-group">
                    <label><?php echo $t['description']; ?></label>
                    <textarea class="form-control" id="save-template-description" rows="2"></textarea>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="save-is-active" checked>
                    <label class="custom-control-label" for="save-is-active"><?php echo $t['active']; ?></label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="save-is-default">
                    <label class="custom-control-label" for="save-is-default"><?php echo $t['set_as_default']; ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                <button type="button" class="btn btn-primary" id="confirm-save-btn">
                    <i class="fa fa-save"></i> <?php echo $t['save_template']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Element Types Config (for JavaScript) -->
<script>
    window.elementTypes = <?php echo json_encode($element_types); ?>;
    window.existingTemplate = <?php echo $template ? json_encode(array(
        'id' => $template->id,
        'name' => $template->name,
        'description' => $template->description ?? '',
        'paper_size' => $template->paper_size,
        'orientation' => $template->orientation,
        'background_image_url' => $template->background_image_url,
        'elements_config' => $elements_config
    )) : 'null'; ?>;

    // Translations for JavaScript
    window.visualBuilderTranslations = {
        saving: '<?php echo esc_js($t['saving']); ?>',
        save_success: '<?php echo esc_js($t['save_success']); ?>',
        save_error: '<?php echo esc_js($t['save_error']); ?>',
        loading: '<?php echo esc_js($t['loading']); ?>',
        error: '<?php echo esc_js($t['error']); ?>',
        confirm_delete_element: '<?php echo esc_js($t['confirm_delete_element']); ?>',
        template_name_required: '<?php echo esc_js($t['template_name_required']); ?>',
        uploading: '<?php echo esc_js($t['uploading']); ?>',
        upload_success: '<?php echo esc_js($t['upload_success']); ?>',
        upload_error: '<?php echo esc_js($t['upload_error']); ?>',
        generating_preview: '<?php echo esc_js($t['generating_preview']); ?>',
        preview_error: '<?php echo esc_js($t['preview_error']); ?>',
        downloading_pdf: '<?php echo esc_js($t['downloading_pdf']); ?>',
        download_error: '<?php echo esc_js($t['download_error']); ?>',
        no_elements: '<?php echo esc_js($t['no_elements']); ?>',
        drop_here: '<?php echo esc_js($t['drop_here']); ?>',
        delete_element: '<?php echo esc_js($t['delete_element']); ?>',
        cancel: '<?php echo esc_js($t['cancel']); ?>',
        close: '<?php echo esc_js($t['close']); ?>'
    };
</script>

<!-- Load Visual Builder CSS -->
<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/admin-dashboard/css/certificate-visual-builder.css">

<!-- Load Visual Builder JS -->
<script src="<?php echo get_template_directory_uri(); ?>/assets/admin-dashboard/js/certificate-visual-builder.js"></script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
