# DORIAN — Beyond Luxury

A cinematic, interactive luxury web experience. The visitor steps into a
private building and ascends floor by floor — each scroll a new chapter of the
brand. Built to produce a *wow* in the first seconds and to feel like a
multi-million-euro production.

## Experience map

| Floor | Section | Highlights |
|-------|---------|-----------|
| — | **Preloader** | Drawn monogram, gold hairline, loading counter (≤ 2s) |
| — | **Hero** | 4-scene cinematic intro, 3D gold monolith, "Beyond Luxury" reveal |
| 01 | **Brand Story** | Pinned parallax, word-by-word text reveal, drawn architecture |
| 02 | **Services** | 3D tilt glass cards with moving reflections |
| 03 | **Projects** | Full-screen horizontal scroll (GSAP pin) + luxury case-study modal |
| — | **Statistics** | Animated counters |
| — | **Testimonials** | Editorial magazine layout |
| 04 | **Innovation** | Interactive WebGL light field that follows the cursor |
| 05 | **Contact** | Glowing inputs, smart validation, success animation |
| — | **Footer** | Magnetic back-to-top |

A persistent **Elevator HUD** tracks the active floor — animating the floor
number, shifting the cabin light tint, and playing a soft *ding* on each
change.

## Global craft

- **Smooth scroll** — Lenis inertia, synced to GSAP ScrollTrigger.
- **Dynamic cursor** — dot + trailing ring with contextual modes (Explore,
  Enter, Scroll, Open, View Project).
- **Magnetic elements** — buttons and links are gently pulled toward the cursor.
- **Ambient motion** — floating sparkles, light rays, grain overlay, vignette.
- **Sound design** — synthesised (no audio files) elevator ding, hover ticks
  and transition swells; fully mutable via the floating toggle.
- **Accessibility & motion** — honours `prefers-reduced-motion`, semantic
  markup, keyboard-focusable controls.

## Tech stack

- [Next.js 14](https://nextjs.org/) (App Router) + TypeScript
- [GSAP](https://gsap.com/) + ScrollTrigger
- [Lenis](https://lenis.darkroom.engineering/) smooth scroll
- [Framer Motion](https://www.framer.com/motion/)
- [Three.js](https://threejs.org/) + [React Three Fiber](https://r3f.docs.pmnd.rs/) + drei
- [Tailwind CSS](https://tailwindcss.com/)

3D scenes are dynamically imported (`ssr: false`) and disabled or reduced on
mobile to keep the experience light.

## Getting started

```bash
npm install
npm run dev      # http://localhost:3000
```

```bash
npm run build    # production build
npm start        # serve the production build
```

## Performance notes

- 3D is lazy-loaded and excluded from the initial bundle.
- Heavy effects (horizontal scroll, innovation field) are gated to desktop.
- Fonts use `next/font` with `display: swap`; images prefer AVIF/WebP.
- All scroll-driven work runs through a single GSAP/Lenis RAF loop.
