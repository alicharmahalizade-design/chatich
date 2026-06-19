/* Dorian — preloader: wax seal stamp + gold sweep + counter (<= 2.2s). */
window.Dorian = window.Dorian || {};

window.Dorian.toFa = function (n) {
  var fa = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
  return String(n).replace(/\d/g, function (d) {
    return fa[d];
  });
};

window.Dorian.runPreloader = function () {
  return new Promise(function (resolve) {
    var el = document.getElementById("preloader");
    var bar = document.getElementById("pre-bar");
    var count = document.getElementById("pre-count");
    var seal = document.getElementById("seal-pre");
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (!el) {
      resolve();
      return;
    }

    // Stamp the seal in.
    if (window.gsap && !reduced) {
      gsap.fromTo(
        seal,
        { scale: 0.4, rotate: -25, opacity: 0 },
        { scale: 1, rotate: 0, opacity: 1, duration: 0.7, ease: "back.out(1.7)" }
      );
    }

    var total = reduced ? 300 : 1800;
    var t0 = performance.now();

    function tick(now) {
      var p = Math.min(1, (now - t0) / total);
      var eased = 1 - Math.pow(1 - p, 3);
      var val = Math.round(eased * 100);
      if (bar) bar.style.width = val + "%";
      if (count) count.textContent = window.Dorian.toFa(val);
      if (p < 1) {
        requestAnimationFrame(tick);
      } else {
        setTimeout(function () {
          if (window.gsap && !reduced) {
            gsap.to(el, {
              clipPath: "inset(0 0 100% 0)",
              duration: 0.9,
              ease: "power4.inOut",
              onComplete: function () {
                el.style.display = "none";
                resolve();
              },
            });
          } else {
            el.style.display = "none";
            resolve();
          }
        }, 180);
      }
    }
    requestAnimationFrame(tick);
  });
};
