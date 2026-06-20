/* Dorian — spotlight, magnetic elements, word-by-word headline reveal. */
window.Dorian = window.Dorian || {};

window.Dorian.initInteractions = function () {
  var fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- Hero spotlight follows the cursor ---- */
  var hero = document.getElementById("hero");
  var spot = hero ? hero.querySelector(".hero__spotlight") : null;
  if (hero && spot && fine && !reduced) {
    hero.addEventListener("mousemove", function (e) {
      var r = hero.getBoundingClientRect();
      spot.style.setProperty("--mx", ((e.clientX - r.left) / r.width) * 100 + "%");
      spot.style.setProperty("--my", ((e.clientY - r.top) / r.height) * 100 + "%");
    });
  }

  /* ---- Magnetic elements ---- */
  if (fine && !reduced && window.gsap) {
    document.querySelectorAll("[data-magnetic]").forEach(function (el) {
      var strength = parseFloat(el.getAttribute("data-magnetic")) || 0.35;
      el.addEventListener("mousemove", function (e) {
        var r = el.getBoundingClientRect();
        var x = e.clientX - (r.left + r.width / 2);
        var y = e.clientY - (r.top + r.height / 2);
        gsap.to(el, {
          x: x * strength,
          y: y * strength,
          duration: 0.6,
          ease: "power3.out",
        });
      });
      el.addEventListener("mouseleave", function () {
        gsap.to(el, { x: 0, y: 0, duration: 0.6, ease: "elastic.out(1, 0.4)" });
      });
    });
  }

  /* ---- Word-by-word reveal for [data-split] ---- */
  document.querySelectorAll("[data-split]").forEach(function (el) {
    var words = el.textContent.trim().split(/\s+/);
    el.textContent = "";
    el.classList.add("split-ready");
    var spans = [];
    words.forEach(function (w, i) {
      var wrap = document.createElement("span");
      wrap.className = "reveal-line";
      var inner = document.createElement("span");
      inner.textContent = w;
      wrap.appendChild(inner);
      el.appendChild(wrap);
      if (i < words.length - 1) el.appendChild(document.createTextNode(" "));
      spans.push(inner);
    });

    if (reduced || !window.gsap) {
      spans.forEach(function (s) {
        s.style.transform = "none";
      });
      return;
    }
    gsap.set(spans, { yPercent: 110 });
    gsap.to(spans, {
      yPercent: 0,
      duration: 0.9,
      ease: "power3.out",
      stagger: 0.05,
      scrollTrigger: { trigger: el, start: "top 88%" },
    });
  });

  /* ---- Count-up for [data-count] stats ---- */
  document.querySelectorAll("[data-count]").forEach(function (el) {
    var target = parseFloat(el.getAttribute("data-count"));
    var suffix = el.getAttribute("data-suffix") || "";
    if (isNaN(target)) return;
    if (reduced || !window.gsap) {
      el.textContent = target + suffix;
      return;
    }
    var obj = { n: 0 };
    gsap.to(obj, {
      n: target,
      duration: 1.7,
      ease: "power2.out",
      scrollTrigger: { trigger: el, start: "top 92%", once: true },
      onUpdate: function () {
        el.textContent = Math.round(obj.n) + suffix;
      },
      onComplete: function () {
        el.textContent = target + suffix;
      },
    });
  });
};
