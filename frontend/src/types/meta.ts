export interface MetaConfiguration { enabled: boolean; appId: string; configId: string; graphVersion: string; missing: string[]; requiresHttps: boolean }
export interface MetaConnectionStatus {
  status: 'not_connected' | 'connecting' | 'connected' | 'action_required' | 'verification_required' | 'token_invalid' | 'restricted' | 'disconnected' | 'webhook_error';
  metaBusinessId?: string | null; connectedAt: string | null; lastSyncedAt: string | null; lastTestedAt: string | null;
  waba: { id: string; name: string | null; currency: string | null; reviewStatus: string | null } | null;
  phone: { id: string; number: string | null; verifiedName: string | null; qualityRating: string | null; nameStatus: string | null; registrationStatus: string | null; isDefault: boolean } | null;
  webhookStatus: string; error: { code: string; message: string } | null;
}
