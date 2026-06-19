"use client";

import { useEffect, useRef, useState } from "react";
import { useInView } from "framer-motion";

const STATS = [
  { value: 120, suffix: "+", label: "Experiences crafted" },
  { value: 38, suffix: "%", label: "Avg. conversion lift" },
  { value: 14, suffix: "", label: "International awards" },
  { value: 9, suffix: "", label: "Years of mastery" },
];

function Counter({
  value,
  suffix,
  start,
}: {
  value: number;
  suffix: string;
  start: boolean;
}) {
  const [n, setN] = useState(0);

  useEffect(() => {
    if (!start) return;
    let raf = 0;
    const t0 = performance.now();
    const dur = 1600;
    const loop = (now: number) => {
      const p = Math.min(1, (now - t0) / dur);
      const eased = 1 - Math.pow(1 - p, 3);
      setN(Math.round(eased * value));
      if (p < 1) raf = requestAnimationFrame(loop);
    };
    raf = requestAnimationFrame(loop);
    return () => cancelAnimationFrame(raf);
  }, [start, value]);

  return (
    <span className="tabular-nums">
      {n}
      {suffix}
    </span>
  );
}

export default function Stats() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, amount: 0.4 });

  return (
    <section className="relative mx-auto max-w-7xl px-6 py-24 lg:px-10">
      <div className="hairline mb-16" />
      <div ref={ref} className="grid grid-cols-2 gap-12 md:grid-cols-4">
        {STATS.map((s) => (
          <div key={s.label} className="text-center md:text-left">
            <p className="font-display text-5xl font-light text-gradient-gold md:text-7xl">
              <Counter value={s.value} suffix={s.suffix} start={inView} />
            </p>
            <p className="mt-3 font-sans text-[11px] uppercase tracking-wide2 text-smoke">
              {s.label}
            </p>
          </div>
        ))}
      </div>
      <div className="hairline mt-16" />
    </section>
  );
}
