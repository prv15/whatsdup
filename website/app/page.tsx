import Link from "next/link";
import { SiteFooter, SiteHeader } from "./site-components";
import { PricingSection } from "./pricing-section";

const appUrl = process.env.NEXT_PUBLIC_APP_URL || "https://app.whatsdup.in";

export default function Home() {
  return (
    <main>
      <SiteHeader appUrl={appUrl} />
      <section className="hero shell">
        <div className="hero-copy">
          <span className="eyebrow"><i /> Powered by Meta&apos;s official WhatsApp Cloud API</span>
          <h1>Take every customer conversation <em>UP.</em></h1>
          <p className="hero-lead">Turn new leads into conversations, conversations into customers, and customers into lasting relationships. WhatsdUP makes official WhatsApp campaigns simple enough for anyone—and powerful enough to grow any business.</p>
          <div className="hero-actions">
            <a className="button primary" href={appUrl}>Start moving UP <span aria-hidden="true">↗</span></a>
            <a className="button secondary" href="#how-it-works">See how simple it is</a>
          </div>
          <div className="trust-row" aria-label="Platform assurances">
            <span><b>✓</b> Official Meta API</span><span><b>✓</b> 0% fee markup</span><span><b>✓</b> No unofficial tools</span>
          </div>
        </div>
        <div className="product-stage" aria-label="WhatsdUP campaign dashboard preview">
          <div className="message-chip chip-one"><b>Leads moving UP</b><span>+328 new conversations</span></div>
          <div className="message-chip chip-two"><b>Customers reached</b><span>96.8% delivered</span></div>
          <div className="dashboard-card">
            <div className="dash-top"><div><small>Growth campaign</small><strong>Turn interest into action</strong></div><span className="live-pill">Live</span></div>
            <div className="metric-grid"><div><small>Reached</small><b>1,248</b></div><div><small>Delivered</small><b>1,087</b></div><div><small>Read</small><b>824</b></div></div>
            <div className="chart" aria-hidden="true"><span style={{height:"31%"}}/><span style={{height:"48%"}}/><span style={{height:"42%"}}/><span style={{height:"71%"}}/><span style={{height:"60%"}}/><span style={{height:"84%"}}/><span style={{height:"75%"}}/><span style={{height:"92%"}}/></div>
            <div className="activity"><span className="avatar">AS</span><div><b>Latest delivery confirmed</b><small>Customer update · just now</small></div><strong>Read</strong></div>
          </div>
        </div>
      </section>

      <section className="proof-strip"><div className="shell proof-inner"><p>One simple platform. Every stage of growth.</p><div><span>Lead generation</span><span>Faster follow-ups</span><span>Delivery updates</span><span>Customer retention</span></div></div></section>

      <section className="section shell" id="how-it-works">
        <div className="section-heading"><span className="eyebrow"><i /> From setup to send—without the struggle</span><h2>Four clear steps to move your business UP.</h2><p>WhatsdUP keeps every step easy to understand while handling the serious work—official Meta connection, consent checks, reliable sending and delivery tracking—behind the scenes.</p></div>
        <div className="journey-grid">
          <article><span>01</span><div className="feature-icon">◎</div><h3>Connect the official way</h3><p>Link your Meta business portfolio, WhatsApp account and eligible number through Embedded Signup.</p></article>
          <article><span>02</span><div className="feature-icon">＋</div><h3>Bring your audience together</h3><p>Import opted-in leads and customers, organise useful segments, and honour opt-outs automatically.</p></article>
          <article><span>03</span><div className="feature-icon">▤</div><h3>Create with confidence</h3><p>Use approved WhatsApp templates for offers, reminders, updates and follow-ups that feel timely.</p></article>
          <article><span>04</span><div className="feature-icon">↗</div><h3>Send, learn and grow</h3><p>Launch now or schedule ahead, then see delivery, reads and failures clearly—recipient by recipient.</p></article>
        </div>
      </section>

      <section className="section soft-section" id="features"><div className="shell split-section"><div><span className="eyebrow"><i /> Easy for every business</span><h2>Simple to use. Powerful enough to keep you growing.</h2><p>You should not need a technical team to run effective WhatsApp communication. WhatsdUP gives local shops, service businesses, growing brands and larger teams one clear workspace to attract, inform and retain customers.</p><ul className="check-list"><li><b>↑</b><span><strong>UP your lead generation</strong>Turn enquiries and opted-in audiences into timely, organised follow-up campaigns.</span></li><li><b>↑</b><span><strong>UP your delivery experience</strong>Share confirmations, reminders and status updates while tracking every message outcome.</span></li><li><b>↑</b><span><strong>UP your customer retention</strong>Stay relevant with useful updates, repeat-purchase campaigns and thoughtful re-engagement.</span></li></ul></div><div className="readiness-card"><div className="readiness-head"><div><small>Campaign readiness</small><strong>Ready without the guesswork</strong></div><b>75%</b></div><div className="progress"><i /></div>{["Business profile complete","Official Meta account connected","Opted-in contacts imported","Approved template selected"].map((item,index)=><div className="ready-row" key={item}><span className={index<3?"done":"pending"}>{index<3?"✓":"•"}</span><p>{item}</p><small>{index<3?"Ready":"Next step"}</small></div>)}</div></div></section>

      <section className="section shell compliance-grid"><div className="compliance-copy"><span className="eyebrow"><i /> Grow on official ground</span><h2>Confidence without risky shortcuts.</h2><p>WhatsdUP is built exclusively on Meta&apos;s official WhatsApp Business Platform Cloud API—never QR sessions, scraping, WhatsApp Web automation or unofficial sending libraries. That removes the avoidable account risk associated with unofficial tools and keeps your communication aligned with Meta&apos;s platform.</p><p className="compliance-note">Account quality still depends on consent, useful content and Meta policy compliance. No platform can guarantee immunity from restrictions—but WhatsdUP gives you the right, official foundation.</p><Link href="/privacy" className="text-link">Read our responsible approach <span>→</span></Link></div><div className="principle-grid"><article><span>01</span><h3>Official connection</h3><p>Connect through Meta Embedded Signup and use the supported Cloud API route.</p></article><article><span>02</span><h3>Consent respected</h3><p>Suppress opted-out, invalid and ineligible recipients before a campaign is sent.</p></article><article><span>03</span><h3>Templates approved</h3><p>Send only approved WhatsApp templates, with their current status kept visible.</p></article><article><span>04</span><h3>Every result clear</h3><p>Track queued, sent, delivered, read and failed outcomes without technical guesswork.</p></article></div></section>

      <PricingSection />

      <section className="cta-section shell"><div><span>One official platform. More momentum at every customer touchpoint.</span><h2>It&apos;s time to move your business UP.</h2></div><a className="button light" href={appUrl}>Open WhatsdUP <span aria-hidden="true">↗</span></a></section>
      <SiteFooter appUrl={appUrl}/>
    </main>
  );
}
