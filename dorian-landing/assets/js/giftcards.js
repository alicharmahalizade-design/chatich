/* Dorian — gift cards: subtle 3D tilt + cursor-tracked gold reflection. */
window.Dorian = window.Dorian || {};
window.Dorian.initGiftcards = function () {
  var cards = document.querySelectorAll(".gift-card");
  var fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  if (!fine) return;

  cards.forEach(function (card) {
    card.addEventListener("mousemove", function (e) {
      var r = card.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width;
      var py = (e.clientY - r.top) / r.height;
      card.style.transform =
        "perspective(800px) rotateX(" +
        (py - 0.5) * -8 +
        "deg) rotateY(" +
        (px - 0.5) * 10 +
        "deg) translateY(-6px)";
      card.style.setProperty("--mx", px * 100 + "%");
      card.style.setProperty("--my", py * 100 + "%");
    });
    card.addEventListener("mouseleave", function () {
      card.style.transform = "";
    });
  });
};
