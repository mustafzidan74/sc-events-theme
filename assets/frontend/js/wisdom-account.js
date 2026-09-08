/*
 * Draws the pass QR codes. Everything else on the account page is still
 * handled by the script at the foot of the template.
 */
(function () {
    'use strict';

    function draw() {
        if (typeof QRCode === 'undefined' || typeof QRCode.toCanvas !== 'function') { return; }

        document.querySelectorAll('canvas[data-qr]').forEach(function (canvas) {
            if (canvas.dataset.drawn) { return; }
            canvas.dataset.drawn = '1';

            // High correction: a pass gets scanned off a screen at an angle,
            // through a phone camera, in a crowded foyer.
            QRCode.toCanvas(canvas, canvas.dataset.qr, {
                width: 148,
                margin: 1,
                errorCorrectionLevel: 'H',
                color: { dark: '#000000', light: '#ffffff' }
            }, function (err) {
                if (err) { canvas.remove(); }
            });
        });
    }

    // The library is loaded further down the page, so try now and again once
    // the document has finished parsing.
    draw();
    document.addEventListener('DOMContentLoaded', draw);
    window.addEventListener('load', draw);
}());
