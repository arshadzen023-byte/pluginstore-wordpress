
(function () {
  'use strict';

  // Keep the existing Escape-key accessibility feature.
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.querySelectorAll('.menu-open').forEach(function (node) {
        node.classList.remove('menu-open');
      });
    }
  });

  function startArkCelebration() {
    var canvas = document.getElementById('ark-celebration-canvas');

    if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var effect = canvas.dataset.effect || 'mixed';
    var density = Math.max(10, Math.min(80, Number(canvas.dataset.density) || 35));
    var speedSetting = canvas.dataset.speed || 'normal';

    var speed = speedSetting === 'slow' ? 0.55 :
                speedSetting === 'fast' ? 1.5 : 1;

    var particles = [];
    var width = 0;
    var height = 0;
    var lastSpawn = 0;
    var stopped = false;
    var colors = ['#ffb000', '#2874f0', '#ff477e', '#25b879', '#a56eff', '#ff6b35'];

    function resize() {
      var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
      width = window.innerWidth;
      height = window.innerHeight;
      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function randomColor() {
      return colors[Math.floor(Math.random() * colors.length)];
    }

    function addFirework(x, y) {
      var count = Math.max(12, Math.round(density * 0.65));

      for (var i = 0; i < count; i++) {
        var angle = (Math.PI * 2 * i) / count + Math.random() * 0.12;
        var velocity = 1.1 + Math.random() * 2.5;

        particles.push({
          type: 'firework',
          x: x,
          y: y,
          vx: Math.cos(angle) * velocity,
          vy: Math.sin(angle) * velocity,
          life: 35 + Math.random() * 30,
          maxLife: 65,
          size: 1.2 + Math.random() * 1.8,
          color: randomColor()
        });
      }
    }

    function addConfetti() {
      particles.push({
        type: 'confetti',
        x: Math.random() * width,
        y: -10,
        vx: (Math.random() - 0.5) * 1.3,
        vy: 1 + Math.random() * 2,
        life: 160 + Math.random() * 80,
        maxLife: 240,
        size: 3 + Math.random() * 4,
        rotation: Math.random() * 6,
        color: randomColor()
      });
    }

    function addSparkle() {
      particles.push({
        type: 'sparkle',
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.3,
        vy: -0.15 - Math.random() * 0.35,
        life: 35 + Math.random() * 60,
        maxLife: 95,
        size: 1 + Math.random() * 2.4,
        color: Math.random() > 0.35 ? '#ffca55' : '#ffffff'
      });
    }

    function spawn(now) {
      var interval = (speedSetting === 'fast' ? 500 : speedSetting === 'slow' ? 1500 : 900);

      if (now - lastSpawn < interval / speed) return;
      lastSpawn = now;

      if (effect === 'fireworks' || effect === 'mixed') {
        addFirework(
          width * (0.12 + Math.random() * 0.76),
          height * (0.12 + Math.random() * 0.48)
        );
      }

      if (effect === 'confetti' || effect === 'mixed') {
        var confettiCount = effect === 'mixed' ? 4 : 8;
        for (var i = 0; i < confettiCount; i++) addConfetti();
      }

      if (effect === 'sparkles' || effect === 'mixed') {
        var sparkleCount = effect === 'mixed' ? 5 : 10;
        for (var j = 0; j < sparkleCount; j++) addSparkle();
      }
    }

    function drawParticle(p) {
      var alpha = Math.max(0, Math.min(1, p.life / p.maxLife));
      ctx.globalAlpha = alpha;
      ctx.fillStyle = p.color;

      if (p.type === 'confetti') {
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rotation || 0);
        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 1.7);
        ctx.restore();
      } else if (p.type === 'sparkle') {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size * (0.5 + alpha * 0.5), 0, Math.PI * 2);
        ctx.fill();
      } else {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    function animate(now) {
      if (stopped) return;

      ctx.clearRect(0, 0, width, height);
      spawn(now);

      // Cap particle count to protect performance on phones and laptops.
      var maxParticles = Math.round(density * 7);
      if (particles.length > maxParticles) {
        particles.splice(0, particles.length - maxParticles);
      }

      for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i];

        p.x += p.vx * speed;
        p.y += p.vy * speed;

        if (p.type === 'firework') {
          p.vy += 0.025;
        } else if (p.type === 'confetti') {
          p.vy += 0.006;
          p.rotation = (p.rotation || 0) + 0.035 * speed;
        }

        p.life -= 1;
        drawParticle(p);

        if (
          p.life <= 0 ||
          p.y > height + 25 ||
          p.x < -25 ||
          p.x > width + 25
        ) {
          particles.splice(i, 1);
        }
      }

      ctx.globalAlpha = 1;
      window.requestAnimationFrame(animate);
    }

    resize();
    window.addEventListener('resize', resize, { passive: true });

    // Pause the effect when the browser tab is hidden.
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        ctx.clearRect(0, 0, width, height);
      }
    });

    window.requestAnimationFrame(animate);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startArkCelebration);
  } else {
    startArkCelebration();
  }
})();


