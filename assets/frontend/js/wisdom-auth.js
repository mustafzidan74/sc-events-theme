/*
 * Small helpers for the auth pages. The forms themselves are still submitted
 * by public-scripts.js, so nothing here touches submit handling.
 */
(function () {
    'use strict';

    // Reveal / hide a password field.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-reveal]');
        if (!btn) { return; }

        var field = document.getElementById(btn.getAttribute('data-reveal'));
        if (!field) { return; }

        var showing = field.type === 'text';
        field.type = showing ? 'password' : 'text';
        btn.setAttribute('aria-label', btn.getAttribute(showing ? 'data-label-show' : 'data-label-hide') || '');

        var icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye', showing);
            icon.classList.toggle('fa-eye-slash', !showing);
        }
    });

    // Registration asks for the name in two boxes because that is how people
    // read it, but the endpoint takes one string. Keep the hidden field in
    // step as they type rather than joining at submit time, so it never
    // depends on which submit handler runs first.
    var parts = document.querySelectorAll('[data-name-part]');
    var joined = document.getElementById('name');

    if (parts.length && joined) {
        var sync = function () {
            var out = [];
            parts.forEach(function (p) {
                var v = p.value.trim();
                if (v) { out.push(v); }
            });
            joined.value = out.join(' ');
        };
        parts.forEach(function (p) {
            p.addEventListener('input', sync);
            p.addEventListener('change', sync);
        });
        sync();
    }
}());
