"use client";

import { useState } from "react";
import { sound } from "@/lib/sound";

/** Floating control to enable/disable the ambient sound design. */
export default function SoundToggle() {
  const [on, setOn] = useState(false);

  return (
    <button
      type="button"
      data-cursor="explore"
      aria-pressed={on}
      aria-label={on ? "Mute sound" : "Enable sound"}
      onClick={() => setOn(sound.toggle())}
      className="fixed bottom-6 right-6 z-[90] flex items-center gap-2 rounded-full glass px-4 py-2 text-[10px] uppercase tracking-wide2 text-bone/80 transition-colors hover:text-gold"
    >
      <span className="flex h-4 items-end gap-[2px]">
        {[0, 1, 2, 3].map((i) => (
          <span
            key={i}
            className={`w-[2px] bg-gold transition-all duration-300 ${
              on ? "animate-[soundbar_1s_ease-in-out_infinite]" : "h-1"
            }`}
            style={{
              height: on ? undefined : "4px",
              animationDelay: `${i * 0.12}s`,
            }}
          />
        ))}
      </span>
      {on ? "Sound On" : "Sound Off"}
      <style jsx global>{`
        @keyframes soundbar {
          0%,
          100% {
            height: 4px;
          }
          50% {
            height: 14px;
          }
        }
      `}</style>
    </button>
  );
}
