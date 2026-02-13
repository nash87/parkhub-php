import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  CalendarPlus,
  ChartLine,
  Clock,
  ArrowRight,
  Buildings,
  CheckCircle,
  Warning,
  CaretRight,
  DownloadSimple,
  Lightning,
  Megaphone,
} from '@phosphor-icons/react';
import { House, Briefcase } from '@phosphor-icons/react';
import { api, ParkingLot, ParkingLotDetailed, Booking, HomeofficeSettings, Announcement } from '../api/client';
import { useAuth } from '../context/auth-hook';
import { ParkingLotGrid } from '../components/ParkingLotGrid';
import { format, formatDistanceToNow } from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import { useTranslation } from 'react-i18next';
import { useInstallPrompt } from '../hooks/useInstallPrompt';
import { useUseCaseStore } from '../stores/usecase';

export function DashboardPage() {
  const { t, i18n } = useTranslation();
  const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : de;
  const { user } = useAuth();
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [detailedLots, setDetailedLots] = useState<ParkingLotDetailed[]>([]);
  const [activeBookings, setActiveBookings] = useState<Booking[]>([]);
  const [hoSettings, setHoSettings] = useState<HomeofficeSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [quickBooking, setQuickBooking] = useState(false);
  const { canInstall, install, dismiss } = useInstallPrompt();
  const { useCase } = useUseCaseStore();
  const organizationLabel = t(`usecase.${useCase}.labels.organization`);

  useEffect(() => { loadData(); }, []);

  async function loadData() {
    try {
      const [lotsRes, bookingsRes, hoRes, annRes] = await Promise.all([
        api.getLots(), api.getBookings(), api.getHomeofficeSettings(), api.getActiveAnnouncements(),
      ]);
      if (hoRes.success && hoRes.data) setHoSettings(hoRes.data);
      if (annRes.success && annRes.data) setAnnouncements(annRes.data);
      if (lotsRes.success && lotsRes.data) {
        setLots(lotsRes.data);
        const detailedPromises = lotsRes.data.map((lot: ParkingLot) => api.getLotDetailed(lot.id));
        const detailedResults = await Promise.all(detailedPromises);
        setDetailedLots(detailedResults.filter((r: {success: boolean; data?: ParkingLotDetailed}) => r.success && r.data).map((r: {success: boolean; data?: ParkingLotDetailed}) => r.data!));
      }
      if (bookingsRes.success && bookingsRes.data) {
        setActiveBookings(bookingsRes.data.filter((b: Booking) => b.status === 'active' || b.status === 'confirmed'));
      }
    } finally { setLoading(false); }
  }

  async function handleQuickBook() {
    setQuickBooking(true);
    try {
      const res = await api.quickBook();
      if (res.success && res.data?.booking) {
        await loadData();
      }
    } catch { /* ignore */ }
    setQuickBooking(false);
  }

  const todayDow = new Date().getDay() === 0 ? 6 : new Date().getDay() - 1;
  const todayStr = new Date().toISOString().slice(0, 10);
  const isHoToday = hoSettings ? (
    hoSettings.pattern.weekdays.includes(todayDow) || hoSettings.single_days.some(d => d.date === todayStr)
  ) : false;

  const totalSlots = lots.reduce((sum, lot) => sum + lot.total_slots, 0);
  const availableSlots = lots.reduce((sum, lot) => sum + lot.available_slots, 0);
  const occupancyRate = totalSlots > 0 ? Math.round((1 - availableSlots / totalSlots) * 100) : 0;

  const containerVariants = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.1 } } };
  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-8 w-64 skeleton" />
        <div className="h-20 skeleton rounded-2xl" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[1, 2, 3].map(i => <div key={i} className="h-32 skeleton rounded-2xl" />)}
        </div>
      </div>
    );
  }

  return (
    <motion.div variants={containerVariants} initial="hidden" animate="show" className="space-y-6 sm:space-y-8 max-w-full">
      {/* Welcome + Date */}
      <motion.div variants={itemVariants} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
            {t('dashboard.welcome', { name: user?.name?.split(' ')[0] })}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-0.5 text-sm">
            {format(new Date(), "EEEE, d. MMMM yyyy", { locale: dateFnsLocale })} &middot; {organizationLabel}
          </p>
        </div>
        {!isHoToday && (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-medium text-gray-600 dark:text-gray-400 self-start">
            <Briefcase weight="fill" className="w-3.5 h-3.5" />
            {t('dashboard.inOffice')}
          </span>
        )}
      </motion.div>

      {/* Announcement Banner */}
      {announcements.length > 0 && announcements.map(ann => (
        <motion.div key={ann.id} variants={itemVariants}
          className={`card p-4 border-l-4 ${ann.severity === 'critical' ? 'border-l-red-500 bg-red-50 dark:bg-red-900/20' : ann.severity === 'warning' ? 'border-l-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'border-l-primary-500 bg-primary-50 dark:bg-primary-900/20'}`}>
          <div className="flex items-start gap-3">
            <Megaphone weight="fill" className={`w-5 h-5 mt-0.5 flex-shrink-0 ${ann.severity === 'critical' ? 'text-red-500' : ann.severity === 'warning' ? 'text-amber-500' : 'text-primary-500'}`} />
            <div>
              <p className="font-semibold text-gray-900 dark:text-white text-sm">{ann.title}</p>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-0.5">{ann.message}</p>
            </div>
          </div>
        </motion.div>
      ))}

      {/* Quick Book Button */}
      <motion.div variants={itemVariants}>
        <button onClick={handleQuickBook} disabled={quickBooking}
          className="w-full card bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition-colors text-left">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/50 rounded-xl flex items-center justify-center">
              <Lightning weight="fill" className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <p className="font-semibold text-emerald-800 dark:text-emerald-200">{t('quickBook.title', 'Quick Book')}</p>
              <p className="text-sm text-emerald-600 dark:text-emerald-400">{t('quickBook.subtitle', 'Book your favorite spot for today')}</p>
            </div>
          </div>
        </button>
      </motion.div>

      {/* ═══ PROMINENT BOOKING CTA — ABOVE THE FOLD ═══ */}
      <motion.div variants={itemVariants}>
        <Link to="/book" className="block">
          <div className="card bg-gradient-to-r from-primary-600 to-primary-700 p-5 sm:p-6 text-white hover:shadow-lg transition-shadow">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-4 min-w-0">
                <div className="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
                  <CalendarPlus weight="fill" className="w-7 h-7" />
                </div>
                <div className="min-w-0">
                  <h2 className="font-bold text-lg sm:text-xl">{t('dashboard.bookNowTitle')}</h2>
                  <p className="text-white/80 text-sm mt-0.5">
                    <span className="font-semibold text-white">{availableSlots}</span> {t('common.available')} · {t('dashboard.bookNowSubtitle')}
                  </p>
                </div>
              </div>
              <ArrowRight weight="bold" className="w-6 h-6 flex-shrink-0 opacity-80" />
            </div>
          </div>
        </Link>
      </motion.div>

      {/* Homeoffice Banner */}
      {isHoToday && hoSettings?.parkingSlot && (
        <motion.div variants={itemVariants} className="card bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 p-4">
          <div className="flex items-center gap-3">
            <House weight="fill" className="w-6 h-6 text-sky-600 dark:text-sky-400 flex-shrink-0" />
            <p className="font-medium text-sky-800 dark:text-sky-200 text-sm sm:text-base">
              {t('dashboard.homeofficeToday', { slot: hoSettings.parkingSlot.number })}
            </p>
          </div>
        </motion.div>
      )}

      {/* Stats Grid */}
      <motion.div variants={itemVariants} className="grid grid-cols-3 gap-3 sm:gap-6">
        <div className="stat-card p-3 sm:p-6">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">{t('dashboard.availableSlots')}</p>
              <div className="mt-1 sm:mt-2 flex items-baseline gap-1 sm:gap-2">
                <span className="text-xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{availableSlots}</span>
                <span className="text-gray-500 dark:text-gray-400 text-xs sm:text-base">/ {totalSlots}</span>
              </div>
            </div>
            <div className="w-8 h-8 sm:w-12 sm:h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg sm:rounded-xl flex items-center justify-center">
              <CheckCircle weight="fill" className="w-4 h-4 sm:w-6 sm:h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
          </div>
          <div className="mt-2 sm:mt-4"><div className="progress"><div className="progress-bar bg-emerald-500" style={{ width: `${totalSlots > 0 ? (availableSlots / totalSlots) * 100 : 0}%` }} /></div></div>
        </div>

        <div className="stat-card p-3 sm:p-6">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">{t('dashboard.occupancy')}</p>
              <div className="mt-1 sm:mt-2 flex items-baseline gap-1 sm:gap-2">
                <span className="text-xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{occupancyRate}%</span>
              </div>
            </div>
            <div className="w-8 h-8 sm:w-12 sm:h-12 bg-primary-100 dark:bg-primary-900/30 rounded-lg sm:rounded-xl flex items-center justify-center">
              <ChartLine weight="fill" className="w-4 h-4 sm:w-6 sm:h-6 text-primary-600 dark:text-primary-400" />
            </div>
          </div>
          <div className="mt-2 sm:mt-4"><div className="progress"><div className={`progress-bar ${occupancyRate > 80 ? 'bg-red-500' : occupancyRate > 60 ? 'bg-amber-500' : 'bg-primary-500'}`} style={{ width: `${occupancyRate}%` }} /></div></div>
        </div>

        <div className="stat-card p-3 sm:p-6">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">{t('dashboard.activeBookings')}</p>
              <div className="mt-1 sm:mt-2"><span className="text-xl sm:text-3xl font-bold tracking-tight text-primary-600 dark:text-primary-400">{activeBookings.length}</span></div>
            </div>
            <div className="w-8 h-8 sm:w-12 sm:h-12 bg-amber-100 dark:bg-amber-900/30 rounded-lg sm:rounded-xl flex items-center justify-center">
              <Clock weight="fill" className="w-4 h-4 sm:w-6 sm:h-6 text-amber-600 dark:text-amber-400" />
            </div>
          </div>
          <Link to="/bookings" className="mt-2 sm:mt-4 flex items-center gap-1 text-xs sm:text-sm text-primary-600 dark:text-primary-400 font-medium hover:underline">
            {t('dashboard.viewAll')} <CaretRight weight="bold" className="w-3 h-3 sm:w-4 sm:h-4" />
          </Link>
        </div>
      </motion.div>

      {/* Active Bookings */}
      {activeBookings.length > 0 && (
        <motion.div variants={itemVariants} className="card p-4 sm:p-6">
          <div className="flex items-center justify-between mb-4 sm:mb-6">
            <h2 className="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">{t('dashboard.activeBookings')}</h2>
            <Link to="/bookings" className="text-sm text-primary-600 dark:text-primary-400 font-medium hover:underline">{t('dashboard.viewAll')}</Link>
          </div>
          <div className="space-y-3">
            {activeBookings.slice(0, 3).map((booking, index) => (
              <motion.div key={booking.id} initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: index * 0.1 }}
                className="flex items-center justify-between p-3 sm:p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                <div className="flex items-center gap-3 sm:gap-4">
                  <div className="w-10 h-10 sm:w-14 sm:h-14 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center">
                    <span className="text-sm sm:text-lg font-bold text-primary-600 dark:text-primary-400">{booking.slot_number}</span>
                  </div>
                  <div>
                    <p className="font-medium text-gray-900 dark:text-white text-sm sm:text-base">{booking.lot_name}</p>
                    <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{booking.vehicle_plate || t('dashboard.noPlate')}</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="font-medium text-gray-900 dark:text-white text-sm">{t('dashboard.until', { time: format(new Date(booking.end_time), 'HH:mm') })}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">{formatDistanceToNow(new Date(booking.end_time), { addSuffix: true, locale: dateFnsLocale })}</p>
                </div>
              </motion.div>
            ))}
          </div>
        </motion.div>
      )}

      {/* Parking Lot Grid Overview */}
      {detailedLots.length > 0 && (
        <motion.div variants={itemVariants} className="space-y-4">
          <h2 className="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">{t('dashboard.lotOverview')}</h2>
          {detailedLots.filter((l) => l.layout).map((lot) => (
            <div key={lot.id} className="card p-4 sm:p-6 shadow-md dark:shadow-gray-900/50">
              <h3 className="font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">{lot.name}</h3>
              <ParkingLotGrid layout={lot.layout!} interactive={false} />
            </div>
          ))}
        </motion.div>
      )}

      {/* Parking Lots Cards */}
      <motion.div variants={itemVariants}>
        <div className="flex items-center justify-between mb-4 sm:mb-6">
          <h2 className="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">{t('dashboard.parkingLots')}</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
          {lots.map((lot, index) => (
            <motion.div key={lot.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: index * 0.1 }}>
              <Link to={`/book?lot=${lot.id}`} className="card-hover block p-4 sm:p-6">
                <div className="flex items-start justify-between mb-3 sm:mb-4">
                  <div className="w-10 h-10 sm:w-12 sm:h-12 bg-gray-100 dark:bg-gray-800 rounded-xl flex items-center justify-center">
                    <Buildings weight="fill" className="w-5 h-5 sm:w-6 sm:h-6 text-gray-600 dark:text-gray-400" />
                  </div>
                  <div className={`badge ${lot.available_slots === 0 ? 'badge-error' : lot.available_slots < 5 ? 'badge-warning' : 'badge-success'}`}>
                    {lot.available_slots === 0 ? (
                      <><Warning weight="fill" className="w-3 h-3" />{t('common.full')}</>
                    ) : (
                      <><CheckCircle weight="fill" className="w-3 h-3" />{lot.available_slots} {t('common.free')}</>
                    )}
                  </div>
                </div>
                <h3 className="font-semibold text-gray-900 dark:text-white mb-1 text-sm sm:text-base">{lot.name}</h3>
                <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-3 sm:mb-4">{lot.address}</p>
                <div className="flex items-center justify-between">
                  <div className="flex-1 mr-4"><div className="progress h-1.5"><div className={`progress-bar ${lot.available_slots === 0 ? 'bg-red-500' : 'bg-emerald-500'}`} style={{ width: `${(lot.available_slots / lot.total_slots) * 100}%` }} /></div></div>
                  <span className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{lot.total_slots - lot.available_slots}/{lot.total_slots}</span>
                </div>
              </Link>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* PWA Install Banner */}
      {canInstall && (
        <motion.div variants={itemVariants} className="card bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 p-4">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-3 min-w-0">
              <DownloadSimple weight="bold" className="w-6 h-6 text-primary-600 dark:text-primary-400 flex-shrink-0" />
              <span className="font-medium text-primary-800 dark:text-primary-200 text-sm">{t('pwa.installBanner')}</span>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <button onClick={dismiss} className="btn btn-ghost btn-sm text-primary-600">{t('pwa.dismiss')}</button>
              <button onClick={install} className="btn btn-primary btn-sm">
                <DownloadSimple weight="bold" className="w-4 h-4" />
                {t('pwa.install')}
              </button>
            </div>
          </div>
        </motion.div>
      )}
    </motion.div>
  );
}
