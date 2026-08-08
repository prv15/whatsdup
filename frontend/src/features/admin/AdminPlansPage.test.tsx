import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { AdminPlansPage } from './AdminPlansPage';

describe('admin plans', () => {
  it('shows plan pricing, limits and features', async () => {
    vi.spyOn(api, 'get').mockResolvedValueOnce({ data: { data: [{ id: 'plan-1', name: 'Growth', code: 'growth', description: 'For growing teams.', priceMinor: 229900, annualPriceMinor: 2206800, currency: 'INR', billingInterval: 'month', status: 'active', isPublic: true, sortOrder: 20, limits: { phoneNumbers: 2, teamMembers: 10, contacts: 25000, monthlyRecipients: 100000 }, features: ['Advanced campaign reporting'], createdAt: '2026-08-01', updatedAt: '2026-08-01' }] } });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><MemoryRouter><AdminPlansPage/></MemoryRouter></QueryClientProvider>);
    expect(await screen.findByText('Growth')).toBeInTheDocument();
    expect(screen.getByText('Advanced campaign reporting')).toBeInTheDocument();
    expect(screen.getByText(/₹2,299/)).toBeInTheDocument();
    expect(screen.getByText(/₹22,068/)).toBeInTheDocument();
  });
});
