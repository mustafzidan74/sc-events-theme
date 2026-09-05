<?php
/**
 * Event FAQ - Dark Accordion
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$event_faq = $args['event_faq'] ?? array();

if (empty($event_faq) || !is_array($event_faq)) return;
?>

<section class="sc-section sc-section-alt" id="faq">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.faq', 'Frequently Asked Questions')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.faq_desc', 'Find answers to common questions about this event')); ?></p>
        </div>

        <div class="row g-4">
            <?php
            $half = ceil(count($event_faq) / 2);
            $left = array_slice($event_faq, 0, $half);
            $right = array_slice($event_faq, $half);
            $columns = array($left, $right);

            foreach ($columns as $col_idx => $faq_items):
            ?>
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?php echo $col_idx * 100; ?>">
                <div class="sc-accordion" id="faq-accordion-<?php echo $col_idx; ?>">
                    <?php foreach ($faq_items as $idx => $faq):
                        $faq_id = 'faq-' . $col_idx . '-' . $idx;
                        $is_first = ($col_idx === 0 && $idx === 0);
                    ?>
                    <div class="sc-accordion-item glass-card-subtle">
                        <button class="sc-accordion-trigger <?php echo $is_first ? 'active' : ''; ?>" data-target="#<?php echo $faq_id; ?>">
                            <span><?php echo esc_html($faq['question'] ?? ''); ?></span>
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <div class="sc-accordion-body" id="<?php echo $faq_id; ?>" <?php echo $is_first ? 'style="display: block;"' : ''; ?>>
                            <div class="sc-accordion-content">
                                <?php echo wp_kses_post($faq['answer'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
