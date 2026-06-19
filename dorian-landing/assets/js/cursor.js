/* Dorian — dynamic luxury cursor (dot + trailing labelled ring). */
window.Dorian = window.Dorian || {};
window.Dorian.initCursor = function () {
  var fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  if (!fine) return;

  var dot = document.getElementById("cursorDot");
  var ring = document.getElementById("cursorRing");
  var label = document.getElementById("cursorLabel");
  if (!dot || !ring) return;

  document.body.classList.add("custom-cursor");

  var LABELS = {
    explore: "Explore",
    enter: "Enter",
    scroll: "Scroll",
    open: "Open",
    view: "View",
  };

  var mx = innerWidth / 2,
    my = innerHeight / 2,
    rx = mx,
    ry = my;

  window.addEventListener("mousemove", function (e) {
    mx = e.clientX;
    my = e.clientY;
    dot.style.transform = "translate(" + mx + "px," + my + "px) translate(-50%,-50%)";

    var t = e.target.closest ? e.target.closest("[data-cursor]") : null;
    if (t) {
      var mode = t.getAttribute("data-cursor");
      ring.classList.add("is-label");
      if (label) label.textContent = LABELS[mode] || "";
    } else {
      ring.classList.remove("is-label");
    }
  });

  window.addEventListener("mousedown", function () {
    ring.classList.add("is-down");
  });
  window.addEventListener("mouseup", function () {
    ring.classList.remove("is-down");
  });

  function render() {
    rx += (mx - rx) * 0.18;
    ry += (my - ry) * 0.18;
    ring.style.transform =
      "translate(" + rx + "px," + ry + "px) translate(-50%,-50%)";
    requestAnimationFrame(render);
  }
  requestAnimationFrame(render);
};
