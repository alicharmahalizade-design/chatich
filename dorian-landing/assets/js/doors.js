/* Dorian — brass elevator doors: intro reveal, floor-change wipe, closing. */
window.Dorian = window.Dorian || {};

(function () {
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Set the intro doors closed immediately (hidden behind the preloader).
  window.Dorian.initDoors = function () {
    var intro = document.getElementById("introDoors");
    if (intro && !reduced) intro.classList.add("is-closed");
  };

  // Open the intro doors to reveal the hero.
  window.Dorian.openIntroDoors = function () {
    var intro = document.getElementById("introDoors");
    if (!intro) return Promise.resolve();
    if (reduced || !window.gsap) {
      intro.style.display = "none";
      return Promise.resolve();
    }
    return new Promise(function (resolve) {
      if (window.DorianSound) window.DorianSound.play("whoosh");
      gsap.to(intro.querySelector(".door--l"), {
        xPercent: -101,
        duration: 1.3,
        ease: "power3.inOut",
      });
      gsap.to(intro.querySelector(".door--r"), {
        xPercent: 101,
        duration: 1.3,
        ease: "power3.inOut",
        onComplete: function () {
          intro.style.display = "none";
          resolve();
        },
      });
    });
  };

  // Quick brass wipe used between elevator floors. onMid fires when shut.
  var sweeping = false;
  window.Dorian.elevatorDoorSweep = function (onMid) {
    var doors = document.getElementById("elevDoors");
    if (!doors || reduced || !window.gsap) {
      if (onMid) onMid();
      return;
    }
    if (sweeping) {
      if (onMid) onMid();
      return;
    }
    sweeping = true;
    var l = doors.querySelector(".door--l");
    var r = doors.querySelector(".door--r");
    var tl = gsap.timeline({
      onComplete: function () {
        sweeping = false;
      },
    });
    tl.to([l], { xPercent: 0, duration: 0.32, ease: "power2.in" }, 0)
      .to([r], { xPercent: 0, duration: 0.32, ease: "power2.in" }, 0)
      .add(function () {
        if (onMid) onMid();
      })
      .to([l], { xPercent: -101, duration: 0.42, ease: "power2.out" }, ">0.05")
      .to([r], { xPercent: 101, duration: 0.42, ease: "power2.out" }, "<");
  };

  // Closing scene: doors slide shut as the section enters, tagline appears.
  window.Dorian.initClosing = function () {
    var closing = document.getElementById("closing");
    if (!closing || !window.gsap || !window.ScrollTrigger) return;
    var doors = closing.querySelector(".doors");
    var line = closing.querySelector(".closing__line");

    if (reduced) {
      if (line) line.style.opacity = 1;
      return;
    }

    ScrollTrigger.create({
      trigger: closing,
      start: "top 60%",
      once: true,
      onEnter: function () {
        if (window.DorianSound) window.DorianSound.play("ding");
        gsap.to(doors.querySelector(".door--l"), {
          xPercent: 0,
          duration: 1.4,
          ease: "power3.inOut",
        });
        gsap.to(doors.querySelector(".door--r"), {
          xPercent: 0,
          duration: 1.4,
          ease: "power3.inOut",
        });
        gsap.to(line, { opacity: 1, duration: 1.2, delay: 1.1 });
      },
    });
  };
})();
