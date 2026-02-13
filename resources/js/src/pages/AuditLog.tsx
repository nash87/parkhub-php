import { useEffect, useState } from 'react';
import { ClockCounterClockwise, MagnifyingGlass } from '@phosphor-icons/react';
import { api, AuditLogEntry } from '../api/client';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { de, enUS } from 'date-fns/locale';

export function AuditLogPage() {
  const { t, i18n } = useTranslation();
  const locale = i18n.language?.startsWith('en') ? enUS : de;
  const [entries, setEntries] = useState<AuditLogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');

  useEffect(() => { loadLog(); }, []);

  async function loadLog() {
    const res = await api.getAuditLog(1, 100);
    if (res.success && res.data) setEntries(res.data);
    setLoading(false);
  }

  const filtered = filter
    ? entries.filter(e => e.action.toLowerCase().includes(filter.toLowerCase()) || (e.user_name || '').toLowerCase().includes(filter.toLowerCase()))
    : entries;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ClockCounterClockwise weight="fill" className="w-6 h-6 text-primary-600" />
            {t('auditLog.title', 'Audit Log')}
          </h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{t('auditLog.subtitle', 'All system activities')}</p>
        </div>
      </div>

      <div className="relative">
        <MagnifyingGlass weight="bold" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input
          type="text"
          value={filter}
          onChange={e => setFilter(e.target.value)}
          placeholder={t('auditLog.search', 'Search actions or users...')}
          className="input pl-10"
        />
      </div>

      {loading ? (
        <div className="space-y-2">{[1,2,3,4,5].map(i => <div key={i} className="h-12 skeleton rounded-lg" />)}</div>
      ) : (
        <div className="card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 dark:bg-gray-800/50">
                  <th className="text-left px-4 py-3 font-medium text-gray-500 dark:text-gray-400">{t('auditLog.time', 'Time')}</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500 dark:text-gray-400">{t('auditLog.user', 'User')}</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500 dark:text-gray-400">{t('auditLog.action', 'Action')}</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500 dark:text-gray-400">{t('auditLog.details', 'Details')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filtered.map(entry => (
                  <tr key={entry.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                      {format(new Date(entry.created_at), 'dd.MM.yy HH:mm', { locale })}
                    </td>
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{entry.user_name || '-'}</td>
                    <td className="px-4 py-3">
                      <span className="badge badge-info">{entry.action}</span>
                    </td>
                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400 max-w-xs truncate">{entry.details || '-'}</td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">{t('auditLog.empty', 'No entries found')}</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
