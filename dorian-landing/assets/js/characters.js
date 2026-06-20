/* Dorian — team gallery: horizontal scroll with drag, wheel and arrows.
   The viewport is forced LTR so scrollLeft stays sane; card text is RTL. */
window.Dorian = window.Dorian || {};
window.Dorian.initCharacters = function () {
  var vp = document.getElementById("teamViewport");
  var track = document.getElementById("teamTrack");
  if (!vp || !track) return;

  /* ---- Mouse drag to scroll (touch uses native scrolling) ---- */
  var down = false;
  var startX = 0;
  var startScroll = 0;
  var moved = 0;

  vp.addEventListener("pointerdown", function (e) {
    if (e.pointerType !== "mouse") return;
    down = true;
    moved = 0;
    startX = e.clientX;
    startScroll = vp.scrollLeft;
    vp.classList.add("is-dragging");
    try {
      vp.setPointerCapture(e.pointerId);
    } catch (_) {}
  });
  vp.addEventListener("pointermove", function (e) {
    if (!down) return;
    var dx = e.clientX - startX;
    moved = Math.max(moved, Math.abs(dx));
    vp.scrollLeft = startScroll - dx;
  });
  function end(e) {
    if (!down) return;
    down = false;
    vp.classList.remove("is-dragging");
    try {
      vp.releasePointerCapture(e.pointerId);
    } catch (_) {}
  }
  vp.addEventListener("pointerup", end);
  vp.addEventListener("pointercancel", end);
  // Swallow the click that follows a real drag.
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

  /* ---- Vertical wheel scrolls the gallery horizontally (with room) ---- */
  vp.addEventListener(
    "wheel",
    function (e) {
      if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
      var max = track.scrollWidth - vp.clientWidth;
      var atStart = vp.scrollLeft <= 0;
      var atEnd = vp.scrollLeft >= max - 1;
      if ((e.deltaY > 0 && !atEnd) || (e.deltaY < 0 && !atStart)) {
        vp.scrollLeft += e.deltaY;
        e.preventDefault();
      }
    },
    { passive: false }
  );

  /* ---- Prev / next arrows ---- */
  function step(dir) {
    var card = track.querySelector(".team-card");
    var gap = 24;
    var w = card ? card.getBoundingClientRect().width + gap : 300;
    vp.scrollBy({ left: dir * w, behavior: "smooth" });
  }
  document.querySelectorAll(".team-arrow").forEach(function (b) {
    b.addEventListener("click", function () {
      step(b.getAttribute("data-dir") === "next" ? 1 : -1);
      if (window.DorianSound) window.DorianSound.play("tick");
    });
  });
};
