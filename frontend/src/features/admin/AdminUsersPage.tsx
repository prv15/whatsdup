import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { KeyRound, LogOut, ShieldAlert, ShieldCheck, UserCheck, UserX, X } from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { AdminUser } from '../../types/admin';

export function AdminUsersPage() {
  const [resettingUser, setResettingUser] = useState<AdminUser | null>(null);
  const [newPassword, setNewPassword] = useState('');
  const [passwordError, setPasswordError] = useState('');
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['admin-users'],
    queryFn: async () => (await api.get<{ data: AdminUser[] }>('/admin/users')).data.data,
  });

  const toggleStatus = useMutation({
    mutationFn: async ({ id, status }: { id: string; status: 'active' | 'suspended' }) =>
      (await api.patch(`/admin/users/${id}/status`, { status })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-users'] }),
  });

  const resetPassword = useMutation({
    mutationFn: async ({ id, password }: { id: string; password: string }) =>
      (await api.post(`/admin/users/${id}/reset-password`, { password })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      setResettingUser(null);
      setNewPassword('');
    },
  });

  const revokeSessions = useMutation({
    mutationFn: async (id: string) => (await api.post(`/admin/users/${id}/revoke-sessions`)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-users'] }),
  });

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword.length < 12) {
      setPasswordError('Password must contain at least 12 characters.');
      return;
    }
    setPasswordError('');
    if (resettingUser) {
      resetPassword.mutate({ id: resettingUser.id, password: newPassword });
    }
  };

  return (
    <div className="space-y-8">
      <div>
        <p className="text-sm font-semibold text-brand-700">Identity & Access</p>
        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Users</h1>
        <p className="mt-2 text-muted">Platform administrators and customer identities across WhatstheUp.</p>
      </div>

      {resettingUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-lg font-semibold">Reset User Password</h3>
                <p className="mt-1 text-xs text-muted">User: {resettingUser.email}</p>
              </div>
              <button
                onClick={() => {
                  setResettingUser(null);
                  setNewPassword('');
                  setPasswordError('');
                }}
                className="rounded-lg p-1.5 hover:bg-gray-100"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handlePasswordSubmit} className="mt-5 space-y-4">
              <div>
                <label className="mb-1 block text-xs font-medium text-muted">New Temporary Password</label>
                <input
                  type="password"
                  placeholder="At least 12 characters"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm"
                />
              </div>

              {passwordError && <div className="rounded-xl bg-red-50 p-2.5 text-xs text-red-700">{passwordError}</div>}

              <div className="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setResettingUser(null)}
                  className="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={resetPassword.isPending}
                  className="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-60"
                >
                  {resetPassword.isPending ? 'Resetting…' : 'Set Password'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <section className="overflow-hidden rounded-2xl border border-line bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">User</th>
                <th className="px-5 py-3">Workspace</th>
                <th className="px-5 py-3">Role</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3">Verified</th>
                <th className="px-5 py-3">Last Login</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {query.data?.map((user) => (
                <tr key={user.id}>
                  <td className="px-5 py-4">
                    <p className="font-medium">{user.name}</p>
                    <p className="text-xs text-muted">{user.email}</p>
                  </td>
                  <td className="px-5 py-4">{user.workspaces}</td>
                  <td className="px-5 py-4">
                    <span className="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                      {user.roleName ?? 'Viewer'}
                    </span>
                  </td>
                  <td className="px-5 py-4">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium capitalize ${
                        user.status === 'active' ? 'bg-brand-50 text-brand-700' : 'bg-rose-50 text-rose-700'
                      }`}
                    >
                      {user.status}
                    </span>
                  </td>
                  <td className="px-5 py-4">{user.emailVerified ? 'Yes' : 'No'}</td>
                  <td className="px-5 py-4 text-xs text-muted">{user.lastLoginAt ?? 'Never'}</td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        title="Reset Password"
                        onClick={() => setResettingUser(user)}
                        className="rounded-lg border border-line p-1.5 text-muted hover:bg-gray-50 hover:text-gray-900"
                      >
                        <KeyRound size={15} />
                      </button>
                      <button
                        title="Revoke Active Sessions"
                        onClick={() => revokeSessions.mutate(user.id)}
                        disabled={revokeSessions.isPending}
                        className="rounded-lg border border-line p-1.5 text-muted hover:bg-gray-50 hover:text-rose-600"
                      >
                        <LogOut size={15} />
                      </button>
                      <button
                        onClick={() =>
                          toggleStatus.mutate({
                            id: user.id,
                            status: user.status === 'active' ? 'suspended' : 'active',
                          })
                        }
                        disabled={toggleStatus.isPending}
                        className={`rounded-lg px-2.5 py-1 text-xs font-semibold ${
                          user.status === 'active'
                            ? 'border border-line text-rose-700 hover:bg-rose-50'
                            : 'bg-brand-700 text-white hover:bg-brand-800'
                        }`}
                      >
                        {user.status === 'active' ? 'Suspend' : 'Activate'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {query.isLoading && <p className="p-8 text-center text-muted">Loading users…</p>}
        {!query.isLoading && query.data?.length === 0 && <p className="p-8 text-center text-muted">No users found.</p>}
      </section>
    </div>
  );
}