/* =========================================
   ARK PRODUCT UI - IMAGE ACTIONS + SCROLL SEARCH
   ========================================= */
(function () {
  'use strict';

  function initArkProductUI() {
    if (!document.body.classList.contains('single-product')) return;

    var topbar = document.querySelector('.ark-product-topbar');
    var stickyBar = document.querySelector('.ark-product-sticky-bar');
    var lastScrollY = window.scrollY || 0;
    var searchTicking = false;

    /* Search appears when user scrolls up.
       It is hidden on initial page load. */
    function updateSearchVisibility() {
      if (!topbar) return;

      var currentY = window.scrollY || 0;
      var movingUp = currentY < lastScrollY - 3;
      var movingDown = currentY > lastScrollY + 3;

      if (currentY > 100 && movingUp) {
        topbar.classList.add('is-visible');
      } else if (movingDown || currentY <= 40) {
        topbar.classList.remove('is-visible');
      }

      lastScrollY = currentY;
      searchTicking = false;
    }

    window.addEventListener('scroll', function () {
      if (!searchTicking) {
        window.requestAnimationFrame(updateSearchVisibility);
        searchTicking = true;
      }
    }, { passive: true });

    /* Locate the product image gallery */
    function getGallery() {
      return document.querySelector(
        'body.single-product .woocommerce div.product div.images'
      );
    }

    /* Create action buttons on the image, only once */
    function getImageActionContainer() {
      var gallery = getGallery();
      if (!gallery) return null;

      var container = gallery.querySelector('.ark-product-image-actions');

      if (!container) {
        container = document.createElement('div');
        container.className = 'ark-product-image-actions';
        container.setAttribute('aria-label', 'Product actions');
        gallery.appendChild(container);
      }

      return container;
    }

    /* Move the ACTUAL wishlist plugin element, preserving its handlers */
    function moveWishlistToImage() {
      var container = getImageActionContainer();
      if (!container) return;

      if (container.querySelector(
        '.yith-wcwl-add-to-wishlist, .tinv-wraper, .woosw-btn, .wd-wishlist-btn, a.add_to_wishlist'
      )) {
        return;
      }

      var candidates = [
        '.yith-wcwl-add-to-wishlist',
        '.tinv-wraper',
        '.woosw-btn',
        '.wd-wishlist-btn',
        'a.add_to_wishlist'
      ];

      var wishlist = null;

      for (var i = 0; i < candidates.length; i++) {
        wishlist = document.querySelector(
          'body.single-product .summary ' + candidates[i]
        );

        if (wishlist) break;
      }

      if (!wishlist) return;

      /* Prefer moving the plugin's wrapper instead of just its link */
      if (
        wishlist.matches('a.add_to_wishlist') &&
        wishlist.parentElement &&
        wishlist.parentElement.classList.contains('yith-wcwl-add-to-wishlist')
      ) {
        wishlist = wishlist.parentElement;
      }

      container.appendChild(wishlist);
      wishlist.classList.add('ark-product-wishlist');
    }

    /* Add a share button next to the wishlist */
    function addShareButton() {
      var container = getImageActionContainer();
      if (!container || container.querySelector('.ark-product-share')) return;

      var share = document.createElement('button');
      share.type = 'button';
      share.className = 'ark-product-share';
      share.setAttribute('aria-label', 'Share product');
      share.innerHTML =
        '<span class="material-symbols-rounded" aria-hidden="true">share</span>';

      share.addEventListener('click', async function () {
        var title = document.querySelector('.product_title');
        var data = {
          title: title ? title.textContent.trim() : document.title,
          text: 'Check out this product',
          url: window.location.href
        };

        try {
          if (navigator.share) {
            await navigator.share(data);
          } else if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(window.location.href);
            alert('Product link copied. You can now share it.');
          } else {
            window.prompt('Copy this product link:', window.location.href);
          }
        } catch (error) {
          // Sharing cancelled by the customer; no error message needed.
        }
      });

      container.insertBefore(share, container.firstChild);
    }

    /* Sticky bar buttons use the original WooCommerce form */
    function submitProductForm(buyNow) {
      var form = document.querySelector(
        'body.single-product form.cart'
      );

      if (!form) {
        alert('Purchase form is not available for this product.');
        return;
      }

      var originalButton = form.querySelector(
        '.single_add_to_cart_button'
      );

      if (!originalButton) {
        alert('Add to Cart is not available for this product.');
        return;
      }

      var flag = form.querySelector('.ark-buy-now-flag');

      if (!flag) {
        flag = document.createElement('input');
        flag.type = 'hidden';
        flag.name = 'ark_buy_now';
        flag.className = 'ark-buy-now-flag';
        form.appendChild(flag);
      }

      flag.value = buyNow ? '1' : '0';

      /* requestSubmit keeps native validation active where supported */
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(originalButton);
      } else {
        originalButton.click();
      }
    }

    if (stickyBar) {
      var cartButton = stickyBar.querySelector('.ark-product-sticky-cart');
      var buyButton = stickyBar.querySelector('.ark-product-sticky-buy');

      if (cartButton) {
        cartButton.addEventListener('click', function () {
          submitProductForm(false);
        });
      }

      if (buyButton) {
        buyButton.addEventListener('click', function () {
          submitProductForm(true);
        });
      }
    }

    function refreshImageActions() {
      addShareButton();
      moveWishlistToImage();
    }

    refreshImageActions();

    /* Some wishlist plugins render after page load */
    if (window.MutationObserver && document.body) {
      var observer = new MutationObserver(function () {
        refreshImageActions();
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArkProductUI);
  } else {
    initArkProductUI();
  }
})();

