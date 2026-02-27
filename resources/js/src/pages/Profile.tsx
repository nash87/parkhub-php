import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { User, Envelope, Shield, MapPin, CalendarCheck, House, PencilSimple, FloppyDisk, ChartBar, Eye, TextAa, HandSwipeRight, CircleHalf, DownloadSimple, Trash, Eraser } from '@phosphor-icons/react';
import { useAuth } from '../context/auth-hook';
import { api, UserStats } from '../api/client';
import { useAccessibility, ColorMode, FontScale } from '../stores/accessibility';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { ThemeSelector } from "../components/ThemeSelector";

export function ProfilePage() {
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  const a11y = useAccessibility();
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState({ name: user?.name || '', email: user?.email || '' });
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showAnonymizeConfirm, setShowAnonymizeConfirm] = useState(false);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [exporting, setExporting] = useState(false);

  useEffect(() => {
    api.getUserStats().then((res: { success: boolean; data?: UserStats }) => { if (res.success && res.data) setStats(res.data); }).catch(() => {});
  }, []);

  function handleSave() { setEditing(false); toast.success(t('profile.updated')); }

  async function handleExportData() {
    setExporting(true);
    try {
      const base = (import.meta.env.VITE_API_URL as string) || '';
      const token = localStorage.getItem('parkhub_token');
      const res = await fetch(`${base}/api/v1/user/export`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error('Export failed');
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = 'my-parkhub-data.json'; a.click();
      URL.revokeObjectURL(url);
      toast.success(t('gdpr.exported'));
    } catch {
      toast.error(t('gdpr.exportFailed', 'Export failed'));
    } finally {
      setExporting(false);
    }
  }

  async function handleDeleteAccount() {
    try {
      const base = (import.meta.env.VITE_API_URL as string) || '';
      const token = localStorage.getItem('parkhub_token');
      const res = await fetch(`${base}/api/v1/users/me/delete`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error('Delete failed');
      toast.success(t('gdpr.deleted', 'Account deleted'));
      logout();
    } catch {
      toast.error(t('gdpr.deleteFailed', 'Delete failed'));
    }
    setShowDeleteConfirm(false);
  }

  async function handleAnonymizeAccount() {
    try {
      const base = (import.meta.env.VITE_API_URL as string) || '';
      const token = localStorage.getItem('parkhub_token');
      const res = await fetch(`${base}/api/v1/users/me/anonymize`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason: 'User request (GDPR Art. 17)' }),
      });
      if (!res.ok) throw new Error('Anonymization failed');
      toast.success(t('gdpr.anonymized', 'Persönliche Daten wurden gelöscht (DSGVO Art. 17)'));
      logout();
    } catch {
      toast.error(t('gdpr.anonymizeFailed', 'Anonymisierung fehlgeschlagen'));
    }
    setShowAnonymizeConfirm(false);
  }

  const initials = user?.name?.split(' ').map(n => n[0]).join('').toUpperCase() || '?';
  const roleLabels: Record<string, string> = { user: t('profile.roles.user'), admin: t('profile.roles.admin'), superadmin: t('profile.roles.superadmin') };

  const containerVariants = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.1 } } };
  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  const colorModes: ColorMode[] = ['normal', 'protanopia', 'deuteranopia', 'tritanopia'];
  const fontScales: FontScale[] = ['small', 'normal', 'large', 'xlarge'];

  return (
    <motion.div variants={containerVariants} initial="hidden" animate="show" className="max-w-3xl mx-auto space-y-8">
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('profile.title')}</h1>
        <p className="text-gray-500 dark:text-gray-400 mt-1">{t('profile.subtitle')}</p>
      </motion.div>

      {/* Profile Card */}
      <motion.div variants={itemVariants} className="card p-8">
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-6">
          <div className="w-24 h-24 rounded-2xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
            <span className="text-3xl font-bold text-primary-600 dark:text-primary-400">{initials}</span>
          </div>
          <div className="flex-1 text-center sm:text-left">
            {editing ? (
              <div className="space-y-3">
                <div><label className="label" htmlFor="profile-name">{t('profile.name')}</label><input id="profile-name" type="text" value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })} className="input" /></div>
                <div><label className="label" htmlFor="profile-email">{t('profile.email')}</label><input id="profile-email" type="email" value={formData.email} onChange={e => setFormData({ ...formData, email: e.target.value })} className="input" /></div>
                <div className="flex gap-2">
                  <button onClick={handleSave} className="btn btn-primary btn-sm"><FloppyDisk weight="bold" className="w-4 h-4" />{t('common.save')}</button>
                  <button onClick={() => setEditing(false)} className="btn btn-secondary btn-sm">{t('common.cancel')}</button>
                </div>
              </div>
            ) : (
              <>
                <h2 className="text-xl font-bold text-gray-900 dark:text-white">{user?.name}</h2>
                <div className="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-2">
                  <span className="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"><User weight="regular" className="w-4 h-4" />@{user?.username}</span>
                  <span className="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"><Envelope weight="regular" className="w-4 h-4" />{user?.email}</span>
                </div>
                <div className="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                  <span className={`badge ${user?.role === 'admin' || user?.role === 'superadmin' ? 'badge-warning' : 'badge-info'}`}><Shield weight="fill" className="w-3 h-3" />{roleLabels[user?.role || 'user']}</span>
                  <button onClick={() => setEditing(true)} className="btn btn-ghost btn-sm"><PencilSimple weight="bold" className="w-3.5 h-3.5" />{t('common.edit')}</button>
                </div>
              </>
            )}
          </div>
        </div>
      </motion.div>

      {/* My Slot */}
      <motion.div variants={itemVariants} className="card p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"><MapPin weight="fill" className="w-5 h-5 text-primary-600" />{t('profile.mySlot')}</h3>
        <div className="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
          <p className="text-sm text-gray-400 dark:text-gray-500">{t('profile.noSlotAssigned', 'No parking slot assigned')}</p>
        </div>
      </motion.div>

      {/* Stats */}
      <motion.div variants={itemVariants} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="stat-card"><div className="flex items-center justify-between"><div><p className="text-sm text-gray-500 dark:text-gray-400">{t('profile.bookingsThisMonth')}</p><p className="stat-value text-primary-600 dark:text-primary-400 mt-1">{stats?.bookings_this_month ?? '-'}</p></div><CalendarCheck weight="fill" className="w-8 h-8 text-primary-200 dark:text-primary-800" /></div></div>
        <div className="stat-card"><div className="flex items-center justify-between"><div><p className="text-sm text-gray-500 dark:text-gray-400">{t('profile.homeOfficeDays')}</p><p className="stat-value text-sky-600 dark:text-sky-400 mt-1">{stats?.homeoffice_days_this_month ?? '-'}</p></div><House weight="fill" className="w-8 h-8 text-sky-200 dark:text-sky-800" /></div></div>
        <div className="stat-card"><div className="flex items-center justify-between"><div><p className="text-sm text-gray-500 dark:text-gray-400">{t('profile.avgDuration')}</p><p className="stat-value text-amber-600 dark:text-amber-400 mt-1">{stats ? `${stats.avg_duration_minutes} min` : '-'}</p></div><ChartBar weight="fill" className="w-8 h-8 text-amber-200 dark:text-amber-800" /></div></div>
      </motion.div>

