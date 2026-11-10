
(function () {
  'use strict';

  function init(root) {
    var track = root.querySelector('.ark-promo-track');
    var prev = root.querySelector('.ark-promo-prev');
    var next = root.querySelector('.ark-promo-next');

    if (!track || root.dataset.arkPromoInit === '1') return;
    root.dataset.arkPromoInit = '1';

    function step() {
      var card = track.querySelector('.ark-promo-card');
      return card ? card.getBoundingClientRect().width + 12 : 170;
    }

    if (prev) {
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -step() * 2, behavior: 'smooth' });
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        track.scrollBy({ left: step() * 2, behavior: 'smooth' });
      });
    }

    var speed = parseInt(root.getAttribute('data-speed') || '0', 10);
    var timer = null;
    var paused = false;

    function stop() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();

      if (!speed || speed < 1000 || track.scrollWidth <= track.clientWidth) {
        return;
      }

      timer = window.setInterval(function () {
        if (paused || document.hidden) return;

        var max = track.scrollWidth - track.clientWidth;

        if (track.scrollLeft >= max - 4) {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          track.scrollBy({ left: step(), behavior: 'smooth' });
        }
      }, speed);
    }

    root.addEventListener('mouseenter', function () { paused = true; });
    root.addEventListener('mouseleave', function () { paused = false; });
    root.addEventListener('focusin', function () { paused = true; });
    root.addEventListener('focusout', function () { paused = false; });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop();
      else start();
    });

    window.addEventListener('resize', start, { passive: true });
    start();
  }

  function run() {
    var sliders = document.querySelectorAll('.ark-promo-slider');

    if (!sliders.length) return;

    // Put the ad slider immediately below ARK's product search/header bar.
    var anchor =
      document.querySelector('.ark-product-topbar') ||
      document.querySelector('.ark-site-header') ||
      document.querySelector('header');

    if (anchor && anchor.parentNode) {
      sliders.forEach(function (slider) {
        anchor.insertAdjacentElement('afterend', slider);
        anchor = slider;
      });
    }

    sliders.forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
