(function () {
    var menuToggle = document.querySelector('.menu-toggle');
    var mainNav = document.querySelector('.main-nav');
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function () {
            mainNav.classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', mainNav.classList.contains('open'));
            menuToggle.textContent = mainNav.classList.contains('open') ? '✕' : '☰';
        });
        mainNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mainNav.classList.remove('open');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.textContent = '☰';
            });
        });
    }

    // Countdown
    var countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        var weddingDate = countdownEl.dataset.date;
        if (weddingDate) {
            function updateCountdown() {
                var now = new Date();
                var wedding = new Date(weddingDate + 'T00:00:00');
                var diff = wedding - now;

                if (diff <= 0) {
                    countdownEl.innerHTML = '<div class="countdown-box"><span class="countdown-num">0</span><span class="countdown-label">Today!</span></div>';
                    return;
                }

                var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                var secs = Math.floor((diff % (1000 * 60)) / 1000);

                countdownEl.innerHTML =
                    '<div class="countdown-box"><span class="countdown-num">' + days + '</span><span class="countdown-label">Days</span></div>' +
                    '<div class="countdown-box"><span class="countdown-num">' + hours + '</span><span class="countdown-label">Hours</span></div>' +
                    '<div class="countdown-box"><span class="countdown-num">' + mins + '</span><span class="countdown-label">Mins</span></div>' +
                    '<div class="countdown-box"><span class="countdown-num">' + secs + '</span><span class="countdown-label">Secs</span></div>';
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
    }
})();
