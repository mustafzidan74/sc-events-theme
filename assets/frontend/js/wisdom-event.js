/*
 * Event page helpers. Nothing here touches ticketing — the checkout script
 * still owns every button that starts a purchase.
 */
(function () {
    'use strict';

    // Long descriptions are clamped, but only where clamping actually hides
    // something: a short one keeps its toggle out of the way.
    document.querySelectorAll('[data-clamp]').forEach(function (box) {
        var toggle = box.parentElement.querySelector('[data-clamp-toggle]');
        if (!toggle) { return; }

        if (box.scrollHeight <= box.clientHeight + 8) {
            box.classList.remove('w-prose--clamped');
            toggle.hidden = true;
            return;
        }

        toggle.addEventListener('click', function () {
            var open = !box.classList.toggle('w-prose--clamped');
            toggle.textContent = open ? toggle.dataset.less : toggle.dataset.more;
        });
    });

    // Share: hand off to the OS sheet where there is one, otherwise put the
    // link on the clipboard and say so.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-share]');
        if (!btn) { return; }

        var payload = { title: btn.dataset.title || document.title, url: location.href };

        if (navigator.share) {
            navigator.share(payload).catch(function () { /* dismissed */ });
            return;
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(location.href).then(function () {
                var was = btn.getAttribute('aria-label');
                btn.setAttribute('aria-label', btn.dataset.copied || 'Link copied');
                btn.classList.add('is-copied');
                setTimeout(function () {
                    btn.setAttribute('aria-label', was);
                    btn.classList.remove('is-copied');
                }, 1600);
            });
        }
    });

    // Day tabs on the programme.
    document.querySelectorAll('[data-day-tabs]').forEach(function (tabs) {
        tabs.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-day]');
            if (!tab) { return; }

            tabs.querySelectorAll('[data-day]').forEach(function (t) {
                t.setAttribute('aria-pressed', String(t === tab));
            });

            document.querySelectorAll('[data-day-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.dayPanel !== tab.dataset.day;
            });
        });
    });
}());
