import { useCallback, useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Car, Clock, CheckCircle, SpinnerGap, MapPin, CalendarBlank, Repeat, Heart, Star, Sun, Moon, FloppyDisk,
} from '@phosphor-icons/react';
import { api, ParkingLot, ParkingLotDetailed, Vehicle, SlotConfig } from '../api/client';
import { ParkingLotGrid } from '../components/ParkingLotGrid';
import { LicensePlateInput } from '../components/LicensePlateInput';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { format, addDays, differenceInDays } from 'date-fns';
import { de, enUS } from 'date-fns/locale';

function BookingSuccessModal({ open, onDashboard, onNewBooking, summary }: {
  open: boolean; onDashboard: () => void; onNewBooking: () => void;
  summary: { lot: string; slot: string; type: string; time: string; plate: string };
}) {
  const { t } = useTranslation();
  return (
    <AnimatePresence>
      {open && (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <motion.div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
          <motion.div initial={{ opacity: 0, scale: 0.8 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.8 }} transition={{ type: 'spring', damping: 20, stiffness: 300 }} className="relative w-full max-w-md card p-8 shadow-2xl text-center">
            <motion.div initial={{ scale: 0 }} animate={{ scale: 1 }} transition={{ delay: 0.2, type: 'spring', damping: 12 }} className="w-20 h-20 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
              <motion.div initial={{ scale: 0, rotate: -180 }} animate={{ scale: 1, rotate: 0 }} transition={{ delay: 0.4, type: 'spring', damping: 15 }}>
                <CheckCircle weight="fill" className="w-12 h-12 text-emerald-500" />
              </motion.div>
            </motion.div>
            <motion.h2 initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.5 }} className="text-2xl font-bold text-gray-900 dark:text-white mb-2">{t('bookingSuccess.title')}</motion.h2>
            <motion.p initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.6 }} className="text-gray-500 dark:text-gray-400 mb-6">{t('bookingSuccess.subtitle')}</motion.p>
            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.7 }} className="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 mb-6 text-left space-y-2">
              {[
                [t('bookingSuccess.parkingLot'), summary.lot],
                [t('bookingSuccess.slot'), summary.slot],
                [t('bookingSuccess.type'), summary.type],
                [t('bookingSuccess.time'), summary.time],
                [t('bookingSuccess.plate'), summary.plate],
              ].map(([label, val], i) => (
                <div key={i} className="flex justify-between text-sm">
                  <span className="text-gray-500 dark:text-gray-400">{label}</span>
                  <span className={`font-medium text-gray-900 dark:text-white ${i === 1 ? 'font-bold text-primary-600 dark:text-primary-400' : ''} ${i === 4 ? 'font-mono' : ''}`}>{val}</span>
                </div>
              ))}
            </motion.div>
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.8 }} className="flex gap-3">
              <button onClick={onNewBooking} className="btn btn-secondary flex-1">{t('bookingSuccess.newBooking')}</button>
              <button onClick={onDashboard} className="btn btn-primary flex-1">{t('bookingSuccess.toDashboard')}</button>
            </motion.div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

type BookingType = 'einmalig' | 'mehrtaegig' | 'dauer';
type DauerInterval = 'weekly' | 'monthly';

