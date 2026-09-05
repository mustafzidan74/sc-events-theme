/**
 * Event Countdown Timer
 *
 * Standalone countdown timer for event pages
 *
 * @package sc_events
 * @version 1.0.0
 */

(function() {
    'use strict';

    /**
     * Initialize countdown timers
     */
    function initCountdowns() {
        var countdowns = document.querySelectorAll('.event-countdown-mini');

        countdowns.forEach(function(countdown) {
            var timestamp = parseInt(countdown.getAttribute('data-timestamp')) * 1000;

            if (!timestamp || isNaN(timestamp)) {
                return;
            }

            function updateCountdown() {
                var now = new Date().getTime();
                var distance = timestamp - now;

                if (distance < 0) {
                    countdown.innerHTML = '<div class="event-ended-info"><i class="fa-solid fa-check-circle"></i> Event Started</div>';
                    return;
                }

                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                var daysEl = countdown.querySelector('.days');
                var hoursEl = countdown.querySelector('.hours');
                var minutesEl = countdown.querySelector('.minutes');
                var secondsEl = countdown.querySelector('.seconds');

                if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
                if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
                if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
                if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, '0');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
    }

    /**
     * Initialize single event page countdown
     */
    function initSingleEventCountdown() {
        var countdown = document.querySelector('.event-countdown');

        if (!countdown) {
            return;
        }

        var eventDate = countdown.getAttribute('data-event-date');

        if (!eventDate) {
            return;
        }

        var targetDate = new Date(eventDate).getTime();

        function updateCountdown() {
            var now = new Date().getTime();
            var distance = targetDate - now;

            if (distance < 0) {
                countdown.style.display = 'none';
                return;
            }

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            var daysEl = document.getElementById('days');
            var hoursEl = document.getElementById('hours');
            var minutesEl = document.getElementById('minutes');
            var secondsEl = document.getElementById('seconds');

            if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
            if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
            if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
            if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // Initialize on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        initCountdowns();
        initSingleEventCountdown();
    });

})();
