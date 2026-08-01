import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || "https://whatstheup.in"),
  title: { default: "WhatstheUp | Official WhatsApp Campaigns", template: "%s | WhatstheUp" },
  description: "Connect Meta, reach opted-in contacts with approved WhatsApp templates, and track every campaign delivery.",
  openGraph: { type: "website", siteName: "WhatstheUp", title: "WhatstheUp | Official WhatsApp Campaigns", description: "Official WhatsApp campaigns, made clear.", images: [{ url: "/og.png", width: 1742, height: 909, alt: "WhatstheUp — Official WhatsApp campaigns, made clear." }] },
  twitter: { card: "summary_large_image", title: "WhatstheUp", description: "Official WhatsApp campaigns, made clear.", images: ["/og.png"] },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="en"><body className={`${geistSans.variable} ${geistMono.variable}`}>{children}</body></html>; }
