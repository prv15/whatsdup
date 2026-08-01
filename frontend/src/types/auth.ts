export interface BusinessSummary { id: string; name: string; slug: string; timezone: string; status: string }
export interface CurrentUser { id: string; name: string; email: string; emailVerified: boolean; scope: 'platform' | 'business'; business: BusinessSummary | null; roles: string[]; permissions: string[] }
export interface AuthPayload { accessToken: string; expiresIn: number; user: CurrentUser }
