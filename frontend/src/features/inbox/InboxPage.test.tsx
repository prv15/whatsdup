import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { InboxPage } from './InboxPage';

describe('InboxPage', () => {
  it('renders conversation list, loads chat history, and sends message within 24h window', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url.startsWith('/inbox/conversations?')) {
        return {
          data: {
            data: [
              {
                id: 'conv-1',
                status: 'open',
                lastMessageAt: '2026-10-01T12:00:00Z',
                windowExpiresAt: '2026-10-02T12:00:00Z',
                isWindowOpen: true,
                windowRemainingMinutes: 1400,
                unreadCount: 1,
                assignedUserId: null,
                assignedUserName: null,
                contact: {
                  id: 'contact-1',
                  name: 'Alice Smith',
                  phone: '+919876543210',
                },
                lastMessage: {
                  content: 'Hi, I need assistance with my order',
                  direction: 'inbound',
                  status: 'delivered',
                },
                createdAt: '2026-10-01T10:00:00Z',
              },
            ],
          },
        } as never;
      }
      if (url === '/inbox/conversations/conv-1/messages') {
        return {
          data: {
            data: [
              {
                id: 'msg-1',
                conversationId: 'conv-1',
                direction: 'inbound',
                senderUserId: null,
                senderName: null,
                messageType: 'text',
                content: 'Hi, I need assistance with my order',
                mediaUrl: null,
                metaMessageId: 'wamid.123',
                status: 'delivered',
                errorCode: null,
                errorMessage: null,
                sentAt: '2026-10-01T12:00:00Z',
                deliveredAt: '2026-10-01T12:00:01Z',
                readAt: null,
                createdAt: '2026-10-01T12:00:00Z',
              },
            ],
          },
        } as never;
      }
      return { data: { data: [] } } as never;
    });

    const postSpy = vi.spyOn(api, 'post').mockImplementation(async (url: string, body?: any) => {
      if (url === '/inbox/conversations/conv-1/read') {
        return { data: { data: { success: true } } } as never;
      }
      if (url === '/inbox/conversations/conv-1/messages') {
        return {
          data: {
            data: {
              id: 'msg-2',
              conversationId: 'conv-1',
              direction: 'outbound',
              senderUserId: 'user-1',
              senderName: 'You',
              messageType: 'text',
              content: body?.content || '',
              metaMessageId: 'wamid.out.456',
              status: 'sent',
              sentAt: '2026-10-01T12:05:00Z',
              createdAt: '2026-10-01T12:05:00Z',
            },
          },
        } as never;
      }
      return { data: { data: {} } } as never;
    });

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <InboxPage />
        </MemoryRouter>
      </QueryClientProvider>
    );

    // 1. Verify inbox title and conversation list render
    expect(await screen.findByText('Shared Team Inbox')).toBeInTheDocument();
    expect(await screen.findByText('Alice Smith')).toBeInTheDocument();
    expect(screen.getByText('+919876543210')).toBeInTheDocument();
    expect(screen.getByText('Hi, I need assistance with my order')).toBeInTheDocument();

    // 2. Click conversation to view thread
    const convCard = screen.getByText('Alice Smith');
    fireEvent.click(convCard);

    // 3. Verify 24h window badge and message bubble render
    expect(await screen.findByText(/24h Window Open/)).toBeInTheDocument();
    const chatBubble = await screen.findAllByText('Hi, I need assistance with my order');
    expect(chatBubble.length).toBeGreaterThan(0);

    // 4. Type and send a reply
    const input = screen.getByPlaceholderText(/Type your reply.../i);
    fireEvent.change(input, { target: { value: 'Hello Alice, how can I help you today?' } });

    const form = input.closest('form')!;
    fireEvent.submit(form);

    // 5. Verify API call with waitFor
    await waitFor(() => {
      expect(postSpy).toHaveBeenCalledWith(
        '/inbox/conversations/conv-1/messages',
        { content: 'Hello Alice, how can I help you today?' }
      );
    });
  });
});
