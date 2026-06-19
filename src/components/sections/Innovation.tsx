"use client";

import dynamic from "next/dynamic";
import Reveal, { RevealText } from "@/components/Reveal";

const InnovationScene = dynamic(
  () => import("@/components/three/InnovationScene"),
  { ssr: false }
);

const PILLARS = [
  { title: "Real-time 3D", desc: "WebGL environments rendered at 60fps." },
  { title: "Motion systems", desc: "Choreographed transitions, never a cut." },
  { title: "Performance", desc: "Cinematic weight, instant feel." },
];

export default function Innovation() {
  return (
    <section
      id="floor-04"
      className="floor relative min-h-screen overflow-hidden"
    >
      {/* Interactive 3D backdrop */}
      <div className="absolute inset-0 z-0 hidden md:block">
        <InnovationScene />
      </div>
      <div className="pointer-events-none absolute inset-0 z-[1] bg-gradient-to-b from-ink via-ink/40 to-ink" />

      <div className="relative z-10 mx-auto flex min-h-screen max-w-7xl flex-col justify-center px-6 py-32 lg:px-10">
        <div className="mb-12 flex items-center gap-6">
          <span className="font-sans text-[11px] uppercase tracking-luxe text-gold">
            04 — Innovation
          </span>
          <div className="h-px flex-1 bg-white/10" />
        </div>

        <h2 className="max-w-3xl font-display text-4xl font-light leading-tight text-bone md:text-7xl">
          <RevealText text="Technology you feel," />
          <br />
          <span className="italic text-gradient-gold">
            <RevealText text="never see." delay={0.2} />
          </span>
        </h2>

        <Reveal delay={0.2}>
          <p className="mt-6 max-w-md font-sans text-base leading-relaxed text-smoke">
            Move your cursor — the light follows. Beneath every Dorian
            experience lies an engine tuned for depth, responsiveness and
            restraint.
          </p>
        </Reveal>

        <div className="mt-16 grid max-w-3xl gap-px overflow-hidden rounded-sm border border-white/10 sm:grid-cols-3">
          {PILLARS.map((p, i) => (
            <Reveal key={p.title} delay={0.1 * i}>
              <div className="glass h-full p-6">
                <h3 className="font-display text-xl text-bone">{p.title}</h3>
                <p className="mt-2 font-sans text-sm leading-relaxed text-smoke">
                  {p.desc}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
