import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { api } from '../api/client';
import { t } from '../i18n';
import { useTheme } from '../stores/theme';
import { setLanguage, getLanguage, LANGUAGES } from '../i18n';
import { UserCircle, Sun, Moon, Globe, Download, Trash } from '@phosphor-icons/react';

export default function Profile() {
  const { user, updateUser, logout } = useAuth();
  const { isDark, setTheme, theme } = useTheme();
  const [form, setForm] = useState({ name: user?.name || '', email: user?.email || '', phone: '' });
  const [stats, setStats] = useState<any>(null);
  const [saved, setSaved] = useState(false);
  const [lang, setLang] = useState(getLanguage());

  useEffect(() => { api.get<any>('/user/stats').then(setStats).catch(() => {}); }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await api.put<any>('/me', form);
    updateUser(res);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  const changeLang = (code: string) => {
    setLang(code);
    setLanguage(code);
    window.location.reload();
  };

  const exportData = async () => {
    const me = await api.get('/me');
    const bookings = await api.get('/bookings');
    const vehicles = await api.get('/vehicles');
    const data = { user: me, bookings, vehicles };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'parkhub-data-export.json'; a.click();
  };

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-bold mb-1">{t('profile.title')}</h1>
      <p className="text-slate-500 mb-6">{t('profile.subtitle')}</p>

      <div className="card mb-6">
        <div className="flex items-center gap-4 mb-6">
          <UserCircle size={64} className="text-slate-400" />
          <div>
            <p className="text-xl font-bold">{user?.name}</p>
            <p className="text-slate-500">@{user?.username}</p>
            <span className="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 px-2 py-1 rounded-full mt-1 inline-block">{user?.role}</span>
          </div>
        </div>

        <form onSubmit={handleSave} className="space-y-4">
          <div><label className="block text-sm font-medium mb-1">{t('profile.name')}</label><input type="text" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} className="input" /></div>
          <div><label className="block text-sm font-medium mb-1">{t('profile.email')}</label><input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} className="input" /></div>
          <button type="submit" className="btn-primary">{saved ? '✓ Saved!' : t('common.save')}</button>
        </form>
      </div>

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-2 gap-4 mb-6">
          <div className="card"><p className="text-2xl font-bold">{stats.total_bookings}</p><p className="text-sm text-slate-500">{t('userStats.totalBookings')}</p></div>
          <div className="card"><p className="text-2xl font-bold">{stats.this_month}</p><p className="text-sm text-slate-500">{t('userStats.thisMonth')}</p></div>
          <div className="card"><p className="text-2xl font-bold">{stats.homeoffice_days}</p><p className="text-sm text-slate-500">{t('userStats.homeOfficeDays')}</p></div>
          <div className="card"><p className="text-2xl font-bold">{stats.favorite_slot || '—'}</p><p className="text-sm text-slate-500">{t('userStats.favoriteSlot')}</p></div>
        </div>
      )}

      {/* Settings */}
      <div className="card mb-6">
        <h2 className="font-semibold mb-4">Settings</h2>
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2"><Sun size={18} /> Theme</div>
            <select value={theme} onChange={e => setTheme(e.target.value as any)} className="input w-auto">
              <option value="system">System</option>
              <option value="light">Light</option>
              <option value="dark">Dark</option>
            </select>
          </div>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2"><Globe size={18} /> Language</div>
            <select value={lang} onChange={e => changeLang(e.target.value)} className="input w-auto">
              {LANGUAGES.map(l => <option key={l.code} value={l.code}>{l.name}</option>)}
            </select>
          </div>
        </div>
      </div>

      {/* GDPR */}
      <div className="card">
        <h2 className="font-semibold mb-4">Data & Privacy (GDPR)</h2>
        <div className="space-y-3">
          <button onClick={exportData} className="btn-secondary w-full flex items-center justify-center gap-2"><Download size={18} /> {t('gdpr.dataExport')}</button>
          <button onClick={() => { if (confirm(t('gdpr.deleteConfirmMessage'))) { api.delete('/me').then(() => logout()); } }} className="w-full flex items-center justify-center gap-2 p-2 rounded-lg text-red-500 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20">
            <Trash size={18} /> {t('gdpr.deleteAccount')}
          </button>
        </div>
      </div>
    </div>
  );
}
