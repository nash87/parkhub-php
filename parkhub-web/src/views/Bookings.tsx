import {
  useEffect,
  useMemo,
  useState,
  useCallback,
  useOptimistic,
  useTransition,
  type ReactNode,
} from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  Clock,
  Car,
  X,
  SpinnerGap,
  ArrowClockwise,
  Warning,
  MagnifyingGlass,
  Funnel,
  QrCode,
  FilePdf,
  CalendarPlus,
} from '@phosphor-icons/react';
import type { TFunction } from 'react-i18next';
import { api, type Booking, type Vehicle } from '../api/client';
import { BookingsSkeleton } from '../components/Skeleton';
import { ParkingPass } from '../components/ParkingPass';
import { stagger, fadeUp } from '../constants/animations';
import toast from 'react-hot-toast';
import { format, formatDistanceToNow, isFuture } from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import type { Locale } from 'date-fns';
import { useWebSocket, type WsEvent } from '../hooks/useWebSocket';
import { useNavLayout } from '../hooks/useNavLayout';

const FILTER_OPTIONS = [
  { value: 'all', labelKey: 'bookingFilters.statusAll' },
  { value: 'active', labelKey: 'bookingFilters.statusActive' },
  { value: 'confirmed', labelKey: 'bookingFilters.statusConfirmed' },
  { value: 'cancelled', labelKey: 'bookingFilters.statusCancelled' },
  { value: 'completed', labelKey: 'bookingFilters.statusCompleted' },
] as const;

