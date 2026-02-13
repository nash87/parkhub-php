import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { t } from '../i18n';
import { useAuth } from '../context/AuthContext';
import { Users, MapPin, CalendarCheck, ChartBar, Plus, PencilSimple, Megaphone, ClockCounterClockwise, Gear } from '@phosphor-icons/react';

export default function Admin() {
  const { isAdmin } = useAuth();
  const [stats, setStats] = useState<any>(null);
  const [users, setUsers] = useState<any[]>([]);
  const [lots, setLots] = useState<any[]>([]);
  const [tab, setTab] = useState('overview');
  const [newLot, setNewLot] = useState({ name: '', address: '' });
  const [showNewLot, setShowNewLot] = useState(false);

  useEffect(() => {
    if (!isAdmin) return;
    api.get<any>('/admin/stats').then(setStats).catch(() => {});
    api.get<any[]>('/admin/users').then(setUsers).catch(() => {});
    api.get<any[]>('/lots').then(setLots).catch(() => {});
  }, [isAdmin]);

  if (!isAdmin) return <p className="text-red-500">Access denied</p>;

  const createLot = async (e: React.FormEvent) => {
    e.preventDefault();
    await api.post('/lots', { ...newLot, total_slots: 0, status: 'open' });
    setShowNewLot(false);
    setNewLot({ name: '', address: '' });
    api.get<any[]>('/lots').then(setLots);
  };

  const tabs = [
    { id: 'overview', label: t('admin.tabs.overview'), icon: ChartBar },
    { id: 'lots', label: t('admin.tabs.lots'), icon: MapPin },
    { id: 'users', label: t('admin.tabs.users'), icon: Users },
  ];

  return (
    <div>
      <h1 className="text-2xl font-bold mb-1">{t('admin.title')}</h1>
      <p className="text-slate-500 mb-6">{t('admin.subtitle')}</p>

      <div className="flex gap-2 mb-6 overflow-x-auto">
        {tabs.map(tb => (
          <button key={tb.id} onClick={() => setTab(tb.id)}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap ${tab === tb.id ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-800'}`}>
            <tb.icon size={18} /> {tb.label}
          </button>
        ))}
        <Link to="/admin/audit-log" className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 dark:bg-slate-800 whitespace-nowrap">
          <ClockCounterClockwise size={18} /> Audit Log
        </Link>
      </div>

      {tab === 'overview' && stats && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="card"><p className="text-3xl font-bold">{stats.total_users}</p><p className="text-sm text-slate-500">Users</p></div>
          <div className="card"><p className="text-3xl font-bold">{stats.total_slots}</p><p className="text-sm text-slate-500">Total Slots</p></div>
          <div className="card"><p className="text-3xl font-bold">{stats.active_bookings}</p><p className="text-sm text-slate-500">Active Bookings</p></div>
          <div className="card"><p className="text-3xl font-bold">{stats.occupancy_percent}%</p><p className="text-sm text-slate-500">Occupancy</p></div>
        </div>
      )}

      {tab === 'lots' && (
        <div>
          <button onClick={() => setShowNewLot(true)} className="btn-primary mb-4 flex items-center gap-2"><Plus size={18} /> {t('admin.lots.createLot')}</button>
          {showNewLot && (
            <form onSubmit={createLot} className="card mb-4">
              <div className="grid grid-cols-2 gap-4">
                <input type="text" value={newLot.name} onChange={e => setNewLot(f => ({ ...f, name: e.target.value }))} className="input" placeholder="Lot name" required />
                <input type="text" value={newLot.address} onChange={e => setNewLot(f => ({ ...f, address: e.target.value }))} className="input" placeholder="Address" />
              </div>
              <button type="submit" className="btn-primary mt-3">{t('common.save')}</button>
            </form>
          )}
          <div className="space-y-3">
            {lots.map(l => (
              <div key={l.id} className="card flex items-center justify-between">
                <div><p className="font-semibold">{l.name}</p><p className="text-sm text-slate-500">{l.address} — {l.total_slots} slots</p></div>
                <span className={`text-xs px-2 py-1 rounded-full ${l.status === 'open' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}`}>{l.status}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab === 'users' && (
        <div className="card overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b dark:border-slate-700">
              <th className="text-left py-2">{t('admin.users.name')}</th>
              <th className="text-left py-2">{t('admin.users.email')}</th>
              <th className="text-left py-2">{t('admin.users.role')}</th>
              <th className="text-left py-2">{t('admin.users.status')}</th>
            </tr></thead>
            <tbody>
              {users.map((u: any) => (
                <tr key={u.id} className="border-b dark:border-slate-700">
                  <td className="py-2 font-medium">{u.name}</td>
                  <td className="py-2 text-slate-500">{u.email}</td>
                  <td className="py-2"><span className={`text-xs px-2 py-1 rounded-full ${u.role === 'admin' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>{u.role}</span></td>
                  <td className="py-2"><span className={`text-xs px-2 py-1 rounded-full ${u.is_active ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}`}>{u.is_active ? 'Active' : 'Disabled'}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
