/* Dorian — team gallery.
   Desktop: pinned section — vertical page scroll drives horizontal motion
   (drag and arrows scrub the page scroll). Touch/reduced: native swipe. */
window.Dorian = window.Dorian || {};
window.Dorian.initCharacters = function () {
  var vp = document.getElementById("teamViewport");
  var track = document.getElementById("teamTrack");
  if (!vp || !track) return;

  var fine = matchMedia("(hover: hover) and (pointer: fine)").matches;
  var desktop = matchMedia("(min-width: 1025px)").matches;
  var reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  var canPin = desktop && fine && !reduced && window.gsap && window.ScrollTrigger;

  var lenis = window.__lenis;
  function getScroll() {
    return window.scrollY || window.pageYOffset || 0;
  }
  function cardStep() {
    var c = track.querySelector(".team-card");
    return c ? c.getBoundingClientRect().width + 24 : 290;
  }

  /* ===================== Pinned horizontal scroll ===================== */
  if (canPin) {
    vp.classList.add("is-pinned");

    var distance = function () {
      return Math.max(0, track.scrollWidth - vp.clientWidth + 64);
    };

    gsap.to(track, {
      x: function () {
        return -distance();
      },
      ease: "none",
      scrollTrigger: {
        trigger: "#characters",
        start: "top top",
        end: function () {
          return "+=" + distance();
        },
        pin: true,
        scrub: 0.6,
        anticipatePin: 1,
        invalidateOnRefresh: true,
      },
    });

    // Arrows scrub the page scroll by one card.
    function pageBy(delta) {
      if (lenis) lenis.scrollTo(getScroll() + delta, { duration: 0.9 });
      else window.scrollTo({ top: getScroll() + delta, behavior: "smooth" });
    }
    document.querySelectorAll(".team-arrow").forEach(function (b) {
      b.addEventListener("click", function () {
        pageBy((b.getAttribute("data-dir") === "next" ? 1 : -1) * cardStep());
        if (window.DorianSound) window.DorianSound.play("tick");
      });
    });

    // Mouse drag also scrubs the page scroll (1:1).
    var down = false;
    var startX = 0;
    var startScroll = 0;
    var moved = 0;
    vp.addEventListener("pointerdown", function (e) {
      if (e.pointerType !== "mouse") return;
      down = true;
      moved = 0;
      startX = e.clientX;
      startScroll = getScroll();
      vp.classList.add("is-dragging");
      try {
        vp.setPointerCapture(e.pointerId);
      } catch (_) {}
    });
    vp.addEventListener("pointermove", function (e) {
      if (!down) return;
      var dx = e.clientX - startX;
      moved = Math.max(moved, Math.abs(dx));
      var target = startScroll - dx;
      if (lenis) lenis.scrollTo(target, { immediate: true });
      else window.scrollTo(0, target);
    });
    function endDrag(e) {
      if (!down) return;
      down = false;
      vp.classList.remove("is-dragging");
      try {
        vp.releasePointerCapture(e.pointerId);
      } catch (_) {}
    }
    vp.addEventListener("pointerup", endDrag);
    vp.addEventListener("pointercancel", endDrag);
    vp.addEventListener(
      "click",
      function (e) {
        if (moved > 6) {
          e.preventDefault();
          e.stopPropagation();
        }
      },
      true
    );
    return;
  }

  /* ===================== Native swipe (touch / fallback) ============== */
  var d2 = false;
  var sx = 0;
  var ss = 0;
  var mv = 0;
  vp.addEventListener("pointerdown", function (e) {
    if (e.pointerType !== "mouse") return;
    d2 = true;
    mv = 0;
    sx = e.clientX;
    ss = vp.scrollLeft;
    vp.classList.add("is-dragging");
    try {
      vp.setPointerCapture(e.pointerId);
    } catch (_) {}
  });
  vp.addEventListener("pointermove", function (e) {
    if (!d2) return;
    var dx = e.clientX - sx;
    mv = Math.max(mv, Math.abs(dx));
    vp.scrollLeft = ss - dx;
  });
  function end2(e) {
    if (!d2) return;
    d2 = false;
    vp.classList.remove("is-dragging");
    try {
      vp.releasePointerCapture(e.pointerId);
    } catch (_) {}
  }
  vp.addEventListener("pointerup", end2);
  vp.addEventListener("pointercancel", end2);
  vp.addEventListener(
    "click",
    function (e) {
      if (mv > 6) {
        e.preventDefault();
        e.stopPropagation();
      }
    },
    true
  );

  document.querySelectorAll(".team-arrow").forEach(function (b) {
    b.addEventListener("click", function () {
      var dir = b.getAttribute("data-dir") === "next" ? 1 : -1;
      vp.scrollBy({ left: dir * cardStep(), behavior: "smooth" });
      if (window.DorianSound) window.DorianSound.play("tick");
    });
  });
};
