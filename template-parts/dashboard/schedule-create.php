<?php
/**
 * Dashboard - Create Schedule Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb;

// Load events
$events_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_results("SELECT id, title FROM $events_table ORDER BY title ASC");

// Load halls
$halls = array();
$halls_table = $wpdb->prefix . 'sc_halls';
if ($wpdb->get_var("SHOW TABLES LIKE '$halls_table'") === $halls_table) {
    $halls = $wpdb->get_results("SELECT id, name FROM $halls_table WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
}

// Load speakers
$speakers = array();
$speakers_table = $wpdb->prefix . 'sc_speakers';
if ($wpdb->get_var("SHOW TABLES LIKE '$speakers_table'") === $speakers_table) {
    $speakers = $wpdb->get_results("SELECT id, name FROM $speakers_table ORDER BY name ASC");
}

// Pre-selected event from query string
$preselect_event = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

$page_title = sc_t('dashboard_pages.add_schedule', 'Add Schedule');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.add_schedule', 'Add Schedule')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/schedules'); ?>"><?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.add', 'Add')); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/schedules'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo esc_html(sc_t('dashboard_pages.back_to_schedules', 'Back to Schedules')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="schedule-form">
            <?php wp_nonce_field('sc_schedule_action', 'sc_schedule_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_schedule">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-calendar-check-o"></i> <?php echo esc_html(sc_t('dashboard_pages.schedule_details', 'Schedule Details')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="schedule-event"><?php echo esc_html(sc_t('nav.events', 'Event')); ?> <span class="text-danger">*</span></label>
                                <select class="form-control" id="schedule-event" name="event_id" required>
                                    <option value=""><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo esc_attr($event->id); ?>" <?php echo $preselect_event == $event->id ? 'selected' : ''; ?>><?php echo esc_html($event->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="schedule-type"><?php echo esc_html(sc_t('dashboard_pages.type', 'Type')); ?> <span class="text-danger">*</span></label>
                                <select class="form-control" id="schedule-type" name="type">
                                    <option value="session"><?php echo esc_html(sc_t('dashboard_pages.session', 'Session')); ?></option>
                                    <option value="registration"><?php echo esc_html(sc_t('dashboard_pages.registration_type', 'Registration')); ?></option>
                                    <option value="break"><?php echo esc_html(sc_t('dashboard_pages.break_type', 'Break')); ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="schedule-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="schedule-title" name="title" required placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_schedule_title', 'Enter schedule title')); ?>">
                            </div>

                            <div class="form-group">
                                <label for="schedule-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                                <textarea class="form-control" id="schedule-description" name="description" rows="3" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_description', 'Enter description...')); ?>"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="schedule-date"><?php echo esc_html(sc_t('dashboard_pages.date', 'Date')); ?> <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="schedule-date" name="schedule_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="schedule-start"><?php echo esc_html(sc_t('dashboard_pages.start_time', 'Start Time')); ?> <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="schedule-start" name="start_time" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="schedule-end"><?php echo esc_html(sc_t('dashboard_pages.end_time', 'End Time')); ?> <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="schedule-end" name="end_time" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="schedule-hall"><?php echo esc_html(sc_t('nav.halls', 'Hall')); ?></label>
                                        <select class="form-control" id="schedule-hall" name="hall_id">
                                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.no_hall', 'No Hall')); ?></option>
                                            <?php foreach ($halls as $hall): ?>
                                                <option value="<?php echo esc_attr($hall->id); ?>"><?php echo esc_html($hall->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="schedule-speaker"><?php echo esc_html(sc_t('dashboard_pages.speaker', 'Speaker')); ?></label>
                                        <select class="form-control" id="schedule-speaker" name="speaker_id">
                                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.no_speaker', 'No Speaker')); ?></option>
                                            <?php foreach ($speakers as $speaker): ?>
                                                <option value="<?php echo esc_attr($speaker->id); ?>"><?php echo esc_html($speaker->name); ?></option>
                                            <?php endforeach; ?>
                                            <option value="other"><?php echo esc_html(sc_t('dashboard_pages.other_type_name', 'Other (type name)')); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" id="speaker-name-group" style="display:none;">
                                <label for="schedule-speaker-name"><?php echo esc_html(sc_t('dashboard_pages.speaker_name', 'Speaker Name')); ?></label>
                                <input type="text" class="form-control" id="schedule-speaker-name" name="speaker_name" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_speaker_name', 'Enter speaker name')); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Registration Timing -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sign-in"></i> <?php echo esc_html(sc_t('registration', 'Registration')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="registration_start"><?php echo esc_html(sc_t('dashboard_pages.start_time', 'Start Time')); ?></label>
                                        <input type="time" class="form-control" id="registration_start" name="registration_start">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="registration_end"><?php echo esc_html(sc_t('dashboard_pages.end_time', 'End Time')); ?></label>
                                        <input type="time" class="form-control" id="registration_end" name="registration_end">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Break & Expo Visit Timing -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-coffee"></i> <?php echo esc_html(sc_t('break_expo_visit', 'Break & Expo Visit')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="break_start"><?php echo esc_html(sc_t('dashboard_pages.start_time', 'Start Time')); ?></label>
                                        <input type="time" class="form-control" id="break_start" name="break_start">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="break_end"><?php echo esc_html(sc_t('dashboard_pages.end_time', 'End Time')); ?></label>
                                        <input type="time" class="form-control" id="break_end" name="break_end">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.publish', 'Publish')); ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="schedule-sort-order"><?php echo esc_html(sc_t('dashboard_pages.sort_order', 'Sort Order')); ?></label>
                                <input type="number" class="form-control" id="schedule-sort-order" name="sort_order" min="0" value="0">
                                <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first')); ?></small>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?>: <span class="badge badge-secondary"><?php echo esc_html(sc_t('dashboard_pages.new', 'New')); ?></span>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-schedule-btn">
                                    <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.save_schedule', 'Save Schedule')); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle speaker name field
    $('#schedule-speaker').on('change', function() {
        if ($(this).val() === 'other') {
            $('#speaker-name-group').show();
        } else {
            $('#speaker-name-group').hide();
            $('#schedule-speaker-name').val('');
        }
    });

    // Form submission
    $('#schedule-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#save-schedule-btn');

        // If speaker is "other", clear speaker_id so it sends empty
        var formData = $(this).serializeArray();
        var filtered = formData.filter(function(item) {
            if (item.name === 'speaker_id' && item.value === 'other') {
                item.value = '';
            }
            return true;
        });

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo esc_js(sc_t('dashboard_pages.saving', 'Saving...')); ?>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $.param(filtered),
            success: function(response) {
                if (response.success) {
                    toastr.success('<?php echo esc_js(sc_t('dashboard_pages.schedule_created', 'Schedule created successfully!')); ?>');
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/schedules'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_creating_schedule', 'Error creating schedule')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.save_schedule', 'Save Schedule')); ?>');
                }
            },
            error: function() {
                toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.save_schedule', 'Save Schedule')); ?>');
            }
        });
    });
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
