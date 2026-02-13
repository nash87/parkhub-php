import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { Plus, Trash, House, Airplane, FirstAid, Briefcase, DotsThree } from '@phosphor-icons/react';

const TYPE_ICONS: Record<string, any> = { homeoffice: House, vacation: Airplane, sick: FirstAid, training: Briefcase, other: DotsThree };
const TYPE_COLORS: Record<string, string> = { homeoffice: 'blue', vacation: 'green', sick: 'red', training: 'purple', other: 'slate' };

export default function Absences() {
  const [absences, setAbsences] = useState<any[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ absence_type: 'homeoffice', start_date: '', end_date: '', note: '' });

  const load = () => api.get<any[]>('/absences').then(setAbsences).catch(() => {});
  useEffect(load, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await api.post('/absences', form);
    setShowForm(false);
    setForm({ absence_type: 'homeoffice', start_date: '', end_date: '', note: '' });
    load();
  };

  const handleDelete = async (id: string) => {
    await api.delete(`/absences/${id}`);
    load();
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">{t('absences.title')}</h1>
          <p className="text-slate-500">{t('absences.subtitle')}</p>
        </div>
        <button onClick={() => setShowForm(!showForm)} className="btn-primary flex items-center gap-2"><Plus size={18} /> {t('absences.addAbsence')}</button>
      </div>

      {showForm && (
        <form onSubmit={handleSubmit} className="card mb-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">Type</label>
              <select value={form.absence_type} onChange={e => setForm(f => ({ ...f, absence_type: e.target.value }))} className="input">
                <option value="homeoffice">{t('absences.types.homeoffice')}</option>
                <option value="vacation">{t('absences.types.vacation')}</option>
                <option value="sick">{t('absences.types.sick')}</option>
                <option value="training">Training</option>
                <option value="other">{t('absences.types.other')}</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Note</label>
              <input type="text" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} className="input" placeholder={t('absences.notePlaceholder')} />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">{t('absences.startDate')}</label>
              <input type="date" value={form.start_date} onChange={e => setForm(f => ({ ...f, start_date: e.target.value }))} className="input" required />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">{t('absences.endDate')}</label>
              <input type="date" value={form.end_date} onChange={e => setForm(f => ({ ...f, end_date: e.target.value }))} className="input" required />
            </div>
          </div>
          <div className="flex gap-2 mt-4">
            <button type="submit" className="btn-primary">{t('absences.addBtn')}</button>
            <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">{t('common.cancel')}</button>
          </div>
        </form>
      )}

      <div className="space-y-3">
        {absences.map(a => {
          const Icon = TYPE_ICONS[a.absence_type] || DotsThree;
          const color = TYPE_COLORS[a.absence_type] || 'slate';
          return (
            <div key={a.id} className="card flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg bg-${color}-100 dark:bg-${color}-900/30`}><Icon size={20} /></div>
                <div>
                  <p className="font-medium capitalize">{a.absence_type}</p>
                  <p className="text-sm text-slate-500">{a.start_date} → {a.end_date}{a.note ? ` — ${a.note}` : ''}</p>
                </div>
              </div>
              <button onClick={() => handleDelete(a.id)} className="text-red-500 p-2 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><Trash size={18} /></button>
            </div>
          );
        })}
        {absences.length === 0 && <p className="text-slate-500 text-center py-8">{t('absences.noEntries')}</p>}
      </div>
    </div>
  );
}
