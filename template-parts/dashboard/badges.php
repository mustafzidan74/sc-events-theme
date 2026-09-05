<?php
/**
 * Badge / Lanyard Printing Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

// Get events for dropdown
$events = SC_Event::get_all(array(
    'status' => array('publish', 'completed'),
    'limit' => 1000,
    'orderby' => 'start_date',
    'order' => 'DESC',
));

$dashboard_url = home_url('/event-manager-dashboard/');

// Translations
$t = array(
    'page_title'        => sc_t('badges.page_title', 'Badge Printing'),
    'select_event'      => sc_t('badges.select_event', 'Select Event'),
    'choose_event'      => sc_t('badges.choose_event', '-- Choose Event --'),
    'choose_design'     => sc_t('badges.choose_design', 'Choose Badge Design'),
    'customize'         => sc_t('badges.customize', 'Customize'),
    'select_people'     => sc_t('badges.select_people', 'Select People'),
    'attendees'         => sc_t('badges.attendees', 'Attendees'),
    'speakers'          => sc_t('badges.speakers', 'Speakers'),
    'organizers'        => sc_t('badges.organizers', 'Organizers'),
    'generate_pdf'      => sc_t('badges.generate_pdf', 'Generate Badges PDF'),
    'preview'           => sc_t('badges.preview', 'Badge Preview'),
    'primary_color'     => sc_t('badges.primary_color', 'Primary Color'),
    'event_logo'        => sc_t('badges.event_logo', 'Event Logo'),
    'upload_logo'       => sc_t('badges.upload_logo', 'Upload Logo'),
    'remove_logo'       => sc_t('badges.remove_logo', 'Remove'),
    'include_qr'        => sc_t('badges.include_qr', 'Include QR Code'),
    'include_event'     => sc_t('badges.include_event', 'Include Event Name'),
    'badge_size'        => sc_t('badges.badge_size', 'Badge Size'),
    'standard_size'     => sc_t('badges.standard_size', 'Standard (4" × 3")'),
    'id_card_size'      => sc_t('badges.id_card_size', 'ID Card (3.4" × 2.1")'),
    'layout'            => sc_t('badges.layout', 'Print Layout'),
    'grid_layout'       => sc_t('badges.grid_layout', '6 per page (A4)'),
    'single_layout'     => sc_t('badges.single_layout', '1 per page'),
    'select_all'        => sc_t('badges.select_all', 'Select All'),
    'select_none'       => sc_t('badges.select_none', 'Deselect All'),
    'selected'          => sc_t('badges.selected', 'selected'),
    'generating'        => sc_t('badges.generating', 'Generating badges...'),
    'no_attendees'      => sc_t('badges.no_attendees', 'No attendees found for this event.'),
    'no_speakers'       => sc_t('badges.no_speakers', 'No speakers assigned to this event.'),
    'no_organizers'     => sc_t('badges.no_organizers', 'No organizers assigned to this event.'),
    'corporate'         => sc_t('badges.corporate', 'Corporate'),
    'modern'            => sc_t('badges.modern', 'Modern'),
    'elegant'           => sc_t('badges.elegant', 'Elegant'),
    'info'              => sc_t('badges.info', 'Information'),
    'search'            => sc_t('badges.search', 'Search...'),
    'name'              => sc_t('badges.name', 'Name'),
    'email'             => sc_t('badges.email', 'Email'),
    'ticket'            => sc_t('badges.ticket', 'Ticket'),
    'type'              => sc_t('badges.type', 'Type'),
    'title'             => sc_t('badges.title', 'Title'),
    'company'           => sc_t('badges.company', 'Company'),
);

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html($t['page_title']); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo esc_url($dashboard_url . 'home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html($t['page_title']); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row clearfix">
        <!-- Main Column -->
        <div class="col-lg-8 col-md-12">

            <!-- Step 1: Select Event -->
            <div class="card">
                <div class="header">
                    <h2><i class="fa fa-calendar mr-2"></i> <?php echo esc_html($t['select_event']); ?></h2>
                </div>
                <div class="body">
                    <select id="badge-event-select" class="form-control">
                        <option value=""><?php echo esc_html($t['choose_event']); ?></option>
                        <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event->id; ?>">
                            <?php echo esc_html($event->title); ?>
                            <?php if (!empty($event->start_date)): ?>
                                (<?php echo esc_html(date_i18n('Y-m-d', strtotime($event->start_date))); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="event-loading" class="text-center mt-3" style="display:none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                </div>
            </div>

            <!-- Step 2: Choose Design -->
            <div class="card" id="design-card" style="display:none;">
                <div class="header">
                    <h2><i class="fa fa-paint-brush mr-2"></i> <?php echo esc_html($t['choose_design']); ?></h2>
                </div>
                <div class="body">
                    <div class="row" id="design-options">
                        <!-- Corporate -->
                        <div class="col-md-4 mb-3">
                            <div class="design-option selected" data-design="corporate">
                                <div class="design-preview design-corporate">
                                    <div class="dp-bar"></div>
                                    <div class="dp-name">John Doe</div>
                                    <div class="dp-sub">Software Engineer</div>
                                    <div class="dp-bottom">
                                        <span class="dp-type dp-type-attendee">ATTENDEE</span>
                                        <span class="dp-qr"><i class="fa fa-qrcode"></i></span>
                                    </div>
                                </div>
                                <div class="design-label"><?php echo esc_html($t['corporate']); ?></div>
                            </div>
                        </div>
                        <!-- Modern -->
                        <div class="col-md-4 mb-3">
                            <div class="design-option" data-design="modern">
                                <div class="design-preview design-modern">
                                    <div class="dp-strip"></div>
                                    <div class="dp-content">
                                        <div class="dp-name">John Doe</div>
                                        <div class="dp-sub">Software Engineer</div>
                                        <div class="dp-bottom">
                                            <span class="dp-type dp-type-speaker">SPEAKER</span>
                                            <span class="dp-qr"><i class="fa fa-qrcode"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="design-label"><?php echo esc_html($t['modern']); ?></div>
                            </div>
                        </div>
                        <!-- Elegant -->
                        <div class="col-md-4 mb-3">
                            <div class="design-option" data-design="elegant">
                                <div class="design-preview design-elegant">
                                    <div class="dp-circle">JD</div>
                                    <div class="dp-name">John Doe</div>
                                    <div class="dp-sub">Speaker</div>
                                    <div class="dp-bottom">
                                        <span class="dp-type">SPEAKER</span>
                                        <span class="dp-qr"><i class="fa fa-qrcode"></i></span>
                                    </div>
                                </div>
                                <div class="design-label"><?php echo esc_html($t['elegant']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Customize -->
            <div class="card" id="customize-card" style="display:none;">
                <div class="header">
                    <h2><i class="fa fa-sliders mr-2"></i> <?php echo esc_html($t['customize']); ?></h2>
                </div>
                <div class="body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo esc_html($t['primary_color']); ?></label>
                                <div class="d-flex align-items-center">
                                    <input type="color" id="badge-color" value="#1a73e8" class="form-control" style="width:50px;height:38px;padding:2px;cursor:pointer;">
                                    <span class="ml-2" id="color-hex-display">#1a73e8</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo esc_html($t['event_logo']); ?></label>
                                <div>
                                    <button type="button" id="upload-logo-btn" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-upload"></i> <?php echo esc_html($t['upload_logo']); ?>
                                    </button>
                                    <button type="button" id="remove-logo-btn" class="btn btn-sm btn-outline-danger ml-1" style="display:none;">
                                        <i class="fa fa-times"></i> <?php echo esc_html($t['remove_logo']); ?>
                                    </button>
                                    <input type="hidden" id="badge-logo-url" value="">
                                    <img id="logo-preview" src="" style="display:none;max-height:30px;margin-left:10px;vertical-align:middle;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo esc_html($t['badge_size']); ?></label>
                                <div>
                                    <label class="fancy-radio mr-3">
                                        <input type="radio" name="badge_size" value="standard" checked>
                                        <span><i></i> <?php echo esc_html($t['standard_size']); ?></span>
                                    </label>
                                    <label class="fancy-radio">
                                        <input type="radio" name="badge_size" value="id_card">
                                        <span><i></i> <?php echo esc_html($t['id_card_size']); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo esc_html($t['layout']); ?></label>
                                <div>
                                    <label class="fancy-radio mr-3">
                                        <input type="radio" name="layout_mode" value="grid" checked>
                                        <span><i></i> <?php echo esc_html($t['grid_layout']); ?></span>
                                    </label>
                                    <label class="fancy-radio">
                                        <input type="radio" name="layout_mode" value="single">
                                        <span><i></i> <?php echo esc_html($t['single_layout']); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="fancy-checkbox">
                                <input type="checkbox" id="include-qr" checked>
                                <span><?php echo esc_html($t['include_qr']); ?></span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="fancy-checkbox">
                                <input type="checkbox" id="include-event-name" checked>
                                <span><?php echo esc_html($t['include_event']); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Select People -->
            <div class="card" id="people-card" style="display:none;">
                <div class="header">
                    <h2><i class="fa fa-users mr-2"></i> <?php echo esc_html($t['select_people']); ?>
                        <span class="badge badge-primary ml-2" id="selected-count" style="display:none;">0 <?php echo esc_html($t['selected']); ?></span>
                    </h2>
                </div>
                <div class="body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-attendees" role="tab">
                                <i class="fa fa-users"></i> <?php echo esc_html($t['attendees']); ?>
                                <span class="badge badge-secondary ml-1" id="count-attendees">0</span>
                            </a>
                        </li>
                        <li class="nav-item" id="speakers-tab-li">
                            <a class="nav-link" data-toggle="tab" href="#tab-speakers" role="tab">
                                <i class="fa fa-microphone"></i> <?php echo esc_html($t['speakers']); ?>
                                <span class="badge badge-secondary ml-1" id="count-speakers">0</span>
                            </a>
                        </li>
                        <li class="nav-item" id="organizers-tab-li">
                            <a class="nav-link" data-toggle="tab" href="#tab-organizers" role="tab">
                                <i class="fa fa-building"></i> <?php echo esc_html($t['organizers']); ?>
                                <span class="badge badge-secondary ml-1" id="count-organizers">0</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Selection Controls -->
                    <div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap" style="gap:8px;">
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-all"><?php echo esc_html($t['select_all']); ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-select-none"><?php echo esc_html($t['select_none']); ?></button>
                        </div>
                        <div>
                            <input type="text" id="people-search" class="form-control form-control-sm" placeholder="<?php echo esc_attr($t['search']); ?>" style="width:200px;">
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-attendees" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="attendees-table">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" id="check-all-attendees"></th>
                                            <th><?php echo esc_html($t['name']); ?></th>
                                            <th><?php echo esc_html($t['email']); ?></th>
                                            <th><?php echo esc_html($t['ticket']); ?></th>
                                            <th><?php echo esc_html($t['type']); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div id="no-attendees" class="text-center text-muted py-3" style="display:none;">
                                    <?php echo esc_html($t['no_attendees']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-speakers" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="speakers-table">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" id="check-all-speakers"></th>
                                            <th><?php echo esc_html($t['name']); ?></th>
                                            <th><?php echo esc_html($t['title']); ?></th>
                                            <th><?php echo esc_html($t['company']); ?></th>
                                            <th><?php echo esc_html($t['type']); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div id="no-speakers" class="text-center text-muted py-3" style="display:none;">
                                    <?php echo esc_html($t['no_speakers']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-organizers" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="organizers-table">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" id="check-all-organizers"></th>
                                            <th><?php echo esc_html($t['name']); ?></th>
                                            <th><?php echo esc_html($t['type']); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div id="no-organizers" class="text-center text-muted py-3" style="display:none;">
                                    <?php echo esc_html($t['no_organizers']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Generate Button -->
            <div id="generate-section" style="display:none;">
                <button type="button" id="generate-btn" class="btn btn-lg btn-success btn-block" disabled>
                    <i class="fa fa-file-pdf-o mr-2"></i> <?php echo esc_html($t['generate_pdf']); ?>
                </button>
                <div id="generate-loading" class="text-center mt-3" style="display:none;">
                    <i class="fa fa-spinner fa-spin fa-2x text-success"></i>
                    <p class="mt-2 text-muted"><?php echo esc_html($t['generating']); ?></p>
                </div>
            </div>

        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4 col-md-12">

            <!-- Badge Preview -->
            <div class="card" id="preview-card" style="display:none;">
                <div class="header">
                    <h2><i class="fa fa-eye mr-2"></i> <?php echo esc_html($t['preview']); ?></h2>
                </div>
                <div class="body text-center" style="padding:15px;">
                    <div id="badge-preview-container"></div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card" id="info-card" style="display:none;">
                <div class="header">
                    <h2><i class="fa fa-info-circle mr-2"></i> <?php echo esc_html($t['info']); ?></h2>
                </div>
                <div class="body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fa fa-users text-primary mr-2"></i> <?php echo esc_html($t['attendees']); ?>: <strong id="info-attendees">0</strong></li>
                        <li class="mb-2"><i class="fa fa-microphone text-success mr-2"></i> <?php echo esc_html($t['speakers']); ?>: <strong id="info-speakers">0</strong></li>
                        <li class="mb-2"><i class="fa fa-building text-purple mr-2"></i> <?php echo esc_html($t['organizers']); ?>: <strong id="info-organizers">0</strong></li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>
</div>

<!-- Styles -->
<style>
/* Design picker */
.design-option {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.design-option:hover {
    border-color: #1a73e8;
    box-shadow: 0 2px 8px rgba(26,115,232,0.15);
}
.design-option.selected {
    border-color: #1a73e8;
    box-shadow: 0 2px 12px rgba(26,115,232,0.25);
    background: #f8faff;
}
.design-label {
    font-weight: 600;
    font-size: 13px;
    margin-top: 6px;
    color: #333;
}

/* Design preview thumbnails */
.design-preview {
    width: 100%;
    height: 120px;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
    font-family: 'Inter', Arial, sans-serif;
    background: #fff;
    border: 1px solid #eee;
}

/* Corporate preview */
.design-corporate .dp-bar {
    height: 22px;
    background: var(--badge-color, #1a73e8);
}
.design-corporate .dp-name {
    font-size: 13px;
    font-weight: 700;
    color: #212121;
    margin-top: 10px;
    text-align: center;
}
.design-corporate .dp-sub {
    font-size: 9px;
    color: #888;
    text-align: center;
}
.design-corporate .dp-bottom {
    position: absolute;
    bottom: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Modern preview */
.design-modern {
    display: flex;
}
.design-modern .dp-strip {
    width: 16px;
    background: var(--badge-type-color, #2ecc71);
    flex-shrink: 0;
}
.design-modern .dp-content {
    padding: 10px 8px;
    flex: 1;
    position: relative;
}
.design-modern .dp-name {
    font-size: 13px;
    font-weight: 700;
    color: #212121;
}
.design-modern .dp-sub {
    font-size: 9px;
    color: #888;
    margin-top: 2px;
}
.design-modern .dp-bottom {
    position: absolute;
    bottom: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Elegant preview */
.design-elegant {
    background: var(--badge-color, #1a73e8);
    text-align: center;
    padding-top: 8px;
}
.design-elegant .dp-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    color: var(--badge-color, #1a73e8);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}
.design-elegant .dp-name {
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    margin-top: 6px;
}
.design-elegant .dp-sub {
    font-size: 9px;
    color: rgba(255,255,255,0.7);
}
.design-elegant .dp-bottom {
    position: absolute;
    bottom: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.design-elegant .dp-type {
    background: rgba(255,255,255,0.9);
    color: var(--badge-color, #1a73e8);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 8px;
    font-weight: 700;
}
.design-elegant .dp-qr {
    color: rgba(255,255,255,0.8);
}

/* Type badges in previews */
.dp-type {
    font-size: 8px;
    font-weight: 700;
    color: #fff;
    padding: 1px 6px;
    border-radius: 3px;
}
.dp-type-attendee { background: #3498db; }
.dp-type-vip { background: #f39c12; }
.dp-type-speaker { background: #2ecc71; }
.dp-type-organizer { background: #9b59b6; }
.dp-qr { font-size: 18px; color: #ccc; }

/* Badge preview in sidebar */
#badge-preview-container .badge-preview-box {
    display: inline-block;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    font-family: 'Inter', Arial, sans-serif;
    text-align: center;
}

/* People table */
#people-card .table th,
#people-card .table td {
    vertical-align: middle;
    font-size: 13px;
}
#people-card .table .badge {
    font-size: 10px;
}
.text-purple { color: #9b59b6 !important; }

/* Responsive */
@media (max-width: 767px) {
    .design-preview { height: 100px; }
    #people-search { width: 100% !important; margin-top: 5px; }
}
</style>

<script>
(function() {
    'use strict';

    // State
    var selectedEvent = null;
    var selectedDesign = 'corporate';
    var eventTitle = '';
    var attendeesData = [];
    var speakersData = [];
    var organizersData = [];
    var selectedPeople = {}; // {key: {id, type}} where key = type-id

    // DOM ready
    $(document).ready(function() {
        updateDesignColors();

        // Event selection
        $('#badge-event-select').on('change', function() {
            var eventId = $(this).val();
            if (!eventId) {
                resetAll();
                return;
            }
            selectedEvent = parseInt(eventId);
            eventTitle = $(this).find('option:selected').text().trim();
            loadEventPeople(selectedEvent);
        });

        // Design selection
        $(document).on('click', '.design-option', function() {
            $('.design-option').removeClass('selected');
            $(this).addClass('selected');
            selectedDesign = $(this).data('design');
            updateBadgePreview();
        });

        // Color change
        $('#badge-color').on('input', function() {
            $('#color-hex-display').text($(this).val());
            updateDesignColors();
            updateBadgePreview();
        });

        // Logo upload
        $('#upload-logo-btn').on('click', function() {
            var frame = wp.media({
                title: '<?php echo esc_js($t['upload_logo']); ?>',
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#badge-logo-url').val(attachment.url);
                $('#logo-preview').attr('src', attachment.url).show();
                $('#remove-logo-btn').show();
                updateBadgePreview();
            });
            frame.open();
        });

        $('#remove-logo-btn').on('click', function() {
            $('#badge-logo-url').val('');
            $('#logo-preview').hide();
            $(this).hide();
            updateBadgePreview();
        });

        // Checkboxes & options
        $('#include-qr, #include-event-name').on('change', function() { updateBadgePreview(); });
        $('input[name="badge_size"], input[name="layout_mode"]').on('change', function() { updateBadgePreview(); });

        // Select all / none / VIP
        $('#btn-select-all').on('click', function() { selectPeople('all'); });
        $('#btn-select-none').on('click', function() { selectPeople('none'); });

        // Check-all checkboxes
        $('#check-all-attendees').on('change', function() { toggleAll('attendee', this.checked); });
        $('#check-all-speakers').on('change', function() { toggleAll('speaker', this.checked); });
        $('#check-all-organizers').on('change', function() { toggleAll('organizer', this.checked); });


        // Search
        $('#people-search').on('input', function() {
            var term = $(this).val().toLowerCase();
            filterPeopleTable(term);
        });

        // Individual checkbox
        $(document).on('change', '.person-check', function() {
            var key = $(this).data('key');
            var id = $(this).data('id');
            var type = $(this).data('type');

            if (this.checked) {
                selectedPeople[key] = { id: id, type: type };
            } else {
                delete selectedPeople[key];
            }
            updateSelectedCount();
        });

        // Generate
        $('#generate-btn').on('click', function() { generateBadges(); });
    });

    // Load people for event
    function loadEventPeople(eventId) {
        $('#event-loading').show();
        resetPeopleData();

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_badge_people',
                nonce: scDashboard.nonce,
                event_id: eventId
            },
            success: function(response) {
                $('#event-loading').hide();
                if (!response.success) {
                    toastr.error(response.data ? response.data.message : 'Error loading data');
                    return;
                }

                attendeesData = response.data.attendees || [];
                speakersData = response.data.speakers || [];
                organizersData = response.data.organizers || [];

                // Update counts
                $('#count-attendees').text(attendeesData.length);
                $('#count-speakers').text(speakersData.length);
                $('#count-organizers').text(organizersData.length);
                $('#info-attendees').text(attendeesData.length);
                $('#info-speakers').text(speakersData.length);
                $('#info-organizers').text(organizersData.length);

                // Show/hide tabs
                if (speakersData.length === 0) $('#speakers-tab-li').hide(); else $('#speakers-tab-li').show();
                if (organizersData.length === 0) $('#organizers-tab-li').hide(); else $('#organizers-tab-li').show();

                // Render tables
                renderAttendees();
                renderSpeakers();
                renderOrganizers();

                // Show sections
                $('#design-card, #customize-card, #people-card, #generate-section, #preview-card, #info-card').show();

                updateBadgePreview();
            },
            error: function() {
                $('#event-loading').hide();
                toastr.error('Failed to load data. Please try again.');
            }
        });
    }

    // Render attendees table
    function renderAttendees() {
        var tbody = $('#attendees-table tbody');
        tbody.empty();

        if (attendeesData.length === 0) {
            $('#attendees-table').hide();
            $('#no-attendees').show();
            return;
        }
        $('#attendees-table').show();
        $('#no-attendees').hide();

        attendeesData.forEach(function(att) {
            var key = 'attendee-' + att.id;
            var typeLabel = att.is_vip ? '<span class="badge" style="background:#f39c12;color:#fff;">VIP</span>' : '<span class="badge" style="background:#3498db;color:#fff;">ATTENDEE</span>';
            tbody.append(
                '<tr data-searchable="' + escapeHtml((att.name + ' ' + att.email).toLowerCase()) + '">' +
                '<td><input type="checkbox" class="person-check" data-key="' + key + '" data-id="' + att.id + '" data-type="attendee"></td>' +
                '<td>' + escapeHtml(att.name) + '</td>' +
                '<td><small class="text-muted">' + escapeHtml(att.email) + '</small></td>' +
                '<td><small>' + escapeHtml(att.ticket_name) + '</small></td>' +
                '<td>' + typeLabel + '</td>' +
                '</tr>'
            );
        });
    }

    // Render speakers table
    function renderSpeakers() {
        var tbody = $('#speakers-table tbody');
        tbody.empty();

        if (speakersData.length === 0) {
            $('#speakers-table').hide();
            $('#no-speakers').show();
            return;
        }
        $('#speakers-table').show();
        $('#no-speakers').hide();

        speakersData.forEach(function(spk) {
            var key = 'speaker-' + spk.id;
            tbody.append(
                '<tr data-searchable="' + escapeHtml((spk.name + ' ' + spk.title + ' ' + spk.company).toLowerCase()) + '">' +
                '<td><input type="checkbox" class="person-check" data-key="' + key + '" data-id="' + spk.id + '" data-type="speaker"></td>' +
                '<td>' + escapeHtml(spk.name) + '</td>' +
                '<td><small>' + escapeHtml(spk.title || '') + '</small></td>' +
                '<td><small>' + escapeHtml(spk.company || '') + '</small></td>' +
                '<td><span class="badge" style="background:#2ecc71;color:#fff;">SPEAKER</span></td>' +
                '</tr>'
            );
        });
    }

    // Render organizers table
    function renderOrganizers() {
        var tbody = $('#organizers-table tbody');
        tbody.empty();

        if (organizersData.length === 0) {
            $('#organizers-table').hide();
            $('#no-organizers').show();
            return;
        }
        $('#organizers-table').show();
        $('#no-organizers').hide();

        organizersData.forEach(function(org) {
            var key = 'organizer-' + org.id;
            tbody.append(
                '<tr data-searchable="' + escapeHtml(org.name.toLowerCase()) + '">' +
                '<td><input type="checkbox" class="person-check" data-key="' + key + '" data-id="' + org.id + '" data-type="organizer"></td>' +
                '<td>' + escapeHtml(org.name) + '</td>' +
                '<td><span class="badge" style="background:#9b59b6;color:#fff;">ORGANIZER</span></td>' +
                '</tr>'
            );
        });
    }

    // Select helpers
    function selectPeople(mode) {
        var activeTab = $('.tab-pane.active').attr('id');
        var tableId = '#attendees-table';
        if (activeTab === 'tab-speakers') tableId = '#speakers-table';
        if (activeTab === 'tab-organizers') tableId = '#organizers-table';

        if (mode === 'all') {
            $(tableId + ' .person-check:visible').prop('checked', true).trigger('change');
        } else if (mode === 'none') {
            $(tableId + ' .person-check').prop('checked', false).trigger('change');
        }
    }

    function toggleAll(type, checked) {
        var tableId = '#' + type + 's-table';
        if (type === 'attendee') tableId = '#attendees-table';

        $(tableId + ' .person-check:visible').each(function() {
            this.checked = checked;
            var key = $(this).data('key');
            if (checked) {
                selectedPeople[key] = { id: $(this).data('id'), type: $(this).data('type') };
            } else {
                delete selectedPeople[key];
            }
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var count = Object.keys(selectedPeople).length;
        if (count > 0) {
            $('#selected-count').text(count + ' <?php echo esc_js($t['selected']); ?>').show();
            $('#generate-btn').prop('disabled', false);
        } else {
            $('#selected-count').hide();
            $('#generate-btn').prop('disabled', true);
        }
    }

    function filterPeopleTable(term) {
        var activeTab = $('.tab-pane.active').attr('id');
        var tableId = '#attendees-table';
        if (activeTab === 'tab-speakers') tableId = '#speakers-table';
        if (activeTab === 'tab-organizers') tableId = '#organizers-table';

        $(tableId + ' tbody tr').each(function() {
            var searchable = $(this).data('searchable') || '';
            $(this).toggle(term === '' || searchable.indexOf(term) !== -1);
        });
    }

    // Badge preview
    function updateBadgePreview() {
        var color = $('#badge-color').val();
        var logoUrl = $('#badge-logo-url').val();
        var includeQR = $('#include-qr').is(':checked');
        var includeEvent = $('#include-event-name').is(':checked');

        var name = 'John Doe';
        var subtitle = 'Software Engineer';
        var badgeType = 'attendee';

        // Use first selected person data if available
        var keys = Object.keys(selectedPeople);
        if (keys.length > 0) {
            var first = selectedPeople[keys[0]];
            if (first.type === 'attendee') {
                var att = attendeesData.find(function(a) { return a.id == first.id; });
                if (att) { name = att.name; subtitle = att.company || att.ticket_name; badgeType = att.is_vip ? 'vip' : 'attendee'; }
            } else if (first.type === 'speaker') {
                var spk = speakersData.find(function(s) { return s.id == first.id; });
                if (spk) { name = spk.name; subtitle = spk.title || spk.company; badgeType = 'speaker'; }
            } else if (first.type === 'organizer') {
                var org = organizersData.find(function(o) { return o.id == first.id; });
                if (org) { name = org.name; subtitle = ''; badgeType = 'organizer'; }
            }
        }

        var typeColors = { attendee: '#3498db', vip: '#f39c12', speaker: '#2ecc71', organizer: '#9b59b6' };
        var typeLabels = { attendee: 'ATTENDEE', vip: 'VIP', speaker: 'SPEAKER', organizer: 'ORGANIZER' };
        var tc = typeColors[badgeType] || '#3498db';
        var tl = typeLabels[badgeType] || 'ATTENDEE';

        var qrHtml = includeQR ? '<i class="fa fa-qrcode" style="font-size:28px;color:#999;"></i>' : '';
        var eventHtml = includeEvent && eventTitle ? '<div style="font-size:7px;color:#aaa;text-align:center;position:absolute;bottom:2px;left:0;right:0;">' + escapeHtml(eventTitle.split('(')[0].trim()) + '</div>' : '';

        var html = '';

        if (selectedDesign === 'corporate') {
            html = '<div class="badge-preview-box" style="width:260px;height:195px;position:relative;background:#fff;">' +
                '<div style="background:' + color + ';height:24px;display:flex;align-items:center;padding:0 8px;">' +
                (logoUrl ? '<img src="' + logoUrl + '" style="height:16px;object-fit:contain;">' : '') +
                '</div>' +
                '<div style="padding-top:22px;text-align:center;">' +
                '<div style="font-size:16px;font-weight:700;color:#212121;">' + escapeHtml(name) + '</div>' +
                '<div style="font-size:10px;color:#888;margin-top:3px;">' + escapeHtml(subtitle) + '</div>' +
                '</div>' +
                '<div style="position:absolute;bottom:16px;left:10px;"><span style="background:' + tc + ';color:#fff;padding:2px 8px;border-radius:3px;font-size:9px;font-weight:700;">' + tl + '</span></div>' +
                '<div style="position:absolute;bottom:10px;right:10px;">' + qrHtml + '</div>' +
                eventHtml +
                '</div>';
        } else if (selectedDesign === 'modern') {
            html = '<div class="badge-preview-box" style="width:260px;height:195px;position:relative;background:#fff;display:flex;">' +
                '<div style="width:16px;background:' + tc + ';flex-shrink:0;"></div>' +
                '<div style="flex:1;padding:14px 10px;position:relative;">' +
                '<div style="font-size:16px;font-weight:700;color:#212121;">' + escapeHtml(name) + '</div>' +
                '<div style="font-size:10px;color:#888;margin-top:3px;">' + escapeHtml(subtitle) + '</div>' +
                '<div style="position:absolute;bottom:16px;left:10px;"><span style="background:' + tc + ';color:#fff;padding:2px 8px;border-radius:3px;font-size:9px;font-weight:700;">' + tl + '</span></div>' +
                '<div style="position:absolute;bottom:10px;right:10px;">' + qrHtml + '</div>' +
                (includeEvent && eventTitle ? '<div style="position:absolute;bottom:2px;left:10px;font-size:7px;color:#aaa;">' + escapeHtml(eventTitle.split('(')[0].trim()) + '</div>' : '') +
                '</div>' +
                '</div>';
        } else if (selectedDesign === 'elegant') {
            var initials = name.split(' ').slice(0, 2).map(function(w) { return w.charAt(0).toUpperCase(); }).join('');
            html = '<div class="badge-preview-box" style="width:260px;height:195px;position:relative;background:' + color + ';text-align:center;padding-top:12px;">' +
                '<div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.9);color:' + color + ';display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;">' + initials + '</div>' +
                '<div style="font-size:15px;font-weight:700;color:#fff;margin-top:8px;">' + escapeHtml(name) + '</div>' +
                '<div style="font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;">' + escapeHtml(subtitle) + '</div>' +
                '<div style="position:absolute;bottom:14px;left:10px;"><span style="background:rgba(255,255,255,0.9);color:' + color + ';padding:2px 8px;border-radius:3px;font-size:9px;font-weight:700;">' + tl + '</span></div>' +
                '<div style="position:absolute;bottom:8px;right:10px;background:rgba(255,255,255,0.9);border-radius:3px;padding:3px;">' +
                (includeQR ? '<i class="fa fa-qrcode" style="font-size:24px;color:#333;"></i>' : '') +
                '</div>' +
                (includeEvent && eventTitle ? '<div style="position:absolute;bottom:2px;left:0;right:0;font-size:7px;color:rgba(255,255,255,0.6);">' + escapeHtml(eventTitle.split('(')[0].trim()) + '</div>' : '') +
                '</div>';
        }

        $('#badge-preview-container').html(html);
    }

    function updateDesignColors() {
        var color = $('#badge-color').val();
        document.documentElement.style.setProperty('--badge-color', color);
        // Update elegant preview
        $('.design-elegant').css('background', color);
        $('.design-elegant .dp-circle').css('color', color);
        // Update corporate bar
        $('.design-corporate .dp-bar').css('background', color);
    }

    // Generate badges
    function generateBadges() {
        var people = [];
        Object.keys(selectedPeople).forEach(function(key) {
            people.push(selectedPeople[key]);
        });

        if (people.length === 0) {
            toastr.warning('Please select at least one person.');
            return;
        }

        $('#generate-btn').prop('disabled', true);
        $('#generate-loading').show();

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_prepare_badges',
                nonce: scDashboard.nonce,
                event_id: selectedEvent,
                design: selectedDesign,
                badge_size: $('input[name="badge_size"]:checked').val(),
                primary_color: $('#badge-color').val(),
                logo_url: $('#badge-logo-url').val(),
                include_qr: $('#include-qr').is(':checked') ? '1' : '0',
                include_event_name: $('#include-event-name').is(':checked') ? '1' : '0',
                layout_mode: $('input[name="layout_mode"]:checked').val(),
                people: JSON.stringify(people)
            },
            success: function(response) {
                $('#generate-loading').hide();
                $('#generate-btn').prop('disabled', false);

                if (!response.success) {
                    toastr.error(response.data ? response.data.message : 'Error generating badges');
                    return;
                }

                toastr.success('Badges generated! (' + response.data.count + ' badges)');

                // Open PDF in new tab
                var link = document.createElement('a');
                link.href = response.data.download_url;
                link.target = '_blank';
                link.click();
            },
            error: function() {
                $('#generate-loading').hide();
                $('#generate-btn').prop('disabled', false);
                toastr.error('Failed to generate badges. Please try again.');
            }
        });
    }

    // Reset
    function resetAll() {
        selectedEvent = null;
        eventTitle = '';
        resetPeopleData();
        $('#design-card, #customize-card, #people-card, #generate-section, #preview-card, #info-card').hide();
    }

    function resetPeopleData() {
        attendeesData = [];
        speakersData = [];
        organizersData = [];
        selectedPeople = {};
        updateSelectedCount();
        $('#attendees-table tbody, #speakers-table tbody, #organizers-table tbody').empty();
        $('#count-attendees, #count-speakers, #count-organizers').text('0');
    }

    // Utility
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})();
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
