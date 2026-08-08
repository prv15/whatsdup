import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || "https://whatsdup.in"),
  title: { default: "WhatsdUP | Official WhatsApp Campaigns", template: "%s | WhatsdUP" },
  description: "Move your lead generation, delivery experience and customer retention UP with simple campaigns on Meta's official WhatsApp Cloud API.",
  icons: {
    icon: [{ url: "/favicon.png", type: "image/png", sizes: "100x100" }],
    shortcut: "/favicon.png",
    apple: "/favicon.png",
  },
  openGraph: { type: "website", siteName: "WhatsdUP", title: "WhatsdUP | Move Every Customer Conversation UP", description: "Simple, powerful WhatsApp campaigns built on Meta's official Cloud API—with 0% markup on messaging fees.", images: [{ url: "/og-growth.png", width: 1734, height: 907, alt: "WhatsdUP — Move every customer conversation UP." }] },
  twitter: { card: "summary_large_image", title: "WhatsdUP | Move Every Customer Conversation UP", description: "Simple, powerful campaigns on Meta's official WhatsApp Cloud API.", images: ["/og-growth.png"] },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="en"><body>{children}</body></html>; }
