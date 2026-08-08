import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { DashboardPage } from '../features/dashboard/DashboardPage';
import { api } from '../services/api';
describe('dashboard', () => { it('shows launch readiness', () => { vi.spyOn(api, 'get').mockResolvedValue({ data: { data: { metrics: { messagesToday: 0, contacts: 0, approvedTemplates: 0, scheduledCampaigns: 0 }, metaStatus: 'not_connected' } } }); const client = new QueryClient({ defaultOptions: { queries: { retry: false } } }); render(<QueryClientProvider client={client}><MemoryRouter><DashboardPage/></MemoryRouter></QueryClientProvider>); expect(screen.getByText('Launch readiness')).toBeInTheDocument(); }); });
