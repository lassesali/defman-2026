import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import { type AuthUser, UserLevel } from '../types/defman';

/**
 * <AdminUserApproval />
 * Refactored from: form_usermanagement.php, form_apply.php, form_newuser.php, randompassword.php
 * Endpoints: GET/POST/PATCH/DELETE /api/admin/users
 */
export const AdminUserApproval: React.FC = () => {
  const [users, setUsers] = useState<AuthUser[]>([]);
  const [applicants, setApplicants] = useState<AuthUser[]>([]);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [newUser, setNewUser] = useState({
    username: '',
    password: '',
    email: '',
    userlevel: UserLevel.CUSTOMER,
  });

  const loadUsers = useCallback(async () => {
    const res = await defmanApi.getAdminUsers();
    setUsers(res.users);
    setApplicants(res.applicants);
    setNewUser((prev) => ({
      ...prev,
      password: prev.password || res.generated_password_suggestion,
    }));
  }, []);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const handleApprove = async (user: AuthUser, level = UserLevel.CUSTOMER) => {
    if (!window.confirm(`Are you SURE that you want to ACCEPT\nUser: ${user.login.toUpperCase()}?`)) {
      return;
    }
    const res = await defmanApi.patchAdminUser(user.id, {
      action: 'approve',
      userlevel: level,
    });
    setStatusMessage(res.message);
    loadUsers();
  };

  const handleDeleteOrDecline = async (user: AuthUser, isDecline = false) => {
    const verb = isDecline ? 'DECLINE' : 'DELETE';
    if (!window.confirm(`Are you SURE that you want to ${verb}\nUser: ${user.login.toUpperCase()}?`)) {
      return;
    }
    const res = await defmanApi.deleteAdminUser(user.id);
    setStatusMessage(res.message);
    loadUsers();
  };

  const handleRoleChange = async (userId: number, level: UserLevel) => {
    const res = await defmanApi.patchAdminUser(userId, {
      action: 'role',
      userlevel: level,
    });
    setStatusMessage(res.message);
    loadUsers();
  };

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const res = await defmanApi.createAdminUser(newUser);
      setStatusMessage(res.message);
      setNewUser({
        username: '',
        password: '',
        email: '',
        userlevel: UserLevel.CUSTOMER,
      });
      loadUsers();
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Failed to add user');
    }
  };

  const roleLabel = (level: UserLevel) => {
    switch (level) {
      case UserLevel.PUBLIC:
        return 'Public';
      case UserLevel.CUSTOMER:
        return 'Customer';
      case UserLevel.ANALYZER:
        return 'Analyzer';
      case UserLevel.ADMIN:
        return 'Admin';
      default:
        return 'Applying';
    }
  };

  return (
    <div className="space-y-6 text-slate-100">
      {errorMessage && (
        <div className="p-3 rounded bg-red-950 border border-red-600 text-amber-300 text-xs">
          {errorMessage}
        </div>
      )}
      {statusMessage && (
        <div className="p-3 rounded bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs">
          {statusMessage}
        </div>
      )}

      {/* Active Users Table (form_usermanagement.php top table) */}
      <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 shadow-lg space-y-3">
        <h2 className="text-lg font-bold text-amber-400">
          User Management ({users.length} Active Accounts)
        </h2>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
                <th className="p-2.5">ID</th>
                <th className="p-2.5">Username</th>
                <th className="p-2.5">Full Name</th>
                <th className="p-2.5">Email</th>
                <th className="p-2.5">Company</th>
                <th className="p-2.5">Phone</th>
                <th className="p-2.5">Userlevel</th>
                <th className="p-2.5">Status</th>
                <th className="p-2.5 text-right">Delete User</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {users.map((u) => (
                <tr key={u.id} className="hover:bg-slate-800/50">
                  <td className="p-2.5 font-mono">#{u.id}</td>
                  <td className="p-2.5 font-semibold">{u.login}</td>
                  <td className="p-2.5 text-amber-300">{u.name || '—'}</td>
                  <td className="p-2.5">{u.email}</td>
                  <td className="p-2.5">{u.company || '—'}</td>
                  <td className="p-2.5">{u.phone || '—'}</td>
                  <td className="p-2.5">
                    <select
                      value={u.userlevel}
                      onChange={(e) =>
                        handleRoleChange(u.id, Number(e.target.value) as UserLevel)
                      }
                      className="rounded bg-slate-800 border border-slate-600 px-2 py-1"
                    >
                      <option value={UserLevel.PUBLIC}>1 - Public</option>
                      <option value={UserLevel.CUSTOMER}>2 - Customer</option>
                      <option value={UserLevel.ANALYZER}>3 - Analyzer</option>
                      <option value={UserLevel.ADMIN}>4 - Admin</option>
                    </select>
                  </td>
                  <td className="p-2.5">
                    {u.logged === 1 ? (
                      <span className="text-emerald-400 font-semibold">Online</span>
                    ) : (
                      <span className="text-red-400 font-semibold">Offline</span>
                    )}
                  </td>
                  <td className="p-2.5 text-right">
                    {u.userlevel !== UserLevel.ADMIN && (
                      <button
                        type="button"
                        onClick={() => handleDeleteOrDecline(u, false)}
                        className="px-2.5 py-1 rounded bg-red-900/70 hover:bg-red-800 text-red-200"
                      >
                        Delete
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Applying Users Queue (form_usermanagement.php bottom table + form_apply.php) */}
      <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 shadow-lg space-y-3">
        <h3 className="text-base font-bold text-amber-400">
          Applying Users Approval Queue ({applicants.length})
        </h3>
        {applicants.length === 0 ? (
          <p className="text-xs text-slate-400">No pending user applications.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
                  <th className="p-2.5">ID</th>
                  <th className="p-2.5">Username</th>
                  <th className="p-2.5">Full Name</th>
                  <th className="p-2.5">Email</th>
                  <th className="p-2.5">Company</th>
                  <th className="p-2.5">Phone</th>
                  <th className="p-2.5">Status</th>
                  <th className="p-2.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {applicants.map((app) => (
                  <tr key={app.id}>
                    <td className="p-2.5 font-mono">#{app.id}</td>
                    <td className="p-2.5 font-semibold">{app.login}</td>
                    <td className="p-2.5 text-amber-300">{app.name || '—'}</td>
                    <td className="p-2.5">{app.email}</td>
                    <td className="p-2.5">{app.company || '—'}</td>
                    <td className="p-2.5">{app.phone || '—'}</td>
                    <td className="p-2.5">{roleLabel(app.userlevel)}</td>
                    <td className="p-2.5 text-right space-x-2">
                      <button
                        type="button"
                        onClick={() => handleDeleteOrDecline(app, true)}
                        className="px-2.5 py-1 rounded bg-red-900/70 hover:bg-red-800 text-red-200"
                      >
                        Decline
                      </button>
                      <button
                        type="button"
                        onClick={() => handleApprove(app, UserLevel.CUSTOMER)}
                        className="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold"
                      >
                        Accept (Customer)
                      </button>
                      <button
                        type="button"
                        onClick={() => handleApprove(app, UserLevel.ANALYZER)}
                        className="px-2.5 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
                      >
                        Accept (Analyzer)
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add New User Form (form_newuser.php) */}
      <form
        onSubmit={handleCreateUser}
        className="bg-slate-900 border border-slate-700 rounded-xl p-5 shadow-lg space-y-3 max-w-xl text-xs"
      >
        <h3 className="text-base font-bold text-amber-400">
          Add New User (form_newuser.php)
        </h3>
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">Username (Alphanumeric) *</span>
            <input
              type="text"
              required
              value={newUser.username}
              onChange={(e) =>
                setNewUser({ ...newUser, username: e.target.value })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Generated / Custom Password *</span>
            <input
              type="text"
              required
              value={newUser.password}
              onChange={(e) =>
                setNewUser({ ...newUser, password: e.target.value })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5 font-mono"
            />
          </label>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">Email *</span>
            <input
              type="email"
              required
              value={newUser.email}
              onChange={(e) =>
                setNewUser({ ...newUser, email: e.target.value })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Userlevel *</span>
            <select
              value={newUser.userlevel}
              onChange={(e) =>
                setNewUser({
                  ...newUser,
                  userlevel: Number(e.target.value) as UserLevel,
                })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            >
              <option value={UserLevel.PUBLIC}>Public (Level 1)</option>
              <option value={UserLevel.CUSTOMER}>Customer (Level 2)</option>
              <option value={UserLevel.ANALYZER}>Analyzer (Level 3)</option>
              <option value={UserLevel.ADMIN}>Admin (Level 4)</option>
            </select>
          </label>
        </div>
        <button
          type="submit"
          className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
        >
          Add User
        </button>
      </form>
    </div>
  );
};
