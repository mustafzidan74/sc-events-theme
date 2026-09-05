/**
 * Front Page Scripts
 * Countdown timers and contact form handling
 *
 * @package sc_events
 */

document.addEventListener('DOMContentLoaded', function() {
    // Countdown timers
    var countdowns = document.querySelectorAll('.event-countdown-mini');
    countdowns.forEach(function(countdown) {
        var timestamp = parseInt(countdown.dataset.timestamp) * 1000;

        function updateCountdown() {
            var now = new Date().getTime();
            var distance = timestamp - now;

            if (distance < 0) {
                countdown.querySelector('.days').textContent = '00';
                countdown.querySelector('.hours').textContent = '00';
                countdown.querySelector('.minutes').textContent = '00';
                countdown.querySelector('.seconds').textContent = '00';
                return;
            }

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdown.querySelector('.days').textContent = days.toString().padStart(2, '0');
            countdown.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
            countdown.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
            countdown.querySelector('.seconds').textContent = seconds.toString().padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    });

    // Contact form
    var contactForm = document.getElementById('homepage-contact-form');
    var alertBox = document.getElementById('contact-form-alert');

    function showAlert(type, message) {
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<i class="fa-solid ' + iconClass + ' me-2"></i>' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        alertBox.style.display = 'block';
    }

    if (contactForm && typeof scPublic !== 'undefined') {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Hide previous alert
            alertBox.style.display = 'none';

            var btn = contactForm.querySelector('button[type="submit"]');
            var btnText = btn.querySelector('.btn-text');
            var btnLoading = btn.querySelector('.btn-loading');

            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            btn.disabled = true;

            var formData = new FormData(contactForm);
            formData.append('action', 'sc_submit_contact_form');
            formData.append('nonce', scPublic.nonce);

            fetch(scPublic.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                btn.disabled = false;

                if (data.success) {
                    showAlert('success', data.data.message);
                    contactForm.reset();
                } else {
                    showAlert('danger', data.data.message);
                }
            })
            .catch(function(error) {
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                btn.disabled = false;

                // Get descriptive error message
                var errorMsg = 'Failed to send your message. ';
                if (!navigator.onLine) {
                    errorMsg += 'You appear to be offline. Please check your internet connection.';
                } else if (error.name === 'AbortError') {
                    errorMsg += 'The request was cancelled. Please try again.';
                } else if (error.name === 'TypeError') {
                    errorMsg += 'Could not connect to the server. Please check your internet connection.';
                } else {
                    errorMsg += 'Please try again or contact us directly via email.';
                }
                showAlert('danger', errorMsg);
            });
        });
    }
});
