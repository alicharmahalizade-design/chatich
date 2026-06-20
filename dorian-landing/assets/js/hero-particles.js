/* Dorian — hero ambient: fine gold dust drifting slowly upward. */
window.Dorian = window.Dorian || {};
window.Dorian.initHeroParticles = function () {
  var reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  var canvas = document.getElementById("heroParticles");
  var hero = document.getElementById("hero");
  if (!canvas || !hero || reduced) return;
  var ctx = canvas.getContext("2d");
  if (!ctx) return;

  var dpr = Math.min(window.devicePixelRatio || 1, 2);
  var W = 0;
  var H = 0;
  var particles = [];
  var COLORS = ["rgba(201,162,39,", "rgba(216,189,106,", "rgba(150,128,80,"];

  function resize() {
    var r = hero.getBoundingClientRect();
    W = r.width;
    H = r.height;
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    canvas.style.width = W + "px";
    canvas.style.height = H + "px";
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function spawn(initial) {
    return {
      x: Math.random() * W,
      y: initial ? Math.random() * H : H + Math.random() * 40,
      r: Math.random() * 1.8 + 0.5,
      vy: -(Math.random() * 0.35 + 0.1),
      drift: (Math.random() - 0.5) * 0.22,
      phase: Math.random() * Math.PI * 2,
      life: 0,
      ttl: Math.random() * 420 + 320,
      color: COLORS[Math.floor(Math.random() * COLORS.length)],
      maxA: Math.random() * 0.5 + 0.12,
    };
  }

  function init() {
    resize();
    var count = Math.min(70, Math.max(24, Math.floor(W / 24)));
    particles = [];
    for (var i = 0; i < count; i++) particles.push(spawn(true));
  }

  var visible = true;
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (e) {
      visible = e[0].isIntersecting;
    }).observe(hero);
  }

  function frame() {
    requestAnimationFrame(frame);
    if (!visible) return;
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      p.life++;
      p.y += p.vy;
      p.phase += 0.02;
      p.x += p.drift + Math.sin(p.phase) * 0.2;
      if (p.y < -10 || p.life > p.ttl) {
        particles[i] = spawn(false);
        continue;
      }
      var t = p.life / p.ttl;
      var a = p.maxA * Math.sin(Math.min(1, t) * Math.PI);
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = p.color + Math.max(0, a).toFixed(3) + ")";
      ctx.fill();
    }
  }

  init();
  frame();
  window.addEventListener("resize", init);
};
