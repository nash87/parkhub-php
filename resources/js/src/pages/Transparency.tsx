import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  ShieldCheck,
  Database,
  Lock,
  Globe,
  UserCircle,
  FileText,
  ArrowSquareOut,
  CheckCircle,
  Buildings,
} from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

interface DataRow {
  category: string;
  data: string;
  purpose: string;
  basis: string;
  retention: string;
}

const DATA_TABLE: DataRow[] = [
  {
    category: 'Konto',
    data: 'Name, E-Mail, Abteilung, Passwort-Hash',
    purpose: 'Authentifizierung, Zugriffskontrolle',
    basis: 'Art. 6 Abs. 1 lit. b DSGVO',
    retention: 'Bis zur Kontolöschung',
  },
  {
    category: 'Buchungen',
    data: 'Stellplatz, Zeitraum, Kennzeichen, Buchungs-ID',
    purpose: 'Buchungsdurchführung, Abrechnung',
    basis: 'Art. 6 Abs. 1 lit. b DSGVO',
    retention: '7 Jahre (§ 257 HGB / § 147 AO)',
  },
  {
    category: 'Abwesenheiten',
    data: 'Datum, Typ (Homeoffice/Urlaub), Notiz',
    purpose: 'Kapazitätsplanung, Teamübersicht',
    basis: 'Art. 6 Abs. 1 lit. b DSGVO',
    retention: 'Bis zur Kontolöschung',
  },
  {
    category: 'Fahrzeuge',
    data: 'Kennzeichen, Marke, Modell, Farbe',
    purpose: 'Vereinfachte Buchung',
    basis: 'Art. 6 Abs. 1 lit. b DSGVO',
    retention: 'Bis zur Löschung des Fahrzeugs',
  },
  {
    category: 'Server-Logs',
    data: 'IP-Adresse, Zeitstempel, aufgerufene URL, HTTP-Statuscode',
    purpose: 'Betriebssicherheit, Fehleranalyse',
    basis: 'Art. 6 Abs. 1 lit. f DSGVO',
    retention: '30 Tage (rollierend)',
  },
];

const SECURITY_FEATURES = [
  { label: 'Transport', value: 'TLS 1.3 (HTTPS)' },
  { label: 'Passwort-Hashing', value: 'bcrypt (cost 12)' },
  { label: 'Authentifizierung', value: 'Laravel Sanctum (Bearer Token)' },
  { label: 'Session-Speicher', value: 'localStorage (kein HTTP-Cookie)' },
  { label: 'Security-Header', value: 'X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy' },
  { label: 'Rate Limiting', value: '10 Login-Versuche/Minute, 5 Password-Resets/15 Min. pro IP' },
  { label: 'CSRF-Schutz', value: 'Aktiv für Web-Routes; deaktiviert für API (Token-Authentifizierung)' },
];

const THIRD_PARTY = [
  { service: 'Google Analytics', used: false },
  { service: 'Meta Pixel / Facebook', used: false },
  { service: 'Tracking-Cookies', used: false },
  { service: 'Cloud-Dienste (AWS, Azure, GCP)', used: false },
  { service: 'CDN (Cloudflare etc.)', used: false },
  { service: 'Externe Fonts (Google Fonts etc.)', used: false },
];

