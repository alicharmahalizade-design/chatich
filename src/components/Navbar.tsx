"use client";

import { useEffect, useState } from "react";
import type Lenis from "lenis";
import { useMagnetic } from "@/lib/useMagnetic";
import { sound } from "@/lib/sound";

const LINKS = [
  { label: "Story", target: "#floor-01" },
  { label: "Services", target: "#floor-02" },
  { label: "Projects", target: "#floor-03" },
  { label: "Innovation", target: "#floor-04" },
  { label: "Contact", target: "#floor-05" },
];

function scrollTo(target: string) {
  const lenis = (window as unknown as { lenis?: Lenis }).lenis;
  const el = document.querySelector(target);
  if (lenis && el) {
    lenis.scrollTo(el as HTMLElement, { offset: 0, duration: 1.6 });
  } else if (el) {
    el.scrollIntoView({ behavior: "smooth" });
  }
}

function NavLink({ label, target }: { label: string; target: string }) {
  const ref = useMagnetic<HTMLButtonElement>(0.3);
  return (
    <button
      ref={ref}
      data-cursor="explore"
      onClick={() => {
        sound.play("tick");
        scrollTo(target);
      }}
      className="group relative px-1 py-1 font-sans text-[11px] uppercase tracking-wide2 text-bone/70 transition-colors hover:text-gold"
    >
      {label}
      <span className="absolute -bottom-0.5 left-0 h-px w-0 bg-gold transition-all duration-500 group-hover:w-full" />
    </button>
  );
}

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const logoRef = useMagnetic<HTMLButtonElement>(0.25);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <header
      className={`fixed inset-x-0 top-0 z-[85] transition-all duration-500 ${
        scrolled ? "py-3 backdrop-blur-md" : "py-6"
      }`}
    >
      <div
        className={`pointer-events-none absolute inset-0 transition-opacity duration-500 ${
          scrolled ? "opacity-100" : "opacity-0"
        }`}
        style={{
          background:
            "linear-gradient(to bottom, rgba(10,10,11,0.85), transparent)",
        }}
      />
      <nav className="relative mx-auto flex max-w-7xl items-center justify-between px-6 lg:px-10">
        <button
          ref={logoRef}
          data-cursor="scroll"
          onClick={() => scrollTo("body")}
          className="font-display text-2xl font-semibold tracking-wide2 text-bone"
        >
          DORIAN
        </button>

        <div className="hidden items-center gap-8 md:flex">
          {LINKS.map((l) => (
            <NavLink key={l.target} {...l} />
          ))}
        </div>

        <button
          data-cursor="enter"
          onClick={() => {
            sound.play("tick");
            scrollTo("#floor-05");
          }}
          className="hidden rounded-full border border-gold/50 px-5 py-2 font-sans text-[11px] uppercase tracking-wide2 text-gold transition-all duration-300 hover:bg-gold hover:text-ink md:block"
        >
          Begin
        </button>
      </nav>
    </header>
  );
}
