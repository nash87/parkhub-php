import { useEffect, useState } from 'react';
import { ShieldCheck, Eye, Database, Info, SpinnerGap } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

interface PrivacyConfig {
  store_ip_addresses: boolean;
  booking_visibility: number;
  show_plates_to_users: boolean;
  data_retention_days: number;
  audit_retention_days: number;
  show_booker_name: boolean;
  license_plate_display: number;
  license_plate_entry_mode: number;
}


function PrivacyToggle({ checked, onChange, label, desc }: { checked: boolean; onChange: (v: boolean) => void; label: string; desc?: string }) {
  return (
    <label className="flex items-start gap-3 cursor-pointer group">
      <div className="relative mt-0.5">
        <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} className="sr-only peer" />
        <div className="w-10 h-6 bg-gray-300 dark:bg-gray-600 rounded-full peer-checked:bg-primary-500 transition-colors" />
        <div className="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow peer-checked:translate-x-4 transition-transform" />
      </div>
      <div>
        <span className="text-sm font-medium text-gray-900 dark:text-white">{label}</span>
        {desc && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{desc}</p>}
      </div>
    </label>
  );
}

export function AdminPrivacyPage() {
  const { t } = useTranslation();
  const [config, setConfig] = useState<PrivacyConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const token = localStorage.getItem('parkhub_token');
    let cancelled = false;
    fetch('/api/v1/admin/privacy', { headers: { Authorization: 'Bearer ' + token } })
      .then(r => r.json())
      .then(d => {
        if (cancelled) return;
        if (d.data) setConfig(d.data);
        else setError(d.error?.message || 'Failed to load privacy settings');
      })
      .catch(() => { if (!cancelled) setError('Failed to load privacy settings'); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, []);

  const save = async () => {
    if (!config) return;
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const token = localStorage.getItem('parkhub_token');
      const res = await fetch('/api/v1/admin/privacy', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify(config),
      });
      const d = await res.json();
      if (d.data) { setConfig(d.data); setSaved(true); setTimeout(() => setSaved(false), 3000); }
      else setError(d.error?.message || 'Failed to save');
    } catch { setError('Network error'); }
    setSaving(false);
  };

  if (loading) return <div className="flex justify-center py-12"><SpinnerGap className="w-8 h-8 animate-spin text-primary-500" /></div>;

  if (!config) return <div className="card p-6"><div className="text-sm text-red-600 dark:text-red-400">{error || 'Failed to load privacy settings'}</div></div>;

  // Toggle extracted to module-level PrivacyToggle component below

  const visibilityOptions = [
    { value: 0, label: t('admin.privacy.visibility.full', 'Full name + plate'), desc: t('admin.privacy.visibility.fullDesc', 'Show booker name and license plate') },
    { value: 1, label: t('admin.privacy.visibility.firstName', 'First name only'), desc: t('admin.privacy.visibility.firstNameDesc', 'Only show first name of booker') },
    { value: 2, label: t('admin.privacy.visibility.initials', 'Initials only'), desc: t('admin.privacy.visibility.initialsDesc', 'Show initials (e.g. "J.D.")') },
    { value: 3, label: t('admin.privacy.visibility.occupied', '"Occupied" only'), desc: t('admin.privacy.visibility.occupiedDesc', 'Just show slot as occupied, no personal info') },
  ];

  const plateOptions = [
    { value: 0, label: t('admin.privacy.plates.show', 'Show') },
    { value: 1, label: t('admin.privacy.plates.blur', 'Blur') },
    { value: 2, label: t('admin.privacy.plates.redact', 'Redact') },
    { value: 3, label: t('admin.privacy.plates.hide', 'Hide') },
  ];

  const plateEntryOptions = [
    { value: 0, label: t('admin.privacy.plateEntry.optional', 'Optional') },
    { value: 1, label: t('admin.privacy.plateEntry.required', 'Required') },
    { value: 2, label: t('admin.privacy.plateEntry.disabled', 'Disabled') },
  ];

  return (
    <div className="space-y-6">
      {error && <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 text-sm text-red-700 dark:text-red-300">{error}</div>}
      {saved && <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4 text-sm text-green-700 dark:text-green-300">{t('admin.privacy.saved', 'Privacy settings saved successfully.')}</div>}

      {/* Card 1: Booking Visibility */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
          <Eye weight="fill" className="w-5 h-5 text-primary-500" />
          {t('admin.privacy.bookingVisibility.title', 'Booking Visibility')}
        </h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {t('admin.privacy.bookingVisibility.label', 'What non-admin users see for other bookings')}
            </label>
            <div className="space-y-2">
              {visibilityOptions.map(opt => (
                <label key={opt.value} className="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                  <input type="radio" name="booking_visibility" checked={config.booking_visibility === opt.value} onChange={() => setConfig({ ...config, booking_visibility: opt.value })} className="mt-0.5" />
                  <div>
                    <span className="text-sm font-medium text-gray-900 dark:text-white">{opt.label}</span>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{opt.desc}</p>
                  </div>
                </label>
              ))}
            </div>
          </div>
          <div className="border-t border-gray-100 dark:border-gray-800 pt-4 space-y-3">
            <PrivacyToggle checked={config.show_booker_name} onChange={v => setConfig({ ...config, show_booker_name: v })} label={t('admin.privacy.showBookerName', 'Show booker name on slots')} desc={t('admin.privacy.showBookerNameDesc', 'Allow users to see who booked which slot')} />
            <PrivacyToggle checked={config.show_plates_to_users} onChange={v => setConfig({ ...config, show_plates_to_users: v })} label={t('admin.privacy.showPlatesToUsers', 'Show license plates to users')} desc={t('admin.privacy.showPlatesToUsersDesc', 'Non-admin users can see license plates')} />
          </div>
          <div className="border-t border-gray-100 dark:border-gray-800 pt-4">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{t('admin.privacy.plateDisplay', 'License plate display mode')}</label>
            <select value={config.license_plate_display} onChange={e => setConfig({ ...config, license_plate_display: Number(e.target.value) })} className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-base">
              {plateOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          
          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{t('admin.privacy.plateEntry', 'License plate input')}</label>
            <select value={config.license_plate_entry_mode} onChange={e => setConfig({ ...config, license_plate_entry_mode: Number(e.target.value) })} className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-base">
              {plateEntryOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('admin.privacy.plateEntryDesc', 'Control whether users must provide a license plate when booking. For GDPR, consider Optional or Disabled.')}</p>
          </div>
          </div>
        </div>
      </div>

      {/* Card 2: Data Storage */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
          <Database weight="fill" className="w-5 h-5 text-purple-500" />
          {t('admin.privacy.dataStorage.title', 'Data Storage')}
        </h3>
        <div className="space-y-4">
          <PrivacyToggle checked={config.store_ip_addresses} onChange={v => setConfig({ ...config, store_ip_addresses: v })} label={t('admin.privacy.storeIp', 'Store IP addresses in audit logs')} desc={t('admin.privacy.storeIpDesc', 'When disabled, IP addresses are not recorded (recommended for GDPR)')} />
          <div className="border-t border-gray-100 dark:border-gray-800 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{t('admin.privacy.bookingRetention', 'Booking data retention (days)')}</label>
              <input type="number" min={0} value={config.data_retention_days} onChange={e => setConfig({ ...config, data_retention_days: Math.max(0, Number(e.target.value)) })} className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-base" />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('admin.privacy.retentionHint', '0 = keep forever')}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{t('admin.privacy.auditRetention', 'Audit log retention (days)')}</label>
              <input type="number" min={0} value={config.audit_retention_days} onChange={e => setConfig({ ...config, audit_retention_days: Math.max(0, Number(e.target.value)) })} className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-base" />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('admin.privacy.retentionHint', '0 = keep forever')}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Card 3: GDPR Compliance */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
          <ShieldCheck weight="fill" className="w-5 h-5 text-emerald-500" />
          {t('admin.privacy.gdpr.title', 'GDPR Compliance')}
        </h3>
        <div className="space-y-3">
          <div className="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
            <Info weight="fill" className="w-4 h-4 text-primary-500 mt-0.5 shrink-0" />
            <span>{t('admin.privacy.gdpr.info', 'Users can export and delete their data via Profile settings (Art. 15 & 17 GDPR). Configure visibility and retention above to minimize data exposure.')}</span>
          </div>
          <div className="flex flex-wrap gap-2 pt-2">
            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${!config.store_ip_addresses ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'}`}>
              {!config.store_ip_addresses ? '✓' : '!'} {t('admin.privacy.gdpr.ipAnon', 'IP anonymization')}
            </span>
            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${config.booking_visibility >= 2 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'}`}>
              {config.booking_visibility >= 2 ? '✓' : '!'} {t('admin.privacy.gdpr.dataMin', 'Data minimization')}
            </span>
            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${config.data_retention_days > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'}`}>
              {config.data_retention_days > 0 ? '✓' : '!'} {t('admin.privacy.gdpr.retention', 'Data retention policy')}
            </span>
            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
              ✓ {t('admin.privacy.gdpr.export', 'Data export (Art. 15)')}
            </span>
            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
              ✓ {t('admin.privacy.gdpr.deletion', 'Account deletion (Art. 17)')}
            </span>
          </div>
        </div>
      </div>

      {/* Save Button */}
      <div className="flex justify-end">
        <button onClick={save} disabled={saving} className="inline-flex items-center gap-2 px-6 py-2.5 bg-primary-500 hover:bg-primary-600 text-stone-900 disabled:opacity-50 text-white font-medium rounded-xl transition-colors">
          {saving && <SpinnerGap className="w-4 h-4 animate-spin" />}
          {saving ? t('common.saving', 'Saving...') : t('common.save', 'Save')}
        </button>
      </div>
    </div>
  );
}
