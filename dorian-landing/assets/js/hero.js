/* Dorian — hero cinematic entrance (staged GSAP timeline + DrawSVG frame). */
window.Dorian = window.Dorian || {};
window.Dorian.initHero = function () {
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!window.gsap) return;

  if (reduced) {
    gsap.set(
      "[data-hero]",
      { opacity: 1, y: 0, scale: 1, clearProps: "all" }
    );
    return;
  }

  var tl = gsap.timeline({ defaults: { ease: "power3.out" } });

  // 1. gold oval frame draws itself
  if (window.DrawSVGPlugin) {
    tl.from("#frame-ellipse", {
      drawSVG: "0%",
      duration: 1.6,
      ease: "power2.inOut",
    });
  }

  // 2. portrait fades from sepia/blur to full
  tl.from(
    '[data-hero="portrait"]',
    { opacity: 0, filter: "sepia(1) blur(8px)", scale: 1.08, duration: 1.4 },
    "-=1.1"
  );

  // 3. tagline script writes in
  tl.from(
    '[data-hero="tagline"]',
    { opacity: 0, y: 24, duration: 1 },
    "-=0.9"
  );

  // 4. wordmark
  tl.from(
    '[data-hero="wordmark"]',
    { opacity: 0, y: 30, letterSpacing: "0.5em", duration: 1 },
    "-=0.6"
  );

  // 5. wax seal stamps down
  tl.from(
    '[data-hero="seal"]',
    {
      scale: 0.3,
      rotate: -30,
      opacity: 0,
      duration: 0.7,
      ease: "back.out(1.8)",
      onStart: function () {
        if (window.DorianSound) window.DorianSound.play("tick");
      },
    },
    "-=0.4"
  );

  // 6. CTA
  tl.from('[data-hero="cta"]', { opacity: 0, y: 20, duration: 0.8 }, "-=0.3");

  // Parallax on scroll + pointer
  gsap.to('[data-hero="frame"]', {
    yPercent: 18,
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
    var rx = (e.clientX / innerWidth - 0.5) * 18;
    var ry = (e.clientY / innerHeight - 0.5) * 18;
    gsap.to('[data-hero="frame"]', { x: rx, y: ry, duration: 0.8 });
    gsap.to('[data-hero="portrait"]', { x: rx * 0.4, y: ry * 0.4, duration: 0.8 });
  });
};
