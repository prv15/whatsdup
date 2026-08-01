import { lazy, Suspense, useEffect, useState, useSyncExternalStore } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AppShell } from '../components/layout/AppShell';
import { api } from '../services/api';
import { authStore } from '../stores/authStore';
import type { AuthPayload } from '../types/auth';
const LoginPage = lazy(() => import('../features/auth/LoginPage').then((m) => ({ default: m.LoginPage })));
const DashboardPage = lazy(() => import('../features/dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })));
const MilestoneNoticePage = lazy(() => import('../features/shared/MilestoneNoticePage').then((m) => ({ default: m.MilestoneNoticePage })));
function Protected() { const location = useLocation(); const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); return user ? <AppShell/> : <Navigate to="/login" replace state={{ from: location.pathname }}/>; }
export function App() {
  const [booting, setBooting] = useState(true);
  useEffect(() => { api.post<AuthPayload>('/auth/refresh').then(({ data }) => authStore.setSession(data.accessToken, data.user)).catch(() => authStore.clear()).finally(() => setBooting(false)); }, []);
  if (booting) return <div className="grid min-h-screen place-items-center bg-canvas"><div className="h-10 w-10 animate-pulse rounded-2xl bg-brand-500"/></div>;
  return <Suspense fallback={<div className="grid min-h-screen place-items-center">Loading…</div>}><Routes><Route path="/login" element={<LoginPage/>}/><Route element={<Protected/>}><Route path="/dashboard" element={<DashboardPage/>}/>{['campaigns', 'templates', 'contacts', 'reports', 'settings'].map((route) => <Route key={route} path={`/${route}`} element={<MilestoneNoticePage title={route[0]!.toUpperCase() + route.slice(1)}/>}/>)}</Route><Route path="*" element={<Navigate to="/dashboard" replace/>}/></Routes></Suspense>;
}
