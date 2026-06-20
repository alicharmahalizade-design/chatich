/* Dorian — logo flight.
   The hero "Dorian" wordmark flies up and shrinks into the header logo as
   you scroll, then hands off to the (white) nav logo. Desktop only. */
window.Dorian = window.Dorian || {};
window.Dorian.initLogoFlight = function () {
  var reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  var coarse = matchMedia("(max-width: 700px)").matches;

  var heroImg = document.querySelector(".hero__wordmark img");
  var navLogo = document.querySelector(".nav__logo");
  var navImg = navLogo ? navLogo.querySelector("img") : null;
  var fly = document.getElementById("logoFly");
  if (!heroImg || !navImg || !fly || !window.gsap || !window.ScrollTrigger) return;

  // Reduced motion / small screens: leave both logos as they are, no flight.
  if (reduced || coarse) return;

  var S = {};
  function measure() {
    var hb = heroImg.getBoundingClientRect();
    var nb = navImg.getBoundingClientRect();
    // Hero position is captured in document space so it's correct at any scroll.
    S.hx = hb.left + hb.width / 2;
    S.hy = hb.top + window.scrollY + hb.height / 2;
    S.hw = hb.width;
    S.nx = nb.left + nb.width / 2;
    S.ny = nb.top + nb.height / 2; // nav is fixed -> constant in the viewport
    S.nw = nb.width;
    fly.style.width = S.hw + "px";
  }

  function clamp01(v) {
    return v < 0 ? 0 : v > 1 ? 1 : v;
  }
  function render(p) {
    var cx = S.hx + (S.nx - S.hx) * p;
    var cy = S.hy + (S.ny - S.hy) * p;
    var sc = 1 + (S.nw / S.hw - 1) * p;
    fly.style.transform =
      "translate(" + cx + "px," + cy + "px) translate(-50%, -50%) scale(" + sc + ")";
    // Cross-fade the fly out into the real (white) nav logo near the end.
    fly.style.opacity = String(clamp01((0.95 - p) / 0.15));
    navImg.style.opacity = String(clamp01((p - 0.72) / 0.28));
  }

  // The fly stands in for the hero wordmark; hide the real one (keep layout).
  heroImg.style.visibility = "hidden";
  navImg.style.opacity = "0";

  measure();
  render(0);

  ScrollTrigger.create({
    trigger: "#hero",
    start: "top top",
    end: function () {
      return "+=" + Math.max(220, window.innerHeight * 0.55);
    },
    scrub: true,
    invalidateOnRefresh: true,
    onRefresh: function () {
      measure();
    },
    onUpdate: function (self) {
      render(self.progress);
    },
  });
};
