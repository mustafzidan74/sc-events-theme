<?php
/**
 * Event Schedule - Dark & Premium Timeline
 * Prefers sc_sessions module data when available, falls back to sc_schedules.
 *
 * @package sc_events
 * @version 5.2.0
 */

if (!defined('ABSPATH')) exit;

$event = $args['event'] ?? null;
$event_id = $args['event_id'] ?? 0;
$schedules_file = $args['schedules_file'] ?? 0;

if (!$event) return;

global $wpdb;
$sc_prefix = $wpdb->prefix . 'sc_';

// --- Try Sessions Module first ---
$use_sessions = false;
$sessions_data = array();
$session_speaker_map = array();

if (class_exists('SC_Session_Attendance')) {
    $sessions_data = SC_Session_Attendance::get_event_sessions($event_id);
    if (!empty($sessions_data)) {
        $use_sessions = true;
        $session_ids = array_map(function($s) { return $s->id; }, $sessions_data);
        $session_speaker_map = SC_Session_Attendance::get_speakers_for_sessions($session_ids);
    }
}

// --- Fallback to sc_schedules ---
$schedules = array();
if (!$use_sessions) {
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, h.name as hall_name,
                sp.name as speaker_db_name, sp.title as speaker_title, sp.photo as speaker_photo_id
         FROM {$sc_prefix}schedules s
         LEFT JOIN {$sc_prefix}halls h ON s.hall_id = h.id
         LEFT JOIN {$sc_prefix}speakers sp ON s.speaker_id = sp.id
         WHERE s.event_id = %d AND s.is_active = 1
         ORDER BY s.schedule_date ASC, s.start_time ASC, s.sort_order ASC",
        $event_id
    ));
}

// Get schedule PDF URL
$schedules_file_url = '';
$schedules_file_name = '';
if ($schedules_file) {
    $schedules_file_url = wp_get_attachment_url($schedules_file);
    $schedules_file_name = basename(get_attached_file($schedules_file));
}

// If nothing to show, skip
if (empty($sessions_data) && empty($schedules) && !$schedules_file_url) return;

// Group data by date
$grouped = array();
if ($use_sessions) {
    foreach ($sessions_data as $item) {
        $date = $item->session_date;
        if (!isset($grouped[$date])) $grouped[$date] = array();
        $grouped[$date][] = $item;
    }
} elseif (!empty($schedules)) {
    foreach ($schedules as $item) {
        $date = $item->schedule_date;
        if (!isset($grouped[$date])) $grouped[$date] = array();
        $grouped[$date][] = $item;
    }
}
$dates = array_keys($grouped);

// Session type labels and colors for badges
$session_type_icons = array(
    'lecture'     => 'fa-solid fa-chalkboard',
    'workshop'    => 'fa-solid fa-flask',
    'panel'       => 'fa-solid fa-users',
    'keynote'     => 'fa-solid fa-star',
    'break'       => 'fa-solid fa-mug-hot',
    'networking'  => 'fa-solid fa-handshake',
    'exhibition'  => 'fa-solid fa-store',
    'poster'      => 'fa-solid fa-image',
    'symposium'   => 'fa-solid fa-building-columns',
    'hands_on'    => 'fa-solid fa-hand',
    'other'       => 'fa-solid fa-circle',
);
?>