/* ARK Product Image Actions: share + existing wishlist plugin */
(function () {
  'use strict';

  function setupProductImageActions() {
    if (!document.body.classList.contains('single-product')) return;

    var gallery = document.querySelector(
      '.single-product .woocommerce div.product div.images'
    );

    if (!gallery) return;

    var actions = gallery.querySelector('.ark-product-image-actions');

    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'ark-product-image-actions';
      actions.setAttribute('aria-label', 'Product actions');
      gallery.appendChild(actions);
    }

    if (!actions.querySelector('.ark-product-share')) {
      var share = document.createElement('button');
      share.type = 'button';
      share.className = 'ark-product-share';
      share.setAttribute('aria-label', 'Share product');
      share.innerHTML =
        '<span class="material-symbols-rounded" aria-hidden="true">share</span>';

      share.addEventListener('click', async function () {
        var title = document.querySelector('.product_title');
        var data = {
          title: title ? title.textContent.trim() : document.title,
          text: 'Check out this product',
          url: window.location.href
        };

        try {
          if (navigator.share) {
            await navigator.share(data);
          } else if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(data.url);
            alert('Product link copied.');
          } else {
            window.prompt('Copy product link:', data.url);
          }
        } catch (error) {
          // Sharing was cancelled by the user.
        }
      });

      actions.appendChild(share);
    }

    function findWishlist() {
      var selectors = [
        '.yith-wcwl-add-to-wishlist',
        '.tinv-wraper',
        '.woosw-btn',
        '.wd-wishlist-btn',
        '.add_to_wishlist',
        'a[href*="add_to_wishlist"]'
      ];

      for (var i = 0; i < selectors.length; i++) {
        var element = document.querySelector(
          '.single-product .summary ' + selectors[i]
        );

        if (element && !actions.contains(element)) {
          return element;
        }
      }

      /* Fallback: locate the existing wishlist link by its visible label */
      var links = document.querySelectorAll(
        '.single-product .summary a, .single-product .summary button'
      );

      for (var j = 0; j < links.length; j++) {
        var label = (links[j].innerText || links[j].getAttribute('aria-label') || '')
          .trim()
          .toLowerCase();

        if (
          label.includes('add to wishlist') ||
          label.includes('add to watchlist') ||
          label === 'wishlist'
        ) {
          return links[j];
        }
      }

      return null;
    }

    function moveWishlist() {
      var wishlist = findWishlist();
      if (!wishlist) return;

      /* Move the plugin wrapper where possible, preserving its own events */
      if (
        wishlist.matches('a, button') &&
        wishlist.parentElement &&
        (
          wishlist.parentElement.classList.contains('yith-wcwl-add-to-wishlist') ||
          wishlist.parentElement.classList.contains('tinv-wraper')
        )
      ) {
        wishlist = wishlist.parentElement;
      }

      wishlist.classList.add('ark-product-wishlist');
      actions.appendChild(wishlist);
    }

    moveWishlist();

    /* Allow wishlist plugins that render later to finish first */
    if (!gallery.dataset.arkWishlistObserver) {
      gallery.dataset.arkWishlistObserver = '1';

      var observer = new MutationObserver(function () {
        moveWishlist();
      });

      observer.observe(document.querySelector('.single-product .summary') || gallery, {
        childList: true,
        subtree: true
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupProductImageActions);
  } else {
    setupProductImageActions();
  }
})();

