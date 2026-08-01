import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { MetaConnectionPage } from './MetaConnectionPage';

describe('Meta connection', () => {
  it('safely disables signup when platform credentials are missing', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url) => {
      if (url === '/meta/configuration') return { data: { data: { enabled: false, appId: '', configId: '', graphVersion: 'v25.0', missing: ['META_APP_ID'], requiresHttps: true } } } as never;
      return { data: { data: { status: 'not_connected', connectedAt: null, lastSyncedAt: null, lastTestedAt: null, waba: null, phone: null, webhookStatus: 'pending', error: null } } } as never;
    });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><MetaConnectionPage/></QueryClientProvider>);
    expect(await screen.findByText('Platform configuration required')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Connect with Meta' })).toBeDisabled();
  });
});
