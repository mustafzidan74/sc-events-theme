<?php
/**
 * Good to know.
 *
 * Native <details> rather than a scripted accordion: it opens without
 * JavaScript, it is findable with the browser's own in-page search, and the
 * first answer starts open so the section never reads as an empty list.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event_faq = $args['event_faq'] ?? [];

if (empty($event_faq) || !is_array($event_faq)) return;
?>

<section class="w-ev__section" id="faq">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.good_to_know', 'Good to know')); ?></h2>

    <div class="w-faq">
        <?php foreach ($event_faq as $i => $faq):
            $q = $faq['question'] ?? '';
            $a = $faq['answer'] ?? '';
            if (!$q) { continue; }
        ?>
        <details<?php echo $i === 0 ? ' open' : ''; ?>>
            <summary><?php echo esc_html($q); ?></summary>
            <div class="w-faq__a"><?php echo wp_kses_post(wpautop($a)); ?></div>
        </details>
        <?php endforeach; ?>
    </div>
</section>
