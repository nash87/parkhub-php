import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { CalendarCheck, X, Clock } from '@phosphor-icons/react';

export default function Bookings() {
  const [bookings, setBookings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    api.get<any[]>('/bookings').then(setBookings).finally(() => setLoading(false));
  };

  useEffect(load, []);

  const cancel = async (id: string) => {
    if (!confirm(t('confirm.cancelBookingMessage'))) return;
    await api.delete(`/bookings/${id}`);
    load();
  };

  const now = new Date();
  const active = bookings.filter(b => new Date(b.end_time) > now && ['confirmed', 'active'].includes(b.status));
  const past = bookings.filter(b => new Date(b.end_time) <= now || !['confirmed', 'active'].includes(b.status));

  return (
    <div>
      <h1 className="text-2xl font-bold mb-1">{t('bookings.title')}</h1>
      <p className="text-slate-500 mb-6">{t('bookings.subtitle')}</p>

      <h2 className="text-lg font-semibold mb-3 flex items-center gap-2"><CalendarCheck size={20} className="text-green-600" /> {t('bookings.active')} ({active.length})</h2>
      {active.length === 0 ? (
        <p className="text-slate-500 text-sm mb-6">{t('bookings.noActive')}</p>
      ) : (
        <div className="space-y-3 mb-8">
          {active.map(b => (
            <div key={b.id} className="card flex items-center justify-between">
              <div>
                <p className="font-medium">{b.lot_name} — {b.slot_number}</p>
                <p className="text-sm text-slate-500 flex items-center gap-1"><Clock size={14} /> {new Date(b.start_time).toLocaleString()} → {new Date(b.end_time).toLocaleString()}</p>
                {b.vehicle_plate && <p className="text-xs text-slate-400">{b.vehicle_plate}</p>}
              </div>
              <button onClick={() => cancel(b.id)} className="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 p-2 rounded-lg" title={t('bookings.cancelBtn')}>
                <X size={20} />
              </button>
            </div>
          ))}
        </div>
      )}

      <h2 className="text-lg font-semibold mb-3 flex items-center gap-2"><Clock size={20} className="text-slate-400" /> {t('bookings.past')}</h2>
      {past.length === 0 ? (
        <p className="text-slate-500 text-sm">{t('bookings.noPast')}</p>
      ) : (
        <div className="space-y-3">
          {past.slice(0, 20).map(b => (
            <div key={b.id} className="card opacity-75">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">{b.lot_name} — {b.slot_number}</p>
                  <p className="text-sm text-slate-500">{new Date(b.start_time).toLocaleDateString()} — {b.vehicle_plate}</p>
                </div>
                <span className={`text-xs px-2 py-1 rounded-full ${b.status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-500'}`}>{b.status}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
