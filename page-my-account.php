<?php
/**
 * Template Name: My Account Page
 * Public Frontend User Dashboard - Dark & Premium Design
 *
 * @package sc_events
 * @version 5.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/?redirect=' . urlencode(home_url('/my-account/'))));
    exit;
}

// Enqueue account page styles
wp_enqueue_style('sc-account-page', get_template_directory_uri() . '/assets/frontend/css/account-page.css', array(), '5.0.0');

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$user_phone = get_user_meta($user_id, 'phone', true);
$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Get user's tickets/registrations from Custom Tables
$attendees = array();
$total_tickets = 0;
$upcoming_tickets = 0;
$past_tickets = 0;

if (class_exists('SC_Attendee')) {
    // Tickets registered by the user themselves carry user_id; tickets created by
    // an organizer from the dashboard usually only carry an email. Pull both sets
    // and merge — a single source misses one of the two flows.
    $by_user  = SC_Attendee::get_by_user($user_id, array(
        'status' => 'active',
        'payment_status' => 'success',
        'limit' => 100,
        'order' => 'DESC',
    ));
    $by_email = SC_Attendee::search_by_email($current_user->user_email);

    $merged = array();
    foreach (array_merge($by_user, $by_email) as $row) {
        if (!$row || empty($row->id)) {
            continue;
        }
        // Filter cancelled/transferred tickets that search_by_email doesn't filter
        if (!empty($row->status) && $row->status !== 'active') {
            continue;
        }
        $merged[(int) $row->id] = $row; // dedupe by ID
    }
    $attendees = array_values($merged);
    $total_tickets = count($attendees);

    // Count upcoming vs past
    foreach ($attendees as $attendee) {
        if (class_exists('SC_Event')) {
            $event = SC_Event::get($attendee->event_id);
            if ($event) {
                $ev_end = ($event->end_date ?: $event->start_date) . ($event->end_time ? ' ' . $event->end_time : ' 23:59:59');
                if (strtotime($ev_end) >= time()) {
                    $upcoming_tickets++;
                } else {
                    $past_tickets++;
                }
            }
        }
    }
}

// Get user's favorites
$favorite_events = array();
$favorites_count = 0;
global $wpdb;
$fav_table = $wpdb->prefix . 'sc_favorites';
if ($wpdb->get_var("SHOW TABLES LIKE '$fav_table'") === $fav_table) {
    $fav_event_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT event_id FROM $fav_table WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    ));
    $favorites_count = count($fav_event_ids);
    if (!empty($fav_event_ids) && class_exists('SC_Event')) {
        foreach ($fav_event_ids as $fav_eid) {
            $fev = SC_Event::get($fav_eid);
            if ($fev) $favorite_events[] = $fev;
        }
    }
}

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<div class="sc-page-header">
    <div class="container">
        <h1><?php esc_html_e('My Account', 'sc_events'); ?></h1>
        <div class="sc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php esc_html_e('My Account', 'sc_events'); ?></span>
        </div>
    </div>
</div>

<!-- Account Section -->
<section class="sc-account-section">
    <div class="container">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3 col-md-4" data-aos="fade-right" data-aos-duration="600">
                <div class="sc-account-sidebar">
                    <!-- User Info -->
                    <div class="sc-user-info">
                        <div class="sc-user-avatar">
                            <?php echo get_avatar($user_id, 100, '', '', array('class' => 'avatar-img')); ?>
                        </div>
                        <h4 class="sc-user-name"><?php echo esc_html($current_user->display_name); ?></h4>
                        <p class="sc-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                    </div>

                    <!-- Stats -->
                    <div class="sc-user-stats">
                        <div class="sc-stat-item">
                            <span class="sc-stat-number"><?php echo $total_tickets; ?></span>
                            <span class="sc-stat-label"><?php esc_html_e('Total', 'sc_events'); ?></span>
                        </div>
                        <div class="sc-stat-item">
                            <span class="sc-stat-number"><?php echo $upcoming_tickets; ?></span>
                            <span class="sc-stat-label"><?php esc_html_e('Upcoming', 'sc_events'); ?></span>
                        </div>
                        <div class="sc-stat-item">
                            <span class="sc-stat-number"><?php echo $past_tickets; ?></span>
                            <span class="sc-stat-label"><?php esc_html_e('Past', 'sc_events'); ?></span>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <?php
                    // Count user's certificates so we can badge the new nav item
                    $user_cert_count = 0;
                    if (!empty($attendees) && class_exists('SC_Certificate')) {
                        foreach ($attendees as $att) {
                            $c = SC_Certificate::get_by_attendee_event($att->id, $att->event_id);
                            if (is_array($c) && ($c['status'] ?? '') !== 'revoked') {
                                $user_cert_count++;
                            }
                        }
                    }
                    ?>
                    <div class="sc-account-nav">
                        <a href="#tickets" class="sc-nav-item active" data-tab="tickets">
                            <i class="fa-solid fa-ticket"></i>
                            <span><?php esc_html_e('My Tickets', 'sc_events'); ?></span>
                        </a>
                        <a href="#certificates" class="sc-nav-item" data-tab="certificates">
                            <i class="fa-solid fa-certificate"></i>
                            <span><?php esc_html_e('My Certificates', 'sc_events'); ?> <?php if ($user_cert_count > 0): ?><span class="sc-nav-badge"><?php echo $user_cert_count; ?></span><?php endif; ?></span>
                        </a>
                        <a href="#favorites" class="sc-nav-item" data-tab="favorites">
                            <i class="fa-solid fa-heart"></i>
                            <span><?php esc_html_e('My Favorites', 'sc_events'); ?> <?php if ($favorites_count > 0): ?><span class="sc-nav-badge"><?php echo $favorites_count; ?></span><?php endif; ?></span>
                        </a>
                        <a href="#profile" class="sc-nav-item" data-tab="profile">
                            <i class="fa-solid fa-user-pen"></i>
                            <span><?php esc_html_e('Profile Settings', 'sc_events'); ?></span>
                        </a>
                        <a href="#" class="sc-nav-item sc-nav-logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span><?php esc_html_e('Logout', 'sc_events'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="col-lg-9 col-md-8" data-aos="fade-left" data-aos-duration="600">
                <!-- Tickets Tab -->
                <div class="sc-tab-content" id="tickets-tab" data-tab-content="tickets">
                    <div class="sc-content-header">
                        <h3><i class="fa-solid fa-ticket"></i> <?php esc_html_e('My Tickets', 'sc_events'); ?></h3>
                    </div>

                    <?php if (!empty($attendees)): ?>
                    <div class="sc-tickets-list">
                        <?php foreach ($attendees as $attendee):
                            // Get event from Custom Tables
                            $event = class_exists('SC_Event') ? SC_Event::get($attendee->event_id) : null;
                            if (!$event) continue;

                            $ev_end_dt = ($event->end_date ?: $event->start_date) . ($event->end_time ? ' ' . $event->end_time : '');
                            $is_past = strtotime($ev_end_dt) < time();
                            $status = $attendee->payment_status;

                            // Attendance data
                            $checked_in = !empty($attendee->checked_in);
                            $checkin_time = $attendee->checked_in_at ?? null;
                            $checkout_time = null;
                            $duration = null;

                            if ($checked_in && class_exists('SC_Checkin')) {
                                $checkin_logs = SC_Checkin::get_by_attendee($attendee->id);
                                foreach ($checkin_logs as $log) {
                                    if (in_array($log->action, array('checkout', 'manual_checkout', 'check_out'))) {
                                        $checkout_time = $log->created_at;
                                        break;
                                    }
                                }
                                if ($checkin_time && $checkout_time) {
                                    $diff = strtotime($checkout_time) - strtotime($checkin_time);
                                    if ($diff > 0) {
                                        $hours = floor($diff / 3600);
                                        $mins = floor(($diff % 3600) / 60);
                                        $duration = ($hours > 0 ? $hours . 'h ' : '') . $mins . 'm';
                                    }
                                }
                            }

                            // Session attendance
                            $session_stats = null;
                            $session_details = array();
                            if (class_exists('SC_Session_Attendance')) {
                                $sessions = SC_Session_Attendance::get_attendee_summary($attendee->id, $event->id);
                                if (!empty($sessions)) {
                                    $attended_sessions = 0;
                                    $total_cme = 0;
                                    foreach ($sessions as $s) {
                                        if (!empty($s->check_in_time)) $attended_sessions++;
                                        $total_cme += floatval($s->earned_cme_hours ?? 0);
                                    }
                                    $session_stats = array(
                                        'attended' => $attended_sessions,
                                        'total'    => count($sessions),
                                        'cme'      => $total_cme
                                    );
                                    $session_details = $sessions;
                                }
                            }

                            // Certificate data
                            $certificate = null;
                            $cert_eligible = false;
                            $cert_download_url = null;
                            if (!empty($event->enable_certificates) && class_exists('SC_Certificate')) {
                                $certificate = SC_Certificate::get_by_attendee_event($attendee->id, $event->id);
                                if ($certificate && ($certificate['status'] ?? '') !== 'revoked') {
                                    $download_token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);
                                    $cert_download_url = add_query_arg(array(
                                        'action' => 'sc_download_certificate',
                                        'id'     => $certificate['id'],
                                        'token'  => $download_token
                                    ), admin_url('admin-ajax.php'));
                                } elseif (!$certificate) {
                                    // Check eligibility (no cert issued yet)
                                    $meets_checkin = empty($event->certificate_require_checkin) || $checked_in;
                                    $meets_checkout = empty($event->certificate_require_checkout) || $checkout_time;
                                    $meets_ended = empty($event->certificate_require_event_ended) || $is_past;
                                    $cert_eligible = $meets_checkin && $meets_checkout && $meets_ended;
                                }
                            }

                            // Event status label (use end_date + end_time for accuracy)
                            $event_end = $event->end_date ?: $event->start_date;
                            $event_end_full = $event_end . ($event->end_time ? ' ' . $event->end_time : ' 23:59:59');
                            $is_ended = strtotime($event_end_full) < time();
                            $is_today = !$is_ended && (
                                date('Y-m-d', strtotime($event->start_date)) <= date('Y-m-d') &&
                                date('Y-m-d', strtotime($event_end)) >= date('Y-m-d')
                            );
                        ?>
                        <div class="sc-ticket-card <?php echo $is_past ? 'past-ticket' : ''; ?>">
                            <div class="sc-ticket-image">
                                <?php if ($event->featured_image && wp_get_attachment_url($event->featured_image)): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($event->featured_image)); ?>" alt="<?php echo esc_attr($event->title); ?>">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.03);">
                                        <i class="fa-solid fa-calendar-days" style="font-size:40px;color:var(--sc-text-muted);"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if ($is_past && $checked_in): ?>
                                <div class="sc-ticket-badge attended"><i class="fa-solid fa-circle-check"></i> <?php esc_html_e('Attended', 'sc_events'); ?></div>
                                <?php elseif ($is_past && !$checked_in): ?>
                                <div class="sc-ticket-badge missed"><?php esc_html_e('Missed', 'sc_events'); ?></div>
                                <?php elseif ($checked_in): ?>
                                <div class="sc-ticket-badge checked-in"><i class="fa-solid fa-circle-check"></i> <?php esc_html_e('Checked In', 'sc_events'); ?></div>
                                <?php elseif ($status === 'success'): ?>
                                <div class="sc-ticket-badge confirmed"><?php esc_html_e('Confirmed', 'sc_events'); ?></div>
                                <?php elseif ($status === 'pending'): ?>
                                <div class="sc-ticket-badge pending"><?php esc_html_e('Pending', 'sc_events'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="sc-ticket-content">
                                <h4 class="sc-ticket-title">
                                    <a href="<?php echo esc_url(home_url('/event/' . $event->slug)); ?>"><?php echo esc_html($event->title); ?></a>
                                </h4>
                                <div class="sc-ticket-meta">
                                    <span><i class="fa-solid fa-calendar-days"></i> <?php echo esc_html($event->start_date_formatted); ?></span>
                                    <?php if ($event->start_time): ?>
                                    <span><i class="fa-solid fa-clock"></i> <?php echo esc_html($event->start_time_formatted); ?></span>
                                    <?php endif; ?>
                                    <?php if ($event->venue_name): ?>
                                    <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html(wp_trim_words($event->venue_name, 4)); ?></span>
                                    <?php endif; ?>
                                    <?php if ($is_ended): ?>
                                    <span class="sc-event-status-tag ended"><i class="fa-solid fa-flag-checkered"></i> <?php esc_html_e('Ended', 'sc_events'); ?></span>
                                    <?php elseif ($is_today): ?>
                                    <span class="sc-event-status-tag live"><i class="fa-solid fa-circle"></i> <?php esc_html_e('Live Now', 'sc_events'); ?></span>
                                    <?php else: ?>
                                    <span class="sc-event-status-tag upcoming"><i class="fa-solid fa-clock"></i> <?php esc_html_e('Upcoming', 'sc_events'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sc-ticket-info">
                                    <div class="sc-info-item">
                                        <span class="sc-info-label"><?php esc_html_e('Ticket ID', 'sc_events'); ?></span>
                                        <span class="sc-info-value"><?php echo esc_html($attendee->ticket_code); ?></span>
                                    </div>
                                    <div class="sc-info-item">
                                        <span class="sc-info-label"><?php esc_html_e('Type', 'sc_events'); ?></span>
                                        <span class="sc-info-value">
                                            <?php
                                            switch ($attendee->payment_method) {
                                                case 'free':
                                                    esc_html_e('Free', 'sc_events');
                                                    break;
                                                case 'coupon':
                                                    esc_html_e('Coupon', 'sc_events');
                                                    break;
                                                case 'paid':
                                                case 'woocommerce':
                                                    esc_html_e('Paid', 'sc_events');
                                                    break;
                                                default:
                                                    echo esc_html($attendee->payment_method ?: __('Free', 'sc_events'));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($checked_in): ?>
                                <div class="sc-attendance-info">
                                    <div class="sc-attendance-row">
                                        <i class="fa-solid fa-right-to-bracket"></i>
                                        <span><?php esc_html_e('Check-in', 'sc_events'); ?>: <?php echo esc_html(date_i18n('M d, Y \a\t g:i A', strtotime($checkin_time))); ?></span>
                                    </div>
                                    <?php if ($checkout_time): ?>
                                    <div class="sc-attendance-row">
                                        <i class="fa-solid fa-right-from-bracket"></i>
                                        <span><?php esc_html_e('Check-out', 'sc_events'); ?>: <?php echo esc_html(date_i18n('M d, Y \a\t g:i A', strtotime($checkout_time))); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($duration): ?>
                                    <div class="sc-attendance-row">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                        <span><?php esc_html_e('Duration', 'sc_events'); ?>: <?php echo esc_html($duration); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($session_stats && $session_stats['total'] > 0): ?>
                                <div class="sc-session-stats">
                                    <span class="sc-session-stat">
                                        <i class="fa-solid fa-chalkboard-user"></i>
                                        <?php printf(esc_html__('Sessions: %d/%d attended', 'sc_events'), $session_stats['attended'], $session_stats['total']); ?>
                                    </span>
                                    <?php if ($session_stats['cme'] > 0): ?>
                                    <span class="sc-session-stat">
                                        <i class="fa-solid fa-award"></i>
                                        <?php printf(esc_html__('CME: %s hours', 'sc_events'), number_format($session_stats['cme'], 1)); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($session_details)): ?>
                                    <button class="sc-session-toggle" onclick="this.closest('.sc-session-stats').nextElementSibling.classList.toggle('sc-expanded');this.classList.toggle('sc-toggled')">
                                        <i class="fa-solid fa-chevron-down"></i>
                                        <span><?php esc_html_e('View Details', 'sc_events'); ?></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($session_details)): ?>
                                <div class="sc-session-detail-list">
                                    <?php foreach ($session_details as $sd):
                                        $sd_attended = !empty($sd->check_in_time);
                                        $sd_waitlisted = ($sd->registration_status ?? '') === 'waitlisted';
                                    ?>
                                    <div class="sc-session-detail-item <?php echo $sd_waitlisted ? 'sc-sd-waitlisted' : ''; ?>">
                                        <span class="sc-sd-time"><?php echo esc_html(date('g:i A', strtotime($sd->start_time))); ?></span>
                                        <span class="sc-sd-title"><?php echo esc_html($sd->title); ?></span>
                                        <?php if (!empty($sd->hall_name)): ?>
                                        <span class="sc-sd-hall"><?php echo esc_html($sd->hall_name); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($sd->earned_cme_hours) && $sd->earned_cme_hours > 0): ?>
                                        <span class="sc-sd-cme"><?php echo esc_html(number_format($sd->earned_cme_hours, 1)); ?> CME</span>
                                        <?php endif; ?>
                                        <span class="sc-sd-status">
                                            <?php if ($sd_waitlisted): ?>
                                                <i class="fa-solid fa-clock" title="<?php esc_attr_e('Waitlisted', 'sc_events'); ?>"></i>
                                            <?php elseif ($sd_attended): ?>
                                                <i class="fa-solid fa-circle-check" title="<?php esc_attr_e('Attended', 'sc_events'); ?>"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-ticket" title="<?php esc_attr_e('Registered', 'sc_events'); ?>"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php
                                // === Certificates Section (hidden in My Tickets, shown in My Certificates tab instead) ===
                                $has_event_cert = !empty($event->enable_certificates);
                                $session_certs = array();
                                if (!empty($session_details)) {
                                    foreach ($session_details as $sd) {
                                        if (!empty($sd->enable_certificate)) {
                                            $session_certs[] = $sd;
                                        }
                                    }
                                }
                                $show_certs_section = false; // hidden in My Tickets per request
                                ?>
                                <?php if ($show_certs_section): ?>
                                <div class="sc-certificates-section">
                                    <div class="sc-certs-header" onclick="this.parentElement.classList.toggle('sc-certs-open')">
                                        <span class="sc-certs-title">
                                            <i class="fa-solid fa-award"></i>
                                            <?php esc_html_e('Certificates', 'sc_events'); ?>
                                            <?php
                                            $cert_count = ($has_event_cert && $cert_download_url ? 1 : 0);
                                            foreach ($session_certs as $sc_item) {
                                                if (!empty($sc_item->certificate_issued)) $cert_count++;
                                            }
                                            if ($cert_count > 0): ?>
                                            <span class="sc-certs-count"><?php echo $cert_count; ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <i class="fa-solid fa-chevron-down sc-certs-chevron"></i>
                                    </div>
                                    <div class="sc-certs-body">
                                        <?php if ($has_event_cert): ?>
                                        <div class="sc-cert-item sc-cert-event">
                                            <div class="sc-cert-icon">
                                                <i class="fa-solid fa-trophy"></i>
                                            </div>
                                            <div class="sc-cert-info">
                                                <span class="sc-cert-label"><?php esc_html_e('Event Certificate', 'sc_events'); ?></span>
                                                <span class="sc-cert-name"><?php echo esc_html($event->title); ?></span>
                                            </div>
                                            <div class="sc-cert-action">
                                                <?php if ($cert_download_url): ?>
                                                <a href="<?php echo esc_url($cert_download_url); ?>" class="sc-cert-btn sc-cert-download" target="_blank">
                                                    <i class="fa-solid fa-download"></i>
                                                    <?php esc_html_e('Download', 'sc_events'); ?>
                                                </a>
                                                <?php elseif ($cert_eligible && !$certificate): ?>
                                                <button class="sc-cert-btn sc-cert-request sc-request-certificate" data-attendee-id="<?php echo esc_attr($attendee->id); ?>" data-event-id="<?php echo esc_attr($event->id); ?>" data-ticket-code="<?php echo esc_attr($attendee->ticket_code); ?>">
                                                    <i class="fa-solid fa-certificate"></i>
                                                    <?php esc_html_e('Get Certificate', 'sc_events'); ?>
                                                </button>
                                                <?php else: ?>
                                                <span class="sc-cert-status sc-cert-pending">
                                                    <i class="fa-solid fa-clock"></i>
                                                    <?php
                                                    if (!empty($event->certificate_require_checkin) && !$checked_in) {
                                                        esc_html_e('Requires check-in', 'sc_events');
                                                    } elseif (!empty($event->certificate_require_event_ended) && !$is_past) {
                                                        esc_html_e('Available after event ends', 'sc_events');
                                                    } else {
                                                        esc_html_e('Not yet available', 'sc_events');
                                                    }
                                                    ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($session_certs)): ?>
                                        <?php if ($has_event_cert): ?>
                                        <div class="sc-cert-divider"></div>
                                        <?php endif; ?>
                                        <div class="sc-cert-session-label">
                                            <i class="fa-solid fa-chalkboard-user"></i>
                                            <?php printf(esc_html__('Session Certificates (%d)', 'sc_events'), count($session_certs)); ?>
                                        </div>
                                        <?php foreach ($session_certs as $sc_item):
                                            $sc_eligible = !empty($sc_item->certificate_eligible);
                                            $sc_issued = !empty($sc_item->certificate_issued);
                                            $sc_attended = !empty($sc_item->check_in_time);
                                            $sc_min_pct = floatval($sc_item->min_attendance_percentage ?? 80);
                                            $sc_att_pct = floatval($sc_item->attendance_percentage ?? 0);
                                        ?>
                                        <div class="sc-cert-item sc-cert-session">
                                            <div class="sc-cert-icon sc-cert-icon-session">
                                                <?php if ($sc_issued): ?>
                                                <i class="fa-solid fa-circle-check"></i>
                                                <?php elseif ($sc_eligible): ?>
                                                <i class="fa-solid fa-certificate"></i>
                                                <?php else: ?>
                                                <i class="fa-regular fa-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="sc-cert-info">
                                                <span class="sc-cert-name"><?php echo esc_html($sc_item->title); ?></span>
                                                <span class="sc-cert-meta">
                                                    <?php echo esc_html(date('g:i A', strtotime($sc_item->start_time))); ?>
                                                    <?php if (!empty($sc_item->hall_name)): ?>
                                                    &middot; <?php echo esc_html($sc_item->hall_name); ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sc_item->earned_cme_hours) && $sc_item->earned_cme_hours > 0): ?>
                                                    &middot; <?php printf(esc_html__('%s CME', 'sc_events'), number_format($sc_item->earned_cme_hours, 1)); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="sc-cert-action">
                                                <?php if ($sc_issued): ?>
                                                <span class="sc-cert-status sc-cert-issued">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    <?php esc_html_e('Issued', 'sc_events'); ?>
                                                </span>
                                                <?php elseif ($sc_eligible): ?>
                                                <span class="sc-cert-status sc-cert-eligible">
                                                    <i class="fa-solid fa-check"></i>
                                                    <?php esc_html_e('Eligible', 'sc_events'); ?>
                                                </span>
                                                <?php elseif ($sc_attended): ?>
                                                <span class="sc-cert-status sc-cert-progress">
                                                    <?php printf(esc_html__('%d%% / %d%%', 'sc_events'), round($sc_att_pct), round($sc_min_pct)); ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="sc-cert-status sc-cert-pending">
                                                    <?php printf(esc_html__('Min %d%% attendance', 'sc_events'), round($sc_min_pct)); ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($status === 'success'): ?>
                                <div class="sc-ticket-actions">
                                    <a href="<?php echo esc_url(home_url('/ticket-view/?attendee_id=' . $attendee->id . '&ticket_code=' . $attendee->ticket_code)); ?>" class="sc-ticket-btn" target="_blank">
                                        <i class="fa-solid fa-ticket"></i>
                                        <?php esc_html_e('View Ticket', 'sc_events'); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sc-empty-state">
                        <div class="sc-empty-icon">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <h4><?php esc_html_e("You don't have any tickets yet", 'sc_events'); ?></h4>
                        <p><?php esc_html_e('Browse our events and register for one!', 'sc_events'); ?></p>
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-gold">
                            <?php esc_html_e('Browse Events', 'sc_events'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Certificates Tab — dedicated page so users find their certificates fast -->
                <div class="sc-tab-content" id="certificates-tab" data-tab-content="certificates" style="display: none;">
                    <div class="sc-content-header">
                        <h3><i class="fa-solid fa-certificate"></i> <?php esc_html_e('My Certificates', 'sc_events'); ?></h3>
                        <p class="sc-content-subtitle"><?php esc_html_e('All your event certificates in one place', 'sc_events'); ?></p>
                    </div>

                    <?php
                    // Build a flat list of certificates across all the user's tickets.
                    $cert_list = array();
                    if (!empty($attendees) && class_exists('SC_Certificate') && class_exists('SC_Event')) {
                        foreach ($attendees as $att) {
                            $event = SC_Event::get($att->event_id);
                            if (!$event) continue;

                            $cert = SC_Certificate::get_by_attendee_event($att->id, $att->event_id);

                            // Track this attendee's eligibility regardless of whether issued
                            $checked_in    = !empty($att->checked_in);
                            $event_end     = ($event->end_date ?: $event->start_date);
                            $event_is_past = strtotime($event_end) < strtotime(date('Y-m-d'));

                            $cert_list[] = array(
                                'event'       => $event,
                                'attendee'    => $att,
                                'certificate' => is_array($cert) ? $cert : null,
                                'eligible'    => !empty($event->enable_certificates)
                                                  && (empty($event->certificate_require_checkin) || $checked_in)
                                                  && (empty($event->certificate_require_event_ended) || $event_is_past),
                                'checked_in'  => $checked_in,
                                'is_past'     => $event_is_past,
                            );
                        }
                    }
                    ?>

                    <?php if (!empty($cert_list)): ?>
                    <div class="sc-certs-grid">
                        <?php foreach ($cert_list as $row):
                            $event       = $row['event'];
                            $att         = $row['attendee'];
                            $certificate = $row['certificate'];
                            $is_revoked  = $certificate && ($certificate['status'] ?? '') === 'revoked';
                            $is_issued   = $certificate && !$is_revoked;
                            $download_url = '';
                            if ($is_issued) {
                                $token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);
                                $download_url = add_query_arg(array(
                                    'action' => 'sc_download_certificate',
                                    'id'     => $certificate['id'],
                                    'token'  => $token,
                                ), admin_url('admin-ajax.php'));
                            }
                        ?>
                        <div class="sc-cert-card-full">
                            <div class="sc-cert-card-icon">
                                <i class="fa-solid fa-trophy"></i>
                            </div>
                            <div class="sc-cert-card-body">
                                <span class="sc-cert-card-label"><?php esc_html_e('Event Certificate', 'sc_events'); ?></span>
                                <h4 class="sc-cert-card-title"><?php echo esc_html($event->title); ?></h4>
                                <div class="sc-cert-card-meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?php echo esc_html(date_i18n('F j, Y', strtotime($event->start_date))); ?></span>
                                    <?php if (!empty($att->ticket_code)): ?>
                                    <span><i class="fa-solid fa-ticket"></i> <?php echo esc_html($att->ticket_code); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="sc-cert-card-action">
                                <?php if ($is_issued && $download_url): ?>
                                    <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="sc-btn sc-btn-gold">
                                        <i class="fa-solid fa-download"></i>
                                        <?php esc_html_e('Download', 'sc_events'); ?>
                                    </a>
                                <?php elseif ($row['eligible']): ?>
                                    <button class="sc-btn sc-btn-outline sc-request-certificate"
                                            data-attendee-id="<?php echo esc_attr($att->id); ?>"
                                            data-event-id="<?php echo esc_attr($event->id); ?>"
                                            data-ticket-code="<?php echo esc_attr($att->ticket_code); ?>">
                                        <i class="fa-solid fa-certificate"></i>
                                        <?php esc_html_e('Get Certificate', 'sc_events'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="sc-cert-pending-badge">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php
                                        if (empty($event->enable_certificates)) {
                                            esc_html_e('Not available', 'sc_events');
                                        } elseif (!empty($event->certificate_require_checkin) && !$row['checked_in']) {
                                            esc_html_e('Requires check-in', 'sc_events');
                                        } elseif (!empty($event->certificate_require_event_ended) && !$row['is_past']) {
                                            esc_html_e('After event ends', 'sc_events');
                                        } else {
                                            esc_html_e('Not yet available', 'sc_events');
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sc-empty-state">
                        <i class="fa-solid fa-award"></i>
                        <h4><?php esc_html_e('No certificates yet', 'sc_events'); ?></h4>
                        <p><?php esc_html_e('Your certificates will appear here after attending events.', 'sc_events'); ?></p>
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-gold">
                            <?php esc_html_e('Browse Events', 'sc_events'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Favorites Tab -->
                <div class="sc-tab-content" id="favorites-tab" data-tab-content="favorites" style="display: none;">
                    <div class="sc-content-header">
                        <h3><i class="fa-solid fa-heart"></i> <?php esc_html_e('My Favorites', 'sc_events'); ?></h3>
                    </div>

                    <?php if (!empty($favorite_events)): ?>
                    <div class="sc-favorites-list">
                        <?php foreach ($favorite_events as $fav_event):
                            $fav_is_past = strtotime($fav_event->start_date) < strtotime(date('Y-m-d'));
                            $fav_thumb = '';
                            if ($fav_event->featured_image) {
                                $fav_thumb = wp_get_attachment_image_url($fav_event->featured_image, 'medium');
                            }
                            if (!$fav_thumb && $fav_event->banner_image) {
                                $fav_thumb = wp_get_attachment_image_url($fav_event->banner_image, 'medium');
                            }

                            // Get price info
                            $fav_tickets = class_exists('SC_Ticket') ? SC_Ticket::get_by_event($fav_event->id) : array();
                            $fav_min_price = 0;
                            $fav_is_free = true;
                            foreach ($fav_tickets as $ft) {
                                $fp = floatval($ft->price ?? 0);
                                if ($fp > 0) {
                                    $fav_is_free = false;
                                    if ($fav_min_price == 0 || $fp < $fav_min_price) $fav_min_price = $fp;
                                }
                            }
                        ?>
                        <div class="sc-favorite-card <?php echo $fav_is_past ? 'past-event' : ''; ?>" data-event-id="<?php echo esc_attr($fav_event->id); ?>">
                            <div class="sc-favorite-image">
                                <?php if ($fav_thumb): ?>
                                    <img src="<?php echo esc_url($fav_thumb); ?>" alt="<?php echo esc_attr($fav_event->title); ?>">
                                <?php else: ?>
                                    <div class="sc-favorite-placeholder">
                                        <i class="fa-solid fa-calendar-days"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if ($fav_is_past): ?>
                                <div class="sc-ticket-badge past"><?php esc_html_e('Ended', 'sc_events'); ?></div>
                                <?php else: ?>
                                <div class="sc-ticket-badge confirmed"><?php esc_html_e('Upcoming', 'sc_events'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="sc-favorite-content">
                                <h4 class="sc-favorite-title">
                                    <a href="<?php echo esc_url(home_url('/event/' . $fav_event->slug)); ?>"><?php echo esc_html($fav_event->title); ?></a>
                                </h4>
                                <div class="sc-ticket-meta">
                                    <span><i class="fa-solid fa-calendar-days"></i> <?php echo esc_html(date_i18n('M d, Y', strtotime($fav_event->start_date))); ?></span>
                                    <?php if ($fav_event->start_time): ?>
                                    <span><i class="fa-solid fa-clock"></i> <?php echo esc_html(date('g:i A', strtotime($fav_event->start_time))); ?></span>
                                    <?php endif; ?>
                                    <?php if ($fav_event->venue_name): ?>
                                    <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html(wp_trim_words($fav_event->venue_name, 4)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sc-favorite-footer">
                                    <span class="sc-favorite-price">
                                        <?php if ($fav_is_free): ?>
                                            <?php esc_html_e('Free', 'sc_events'); ?>
                                        <?php else: ?>
                                            <?php esc_html_e('From', 'sc_events'); ?> <?php echo esc_html(function_exists('sc_format_price') ? sc_format_price($fav_min_price) : number_format($fav_min_price) . ' EGP'); ?>
                                        <?php endif; ?>
                                    </span>
                                    <div class="sc-favorite-actions">
                                        <a href="<?php echo esc_url(home_url('/event/' . $fav_event->slug)); ?>" class="sc-ticket-btn">
                                            <i class="fa-solid fa-eye"></i>
                                            <?php esc_html_e('View Event', 'sc_events'); ?>
                                        </a>
                                        <button class="sc-ticket-btn sc-remove-favorite" data-event-id="<?php echo esc_attr($fav_event->id); ?>">
                                            <i class="fa-solid fa-heart-crack"></i>
                                            <?php esc_html_e('Remove', 'sc_events'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sc-empty-state">
                        <div class="sc-empty-icon">
                            <i class="fa-solid fa-heart"></i>
                        </div>
                        <h4><?php esc_html_e("You haven't saved any events yet", 'sc_events'); ?></h4>
                        <p><?php esc_html_e('Browse events and click the heart icon to save your favorites!', 'sc_events'); ?></p>
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-gold">
                            <?php esc_html_e('Browse Events', 'sc_events'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Tab -->
                <div class="sc-tab-content" id="profile-tab" data-tab-content="profile" style="display: none;">
                    <div class="sc-content-header">
                        <h3><i class="fa-solid fa-user-pen"></i> <?php esc_html_e('Profile Settings', 'sc_events'); ?></h3>
                    </div>

                    <form id="profile-form" class="sc-profile-form">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="profile-name">
                                        <i class="fa-solid fa-user"></i>
                                        <?php esc_html_e('Full Name', 'sc_events'); ?>
                                    </label>
                                    <input type="text" id="profile-name" name="name" class="sc-auth-input" value="<?php echo esc_attr($current_user->display_name); ?>" required>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="profile-email">
                                        <i class="fa-solid fa-envelope"></i>
                                        <?php esc_html_e('Email Address', 'sc_events'); ?>
                                    </label>
                                    <input type="email" id="profile-email" class="sc-auth-input" value="<?php echo esc_attr($current_user->user_email); ?>" disabled>
                                    <small class="sc-input-hint"><?php esc_html_e('Email cannot be changed', 'sc_events'); ?></small>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="profile-phone">
                                        <i class="fa-solid fa-phone"></i>
                                        <?php esc_html_e('Phone Number', 'sc_events'); ?>
                                    </label>
                                    <?php
                                    // Parse phone to extract country code
                                    $phone_code = '+20';
                                    $phone_number = $user_phone;
                                    $country_codes = array('+971', '+966', '+20');
                                    foreach ($country_codes as $code) {
                                        if (strpos($user_phone, $code) === 0) {
                                            $phone_code = $code;
                                            $phone_number = trim(substr($user_phone, strlen($code)));
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="sc-phone-group">
                                        <select id="profile-phone-code" name="phone_code" class="sc-phone-code">
                                            <option value="+20" <?php selected($phone_code, '+20'); ?>>+20</option>
                                            <option value="+966" <?php selected($phone_code, '+966'); ?>>+966</option>
                                            <option value="+971" <?php selected($phone_code, '+971'); ?>>+971</option>
                                        </select>
                                        <input type="tel" id="profile-phone" name="phone" class="sc-auth-input" value="<?php echo esc_attr($phone_number); ?>" placeholder="XXX XXX XXXX">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sc-section-divider">
                            <span><?php esc_html_e('Change Password', 'sc_events'); ?></span>
                        </div>
                        <p style="color: var(--sc-text-muted); font-size: var(--sc-text-sm); margin-bottom: var(--sc-space-5);"><?php esc_html_e('Leave blank to keep your current password', 'sc_events'); ?></p>

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="current_password">
                                        <i class="fa-solid fa-lock"></i>
                                        <?php esc_html_e('Current Password', 'sc_events'); ?>
                                    </label>
                                    <input type="password" id="current_password" name="current_password" class="sc-auth-input">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="new_password">
                                        <i class="fa-solid fa-key"></i>
                                        <?php esc_html_e('New Password', 'sc_events'); ?>
                                    </label>
                                    <input type="password" id="new_password" name="new_password" class="sc-auth-input" minlength="6">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="confirm_password">
                                        <i class="fa-solid fa-check-double"></i>
                                        <?php esc_html_e('Confirm Password', 'sc_events'); ?>
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="sc-auth-input">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: var(--sc-space-6);">
                            <button type="submit" class="sc-auth-btn" style="max-width: 240px;">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <?php esc_html_e('Save Changes', 'sc_events'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- QR Code Modal -->
<div class="modal fade" id="qr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php esc_html_e('Your Ticket QR Code', 'sc_events'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qr-code-container" class="mb-3"></div>
                <p class="mb-1"><strong id="qr-ticket-id"></strong></p>
                <small style="color: var(--sc-text-muted);"><?php esc_html_e('Show this QR code at the event entrance', 'sc_events'); ?></small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="sc-ticket-btn" id="download-qr">
                    <i class="fa-solid fa-download"></i>
                    <?php esc_html_e('Download', 'sc_events'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Load QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
jQuery(document).ready(function($) {
    // Tab Navigation
    $('.sc-account-nav .sc-nav-item[data-tab]').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        // Update active nav
        $('.sc-account-nav .sc-nav-item').removeClass('active');
        $(this).addClass('active');

        // Show tab content
        $('.sc-tab-content').hide();
        $('[data-tab-content="' + tab + '"]').show();
    });

    // Download QR Code directly
    $('.btn-download-qr').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        var qrData = $(this).data('qr');
        var eventTitle = $(this).data('event-title');

        if (typeof QRCode !== 'undefined' && typeof QRCode.toDataURL === 'function') {
            QRCode.toDataURL(qrData || ticketId, {
                width: 400,
                height: 400,
                margin: 2,
                errorCorrectionLevel: 'H',
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function(err, url) {
                if (!err && url) {
                    var link = document.createElement('a');
                    var fileName = eventTitle ? eventTitle.replace(/[^a-z0-9]/gi, '-') : ticketId;
                    link.download = 'QR-' + fileName + '.png';
                    link.href = url;
                    link.click();
                }
            });
        }
    });

    // Request Certificate
    $('.sc-request-certificate').on('click', function() {
        var $btn = $(this);
        var ticketCode = $btn.data('ticket-code');
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Requesting...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_request_certificate_public',
                nonce: scPublic.nonce,
                ticket_code: ticketCode
            },
            success: function(response) {
                if (response.success && response.data.download_url) {
                    $btn.replaceWith(
                        '<a href="' + response.data.download_url + '" class="sc-cert-btn sc-cert-download" target="_blank">' +
                        '<i class="fa-solid fa-download"></i> <?php echo esc_js(__('Download', 'sc_events')); ?></a>'
                    );
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo esc_js(__('Certificate Ready!', 'sc_events')); ?>',
                        text: '<?php echo esc_js(__('Your certificate has been issued.', 'sc_events')); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    Swal.fire({
                        icon: 'info',
                        title: '<?php echo esc_js(__('Not Available', 'sc_events')); ?>',
                        text: response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Certificate is not available yet.', 'sc_events')); ?>'
                    });
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Logout
    $('.sc-nav-logout').on('click', function(e) {
        e.preventDefault();
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--sc-primary').trim() || '#D4AF37';
        Swal.fire({
            title: '<?php echo esc_js(__('Logout?', 'sc_events')); ?>',
            text: '<?php echo esc_js(__('Are you sure you want to logout?', 'sc_events')); ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: primaryColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?php echo esc_js(__('Yes, logout', 'sc_events')); ?>',
            cancelButtonText: '<?php echo esc_js(__('Cancel', 'sc_events')); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo esc_js(wp_logout_url(home_url('/'))); ?>';
            }
        });
    });

    // Profile Form
    $('#profile-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.html();
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--sc-primary').trim() || '#D4AF37';

        // Validate passwords
        var newPass = $form.find('[name="new_password"]').val();
        var confirmPass = $form.find('[name="confirm_password"]').val();

        if (newPass && newPass !== confirmPass) {
            Swal.fire({
                icon: 'error',
                title: '<?php echo esc_js(__('Password Mismatch', 'sc_events')); ?>',
                text: '<?php echo esc_js(__('New passwords do not match', 'sc_events')); ?>',
                confirmButtonColor: primaryColor
            });
            return;
        }

        $button.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Saving...', 'sc_events')); ?>');

        // Combine phone code and number
        var phoneCode = $form.find('[name="phone_code"]').val() || '+20';
        var phoneNumber = $form.find('[name="phone"]').val().trim();
        var fullPhone = phoneNumber ? phoneCode + phoneNumber : '';

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_update_profile',
                nonce: scPublic.nonce,
                name: $form.find('[name="name"]').val(),
                phone: fullPhone,
                current_password: $form.find('[name="current_password"]').val(),
                new_password: newPass
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo esc_js(__('Saved!', 'sc_events')); ?>',
                        text: response.data.message || '<?php echo esc_js(__('Profile updated successfully', 'sc_events')); ?>',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Clear password fields
                    $form.find('[name="current_password"], [name="new_password"], [name="confirm_password"]').val('');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo esc_js(__('Error', 'sc_events')); ?>',
                        text: response.data.message || '<?php echo esc_js(__('Could not update profile', 'sc_events')); ?>',
                        confirmButtonColor: primaryColor
                    });
                }
                $button.prop('disabled', false).html(originalText);
            },
            error: function(xhr, status) {
                var errorMsg = '<?php echo esc_js(__('Failed to update profile. ', 'sc_events')); ?>';
                if (status === 'timeout') {
                    errorMsg += '<?php echo esc_js(__('The request timed out. Please try again.', 'sc_events')); ?>';
                } else if (xhr.status === 0) {
                    errorMsg += '<?php echo esc_js(__('Could not connect to server. Please check your internet connection.', 'sc_events')); ?>';
                } else if (xhr.status === 403) {
                    errorMsg += '<?php echo esc_js(__('Your session may have expired. Please refresh and try again.', 'sc_events')); ?>';
                } else if (xhr.status === 500) {
                    errorMsg += '<?php echo esc_js(__('Server error occurred. Please try again later.', 'sc_events')); ?>';
                } else {
                    errorMsg += '<?php echo esc_js(__('Please try again or contact support.', 'sc_events')); ?>';
                }
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo esc_js(__('Error', 'sc_events')); ?>',
                    text: errorMsg,
                    confirmButtonColor: primaryColor
                });
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
