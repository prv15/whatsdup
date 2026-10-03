import { lazy, Suspense, useEffect, useState, useSyncExternalStore } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AppShell } from '../components/layout/AppShell';
import { AdminShell } from '../components/layout/AdminShell';
import { api } from '../services/api';
import { authStore } from '../stores/authStore';
import type { AuthPayload } from '../types/auth';
const LoginPage = lazy(() => import('../features/auth/LoginPage').then((m) => ({ default: m.LoginPage })));
const ForgotPasswordPage = lazy(() => import('../features/auth/PasswordRecoveryPages').then((m) => ({ default: m.ForgotPasswordPage })));
const ResetPasswordPage = lazy(() => import('../features/auth/PasswordRecoveryPages').then((m) => ({ default: m.ResetPasswordPage })));
const PrivacyPolicyPage = lazy(() => import('../features/legal/LegalPages').then((m) => ({ default: m.PrivacyPolicyPage })));
const TermsPage = lazy(() => import('../features/legal/LegalPages').then((m) => ({ default: m.TermsPage })));
const DataDeletionPage = lazy(() => import('../features/legal/LegalPages').then((m) => ({ default: m.DataDeletionPage })));
const DashboardPage = lazy(() => import('../features/dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })));
const AdminDashboardPage = lazy(() => import('../features/admin/AdminDashboardPage').then((m) => ({ default: m.AdminDashboardPage })));
const AdminBusinessesPage = lazy(() => import('../features/admin/AdminBusinessesPage').then((m) => ({ default: m.AdminBusinessesPage })));
const AdminUsersPage = lazy(() => import('../features/admin/AdminUsersPage').then((m) => ({ default: m.AdminUsersPage })));
const AdminPlansPage = lazy(() => import('../features/admin/AdminPlansPage').then((m) => ({ default: m.AdminPlansPage })));
const AdminMetaConnectionsPage = lazy(() => import('../features/admin/AdminMetaConnectionsPage').then((m) => ({ default: m.AdminMetaConnectionsPage })));
const AdminQueuePage = lazy(() => import('../features/admin/AdminQueuePage').then((m) => ({ default: m.AdminQueuePage })));
const AdminAuditLogsPage = lazy(() => import('../features/admin/AdminAuditLogsPage').then((m) => ({ default: m.AdminAuditLogsPage })));
const MetaConnectionPage = lazy(() => import('../features/meta/MetaConnectionPage').then((m) => ({ default: m.MetaConnectionPage })));
const ContactsPage = lazy(() => import('../features/contacts/ContactsPage').then((m) => ({ default: m.ContactsPage })));
const TemplatesPage = lazy(() => import('../features/templates/TemplatesPage').then((m) => ({ default: m.TemplatesPage })));
const CampaignsPage = lazy(() => import('../features/campaigns/CampaignsPage').then((m) => ({ default: m.CampaignsPage })));
const InboxPage = lazy(() => import('../features/inbox/InboxPage').then((m) => ({ default: m.InboxPage })));
const SettingsPage = lazy(() => import('../features/settings/SettingsPage').then((m) => ({ default: m.SettingsPage })));
const ReportsPage = lazy(() => import('../features/reports/ReportsPage').then((m) => ({ default: m.ReportsPage })));
function BusinessProtected() { const location = useLocation(); const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }}/>; return user.scope === 'business' ? <AppShell/> : <Navigate to="/admin" replace/>; }
function PlatformProtected() { const location = useLocation(); const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }}/>; return user.scope === 'platform' ? <AdminShell/> : <Navigate to="/dashboard" replace/>; }
function HomeRedirect() { const { user } = useSyncExternalStore(authStore.subscribe, authStore.getSnapshot); return <Navigate to={user?.scope === 'platform' ? '/admin' : user ? '/dashboard' : '/login'} replace/>; }
export function App() {
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    const storedRt = authStore.getRefreshToken();
    if (!storedRt) {
      setBooting(false);
      return;
    }
    const timeout = setTimeout(() => {
      setBooting(false);
    }, 2500);

    api.post<AuthPayload>('/auth/refresh', { refreshToken: storedRt })
      .then(({ data }) => authStore.setSession(data.accessToken, data.user, data.refreshToken))
      .catch(() => authStore.clear())
      .finally(() => {
        clearTimeout(timeout);
        setBooting(false);
      });

    return () => clearTimeout(timeout);
  }, []);

  if (booting) return <div className="grid min-h-screen place-items-center bg-canvas"><div className="h-10 w-10 animate-pulse rounded-2xl bg-brand-500"/></div>;
  return <Suspense fallback={<div className="grid min-h-screen place-items-center">Loading…</div>}><Routes><Route path="/login" element={<LoginPage/>}/><Route path="/forgot-password" element={<ForgotPasswordPage/>}/><Route path="/reset-password" element={<ResetPasswordPage/>}/><Route path="/privacy" element={<PrivacyPolicyPage/>}/><Route path="/terms" element={<TermsPage/>}/><Route path="/data-deletion" element={<DataDeletionPage/>}/><Route element={<BusinessProtected/>}><Route path="/dashboard" element={<DashboardPage/>}/><Route path="/meta" element={<MetaConnectionPage/>}/><Route path="/contacts" element={<ContactsPage/>}/><Route path="/inbox" element={<InboxPage/>}/><Route path="/templates" element={<TemplatesPage/>}/><Route path="/campaigns" element={<CampaignsPage/>}/><Route path="/reports" element={<ReportsPage/>}/><Route path="/settings" element={<SettingsPage/>}/></Route><Route element={<PlatformProtected/>}><Route path="/admin" element={<AdminDashboardPage/>}/><Route path="/admin/businesses" element={<AdminBusinessesPage/>}/><Route path="/admin/meta-connections" element={<AdminMetaConnectionsPage/>}/><Route path="/admin/queue" element={<AdminQueuePage/>}/><Route path="/admin/users" element={<AdminUsersPage/>}/><Route path="/admin/plans" element={<AdminPlansPage/>}/><Route path="/admin/audit-logs" element={<AdminAuditLogsPage/>}/></Route><Route path="*" element={<HomeRedirect/>}/></Routes></Suspense>;
}
