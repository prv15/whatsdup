import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Activity, AlertTriangle, CheckCircle2, Clock, ListTodo, RefreshCw, RotateCcw, ShieldAlert, Unlock } from 'lucide-react';
import { api } from '../../services/api';
import type { AdminFailedJob, AdminQueueHealth } from '../../types/admin';

export function AdminQueuePage() {
  const queryClient = useQueryClient();

  const healthQuery = useQuery({
    queryKey: ['admin-queue-health'],
    queryFn: async () => (await api.get<{ data: AdminQueueHealth }>('/admin/queue/health')).data.data,
    refetchInterval: 10000,
  });

  const failedQuery = useQuery({
    queryKey: ['admin-queue-failed'],
    queryFn: async () => (await api.get<{ data: AdminFailedJob[] }>('/admin/queue/failed')).data.data,
    refetchInterval: 10000,
  });

  const retrySingle = useMutation({
    mutationFn: async (id: number) => (await api.post(`/admin/queue/retry/${id}`)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-queue-health'] });
      queryClient.invalidateQueries({ queryKey: ['admin-queue-failed'] });
    },
  });

  const retryAll = useMutation({
    mutationFn: async () => (await api.post('/admin/queue/retry-all')).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-queue-health'] });
      queryClient.invalidateQueries({ queryKey: ['admin-queue-failed'] });
    },
  });

  const clearStale = useMutation({
    mutationFn: async () => (await api.post('/admin/queue/clear-stale')).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-queue-health'] });
      queryClient.invalidateQueries({ queryKey: ['admin-queue-failed'] });
    },
  });

  const health = healthQuery.data;
  const failedJobs = failedQuery.data ?? [];

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">System Operations</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Queue & Worker Health</h1>
          <p className="mt-2 text-muted">Monitor asynchronous campaign dispatches, worker throughput, and recover failed jobs.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => clearStale.mutate()}
            disabled={clearStale.isPending}
            className="flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50"
          >
            <Unlock size={16} /> {clearStale.isPending ? 'Clearing…' : 'Clear Stale Locks'}
          </button>
          <button
            onClick={() => retryAll.mutate()}
            disabled={retryAll.isPending || !health?.failed}
            className="flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
          >
            <RotateCcw size={16} /> {retryAll.isPending ? 'Retrying…' : 'Retry All Failed'}
          </button>
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-center justify-between text-muted">
            <span className="text-xs uppercase tracking-wide">Ready Jobs</span>
            <ListTodo size={18} className="text-brand-700" />
          </div>
          <p className="mt-2 text-3xl font-semibold">{health?.ready ?? 0}</p>
          <p className="mt-1 text-xs text-muted">
            {health?.oldest_ready_seconds !== null && health?.oldest_ready_seconds !== undefined
              ? `Oldest waiting: ${health.oldest_ready_seconds}s`
              : 'Queue is clear'}
          </p>
        </article>

        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-center justify-between text-muted">
            <span className="text-xs uppercase tracking-wide">In-Flight (Reserved)</span>
            <Activity size={18} className="text-blue-600" />
          </div>
          <p className="mt-2 text-3xl font-semibold text-blue-700">{health?.reserved ?? 0}</p>
          <p className="mt-1 text-xs text-muted">
            {health?.stale_reserved ? (
              <span className="text-amber-600 font-medium">{health.stale_reserved} stale locks detected</span>
            ) : (
              'Active worker execution'
            )}
          </p>
        </article>

        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-center justify-between text-muted">
            <span className="text-xs uppercase tracking-wide">Completed Jobs</span>
            <CheckCircle2 size={18} className="text-emerald-600" />
          </div>
          <p className="mt-2 text-3xl font-semibold text-emerald-700">{health?.completed ?? 0}</p>
          <p className="mt-1 text-xs text-muted">Successfully processed dispatches</p>
        </article>

        <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-center justify-between text-muted">
            <span className="text-xs uppercase tracking-wide">Transient Failures</span>
            <AlertTriangle size={18} className="text-amber-600" />
          </div>
          <p className="mt-2 text-3xl font-semibold text-amber-700">{health?.failed ?? 0}</p>
          <p className="mt-1 text-xs text-muted">{health?.permanent_failed ?? 0} dead letters</p>
        </article>
      </section>

      <section className="overflow-hidden rounded-2xl border border-line bg-white">
        <div className="flex items-center justify-between border-b border-line p-5">
          <div>
            <h2 className="text-lg font-semibold">Failed Dispatch Jobs</h2>
            <p className="mt-0.5 text-xs text-muted">Recent errors recorded during queue worker execution.</p>
          </div>
          <button
            onClick={() => failedQuery.refetch()}
            disabled={failedQuery.isFetching}
            className="rounded-lg p-2 hover:bg-gray-100"
          >
            <RefreshCw size={16} className={failedQuery.isFetching ? 'animate-spin' : ''} />
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">Job ID</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Queue / Type</th>
                <th className="px-5 py-3">Error Diagnostic</th>
                <th className="px-5 py-3">Failed At</th>
                <th className="px-5 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {failedJobs.map((job) => (
                <tr key={job.id}>
                  <td className="px-5 py-4 font-mono text-xs">#{job.queue_job_id}</td>
                  <td className="px-5 py-4 font-medium">{job.business_name ?? 'Platform'}</td>
                  <td className="px-5 py-4">
                    <span className="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                      {job.queue}
                    </span>
                    <p className="mt-1 text-[11px] text-muted font-mono">{job.job_type}</p>
                  </td>
                  <td className="px-5 py-4 max-w-md">
                    <p className="text-xs text-rose-700 font-medium truncate">{job.error_type}</p>
                    <p className="mt-0.5 text-xs text-muted truncate">{job.error_message}</p>
                  </td>
                  <td className="px-5 py-4 text-xs text-muted">
                    {new Date(job.failed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                  </td>
                  <td className="px-5 py-4 text-right">
                    {job.retried_at ? (
                      <span className="text-xs text-emerald-700 font-medium">Retried</span>
                    ) : (
                      <button
                        onClick={() => retrySingle.mutate(job.id)}
                        disabled={retrySingle.isPending}
                        className="rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 hover:bg-brand-100 disabled:opacity-50"
                      >
                        Retry Job
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {failedQuery.isLoading && <p className="p-8 text-center text-muted">Loading failed jobs…</p>}
        {!failedQuery.isLoading && failedJobs.length === 0 && (
          <div className="p-12 text-center">
            <CheckCircle2 className="mx-auto text-emerald-600" size={32} />
            <h3 className="mt-3 font-semibold text-gray-900">Zero Failed Jobs</h3>
            <p className="mt-1 text-sm text-muted">The queue worker has processed all tasks without errors.</p>
          </div>
        )}
      </section>
    </div>
  );
}