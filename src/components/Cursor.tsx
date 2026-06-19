"use client";

import { useEffect, useRef, useState } from "react";

type CursorMode = "explore" | "enter" | "scroll" | "open" | "view";

const LABELS: Record<CursorMode, string> = {
  explore: "Explore",
  enter: "Enter",
  scroll: "Scroll",
  open: "Open",
  view: "View Project",
};

/**
 * Dynamic luxury cursor. A small dot tracks the pointer 1:1 while a larger ring
 * trails with easing. The ring expands and shows a contextual label when the
 * pointer is over an element carrying `data-cursor="<mode>"`.
 */
export default function Cursor() {
  const dotRef = useRef<HTMLDivElement>(null);
  const ringRef = useRef<HTMLDivElement>(null);
  const [mode, setMode] = useState<CursorMode | null>(null);
  const [active, setActive] = useState(false);

  useEffect(() => {
    const fine = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
    if (!fine) return;

    document.body.classList.add("custom-cursor");
    setActive(true);

    let mx = window.innerWidth / 2;
    let my = window.innerHeight / 2;
    let rx = mx;
    let ry = my;
    let raf = 0;

    const onMove = (e: MouseEvent) => {
      mx = e.clientX;
      my = e.clientY;
      if (dotRef.current) {
        dotRef.current.style.transform = `translate3d(${mx}px, ${my}px, 0) translate(-50%, -50%)`;
      }

      const target = (e.target as HTMLElement)?.closest?.(
        "[data-cursor]"
      ) as HTMLElement | null;
      if (target) {
        setMode((target.dataset.cursor as CursorMode) ?? "explore");
      } else {
        setMode(null);
      }
    };

    const render = () => {
      rx += (mx - rx) * 0.18;
      ry += (my - ry) * 0.18;
      if (ringRef.current) {
        ringRef.current.style.transform = `translate3d(${rx}px, ${ry}px, 0) translate(-50%, -50%)`;
      }
      raf = requestAnimationFrame(render);
    };

    const onDown = () => ringRef.current?.classList.add("cursor-down");
    const onUp = () => ringRef.current?.classList.remove("cursor-down");

    window.addEventListener("mousemove", onMove);
    window.addEventListener("mousedown", onDown);
    window.addEventListener("mouseup", onUp);
    raf = requestAnimationFrame(render);

    return () => {
      document.body.classList.remove("custom-cursor");
      window.removeEventListener("mousemove", onMove);
      window.removeEventListener("mousedown", onDown);
      window.removeEventListener("mouseup", onUp);
      cancelAnimationFrame(raf);
    };
  }, []);

  if (!active) return null;

  const labelled = mode !== null;

  return (
    <div aria-hidden className="pointer-events-none fixed inset-0 z-[100]">
      <div
        ref={dotRef}
        className="fixed left-0 top-0 h-1.5 w-1.5 rounded-full bg-gold mix-blend-difference"
      />
      <div
        ref={ringRef}
        className={`cursor-ring fixed left-0 top-0 flex items-center justify-center rounded-full border border-gold/70 transition-[width,height,background-color] duration-300 ease-out ${
          labelled ? "h-24 w-24 bg-gold/10" : "h-9 w-9"
        }`}
      >
        <span
          className={`whitespace-nowrap font-sans text-[10px] uppercase tracking-wide2 text-gold transition-opacity duration-200 ${
            labelled ? "opacity-100" : "opacity-0"
          }`}
        >
          {mode ? LABELS[mode] : ""}
        </span>
      </div>
      <style jsx global>{`
        .cursor-ring.cursor-down {
          scale: 0.8;
        }
      `}</style>
    </div>
  );
}
