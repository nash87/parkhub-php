import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { CaretLeft, CaretRight } from '@phosphor-icons/react';

export default function Calendar() {
  const [bookings, setBookings] = useState<any[]>([]);
  const [absences, setAbsences] = useState<any[]>([]);
  const [month, setMonth] = useState(new Date());

  useEffect(() => {
    api.get<any[]>('/bookings').then(setBookings).catch(() => {});
    api.get<any[]>('/absences').then(setAbsences).catch(() => {});
  }, []);

  const year = month.getFullYear();
  const m = month.getMonth();
  const firstDay = new Date(year, m, 1);
  const lastDay = new Date(year, m + 1, 0);
  const startPad = (firstDay.getDay() + 6) % 7;
  const days = Array.from({ length: lastDay.getDate() }, (_, i) => i + 1);

  const getEvents = (day: number) => {
    const date = `${year}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayBookings = bookings.filter(b => b.start_time?.startsWith(date));
    const dayAbsences = absences.filter(a => a.start_date <= date && a.end_date >= date);
    return { bookings: dayBookings, absences: dayAbsences };
  };

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6">{t('calendar.title')}</h1>
      <div className="card">
        <div className="flex items-center justify-between mb-4">
          <button onClick={() => setMonth(new Date(year, m - 1))} className="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg"><CaretLeft size={20} /></button>
          <h2 className="text-lg font-semibold">{month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h2>
          <button onClick={() => setMonth(new Date(year, m + 1))} className="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg"><CaretRight size={20} /></button>
        </div>
        <div className="grid grid-cols-7 gap-1 text-center text-sm">
          {['Mo','Tu','We','Th','Fr','Sa','Su'].map(d => <div key={d} className="font-medium text-slate-500 py-2">{d}</div>)}
          {Array(startPad).fill(null).map((_, i) => <div key={`pad-${i}`} />)}
          {days.map(day => {
            const events = getEvents(day);
            const isToday = new Date().getDate() === day && new Date().getMonth() === m && new Date().getFullYear() === year;
            return (
              <div key={day} className={`p-2 rounded-lg text-sm ${isToday ? 'bg-amber-100 dark:bg-amber-900/30 font-bold' : 'hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
                {day}
                {events.bookings.length > 0 && <div className="w-1.5 h-1.5 bg-amber-500 rounded-full mx-auto mt-0.5" />}
                {events.absences.length > 0 && <div className="w-1.5 h-1.5 bg-blue-500 rounded-full mx-auto mt-0.5" />}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
