"use client";

import { useRef, useState } from "react";
import Reveal from "@/components/Reveal";

const SERVICES = [
  {
    n: "I",
    title: "Cinematic Web",
    desc: "Immersive, scroll-driven narratives engineered for awe and conversion.",
  },
  {
    n: "II",
    title: "Brand Architecture",
    desc: "Identity systems with the weight and permanence of built form.",
  },
  {
    n: "III",
    title: "3D & Motion",
    desc: "Real-time environments, product theatre and spatial storytelling.",
  },
  {
    n: "IV",
    title: "Experience Design",
    desc: "Interfaces that feel inevitable — considered to the final pixel.",
  },
];

function TiltCard({ s, i }: { s: (typeof SERVICES)[number]; i: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const [style, setStyle] = useState({ rx: 0, ry: 0, gx: 50, gy: 50 });

  const onMove = (e: React.MouseEvent) => {
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const px = (e.clientX - rect.left) / rect.width;
    const py = (e.clientY - rect.top) / rect.height;
    setStyle({
      rx: (py - 0.5) * -10,
      ry: (px - 0.5) * 12,
      gx: px * 100,
      gy: py * 100,
    });
  };

  const reset = () => setStyle({ rx: 0, ry: 0, gx: 50, gy: 50 });

  return (
    <Reveal delay={i * 0.08} variant="up">
      <div
        ref={ref}
        data-cursor="explore"
        onMouseMove={onMove}
        onMouseLeave={reset}
        className="group relative h-72 rounded-sm transition-transform duration-200 will-change-transform"
        style={{
          transform: `perspective(900px) rotateX(${style.rx}deg) rotateY(${style.ry}deg) translateY(${style.rx ? -8 : 0}px)`,
          transformStyle: "preserve-3d",
        }}
      >
        <div className="glass absolute inset-0 overflow-hidden rounded-sm">
          {/* moving glass reflection */}
          <div
            className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
            style={{
              background: `radial-gradient(420px circle at ${style.gx}% ${style.gy}%, rgba(232,205,126,0.18), transparent 45%)`,
            }}
          />
          <div className="flex h-full flex-col justify-between p-8">
            <span className="font-display text-3xl text-gold/70">{s.n}</span>
            <div>
              <h3 className="font-display text-2xl text-bone">{s.title}</h3>
              <p className="mt-3 font-sans text-sm leading-relaxed text-smoke">
                {s.desc}
              </p>
            </div>
          </div>
          <div className="absolute bottom-0 left-0 h-px w-0 bg-gold transition-all duration-500 group-hover:w-full" />
        </div>
      </div>
    </Reveal>
  );
}

export default function Services() {
  return (
    <section
      id="floor-02"
      className="floor relative mx-auto max-w-7xl px-6 py-32 lg:px-10"
    >
      <div className="mb-16 flex items-center gap-6">
        <span className="font-sans text-[11px] uppercase tracking-luxe text-gold">
          02 — Services
        </span>
        <div className="h-px flex-1 bg-white/10" />
      </div>

      <Reveal>
        <h2 className="mb-16 max-w-2xl font-display text-4xl font-light leading-tight text-bone md:text-6xl">
          Capabilities, rendered in <span className="italic text-gradient-gold">precision.</span>
        </h2>
      </Reveal>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {SERVICES.map((s, i) => (
          <TiltCard key={s.title} s={s} i={i} />
        ))}
      </div>
    </section>
  );
}
