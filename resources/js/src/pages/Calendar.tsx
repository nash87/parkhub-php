import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { CaretLeft, CaretRight, ArrowsClockwise } from '@phosphor-icons/react';
import { api, CalendarEvent } from '../api/client';
import { useTranslation } from 'react-i18next';
import { format, startOfMonth, endOfMonth, startOfWeek, endOfWeek, addDays, addMonths, subMonths, isSameMonth, isSameDay, isToday } from 'date-fns';
import { de, enUS } from 'date-fns/locale';

const statusColors: Record<string, string> = {
  confirmed: 'bg-emerald-500',
  active: 'bg-emerald-500',
  pending: 'bg-amber-500',
  cancelled: 'bg-gray-400',
  completed: 'bg-primary-500',
  autoreleased: 'bg-red-400',
};

export function CalendarPage() {
  const { t, i18n } = useTranslation();
  const locale = i18n.language?.startsWith('de') ? de : enUS;
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  
  useEffect(() => {
    loadEvents();
  }, [currentMonth]);

  async function loadEvents() {
    // loading;
    const res = await api.getCalendarEvents();
    if (res.success && res.data) setEvents(res.data);
    // loaded;
  }

  const monthStart = startOfMonth(currentMonth);
  const monthEnd = endOfMonth(currentMonth);
  const calStart = startOfWeek(monthStart, { weekStartsOn: 1 });
  const calEnd = endOfWeek(monthEnd, { weekStartsOn: 1 });

  const days: Date[] = [];
  let d = calStart;
  while (d <= calEnd) {
    days.push(d);
    d = addDays(d, 1);
  }

  const eventsForDay = (day: Date) =>
    events.filter(e => isSameDay(new Date(e.start), day));

  const selectedEvents = selectedDate ? eventsForDay(selectedDate) : [];

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6 max-w-full">
      <div className="flex items-center justify-between">
        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
          {t('calendar.title')}
        </h1>
        <div className="flex items-center gap-2">
          <button onClick={() => setCurrentMonth(subMonths(currentMonth, 1))} className="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <CaretLeft weight="bold" className="w-5 h-5 text-gray-600 dark:text-gray-400" />
          </button>
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[140px] text-center">
            {format(currentMonth, 'MMMM yyyy', { locale })}
          </span>
          <button onClick={() => setCurrentMonth(addMonths(currentMonth, 1))} className="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <CaretRight weight="bold" className="w-5 h-5 text-gray-600 dark:text-gray-400" />
          </button>
        </div>
      </div>

      {/* Calendar Grid */}
      <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        {/* Header */}
        <div className="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
          {['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map(day => (
            <div key={day} className="p-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
              {t(`calendar.days.${day.toLowerCase()}`)}
            </div>
          ))}
        </div>
        {/* Days */}
        <div className="grid grid-cols-7">
          {days.map((day, idx) => {
            const dayEvents = eventsForDay(day);
            const inMonth = isSameMonth(day, currentMonth);
            const today = isToday(day);
            const selected = selectedDate && isSameDay(day, selectedDate);
            return (
              <button
                key={idx}
                onClick={() => setSelectedDate(day)}
                className={`min-h-[60px] sm:min-h-[80px] p-1 border-b border-r border-gray-100 dark:border-gray-800 text-left transition-colors
                  ${!inMonth ? 'opacity-30' : ''}
                  ${selected ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'}
                `}
              >
                <span className={`inline-flex items-center justify-center w-6 h-6 text-xs font-medium rounded-full
                  ${today ? 'bg-primary-600 text-white' : 'text-gray-700 dark:text-gray-300'}
                `}>
                  {format(day, 'd')}
                </span>
                <div className="mt-0.5 space-y-0.5">
                  {dayEvents.slice(0, 3).map(e => (
                    <div key={e.id} className={`h-1.5 rounded-full ${statusColors[e.status] || 'bg-gray-300'}`} />
                  ))}
                  {dayEvents.length > 3 && (
                    <span className="text-[10px] text-gray-400">+{dayEvents.length - 3}</span>
                  )}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Selected Day Detail */}
      {selectedDate && (
        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-3">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {format(selectedDate, 'EEEE, d. MMMM', { locale })}
          </h2>
          {selectedEvents.length === 0 ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">{t('calendar.noBookings')}</p>
          ) : (
            <div className="space-y-2">
              {selectedEvents.map(e => (
                <div key={e.id} className="flex items-center gap-3 p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                  <div className={`w-2 h-8 rounded-full ${statusColors[e.status] || 'bg-gray-300'}`} />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                      {e.title}
                      {e.is_recurring && <ArrowsClockwise weight="bold" className="inline w-3.5 h-3.5 ml-1 text-primary-500" />}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      {format(new Date(e.start), 'HH:mm')} - {format(new Date(e.end), 'HH:mm')}
                      {e.lot_name && ` · ${e.lot_name}`}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </motion.div>
      )}
    </motion.div>
  );
}
