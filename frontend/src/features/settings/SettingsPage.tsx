import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Building2,
  CheckCircle2,
  KeyRound,
  Lock,
  Plus,
  Shield,
  Sparkles,
  Trash2,
  UserCheck,
  Users,
  Zap,
} from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { WorkspaceSettings } from '../../types/operations';

export function SettingsPage() {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'profile' | 'team' | 'quota' | 'security'>('profile');
  const [notice, setNotice] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Invite modal state
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteName, setInviteName] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('Campaign Manager');

  // Remove member modal
  const [memberToRemove, setMemberToRemove] = useState<{ id: string; name: string } | null>(null);

  // Password state
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const { data: settings, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get<{ data: WorkspaceSettings }>('/settings')).data.data,
  });

  // Profile form state initialized from data
  const [bizName, setBizName] = useState<string | null>(null);
  const [legalName, setLegalName] = useState<string | null>(null);
  const [timezone, setTimezone] = useState<string | null>(null);
  const [language, setLanguage] = useState<string | null>(null);
  const [defaultCountryCode, setDefaultCountryCode] = useState<string | null>(null);

  const currentBizName = bizName ?? settings?.business?.name ?? '';
  const currentLegalName = legalName ?? settings?.business?.legalName ?? '';
  const currentTimezone = timezone ?? settings?.business?.timezone ?? 'Asia/Kolkata';
  const currentLanguage = language ?? settings?.business?.language ?? 'en';
  const currentCountryCode = defaultCountryCode ?? settings?.business?.defaultCountryCode ?? '+91';

  // Update profile mutation
  const updateProfile = useMutation({
    mutationFn: async () =>
      (
        await api.patch<{ data: WorkspaceSettings }>('/settings', {
          name: currentBizName,
          legalName: currentLegalName,
          timezone: currentTimezone,
          language: currentLanguage,
          defaultCountryCode: currentCountryCode,
        })
      ).data.data,
    onSuccess: (updated) => {
      queryClient.setQueryData(['settings'], updated);
      setNotice({ type: 'success', message: 'Business settings updated successfully.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message ??
        'Failed to save settings.';
      setNotice({ type: 'error', message: msg });
    },
  });

  // Invite team member mutation
  const inviteMember = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ data: WorkspaceSettings }>('/settings/team/invite', {
          name: inviteName,
          email: inviteEmail,
          role: inviteRole,
        })
      ).data.data,
    onSuccess: (updated) => {
      queryClient.setQueryData(['settings'], updated);
      setShowInviteModal(false);
      setInviteName('');
      setInviteEmail('');
      setNotice({ type: 'success', message: 'Team member added to workspace successfully.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message ??
        'Failed to invite team member.';
      setNotice({ type: 'error', message: msg });
    },
  });

  // Remove team member mutation
  const removeMember = useMutation({
    mutationFn: async (id: string) =>
      (await api.delete<{ data: WorkspaceSettings }>(`/settings/team/${id}`)).data.data,
    onSuccess: (updated) => {
      queryClient.setQueryData(['settings'], updated);
      setMemberToRemove(null);
      setNotice({ type: 'success', message: 'Team member removed from workspace.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message ??
        'Failed to remove team member.';
      setNotice({ type: 'error', message: msg });
    },
  });

  // Change password mutation
  const changePasswordMutation = useMutation({
    mutationFn: async () =>
      (
        await api.post('/settings/security/change-password', {
          currentPassword,
          newPassword,
        })
      ).data,
    onSuccess: () => {
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setNotice({ type: 'success', message: 'Password updated successfully.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message ??
        'Failed to update password.';
      setNotice({ type: 'error', message: msg });
    },
  });

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword.length < 8) {
      setNotice({ type: 'error', message: 'New password must be at least 8 characters long.' });
      return;
    }
    if (newPassword !== confirmPassword) {
      setNotice({ type: 'error', message: 'New passwords do not match.' });
      return;
    }
    changePasswordMutation.mutate();
  };

  const quota = settings?.quota;
  const recipientLimit = quota?.monthlyRecipients?.limit ?? null;
  const recipientUsed = quota?.monthlyRecipients?.used ?? 0;
  const recipientPercentage = quota?.monthlyRecipients?.percentage ?? 0;

  const contactsLimit = quota?.contacts?.limit ?? null;
  const contactsUsed = quota?.contacts?.used ?? 0;
  const contactsPercentage = quota?.contacts?.percentage ?? 0;

  if (isLoading) {
    return <div className="p-8 text-center text-muted">Loading workspace settings…</div>;
  }

  return (
    <div className="space-y-8">
      <div>
        <p className="text-sm font-semibold text-brand-700">Administration</p>
        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Workspace Settings</h1>
        <p className="mt-2 text-muted">
          Manage your business profile, team permissions, security settings, and subscription quota.
        </p>
      </div>

      {notice && (
        <div
          className={`flex items-start justify-between gap-3 rounded-2xl border p-4 text-sm ${
            notice.type === 'success'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border-red-200 bg-red-50 text-red-800'
          }`}
        >
          <div className="flex items-center gap-2.5">
            {notice.type === 'success' ? (
              <CheckCircle2 size={18} className="shrink-0 text-emerald-600" />
            ) : (
              <Lock size={18} className="shrink-0 text-red-600" />
            )}
            <span>{notice.message}</span>
          </div>
          <button
            onClick={() => setNotice(null)}
            className="ml-auto font-semibold text-muted hover:text-ink"
            aria-label="Dismiss notice"
          >
            ×
          </button>
        </div>
      )}

      {/* Settings Navigation Tabs */}
      <div className="flex border-b border-line gap-6">
        {[
          { id: 'profile', label: 'Business Profile', icon: Building2 },
          { id: 'team', label: `Team Members (${settings?.team?.length ?? 0})`, icon: Users },
          { id: 'quota', label: 'Plan & Quotas', icon: Sparkles },
          { id: 'security', label: 'Account Security', icon: KeyRound },
        ].map((tab) => {
          const Icon = tab.icon;
          const active = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => {
                setActiveTab(tab.id as typeof activeTab);
                setNotice(null);
              }}
              className={`flex items-center gap-2 border-b-2 pb-3.5 text-sm font-semibold transition-colors ${
                active
                  ? 'border-brand-700 text-brand-700'
                  : 'border-transparent text-muted hover:text-ink'
              }`}
            >
              <Icon size={16} />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* Tab 1: Business Profile */}
      {activeTab === 'profile' && (
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card max-w-3xl">
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-ink">General Profile</h2>
            <p className="mt-1 text-sm text-muted">
              Configure business identity used for your WhatsApp workspace and billing.
            </p>
          </div>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              updateProfile.mutate();
            }}
            className="space-y-5"
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                  Business Display Name
                </label>
                <input
                  type="text"
                  required
                  value={currentBizName}
                  onChange={(e) => setBizName(e.target.value)}
                  className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                  placeholder="e.g. Green Apple"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                  Legal Entity Name (GST / Reg)
                </label>
                <input
                  type="text"
                  value={currentLegalName}
                  onChange={(e) => setLegalName(e.target.value)}
                  className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                  placeholder="e.g. Green Apple Solutions Pvt Ltd"
                />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div>
                <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                  Default Country Code
                </label>
                <input
                  type="text"
                  value={currentCountryCode}
                  onChange={(e) => setDefaultCountryCode(e.target.value)}
                  className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                  placeholder="+91"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                  Timezone
                </label>
                <select
                  value={currentTimezone}
                  onChange={(e) => setTimezone(e.target.value)}
                  className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                >
                  <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                  <option value="UTC">UTC (Coordinated Universal Time)</option>
                  <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                  <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                  <option value="Europe/London">Europe/London (GMT/BST)</option>
                  <option value="America/New_York">America/New_York (EST)</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                  Interface Language
                </label>
                <select
                  value={currentLanguage}
                  onChange={(e) => setLanguage(e.target.value)}
                  className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                >
                  <option value="en">English (US/UK)</option>
                  <option value="hi">Hindi (हिंदी)</option>
                </select>
              </div>
            </div>

            <div className="rounded-xl bg-slate-50 p-4 border border-line/70">
              <div className="flex flex-wrap items-center justify-between text-xs text-muted">
                <span>
                  Workspace Slug: <strong>{settings?.business?.slug}</strong>
                </span>
                <span>
                  Workspace Created:{' '}
                  <strong>{new Date(settings?.business?.createdAt ?? '').toLocaleDateString()}</strong>
                </span>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <button
                type="submit"
                disabled={updateProfile.isPending}
                className="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
              >
                {updateProfile.isPending ? 'Saving…' : 'Save Changes'}
              </button>
            </div>
          </form>
        </section>
      )}

      {/* Tab 2: Team Members */}
      {activeTab === 'team' && (
        <section className="space-y-6">
          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h2 className="text-xl font-semibold text-ink">Workspace Team Members</h2>
              <p className="mt-1 text-sm text-muted">
                Collaborate with your team to review campaigns, manage customer conversations, and build templates.
              </p>
            </div>
            <button
              onClick={() => setShowInviteModal(true)}
              className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"
            >
              <Plus size={16} /> Add Team Member
            </button>
          </div>

          <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <table className="w-full min-w-[650px] text-left text-sm">
              <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-6 py-3.5">User</th>
                  <th className="px-6 py-3.5">Email</th>
                  <th className="px-6 py-3.5">Role</th>
                  <th className="px-6 py-3.5">Status</th>
                  <th className="px-6 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {settings?.team?.map((member) => (
                  <tr key={member.id} className="hover:bg-slate-50/50">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700 font-semibold text-xs">
                          {member.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p className="font-semibold text-ink flex items-center gap-1.5">
                            {member.name}
                            {member.isPrimary && (
                              <span className="rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                Primary Owner
                              </span>
                            )}
                          </p>
                          <p className="text-xs text-muted">Joined {new Date(member.joinedAt).toLocaleDateString()}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-muted">{member.email}</td>
                    <td className="px-6 py-4">
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                          member.role === 'Business Owner'
                            ? 'bg-amber-50 text-amber-800'
                            : member.role === 'Business Admin'
                            ? 'bg-blue-50 text-blue-800'
                            : member.role === 'Campaign Manager'
                            ? 'bg-purple-50 text-purple-800'
                            : 'bg-slate-100 text-slate-700'
                        }`}
                      >
                        <Shield size={12} /> {member.role}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 capitalize">
                        <UserCheck size={12} /> {member.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      {!member.isPrimary && (
                        <button
                          onClick={() => setMemberToRemove({ id: member.id, name: member.name })}
                          className="rounded-lg p-1.5 text-red-600 hover:bg-red-50 transition-colors"
                          title="Remove user"
                        >
                          <Trash2 size={16} />
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Invite Member Modal */}
          {showInviteModal && (
            <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm">
              <div className="w-full max-w-md rounded-2xl border border-line bg-white p-6 shadow-2xl">
                <h3 className="text-lg font-semibold text-ink">Add Team Member</h3>
                <p className="mt-1 text-xs text-muted">
                  Add a colleague to your workspace. They can log in with their email.
                </p>

                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    inviteMember.mutate();
                  }}
                  className="mt-5 space-y-4"
                >
                  <div>
                    <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1">
                      Full Name
                    </label>
                    <input
                      type="text"
                      required
                      value={inviteName}
                      onChange={(e) => setInviteName(e.target.value)}
                      className="w-full rounded-xl border border-line px-3.5 py-2 text-sm focus:border-brand-600 focus:outline-none"
                      placeholder="e.g. Priya Sharma"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1">
                      Email Address
                    </label>
                    <input
                      type="email"
                      required
                      value={inviteEmail}
                      onChange={(e) => setInviteEmail(e.target.value)}
                      className="w-full rounded-xl border border-line px-3.5 py-2 text-sm focus:border-brand-600 focus:outline-none"
                      placeholder="e.g. priya@company.com"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1">
                      Workspace Role
                    </label>
                    <select
                      value={inviteRole}
                      onChange={(e) => setInviteRole(e.target.value)}
                      className="w-full rounded-xl border border-line px-3.5 py-2 text-sm focus:border-brand-600 focus:outline-none"
                    >
                      <option value="Business Admin">Business Admin (Full operational access)</option>
                      <option value="Campaign Manager">Campaign Manager (Campaigns & Contacts)</option>
                      <option value="Viewer">Viewer (Read-only analytics)</option>
                    </select>
                  </div>

                  <div className="flex justify-end gap-3 pt-3">
                    <button
                      type="button"
                      onClick={() => setShowInviteModal(false)}
                      className="rounded-xl border border-line px-4 py-2 text-xs font-semibold text-muted hover:text-ink"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={inviteMember.isPending}
                      className="rounded-xl bg-brand-700 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
                    >
                      {inviteMember.isPending ? 'Adding…' : 'Add Member'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Remove Member Confirmation Modal */}
          {memberToRemove && (
            <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm">
              <div className="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-2xl">
                <h3 className="text-lg font-semibold text-ink">Remove Team Member?</h3>
                <p className="mt-2 text-xs text-muted leading-5">
                  Are you sure you want to remove <strong>{memberToRemove.name}</strong> from this workspace? They will
                  lose access to contacts, campaigns, and inbox immediately.
                </p>
                <div className="flex justify-end gap-3 pt-5">
                  <button
                    onClick={() => setMemberToRemove(null)}
                    className="rounded-xl border border-line px-4 py-2 text-xs font-semibold text-muted hover:text-ink"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={() => removeMember.mutate(memberToRemove.id)}
                    disabled={removeMember.isPending}
                    className="rounded-xl bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                  >
                    {removeMember.isPending ? 'Removing…' : 'Remove Member'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </section>
      )}

      {/* Tab 3: Plan & Quotas */}
      {activeTab === 'quota' && (
        <section className="space-y-6 max-w-3xl">
          <div className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
              <div>
                <div className="flex items-center gap-2">
                  <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                    <Sparkles size={13} /> {quota?.plan?.name ?? 'Standard'} Plan
                  </span>
                  <span className="text-xs text-muted">Active Subscription</span>
                </div>
                <h2 className="mt-2 text-xl font-semibold text-ink">Subscription Quota Breakdown</h2>
              </div>
              <a
                href="mailto:support@whatsdup.in?subject=Upgrade%20WhatsdUP%20Plan"
                className="inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100"
              >
                <Zap size={14} /> Upgrade Plan
              </a>
            </div>

            <div className="mt-6 grid gap-6 sm:grid-cols-2">
              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-medium text-muted">Monthly Message Allowance</span>
                  <span className="font-semibold text-ink">
                    {recipientUsed.toLocaleString()} /{' '}
                    {recipientLimit !== null ? recipientLimit.toLocaleString() : 'Unlimited'}
                  </span>
                </div>
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                  <div
                    className={`h-full rounded-full transition-all duration-500 ${
                      recipientPercentage > 90 ? 'bg-red-500' : recipientPercentage > 75 ? 'bg-amber-500' : 'bg-brand-600'
                    }`}
                    style={{ width: `${Math.min(100, recipientPercentage)}%` }}
                  />
                </div>
                <p className="mt-2 text-xs text-muted">
                  {recipientLimit !== null
                    ? `${Math.max(0, recipientLimit - recipientUsed).toLocaleString()} messages remaining this cycle`
                    : 'Unlimited messages allowed on your current plan'}
                </p>
              </div>

              <div className="rounded-xl border border-line/70 bg-slate-50/50 p-4">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-medium text-muted">Audience Contacts Limit</span>
                  <span className="font-semibold text-ink">
                    {contactsUsed.toLocaleString()} / {contactsLimit !== null ? contactsLimit.toLocaleString() : 'Unlimited'}
                  </span>
                </div>
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                  <div
                    className={`h-full rounded-full transition-all duration-500 ${
                      contactsPercentage > 90 ? 'bg-red-500' : 'bg-brand-600'
                    }`}
                    style={{ width: `${Math.min(100, contactsPercentage)}%` }}
                  />
                </div>
                <p className="mt-2 text-xs text-muted">
                  {contactsLimit !== null
                    ? `${Math.max(0, contactsLimit - contactsUsed).toLocaleString()} contact slots remaining`
                    : 'Unlimited contact storage'}
                </p>
              </div>
            </div>

            <div className="mt-6 rounded-xl bg-slate-50 p-4 text-xs text-muted leading-5">
              WhatsdUP plans are designed to provide the most affordable WhatsApp Cloud API broadcast pricing. When you
              need to send to larger audiences, simply upgrade your tier.
            </div>
          </div>
        </section>
      )}

      {/* Tab 4: Account Security */}
      {activeTab === 'security' && (
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card max-w-xl">
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-ink">Account Security</h2>
            <p className="mt-1 text-sm text-muted">
              Update your account password to ensure your WhatsApp workspace remains protected.
            </p>
          </div>

          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                Current Password
              </label>
              <input
                type="password"
                required
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                placeholder="Enter current password"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                New Password
              </label>
              <input
                type="password"
                required
                minLength={8}
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                placeholder="Minimum 8 characters"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">
                Confirm New Password
              </label>
              <input
                type="password"
                required
                minLength={8}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-brand-600 focus:outline-none"
                placeholder="Re-enter new password"
              />
            </div>

            <div className="flex justify-end pt-3">
              <button
                type="submit"
                disabled={changePasswordMutation.isPending}
                className="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
              >
                {changePasswordMutation.isPending ? 'Updating…' : 'Update Password'}
              </button>
            </div>
          </form>
        </section>
      )}
    </div>
  );
}