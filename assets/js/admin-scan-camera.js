(function () {
    'use strict';

    function extractPassCode(text) {
        var raw = (text || '').trim();
        if (!raw) {
            return '';
        }
        try {
            var url = new URL(raw);
            var code = url.searchParams.get('code');
            if (code && /^[A-Fa-f0-9]{16}$/.test(code.trim())) {
                return code.trim().toUpperCase();
            }
        } catch (e) {
            /* not a full URL */
        }
        var match = raw.match(/[?&]code=([A-Fa-f0-9]{16})/i);
        if (match) {
            return match[1].toUpperCase();
        }
        if (/^[A-Fa-f0-9]{16}$/.test(raw)) {
            return raw.toUpperCase();
        }
        return '';
    }

    function showStatus(el, message) {
        if (!el) {
            return;
        }
        el.textContent = message;
        el.hidden = !message;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var readerEl = document.getElementById('admin-scan-reader');
        if (!readerEl || typeof Html5QrcodeScanner !== 'function') {
            return;
        }

        var scanUrl = readerEl.getAttribute('data-scan-url') || '/admin/scan';
        var statusEl = document.getElementById('admin-scan-camera-status');
        var handled = false;
        var scanner = null;

        function onScanSuccess(decodedText) {
            if (handled) {
                return;
            }
            var code = extractPassCode(decodedText);
            if (!code) {
                showStatus(statusEl, 'QR read but no valid pass code found. Try again or enter the code manually.');
                return;
            }
            handled = true;
            showStatus(statusEl, 'Pass found — checking in…');
            if (scanner && typeof scanner.clear === 'function') {
                scanner.clear().catch(function () {});
            }
            window.location.href = scanUrl + (scanUrl.indexOf('?') >= 0 ? '&' : '?') + 'code=' + encodeURIComponent(code);
        }

        function onScanFailure() {
            /* ignore per-frame misses */
        }

        scanner = new Html5QrcodeScanner(
            'admin-scan-reader',
            {
                fps: 10,
                qrbox: function (viewfinderWidth, viewfinderHeight) {
                    var size = Math.min(viewfinderWidth, viewfinderHeight, 280);
                    return { width: size, height: size };
                },
                rememberLastUsedCamera: true,
            },
            false
        );

        try {
            scanner.render(onScanSuccess, onScanFailure);
        } catch (err) {
            showStatus(statusEl, 'Could not start the camera. Allow camera access or enter the code manually below.');
        }
    });
})();