export function BookingsPage() {
  const { t, i18n } = useTranslation();
  const [navLayout] = useNavLayout();
  const dateFnsLocale = i18n.language?.startsWith('de') ? de : enUS;
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState('all');
  const [searchLot, setSearchLot] = useState('');
  const [passBooking, setPassBooking] = useState<Booking | null>(null);

  const [optimisticBookings, applyOptimistic] = useOptimistic(
    bookings,
    (state: Booking[], action: { type: 'cancel'; id: string }) => {
      if (action.type === 'cancel') {
        return state.map((booking) => (
          booking.id === action.id ? { ...booking, status: 'cancelled' } : booking
        ));
      }
      return state;
    },
  );
  const [, startTransition] = useTransition();

  const handleWsEvent = useCallback((event: WsEvent) => {
    if (event.event === 'booking_created') {
      toast.success(t('bookings.wsNewBooking', 'Someone booked a spot in this lot'));
    } else if (event.event === 'booking_cancelled') {
      toast(t('bookings.wsCancelledBooking', 'A booking was cancelled'), { icon: '📋' });
    }
  }, [t]);

  useWebSocket({ onEvent: handleWsEvent });

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    const [bookingsResponse, vehiclesResponse] = await Promise.all([
      api.getBookings(),
      api.getVehicles(),
    ]);

    if (bookingsResponse.success && bookingsResponse.data) {
      setBookings(bookingsResponse.data);
    }
    if (vehiclesResponse.success && vehiclesResponse.data) {
      setVehicles(vehiclesResponse.data);
    }
    setLoading(false);
  }

  async function handleCancel(id: string) {
    setCancelling(id);
    startTransition(() => applyOptimistic({ type: 'cancel', id }));
    const response = await api.cancelBooking(id);
    if (response.success) {
      setBookings((current) => current.map((booking) => (
        booking.id === id ? { ...booking, status: 'cancelled' } : booking
      )));
      toast.success(t('bookings.cancelled'));
    } else {
      toast.error(t('bookings.cancelFailed'));
    }
    setCancelling(null);
  }

  const filtered = useMemo(() => optimisticBookings.filter((booking) => {
    if (filterStatus !== 'all' && booking.status !== filterStatus) return false;
    if (searchLot && !booking.lot_name.toLowerCase().includes(searchLot.toLowerCase())) return false;
    return true;
  }), [optimisticBookings, filterStatus, searchLot]);

  const isActiveOrConfirmed = (status: string) => status === 'active' || status === 'confirmed';
  const now = Date.now();
  const { active, upcoming, past } = useMemo(() => ({
    active: filtered.filter((booking) => (
      isActiveOrConfirmed(booking.status) && !isFuture(new Date(booking.start_time))
    )),
    upcoming: filtered.filter((booking) => (
      isActiveOrConfirmed(booking.status) && isFuture(new Date(booking.start_time))
    )),
    past: filtered.filter((booking) => booking.status === 'completed' || booking.status === 'cancelled'),
  }), [filtered]);

  const summaryCards = [
    {
      label: t('bookingFilters.totalCount', { count: filtered.length }),
      value: filtered.length,
      note: t('bookings.summaryVisible', 'visible bookings'),
    },
    {
      label: t('bookings.active'),
      value: active.length,
      note: t('bookings.summaryActive', 'currently valid'),
    },
    {
      label: t('bookings.upcoming'),
      value: upcoming.length,
      note: t('bookings.summaryUpcoming', 'arriving later'),
    },
    {
      label: t('bookings.past'),
      value: past.length,
      note: t('bookings.summaryPast', 'history and cancelled'),
    },
  ];

  if (loading) return <BookingsSkeleton />;

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key="bookings-loaded"
        variants={stagger}
        initial="hidden"
        animate="show"
        className="space-y-6"
      >
        <motion.div
          variants={fadeUp}
          data-testid="bookings-summary-band"
          className={`overflow-hidden rounded-[28px] border p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] ${
            navLayout === 'focus'
              ? 'border-slate-800/80 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-white'
              : 'border-surface-200/70 bg-gradient-to-br from-white via-white to-primary-50/60 dark:border-surface-800 dark:from-surface-950 dark:via-surface-950 dark:to-primary-950/20'
          }`}
        >
          <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div className="space-y-2">
                <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium ${
                  navLayout === 'focus'
                    ? 'border-cyan-500/30 bg-cyan-500/10 text-cyan-100'
                    : 'border-primary-200/80 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-300'
                }`}>
                  <Clock weight="bold" className="h-3.5 w-3.5" />
                  {navLayout === 'focus'
                    ? t('bookings.summaryBadgeFocus', 'Live ledger')
                    : t('bookings.summaryBadge', 'Booking desk')}
                </div>
                <div>
                  <h1 className={`text-3xl font-bold tracking-tight ${navLayout === 'focus' ? 'text-white' : 'text-surface-950 dark:text-white'}`}>
                    {t('bookings.title')}
                  </h1>
                  <p className={`mt-1 max-w-2xl text-sm ${navLayout === 'focus' ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                    {navLayout === 'focus'
                      ? t('bookings.focusSubtitle', 'Operations cadence')
                      : t('bookings.subtitle')}
                  </p>
                </div>
                <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs shadow-sm ${
                  navLayout === 'focus'
                    ? 'border-slate-700 bg-slate-900/80 text-slate-300'
                    : 'border-surface-200 bg-white/80 text-surface-500 dark:border-surface-800 dark:bg-surface-900/70 dark:text-surface-400'
                }`}>
                  <Car weight="regular" className="h-3.5 w-3.5" />
                  {t('bookings.summaryVehicles', '{{count}} vehicles on file', { count: vehicles.length })}
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <button onClick={loadData} className="btn btn-secondary">
                  <ArrowClockwise weight="bold" className="w-4 h-4" /> {t('common.refresh')}
                </button>
                <Link to="/book" className="btn btn-primary">
                  <CalendarPlus weight="bold" className="w-4 h-4" /> {t('bookings.bookNow')}
                </Link>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
              {summaryCards.map((card) => (
                <div
                  key={card.label}
                  className={`rounded-2xl border px-4 py-3 shadow-sm backdrop-blur ${
                    navLayout === 'focus'
                      ? 'border-slate-700/80 bg-slate-900/80'
                      : 'border-white/70 bg-white/80 dark:border-surface-800 dark:bg-surface-900/80'
                  }`}
                >
                  <p className={`text-[11px] uppercase tracking-[0.16em] ${navLayout === 'focus' ? 'text-slate-400' : 'text-surface-400 dark:text-surface-500'}`}>
                    {card.label}
                  </p>
                  <div className="mt-2 flex items-end justify-between gap-3">
                    <span className={`text-2xl font-semibold ${navLayout === 'focus' ? 'text-white' : 'text-surface-950 dark:text-white'}`}>
                      {card.value}
                    </span>
                    <span className={`text-xs ${navLayout === 'focus' ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>{card.note}</span>
                  </div>
                </div>
              ))}
            </div>

            <div className={`rounded-[24px] border p-4 backdrop-blur ${
              navLayout === 'focus'
                ? 'border-slate-700 bg-slate-950/80'
                : 'border-surface-200/70 bg-white/85 dark:border-surface-800 dark:bg-surface-950/70'
            }`}>
              <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                <div className="relative xl:min-w-[280px] xl:flex-1">
                  <MagnifyingGlass weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                  <input
                    type="text"
                    value={searchLot}
                    onChange={(event) => setSearchLot(event.target.value)}
                    placeholder={t('bookingFilters.searchLot')}
                    className="input pl-9 pr-3 text-sm"
                    aria-label={t('bookingFilters.searchLot')}
                  />
                </div>

                <div className="flex flex-col gap-3 xl:min-w-0 xl:flex-1">
                  <div className="flex items-center gap-2" aria-live="polite">
                    <Funnel weight="bold" className="h-4 w-4 text-surface-400" />
                    <span className="text-sm font-medium text-surface-700 dark:text-surface-300">
                      {t('common.filter')}
                    </span>
                    <span className="ml-auto rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium text-surface-500 dark:bg-surface-900 dark:text-surface-400">
                      {t('bookingFilters.totalCount', { count: filtered.length })}
                    </span>
                  </div>

                  <div role="toolbar" aria-label={t('common.filter')} className="flex flex-wrap gap-2">
                    {FILTER_OPTIONS.map((option) => {
                      const selected = filterStatus === option.value;
                      return (
                        <button
                          key={option.value}
                          type="button"
                          onClick={() => setFilterStatus(option.value)}
                          aria-pressed={selected}
                          className={`rounded-full border px-3.5 py-2 text-sm font-medium transition ${
                            selected
                              ? 'border-primary-300 bg-primary-50 text-primary-700 shadow-sm dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-300'
                              : 'border-surface-200 bg-white text-surface-600 hover:border-surface-300 hover:text-surface-900 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:border-surface-600 dark:hover:text-white'
                          }`}
                        >
                          {t(option.labelKey)}
                        </button>
                      );
                    })}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </motion.div>

        <Section title={t('bookings.active')} count={active.length}>
          {active.length === 0 ? (
            <Empty text={t('bookings.noActive')} showAction t={t} />
          ) : (
            <BookingList
              bookings={active}
              now={now}
              vehicles={vehicles}
              onCancel={handleCancel}
              cancelling={cancelling}
              onShowPass={setPassBooking}
              t={t}
              dateFnsLocale={dateFnsLocale}
            />
          )}
        </Section>

        <Section title={t('bookings.upcoming')} count={upcoming.length}>
          {upcoming.length === 0 ? (
            <Empty text={t('bookings.noUpcoming')} t={t} />
          ) : (
            <BookingList
              bookings={upcoming}
              now={now}
              vehicles={vehicles}
              onCancel={handleCancel}
              cancelling={cancelling}
              onShowPass={setPassBooking}
              t={t}
              dateFnsLocale={dateFnsLocale}
            />
          )}
        </Section>

        <Section title={t('bookings.past')} count={past.length}>
          {past.length === 0 ? (
            <Empty text={t('bookings.noPast')} t={t} />
          ) : (
            <BookingList
              bookings={past}
              now={now}
              vehicles={vehicles}
              onCancel={handleCancel}
              cancelling={cancelling}
              t={t}
              dateFnsLocale={dateFnsLocale}
            />
          )}
        </Section>
      </motion.div>
      {passBooking && <ParkingPass booking={passBooking} onClose={() => setPassBooking(null)} />}
    </AnimatePresence>
  );
}

