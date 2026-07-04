/* Dorian — internal pages (light, no dependencies).
   Sticky-header compaction, mobile menu, reveal-on-scroll, custom cursor, form UX. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;

  /* ---------- header: compact on scroll ---------- */
  var head = doc.getElementById('head');
  if (head && !head.classList.contains('head--static')) {
    var onScroll = function () {
      head.classList.toggle('compact', window.scrollY > 40);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- mobile menu ---------- */
  var burger = doc.getElementById('burger'), nav = doc.getElementById('nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      doc.body.style.overflow = open ? 'hidden' : '';
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        nav.classList.remove('open'); burger.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false'); doc.body.style.overflow = '';
      }
    });
  }

  /* ---------- reveal on scroll ---------- */
  if (doc.body.classList.contains('dorian-anim') &&
      'IntersectionObserver' in window &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    var sel = '.post-card, .value-card, .about__visual, .contact__card, .contact__formwrap, .single__hero, .pagehero__inner';
    doc.querySelectorAll(sel).forEach(function (el) { io.observe(el); });
  }

  /* ---------- custom cursor (fine pointers only) ---------- */
  if (window.matchMedia('(pointer:fine)').matches &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var dot = doc.getElementById('curDot'), ring = doc.getElementById('curRing');
    if (dot && ring) {
      root.classList.add('has-cursor');
      var rx = 0, ry = 0, tx = 0, ty = 0;
      window.addEventListener('mousemove', function (e) {
        tx = e.clientX; ty = e.clientY;
        dot.style.transform = 'translate(' + tx + 'px,' + ty + 'px) translate(-50%,-50%)';
      });
      (function loop() {
        rx += (tx - rx) * 0.18; ry += (ty - ry) * 0.18;
        ring.style.transform = 'translate(' + rx + 'px,' + ry + 'px) translate(-50%,-50%)';
        requestAnimationFrame(loop);
      })();
      doc.addEventListener('mouseover', function (e) {
        if (e.target.closest('a, button, .btn, input, textarea')) ring.classList.add('hover');
      });
      doc.addEventListener('mouseout', function (e) {
        if (e.target.closest('a, button, .btn, input, textarea')) ring.classList.remove('hover');
      });
      window.addEventListener('mousedown', function () { ring.classList.add('down'); });
      window.addEventListener('mouseup', function () { ring.classList.remove('down'); });
    }
  }

  /* ---------- contact form: guard double-submit ---------- */
  var cf = doc.querySelector('.contact-form');
  if (cf) {
    cf.addEventListener('submit', function () {
      var b = cf.querySelector('button[type=submit]');
      if (b) { b.disabled = true; b.textContent = 'در حال ارسال…'; }
    });
    // scroll to the status note if we just returned from a submit
    var note = doc.querySelector('.contact__note');
    if (note) note.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
})();
