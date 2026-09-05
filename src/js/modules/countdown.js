/**
 * Countdown Timer Module
 *
 * @package sc_events
 * @version 1.0.0
 */

/**
 * Initialize countdown timers
 */
export function initCountdown() {
    initMiniCountdowns();
    initSingleEventCountdown();
}

/**
 * Initialize mini countdown timers (archive/category pages)
 */
function initMiniCountdowns() {
    const countdowns = document.querySelectorAll('.event-countdown-mini');

    countdowns.forEach((countdown) => {
        const timestamp = parseInt(countdown.getAttribute('data-timestamp')) * 1000;

        if (!timestamp || isNaN(timestamp)) {
            return;
        }

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = timestamp - now;

            if (distance < 0) {
                countdown.innerHTML = '<div class="event-ended-info"><i class="fa-solid fa-check-circle"></i> Event Started</div>';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const daysEl = countdown.querySelector('.days');
            const hoursEl = countdown.querySelector('.hours');
            const minutesEl = countdown.querySelector('.minutes');
            const secondsEl = countdown.querySelector('.seconds');

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
    const countdown = document.querySelector('.event-countdown');

    if (!countdown) {
        return;
    }

    const eventDate = countdown.getAttribute('data-event-date');

    if (!eventDate) {
        return;
    }

    const targetDate = new Date(eventDate).getTime();

    function updateCountdown() {
        const now = new Date().getTime();
        const distance = targetDate - now;

        if (distance < 0) {
            countdown.style.display = 'none';
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        const daysEl = document.getElementById('days');
        const hoursEl = document.getElementById('hours');
        const minutesEl = document.getElementById('minutes');
        const secondsEl = document.getElementById('seconds');

        if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
        if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
        if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
        if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
}
