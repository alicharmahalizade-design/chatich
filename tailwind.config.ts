import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        ink: {
          DEFAULT: "#0a0a0b",
          900: "#0a0a0b",
          800: "#111113",
          700: "#1a1a1d",
          600: "#242428",
        },
        gold: {
          DEFAULT: "#c9a227",
          light: "#e8cd7e",
          soft: "#d8bd6a",
          deep: "#9c7b1a",
        },
        bone: "#f4f1ea",
        smoke: "#9a958c",
      },
      fontFamily: {
        display: ["var(--font-display)", "serif"],
        sans: ["var(--font-sans)", "system-ui", "sans-serif"],
      },
      letterSpacing: {
        luxe: "0.35em",
        wide2: "0.18em",
      },
      keyframes: {
        shimmer: {
          "0%": { backgroundPosition: "200% 0" },
          "100%": { backgroundPosition: "-200% 0" },
        },
        floatY: {
          "0%, 100%": { transform: "translateY(0)" },
          "50%": { transform: "translateY(-12px)" },
        },
      },
      animation: {
        shimmer: "shimmer 6s linear infinite",
        floatY: "floatY 6s ease-in-out infinite",
      },
    },
  },
  plugins: [],
};

export default config;
