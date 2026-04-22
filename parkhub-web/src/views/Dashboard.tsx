import { useEffect, useState, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import {
  CalendarCheck, Car, Coins, CalendarPlus, ArrowRight,
  MapPin, ChartLine, Gauge, Leaf,
} from '@phosphor-icons/react';
import { useAuth } from '../context/AuthContext';
import { api, type Booking, type Co2Summary, type UserStats } from '../api/client';
import { DashboardSkeleton } from '../components/Skeleton';
import { staggerSlow, fadeUp } from '../constants/animations';
import { useWebSocket, type WsEvent } from '../hooks/useWebSocket';
import {
  KpiCard,
  TrendCard,
  SensorFeedCard,
  RecentActivityCard,
  type ActivityRow,
  type SensorEntry,
} from '../components/KineticObservatory';
import { useNavLayout } from '../hooks/useNavLayout';
import { useTheme } from '../context/ThemeContext';

interface SurfaceLot {
  label: string;
  occupied: number;
  total: number;
  available: number;
  occupancy: number;
}

interface SurfaceFeedEntry {
  id: string;
  title: string;
  meta: string;
  tone: 'live' | 'warning' | 'neutral';
}

export function DashboardPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [navLayout] = useNavLayout();
  const { designTheme } = useTheme();
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [co2, setCo2] = useState<Co2Summary | null>(null);
  const [loading, setLoading] = useState(true);

  const handleWsEvent = useCallback((event: WsEvent) => {
    switch (event.event) {
      case 'booking_created':
        toast.success(t('dashboard.wsBookingCreated', 'New booking created'));
        break;
      case 'booking_cancelled':
        toast(t('dashboard.wsBookingCancelled', 'A booking was cancelled'), { icon: '📋' });
        break;
      case 'occupancy_changed':
        break; // Occupancy updates handled via hook state
    }
  }, [t]);

  const { connected: wsConnected, occupancy } = useWebSocket({ onEvent: handleWsEvent });

  useEffect(() => {
    // CO2 summary is best-effort — the tile renders '—' if the endpoint
    // errors or is disabled so the whole dashboard doesn't block on it.
    Promise.all([
      api.getBookings(),
      api.getUserStats(),
      api.getCo2Summary().catch(() => null),
    ]).then(([bRes, sRes, cRes]) => {
      if (bRes.success && bRes.data) setBookings(bRes.data);
      if (sRes.success && sRes.data) setStats(sRes.data);
      if (cRes && cRes.success && cRes.data) setCo2(cRes.data);
    }).finally(() => setLoading(false));
  }, []);

  const hour = new Date().getHours();
  const timeOfDay = hour < 12 ? t('dashboard.morning') : hour < 18 ? t('dashboard.afternoon') : t('dashboard.evening');
  const activeBookings = bookings.filter(b => b.status === 'active' || b.status === 'confirmed');
  const name = user?.name?.split(' ')[0] || user?.username || '';

  // Trend period selector
  const [trendPeriod, setTrendPeriod] = useState<'7d' | '30d'>('7d');

  // Build activity chart from real booking data
  const activityTrend = useMemo(() => {
    const daysBack = trendPeriod === '7d' ? 7 : 30;
    const days: { label: string; value: number }[] = [];
    const now = new Date();
    for (let i = daysBack - 1; i >= 0; i--) {
      const d = new Date(now);
      d.setDate(d.getDate() - i);
      const dayStr = d.toLocaleDateString(undefined, { weekday: 'short' });
      const dateKey = d.toISOString().slice(0, 10);
      const count = bookings.filter(b => b.start_time.slice(0, 10) === dateKey).length;
      days.push({ label: dayStr, value: count });
    }
    return days;
  }, [bookings, trendPeriod]);

  // Recent activity rows (derived from bookings)
  const recentActivity = useMemo<ActivityRow[]>(() => {
    const mapStatus = (s: string): ActivityRow['status'] => {
      if (s === 'active') return 'in_progress';
      if (s === 'confirmed') return 'confirmed';
      if (s === 'completed') return 'completed';
      if (s === 'cancelled') return 'cancelled';
      return 'pending';
    };
    return bookings
      .slice(0, 5)
      .map((b) => {
        const start = new Date(b.start_time);
        const end = new Date(b.end_time);
        const durationMs = end.getTime() - start.getTime();
        const hours = Math.floor(durationMs / 3600000);
        const minutes = Math.floor((durationMs % 3600000) / 60000);
        return {
          id: b.id,
          vehicle: b.vehicle_plate || t('dashboard.unknownVehicle', 'Unknown vehicle'),
          owner: b.lot_name,
          slot: b.slot_number,
          checkInTime: start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }),
          duration: `${hours}h ${minutes}m`,
          status: mapStatus(b.status),
        };
      });
  }, [bookings, t]);

  const surfaceLots = useMemo<SurfaceLot[]>(() => {
    const fromSocket = Object.entries(occupancy).map(([lotId, value], index) => {
      const matched = bookings.find((booking) => booking.lot_id === lotId || booking.lot_name === lotId);
      const total = Math.max(value.total || 0, 1);
      const available = Math.max(0, value.available || 0);
      const occupied = Math.max(0, total - available);
      return {
        label: matched?.lot_name || `Zone ${index + 1}`,
        occupied,
        total,
        available,
        occupancy: occupied / total,
      };
    });

    if (fromSocket.length > 0) return fromSocket.slice(0, 4);

    const grouped = new Map<string, number>();
    for (const booking of activeBookings) {
      const key = booking.lot_name || 'Main Campus';
      grouped.set(key, (grouped.get(key) || 0) + 1);
    }

    const fallback = Array.from(grouped.entries()).map(([label, occupied]) => ({
      label,
      occupied,
      total: Math.max(occupied + 6, 10),
      available: Math.max(0, Math.max(occupied + 6, 10) - occupied),
      occupancy: occupied / Math.max(occupied + 6, 10),
    }));

    if (fallback.length > 0) return fallback.slice(0, 4);

    return [
      { label: 'HQ West', occupied: 8, total: 12, available: 4, occupancy: 8 / 12 },
      { label: 'Garage North', occupied: 5, total: 10, available: 5, occupancy: 0.5 },
      { label: 'Visitor Deck', occupied: 3, total: 8, available: 5, occupancy: 0.375 },
    ];
  }, [occupancy, bookings, activeBookings]);

  const activeBooking = activeBookings[0] ?? null;

  const surfaceFeed = useMemo<SurfaceFeedEntry[]>(() => {
    const entries: SurfaceFeedEntry[] = [];

    if (activeBooking) {
      entries.push({
        id: `active-${activeBooking.id}`,
        title: `${activeBooking.lot_name} live pass active`,
        meta: `Slot ${activeBooking.slot_number} · ${activeBooking.vehicle_plate || 'assigned vehicle'}`,
        tone: 'live',
      });
    }

    if (recentActivity[0]) {
      entries.push({
        id: `recent-${recentActivity[0].id}`,
        title: recentActivity[0].vehicle,
        meta: `${recentActivity[0].owner} · ${recentActivity[0].duration}`,
        tone: recentActivity[0].status === 'cancelled' ? 'warning' : 'neutral',
      });
    }

    if (surfaceLots[0]) {
      entries.push({
        id: `lot-${surfaceLots[0].label}`,
        title: `${surfaceLots[0].label} occupancy`,
        meta: `${Math.round(surfaceLots[0].occupancy * 100)}% occupied · ${surfaceLots[0].available} free`,
        tone: surfaceLots[0].occupancy > 0.8 ? 'warning' : 'live',
      });
    }

    return entries.slice(0, 3);
  }, [activeBooking, recentActivity, surfaceLots]);

  const nextBooking = useMemo(() => {
    const now = Date.now();
    return bookings
      .filter((booking) => new Date(booking.start_time).getTime() > now)
      .sort((a, b) => new Date(a.start_time).getTime() - new Date(b.start_time).getTime())[0] ?? null;
  }, [bookings]);

  const liveClock = new Date().toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });

  const waitlistEstimate = Math.max(4, Math.round(surfaceLots.reduce((sum, lot) => sum + lot.occupancy, 0) / Math.max(surfaceLots.length, 1) * 18));
  const evAvailable = Math.max(1, surfaceLots.reduce((sum, lot) => sum + Math.max(1, Math.round(lot.available / 2)), 0));
  const headlineAnnouncement = activeBooking
    ? `Live now: ${activeBooking.lot_name} pass active on slot ${activeBooking.slot_number}`
    : 'New design preview: EV fast-charging and live occupancy are now front and center';

  // Live sensor feed — derived from lots (placeholder until a real sensor API exists)
  const sensorFeed = useMemo<SensorEntry[]>(() => {
    const lots = Array.from(new Set(bookings.map((b) => b.lot_name).filter(Boolean))).slice(0, 4);
    if (lots.length === 0) {
      return [
        { name: t('dashboard.entranceGateA', 'Entrance Gate A'), status: 'active' },
        { name: t('dashboard.entranceGateB', 'Entrance Gate B'), status: 'active' },
        { name: t('dashboard.exitGate', 'Exit Gate'), status: 'active' },
      ];
    }
    return lots.map((lot, idx) => ({
      name: lot,
      status: idx === lots.length - 1 && lots.length > 2 ? 'maintenance' : 'active',
    } as SensorEntry));
  }, [bookings, t]);

  // KPI derived values
  const totalBookings = stats?.total_bookings ?? bookings.length;
  const bookingsThisMonth = stats?.bookings_this_month ?? 0;
  const creditsLeft = user?.credits_balance ?? 0;
  // Delta: month-over-month estimate from current snapshot (simplified)
  const monthDelta = bookingsThisMonth > 0 ? Math.round((bookingsThisMonth / Math.max(totalBookings, 1)) * 100) : 0;

  const container = staggerSlow;
  const item = fadeUp;

  // Time-based gradient accent for greeting
  const greetingGradient = hour < 12
    ? 'from-amber-400/10 via-orange-400/5 to-transparent dark:from-amber-500/8 dark:via-orange-500/3'
    : hour < 18
    ? 'from-sky-400/10 via-blue-400/5 to-transparent dark:from-sky-500/8 dark:via-blue-500/3'
    : 'from-indigo-400/10 via-purple-400/5 to-transparent dark:from-indigo-500/8 dark:via-purple-500/3';

  if (loading) return <div role="status" aria-label={t('dashboard.loadingDashboard')}><DashboardSkeleton /></div>;

  const surfaceVariant = designTheme === 'void' || navLayout === 'focus'
    ? 'void'
    : designTheme === 'marble' || navLayout === 'dock'
    ? 'marble'
    : null;

  return (
    <AnimatePresence mode="wait">
    <motion.div key="dashboard-loaded" variants={container} initial="hidden" animate="show" className="space-y-6">
      {/* Greeting with time-based gradient accent */}
      <motion.div
        variants={item}
        className={`relative overflow-hidden rounded-2xl px-6 py-5 bg-gradient-to-r ${greetingGradient}`}
      >
        <div className="flex items-center gap-3">
          <h1 className="text-2xl sm:text-3xl font-bold text-surface-900 dark:text-white tracking-tight" style={{ letterSpacing: '-0.025em' }}>
            {t('dashboard.greeting', { timeOfDay, name })}
          </h1>
          {wsConnected && (
            <span
              className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
              title={t('dashboard.wsConnected', 'Live updates active')}
              data-testid="ws-connected-indicator"
            >
              <span className="w-2 h-2 rounded-full bg-emerald-500 pulse-dot" />
              {t('dashboard.live', 'Live')}
            </span>
          )}
        </div>
      </motion.div>

      {surfaceVariant === 'marble' && (
        <motion.div variants={item}>
          <MarbleDashboardSurface
            clock={liveClock}
            creditsLeft={creditsLeft}
            activeCount={activeBookings.length}
            bookingsThisMonth={bookingsThisMonth}
            co2Saved={co2?.saved_kg ?? 0}
            announcement={headlineAnnouncement}
            activeBooking={activeBooking}
            nextBooking={nextBooking}
            lots={surfaceLots}
            feed={surfaceFeed}
            evAvailable={evAvailable}
            waitlistEstimate={waitlistEstimate}
          />
        </motion.div>
      )}

      {surfaceVariant === 'void' && (
        <motion.div variants={item}>
          <VoidDashboardSurface
            clock={liveClock}
            creditsLeft={creditsLeft}
            activeCount={activeBookings.length}
            announcement={headlineAnnouncement}
            activeBooking={activeBooking}
            lots={surfaceLots}
            feed={surfaceFeed}
            evAvailable={evAvailable}
            waitlistEstimate={waitlistEstimate}
          />
        </motion.div>
      )}

      {/* KPI Row — Kinetic Observatory style with delta badges */}
      <motion.div
        variants={item}
        className="grid grid-cols-2 lg:grid-cols-5 gap-3"
        role="region"
        aria-label={t('dashboard.statistics')}
      >
        <KpiCard
          label={t('dashboard.activeBookings')}
          value={activeBookings.length}
          icon={<ChartLine weight="bold" />}
          live={activeBookings.length > 0}
          data-testid="kpi-active-bookings"
        />
        <KpiCard
          label={t('dashboard.creditsLeft')}
          value={creditsLeft}
          icon={<Coins weight="bold" />}
          data-testid="kpi-credits"
        />
        <KpiCard
          label={t('dashboard.thisMonth')}
          value={bookingsThisMonth}
          icon={<CalendarCheck weight="bold" />}
          delta={monthDelta > 0 ? { value: monthDelta, suffix: '%' } : undefined}
          data-testid="kpi-this-month"
        />
        <KpiCard
          label={t('dashboard.totalBookings', 'Total Bookings')}
          value={totalBookings}
          icon={<Gauge weight="bold" />}
          data-testid="kpi-total"
        />
        <KpiCard
          label={t('dashboard.co2Saved', 'CO₂ Saved (30d)')}
          value={co2 ? `${co2.saved_kg.toFixed(1)} kg` : '—'}
          icon={<Leaf weight="bold" />}
          data-testid="kpi-co2-saved"
        />
      </motion.div>

      {/* Trend chart + Live sensor feed */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2">
          <TrendCard
            title={t('dashboard.weeklyActivityTitle', 'Weekly Activity')}
            subtitle={t('dashboard.weeklyActivitySubtitle', 'Booking volume over the selected period')}
            points={activityTrend.map((d) => d.value)}
            labels={activityTrend.map((d) => d.label).filter((_, i, arr) => arr.length <= 7 || i % Math.ceil(arr.length / 7) === 0).slice(0, 7)}
            periods={[
              { key: '7d', label: t('dashboard.period7d', '7 Days') },
              { key: '30d', label: t('dashboard.period30d', '30 Days') },
            ]}
            activePeriod={trendPeriod}
            onPeriodChange={(k) => setTrendPeriod(k as '7d' | '30d')}
          />
        </div>
        <SensorFeedCard
          title={t('dashboard.liveSensorFeed', 'Live Sensor Feed')}
          subtitle={t('dashboard.sensorFeedSubtitle', 'Real-time gate and entry status')}
          sensors={sensorFeed}
        />
      </motion.div>

      {/* Active bookings list + Quick actions */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 card p-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <h2 className="text-lg font-semibold text-surface-900 dark:text-white" style={{ letterSpacing: '-0.02em' }}>
                {t('dashboard.activeBookings')}
              </h2>
              {activeBookings.length > 0 && (
                <span className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-dot" />
                  {activeBookings.length}
                </span>
              )}
            </div>
            <Link to="/bookings" className="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium flex items-center gap-1 transition-colors">
              {t('nav.bookings')} <ArrowRight weight="bold" className="w-3.5 h-3.5" />
            </Link>
          </div>

          {activeBookings.length === 0 ? (
            <div className="py-10 px-4 flex flex-col items-center text-center">
              <div
                className="w-16 h-16 rounded-full bg-primary-500/10 dark:bg-primary-500/10 ring-1 ring-primary-500/20 flex items-center justify-center mb-4"
                aria-hidden="true"
              >
                <CalendarPlus weight="duotone" className="w-8 h-8 text-primary-600 dark:text-primary-400" />
              </div>
              <h3 className="text-base font-semibold text-surface-900 dark:text-white mb-1" style={{ letterSpacing: '-0.01em' }}>
                {t('dashboard.emptyBookingsTitle')}
              </h3>
              <p className="text-sm text-surface-500 dark:text-surface-400 max-w-xs mb-5 leading-relaxed">
                {t('dashboard.emptyBookingsSubtitle')}
              </p>
              <div className="flex flex-wrap items-center justify-center gap-2">
                <Link to="/book" className="btn btn-primary">{t('dashboard.emptyBookingsPrimary')}</Link>
                <button
                  type="button"
                  onClick={() => {
                    window.dispatchEvent(new CustomEvent('parkhub:open-command-palette'));
                  }}
                  className="btn btn-ghost text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white"
                >
                  {t('dashboard.emptyBookingsSecondary')}
                </button>
              </div>
            </div>
          ) : (
            <div className="space-y-2">
              {activeBookings.slice(0, 5).map((b, i) => (
                <motion.div
                  key={b.id}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.05, type: 'spring', stiffness: 300, damping: 24 }}
                  className="flex items-center gap-4 p-3 rounded-xl bg-surface-50/60 dark:bg-surface-800/40 transition-all hover:bg-surface-100/80 dark:hover:bg-surface-700/40 group"
                >
                  <div className="w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400">
                    <span className="text-sm font-bold" style={{ fontVariantNumeric: 'tabular-nums' }}>
                      {b.slot_number}
                    </span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-surface-900 dark:text-white truncate">{b.lot_name}</p>
                    <div className="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                      <MapPin weight="regular" className="w-3.5 h-3.5" />
                      {t('dashboard.slot')} {b.slot_number}
                      {b.vehicle_plate && <><span className="mx-1">·</span><Car weight="regular" className="w-3.5 h-3.5" />{b.vehicle_plate}</>}
                    </div>
                  </div>
                  <div className="text-right">
                    <span className="badge badge-success">{t('bookings.statusActive')}</span>
                  </div>
                </motion.div>
              ))}
            </div>
          )}
        </div>

        <div className="card p-6">
          <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-4" style={{ letterSpacing: '-0.02em' }}>
            {t('dashboard.quickActions')}
          </h2>
          <div className="space-y-2">
            {[
              { to: '/book', icon: CalendarPlus, label: t('dashboard.bookSpot'), accent: true },
              { to: '/vehicles', icon: Car, label: t('dashboard.myVehicles') },
              { to: '/bookings', icon: CalendarCheck, label: t('dashboard.viewBookings') },
              { to: '/credits', icon: Coins, label: t('nav.credits') },
            ].map((action) => (
              <Link
                key={action.to}
                to={action.to}
                className={`flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl transition-all group
                  ${action.accent
                    ? 'text-primary-700 dark:text-primary-300 bg-primary-500/10 hover:bg-primary-500/15'
                    : 'text-surface-700 dark:text-surface-300 bg-surface-500/5 hover:bg-surface-500/10'
                  }`}
              >
                <action.icon weight="regular" className={`w-4 h-4 transition-transform group-hover:scale-110 ${action.accent ? 'text-primary-500' : 'text-surface-400 dark:text-surface-500'}`} />
                <span className="flex-1">{action.label}</span>
                <ArrowRight weight="bold" className="w-3.5 h-3.5 text-surface-400 opacity-0 group-hover:opacity-100 transition-opacity" />
              </Link>
            ))}
          </div>
        </div>
      </motion.div>

      {/* Recent Activity table (Kinetic Observatory pattern) */}
      <motion.div variants={item}>
        <RecentActivityCard
          title={t('dashboard.recentActivity', 'Recent Activity')}
          rows={recentActivity}
          viewAllHref="/bookings"
          emptyText={t('dashboard.noActivity', 'No recent activity yet')}
          columnLabels={{
            vehicle: t('dashboard.colVehicleOwner', 'Vehicle / Location'),
            slot: t('dashboard.colSlot', 'Slot No.'),
            checkIn: t('dashboard.colCheckIn', 'Check-In Time'),
            duration: t('dashboard.colDuration', 'Duration'),
            status: t('dashboard.colStatus', 'Status'),
          }}
        />
      </motion.div>
    </motion.div>
    </AnimatePresence>
  );
}