export function TransparencyPage() {
  const { t } = useTranslation();
  const [companyName, setCompanyName] = useState('');

  useEffect(() => {
    const base = (import.meta.env.VITE_API_URL as string) || '';
    fetch(`${base}/api/v1/branding`)
      .then(r => r.json())
      .then(d => {
        const data = d.data || d;
        if (data.company_name) setCompanyName(data.company_name);
      })
      .catch(() => {});
  }, []);

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-4xl mx-auto space-y-8 py-8 px-4"
    >
      {/* Header */}
      <div className="flex items-center gap-3">
        <ShieldCheck weight="fill" className="w-8 h-8 text-primary-600" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('transparency.title', 'Datenschutz & Transparenz')}
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            {t('transparency.subtitle', 'Was wir speichern, wie wir es schützen und welche Rechte Sie haben')}
          </p>
        </div>
      </div>

      {/* On-premise badge */}
      <div className="card p-5 flex items-start gap-4 border-l-4 border-l-green-500 bg-green-50 dark:bg-green-900/10">
        <Buildings weight="fill" className="w-6 h-6 text-green-600 dark:text-green-400 shrink-0 mt-0.5" />
        <div>
          <p className="font-semibold text-gray-900 dark:text-white">
            On-Premise — Ihre Daten bleiben bei Ihnen
            {companyName && <span className="font-normal text-gray-600 dark:text-gray-300"> ({companyName})</span>}
          </p>
          <p className="text-sm text-gray-600 dark:text-gray-300 mt-1">
            ParkHub wird auf den eigenen Servern Ihres Betreibers betrieben. Alle Daten werden
            ausschließlich dort gespeichert. Es findet keine Übermittlung an externe Cloud-Dienste
            oder Dritte statt (außer bei konfiguriertem SMTP-E-Mail-Versand).
          </p>
        </div>
      </div>

      {/* Data table */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-5">
          <Database weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            Welche Daten werden gespeichert?
          </h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead>
              <tr className="border-b border-gray-200 dark:border-gray-700">
                <th className="pb-3 pr-3 font-semibold text-gray-900 dark:text-white">Kategorie</th>
                <th className="pb-3 pr-3 font-semibold text-gray-900 dark:text-white">Daten</th>
                <th className="pb-3 pr-3 font-semibold text-gray-900 dark:text-white hidden sm:table-cell">Zweck</th>
                <th className="pb-3 pr-3 font-semibold text-gray-900 dark:text-white hidden md:table-cell">Rechtsgrundlage</th>
                <th className="pb-3 font-semibold text-gray-900 dark:text-white">Speicherdauer</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {DATA_TABLE.map(row => (
                <tr key={row.category}>
                  <td className="py-3 pr-3 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                    {row.category}
                  </td>
                  <td className="py-3 pr-3 text-gray-600 dark:text-gray-300">{row.data}</td>
                  <td className="py-3 pr-3 text-gray-600 dark:text-gray-300 hidden sm:table-cell">{row.purpose}</td>
                  <td className="py-3 pr-3 text-gray-600 dark:text-gray-300 hidden md:table-cell font-mono text-xs">
                    {row.basis}
                  </td>
                  <td className="py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{row.retention}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
          <span className="font-medium">Hinweis zur Kontolöschung (DSGVO Art. 17):</span> Bei Löschung eines
          Nutzerkontos werden Buchungseinträge anonymisiert (Name und Kennzeichen durch „[GELÖSCHT]" ersetzt).
          Abwesenheiten und Fahrzeuge werden vollständig gelöscht.
          Anonymisierte Buchungsdaten werden für steuerrechtliche Aufbewahrungspflichten behalten.
        </p>
      </div>

      {/* No third parties */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-5">
          <Globe weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            Keine externen Dienste
          </h2>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
          {THIRD_PARTY.map(({ service, used }) => (
            <div key={service} className="flex items-center gap-2 text-sm">
              <CheckCircle weight="fill" className={`w-4 h-4 shrink-0 ${used ? 'text-amber-500' : 'text-green-500'}`} />
              <span className="text-gray-600 dark:text-gray-300">
                {service} — nicht verwendet
              </span>
            </div>
          ))}
        </div>
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-4">
          Alle Assets (JavaScript, CSS, Icons) werden vom eigenen Server ausgeliefert.
          Es werden keine externen URLs beim Laden der App kontaktiert.
        </p>
      </div>

      {/* Security features */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-5">
          <Lock weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            Technische Sicherheitsmaßnahmen
          </h2>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {SECURITY_FEATURES.map(({ label, value }) => (
            <div key={label} className="flex gap-2 text-sm">
              <span className="font-medium text-gray-900 dark:text-white shrink-0">{label}:</span>
              <span className="text-gray-600 dark:text-gray-300">{value}</span>
            </div>
          ))}
        </div>
      </div>

      {/* GDPR rights */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-5">
          <UserCircle weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            Ihre DSGVO-Rechte (Art. 15–22)
          </h2>
        </div>
        <div className="space-y-3">
          <div className="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-sm">
            <CheckCircle weight="fill" className="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
            <div>
              <span className="font-medium text-gray-900 dark:text-white">Auskunft (Art. 15) &amp; Datenportabilität (Art. 20):</span>
              <span className="text-gray-600 dark:text-gray-300 ml-1">
                Laden Sie alle Ihre Daten als JSON-Datei herunter unter{' '}
                <Link to="/profile" className="text-primary-600 hover:underline dark:text-primary-400">
                  Profil → Datenschutz → Daten exportieren
                </Link>.
                Der Export enthält: Profil, Buchungen, Abwesenheiten, Fahrzeuge, Einstellungen.
              </span>
            </div>
          </div>
          <div className="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-sm">
            <CheckCircle weight="fill" className="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
            <div>
              <span className="font-medium text-gray-900 dark:text-white">Löschung (Art. 17):</span>
              <span className="text-gray-600 dark:text-gray-300 ml-1">
                Konto anonymisieren unter{' '}
                <Link to="/profile" className="text-primary-600 hover:underline dark:text-primary-400">
                  Profil → Konto löschen
                </Link>.
                Persönliche Daten werden anonymisiert; anonymisierte Buchungseinträge werden
                aus steuerrechtlichen Gründen aufbewahrt.
              </span>
            </div>
          </div>
          <div className="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-sm">
            <CheckCircle weight="fill" className="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
            <div>
              <span className="font-medium text-gray-900 dark:text-white">Berichtigung (Art. 16):</span>
              <span className="text-gray-600 dark:text-gray-300 ml-1">
                Profildaten können jederzeit unter{' '}
                <Link to="/profile" className="text-primary-600 hover:underline dark:text-primary-400">
                  Profil
                </Link>{' '}
                geändert werden.
              </span>
            </div>
          </div>
          <div className="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-sm">
            <CheckCircle weight="fill" className="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
            <div>
              <span className="font-medium text-gray-900 dark:text-white">Einschränkung (Art. 18), Widerspruch (Art. 21), Beschwerde:</span>
              <span className="text-gray-600 dark:text-gray-300 ml-1">
                Wenden Sie sich an den Betreiber dieser ParkHub-Instanz (Kontakt im{' '}
                <Link to="/impressum" className="text-primary-600 hover:underline dark:text-primary-400">
                  Impressum
                </Link>).
                Beschwerden können Sie an Ihre zuständige Landesdatenschutzbehörde richten.
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Links */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-4">
          <FileText weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            Rechtliche Dokumente
          </h2>
        </div>
        <div className="flex flex-wrap gap-3">
          <Link
            to="/privacy"
            className="inline-flex items-center gap-2 text-sm text-primary-600 hover:underline dark:text-primary-400"
          >
            <FileText weight="fill" className="w-4 h-4" />
            Datenschutzerklärung
          </Link>
          <span className="text-gray-300 dark:text-gray-600">|</span>
          <Link
            to="/impressum"
            className="inline-flex items-center gap-2 text-sm text-primary-600 hover:underline dark:text-primary-400"
          >
            <Buildings weight="fill" className="w-4 h-4" />
            Impressum
          </Link>
          <span className="text-gray-300 dark:text-gray-600">|</span>
          <Link
            to="/terms"
            className="inline-flex items-center gap-2 text-sm text-primary-600 hover:underline dark:text-primary-400"
          >
            <FileText weight="fill" className="w-4 h-4" />
            AGB
          </Link>
          <span className="text-gray-300 dark:text-gray-600">|</span>
          <a
            href="https://github.com/frostplexx/parkhub"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-sm text-primary-600 hover:underline dark:text-primary-400"
          >
            <ArrowSquareOut weight="fill" className="w-4 h-4" />
            Open Source (GitHub)
          </a>
        </div>
      </div>

      <div className="card p-4 bg-gray-50 dark:bg-gray-800/50">
        <p className="text-xs text-gray-500 dark:text-gray-400">
          Diese Seite gibt Auskunft über die Standardkonfiguration von ParkHub. Der Betreiber
          dieser Instanz ist für die tatsächliche Umsetzung und die Vollständigkeit aller
          Datenschutzangaben verantwortlich. Letzte Überprüfung der Softwareversion: 2026.
        </p>
      </div>
    </motion.div>
  );
}
