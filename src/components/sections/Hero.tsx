"use client";

import dynamic from "next/dynamic";
import { useEffect, useRef, useState } from "react";
import { motion } from "framer-motion";
import type Lenis from "lenis";
import { useMagnetic } from "@/lib/useMagnetic";
import { sound } from "@/lib/sound";

const HeroScene = dynamic(() => import("@/components/three/HeroScene"), {
  ssr: false,
});

export default function Hero({ ready }: { ready: boolean }) {
  const [scene, setScene] = useState(0);
  const btnRef = useMagnetic<HTMLButtonElement>(0.45);
  const sectionRef = useRef<HTMLElement>(null);

  // Cinematic scene sequencing once the preloader has handed over.
  useEffect(() => {
    if (!ready) return;
    const t1 = setTimeout(() => setScene(1), 400);
    const t2 = setTimeout(() => setScene(2), 1500);
    const t3 = setTimeout(() => setScene(3), 2600);
    return () => {
      clearTimeout(t1);
      clearTimeout(t2);
      clearTimeout(t3);
    };
  }, [ready]);

  const goNext = () => {
    sound.play("ding");
    const lenis = (window as unknown as { lenis?: Lenis }).lenis;
    const el = document.querySelector("#floor-01");
    if (lenis && el) lenis.scrollTo(el as HTMLElement, { duration: 1.8 });
  };

  return (
    <section
      ref={sectionRef}
      className="relative h-screen w-full overflow-hidden"
      data-cursor="scroll"
    >
      {/* 3D environment — Scene 2 onward */}
      <motion.div
        className="absolute inset-0"
        initial={{ opacity: 0, scale: 1.1 }}
        animate={scene >= 2 ? { opacity: 1, scale: 1 } : { opacity: 0 }}
        transition={{ duration: 2, ease: [0.22, 1, 0.36, 1] }}
      >
        {ready && <HeroScene />}
      </motion.div>

      {/* Light rays */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div
          className="absolute left-1/2 top-0 h-[140%] w-[60%] -translate-x-1/2 opacity-40"
          style={{
            background:
              "conic-gradient(from 180deg at 50% 0%, transparent 40%, rgba(201,162,39,0.12) 50%, transparent 60%)",
          }}
        />
      </div>

      {/* Content */}
      <div className="relative z-10 flex h-full flex-col items-center justify-center px-6 text-center">
        {/* Scene 1 — monogram */}
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={
            scene >= 1 && scene < 3
              ? { opacity: 1, scale: 1 }
              : scene >= 3
                ? { opacity: 0, scale: 1.1, y: -30 }
                : {}
          }
          transition={{ duration: 1.1, ease: [0.22, 1, 0.36, 1] }}
          className="absolute"
        >
          <span className="font-display text-7xl font-light tracking-luxe text-bone md:text-9xl">
            D
          </span>
        </motion.div>

        {/* Scene 3 — title */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={scene >= 3 ? { opacity: 1 } : {}}
          transition={{ duration: 1.2 }}
          className="flex flex-col items-center"
        >
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={scene >= 3 ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 1, delay: 0.2 }}
            className="mb-6 font-sans text-[11px] uppercase tracking-luxe text-gold"
          >
            Luxury Experience Studio
          </motion.p>
          <h1 className="font-display text-6xl font-light leading-[0.95] text-bone md:text-8xl lg:text-[9rem]">
            <span className="block overflow-hidden">
              <motion.span
                className="block"
                initial={{ y: "110%" }}
                animate={scene >= 3 ? { y: 0 } : {}}
                transition={{ duration: 1.1, ease: [0.22, 1, 0.36, 1] }}
              >
                Beyond
              </motion.span>
            </span>
            <span className="block overflow-hidden">
              <motion.span
                className="block text-gradient-gold italic"
                initial={{ y: "110%" }}
                animate={scene >= 3 ? { y: 0 } : {}}
                transition={{ duration: 1.1, delay: 0.12, ease: [0.22, 1, 0.36, 1] }}
              >
                Luxury
              </motion.span>
            </span>
          </h1>
        </motion.div>

        {/* Scene 4 — scroll indicator / elevator button */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={scene >= 3 ? { opacity: 1, y: 0 } : {}}
          transition={{ duration: 1, delay: 0.8 }}
          className="absolute bottom-12 flex flex-col items-center gap-4"
        >
          <button
            ref={btnRef}
            data-cursor="enter"
            onClick={goNext}
            aria-label="Enter the experience"
            className="group relative flex h-16 w-16 items-center justify-center rounded-full border border-gold/40 transition-colors hover:border-gold"
          >
            <span className="absolute inset-0 rounded-full bg-gold/0 transition-colors group-hover:bg-gold/10" />
            <svg width="14" height="22" viewBox="0 0 14 22" fill="none">
              <motion.path
                d="M7 1 V21 M1 15 L7 21 L13 15"
                stroke="#c9a227"
                strokeWidth="1.2"
                animate={{ y: [0, 4, 0] }}
                transition={{ duration: 1.8, repeat: Infinity, ease: "easeInOut" }}
              />
            </svg>
          </button>
          <span className="font-sans text-[10px] uppercase tracking-wide2 text-smoke">
            Enter
          </span>
        </motion.div>
      </div>

      {/* bottom fade into elevator */}
      <div className="pointer-events-none absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-ink to-transparent" />
    </section>
  );
}
