<?php
/**
 * Event Organizing Company Section
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$oc = $args['organizing_company'] ?? null;

if (empty($oc) || empty($oc['name'])) return;

$logo_url = !empty($oc['logo']) ? wp_get_attachment_url($oc['logo']) : '';
$banner_url = !empty($oc['banner']) ? wp_get_attachment_url($oc['banner']) : '';
$social = $oc['social'] ?? [];
?>

<section class="sc-section" id="organizing-company">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.organizing_company', 'Organized By')); ?></h2>
        </div>

        <div class="row justify-content-center" data-aos="fade-up">
            <div class="col-lg-8">
                <div style="background: var(--sc-card-bg, rgba(255,255,255,0.03)); border: 1px solid var(--sc-border, rgba(255,255,255,0.08)); border-radius: 16px; overflow: hidden;">

                    <?php if ($banner_url): ?>
                    <div style="height: 180px; background: url('<?php echo esc_url($banner_url); ?>') center/cover no-repeat;"></div>
                    <?php endif; ?>

                    <div style="padding: 30px; text-align: center; <?php echo $banner_url ? 'margin-top: -50px;' : ''; ?>">
                        <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($oc['name']); ?>"
                             style="width: 100px; height: 100px; border-radius: 16px; object-fit: contain; background: var(--sc-bg, #fff); border: 3px solid var(--sc-border, rgba(255,255,255,0.1)); padding: 8px; <?php echo $banner_url ? 'position: relative;' : ''; ?>">
                        <?php endif; ?>

                        <h3 style="margin-top: 16px; color: var(--sc-text-primary, #f1f5f9); font-size: 1.4rem;">
                            <?php echo esc_html($oc['name']); ?>
                        </h3>

                        <?php if (!empty($oc['description'])): ?>
                        <p style="color: var(--sc-text-secondary, #94a3b8); margin-top: 12px; font-size: 0.95rem; line-height: 1.7;">
                            <?php echo wp_kses_post($oc['description']); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($oc['website']) || !empty($oc['phone']) || !empty($oc['email'])): ?>
                        <div style="margin-top: 20px; display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; font-size: 0.9rem;">
                            <?php if (!empty($oc['website'])): ?>
                            <a href="<?php echo esc_url($oc['website']); ?>" target="_blank" style="color: var(--sc-primary, #3b82f6); text-decoration: none;">
                                <i class="fa-solid fa-globe"></i> <?php echo esc_html(parse_url($oc['website'], PHP_URL_HOST)); ?>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($oc['phone'])): ?>
                            <a href="tel:<?php echo esc_attr($oc['phone']); ?>" style="color: var(--sc-text-secondary, #94a3b8); text-decoration: none;">
                                <i class="fa-solid fa-phone"></i> <?php echo esc_html($oc['phone']); ?>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($oc['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($oc['email']); ?>" style="color: var(--sc-text-secondary, #94a3b8); text-decoration: none;">
                                <i class="fa-solid fa-envelope"></i> <?php echo esc_html($oc['email']); ?>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($oc['whatsapp'])): ?>
                            <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $oc['whatsapp'])); ?>" target="_blank" style="color: #25d366; text-decoration: none;">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($social)): ?>
                        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 12px;">
                            <?php if (!empty($social['facebook'])): ?>
                            <a href="<?php echo esc_url($social['facebook']); ?>" target="_blank" style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59,130,246,0.1); display: flex; align-items: center; justify-content: center; color: #3b82f6; text-decoration: none; transition: all 0.2s;">
                                <i class="fa-brands fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['twitter'])): ?>
                            <a href="<?php echo esc_url($social['twitter']); ?>" target="_blank" style="width: 40px; height: 40px; border-radius: 10px; background: rgba(29,161,242,0.1); display: flex; align-items: center; justify-content: center; color: #1da1f2; text-decoration: none; transition: all 0.2s;">
                                <i class="fa-brands fa-twitter"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['instagram'])): ?>
                            <a href="<?php echo esc_url($social['instagram']); ?>" target="_blank" style="width: 40px; height: 40px; border-radius: 10px; background: rgba(228,64,95,0.1); display: flex; align-items: center; justify-content: center; color: #e4405f; text-decoration: none; transition: all 0.2s;">
                                <i class="fa-brands fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($social['linkedin'])): ?>
                            <a href="<?php echo esc_url($social['linkedin']); ?>" target="_blank" style="width: 40px; height: 40px; border-radius: 10px; background: rgba(10,102,194,0.1); display: flex; align-items: center; justify-content: center; color: #0a66c2; text-decoration: none; transition: all 0.2s;">
                                <i class="fa-brands fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
