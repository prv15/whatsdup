import { useQuery } from '@tanstack/react-query';
import { Building2, CheckCircle2, Phone, Radio, RefreshCw, Search, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { AdminMetaConnection } from '../../types/admin';

export function AdminMetaConnectionsPage() {
  const [search, setSearch] = useState('');

  const query = useQuery({
    queryKey: ['admin-meta-connections'],
    queryFn: async () => (await api.get<{ data: AdminMetaConnection[] }>('/admin/meta-connections')).data.data,
  });

  const connections = query.data ?? [];
  const filtered = connections.filter(
    (c) =>
      c.businessName.toLowerCase().includes(search.toLowerCase()) ||
      (c.displayPhoneNumber ?? '').includes(search) ||
      (c.wabaId ?? '').includes(search)
  );

  const activeCount = connections.filter((c) => c.connectionStatus === 'connected').length;
  const greenCount = connections.filter((c) => (c.qualityRating ?? '').toUpperCase() === 'GREEN').length;

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Platform Fleet</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">WhatsApp Connections</h1>
          <p className="mt-2 text-muted">Monitor all customer WABA accounts, verified phone numbers, and Meta webhook health.</p>
        </div>
        <button
          onClick={() => query.refetch()}
          disabled={query.isFetching}
          className="flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50"
        >
          <RefreshCw size={16} className={query.isFetching ? 'animate-spin' : ''} /> Refresh Fleet
        </button>
      </div>

      <section className="grid gap-4 sm:grid-cols-3">
        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <p className="text-xs uppercase tracking-wide text-muted">Total WhatsApp Assets</p>
          <p className="mt-2 text-3xl font-semibold">{connections.length}</p>
          <p className="mt-1 text-xs text-muted">Connected across all tenant workspaces</p>
        </article>
        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <p className="text-xs uppercase tracking-wide text-muted">Active Connected</p>
          <p className="mt-2 text-3xl font-semibold text-brand-700">{activeCount}</p>
          <p className="mt-1 text-xs text-muted">Fully authorized with Meta Cloud API</p>
        </article>
        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <p className="text-xs uppercase tracking-wide text-muted">High Quality Rating (Green)</p>
          <p className="mt-2 text-3xl font-semibold text-emerald-700">{greenCount}</p>
          <p className="mt-1 text-xs text-muted">Optimal delivery throughput</p>
        </article>
      </section>

      <section className="overflow-hidden rounded-2xl border border-line bg-white">
        <div className="border-b border-line p-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted" size={16} />
            <input
              type="text"
              placeholder="Search by business, phone number, WABA ID…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-xl border border-line py-2 pl-9 pr-4 text-sm"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Phone Number</th>
                <th className="px-5 py-3">WABA Details</th>
                <th className="px-5 py-3">Quality Rating</th>
                <th className="px-5 py-3">Webhook</th>
                <th className="px-5 py-3">Connected At</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {filtered.map((item) => (
                <tr key={item.id}>
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">
                      <span className="rounded-xl bg-brand-50 p-2 text-brand-700">
                        <Building2 size={18} />
                      </span>
                      <div>
                        <p className="font-medium">{item.businessName}</p>
                        <p className="text-xs text-muted">{item.businessSlug}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    {item.displayPhoneNumber ? (
                      <div>
                        <p className="font-semibold text-brand-900">{item.displayPhoneNumber}</p>
                        <p className="text-xs text-muted">{item.verifiedName ?? 'Verified Name Pending'}</p>
                        <span className="mt-0.5 inline-block text-[11px] text-muted">ID: {item.phoneNumberId}</span>
                      </div>
                    ) : (
                      <span className="text-xs text-muted">No phone registered</span>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <div>
                      <p className="font-medium text-xs">{item.wabaName ?? 'WABA Account'}</p>
                      <p className="text-xs text-muted font-mono">{item.wabaId ?? 'ID unavailable'}</p>
                      <span className="text-[11px] capitalize text-muted">{item.wabaReviewStatus ?? 'Approved'}</span>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <QualityBadge rating={item.qualityRating} />
                  </td>
                  <td className="px-5 py-4">
                    <span
                      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${
                        item.webhookStatus === 'active' || item.webhookStatus === 'subscribed'
                          ? 'bg-emerald-50 text-emerald-700'
                          : 'bg-amber-50 text-amber-700'
                      }`}
                    >
                      <Radio size={12} /> {item.webhookStatus}
                    </span>
                  </td>
                  <td className="px-5 py-4 text-xs text-muted">
                    {item.connectedAt ? new Date(item.connectedAt).toLocaleDateString() : 'N/A'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {query.isLoading && <p className="p-8 text-center text-muted">Loading WhatsApp fleet…</p>}
        {!query.isLoading && filtered.length === 0 && (
          <p className="p-8 text-center text-muted">No WhatsApp accounts match your search.</p>
        )}
      </section>
    </div>
  );
}

function QualityBadge({ rating }: { rating: string | null }) {
  const norm = (rating ?? '').toUpperCase();
  if (norm === 'GREEN') {
    return <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">High (Green)</span>;
  }
  if (norm === 'YELLOW') {
    return <span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Medium (Yellow)</span>;
  }
  if (norm === 'RED') {
    return <span className="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Low (Red)</span>;
  }
  return <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-muted">Normal</span>;
}