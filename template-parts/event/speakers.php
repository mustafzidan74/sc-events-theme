<?php
/**
 * Faculty.
 *
 * Portraits at a consistent 3:4, initials where there is no photo, and each
 * card links to the speaker's own page rather than opening a modal — so the
 * link can be shared and the back button behaves.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event_speakers = $args['event_speakers'] ?? [];

if (empty($event_speakers)) return;

// Enough to fill the grid; the rest are one click away on the speakers page.
$shown  = array_slice($event_speakers, 0, 12);
$hidden = count($event_speakers) - count($shown);
?>

<section class="w-ev__section" id="speakers">
    <div class="w-ev__bar">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.speakers', 'Speakers')); ?></h2>
        <?php if ($hidden > 0): ?>
        <a class="w-btn w-btn--outline w-btn--sm" href="<?php echo esc_url(home_url('/speakers/')); ?>">
            <?php printf(
                esc_html(sc_t('frontend.all_n', 'All %s')),
                esc_html(number_format_i18n(count($event_speakers)))
            ); ?>
        </a>
        <?php endif; ?>
    </div>

    <div class="w-ev__faculty">
        <?php foreach ($shown as $speaker):
            $photo = sc_img(!empty($speaker->photo) ? $speaker->photo : ($speaker->photo_url ?? ''), 'medium_large', '(max-width: 767px) 45vw, 180px', array('alt' => $speaker->name), 800);

            $initials = sc_initials($speaker->name);

            $role = trim(implode(' · ', array_filter([
                $speaker->job_title ?? ($speaker->title ?? ''),
                $speaker->company ?? '',
            ])));

            $href = function_exists('sc_speaker_permalink')
                ? sc_speaker_permalink($speaker)
                : home_url('/speakers/');
        ?>
        <a class="w-face" href="<?php echo esc_url($href); ?>">
            <span class="w-face__pic">
                <?php if ($photo): ?>
                    <?php echo $photo; // Built by wp_get_attachment_image(). ?>
                <?php else: ?>
                    <span class="w-face__initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                <?php endif; ?>
            </span>
            <span class="w-face__name"><?php echo esc_html($speaker->name); ?></span>
            <?php if ($role): ?>
                <span class="w-face__role"><?php echo esc_html($role); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
