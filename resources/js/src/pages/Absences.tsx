import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { motion, AnimatePresence, PanInfo } from 'framer-motion';
import {
  House, Airplane, Calendar, CalendarCheck, Trash, Plus, CaretLeft, CaretRight,
  UploadSimple, UsersThree, FirstAidKit, Briefcase, NoteBlank, CalendarBlank,
  X,
} from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { api, AbsenceEntry, TeamAbsenceEntry, AbsencePattern } from '../api/client';

// ═══════════════════════════════════════════════════════════════════════════════
// TYPES & HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

type AbsenceType = 'homeoffice' | 'vacation' | 'sick' | 'business_trip' | 'other';
type ViewMode = 'calendar' | 'team';

const ABSENCE_CONFIG: Record<AbsenceType, { icon: typeof House; color: string; bgLight: string; bgDark: string; ring: string; dot: string; emoji: string }> = {
  homeoffice: { icon: House, color: 'text-primary-600 dark:text-primary-400', bgLight: 'bg-primary-100 dark:bg-primary-900/30', bgDark: 'bg-primary-200 dark:bg-primary-800/50', ring: 'ring-primary-400', dot: 'bg-primary-500', emoji: '🏠' },
  vacation: { icon: Airplane, color: 'text-orange-600 dark:text-orange-400', bgLight: 'bg-orange-100 dark:bg-orange-900/30', bgDark: 'bg-orange-200 dark:bg-orange-800/50', ring: 'ring-orange-400', dot: 'bg-orange-500', emoji: '🏖️' },
  sick: { icon: FirstAidKit, color: 'text-red-600 dark:text-red-400', bgLight: 'bg-red-100 dark:bg-red-900/30', bgDark: 'bg-red-200 dark:bg-red-800/50', ring: 'ring-red-400', dot: 'bg-red-500', emoji: '🤒' },
  business_trip: { icon: Briefcase, color: 'text-purple-600 dark:text-purple-400', bgLight: 'bg-purple-100 dark:bg-purple-900/30', bgDark: 'bg-purple-200 dark:bg-purple-800/50', ring: 'ring-purple-400', dot: 'bg-purple-500', emoji: '✈️' },
  other: { icon: NoteBlank, color: 'text-gray-600 dark:text-gray-400', bgLight: 'bg-gray-100 dark:bg-gray-900/30', bgDark: 'bg-gray-200 dark:bg-gray-800/50', ring: 'ring-gray-400', dot: 'bg-gray-500', emoji: '📋' },
};

function isSameDay(a: Date, b: Date) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
function isDateInRange(date: Date, start: string, end: string) { const d = date.toISOString().slice(0, 10); return d >= start && d <= end; }
function getMonday(d: Date) { const day = d.getDay(); const diff = d.getDate() - day + (day === 0 ? -6 : 1); return new Date(d.getFullYear(), d.getMonth(), diff); }
function toDateStr(d: Date) { return d.toISOString().slice(0, 10); }

// ═══════════════════════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════════════════════════════

