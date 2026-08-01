export interface BusinessSummary { id: string; name: string; slug: string; timezone: string; status: string }
export interface CurrentUser { id: string; name: string; email: string; emailVerified: boolean; business: BusinessSummary; roles: string[]; permissions: string[] }
export interface AuthPayload { accessToken: string; expiresIn: number; user: CurrentUser }
