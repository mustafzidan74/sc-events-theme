<?php
/**
 * "Download your certificate again" bar, under the public header
 * (inc/public-frontend/certificate-notice.php decides who sees it).
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
?>
<aside class="w-certnote" id="w-certnote" role="region" aria-label="<?php echo esc_attr(sc_t('frontend.cert_notice_label', 'Certificate update')); ?>">
    <div class="w-certnote__inner">
        <i class="fa-solid fa-certificate w-certnote__icon" aria-hidden="true"></i>
        <p class="w-certnote__text">
            <strong><?php echo esc_html(sc_t('frontend.cert_notice_title', 'Important update:')); ?></strong>
            <?php
            // str_replace, not sprintf: an edited translation with a stray % must not break the page.
            echo esc_html(str_replace('%s', $event_title, count($items) > 1
                ? sc_t('frontend.cert_notice_many', 'your %s certificates have been updated. Please download them again.')
                : sc_t('frontend.cert_notice_one', 'your %s certificate has been updated. Please download it again.')));
            ?>
        </p>
        <div class="w-certnote__actions">
            <?php foreach ($items as $item): ?>
            <a class="w-btn w-btn--sm w-certnote__get" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <span><?php echo esc_html($item['title']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="w-certnote__close" type="button" aria-label="<?php echo esc_attr(sc_t('frontend.close', 'Close')); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('sc_certificate_notice')); ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
</aside>
<script>
(function () {
    var bar = document.getElementById('w-certnote');
    if (!bar) { return; }
    // A download takes that certificate out of the bar (the server records it too).
    bar.addEventListener('click', function (e) {
        var get = e.target.closest('.w-certnote__get');
        if (get) {
            setTimeout(function () {
                get.remove();
                if (!bar.querySelector('.w-certnote__get')) { bar.remove(); }
            }, 300);
        }
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
