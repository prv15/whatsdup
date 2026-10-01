import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { CampaignsPage } from './CampaignsPage';

describe('CampaignsPage', () => {
  it('renders campaign delivery funnel and opens launch confirmation dialog', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url === '/campaigns') {
        return {
          data: {
            data: [
              {
                id: 'camp-1',
                name: 'Spring Promo',
                audienceType: 'all_opted_in',
                status: 'draft',
                scheduledAt: null,
                launchedAt: null,
                completedAt: null,
                recipientCount: 50,
                acceptedCount: 48,
                deliveredCount: 45,
                readCount: 30,
                failedCount: 2,
                failureCode: null,
                failureMessage: null,
                templateName: 'spring_offer',
                templateLanguage: 'en_US',
                createdAt: '2026-09-18T12:00:00Z',
              },
            ],
          },
        } as never;
      }
      if (url === '/templates') {
        return {
          data: {
            data: [
              {
                id: 'tmpl-1',
                name: 'spring_offer',
                language: 'en_US',
                category: 'marketing',
                headerType: 'none',
                headerMediaUrl: null,
                body: 'Hello {{1}}, here is your deal {{2}}',
                variables: ['1', '2'],
                status: 'approved',
                rejectionReason: null,
                createdAt: '2026-09-18T10:00:00Z',
                updatedAt: '2026-09-18T10:00:00Z',
              },
            ],
          },
        } as never;
      }
      if (url === '/contacts') {
        return {
          data: {
            data: {
              contacts: [
                {
                  id: 'contact-1',
                  phone: '+15551234567',
                  name: 'Alice',
                  email: null,
                  tags: [],
                  consentStatus: 'opted_in',
                  consentAt: '2026-09-18T10:00:00Z',
                  source: 'web',
                  createdAt: '2026-09-18T10:00:00Z',
                  updatedAt: '2026-09-18T10:00:00Z',
                },
              ],
              groups: [],
              imports: [],
            },
          },
        } as never;
      }
      if (url === '/contact-groups') {
        return { data: { data: [] } } as never;
      }
      if (url === '/campaigns/camp-1/recipients') {
        return {
          data: {
            data: [
              {
                id: 'rcpt-1',
                contactId: 'contact-1',
                phone: '+15551234567',
                contactName: 'Alice',
                status: 'delivered',
                metaMessageId: 'wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI1Mj',
                failureCode: null,
                failureMessage: null,
                sentAt: '2026-09-18T12:01:00Z',
                deliveredAt: '2026-09-18T12:01:05Z',
                readAt: null,
              },
            ],
          },
        } as never;
      }
      return { data: { data: [] } } as never;
    });

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <CampaignsPage />
        </MemoryRouter>
      </QueryClientProvider>
    );

    // 1. Check funnel stats render
    expect(await screen.findByText('Spring Promo')).toBeInTheDocument();
    expect(screen.getByText('45')).toBeInTheDocument();
    expect(screen.getByText('delivered')).toBeInTheDocument();
    expect(screen.getByText('30')).toBeInTheDocument();
    expect(screen.getByText('read')).toBeInTheDocument();
    expect(screen.getByText('48 accepted by Meta')).toBeInTheDocument();
    expect(screen.getByText('2 failed')).toBeInTheDocument();

    // 2. Click Launch -> Opens Launch Pre-Flight Confirmation Modal
    const launchButton = screen.getByRole('button', { name: /Launch/i });
    fireEvent.click(launchButton);

    expect(await screen.findByText('Ready to Launch Campaign?')).toBeInTheDocument();
    expect(screen.getByText('50 contact(s)')).toBeInTheDocument();
    expect(screen.getByText(/Opt-out Suppression Active/)).toBeInTheDocument();

    // Close launch modal
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    // 3. Inspect recipients drawer
    const inspectButton = screen.getByRole('button', { name: /View recipients for Spring Promo/i });
    fireEvent.click(inspectButton);

    expect(await screen.findByText(/Campaign Recipients: Spring Promo/)).toBeInTheDocument();
    expect(await screen.findByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('+15551234567')).toBeInTheDocument();
  });
});
