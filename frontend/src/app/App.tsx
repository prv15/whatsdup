import { lazy, Suspense, useEffect, useState, useSyncExternalStore } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AppShell } from '../components/layout/AppShell';
import { AdminShell } from '../components/layout/AdminShell';
import { api } from '../services/api';
import { authStore } from '../stores/authStore';
import type { AuthPayload } from '../types/auth';
const LoginPage = lazy(() => import('../features/auth/LoginPage').then((m) => ({ default: m.LoginPage })));
const DashboardPage = lazy(() => import('../features/dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })));
const MilestoneNoticePage = lazy(() => import('../features/shared/MilestoneNoticePage').then((m) => ({ default: m.MilestoneNoticePage })));
const AdminDashboardPage = lazy(() => import('../features/admin/AdminDashboardPage').then((m) => ({ default: m.AdminDashboardPage })));
const AdminBusinessesPage = lazy(() => import('../features/admin/AdminBusinessesPage').then((m) => ({ default: m.AdminBusinessesPage })));
const AdminUsersPage = lazy(() => import('../features/admin/AdminUsersPage').then((m) => ({ default: m.AdminUsersPage })));
const MetaConnectionPage = lazy(() => import('../features/meta/MetaConnectionPage').then((m) => ({ default: m.MetaConnectionPage })));
function BusinessProtected() { const location = useLocation(); const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }}/>; return user.scope === 'business' ? <AppShell/> : <Navigate to="/admin" replace/>; }
function PlatformProtected() { const location = useLocation(); const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }}/>; return user.scope === 'platform' ? <AdminShell/> : <Navigate to="/dashboard" replace/>; }
function HomeRedirect() { const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); return <Navigate to={user?.scope === 'platform' ? '/admin' : user ? '/dashboard' : '/login'} replace/>; }
export function App() {
  const [booting, setBooting] = useState(true);
  useEffect(() => { api.post<AuthPayload>('/auth/refresh').then(({ data }) => authStore.setSession(data.accessToken, data.user)).catch(() => authStore.clear()).finally(() => setBooting(false)); }, []);
  if (booting) return <div className="grid min-h-screen place-items-center bg-canvas"><div className="h-10 w-10 animate-pulse rounded-2xl bg-brand-500"/></div>;
  return <Suspense fallback={<div className="grid min-h-screen place-items-center">Loading…</div>}><Routes><Route path="/login" element={<LoginPage/>}/><Route element={<BusinessProtected/>}><Route path="/dashboard" element={<DashboardPage/>}/><Route path="/meta" element={<MetaConnectionPage/>}/>{['campaigns', 'templates', 'contacts', 'reports', 'settings'].map((route) => <Route key={route} path={`/${route}`} element={<MilestoneNoticePage title={route[0]!.toUpperCase() + route.slice(1)}/>}/>)}</Route><Route element={<PlatformProtected/>}><Route path="/admin" element={<AdminDashboardPage/>}/><Route path="/admin/businesses" element={<AdminBusinessesPage/>}/><Route path="/admin/users" element={<AdminUsersPage/>}/></Route><Route path="*" element={<HomeRedirect/>}/></Routes></Suspense>;
}
