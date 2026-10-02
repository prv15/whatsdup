export interface AdminMetrics {
  businesses: number;
  activeBusinesses: number;
  users: number;
  activeSessions: number;
  queuedJobs: number;
  failedJobs: number;
  connectedWhatsApp?: number;
  totalCampaigns?: number;
}

export interface AdminBusinessPlan {
  id: string;
  name: string;
  code: string;
  status: string;
  billingInterval: string;
  currentPeriodEndsAt: string | null;
}

export interface AdminBusinessWhatsApp {
  phoneNumber: string;
  verifiedName: string | null;
  qualityRating: string | null;
  wabaId: string | null;
  connectionStatus: string;
}

export interface AdminBusiness {
  id: string;
  name: string;
  slug: string;
  timezone: string;
  status: 'pending' | 'active' | 'suspended' | 'archived';
  ownerName: string | null;
  ownerEmail: string | null;
  userCount: number;
  createdAt: string;
  plan?: AdminBusinessPlan | null;
  whatsapp?: AdminBusinessWhatsApp | null;
}

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  status: string;
  emailVerified: boolean;
  lastLoginAt: string | null;
  createdAt: string;
  workspaces: string;
  roleName?: string;
}

export interface AdminPlanLimits {
  phoneNumbers: number | null;
  teamMembers: number | null;
  contacts: number | null;
  monthlyRecipients: number | null;
}

export interface AdminPlan {
  id: string;
  name: string;
  code: string;
  description: string;
  priceMinor: number | null;
  annualPriceMinor: number | null;
  currency: string;
  billingInterval: 'month' | 'year' | 'custom';
  status: 'active' | 'archived';
  isPublic: boolean;
  sortOrder: number;
  limits: AdminPlanLimits;
  features: string[];
  createdAt: string;
  updatedAt: string;
}

export interface AdminMetaConnection {
  id: string;
  businessId: string;
  businessName: string;
  businessSlug: string;
  connectionStatus: string;
  metaBusinessId: string | null;
  wabaId: string | null;
  wabaName: string | null;
  wabaReviewStatus: string | null;
  phoneNumberId: string | null;
  displayPhoneNumber: string | null;
  verifiedName: string | null;
  qualityRating: string | null;
  nameStatus: string | null;
  webhookStatus: string;
  lastErrorMessage: string | null;
  connectedAt: string | null;
  lastSyncedAt: string | null;
}

export interface AdminQueueHealth {
  ready: number;
  reserved: number;
  completed: number;
  failed: number;
  permanent_failed: number;
  stale_reserved: number;
  oldest_ready_seconds: number | null;
}

export interface AdminFailedJob {
  id: number;
  queue_job_id: number;
  business_id: string | null;
  queue: string;
  job_type: string;
  error_type: string;
  error_message: string;
  failed_at: string;
  retried_at: string | null;
  business_name: string | null;
}

export interface AdminAuditLog {
  id: string;
  businessId: string | null;
  businessName: string | null;
  userId: string | null;
  userName: string | null;
  userEmail: string | null;
  action: string;
  subjectType: string;
  subjectId: string | null;
  metadata: Record<string, unknown>;
  createdAt: string;
}