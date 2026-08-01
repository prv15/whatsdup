import Link from "next/link";
import { SiteFooter, SiteHeader } from "./site-components";

const appUrl = process.env.NEXT_PUBLIC_APP_URL || "https://app.whatstheup.in";

export default function Home() {
  return (
    <main>
      <SiteHeader appUrl={appUrl} />
      <section className="hero shell">
        <div className="hero-copy">
          <span className="eyebrow"><i /> Official WhatsApp Business Platform</span>
          <h1>Campaigns your customers welcome. Clarity your team can trust.</h1>
          <p className="hero-lead">Connect your official Meta account, import opted-in contacts, launch approved template campaigns, and understand every delivery from one focused workspace.</p>
          <div className="hero-actions">
            <a className="button primary" href={appUrl}>Open WhatstheUp <span aria-hidden="true">↗</span></a>
            <a className="button secondary" href="#how-it-works">See how it works</a>
          </div>
          <div className="trust-row" aria-label="Platform assurances">
            <span><b>✓</b> Official Cloud API</span><span><b>✓</b> Consent aware</span><span><b>✓</b> Delivery tracked</span>
          </div>
        </div>
        <div className="product-stage" aria-label="WhatstheUp campaign dashboard preview">
          <div className="message-chip chip-one"><b>Campaign ready</b><span>1,248 valid recipients</span></div>
          <div className="message-chip chip-two"><b>Delivered</b><span>96.8% delivery rate</span></div>
          <div className="dashboard-card">
            <div className="dash-top"><div><small>Campaign overview</small><strong>Festival customer update</strong></div><span className="live-pill">Processing</span></div>
            <div className="metric-grid"><div><small>Queued</small><b>1,248</b></div><div><small>Delivered</small><b>1,087</b></div><div><small>Read</small><b>824</b></div></div>
            <div className="chart" aria-hidden="true"><span style={{height:"31%"}}/><span style={{height:"48%"}}/><span style={{height:"42%"}}/><span style={{height:"71%"}}/><span style={{height:"60%"}}/><span style={{height:"84%"}}/><span style={{height:"75%"}}/><span style={{height:"92%"}}/></div>
            <div className="activity"><span className="avatar">AS</span><div><b>Latest delivery confirmed</b><small>Customer update · just now</small></div><strong>Read</strong></div>
          </div>
        </div>
      </section>

      <section className="proof-strip"><div className="shell proof-inner"><p>Built for responsible business communication</p><div><span>Marketing</span><span>Utility</span><span>Authentication</span><span>Scheduled campaigns</span></div></div></section>

      <section className="section shell" id="how-it-works">
        <div className="section-heading"><span className="eyebrow"><i /> A calmer campaign workflow</span><h2>From Meta connection to measurable delivery.</h2><p>WhatstheUp keeps the essential journey simple while the platform handles consent, validation, queueing and status updates behind the scenes.</p></div>
        <div className="journey-grid">
          <article><span>01</span><div className="feature-icon">◎</div><h3>Connect officially</h3><p>Use Meta Embedded Signup to link your business portfolio, WABA and eligible phone number.</p></article>
          <article><span>02</span><div className="feature-icon">＋</div><h3>Bring opted-in contacts</h3><p>Import customers, record consent, organise groups and automatically suppress opt-outs.</p></article>
          <article><span>03</span><div className="feature-icon">▤</div><h3>Choose an approved template</h3><p>Create or customise compliant drafts, submit them to Meta and use only approved versions.</p></article>
          <article><span>04</span><div className="feature-icon">↗</div><h3>Send and understand</h3><p>Launch now or schedule, then follow queued, sent, delivered, read and failed outcomes.</p></article>
        </div>
      </section>

      <section className="section soft-section" id="features"><div className="shell split-section"><div><span className="eyebrow"><i /> Built for real operations</span><h2>Simple on the surface. Serious underneath.</h2><p>Every visible action is backed by tenant isolation, permission checks, queued processing and auditable events—giving your team a focused product without compromising the foundation.</p><ul className="check-list"><li><b>✓</b><span><strong>Consent-first audience controls</strong>Exclude opted-out, suppressed, invalid and incomplete recipients before sending.</span></li><li><b>✓</b><span><strong>Reliable scheduled delivery</strong>Campaigns run independently of the browser through queue workers and UTC scheduling.</span></li><li><b>✓</b><span><strong>Recipient-level reporting</strong>See status timestamps, Meta message IDs, failures and retry history.</span></li></ul></div><div className="readiness-card"><div className="readiness-head"><div><small>Launch readiness</small><strong>Your first campaign</strong></div><b>75%</b></div><div className="progress"><i /></div>{["Business profile complete","Meta account connected","Contacts imported","Template approval pending"].map((item,index)=><div className="ready-row" key={item}><span className={index<3?"done":"pending"}>{index<3?"✓":"•"}</span><p>{item}</p><small>{index<3?"Ready":"Action required"}</small></div>)}</div></div></section>

      <section className="section shell compliance-grid"><div className="compliance-copy"><span className="eyebrow"><i /> Responsible by design</span><h2>Official infrastructure. No shortcuts.</h2><p>WhatstheUp is designed exclusively for Meta’s official WhatsApp Business Platform Cloud API. It does not use QR sessions, WhatsApp Web automation, scraping or unofficial sending libraries.</p><Link href="/privacy" className="text-link">Read our privacy approach <span>→</span></Link></div><div className="principle-grid"><article><span>01</span><h3>Tenant isolated</h3><p>Business data is scoped by authenticated membership—not browser-supplied identifiers.</p></article><article><span>02</span><h3>Tokens protected</h3><p>Long-lived credentials remain server-side and are encrypted at rest.</p></article><article><span>03</span><h3>Actions audited</h3><p>Security and operational events retain an accountable history.</p></article><article><span>04</span><h3>Approvals respected</h3><p>Unavailable or unapproved Meta features remain disabled with clear explanations.</p></article></div></section>

      <section className="section pricing-section" id="pricing">
        <div className="shell">
          <div className="section-heading pricing-heading"><span className="eyebrow"><i /> Simple monthly plans</span><h2>Choose a plan that fits your customer conversations.</h2><p>Start with the essentials and move up as your audience and team grow. Every plan uses the official WhatsApp Business Platform Cloud API.</p></div>
          <div className="pricing-grid">
            <article className="price-card">
              <div className="plan-intro"><span>Launch</span><p>For small businesses beginning with structured WhatsApp campaigns.</p></div>
              <div className="plan-price"><b>₹999</b><span>/ month</span></div>
              <a className="button secondary plan-button" href="mailto:hello@whatstheup.in?subject=WhatstheUp%20Launch%20plan">Choose Launch</a>
              <ul><li>1 WhatsApp phone number</li><li>2 team members</li><li>Up to 5,000 contacts</li><li>10,000 campaign recipients/month</li><li>Contact import and groups</li><li>Template sync and campaigns</li><li>Delivery and read reporting</li><li>Email support</li></ul>
            </article>
            <article className="price-card featured">
              <div className="popular-pill">Most popular</div>
              <div className="plan-intro"><span>Growth</span><p>For growing teams running regular campaigns across larger audiences.</p></div>
              <div className="plan-price"><b>₹2,499</b><span>/ month</span></div>
              <a className="button primary plan-button" href="mailto:hello@whatstheup.in?subject=WhatstheUp%20Growth%20plan">Choose Growth</a>
              <ul><li>2 WhatsApp phone numbers</li><li>5 team members</li><li>Up to 25,000 contacts</li><li>75,000 campaign recipients/month</li><li>Everything in Launch</li><li>Tags and custom contact fields</li><li>Advanced campaign reporting</li><li>Priority email support</li></ul>
            </article>
            <article className="price-card">
              <div className="plan-intro"><span>Scale</span><p>For established operations needing higher limits and closer support.</p></div>
              <div className="plan-price"><b>Custom</b><span>built for your volume</span></div>
              <a className="button secondary plan-button" href="mailto:hello@whatstheup.in?subject=WhatstheUp%20Scale%20plan">Contact sales</a>
              <ul><li>Multiple WhatsApp phone numbers</li><li>Custom team and contact limits</li><li>Custom campaign volume</li><li>Everything in Growth</li><li>Roles and permission controls</li><li>Audit and operational exports</li><li>Guided onboarding</li><li>Dedicated priority support</li></ul>
            </article>
          </div>
          <p className="pricing-note">Prices exclude applicable taxes. Meta’s WhatsApp messaging charges are billed separately according to Meta’s current rates. Fair-use and platform policies apply.</p>
        </div>
      </section>

      <section className="cta-section shell"><div><span>Ready to build a better customer communication rhythm?</span><h2>Start with one clear, controlled campaign.</h2></div><a className="button light" href={appUrl}>Sign in to WhatstheUp <span aria-hidden="true">↗</span></a></section>
      <SiteFooter appUrl={appUrl}/>
    </main>
  );
}
