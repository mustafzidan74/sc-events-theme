<?php
/**
 * Certificate verification page — /certificate-verify/{code}/.
 *
 * The QR code on every certificate PDF points here. Shows whether the code
 * belongs to a real certificate and, if so, who it was issued to and for what.
 * Only the random verification code is accepted, so certificates can't be
 * found by guessing sequential numbers.
 *
 * Expects: $sc_verify_code (string, may be empty for the lookup form).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $sc_verify_code));
$certificate = ($code !== '' && class_exists('SC_Certificate')) ? SC_Certificate::get_by_verification_code($code) : null;
$state = $code === '' ? 'form' : (!$certificate ? 'missing' : ($certificate['status'] === 'revoked' ? 'revoked' : 'valid'));

$workshop_title = '';
if ($certificate && !empty($certificate['workshop_id'])) {
    global $wpdb;
    $workshop_title = (string) $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}sc_workshops WHERE id = %d", (int) $certificate['workshop_id']));
}
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));

get_template_part('template-parts/public/header', 'public');
?>

<main class="w-verify">
    <header class="w-verify__head">
        <p class="w-verify__eyebrow"><?php echo esc_html(sc_t('frontend.certificate_verification', 'Certificate verification')); ?></p>
        <h1><?php
            if ($state === 'valid') {
                echo esc_html(sc_t('frontend.certificate_is_valid', 'This certificate is genuine.'));
            } elseif ($state === 'revoked') {
                echo esc_html(sc_t('frontend.certificate_revoked', 'This certificate has been revoked.'));
            } elseif ($state === 'missing') {
                echo esc_html(sc_t('frontend.certificate_not_found', 'We couldn’t find this certificate.'));
            } else {
                echo esc_html(sc_t('frontend.verify_a_certificate', 'Verify a certificate'));
            }
        ?></h1>
    </header>

    <?php if ($state === 'valid' || $state === 'revoked'): ?>
    <section class="w-card w-verify__card w-verify__card--<?php echo esc_attr($state); ?>" aria-labelledby="verify-name">
        <div class="w-verify__status">
            <span class="w-verify__icon" aria-hidden="true"><i class="fa-solid <?php echo $state === 'valid' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i></span>
            <span><?php echo esc_html($state === 'valid'
                ? sprintf(sc_t('frontend.issued_by_platform', 'Issued by %s'), $platform_name)
                : sc_t('frontend.certificate_revoked_note', 'It was issued by us but is no longer valid.')); ?></span>
        </div>
        <dl class="w-verify__facts">
            <div><dt><?php echo esc_html(sc_t('frontend.awarded_to', 'Awarded to')); ?></dt><dd id="verify-name" class="w-verify__name"><?php echo esc_html($certificate['attendee_name']); ?></dd></div>
            <div><dt><?php echo esc_html(sc_t('frontend.for', 'For')); ?></dt><dd><?php echo esc_html($certificate['event_title']); ?><?php echo $workshop_title !== '' ? '<br><span class="w-verify__sub">' . esc_html($workshop_title) . '</span>' : ''; ?></dd></div>
            <div><dt><?php echo esc_html(sc_t('frontend.event_date', 'Event date')); ?></dt><dd><?php echo esc_html(date_i18n('j F Y', strtotime($certificate['event_date']))); ?></dd></div>
            <div><dt><?php echo esc_html(sc_t('frontend.certificate_number', 'Certificate number')); ?></dt><dd class="w-verify__mono"><?php echo esc_html($certificate['certificate_number']); ?></dd></div>
            <div><dt><?php echo esc_html(sc_t('frontend.issued_on', 'Issued on')); ?></dt><dd><?php echo esc_html(date_i18n('j F Y', strtotime($certificate['issued_at']))); ?></dd></div>
            <?php if ($state === 'revoked' && !empty($certificate['revoked_at'])): ?>
            <div><dt><?php echo esc_html(sc_t('frontend.revoked_on', 'Revoked on')); ?></dt><dd><?php echo esc_html(date_i18n('j F Y', strtotime($certificate['revoked_at']))); ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>
    <?php endif; ?>

    <?php if ($state === 'missing'): ?>
    <p class="w-verify__lede"><?php echo esc_html(sc_t('frontend.certificate_not_found_help', 'Scan the QR code on the certificate again, or type the verification code carefully. If it still doesn’t match, the certificate wasn’t issued by us.')); ?></p>
    <?php endif; ?>

    <?php if ($state !== 'valid'): ?>
    <form class="w-verify__form" method="get" action="<?php echo esc_url(home_url('/certificate-verify/')); ?>">
        <div class="w-field">
            <label class="w-label" for="verify-code"><?php echo esc_html(sc_t('frontend.verification_code', 'Verification code')); ?></label>
            <input class="w-input w-verify__mono" type="text" id="verify-code" name="code" value="<?php echo esc_attr($code); ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" required>
        </div>
        <button class="w-btn" type="submit"><?php echo esc_html(sc_t('frontend.verify', 'Verify')); ?></button>
    </form>
    <?php endif; ?>
</main>

<style>
.w-verify { max-width: 720px; margin: 0 auto; padding: var(--w-space-10) var(--w-space-6) var(--w-space-16); display: flex; flex-direction: column; gap: var(--w-space-6); }
.w-verify__eyebrow { margin: 0 0 var(--w-space-2); color: var(--w-text-3); font-size: var(--w-small-size); font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.w-verify__head h1 { margin: 0; font-size: var(--w-h1-size); line-height: var(--w-h1-lh); font-weight: var(--w-h1-weight); letter-spacing: var(--w-h1-ls); color: var(--w-text); }
.w-verify__lede { margin: 0; color: var(--w-text-2); font-size: var(--w-body-lg-size); line-height: var(--w-body-lg-lh); }
.w-verify__card { padding: var(--w-space-6); border-top: 4px solid var(--w-teal); }
.w-verify__card--revoked { border-top-color: var(--w-primary); }
.w-verify__status { display: flex; align-items: center; gap: var(--w-space-3); margin-bottom: var(--w-space-5); color: var(--w-text-2); font-weight: 500; }
.w-verify__icon { font-size: 28px; line-height: 1; color: var(--w-teal); }
.w-verify__card--revoked .w-verify__icon { color: var(--w-primary); }
.w-verify__facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--w-space-5) var(--w-space-6); margin: 0; }
.w-verify__facts > div:first-child, .w-verify__facts > div:nth-child(2) { grid-column: 1 / -1; }
.w-verify__facts dt { margin: 0 0 var(--w-space-1); color: var(--w-text-3); font-size: var(--w-small-size); font-weight: 500; }
.w-verify__facts dd { margin: 0; color: var(--w-text); font-size: var(--w-body-size); line-height: var(--w-body-lh); overflow-wrap: anywhere; }
.w-verify__name { font-size: var(--w-h2-size) !important; line-height: var(--w-h2-lh) !important; font-weight: var(--w-h2-weight); }
.w-verify__sub { color: var(--w-text-2); }
.w-verify__mono { font-family: var(--w-font-mono); letter-spacing: .03em; }
.w-verify__form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--w-space-3); }
.w-verify__form .w-field { flex: 1 1 260px; margin: 0; }
@media (max-width: 575.98px) {
  .w-verify { padding: var(--w-space-8) var(--w-space-4) var(--w-space-12); }
  .w-verify__facts { grid-template-columns: minmax(0, 1fr); }
  .w-verify__form .w-btn { width: 100%; }
}
</style>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
