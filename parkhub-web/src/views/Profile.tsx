import { useEffect, useRef, useState, type ReactNode } from 'react';
import { motion } from 'framer-motion';
import {
  UserCircle,
  Envelope,
  PencilSimple,
  FloppyDisk,
  SpinnerGap,
  Lock,
  DownloadSimple,
  Trash,
  CaretDown,
  CaretUp,
  MapPin,
  Question,
} from '@phosphor-icons/react';
import { useAuth } from '../context/AuthContext';
import { api, type UserStats } from '../api/client';
import { useTranslation } from 'react-i18next';
import { staggerSlow, fadeUp } from '../constants/animations';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { TwoFactorSetupComponent } from '../components/TwoFactorSetup';
import { NotificationPreferencesComponent } from '../components/NotificationPreferences';
import { LoginHistoryComponent } from '../components/LoginHistory';
import { ProfileThemeSection } from '../components/ProfileThemeSection';
import { useTheme } from '../context/ThemeContext';

export function ProfilePage() {
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  const { designTheme } = useTheme();
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState({ name: user?.name || '', email: user?.email || '' });
  const [saving, setSaving] = useState(false);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [exporting, setExporting] = useState(false);
  const [confirmState, setConfirmState] = useState<{ open: boolean; action: () => void }>({
    open: false,
    action: () => {},
  });
  const [pwOpen, setPwOpen] = useState(false);
  const [pwForm, setPwForm] = useState({ current: '', newPw: '', confirm: '' });
  const [pwSaving, setPwSaving] = useState(false);
  const [geofenceEnabled, setGeofenceEnabled] = useState(() => (
    typeof window !== 'undefined' && window.localStorage.getItem('parkhub_geofence_auto') === 'true'
  ));

  useEffect(() => {
    api.getUserStats()
      .then((res) => {
        if (res.success && res.data) setStats(res.data);
      })
      .catch(() => {});
  }, []);

  async function handleSave() {
    setSaving(true);
    try {
      const res = await api.updateMe({ name: formData.name, email: formData.email });
      if (res.success) {
        setEditing(false);
        toast.success(t('profile.updated', 'Profil aktualisiert'));
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleChangePassword() {
    /* istanbul ignore next -- defensive guards; UI disables the button */
    if (pwForm.newPw.length < 8) {
      toast.error(t('profile.passwordTooShort', 'Mind. 8 Zeichen'));
      return;
    }
    /* istanbul ignore next -- defensive guards */
    if (pwForm.newPw !== pwForm.confirm) {
      toast.error(t('profile.passwordsMismatch', 'Passwörter stimmen nicht überein'));
      return;
    }
    /* istanbul ignore next -- defensive guards */
    if (!pwForm.current) {
      toast.error(t('profile.currentPasswordRequired', 'Aktuelles Passwort eingeben'));
      return;
    }

    setPwSaving(true);
    try {
      const res = await api.changePassword(pwForm.current, pwForm.newPw, pwForm.confirm);
      if (res.success) {
        toast.success(t('profile.passwordChanged', 'Passwort geändert'));
        setPwForm({ current: '', newPw: '', confirm: '' });
        setPwOpen(false);
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    } finally {
      setPwSaving(false);
    }
  }

  async function handleExportData() {
    setExporting(true);
    try {
      const blob = await api.exportMyData();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = 'my-parkhub-data.json';
      anchor.click();
      URL.revokeObjectURL(url);
      toast.success(t('gdpr.exported'));
    } catch {
      toast.error(t('gdpr.exportFailed'));
    } finally {
      setExporting(false);
    }
  }

  function handleDeleteAccount() {
    setConfirmState({
      open: true,
      action: async () => {
        setConfirmState({ open: false, action: () => {} });
        try {
          const res = await api.deleteMyAccount();
          if (res.success) {
            toast.success(t('gdpr.deleted'));
            logout();
          } else {
            toast.error(res.error?.message || t('gdpr.deleteFailed'));
          }
        } catch {
          toast.error(t('gdpr.deleteFailed'));
        }
      },
    });
  }

  function AnimatedNumber({ value, suffix = '' }: { value: number; suffix?: string }) {
    const safeValue = Number.isFinite(value) ? value : 0;
    const [display, setDisplay] = useState(0);
    const rafRef = useRef<number>(0);

    useEffect(() => {
      if (safeValue === 0) {
        setDisplay(0);
        return;
      }

      const duration = 600;
      const start = performance.now();

      function tick(now: number) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        setDisplay(Math.round(eased * safeValue));
        if (progress < 1) rafRef.current = requestAnimationFrame(tick);
      }

      rafRef.current = requestAnimationFrame(tick);
      return () => cancelAnimationFrame(rafRef.current);
    }, [safeValue]);

    return <>{display}{suffix}</>;
  }

  const initials = user?.name?.split(' ').map((segment) => segment[0]).join('').toUpperCase() || '?';
  const roleLabels: Record<string, string> = {
    user: t('profile.roles.user'),
    admin: t('profile.roles.admin'),
    superadmin: t('profile.roles.superadmin'),
  };
  const isVoid = designTheme === 'void';
  const summaryTone = isVoid ? 'void' : 'marble';
  const container = staggerSlow;
  const item = fadeUp;
  const totalBookings = stats?.total_bookings ?? 0;

  const heroStats = [
    {
      label: t('profile.bookingsThisMonth', 'Buchungen (Monat)'),
      value: stats ? <AnimatedNumber value={stats.bookings_this_month} /> : '-',
      meta: t('profile.monthlySnapshot', 'Monthly snapshot'),
    },
    {
      label: t('profile.homeOfficeDays', 'Homeoffice-Tage'),
      value: stats ? <AnimatedNumber value={stats.homeoffice_days_this_month} /> : '-',
      meta: t('profile.flexMode', 'Flex cadence'),
    },
    {
      label: t('profile.avgDuration', 'Durchschn. Dauer'),
      value: stats ? <AnimatedNumber value={stats.avg_duration_minutes} suffix=" min" /> : '-',
      meta: t('profile.averageWindow', 'Session average'),
    },
  ];

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="mx-auto max-w-6xl space-y-6">
      <motion.div
        variants={item}
        data-testid="profile-summary-surface"
        data-surface-tone={summaryTone}
        className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
          isVoid
            ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
            : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
        }`}
      >
        <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <UserCircle weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void identity suite' : 'Marble profile deck'}
            </div>

            <h1 className="text-3xl font-black tracking-[-0.04em]">{t('profile.title', 'Profil')}</h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('profile.subtitle', 'Persönliche Daten verwalten')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-3" aria-live="polite">
              {heroStats.map((stat, index) => (
                <HeroStat
                  key={stat.label}
                  label={stat.label}
                  value={stat.value}
                  meta={stat.meta}
                  isVoid={isVoid}
                  accent={index === 0}
                />
              ))}
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <div className="flex items-start gap-4">
              <div className={`flex h-16 w-16 items-center justify-center rounded-[20px] text-xl font-bold ${
                isVoid
                  ? 'bg-cyan-500/12 text-cyan-100'
                  : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
              }`}>
                {initials}
              </div>

              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-xl font-semibold tracking-[-0.03em]">{user?.name}</h2>
                  <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                    isVoid
                      ? 'bg-white/8 text-cyan-100'
                      : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300'
                  }`}>
                    {roleLabels[user?.role || 'user']}
                  </span>
                </div>

                <div className={`mt-3 space-y-2 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  <p className="flex items-center gap-2">
                    <UserCircle weight="regular" className="h-4 w-4" />
                    @{user?.username}
                  </p>
                  <p className="flex items-center gap-2 break-all">
                    <Envelope weight="regular" className="h-4 w-4" />
                    {user?.email}
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <PanelMetric
                label={t('profile.totalBookings', 'Total bookings')}
                value={String(totalBookings)}
                isVoid={isVoid}
              />
              <PanelMetric
                label={t('profile.designTheme', 'Design theme')}
                value={isVoid ? 'Void' : 'Marble'}
                isVoid={isVoid}
              />
            </div>

            <div className="mt-5">
              {!editing ? (
                <button onClick={() => setEditing(true)} className="btn btn-secondary btn-sm">
                  <PencilSimple weight="bold" className="h-3.5 w-3.5" /> {t('common.edit', 'Bearbeiten')}
                </button>
              ) : (
                <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                  isVoid
                    ? 'bg-cyan-500/10 text-cyan-100'
                    : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                }`}>
                  {t('profile.editingLive', 'Editing live')}
                </span>
              )}
            </div>
          </div>
        </div>
      </motion.div>

      <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
        <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">
                {t('profile.identityLabel', 'Identity')}
              </p>
              <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
                {t('profile.personalDetails', 'Persönliche Daten')}
              </h2>
            </div>
            <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
              {t('profile.identityBadge', 'Profile record')}
            </span>
          </div>

          {editing ? (
            <div className="space-y-4">
              <div>
                <label htmlFor="profile-name" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">
                  {t('profile.name', 'Name')}
                </label>
                <input
                  id="profile-name"
                  type="text"
                  value={formData.name}
                  onChange={(event) => setFormData({ ...formData, name: event.target.value })}
                  className="input"
                />
              </div>

              <div>
                <label htmlFor="profile-email" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">
                  {t('profile.email', 'E-Mail')}
                </label>
                <input
                  id="profile-email"
                  type="email"
                  value={formData.email}
                  onChange={(event) => setFormData({ ...formData, email: event.target.value })}
                  className="input"
                />
              </div>

              <div className="flex flex-wrap gap-2">
                <button onClick={handleSave} disabled={saving} className="btn btn-primary btn-sm">
                  {saving ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> : <FloppyDisk weight="bold" className="h-4 w-4" />}
                  {t('common.save', 'Speichern')}
                </button>
                <button onClick={() => setEditing(false)} className="btn btn-secondary btn-sm">
                  {t('common.cancel', 'Abbrechen')}
                </button>
              </div>
            </div>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2">
              <ReadOnlyMetric label={t('profile.totalBookings', 'Total bookings')} value={String(totalBookings)} />
              <ReadOnlyMetric label={t('profile.designTheme', 'Design theme')} value={isVoid ? 'Void' : 'Marble'} />
              <ReadOnlyMetric label={t('profile.memberId', 'Member ID')} value={user?.id || '—'} />
              <ReadOnlyMetric label={t('profile.syncState', 'Sync state')} value={t('profile.readyState', 'Ready')} />
            </div>
          )}
        </motion.div>

        <div className="space-y-4">
          <motion.div
            variants={item}
            data-testid="accessibility-section"
            className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-slate-800 bg-slate-950/85 text-white'
                : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
            }`}
          >
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
              {t('accessible.needsLabel', 'Accessibility')}
            </p>
            <h3 className="mt-2 text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
              {t('accessible.needs', 'Accessibility Needs')}
            </h3>
            <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('accessible.needsDesc', 'Select your accessibility requirements for priority parking access.')}
            </p>
            <select
              data-testid="accessibility-selector"
              className="input mt-4"
              defaultValue=""
              onChange={async (event) => {
                try {
                  const res = await api.setAccessibilityNeeds(event.target.value || 'none');
                  if (res.success) toast.success(t('accessible.updated', 'Accessibility needs updated'));
                  else toast.error(res.error?.message || t('common.error'));
                } catch {
                  toast.error(t('common.error'));
                }
              }}
            >
              <option value="none">{t('accessible.none', 'No accessibility needs')}</option>
              <option value="wheelchair">{t('accessible.wheelchair', 'Wheelchair')}</option>
              <option value="reduced_mobility">{t('accessible.reducedMobility', 'Reduced Mobility')}</option>
              <option value="visual">{t('accessible.visual', 'Visual impairment')}</option>
              <option value="hearing">{t('accessible.hearing', 'Hearing impairment')}</option>
            </select>
          </motion.div>

          <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 rounded-2xl bg-primary-500/10 p-3 text-primary-600 dark:text-primary-300">
                <MapPin weight="fill" className="h-5 w-5" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <h3 className="text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
                    {t('geofence.autoCheckIn')}
                  </h3>
                  <div className="group relative">
                    <Question weight="fill" className="h-4 w-4 cursor-help text-surface-400" />
                    <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-lg bg-surface-800 p-2 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100">
                      {t('geofence.help')}
                    </div>
                  </div>
                </div>
                <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">{t('geofence.autoCheckInDesc')}</p>

                <div className="mt-4 flex items-center justify-between gap-3 rounded-[18px] border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/70">
                  <div>
                    <p className="text-sm font-medium text-surface-900 dark:text-white">
                      {geofenceEnabled ? t('geofence.enabled') : t('geofence.disabled')}
                    </p>
                    <p className="text-xs text-surface-500 dark:text-surface-400">
                      {t('geofence.statusHint', 'Toggle automatic arrival recognition when you approach a lot.')}
                    </p>
                  </div>

                  <label className="relative inline-flex cursor-pointer items-center">
                    <input
                      type="checkbox"
                      className="peer sr-only"
                      checked={geofenceEnabled}
                      onChange={(event) => {
                        const nextValue = event.target.checked;
                        setGeofenceEnabled(nextValue);
                        localStorage.setItem('parkhub_geofence_auto', String(nextValue));
                        toast.success(nextValue ? t('geofence.enabled') : t('geofence.disabled'));
                      }}
                    />
                    <div className="h-6 w-11 rounded-full bg-surface-200 after:absolute after:left-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full dark:bg-surface-700" />
                  </label>
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
        <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <button onClick={() => setPwOpen(!pwOpen)} className="flex w-full items-center justify-between gap-3" aria-expanded={pwOpen}>
            <div className="text-left">
              <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">
                {t('profile.securityLabel', 'Security')}
              </p>
              <h3 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
                {t('profile.changePassword', 'Passwort ändern')}
              </h3>
            </div>
            {pwOpen ? <CaretUp weight="bold" className="h-4 w-4 text-surface-400" /> : <CaretDown weight="bold" className="h-4 w-4 text-surface-400" />}
          </button>

          {pwOpen && (
            <div className="mt-4 space-y-3">
              <div>
                <label htmlFor="pw-current" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">
                  {t('profile.currentPassword', 'Aktuelles Passwort')}
                </label>
                <input
                  id="pw-current"
                  type="password"
                  value={pwForm.current}
                  onChange={(event) => setPwForm({ ...pwForm, current: event.target.value })}
                  className="input"
                  autoComplete="current-password"
                />
              </div>

              <div>
                <label htmlFor="pw-new" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">
                  {t('profile.newPassword', 'Neues Passwort')}
                </label>
                <input
                  id="pw-new"
                  type="password"
                  value={pwForm.newPw}
                  onChange={(event) => setPwForm({ ...pwForm, newPw: event.target.value })}
                  className="input"
                  autoComplete="new-password"
                />
                {pwForm.newPw.length > 0 && pwForm.newPw.length < 8 && (
                  <p className="mt-1 text-xs text-amber-600">{t('profile.minChars')}</p>
                )}
              </div>

              <div>
                <label htmlFor="pw-confirm" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">
                  {t('profile.confirmPassword', 'Passwort bestätigen')}
                </label>
                <input
                  id="pw-confirm"
                  type="password"
                  value={pwForm.confirm}
                  onChange={(event) => setPwForm({ ...pwForm, confirm: event.target.value })}
                  className="input"
                  autoComplete="new-password"
                />
                {pwForm.confirm.length > 0 && pwForm.newPw !== pwForm.confirm && (
                  <p className="mt-1 text-xs text-red-600">{t('profile.passwordsNoMatch')}</p>
                )}
              </div>

              <button
                onClick={handleChangePassword}
                disabled={pwSaving || pwForm.newPw.length < 8 || pwForm.newPw !== pwForm.confirm || !pwForm.current}
                className="btn btn-primary btn-sm disabled:opacity-60"
              >
                {pwSaving ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> : <Lock weight="bold" className="h-4 w-4" />}
                {t('profile.changePasswordBtn', 'Passwort ändern')}
              </button>
            </div>
          )}
        </motion.div>

        <motion.div variants={item} className={`rounded-[24px] border p-5 ${
          isVoid
            ? 'border-slate-800 bg-slate-950/85 text-white'
            : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
        }`}>
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
            {t('gdpr.summaryLabel', 'Data rights')}
          </p>
          <h3 className="mt-2 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
            DSGVO / GDPR
          </h3>
          <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
            {t('gdpr.rights', 'Ihre Rechte gemäß DSGVO Art. 15, 17 und 20.')}
          </p>

          <div className="mt-5 flex flex-col gap-3">
            <button onClick={handleExportData} disabled={exporting} className="btn btn-secondary w-full justify-start">
              {exporting ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> : <DownloadSimple weight="bold" className="h-4 w-4" />}
              <div className="text-left">
                <div className="font-medium">{t('gdpr.dataExport', 'Daten exportieren')}</div>
                <div className="text-xs opacity-70">{t('gdpr.dataExportDesc', 'Art. 20 Datenportabilität')}</div>
              </div>
            </button>

            <button
              onClick={handleDeleteAccount}
              className="btn btn-secondary w-full justify-start border-red-300 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20"
            >
              <Trash weight="bold" className="h-4 w-4 text-red-600" />
              <div className="text-left">
                <div className="font-medium">{t('gdpr.deleteAccount', 'Konto löschen')}</div>
                <div className="text-xs opacity-70">{t('gdpr.deleteAccountDesc', 'Alle Daten unwiderruflich löschen')}</div>
              </div>
            </button>
          </div>
        </motion.div>
      </div>

      <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <ProfileThemeSection />
      </motion.div>

      <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <TwoFactorSetupComponent />
      </motion.div>

      <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <NotificationPreferencesComponent />
      </motion.div>

      <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <LoginHistoryComponent />
      </motion.div>

      <ConfirmDialog
        open={confirmState.open}
        title={t('gdpr.deleteAccount')}
        message={t('gdpr.deleteConfirmMessage')}
        variant="danger"
        onConfirm={confirmState.action}
        onCancel={() => setConfirmState({ open: false, action: () => {} })}
      />
    </motion.div>
  );
}

function HeroStat({
  label,
  value,
  meta,
  isVoid,
  accent = false,
}: {
  label: string;
  value: ReactNode;
  meta: string;
  isVoid: boolean;
  accent?: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 py-3 backdrop-blur ${
      isVoid
        ? accent
          ? 'border-cyan-500/30 bg-cyan-500/10'
          : 'border-white/10 bg-white/[0.04]'
        : accent
          ? 'border-emerald-200 bg-white/85 dark:border-emerald-900/40 dark:bg-white/[0.04]'
          : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.16em] ${isVoid ? 'text-white/50' : 'text-surface-500 dark:text-white/45'}`}>
        {label}
      </p>
      <div className="mt-2 flex items-end justify-between gap-3">
        <span className="text-2xl font-semibold tracking-[-0.03em]">{value}</span>
        <span className={`text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</span>
      </div>
    </div>
  );
}

function PanelMetric({ label, value, isVoid }: { label: string; value: string; isVoid: boolean }) {
  return (
    <div className={`rounded-[18px] border px-4 py-3 ${
      isVoid
        ? 'border-white/10 bg-slate-950/70'
        : 'border-surface-200 bg-white/80 dark:border-surface-800 dark:bg-surface-900/70'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.16em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
        {label}
      </p>
      <p className="mt-2 text-lg font-semibold tracking-[-0.02em]">{value}</p>
    </div>
  );
}

function ReadOnlyMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[18px] border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/70">
      <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-surface-500 dark:text-surface-400">
        {label}
      </p>
      <p className="mt-2 text-sm font-medium text-surface-900 dark:text-white">{value}</p>
    </div>
  );
}
