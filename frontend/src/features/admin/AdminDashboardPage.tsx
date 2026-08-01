import { useQuery } from '@tanstack/react-query';
import { Activity, Building2, CircleAlert, ListTodo, ShieldCheck, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../../services/api';
import type { AdminMetrics } from '../../types/admin';
const metricConfig = [
  ['Businesses', 'businesses', Building2], ['Active businesses', 'activeBusinesses', ShieldCheck], ['Users', 'users', Users],
  ['Active sessions', 'activeSessions', Activity], ['Queued jobs', 'queuedJobs', ListTodo], ['Failed jobs', 'failedJobs', CircleAlert],
] as const;
export function AdminDashboardPage() {
  const query = useQuery({ queryKey: ['admin-dashboard'], queryFn: async () => (await api.get<{ data: AdminMetrics }>('/admin/dashboard')).data.data });
  return <div className="space-y-8"><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-semibold text-brand-700">Platform operations</p><h1 className="mt-1 text-3xl font-semibold tracking-tight">Super Admin overview</h1><p className="mt-2 text-muted">Manage customer businesses and platform access from one place.</p></div><Link to="/admin/businesses" className="rounded-xl bg-brand-700 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-brand-800">Create business</Link></div>
    {query.isError && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Could not load platform metrics.</div>}
    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{metricConfig.map(([label, key, Icon]) => <article key={key} className="rounded-2xl border border-line bg-white p-5 shadow-card"><div className="flex items-start justify-between"><div><p className="text-sm text-muted">{label}</p><p className="mt-3 text-3xl font-semibold">{query.isLoading ? '—' : (query.data?.[key] ?? 0)}</p></div><span className="rounded-xl bg-brand-50 p-2.5 text-brand-700"><Icon size={20}/></span></div></article>)}</section>
    <section className="rounded-2xl border border-line bg-white p-6"><h2 className="text-lg font-semibold">Release operations</h2><p className="mt-1 text-sm text-muted">Only working administration modules are currently visible. Meta connections, templates, campaigns, webhooks and queue inspection will appear as their milestones become operational.</p></section>
  </div>;
}
