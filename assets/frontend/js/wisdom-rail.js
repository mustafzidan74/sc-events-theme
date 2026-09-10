/*
 * Turns the horizontal card rails into sliders.
 *
 * The markup is already a scroll-snap container, which is what touch wants, so
 * nothing here replaces that — it adds what a mouse needs on top: arrows, drag,
 * and an end state so a dead arrow does not look broken. No library: a rail is
 * a scroller, and the browser already knows how to scroll.
 */
(function () {
    'use strict';

    var SELECTOR = '.w-rail, .w-slot__list';

    function step(rail) {
        var card = rail.firstElementChild;
        if (!card) { return rail.clientWidth * 0.8; }
        var gap = parseFloat(getComputedStyle(rail).columnGap || getComputedStyle(rail).gap) || 14;
        return card.getBoundingClientRect().width + gap;
    }

    // The rails carry a few pixels of padding, so a rail sitting at rest can
    // report a small non-zero scrollLeft. The tolerance has to clear that or
    // the back arrow never reads as disabled at the start.
    var EDGE = 12;

    function atStart(rail) {
        return Math.abs(rail.scrollLeft) <= EDGE;
    }

    function atEnd(rail) {
        // scrollLeft runs negative under RTL in most engines, so compare on size.
        var max = rail.scrollWidth - rail.clientWidth;
        return max - Math.abs(rail.scrollLeft) <= EDGE;
    }

    function makeButton(dir, label) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'w-rail__btn';
        b.dataset.rail = dir;
        b.setAttribute('aria-label', label);
        b.innerHTML = '<i class="fa-solid fa-chevron-' + (dir === 'next' ? 'right' : 'left') + '" aria-hidden="true"></i>';
        return b;
    }

    function controlsFor(rail) {
        // Reuse the arrows the template already printed, if any.
        var head = rail.previousElementSibling;
        var existing = head ? head.querySelectorAll('[data-rail]') : [];
        if (existing.length) { return existing; }

        // Otherwise add a pair beside the section heading, or above the rail.
        var nav = document.createElement('div');
        nav.className = 'w-rail__nav';
        nav.appendChild(makeButton('prev', rail.dataset.prevLabel || 'Previous'));
        nav.appendChild(makeButton('next', rail.dataset.nextLabel || 'Next'));

        var slot = head && head.classList.contains('w-section__head') ? head : null;
        if (slot) {
            slot.appendChild(nav);
        } else {
            rail.parentNode.insertBefore(nav, rail);
        }
        return nav.querySelectorAll('[data-rail]');
    }

    function setup(rail) {
        if (rail.dataset.railReady) { return; }
        rail.dataset.railReady = '1';

        // Nothing to slide.
        if (rail.scrollWidth <= rail.clientWidth + 4) { return; }

        var buttons = controlsFor(rail);
        var rtl = getComputedStyle(rail).direction === 'rtl';

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = btn.dataset.rail === 'next' ? 1 : -1;
                if (rtl) { dir *= -1; }
                rail.scrollBy({ left: step(rail) * dir, behavior: 'smooth' });
            });
        });

        function sync() {
            buttons.forEach(function (btn) {
                var isNext = btn.dataset.rail === 'next';
                btn.disabled = isNext ? atEnd(rail) : atStart(rail);
            });
        }

        rail.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        sync();

        // Drag with a mouse. Touch already works, and dragging with a finger
        // would fight the native scroll, so pointer events from touch are left
        // alone.
        var down = false, startX = 0, startScroll = 0, moved = false;

        rail.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'touch' || e.button !== 0) { return; }
            down = true;
            moved = false;
            startX = e.clientX;
            startScroll = rail.scrollLeft;
            rail.classList.add('is-dragging');
        });

        rail.addEventListener('pointermove', function (e) {
            if (!down) { return; }
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 3) { moved = true; }
            rail.scrollLeft = startScroll - dx;
        });

        function release() {
            if (!down) { return; }
            down = false;
            rail.classList.remove('is-dragging');
        }

        rail.addEventListener('pointerup', release);
        rail.addEventListener('pointercancel', release);
        rail.addEventListener('pointerleave', release);

        // A drag that ends on a card should not also follow its link.
        rail.addEventListener('click', function (e) {
            if (moved) {
                e.preventDefault();
                e.stopPropagation();
                moved = false;
            }
        }, true);
    }

    function init() {
        document.querySelectorAll(SELECTOR).forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
