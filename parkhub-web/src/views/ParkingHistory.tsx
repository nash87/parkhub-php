import { useEffect, useState, type ReactNode } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  ClockCounterClockwise,
  Star,
  Clock,
  TrendUp,
  CalendarBlank,
  FunnelSimple,
  CaretLeft,
  CaretRight,
  SpinnerGap,
  Coins,
} from '@phosphor-icons/react';
import { api, type Booking, type ParkingLot, type PersonalParkingStats } from '../api/client';
import { useTranslation } from 'react-i18next';
import { stagger, fadeUp } from '../constants/animations';
import { OnboardingHint } from '../components/OnboardingHint';
import { useTheme } from '../context/ThemeContext';

export function ParkingHistoryPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [stats, setStats] = useState<PersonalParkingStats | null>(null);
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [filterLot, setFilterLot] = useState('');
  const [filterFrom, setFilterFrom] = useState('');
  const [filterTo, setFilterTo] = useState('');

  useEffect(() => {
    api.getLots().then((res) => {
      if (res.success && res.data) setLots(res.data);
    });
    api.getBookingStats().then((res) => {
      if (res.success && res.data) setStats(res.data);
    });
  }, []);

  useEffect(() => {
    setLoading(true);
    api.getBookingHistory({
      lot_id: filterLot || undefined,
      from: filterFrom ? new Date(filterFrom).toISOString() : undefined,
      to: filterTo ? new Date(`${filterTo}T23:59:59`).toISOString() : undefined,
      page,
      per_page: 10,
    }).then((res) => {
      if (res.success && res.data) {
        setBookings(res.data.items);
        setTotalPages(res.data.total_pages);
        setTotal(res.data.total);
      }
    }).finally(() => setLoading(false));
  }, [page, filterLot, filterFrom, filterTo]);

  const dayNames: Record<string, string> = {
    Monday: t('history.monday'),
    Tuesday: t('history.tuesday'),
    Wednesday: t('history.wednesday'),
    Thursday: t('history.thursday'),
    Friday: t('history.friday'),
    Saturday: t('history.saturday'),
    Sunday: t('history.sunday'),
  };

  const maxTrend = stats?.monthly_trend ? Math.max(...stats.monthly_trend.map((month) => month.bookings), 1) : 1;
  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';
  const activeFilterCount = [filterLot, filterFrom, filterTo].filter(Boolean).length;
  const visibleSpend = bookings.reduce((sum, booking) => sum + (booking.total_price ?? 0), 0);
  const completedBookings = bookings.filter((booking) => booking.status === 'completed').length;
  const cancelledBookings = bookings.filter((booking) => booking.status === 'cancelled').length;

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key="history"
        variants={stagger}
        initial="hidden"
        animate="show"
        className="space-y-6"
        data-testid="history-shell"
        data-surface={surfaceVariant}
      >
        <motion.div
          variants={fadeUp}
          className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
            isVoid
              ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
              : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
          }`}
        >
          <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div>
              <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
                isVoid
                  ? 'bg-cyan-500/10 text-cyan-100'
                  : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
              }`}>
                <ClockCounterClockwise weight="fill" className="h-3.5 w-3.5" />
                {isVoid ? 'Void history ledger' : 'Marble history ledger'}
              </div>

              <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h1 className="text-3xl font-black tracking-[-0.04em]">{t('history.title')}</h1>
                  <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                    {t('history.subtitle')}
                  </p>
                </div>
                <OnboardingHint hintKey="history" text={t('history.help')} />
              </div>

              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <HeroStat
                  label={t('history.totalBookings')}
                  value={String(stats?.total_bookings ?? total)}
                  meta={t('history.monthlyTrend')}
                  icon={<CalendarBlank weight="fill" className="h-4 w-4" />}
                  isVoid={isVoid}
                  accent
                />
                <HeroStat
                  label={t('history.avgDuration')}
                  value={`${Math.round(stats?.avg_duration_minutes ?? 0)} min`}
                  meta={t('history.busiestDayDesc')}
                  icon={<Clock weight="fill" className="h-4 w-4" />}
                  isVoid={isVoid}
                />
                <HeroStat
                  label={t('history.creditsSpent')}
                  value={String(stats?.credits_spent ?? 0)}
                  meta={stats?.favorite_lot || '—'}
                  icon={<Coins weight="fill" className="h-4 w-4" />}
                  isVoid={isVoid}
                />
              </div>
            </div>

            <div className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-white/10 bg-white/[0.04]'
                : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
            }`}>
              <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                Active window
              </p>
              <div className="mt-4 space-y-3">
                <PanelMetric
                  label={t('history.favoriteLot')}
                  value={stats?.favorite_lot || '—'}
                  helper={t('history.filters')}
                  isVoid={isVoid}
                />
                <PanelMetric
                  label="Visible on page"
                  value={String(bookings.length)}
                  helper={`${activeFilterCount} active filters`}
                  isVoid={isVoid}
                />
                <PanelMetric
                  label="Visible spend"
                  value={visibleSpend ? `${visibleSpend}` : '—'}
                  helper={`${page} / ${Math.max(totalPages, 1)} pages`}
                  isVoid={isVoid}
                />
              </div>
            </div>
          </div>
        </motion.div>

        {stats && (
          <motion.div variants={fadeUp} className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label={t('history.totalBookings')} icon={<CalendarBlank weight="regular" className="h-4 w-4" />}>
              <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.total_bookings}</p>
            </StatCard>
            <StatCard label={t('history.favoriteLot')} icon={<Star weight="regular" className="h-4 w-4" />}>
              <p className="truncate text-lg font-bold text-surface-900 dark:text-white">{stats.favorite_lot || '—'}</p>
            </StatCard>
            <StatCard label={t('history.avgDuration')} icon={<Clock weight="regular" className="h-4 w-4" />}>
              <p className="text-2xl font-bold text-surface-900 dark:text-white">
                {Math.round(stats.avg_duration_minutes)}
                <span className="ml-1 text-sm font-normal text-surface-500 dark:text-surface-400">min</span>
              </p>
            </StatCard>
            <StatCard label={t('history.creditsSpent')} icon={<Coins weight="regular" className="h-4 w-4" />}>
              <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.credits_spent}</p>
            </StatCard>
          </motion.div>
        )}

        {stats && (
          <motion.div variants={fadeUp} className="grid grid-cols-1 gap-4 md:grid-cols-[1.1fr_0.9fr]">
            <div className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
              <div className="mb-4 flex items-center gap-2">
                <TrendUp weight="regular" className="h-4 w-4 text-primary-600 dark:text-primary-400" />
                <h2 className="text-sm font-semibold text-surface-900 dark:text-white">{t('history.monthlyTrend')}</h2>
              </div>
              <div className="flex h-32 items-end gap-2">
                {stats.monthly_trend.map((month) => (
                  <div key={month.month} className="flex flex-1 flex-col items-center gap-1">
                    <span className="text-xs font-medium text-surface-900 dark:text-white">{month.bookings}</span>
                    <div
                      className="w-full rounded-t bg-primary-500 transition-all dark:bg-primary-400"
                      style={{ height: `${Math.max((month.bookings / maxTrend) * 100, 4)}%` }}
                    />
                    <span className="text-[10px] text-surface-400 dark:text-surface-500">{month.month.slice(5)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-slate-800 bg-slate-950/85'
                : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
            }`}>
              <div className="mb-4 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <CalendarBlank weight="regular" className="h-4 w-4 text-primary-600 dark:text-primary-400" />
                  <h2 className="text-sm font-semibold text-surface-900 dark:text-white">{t('history.busiestDay')}</h2>
                </div>
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                  isVoid
                    ? 'bg-cyan-500/10 text-cyan-100'
                    : 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                }`}>
                  {completedBookings} completed
                </span>
              </div>

              <div className="flex h-32 items-center justify-center rounded-[20px] border border-dashed border-surface-200 dark:border-surface-800">
                <div className="text-center">
                  <p className="text-4xl font-bold text-primary-600 dark:text-primary-400">
                    {stats.busiest_day ? dayNames[stats.busiest_day] || stats.busiest_day : '—'}
                  </p>
                  <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">{t('history.busiestDayDesc')}</p>
                </div>
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <PanelMetric label="Completed" value={String(completedBookings)} helper="Current page" isVoid={isVoid} compact />
                <PanelMetric label="Cancelled" value={String(cancelledBookings)} helper="Current page" isVoid={isVoid} compact />
              </div>
            </div>
          </motion.div>
        )}

        <motion.div variants={fadeUp} className="rounded-[24px] border border-surface-200 bg-white p-4 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="mb-3 flex items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <FunnelSimple weight="regular" className="h-4 w-4 text-surface-500" />
              <span className="text-sm font-medium text-surface-700 dark:text-surface-300">{t('history.filters')}</span>
            </div>
            <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
              {activeFilterCount} active
            </span>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label className="space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500">{t('history.filterLot')}</span>
              <select
                value={filterLot}
                onChange={(event) => {
                  setFilterLot(event.target.value);
                  setPage(1);
                }}
                className="input text-sm"
                aria-label={t('history.filterLot')}
              >
                <option value="">{t('history.allLots')}</option>
                {lots.map((lot) => (
                  <option key={lot.id} value={lot.id}>{lot.name}</option>
                ))}
              </select>
            </label>

            <label className="space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500">{t('history.dateFrom')}</span>
              <input
                type="date"
                value={filterFrom}
                onChange={(event) => {
                  setFilterFrom(event.target.value);
                  setPage(1);
                }}
                className="input text-sm"
                aria-label={t('history.dateFrom')}
              />
            </label>

            <label className="space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500">{t('history.dateTo')}</span>
              <input
                type="date"
                value={filterTo}
                onChange={(event) => {
                  setFilterTo(event.target.value);
                  setPage(1);
                }}
                className="input text-sm"
                aria-label={t('history.dateTo')}
              />
            </label>
          </div>
        </motion.div>

        <motion.div variants={fadeUp} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">Timeline</p>
              <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{t('history.title')}</h2>
            </div>
            <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
              {total} total
            </span>
          </div>

          {loading ? (
            <div className="flex items-center justify-center py-16">
              <SpinnerGap weight="bold" className="h-8 w-8 animate-spin text-primary-500" />
            </div>
          ) : bookings.length === 0 ? (
            <div className="rounded-[20px] border border-dashed border-surface-200 p-16 text-center dark:border-surface-800">
              <ClockCounterClockwise weight="light" className="mx-auto h-20 w-20 text-surface-200 dark:text-surface-700" />
              <p className="mt-4 text-surface-500 dark:text-surface-400">{t('history.noHistory')}</p>
            </div>
          ) : (
            <div className="space-y-3">
              {bookings.map((booking) => (
                <div
                  key={booking.id}
                  className="flex items-center gap-4 rounded-[22px] border border-surface-200/80 bg-surface-50/70 p-4 transition-colors hover:border-primary-300 hover:bg-white dark:border-surface-800 dark:bg-surface-900/80 dark:hover:border-primary-800 dark:hover:bg-surface-900"
                >
                  <div className={`h-2 w-2 flex-shrink-0 rounded-full ${
                    booking.status === 'completed' ? 'bg-green-500' : booking.status === 'cancelled' ? 'bg-red-500' : 'bg-surface-400'
                  }`} />

                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="truncate font-medium text-surface-900 dark:text-white">{booking.lot_name}</p>
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                        booking.status === 'completed'
                          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                          : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                      }`}>
                        {t(`history.status.${booking.status}`)}
                      </span>
                    </div>
                    <p className="mt-0.5 text-sm text-surface-500 dark:text-surface-400">
                      {t('history.slot')} {booking.slot_number} &middot; {new Date(booking.start_time).toLocaleDateString()} {new Date(booking.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} — {new Date(booking.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </p>
                  </div>

                  {booking.total_price != null && (
                    <div className="flex-shrink-0 text-right">
                      <p className="font-semibold text-surface-900 dark:text-white">{booking.total_price} {booking.currency || 'EUR'}</p>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </motion.div>

        {totalPages > 1 && (
          <motion.div variants={fadeUp} className="flex items-center justify-between rounded-[20px] border border-surface-200 bg-white px-4 py-3 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
            <span className="text-sm text-surface-500 dark:text-surface-400">
              {t('history.showing', { from: (page - 1) * 10 + 1, to: Math.min(page * 10, total), total })}
            </span>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={page <= 1}
                className="rounded-lg border border-surface-200 p-2 transition-colors hover:bg-surface-50 disabled:opacity-40 dark:border-surface-700 dark:hover:bg-surface-800"
                aria-label={t('history.prevPage')}
              >
                <CaretLeft weight="bold" className="h-4 w-4" />
              </button>
              <span className="px-2 text-sm font-medium text-surface-900 dark:text-white">
                {page} / {totalPages}
              </span>
              <button
                onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
                disabled={page >= totalPages}
                className="rounded-lg border border-surface-200 p-2 transition-colors hover:bg-surface-50 disabled:opacity-40 dark:border-surface-700 dark:hover:bg-surface-800"
                aria-label={t('history.nextPage')}
              >
                <CaretRight weight="bold" className="h-4 w-4" />
              </button>
            </div>
          </motion.div>
        )}
      </motion.div>
    </AnimatePresence>
  );
}

function HeroStat({
  label,
  value,
  meta,
  icon,
  isVoid,
  accent = false,
}: {
  label: string;
  value: string;
  meta: string;
  icon: ReactNode;
  isVoid: boolean;
  accent?: boolean;
}) {
  return (
    <div className={`rounded-[22px] border px-4 py-4 ${
      isVoid
        ? accent
          ? 'border-cyan-500/20 bg-cyan-500/10'
          : 'border-white/10 bg-white/[0.04]'
        : accent
        ? 'border-emerald-200 bg-emerald-500/10 dark:border-emerald-900/60'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${
            isVoid
              ? accent ? 'text-cyan-100' : 'text-white/45'
              : accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45'
          }`}>
            {label}
          </p>
          <p className="mt-3 text-2xl font-semibold tracking-[-0.03em]">{value}</p>
          <p className={`mt-2 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
        </div>
        <div className={`rounded-2xl p-2 ${isVoid ? 'bg-white/[0.06] text-cyan-100' : 'bg-white text-emerald-700 dark:bg-white/10 dark:text-emerald-300'}`}>
          {icon}
        </div>
      </div>
    </div>
  );
}

function PanelMetric({
  label,
  value,
  helper,
  isVoid,
  compact = false,
}: {
  label: string;
  value: string;
  helper: string;
  isVoid: boolean;
  compact?: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 ${compact ? 'py-3' : 'py-4'} ${
      isVoid
        ? 'border-white/10 bg-white/[0.03]'
        : 'border-surface-200 bg-white/80 dark:border-surface-800 dark:bg-surface-900/70'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-400 dark:text-surface-500'}`}>{label}</p>
      <p className="mt-2 text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{helper}</p>
    </div>
  );
}

function StatCard({
  label,
  icon,
  children,
}: {
  label: string;
  icon: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="rounded-[22px] border border-surface-200 bg-white p-4 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
      <div className="mb-1 flex items-center gap-2 text-surface-500 dark:text-surface-400">
        {icon}
        <span className="text-xs font-medium">{label}</span>
      </div>
      {children}
    </div>
  );
}
