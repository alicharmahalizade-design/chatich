"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Reveal from "@/components/Reveal";
import { useMagnetic } from "@/lib/useMagnetic";
import { sound } from "@/lib/sound";

type Field = "name" | "email" | "message";

function Input({
  id,
  label,
  type = "text",
  value,
  onChange,
  error,
  textarea,
}: {
  id: Field;
  label: string;
  type?: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
  textarea?: boolean;
}) {
  const [focus, setFocus] = useState(false);
  return (
    <div className="relative">
      <label
        htmlFor={id}
        className="mb-2 block font-sans text-[10px] uppercase tracking-wide2 text-smoke"
      >
        {label}
      </label>
      <div
        className="relative rounded-sm transition-shadow duration-300"
        style={{
          boxShadow: focus
            ? "0 0 0 1px rgba(201,162,39,0.6), 0 0 24px rgba(201,162,39,0.18)"
            : "0 0 0 1px rgba(255,255,255,0.08)",
        }}
      >
        {textarea ? (
          <textarea
            id={id}
            rows={4}
            value={value}
            onFocus={() => setFocus(true)}
            onBlur={() => setFocus(false)}
            onChange={(e) => onChange(e.target.value)}
            className="w-full resize-none bg-transparent px-4 py-3 font-sans text-bone outline-none"
          />
        ) : (
          <input
            id={id}
            type={type}
            value={value}
            onFocus={() => setFocus(true)}
            onBlur={() => setFocus(false)}
            onChange={(e) => onChange(e.target.value)}
            className="w-full bg-transparent px-4 py-3 font-sans text-bone outline-none"
          />
        )}
      </div>
      <AnimatePresence>
        {error && (
          <motion.p
            initial={{ opacity: 0, y: -4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="mt-1 font-sans text-[10px] tracking-wide text-red-400/80"
          >
            {error}
          </motion.p>
        )}
      </AnimatePresence>
    </div>
  );
}

export default function Contact() {
  const [form, setForm] = useState({ name: "", email: "", message: "" });
  const [errors, setErrors] = useState<Partial<Record<Field, string>>>({});
  const [sent, setSent] = useState(false);
  const btnRef = useMagnetic<HTMLButtonElement>(0.4);

  const validate = () => {
    const e: Partial<Record<Field, string>> = {};
    if (form.name.trim().length < 2) e.name = "Please share your name.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
      e.email = "A valid email is required.";
    if (form.message.trim().length < 10)
      e.message = "Tell us a little more (10+ characters).";
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const submit = (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!validate()) return;
    sound.play("ding");
    setSent(true);
  };

  return (
    <section
      id="floor-05"
      className="floor relative mx-auto max-w-7xl px-6 py-32 lg:px-10"
    >
      <div className="mb-16 flex items-center gap-6">
        <span className="font-sans text-[11px] uppercase tracking-luxe text-gold">
          05 — Contact
        </span>
        <div className="h-px flex-1 bg-white/10" />
      </div>

      <div className="grid gap-16 lg:grid-cols-2">
        <div>
          <Reveal>
            <h2 className="font-display text-4xl font-light leading-tight text-bone md:text-7xl">
              Let&apos;s build the
              <br />
              <span className="italic text-gradient-gold">remarkable.</span>
            </h2>
          </Reveal>
          <Reveal delay={0.15}>
            <p className="mt-8 max-w-sm font-sans text-base leading-relaxed text-smoke">
              Tell us about your brand and your ambition. We take on a limited
              number of engagements each year.
            </p>
          </Reveal>
          <Reveal delay={0.25}>
            <div className="mt-10 space-y-2 font-sans text-sm text-bone/80">
              <p>studio@dorian.com</p>
              <p>+33 1 84 80 00 00</p>
              <p className="text-smoke">Paris · Dubai</p>
            </div>
          </Reveal>
        </div>

        <div className="relative">
          <AnimatePresence mode="wait">
            {sent ? (
              <motion.div
                key="success"
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                className="glass flex h-full min-h-[420px] flex-col items-center justify-center rounded-sm p-10 text-center"
              >
                <motion.svg
                  width="72"
                  height="72"
                  viewBox="0 0 72 72"
                  initial="hidden"
                  animate="visible"
                >
                  <motion.circle
                    cx="36"
                    cy="36"
                    r="33"
                    fill="none"
                    stroke="#c9a227"
                    strokeWidth="1.5"
                    variants={{
                      hidden: { pathLength: 0 },
                      visible: {
                        pathLength: 1,
                        transition: { duration: 0.8 },
                      },
                    }}
                  />
                  <motion.path
                    d="M22 37 L32 47 L51 26"
                    fill="none"
                    stroke="#e8cd7e"
                    strokeWidth="2"
                    variants={{
                      hidden: { pathLength: 0 },
                      visible: {
                        pathLength: 1,
                        transition: { duration: 0.5, delay: 0.6 },
                      },
                    }}
                  />
                </motion.svg>
                <h3 className="mt-6 font-display text-3xl text-bone">
                  Message received.
                </h3>
                <p className="mt-2 font-sans text-sm text-smoke">
                  Our studio will be in touch within two business days.
                </p>
              </motion.div>
            ) : (
              <motion.form
                key="form"
                onSubmit={submit}
                noValidate
                className="space-y-6"
                exit={{ opacity: 0 }}
              >
                <Input
                  id="name"
                  label="Your name"
                  value={form.name}
                  onChange={(v) => setForm({ ...form, name: v })}
                  error={errors.name}
                />
                <Input
                  id="email"
                  label="Email"
                  type="email"
                  value={form.email}
                  onChange={(v) => setForm({ ...form, email: v })}
                  error={errors.email}
                />
                <Input
                  id="message"
                  label="Your vision"
                  textarea
                  value={form.message}
                  onChange={(v) => setForm({ ...form, message: v })}
                  error={errors.message}
                />
                <button
                  ref={btnRef}
                  data-cursor="enter"
                  type="submit"
                  className="group relative w-full overflow-hidden rounded-full border border-gold/50 px-8 py-4 font-sans text-[11px] uppercase tracking-luxe text-gold transition-colors hover:text-ink"
                >
                  <span className="absolute inset-0 -z-0 translate-y-full bg-gold transition-transform duration-500 group-hover:translate-y-0" />
                  <span className="relative z-10">Begin the conversation</span>
                </button>
              </motion.form>
            )}
          </AnimatePresence>
        </div>
      </div>
    </section>
  );
}
