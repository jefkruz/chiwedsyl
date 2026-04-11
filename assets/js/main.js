(function () {
    var pageLoader = document.getElementById('page-loader');
    if (pageLoader) {
        setTimeout(function () {
            pageLoader.classList.add('is-hidden');
            setTimeout(function () {
                if (pageLoader && pageLoader.parentNode) pageLoader.parentNode.removeChild(pageLoader);
            }, 320);
        }, 1000);
    }

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
                    '<span class="countdown-sep" aria-hidden="true"></span>' +
                    '<div class="countdown-box"><span class="countdown-num">' + hours + '</span><span class="countdown-label">Hours</span></div>' +
                    '<span class="countdown-sep" aria-hidden="true"></span>' +
                    '<div class="countdown-box"><span class="countdown-num">' + mins + '</span><span class="countdown-label">Mins</span></div>' +
                    '<span class="countdown-sep" aria-hidden="true"></span>' +
                    '<div class="countdown-box"><span class="countdown-num">' + secs + '</span><span class="countdown-label">Secs</span></div>';
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
    }

    function carouselStepAmount(carousel) {
        if (carousel.classList.contains('home-wishes-carousel')) {
            return carousel.clientWidth;
        }
        var track = carousel.querySelector('.home-carousel-track');
        var item = track && track.children.length ? track.children[0] : null;
        if (!item || !track) {
            return Math.max(220, Math.floor(carousel.clientWidth * 0.85));
        }
        var gs = window.getComputedStyle(track);
        var gap = parseFloat(gs.gap || gs.columnGap || '0') || 0;
        return item.getBoundingClientRect().width + gap;
    }

    // Home page carousels
    document.querySelectorAll('.carousel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var carousel = targetId ? document.getElementById(targetId) : null;
            if (!carousel) return;
            var amount = carouselStepAmount(carousel);
            var delta = btn.classList.contains('prev') ? -amount : amount;
            carousel.scrollBy({ left: delta, behavior: 'smooth' });
        });
    });

    // Home page carousels autoplay (pause on interaction); well wishes are manual (arrows only)
    document.querySelectorAll('.home-carousel').forEach(function (carousel) {
        if (carousel.classList.contains('home-wishes-carousel')) return;
        var timer = null;
        var isPaused = false;
        var step = function () {
            var amount = carouselStepAmount(carousel);
            var nearEnd = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 8;
            if (nearEnd) {
                carousel.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                carousel.scrollBy({ left: amount, behavior: 'smooth' });
            }
        };

        var start = function () {
            if (timer || isPaused) return;
            timer = setInterval(step, 3500);
        };

        var stop = function () {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        };

        var pause = function () {
            isPaused = true;
            stop();
        };

        var resume = function () {
            isPaused = false;
            start();
        };

        carousel.addEventListener('mouseenter', pause);
        carousel.addEventListener('mouseleave', resume);
        carousel.addEventListener('focusin', pause);
        carousel.addEventListener('focusout', resume);
        carousel.addEventListener('touchstart', pause, { passive: true });
        carousel.addEventListener('touchend', resume, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else if (!isPaused) start();
        });

        start();
    });
})();
