import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Pencil, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { api } from '../../services/api';
import type { AdminPlan } from '../../types/admin';

const limitField = z.string().trim().refine((item) => item === '' || (/^\d+$/.test(item) && Number(item) <= 1_000_000_000), 'Use a whole number or leave blank');
const validPrice = (value: string) => value === '' || (/^\d+(\.\d{1,2})?$/.test(value) && Number(value) <= 1_000_000);
const schema = z.object({
  name: z.string().trim().min(2, 'Enter a plan name').max(120),
  code: z.string().trim().regex(/^[a-z][a-z0-9-]{1,79}$/, 'Use lowercase letters, numbers and hyphens'),
  description: z.string().trim().max(500),
  priceRupees: z.string().trim().refine(validPrice, 'Enter a valid monthly price'), annualPriceRupees: z.string().trim().refine(validPrice, 'Enter a valid annual price'),
  billingInterval: z.enum(['month', 'year', 'custom']), status: z.enum(['active', 'archived']), isPublic: z.boolean(),
  sortOrder: z.number().int().min(0).max(10000),
  phoneNumbers: limitField, teamMembers: limitField, contacts: limitField, monthlyRecipients: limitField,
  features: z.string().max(8000),
});
type Values = z.infer<typeof schema>;

const emptyValues: Values = { name: '', code: '', description: '', priceRupees: '', annualPriceRupees: '', billingInterval: 'month', status: 'active', isPublic: true, sortOrder: 0, phoneNumbers: '', teamMembers: '', contacts: '', monthlyRecipients: '', features: '' };

