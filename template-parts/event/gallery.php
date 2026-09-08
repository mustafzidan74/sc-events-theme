<?php
/**
 * The free-form sections an organiser adds to an event.
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

/**
 * Resolve an image reference, which may be an attachment id or a bare URL.
 */
if (!function_exists('sc_event_image_src')) {
    function sc_event_image_src($ref, $size = 'large') {
        if (empty($ref)) { return ''; }
        if (is_array($ref)) { $ref = $ref['id'] ?? ($ref['url'] ?? ''); }
        return is_numeric($ref) ? (wp_get_attachment_image_url($ref, $size) ?: '') : (string) $ref;
    }
}

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
    $hero    = sc_event_image_src($section['main_image'] ?? '');

    if (!$heading && !$content && !$images && !$cards && !$hero) { continue; }
?>
<section class="w-ev__section">
    <?php if ($heading): ?>
        <h2 class="w-ev__h2"><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>

    <?php if ($content): ?>
        <div class="w-prose"><?php echo wp_kses_post(wpautop($content)); ?></div>
    <?php endif; ?>

    <?php if ($hero): ?>
        <div class="w-gallery__shot" style="aspect-ratio:16/9">
            <img src="<?php echo esc_url($hero); ?>" alt="<?php echo esc_attr($heading); ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>

    <?php if ($images): ?>
    <div class="w-gallery">
        <?php foreach ($images as $img):
            $src = sc_event_image_src($img);
            if (!$src) { continue; }
        ?>
        <div class="w-gallery__shot">
            <img src="<?php echo esc_url($src); ?>" alt="" loading="lazy" decoding="async">
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($cards): ?>
    <div class="w-ev__faculty">
        <?php foreach ($cards as $card):
            $src = sc_event_image_src($card['image'] ?? '', 'medium_large');
            $title = $card['title'] ?? '';
            $text  = $card['content'] ?? ($card['description'] ?? '');
        ?>
        <div class="w-face">
            <?php if ($src): ?>
            <span class="w-face__pic" style="aspect-ratio:4/3">
                <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
            </span>
            <?php endif; ?>
            <?php if ($title): ?><span class="w-face__name"><?php echo esc_html($title); ?></span><?php endif; ?>
            <?php if ($text): ?><span class="w-face__role"><?php echo esc_html(wp_strip_all_tags($text)); ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($cta_url && $cta_txt): ?>
    <a class="w-btn w-btn--outline" href="<?php echo esc_url($cta_url); ?>" style="align-self:flex-start">
        <?php echo esc_html($cta_txt); ?>
    </a>
    <?php endif; ?>
</section>
<?php endforeach; ?>