<section class="sc-section sc-section-alt" id="schedule">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.event_schedule', 'Event Schedule')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.event_schedule_desc', 'Plan your day with our detailed event timeline')); ?></p>
        </div>

        <?php if ($schedules_file_url): ?>
        <!-- Schedule PDF Download -->
        <div class="sc-schedule-file glass-card-static" data-aos="fade-up" style="display: flex; align-items: center; gap: 16px; padding: 20px 24px; margin-bottom: var(--sc-space-8);">
            <div style="width: 48px; height: 48px; border-radius: var(--sc-radius-lg); background: var(--sc-primary-alpha-10); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa-solid fa-file-pdf" style="font-size: 22px; color: var(--sc-primary);"></i>
            </div>
            <div style="flex: 1; min-width: 0;">
                <strong style="color: var(--sc-text-primary); display: block; font-size: 15px;"><?php echo esc_html(sc_t('frontend.event_schedules_file', 'Event Schedules File')); ?></strong>
                <span style="color: var(--sc-text-muted); font-size: 13px;"><?php echo esc_html($schedules_file_name); ?></span>
            </div>
            <a href="<?php echo esc_url($schedules_file_url); ?>" target="_blank" class="sc-btn sc-btn-outline" style="flex-shrink: 0; padding: 8px 20px; font-size: 14px;">
                <i class="fa-solid fa-download"></i> <?php echo esc_html(sc_t('frontend.download_pdf', 'Download PDF')); ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($grouped)): ?>

        <?php if (count($dates) > 1): ?>
        <div class="sc-pills" style="justify-content: center; margin-bottom: var(--sc-space-8);" data-aos="fade-up">
            <?php foreach ($dates as $i => $date): ?>
            <button class="sc-pill <?php echo $i === 0 ? 'active' : ''; ?>" data-tab="day-<?php echo $i; ?>">
                <?php printf(sc_t('frontend.day_d', 'Day %d'), $i + 1); ?>
                <small style="display: block; font-size: 10px; opacity: 0.7;"><?php echo date_i18n('M d', strtotime($date)); ?></small>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($dates as $i => $date):
            $day_items_count = count($grouped[$date]);
            $needs_load_more = $day_items_count > 5;
        ?>
        <div class="sc-schedule-day <?php echo $i > 0 ? 'd-none' : ''; ?>" data-day="day-<?php echo $i; ?>">
            <?php if (count($dates) <= 1): ?>
            <p class="text-center sc-text-muted" style="margin-bottom: var(--sc-space-6);">
                <i class="fa-regular fa-calendar"></i> <?php echo date_i18n('l, F d, Y', strtotime($date)); ?>
            </p>
            <?php endif; ?>

            <div class="sc-timeline <?php echo $needs_load_more ? 'sc-timeline-collapsed' : ''; ?>">
            <?php if ($use_sessions): ?>
                <?php /* ========= SESSIONS MODULE VIEW ========= */ ?>
                <?php foreach ($grouped[$date] as $idx => $session):
                    $speakers = $session_speaker_map[$session->id] ?? array();
                    $is_break = in_array($session->session_type, array('break', 'networking'));
                    $is_full = $session->capacity > 0 && $session->registered_count >= $session->capacity;
                    $type_icon = $session_type_icons[$session->session_type] ?? 'fa-solid fa-circle';
                ?>
                <div class="sc-timeline-item" data-aos="fade-up" data-aos-delay="<?php echo min($idx * 60, 300); ?>">
                    <div class="sc-timeline-dot <?php echo $is_break ? 'sc-timeline-dot-break' : ''; ?>"></div>
                    <div class="sc-timeline-card glass-card<?php echo $is_break ? '-subtle' : ''; ?>">
                        <!-- Time -->
                        <div class="sc-timeline-time">
                            <?php echo esc_html(date('g:i A', strtotime($session->start_time))); ?>
                            <?php if ($session->end_time): ?>
                            - <?php echo esc_html(date('g:i A', strtotime($session->end_time))); ?>
                            <?php endif; ?>
                            <?php if ($session->duration_minutes && !$is_break): ?>
                            <span class="sc-session-duration">(<?php echo esc_html($session->duration_minutes); ?> min)</span>
                            <?php endif; ?>
                        </div>

                        <!-- Title + Type -->
                        <div class="sc-session-title-row">
                            <h5 class="sc-timeline-title"><?php echo esc_html($session->title); ?></h5>
                            <span class="sc-session-type-badge sc-session-type-<?php echo esc_attr($session->session_type); ?>">
                                <i class="<?php echo esc_attr($type_icon); ?>"></i>
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $session->session_type))); ?>
                            </span>
                        </div>

                        <?php if (!empty($session->track)): ?>
                        <div class="sc-session-track">
                            <i class="fa-solid fa-layer-group"></i>
                            <?php echo esc_html($session->track); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Speakers -->
                        <div class="sc-timeline-meta">
                            <?php if (!empty($speakers)): ?>
                                <?php foreach ($speakers as $sp): ?>
                                <span class="sc-timeline-speaker">
                                    <?php if ($sp->photo_url): ?>
                                    <img src="<?php echo esc_url($sp->photo_url); ?>" alt="" class="sc-timeline-avatar">
                                    <?php endif; ?>
                                    <?php echo esc_html($sp->name); ?>
                                    <?php if ($sp->role && $sp->role !== 'speaker'): ?>
                                    <small class="sc-speaker-role">(<?php echo esc_html(ucfirst($sp->role)); ?>)</small>
                                    <?php endif; ?>
                                </span>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($session->hall_name)): ?>
                            <span class="sc-badge-gold"><i class="fa-solid fa-location-dot"></i> <?php echo esc_html($session->hall_name); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($session->description) && !$is_break): ?>
                        <p class="sc-timeline-desc"><?php echo esc_html(wp_trim_words($session->description, 30)); ?></p>
                        <?php endif; ?>

                        <!-- Indicators -->
                        <?php if (!$is_break): ?>
                        <?php
                        $has_indicators = ($session->cme_hours > 0) || ($session->capacity > 0) || !empty($session->enable_certificate);
                        ?>
                        <?php if ($has_indicators): ?>
                        <div class="sc-session-indicators">
                            <?php if ($session->cme_hours > 0): ?>
                            <span class="sc-session-indicator sc-indicator-cme">
                                <i class="fa-solid fa-award"></i>
                                <?php printf(sc_t('frontend.cme_hrs', '%s CME hrs'), number_format($session->cme_hours, 1)); ?>
                            </span>
                            <?php endif; ?>

                            <?php if ($session->capacity > 0): ?>
                            <span class="sc-session-indicator <?php echo $is_full ? 'sc-indicator-full' : ''; ?>">
                                <i class="fa-solid fa-users"></i>
                                <?php if ($is_full): ?>
                                    <?php echo esc_html(sc_t('frontend.full', 'Full')); ?>
                                <?php else: ?>
                                    <?php printf('%d/%d', $session->registered_count, $session->capacity); ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($session->enable_certificate)): ?>
                            <span class="sc-session-indicator sc-indicator-cert">
                                <i class="fa-solid fa-certificate"></i>
                                <?php echo esc_html(sc_t('frontend.certificate', 'Certificate')); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <?php /* ========= LEGACY SCHEDULES VIEW ========= */ ?>
                <?php foreach ($grouped[$date] as $idx => $item):
                    $speaker_name = $item->speaker_db_name ?: ($item->speaker_name ?? '');
                    $speaker_photo = '';
                    if (!empty($item->speaker_photo_id)) {
                        $speaker_photo = wp_get_attachment_image_url($item->speaker_photo_id, 'thumbnail');
                    }
                ?>
                <div class="sc-timeline-item" data-aos="fade-up" data-aos-delay="<?php echo $idx * 60; ?>">
                    <div class="sc-timeline-dot"></div>
                    <div class="sc-timeline-card glass-card">
                        <div class="sc-timeline-time">
                            <?php echo esc_html(date('g:i A', strtotime($item->start_time))); ?>
                            <?php if ($item->end_time): ?>
                            - <?php echo esc_html(date('g:i A', strtotime($item->end_time))); ?>
                            <?php endif; ?>
                        </div>
                        <h5 class="sc-timeline-title"><?php echo esc_html($item->title); ?></h5>

                        <div class="sc-timeline-meta">
                            <?php if ($speaker_name): ?>
                            <span class="sc-timeline-speaker">
                                <?php if ($speaker_photo): ?>
                                <img src="<?php echo esc_url($speaker_photo); ?>" alt="" class="sc-timeline-avatar">
                                <?php endif; ?>
                                <?php echo esc_html($speaker_name); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($item->hall_name)): ?>
                            <span class="sc-badge-gold"><i class="fa-solid fa-location-dot"></i> <?php echo esc_html($item->hall_name); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item->registration_start) || !empty($item->break_start)): ?>
                        <div class="sc-timeline-meta" style="margin-top: 8px;">
                            <?php if (!empty($item->registration_start)): ?>
                            <span class="sc-badge-gold" style="background: rgba(99,102,241,0.15); color: #818cf8;">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <?php echo esc_html(sc_t('registration', 'Registration')); ?>:
                                <?php echo esc_html(date('g:i A', strtotime($item->registration_start))); ?>
                                <?php if (!empty($item->registration_end)): ?>
                                – <?php echo esc_html(date('g:i A', strtotime($item->registration_end))); ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($item->break_start)): ?>
                            <span class="sc-badge-gold" style="background: rgba(251,191,36,0.15); color: #fbbf24;">
                                <i class="fa-solid fa-mug-hot"></i>
                                <?php echo esc_html(sc_t('break_expo_visit', 'Break & Expo')); ?>:
                                <?php echo esc_html(date('g:i A', strtotime($item->break_start))); ?>
                                <?php if (!empty($item->break_end)): ?>
                                – <?php echo esc_html(date('g:i A', strtotime($item->break_end))); ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($item->description)): ?>
                        <p class="sc-timeline-desc"><?php echo esc_html(wp_trim_words($item->description, 30)); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <?php if ($needs_load_more): ?>
            <div class="text-center" style="margin-top: var(--sc-space-6);">
                <button type="button" class="sc-btn sc-btn-outline sc-schedule-load-more" data-day="day-<?php echo $i; ?>">
                    <i class="fa-solid fa-chevron-down"></i>
                    <span class="sc-load-more-text"><?php echo esc_html(sc_t('frontend.load_more', 'Load More')); ?></span>
                    <span class="sc-load-less-text" style="display: none;"><?php echo esc_html(sc_t('frontend.show_less', 'Show Less')); ?></span>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</section>

<style>
.sc-timeline-collapsed > .sc-timeline-item:nth-child(n+6) { display: none; }
.sc-timeline-collapsed.sc-timeline-expanded > .sc-timeline-item:nth-child(n+6) { display: block; }
.sc-schedule-load-more.is-expanded .sc-load-more-text { display: none; }
.sc-schedule-load-more.is-expanded .sc-load-less-text { display: inline !important; }
.sc-schedule-load-more.is-expanded i { transform: rotate(180deg); }
.sc-schedule-load-more i { transition: transform 0.25s ease; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sc-schedule-load-more').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var dayKey = this.getAttribute('data-day');
            var dayContainer = document.querySelector('.sc-schedule-day[data-day="' + dayKey + '"]');
            if (!dayContainer) return;
            var timeline = dayContainer.querySelector('.sc-timeline');
            if (!timeline) return;
            timeline.classList.toggle('sc-timeline-expanded');
            this.classList.toggle('is-expanded');
        });
    });
});
</script>