function MarbleDashboardSurface({
  clock,
  creditsLeft,
  activeCount,
  bookingsThisMonth,
  co2Saved,
  announcement,
  activeBooking,
  nextBooking,
  lots,
  feed,
  evAvailable,
  waitlistEstimate,
}: {
  clock: string;
  creditsLeft: number;
  activeCount: number;
  bookingsThisMonth: number;
  co2Saved: number;
  announcement: string;
  activeBooking: Booking | null;
  nextBooking: Booking | null;
  lots: SurfaceLot[];
  feed: SurfaceFeedEntry[];
  evAvailable: number;
  waitlistEstimate: number;
}) {
  return (
    <section data-testid="marble-surface" className="overflow-hidden rounded-[28px] border border-surface-200/70 bg-white/90 shadow-xl shadow-surface-900/5 dark:border-surface-800 dark:bg-surface-900/80">
      <div className="flex items-center justify-between gap-3 border-b border-emerald-500/20 bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-white">
        <div className="flex items-center gap-2 text-sm font-medium">
          <span className="inline-block h-2 w-2 rounded-full bg-white/90" />
          {announcement}
        </div>
        <span className="text-xs font-semibold uppercase tracking-[0.18em]">{clock}</span>
      </div>

      <div className="grid gap-4 p-5 lg:grid-cols-[1.5fr_1fr]">
        <div className="grid gap-4 md:grid-cols-2">
          <article className="rounded-[24px] border border-surface-200 bg-gradient-to-br from-surface-50 to-white p-5 dark:border-surface-800 dark:from-surface-900 dark:to-surface-950">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Marble Surface</p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-surface-900 dark:text-white">Today at a glance</h2>
              </div>
              <span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">{creditsLeft} credits</span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <MarbleStat label="Active" value={String(activeCount)} />
              <MarbleStat label="This month" value={String(bookingsThisMonth)} />
              <MarbleStat label="CO₂ saved" value={`${co2Saved.toFixed(1)}kg`} />
            </div>
            <div className="mt-4 rounded-2xl bg-surface-900 px-4 py-3 text-white dark:bg-surface-950">
              <div className="flex items-center justify-between text-xs uppercase tracking-[0.18em] text-surface-400">
                <span>Live pass</span>
                <span>{clock}</span>
              </div>
              <div className="mt-3 flex items-end justify-between gap-4">
                <div>
                  <p className="text-lg font-semibold">{activeBooking?.lot_name || 'No active booking'}</p>
                  <p className="text-sm text-surface-400">
                    {activeBooking ? `Slot ${activeBooking.slot_number}` : 'Book a spot to activate your pass'}
                  </p>
                </div>
                <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">
                  {activeBooking?.vehicle_plate || 'Standby'}
                </span>
              </div>
            </div>
          </article>

          <article className="rounded-[24px] border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-950">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">EV charging</p>
                <h3 className="mt-1 text-lg font-semibold text-surface-900 dark:text-white">Fast chargers available</h3>
              </div>
              <span className="rounded-full bg-violet-500/10 px-3 py-1 text-xs font-semibold text-violet-700 dark:text-violet-300">{evAvailable} free</span>
            </div>
            <div className="space-y-2">
              {[0, 1, 2].map((index) => (
                <div key={index} className="flex items-center justify-between rounded-2xl bg-surface-100 px-3 py-2.5 dark:bg-surface-900">
                  <div>
                    <p className="text-sm font-medium text-surface-900 dark:text-white">EV Bay {index + 1}</p>
                    <p className="text-xs text-surface-500 dark:text-surface-400">150 kW CCS · near entrance</p>
                  </div>
                  <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${index < evAvailable ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-surface-200 text-surface-500 dark:bg-surface-800 dark:text-surface-400'}`}>
                    {index < evAvailable ? 'Available' : 'Busy'}
                  </span>
                </div>
              ))}
            </div>
          </article>

          <article className="rounded-[24px] border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-950">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Zones</p>
                <h3 className="mt-1 text-lg font-semibold text-surface-900 dark:text-white">Occupancy by area</h3>
              </div>
              <span className="text-sm font-semibold text-surface-500 dark:text-surface-400">{lots.length} zones</span>
            </div>
            <div className="space-y-3">
              {lots.map((lot) => (
                <div key={lot.label}>
                  <div className="mb-1.5 flex items-center justify-between text-sm">
                    <span className="font-medium text-surface-900 dark:text-white">{lot.label}</span>
                    <span className="text-surface-500 dark:text-surface-400">{lot.available} free</span>
                  </div>
                  <div className="h-2 rounded-full bg-surface-100 dark:bg-surface-800">
                    <div className={`h-2 rounded-full ${lot.occupancy > 0.8 ? 'bg-rose-500' : lot.occupancy > 0.6 ? 'bg-amber-500' : 'bg-emerald-500'}`} style={{ width: `${Math.max(8, Math.round(lot.occupancy * 100))}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </article>

          <article className="rounded-[24px] border border-surface-200 bg-gradient-to-br from-surface-900 to-surface-800 p-5 text-white dark:border-surface-700">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-400">Waitlist</p>
                <h3 className="mt-1 text-lg font-semibold">Predictive queue</h3>
              </div>
              <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{waitlistEstimate} min</span>
            </div>
            <p className="text-sm text-surface-300">Demand is clustering around the next two morning peaks. Opening the waitlist now will smooth the 09:00 rush.</p>
            <div className="mt-4 flex items-center justify-between rounded-2xl bg-white/5 px-4 py-3">
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-surface-400">Up next</p>
                <p className="mt-1 text-sm font-medium">{nextBooking?.lot_name || 'No queued booking yet'}</p>
              </div>
              <span className="text-sm text-surface-300">{nextBooking ? new Date(nextBooking.start_time).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : 'Standby'}</span>
            </div>
          </article>
        </div>

        <article className="rounded-[24px] border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-950">
          <div className="mb-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Live feed</p>
              <h3 className="mt-1 text-lg font-semibold text-surface-900 dark:text-white">Operational stream</h3>
            </div>
            <span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Live</span>
          </div>
          <div className="space-y-3">
            {feed.map((entry) => (
              <div key={entry.id} className="rounded-2xl border border-surface-200 px-4 py-3 dark:border-surface-800">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-medium text-surface-900 dark:text-white">{entry.title}</p>
                  <span className={`inline-block h-2.5 w-2.5 rounded-full ${entry.tone === 'warning' ? 'bg-amber-500' : entry.tone === 'live' ? 'bg-emerald-500' : 'bg-surface-300 dark:bg-surface-600'}`} />
                </div>
                <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{entry.meta}</p>
              </div>
            ))}
          </div>
        </article>
      </div>
    </section>
  );
}

function MarbleStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-surface-100 px-3 py-3 dark:bg-surface-800">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">{label}</p>
      <p className="mt-2 text-xl font-bold tracking-tight text-surface-900 dark:text-white">{value}</p>
    </div>
  );
}

function VoidDashboardSurface({
  clock,
  creditsLeft,
  activeCount,
  announcement,
  activeBooking,
  lots,
  feed,
  evAvailable,
  waitlistEstimate,
}: {
  clock: string;
  creditsLeft: number;
  activeCount: number;
  announcement: string;
  activeBooking: Booking | null;
  lots: SurfaceLot[];
  feed: SurfaceFeedEntry[];
  evAvailable: number;
  waitlistEstimate: number;
}) {
  const ticker = `PARKHUB LIVE · ${creditsLeft} CREDITS · ${activeCount} ACTIVE · ${evAvailable} EV FREE · WAITLIST ${waitlistEstimate} MIN · ${clock} · `;

  return (
    <section data-testid="void-surface" className="overflow-hidden rounded-[28px] border border-surface-800 bg-black text-white shadow-2xl shadow-black/30">
      <div className="overflow-hidden border-b border-white/10 py-2">
        <div className="whitespace-nowrap px-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/70">
          {ticker.repeat(3)}
        </div>
      </div>
      <div className="grid gap-6 p-5 lg:grid-cols-[0.85fr_1.15fr]">
        <div className="space-y-5 border-b border-white/10 pb-5 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-5">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">Void Surface</p>
            <h2 className="mt-2 text-3xl font-semibold tracking-[-0.04em]">Editorial operations</h2>
            <p className="mt-3 text-sm leading-6 text-white/65">{announcement}</p>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <VoidMetric label="Credits" value={String(creditsLeft)} />
            <VoidMetric label="Live passes" value={String(activeCount)} />
            <VoidMetric label="EV free" value={String(evAvailable)} />
            <VoidMetric label="Waitlist" value={`${waitlistEstimate}m`} />
          </div>
          <div className="rounded-[24px] border border-white/10 bg-white/[0.03] p-4">
            <div className="flex items-center justify-between">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">Current pass</p>
              <span className="text-xs text-white/50">{clock}</span>
            </div>
            <p className="mt-3 text-xl font-semibold tracking-[-0.03em]">{activeBooking?.lot_name || 'No active booking'}</p>
            <p className="mt-1 text-sm text-white/60">{activeBooking ? `Slot ${activeBooking.slot_number} · ${activeBooking.vehicle_plate || 'assigned vehicle'}` : 'Reserve a space to light this section up.'}</p>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <article className="rounded-[24px] border border-white/10 bg-white/[0.03] p-4 md:col-span-2">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">Zones</p>
                <h3 className="mt-1 text-lg font-semibold">Occupancy board</h3>
              </div>
              <span className="text-xs text-white/50">{lots.length} zones</span>
            </div>
            <div className="grid gap-3 md:grid-cols-2">
              {lots.map((lot, index) => (
                <div key={lot.label} className="rounded-2xl border border-white/10 p-3">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/35">{String(index + 1).padStart(2, '0')}</span>
                    <span className="text-xs text-white/50">{lot.available} free</span>
                  </div>
                  <p className="text-sm font-medium text-white">{lot.label}</p>
                  <div className="mt-3 h-1.5 rounded-full bg-white/10">
                    <div className={`h-1.5 rounded-full ${lot.occupancy > 0.8 ? 'bg-rose-500' : lot.occupancy > 0.6 ? 'bg-amber-400' : 'bg-emerald-400'}`} style={{ width: `${Math.max(10, Math.round(lot.occupancy * 100))}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </article>

          <article className="rounded-[24px] border border-white/10 bg-white/[0.03] p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">EV</p>
            <h3 className="mt-1 text-lg font-semibold">Fast-charge status</h3>
            <div className="mt-4 space-y-2">
              {[0, 1, 2].map((index) => (
                <div key={index} className="flex items-center justify-between rounded-2xl bg-white/[0.04] px-3 py-2.5">
                  <span className="text-sm text-white/80">Bay {index + 1}</span>
                  <span className={`text-xs font-semibold ${index < evAvailable ? 'text-emerald-300' : 'text-white/40'}`}>
                    {index < evAvailable ? 'Open' : 'Busy'}
                  </span>
                </div>
              ))}
            </div>
          </article>

          <article className="rounded-[24px] border border-white/10 bg-white/[0.03] p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">Live feed</p>
            <h3 className="mt-1 text-lg font-semibold">Operational stream</h3>
            <div className="mt-4 space-y-3">
              {feed.map((entry) => (
                <div key={entry.id} className="border-b border-white/10 pb-3 last:border-b-0 last:pb-0">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-medium text-white">{entry.title}</p>
                    <span className={`inline-block h-2 w-2 rounded-full ${entry.tone === 'warning' ? 'bg-amber-400' : entry.tone === 'live' ? 'bg-emerald-400' : 'bg-white/30'}`} />
                  </div>
                  <p className="mt-1 text-xs text-white/50">{entry.meta}</p>
                </div>
              ))}
            </div>
          </article>
        </div>
      </div>
    </section>
  );
}

function VoidMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[22px] border border-white/10 bg-white/[0.03] px-4 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/40">{label}</p>
      <p className="mt-2 text-2xl font-semibold tracking-[-0.04em]">{value}</p>
    </div>
  );
}
