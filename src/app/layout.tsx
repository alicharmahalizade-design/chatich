import type { Metadata, Viewport } from "next";
import { Cormorant_Garamond, Inter } from "next/font/google";
import "./globals.css";

const display = Cormorant_Garamond({
  subsets: ["latin"],
  weight: ["300", "400", "500", "600", "700"],
  variable: "--font-display",
  display: "swap",
});

const sans = Inter({
  subsets: ["latin"],
  weight: ["300", "400", "500", "600"],
  variable: "--font-sans",
  display: "swap",
});

const SITE_URL = "https://dorian.example.com";

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: "Dorian — Beyond Luxury",
    template: "%s · Dorian",
  },
  description:
    "Dorian is a luxury experience studio crafting cinematic, architectural and immersive digital spaces. Beyond Luxury.",
  keywords: [
    "Dorian",
    "luxury",
    "cinematic",
    "interactive",
    "architecture",
    "premium design",
    "immersive experience",
  ],
  authors: [{ name: "Dorian" }],
  openGraph: {
    title: "Dorian — Beyond Luxury",
    description:
      "A cinematic, interactive luxury experience. Step into the building.",
    url: SITE_URL,
    siteName: "Dorian",
    type: "website",
    locale: "en_US",
  },
  twitter: {
    card: "summary_large_image",
    title: "Dorian — Beyond Luxury",
    description: "A cinematic, interactive luxury experience.",
  },
  robots: { index: true, follow: true },
};

export const viewport: Viewport = {
  themeColor: "#0a0a0b",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" className={`${display.variable} ${sans.variable}`}>
      <body>{children}</body>
    </html>
  );
}
