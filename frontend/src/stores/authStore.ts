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
  setSession(token: string, nextUser: CurrentUser) { accessToken = token; user = nextUser; update(); },
  clear() { accessToken = null; user = null; update(); },
  subscribe(listener: Listener) { listeners.add(listener); return () => { listeners.delete(listener); }; },
};
