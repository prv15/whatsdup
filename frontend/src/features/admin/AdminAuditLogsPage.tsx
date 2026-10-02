import { useQuery } from '@tanstack/react-query';
import { Building2, Calendar, FileText, Filter, RefreshCw, Search, ShieldCheck, User } from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { AdminAuditLog } from '../../types/admin';

export function AdminAuditLogsPage() {
  const [actionFilter, setActionFilter] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  const query = useQuery({
    queryKey: ['admin-audit-logs', actionFilter],
    queryFn: async () =>
      (
        await api.get<{ data: AdminAuditLog[] }>('/admin/audit-logs', {
          params: { action: actionFilter || undefined, limit: 100 },
        })
      ).data.data,
  });

  const logs = query.data ?? [];
  const filtered = logs.filter(
    (l) =>
      (l.userName ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
      (l.userEmail ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
      (l.businessName ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
      l.action.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Governance & Security</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Platform Audit Trail</h1>
          <p className="mt-2 text-muted">Immutable history of administrative actions, plan updates, and tenant changes.</p>
        </div>
        <button
          onClick={() => query.refetch()}
          disabled={query.isFetching}
          className="flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50"
        >
          <RefreshCw size={16} className={query.isFetching ? 'animate-spin' : ''} /> Refresh
        </button>
      </div>

      <section className="overflow-hidden rounded-2xl border border-line bg-white">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-line p-4">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted" size={16} />
            <input
              type="text"
              placeholder="Search by actor, business, or action…"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full rounded-xl border border-line py-2 pl-9 pr-4 text-sm"
            />
          </div>

          <div className="flex items-center gap-2">
            <Filter size={16} className="text-muted" />
            <select
              value={actionFilter}
              onChange={(e) => setActionFilter(e.target.value)}
              className="rounded-xl border border-line px-3 py-2 text-sm"
            >
              <option value="">All Action Types</option>
              <option value="admin.business">Business Provisions</option>
              <option value="admin.subscription">Plan Assignments</option>
              <option value="admin.user">User Security Actions</option>
              <option value="admin.plan">Plan Catalog Edits</option>
              <option value="campaign.">Campaign Actions</option>
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">Timestamp</th>
                <th className="px-5 py-3">Actor</th>
                <th className="px-5 py-3">Action</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Subject</th>
                <th className="px-5 py-3">Metadata</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {filtered.map((log) => (
                <tr key={log.id}>
                  <td className="px-5 py-4 whitespace-nowrap text-xs text-muted">
                    <span className="font-mono">
                      {new Date(log.createdAt).toLocaleDateString()}{' '}
                      {new Date(log.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-2">
                      <span className="rounded-full bg-gray-100 p-1 text-gray-700">
                        <User size={13} />
                      </span>
                      <div>
                        <p className="font-medium text-xs">{log.userName ?? 'System'}</p>
                        <p className="text-[11px] text-muted">{log.userEmail ?? 'Internal'}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <span className="rounded-md bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800 font-mono">
                      {log.action}
                    </span>
                  </td>
                  <td className="px-5 py-4 text-xs font-medium">{log.businessName ?? 'Global / Platform'}</td>
                  <td className="px-5 py-4 text-xs text-muted">
                    <span className="capitalize">{log.subjectType}</span>
                    {log.subjectId && <p className="font-mono text-[10px] text-muted truncate max-w-[100px]">{log.subjectId}</p>}
                  </td>
                  <td className="px-5 py-4 max-w-xs text-xs">
                    <pre className="rounded bg-gray-50 p-1 text-[11px] text-gray-600 font-mono truncate">
                      {JSON.stringify(log.metadata)}
                    </pre>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {query.isLoading && <p className="p-8 text-center text-muted">Loading audit records…</p>}
        {!query.isLoading && filtered.length === 0 && (
          <p className="p-8 text-center text-muted">No audit logs found matching criteria.</p>
        )}
      </section>
    </div>
  );
}