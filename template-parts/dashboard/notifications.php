<?php
/**
 * Dashboard - Push Notifications Page
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
$events_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_results("SELECT id, title FROM $events_table WHERE status = 'publish' ORDER BY title ASC");

$fcm_key = get_option('sc_fcm_server_key', '');
$has_key = !empty($fcm_key);

$page_title = sc_t('nav.notifications', 'Push Notifications');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<style>
/* ── Notifications Page Styles ── */
.notif-stat-card {
    border-radius: 16px;
    padding: 24px 20px;
    display: flex;
    align-items: center;
    gap: 18px;
    color: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.notif-stat-card::after {
    content: '';
    position: absolute;
    right: -20px;
    top: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
}
.notif-stat-card .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(255,255,255,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}
.notif-stat-card .stat-label {
    font-size: 12px;
    opacity: .85;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 4px;
}
.notif-stat-card .stat-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
}
.notif-stat-card.blue  { background: linear-gradient(135deg, #1565c0, #42a5f5); }
.notif-stat-card.green { background: linear-gradient(135deg, #2e7d32, #66bb6a); }
.notif-stat-card.purple{ background: linear-gradient(135deg, #6a1b9a, #ab47bc); }
.notif-stat-card.amber { background: linear-gradient(135deg, #e65100, #ffa726); }
.notif-stat-card.red   { background: linear-gradient(135deg, #b71c1c, #ef5350); }
.notif-stat-card.teal  { background: linear-gradient(135deg, #00695c, #26a69a); }

/* Send form card */
.notif-compose-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    overflow: hidden;
    margin-bottom: 24px;
}
.notif-compose-card .card-head {
    padding: 20px 24px 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 16px;
}
.notif-compose-card .card-head .head-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1565c0, #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 16px;
}
.notif-compose-card .card-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1a1a2e;
}
.notif-compose-card .card-body { padding: 24px; }

/* Target selector */
.notif-target-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 0;
}
.notif-target-option {
    position: relative;
    cursor: pointer;
}
.notif-target-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.notif-target-option .option-box {
    border: 2px solid #e8e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all .2s;
    background: #fafafe;
    cursor: pointer;
}
.notif-target-option input:checked + .option-box {
    border-color: #1565c0;
    background: #e8f0fe;
}
.notif-target-option .option-box i {
    font-size: 18px;
    color: #888;
    width: 20px;
    text-align: center;
}
.notif-target-option input:checked + .option-box i {
    color: #1565c0;
}
.notif-target-option .option-box .opt-label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
}
.notif-target-option .option-box .opt-sub {
    font-size: 11px;
    color: #999;
}
.notif-target-option input:checked + .option-box .opt-label {
    color: #1565c0;
}

/* Send button */
.btn-notif-send {
    background: linear-gradient(135deg, #1565c0, #42a5f5);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 12px 32px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all .2s;
    box-shadow: 0 4px 12px rgba(21,101,192,.3);
}
.btn-notif-send:hover { opacity: .9; transform: translateY(-1px); }
.btn-notif-send:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* History card */
.notif-history-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    overflow: hidden;
}
.notif-history-card .card-head {
    padding: 20px 24px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.notif-history-card .card-head .head-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6a1b9a, #ab47bc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 16px;
}
.notif-history-card table thead th {
    background: #f8f9ff;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #666;
    border-top: none;
    padding: 12px 16px;
}
.notif-history-card table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    font-size: 13px;
    border-color: #f0f0f0;
}
.notif-history-card table tbody tr:hover { background: #fafafe; }
.badge-recipients {
    background: #e8f0fe;
    color: #1565c0;
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 600;
}
.badge-status {
    background: #e8f5e9;
    color: #2e7d32;
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.notif-time { color: #999; font-size: 11px; }

/* Sidebar cards */
.notif-side-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    overflow: hidden;
    margin-bottom: 20px;
}
.notif-side-card .side-head {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f0f0f0;
}
.notif-side-card .side-head .head-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 15px;
}
.notif-side-card .side-head h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
}
.notif-side-card .side-body { padding: 20px; }

.fcm-key-input {
    position: relative;
}
.fcm-key-input input {
    border-radius: 10px !important;
    border: 2px solid #e8e8f0 !important;
    padding: 10px 16px !important;
    font-size: 13px !important;
    transition: border-color .2s !important;
}
.fcm-key-input input:focus {
    border-color: #1565c0 !important;
    box-shadow: none !important;
}

.btn-save-key {
    width: 100%;
    background: linear-gradient(135deg, #2e7d32, #66bb6a);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 11px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: opacity .2s;
    margin-top: 12px;
}
.btn-save-key:hover { opacity: .9; }

.fcm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 14px;
}
.fcm-status-badge.ok  { background: #e8f5e9; color: #2e7d32; }
.fcm-status-badge.bad { background: #fff3e0; color: #e65100; }

/* How it works */
.how-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}
.how-item:last-child { margin-bottom: 0; }
.how-item .how-num {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1565c0, #42a5f5);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.how-item .how-text {
    font-size: 12px;
    color: #555;
    line-height: 1.5;
}
.how-item .how-text strong { color: #333; }

/* Form fields */
#notif-send-form .form-control {
    border-radius: 10px;
    border: 2px solid #e8e8f0;
    padding: 10px 14px;
    font-size: 14px;
    transition: border-color .2s;
}
#notif-send-form .form-control:focus {
    border-color: #1565c0;
    box-shadow: none;
}
#notif-send-form label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 6px;
}

/* Empty state */
.notif-empty {
    text-align: center;
    padding: 40px 20px;
    color: #aaa;
}
.notif-empty i { font-size: 40px; margin-bottom: 10px; display: block; }
.notif-empty p { font-size: 13px; margin: 0; }
</style>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('nav.notifications', 'Push Notifications')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.notifications', 'Push Notifications')); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="notif-stat-card blue">
                    <div class="stat-icon"><i class="fa fa-mobile"></i></div>
                    <div>
                        <div class="stat-label"><?php echo esc_html(sc_t('notifications.registered_devices', 'Registered Devices')); ?></div>
                        <div class="stat-value" id="stat-tokens">–</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="notif-stat-card green">
                    <div class="stat-icon"><i class="fa fa-paper-plane"></i></div>
                    <div>
                        <div class="stat-label"><?php echo esc_html(sc_t('notifications.sent_today', 'Sent Today')); ?></div>
                        <div class="stat-value" id="stat-today">–</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="notif-stat-card purple">
                    <div class="stat-icon"><i class="fa fa-bell"></i></div>
                    <div>
                        <div class="stat-label"><?php echo esc_html(sc_t('notifications.total_sent', 'Total Sent')); ?></div>
                        <div class="stat-value" id="stat-total">–</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="notif-stat-card <?php echo $has_key ? 'teal' : 'amber'; ?>" id="stat-key-card">
                    <div class="stat-icon"><i class="fa fa-key"></i></div>
                    <div>
                        <div class="stat-label"><?php echo esc_html(sc_t('notifications.fcm_key_status', 'FCM Key')); ?></div>
                        <div class="stat-value" style="font-size:16px;" id="stat-fcm-key">–</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Column -->
            <div class="col-lg-8">

                <!-- Send Notification -->
                <div class="notif-compose-card">
                    <div class="card-head">
                        <div class="head-icon"><i class="fa fa-paper-plane"></i></div>
                        <div>
                            <h3><?php echo esc_html(sc_t('notifications.send_notification', 'Send Notification')); ?></h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="notif-send-form">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="notif-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="notif-title" name="notif_title" required
                                            placeholder="<?php echo esc_attr(sc_t('notifications.title_placeholder', 'Enter notification title...')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="notif-body"><?php echo esc_html(sc_t('notifications.message', 'Message')); ?> <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="notif-body" name="notif_body" rows="3" required
                                            placeholder="<?php echo esc_attr(sc_t('notifications.message_placeholder', 'Enter notification message...')); ?>"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><?php echo esc_html(sc_t('notifications.target_audience', 'Target Audience')); ?></label>
                                <div class="notif-target-group">
                                    <label class="notif-target-option">
                                        <input type="radio" name="notif_target" value="all" checked>
                                        <div class="option-box">
                                            <i class="fa fa-globe"></i>
                                            <div>
                                                <div class="opt-label"><?php echo esc_html(sc_t('notifications.all_users', 'All Users')); ?></div>
                                                <div class="opt-sub"><?php echo esc_html(sc_t('notifications.all_users_sub', 'Send to all registered devices')); ?></div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="notif-target-option">
                                        <input type="radio" name="notif_target" value="event">
                                        <div class="option-box">
                                            <i class="fa fa-calendar"></i>
                                            <div>
                                                <div class="opt-label"><?php echo esc_html(sc_t('notifications.event_attendees', 'Event Attendees')); ?></div>
                                                <div class="opt-sub"><?php echo esc_html(sc_t('notifications.event_attendees_sub', 'Send to specific event only')); ?></div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="notif-event-group" style="display:none;">
                                <label for="notif-event-id"><?php echo esc_html(sc_t('nav.events', 'Select Event')); ?></label>
                                <select class="form-control" id="notif-event-id" name="notif_event_id">
                                    <option value=""><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event...')); ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-top: 8px;">
                                <button type="submit" class="btn-notif-send" id="notif-send-btn">
                                    <i class="fa fa-paper-plane"></i>
                                    <?php echo esc_html(sc_t('notifications.send', 'Send Notification')); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- History -->
                <div class="notif-history-card">
                    <div class="card-head">
                        <div class="head-icon"><i class="fa fa-history"></i></div>
                        <div>
                            <h3 style="margin:0;font-size:16px;font-weight:600;color:#1a1a2e;"><?php echo esc_html(sc_t('notifications.history', 'Sent History')); ?></h3>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="notif-history-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.date', 'Date')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?></th>
                                    <th><?php echo esc_html(sc_t('notifications.message', 'Message')); ?></th>
                                    <th><?php echo esc_html(sc_t('nav.events', 'Event')); ?></th>
                                    <th><?php echo esc_html(sc_t('notifications.recipients', 'Recipients')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                </tr>
                            </thead>
                            <tbody id="notif-history-body">
                                <tr><td colspan="6" class="text-center" style="padding:30px;color:#aaa;"><i class="fa fa-spinner fa-spin"></i></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="notif-pagination" style="padding: 16px 24px; border-top: 1px solid #f0f0f0;"></div>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">

                <!-- FCM Key -->
                <div class="notif-side-card">
                    <div class="side-head">
                        <div class="head-icon" style="background: linear-gradient(135deg,#e65100,#ffa726);">
                            <i class="fa fa-key"></i>
                        </div>
                        <h4><?php echo esc_html(sc_t('notifications.fcm_server_key', 'FCM Server Key')); ?></h4>
                    </div>
                    <div class="side-body">
                        <?php if ($has_key): ?>
                        <div class="fcm-status-badge ok">
                            <i class="fa fa-check-circle"></i> <?php echo esc_html(sc_t('notifications.configured', 'Configured & Ready')); ?>
                        </div>
                        <?php else: ?>
                        <div class="fcm-status-badge bad">
                            <i class="fa fa-exclamation-triangle"></i> <?php echo esc_html(sc_t('notifications.not_configured', 'Not Configured')); ?>
                        </div>
                        <?php endif; ?>

                        <form id="fcm-key-form">
                            <div class="fcm-key-input">
                                <label style="font-size:12px;font-weight:600;color:#666;margin-bottom:6px;display:block;">
                                    <?php echo esc_html(sc_t('notifications.server_key', 'Server Key')); ?>
                                </label>
                                <input type="text" class="form-control" id="fcm-server-key" name="fcm_server_key"
                                    value="<?php echo esc_attr($fcm_key); ?>"
                                    placeholder="AAAA...">
                                <small style="display:block;margin-top:6px;font-size:11px;color:#999;">
                                    <i class="fa fa-info-circle"></i>
                                    <?php echo esc_html(sc_t('notifications.fcm_key_hint', 'Firebase Console → Project Settings → Cloud Messaging')); ?>
                                </small>
                            </div>
                            <button type="submit" class="btn-save-key" id="fcm-key-btn">
                                <i class="fa fa-save"></i> <?php echo esc_html(sc_t('dashboard_pages.save', 'Save Key')); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- How it works -->
                <div class="notif-side-card">
                    <div class="side-head">
                        <div class="head-icon" style="background: linear-gradient(135deg,#1565c0,#42a5f5);">
                            <i class="fa fa-info"></i>
                        </div>
                        <h4><?php echo esc_html(sc_t('notifications.how_it_works', 'How it works')); ?></h4>
                    </div>
                    <div class="side-body">
                        <div class="how-item">
                            <div class="how-num">1</div>
                            <div class="how-text"><strong><?php echo esc_html(sc_t('notifications.hint_1_title', 'Register Device')); ?></strong><br><?php echo esc_html(sc_t('notifications.hint_1', 'Mobile app registers its FCM token via the API after login')); ?></div>
                        </div>
                        <div class="how-item">
                            <div class="how-num">2</div>
                            <div class="how-text"><strong><?php echo esc_html(sc_t('notifications.hint_2_title', 'Auto Notify')); ?></strong><br><?php echo esc_html(sc_t('notifications.hint_2', 'New event created → notification sent to all devices instantly')); ?></div>
                        </div>
                        <div class="how-item">
                            <div class="how-num">3</div>
                            <div class="how-text"><strong><?php echo esc_html(sc_t('notifications.hint_3_title', 'Custom Send')); ?></strong><br><?php echo esc_html(sc_t('notifications.hint_3', 'Use this page to send custom messages to all users or event attendees')); ?></div>
                        </div>
                        <div class="how-item">
                            <div class="how-num">4</div>
                            <div class="how-text"><strong><?php echo esc_html(sc_t('notifications.hint_4_title', 'Logged')); ?></strong><br><?php echo esc_html(sc_t('notifications.hint_4', 'All sent notifications are logged with recipient count and timestamp')); ?></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    function loadStats() {
        $.post(scDashboard.ajaxurl, { action: 'sc_get_notifications_stats', nonce: scDashboard.nonce }, function(res) {
            if (!res.success) return;
            var d = res.data;
            $('#stat-tokens').text(d.total_tokens);
            $('#stat-today').text(d.sent_today);
            $('#stat-total').text(d.total_sent);
            if (d.has_fcm_key) {
                $('#stat-fcm-key').text('<?php echo esc_js(sc_t('notifications.configured', 'Configured')); ?>');
                $('#stat-key-card').removeClass('amber red').addClass('teal');
            } else {
                $('#stat-fcm-key').text('<?php echo esc_js(sc_t('notifications.not_configured', 'Not Set')); ?>');
                $('#stat-key-card').removeClass('teal').addClass('amber');
            }
        });
    }

    function loadHistory(page) {
        page = page || 1;
        $('#notif-history-body').html('<tr><td colspan="6" style="padding:30px;text-align:center;color:#aaa;"><i class="fa fa-spinner fa-spin fa-lg"></i></td></tr>');
        $.post(scDashboard.ajaxurl, { action: 'sc_get_notifications_history', nonce: scDashboard.nonce, page: page }, function(res) {
            if (!res.success) return;
            var d = res.data;
            var html = '';
            if (d.items.length === 0) {
                html = '<tr><td colspan="6"><div class="notif-empty"><i class="fa fa-bell-slash"></i><p><?php echo esc_js(sc_t('notifications.no_history', 'No notifications sent yet.')); ?></p></div></td></tr>';
            } else {
                $.each(d.items, function(i, n) {
                    var evTitle = n.event_title ? '<span style="font-size:11px;background:#f0f0f8;padding:2px 8px;border-radius:20px;">' + $('<s>').text(n.event_title).html() + '</span>' : '<span style="color:#ccc;">–</span>';
                    var dateStr = n.created_at ? n.created_at.substring(0, 16) : '';
                    html += '<tr>';
                    html += '<td><div class="notif-time"><i class="fa fa-clock-o"></i> ' + dateStr + '</div></td>';
                    html += '<td><strong style="font-size:13px;">' + $('<s>').text(n.title).html() + '</strong></td>';
                    html += '<td style="max-width:160px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#666;font-size:12px;" title="' + $('<s>').attr('title', n.body).prop('outerHTML').replace(/<[^>]+>/g,'') + '">' + $('<s>').text(n.body).html() + '</span></td>';
                    html += '<td>' + evTitle + '</td>';
                    html += '<td><span class="badge-recipients"><i class="fa fa-mobile"></i> ' + n.recipient_count + '</span></td>';
                    html += '<td><span class="badge-status">' + n.status + '</span></td>';
                    html += '</tr>';
                });
            }
            $('#notif-history-body').html(html);
            var paging = '';
            if (d.last_page > 1) {
                paging = '<ul class="pagination pagination-sm" style="margin:0;">';
                for (var p = 1; p <= d.last_page; p++) {
                    paging += '<li class="page-item' + (p === d.page ? ' active' : '') + '"><a class="page-link notif-page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
                }
                paging += '</ul>';
            }
            $('#notif-pagination').html(paging);
        });
    }

    $(document).on('click', '.notif-page-link', function(e) {
        e.preventDefault();
        loadHistory($(this).data('page'));
    });

    $('input[name="notif_target"]').on('change', function() {
        $('#notif-event-group').toggle($(this).val() === 'event');
    });

    $('#notif-send-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#notif-send-btn');
        var data = $(this).serializeArray();
        data.push({name:'action',value:'sc_send_notification'},{name:'nonce',value:scDashboard.nonce});
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo esc_js(sc_t('dashboard_pages.saving', 'Sending...')); ?>');
        $.post(scDashboard.ajaxurl, $.param(data), function(res) {
            if (res.success) {
                toastr.success(res.data.message);
                $('#notif-send-form')[0].reset();
                $('#notif-event-group').hide();
                loadStats(); loadHistory(1);
            } else {
                toastr.error(res.data.message || '<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'Error occurred.')); ?>');
            }
            $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> <?php echo esc_js(sc_t('notifications.send', 'Send Notification')); ?>');
        }).fail(function() {
            toastr.error('<?php echo esc_js(sc_t('dashboard_pages.error_occurred', 'Error occurred.')); ?>');
            $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> <?php echo esc_js(sc_t('notifications.send', 'Send Notification')); ?>');
        });
    });

    $('#fcm-key-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#fcm-key-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post(scDashboard.ajaxurl, { action:'sc_save_fcm_key', nonce:scDashboard.nonce, fcm_server_key:$('#fcm-server-key').val() }, function(res) {
            if (res.success) { toastr.success(res.data.message); loadStats(); }
            else { toastr.error(res.data.message); }
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js(sc_t('dashboard_pages.save', 'Save Key')); ?>');
        });
    });

    loadStats();
    loadHistory(1);
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