export function AbsencesPage() {
  const { t } = useTranslation();
  const [entries, setEntries] = useState<AbsenceEntry[]>([]);
  const [teamEntries, setTeamEntries] = useState<TeamAbsenceEntry[]>([]);
  const [patterns, setPatterns] = useState<AbsencePattern[]>([]);
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState<ViewMode>('calendar');
  const [showAddModal, setShowAddModal] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [showPattern, setShowPattern] = useState(false);

  const today = useMemo(() => new Date(), []);
  const [calMonth, setCalMonth] = useState(today.getMonth());
  const [calYear, setCalYear] = useState(today.getFullYear());

  const loadData = useCallback(async () => {
    try {
      const [absRes, teamRes, patRes] = await Promise.all([
        api.listAbsences(),
        api.teamAbsences(),
        api.getAbsencePattern(),
      ]);
      if (absRes.success && absRes.data) setEntries(absRes.data);
      if (teamRes.success && teamRes.data) setTeamEntries(teamRes.data);
      if (patRes.success && patRes.data) setPatterns(patRes.data);
    } catch { /* ignore */ }
    setLoading(false);
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => { void loadData(); }, 0);
    return () => clearTimeout(timer);
  }, [loadData]);

  // ── Calendar navigation ──
  function prevMonth() {
    if (calMonth === 0) {
      setCalMonth(11);
      setCalYear(y => y - 1);
    } else {
      setCalMonth(m => m - 1);
    }
  }

  function nextMonth() {
    if (calMonth === 11) {
      setCalMonth(0);
      setCalYear(y => y + 1);
    } else {
      setCalMonth(m => m + 1);
    }
  }

  // Swipe support
  const handleDragEnd = useCallback((_: MouseEvent | TouchEvent | PointerEvent, info: PanInfo) => {
    if (info.offset.x > 80) {
      if (calMonth === 0) {
        setCalMonth(11);
        setCalYear(y => y - 1);
      } else {
        setCalMonth(m => m - 1);
      }
    } else if (info.offset.x < -80) {
      if (calMonth === 11) {
        setCalMonth(0);
        setCalYear(y => y + 1);
      } else {
        setCalMonth(m => m + 1);
      }
    }
  }, [calMonth]);

  // ── Stats ──
  const hoPattern = useMemo(() => patterns.find(p => p.absence_type === 'homeoffice'), [patterns]);

  const todayStr = toDateStr(today);
  const todayAbsence = useMemo(() => {
    const entry = entries.find(e => isDateInRange(today, e.start_date, e.end_date));
    if (entry) return entry.absence_type as AbsenceType;
    const todayDow = today.getDay() === 0 ? 6 : today.getDay() - 1;
    if (hoPattern && hoPattern.weekdays.includes(todayDow)) return 'homeoffice' as AbsenceType;
    return null;
  }, [entries, hoPattern, today]);

  const weekStats = useMemo(() => {
    const monday = getMonday(today);
    const counts: Record<AbsenceType, number> = { homeoffice: 0, vacation: 0, sick: 0, business_trip: 0, other: 0 };
    for (let i = 0; i < 5; i++) {
      const d = new Date(monday); d.setDate(monday.getDate() + i);
      
      const entry = entries.find(e => isDateInRange(d, e.start_date, e.end_date));
      if (entry) { counts[entry.absence_type as AbsenceType]++; continue; }
      const dow = d.getDay() === 0 ? 6 : d.getDay() - 1;
      if (hoPattern && hoPattern.weekdays.includes(dow)) counts.homeoffice++;
    }
    return counts;
  }, [entries, hoPattern, today]);

  const nextAbsence = useMemo(() => {
    const future = entries.filter(e => e.end_date >= todayStr).sort((a, b) => a.start_date.localeCompare(b.start_date));
    return future[0] || null;
  }, [entries, todayStr]);

  // ── Calendar days ──
  const WEEKDAY_SHORT = [
    t('homeoffice.weekdaysShort.mon'), t('homeoffice.weekdaysShort.tue'),
    t('homeoffice.weekdaysShort.wed'), t('homeoffice.weekdaysShort.thu'),
    t('homeoffice.weekdaysShort.fri'), t('homeoffice.weekdaysShort.sat'),
    t('homeoffice.weekdaysShort.sun'),
  ];

  interface CalDay {
    date: Date;
    inMonth: boolean;
    isToday: boolean;
    absenceTypes: AbsenceType[];
  }

  const calendarDays = useMemo((): CalDay[] => {
    const year = calYear; const month = calMonth;
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDow = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
    const days: CalDay[] = [];

    for (let i = 0; i < startDow; i++) {
      const d = new Date(year, month, 1 - startDow + i);
      days.push({ date: d, inMonth: false, isToday: false, absenceTypes: [] });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
      const date = new Date(year, month, d);
      const dow = date.getDay() === 0 ? 6 : date.getDay() - 1;
      const types: AbsenceType[] = [];

      // Check entries
      for (const e of entries) {
        if (isDateInRange(date, e.start_date, e.end_date)) {
          const at = e.absence_type as AbsenceType;
          if (!types.includes(at)) types.push(at);
        }
      }

      // Check pattern
      if (dow < 5 && hoPattern && hoPattern.weekdays.includes(dow) && !types.includes('homeoffice')) {
        types.push('homeoffice');
      }

      days.push({ date, inMonth: true, isToday: isSameDay(date, today), absenceTypes: types });
    }

    while (days.length % 7 !== 0) {
      const d = new Date(year, month + 1, days.length - startDow - lastDay.getDate() + 1);
      days.push({ date: d, inMonth: false, isToday: false, absenceTypes: [] });
    }

    return days;
  }, [entries, hoPattern, calMonth, calYear, today]);

  const calMonthLabel = new Date(calYear, calMonth, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

  // ── Delete handler ──
  async function deleteEntry(id: string) {
    const res = await api.deleteAbsence(id);
    if (res.success) {
      setEntries(prev => prev.filter(e => e.id !== id));
      toast.success(t('absences.deleted'));
    }
  }

  // ── Add handler ──
  async function handleAdd(type: AbsenceType, startDate: string, endDate: string, note: string) {
    const res = await api.createAbsence(type, startDate, endDate, note || undefined);
    if (res.success && res.data) {
      setEntries(prev => [...prev, res.data!].sort((a, b) => a.start_date.localeCompare(b.start_date)));
      toast.success(t('absences.added'));
      setShowAddModal(false);
    } else {
      toast.error(res.error?.message || 'Error');
    }
  }

  // ── Pattern handler ──
  async function handlePatternSave(weekdays: number[]) {
    const res = await api.setAbsencePattern('homeoffice', weekdays);
    if (res.success && res.data) {
      setPatterns(prev => {
        const filtered = prev.filter(p => p.absence_type !== 'homeoffice');
        return [...filtered, res.data!];
      });
      toast.success(t('absences.patternUpdated'));
    }
  }

  // ── Animation variants ──
  const containerVariants = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.06 } } };
  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  // ── Loading skeleton ──
  if (loading) return (
    <div className="space-y-6 max-w-full overflow-x-hidden">
      <div className="h-8 w-64 skeleton rounded-lg" />
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {[1,2,3].map(i => <div key={i} className="h-24 skeleton rounded-2xl" />)}
      </div>
      <div className="h-80 skeleton rounded-2xl" />
    </div>
  );

  return (
    <motion.div variants={containerVariants} initial="hidden" animate="show" className="space-y-6 max-w-full overflow-x-hidden">
      {/* ── Header ── */}
      <motion.div variants={itemVariants} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <Calendar weight="fill" className="w-7 h-7 text-primary-600" />
            {t('absences.title')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{t('absences.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <div className="flex bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
            <button
              onClick={() => setViewMode('calendar')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${viewMode === 'calendar' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'}`}
            >
              {t('absences.myCalendar')}
            </button>
            <button
              onClick={() => setViewMode('team')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${viewMode === 'team' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'}`}
            >
              <UsersThree weight="bold" className="w-4 h-4 inline mr-1" />
              {t('absences.teamView')}
            </button>
          </div>
        </div>
      </motion.div>

      {/* ── Today banner ── */}
      {todayAbsence && (
        <motion.div variants={itemVariants} className={`card ${ABSENCE_CONFIG[todayAbsence].bgLight} border ${todayAbsence === 'homeoffice' ? 'border-primary-200 dark:border-primary-800' : todayAbsence === 'vacation' ? 'border-orange-200 dark:border-orange-800' : todayAbsence === 'sick' ? 'border-red-200 dark:border-red-800' : todayAbsence === 'business_trip' ? 'border-purple-200 dark:border-purple-800' : 'border-gray-200 dark:border-gray-800'} p-4`}>
          <div className="flex items-center gap-3">
            {(() => { const Icon = ABSENCE_CONFIG[todayAbsence].icon; return <Icon weight="fill" className={`w-6 h-6 ${ABSENCE_CONFIG[todayAbsence].color}`} />; })()}
            <div>
              <p className="font-semibold text-gray-800 dark:text-gray-200">{t(`absences.todayBanner.${todayAbsence}`)}</p>
              <p className="text-sm text-gray-600 dark:text-gray-400">{t('absences.todayBannerDesc')}</p>
            </div>
          </div>
        </motion.div>
      )}

      {/* ── Stat cards ── */}
      <motion.div variants={itemVariants} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div className="stat-card">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{t('absences.thisWeek')}</p>
              <div className="mt-2 flex flex-wrap gap-2">
                {weekStats.homeoffice > 0 && <span className="text-sm font-semibold text-primary-600 dark:text-primary-400">{weekStats.homeoffice}× {ABSENCE_CONFIG.homeoffice.emoji}</span>}
                {weekStats.vacation > 0 && <span className="text-sm font-semibold text-orange-600 dark:text-orange-400">{weekStats.vacation}× {ABSENCE_CONFIG.vacation.emoji}</span>}
                {weekStats.sick > 0 && <span className="text-sm font-semibold text-red-600 dark:text-red-400">{weekStats.sick}× {ABSENCE_CONFIG.sick.emoji}</span>}
                {weekStats.business_trip > 0 && <span className="text-sm font-semibold text-purple-600 dark:text-purple-400">{weekStats.business_trip}× {ABSENCE_CONFIG.business_trip.emoji}</span>}
                {Object.values(weekStats).every(v => v === 0) && <span className="text-sm text-gray-400">{t('absences.noAbsencesThisWeek')}</span>}
              </div>
            </div>
            <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center">
              <Calendar weight="fill" className="w-5 h-5 text-primary-600 dark:text-primary-400" />
            </div>
          </div>
        </div>

        <div className="stat-card">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{t('absences.nextAbsence')}</p>
              {nextAbsence ? (
                <div className="mt-2">
                  <span className="text-sm font-semibold text-gray-900 dark:text-white">
                    {new Date(nextAbsence.start_date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                  </span>
                  <span className={`ml-2 text-sm ${ABSENCE_CONFIG[nextAbsence.absence_type as AbsenceType]?.color || ''}`}>
                    {ABSENCE_CONFIG[nextAbsence.absence_type as AbsenceType]?.emoji} {t(`absences.types.${nextAbsence.absence_type}`)}
                  </span>
                </div>
              ) : (
                <p className="mt-2 text-sm text-gray-400">{t('absences.noneScheduled')}</p>
              )}
            </div>
            <div className="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
              <CalendarCheck weight="fill" className="w-5 h-5 text-orange-600 dark:text-orange-400" />
            </div>
          </div>
        </div>

        <div className="stat-card sm:col-span-2 lg:col-span-1">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{t('absences.totalEntries')}</p>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="stat-value text-primary-600 dark:text-primary-400">{entries.length}</span>
                <span className="text-gray-500 dark:text-gray-400">{t('absences.entries')}</span>
              </div>
            </div>
            <div className="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
              <CalendarBlank weight="fill" className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
          </div>
        </div>
      </motion.div>

      {/* ── Main content ── */}
      {viewMode === 'calendar' ? (
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
          {/* Calendar */}
          <motion.div variants={itemVariants} className="lg:col-span-3 card p-4 sm:p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{calMonthLabel}</h2>
              <div className="flex items-center gap-1">
                <button onClick={prevMonth} className="btn btn-ghost btn-icon min-w-[44px] min-h-[44px]" aria-label="Previous month"><CaretLeft weight="bold" className="w-5 h-5" /></button>
                <button onClick={() => { setCalMonth(today.getMonth()); setCalYear(today.getFullYear()); }} className="btn btn-ghost text-xs px-2">{t('absences.today')}</button>
                <button onClick={nextMonth} className="btn btn-ghost btn-icon min-w-[44px] min-h-[44px]" aria-label="Next month"><CaretRight weight="bold" className="w-5 h-5" /></button>
              </div>
            </div>
            <motion.div
              drag="x"
              dragConstraints={{ left: 0, right: 0 }}
              dragElastic={0.2}
              onDragEnd={handleDragEnd}
              className="touch-pan-y"
            >
              <div className="grid grid-cols-7 gap-1">
                {WEEKDAY_SHORT.map(d => <div key={d} className="text-center text-xs font-semibold text-gray-400 dark:text-gray-500 py-2">{d}</div>)}
                {calendarDays.map((day, i) => {
                  const isWeekend = day.date.getDay() === 0 || day.date.getDay() === 6;
                  const mainType = day.absenceTypes[0];
                  const cfg = mainType ? ABSENCE_CONFIG[mainType] : null;
                  return (
                    <div
                      key={i}
                      className={`relative flex flex-col items-center justify-center rounded-lg text-sm font-medium transition-all min-h-[40px] min-w-[40px] ${
                        !day.inMonth ? 'text-gray-300 dark:text-gray-700' :
                        day.isToday ? 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900' : ''
                      } ${
                        cfg ? `${cfg.bgDark} ${cfg.color.split(' ')[0]}` :
                        isWeekend && day.inMonth ? 'text-gray-400 dark:text-gray-500' :
                        day.inMonth ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' : ''
                      }`}
                    >
                      {day.date.getDate()}
                      {day.absenceTypes.length > 0 && (
                        <div className="flex gap-0.5 mt-0.5">
                          {day.absenceTypes.slice(0, 3).map((at, j) => (
                            <div key={j} className={`w-1.5 h-1.5 rounded-full ${ABSENCE_CONFIG[at].dot}`} />
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </motion.div>
            {/* Legend */}
            <div className="flex flex-wrap gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
              {(Object.entries(ABSENCE_CONFIG) as [AbsenceType, typeof ABSENCE_CONFIG.homeoffice][]).map(([type, cfg]) => (
                <div key={type} className="flex items-center gap-1.5">
                  <div className={`w-3 h-3 rounded-sm ${cfg.bgDark}`} />
                  <span>{t(`absences.types.${type}`)}</span>
                </div>
              ))}
              <div className="flex items-center gap-1.5"><div className="w-3 h-3 rounded-sm ring-2 ring-primary-500" /><span>{t('absences.legendToday')}</span></div>
            </div>
          </motion.div>

          {/* Sidebar: entries + actions */}
          <div className="lg:col-span-2 space-y-6">
            {/* Pattern section */}
            <motion.div variants={itemVariants} className="card p-4 sm:p-6">
              <button
                onClick={() => setShowPattern(!showPattern)}
                className="flex items-center justify-between w-full text-left"
              >
                <h3 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <House weight="fill" className="w-5 h-5 text-primary-600" />
                  {t('absences.weeklyPattern')}
                </h3>
                <CaretRight weight="bold" className={`w-4 h-4 text-gray-400 transition-transform ${showPattern ? 'rotate-90' : ''}`} />
              </button>
              <AnimatePresence>
                {showPattern && (
                  <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-3 mb-3">{t('absences.patternDesc')}</p>
                    <WeekdayToggle
                      weekdays={hoPattern?.weekdays || []}
                      onChange={handlePatternSave}
                      t={t}
                    />
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>

            {/* Import section */}
            <motion.div variants={itemVariants} className="card p-4 sm:p-6">
              <button
                onClick={() => setShowImport(!showImport)}
                className="flex items-center justify-between w-full text-left"
              >
                <h3 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <UploadSimple weight="bold" className="w-5 h-5 text-orange-600" />
                  {t('absences.importIcal')}
                </h3>
                <CaretRight weight="bold" className={`w-4 h-4 text-gray-400 transition-transform ${showImport ? 'rotate-90' : ''}`} />
              </button>
              <AnimatePresence>
                {showImport && (
                  <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                    <IcalImport
                      t={t}
                      onImported={(newEntries) => {
                        setEntries(prev => [...prev, ...newEntries].sort((a, b) => a.start_date.localeCompare(b.start_date)));
                      }}
                    />
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>

            {/* Upcoming entries */}
            <motion.div variants={itemVariants} className="card p-4 sm:p-6">
              <h3 className="text-base font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                <CalendarCheck weight="fill" className="w-5 h-5 text-emerald-600" />
                {t('absences.upcoming')}
              </h3>
              <div className="space-y-2 max-h-72 overflow-y-auto">
                {entries.filter(e => e.end_date >= todayStr).sort((a, b) => a.start_date.localeCompare(b.start_date)).map(entry => {
                  const cfg = ABSENCE_CONFIG[entry.absence_type as AbsenceType] || ABSENCE_CONFIG.other;
                  const Icon = cfg.icon;
                  return (
                    <div key={entry.id} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                      <div className="flex items-center gap-3 min-w-0">
                        <Icon weight="fill" className={`w-5 h-5 flex-shrink-0 ${cfg.color}`} />
                        <div className="min-w-0">
                          <span className="text-sm font-medium text-gray-900 dark:text-white block truncate">
                            {new Date(entry.start_date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                            {entry.start_date !== entry.end_date && <> – {new Date(entry.end_date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</>}
                          </span>
                          <span className={`text-xs ${cfg.color}`}>{t(`absences.types.${entry.absence_type}`)}</span>
                          {entry.note && <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{entry.note}</p>}
                        </div>
                      </div>
                      <button onClick={() => deleteEntry(entry.id)} className="btn btn-ghost btn-icon text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 min-w-[44px] min-h-[44px]">
                        <Trash weight="bold" className="w-4 h-4" />
                      </button>
                    </div>
                  );
                })}
                {entries.filter(e => e.end_date >= todayStr).length === 0 && (
                  <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-6">{t('absences.noEntries')}</p>
                )}
              </div>
            </motion.div>
          </div>
        </div>
      ) : (
        /* ── Team view ── */
        <TeamView teamEntries={teamEntries} todayStr={todayStr} t={t} />
      )}

      {/* ── FAB ── */}
      <motion.button
        whileHover={{ scale: 1.05 }}
        whileTap={{ scale: 0.95 }}
        onClick={() => setShowAddModal(true)}
        className="fixed bottom-20 right-4 md:bottom-8 md:right-8 z-20 w-14 h-14 bg-primary-600 hover:bg-primary-700 text-white rounded-full shadow-lg flex items-center justify-center"
        style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
      >
        <Plus weight="bold" className="w-6 h-6" />
      </motion.button>

      {/* ── Add Modal / Bottom Sheet ── */}
      <AnimatePresence>
        {showAddModal && (
          <AddAbsenceSheet
            onClose={() => setShowAddModal(false)}
            onAdd={handleAdd}
            t={t}
          />
        )}
      </AnimatePresence>
    </motion.div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// WEEKDAY TOGGLE
// ═══════════════════════════════════════════════════════════════════════════════

interface WeekdayToggleProps {
  weekdays: number[];
  onChange: (weekdays: number[]) => void;
  t: (key: string) => string;
}

function WeekdayToggle({ weekdays, onChange, t }: WeekdayToggleProps) {
  const DAYS = [
    t('homeoffice.weekdaysShort.mon'), t('homeoffice.weekdaysShort.tue'),
    t('homeoffice.weekdaysShort.wed'), t('homeoffice.weekdaysShort.thu'),
    t('homeoffice.weekdaysShort.fri'),
  ];

  function toggle(day: number) {
    const next = weekdays.includes(day) ? weekdays.filter(d => d !== day) : [...weekdays, day].sort();
    onChange(next);
  }

  return (
    <div className="grid grid-cols-5 gap-2">
      {DAYS.map((name, i) => {
        const active = weekdays.includes(i);
        return (
          <motion.button
            key={i}
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={() => toggle(i)}
            className={`flex flex-col items-center gap-1 py-3 px-2 rounded-xl border-2 transition-all font-medium min-h-[44px] ${
              active
                ? 'bg-primary-100 dark:bg-primary-900/40 border-primary-400 dark:border-primary-600 text-primary-700 dark:text-primary-300'
                : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
            }`}
          >
            <span className="text-sm">{name}</span>
            {active && <House weight="fill" className="w-4 h-4" />}
          </motion.button>
        );
      })}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// ICAL IMPORT
// ═══════════════════════════════════════════════════════════════════════════════

interface IcalImportProps {
  t: (key: string, params?: Record<string, string | number>) => string;
  onImported: (entries: AbsenceEntry[]) => void;
}

function IcalImport({ t, onImported }: IcalImportProps) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);

  async function handleFile(file: File) {
    try {
      const data = await api.importAbsenceIcal(file);
      if (data.success && data.data) {
        onImported(data.data);
        toast.success(t('absences.imported', { count: data.data.length }));
      } else {
        toast.error(data.error?.message || 'Import failed');
      }
    } catch {
      toast.error('Import failed');
    }
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    setDragOver(false);
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  }

  return (
    <div className="mt-3">
      <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">{t('absences.importDesc')}</p>
      <div
        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => fileRef.current?.click()}
        className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors min-h-[44px] ${
          dragOver ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400'
        }`}
      >
        <UploadSimple weight="bold" className="w-8 h-8 mx-auto mb-2 text-gray-400" />
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('absences.importDropzone')}</p>
      </div>
      <input ref={fileRef} type="file" accept=".ics,.ical" onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); if (fileRef.current) fileRef.current.value = ''; }} className="hidden" />
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADD ABSENCE BOTTOM SHEET / MODAL
// ═══════════════════════════════════════════════════════════════════════════════

interface AddAbsenceSheetProps {
  onClose: () => void;
  onAdd: (type: AbsenceType, startDate: string, endDate: string, note: string) => void;
  t: (key: string) => string;
}

function AddAbsenceSheet({ onClose, onAdd, t }: AddAbsenceSheetProps) {
  const [type, setType] = useState<AbsenceType>('homeoffice');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [note, setNote] = useState('');

  const today = useMemo(() => new Date(), []);
  const todayStr = today.toISOString().slice(0, 10);
  const monday = getMonday(today);
  const friday = new Date(monday);
  friday.setDate(monday.getDate() + 4);

  function setToday() { setStartDate(todayStr); setEndDate(todayStr); }
  function setThisWeek() { setStartDate(toDateStr(monday)); setEndDate(toDateStr(friday)); }

  return (
    <>
      {/* Backdrop */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        className="fixed inset-0 bg-black/40 z-50"
        onClick={onClose}
      />
      {/* Sheet */}
      <motion.div
        initial={{ y: '100%' }}
        animate={{ y: 0 }}
        exit={{ y: '100%' }}
        transition={{ type: 'spring', damping: 25, stiffness: 300 }}
        className="fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 rounded-t-2xl max-h-[90vh] overflow-y-auto sm:relative sm:max-w-lg sm:mx-auto sm:my-auto sm:rounded-2xl sm:fixed sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:bottom-auto"
        style={{ paddingBottom: 'env(safe-area-inset-bottom, 16px)' }}
      >
        {/* Handle */}
        <div className="w-8 h-1 bg-gray-300 dark:bg-gray-700 rounded-full mx-auto mt-3 sm:hidden" />
        <div className="p-5">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('absences.addAbsence')}</h2>
            <button onClick={onClose} className="btn btn-ghost btn-icon min-w-[44px] min-h-[44px]"><X weight="bold" className="w-5 h-5" /></button>
          </div>

          {/* Type selection */}
          <div className="grid grid-cols-3 sm:grid-cols-5 gap-2 mb-4">
            {(Object.entries(ABSENCE_CONFIG) as [AbsenceType, typeof ABSENCE_CONFIG.homeoffice][]).map(([at, cfg]) => {
              const Icon = cfg.icon;
              const active = type === at;
              return (
                <motion.button
                  key={at}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setType(at)}
                  className={`flex flex-col items-center gap-1 py-3 px-2 rounded-xl border-2 transition-all min-h-[44px] ${
                    active
                      ? `${cfg.bgLight} border-current ${cfg.color}`
                      : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-500'
                  }`}
                >
                  <Icon weight={active ? 'fill' : 'regular'} className="w-5 h-5" />
                  <span className="text-xs font-medium truncate w-full text-center">{t(`absences.types.${at}`)}</span>
                </motion.button>
              );
            })}
          </div>

          {/* Quick buttons */}
          <div className="flex gap-2 mb-4">
            <button onClick={setToday} className="btn btn-ghost text-sm flex-1 min-h-[44px]">{t('absences.quickToday')}</button>
            <button onClick={setThisWeek} className="btn btn-ghost text-sm flex-1 min-h-[44px]">{t('absences.quickWeek')}</button>
          </div>

          {/* Date range */}
          <div className="grid grid-cols-2 gap-3 mb-4">
            <div>
              <label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('absences.startDate')}</label>
              <input type="date" value={startDate} onChange={e => { setStartDate(e.target.value); if (!endDate || e.target.value > endDate) setEndDate(e.target.value); }} className="input w-full text-[16px]" />
            </div>
            <div>
              <label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('absences.endDate')}</label>
              <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="input w-full text-[16px]" min={startDate} />
            </div>
          </div>

          {/* Note */}
          <input
            type="text"
            placeholder={t('absences.notePlaceholder')}
            value={note}
            onChange={e => setNote(e.target.value)}
            className="input w-full mb-4 text-[16px]"
          />

          {/* Submit */}
          <button
            onClick={() => onAdd(type, startDate, endDate, note)}
            disabled={!startDate || !endDate}
            className="btn btn-primary w-full min-h-[44px]"
          >
            <Plus weight="bold" className="w-4 h-4" />
            {t('absences.addBtn')}
          </button>
        </div>
      </motion.div>
    </>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEAM VIEW
// ═══════════════════════════════════════════════════════════════════════════════

interface TeamViewProps {
  teamEntries: TeamAbsenceEntry[];
  todayStr: string;
  t: (key: string) => string;
}

function TeamView({ teamEntries, todayStr, t }: TeamViewProps) {
  const upcoming = teamEntries.filter(e => e.end_date >= todayStr).sort((a, b) => a.start_date.localeCompare(b.start_date));

  // Group by user
  const byUser: Record<string, TeamAbsenceEntry[]> = {};
  for (const e of upcoming) {
    if (!byUser[e.user_name]) byUser[e.user_name] = [];
    byUser[e.user_name].push(e);
  }

  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  return (
    <motion.div variants={{ hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.05 } } }} initial="hidden" animate="show" className="space-y-4">
      {Object.keys(byUser).length === 0 ? (
        <div className="card p-8 text-center">
          <UsersThree weight="light" className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
          <p className="text-gray-500 dark:text-gray-400">{t('absences.noTeamEntries')}</p>
        </div>
      ) : (
        Object.entries(byUser).map(([name, entries]) => (
          <motion.div key={name} variants={itemVariants} className="card p-4">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center text-sm font-bold text-primary-700 dark:text-primary-300">
                {name.charAt(0).toUpperCase()}
              </div>
              <span className="font-medium text-gray-900 dark:text-white">{name}</span>
            </div>
            <div className="space-y-2">
              {entries.map((entry, i) => {
                const cfg = ABSENCE_CONFIG[entry.absence_type as AbsenceType] || ABSENCE_CONFIG.other;
                return (
                  <div key={i} className={`flex items-center gap-2 px-3 py-2 rounded-lg ${cfg.bgLight}`}>
                    <div className={`w-2 h-2 rounded-full ${cfg.dot}`} />
                    <span className={`text-sm font-medium ${cfg.color}`}>{t(`absences.types.${entry.absence_type}`)}</span>
                    <span className="text-sm text-gray-600 dark:text-gray-400 ml-auto">
                      {new Date(entry.start_date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                      {entry.start_date !== entry.end_date && <> – {new Date(entry.end_date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</>}
                    </span>
                  </div>
                );
              })}
            </div>
          </motion.div>
        ))
      )}
    </motion.div>
  );
}
