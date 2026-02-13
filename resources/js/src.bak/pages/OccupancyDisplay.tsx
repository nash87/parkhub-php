import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';

export default function OccupancyDisplay() {
  const [data, setData] = useState<any>(null);

  const load = () => api.get<any>('/public/display').then(setData).catch(() => {});
  useEffect(() => { load(); const i = setInterval(load, 30000); return () => clearInterval(i); }, []);

  if (!data) return <div className="min-h-screen bg-slate-900 flex items-center justify-center"><div className="animate-spin h-12 w-12 border-4 border-amber-500 border-t-transparent rounded-full" /></div>;

  return (
    <div className="min-h-screen bg-slate-900 text-white p-8">
      <div className="text-center mb-8">
        <h1 className="text-4xl font-bold text-amber-500">{data.company_name}</h1>
        <p className="text-slate-400 text-xl mt-2">{t('occupancy.title')}</p>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
        {data.lots?.map((lot: any) => {
          const pct = lot.total > 0 ? Math.round((lot.occupied / lot.total) * 100) : 0;
          const isFull = lot.available <= 0;
          return (
            <div key={lot.id} className="bg-slate-800 rounded-2xl p-6 text-center">
              <h2 className="text-xl font-semibold mb-4">{lot.name}</h2>
              <div className={`text-6xl font-bold mb-2 ${isFull ? 'text-red-500' : 'text-green-500'}`}>
                {isFull ? t('occupancy.full') : lot.available}
              </div>
              <p className="text-slate-400 mb-4">{isFull ? '' : `${t('common.available')} of ${lot.total}`}</p>
              <div className="w-full h-3 bg-slate-700 rounded-full overflow-hidden">
                <div className={`h-full rounded-full transition-all ${pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-amber-500' : 'bg-green-500'}`} style={{ width: `${pct}%` }} />
              </div>
              <p className="text-sm text-slate-500 mt-2">{pct}% occupied</p>
            </div>
          );
        })}
      </div>
      <p className="text-center text-slate-600 text-sm mt-8">{t('occupancy.autoRefresh')}</p>
    </div>
  );
}
