<?php
/**
 * "Download your certificate again" bar, pinned under the public header with the
 * message scrolling across it (inc/public-frontend/certificate-notice.php decides
 * who sees it). Wide screens get a button per certificate; phones get one button.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$items = function_exists('sc_certificate_notice_items') ? sc_certificate_notice_items(wp_get_current_user()) : array();
if (!$items) {
    return;
}
$event = SC_Event::get(sc_certificate_notice()['event_id']);
$event_title = $event ? $event->title : '';
// str_replace, not sprintf: an edited translation with a stray % must not break the page.
$message = str_replace('%s', $event_title, count($items) > 1
    ? sc_t('frontend.cert_notice_many', 'your %s certificates have been updated. Please download them again.')
    : sc_t('frontend.cert_notice_one', 'your %s certificate has been updated. Please download it again.'));
$label = sc_t('frontend.cert_notice_title', 'Important update:');
// One certificate downloads straight away; several are listed on My Account.
$phone_url = count($items) === 1 ? $items[0]['url'] : home_url('/my-account/#certificates');
?>
<aside class="w-certnote" id="w-certnote" role="region" aria-label="<?php echo esc_attr(sc_t('frontend.cert_notice_label', 'Certificate update')); ?>">
    <span class="w-certnote__badge">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        <span class="w-certnote__badge-text"><?php echo esc_html(sc_t('frontend.cert_notice_badge', 'Important')); ?></span>
    </span>

    <p class="w-certnote__sr"><?php echo esc_html($label . ' ' . $message); ?></p>
    <div class="w-certnote__marquee" aria-hidden="true">
        <div class="w-certnote__track">
            <?php for ($copy = 0; $copy < 2; $copy++): // two identical halves make the loop seamless ?>
            <span class="w-certnote__group">
                <?php for ($i = 0; $i < 2; $i++): ?>
                <span class="w-certnote__msg"><strong><?php echo esc_html($label); ?></strong> <?php echo esc_html($message); ?></span>
                <i class="fa-solid fa-certificate w-certnote__sep" aria-hidden="true"></i>
                <?php endfor; ?>
            </span>
            <?php endfor; ?>
        </div>
    </div>

    <div class="w-certnote__actions">
        <?php foreach ($items as $item): ?>
        <a class="w-certnote__get" data-id="<?php echo esc_attr($item['id']); ?>" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-download" aria-hidden="true"></i>
            <span><?php echo esc_html($item['title']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <a class="w-certnote__get w-certnote__get--phone"<?php echo count($items) === 1 ? ' data-id="' . esc_attr($items[0]['id']) . '" target="_blank" rel="noopener"' : ''; ?> href="<?php echo esc_url($phone_url); ?>">
        <i class="fa-solid fa-download" aria-hidden="true"></i>
        <span><?php echo esc_html(sc_t('frontend.download', 'Download')); ?></span>
    </a>

    <button class="w-certnote__close" type="button" aria-label="<?php echo esc_attr(sc_t('frontend.close', 'Close')); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce('sc_certificate_notice')); ?>">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
</aside>
<script>
(function () {
    var bar = document.getElementById('w-certnote');
    if (!bar) { return; }
    // A download takes that certificate out of the bar (the server records it too).
    bar.addEventListener('click', function (e) {
        var get = e.target.closest('.w-certnote__get[data-id]');
        if (!get) { return; }
        setTimeout(function () {
            bar.querySelectorAll('.w-certnote__get[data-id="' + get.dataset.id + '"]').forEach(function (el) { el.remove(); });
            if (!bar.querySelector('.w-certnote__actions .w-certnote__get')) { bar.remove(); }
        }, 300);
    });
    bar.querySelector('.w-certnote__close').addEventListener('click', function () {
        bar.remove();
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=sc_certificate_notice_close&nonce=' + encodeURIComponent(this.dataset.nonce)
        });
    });
})();
</script>
