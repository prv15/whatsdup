import type { CurrentUser } from '../types/auth';
type Listener = () => void;
let accessToken: string | null = null;
let user: CurrentUser | null = null;
const listeners = new Set<Listener>();
const snapshot = () => ({ accessToken, user });
let currentSnapshot = snapshot();
const update = () => { currentSnapshot = snapshot(); listeners.forEach((listener) => listener()); };
export const authStore = {
  getSnapshot: () => currentSnapshot,
  setSession(token: string, nextUser: CurrentUser, refreshToken?: string) {
    accessToken = token;
    user = nextUser;
    if (refreshToken) {
      try { localStorage.setItem('whatstheup_rt', refreshToken); } catch {}
    }
    update();
  },
  getRefreshToken(): string | null {
    try { return localStorage.getItem('whatstheup_rt'); } catch { return null; }
  },
  clear() {
    accessToken = null;
    user = null;
    try { localStorage.removeItem('whatstheup_rt'); } catch {}
    update();
  },
  subscribe(listener: Listener) { listeners.add(listener); return () => { listeners.delete(listener); }; },
};