{/* Color Palette */}      <motion.div variants={itemVariants} className="card p-6">        <ThemeSelector />      </motion.div>
      {/* Accessibility Settings */}
      <motion.div variants={itemVariants} className="card p-6 space-y-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Eye weight="fill" className="w-5 h-5 text-primary-600" />{t('accessibility.title')}</h3>

        {/* Color Mode */}
        <div>
          <label className="label flex items-center gap-2"><Eye weight="regular" className="w-4 h-4" />{t('accessibility.colorMode')}</label>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {colorModes.map(mode => (
              <button key={mode} onClick={() => a11y.setColorMode(mode)}
                className={`px-3 py-2 rounded-xl text-xs font-medium transition-all ${a11y.colorMode === mode ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 ring-2 ring-primary-500' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'}`}
                aria-pressed={a11y.colorMode === mode}
              >
                {t(`accessibility.colorModes.${mode}`)}
              </button>
            ))}
          </div>
        </div>

        {/* Font Scale */}
        <div>
          <label className="label flex items-center gap-2"><TextAa weight="regular" className="w-4 h-4" />{t('accessibility.fontScale')}</label>
          <div className="grid grid-cols-4 gap-2">
            {fontScales.map(scale => (
              <button key={scale} onClick={() => a11y.setFontScale(scale)}
                className={`px-3 py-2 rounded-xl text-xs font-medium transition-all ${a11y.fontScale === scale ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 ring-2 ring-primary-500' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'}`}
                aria-pressed={a11y.fontScale === scale}
              >
                {t(`accessibility.fontScales.${scale}`)}
              </button>
            ))}
          </div>
        </div>

        {/* Reduced Motion */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <HandSwipeRight weight="regular" className="w-4 h-4 text-gray-500" />
            <div>
              <p className="text-sm font-medium text-gray-900 dark:text-white">{t('accessibility.reducedMotion')}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">{t('accessibility.reducedMotionDesc')}</p>
            </div>
          </div>
          <button onClick={() => a11y.setReducedMotion(!a11y.reducedMotion)}
            role="switch" aria-checked={a11y.reducedMotion}
            className={`relative w-11 h-6 rounded-full transition-colors ${a11y.reducedMotion ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
            <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${a11y.reducedMotion ? 'translate-x-5' : ''}`} />
          </button>
        </div>

        {/* High Contrast */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CircleHalf weight="regular" className="w-4 h-4 text-gray-500" />
            <div>
              <p className="text-sm font-medium text-gray-900 dark:text-white">{t('accessibility.highContrast')}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">{t('accessibility.highContrastDesc')}</p>
            </div>
          </div>
          <button onClick={() => a11y.setHighContrast(!a11y.highContrast)}
            role="switch" aria-checked={a11y.highContrast}
            className={`relative w-11 h-6 rounded-full transition-colors ${a11y.highContrast ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
            <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${a11y.highContrast ? 'translate-x-5' : ''}`} />
          </button>
        </div>
      </motion.div>

      {/* GDPR Section */}
      <motion.div variants={itemVariants} className="card p-6 space-y-4">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <Shield weight="fill" className="w-5 h-5 text-primary-600" />DSGVO / GDPR
        </h3>
        <p className="text-xs text-gray-500 dark:text-gray-400">
          {t('gdpr.rights', 'Ihre Rechte gemäß DSGVO Art. 15, 17 und 20.')}
        </p>

        <div className="flex flex-col sm:flex-row gap-3">
          <button onClick={handleExportData} disabled={exporting} className="btn btn-secondary flex-1">
            <DownloadSimple weight="bold" className="w-4 h-4" />
            <div className="text-left">
              <div className="font-medium">{t('gdpr.dataExport')}</div>
              <div className="text-xs opacity-60">{t('gdpr.dataExportDesc')}</div>
            </div>
          </button>
          <button onClick={() => setShowAnonymizeConfirm(true)} className="btn btn-secondary flex-1 border-amber-300 dark:border-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20">
            <Eraser weight="bold" className="w-4 h-4 text-amber-600" />
            <div className="text-left">
              <div className="font-medium">{t('gdpr.anonymize', 'Daten löschen (Art. 17)')}</div>
              <div className="text-xs opacity-60">{t('gdpr.anonymizeDesc', 'PII löschen, Buchungen anonymisieren')}</div>
            </div>
          </button>
          <button onClick={() => setShowDeleteConfirm(true)} className="btn btn-danger flex-1">
            <Trash weight="bold" className="w-4 h-4" />
            <div className="text-left">
              <div className="font-medium">{t('gdpr.deleteAccount')}</div>
              <div className="text-xs opacity-60">{t('gdpr.deleteAccountDesc')}</div>
            </div>
          </button>
        </div>
      </motion.div>

      <ConfirmDialog
        open={showDeleteConfirm}
        onCancel={() => setShowDeleteConfirm(false)}
        onConfirm={handleDeleteAccount}
        title={t('gdpr.deleteConfirmTitle')}
        message={t('gdpr.deleteConfirmMessage')}
        confirmLabel={t('gdpr.deleteConfirmBtn')}
        variant="danger"
      />
      <ConfirmDialog
        open={showAnonymizeConfirm}
        onCancel={() => setShowAnonymizeConfirm(false)}
        onConfirm={handleAnonymizeAccount}
        title={t('gdpr.anonymizeConfirmTitle', 'Daten anonymisieren?')}
        message={t('gdpr.anonymizeConfirmMessage', 'Alle persönlichen Daten (Name, E-Mail, Kennzeichen, Abwesenheiten, Fahrzeuge) werden unwiderruflich gelöscht. Buchungsdatensätze bleiben anonymisiert erhalten (steuerrechtliche Aufbewahrungspflicht). Sie werden danach ausgeloggt.')}
        confirmLabel={t('gdpr.anonymizeConfirmBtn', 'Daten löschen')}
        variant="danger"
      />
    </motion.div>
  );
}
