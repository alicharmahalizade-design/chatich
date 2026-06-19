/* Dorian — character cards: 3D tilt + gold glow + flip to specialties. */
window.Dorian = window.Dorian || {};
window.Dorian.initCharacters = function () {
  var cards = document.querySelectorAll(".char-card");
  var fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  cards.forEach(function (card) {
    var inner = card.querySelector(".char-card__inner");

    if (fine) {
      card.addEventListener("mousemove", function (e) {
        if (card.classList.contains("is-flipped")) return;
        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5;
        var py = (e.clientY - r.top) / r.height - 0.5;
        inner.style.transform =
          "rotateX(" + -py * 10 + "deg) rotateY(" + px * 12 + "deg)";
      });
      card.addEventListener("mouseleave", function () {
        if (!card.classList.contains("is-flipped"))
          inner.style.transform = "";
      });
    }

    // Flip: hover on desktop, tap on touch.
    card.addEventListener("mouseenter", function () {
      if (fine) {
        card.classList.add("is-flipped");
        inner.style.transform = "";
        if (window.DorianSound) window.DorianSound.play("tick");
      }
    });
    card.addEventListener("mouseleave", function () {
      if (fine) card.classList.remove("is-flipped");
    });
    card.addEventListener("click", function () {
      if (!fine) card.classList.toggle("is-flipped");
    });
  });
};
