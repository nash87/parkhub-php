import { useEffect, useRef, useState, useMemo, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { CaretLeft, CaretRight, CalendarBlank, LinkSimple, X, Copy, Check, Question, ArrowsClockwise } from '@phosphor-icons/react';
import { api, type CalendarEvent } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { useTheme } from '../context/ThemeContext';
import { useNavLayout } from '../hooks/useNavLayout';

const statusColors: Record<string, string> = {
  confirmed: 'bg-emerald-500',
  active: 'bg-emerald-500',
  pending: 'bg-amber-500',
  cancelled: 'bg-surface-400',
  completed: 'bg-primary-500',
};

function isSameDay(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function isSameMonth(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
}

function isToday(d: Date) {
  return isSameDay(d, new Date());
}

function formatDate(d: Date) {
  return d.toISOString().slice(0, 10);
}

function getSurfaceVariant(designTheme: string, navLayout: string): 'marble' | 'void' {
  if (designTheme === 'void' || navLayout === 'focus') return 'void';
  return 'marble';
}

export function CalendarPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [navLayout] = useNavLayout();
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [loading, setLoading] = useState(true);
  const [showSubscribeModal, setShowSubscribeModal] = useState(false);
  const [subscriptionUrl, setSubscriptionUrl] = useState('');
  const [copied, setCopied] = useState(false);
  const [generatingToken, setGeneratingToken] = useState(false);
  const [showHelp, setShowHelp] = useState(false);
  const [dragEvent, setDragEvent] = useState<CalendarEvent | null>(null);
  const [dropTarget, setDropTarget] = useState<Date | null>(null);
  const [showRescheduleConfirm, setShowRescheduleConfirm] = useState(false);
  const [rescheduling, setRescheduling] = useState(false);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    loadEvents();
    return () => { abortRef.current?.abort(); };
  }, [currentMonth]);

  async function loadEvents() {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    const start = formatDate(new Date(year, month, 1));
    const end = formatDate(new Date(year, month + 1, 0));
    try {
      const res = await api.calendarEvents(start, end);
      if (controller.signal.aborted) return;
      if (res.success && res.data) setEvents(res.data);
    /* istanbul ignore next -- network failure path */
    } catch {
      if (!controller.signal.aborted) toast.error(t('common.error'));
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }

  const handleSubscribe = useCallback(async () => {
    setGeneratingToken(true);
    try {
      const res = await api.generateCalendarToken();
      if (res.success && res.data) {
        setSubscriptionUrl(res.data.url);
        setShowSubscribeModal(true);
      } else {
        toast.error(t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    } finally {
      setGeneratingToken(false);
    }
  }, [t]);

  const handleCopyLink = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(subscriptionUrl);
      setCopied(true);
      toast.success(t('calendar.linkCopied', 'Link copied'));
      setTimeout(() => setCopied(false), 2000);
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
  }, [subscriptionUrl, t]);

  // Build calendar grid (Monday start)
  const days = useMemo(() => {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDow = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;

    const result: Date[] = [];
    // Previous month padding
    for (let i = 0; i < startDow; i++) {
      result.push(new Date(year, month, 1 - startDow + i));
    }
    // Current month
    for (let d = 1; d <= lastDay.getDate(); d++) {
      result.push(new Date(year, month, d));
    }
    // Next month padding
    while (result.length % 7 !== 0) {
      result.push(new Date(year, month + 1, result.length - startDow - lastDay.getDate() + 1));
    }
    return result;
  }, [currentMonth]);

  // Pre-index events by date string for O(1) lookup per day
  const eventsByDay = useMemo(() => {
    const map = new Map<string, CalendarEvent[]>();
    for (const e of events) {
      const key = new Date(e.start).toISOString().slice(0, 10);
      const list = map.get(key);
      if (list) list.push(e);
      else map.set(key, [e]);
    }
    return map;
  }, [events]);

  const eventsForDay = (day: Date) =>
    eventsByDay.get(formatDate(day)) ?? [];

  const selectedEvents = selectedDate ? eventsForDay(selectedDate) : [];
  const upcomingEvents = useMemo(() => {
    const now = Date.now();
    return [...events]
      .filter((event) => new Date(event.end).getTime() >= now)
      .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
      .slice(0, 4);
  }, [events]);
  const bookingEvents = useMemo(
    () => events.filter((event) => event.type === 'booking'),
    [events],
  );
  const absenceEvents = useMemo(
    () => events.filter((event) => event.type === 'absence'),
    [events],
  );
  const surfaceVariant = getSurfaceVariant(designTheme, navLayout);
  const isVoid = surfaceVariant === 'void';
  const nextEvent = upcomingEvents[0] ?? null;

  const monthLabel = currentMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  // Use i18n-aware weekday abbreviations (Monday-start)
  const WEEKDAYS = useMemo(() => {
    const base = new Date(2026, 0, 5); // Monday
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(base);
      d.setDate(base.getDate() + i);
      return d.toLocaleDateString(undefined, { weekday: 'short' });
    });
  }, []);

  // ── Drag-to-Reschedule handlers ──
  function handleDragStart(e: React.DragEvent, event: CalendarEvent) {
    if (event.type !== 'booking') return;
    setDragEvent(event);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', event.id);
  }

  function handleDragOver(e: React.DragEvent, day: Date) {
    if (!dragEvent) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    setDropTarget(day);
  }

  function handleDragLeave() {
    setDropTarget(null);
  }

  function handleDrop(e: React.DragEvent, day: Date) {
    e.preventDefault();
    if (!dragEvent) return;
    setDropTarget(day);
    setShowRescheduleConfirm(true);
  }

  function handleDragEnd() {
    if (!showRescheduleConfirm) {
      setDragEvent(null);
      setDropTarget(null);
    }
  }

  async function confirmReschedule() {
    if (!dragEvent || !dropTarget) return;
    setRescheduling(true);
    try {
      const oldStart = new Date(dragEvent.start);
      const oldEnd = new Date(dragEvent.end);
      const duration = oldEnd.getTime() - oldStart.getTime();
      const newStart = new Date(dropTarget.getFullYear(), dropTarget.getMonth(), dropTarget.getDate(), oldStart.getHours(), oldStart.getMinutes());
      const newEnd = new Date(newStart.getTime() + duration);
      const res = await api.rescheduleBooking(dragEvent.id, newStart.toISOString(), newEnd.toISOString());
      if (res.success && res.data?.success) {
        toast.success(t('calendarDrag.rescheduled'));
        loadEvents();
      } else {
        toast.error(res.data?.message || res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setRescheduling(false);
    setShowRescheduleConfirm(false);
    setDragEvent(null);
    setDropTarget(null);
  }

  function cancelReschedule() {
    setShowRescheduleConfirm(false);
    setDragEvent(null);
    setDropTarget(null);
  }

  function prevMonth() {
    setCurrentMonth(d => new Date(d.getFullYear(), d.getMonth() - 1, 1));
  }
  function nextMonth() {
    setCurrentMonth(d => new Date(d.getFullYear(), d.getMonth() + 1, 1));
  }

  if (loading) return (
    <div className="space-y-4">
      <div className="h-8 w-48 skeleton rounded-lg" />
      <div className="h-96 skeleton rounded-2xl" />
    </div>
  );

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
      data-testid="calendar-shell"
      data-surface={surfaceVariant}
    >
      <section className={`overflow-hidden rounded-[30px] border p-5 shadow-[0_24px_70px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800/90 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200/80 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.15),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.24),_transparent_40%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
          <div className="space-y-4">
            <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] ${
              isVoid
                ? 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100'
                : 'border-emerald-200/80 bg-white/80 text-emerald-700 dark:border-emerald-900/50 dark:bg-surface-950/60 dark:text-emerald-300'
            }`}>
              <CalendarBlank weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void schedule room' : 'Marble calendar deck'}
            </div>
            <div>
              <h1 className="text-3xl font-black tracking-[-0.04em]">{t('calendar.title', 'Calendar')}</h1>
              <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                {t(
                  'calendar.subtitle',
                  'Monitor bookings, drag confirmed reservations to a new date, and hand off your private feed to external calendar clients without leaving the ops surface.',
                )}
              </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <SurfaceStat
                label={t('calendar.metrics.bookings', 'Bookings')}
                value={String(bookingEvents.length)}
                meta={t('calendar.metrics.bookingsMeta', 'Visible this month')}
                isVoid={isVoid}
              />
              <SurfaceStat
                label={t('calendar.metrics.absences', 'Absences')}
                value={String(absenceEvents.length)}
                meta={t('calendar.metrics.absencesMeta', 'Personal blocks')}
                isVoid={isVoid}
              />
              <SurfaceStat
                label={t('calendar.metrics.next', 'Next event')}
                value={nextEvent ? new Date(nextEvent.start).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '—'}
                meta={nextEvent ? nextEvent.title : t('calendar.metrics.nextMeta', 'No upcoming entries')}
                isVoid={isVoid}
              />
            </div>
          </div>

          <div className={`w-full max-w-xl rounded-[24px] border p-4 backdrop-blur ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.22em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
                  {t('calendar.controlKicker', 'Schedule controls')}
                </p>
                <p className={`mt-1 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('calendar.controlCopy', 'Keep your calendar feed synced and move bookings directly from the month canvas.')}
                </p>
              </div>
              <div className="flex items-center gap-2 self-start sm:self-auto">
                <button onClick={() => setShowHelp(!showHelp)} className={`flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl transition-colors ${
                  isVoid ? 'bg-slate-950/70 text-slate-300 hover:bg-slate-900' : 'bg-white/85 text-surface-500 hover:bg-white dark:bg-surface-950/60 dark:text-surface-300'
                }`} title={t('calendarDrag.helpLabel')}>
                  <Question size={20} />
                </button>
                <button
                  onClick={handleSubscribe}
                  disabled={generatingToken}
                  aria-label={t('calendar.subscribe', 'Subscribe to Calendar')}
                  className="flex min-h-[44px] items-center gap-1.5 rounded-xl bg-primary-600 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
                >
                  <LinkSimple weight="bold" className="h-4 w-4" aria-hidden="true" />
                  {t('calendar.subscribe', 'Subscribe')}
                </button>
              </div>
            </div>

            <div className={`mt-4 flex flex-col gap-3 rounded-[20px] border p-3 sm:flex-row sm:items-center sm:justify-between ${
              isVoid ? 'border-slate-800 bg-slate-950/70' : 'border-white/70 bg-white/75 dark:border-surface-800 dark:bg-surface-950/65'
            }`}>
              <div>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
                  {t('calendar.currentMonth', 'Current month')}
                </p>
                <span aria-live="polite" className={`mt-1 block text-lg font-semibold ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>
                  {monthLabel}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <button onClick={prevMonth} aria-label={t('calendar.previousMonth', 'Previous month')} className={`flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl transition-colors ${
                  isVoid ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700'
                }`}>
                  <CaretLeft weight="bold" className="h-5 w-5" aria-hidden="true" />
                </button>
                <button onClick={nextMonth} aria-label={t('calendar.nextMonth', 'Next month')} className={`flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl transition-colors ${
                  isVoid ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700'
                }`}>
                  <CaretRight weight="bold" className="h-5 w-5" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(300px,0.9fr)]">
        <section className={`overflow-hidden rounded-[26px] border ${
          isVoid
            ? 'border-slate-800 bg-slate-950/90'
            : 'border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-950/80'
        }`}>
          <div className={`grid grid-cols-7 border-b ${isVoid ? 'border-slate-800' : 'border-surface-200 dark:border-surface-800'}`}>
            {WEEKDAYS.map(day => (
              <div key={day} className={`p-3 text-center text-[11px] font-semibold uppercase tracking-[0.18em] ${
                isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'
              }`}>{day}</div>
            ))}
          </div>
          <div className="grid grid-cols-7">
            {days.map((day, idx) => {
              const dayEvents = eventsForDay(day);
              const inMonth = isSameMonth(day, currentMonth);
              const today = isToday(day);
              const selected = selectedDate && isSameDay(day, selectedDate);
              const isDropTarget = dropTarget && isSameDay(day, dropTarget);
              return (
                <div
                  key={idx}
                  onClick={() => setSelectedDate(day)}
                  onDragOver={(e) => handleDragOver(e, day)}
                  onDragLeave={handleDragLeave}
                  onDrop={(e) => handleDrop(e, day)}
                  role="button"
                  tabIndex={0}
                  aria-label={`${day.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' })}${dayEvents.length > 0 ? `, ${dayEvents.length} ${t('calendar.events', 'events')}` : ''}`}
                  aria-pressed={!!selected}
                  className={`min-h-[110px] border-b border-r p-2 text-left transition-colors ${
                    isVoid ? 'border-slate-800' : 'border-surface-100 dark:border-surface-800'
                  } cursor-pointer ${
                    !inMonth ? 'opacity-35' : ''
                  } ${selected
                    ? isVoid
                      ? 'bg-cyan-500/10'
                      : 'bg-primary-50 dark:bg-primary-900/20'
                    : isVoid
                      ? 'hover:bg-slate-900/80'
                      : 'hover:bg-surface-50 dark:hover:bg-surface-800/50'
                  } ${isDropTarget
                    ? isVoid
                      ? 'ring-2 ring-cyan-400 bg-cyan-500/10'
                      : 'ring-2 ring-primary-500 bg-primary-50/50 dark:bg-primary-900/30'
                    : ''
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                      today
                        ? 'bg-primary-600 text-white'
                        : isVoid
                          ? 'text-slate-300'
                          : 'text-surface-700 dark:text-surface-300'
                    }`}>
                      {day.getDate()}
                    </span>
                    {dayEvents.length > 0 && (
                      <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                        isVoid ? 'bg-slate-900 text-slate-300' : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                      }`}>
                        {dayEvents.length}
                      </span>
                    )}
                  </div>
                  <div className="mt-3 space-y-1.5">
                    {dayEvents.slice(0, 3).map(e => (
                      <div
                        key={e.id}
                        draggable={e.type === 'booking'}
                        onDragStart={(ev) => handleDragStart(ev, e)}
                        onDragEnd={handleDragEnd}
                        className={`rounded-xl border px-2 py-1.5 ${e.type === 'booking' ? 'cursor-grab active:cursor-grabbing' : ''} ${
                          isVoid
                            ? 'border-slate-800 bg-slate-900/90'
                            : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <div className={`h-2 w-2 rounded-full ${statusColors[e.status] || 'bg-surface-300'}`} />
                          <p className={`min-w-0 truncate text-[11px] font-medium ${
                            isVoid ? 'text-white' : 'text-surface-700 dark:text-surface-200'
                          }`}>
                            {e.title}
                          </p>
                        </div>
                      </div>
                    ))}
                    {dayEvents.length > 3 && <span className={`block text-[10px] ${isVoid ? 'text-slate-500' : 'text-surface-400'}`}>+{dayEvents.length - 3}</span>}
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        <aside className="space-y-4">
          <section className={`rounded-[26px] border p-5 ${
            isVoid
              ? 'border-slate-800 bg-slate-950/90 text-white'
              : 'border-surface-200 bg-white text-surface-900 dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
          }`}>
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
                  {selectedDate ? t('calendar.selectedDay', 'Selected day') : t('calendar.dayInspector', 'Day inspector')}
                </p>
                <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em]">
                  {selectedDate
                    ? selectedDate.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' })
                    : t('calendar.selectDayTitle', 'Pick a date')}
                </h2>
              </div>
              {selectedDate && (
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                  isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-primary-50 text-primary-700 dark:bg-primary-950/30 dark:text-primary-300'
                }`}>
                  {selectedEvents.length} {t('calendar.events', 'events')}
                </span>
              )}
            </div>

            {selectedDate ? (
              selectedEvents.length === 0 ? (
                <div className={`mt-5 rounded-[22px] border p-8 text-center ${
                  isVoid ? 'border-slate-800 bg-slate-900/80' : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/60'
                }`}>
                  <CalendarBlank weight="light" className={`mx-auto mb-2 h-10 w-10 ${isVoid ? 'text-slate-600' : 'text-surface-300 dark:text-surface-600'}`} />
                  <p className={`text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{t('calendar.noBookings', 'No entries on this day')}</p>
                </div>
              ) : (
                <div className="mt-5 space-y-3">
                  {selectedEvents.map(e => (
                    <div key={e.id} className={`rounded-[20px] border p-4 ${
                      isVoid ? 'border-slate-800 bg-slate-900/90' : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
                    }`}>
                      <div className="flex items-start gap-3">
                        <div className={`mt-1 h-10 w-2 rounded-full ${statusColors[e.status] || 'bg-surface-300'}`} />
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-semibold">{e.title}</p>
                          <p className={`mt-1 text-xs ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>
                            {new Date(e.start).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })} - {new Date(e.end).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
                            {e.lot_name && ` · ${e.lot_name}`}
                          </p>
                          <div className="mt-3 flex flex-wrap gap-2">
                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                              isVoid ? 'bg-slate-950 text-slate-300' : 'bg-white text-surface-600 dark:bg-surface-950 dark:text-surface-300'
                            }`}>
                              {e.type === 'booking' ? t('calendar.booking', 'Booking') : t('calendar.absence', 'Absence')}
                            </span>
                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                              isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-primary-50 text-primary-700 dark:bg-primary-950/30 dark:text-primary-300'
                            }`}>
                              {e.status}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )
            ) : (
              <div className={`mt-5 rounded-[22px] border p-8 text-center ${
                isVoid ? 'border-slate-800 bg-slate-900/80' : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/60'
              }`}>
                <p className={`text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{t('calendar.selectDay', 'Click a day to see entries')}</p>
              </div>
            )}
          </section>

          <section className={`rounded-[26px] border p-5 ${
            isVoid
              ? 'border-slate-800 bg-slate-950/90 text-white'
              : 'border-surface-200 bg-white text-surface-900 dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
          }`}>
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
                  {t('calendar.upNext', 'Up next')}
                </p>
                <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em]">{t('calendar.upNextTitle', 'Upcoming entries')}</h2>
              </div>
              <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                isVoid ? 'bg-slate-900 text-slate-300' : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
              }`}>
                {upcomingEvents.length}
              </span>
            </div>

            {upcomingEvents.length === 0 ? (
              <p className={`mt-4 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>
                {t('calendar.noUpcoming', 'No upcoming entries in view.')}
              </p>
            ) : (
              <div className="mt-4 space-y-3">
                {upcomingEvents.map((event) => (
                  <div key={event.id} className={`rounded-[20px] border px-4 py-3 ${
                    isVoid ? 'border-slate-800 bg-slate-900/90' : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
                  }`}>
                    <div className="flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{event.title}</p>
                        <p className={`mt-1 text-xs ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>
                          {new Date(event.start).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} · {new Date(event.start).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
                          {event.lot_name ? ` · ${event.lot_name}` : ''}
                        </p>
                      </div>
                      <div className={`h-3 w-3 rounded-full ${statusColors[event.status] || 'bg-surface-300'}`} />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>
        </aside>
      </div>

      {/* Drag help tooltip */}
      <AnimatePresence>
        {showHelp && (
          <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }}
            className="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-sm text-primary-800 dark:text-primary-300 flex items-start gap-2">
            <ArrowsClockwise size={18} className="mt-0.5 shrink-0" />
            <span>{t('calendarDrag.help')}</span>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Reschedule confirmation dialog */}
      <AnimatePresence>
        {showRescheduleConfirm && dragEvent && dropTarget && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" data-testid="reschedule-confirm">
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="bg-white dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6 max-w-sm w-full mx-4 shadow-xl"
            >
              <h3 className="text-lg font-semibold text-surface-900 dark:text-white mb-3">
                {t('calendarDrag.confirmTitle')}
              </h3>
              <p className="text-sm text-surface-600 dark:text-surface-400 mb-2">
                {t('calendarDrag.confirmDesc')}
              </p>
              <div className="text-sm text-surface-700 dark:text-surface-300 mb-4 space-y-1">
                <p><strong>{dragEvent.title}</strong></p>
                <p>{t('calendarDrag.from')}: {new Date(dragEvent.start).toLocaleDateString()}</p>
                <p>{t('calendarDrag.to')}: {dropTarget.toLocaleDateString()}</p>
              </div>
              <div className="flex gap-2 justify-end">
                <button onClick={cancelReschedule}
                  className="px-4 py-2 rounded-lg text-sm font-medium bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700">
                  {t('common.cancel', 'Cancel')}
                </button>
                <button onClick={confirmReschedule} disabled={rescheduling}
                  className="px-4 py-2 rounded-lg text-sm font-medium bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50 flex items-center gap-1">
                  <ArrowsClockwise size={14} />
                  {rescheduling ? t('calendarDrag.rescheduling') : t('calendarDrag.confirmBtn')}
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* Subscribe modal */}
      <AnimatePresence>
        {showSubscribeModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowSubscribeModal(false)}>
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              onClick={(e) => e.stopPropagation()}
              className="bg-white dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-6 max-w-lg w-full mx-4 shadow-xl"
            >
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-surface-900 dark:text-white">
                  {t('calendar.subscribeTitle', 'Subscribe to Calendar')}
                </h3>
                <button
                  onClick={() => setShowSubscribeModal(false)}
                  aria-label={t('common.close', 'Close')}
                  className="p-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                >
                  <X weight="bold" className="w-5 h-5 text-surface-500" />
                </button>
              </div>

              <p className="text-sm text-surface-600 dark:text-surface-400 mb-4">
                {t('calendar.subscribeDesc', 'Use this URL to subscribe to your parking calendar from any calendar app.')}
              </p>

              {/* URL field with copy button */}
              <div className="flex items-center gap-2 mb-6">
                <input
                  type="text"
                  readOnly
                  value={subscriptionUrl}
                  className="flex-1 px-3 py-2 text-sm rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 text-surface-900 dark:text-white font-mono truncate"
                  data-testid="subscription-url"
                />
                <button
                  onClick={handleCopyLink}
                  aria-label={t('calendar.copyLink', 'Copy link')}
                  className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl bg-primary-600 text-white hover:bg-primary-700 transition-colors min-h-[40px]"
                >
                  {copied ? <Check weight="bold" className="w-4 h-4" /> : <Copy weight="bold" className="w-4 h-4" />}
                  {t('calendar.copyLink', 'Copy')}
                </button>
              </div>

              {/* Instructions */}
              <div className="space-y-3">
                <h4 className="text-sm font-medium text-surface-900 dark:text-white">
                  {t('calendar.instructions', 'How to subscribe')}
                </h4>
                <div className="space-y-2 text-xs text-surface-600 dark:text-surface-400">
                  <div className="flex gap-2">
                    <span className="font-semibold text-surface-700 dark:text-surface-300 shrink-0">Google Calendar:</span>
                    <span>{t('calendar.instructionGoogle', 'Settings > Add calendar > From URL > paste the link')}</span>
                  </div>
                  <div className="flex gap-2">
                    <span className="font-semibold text-surface-700 dark:text-surface-300 shrink-0">Outlook:</span>
                    <span>{t('calendar.instructionOutlook', 'Add calendar > Subscribe from web > paste the link')}</span>
                  </div>
                  <div className="flex gap-2">
                    <span className="font-semibold text-surface-700 dark:text-surface-300 shrink-0">Apple Calendar:</span>
                    <span>{t('calendar.instructionApple', 'File > New Calendar Subscription > paste the link')}</span>
                  </div>
                </div>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </motion.div>
  );
}

function SurfaceStat({
  label,
  value,
  meta,
  isVoid,
}: {
  label: string;
  value: string;
  meta: string;
  isVoid: boolean;
}) {
  return (
    <div className={`rounded-[22px] border p-4 ${
      isVoid
        ? 'border-white/10 bg-white/[0.04]'
        : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>{label}</p>
      <p className="mt-3 text-2xl font-black tracking-[-0.04em]">{value}</p>
      <p className={`mt-2 text-xs leading-5 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>{meta}</p>
    </div>
  );
}
