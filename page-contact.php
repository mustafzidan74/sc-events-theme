<?php
/**
 * Template Name: Contact Page
 * Contact Us Page - Dark & Premium Design
 *
 * @package sc_events
 * @version 5.0.0
 */

// Load header
get_template_part('template-parts/public/header', 'public');

// Get platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_email = get_option('sc_platform_email', get_option('admin_email'));
$platform_phone = get_option('sc_platform_phone', '');
$platform_whatsapp = get_option('sc_platform_whatsapp', '');
$platform_address = get_option('sc_platform_address', '');

// Assets URL
$assets_url = get_template_directory_uri() . '/assets/frontend/';
?>

<!-- Page Header -->
<div class="sc-page-header">
    <div class="container">
        <h1><?php esc_html_e('Contact Us', 'sc_events'); ?></h1>
        <div class="sc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php esc_html_e('Contact', 'sc_events'); ?></span>
        </div>
    </div>
</div>

<!-- Contact Section -->
<section class="sc-contact-section">
    <div class="container">
        <div class="row g-4 align-items-start">

            <!-- Contact Form -->
            <div class="col-lg-7" data-aos="fade-right" data-aos-duration="800">
                <div class="sc-contact-form-card glass-card-static">
                    <h3 class="sc-contact-form-title">
                        <i class="fa-solid fa-paper-plane"></i>
                        <?php esc_html_e('Leave A Message', 'sc_events'); ?>
                    </h3>

                    <form id="contact-page-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="contact-name">
                                        <i class="fa-solid fa-user"></i>
                                        <?php esc_html_e('Name', 'sc_events'); ?>
                                    </label>
                                    <input type="text" id="contact-name" name="contact_name" class="sc-auth-input" placeholder="<?php esc_attr_e('Your name', 'sc_events'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="contact-phone">
                                        <i class="fa-solid fa-phone"></i>
                                        <?php esc_html_e('Phone', 'sc_events'); ?>
                                    </label>
                                    <input type="text" id="contact-phone" name="contact_phone" class="sc-auth-input" placeholder="<?php esc_attr_e('Your phone number', 'sc_events'); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="contact-email">
                                        <i class="fa-solid fa-envelope"></i>
                                        <?php esc_html_e('Email', 'sc_events'); ?>
                                    </label>
                                    <input type="email" id="contact-email" name="contact_email" class="sc-auth-input" placeholder="<?php esc_attr_e('Your email address', 'sc_events'); ?>" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="contact-subject">
                                        <i class="fa-solid fa-tag"></i>
                                        <?php esc_html_e('Subject', 'sc_events'); ?>
                                    </label>
                                    <input type="text" id="contact-subject" name="contact_subject" class="sc-auth-input" placeholder="<?php esc_attr_e('Message subject', 'sc_events'); ?>" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="contact-message">
                                        <i class="fa-solid fa-message"></i>
                                        <?php esc_html_e('Message', 'sc_events'); ?>
                                    </label>
                                    <textarea id="contact-message" name="contact_message" class="sc-auth-input sc-contact-textarea" placeholder="<?php esc_attr_e('Write your message...', 'sc_events'); ?>" required></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="sc-auth-btn">
                                    <span class="btn-text"><i class="fa-solid fa-paper-plane"></i> <?php esc_html_e('Send Message', 'sc_events'); ?></span>
                                    <span class="btn-loading" style="display:none;"><i class="fa-solid fa-spinner fa-spin"></i> <?php esc_html_e('Sending...', 'sc_events'); ?></span>
                                </button>
                            </div>
                            <div class="col-12">
                                <div id="contact-form-alert" style="display:none;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-5" data-aos="fade-left" data-aos-duration="800">
                <div class="sc-contact-info">
                    <div class="sc-contact-info-header">
                        <span class="sc-contact-badge"><?php esc_html_e('Get In Touch', 'sc_events'); ?></span>
                        <h2 class="sc-contact-info-title"><?php esc_html_e('Connect with Our Team', 'sc_events'); ?></h2>
                        <p class="sc-contact-info-desc"><?php esc_html_e('We\'re here to help! If you have any questions, need assistance with registration, or want to learn more about our events, feel free to reach out.', 'sc_events'); ?></p>
                    </div>

                    <div class="sc-contact-cards">
                        <?php if ($platform_email): ?>
                        <a href="mailto:<?php echo esc_attr($platform_email); ?>" class="sc-contact-item glass-card-static">
                            <div class="sc-contact-item-icon">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="sc-contact-item-text">
                                <h4><?php esc_html_e('Our Email', 'sc_events'); ?></h4>
                                <span><?php echo esc_html($platform_email); ?></span>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($platform_phone): ?>
                        <a href="tel:<?php echo esc_attr($platform_phone); ?>" class="sc-contact-item glass-card-static">
                            <div class="sc-contact-item-icon">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div class="sc-contact-item-text">
                                <h4><?php esc_html_e('Call Us', 'sc_events'); ?></h4>
                                <span><?php echo esc_html($platform_phone); ?></span>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($platform_whatsapp): ?>
                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $platform_whatsapp)); ?>" target="_blank" class="sc-contact-item sc-contact-whatsapp glass-card-static">
                            <div class="sc-contact-item-icon sc-whatsapp-icon">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div class="sc-contact-item-text">
                                <h4><?php esc_html_e('WhatsApp', 'sc_events'); ?></h4>
                                <span><?php echo esc_html($platform_whatsapp); ?></span>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($platform_address): ?>
                        <div class="sc-contact-item glass-card-static">
                            <div class="sc-contact-item-icon">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="sc-contact-item-text">
                                <h4><?php esc_html_e('Our Location', 'sc_events'); ?></h4>
                                <span><?php echo esc_html($platform_address); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Contact Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/contact-page.css">


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
