import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';

/**
 * <UserProfileSettings />
 * Refactored from: form_userdetails.php (Password settings, Email settings, Personal settings)
 * Endpoint: GET / PUT /api/users/me
 */
export const UserProfileSettings: React.FC = () => {
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [company, setCompany] = useState('');
  const [phone, setPhone] = useState('');
  const [changePassword, setChangePassword] = useState('');
  const [verifyPassword, setVerifyPassword] = useState('');

  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    defmanApi.getProfile().then((res) => {
      setEmail(res.email || '');
      setName(res.name || '');
      setCompany(res.company || '');
      setPhone(res.phone || '');
    });
  }, []);

  const handleUpdate = async (
    e: React.FormEvent,
    section: 'password' | 'email' | 'personal'
  ) => {
    e.preventDefault();
    setMessage(null);
    setError(null);
    try {
      const payload =
        section === 'password'
          ? {
              section,
              change_password: changePassword,
              verify_password: verifyPassword,
            }
          : section === 'email'
          ? { section, email }
          : { section, name, company, phone };

      const res = await defmanApi.updateProfile(payload);
      setMessage(res.message);
      if (section === 'password') {
        setChangePassword('');
        setVerifyPassword('');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
    }
  };

  return (
    <div className="space-y-4 text-slate-100 text-xs">
      {error && (
        <div className="p-3 rounded bg-red-950 border border-red-600 text-amber-300">
          {error}
        </div>
      )}
      {message && (
        <div className="p-3 rounded bg-emerald-950 border border-emerald-600 text-emerald-300">
          {message}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Password Settings */}
        <form
          onSubmit={(e) => handleUpdate(e, 'password')}
          className="bg-slate-900 border border-slate-700 rounded-xl p-5 space-y-3"
        >
          <h3 className="text-base font-bold text-amber-400">Password Settings</h3>
          <label className="block">
            <span className="text-slate-300">Change password</span>
            <input
              type="password"
              required
              value={changePassword}
              onChange={(e) => setChangePassword(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Verify new password</span>
            <input
              type="password"
              required
              value={verifyPassword}
              onChange={(e) => setVerifyPassword(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <button
            type="submit"
            className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
          >
            Update Password
          </button>
        </form>

        {/* Email Settings */}
        <form
          onSubmit={(e) => handleUpdate(e, 'email')}
          className="bg-slate-900 border border-slate-700 rounded-xl p-5 space-y-3"
        >
          <h3 className="text-base font-bold text-amber-400">Email Settings</h3>
          <label className="block">
            <span className="text-slate-300">Change Email</span>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <button
            type="submit"
            className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
          >
            Update Email
          </button>
        </form>

        {/* Personal Settings */}
        <form
          onSubmit={(e) => handleUpdate(e, 'personal')}
          className="bg-slate-900 border border-slate-700 rounded-xl p-5 space-y-3"
        >
          <h3 className="text-base font-bold text-amber-400">Personal Settings</h3>
          <label className="block">
            <span className="text-slate-300">Full name</span>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Name of Company</span>
            <input
              type="text"
              maxLength={50}
              value={company}
              onChange={(e) => setCompany(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Phonenumber</span>
            <input
              type="text"
              maxLength={50}
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <button
            type="submit"
            className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
          >
            Update Personal Info
          </button>
        </form>
      </div>
    </div>
  );
};
