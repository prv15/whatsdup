export interface Contact { id: string; phone: string; name: string | null; email: string | null; tags: string[]; consentStatus: 'opted_in' | 'opted_out' | 'unknown'; consentAt: string | null; source: string; createdAt: string; updatedAt: string }
export interface ContactImport { id: string; fileName: string; status: string; totalRows: number; importedRows: number; updatedRows: number; skippedRows: number; errors: { row: number; message: string }[]; createdAt: string; completedAt: string | null }
export interface ContactGroup { id: string; name: string; description: string | null; memberCount: number; createdAt: string; updatedAt: string }
export interface ContactsResponse { contacts: Contact[]; groups: ContactGroup[]; imports: ContactImport[] }
export interface MessageTemplate { id: string; name: string; language: string; category: 'marketing' | 'utility' | 'authentication'; headerType: 'none' | 'image'; headerMediaUrl: string | null; body: string; variables?: string[]; status: 'draft' | 'pending' | 'approved' | 'rejected'; rejectionReason: string | null; createdAt: string; updatedAt: string }
export interface CampaignVariableMapping { type: 'contact_field' | 'static'; value: string; fallback?: string | undefined }
export interface Campaign { id: string; name: string; audienceType: 'all_opted_in' | 'selected' | 'groups'; status: string; scheduledAt: string | null; launchedAt: string | null; completedAt: string | null; recipientCount: number; acceptedCount: number; deliveredCount: number; readCount: number; failedCount: number; failureCode: string | null; failureMessage: string | null; templateName: string; templateLanguage: string; variableMappings?: Record<string, CampaignVariableMapping | string> | null; createdAt: string }
export interface CampaignRecipient { id: string; contactId: string; phone: string; contactName: string | null; status: 'queued' | 'accepted' | 'sent' | 'delivered' | 'read' | 'failed'; metaMessageId: string | null; failureCode: string | null; failureMessage: string | null; sentAt: string | null; deliveredAt: string | null; readAt: string | null }
export interface QuotaOverview {
  plan: { name: string; code: string; status: string };
  contacts: { used: number; limit: number | null; percentage: number };
  monthlyRecipients: { used: number; limit: number | null; percentage: number; periodStart?: string; periodEnd?: string };
  phoneNumbers: { used: number; limit: number | null };
}
export interface WorkspaceDashboard {
  metrics: { messagesToday: number; contacts: number; approvedTemplates: number; scheduledCampaigns: number };
  metaStatus: string;
  metaBusinessId?: string | null;
  businessVerificationStatus?: 'unverified' | 'pending' | 'verified' | 'failed';
  verificationInitiatedAt?: string | null;
  messagingLimitTier?: string | null;
  quota?: QuotaOverview;
}
export interface ConversationSummary {
  id: string;
  status: 'open' | 'closed' | 'snoozed';
  lastMessageAt: string;
  windowExpiresAt: string | null;
  isWindowOpen: boolean;
  windowRemainingMinutes: number;
  unreadCount: number;
  assignedUserId: string | null;
  assignedUserName: string | null;
  contact: { id: string; name: string | null; phone: string; email?: string | null; tags?: string[]; consentStatus?: string };
  lastMessage: { content: string; direction: 'inbound' | 'outbound'; status: string } | null;
  createdAt: string;
}
export interface ConversationMessage {
  id: string;
  conversationId: string;
  direction: 'inbound' | 'outbound';
  senderUserId: string | null;
  senderName: string | null;
  messageType: 'text' | 'image' | 'document' | 'template' | 'interactive';
  content: string;
  mediaUrl: string | null;
  metaMessageId: string | null;
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  errorCode: string | null;
  errorMessage: string | null;
  sentAt: string | null;
  deliveredAt: string | null;
  readAt: string | null;
  createdAt: string;
}
