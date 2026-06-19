/* Dorian — orchestrator: Lenis + GSAP wiring, nav, reveals, boot sequence. */
(function () {
  "use strict";
  var D = window.Dorian || {};

  if (window.gsap) {
    gsap.registerPlugin(window.ScrollTrigger);
    if (window.DrawSVGPlugin) gsap.registerPlugin(window.DrawSVGPlugin);
  }

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- Lenis smooth scroll, synced to GSAP ScrollTrigger ---- */
  var lenis = null;
  if (window.Lenis) {
    lenis = new Lenis({
      duration: 1.15,
      easing: function (t) {
        return Math.min(1, 1.001 - Math.pow(2, -10 * t));
      },
      smoothWheel: !reduced,
    });
    window.__lenis = lenis;
    if (window.gsap && window.ScrollTrigger) {
      lenis.on("scroll", ScrollTrigger.update);
      gsap.ticker.add(function (time) {
        lenis.raf(time * 1000);
      });
      gsap.ticker.lagSmoothing(0);
    }
    lenis.stop(); // held until preloader completes
  }

  function scrollToTarget(target) {
    var el =
      target === "#hero" || target === "body"
        ? 0
        : document.querySelector(target);
    if (lenis) lenis.scrollTo(el, { duration: 1.6 });
    else if (el && el.scrollIntoView) el.scrollIntoView({ behavior: "smooth" });
    else window.scrollTo({ top: 0, behavior: "smooth" });
  }

  /* ---- Anchor links ---- */
  document.querySelectorAll("[data-scroll]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      var href = a.getAttribute("href");
      if (href && href.charAt(0) === "#") {
        e.preventDefault();
        if (window.DorianSound) window.DorianSound.play("tick");
        scrollToTarget(href);
      }
    });
  });

  /* ---- Nav background on scroll ---- */
  var nav = document.getElementById("nav");
  function onScroll() {
    if (nav) nav.classList.toggle("is-scrolled", window.scrollY > 40);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---- Reveal animations ---- */
  function initReveals() {
    if (!window.gsap || reduced) {
      document.querySelectorAll("[data-reveal]").forEach(function (el) {
        el.style.opacity = 1;
        el.style.transform = "none";
      });
      return;
    }
    gsap.utils.toArray("[data-reveal]").forEach(function (el) {
      gsap.to(el, {
        opacity: 1,
        y: 0,
        duration: 0.9,
        ease: "power3.out",
        scrollTrigger: { trigger: el, start: "top 85%" },
      });
    });
  }

  /* ---- Sound toggle ---- */
  var sBtn = document.getElementById("soundToggle");
  var sLabel = document.getElementById("soundLabel");
  if (sBtn) {
    sBtn.addEventListener("click", function () {
      var on = window.DorianSound ? window.DorianSound.toggle() : false;
      sBtn.classList.toggle("is-on", on);
      sBtn.setAttribute("aria-pressed", String(on));
      if (sLabel) sLabel.textContent = on ? "صدا روشن" : "صدا خاموش";
    });
  }

  /* ---- Year ---- */
  var year = document.getElementById("year");
  if (year) year.textContent = window.Dorian.toFa(new Date().getFullYear());

  /* ---- Boot sequence ---- */
  function boot() {
    if (D.initCursor) D.initCursor();

    var start = D.runPreloader ? D.runPreloader() : Promise.resolve();
    start.then(function () {
      if (lenis) lenis.start();
      document.body.classList.add("is-ready");

      if (D.initHero) D.initHero();
      if (D.initElevator) D.initElevator();
      if (D.initCharacters) D.initCharacters();
      if (D.initGiftcards) D.initGiftcards();
      if (D.initReserve) D.initReserve();
      initReveals();

      if (window.ScrollTrigger) {
        ScrollTrigger.refresh();
        // Re-measure once fonts/images settle (pin accuracy).
        window.addEventListener("load", function () {
          ScrollTrigger.refresh();
        });
        setTimeout(function () {
          ScrollTrigger.refresh();
        }, 600);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
