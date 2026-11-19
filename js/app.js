/**
 * Fundi Electric storefront behaviour. Plain JavaScript, no libraries.
 *
 * Each feature looks for its data- attribute and does nothing on pages that
 * don't have it, so the one file is safe to load everywhere. The pages all
 * work without it; this only adds conveniences.
 */
(function () {
  'use strict';

  /*
   * Mobile menu: show or hide the panel and keep the button in sync. The
   * header gets "is-open" so the see-through home header can turn solid.
   */
  var menuToggle = document.querySelector('[data-menu-toggle]');
  var mobileMenu = menuToggle && document.getElementById(menuToggle.getAttribute('aria-controls'));
  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', function () {
      var opening = mobileMenu.hidden;
      mobileMenu.hidden = !opening;
      menuToggle.closest('.site-header').classList.toggle('is-open', opening);
      menuToggle.setAttribute('aria-expanded', String(opening));
      menuToggle.setAttribute('aria-label', opening ? 'Close menu' : 'Open menu');
      menuToggle.querySelector('use').setAttribute('href', opening ? '#i-close' : '#i-menu');
    });
  }

  /* Account menu: a <details> dropdown, closed by clicking elsewhere or pressing Escape. */
  var accountMenu = document.querySelector('[data-account-menu]');
  if (accountMenu) {
    document.addEventListener('click', function (event) {
      if (accountMenu.open && !accountMenu.contains(event.target)) {
        accountMenu.open = false;
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && accountMenu.open) {
        accountMenu.open = false;
        accountMenu.querySelector('summary').focus();
      }
    });
  }

  /* Ask before actions such as logging out: data-confirm="Question?" */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  /* Flash messages: the close button removes the message. */
  document.querySelectorAll('[data-dismiss]').forEach(function (button) {
    button.addEventListener('click', function () {
      button.parentElement.remove();
    });
  });

  /*
   * Product carousel: the track is a horizontally scrolling row, so it works
   * by swipe or keyboard on its own. The arrow buttons step one card at a time
   * and hide at either end.
   */
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-carousel]').forEach(function (carousel) {
    var track = carousel.querySelector('[data-carousel-track]');
    var prev = carousel.querySelector('[data-carousel-prev]');
    var next = carousel.querySelector('[data-carousel-next]');
    if (!track || !prev || !next) { return; }

    function cardWidth() {
      var first = track.firstElementChild;
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      return first ? first.getBoundingClientRect().width + gap : track.clientWidth;
    }

    function scrollByCards(direction) {
      track.scrollBy({ left: direction * cardWidth(), behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    function update() {
      var end = track.scrollWidth - track.clientWidth - 2;
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft >= end;
    }

    prev.hidden = false;
    next.hidden = false;
    prev.addEventListener('click', function () { scrollByCards(-1); });
    next.addEventListener('click', function () { scrollByCards(1); });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });
})();
