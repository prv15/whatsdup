import { useState } from 'react';
import { api } from '../../services/api';
import type { MetaConnectionStatus } from '../../types/meta';

export function TestConnectionForm({ config, onConnected }: { config: { wabaId: string; phoneNumberId: string }; onConnected: (state: MetaConnectionStatus) => void }) {
  const [token, setToken] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  return <section className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
    <h2 className="text-xl font-semibold">Developer test connection</h2>
    <p className="mt-2 text-sm">WABA: {config.wabaId} · Phone Number ID: {config.phoneNumberId}</p>
    <p className="mt-2 text-sm">Use the token from this app’s Meta test setup. It is encrypted on the server. Test tokens expire; refresh here when needed. Only send to recipients verified in Meta’s test setup. Connection does not prove message delivery.</p>
    <form className="mt-4 space-y-4" onSubmit={async (event) => {
      event.preventDefault(); if (busy || !confirmed || !token.trim()) return;
      setBusy(true); setMessage('');
      try {
        const result = await api.post<{ data: MetaConnectionStatus }>('/meta/connection/test', { accessToken: token.trim(), confirmReplacement: true });
        setToken(''); setConfirmed(false); onConnected(result.data.data);
        setMessage(result.data.data.status === 'connected' ? 'Test connection saved. Open Templates and Sync from Meta, then create a new test campaign for your verified recipient.' : 'Token saved, but webhook setup needs attention. See the connection error above.');
      } catch (error) {
        const failure = error as { response?: { data?: { error?: { message?: string } } } };
        setMessage(failure.response?.data?.error?.message || 'Test connection could not be saved.');
      } finally { setToken(''); setBusy(false); }
    }}>
      <label className="block text-sm font-medium">Meta test access token
        <input type="password" autoComplete="off" spellCheck={false} required value={token} onChange={(event) => setToken(event.target.value)} className="mt-1 block w-full rounded-lg border p-3" />
      </label>
      <label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />Replace this workspace’s saved sender with the configured test account. Keep campaign history and reset template approvals if the account changes.</label>
      <button disabled={busy || !confirmed || !token.trim()} className="rounded-xl bg-brand-700 px-4 py-3 font-semibold text-white disabled:opacity-50">{busy ? 'Validating test account…' : 'Connect test account'}</button>
      {message && <p role="status" className="text-sm">{message}</p>}
    </form>
  </section>;
}
