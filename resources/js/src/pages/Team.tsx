import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { MagnifyingGlass, Car, House, Airplane, FirstAid, Minus } from '@phosphor-icons/react';

const STATUS_ICONS: Record<string, any> = { parked: Car, homeoffice: House, vacation: Airplane, sick: FirstAid, not_scheduled: Minus };
const STATUS_COLORS: Record<string, string> = { parked: 'text-green-600', homeoffice: 'text-blue-600', vacation: 'text-amber-600', sick: 'text-red-600', not_scheduled: 'text-slate-400' };

export default function Team() {
  const [members, setMembers] = useState<any[]>([]);
  const [search, setSearch] = useState('');

  useEffect(() => { api.get<any[]>('/team').then(setMembers).catch(() => {}); }, []);

  const filtered = members.filter(m => m.name?.toLowerCase().includes(search.toLowerCase()));

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6">{t('team.title')}</h1>
      <div className="relative mb-4">
        <MagnifyingGlass size={18} className="absolute left-3 top-3 text-slate-400" />
        <input type="text" value={search} onChange={e => setSearch(e.target.value)} className="input pl-10" placeholder={t('team.search')} />
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {filtered.map(m => {
          const Icon = STATUS_ICONS[m.status] || Minus;
          const color = STATUS_COLORS[m.status] || 'text-slate-400';
          return (
            <div key={m.id} className="card flex items-center gap-3">
              <div className={`p-2 rounded-lg bg-slate-100 dark:bg-slate-700 ${color}`}><Icon size={20} /></div>
              <div className="flex-1 min-w-0">
                <p className="font-medium truncate">{m.name}</p>
                <p className="text-sm text-slate-500">{m.slot ? `${t('team.slot')} ${m.slot}` : t(`team.status.${m.status}`)}</p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
