/**
 * Saves the on-screen guest access card as PNG (pixel match to the rendered card).
 */
(function () {
    'use strict';

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = resolve;
            s.onerror = function () {
                reject(new Error('load-failed'));
            };
            document.head.appendChild(s);
        });
    }

    function ensureHtml2Canvas() {
        if (window.html2canvas) {
            return Promise.resolve();
        }
        return loadScript(
            'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'
        );
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-access-card-download]');
        if (!btn) {
            return;
        }
        e.preventDefault();

        var el = document.querySelector('.guest-access-card');
        if (!el) {
            return;
        }

        var name = el.getAttribute('data-download-name') || 'access-pass.png';
        var label = btn.getAttribute('aria-label') || 'Download pass';
        var prevText = btn.textContent;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');

        ensureHtml2Canvas()
            .then(function () {
                var scale = Math.min(2, window.devicePixelRatio || 1);
                return window.html2canvas(el, {
                    scale: scale,
                    useCORS: true,
                    allowTaint: false,
                    backgroundColor: null,
                    logging: false,
                });
            })
            .then(function (canvas) {
                var a = document.createElement('a');
                a.download = name;
                a.href = canvas.toDataURL('image/png');
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .catch(function () {
                window.alert(
                    'Could not save the image. Try again, use another browser, or take a screenshot of your pass.'
                );
            })
            .finally(function () {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                if (prevText) {
                    btn.textContent = prevText;
                }
            });
    });
})();
