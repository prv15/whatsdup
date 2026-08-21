import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, CheckCircle2, KeyRound, Mail } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import { api } from '../../services/api';

const emailSchema = z.object({ email: z.email('Enter a valid email') });
const passwordSchema = z.object({ password: z.string().min(12, 'Use at least 12 characters'), confirmPassword: z.string() })
  .refine((value) => value.password === value.confirmPassword, { message: 'Passwords do not match', path: ['confirmPassword'] });

export function ForgotPasswordPage() {
  const [sent, setSent] = useState(false); const [serverError, setServerError] = useState('');
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<z.infer<typeof emailSchema>>({ resolver: zodResolver(emailSchema) });
  const submit = handleSubmit(async (values) => { setServerError(''); try { await api.post('/auth/forgot-password', values); setSent(true); } catch { setServerError('We could not process the request. Please try again shortly.'); } });
  return <AuthCard icon={<Mail/>} title={sent ? 'Check your email' : 'Forgot your password?'} subtitle={sent ? 'If an active account exists for that address, we sent a secure reset link.' : 'Enter your work email and we will send you a secure reset link.'}>{sent ? <><div className="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"><CheckCircle2 className="mt-0.5 shrink-0" size={18}/>The link expires in one hour and can only be used once.</div><BackToLogin/></> : <form onSubmit={submit} className="space-y-5" noValidate><Field label="Work email" error={errors.email?.message}><input className="w-full rounded-xl border border-line px-4 py-3.5" type="email" autoComplete="email" autoFocus {...register('email')}/></Field>{serverError && <div role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{serverError}</div>}<button disabled={isSubmitting} className="w-full rounded-xl bg-brand-700 px-4 py-3.5 font-semibold text-white hover:bg-brand-800 disabled:opacity-60">{isSubmitting ? 'Sending…' : 'Send reset link'}</button><BackToLogin/></form>}</AuthCard>;
}

export function ResetPasswordPage() {
  const [params] = useSearchParams(); const token = params.get('token') ?? ''; const [complete, setComplete] = useState(false); const [serverError, setServerError] = useState(token ? '' : 'This reset link is incomplete. Request a new one.');
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<z.infer<typeof passwordSchema>>({ resolver: zodResolver(passwordSchema) });
  const submit = handleSubmit(async ({ password }) => { setServerError(''); try { await api.post('/auth/reset-password', { token, password }); setComplete(true); } catch { setServerError('This reset link is invalid or expired. Request a new one.'); } });
  return <AuthCard icon={<KeyRound/>} title={complete ? 'Password updated' : 'Choose a new password'} subtitle={complete ? 'Your sessions were signed out to keep your account secure.' : 'Use at least 12 characters. A longer, unique password is best.'}>{complete ? <Link to="/login" className="block rounded-xl bg-brand-700 px-4 py-3.5 text-center font-semibold text-white hover:bg-brand-800">Continue to sign in</Link> : <form onSubmit={submit} className="space-y-5" noValidate><Field label="New password" error={errors.password?.message}><input className="w-full rounded-xl border border-line px-4 py-3.5" type="password" autoComplete="new-password" autoFocus {...register('password')}/></Field><Field label="Confirm new password" error={errors.confirmPassword?.message}><input className="w-full rounded-xl border border-line px-4 py-3.5" type="password" autoComplete="new-password" {...register('confirmPassword')}/></Field>{serverError && <div role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{serverError}</div>}<button disabled={isSubmitting || !token} className="w-full rounded-xl bg-brand-700 px-4 py-3.5 font-semibold text-white hover:bg-brand-800 disabled:opacity-60">{isSubmitting ? 'Updating…' : 'Reset password'}</button><Link to="/forgot-password" className="block text-center text-sm font-medium text-brand-700 hover:underline">Request a new link</Link></form>}</AuthCard>;
}

function AuthCard({ icon, title, subtitle, children }: { icon: ReactNode; title: string; subtitle: string; children: ReactNode }) { return <main className="grid min-h-screen place-items-center bg-canvas p-4"><section className="w-full max-w-md rounded-3xl border border-line bg-white p-7 shadow-card sm:p-10"><div className="mb-6 grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-700">{icon}</div><h1 className="text-3xl font-semibold tracking-tight">{title}</h1><p className="mb-8 mt-2 leading-7 text-muted">{subtitle}</p>{children}</section></main>; }
function Field({ label, error, children }: { label: string; error: string | undefined; children: ReactNode }) { return <label className="block"><span className="mb-2 block text-sm font-medium">{label}</span>{children}{error && <span className="mt-1 block text-sm text-red-600">{error}</span>}</label>; }
function BackToLogin() { return <Link to="/login" className="flex items-center justify-center gap-2 text-sm font-medium text-brand-700 hover:underline"><ArrowLeft size={16}/>Back to sign in</Link>; }
