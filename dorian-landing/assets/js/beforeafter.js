/* Dorian — before/after comparison slider (drag / touch / keyboard). */
window.Dorian = window.Dorian || {};

window.Dorian.initBeforeAfter = function () {
  var ba = document.getElementById("ba");
  if (!ba) return;
  var after = ba.querySelector(".ba__after");
  var handle = ba.querySelector(".ba__handle");
  var dragging = false;

  function setPos(clientX) {
    var r = ba.getBoundingClientRect();
    var pct = ((clientX - r.left) / r.width) * 100;
    pct = Math.max(2, Math.min(98, pct));
    after.style.clipPath = "inset(0 0 0 " + pct + "%)";
    handle.style.left = pct + "%";
  }

  function down(e) {
    dragging = true;
    setPos((e.touches ? e.touches[0] : e).clientX);
  }
  function move(e) {
    if (!dragging) return;
    setPos((e.touches ? e.touches[0] : e).clientX);
  }
  function up() {
    dragging = false;
  }

  ba.addEventListener("mousedown", down);
  window.addEventListener("mousemove", move);
  window.addEventListener("mouseup", up);
  ba.addEventListener("touchstart", down, { passive: true });
  ba.addEventListener("touchmove", move, { passive: true });
  ba.addEventListener("touchend", up);

  // Hover-to-scrub on desktop for an effortless feel.
  ba.addEventListener("mousemove", function (e) {
    if (!dragging) setPos(e.clientX);
  });

  // Keyboard accessibility.
  ba.setAttribute("tabindex", "0");
  var pos = 50;
  ba.addEventListener("keydown", function (e) {
    if (e.key === "ArrowLeft") pos = Math.max(2, pos - 4);
    else if (e.key === "ArrowRight") pos = Math.min(98, pos + 4);
    else return;
    var r = ba.getBoundingClientRect();
    setPos(r.left + (pos / 100) * r.width);
  });
};
