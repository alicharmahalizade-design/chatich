/* Dorian — hero cinematic entrance (staged GSAP timeline). */
window.Dorian = window.Dorian || {};
window.Dorian.initHero = function () {
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!window.gsap) return;

  if (reduced) {
    gsap.set("[data-hero]", { opacity: 1, y: 0, scale: 1, clearProps: "all" });
    return;
  }

  var tl = gsap.timeline({ defaults: { ease: "power3.out" } });

  // 1. framed portrait fades from soft/blur to full
  tl.from('[data-hero="frame"]', {
    opacity: 0,
    scale: 1.08,
    filter: "blur(10px)",
    duration: 1.5,
  });

  // 2. script tagline writes in
  tl.from('[data-hero="tagline"]', { opacity: 0, y: 24, duration: 1 }, "-=1.0");

  // 3. wordmark
  tl.from(
    '[data-hero="wordmark"]',
    {
      opacity: 0,
      y: 30,
      letterSpacing: "0.5em",
      duration: 1,
      onStart: function () {
        if (window.DorianSound) window.DorianSound.play("tick");
      },
    },
    "-=0.6"
  );

  // 4. CTA
  tl.from('[data-hero="cta"]', { opacity: 0, y: 20, duration: 0.8 }, "-=0.3");

  // Parallax on scroll + pointer
  gsap.to('[data-hero="frame"]', {
    yPercent: 16,
    ease: "none",
    scrollTrigger: {
      trigger: "#hero",
      start: "top top",
      end: "bottom top",
      scrub: true,
    },
  });

  var hero = document.getElementById("hero");
  hero.addEventListener("mousemove", function (e) {
    var rx = (e.clientX / innerWidth - 0.5) * 16;
    var ry = (e.clientY / innerHeight - 0.5) * 16;
    gsap.to('[data-hero="frame"]', { x: rx, y: ry, duration: 0.8 });
  });
};
