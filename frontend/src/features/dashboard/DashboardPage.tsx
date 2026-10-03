import { useQuery } from '@tanstack/react-query';
import { ArrowRight, CalendarClock, CheckCircle2, Circle, ExternalLink, FileCheck2, Gauge, MessageSquareText, ShieldAlert, Sparkles, Users, Zap } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../../services/api';
import type { WorkspaceDashboard } from '../../types/operations';

export function DashboardPage() {
  const dashboard = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get<{ data: WorkspaceDashboard }>('/dashboard')).data.data,
  });

  const metaStatus = useQuery({
    queryKey: ['meta-status'],
    queryFn: async () => (await api.get<{ data: { metaBusinessId?: string; status: string } }>('/meta/status')).data.data,
  });

  const data = dashboard.data;
  const quota = data?.quota;
  const metaBusinessId = metaStatus.data?.metaBusinessId;

  const cards = [
    ['Messages today', data?.metrics.messagesToday ?? 0, MessageSquareText],
    ['Contacts', data?.metrics.contacts ?? 0, Users],
    ['Approved templates', data?.metrics.approvedTemplates ?? 0, FileCheck2],
    ['Scheduled', data?.metrics.scheduledCampaigns ?? 0, CalendarClock],
  ] as const;

  const complete = [data?.metaStatus === 'connected', (data?.metrics.contacts ?? 0) > 0, (data?.metrics.approvedTemplates ?? 0) > 0];
  const progress = Math.round((complete.filter(Boolean).length / complete.length) * 100);

  const next =
    data?.metaStatus === 'connected'
      ? data?.metrics.contacts
        ? {
            to: '/templates',
            title: 'Choose an approved template',
            copy: 'Create or sync an approved Meta template before you launch your first campaign.',
          }
        : {
            to: '/contacts',
            title: 'Import your opted-in contacts',
            copy: 'Upload a simple CSV to prepare an eligible campaign audience.',
          }
      : {
          to: '/meta',
          title: 'Connect WhatsApp',
          copy: 'Use Meta’s official Embedded Signup to link your business portfolio, WABA and phone number.',
        };

  const recipientLimit = quota?.monthlyRecipients?.limit ?? null;
  const recipientUsed = quota?.monthlyRecipients?.used ?? 0;
  const recipientRemaining = recipientLimit !== null ? Math.max(0, recipientLimit - recipientUsed) : null;
  const recipientPercentage = quota?.monthlyRecipients?.percentage ?? 0;

  const contactsLimit = quota?.contacts?.limit ?? null;
  const contactsUsed = quota?.contacts?.used ?? 0;
  const contactsPercentage = quota?.contacts?.percentage ?? 0;

  return (
    <div className="space-y-8">
      <div>
        <p className="text-sm font-medium text-brand-700">Business workspace</p>
        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Your growth workspace</h1>
        <p className="mt-2 text-muted">Manage your official WhatsApp campaigns, monitor monthly quotas, and scale your audience.</p>
      </div>

      {/* Top 4 KPI Metrics */}
      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map(([label, value, Icon]) => (
          <article key={label} className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-muted">{label}</p>
                <p className="mt-3 text-3xl font-semibold text-ink">{value.toLocaleString()}</p>
              </div>
              <span className="rounded-xl bg-brand-50 p-2.5 text-brand-700">
                <Icon size={20} />
              </span>
            </div>
          </article>
        ))}
      </section>

      {/* Subscription Plan Quotas & Meta Tier Section */}
      <section className="grid gap-6 lg:grid-cols-3">
        {/* WhatsdUP Plan Allowance Card */}
        <article className="rounded-2xl border border-line bg-white p-6 shadow-card lg:col-span-2">
          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                  <Sparkles size={13} /> {quota?.plan?.name ?? 'Standard'} Plan
                </span>
                <span className="text-xs text-muted">Active Subscription</span>
              </div>
              <h2 className="mt-2 text-xl font-semibold text-ink">WhatsdUP Monthly Quota</h2>
            </div>
            <a
              href="mailto:support@whatsdup.in?subject=Upgrade%20WhatsdUP%20Plan"
              className="inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100"
            >
              <Zap size={14} /> Upgrade Plan
            </a>
          </div>

          <div className="mt-6 grid gap-6 sm:grid-cols-2">
            {/* Monthly Messages Allowance */}
            <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
              <div className="flex items-center justify-between text-xs">
                <span className="font-medium text-muted">Monthly Message Allowance</span>
                <span className="font-semibold text-ink">
                  {recipientUsed.toLocaleString()} / {recipientLimit !== null ? recipientLimit.toLocaleString() : 'Unlimited'}
                </span>
              </div>
              <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                  className={`h-full rounded-full transition-all duration-500 ${
                    recipientPercentage > 90 ? 'bg-red-500' : recipientPercentage > 75 ? 'bg-amber-500' : 'bg-brand-600'
                  }`}
                  style={{ width: `${Math.min(100, recipientPercentage)}%` }}
                />
              </div>
              <p className="mt-2 text-xs text-muted">
                {recipientRemaining !== null
                  ? `${recipientRemaining.toLocaleString()} messages remaining this billing cycle`
                  : 'Unlimited messages allowed on your current plan'}
              </p>
            </div>

            {/* Stored Contacts Allowance */}
            <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
              <div className="flex items-center justify-between text-xs">
                <span className="font-medium text-muted">Audience Contacts Limit</span>
                <span className="font-semibold text-ink">
                  {contactsUsed.toLocaleString()} / {contactsLimit !== null ? contactsLimit.toLocaleString() : 'Unlimited'}
                </span>
              </div>
              <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                  className={`h-full rounded-full transition-all duration-500 ${
                    contactsPercentage > 90 ? 'bg-red-500' : 'bg-brand-600'
                  }`}
                  style={{ width: `${Math.min(100, contactsPercentage)}%` }}
                />
              </div>
              <p className="mt-2 text-xs text-muted">
                {contactsLimit !== null
                  ? `${Math.max(0, contactsLimit - contactsUsed).toLocaleString()} contact slots remaining`
                  : 'Unlimited contact storage'}
              </p>
            </div>
          </div>

          <div className="mt-4 flex items-center justify-between border-t border-line/60 pt-4 text-xs text-muted">
            <span>WhatsdUP resets your monthly allowance at the start of each billing cycle.</span>
            <Link to="/campaigns" className="font-semibold text-brand-700 hover:underline">
              View campaigns →
            </Link>
          </div>
        </article>

        {/* Meta Daily Throughput & Verification Card */}
        <article className="rounded-2xl border border-line bg-white p-6 shadow-card flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                <Gauge size={13} /> Meta Daily Throttle
              </span>
              <span className="text-xs font-mono font-medium text-muted">24h Rolling</span>
            </div>

            <h3 className="mt-3 text-lg font-semibold text-ink">250 conversations / 24h</h3>
            <p className="mt-1 text-xs text-muted">
              Unverified Trial Tier. Meta restricts new numbers to 250 business-initiated contacts per 24 hours.
            </p>

            <div className="mt-4 rounded-xl bg-amber-50/70 p-3 border border-amber-200/50">
              <div className="flex items-start gap-2">
                <ShieldAlert size={16} className="mt-0.5 shrink-0 text-amber-700" />
                <div className="text-xs text-amber-800 leading-5">
                  <strong>Scale to 1,000+ msgs/day:</strong> Verify your business legal documents (GST/Incorporation) in Meta Security Center.
                </div>
              </div>
            </div>
          </div>

          <div className="mt-5">
            <a
              href={
                metaBusinessId
                  ? `https://business.facebook.com/settings/security?business_id=${metaBusinessId}`
                  : 'https://business.facebook.com/settings/security'
              }
              target="_blank"
              rel="noreferrer"
              className="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-slate-800"
            >
              Verify Business on Meta <ExternalLink size={13} />
            </a>
          </div>
        </article>
      </section>

      {/* Launch Readiness & Next Action */}
      <section className="grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
        <article className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-lg font-semibold">Launch readiness</h2>
              <p className="mt-1 text-sm text-muted">Complete the essentials before your first campaign.</p>
            </div>
            <div className="text-right">
              <p className="text-2xl font-semibold text-brand-700">{progress}%</p>
              <p className="text-xs text-muted">
                {complete.filter(Boolean).length} of 3
              </p>
            </div>
          </div>
          <div className="mt-5 h-2 overflow-hidden rounded-full bg-gray-100">
            <span className="block h-full bg-brand-500" style={{ width: `${progress}%` }} />
          </div>
          <div className="mt-6 divide-y divide-line">
            {[
              ['Connect your Meta account', complete[0], '/meta'],
              ['Import opted-in contacts', complete[1], '/contacts'],
              ['Have a Meta template approved', complete[2], '/templates'],
            ].map(([item, done, link]) => (
              <Link to={String(link)} className="flex items-center gap-3 py-3 hover:bg-slate-50/50" key={String(item)}>
                {done ? <CheckCircle2 size={19} className="text-brand-700" /> : <Circle size={19} className="text-gray-300" />}
                <span className="text-sm font-medium">{item}</span>
                <ArrowRight size={16} className="ml-auto text-muted" />
              </Link>
            ))}
          </div>
        </article>

        <article className="rounded-2xl bg-brand-800 p-6 text-white shadow-card flex flex-col justify-between">
          <div>
            <p className="text-sm font-medium text-brand-100">Next best action</p>
            <h2 className="mt-3 text-2xl font-semibold">{next.title}</h2>
            <p className="mt-3 leading-7 text-white/70">{next.copy}</p>
          </div>
          <Link
            to={next.to}
            className="mt-8 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 font-semibold text-brand-800 hover:bg-brand-400"
          >
            Continue <ArrowRight size={18} />
          </Link>
        </article>
      </section>
    </div>
  );
}
