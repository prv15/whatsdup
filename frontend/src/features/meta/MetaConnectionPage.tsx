import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, CheckCircle2, ExternalLink, Link2, Phone, RefreshCw, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { api } from '../../services/api';
import type { MetaConfiguration, MetaConnectionStatus } from '../../types/meta';

declare global {
  interface Window { FB?: { init(options: { appId: string; cookie: boolean; xfbml: boolean; version: string }): void; login(callback: (response: { authResponse?: { code?: string } }) => void, options: Record<string, unknown>): void } }
}

export function MetaConnectionPage() {
  const queryClient = useQueryClient(); const [sdkReady, setSdkReady] = useState(false); const [signupError, setSignupError] = useState('');
  const codeRef = useRef<string | null>(null); const assetsRef = useRef<{ wabaId: string; phoneNumberId: string } | null>(null);
  const configuration = useQuery({ queryKey: ['meta-configuration'], queryFn: async () => (await api.get<{ data: MetaConfiguration }>('/meta/configuration')).data.data });
  const connection = useQuery({ queryKey: ['meta-connection'], queryFn: async () => (await api.get<{ data: MetaConnectionStatus }>('/meta/connection')).data.data });
  const complete = useMutation({ mutationFn: async (payload: { code: string; wabaId: string; phoneNumberId: string }) => (await api.post<{ data: MetaConnectionStatus }>('/meta/connection/complete', payload)).data.data, onSuccess: (data) => { queryClient.setQueryData(['meta-connection'], data); }, onError: (error) => setSignupError(apiError(error)) });

  const tryComplete = () => { if (codeRef.current && assetsRef.current && !complete.isPending) complete.mutate({ code: codeRef.current, ...assetsRef.current }); };
  useEffect(() => {
    const config = configuration.data;
    if (!config?.enabled || !config.appId) return;
    if (window.FB) { window.FB.init({ appId: config.appId, cookie: true, xfbml: true, version: config.graphVersion }); setSdkReady(true); return; }
    if (document.getElementById('facebook-jssdk')) return;
    const script = document.createElement('script'); script.id = 'facebook-jssdk'; script.async = true; script.defer = true; script.crossOrigin = 'anonymous'; script.src = 'https://connect.facebook.net/en_US/sdk.js';
    script.onload = () => { window.FB?.init({ appId: config.appId, cookie: true, xfbml: true, version: config.graphVersion }); setSdkReady(true); };
    script.onerror = () => setSignupError('The official Meta signup SDK could not be loaded.'); document.body.appendChild(script);
  }, [configuration.data]);
  useEffect(() => {
    const listener = (event: MessageEvent) => {
      if (!['https://www.facebook.com', 'https://web.facebook.com'].includes(event.origin)) return;
      let payload: unknown = event.data;
      if (typeof payload === 'string') { try { payload = JSON.parse(payload); } catch { return; } }
      const message = payload as { type?: string; event?: string; data?: { waba_id?: string; phone_number_id?: string } };
      if (message.type === 'WA_EMBEDDED_SIGNUP' && message.event === 'FINISH' && message.data?.waba_id && message.data.phone_number_id) { assetsRef.current = { wabaId: message.data.waba_id, phoneNumberId: message.data.phone_number_id }; tryComplete(); }
    };
    window.addEventListener('message', listener); return () => window.removeEventListener('message', listener);
  });
  const startSignup = () => {
    const config = configuration.data; setSignupError(''); codeRef.current = null; assetsRef.current = null;
    if (!config?.enabled || !window.FB) { setSignupError('Meta Embedded Signup is not ready.'); return; }
    window.FB.login((response) => { const code = response.authResponse?.code; if (!code) { setSignupError('Meta signup was cancelled or did not return an authorization code.'); return; } codeRef.current = code; tryComplete(); }, { config_id: config.configId, response_type: 'code', override_default_response_type: true, extras: { setup: {} } });
  };
  const state = connection.data; const configured = configuration.data?.enabled === true; const canConnect = configured && sdkReady && !configuration.data?.requiresHttps && state?.status === 'not_connected';
  return <div className="space-y-8"><div><p className="text-sm font-semibold text-brand-700">Official Cloud API</p><h1 className="mt-1 text-3xl font-semibold tracking-tight">Meta Connection</h1><p className="mt-2 max-w-3xl text-muted">Connect your Meta business portfolio, WhatsApp Business Account and phone number through Meta’s official Embedded Signup.</p></div>
    {!configured && <Notice title="Platform configuration required" text={`The platform administrator must configure: ${configuration.data?.missing.join(', ') || 'Meta App credentials'}. No connection is simulated while these values are missing.`}/>} {configuration.data?.requiresHttps && <Notice title="HTTPS required" text="Meta Embedded Signup requires a secure HTTPS application URL. Use the production or an HTTPS development domain to connect real assets."/>}
    <section className="grid gap-6 xl:grid-cols-[1.1fr_.9fr]"><article className="rounded-2xl border border-line bg-white p-6 shadow-card"><div className="flex items-start justify-between gap-4"><div><h2 className="text-xl font-semibold">Connection status</h2><p className="mt-1 text-sm text-muted">Graph API {configuration.data?.graphVersion || 'not configured'}</p></div><Status value={state?.status ?? 'not_connected'}/></div>{state?.status === 'connected' || state?.status === 'webhook_error' ? <div className="mt-7 grid gap-4 sm:grid-cols-2"><Info label="WABA" value={state.waba?.name || state.waba?.id || 'Unavailable'}/><Info label="Phone number" value={state.phone?.number || state.phone?.id || 'Unavailable'}/><Info label="Verified name" value={state.phone?.verifiedName || 'Pending'}/><Info label="Quality" value={state.phone?.qualityRating || 'Unknown'}/><Info label="Webhook" value={state.webhookStatus}/><Info label="Connected" value={state.connectedAt || 'Unavailable'}/></div> : <div className="mt-8 rounded-2xl bg-brand-50 p-6"><div className="flex items-center gap-3 text-brand-700"><Link2/><h3 className="font-semibold">No WhatsApp account connected</h3></div><p className="mt-3 text-sm leading-6 text-muted">You will sign in with Meta, choose or create a business portfolio and WABA, then verify an eligible phone number. WhatstheUp never receives your Meta password.</p><button disabled={!canConnect || complete.isPending} onClick={startSignup} className="mt-6 flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{complete.isPending ? <RefreshCw className="animate-spin" size={18}/> : <ExternalLink size={18}/>} {complete.isPending ? 'Verifying connection…' : 'Connect with Meta'}</button></div>}{(signupError || state?.error) && <div role="alert" className="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{signupError || state?.error?.message}</div>}</article>
      <article className="rounded-2xl border border-line bg-white p-6 shadow-card"><h2 className="text-xl font-semibold">Before you connect</h2><div className="mt-6 space-y-5"><Step icon={<ShieldCheck/>} title="Meta business portfolio" text="You need administrator access to the portfolio that will own the WhatsApp account."/><Step icon={<Phone/>} title="Eligible phone number" text="The number must be able to receive the verification method offered by Meta and meet current registration rules."/><Step icon={<CheckCircle2/>} title="Production approvals" text="Business verification, App Review, permissions, display name and templates remain subject to Meta approval."/></div></article></section>
  </div>;
}
function Notice({ title, text }: { title: string; text: string }) { return <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900"><AlertTriangle className="mt-0.5 shrink-0" size={20}/><div><p className="font-semibold">{title}</p><p className="mt-1 text-sm leading-6">{text}</p></div></div>; }
function Status({ value }: { value: string }) { const ok = value === 'connected'; return <span className={`rounded-full px-3 py-1.5 text-xs font-semibold capitalize ${ok ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-muted'}`}>{value.replaceAll('_', ' ')}</span>; }
function Info({ label, value }: { label: string; value: string }) { return <div className="rounded-xl border border-line p-4"><p className="text-xs uppercase tracking-wide text-muted">{label}</p><p className="mt-2 break-words font-medium capitalize">{value}</p></div>; }
function Step({ icon, title, text }: { icon: React.ReactNode; title: string; text: string }) { return <div className="flex gap-3"><span className="mt-0.5 text-brand-700">{icon}</span><div><h3 className="font-medium">{title}</h3><p className="mt-1 text-sm leading-6 text-muted">{text}</p></div></div>; }
function apiError(error: Error): string { const candidate = error as Error & { response?: { data?: { error?: { message?: string } } } }; return candidate.response?.data?.error?.message ?? 'Meta connection could not be completed.'; }
