<?php
/**
 * Dashboard - Edit Schedule Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$schedule_id) {
    wp_redirect(home_url('/event-manager-dashboard/schedules'));
    exit;
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

$page_title = sc_t('dashboard_pages.edit_schedule', 'Edit Schedule');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.edit_schedule', 'Edit Schedule')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/schedules'); ?>"><?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></li>
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

        <!-- Loading State -->
        <div id="schedule-loading" class="text-center py-5">
            <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
            <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
        </div>

        <!-- Schedule Form -->
        <div id="schedule-form-container" style="display:none;">
            <form id="schedule-form">
                <?php wp_nonce_field('sc_schedule_action', 'sc_schedule_nonce'); ?>
                <input type="hidden" name="action" value="sc_save_schedule">
                <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule_id); ?>">

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
                                            <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
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
                                    <input type="text" class="form-control" id="schedule-title" name="title" required>
                                </div>

                                <div class="form-group">
                                    <label for="schedule-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                                    <textarea class="form-control" id="schedule-description" name="description" rows="3"></textarea>
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
                                    <input type="text" class="form-control" id="schedule-speaker-name" name="speaker_name">
                                </div>
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
                            <div class="header bg-primary">
                                <h2 class="text-white"><i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.update', 'Update')); ?></h2>
                            </div>
                            <div class="body">
                                <div class="form-group">
                                    <label for="schedule-sort-order"><?php echo esc_html(sc_t('dashboard_pages.sort_order', 'Sort Order')); ?></label>
                                    <input type="number" class="form-control" id="schedule-sort-order" name="sort_order" min="0" value="0">
                                    <small class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.sort_order_hint', 'Lower numbers appear first')); ?></small>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-schedule-btn">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.update_schedule', 'Update Schedule')); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="card">
                            <div class="header bg-danger">
                                <h2 class="text-white"><i class="fa fa-exclamation-triangle"></i> <?php echo esc_html(sc_t('dashboard_pages.danger_zone', 'Danger Zone')); ?></h2>
                            </div>
                            <div class="body">
                                <p class="text-muted small"><?php echo esc_html(sc_t('dashboard_pages.delete_schedule_warning', 'This action cannot be undone.')); ?></p>
                                <button type="button" class="btn btn-outline-danger btn-block" id="delete-schedule-btn">
                                    <i class="fa fa-trash"></i> <?php echo esc_html(sc_t('dashboard_pages.delete_schedule', 'Delete Schedule')); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var scheduleId = <?php echo intval($schedule_id); ?>;

    // Toggle speaker name field
    $('#schedule-speaker').on('change', function() {
        if ($(this).val() === 'other') {
            $('#speaker-name-group').show();
        } else {
            $('#speaker-name-group').hide();
            $('#schedule-speaker-name').val('');
        }
    });

    // Load schedule data
    $.ajax({
        url: scDashboard.ajaxurl,
        type: 'POST',
        data: { action: 'sc_get_schedule', nonce: scDashboard.nonce, schedule_id: scheduleId }
    }).done(function(response) {
        if (response.success) {
            var s = response.data.schedule;
            $('#schedule-event').val(s.event_id);
            $('#schedule-type').val(s.type || 'session');
            $('#schedule-title').val(s.title);
            $('#schedule-description').val(s.description);
            $('#schedule-date').val(s.schedule_date);
            $('#schedule-start').val(s.start_time ? s.start_time.substring(0, 5) : '');
            $('#schedule-end').val(s.end_time ? s.end_time.substring(0, 5) : '');
            $('#schedule-hall').val(s.hall_id || '');
            $('#schedule-sort-order').val(s.sort_order);

            // Speaker: if speaker_id exists, select it. If only speaker_name, select "other"
            if (s.speaker_id) {
                $('#schedule-speaker').val(s.speaker_id);
            } else if (s.speaker_name) {
                $('#schedule-speaker').val('other');
                $('#schedule-speaker-name').val(s.speaker_name);
                $('#speaker-name-group').show();
            }

            // Registration & Break timings
            $('#registration_start').val(s.registration_start ? s.registration_start.substring(0, 5) : '');
            $('#registration_end').val(s.registration_end ? s.registration_end.substring(0, 5) : '');
            $('#break_start').val(s.break_start ? s.break_start.substring(0, 5) : '');
            $('#break_end').val(s.break_end ? s.break_end.substring(0, 5) : '');

            $('#schedule-loading').hide();
            $('#schedule-form-container').show();
        } else {
            toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.schedule_not_found', 'Schedule not found')); ?>');
        }
    }).fail(function() {
        toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
    });

    // Form submission
    $('#schedule-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#save-schedule-btn');

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
                    toastr.success(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.schedule_updated', 'Schedule updated successfully!')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_schedule', 'Update Schedule')); ?>');
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_updating_schedule', 'Error updating schedule')); ?>');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_schedule', 'Update Schedule')); ?>');
                }
            },
            error: function() {
                toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.')); ?>');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.update_schedule', 'Update Schedule')); ?>');
            }
        });
    });

    // Delete schedule
    $('#delete-schedule-btn').on('click', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: { action: 'sc_delete_schedule', nonce: scDashboard.nonce, schedule_id: scheduleId }
            }).done(function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/schedules'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_schedule', 'Error deleting schedule')); ?>');
                }
            });
        });
    });
});
</script>

<style>
.card .header h2 { font-size: 16px; }
.form-group label { font-weight: 500; margin-bottom: 5px; }
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
