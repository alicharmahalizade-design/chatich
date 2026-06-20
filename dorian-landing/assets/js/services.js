/* Dorian — section 02: pin the section and scrub the 360° video with scroll.
   When the video reaches its end the pin releases and scrolling continues. */
window.Dorian = window.Dorian || {};
window.Dorian.initServices = function () {
  var section = document.getElementById("manifesto");
  var video = document.getElementById("servicesVideo");
  var label = document.getElementById("mvLabel");
  if (!section || !video || !window.gsap || !window.ScrollTrigger) return;

  var reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  var mobile = matchMedia("(max-width: 768px)").matches;

  // Mobile / reduced motion: don't pin or scrub (iOS scrubbing is janky).
  // Just play the short clip once when it scrolls into view.
  if (reduced || mobile) {
    ScrollTrigger.create({
      trigger: section,
      start: "top 65%",
      onEnter: function () {
        video.play().catch(function () {});
      },
      onLeaveBack: function () {
        try {
          video.pause();
          video.currentTime = 0;
        } catch (e) {}
      },
    });
    return;
  }

  try {
    video.pause();
  } catch (e) {}

  var lastT = -1;
  function build() {
    var dur = video.duration && isFinite(video.duration) ? video.duration : 4.1;

    ScrollTrigger.create({
      trigger: section,
      start: "top top",
      // Scroll distance mapped to the clip length (~340px per second).
      end: function () {
        return "+=" + Math.round(dur * 340);
      },
      pin: true,
      scrub: 0.35,
      anticipatePin: 1,
      invalidateOnRefresh: true,
      onUpdate: function (self) {
        var t = self.progress * (dur - 0.05);
        if (isFinite(t) && t >= 0 && Math.abs(t - lastT) > 0.012) {
          lastT = t;
          try {
            video.currentTime = t;
          } catch (e) {}
        }
        // The intro label fades out as the scrub begins.
        if (label) {
          label.style.opacity = String(Math.max(0, 1 - self.progress * 6));
        }
      },
    });

    // Nudge the first frame so the poster hands off to live video.
    try {
      video.currentTime = 0.01;
    } catch (e) {}
    ScrollTrigger.refresh();
  }

  if (video.readyState >= 1 && video.duration) build();
  else video.addEventListener("loadedmetadata", build, { once: true });
};
