import { useEffect, useState } from 'react';
import { Article, SpinnerGap, Check, Warning } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

interface ImpressumConfig {
  provider_name: string;
  provider_legal_form: string;
  street: string;
  zip_city: string;
  country: string;
  email: string;
  phone: string;
  register_court: string;
  register_number: string;
  vat_id: string;
  responsible_person: string;
  custom_text: string;
}

function Field({ label, value, onChange, required, type = 'text', hint }: {
  label: string; value: string; onChange: (v: string) => void;
  required?: boolean; type?: string; hint?: string;
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        {label}{required && <span className="text-red-500 ml-0.5">*</span>}
      </label>
      <input
        type={type}
        value={value}
        onChange={e => onChange(e.target.value)}
        className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-sm"
      />
      {hint && <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{hint}</p>}
    </div>
  );
}

export function AdminImpressPage() {
  const { t } = useTranslation();
  const [cfg, setCfg] = useState<ImpressumConfig>({
    provider_name: '', provider_legal_form: '', street: '', zip_city: '',
    country: 'Deutschland', email: '', phone: '', register_court: '',
    register_number: '', vat_id: '', responsible_person: '', custom_text: '',
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  const base = (import.meta.env.VITE_API_URL as string) || '';
  const token = localStorage.getItem('parkhub_token');
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };

  useEffect(() => {
    fetch(`${base}/api/v1/admin/impressum`, { headers })
      .then(r => r.json())
      .then(d => { if (d && d.email !== undefined) setCfg(d); })
      .catch(() => setError('Failed to load Impressum settings'))
      .finally(() => setLoading(false));
  }, []);

  const set = (key: keyof ImpressumConfig) => (v: string) => setCfg(c => ({ ...c, [key]: v }));

  const save = async () => {
    setSaving(true); setError(''); setSaved(false);
    try {
      const res = await fetch(`${base}/api/v1/admin/impressum`, {
        method: 'PUT', headers, body: JSON.stringify(cfg),
      });
      const d = await res.json();
      if (res.ok) { setSaved(true); setTimeout(() => setSaved(false), 3000); }
      else setError(d.message || 'Failed to save');
    } catch { setError('Network error'); }
    setSaving(false);
  };

  const isComplete = cfg.provider_name && cfg.street && cfg.zip_city && cfg.email;

  if (loading) return <div className="flex justify-center py-12"><SpinnerGap className="w-8 h-8 animate-spin text-primary-500" /></div>;

  return (
    <div className="space-y-6">
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 text-sm text-red-700 dark:text-red-300">
          {error}
        </div>
      )}
      {saved && (
        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4 text-sm text-green-700 dark:text-green-300 flex items-center gap-2">
          <Check weight="bold" className="w-4 h-4" /> Impressum gespeichert.
        </div>
      )}

      {/* Completeness indicator */}
      <div className={`rounded-xl p-4 flex items-start gap-3 ${isComplete ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800'}`}>
        {isComplete
          ? <Check weight="bold" className="w-5 h-5 text-green-600 mt-0.5 shrink-0" />
          : <Warning weight="fill" className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />}
        <div>
          <p className="text-sm font-medium text-gray-900 dark:text-white">
            {isComplete ? 'Impressum vollständig (§ 5 DDG)' : 'Impressum unvollständig — Pflichtangaben fehlen'}
          </p>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Gemäß § 5 DDG müssen Name, Anschrift und E-Mail-Adresse angegeben werden. Das Impressum ist unter <code className="font-mono">/impressum</code> öffentlich einsehbar.
          </p>
        </div>
      </div>

      {/* Anbieter */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
          <Article weight="fill" className="w-5 h-5 text-primary-500" />
          Anbieterangaben (§ 5 DDG Pflichtangaben)
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Firmenname / Name" value={cfg.provider_name} onChange={set('provider_name')} required hint="z.B. Mustermann GmbH oder Max Mustermann" />
          <Field label="Rechtsform" value={cfg.provider_legal_form} onChange={set('provider_legal_form')} hint="z.B. GmbH, GbR, e.V., Einzelunternehmen" />
          <Field label="Straße und Hausnummer" value={cfg.street} onChange={set('street')} required hint="Keine Postfach-Adresse erlaubt" />
          <Field label="PLZ und Ort" value={cfg.zip_city} onChange={set('zip_city')} required hint="z.B. 80331 München" />
          <Field label="Land" value={cfg.country} onChange={set('country')} />
        </div>
      </div>

      {/* Kontakt */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Kontakt</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="E-Mail-Adresse" value={cfg.email} onChange={set('email')} required type="email" hint="Pflichtangabe nach § 5 DDG" />
          <Field label="Telefonnummer" value={cfg.phone} onChange={set('phone')} hint="Empfohlen für schnelle Erreichbarkeit" />
        </div>
      </div>

      {/* Handelsregister */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Handelsregister &amp; Steuern</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Registergericht" value={cfg.register_court} onChange={set('register_court')} hint="z.B. Amtsgericht München" />
          <Field label="Registernummer" value={cfg.register_number} onChange={set('register_number')} hint="z.B. HRB 12345" />
          <Field label="Umsatzsteuer-ID (§ 27a UStG)" value={cfg.vat_id} onChange={set('vat_id')} hint="z.B. DE123456789" />
        </div>
      </div>

      {/* Verantwortliche Person */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Verantwortliche Person</h3>
        <Field label="Verantwortlich für den Inhalt" value={cfg.responsible_person} onChange={set('responsible_person')} hint="Nur bei redaktionellen Inhalten nach § 55 RStV erforderlich" />
      </div>

      {/* Zusatztext */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Zusatzangaben (optional)</h3>
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zusatztext</label>
          <textarea
            value={cfg.custom_text}
            onChange={e => setCfg(c => ({ ...c, custom_text: e.target.value }))}
            rows={4}
            className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-sm resize-none"
            placeholder="Weitere Angaben (z.B. Haftungsausschluss, Streitbeilegung...)"
          />
        </div>
      </div>

      <div className="flex justify-end">
        <button onClick={save} disabled={saving} className="inline-flex items-center gap-2 px-6 py-2.5 bg-primary-500 hover:bg-primary-600 text-white font-medium rounded-xl transition-colors disabled:opacity-50">
          {saving && <SpinnerGap className="w-4 h-4 animate-spin" />}
          {saving ? 'Speichern...' : 'Impressum speichern'}
        </button>
      </div>
    </div>
  );
}
