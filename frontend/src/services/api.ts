import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { authStore } from '../stores/authStore';
import type { AuthPayload } from '../types/auth';
const baseURL = import.meta.env.VITE_API_BASE_URL;
if (!baseURL) throw new Error('VITE_API_BASE_URL is required');
export const api = axios.create({ baseURL, withCredentials: true, headers: { Accept: 'application/json' } });
let refreshRequest: Promise<AuthPayload> | null = null;
api.interceptors.request.use((config) => { const token = authStore.getSnapshot().accessToken; if (token) config.headers.Authorization = `Bearer ${token}`; return config; });
api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const request = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined;
  if (!request || request._retried || error.response?.status !== 401 || request.url?.includes('/auth/refresh')) throw error;
  request._retried = true;
  refreshRequest ??= api.post<AuthPayload>('/auth/refresh').then(({ data }) => data).finally(() => { refreshRequest = null; });
  try { const session = await refreshRequest; authStore.setSession(session.accessToken, session.user); request.headers.Authorization = `Bearer ${session.accessToken}`; return api(request); }
  catch (refreshError) { authStore.clear(); throw refreshError; }
});
