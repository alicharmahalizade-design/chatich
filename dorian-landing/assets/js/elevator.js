/* Dorian — Elevator: pinned cinematic ascent through 3 floors.
   Narrative: ascent from Underground (00) -> First (01) -> Second (02).
   (To reverse, flip FLOOR_ORDER below.) */
window.Dorian = window.Dorian || {};
window.Dorian.initElevator = function () {
  var pin = document.getElementById("elevatorPin");
  if (!pin || !window.gsap || !window.ScrollTrigger) return;

  var cells = document.querySelectorAll(".floor-cell");
  var panels = document.querySelectorAll(".floor-panel");
  var cabin = document.getElementById("cabin");
  var counter = document.getElementById("floorCounter");
  var COUNT = ["۰۰", "۰۱", "۰۲"];

  // Floors encountered in scroll order (ascent).
  var FLOOR_ORDER = [0, 1, 2];
  // Vertical center (% from top of shaft) of each floor's building cell.
  var CELL_CENTER = { 0: 83.33, 1: 50, 2: 16.67 };

  function setActive(idx) {
    var floor = FLOOR_ORDER[idx];
    cells.forEach(function (c) {
      c.classList.toggle("is-active", +c.dataset.cell === floor);
    });
    panels.forEach(function (p) {
      var on = +p.dataset.panel === floor;
      p.classList.toggle("is-active", on);
    });
    if (counter) counter.textContent = COUNT[floor];
  }

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var mobile = window.matchMedia("(max-width: 1024px)").matches;

  // Mobile / reduced: show floors as a simple stacked reveal, no pin.
  if (mobile || reduced) {
    var section = document.getElementById("elevator");
    if (section) section.classList.add("is-static");
    cells.forEach(function (c) {
      c.classList.add("is-active");
    });
    return;
  }

  setActive(0);
  var current = 0;

  ScrollTrigger.create({
    trigger: "#elevator",
    start: "top top",
    end: "+=300%",
    pin: pin,
    scrub: 1,
    anticipatePin: 1,
    invalidateOnRefresh: true,
    onUpdate: function (self) {
      var p = self.progress;
      // Smooth cabin glide (bottom -> top during ascent).
      var top = CELL_CENTER[0] - p * (CELL_CENTER[0] - CELL_CENTER[2]);
      if (cabin) cabin.style.top = top + "%";

      // Discrete active floor (3 segments).
      var idx = Math.min(2, Math.floor(p * 3 + 0.0001));
      if (idx !== current) {
        current = idx;
        var swap = function () {
          setActive(idx);
          if (window.DorianSound) window.DorianSound.play("ding");
          if (window.gsap) {
            var floor = FLOOR_ORDER[idx];
            gsap.fromTo(
              '.floor-panel[data-panel="' + floor + '"]',
              { y: 50, opacity: 0, filter: "blur(6px)" },
              { y: 0, opacity: 1, filter: "blur(0px)", duration: 0.7, ease: "power3.out" }
            );
          }
        };
        // Brass doors wipe across as the floor changes, swapping at the shut point.
        if (window.Dorian.elevatorDoorSweep) window.Dorian.elevatorDoorSweep(swap);
        else swap();
      }
    },
  });
};
