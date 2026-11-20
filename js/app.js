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

  /*
   * Voice search: the mic button fills the search box from speech and submits
   * the search. The button stays hidden in browsers without speech recognition.
   */
  var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (Recognition) {
    document.querySelectorAll('[data-voice-search]').forEach(function (button) {
      var form = button.form;
      var input = form.querySelector('input[name="q"]');
      button.hidden = false;

      button.addEventListener('click', function () {
        var recognition = new Recognition();
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        function stopListening() {
          button.classList.remove('is-listening');
          button.setAttribute('aria-pressed', 'false');
        }

        recognition.onresult = function (event) {
          input.value = event.results[0][0].transcript;
          form.submit();
        };
        recognition.onend = stopListening;
        recognition.onerror = stopListening;

        button.classList.add('is-listening');
        button.setAttribute('aria-pressed', 'true');
        recognition.start();
      });
    });
  }

  /* Selects marked data-autosubmit send their form as soon as they change (cart quantities). */
  document.querySelectorAll('[data-autosubmit]').forEach(function (field) {
    field.addEventListener('change', function () {
      field.form.submit();
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

  /* Quantity stepper: − and + change the number, kept within the field's min and max. */
  document.querySelectorAll('[data-qty]').forEach(function (stepper) {
    var input = stepper.querySelector('input');
    var min = parseInt(input.min, 10) || 1;
    var max = parseInt(input.max, 10) || min;

    function set(value) {
      input.value = Math.max(min, Math.min(max, value || min));
    }

    stepper.querySelectorAll('button').forEach(function (button) { button.hidden = false; });
    stepper.querySelector('[data-qty-dec]').addEventListener('click', function () { set(parseInt(input.value, 10) - 1); });
    stepper.querySelector('[data-qty-inc]').addEventListener('click', function () { set(parseInt(input.value, 10) + 1); });
    input.addEventListener('change', function () { set(parseInt(input.value, 10)); });
  });

  /* Product photo zoom: with a mouse, the photo doubles in size and follows the pointer. */
  var zoom = document.querySelector('[data-zoom]');
  if (zoom && window.matchMedia('(hover: hover)').matches) {
    var zoomImage = zoom.querySelector('img');
    zoom.addEventListener('mouseenter', function () { zoom.classList.add('is-zoomed'); });
    zoom.addEventListener('mouseleave', function () {
      zoom.classList.remove('is-zoomed');
      zoomImage.style.transformOrigin = '';
    });
    zoom.addEventListener('mousemove', function (event) {
      var box = zoom.getBoundingClientRect();
      var x = ((event.clientX - box.left) / box.width) * 100;
      var y = ((event.clientY - box.top) / box.height) * 100;
      zoomImage.style.transformOrigin = x + '% ' + y + '%';
    });
  }
})();
