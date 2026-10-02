import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, CreditCard, Phone, Plus, ShieldCheck, X } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { api } from '../../services/api';
import type { AdminBusiness, AdminPlan } from '../../types/admin';

const createSchema = z.object({
  businessName: z.string().trim().min(2, 'Enter the business name').max(190),
  ownerName: z.string().trim().min(2, 'Enter the owner name').max(190),
  ownerEmail: z.string().email('Enter a valid email'),
  ownerPassword: z.string().min(12, 'Use at least 12 characters'),
  timezone: z.string().min(1),
  planId: z.string().optional(),
});
type CreateValues = z.infer<typeof createSchema>;

export function AdminBusinessesPage() {
  const [creating, setCreating] = useState(false);
  const [managingPlanFor, setManagingPlanFor] = useState<AdminBusiness | null>(null);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['admin-businesses'],
    queryFn: async () => (await api.get<{ data: AdminBusiness[] }>('/admin/businesses')).data.data,
  });

  const plansQuery = useQuery({
    queryKey: ['admin-plans'],
    queryFn: async () => (await api.get<{ data: AdminPlan[] }>('/admin/plans')).data.data,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CreateValues>({
    resolver: zodResolver(createSchema),
    defaultValues: { timezone: 'Asia/Kolkata' },
  });

  const create = useMutation({
    mutationFn: async (values: CreateValues) => (await api.post<{ data: AdminBusiness }>('/admin/businesses', values)).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-businesses'] });
      queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] });
      reset({ timezone: 'Asia/Kolkata' });
      setCreating(false);
    },
  });

  const status = useMutation({
    mutationFn: async ({ id, value }: { id: string; value: 'active' | 'suspended' }) =>
      api.patch(`/admin/businesses/${id}`, { status: value }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-businesses'] });
      queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] });
    },
  });

  const assignPlan = useMutation({
    mutationFn: async ({ id, planId, billingInterval, expiresDays }: { id: string; planId: string; billingInterval: string; expiresDays: number }) =>
      (await api.post(`/admin/businesses/${id}/plan`, { planId, billingInterval, expiresDays })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-businesses'] });
      setManagingPlanFor(null);
    },
  });

  return (
    <div>
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Customers</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Businesses</h1>
          <p className="mt-2 text-muted">Provision customer workspaces, assign subscription plans, and monitor WhatsApp status.</p>
        </div>
        <button
          onClick={() => setCreating(true)}
          className="flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-800"
        >
          <Plus size={18} /> Create business
        </button>
      </div>

      {creating && (
        <section aria-label="Create business" className="mt-8 rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex items-start justify-between">
            <div>
              <h2 className="text-xl font-semibold">Create customer business</h2>
              <p className="mt-1 text-sm text-muted">Creates an active workspace, assigns its initial plan, and provisions the first Business Owner.</p>
            </div>
            <button aria-label="Close form" onClick={() => setCreating(false)} className="rounded-lg p-2 hover:bg-gray-100">
              <X />
            </button>
          </div>
          <form className="mt-6 grid gap-5 md:grid-cols-2" onSubmit={handleSubmit((values) => create.mutate(values))}>
            <Field label="Business name" error={errors.businessName?.message}>
              <input className="input" {...register('businessName')} />
            </Field>
            <Field label="Timezone" error={errors.timezone?.message}>
              <select className="input" {...register('timezone')}>
                <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                <option value="UTC">UTC</option>
                <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                <option value="Europe/London">Europe/London (GMT)</option>
                <option value="America/New_York">America/New_York (EST)</option>
              </select>
            </Field>
            <Field label="Initial Subscription Plan" error={errors.planId?.message}>
              <select className="input" {...register('planId')}>
                <option value="">Default (Launch Plan)</option>
                {plansQuery.data?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} ({p.code.toUpperCase()})
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Owner name" error={errors.ownerName?.message}>
              <input className="input" {...register('ownerName')} />
            </Field>
            <Field label="Owner email" error={errors.ownerEmail?.message}>
              <input type="email" className="input" {...register('ownerEmail')} />
            </Field>
            <Field label="Temporary owner password" error={errors.ownerPassword?.message}>
              <input type="password" autoComplete="new-password" className="input" {...register('ownerPassword')} />
            </Field>
            <div className="flex items-end md:col-span-2">
              <button disabled={create.isPending} className="w-full rounded-xl bg-brand-700 px-4 py-3 font-semibold text-white disabled:opacity-60">
                {create.isPending ? 'Creating…' : 'Create business and provision plan'}
              </button>
            </div>
            {create.isError && (
              <div role="alert" className="md:col-span-2 rounded-xl bg-red-50 p-3 text-sm text-red-700">
                {apiError(create.error)}
              </div>
            )}
          </form>
        </section>
      )}

      {managingPlanFor && (
        <PlanModal
          business={managingPlanFor}
          plans={plansQuery.data ?? []}
          onClose={() => setManagingPlanFor(null)}
          onAssign={(planId, billingInterval, expiresDays) =>
            assignPlan.mutate({ id: managingPlanFor.id, planId, billingInterval, expiresDays })
          }
          isPending={assignPlan.isPending}
          error={assignPlan.isError ? apiError(assignPlan.error) : ''}
        />
      )}

      {query.isError && (
        <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <p className="font-semibold">Could not load businesses.</p>
          <p className="mt-1 text-xs opacity-90">{apiError(query.error)}</p>
        </div>
      )}

      <section className="mt-8 overflow-hidden rounded-2xl border border-line bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Plan</th>
                <th className="px-5 py-3">WhatsApp Number</th>
                <th className="px-5 py-3">Owner</th>
                <th className="px-5 py-3">Users</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {query.data?.map((business) => (
                <tr key={business.id}>
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">
                      <span className="rounded-xl bg-brand-50 p-2 text-brand-700">
                        <Building2 size={18} />
                      </span>
                      <div>
                        <p className="font-medium">{business.name}</p>
                        <p className="text-xs text-muted">{business.slug}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    {business.plan ? (
                      <div>
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                          <CreditCard size={12} /> {business.plan.name}
                        </span>
                        <p className="mt-1 text-[11px] text-muted capitalize">
                          {business.plan.billingInterval} • {business.plan.status}
                        </p>
                      </div>
                    ) : (
                      <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-muted">No Plan</span>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    {business.whatsapp ? (
                      <div>
                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-brand-700">
                          <Phone size={13} /> {business.whatsapp.phoneNumber}
                        </span>
                        <p className="mt-0.5 text-[11px] text-muted">
                          {business.whatsapp.qualityRating || 'Quality Normal'}
                        </p>
                      </div>
                    ) : (
                      <span className="text-xs text-muted">Not Connected</span>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <p>{business.ownerName ?? 'Not assigned'}</p>
                    <p className="text-xs text-muted">{business.ownerEmail}</p>
                  </td>
                  <td className="px-5 py-4">{business.userCount}</td>
                  <td className="px-5 py-4">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium capitalize ${
                        business.status === 'active' ? 'bg-brand-50 text-brand-700' : 'bg-amber-50 text-amber-700'
                      }`}
                    >
                      {business.status}
                    </span>
                  </td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => setManagingPlanFor(business)}
                        className="rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 hover:bg-brand-100"
                      >
                        Manage Plan
                      </button>
                      <button
                        disabled={status.isPending}
                        onClick={() =>
                          status.mutate({
                            id: business.id,
                            value: business.status === 'active' ? 'suspended' : 'active',
                          })
                        }
                        className="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold hover:bg-gray-50"
                      >
                        {business.status === 'active' ? 'Suspend' : 'Activate'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {query.isLoading && <p className="p-8 text-center text-muted">Loading businesses…</p>}
        {!query.isLoading && query.data?.length === 0 && (
          <p className="p-8 text-center text-muted">No businesses yet. Create the first customer workspace.</p>
        )}
      </section>
    </div>
  );
}

function PlanModal({
  business,
  plans,
  onClose,
  onAssign,
  isPending,
  error,
}: {
  business: AdminBusiness;
  plans: AdminPlan[];
  onClose: () => void;
  onAssign: (planId: string, billingInterval: string, expiresDays: number) => void;
  isPending: boolean;
  error: string;
}) {
  const [selectedPlanId, setSelectedPlanId] = useState(business.plan?.id ?? plans[0]?.id ?? '');
  const [billingInterval, setBillingInterval] = useState(business.plan?.billingInterval ?? 'month');
  const [expiresDays, setExpiresDays] = useState(30);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between">
          <div>
            <h3 className="text-lg font-semibold">Assign Subscription Plan</h3>
            <p className="mt-1 text-xs text-muted">Business: {business.name}</p>
          </div>
          <button onClick={onClose} className="rounded-lg p-1.5 hover:bg-gray-100">
            <X size={18} />
          </button>
        </div>

        <div className="mt-5 space-y-4">
          <div>
            <label className="mb-1 block text-xs font-medium text-muted">Choose Plan</label>
            <select
              value={selectedPlanId}
              onChange={(e) => setSelectedPlanId(e.target.value)}
              className="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm"
            >
              {plans.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.code.toUpperCase()})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-muted">Billing Cycle</label>
            <select
              value={billingInterval}
              onChange={(e) => {
                setBillingInterval(e.target.value);
                setExpiresDays(e.target.value === 'year' ? 365 : 30);
              }}
              className="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm"
            >
              <option value="month">Monthly</option>
              <option value="year">Annual (Yearly)</option>
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-muted">Validity Duration (Days)</label>
            <input
              type="number"
              min={1}
              max={3650}
              value={expiresDays}
              onChange={(e) => setExpiresDays(parseInt(e.target.value, 10) || 30)}
              className="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm"
            />
          </div>

          {error && <div className="rounded-xl bg-red-50 p-3 text-xs text-red-700">{error}</div>}

          <div className="mt-6 flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="button"
              disabled={isPending || !selectedPlanId}
              onClick={() => onAssign(selectedPlanId, billingInterval, expiresDays)}
              className="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-60"
            >
              {isPending ? 'Updating…' : 'Save Plan'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Field({ label, error, children }: { label: string; error: string | undefined; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-medium">{label}</span>
      {children}
      {error && <span className="mt-1 block text-sm text-red-600">{error}</span>}
    </label>
  );
}

function apiError(error: unknown): string {
  const candidate = error as { response?: { data?: { error?: { message?: string } } } };
  return candidate.response?.data?.error?.message ?? 'The operation could not be completed.';
}