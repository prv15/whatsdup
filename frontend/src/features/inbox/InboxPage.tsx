import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertCircle,
  ArrowRight,
  Check,
  CheckCheck,
  Clock,
  CornerDownLeft,
  Filter,
  Inbox as InboxIcon,
  MessageSquare,
  RefreshCw,
  Search,
  Send,
  ShieldAlert,
  User,
  XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { api } from '../../services/api';
import type { ConversationMessage, ConversationSummary } from '../../types/operations';

export function InboxPage() {
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<'all' | 'open' | 'closed'>('open');
  const [search, setSearch] = useState('');
  const [messageText, setMessageText] = useState('');
  const [sendError, setSendError] = useState<string | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // 1. Fetch conversations list
  const { data: conversations = [], isLoading: loadingConversations, refetch: refetchConversations } = useQuery({
    queryKey: ['inbox', 'conversations', filterStatus],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (filterStatus !== 'all') params.set('status', filterStatus);
      const res = await api.get<{ data: ConversationSummary[] }>(`/inbox/conversations?${params.toString()}`);
      return res.data.data;
    },
    refetchInterval: 10000,
  });

  // Filter conversations locally by search query
  const filteredConversations = useMemo(() => {
    if (!search.trim()) return conversations;
    const q = search.toLowerCase();
    return conversations.filter(
      (c) =>
        (c.contact.name && c.contact.name.toLowerCase().includes(q)) ||
        c.contact.phone.includes(q) ||
        (c.lastMessage?.content && c.lastMessage.content.toLowerCase().includes(q))
    );
  }, [conversations, search]);

  // Selected conversation object
  const selectedConv = useMemo(() => {
    return conversations.find((c) => c.id === selectedId) || null;
  }, [conversations, selectedId]);

  // 2. Fetch messages for active conversation
  const { data: messages = [], isLoading: loadingMessages, refetch: refetchMessages } = useQuery({
    queryKey: ['inbox', 'messages', selectedId],
    queryFn: async () => {
      if (!selectedId) return [];
      const res = await api.get<{ data: ConversationMessage[] }>(`/inbox/conversations/${selectedId}/messages`);
      return res.data.data;
    },
    enabled: !!selectedId,
    refetchInterval: 6000,
  });

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    if (messages.length > 0 && typeof messagesEndRef.current?.scrollIntoView === 'function') {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages.length]);

  // Mark conversation as read on select
  useEffect(() => {
    if (selectedId && selectedConv && selectedConv.unreadCount > 0) {
      api.post(`/inbox/conversations/${selectedId}/read`).then(() => {
        queryClient.invalidateQueries({ queryKey: ['inbox', 'conversations'] });
      }).catch(() => {});
    }
  }, [selectedId, selectedConv?.unreadCount, queryClient]);

  // 3. Send message mutation
  const sendMutation = useMutation({
    mutationFn: async (content: string) => {
      if (!selectedId) throw new Error('No conversation selected');
      setSendError(null);
      const res = await api.post<{ data: ConversationMessage }>(`/inbox/conversations/${selectedId}/messages`, { content });
      return res.data.data;
    },
    onSuccess: (newMsg) => {
      setMessageText('');
      setSendError(null);
      queryClient.setQueryData<ConversationMessage[]>(['inbox', 'messages', selectedId], (old = []) => [...old, newMsg]);
      queryClient.invalidateQueries({ queryKey: ['inbox', 'conversations'] });
    },
    onError: (err: any) => {
      const msg = err.response?.data?.error?.message || err.message || 'Failed to send message.';
      setSendError(msg);
    },
  });

  // 4. Toggle conversation status mutation
  const statusMutation = useMutation({
    mutationFn: async ({ id, status }: { id: string; status: 'open' | 'closed' }) => {
      const res = await api.patch<{ data: ConversationSummary }>(`/inbox/conversations/${id}`, { status });
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inbox', 'conversations'] });
    },
  });

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!messageText.trim() || sendMutation.isPending) return;
    sendMutation.mutate(messageText.trim());
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend(e);
    }
  };

  const totalUnread = useMemo(() => {
    return conversations.reduce((acc, c) => acc + (c.unreadCount || 0), 0);
  }, [conversations]);

  return (
    <div className="h-[calc(100vh-130px)] flex flex-col rounded-2xl border border-line bg-white shadow-card overflow-hidden">
      {/* Top Banner / Stats Header */}
      <div className="flex items-center justify-between border-b border-line px-6 py-4 bg-canvas/40">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
            <InboxIcon size={22} />
          </div>
          <div>
            <h1 className="text-lg font-semibold text-ink">Shared Team Inbox</h1>
            <p className="text-xs text-muted">Two-way live WhatsApp customer conversations & care</p>
          </div>
        </div>
        <div className="flex items-center gap-4">
          {totalUnread > 0 && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800">
              <span className="h-2 w-2 rounded-full bg-brand-600 animate-pulse" />
              {totalUnread} unread
            </span>
          )}
          <button
            onClick={() => {
              refetchConversations();
              if (selectedId) refetchMessages();
            }}
            className="flex items-center gap-1.5 rounded-xl border border-line bg-white px-3 py-1.5 text-xs font-medium text-muted hover:bg-gray-50 hover:text-ink transition"
            title="Refresh inbox"
          >
            <RefreshCw size={14} /> Refresh
          </button>
        </div>
      </div>

      {/* Main 2-Column Split Pane */}
      <div className="flex-1 grid grid-cols-1 md:grid-cols-[340px_1fr] lg:grid-cols-[380px_1fr] overflow-hidden">
        {/* Left Column: Conversations List */}
        <div className="flex flex-col border-r border-line bg-white h-full overflow-hidden">
          {/* Search & Filter Header */}
          <div className="p-3 border-b border-line space-y-2.5">
            <div className="relative">
              <Search className="absolute left-3 top-2.5 text-muted" size={16} />
              <input
                type="text"
                placeholder="Search contact or phone..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full rounded-xl border border-line bg-canvas/60 py-2 pl-9 pr-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
              />
            </div>
            {/* Filter Tabs */}
            <div className="flex items-center gap-1 rounded-xl bg-canvas p-1">
              {(['open', 'all', 'closed'] as const).map((tab) => (
                <button
                  key={tab}
                  onClick={() => setFilterStatus(tab)}
                  className={`flex-1 rounded-lg py-1 text-xs font-medium capitalize transition ${
                    filterStatus === tab
                      ? 'bg-white text-ink shadow-sm'
                      : 'text-muted hover:text-ink'
                  }`}
                >
                  {tab}
                </button>
              ))}
            </div>
          </div>

          {/* Conversations Scroll Area */}
          <div className="flex-1 overflow-y-auto divide-y divide-line">
            {loadingConversations ? (
              <div className="p-8 text-center text-xs text-muted">Loading chats…</div>
            ) : filteredConversations.length === 0 ? (
              <div className="p-8 text-center text-muted">
                <MessageSquare className="mx-auto mb-2 text-gray-300" size={28} />
                <p className="text-xs font-medium">No conversations found</p>
                <p className="mt-1 text-[11px] text-muted">
                  {filterStatus === 'open'
                    ? 'No open chats. New customer messages appear here automatically.'
                    : 'No conversation matches this filter.'}
                </p>
              </div>
            ) : (
              filteredConversations.map((conv) => {
                const isSelected = conv.id === selectedId;
                const contactInitial = (conv.contact.name || conv.contact.phone || '?').charAt(0).toUpperCase();

                return (
                  <button
                    key={conv.id}
                    onClick={() => setSelectedId(conv.id)}
                    className={`w-full text-left p-3.5 flex items-start gap-3 transition ${
                      isSelected
                        ? 'bg-brand-50/80 border-l-4 border-l-brand-600'
                        : 'hover:bg-gray-50/80'
                    }`}
                  >
                    {/* Contact Avatar */}
                    <div className="relative shrink-0">
                      <div className="h-10 w-10 rounded-full bg-brand-100 text-brand-800 flex items-center justify-center font-semibold text-sm">
                        {contactInitial}
                      </div>
                      {conv.isWindowOpen && (
                        <span
                          className="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-500 border-2 border-white"
                          title="24h Service Window Active"
                        />
                      )}
                    </div>

                    {/* Chat Preview Info */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-1 mb-1">
                        <span className="text-xs font-semibold text-ink truncate">
                          {conv.contact.name || conv.contact.phone}
                        </span>
                        <span className="text-[10px] text-muted shrink-0">
                          {conv.lastMessageAt ? new Date(conv.lastMessageAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                        </span>
                      </div>

                      <p className="text-[11px] text-muted truncate">
                        {conv.lastMessage ? (
                          <>
                            {conv.lastMessage.direction === 'outbound' && (
                              <span className="font-medium text-ink mr-1">You:</span>
                            )}
                            {conv.lastMessage.content}
                          </>
                        ) : (
                          <span className="italic">No messages yet</span>
                        )}
                      </p>

                      <div className="mt-2 flex items-center justify-between">
                        <span className="text-[10px] text-muted font-mono">
                          {conv.contact.phone}
                        </span>
                        <div className="flex items-center gap-1.5">
                          {conv.status === 'closed' && (
                            <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium text-muted">
                              Closed
                            </span>
                          )}
                          {conv.unreadCount > 0 && (
                            <span className="rounded-full bg-brand-600 text-white px-1.5 py-0.2 text-[10px] font-bold">
                              {conv.unreadCount}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* Right Column: Chat History & Composer */}
        <div className="flex flex-col bg-canvas/30 h-full overflow-hidden">
          {selectedConv ? (
            <>
              {/* Active Chat Header */}
              <div className="p-4 border-b border-line bg-white flex items-center justify-between gap-4">
                <div className="flex items-center gap-3 min-w-0">
                  <div className="h-10 w-10 rounded-full bg-brand-100 text-brand-800 flex items-center justify-center font-bold text-sm shrink-0">
                    {(selectedConv.contact.name || selectedConv.contact.phone).charAt(0).toUpperCase()}
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <h2 className="text-sm font-semibold text-ink truncate">
                        {selectedConv.contact.name || selectedConv.contact.phone}
                      </h2>
                      {selectedConv.isWindowOpen ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[10px] font-medium border border-emerald-200">
                          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                          24h Window Open ({Math.floor(selectedConv.windowRemainingMinutes / 60)}h {selectedConv.windowRemainingMinutes % 60}m)
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 px-2 py-0.5 text-[10px] font-medium border border-amber-200" title="Free-form messages restricted by Meta until customer replies">
                          <Clock size={11} />
                          Window Expired
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-muted font-mono">{selectedConv.contact.phone}</p>
                  </div>
                </div>

                {/* Status Toggle & Details */}
                <div className="flex items-center gap-2">
                  <button
                    onClick={() =>
                      statusMutation.mutate({
                        id: selectedConv.id,
                        status: selectedConv.status === 'open' ? 'closed' : 'open',
                      })
                    }
                    disabled={statusMutation.isPending}
                    className="rounded-xl border border-line bg-white px-3 py-1.5 text-xs font-medium text-muted hover:bg-gray-50 hover:text-ink transition flex items-center gap-1.5"
                  >
                    {selectedConv.status === 'open' ? (
                      <>
                        <XCircle size={14} className="text-gray-400" /> Close Chat
                      </>
                    ) : (
                      <>
                        <RefreshCw size={14} className="text-emerald-600" /> Re-open Chat
                      </>
                    )}
                  </button>
                </div>
              </div>

              {/* 24-Hour Policy Notice Banner */}
              {!selectedConv.isWindowOpen && (
                <div className="px-4 py-2 bg-amber-50 border-b border-amber-200 flex items-center gap-2 text-xs text-amber-800">
                  <ShieldAlert size={16} className="shrink-0 text-amber-600" />
                  <span>
                    <strong>Meta 24-Hour Care Window Expired:</strong> Free-form replies are blocked by WhatsApp Cloud API. The customer must message you first or receive a pre-approved template message to re-open the 24h care session.
                  </span>
                </div>
              )}

              {/* Message History Timeline */}
              <div className="flex-1 overflow-y-auto p-4 space-y-3">
                {loadingMessages ? (
                  <div className="p-8 text-center text-xs text-muted">Loading messages…</div>
                ) : messages.length === 0 ? (
                  <div className="p-12 text-center text-muted">
                    <p className="text-xs">No message history yet for this conversation.</p>
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isOutbound = msg.direction === 'outbound';

                    return (
                      <div
                        key={msg.id}
                        className={`flex flex-col ${isOutbound ? 'items-end' : 'items-start'}`}
                      >
                        <div
                          className={`max-w-[78%] sm:max-w-[65%] rounded-2xl px-4 py-2.5 text-xs shadow-sm ${
                            isOutbound
                              ? 'bg-brand-600 text-white rounded-br-xs'
                              : 'bg-white text-ink border border-line rounded-bl-xs'
                          }`}
                        >
                          <p className="whitespace-pre-wrap leading-relaxed">{msg.content}</p>

                          <div
                            className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${
                              isOutbound ? 'text-brand-100' : 'text-muted'
                            }`}
                          >
                            <span>
                              {new Date(msg.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </span>
                            {isOutbound && (
                              <span title={`Status: ${msg.status}`}>
                                {msg.status === 'read' ? (
                                  <CheckCheck size={14} className="text-sky-300" />
                                ) : msg.status === 'delivered' ? (
                                  <CheckCheck size={14} className="text-white/80" />
                                ) : msg.status === 'sent' ? (
                                  <Check size={14} className="text-white/70" />
                                ) : msg.status === 'failed' ? (
                                  <AlertCircle size={14} className="text-red-300" />
                                ) : (
                                  <Clock size={12} className="text-white/60" />
                                )}
                              </span>
                            )}
                          </div>
                        </div>
                        {isOutbound && msg.senderName && (
                          <span className="text-[10px] text-muted mt-0.5 mr-1 font-medium">
                            {msg.senderName}
                          </span>
                        )}
                      </div>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Chat Composer */}
              <div className="p-3 border-t border-line bg-white">
                {sendError && (
                  <div className="mb-2 p-2 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center justify-between">
                    <span>{sendError}</span>
                    <button onClick={() => setSendError(null)} className="text-red-400 hover:text-red-700">
                      ×
                    </button>
                  </div>
                )}
                <form onSubmit={handleSend} className="flex items-end gap-2">
                  <div className="relative flex-1">
                    <textarea
                      value={messageText}
                      onChange={(e) => setMessageText(e.target.value)}
                      onKeyDown={handleKeyDown}
                      disabled={!selectedConv.isWindowOpen || sendMutation.isPending}
                      placeholder={
                        selectedConv.isWindowOpen
                          ? 'Type your reply... (Press Enter to send, Shift+Enter for newline)'
                          : 'Outbound free-form messages disabled (24-hour window expired)'
                      }
                      rows={2}
                      className="w-full resize-none rounded-xl border border-line bg-canvas/40 p-2.5 text-xs text-ink placeholder:text-muted focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={!messageText.trim() || !selectedConv.isWindowOpen || sendMutation.isPending}
                    className="h-10 px-4 rounded-xl bg-brand-600 text-white font-medium text-xs flex items-center justify-center gap-1.5 hover:bg-brand-700 transition disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                  >
                    {sendMutation.isPending ? (
                      <RefreshCw size={14} className="animate-spin" />
                    ) : (
                      <>
                        <span>Send</span>
                        <Send size={13} />
                      </>
                    )}
                  </button>
                </form>
              </div>
            </>
          ) : (
            /* No Conversation Selected Placeholder */
            <div className="h-full flex flex-col items-center justify-center p-8 text-center text-muted">
              <div className="h-16 w-16 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center mb-4">
                <MessageSquare size={32} />
              </div>
              <h3 className="text-base font-semibold text-ink">Select a Conversation</h3>
              <p className="mt-1 text-xs max-w-sm text-muted">
                Choose an active chat from the left panel to review message history and reply to your WhatsApp customers in real time.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
