"use client";

import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { sound } from "@/lib/sound";

if (typeof window !== "undefined") {
  gsap.registerPlugin(ScrollTrigger);
}

type Project = {
  title: string;
  category: string;
  year: string;
  grad: string;
  summary: string;
  scope: string[];
};

const PROJECTS: Project[] = [
  {
    title: "Maison Noir",
    category: "Hospitality",
    year: "2025",
    grad: "linear-gradient(135deg,#2a2118,#0f0d0a)",
    summary:
      "A flagship digital concierge for a private members' residence — booking, art and atmosphere in one cinematic flow.",
    scope: ["Strategy", "3D", "Web", "Motion"],
  },
  {
    title: "Aurea Watches",
    category: "Product",
    year: "2025",
    grad: "linear-gradient(135deg,#26211a,#101013)",
    summary:
      "Real-time configurator letting collectors orbit each timepiece in photoreal detail before commissioning.",
    scope: ["WebGL", "Configurator", "UX"],
  },
  {
    title: "Vellum Estates",
    category: "Real Estate",
    year: "2024",
    grad: "linear-gradient(135deg,#1d2622,#0c0f0e)",
    summary:
      "An interactive tower — visitors ascend floor by floor to explore residences, amenities and views.",
    scope: ["Brand", "Web", "3D Tour"],
  },
  {
    title: "Solène Parfums",
    category: "Beauty",
    year: "2024",
    grad: "linear-gradient(135deg,#2a1d22,#100c0e)",
    summary:
      "A scent told as light and motion — an editorial launch site that drove a 38% lift in conversion.",
    scope: ["Editorial", "Motion", "Web"],
  },
];

function ProjectCard({
  p,
  onOpen,
}: {
  p: Project;
  onOpen: () => void;
}) {
  return (
    <article
      data-cursor="view"
      onClick={onOpen}
      className="group relative flex h-[68vh] w-[80vw] flex-shrink-0 cursor-none flex-col justify-end overflow-hidden rounded-sm md:w-[46vw]"
      style={{ background: p.grad }}
    >
      {/* simulated cover video shimmer */}
      <div className="absolute inset-0 opacity-60 transition-transform duration-700 group-hover:scale-105">
        <div
          className="absolute inset-0"
          style={{
            background:
              "radial-gradient(80% 60% at 50% 40%, rgba(201,162,39,0.18), transparent 70%)",
          }}
        />
        <div className="absolute inset-0 animate-shimmer bg-[linear-gradient(110deg,transparent,rgba(255,255,255,0.05),transparent)] bg-[length:200%_100%]" />
      </div>

      <div className="absolute right-6 top-6 font-sans text-[10px] uppercase tracking-wide2 text-bone/50">
        {p.year}
      </div>

      <div className="relative z-10 p-8">
        <p className="mb-2 font-sans text-[10px] uppercase tracking-wide2 text-gold">
          {p.category}
        </p>
        <h3 className="font-display text-4xl font-light text-bone md:text-5xl">
          {p.title}
        </h3>
        <div className="mt-4 flex items-center gap-3 opacity-0 transition-opacity duration-500 group-hover:opacity-100">
          <span className="font-sans text-[11px] uppercase tracking-wide2 text-bone/80">
            View Case Study
          </span>
          <span className="text-gold">→</span>
        </div>
      </div>

      <div className="pointer-events-none absolute inset-0 border border-white/0 transition-colors duration-500 group-hover:border-gold/40" />
    </article>
  );
}

export default function Projects() {
  const sectionRef = useRef<HTMLDivElement>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState<Project | null>(null);

  useEffect(() => {
    const section = sectionRef.current;
    const track = trackRef.current;
    if (!section || !track) return;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const mobile = window.matchMedia("(max-width: 768px)").matches;
    if (reduced || mobile) return;

    const ctx = gsap.context(() => {
      const distance = track.scrollWidth - window.innerWidth;
      gsap.to(track, {
        x: -distance,
        ease: "none",
        scrollTrigger: {
          trigger: section,
          start: "top top",
          end: () => `+=${distance + window.innerHeight * 0.5}`,
          pin: true,
          scrub: 1,
          invalidateOnRefresh: true,
        },
      });
    }, section);

    return () => ctx.revert();
  }, []);

  return (
    <section
      id="floor-03"
      ref={sectionRef}
      className="floor relative min-h-screen overflow-hidden py-0"
    >
      <div className="flex h-screen flex-col justify-center">
        <div className="mb-10 flex items-center gap-6 px-6 lg:px-10">
          <span className="font-sans text-[11px] uppercase tracking-luxe text-gold">
            03 — Projects
          </span>
          <div className="h-px flex-1 bg-white/10" />
          <span className="hidden font-sans text-[10px] uppercase tracking-wide2 text-smoke md:block">
            Scroll to explore →
          </span>
        </div>

        <div
          ref={trackRef}
          className="flex items-center gap-6 px-6 will-change-transform lg:px-10"
        >
          <div className="flex-shrink-0 pr-4">
            <h2 className="w-[60vw] font-display text-5xl font-light leading-tight text-bone md:w-[28vw] md:text-7xl">
              Selected <span className="italic text-gradient-gold">work.</span>
            </h2>
          </div>
          {PROJECTS.map((p) => (
            <ProjectCard
              key={p.title}
              p={p}
              onOpen={() => {
                sound.play("transition");
                setOpen(p);
              }}
            />
          ))}
          <div className="flex w-[40vw] flex-shrink-0 items-center justify-center md:w-[20vw]">
            <div className="text-center">
              <p className="font-display text-3xl text-bone/40">+ more</p>
              <p className="mt-2 font-sans text-[10px] uppercase tracking-wide2 text-smoke">
                On request
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Luxury modal */}
      <AnimatePresence>
        {open && (
          <motion.div
            className="fixed inset-0 z-[110] flex items-center justify-center p-6"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
          >
            <motion.div
              className="absolute inset-0 bg-ink/80 backdrop-blur-md"
              onClick={() => setOpen(null)}
              data-cursor="explore"
            />
            <motion.div
              initial={{ y: 60, opacity: 0, scale: 0.96 }}
              animate={{ y: 0, opacity: 1, scale: 1 }}
              exit={{ y: 40, opacity: 0, scale: 0.96 }}
              transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
              className="glass relative z-10 w-full max-w-3xl overflow-hidden rounded-sm"
            >
              <div
                className="h-56 w-full"
                style={{ background: open.grad }}
              >
                <div className="flex h-full items-end p-8">
                  <h3 className="font-display text-5xl font-light text-bone">
                    {open.title}
                  </h3>
                </div>
              </div>
              <div className="p-8">
                <div className="flex flex-wrap items-center gap-x-8 gap-y-2 text-[10px] uppercase tracking-wide2 text-smoke">
                  <span className="text-gold">{open.category}</span>
                  <span>{open.year}</span>
                  <span>{open.scope.join(" · ")}</span>
                </div>
                <p className="mt-6 max-w-xl font-sans text-base leading-relaxed text-bone/80">
                  {open.summary}
                </p>
                <button
                  data-cursor="explore"
                  onClick={() => setOpen(null)}
                  className="mt-8 rounded-full border border-gold/50 px-6 py-2 font-sans text-[11px] uppercase tracking-wide2 text-gold transition-colors hover:bg-gold hover:text-ink"
                >
                  Close
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </section>
  );
}
