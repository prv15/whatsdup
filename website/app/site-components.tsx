"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";

const navigation = [
  { href: "/#how-it-works", label: "How it works" },
  { href: "/#features", label: "Why WhatsdUP" },
  { href: "/#pricing", label: "Pricing" },
  { href: "/contact", label: "Contact" },
];

export function SiteHeader({ appUrl }: { appUrl: string }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!menuOpen) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeRef.current?.focus();
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMenuOpen(false);
        triggerRef.current?.focus();
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [menuOpen]);

  const closeMenu = () => setMenuOpen(false);

  return <>
    <header className="site-header">
      <div className="shell header-inner">
        <Link href="/" className="brand" aria-label="WhatsdUP home"><Image className="brand-logo" src="/whatsdup-logo.png" alt="WhatsdUP" width={500} height={150} priority /></Link>
        <nav className="desktop-nav" aria-label="Main navigation">{navigation.map((item) => <Link href={item.href} key={item.href}>{item.label}</Link>)}</nav>
        <div className="header-actions">
          <a className="header-login" href={appUrl}>Sign in <span aria-hidden="true">↗</span></a>
          <button ref={triggerRef} className="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded={menuOpen} aria-controls="mobile-navigation" onClick={() => setMenuOpen(true)}><span /><span /><span /></button>
        </div>
      </div>
    </header>
    <button className={`menu-overlay ${menuOpen ? "open" : ""}`} type="button" aria-label="Close navigation menu" tabIndex={menuOpen ? 0 : -1} onClick={closeMenu} />
    <aside id="mobile-navigation" className={`mobile-drawer ${menuOpen ? "open" : ""}`} aria-hidden={!menuOpen}>
      <div className="drawer-head"><Image src="/whatsdup-logo.png" alt="WhatsdUP" width={500} height={150} /><button ref={closeRef} type="button" aria-label="Close navigation menu" onClick={() => { closeMenu(); triggerRef.current?.focus(); }}>×</button></div>
      <nav aria-label="Mobile navigation">{navigation.map((item, index) => <Link href={item.href} key={item.href} onClick={closeMenu}><span>0{index + 1}</span>{item.label}<b aria-hidden="true">→</b></Link>)}</nav>
      <div className="drawer-message"><span>Official Meta Cloud API</span><strong>Simple campaigns. Powerful growth.</strong><p>Move your leads, delivery updates and customer relationships UP—with 0% markup on Meta messaging fees.</p></div>
    </aside>
  </>;
}

export function SiteFooter({ appUrl }: { appUrl: string }) {
  return <footer className="site-footer"><div className="shell footer-grid"><div><Link href="/" className="footer-brand" aria-label="WhatsdUP home"><Image className="footer-logo" src="/whatsdup-logo.png" alt="WhatsdUP" width={500} height={150} /></Link><p>Simple, official WhatsApp communication that helps every business move UP.</p><a className="footer-email" href="mailto:support@whatsdup.in">support@whatsdup.in</a></div><div><h3>Product</h3><Link href="/#how-it-works">How it works</Link><Link href="/#features">Why WhatsdUP</Link><Link href="/#pricing">Pricing</Link><a href={appUrl}>Sign in</a></div><div><h3>Company</h3><Link href="/contact">Contact</Link><a href="mailto:support@whatsdup.in">Support</a></div><div><h3>Legal &amp; Meta</h3><Link href="/privacy">Privacy policy</Link><Link href="/terms">Terms of service</Link><Link href="/data-deletion">Data deletion</Link></div></div><div className="shell footer-bottom"><span>© {new Date().getFullYear()} WhatsdUP. All rights reserved.</span><span>Built for the official WhatsApp Business Platform Cloud API.</span></div></footer>;
}

export function LegalPage({ eyebrow, title, intro, children }: { eyebrow: string; title: string; intro: string; children: React.ReactNode }) {
  const appUrl = process.env.NEXT_PUBLIC_APP_URL || "https://app.whatsdup.in";
  return <main><SiteHeader appUrl={appUrl}/><section className="legal-hero"><div className="shell narrow"><span className="eyebrow"><i /> {eyebrow}</span><h1>{title}</h1><p>{intro}</p><small>Last updated: 1 August 2026</small></div></section><article className="shell legal-content">{children}</article><SiteFooter appUrl={appUrl}/></main>;
}