export function BookPage() {
  const { t, i18n } = useTranslation();
  const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : de;
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const preselectedLot = searchParams.get('lot');

  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [selectedLot, setSelectedLot] = useState<string>(preselectedLot || '');
  const [detailedLot, setDetailedLot] = useState<ParkingLotDetailed | null>(null);
  const [selectedSlot, setSelectedSlot] = useState<SlotConfig | null>(null);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [selectedVehicle, setSelectedVehicle] = useState<string>('');
  const [customPlate, setCustomPlate] = useState('');
  const [saveVehicle, setSaveVehicle] = useState(true);
  const [duration] = useState(60);
  const [loading, setLoading] = useState(true);
  const [licensePlateEntryMode, setLicensePlateEntryMode] = useState<number>(0); // 0 optional, 1 required, 2 disabled
  const [booking, setBooking] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);
  const [successSummary, setSuccessSummary] = useState({ lot: '', slot: '', type: '', time: '', plate: '' });
  const [bookingType, setBookingType] = useState<BookingType>('einmalig');
  const [bookingDate, setBookingDate] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [timeOption, setTimeOption] = useState<'fullDay' | 'morning' | 'afternoon' | 'custom'>('fullDay');
  const [customStartTime, setCustomStartTime] = useState('08:00');
  const [customEndTime, setCustomEndTime] = useState('18:00');
  const [startDate, setStartDate] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [endDate, setEndDate] = useState(format(addDays(new Date(), 3), 'yyyy-MM-dd'));
  const [dauerInterval, setDauerInterval] = useState<DauerInterval>('monthly');
  const [dauerDays, setDauerDays] = useState<number[]>([1, 3]);

  const [favoriteSlots, setFavoriteSlots] = useState<string[]>(() => {
    try { return JSON.parse(localStorage.getItem('parkhub-favorite-slots') || '[]'); } catch { return []; }
  });

  function toggleFavorite(slotId: string) {
    setFavoriteSlots(prev => {
      const next = prev.includes(slotId) ? prev.filter(s => s !== slotId) : [...prev, slotId];
      localStorage.setItem('parkhub-favorite-slots', JSON.stringify(next));
      toast.success(next.includes(slotId) ? t('favorites.added') : t('favorites.removed'));
      return next;
    });
  }




  const loadInitialData = useCallback(async () => {
    try {
      const [lotsRes, vehiclesRes, privacyRes] = await Promise.all([
        api.getLots(),
        api.getVehicles(),
        fetch(`(import.meta.env.VITE_API_URL || "")/api/v1/settings/privacy`).then(r => r.json()).catch(() => null),
      ]);
      if (privacyRes?.data?.license_plate_entry_mode !== undefined) setLicensePlateEntryMode(Number(privacyRes.data.license_plate_entry_mode));
      if (lotsRes.success && lotsRes.data) { setLots(lotsRes.data); if (preselectedLot) setSelectedLot(preselectedLot); }
      if (vehiclesRes.success && vehiclesRes.data) { setVehicles(vehiclesRes.data); const def = vehiclesRes.data.find(v => v.is_default); if (def) setSelectedVehicle(def.id); }
    } finally { setLoading(false); }
  }, [preselectedLot]);

  useEffect(() => { void loadInitialData(); }, [loadInitialData]);
  useEffect(() => { if (selectedLot) loadDetailedLot(selectedLot); }, [selectedLot]);

  async function loadDetailedLot(lotId: string) {
    const res = await api.getLotDetailed(lotId);
    if (res.success && res.data) setDetailedLot(res.data);
  }

  function handleSlotSelect(slot: SlotConfig) { if (slot.status === 'available') setSelectedSlot(slot); }

  async function handleBook() {
    if (!selectedSlot) return;
    setBooking(true);
    let computedStart: string;
    let durationMinutes = duration;

    if (bookingType === 'einmalig') {
      const timeMap = { fullDay: ['08:00', '18:00'], morning: ['08:00', '12:00'], afternoon: ['12:00', '18:00'], custom: [customStartTime, customEndTime] };
      const [sTime, eTime] = timeMap[timeOption];
      computedStart = new Date(`${bookingDate}T${sTime}:00`).toISOString();
      const end = new Date(`${bookingDate}T${eTime}:00`);
      const start = new Date(`${bookingDate}T${sTime}:00`);
      durationMinutes = Math.round((end.getTime() - start.getTime()) / 60000);
    } else if (bookingType === 'mehrtaegig') {
      durationMinutes = differenceInDays(new Date(endDate), new Date(startDate)) * 24 * 60;
      computedStart = new Date(startDate).toISOString();
    } else {
      durationMinutes = dauerInterval === 'monthly' ? 30 * 24 * 60 : 7 * 24 * 60;
      computedStart = new Date().toISOString();
    }

    const startIso = computedStart;

    const normalizedPlate = (customPlate || '').replace(/[\u2010-\u2015\u2212]/g, '-').replace(/\s+/g, ' ').trim();

    const res = await api.createBooking({
      lot_id: selectedLot,
      slot_id: selectedSlot.id,
      start_time: startIso,
      duration_minutes: durationMinutes,
      vehicle_id: selectedVehicle || undefined,
      license_plate: (licensePlateEntryMode === 2) ? undefined : (normalizedPlate || undefined),
    });

    if (res.success) {
      const plate = selectedVehicle ? (vehicles.find(v => v.id === selectedVehicle)?.plate || '') : customPlate;
      const typeLabel = bookingType === 'einmalig' ? t('book.single') : bookingType === 'mehrtaegig' ? t('book.multiDay') : t('book.recurring');
      const timeMap = { fullDay: ['08:00', '18:00'], morning: ['08:00', '12:00'], afternoon: ['12:00', '18:00'], custom: [customStartTime, customEndTime] };
      const [sT, eT] = timeMap[timeOption];
      const timeLabel = bookingType === 'einmalig'
        ? `${format(new Date(bookingDate), 'd. MMM yyyy', { locale: dateFnsLocale })} ${timeOption === 'fullDay' ? t('booking.fullDay') : `${sT} – ${eT}`}`
        : bookingType === 'mehrtaegig'
        ? `${format(new Date(startDate), 'd. MMM', { locale: dateFnsLocale })} – ${format(new Date(endDate), 'd. MMM yyyy', { locale: dateFnsLocale })}`
        : `${dauerInterval === 'weekly' ? t('book.weekly') : t('book.monthly')}`;
      setSuccessSummary({ lot: selectedLotData?.name || '', slot: selectedSlot!.number, type: typeLabel, time: timeLabel, plate });
      setShowSuccess(true);
      // Save vehicle for future bookings
      if (saveVehicle && normalizedPlate && !selectedVehicle && licensePlateEntryMode !== 2) {
        try {
          const vRes = await api.createVehicle({ plate: normalizedPlate, is_default: vehicles.length === 0 });
          if (vRes.success) toast.success(t("book.vehicleSaved"));
        } catch { /* ignore vehicle save errors */ }
      }
    } else {
      toast.error(res.error?.message || t('book.bookingFailed'));
    }
    setBooking(false);
  }

  const selectedLotData = lots.find(l => l.id === selectedLot);
  const dayNames = (t('dayNamesShort', { returnObjects: true }) as string[]);

  if (loading) return <div className="flex items-center justify-center h-64"><SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" /></div>;

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('book.title')}</h1>
        <p className="text-gray-500 dark:text-gray-400 mt-1">{t('book.subtitle')}</p>
      </div>


      {/* Favorite Slots */}
      {favoriteSlots.length > 0 && detailedLot?.layout && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="card p-4 border-l-4 border-l-amber-400">
          <div className="flex items-center gap-2 mb-3">
            <Star weight="fill" className="w-5 h-5 text-amber-500" />
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{t('favorites.title')}</h3>
          </div>
          <div className="flex flex-wrap gap-2">
            {detailedLot.layout.rows.flatMap(r => r.slots).filter(s => favoriteSlots.includes(s.id) && s.status === 'available').map(slot => (
              <button key={slot.id} onClick={() => handleSlotSelect(slot)}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${selectedSlot?.id === slot.id ? 'bg-primary-600 text-white' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40'}`}>
                {slot.number}
              </button>
            ))}
          </div>
        </motion.div>
      )}

      {/* Step 1 */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="card p-6">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center"><span className="text-sm font-bold text-primary-600 dark:text-primary-400">1</span></div>
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('book.step1')}</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {lots.map((lot) => (
            <button key={lot.id} onClick={() => { setSelectedLot(lot.id); setSelectedSlot(null); setDetailedLot(null); }}
              className={`p-4 rounded-xl border-2 text-left transition-all ${selectedLot === lot.id ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <MapPin weight="fill" className="w-5 h-5 text-gray-400" />
                  <div><p className="font-medium text-gray-900 dark:text-white">{lot.name}</p><p className="text-sm text-gray-500 dark:text-gray-400">{lot.address}</p></div>
                </div>
                <div className={`badge ${lot.available_slots === 0 ? 'badge-error' : 'badge-success'}`}>{lot.available_slots} {t('common.free')}</div>
              </div>
            </button>
          ))}
        </div>
      </motion.div>

      {/* Step 2 */}
      <AnimatePresence>
        {selectedLot && detailedLot?.layout && (
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} className="card p-6 shadow-md dark:shadow-gray-900/50">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center"><span className="text-sm font-bold text-primary-600 dark:text-primary-400">2</span></div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('book.step2')}</h2>
              {selectedLotData && <span className="ml-auto text-sm text-gray-500 dark:text-gray-400">{t('book.ofAvailable', { available: selectedLotData.available_slots, total: selectedLotData.total_slots })}</span>}
            </div>
            {selectedSlot && (
              <div className="mb-4 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-800 flex items-center gap-3">
                <CheckCircle weight="fill" className="w-5 h-5 text-primary-600 dark:text-primary-400" />
                <span className="text-sm font-medium text-primary-700 dark:text-primary-300 flex-1" dangerouslySetInnerHTML={{ __html: t('book.slotSelected', { slot: selectedSlot.number }) }} />
                <button onClick={() => toggleFavorite(selectedSlot.id)} className="p-1.5 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors" aria-label="Toggle favorite">
                  <Heart weight={favoriteSlots.includes(selectedSlot.id) ? 'fill' : 'regular'} className={`w-5 h-5 ${favoriteSlots.includes(selectedSlot.id) ? 'text-red-500' : 'text-primary-400'}`} />
                </button>
              </div>
            )}
            <ParkingLotGrid layout={detailedLot.layout} selectedSlotId={selectedSlot?.id} onSlotSelect={handleSlotSelect} interactive />
          </motion.div>
        )}
      </AnimatePresence>

      {/* Step 3 */}
      <AnimatePresence>
        {selectedSlot && (
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} className="card p-6">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center"><span className="text-sm font-bold text-primary-600 dark:text-primary-400">3</span></div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('book.step3')}</h2>
            </div>
            <div className="space-y-6">
              {/* Booking type */}
              <div>
                <label className="label flex items-center gap-2"><CalendarBlank weight="regular" className="w-4 h-4" />{t('book.bookingType')}</label>
                <div className="grid grid-cols-3 gap-2">
                  {([
                    { value: 'einmalig' as const, label: t('book.single'), icon: Clock },
                    { value: 'mehrtaegig' as const, label: t('book.multiDay'), icon: CalendarBlank },
                    { value: 'dauer' as const, label: t('book.recurring'), icon: Repeat },
                  ]).map(({ value, label, icon: Icon }) => (
                    <button key={value} onClick={() => setBookingType(value)}
                      className={`py-3 px-4 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2 ${bookingType === value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}>
                      <Icon weight={bookingType === value ? 'fill' : 'regular'} className="w-4 h-4" />{label}
                    </button>
                  ))}
                </div>
              </div>

              {bookingType === 'einmalig' && (
                <div className="space-y-4">
                  <div>
                    <label className="label flex items-center gap-2"><CalendarBlank weight="regular" className="w-4 h-4" />{t('booking.selectDate')}</label>
                    <input type="date" value={bookingDate} onChange={(e) => setBookingDate(e.target.value)} min={format(new Date(), 'yyyy-MM-dd')} className="input max-w-xs" />
                  </div>
                  <div>
                    <label className="label flex items-center gap-2"><Clock weight="regular" className="w-4 h-4" />{t('booking.timeRange')}</label>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                      {([
                        { value: 'fullDay' as const, label: t('booking.fullDay'), icon: Sun, time: '08:00-18:00' },
                        { value: 'morning' as const, label: t('booking.morning'), icon: Sun, time: '08:00-12:00' },
                        { value: 'afternoon' as const, label: t('booking.afternoon'), icon: Moon, time: '12:00-18:00' },
                        { value: 'custom' as const, label: t('booking.custom'), icon: Clock, time: '' },
                      ]).map(({ value, label, icon: Icon }) => (
                        <button key={value} onClick={() => setTimeOption(value)}
                          className={`py-3 px-4 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2 ${timeOption === value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}>
                          <Icon weight={timeOption === value ? 'fill' : 'regular'} className="w-4 h-4" />{label}
                        </button>
                      ))}
                    </div>
                  </div>
                  {timeOption === 'custom' && (
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('booking.startTime')}</label>
                        <input type="time" value={customStartTime} onChange={(e) => setCustomStartTime(e.target.value)} className="input" />
                      </div>
                      <div>
                        <label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('booking.endTime')}</label>
                        <input type="time" value={customEndTime} onChange={(e) => setCustomEndTime(e.target.value)} className="input" />
                      </div>
                    </div>
                  )}
                </div>
              )}

              {bookingType === 'mehrtaegig' && (
                <div>
                  <label className="label flex items-center gap-2"><CalendarBlank weight="regular" className="w-4 h-4" />{t('book.period')}</label>
                  <div className="grid grid-cols-2 gap-4">
                    <div><label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('book.startDate')}</label><input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} min={format(new Date(), 'yyyy-MM-dd')} className="input" /></div>
                    <div><label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('book.endDate')}</label><input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} min={startDate} className="input" /></div>
                  </div>
                  {startDate && endDate && (
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
                      {t('book.days', { count: differenceInDays(new Date(endDate), new Date(startDate)) })} — {t('book.dateRange', { start: format(new Date(startDate), 'd. MMM', { locale: dateFnsLocale }), end: format(new Date(endDate), 'd. MMM yyyy', { locale: dateFnsLocale }) })}
                    </p>
                  )}
                </div>
              )}

              {bookingType === 'dauer' && (
                <div className="space-y-4">
                  <div>
                    <label className="label flex items-center gap-2"><Repeat weight="regular" className="w-4 h-4" />{t('book.interval')}</label>
                    <div className="grid grid-cols-2 gap-2">
                      <button onClick={() => setDauerInterval('weekly')} className={`py-2.5 px-4 rounded-xl text-sm font-medium transition-all ${dauerInterval === 'weekly' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}>{t('book.weekly')}</button>
                      <button onClick={() => setDauerInterval('monthly')} className={`py-2.5 px-4 rounded-xl text-sm font-medium transition-all ${dauerInterval === 'monthly' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}>{t('book.monthly')}</button>
                    </div>
                  </div>
                  {dauerInterval === 'weekly' && (
                    <div>
                      <label className="text-xs text-gray-500 dark:text-gray-400 mb-2 block">{t('book.weekdays')}</label>
                      <div className="flex gap-2">
                        {dayNames.map((d: string, i: number) => (
                          <button key={i} onClick={() => setDauerDays(prev => prev.includes(i) ? prev.filter(x => x !== i) : [...prev, i])}
                            className={`w-10 h-10 rounded-xl text-sm font-medium transition-all ${dauerDays.includes(i) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}>{d}</button>
                        ))}
                      </div>
                    </div>
                  )}
                  <div><label className="text-xs text-gray-500 dark:text-gray-400 mb-1 block">{t('book.startDate')}</label><input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} min={format(new Date(), 'yyyy-MM-dd')} className="input max-w-xs" /></div>
                </div>
              )}

              {/* Vehicle */}
              <div>
                <label className="label flex items-center gap-2"><Car weight="regular" className="w-4 h-4" />{t('book.vehicle')}</label>
                {vehicles.length > 0 && (
                  <select value={selectedVehicle} onChange={(e) => { setSelectedVehicle(e.target.value); setCustomPlate(''); }} className="input">
                    <option value="">{t('book.otherPlate')}</option>
                    {vehicles.map((v) => <option key={v.id} value={v.id}>{v.plate} {v.make && v.model ? `(${v.make} ${v.model})` : ''}</option>)}
                  </select>
                )}
                {!selectedVehicle && licensePlateEntryMode !== 2 && <div className="mt-2"><LicensePlateInput value={customPlate} onChange={setCustomPlate} required={licensePlateEntryMode === 1} /></div>}
                {!selectedVehicle && licensePlateEntryMode === 2 && <div className="mt-2 text-sm text-gray-500 dark:text-gray-400">{t('book.plateDisabled', 'License plate entry is disabled by the administrator.')}</div>}
                {!selectedVehicle && customPlate && (
                  <label className="flex items-center gap-2 mt-3 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                    <input type="checkbox" checked={saveVehicle} onChange={(e) => setSaveVehicle(e.target.checked)} className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <FloppyDisk weight="regular" className="w-4 h-4" />
                    {t("book.saveVehicleForFuture")}
                  </label>
                )}
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Summary */}
      <AnimatePresence>
        {selectedSlot && (
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} className="card bg-gradient-to-br from-primary-600 to-primary-700 p-6 text-white">
            <h2 className="text-lg font-semibold mb-4">{t('book.summary')}</h2>
            <div className="grid grid-cols-2 gap-4 mb-6">
              <div><p className="text-white/70 text-sm">{t('book.parkingLot')}</p><p className="font-medium">{selectedLotData?.name}</p></div>
              <div><p className="text-white/70 text-sm">{t('book.parkingSlot')}</p><p className="font-medium">{selectedSlot.number}</p></div>
              <div><p className="text-white/70 text-sm">{t('book.type')}</p><p className="font-medium">{bookingType === 'einmalig' ? t('book.single') : bookingType === 'mehrtaegig' ? t('book.multiDay') : t('book.recurring')}</p></div>
              <div>
                <p className="text-white/70 text-sm">{bookingType === 'einmalig' ? t('book.durationLabel') : bookingType === 'mehrtaegig' ? t('book.periodLabel') : t('book.intervalLabel')}</p>
                <p className="font-medium">
                  {bookingType === 'einmalig' && <>{format(new Date(bookingDate), 'd. MMM yyyy', { locale: dateFnsLocale })} — {timeOption === 'fullDay' ? t('booking.fullDay') : timeOption === 'morning' ? t('booking.morning') : timeOption === 'afternoon' ? t('booking.afternoon') : `${customStartTime} – ${customEndTime}`}</>}
                  {bookingType === 'mehrtaegig' && <>{format(new Date(startDate), 'd. MMM', { locale: dateFnsLocale })} — {format(new Date(endDate), 'd. MMM yyyy', { locale: dateFnsLocale })}</>}
                  {bookingType === 'dauer' && <>{dauerInterval === 'weekly' ? `${t('book.weeklyShort')} (${dauerDays.map(d => dayNames[d]).join(', ')})` : t('book.monthly')} ab {format(new Date(startDate), 'd. MMM yyyy', { locale: dateFnsLocale })}</>}
                </p>
              </div>
              <div className="col-span-2"><p className="text-white/70 text-sm">{t('book.licensePlate')}</p><p className="font-medium">{selectedVehicle ? vehicles.find(v => v.id === selectedVehicle)?.plate : customPlate || '—'}</p></div>
            </div>
            <button type="button" onClick={(e) => { e.preventDefault(); handleBook(); }} disabled={booking || (!selectedVehicle && licensePlateEntryMode === 1 && !customPlate)} className="btn bg-white text-primary-700 hover:bg-white/90 w-full justify-center">
              {booking ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" /> : <><CheckCircle weight="bold" className="w-5 h-5" />{t('book.bookNow')}</>}
            </button>
          </motion.div>
        )}
      </AnimatePresence>

      <BookingSuccessModal open={showSuccess} summary={successSummary} onDashboard={() => navigate('/')} onNewBooking={() => { setShowSuccess(false); setSelectedSlot(null); setSelectedLot(''); setDetailedLot(null); }} />
    </div>
  );
}
