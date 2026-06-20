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
    function build() {
      var dur = video.duration && isFinite(video.duration) ? video.duration : 4.1;
      ScrollTrigger.create({
        trigger: section, start: "top top",
        end: function () { return "+=" + Math.round(dur * 340); },
        pin: true, scrub: 0.35, anticipatePin: 1, invalidateOnRefresh: true,
        onUpdate: function (self) {
          var t = self.progress * (dur - 0.05);
          if (isFinite(t) && t >= 0 && Math.abs(t - lastT) > 0.012) {
            lastT = t; try { video.currentTime = t; } catch (e) {}
          }
        },
      });
      try { video.currentTime = 0.01; } catch (e) {}
    }
    if (video.readyState >= 1 && video.duration) build();
    else video.addEventListener("loadedmetadata", build, { once: true });
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

    setActive(0);
    var current = 0;
    ScrollTrigger.create({
      trigger: "#floors", start: "top top", end: "+=300%",
      pin: pin, scrub: 1, anticipatePin: 1, invalidateOnRefresh: true,
      onUpdate: function (self) {
        var p = self.progress;
        var top = CELL_CENTER[0] - p * (CELL_CENTER[0] - CELL_CENTER[2]);
        if (cabin) cabin.style.top = top + "%";
        var idx = Math.min(2, Math.floor(p * 3 + 0.0001));
        if (idx !== current) {
          current = idx;
          setActive(idx);
          Sound.play("ding");
          var floor = FLOOR_ORDER[idx];
          gsap.killTweensOf(panels);
          panels.forEach(function (q) { gsap.set(q, { opacity: +q.dataset.panel === floor ? 1 : 0 }); });
          gsap.fromTo(
            '.floor-panel[data-panel="' + floor + '"]',
            { y: 40, filter: "blur(6px)" },
            { y: 0, filter: "blur(0px)", duration: 0.6, ease: "power3.out", overwrite: true }
          );
        }
      },
    });
  }

  /* ---------------- sound toggle ---------------- */
  function initSoundToggle() {
    var btn = document.getElementById("soundToggle"), label = document.getElementById("soundLabel");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var on = Sound.toggle();
      btn.classList.toggle("is-on", on);
      btn.setAttribute("aria-pressed", String(on));
      if (label) label.textContent = on ? "صدا روشن" : "صدا خاموش";
    });
  }

  function boot() {
    initStoryVideo();
    initGift();
    initElevator();
    initSoundToggle();
    if (window.ScrollTrigger) ScrollTrigger.refresh();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
