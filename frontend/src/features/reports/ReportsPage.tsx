import { useQuery } from '@tanstack/react-query';
import {
  AlertCircle,
  BarChart3,
  Calendar,
  CheckCircle2,
  Download,
  Eye,
  FileText,
  HelpCircle,
  MessageSquareText,
  Send,
  TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { WorkspaceReports } from '../../types/operations';

export function ReportsPage() {
  const [days, setDays] = useState<number>(30);

  const { data: reports, isLoading } = useQuery({
    queryKey: ['reports', days],
    queryFn: async () => (await api.get<{ data: WorkspaceReports }>(`/reports?days=${days}`)).data.data,
  });

  const summary = reports?.summary;
  const campaigns = reports?.campaigns ?? [];
  const failureReasons = reports?.failureReasons ?? [];

  const handleExportCsv = () => {
    if (!campaigns.length) return;

    const headers = ['Campaign Name', 'Template', 'Category', 'Status', 'Audience', 'Delivered', 'Read', 'Failed', 'Delivery Rate (%)', 'Read Rate (%)', 'Date Launched'];
    const rows = campaigns.map((c) => [
      `"${c.name.replace(/"/g, '""')}"`,
      `"${c.templateName.replace(/"/g, '""')}"`,
      c.templateCategory,
      c.status,
      c.recipientCount,
      c.deliveredCount,
      c.readCount,
      c.failedCount,
      `${c.deliveryRate}%`,
      `${c.readRate}%`,
      c.launchedAt ? new Date(c.launchedAt).toISOString() : '—',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `whatstheup-campaign-report-${days}d.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-8">
      {/* Header with Timeframe Filter & Export */}
      <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Analytics & Insights</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Campaign Reports</h1>
          <p className="mt-2 text-muted">
            Monitor real-time delivery rates, recipient read behavior, and campaign performance over time.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="inline-flex rounded-xl border border-line bg-white p-1 shadow-sm">
            {[
              { label: '7 Days', val: 7 },
              { label: '14 Days', val: 14 },
              { label: '30 Days', val: 30 },
              { label: '90 Days', val: 90 },
            ].map((option) => (
              <button
                key={option.val}
                onClick={() => setDays(option.val)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                  days === option.val ? 'bg-brand-700 text-white' : 'text-muted hover:text-ink'
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>

          <button
            onClick={handleExportCsv}
            disabled={!campaigns.length}
            className="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-ink shadow-sm hover:bg-slate-50 disabled:opacity-50"
          >
            <Download size={14} /> Export CSV
          </button>
        </div>
      </div>

      {isLoading ? (
        <div className="p-12 text-center text-muted">Loading analytics reports…</div>
      ) : (
        <>
          {/* Top 4 KPI Metrics */}
          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wider text-muted">Messages Sent</p>
                  <p className="mt-3 text-3xl font-semibold text-ink">
                    {(summary?.sentCount ?? 0).toLocaleString()}
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    Across {(summary?.totalAttempts ?? 0).toLocaleString()} recipients
                  </p>
                </div>
                <span className="rounded-xl bg-brand-50 p-2.5 text-brand-700">
                  <Send size={20} />
                </span>
              </div>
            </article>

            <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wider text-muted">Delivery Rate</p>
                  <p className="mt-3 text-3xl font-semibold text-emerald-600">
                    {summary?.deliveryRate ?? 0}%
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    {(summary?.deliveredCount ?? 0).toLocaleString()} confirmed delivered
                  </p>
                </div>
                <span className="rounded-xl bg-emerald-50 p-2.5 text-emerald-700">
                  <CheckCircle2 size={20} />
                </span>
              </div>
            </article>

            <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wider text-muted">Read / Open Rate</p>
                  <p className="mt-3 text-3xl font-semibold text-blue-600">
                    {summary?.readRate ?? 0}%
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    {(summary?.readCount ?? 0).toLocaleString()} recipients opened
                  </p>
                </div>
                <span className="rounded-xl bg-blue-50 p-2.5 text-blue-700">
                  <Eye size={20} />
                </span>
              </div>
            </article>

            <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wider text-muted">Failed Rate</p>
                  <p className="mt-3 text-3xl font-semibold text-amber-600">
                    {summary?.failureRate ?? 0}%
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    {(summary?.failedCount ?? 0).toLocaleString()} delivery errors
                  </p>
                </div>
                <span className="rounded-xl bg-amber-50 p-2.5 text-amber-700">
                  <AlertCircle size={20} />
                </span>
              </div>
            </article>
          </section>

          {/* Delivery Funnel Section */}
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h2 className="text-lg font-semibold text-ink">WhatsApp Delivery Funnel</h2>
                <p className="mt-1 text-xs text-muted">
                  Audience progression from campaign dispatch through to device read confirmation.
                </p>
              </div>
              <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                <TrendingUp size={13} /> High Deliverability
              </span>
            </div>

            <div className="grid gap-4 sm:grid-cols-4">
              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <span className="text-xs font-semibold text-muted uppercase">1. Dispatched</span>
                <p className="mt-2 text-2xl font-bold text-ink">
                  {(summary?.totalAttempts ?? 0).toLocaleString()}
                </p>
                <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                  <div className="h-full rounded-full bg-slate-600 w-full" />
                </div>
                <p className="mt-2 text-[11px] text-muted">100% of audience</p>
              </div>

              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <span className="text-xs font-semibold text-muted uppercase">2. Sent to Meta</span>
                <p className="mt-2 text-2xl font-bold text-ink">
                  {(summary?.sentCount ?? 0).toLocaleString()}
                </p>
                <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                  <div
                    className="h-full rounded-full bg-brand-600"
                    style={{
                      width: `${summary?.totalAttempts ? (summary.sentCount / summary.totalAttempts) * 100 : 0}%`,
                    }}
                  />
                </div>
                <p className="mt-2 text-[11px] text-muted">
                  {summary?.totalAttempts ? Math.round((summary.sentCount / summary.totalAttempts) * 100) : 0}% accepted by Meta
                </p>
              </div>

              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <span className="text-xs font-semibold text-muted uppercase">3. Delivered</span>
                <p className="mt-2 text-2xl font-bold text-emerald-600">
                  {(summary?.deliveredCount ?? 0).toLocaleString()}
                </p>
                <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                  <div
                    className="h-full rounded-full bg-emerald-500"
                    style={{ width: `${summary?.deliveryRate ?? 0}%` }}
                  />
                </div>
                <p className="mt-2 text-[11px] text-muted">{summary?.deliveryRate ?? 0}% reached device</p>
              </div>

              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <span className="text-xs font-semibold text-muted uppercase">4. Read by User</span>
                <p className="mt-2 text-2xl font-bold text-blue-600">
                  {(summary?.readCount ?? 0).toLocaleString()}
                </p>
                <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                  <div
                    className="h-full rounded-full bg-blue-500"
                    style={{ width: `${summary?.readRate ?? 0}%` }}
                  />
                </div>
                <p className="mt-2 text-[11px] text-muted">{summary?.readRate ?? 0}% read receipts</p>
              </div>
            </div>
          </section>

          {/* Campaign Performance Table */}
          <section className="space-y-4">
            <div>
              <h2 className="text-lg font-semibold text-ink">Campaign Breakdown</h2>
              <p className="mt-1 text-xs text-muted">
                Individual campaign deliverability results in the selected {days}-day window.
              </p>
            </div>

            <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
              <table className="w-full min-w-[700px] text-left text-sm">
                <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
                  <tr>
                    <th className="px-6 py-3.5">Campaign</th>
                    <th className="px-6 py-3.5">Category</th>
                    <th className="px-6 py-3.5">Date Launched</th>
                    <th className="px-6 py-3.5">Audience</th>
                    <th className="px-6 py-3.5">Delivered</th>
                    <th className="px-6 py-3.5">Read</th>
                    <th className="px-6 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {campaigns.length > 0 ? (
                    campaigns.map((camp) => (
                      <tr key={camp.id} className="hover:bg-slate-50/50">
                        <td className="px-6 py-4">
                          <p className="font-semibold text-ink">{camp.name}</p>
                          <p className="text-xs text-muted flex items-center gap-1 mt-0.5">
                            <FileText size={12} /> {camp.templateName}
                          </p>
                        </td>
                        <td className="px-6 py-4">
                          <span
                            className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${
                              camp.templateCategory === 'marketing'
                                ? 'bg-purple-50 text-purple-700'
                                : 'bg-blue-50 text-blue-700'
                            }`}
                          >
                            {camp.templateCategory}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-xs text-muted">
                          {camp.launchedAt ? (
                            <span className="flex items-center gap-1">
                              <Calendar size={13} />
                              {new Date(camp.launchedAt).toLocaleDateString()}
                            </span>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="px-6 py-4 font-semibold text-ink">
                          {camp.recipientCount.toLocaleString()}
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-2">
                            <span className="font-semibold text-ink">{camp.deliveredCount.toLocaleString()}</span>
                            <span className="rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-700">
                              {camp.deliveryRate}%
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-2">
                            <span className="font-semibold text-ink">{camp.readCount.toLocaleString()}</span>
                            <span className="rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-semibold text-blue-700">
                              {camp.readRate}%
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${
                              camp.status === 'completed'
                                ? 'bg-emerald-50 text-emerald-700'
                                : camp.status === 'processing'
                                ? 'bg-amber-50 text-amber-700'
                                : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {camp.status}
                          </span>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center text-muted">
                        <BarChart3 className="mx-auto text-slate-300 mb-2" size={32} />
                        No campaigns found in the selected {days}-day period.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

          {/* Delivery Failure Insights Card */}
          {failureReasons.length > 0 && (
            <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
              <div className="flex items-start gap-3">
                <div className="rounded-xl bg-amber-50 p-2.5 text-amber-700 mt-0.5">
                  <HelpCircle size={20} />
                </div>
                <div className="w-full">
                  <h3 className="text-base font-semibold text-ink">Delivery Error Diagnostics</h3>
                  <p className="mt-1 text-xs text-muted">
                    Meta reported these common issues for undelivered messages in this period:
                  </p>

                  <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    {failureReasons.map((item, idx) => (
                      <div
                        key={idx}
                        className="rounded-xl border border-line/70 bg-slate-50/60 p-3.5 flex items-start justify-between gap-3 text-xs"
                      >
                        <div>
                          <p className="font-semibold text-ink font-mono">{item.code}</p>
                          <p className="mt-1 text-muted leading-4">{item.message}</p>
                        </div>
                        <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 font-bold text-amber-900">
                          {item.count}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </section>
          )}
        </>
      )}
    </div>
  );
}