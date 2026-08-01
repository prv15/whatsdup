import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { AdminPlansPage } from './AdminPlansPage';

describe('admin plans', () => {
  it('shows plan pricing, limits and features', async () => {
    vi.spyOn(api, 'get').mockResolvedValueOnce({ data: { data: [{ id: 'plan-1', name: 'Growth', code: 'growth', description: 'For growing teams.', priceMinor: 249900, currency: 'INR', billingInterval: 'month', status: 'active', isPublic: true, sortOrder: 20, limits: { phoneNumbers: 2, teamMembers: 5, contacts: 25000, monthlyRecipients: 75000 }, features: ['Advanced campaign reporting'], createdAt: '2026-08-01', updatedAt: '2026-08-01' }] } });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><MemoryRouter><AdminPlansPage/></MemoryRouter></QueryClientProvider>);
    expect(await screen.findByText('Growth')).toBeInTheDocument();
    expect(screen.getByText('Advanced campaign reporting')).toBeInTheDocument();
    expect(screen.getByText(/₹2,499/)).toBeInTheDocument();
  });
});
