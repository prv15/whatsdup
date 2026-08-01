import Link from "next/link";

export function SiteHeader({ appUrl }: { appUrl: string }) {
  return <header className="site-header"><div className="shell header-inner"><Link href="/" className="brand" aria-label="WhatstheUp home"><span>W</span>WhatstheUp</Link><nav aria-label="Main navigation"><Link href="/#how-it-works">How it works</Link><Link href="/#features">Platform</Link><Link href="/#pricing">Pricing</Link><Link href="/contact">Contact</Link></nav><a className="header-login" href={appUrl}>Sign in <span aria-hidden="true">↗</span></a></div></header>;
}

export function SiteFooter({ appUrl }: { appUrl: string }) {
  return <footer className="site-footer"><div className="shell footer-grid"><div><Link href="/" className="brand footer-brand"><span>W</span>WhatstheUp</Link><p>Official WhatsApp campaigns, made clear.</p><a className="footer-email" href="mailto:support@whatstheup.in">support@whatstheup.in</a></div><div><h3>Product</h3><Link href="/#how-it-works">How it works</Link><Link href="/#features">Platform</Link><Link href="/#pricing">Pricing</Link><a href={appUrl}>Sign in</a></div><div><h3>Company</h3><Link href="/contact">Contact</Link><a href="mailto:support@whatstheup.in">Support</a></div><div><h3>Legal &amp; Meta</h3><Link href="/privacy">Privacy policy</Link><Link href="/terms">Terms of service</Link><Link href="/data-deletion">Data deletion</Link></div></div><div className="shell footer-bottom"><span>© {new Date().getFullYear()} WhatstheUp. All rights reserved.</span><span>Built for the official WhatsApp Business Platform Cloud API.</span></div></footer>;
}

export function LegalPage({ eyebrow, title, intro, children }: { eyebrow: string; title: string; intro: string; children: React.ReactNode }) {
  const appUrl = process.env.NEXT_PUBLIC_APP_URL || "https://app.whatstheup.in";
  return <main><SiteHeader appUrl={appUrl}/><section className="legal-hero"><div className="shell narrow"><span className="eyebrow"><i /> {eyebrow}</span><h1>{title}</h1><p>{intro}</p><small>Last updated: 1 August 2026</small></div></section><article className="shell legal-content">{children}</article><SiteFooter appUrl={appUrl}/></main>;
}
