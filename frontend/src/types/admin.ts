export interface AdminMetrics { businesses: number; activeBusinesses: number; users: number; activeSessions: number; queuedJobs: number; failedJobs: number }
export interface AdminBusiness { id: string; name: string; slug: string; timezone: string; status: 'pending' | 'active' | 'suspended' | 'archived'; ownerName: string | null; ownerEmail: string | null; userCount: number; createdAt: string }
export interface AdminUser { id: string; name: string; email: string; status: string; emailVerified: boolean; lastLoginAt: string | null; createdAt: string; workspaces: string }
