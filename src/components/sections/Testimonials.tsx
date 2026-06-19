"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Reveal from "@/components/Reveal";

const QUOTES = [
  {
    quote:
      "Dorian didn't build us a website. They built the feeling of walking into our flagship — at midnight, with the lights just so.",
    name: "Élise Moreau",
    role: "CEO, Maison Noir",
  },
  {
    quote:
      "The most sophisticated digital craft we have ever commissioned. Every detail behaves as if it were inevitable.",
    name: "Karim Haddad",
    role: "Creative Director, Aurea",
  },
  {
    quote:
      "Our sales team now closes in the browser. The experience does the persuading before we say a word.",
    name: "Sofia Lindqvist",
    role: "Partner, Vellum Estates",
  },
];

export default function Testimonials() {
  const [i, setI] = useState(0);
  const q = QUOTES[i];

  return (
    <section className="relative mx-auto max-w-5xl px-6 py-32 text-center lg:px-10">
      <Reveal>
        <p className="mb-12 font-sans text-[11px] uppercase tracking-luxe text-gold">
          In their words
        </p>
      </Reveal>

      <div className="relative min-h-[260px]">
        <AnimatePresence mode="wait">
          <motion.blockquote
            key={i}
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -30 }}
            transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
          >
            <p className="font-display text-3xl font-light italic leading-snug text-bone md:text-5xl md:leading-tight">
              “{q.quote}”
            </p>
            <footer className="mt-10">
              <p className="font-sans text-sm text-gold">{q.name}</p>
              <p className="mt-1 font-sans text-[10px] uppercase tracking-wide2 text-smoke">
                {q.role}
              </p>
            </footer>
          </motion.blockquote>
        </AnimatePresence>
      </div>

      <div className="mt-12 flex items-center justify-center gap-3">
        {QUOTES.map((_, idx) => (
          <button
            key={idx}
            data-cursor="explore"
            aria-label={`Testimonial ${idx + 1}`}
            onClick={() => setI(idx)}
            className={`h-1.5 rounded-full transition-all duration-300 ${
              idx === i ? "w-8 bg-gold" : "w-1.5 bg-white/20 hover:bg-white/40"
            }`}
          />
        ))}
      </div>
    </section>
  );
}
