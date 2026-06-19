"use client";

import { useEffect, useState } from "react";
import type Lenis from "lenis";
import Preloader from "@/components/Preloader";
import SmoothScroll from "@/components/SmoothScroll";
import Cursor from "@/components/Cursor";
import Navbar from "@/components/Navbar";
import SoundToggle from "@/components/SoundToggle";
import ElevatorHUD from "@/components/ElevatorHUD";
import Hero from "@/components/sections/Hero";
import About from "@/components/sections/About";
import Services from "@/components/sections/Services";
import Projects from "@/components/sections/Projects";
import Stats from "@/components/sections/Stats";
import Testimonials from "@/components/sections/Testimonials";
import Innovation from "@/components/sections/Innovation";
import Contact from "@/components/sections/Contact";
import Footer from "@/components/sections/Footer";

export default function Experience() {
  const [ready, setReady] = useState(false);

  // Lock scrolling while the preloader is on screen.
  useEffect(() => {
    const lenis = (window as unknown as { lenis?: Lenis }).lenis;
    if (!ready) {
      document.body.style.overflow = "hidden";
      lenis?.stop();
    } else {
      document.body.style.overflow = "";
      lenis?.start();
    }
  }, [ready]);

  return (
    <SmoothScroll>
      <Preloader onDone={() => setReady(true)} />
      <Cursor />
      <Navbar />
      <SoundToggle />
      <ElevatorHUD />

      <main className="relative z-10">
        <Hero ready={ready} />
        <About />
        <Services />
        <Projects />
        <Stats />
        <Testimonials />
        <Innovation />
        <Contact />
        <Footer />
      </main>

      <div className="vignette" aria-hidden />
      <div className="grain" aria-hidden />
    </SmoothScroll>
  );
}
