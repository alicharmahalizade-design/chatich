/* Dorian — ambient sound engine (Web Audio, synthesised, fully mutable). */
(function () {
  "use strict";

  var ctx = null;
  var master = null;
  var enabled = false;

  function ensure() {
    if (ctx) return ctx;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    ctx = new AC();
    master = ctx.createGain();
    master.gain.value = 0.0001;
    master.connect(ctx.destination);
    return ctx;
  }

  function blip(start, freq, dur, peak, type) {
    var osc = ctx.createOscillator();
    var g = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, start);
    g.gain.linearRampToValueAtTime(peak, start + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
    osc.connect(g);
    g.connect(master);
    osc.start(start);
    osc.stop(start + dur + 0.05);
  }

  var Sound = {
    get enabled() {
      return enabled;
    },
    enable: function () {
      if (!ensure()) return;
      if (ctx.state === "suspended") ctx.resume();
      enabled = true;
      master.gain.cancelScheduledValues(ctx.currentTime);
      master.gain.linearRampToValueAtTime(0.5, ctx.currentTime + 0.4);
    },
    disable: function () {
      enabled = false;
      if (!ctx) return;
      master.gain.cancelScheduledValues(ctx.currentTime);
      master.gain.linearRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
    },
    toggle: function () {
      if (enabled) this.disable();
      else this.enable();
      return enabled;
    },
    play: function (name) {
      if (!enabled || !ctx) return;
      var now = ctx.currentTime;
      if (name === "tick") {
        blip(now, 1200, 0.05, 0.12, "triangle");
      } else if (name === "ding") {
        blip(now, 880, 0.9, 0.22, "sine");
        blip(now + 0.18, 1320, 1.1, 0.2, "sine");
      } else if (name === "whoosh") {
        var osc = ctx.createOscillator();
        var g = ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(180, now);
        osc.frequency.exponentialRampToValueAtTime(540, now + 0.5);
        g.gain.setValueAtTime(0.0001, now);
        g.gain.linearRampToValueAtTime(0.1, now + 0.08);
        g.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);
        osc.connect(g);
        g.connect(master);
        osc.start(now);
        osc.stop(now + 0.65);
      }
    },
  };

  window.DorianSound = Sound;
})();
