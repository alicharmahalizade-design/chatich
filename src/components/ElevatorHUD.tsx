"use client";

import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { sound } from "@/lib/sound";

const FLOORS = [
  { id: "floor-01", n: "01", name: "Brand Story", tint: "201, 162, 39" },
  { id: "floor-02", n: "02", name: "Services", tint: "120, 150, 180" },
  { id: "floor-03", n: "03", name: "Projects", tint: "180, 110, 90" },
  { id: "floor-04", n: "04", name: "Innovation", tint: "120, 200, 170" },
  { id: "floor-05", n: "05", name: "Contact", tint: "201, 162, 39" },
];

/**
 * Persistent elevator interface. As the visitor scrolls, the active floor is
 * derived from which floor section dominates the viewport. On each change the
 * floor number animates, the cabin light tint shifts and a soft ding plays.
 */
export default function ElevatorHUD() {
  const [active, setActive] = useState(-1);
  const [visible, setVisible] = useState(false);
  const prev = useRef(-1);

  useEffect(() => {
    const sections = FLOORS.map((f) => document.getElementById(f.id)).filter(
      Boolean
    ) as HTMLElement[];
    if (!sections.length) return;

    const ratios = new Map<string, number>();

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) =>
          ratios.set(e.target.id, e.isIntersecting ? e.intersectionRatio : 0)
        );
        let bestId = "";
        let best = 0;
        ratios.forEach((r, id) => {
          if (r > best) {
            best = r;
            bestId = id;
          }
        });
        const idx = FLOORS.findIndex((f) => f.id === bestId);
        if (idx !== -1 && best > 0.15) {
          setActive(idx);
          setVisible(true);
        } else if (best <= 0.05) {
          setVisible(false);
        }
      },
      { threshold: [0, 0.15, 0.35, 0.6, 0.85, 1] }
    );

    sections.forEach((s) => observer.observe(s));
    return () => observer.disconnect();
  }, []);

  // Side effects on floor change: ding + tint.
  useEffect(() => {
    if (active === -1) return;
    if (prev.current !== -1 && prev.current !== active) {
      sound.play("ding");
    }
    prev.current = active;
    document.documentElement.style.setProperty(
      "--floor-tint",
      FLOORS[active].tint
    );
  }, [active]);

  const floor = active === -1 ? FLOORS[0] : FLOORS[active];

  return (
    <>
      {/* Ambient cabin light that follows the active floor */}
      <div
        className="pointer-events-none fixed inset-0 z-0 transition-opacity duration-1000"
        style={{
          opacity: visible ? 1 : 0,
          background: `radial-gradient(120% 90% at 50% -10%, rgba(var(--floor-tint, 201,162,39), 0.10), transparent 60%)`,
        }}
      />

      <AnimatePresence>
        {visible && (
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            transition={{ duration: 0.6 }}
            className="fixed left-6 top-1/2 z-[80] hidden -translate-y-1/2 lg:block"
          >
            <div className="flex items-stretch gap-4">
              {/* Vertical rail with the cabin position */}
              <div className="relative w-px bg-white/10" style={{ height: 200 }}>
                <motion.div
                  className="absolute left-1/2 -translate-x-1/2 rounded-full"
                  animate={{ top: `${(active / (FLOORS.length - 1)) * 100}%` }}
                  transition={{ type: "spring", stiffness: 120, damping: 20 }}
                  style={{
                    width: 7,
                    height: 7,
                    marginTop: -3,
                    background: "#c9a227",
                    boxShadow: "0 0 12px 2px rgba(201,162,39,0.6)",
                  }}
                />
              </div>

              <div className="flex flex-col justify-between py-1">
                <div className="flex items-baseline gap-3">
                  <div className="relative h-12 w-12 overflow-hidden">
                    <AnimatePresence mode="popLayout">
                      <motion.span
                        key={floor.n}
                        initial={{ y: "100%", opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        exit={{ y: "-100%", opacity: 0 }}
                        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                        className="absolute inset-0 font-display text-5xl font-light text-gold"
                      >
                        {floor.n}
                      </motion.span>
                    </AnimatePresence>
                  </div>
                </div>
                <div className="mt-auto">
                  <p className="font-sans text-[9px] uppercase tracking-wide2 text-smoke">
                    Floor
                  </p>
                  <AnimatePresence mode="wait">
                    <motion.p
                      key={floor.name}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -8 }}
                      transition={{ duration: 0.4 }}
                      className="font-sans text-[11px] uppercase tracking-wide2 text-bone"
                    >
                      {floor.name}
                    </motion.p>
                  </AnimatePresence>
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}
