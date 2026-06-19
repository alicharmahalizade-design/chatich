"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform } from "framer-motion";
import Reveal, { RevealText } from "@/components/Reveal";

export default function About() {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start end", "end start"],
  });

  const yImg = useTransform(scrollYProgress, [0, 1], ["-8%", "8%"]);
  const ySmall = useTransform(scrollYProgress, [0, 1], ["18%", "-18%"]);
  const lineDraw = useTransform(scrollYProgress, [0.1, 0.6], [0, 1]);

  return (
    <section
      id="floor-01"
      ref={ref}
      className="floor relative mx-auto min-h-screen max-w-7xl px-6 py-32 lg:px-10"
    >
      <div className="mb-16 flex items-center gap-6">
        <span className="font-sans text-[11px] uppercase tracking-luxe text-gold">
          01 — Brand Story
        </span>
        <div className="h-px flex-1 bg-white/10" />
      </div>

      <div className="grid items-center gap-16 lg:grid-cols-2">
        {/* Text column */}
        <div>
          <h2 className="font-display text-4xl font-light leading-tight text-bone md:text-6xl">
            <RevealText text="An architecture of" />
            <br />
            <span className="text-gradient-gold italic">
              <RevealText text="quiet excellence." delay={0.2} />
            </span>
          </h2>

          <Reveal delay={0.2} className="mt-8 max-w-md">
            <p className="font-sans text-base leading-relaxed text-smoke">
              Dorian is a studio for those who measure value in feeling, not
              noise. We compose digital spaces the way master architects shape
              light — every surface intentional, every transition earned.
            </p>
          </Reveal>

          <Reveal delay={0.35} className="mt-8 max-w-md">
            <p className="font-sans text-base leading-relaxed text-smoke">
              The result is presence. A brand that does not ask for attention,
              but commands it.
            </p>
          </Reveal>

          <div className="mt-12 flex gap-12">
            {[
              { k: "Est.", v: "2014" },
              { k: "Studios", v: "Paris · Dubai" },
              { k: "Discipline", v: "Experience" },
            ].map((s, i) => (
              <Reveal key={s.k} delay={0.4 + i * 0.1}>
                <div>
                  <p className="font-display text-2xl text-bone">{s.v}</p>
                  <p className="mt-1 font-sans text-[10px] uppercase tracking-wide2 text-smoke">
                    {s.k}
                  </p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>

        {/* Visual column with parallax + drawn architecture lines */}
        <div className="relative h-[480px] w-full">
          <motion.div
            style={{ y: yImg }}
            className="absolute inset-0 overflow-hidden rounded-sm"
          >
            <div
              className="h-full w-full"
              style={{
                background:
                  "linear-gradient(135deg, #1a1a1d 0%, #242428 40%, #111113 100%)",
              }}
            />
            {/* architectural svg */}
            <svg
              viewBox="0 0 400 480"
              className="absolute inset-0 h-full w-full"
              preserveAspectRatio="xMidYMid slice"
            >
              {[60, 140, 220, 300, 380].map((x) => (
                <motion.line
                  key={x}
                  x1={x}
                  y1={0}
                  x2={x}
                  y2={480}
                  stroke="rgba(201,162,39,0.25)"
                  strokeWidth={1}
                  style={{ pathLength: lineDraw }}
                />
              ))}
              {[120, 240, 360].map((y) => (
                <motion.line
                  key={y}
                  x1={0}
                  y1={y}
                  x2={400}
                  y2={y}
                  stroke="rgba(244,241,234,0.08)"
                  strokeWidth={1}
                  style={{ pathLength: lineDraw }}
                />
              ))}
              <motion.rect
                x={140}
                y={120}
                width={160}
                height={240}
                fill="none"
                stroke="rgba(201,162,39,0.5)"
                strokeWidth={1.5}
                style={{ pathLength: lineDraw }}
              />
            </svg>
          </motion.div>

          <motion.div
            style={{ y: ySmall }}
            className="glass absolute -bottom-8 -left-8 w-48 rounded-sm p-5"
          >
            <p className="font-display text-3xl text-gold">120+</p>
            <p className="mt-1 font-sans text-[10px] uppercase tracking-wide2 text-smoke">
              Experiences crafted
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
