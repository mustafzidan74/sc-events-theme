/**
 * Single Event Page JavaScript
 * Handles countdown, FAQ accordion, schedule tabs, coupon forms, Fancybox gallery
 *
 * @package sc_events
 * @version 5.1.0
 */

jQuery(document).ready(function($) {

    // =========================================
    // Event Countdown Timer
    // =========================================
    var countdownElement = $('.sc-countdown');
    if (countdownElement.length) {
        var eventDate = new Date(countdownElement.data('event-date')).getTime();

        function updateCountdown() {
            var now = new Date().getTime();
            var distance = eventDate - now;

            if (distance < 0) {
                countdownElement.html('<div class="event-countdown-ended">Event has started!</div>');
                return;
            }

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            $('#days').text(String(days).padStart(2, '0'));
            $('#hours').text(String(hours).padStart(2, '0'));
            $('#minutes').text(String(minutes).padStart(2, '0'));
            $('#seconds').text(String(seconds).padStart(2, '0'));
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // =========================================
    // FAQ Accordion
    // =========================================
    $('.sc-accordion-trigger').on('click', function() {
        var $trigger = $(this);
        var $item = $trigger.closest('.sc-accordion-item');
        var $body = $item.find('.sc-accordion-body');
        var isActive = $trigger.hasClass('active');

        // Close all items in the same accordion
        var $accordion = $trigger.closest('.sc-accordion');
        $accordion.find('.sc-accordion-trigger.active').not($trigger).each(function() {
            $(this).removeClass('active');
            $(this).find('i').removeClass('fa-minus').addClass('fa-plus');
            $(this).closest('.sc-accordion-item').find('.sc-accordion-body').slideUp(300);
        });

        // Toggle current item
        if (isActive) {
            $trigger.removeClass('active');
            $trigger.find('i').removeClass('fa-minus').addClass('fa-plus');
            $body.slideUp(300);
        } else {
            $trigger.addClass('active');
            $trigger.find('i').removeClass('fa-plus').addClass('fa-minus');
            $body.slideDown(300);
        }
    });

    // =========================================
    // Schedule Day Tabs
    // =========================================
    $('.sc-pill[data-tab]').on('click', function() {
        var $pill = $(this);
        var target = $pill.data('tab');

        // Update active pill
        $pill.siblings('.sc-pill').removeClass('active');
        $pill.addClass('active');

        // Show target day, hide others
        $('.sc-schedule-day').addClass('d-none');
        $('.sc-schedule-day[data-day="' + target + '"]').removeClass('d-none');
    });

    // =========================================
    // Coupon Form
    // =========================================
    $('.btn-show-coupon-form').on('click', function() {
        $('#coupon-form-wrapper').slideToggle();
        $('#coupon-code').focus();
    });

    // =========================================
    // Fancybox Gallery
    // =========================================
    if (typeof Fancybox !== 'undefined') {
        Fancybox.bind('[data-fancybox^="section-gallery"]', {
            Thumbs: {
                autoStart: true,
            },
            Toolbar: {
                display: {
                    left: [],
                    middle: [],
                    right: ["close"],
                },
            },
        });

        Fancybox.bind('[data-fancybox="gallery"]', {
            Thumbs: {
                autoStart: true,
            },
        });
    }

    // =========================================
    // Smooth scroll for anchor links
    // =========================================
    $('a[href^="#"]').on('click', function(e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 600);
        }
    });

    // =========================================
    // Floating CTA show/hide on scroll
    // =========================================
    var $floatingCta = $('#sc-floating-cta');
    if ($floatingCta.length) {
        var $ticketsSection = $('#tickets');
        var ticketsOffset = $ticketsSection.length ? $ticketsSection.offset().top + $ticketsSection.outerHeight() : 600;

        $(window).on('scroll', function() {
            if ($(window).scrollTop() > ticketsOffset) {
                $floatingCta.addClass('visible');
            } else {
                $floatingCta.removeClass('visible');
            }
        });
    }
});
