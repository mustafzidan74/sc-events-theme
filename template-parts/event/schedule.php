<?php
/**
 * The programme.
 *
 * One day at a time, and within a day one row per starting time — because
 * halls run in parallel and a flat list hides the choice. The parallel track
 * scrolls sideways rather than stacking under the one before it.
 *
 * Data still comes from the sessions module when that is installed, and from
 * sc_schedules otherwise.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event          = $args['event'] ?? null;
$event_id       = $args['event_id'] ?? 0;
$schedules_file = $args['schedules_file'] ?? 0;

if (!$event) return;

global $wpdb;
$sc_prefix = $wpdb->prefix . 'sc_';

$use_sessions  = false;
$sessions_data = [];

if (class_exists('SC_Session_Attendance')) {
    $sessions_data = SC_Session_Attendance::get_event_sessions($event_id);
    $use_sessions  = !empty($sessions_data);
}

$schedules = [];
if (!$use_sessions) {
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, h.name AS hall_name,
                sp.name AS speaker_db_name, sp.title AS speaker_title
         FROM {$sc_prefix}schedules s
         LEFT JOIN {$sc_prefix}halls h ON s.hall_id = h.id
         LEFT JOIN {$sc_prefix}speakers sp ON s.speaker_id = sp.id
         WHERE s.event_id = %d AND s.is_active = 1
         ORDER BY s.schedule_date ASC, s.start_time ASC, s.sort_order ASC",
        $event_id
    ));
}

$file_url = $schedules_file ? wp_get_attachment_url($schedules_file) : '';

if (!$sessions_data && !$schedules && !$file_url) return;

// Group by day, then by starting time inside the day.
$rows = $use_sessions ? $sessions_data : $schedules;
$days = [];
foreach ($rows as $row) {
    $date = $use_sessions ? ($row->session_date ?? '') : ($row->schedule_date ?? '');
    $time = substr((string) ($row->start_time ?? ''), 0, 5);
    if (!$date) { continue; }
    $days[$date][$time][] = $row;
}
ksort($days);
foreach ($days as &$slots) { ksort($slots); }
unset($slots);

$day_keys = array_keys($days);
?>

<section class="w-ev__section" id="programme">
    <div class="w-ev__bar">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.programme', 'Programme')); ?></h2>

        <?php if (count($day_keys) > 1): ?>
        <div class="w-days" data-day-tabs role="group" aria-label="<?php echo esc_attr(sc_t('frontend.programme', 'Programme')); ?>">
            <?php foreach ($day_keys as $i => $date): ?>
            <button type="button" data-day="<?php echo esc_attr($date); ?>"
                    aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                <?php printf(esc_html(sc_t('frontend.day_n', 'Day %d')), $i + 1); ?>
                <span class="w-ses__hall" style="margin-inline-start:6px"><?php echo esc_html(date_i18n('j M', strtotime($date))); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($file_url): ?>
    <a class="w-btn w-btn--outline w-btn--sm" href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener"
       style="align-self:flex-start">
        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
        <?php echo esc_html(sc_t('frontend.download_schedule', 'Download the full programme')); ?>
    </a>
    <?php endif; ?>

    <?php foreach ($day_keys as $i => $date): ?>
    <div data-day-panel="<?php echo esc_attr($date); ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>
         style="display:flex;flex-direction:column;gap:var(--w-space-5)">
        <?php foreach ($days[$date] as $time => $items): ?>
        <div class="w-slot">
            <span class="w-slot__time"><?php echo esc_html($time); ?></span>
            <div class="w-slot__list">
                <?php foreach ($items as $item):
                    $type  = strtolower((string) ($item->type ?? 'session'));
                    $break = $type === 'break';
                    $hall  = $item->hall_name ?? '';
                    $by    = $item->speaker_db_name ?: ($item->speaker_name ?? '');
                    $end   = substr((string) ($item->end_time ?? ''), 0, 5);
                ?>
                <div class="w-ses<?php echo $break ? ' w-ses--break' : ''; ?>">
                    <div class="w-ses__top">
                        <span class="w-ses__hall">
                            <?php echo esc_html($hall ?: ($end ? $time . '–' . $end : '')); ?>
                        </span>
                        <?php if (!$break && $type && $type !== 'session'): ?>
                            <span class="w-ses__tag"><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></span>
                        <?php endif; ?>
                    </div>

                    <span class="w-ses__title"><?php echo esc_html($item->title); ?></span>

                    <?php if ($by || $end): ?>
                    <span class="w-ses__by">
                        <?php echo esc_html($by ?: ($end ? sprintf(sc_t('frontend.until_time', 'until %s'), $end) : '')); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</section>
