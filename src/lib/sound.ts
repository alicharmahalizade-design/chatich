"use client";

/**
 * Lightweight ambient sound engine built on the Web Audio API.
 * No external audio files required — tones are synthesised on the fly so the
 * experience stays light and every sound can be muted globally.
 */

type SoundName = "ding" | "tick" | "transition";

class SoundEngine {
  private ctx: AudioContext | null = null;
  private master: GainNode | null = null;
  private _enabled = false;

  get enabled() {
    return this._enabled;
  }

  private ensureContext() {
    if (typeof window === "undefined") return null;
    if (!this.ctx) {
      const AC =
        window.AudioContext ||
        (window as unknown as { webkitAudioContext: typeof AudioContext })
          .webkitAudioContext;
      if (!AC) return null;
      this.ctx = new AC();
      this.master = this.ctx.createGain();
      this.master.gain.value = 0.0001;
      this.master.connect(this.ctx.destination);
    }
    return this.ctx;
  }

  async enable() {
    const ctx = this.ensureContext();
    if (!ctx || !this.master) return;
    if (ctx.state === "suspended") await ctx.resume();
    this._enabled = true;
    this.master.gain.cancelScheduledValues(ctx.currentTime);
    this.master.gain.linearRampToValueAtTime(0.5, ctx.currentTime + 0.4);
  }

  disable() {
    if (!this.ctx || !this.master) {
      this._enabled = false;
      return;
    }
    this.master.gain.cancelScheduledValues(this.ctx.currentTime);
    this.master.gain.linearRampToValueAtTime(0.0001, this.ctx.currentTime + 0.3);
    this._enabled = false;
  }

  toggle() {
    if (this._enabled) this.disable();
    else void this.enable();
    return this._enabled;
  }

  play(name: SoundName) {
    if (!this._enabled || !this.ctx || !this.master) return;
    const ctx = this.ctx;
    const now = ctx.currentTime;

    if (name === "tick") {
      this.blip(now, 1200, 0.04, 0.12, "triangle");
      return;
    }

    if (name === "ding") {
      // Two-tone elevator chime.
      this.blip(now, 880, 0.9, 0.25, "sine");
      this.blip(now + 0.18, 1320, 1.1, 0.22, "sine");
      return;
    }

    if (name === "transition") {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(220, now);
      osc.frequency.exponentialRampToValueAtTime(660, now + 0.5);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.linearRampToValueAtTime(0.12, now + 0.08);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);
      osc.connect(gain);
      gain.connect(this.master);
      osc.start(now);
      osc.stop(now + 0.65);
    }
  }

  private blip(
    start: number,
    freq: number,
    duration: number,
    peak: number,
    type: OscillatorType
  ) {
    if (!this.ctx || !this.master) return;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.linearRampToValueAtTime(peak, start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(gain);
    gain.connect(this.master);
    osc.start(start);
    osc.stop(start + duration + 0.05);
  }
}

export const sound = new SoundEngine();
