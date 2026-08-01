import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowRight, CheckCircle2, LockKeyhole, MessageCircleMore } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useForm } from 'react-hook-form';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { api } from '../../services/api';
import { authStore } from '../../stores/authStore';
import type { AuthPayload } from '../../types/auth';
const schema = z.object({ email: z.email('Enter a valid email'), password: z.string().min(8, 'Password must be at least 8 characters') });
type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const navigate = useNavigate(); const location = useLocation(); const [serverError, setServerError] = useState('');
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({ resolver: zodResolver(schema) });
  if (authStore.getSnapshot().user) return <Navigate to="/dashboard" replace />;
  const submit = handleSubmit(async (values) => { setServerError(''); try { const { data } = await api.post<AuthPayload>('/auth/login', values); authStore.setSession(data.accessToken, data.user); navigate((location.state as { from?: string } | null)?.from ?? '/dashboard', { replace: true }); } catch { setServerError('We could not sign you in. Check your details or try again shortly.'); } });
  return <main className="min-h-screen bg-canvas p-4 md:p-8"><div className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl overflow-hidden rounded-3xl border border-line bg-white shadow-card lg:grid-cols-[1.05fr_.95fr]">
    <section className="hidden bg-brand-800 p-12 text-white lg:flex lg:flex-col lg:justify-between"><div className="flex items-center gap-3 text-xl font-semibold"><span className="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500 text-brand-800"><MessageCircleMore /></span> WhatstheUp</div><div><p className="mb-4 text-sm font-semibold uppercase tracking-[.2em] text-brand-100">Official WhatsApp campaigns</p><h1 className="max-w-lg text-5xl font-semibold leading-tight">Customer communication, ready when your business is.</h1><p className="mt-6 max-w-md text-lg leading-8 text-white/70">Connect your official Meta account, reach opted-in contacts, and understand every delivery.</p></div><div className="flex gap-6 text-sm text-white/75"><span className="flex gap-2"><CheckCircle2 size={18}/> Consent aware</span><span className="flex gap-2"><CheckCircle2 size={18}/> Delivery tracked</span></div></section>
    <section className="flex items-center p-6 sm:p-12 lg:p-16"><div className="w-full"><div className="mb-10 lg:hidden"><div className="flex items-center gap-3 text-xl font-semibold text-brand-700"><MessageCircleMore/> WhatstheUp</div></div><div className="mb-8"><div className="mb-5 grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-700"><LockKeyhole /></div><h2 className="text-3xl font-semibold tracking-tight">Welcome back</h2><p className="mt-2 text-muted">Sign in to manage your business workspace.</p></div><form onSubmit={submit} className="space-y-5" noValidate><Field label="Work email" error={errors.email?.message}><input className="w-full rounded-xl border border-line px-4 py-3.5" type="email" autoComplete="email" {...register('email')} /></Field><Field label="Password" error={errors.password?.message}><input className="w-full rounded-xl border border-line px-4 py-3.5" type="password" autoComplete="current-password" {...register('password')} /></Field>{serverError && <div role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{serverError}</div>}<button disabled={isSubmitting} className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3.5 font-semibold text-white hover:bg-brand-800 disabled:opacity-60">{isSubmitting ? 'Signing in…' : 'Sign in'} <ArrowRight size={18}/></button></form></div></section>
  </div></main>;
}
function Field({ label, error, children }: { label: string; error: string | undefined; children: ReactNode }) { return <label className="block"><span className="mb-2 block text-sm font-medium">{label}</span>{children}{error && <span className="mt-1 block text-sm text-red-600">{error}</span>}</label>; }
