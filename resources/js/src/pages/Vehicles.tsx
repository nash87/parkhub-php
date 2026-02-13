import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { Plus, Trash, Car } from '@phosphor-icons/react';

export default function Vehicles() {
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ plate: '', make: '', model: '', color: '' });

  const load = () => api.get<any[]>('/vehicles').then(setVehicles).catch(() => {});
  useEffect(load, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await api.post('/vehicles', form);
    setShowForm(false);
    setForm({ plate: '', make: '', model: '', color: '' });
    load();
  };

  const handleDelete = async (id: string) => {
    if (!confirm(t('confirm.deleteVehicleMessage'))) return;
    await api.delete(`/vehicles/${id}`);
    load();
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">{t('vehicles.title')}</h1>
          <p className="text-slate-500">{t('vehicles.subtitle')}</p>
        </div>
        <button onClick={() => setShowForm(!showForm)} className="btn-primary flex items-center gap-2"><Plus size={18} /> {t('vehicles.add')}</button>
      </div>

      {showForm && (
        <form onSubmit={handleSubmit} className="card mb-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label className="block text-sm font-medium mb-1">{t('vehicles.plate')}</label><input type="text" value={form.plate} onChange={e => setForm(f => ({ ...f, plate: e.target.value }))} className="input" placeholder={t('vehicles.platePlaceholder')} required /></div>
            <div><label className="block text-sm font-medium mb-1">{t('vehicles.make')}</label><input type="text" value={form.make} onChange={e => setForm(f => ({ ...f, make: e.target.value }))} className="input" placeholder={t('vehicles.makePlaceholder')} /></div>
            <div><label className="block text-sm font-medium mb-1">{t('vehicles.model')}</label><input type="text" value={form.model} onChange={e => setForm(f => ({ ...f, model: e.target.value }))} className="input" placeholder={t('vehicles.modelPlaceholder')} /></div>
            <div><label className="block text-sm font-medium mb-1">{t('vehicles.color')}</label><input type="text" value={form.color} onChange={e => setForm(f => ({ ...f, color: e.target.value }))} className="input" /></div>
          </div>
          <div className="flex gap-2 mt-4">
            <button type="submit" className="btn-primary">{t('common.save')}</button>
            <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">{t('common.cancel')}</button>
          </div>
        </form>
      )}

      <div className="space-y-3">
        {vehicles.map(v => (
          <div key={v.id} className="card flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30"><Car size={20} className="text-blue-600" /></div>
              <div>
                <p className="font-bold">{v.plate}</p>
                <p className="text-sm text-slate-500">{[v.make, v.model, v.color].filter(Boolean).join(' · ')}</p>
              </div>
            </div>
            <button onClick={() => handleDelete(v.id)} className="text-red-500 p-2 hover:bg-red-50 rounded-lg"><Trash size={18} /></button>
          </div>
        ))}
        {vehicles.length === 0 && <p className="text-slate-500 text-center py-8">{t('vehicles.noVehicles')}</p>}
      </div>
    </div>
  );
}
