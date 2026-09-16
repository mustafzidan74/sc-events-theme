<?php
/**
 * The free-form sections an organiser adds to an event.
 *
 * A section can hold sixty pictures — the 2025 congress has several — so the
 * page shows the first row or two and offers the rest behind one button. On a
 * phone the same pictures become a rail you swipe, because a wall of sixty
 * made that page 46,000 pixels tall.
 *
 * Every stored type — about, card, image_grid, image_slider — carries the same
 * fields, so one renderer covers all four: a heading, some prose, an optional
 * call to action, then whichever of images or cards was filled in. That is a
 * lot less code than four near-identical branches, and a new type inherits
 * sensible output instead of falling through to nothing.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$gallery_images = $args['gallery_images'] ?? [];
$other_sections = $args['other_sections'] ?? [];

if (empty($gallery_images) && empty($other_sections)) return;

$sections = $other_sections;
if ($gallery_images) {
    array_unshift($sections, ['type' => 'image_grid', 'images' => $gallery_images]);
}
?>

<?php foreach ($sections as $section):
    $heading = trim((string) ($section['heading'] ?? ''));
    $content = trim((string) ($section['content'] ?? ''));
    $images  = array_filter((array) ($section['images'] ?? []));
    $cards   = array_filter((array) ($section['cards'] ?? []));
    $cta_url = trim((string) ($section['button_url'] ?? ''));
    $cta_txt = trim((string) ($section['button_text'] ?? ''));
    $hero    = $section['main_image'] ?? '';

    if (!$heading && !$content && !$images && !$cards && !$hero) { continue; }
?>
<section class="w-ev__section">
    <?php if ($heading): ?>
        <h2 class="w-ev__h2"><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>

    <?php
    // Words and a single picture belong side by side; a long text folds up.
    $single = $hero ? sc_img($hero, 'large', '(max-width: 767px) calc(100vw - 40px), 560px', array('alt' => $heading)) : '';
    if (!$single && count($images) === 1) {
        $single = sc_img(reset($images), 'large', '(max-width: 767px) calc(100vw - 40px), 560px', array('alt' => $heading));
        if ($single) {
            $images = array();
        }
    }
    $words = $content !== '' ? mb_strlen(wp_strip_all_tags($content)) : 0;
    $long = $words > 900;
    // One line of text next to a tall poster leaves a column of nothing; only a
    // real paragraph earns the side-by-side layout.
    $split = $single && $words > 240;
    ?>
    <?php if ($content || $single): ?>
    <div class="w-evsec<?php echo $split ? ' w-evsec--split' : ''; ?>">
        <?php if ($content): ?>
        <div class="w-evsec__text">
            <div class="w-prose<?php echo $long ? ' w-prose--clamped' : ''; ?>"<?php echo $long ? ' data-clamp' : ''; ?>>
                <?php echo wp_kses_post(wpautop($content)); ?>
            </div>
            <?php if ($long): ?>
            <button class="w-btn w-btn--outline w-btn--sm" type="button" data-clamp-toggle
                    data-more="<?php echo esc_attr(sc_t('frontend.read_more', 'Read more')); ?>"
                    data-less="<?php echo esc_attr(sc_t('frontend.read_less', 'Read less')); ?>"
                    style="align-self:flex-start">
                <?php echo esc_html(sc_t('frontend.read_more', 'Read more')); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($single): ?>
        <div class="w-evsec__media">
            <?php echo $single; // Built by wp_get_attachment_image(). ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($images):
        $shots = array();
        foreach ($images as $img) {
            // A phone shows one picture at 78% of the screen, a desk a 260-pixel tile;
            // one or two pictures on their own run wider.
            $tag = sc_img($img, 'medium_large', count($images) <= 2
                ? '(max-width: 767px) 78vw, 600px'
                : '(max-width: 767px) 78vw, 260px', array('alt' => $heading), 1024);
            if ($tag) {
                $shots[] = $tag;
            }
        }
        $shown = 8;
        $rest = max(0, count($shots) - $shown);
    ?>
    <?php // One or two pictures are the point of their section, not a thumbnail in a grid. ?>
    <div class="w-shots<?php echo $rest ? ' w-shots--more' : ''; ?><?php echo count($shots) <= 2 ? ' w-shots--few' : ''; ?>" data-shots>
        <?php foreach ($shots as $i => $tag): ?>
        <div class="w-shots__item<?php echo $i >= $shown ? ' is-extra' : ''; ?>">
            <?php echo $tag; // Built by wp_get_attachment_image(). ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($rest): ?>
    <button type="button" class="w-btn w-btn--outline w-btn--sm w-shots__more" data-shots-more
            data-less="<?php echo esc_attr(sc_t('frontend.show_less', 'Show less')); ?>" style="align-self:flex-start">
        <?php printf(esc_html(sc_t('frontend.show_all_photos', 'Show all %s photos')), esc_html(number_format_i18n(count($shots)))); ?>
    </button>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($cards):
        $card_shown = 12;
        $card_rest = max(0, count($cards) - $card_shown);
        $card_i = 0;
    ?>
    <div class="w-ev__faculty<?php echo $card_rest ? ' w-shots--more' : ''; ?>" data-shots>
        <?php foreach ($cards as $card):
            $title = $card['title'] ?? '';
            $src = sc_img($card['image'] ?? '', 'medium_large', '(max-width: 767px) 78vw, 260px', array('alt' => $title), 1024);
            $text  = $card['content'] ?? ($card['description'] ?? '');
        ?>
        <div class="w-face<?php echo $card_i++ >= $card_shown ? ' is-extra' : ''; ?>">
            <?php if ($src): ?>
            <span class="w-face__pic" style="aspect-ratio:4/3">
                <?php echo $src; // Built by wp_get_attachment_image(). ?>
            </span>
            <?php endif; ?>
            <?php if ($title): ?><span class="w-face__name"><?php echo esc_html($title); ?></span><?php endif; ?>
            <?php if ($text): ?><span class="w-face__role"><?php echo esc_html(wp_strip_all_tags($text)); ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($card_rest): ?>
    <button type="button" class="w-btn w-btn--outline w-btn--sm w-shots__more" data-shots-more
            data-less="<?php echo esc_attr(sc_t('frontend.show_less', 'Show less')); ?>" style="align-self:flex-start">
        <?php printf(esc_html(sc_t('frontend.show_all_n', 'Show all %s')), esc_html(number_format_i18n(count($cards)))); ?>
    </button>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($cta_url && $cta_txt): ?>
    <a class="w-btn w-btn--outline" href="<?php echo esc_url($cta_url); ?>" style="align-self:flex-start">
        <?php echo esc_html($cta_txt); ?>
    </a>
    <?php endif; ?>
</section>
<?php endforeach; ?>
