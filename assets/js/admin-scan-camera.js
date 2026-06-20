(function () {
    'use strict';

    var SCANNER_ELEMENT_ID = 'admin-scan-reader';

    function extractPassCode(decodedText) {
        var trimmed = (decodedText || '').trim();
        if (!trimmed) {
            return '';
        }

        try {
            var url = new URL(trimmed, window.location.origin);
            var fromQuery = url.searchParams.get('code');
            if (fromQuery && /^[A-Fa-f0-9]{16}$/.test(fromQuery.trim())) {
                return fromQuery.trim().toUpperCase();
            }
            var pathMatch = url.pathname.match(/\/admin\/scan\/([A-Fa-f0-9]{16})/i);
            if (pathMatch && pathMatch[1]) {
                return pathMatch[1].toUpperCase();
            }
        } catch (e) {
            /* not a URL */
        }

        var queryMatch = trimmed.match(/[?&]code=([A-Fa-f0-9]{16})/i);
        if (queryMatch) {
            return queryMatch[1].toUpperCase();
        }
        if (/^[A-Fa-f0-9]{16}$/.test(trimmed)) {
            return trimmed.toUpperCase();
        }

        return '';
    }

    function showStatus(el, message, isError) {
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.hidden = !message;
        el.classList.toggle('admin-scan-camera-status--error', Boolean(isError && message));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var readerEl = document.getElementById(SCANNER_ELEMENT_ID);
        var startBtn = document.getElementById('admin-scan-start');
        var stopBtn = document.getElementById('admin-scan-stop');
        if (!readerEl || typeof Html5Qrcode !== 'function') {
            return;
        }

        var scanUrl = readerEl.getAttribute('data-scan-url') || '/admin/scan';
        var statusEl = document.getElementById('admin-scan-camera-status');
        var scannerRef = null;
        var starting = false;
        var handled = false;

        function setScannerActive(active) {
            if (startBtn) {
                startBtn.hidden = active;
                startBtn.disabled = starting;
            }
            if (stopBtn) {
                stopBtn.hidden = !active;
            }
        }

        setScannerActive(false);

        function stopScanner() {
            if (!scannerRef) {
                setScannerActive(false);
                return Promise.resolve();
            }
            var current = scannerRef;
            scannerRef = null;
            return current.stop().then(function () {
                return current.clear();
            }).catch(function () {
                /* ignore stop errors */
            }).then(function () {
                setScannerActive(false);
            });
        }

        function goToCheckIn(decodedText) {
            if (handled) {
                return;
            }
            var code = extractPassCode(decodedText);
            if (!code) {
                showStatus(statusEl, 'Could not read a valid pass code from that QR. Try again or enter the code manually.', true);
                return;
            }
            handled = true;
            showStatus(statusEl, 'Pass found — checking in…', false);
            stopScanner().then(function () {
                window.location.href = scanUrl + (scanUrl.indexOf('?') >= 0 ? '&' : '?') + 'code=' + encodeURIComponent(code);
            });
        }

        function startScanner() {
            if (starting || scannerRef) {
                return;
            }
            handled = false;
            showStatus(statusEl, '', false);
            starting = true;
            if (startBtn) {
                startBtn.disabled = true;
            }

            stopScanner().then(function () {
                var html5 = new Html5Qrcode(SCANNER_ELEMENT_ID);
                scannerRef = html5;
                return html5.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                    },
                    function (decodedText) {
                        goToCheckIn(decodedText);
                    },
                    function () {
                        /* ignore per-frame misses */
                    }
                );
            }).then(function () {
                setScannerActive(true);
            }).catch(function (err) {
                showStatus(
                    statusEl,
                    (err && err.message)
                        ? err.message
                        : 'Unable to start the camera. Check browser permissions or enter the pass code manually.',
                    true
                );
                return stopScanner();
            }).then(function () {
                starting = false;
                if (startBtn) {
                    startBtn.disabled = false;
                }
            });
        }

        if (startBtn) {
            startBtn.addEventListener('click', startScanner);
        }
        if (stopBtn) {
            stopBtn.addEventListener('click', function () {
                showStatus(statusEl, '', false);
                stopScanner();
            });
        }

        window.addEventListener('pagehide', function () {
            stopScanner();
        });
    });
})();
