"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";

/**
 * Custom preloader. The Dorian monogram is drawn with a stroke animation while
 * a gold hairline sweeps and the loading counter races to 100. Capped at ~2s.
 */
export default function Preloader({ onDone }: { onDone: () => void }) {
  const [count, setCount] = useState(0);
  const [hidden, setHidden] = useState(false);

  useEffect(() => {
    const start = performance.now();
    const total = 1800;
    let raf = 0;

    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / total);
      // Ease-out so the number decelerates into 100.
      const eased = 1 - Math.pow(1 - progress, 3);
      setCount(Math.round(eased * 100));
      if (progress < 1) {
        raf = requestAnimationFrame(tick);
      } else {
        setTimeout(() => {
          setHidden(true);
          setTimeout(onDone, 750);
        }, 200);
      }
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [onDone]);

  return (
    <AnimatePresence>
      {!hidden && (
        <motion.div
          className="fixed inset-0 z-[120] flex flex-col items-center justify-center bg-ink"
          exit={{
            clipPath: "inset(0 0 100% 0)",
            transition: { duration: 0.9, ease: [0.76, 0, 0.24, 1] },
          }}
        >
          <motion.svg
            width="120"
            height="120"
            viewBox="0 0 120 120"
            className="mb-10"
            initial="hidden"
            animate="visible"
          >
            <motion.path
              d="M30 25 H62 C84 25 96 40 96 60 C96 80 84 95 62 95 H30 Z M44 39 V81 H61 C74 81 82 73 82 60 C82 47 74 39 61 39 Z"
              fill="none"
              stroke="url(#goldgrad)"
              strokeWidth="1.5"
              variants={{
                hidden: { pathLength: 0, opacity: 0 },
                visible: {
                  pathLength: 1,
                  opacity: 1,
                  transition: { duration: 1.6, ease: "easeInOut" },
                },
              }}
            />
            <defs>
              <linearGradient id="goldgrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stopColor="#9c7b1a" />
                <stop offset="50%" stopColor="#e8cd7e" />
                <stop offset="100%" stopColor="#9c7b1a" />
              </linearGradient>
            </defs>
          </motion.svg>

          <div className="relative h-[1px] w-64 overflow-hidden bg-white/10">
            <motion.div
              className="absolute inset-y-0 left-0 bg-gradient-to-r from-gold-deep via-gold-light to-gold-deep"
              initial={{ width: "0%" }}
              animate={{ width: `${count}%` }}
              transition={{ ease: "linear" }}
            />
          </div>

          <div className="mt-5 flex w-64 items-center justify-between font-sans text-[11px] uppercase tracking-wide2 text-smoke">
            <span>Dorian</span>
            <span className="tabular-nums text-gold">{count}</span>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
