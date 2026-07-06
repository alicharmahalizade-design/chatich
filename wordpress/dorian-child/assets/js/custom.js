/* Dorian — custom layer over v1: scrub video, gift flip, elevator + sound.
   Loaded after app.js; reuses the GSAP/ScrollTrigger/Lenis already set up. */
(function () {
  "use strict";

  /* ---------------- synthesised sound engine ---------------- */
  var ctx = null, master = null, sndOn = false;
  function ensure() {
    if (ctx) return ctx;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    ctx = new AC();
    master = ctx.createGain();
    master.gain.value = 0.0001;
    master.connect(ctx.destination);
    return ctx;
  }
  function blip(start, freq, dur, peak, type) {
    var osc = ctx.createOscillator(), g = ctx.createGain();
    osc.type = type; osc.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, start);
    g.gain.linearRampToValueAtTime(peak, start + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
    osc.connect(g); g.connect(master);
    osc.start(start); osc.stop(start + dur + 0.05);
  }
  var Sound = {
    get enabled() { return sndOn; },
    enable: function () {
      if (!ensure()) return;
      if (ctx.state === "suspended") ctx.resume();
      sndOn = true;
      master.gain.cancelScheduledValues(ctx.currentTime);
      master.gain.linearRampToValueAtTime(0.5, ctx.currentTime + 0.4);
    },
    disable: function () {
      sndOn = false;
      if (!ctx) return;
      master.gain.cancelScheduledValues(ctx.currentTime);
      master.gain.linearRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
    },
    toggle: function () { if (sndOn) this.disable(); else this.enable(); return sndOn; },
    play: function (name) {
      if (!sndOn || !ctx) return;
      var now = ctx.currentTime;
      if (name === "tick") blip(now, 1200, 0.05, 0.12, "triangle");
      else if (name === "ding") { blip(now, 880, 0.9, 0.22, "sine"); blip(now + 0.18, 1320, 1.1, 0.2, "sine"); }
    },
  };
  window.DorianSound = Sound;

  /* ---------------- HERO · handwriting tagline (pen reveal) ---------------- */
  function initHeroTagline() {
    var ink = document.querySelector(".hero__tagline-ink");
    var pen = document.querySelector(".hero__pen");
    if (!ink || !window.gsap) return;                       // no GSAP → leave full text visible
    if (matchMedia("(prefers-reduced-motion:reduce)").matches) return;

    // clip immediately (hidden behind the preloader, so no flash)
    gsap.set(ink, { clipPath: "inset(0 100% 0 0)" });
    var played = false;
    function run() {
      if (played) return; played = true;
      var tl = gsap.timeline();
      tl.to(ink, { clipPath: "inset(0 0% 0 0)", duration: 2.4, ease: "none" });
      if (pen) {
        tl.fromTo(pen, { left: "1%", opacity: 1 }, { left: "100%", duration: 2.4, ease: "none" }, "<");
        tl.to(pen, { opacity: 0, duration: 0.4 }, ">-0.15");
      }
    }
    // start once the preloader has parted (app.js adds html.loaded), with a safety net
    var root = document.documentElement;
    if (root.classList.contains("loaded")) { setTimeout(run, 400); return; }
    var obs = new MutationObserver(function () {
      if (root.classList.contains("loaded")) { obs.disconnect(); setTimeout(run, 400); }
    });
    obs.observe(root, { attributes: true, attributeFilter: ["class"] });
    setTimeout(function () { obs.disconnect(); run(); }, 6000);
  }

  /* ---------------- STORY · scroll-scrubbed video ---------------- */
  function initStoryVideo() {
    var section = document.getElementById("story");
    var video = document.getElementById("servicesVideo");
    if (!section || !video || !window.gsap || !window.ScrollTrigger) return;
    var reduced = matchMedia("(prefers-reduced-motion:reduce)").matches;
    var mobile = matchMedia("(max-width:768px)").matches;
    if (reduced || mobile) {
      ScrollTrigger.create({
        trigger: section, start: "top 65%",
        onEnter: function () { video.play().catch(function () {}); },
        onLeaveBack: function () { try { video.pause(); video.currentTime = 0; } catch (e) {} },
      });
      return;
    }
    try { video.pause(); } catch (e) {}
    var lastT = -1;
    function dur() { return (video.duration && isFinite(video.duration)) ? video.duration : 4.1; }
    // Create the pin SYNCHRONOUSLY so its spacer exists before the first refresh.
    // A function-based end + invalidateOnRefresh lets the length self-correct once
    // the real duration is known — without shoving the page around after the fact.
    ScrollTrigger.create({
      trigger: section, start: "top top",
      end: function () { return "+=" + Math.round(dur() * 340); },
      pin: true, scrub: 0.35, invalidateOnRefresh: true,
      refreshPriority: 3,   // topmost pin: must compute its spacer before floors/team
      onUpdate: function (self) {
        if (video.readyState < 1) return;
        var t = self.progress * (dur() - 0.05);
        if (isFinite(t) && t >= 0 && Math.abs(t - lastT) > 0.012) {
          lastT = t; try { video.currentTime = t; } catch (e) {}
        }
      },
    });
    try { video.currentTime = 0.01; } catch (e) {}
    // metadata (duration) loads very early; one refresh then locks the pin length
    if (!(video.readyState >= 1 && video.duration)) {
      video.addEventListener("loadedmetadata", function () {
        if (window.ScrollTrigger) ScrollTrigger.refresh();
      }, { once: true });
    }
  }

  /* ---------------- GIFT · front/back flip ---------------- */
  function initGift() {
    var fine = matchMedia("(hover:hover) and (pointer:fine)").matches;
    document.querySelectorAll(".giftcard").forEach(function (card) {
      if (fine) {
        card.addEventListener("mouseenter", function () { card.classList.add("is-flipped"); Sound.play("tick"); });
        card.addEventListener("mouseleave", function () { card.classList.remove("is-flipped"); });
      } else {
        card.addEventListener("click", function () { card.classList.toggle("is-flipped"); });
      }
    });
  }

  /* ---------------- FLOORS · elevator shaft + lighting + ding ---------------- */
  function initElevator() {
    var pin = document.getElementById("elevatorPin");
    if (!pin || !window.gsap || !window.ScrollTrigger) return;
    var cells = document.querySelectorAll(".floor-cell");
    var panels = document.querySelectorAll(".floor-panel");
    var cabin = document.getElementById("cabin");
    var counter = document.getElementById("floorCounter");
    var COUNT = ["00", "01", "02"];
    var FLOOR_ORDER = [0, 1, 2];
    var CELL_CENTER = { 0: 83.33, 1: 50, 2: 16.67 };

    function setActive(idx) {
      var floor = FLOOR_ORDER[idx];
      cells.forEach(function (c) { c.classList.toggle("is-active", +c.dataset.cell === floor); });
      panels.forEach(function (p) { p.classList.toggle("is-active", +p.dataset.panel === floor); });
      if (counter) counter.textContent = COUNT[floor];
    }

    var reduced = matchMedia("(prefers-reduced-motion:reduce)").matches;
    var mobile = matchMedia("(max-width:1024px)").matches;
    if (mobile || reduced) {
      var sec = document.getElementById("floors");
      if (sec) sec.classList.add("is-static");
      cells.forEach(function (c) { c.classList.add("is-active"); });
      return;
    }

    /* ---- DISCRETE STEPPER: one scroll / swipe / arrow = one floor ----
       The section pins to the viewport; while pinned, page scroll is frozen and
       each gesture advances exactly one floor. At the first/last floor a further
       gesture releases the pin so the page scrolls normally to the next section. */
    var section = document.getElementById("floors");
    function L() { return window.__dorianLenis || null; }   // read Lenis lazily (app.js sets it)
    var current = 0, animating = false, locked = false, cool = 0, justLeft = 0;

    function paint(idx, dir) {
      idx = Math.max(0, Math.min(2, idx));
      current = idx;
      setActive(idx);
      Sound.play("ding");
      var floor = FLOOR_ORDER[idx];
      var top = CELL_CENTER[0] - (idx / 2) * (CELL_CENTER[0] - CELL_CENTER[2]);
      if (cabin) gsap.to(cabin, { top: top + "%", duration: 0.7, ease: "power2.inOut" });
      gsap.killTweensOf(panels);
      panels.forEach(function (q) { gsap.set(q, { opacity: +q.dataset.panel === floor ? 1 : 0 }); });
      animating = true;
      gsap.fromTo('.floor-panel[data-panel="' + floor + '"]',
        { y: (dir < 0 ? -38 : 38), filter: "blur(6px)" },
        { y: 0, filter: "blur(0px)", duration: 0.55, ease: "power3.out", overwrite: true,
          onComplete: function () { animating = false; } });
    }
    setActive(0);

    function lock() {
      if (locked || Date.now() - justLeft < 900) return;   // don't re-lock right after leaving
      locked = true;
      section.classList.add("is-locked");
      var l = L(); if (l) l.stop();
      window.addEventListener("wheel", onWheel, { passive: false });
      window.addEventListener("keydown", onKey);
      window.addEventListener("touchstart", onTouchStart, { passive: true });
      window.addEventListener("touchmove", onTouchMove, { passive: false });
    }
    function unlock() {
      if (!locked) return; locked = false;
      section.classList.remove("is-locked");
      var l = L(); if (l) l.start();
      window.removeEventListener("wheel", onWheel, { passive: false });
      window.removeEventListener("keydown", onKey);
      window.removeEventListener("touchstart", onTouchStart);
      window.removeEventListener("touchmove", onTouchMove, { passive: false });
    }
    function leave(dir) {
      // release and glide to the neighbouring section (never gets stuck)
      justLeft = Date.now();
      unlock();
      var targets = section.parentNode.querySelectorAll(".screen");
      var i = Array.prototype.indexOf.call(targets, section);
      var next = targets[i + (dir > 0 ? 1 : -1)];
      var l = L();
      if (next) { if (l) l.scrollTo(next, { duration: 1.0, lock: true }); else next.scrollIntoView({ behavior: "smooth" }); }
    }
    function step(dir) {
      if (animating) return;
      // at the first/last floor a further gesture LEAVES immediately (no cooldown) so it never sticks
      if (dir > 0 && current >= 2) { leave(1); return; }
      if (dir < 0 && current <= 0) { leave(-1); return; }
      if (Date.now() - cool < 560) return;   // one step per gesture between floors
      cool = Date.now();
      paint(current + (dir > 0 ? 1 : -1), dir);
    }
    function onWheel(e) { e.preventDefault(); if (Math.abs(e.deltaY) < 4) return; step(e.deltaY > 0 ? 1 : -1); }
    function onKey(e) {
      if (e.key === "ArrowDown" || e.key === "PageDown") { e.preventDefault(); step(1); }
      else if (e.key === "ArrowUp" || e.key === "PageUp") { e.preventDefault(); step(-1); }
    }
    var tY = 0;
    function onTouchStart(e) { tY = e.touches[0].clientY; }
    function onTouchMove(e) {
      var dy = tY - e.touches[0].clientY;
      if (Math.abs(dy) < 24) return;
      e.preventDefault(); step(dy > 0 ? 1 : -1); tY = e.touches[0].clientY;
    }

    // engage the stepper exactly when the shaft snaps to the top of the viewport
    ScrollTrigger.create({
      trigger: "#floors", start: "top top", end: "bottom top",
      onEnter: function () { current = 0; paintInstant(0); align(); lock(); },
      onEnterBack: function () { current = 2; paintInstant(2); align(); lock(); },
      onLeave: function () { unlock(); },
      onLeaveBack: function () { unlock(); },
    });
    function align() {
      // snap the section flush to the top so it fills the viewport while locked
      var l = L();
      var y = section.getBoundingClientRect().top + (l ? l.scroll : window.scrollY);
      if (l) l.scrollTo(y, { immediate: true }); else window.scrollTo(0, y);
    }
    function paintInstant(idx) {
      current = idx; setActive(idx);
      var floor = FLOOR_ORDER[idx];
      var top = CELL_CENTER[0] - (idx / 2) * (CELL_CENTER[0] - CELL_CENTER[2]);
      if (cabin) cabin.style.top = top + "%";
      panels.forEach(function (q) { gsap.set(q, { opacity: +q.dataset.panel === floor ? 1 : 0 }); });
    }
  }

  /* ---------------- sound: ON by default ----------------
     Browsers block audio until a user gesture, so we arm the sound engine and
     enable it on the first interaction (pointer / key / scroll / touch). No
     on-screen toggle — sound is simply on. Respects reduced-motion preference. */
  function initSound() {
    if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    var armed = false;
    function arm() {
      if (armed) return; armed = true;
      Sound.enable();
      ["pointerdown", "keydown", "touchstart", "wheel"].forEach(function (ev) {
        window.removeEventListener(ev, arm);
      });
    }
    ["pointerdown", "keydown", "touchstart", "wheel"].forEach(function (ev) {
      window.addEventListener(ev, arm, { once: false, passive: true });
    });
  }

  /* ---------------- mini-cart drawer (WooCommerce) ---------------- */
  function initMiniCart() {
    var trigger = document.getElementById("dorianCart");
    var panel = document.getElementById("dorianMiniCart");
    if (!trigger || !panel) return;
    var closeBtn = document.getElementById("dorianMiniCartClose");

    function open() {
      panel.classList.add("is-open");
      panel.setAttribute("aria-hidden", "false");
      trigger.setAttribute("aria-expanded", "true");
    }
    function close() {
      panel.classList.remove("is-open");
      panel.setAttribute("aria-hidden", "true");
      trigger.setAttribute("aria-expanded", "false");
    }
    function toggle() { panel.classList.contains("is-open") ? close() : open(); }

    trigger.addEventListener("click", function (e) { e.preventDefault(); toggle(); });
    if (closeBtn) closeBtn.addEventListener("click", close);
    document.addEventListener("click", function (e) {
      if (panel.classList.contains("is-open") && !panel.contains(e.target) && !trigger.contains(e.target)) close();
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
  }

  function boot() {
    initHeroTagline();
    initStoryVideo();
    initGift();
    initElevator();
    initSound();
    initMiniCart();
    if (window.ScrollTrigger) ScrollTrigger.refresh();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
