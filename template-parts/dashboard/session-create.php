<?php
/**
 * Dashboard - Create Session Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.add_session', 'Add Session');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get events for dropdown
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => 'publish',
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}

// Pre-select event if passed via URL
$preselect_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

global $wpdb;

// Build events data for JS (dates, capacity from tickets)
$events_js_data = array();
foreach ($events as $ev) {
    // Calculate capacity from tickets (same logic as events list page)
    $event_capacity = 0;
    if (class_exists('SC_Ticket')) {
        $event_tickets = SC_Ticket::get_by_event($ev->id);
        if ($event_tickets) {
            foreach ($event_tickets as $ticket) {
                $qty = intval($ticket->quantity ?? 0);
                if ($qty > 0) {
                    $event_capacity += $qty;
                }
            }
        }
    }
    // Fallback to total_capacity if no tickets
    if (!$event_capacity && $ev->total_capacity > 0) {
        $event_capacity = (int) $ev->total_capacity;
    }

    $events_js_data[$ev->id] = array(
        'start_date' => $ev->start_date,
        'end_date' => $ev->end_date,
        'total_capacity' => $event_capacity,
    );
}

// Get certificate templates
$cert_templates_table = $wpdb->prefix . 'sc_certificate_templates';
$cert_templates = $wpdb->get_results("SELECT id, name FROM $cert_templates_table WHERE is_active = 1 ORDER BY name ASC");

// Session types
$session_types = array(
    'lecture'     => sc_t('sessions.type_lecture', 'Lecture'),
    'workshop'    => sc_t('sessions.type_workshop', 'Workshop'),
    'panel'       => sc_t('sessions.type_panel', 'Panel'),
    'keynote'     => sc_t('sessions.type_keynote', 'Keynote'),
    'break'       => sc_t('sessions.type_break', 'Break'),
    'networking'  => sc_t('sessions.type_networking', 'Networking'),
    'exhibition'  => sc_t('sessions.type_exhibition', 'Exhibition'),
    'poster'      => sc_t('sessions.type_poster', 'Poster'),
    'symposium'   => sc_t('sessions.type_symposium', 'Symposium'),
    'hands_on'    => sc_t('sessions.type_hands_on', 'Hands-on'),
    'other'       => sc_t('sessions.type_other', 'Other'),
);
?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.add_session', 'Add Session')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/sessions'); ?>"><?php echo esc_html(sc_t('nav.sessions', 'Sessions')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.add', 'Add')); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/sessions'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html(sc_t('dashboard_pages.back_to_sessions', 'Back to Sessions')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="session-create-form">
            <?php wp_nonce_field('sc_session_action', 'sc_session_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_session">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.basic_information', 'Basic Information')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="session-title"><?php echo esc_html(sc_t('general.title', 'Title')); ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="session-title" name="title" required placeholder="<?php echo esc_attr(sc_t('sessions.enter_title', 'Enter session title')); ?>">
                            </div>

                            <div class="form-group">
                                <label for="session-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?> <span class="text-danger">*</span></label>
                                <select class="form-control" id="session-event" name="event_id" required>
                                    <option value=""><?php echo esc_html(sc_t('sessions.select_event', 'Select Event')); ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>" <?php selected($preselect_event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="session-description"><?php echo esc_html(sc_t('general.description', 'Description')); ?></label>
                                <textarea class="form-control" id="session-description" name="description" rows="4" placeholder="<?php echo esc_attr(sc_t('sessions.enter_description', 'Enter session description')); ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-clock-o"></i> <?php echo esc_html(sc_t('sessions.date_time', 'Date & Time')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-date"><?php echo esc_html(sc_t('general.date', 'Date')); ?> <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="session-date" name="session_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-start-time"><?php echo esc_html(sc_t('sessions.start_time', 'Start Time')); ?> <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="session-start-time" name="start_time" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-end-time"><?php echo esc_html(sc_t('sessions.end_time', 'End Time')); ?></label>
                                        <input type="time" class="form-control" id="session-end-time" name="end_time">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location & Type -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-map-marker"></i> <?php echo esc_html(sc_t('sessions.location_type', 'Location & Type')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-hall"><?php echo esc_html(sc_t('sessions.hall', 'Hall/Room')); ?></label>
                                        <select class="form-control" id="session-hall" name="hall_name">
                                            <option value=""><?php echo esc_html(sc_t('sessions.select_hall', '-- Select Hall --')); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-type"><?php echo esc_html(sc_t('sessions.type', 'Type')); ?></label>
                                        <select class="form-control" id="session-type" name="session_type">
                                            <?php foreach ($session_types as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="session-track"><?php echo esc_html(sc_t('sessions.track', 'Track')); ?></label>
                                        <input type="text" class="form-control" id="session-track" name="track" placeholder="<?php echo esc_attr(sc_t('sessions.enter_track', 'e.g., Track 1, AI Track')); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Capacity -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-users"></i> <?php echo esc_html(sc_t('sessions.capacity', 'Capacity')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="session-capacity"><?php echo esc_html(sc_t('sessions.capacity', 'Capacity')); ?></label>
                                <input type="number" class="form-control" id="session-capacity" name="capacity" min="0" value="0">
                                <small class="text-muted" id="capacity-hint"><?php echo esc_html(sc_t('sessions.capacity_hint', '0 = unlimited')); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Speakers -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-microphone"></i> <?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></h2>
                        </div>
                        <div class="body">
                            <div id="session-speakers-list">
                                <!-- Speaker rows will be added here -->
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-speaker-btn">
                                <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('sessions.add_speaker', 'Add Speaker')); ?>
                            </button>

                            <small class="text-muted d-block mt-2"><?php echo esc_html(sc_t('sessions.speakers_hint', 'Add speakers and their roles for this session')); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Save -->
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.save_session', 'Save Session')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="session-status"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></label>
                                <select class="form-control" id="session-status" name="status">
                                    <option value="draft"><?php echo esc_html(sc_t('dashboard_pages.draft', 'Draft')); ?></option>
                                    <option value="published"><?php echo esc_html(sc_t('dashboard_pages.published', 'Published')); ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="session-sort-order"><?php echo esc_html(sc_t('dashboard_pages.display_order', 'Display Order')); ?></label>
                                <input type="number" class="form-control" id="session-sort-order" name="sort_order" min="0" value="0">
                                <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.order_hint', 'Lower numbers appear first')); ?></small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-session-btn">
                                    <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.add_session', 'Add Session')); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Settings -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-certificate"></i> <?php echo esc_html(sc_t('sessions.certificate_settings', 'Certificate Settings')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="session-enable-certificate" name="enable_certificate" value="1">
                                    <label class="custom-control-label" for="session-enable-certificate"><?php echo esc_html(sc_t('sessions.enable_certificate', 'Enable Certificate')); ?></label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="session-cert-template"><?php echo esc_html(sc_t('sessions.certificate_template', 'Certificate Template')); ?></label>
                                <select class="form-control" id="session-cert-template" name="certificate_template_id">
                                    <option value=""><?php echo esc_html(sc_t('sessions.select_template', 'Select Template')); ?></option>
                                    <?php foreach ($cert_templates as $tpl): ?>
                                        <option value="<?php echo $tpl->id; ?>"><?php echo esc_html($tpl->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="session-min-attendance"><?php echo esc_html(sc_t('sessions.min_attendance', 'Min Attendance %')); ?></label>
                                <input type="number" class="form-control" id="session-min-attendance" name="min_attendance_percentage" min="0" max="100" value="80">
                                <small class="text-muted"><?php echo esc_html(sc_t('sessions.min_attendance_hint', 'Minimum attendance percentage to be eligible for certificate')); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var sessionTranslations = {
    saving: '<?php echo esc_js(sc_t('dashboard_pages.saving', 'Saving...')); ?>',
    session_created: '<?php echo esc_js(sc_t('sessions.session_created', 'Session created successfully!')); ?>',
    error_creating: '<?php echo esc_js(sc_t('sessions.error_creating', 'Error creating session')); ?>',
    error_occurred: '<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>',
    add_session: '<?php echo esc_js(sc_t('dashboard_pages.add_session', 'Add Session')); ?>',
    select_speaker: '<?php echo esc_js(sc_t('sessions.select_speaker', 'Select Speaker')); ?>',
    other_type_name: '<?php echo esc_js(sc_t('sessions.other_type_name', 'Other (type name)')); ?>',
    select_event_first: '<?php echo esc_js(sc_t('sessions.select_event_first', 'Please select an event first')); ?>'
};

var eventsData = <?php echo json_encode($events_js_data); ?>;
var eventSpeakers = [];

jQuery(document).ready(function($) {
    var speakerIndex = 0;

    // ==========================================
    // LOAD HALLS DROPDOWN
    // ==========================================
    function loadHalls(selectedValue) {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: { action: 'sc_get_halls', nonce: scDashboard.nonce }
        }).done(function(response) {
            if (response.success && response.data.halls) {
                var $sel = $('#session-hall');
                var first = '<option value="">' + '<?php echo esc_js(sc_t('sessions.select_hall', '-- Select Hall --')); ?>' + '</option>';
                var opts = '';
                response.data.halls.forEach(function(h) {
                    var sel = (selectedValue && h.name === selectedValue) ? ' selected' : '';
                    var cap = h.capacity ? ' (' + h.capacity + ')' : '';
                    opts += '<option value="' + $('<span>').text(h.name).html() + '"' + sel + '>' + $('<span>').text(h.name).html() + cap + '</option>';
                });
                $sel.html(first + opts);
            }
        });
    }
    loadHalls();

    // ==========================================
    // EVENT CHANGE HANDLER
    // ==========================================
    $('#session-event').on('change', function() {
        var eventId = $(this).val();
        if (!eventId || !eventsData[eventId]) {
            $('#session-date').attr('min', '').attr('max', '');
            $('#session-capacity').removeAttr('max');
            eventSpeakers = [];
            updateAllSpeakerDropdowns();
            return;
        }

        var ev = eventsData[eventId];

        // Update date constraints
        $('#session-date').attr('min', ev.start_date).attr('max', ev.end_date);
        var currentDate = $('#session-date').val();
        if (currentDate && (currentDate < ev.start_date || currentDate > ev.end_date)) {
            $('#session-date').val('');
        }

        // Update capacity max
        if (ev.total_capacity > 0) {
            $('#session-capacity').attr('max', ev.total_capacity);
            $('#capacity-hint').text('<?php echo esc_js(sc_t('sessions.max_capacity', 'Max allowed')); ?>: ' + ev.total_capacity);
            if (parseInt($('#session-capacity').val()) > ev.total_capacity) {
                $('#session-capacity').val(ev.total_capacity);
            }
        } else {
            $('#session-capacity').removeAttr('max');
            $('#capacity-hint').text('<?php echo esc_js(sc_t('sessions.capacity_hint', '0 = unlimited')); ?>');
        }

        // Load speakers for this event
        loadEventSpeakers(eventId);
    });

    function loadEventSpeakers(eventId) {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_speakers_list',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success) {
                eventSpeakers = response.data.speakers || [];
                updateAllSpeakerDropdowns();
            }
        });
    }

    function updateAllSpeakerDropdowns() {
        $('.speaker-select').each(function() {
            var currentVal = $(this).val();
            $(this).html(buildSpeakerOptions(currentVal));
        });
    }

    // ==========================================
    // SPEAKERS
    // ==========================================
    function buildSpeakerOptions(selectedId) {
        var html = '<option value="">' + sessionTranslations.select_speaker + '</option>';
        eventSpeakers.forEach(function(sp) {
            var selected = (sp.id == selectedId) ? ' selected' : '';
            var label = sp.name + (sp.title ? ' (' + sp.title + ')' : '');
            html += '<option value="' + sp.id + '"' + selected + '>' + label + '</option>';
        });
        var customSelected = (selectedId === '0' || selectedId === 0) ? ' selected' : '';
        html += '<option value="0"' + customSelected + '>' + sessionTranslations.other_type_name + '</option>';
        return html;
    }

    function addSpeakerRow(data) {
        data = data || {};
        var idx = speakerIndex++;
        var isCustom = (data.id === 0 || data.id === '0') && data.custom_name;

        var row = '<div class="speaker-row border rounded p-3 mb-2" data-index="' + idx + '">' +
            '<div class="row">' +
                '<div class="col-md-5">' +
                    '<div class="form-group mb-2">' +
                        '<label><?php echo esc_js(sc_t('nav.speakers', 'Speaker')); ?></label>' +
                        '<select class="form-control speaker-select" name="speakers[' + idx + '][id]">' +
                            buildSpeakerOptions(data.id || '') +
                        '</select>' +
                        '<input type="text" class="form-control mt-1 speaker-custom-name" name="speakers[' + idx + '][custom_name]" value="' + (data.custom_name || '') + '" placeholder="<?php echo esc_js(sc_t('sessions.type_speaker_name', 'Type speaker name')); ?>" style="' + (isCustom ? '' : 'display:none') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<div class="form-group mb-2">' +
                        '<label><?php echo esc_js(sc_t('sessions.speaker_role', 'Role')); ?></label>' +
                        '<input type="text" class="form-control" name="speakers[' + idx + '][role]" value="' + (data.role || 'speaker') + '" placeholder="speaker">' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<div class="form-group mb-2">' +
                        '<label><?php echo esc_js(sc_t('sessions.presentation_title', 'Presentation')); ?></label>' +
                        '<input type="text" class="form-control" name="speakers[' + idx + '][presentation_title]" value="' + (data.presentation_title || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-1 d-flex align-items-end">' +
                    '<button type="button" class="btn btn-sm btn-danger mb-2 remove-speaker-row"><i class="fa fa-times"></i></button>' +
                '</div>' +
            '</div>' +
            '<input type="hidden" name="speakers[' + idx + '][sort_order]" value="' + idx + '">' +
        '</div>';

        $('#session-speakers-list').append(row);
    }

    // Show/hide custom name input
    $(document).on('change', '.speaker-select', function() {
        var customInput = $(this).closest('.form-group').find('.speaker-custom-name');
        if ($(this).val() === '0') {
            customInput.show().focus();
        } else {
            customInput.hide().val('');
        }
    });

    $('#add-speaker-btn').on('click', function() {
        if (!$('#session-event').val()) {
            toastr.warning(sessionTranslations.select_event_first);
            return;
        }
        addSpeakerRow();
    });

    $(document).on('click', '.remove-speaker-row', function() {
        $(this).closest('.speaker-row').remove();
    });

    // ==========================================
    // FORM SUBMISSION
    // ==========================================
    $('#session-create-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#save-session-btn');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + sessionTranslations.saving);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(sessionTranslations.session_created);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/sessions'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data.message || sessionTranslations.error_creating);
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + sessionTranslations.add_session);
                }
            },
            error: function() {
                toastr.error(sessionTranslations.error_occurred);
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + sessionTranslations.add_session);
            }
        });
    });

    // Trigger event change if pre-selected
    if ($('#session-event').val()) {
        $('#session-event').trigger('change');
    }
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
.speaker-row { background: #f8f9fa; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
