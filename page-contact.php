<?php
/**
 * Template Name: Contact Page
 *
 * The form is the page; the ways to reach a human sit beside it rather than
 * under it, because someone with an urgent question about a ticket wants the
 * WhatsApp number, not a form.
 *
 * The script at the foot still owns submission, so #contact-page-form, the
 * contact_* field names, #contact-form-alert and the button's .btn-text /
 * .btn-loading pair are kept exactly as they were.
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');

$platform_email    = get_option('sc_platform_email', get_option('admin_email'));
$platform_phone    = get_option('sc_platform_phone', '');
$platform_whatsapp = get_option('sc_platform_whatsapp', '');
$platform_address  = get_option('sc_platform_address', '');

// Pages that send people here can say what it is about — the sponsors page
// links in asking about a booth — so the subject arrives filled in.
$subject = isset($_GET['subject']) ? sanitize_text_field(wp_unslash($_GET['subject'])) : '';

$ways = [];
if ($platform_email) {
    $ways[] = [
        'href'  => 'mailto:' . $platform_email,
        'icon'  => 'fa-regular fa-envelope',
        'label' => sc_t('frontend.email', 'Email'),
        'value' => $platform_email,
    ];
}
if ($platform_whatsapp) {
    $ways[] = [
        'href'  => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $platform_whatsapp),
        'icon'  => 'fa-brands fa-whatsapp',
        'label' => 'WhatsApp',
        'value' => $platform_whatsapp,
        'blank' => true,
    ];
}
if ($platform_phone) {
    $ways[] = [
        'href'  => 'tel:' . preg_replace('/[^0-9+]/', '', $platform_phone),
        'icon'  => 'fa-solid fa-phone',
        'label' => sc_t('frontend.call_us', 'Phone'),
        'value' => $platform_phone,
    ];
}
?>

<main class="w-contact">
    <header class="w-page-head" style="margin-bottom:0">
        <h1><?php echo esc_html(sc_t('frontend.contact_title', 'Talk to us.')); ?></h1>
        <p><?php echo esc_html(sc_t('frontend.contact_lede', 'Registration, invoices, booths, or anything about the programme.')); ?></p>
    </header>

    <div class="w-contact__grid">
        <form class="w-contact__form" id="contact-page-form">
            <div class="w-contact__pair">
                <div class="w-field">
                    <label class="w-label" for="contact-name"><?php echo esc_html(sc_t('frontend.full_name', 'Name')); ?></label>
                    <input class="w-input" type="text" id="contact-name" name="contact_name"
                           autocomplete="name" required>
                </div>
                <div class="w-field">
                    <label class="w-label" for="contact-phone"><?php echo esc_html(sc_t('frontend.mobile', 'Mobile')); ?></label>
                    <input class="w-input" type="tel" id="contact-phone" name="contact_phone"
                           autocomplete="tel" placeholder="+20 100 000 0000">
                </div>
            </div>

            <div class="w-field">
                <label class="w-label" for="contact-email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                <input class="w-input" type="email" id="contact-email" name="contact_email"
                       autocomplete="email" placeholder="you@clinic.com" required>
            </div>

            <div class="w-field">
                <label class="w-label" for="contact-subject"><?php echo esc_html(sc_t('frontend.subject', 'Subject')); ?></label>
                <input class="w-input" type="text" id="contact-subject" name="contact_subject"
                       value="<?php echo esc_attr($subject); ?>" required>
            </div>

            <div class="w-field">
                <label class="w-label" for="contact-message"><?php echo esc_html(sc_t('frontend.message', 'Message')); ?></label>
                <textarea class="w-input w-contact__area" id="contact-message" name="contact_message"
                          rows="6" required></textarea>
            </div>

            <?php // Left empty by people; bots fill it and their message is dropped. ?>
            <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                <label for="contact-website">Website</label>
                <input type="text" id="contact-website" name="contact_website" tabindex="-1" autocomplete="off">
            </div>

            <div id="contact-form-alert" style="display:none"></div>

            <button class="w-btn w-btn--lg" type="submit" style="align-self:flex-start">
                <span class="btn-text">
                    <?php echo esc_html(sc_t('frontend.send_message', 'Send message')); ?>
                </span>
                <span class="btn-loading" style="display:none">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.sending', 'Sending…')); ?>
                </span>
            </button>
        </form>

        <aside class="w-contact__side">
            <?php foreach ($ways as $way): ?>
            <a class="w-contact__way" href="<?php echo esc_url($way['href']); ?>"
               <?php echo !empty($way['blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <span class="w-contact__icon" aria-hidden="true"><i class="<?php echo esc_attr($way['icon']); ?>"></i></span>
                <span class="w-contact__way-text">
                    <span class="w-contact__way-label"><?php echo esc_html($way['label']); ?></span>
                    <span class="w-contact__way-value"><?php echo esc_html($way['value']); ?></span>
                </span>
            </a>
            <?php endforeach; ?>

            <?php if ($platform_address): ?>
            <div class="w-contact__way" style="cursor:default">
                <span class="w-contact__icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                <span class="w-contact__way-text">
                    <span class="w-contact__way-label"><?php echo esc_html(sc_t('frontend.our_location', 'Address')); ?></span>
                    <span class="w-contact__way-value"><?php echo esc_html($platform_address); ?></span>
                </span>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var contactForm = document.getElementById('contact-page-form');
    var alertBox = document.getElementById('contact-form-alert');

    function showAlert(type, message) {
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<i class="fa-solid ' + iconClass + ' me-2"></i>' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        alertBox.style.display = 'block';
    }

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            alertBox.style.display = 'none';

            var btn = contactForm.querySelector('button[type="submit"]');
            var btnText = btn.querySelector('.btn-text');
            var btnLoading = btn.querySelector('.btn-loading');

            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
            btn.disabled = true;

            var formData = new FormData(contactForm);
            formData.append('action', 'sc_submit_contact_form');
            formData.append('nonce', scPublic.nonce);

            fetch(scPublic.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnText.style.display = 'inline-flex';
                btnLoading.style.display = 'none';
                btn.disabled = false;

                if (data.success) {
                    showAlert('success', data.data.message);
                    contactForm.reset();
                } else {
                    showAlert('danger', data.data.message);
                }
            })
            .catch(error => {
                btnText.style.display = 'inline-flex';
                btnLoading.style.display = 'none';
                btn.disabled = false;

                var errorMsg = '<?php echo esc_js(__('Failed to send your message. ', 'sc_events')); ?>';
                if (!navigator.onLine) {
                    errorMsg += '<?php echo esc_js(__('You appear to be offline. Please check your internet connection.', 'sc_events')); ?>';
                } else {
                    errorMsg += '<?php echo esc_js(__('Please try again or contact us directly via email.', 'sc_events')); ?>';
                }
                showAlert('danger', errorMsg);
            });
        });
    }
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
