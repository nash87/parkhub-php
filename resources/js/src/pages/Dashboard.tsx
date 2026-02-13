import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { api } from '../api/client';
import { t } from '../i18n';
import { Car, CalendarCheck, MapPin, Users, ArrowRight, Lightning } from '@phosphor-icons/react';

export default function Dashboard() {
  const { user } = useAuth();
  const [lots, setLots] = useState<any[]>([]);
  const [bookings, setBookings] = useState<any[]>([]);
  const [stats, setStats] = useState<any>(null);

  useEffect(() => {
    api.get<any[]>('/lots').then(setLots).catch(() => {});
    api.get<any[]>('/bookings?status=confirmed').then(setBookings).catch(() => {});
    api.get<any>('/user/stats').then(setStats).catch(() => {});
  }, []);

  const activeBookings = bookings.filter(b => new Date(b.end_time) > new Date() && ['confirmed', 'active'].includes(b.status));
  const totalSlots = lots.reduce((sum, l) => sum + (l.total_slots || 0), 0);
  const availableSlots = lots.reduce((sum, l) => sum + (l.available_slots || 0), 0);

  return (
    <div>
      <h1 className="text-2xl font-bold mb-1">{t('dashboard.welcome', { name: user?.name || '' })}</h1>
      <p className="text-slate-500 mb-6">Here's your parking overview</p>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="card flex items-center gap-4">
          <div className="p-3 rounded-lg bg-green-100 dark:bg-green-900/30"><MapPin size={24} className="text-green-600" /></div>
          <div><p className="text-2xl font-bold">{availableSlots}</p><p className="text-sm text-slate-500">{t('dashboard.availableSlots')}</p></div>
        </div>
        <div className="card flex items-center gap-4">
          <div className="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30"><CalendarCheck size={24} className="text-amber-600" /></div>
          <div><p className="text-2xl font-bold">{activeBookings.length}</p><p className="text-sm text-slate-500">{t('dashboard.activeBookings')}</p></div>
        </div>
        <div className="card flex items-center gap-4">
          <div className="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30"><Car size={24} className="text-blue-600" /></div>
          <div><p className="text-2xl font-bold">{totalSlots > 0 ? Math.round(((totalSlots - availableSlots) / totalSlots) * 100) : 0}%</p><p className="text-sm text-slate-500">{t('dashboard.occupancy')}</p></div>
        </div>
        <div className="card flex items-center gap-4">
          <div className="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30"><Users size={24} className="text-purple-600" /></div>
          <div><p className="text-2xl font-bold">{stats?.total_bookings || 0}</p><p className="text-sm text-slate-500">Total Bookings</p></div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold">{t('dashboard.bookNowTitle')}</h2>
            <Link to="/book" className="text-amber-600 text-sm font-medium flex items-center gap-1 hover:underline">{t('dashboard.bookNow')} <ArrowRight size={16} /></Link>
          </div>
          <p className="text-slate-500 text-sm mb-4">{t('dashboard.bookNowSubtitle')}</p>
          <Link to="/book" className="btn-primary inline-flex items-center gap-2"><Lightning size={18} /> {t('dashboard.bookNow')}</Link>
        </div>

        <div className="card">
          <h2 className="text-lg font-semibold mb-4">{t('dashboard.parkingLots')}</h2>
          {lots.length === 0 ? (
            <p className="text-slate-500 text-sm">No parking lots configured yet.</p>
          ) : (
            <div className="space-y-3">
              {lots.map(lot => (
                <div key={lot.id} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                  <div>
                    <p className="font-medium">{lot.name}</p>
                    <p className="text-sm text-slate-500">{lot.address}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-green-600">{lot.available_slots} {t('common.free')}</p>
                    <p className="text-xs text-slate-500">of {lot.total_slots || lot.slots?.length || 0}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Active bookings */}
      {activeBookings.length > 0 && (
        <div className="card mt-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold">{t('bookings.active')}</h2>
            <Link to="/bookings" className="text-amber-600 text-sm">{t('dashboard.viewAll')}</Link>
          </div>
          <div className="space-y-3">
            {activeBookings.slice(0, 3).map(b => (
              <div key={b.id} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                <div>
                  <p className="font-medium">{b.lot_name} — {b.slot_number}</p>
                  <p className="text-sm text-slate-500">{b.vehicle_plate || t('dashboard.noPlate')}</p>
                </div>
                <span className="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 px-2 py-1 rounded-full">{b.status}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
