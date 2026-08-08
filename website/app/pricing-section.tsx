"use client";

import { useState } from "react";

type Billing = "monthly" | "annual";
const plans = [
  {
    name: "Launch", description: "A simple, affordable start for local businesses, professionals and growing teams.",
    monthly: 999, annualMonthly: 799, annualTotal: 9588, annualSaving: 2400, featured: false,
    features: ["Up to 5,000 contacts", "12,000 campaign recipients/month", "3 team members", "1 WhatsApp phone number", "Unlimited campaign creation", "Official Meta Cloud API", "0% markup on Meta messaging fees", "Template sync and approval status", "Scheduled campaign sending", "Consent and opt-out suppression", "Delivery and read reporting", "CSV contact imports", "Email support"],
  },
  {
    name: "Growth", description: "More reach, sharper audience controls and deeper insight for ambitious businesses.",
    monthly: 2299, annualMonthly: 1839, annualTotal: 22068, annualSaving: 5520, featured: true,
    features: ["Up to 25,000 contacts", "100,000 campaign recipients/month", "10 team members", "2 WhatsApp phone numbers", "Everything in Launch", "0% markup on Meta messaging fees", "Tags, groups and custom fields", "Advanced campaign analytics", "Recipient-level status history", "Failure diagnostics and safe retries", "API and webhook access", "Audit activity history", "Priority support"],
  },
  {
    name: "Scale", description: "Flexible limits and guided support for agencies, larger teams and high-volume operations.",
    monthly: null, annualMonthly: null, annualTotal: null, annualSaving: null, featured: false,
    features: ["Custom contact and message volumes", "Multiple WhatsApp phone numbers", "Custom team limits", "Everything in Growth", "Advanced roles and permissions", "Operational and audit exports", "Custom API integration support", "Guided Meta onboarding", "Migration and launch assistance", "Priority issue escalation", "Dedicated success contact", "0% markup on Meta messaging charges"],
  },
] as const;

export function PricingSection() {
  const [billing, setBilling] = useState<Billing>("monthly");
  return <section className="section pricing-section" id="pricing"><div className="shell">
    <div className="section-heading pricing-heading"><span className="eyebrow"><i /> Transparent pricing for every stage</span><h2>Choose the plan that moves your business UP.</h2><p>From a first campaign to serious scale, every plan includes official Meta infrastructure, clear reporting and <strong>0% WhatsdUP markup</strong> on Meta&apos;s messaging fees.</p></div>
    <div className="pricing-disclosure"><div><b>0%</b><span><strong>Markup on every plan</strong>Pay Meta&apos;s applicable messaging rates without an extra WhatsdUP percentage.</span></div><div><b>i</b><span><strong>Meta charges are separate</strong>Your subscription covers WhatsdUP software. Meta WhatsApp messaging fees and any Meta advertising spend are billed separately.</span></div></div>
    <div className="billing-switch" role="group" aria-label="Billing frequency"><button type="button" className={billing === "monthly" ? "active" : ""} aria-pressed={billing === "monthly"} onClick={() => setBilling("monthly")}>Monthly</button><button type="button" className={billing === "annual" ? "active" : ""} aria-pressed={billing === "annual"} onClick={() => setBilling("annual")}>Annual <span>Save 20%</span></button></div>
    <div className="pricing-grid">{plans.map((plan) => <article className={`price-card ${plan.featured ? "featured" : ""}`} key={plan.name}>
      {plan.featured && <div className="popular-pill">Best value</div>}
      <div className="plan-intro"><span>{plan.name}</span><p>{plan.description}</p></div>
      <div className="plan-price">{plan.monthly === null ? <><b>Custom</b><span>for your volume</span></> : <><b>₹{(billing === "annual" ? plan.annualMonthly : plan.monthly).toLocaleString("en-IN")}</b><span>/ month</span></>}</div>
      <div className="billing-detail">{plan.monthly === null ? "Volume-based pricing with no Meta fee markup" : billing === "annual" ? <>₹{plan.annualTotal.toLocaleString("en-IN")} billed yearly <strong>Save ₹{plan.annualSaving.toLocaleString("en-IN")}</strong></> : "Billed monthly · change or cancel before renewal"}</div>
      <a className={`button ${plan.featured ? "primary" : "secondary"} plan-button`} href={`mailto:hello@whatsdup.in?subject=WhatsdUP%20${plan.name}%20plan`}>{plan.monthly === null ? "Talk to sales" : "Choose " + plan.name}</a>
      <div className="included-label">What&apos;s included</div><ul>{plan.features.map((feature) => <li key={feature}>{feature}</li>)}</ul>
    </article>)}</div>
    <p className="pricing-note">Prices exclude applicable taxes. WhatsdUP subscription fees do not include Meta&apos;s WhatsApp messaging charges or Meta advertising spend. Those costs are charged separately at Meta&apos;s applicable rates. WhatsdUP adds 0% markup. Platform eligibility, fair-use limits and Meta policies apply.</p>
  </div></section>;
}
