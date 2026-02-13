import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { MagnifyingGlass } from '@phosphor-icons/react';

export default function AuditLog() {
  const [logs, setLogs] = useState<any[]>([]);
  const [search, setSearch] = useState('');

  useEffect(() => {
    api.get<any>(`/admin/audit-log?search=${search}`).then(res => setLogs(res.data || [])).catch(() => {});
  }, [search]);

  return (
    <div>
      <h1 className="text-2xl font-bold mb-1">{t('auditLog.title')}</h1>
      <p className="text-slate-500 mb-6">{t('auditLog.subtitle')}</p>
      <div className="relative mb-4">
        <MagnifyingGlass size={18} className="absolute left-3 top-3 text-slate-400" />
        <input type="text" value={search} onChange={e => setSearch(e.target.value)} className="input pl-10" placeholder={t('auditLog.search')} />
      </div>
      <div className="card overflow-x-auto">
        <table className="w-full text-sm">
          <thead><tr className="border-b dark:border-slate-700">
            <th className="text-left py-2">{t('auditLog.time')}</th>
            <th className="text-left py-2">{t('auditLog.user')}</th>
            <th className="text-left py-2">{t('auditLog.action')}</th>
            <th className="text-left py-2">{t('auditLog.details')}</th>
          </tr></thead>
          <tbody>
            {logs.map((log: any) => (
              <tr key={log.id} className="border-b dark:border-slate-700">
                <td className="py-2 text-slate-500">{new Date(log.created_at).toLocaleString()}</td>
                <td className="py-2">{log.username || '—'}</td>
                <td className="py-2"><span className="bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-xs">{log.action}</span></td>
                <td className="py-2 text-slate-500 text-xs">{log.details ? JSON.stringify(log.details) : ''}</td>
              </tr>
            ))}
            {logs.length === 0 && <tr><td colSpan={4} className="py-8 text-center text-slate-500">{t('auditLog.empty')}</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
