import { useEffect, useState, type ComponentType } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  CalendarBlank, Clock, Car, X, SpinnerGap, CheckCircle, XCircle, ArrowClockwise,
  Warning, MapPin, CalendarPlus, Repeat, PencilSimple, Timer, CalendarCheck,
  MagnifyingGlass, Funnel, Receipt,
} from '@phosphor-icons/react';
import { api, Booking, Vehicle } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { format, formatDistanceToNow, isFuture, type Locale } from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import { ConfirmDialog } from '../components/ConfirmDialog';

function BookingCard({ booking, onCancel, cancelling, vehiclePhoto, t, dateFnsLocale, now }: { booking: Booking; onCancel: (id: string) => void; cancelling: string | null; vehiclePhoto?: string; t: ReturnType<typeof useTranslation>["t"]; dateFnsLocale: Locale; now: number }) {
  const isActiveOrConfirmed = booking.status === 'active' || booking.status === 'confirmed';
  const isExpiringSoon = isActiveOrConfirmed && new Date(booking.end_time).getTime() - now < 30 * 60 * 1000 && !isFuture(new Date(booking.start_time));
  const isUpcoming = isActiveOrConfirmed && isFuture(new Date(booking.start_time));
  const isActive = isActiveOrConfirmed && !isUpcoming;
  const isPastBooking = booking.status === 'completed' || booking.status === 'cancelled';

  const statusConfig: Record<string, { label: string; class: string; icon: ComponentType<{ weight?: "thin" | "light" | "regular" | "bold" | "fill" | "duotone"; className?: string }> }> = {
    active: { label: t('bookings.statusActive'), class: 'badge-success', icon: Clock },
    completed: { label: t('bookings.statusCompleted'), class: 'badge-gray', icon: CheckCircle },
    cancelled: { label: t('bookings.statusCancelled'), class: 'badge-error', icon: XCircle },
  };
  const bookingTypeConfig: Record<string, { label: string; class: string }> = {
    einmalig: { label: t('bookings.typeSingle'), class: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' },
    mehrtaegig: { label: t('bookings.typeMultiDay'), class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
    dauer: { label: t('bookings.typeRecurring'), class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
  };

  const cfg = statusConfig[booking.status];
  const StatusIcon = cfg.icon;
  const bType = booking.booking_type || 'einmalig';
  const typeConf = bookingTypeConfig[bType];

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, x: -100 }}
      className={`card p-6 shadow-md dark:shadow-gray-900/50 border-l-4 transition-all hover:shadow-lg hover:-translate-y-0.5 ${isPastBooking ? 'border-l-gray-300 dark:border-l-gray-600 opacity-80' : isExpiringSoon ? 'border-l-amber-500' : isUpcoming ? 'border-l-primary-500' : 'border-l-primary-500'}`}>
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${isPastBooking ? 'bg-gray-100 dark:bg-gray-800' : isExpiringSoon ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-primary-100 dark:bg-primary-900/30'}`}>
            <span className={`text-xl font-bold ${isPastBooking ? 'text-gray-500 dark:text-gray-400' : isExpiringSoon ? 'text-amber-600 dark:text-amber-400' : 'text-primary-600 dark:text-primary-400'}`}>{booking.slot_number}</span>
          </div>
          <div>
            <p className="font-semibold text-gray-900 dark:text-white">{booking.lot_name}</p>
            <div className="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"><MapPin weight="regular" className="w-3.5 h-3.5" />{t('dashboard.slot')} {booking.slot_number}</div>
          </div>
        </div>
        <div className="flex flex-col items-end gap-2">
          <span className={`badge ${cfg.class}`}><StatusIcon weight="fill" className="w-3 h-3" />{cfg.label}</span>
          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold ${typeConf.class}`}>
            {bType === 'dauer' && <Repeat weight="bold" className="w-3 h-3" />}
            {bType === 'mehrtaegig' && <CalendarCheck weight="bold" className="w-3 h-3" />}
            {bType === 'einmalig' && <Clock weight="bold" className="w-3 h-3" />}
            {typeConf.label}
            {bType === 'dauer' && booking.dauer_interval && <span className="ml-0.5">· {booking.dauer_interval === 'weekly' ? t('bookings.weekly') : t('bookings.monthly')}</span>}
          </span>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-3 text-sm mb-4">
        <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
          {vehiclePhoto ? <img src={vehiclePhoto} alt="" className="w-8 h-8 rounded-full object-cover flex-shrink-0" /> : <Car weight="regular" className="w-4 h-4" />}
          <span className="font-mono">{booking.vehicle_plate || '—'}</span>
        </div>
        <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
          <Timer weight="regular" className="w-4 h-4" />
          {bType === 'einmalig' ? <span>{format(new Date(booking.start_time), 'HH:mm')} — {format(new Date(booking.end_time), 'HH:mm')}</span>
          : <span>{format(new Date(booking.start_time), 'd. MMM', { locale: dateFnsLocale })} — {format(new Date(booking.end_time), 'd. MMM yyyy', { locale: dateFnsLocale })}</span>}
        </div>
      </div>
      <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
        <p className={`text-sm ${isExpiringSoon ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
          {isExpiringSoon && <Warning weight="fill" className="w-3.5 h-3.5 inline mr-1" />}
          {isUpcoming ? t('bookings.startsIn', { time: formatDistanceToNow(new Date(booking.start_time), { addSuffix: true, locale: dateFnsLocale }) })
          : isPastBooking ? format(new Date(booking.start_time), 'd. MMMM yyyy', { locale: dateFnsLocale })
          : t('bookings.endsIn', { time: formatDistanceToNow(new Date(booking.end_time), { addSuffix: true, locale: dateFnsLocale }) })}
        </p>
        <div className="flex items-center gap-2">
          {isActive && <button className="btn btn-sm btn-secondary"><PencilSimple weight="bold" className="w-3.5 h-3.5" />{t('bookings.extend')}</button>}
          {isActiveOrConfirmed && (
            <button
              onClick={() => onCancel(booking.id)}
              disabled={cancelling === booking.id}
              aria-busy={cancelling === booking.id}
              aria-label={`${t('bookings.cancelBtn')} – ${booking.lot_name} ${t('dashboard.slot')} ${booking.slot_number}`}
              className="btn btn-sm btn-ghost text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
            >
              {cancelling === booking.id ? (
                <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" aria-hidden="true" />
              ) : (
                <>
                  <X weight="bold" className="w-4 h-4" aria-hidden="true" />
                  {t('bookings.cancelBtn')}
                </>
              )}
            </button>
          )}
          {booking.status === 'completed' && (
            <button
              className="btn btn-sm btn-secondary"
              title={t('bookings.invoice', 'Rechnung öffnen')}
              onClick={async () => {
                const base = (import.meta.env.VITE_API_URL as string) || '';
                const token = localStorage.getItem('parkhub_token');
                const res = await fetch(`${base}/api/v1/bookings/${booking.id}/invoice`, {
                  headers: { Authorization: `Bearer ${token}` },
                });
                if (res.ok) {
                  const blob = await res.blob();
                  const url = URL.createObjectURL(blob);
                  window.open(url, '_blank');
                  setTimeout(() => URL.revokeObjectURL(url), 60000);
                }
              }}
            >
              <Receipt weight="bold" className="w-3.5 h-3.5" />
              {t('bookings.invoice', 'Rechnung')}
            </button>
          )}
        </div>
      </div>
    </motion.div>
  );
}

function SectionHeader({ icon: Icon, title, count, color, headingId }: { icon: ComponentType<{ weight?: "thin" | "light" | "regular" | "bold" | "fill" | "duotone"; className?: string }>; title: string; count: number; color: string; headingId?: string }) {
  return (
    <h2 id={headingId} className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
      <Icon weight="fill" className={`w-5 h-5 ${color}`} aria-hidden="true" />
      {title}
      <span className="badge badge-gray text-xs" aria-label={`${count} Einträge`}>{count}</span>
    </h2>
  );
}

function EmptySection({ icon: Icon, text, showAction = false, t }: { icon: ComponentType<{ weight?: "thin" | "light" | "regular" | "bold" | "fill" | "duotone"; className?: string }>; text: string; showAction?: boolean; t: ReturnType<typeof useTranslation>["t"] }) {
  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="card p-12 text-center">
      <Icon weight="light" className="w-20 h-20 text-gray-200 dark:text-gray-700 mx-auto mb-4" /><p className="text-gray-500 dark:text-gray-400 mb-4">{text}</p>
      {showAction && <Link to="/book" className="btn btn-primary"><CalendarPlus weight="bold" className="w-4 h-4" />{t('bookings.bookNow')}</Link>}
    </motion.div>
  );
}

export function BookingsPage() {
  const { t, i18n } = useTranslation();
  const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : de;
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState<string | null>(null);
  const [confirmCancelId, setConfirmCancelId] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [filterDateFrom, setFilterDateFrom] = useState('');
  const [filterDateTo, setFilterDateTo] = useState('');
  const [searchLot, setSearchLot] = useState('');


  useEffect(() => { loadData(); }, []);

  async function loadData() {
    try {
      const [bRes, vRes] = await Promise.all([api.getBookings(), api.getVehicles()]);
      if (bRes.success && bRes.data) setBookings(bRes.data);
      if (vRes.success && vRes.data) setVehicles(vRes.data);
    } finally { setLoading(false); }
  }

  function getVehiclePhoto(plate?: string) { if (!plate) return undefined; return vehicles.find(v => v.plate === plate)?.photo_url; }

  async function handleCancel(id: string) {
    setCancelling(id);
    const res = await api.cancelBooking(id);
    if (res.success) { setBookings(bookings.map(b => b.id === id ? { ...b, status: 'cancelled' as const } : b)); toast.success(t('bookings.cancelled')); }
    else { toast.error(t('bookings.cancelFailed')); }
    setCancelling(null);
  }


  // Apply filters
  const filteredBookings = bookings.filter(b => {
    if (filterStatus !== 'all' && b.status !== filterStatus) return false;
    if (searchLot && !b.lot_name.toLowerCase().includes(searchLot.toLowerCase())) return false;
    if (filterDateFrom && new Date(b.start_time) < new Date(filterDateFrom)) return false;
    if (filterDateTo && new Date(b.end_time) > new Date(filterDateTo + 'T23:59:59')) return false;
    return true;
  });

  const isActiveOrConfirmed = (status: string) => status === 'active' || status === 'confirmed';
  const activeBookings = filteredBookings.filter(b => isActiveOrConfirmed(b.status) && !isFuture(new Date(b.start_time)));
  const upcomingBookings = filteredBookings.filter(b => isActiveOrConfirmed(b.status) && isFuture(new Date(b.start_time)));
  const pastBookings = filteredBookings.filter(b => b.status === 'completed' || b.status === 'cancelled');

  if (loading) return (
    <div className="space-y-6" role="status" aria-label={t('bookings.loading', 'Buchungen werden geladen')} aria-busy="true">
      <div className="h-8 w-64 skeleton" aria-hidden="true" />
      {[1,2,3].map(i => <div key={i} className="h-40 skeleton rounded-2xl" aria-hidden="true" />)}
      <span className="sr-only">{t('bookings.loading', 'Buchungen werden geladen…')}</span>
    </div>
  );

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div><h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('bookings.title')}</h1><p className="text-gray-500 dark:text-gray-400 mt-1">{t('bookings.subtitle')}</p></div>
        <button onClick={loadData} className="btn btn-secondary"><ArrowClockwise weight="bold" className="w-4 h-4" />{t('common.refresh')}</button>
      </div>


      {/* Filters */}
      <div className="card p-4">
        <div className="flex items-center gap-2 mb-3">
          <Funnel weight="bold" className="w-4 h-4 text-gray-500" />
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('common.filter')}</span>
          <span className="ml-auto text-xs text-gray-400">{t('bookingFilters.totalCount', { count: filteredBookings.length })}</span>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div className="relative">
            <MagnifyingGlass weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input type="text" value={searchLot} onChange={(e) => setSearchLot(e.target.value)} placeholder={t('bookingFilters.searchLot')} className="input pl-9 text-sm" />
          </div>
          <select value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)} className="input">
            <option value="all">{t('bookingFilters.statusAll')}</option>
            <option value="active">{t('bookingFilters.statusActive')}</option>
            <option value="confirmed">{t('bookingFilters.statusConfirmed')}</option>
            <option value="cancelled">{t('bookingFilters.statusCancelled')}</option>
            <option value="completed">{t('bookingFilters.statusCompleted')}</option>
          </select>
          <input type="date" value={filterDateFrom} onChange={(e) => setFilterDateFrom(e.target.value)} className="input" placeholder={t('bookingFilters.dateFrom')} />
          <input type="date" value={filterDateTo} onChange={(e) => setFilterDateTo(e.target.value)} className="input" placeholder={t('bookingFilters.dateTo')} />
        </div>
      </div>

      <section aria-labelledby="active-section-heading">
        <SectionHeader icon={Clock} title={t('bookings.active')} count={activeBookings.length} color="text-emerald-600" headingId="active-section-heading" />
        {activeBookings.length === 0 ? <EmptySection icon={CalendarBlank} text={t('bookings.noActive')} showAction t={t} /> : (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4"><AnimatePresence>{activeBookings.map(bk => <BookingCard key={bk.id} booking={bk} now={Date.now()} onCancel={(id) => setConfirmCancelId(id)} cancelling={cancelling} vehiclePhoto={getVehiclePhoto(bk.vehicle_plate)} t={t} dateFnsLocale={dateFnsLocale} />)}</AnimatePresence></div>
        )}
      </section>

      <section aria-labelledby="upcoming-section-heading">
        <SectionHeader icon={CalendarPlus} title={t('bookings.upcoming')} count={upcomingBookings.length} color="text-primary-600" headingId="upcoming-section-heading" />
        {upcomingBookings.length === 0 ? <EmptySection icon={CalendarCheck} text={t('bookings.noUpcoming')} t={t} /> : (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4"><AnimatePresence>{upcomingBookings.map(bk => <BookingCard key={bk.id} booking={bk} now={Date.now()} onCancel={(id) => setConfirmCancelId(id)} cancelling={cancelling} vehiclePhoto={getVehiclePhoto(bk.vehicle_plate)} t={t} dateFnsLocale={dateFnsLocale} />)}</AnimatePresence></div>
        )}
      </section>

      <section aria-labelledby="past-section-heading">
        <SectionHeader icon={CalendarBlank} title={t('bookings.past')} count={pastBookings.length} color="text-gray-400" headingId="past-section-heading" />
        {pastBookings.length === 0 ? <EmptySection icon={CheckCircle} text={t('bookings.noPast')} t={t} /> : (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">{pastBookings.map(bk => <BookingCard key={bk.id} booking={bk} now={Date.now()} onCancel={(id) => setConfirmCancelId(id)} cancelling={cancelling} vehiclePhoto={getVehiclePhoto(bk.vehicle_plate)} t={t} dateFnsLocale={dateFnsLocale} />)}</div>
        )}
      </section>

      <ConfirmDialog open={!!confirmCancelId} title={t('confirm.cancelBookingTitle')} message={t('confirm.cancelBookingMessage')} confirmLabel={t('confirm.cancelBookingConfirm')} variant="danger"
        onConfirm={() => { if (confirmCancelId) handleCancel(confirmCancelId); setConfirmCancelId(null); }} onCancel={() => setConfirmCancelId(null)} />
    </div>
  );
}
