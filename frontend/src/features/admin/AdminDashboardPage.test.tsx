import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { AdminDashboardPage } from './AdminDashboardPage';

describe('admin dashboard', () => {
  it('shows platform metrics', async () => {
    vi.spyOn(api, 'get').mockResolvedValueOnce({ data: { data: { businesses: 3, activeBusinesses: 2, users: 5, activeSessions: 1, queuedJobs: 0, failedJobs: 0 } } });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><MemoryRouter><AdminDashboardPage/></MemoryRouter></QueryClientProvider>);
    expect(await screen.findByText('Super Admin overview')).toBeInTheDocument();
    expect(await screen.findByText('3')).toBeInTheDocument();
  });
});