export function AdminPlansPage() {
  const [editing, setEditing] = useState<AdminPlan | 'new' | null>(null);
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-plans'], queryFn: async () => (await api.get<{ data: AdminPlan[] }>('/admin/plans')).data.data });
  const { register, handleSubmit, reset, formState: { errors } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: emptyValues });
  const save = useMutation({
    mutationFn: async (values: Values) => {
      const payload = toPayload(values);
      return editing === 'new' ? api.post('/admin/plans', payload) : api.put(`/admin/plans/${editing!.id}`, payload);
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-plans'] }); closeEditor(); },
  });
  const openCreate = () => { reset(emptyValues); setEditing('new'); };
  const openEdit = (plan: AdminPlan) => { reset({ name: plan.name, code: plan.code, description: plan.description, priceRupees: plan.priceMinor === null ? '' : String(plan.priceMinor / 100), annualPriceRupees: plan.annualPriceMinor === null ? '' : String(plan.annualPriceMinor / 100), billingInterval: plan.billingInterval, status: plan.status, isPublic: plan.isPublic, sortOrder: plan.sortOrder, phoneNumbers: displayLimit(plan.limits.phoneNumbers), teamMembers: displayLimit(plan.limits.teamMembers), contacts: displayLimit(plan.limits.contacts), monthlyRecipients: displayLimit(plan.limits.monthlyRecipients), features: plan.features.join('\n') }); setEditing(plan); };
  const closeEditor = () => { setEditing(null); reset(emptyValues); save.reset(); };

  return <div className="space-y-8">
    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-semibold text-brand-700">Billing catalogue</p><h1 className="mt-1 text-3xl font-semibold tracking-tight">Plans</h1><p className="mt-2 text-muted">Create subscription offers and control their pricing, limits, features and availability.</p></div><button onClick={openCreate} className="flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-800"><Plus size={18}/> Create plan</button></div>

    {editing && <section aria-label={editing === 'new' ? 'Create plan' : 'Edit plan'} className="rounded-2xl border border-line bg-white p-6 shadow-card"><div className="flex items-start justify-between gap-4"><div><h2 className="text-xl font-semibold">{editing === 'new' ? 'Create subscription plan' : `Edit ${editing.name}`}</h2><p className="mt-1 text-sm text-muted">Blank limits mean custom or unlimited by agreement.</p></div><button aria-label="Close form" onClick={closeEditor} className="rounded-lg p-2 hover:bg-gray-100"><X/></button></div>
      <form className="mt-6 grid gap-5 md:grid-cols-2" onSubmit={handleSubmit((values) => save.mutate(values))}>
        <Field label="Plan name" error={errors.name?.message}><input className="input" {...register('name')}/></Field><Field label="Plan code" error={errors.code?.message}><input className="input" placeholder="growth" {...register('code')}/></Field>
        <Field label="Description" error={errors.description?.message} wide><textarea rows={3} className="input resize-y" {...register('description')}/></Field>
        <Field label="Monthly price in rupees" error={errors.priceRupees?.message}><input inputMode="decimal" className="input" placeholder="Leave blank for custom" {...register('priceRupees')}/></Field><Field label="Annual total in rupees" error={errors.annualPriceRupees?.message}><input inputMode="decimal" className="input" placeholder="Example: 9588" {...register('annualPriceRupees')}/></Field><Field label="Billing model" error={errors.billingInterval?.message}><select className="input" {...register('billingInterval')}><option value="month">Monthly with annual option</option><option value="year">Annual only</option><option value="custom">Custom</option></select></Field>
        <Field label="WhatsApp numbers" error={errors.phoneNumbers?.message}><input inputMode="numeric" className="input" {...register('phoneNumbers')}/></Field><Field label="Team members" error={errors.teamMembers?.message}><input inputMode="numeric" className="input" {...register('teamMembers')}/></Field><Field label="Contacts" error={errors.contacts?.message}><input inputMode="numeric" className="input" {...register('contacts')}/></Field><Field label="Monthly campaign recipients" error={errors.monthlyRecipients?.message}><input inputMode="numeric" className="input" {...register('monthlyRecipients')}/></Field>
        <Field label="Features — one per line" error={errors.features?.message} wide><textarea rows={7} className="input resize-y" placeholder={'Contact import and groups\nTemplate sync and campaigns\nDelivery reporting'} {...register('features')}/></Field>
        <Field label="Display order" error={errors.sortOrder?.message}><input type="number" className="input" {...register('sortOrder', { valueAsNumber: true })}/></Field><Field label="Status" error={errors.status?.message}><select className="input" {...register('status')}><option value="active">Active</option><option value="archived">Archived</option></select></Field>
        <label className="flex items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm font-medium md:col-span-2"><input type="checkbox" className="h-4 w-4 accent-brand-700" {...register('isPublic')}/> Show this plan in public plan selections</label>
        {save.isError && <div role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700 md:col-span-2">{apiError(save.error)}</div>}
        <div className="flex justify-end gap-3 md:col-span-2"><button type="button" onClick={closeEditor} className="rounded-xl border border-line px-5 py-3 text-sm font-semibold hover:bg-gray-50">Cancel</button><button disabled={save.isPending} className="rounded-xl bg-brand-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{save.isPending ? 'Saving…' : 'Save plan'}</button></div>
      </form>
    </section>}

    <section className="grid gap-5 xl:grid-cols-3">{query.data?.map((plan) => <article key={plan.id} className={`flex flex-col rounded-2xl border bg-white p-6 ${plan.status === 'active' ? 'border-line' : 'border-dashed border-gray-300 opacity-75'}`}><div className="flex items-start justify-between gap-4"><div><div className="flex flex-wrap items-center gap-2"><h2 className="text-xl font-semibold">{plan.name}</h2><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${plan.status === 'active' ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600'}`}>{plan.status}</span>{!plan.isPublic && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Private</span>}</div><p className="mt-1 text-xs font-medium uppercase tracking-wide text-muted">{plan.code}</p></div><button aria-label={`Edit ${plan.name}`} onClick={() => openEdit(plan)} className="rounded-lg border border-line p-2 text-muted hover:bg-gray-50 hover:text-ink"><Pencil size={16}/></button></div><p className="mt-5 min-h-12 text-sm leading-6 text-muted">{plan.description || 'No description provided.'}</p><p className="mt-5 text-3xl font-semibold tracking-tight text-brand-800">{formatPrice(plan)}<span className="ml-1 text-sm font-normal text-muted">{plan.billingInterval === 'month' ? '/ month' : plan.billingInterval === 'year' ? '/ year' : ''}</span></p>{plan.annualPriceMinor !== null && <p className="mt-1 text-xs font-medium text-brand-700">Annual: {formatMoney(plan.annualPriceMinor, plan.currency)} total</p>}<div className="mt-5 grid grid-cols-2 gap-2 text-xs"><Limit label="Numbers" value={plan.limits.phoneNumbers}/><Limit label="Team" value={plan.limits.teamMembers}/><Limit label="Contacts" value={plan.limits.contacts}/><Limit label="Recipients/mo" value={plan.limits.monthlyRecipients}/></div><ul className="mt-6 space-y-3 border-t border-line pt-5">{plan.features.map((feature) => <li key={feature} className="flex gap-2 text-sm text-gray-600"><Check className="mt-0.5 shrink-0 text-brand-700" size={16}/><span>{feature}</span></li>)}</ul></article>)}
      {query.isLoading && <p className="col-span-full rounded-2xl border border-line bg-white p-8 text-center text-muted">Loading plans…</p>}{query.isError && <p role="alert" className="col-span-full rounded-2xl bg-red-50 p-5 text-red-700">Plans could not be loaded.</p>}{!query.isLoading && query.data?.length === 0 && <p className="col-span-full rounded-2xl border border-line bg-white p-8 text-center text-muted">No plans yet. Create the first subscription plan.</p>}
    </section>
  </div>;
}

function Field({ label, error, wide = false, children }: { label: string; error: string | undefined; wide?: boolean; children: React.ReactNode }) { return <label className={wide ? 'block md:col-span-2' : 'block'}><span className="mb-2 block text-sm font-medium">{label}</span>{children}{error && <span className="mt-1 block text-sm text-red-600">{error}</span>}</label>; }
function Limit({ label, value }: { label: string; value: number | null }) { return <div className="rounded-lg bg-gray-50 p-2.5"><span className="block text-muted">{label}</span><b className="mt-0.5 block text-ink">{value === null ? 'Custom' : value.toLocaleString('en-IN')}</b></div>; }
function displayLimit(value: number | null) { return value === null ? '' : String(value); }
function toLimit(value: string) { return value === '' ? null : Number(value); }
function toPayload(values: Values) { return { name: values.name, code: values.code, description: values.description, priceMinor: values.priceRupees === '' ? null : Math.round(Number(values.priceRupees) * 100), annualPriceMinor: values.annualPriceRupees === '' ? null : Math.round(Number(values.annualPriceRupees) * 100), currency: 'INR', billingInterval: values.billingInterval, status: values.status, isPublic: values.isPublic, sortOrder: values.sortOrder, limits: { phoneNumbers: toLimit(values.phoneNumbers), teamMembers: toLimit(values.teamMembers), contacts: toLimit(values.contacts), monthlyRecipients: toLimit(values.monthlyRecipients) }, features: values.features.split('\n').map((feature) => feature.trim()).filter(Boolean) }; }
function formatMoney(priceMinor: number, currency: string) { return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(priceMinor / 100); }
function formatPrice(plan: AdminPlan) { return plan.priceMinor === null ? 'Custom' : formatMoney(plan.priceMinor, plan.currency); }
function apiError(error: Error): string { const candidate = error as Error & { response?: { data?: { error?: { message?: string } } } }; return candidate.response?.data?.error?.message ?? 'The plan could not be saved.'; }