interface SectionProps {
  title: string;
  count: number;
  children: ReactNode;
}

function Section({ title, count, children }: SectionProps) {
  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold text-surface-900 dark:text-white">{title}</h2>
        <span className="rounded-full border border-surface-200 bg-white px-2.5 py-1 text-xs font-medium text-surface-500 shadow-sm dark:border-surface-800 dark:bg-surface-900 dark:text-surface-400">
          {count}
        </span>
      </div>
      {children}
    </section>
  );
}

interface EmptyProps {
  text: string;
  showAction?: boolean;
  t: TFunction;
}

function Empty({ text, showAction, t }: EmptyProps) {
  if (showAction) {
    return (
      <div className="rounded-[24px] border border-surface-200 bg-white p-8 text-center shadow-[0_24px_60px_-48px_rgba(15,23,42,0.55)] dark:border-surface-800 dark:bg-surface-950/70">
        <div
          className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/20"
          aria-hidden="true"
        >
          <CalendarPlus weight="duotone" className="h-8 w-8 text-primary-600 dark:text-primary-400" />
        </div>
        <h3 className="text-base font-semibold text-surface-900 dark:text-white">
          {t('bookings.emptyActiveTitle')}
        </h3>
        <p className="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-surface-500 dark:text-surface-400">
          {t('bookings.emptyActiveSubtitle')}
        </p>
        <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
          <Link to="/book" className="btn btn-primary">{t('bookings.bookNow')}</Link>
          <button
            type="button"
            onClick={() => window.dispatchEvent(new CustomEvent('parkhub:open-command-palette'))}
            className="btn btn-ghost text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white"
          >
            {t('bookings.emptyActiveSecondary')}
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-[24px] border border-dashed border-surface-300 bg-white/90 p-6 text-left dark:border-surface-700 dark:bg-surface-900/80">
      <p className="text-sm text-surface-500 dark:text-surface-400">{text}</p>
    </div>
  );
}

interface BookingListProps {
  bookings: Booking[];
  now: number;
  vehicles: Vehicle[];
  onCancel: (id: string) => void;
  cancelling: string | null;
  onShowPass?: (booking: Booking) => void;
  t: TFunction;
  dateFnsLocale: Locale;
}

function BookingList({
  bookings,
  now,
  vehicles,
  onCancel,
  cancelling,
  onShowPass,
  t,
  dateFnsLocale,
}: BookingListProps) {
  return (
    <div className="overflow-hidden rounded-[24px] border border-surface-200 bg-white/95 shadow-[0_24px_60px_-48px_rgba(15,23,42,0.55)] dark:border-surface-800 dark:bg-surface-950/80">
      <div className="hidden grid-cols-[minmax(0,1.7fr)_minmax(0,0.95fr)_minmax(0,1.1fr)_minmax(0,0.95fr)_minmax(0,1.2fr)] gap-4 border-b border-surface-200 bg-surface-50/80 px-5 py-3 lg:grid dark:border-surface-800 dark:bg-surface-900/70">
        {['Lot / Slot', 'Vehicle', 'Date & time', 'Status', 'Actions'].map((label) => (
          <span
            key={label}
            className="text-[11px] font-medium uppercase tracking-[0.18em] text-surface-400 dark:text-surface-500"
          >
            {label}
          </span>
        ))}
      </div>

      <AnimatePresence initial={false}>
        {bookings.map((booking) => (
          <BookingRow
            key={booking.id}
            booking={booking}
            now={now}
            vehicles={vehicles}
            onCancel={onCancel}
            cancelling={cancelling}
            onShowPass={onShowPass}
            t={t}
            dateFnsLocale={dateFnsLocale}
          />
        ))}
      </AnimatePresence>
    </div>
  );
}

interface BookingRowProps {
  booking: Booking;
  now: number;
  vehicles: Vehicle[];
  onCancel: (id: string) => void;
  cancelling: string | null;
  onShowPass?: (booking: Booking) => void;
  t: TFunction;
  dateFnsLocale: Locale;
}

function BookingRow({
  booking,
  now,
  vehicles,
  onCancel,
  cancelling,
  onShowPass,
  t,
  dateFnsLocale,
}: BookingRowProps) {
  const actionable = booking.status === 'active' || booking.status === 'confirmed';
  const past = booking.status === 'completed' || booking.status === 'cancelled';
  const expiring = actionable && new Date(booking.end_time).getTime() - now < 30 * 60 * 1000;
  const upcoming = actionable && isFuture(new Date(booking.start_time));
  const dateLabel = format(new Date(booking.start_time), 'EEE, d MMM yyyy', { locale: dateFnsLocale });
  const timeLabel = `${format(new Date(booking.start_time), 'HH:mm')} - ${format(new Date(booking.end_time), 'HH:mm')}`;
  const relativeLabel = upcoming
    ? t('bookings.startsIn', {
      time: formatDistanceToNow(new Date(booking.start_time), { addSuffix: true, locale: dateFnsLocale }),
    })
    : past
    ? format(new Date(booking.start_time), 'd. MMMM yyyy', { locale: dateFnsLocale })
    : t('bookings.endsIn', {
      time: formatDistanceToNow(new Date(booking.end_time), { addSuffix: true, locale: dateFnsLocale }),
    });
  const vehiclePlate = booking.vehicle_plate || t('bookings.noVehicle', 'No plate assigned');
  const knownVehicle = vehicles.some((vehicle) => vehicle.plate === booking.vehicle_plate);

  const statusMap: Record<string, { label: string; chip: string; dot: string }> = {
    active: {
      label: t('bookings.statusActive'),
      chip: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300',
      dot: 'bg-emerald-500',
    },
    confirmed: {
      label: t('bookings.statusConfirmed', t('bookingFilters.statusConfirmed')),
      chip: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300',
      dot: 'bg-sky-500',
    },
    completed: {
      label: t('bookings.statusCompleted'),
      chip: 'border-surface-200 bg-surface-100 text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300',
      dot: 'bg-surface-400',
    },
    cancelled: {
      label: t('bookings.statusCancelled'),
      chip: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300',
      dot: 'bg-red-500',
    },
  };
  const status = statusMap[booking.status] || statusMap.active;

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, x: -60 }}
      className={`grid gap-4 border-b border-surface-200 px-4 py-4 last:border-b-0 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,0.95fr)_minmax(0,1.1fr)_minmax(0,0.95fr)_minmax(0,1.2fr)] lg:items-center lg:px-5 dark:border-surface-800 ${
        past ? 'bg-surface-50/50 dark:bg-surface-950/30' : 'bg-white/80 dark:bg-surface-950/10'
      }`}
    >
      <div className="min-w-0">
        <div className="flex items-start gap-3">
          <div className={`mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border ${
            past
              ? 'border-surface-200 bg-surface-100 text-surface-500 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-400'
              : 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-300'
          }`}>
            <Car weight="bold" className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <p className="truncate font-semibold text-surface-950 dark:text-white">{booking.lot_name}</p>
              {expiring && !past && (
                <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
                  <Warning weight="fill" className="h-3 w-3" />
                  {t('bookings.endingSoon', 'Ending soon')}
                </span>
              )}
            </div>
            <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-surface-500 dark:text-surface-400">
              <span>{t('dashboard.slot')} {booking.slot_number}</span>
              <span className="font-mono text-xs uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500">
                {booking.id}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div className="space-y-1">
        <span className="text-[11px] font-medium uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500 lg:hidden">
          Vehicle
        </span>
        <div className="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300">
          <Car weight="regular" className="h-4 w-4 text-surface-400" />
          <span className="font-medium">{vehiclePlate}</span>
        </div>
        <p className="text-xs text-surface-400 dark:text-surface-500">
          {knownVehicle ? t('bookings.vehicleKnown', 'Saved vehicle') : t('bookings.vehicleGuest', 'One-off plate')}
        </p>
      </div>

      <div className="space-y-1">
        <span className="text-[11px] font-medium uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500 lg:hidden">
          Date &amp; time
        </span>
        <p className="text-sm font-medium text-surface-900 dark:text-white">{dateLabel}</p>
        <span className="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
          <Clock weight="regular" className="h-4 w-4" />
          {timeLabel}
        </span>
      </div>

      <div className="space-y-2">
        <span className="text-[11px] font-medium uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500 lg:hidden">
          Status
        </span>
        <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium ${status.chip}`}>
          <span className={`h-2 w-2 rounded-full ${status.dot}`} />
          {status.label}
        </span>
        <p className={`text-sm ${
          expiring ? 'font-medium text-amber-700 dark:text-amber-300' : 'text-surface-500 dark:text-surface-400'
        }`}>
          {relativeLabel}
        </p>
      </div>

      <div className="space-y-2">
        <span className="text-[11px] font-medium uppercase tracking-[0.16em] text-surface-400 dark:text-surface-500 lg:hidden">
          Actions
        </span>
        <div className="flex flex-wrap items-center gap-2 lg:justify-end">
          {actionable && (
            <button
              onClick={() => onShowPass?.(booking)}
              aria-label={`${t('pass.showPass')} ${booking.lot_name}`}
              className="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 transition hover:border-primary-300 hover:bg-primary-100 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-950/70"
            >
              <QrCode weight="bold" className="h-4 w-4" /> {t('pass.showPass')}
            </button>
          )}
          <a
            href={`${(import.meta as Record<string, unknown>).env && typeof (import.meta as Record<string, any>).env.VITE_API_URL === 'string' ? (import.meta as Record<string, any>).env.VITE_API_URL : ''}/api/v1/bookings/${booking.id}/invoice/pdf`}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 rounded-full border border-surface-200 bg-white px-3 py-2 text-sm font-medium text-surface-700 transition hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800"
            aria-label={`${t('bookings.downloadInvoice')} ${booking.lot_name}`}
            data-testid={`invoice-${booking.id}`}
          >
            <FilePdf weight="bold" className="h-4 w-4" /> {t('bookings.downloadInvoice')}
          </a>
          {actionable && (
            <button
              onClick={() => onCancel(booking.id)}
              disabled={cancelling === booking.id}
              aria-label={`${t('bookings.cancelBtn')} ${booking.lot_name}`}
              className="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:border-red-300 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50"
            >
              {cancelling === booking.id
                ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" />
                : <><X weight="bold" className="h-4 w-4" /> {t('bookings.cancelBtn')}</>}
            </button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
