/* Dorian — gift cards: flip to reveal the back (QR + contact).
   Hover to flip on desktop, tap to flip on touch. */
window.Dorian = window.Dorian || {};
window.Dorian.initGiftcards = function () {
  var cards = document.querySelectorAll(".giftcard");
  var fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  cards.forEach(function (card) {
    if (fine) {
      card.addEventListener("mouseenter", function () {
        card.classList.add("is-flipped");
        if (window.DorianSound) window.DorianSound.play("tick");
      });
      card.addEventListener("mouseleave", function () {
        card.classList.remove("is-flipped");
      });
    } else {
      card.addEventListener("click", function () {
        card.classList.toggle("is-flipped");
      });
    }
  });
};
