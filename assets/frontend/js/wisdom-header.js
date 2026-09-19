/**
 * The header steps aside while the reader scrolls down and comes back the
 * moment they scroll up. It stays put near the top of the page, while the
 * mobile menu is open, and while something in it has keyboard focus.
 */
(function () {
    'use strict';
    var header = document.getElementById('sc-header');
    if (!header) { return; }

    var last = window.scrollY;
    var ticking = false;
    var SHOW_NEAR_TOP = 120;
    var MOVE = 8; // ignore tiny wobbles, such as a phone's address bar settling

    function menuOpen() {
        var drawer = document.getElementById('sc-mobile-sidebar');
        return !!(drawer && drawer.classList.contains('active'));
    }

    function update() {
        ticking = false;
        var y = window.scrollY;
        var delta = y - last;
        if (Math.abs(delta) < MOVE) { return; }
        var hide = delta > 0 && y > SHOW_NEAR_TOP && !menuOpen() && !header.contains(document.activeElement);
        header.classList.toggle('w-header--hidden', hide);
        last = y;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }, { passive: true });

    header.addEventListener('focusin', function () { header.classList.remove('w-header--hidden'); });
})();
