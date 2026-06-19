"use client";

import type Lenis from "lenis";
import { useMagnetic } from "@/lib/useMagnetic";

export default function Footer() {
  const topRef = useMagnetic<HTMLButtonElement>(0.3);

  const toTop = () => {
    const lenis = (window as unknown as { lenis?: Lenis }).lenis;
    if (lenis) lenis.scrollTo(0, { duration: 2 });
    else window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <footer className="relative border-t border-white/10 px-6 py-16 lg:px-10">
      <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-10 md:flex-row md:items-end">
        <div>
          <p className="font-display text-5xl font-light tracking-wide2 text-bone md:text-7xl">
            DORIAN
          </p>
          <p className="mt-4 max-w-xs font-sans text-[11px] uppercase tracking-wide2 text-smoke">
            Beyond Luxury — Cinematic experiences for brands with conviction.
          </p>
        </div>

        <div className="flex flex-col items-center gap-8 md:items-end">
          <div className="flex gap-8">
            {["Instagram", "Behance", "LinkedIn"].map((s) => (
              <a
                key={s}
                href="#"
                data-cursor="explore"
                className="font-sans text-[11px] uppercase tracking-wide2 text-bone/70 transition-colors hover:text-gold"
              >
                {s}
              </a>
            ))}
          </div>
          <button
            ref={topRef}
            data-cursor="scroll"
            onClick={toTop}
            className="flex items-center gap-2 font-sans text-[11px] uppercase tracking-wide2 text-gold"
          >
            Back to top ↑
          </button>
        </div>
      </div>

      <div className="mx-auto mt-12 flex max-w-7xl flex-col items-center justify-between gap-2 border-t border-white/5 pt-8 font-sans text-[10px] uppercase tracking-wide2 text-smoke md:flex-row">
        <span>© {new Date().getFullYear()} Dorian Studio</span>
        <span>Crafted with intention</span>
      </div>
    </footer>
  );
}
