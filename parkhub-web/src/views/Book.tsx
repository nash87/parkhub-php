import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'react-i18next';
import {
  ArrowLeft, MapPin, Clock, Car, SpinnerGap, Check,
  Lightning, Wheelchair, Motorcycle, Star,
  TrendUp, TrendDown,
} from '@phosphor-icons/react';
import {
  api,
  type ParkingLot,
  type ParkingSlot,
  type Vehicle,
  type CreateBookingPayload,
  type DynamicPriceResult,
  type OperatingHoursData,
} from '../api/client';
import { SkeletonCard } from '../components/Skeleton';
import toast from 'react-hot-toast';
import { useNavLayout } from '../hooks/useNavLayout';
import { useTheme } from '../context/ThemeContext';

type Step = 1 | 2 | 3;

const DURATIONS = [
  { label: '1h', hours: 1 },
  { label: '2h', hours: 2 },
  { label: '4h', hours: 4 },
  { label: '8h', hours: 8 },
];

export function BookPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [navLayout] = useNavLayout();
  const { designTheme } = useTheme();

  const [step, setStep] = useState<Step>(1);
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [slots, setSlots] = useState<ParkingSlot[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loadingLots, setLoadingLots] = useState(true);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [confirmed, setConfirmed] = useState(false);

  const [selectedLot, setSelectedLot] = useState<ParkingLot | null>(null);
  const [selectedSlot, setSelectedSlot] = useState<ParkingSlot | null>(null);
  const [dynamicPrice, setDynamicPrice] = useState<DynamicPriceResult | null>(null);
  const [selectedVehicle, setSelectedVehicle] = useState<string>('');
  const [startDate, setStartDate] = useState(() => {
    const now = new Date();
    now.setMinutes(0, 0, 0);
    now.setHours(now.getHours() + 1);
    return now.toISOString().slice(0, 16);
  });
  const [duration, setDuration] = useState(2);

  useEffect(() => {
    Promise.all([api.getLots(), api.getVehicles()])
      .then(([lRes, vRes]) => {
        if (lRes.success && lRes.data) setLots(lRes.data.filter((lot) => lot.status === 'open'));
        if (vRes.success && vRes.data) {
          setVehicles(vRes.data);
          const defaultVehicle = vRes.data.find((vehicle) => vehicle.is_default);
          if (defaultVehicle) setSelectedVehicle(defaultVehicle.id);
        }
      })
      .finally(() => setLoadingLots(false));
  }, []);

  async function selectLot(lot: ParkingLot) {
    setSelectedLot(lot);
    setSelectedSlot(null);
    setDynamicPrice(null);
    setLoadingSlots(true);
    setStep(2);

    const [slotsRes, priceRes] = await Promise.all([
      api.getLotSlots(lot.id),
      api.getDynamicPrice(lot.id),
    ]);

    if (slotsRes.success && slotsRes.data) setSlots(slotsRes.data);
    else {
      toast.error(t('common.error'));
      setSlots([]);
    }

    if (priceRes.success && priceRes.data) setDynamicPrice(priceRes.data);
    setLoadingSlots(false);
  }

  function goToConfirm() {
    if (selectedSlot) setStep(3);
  }

  function goBack() {
    if (step === 2) {
      setStep(1);
      setSelectedLot(null);
      setSelectedSlot(null);
    } else if (step === 3) {
      setStep(2);
    }
  }

  async function handleConfirm() {
    if (!selectedLot || !selectedSlot) return;

    setSubmitting(true);
    const start = new Date(startDate);
    const end = new Date(start.getTime() + duration * 60 * 60 * 1000);
    const payload: CreateBookingPayload = {
      lot_id: selectedLot.id,
      slot_id: selectedSlot.id,
      start_time: start.toISOString(),
      end_time: end.toISOString(),
      vehicle_id: selectedVehicle || undefined,
    };

    const res = await api.createBooking(payload);
    if (res.success) {
      setConfirmed(true);
      toast.success(t('book.success'));
      navigate('/bookings');
    } else {
      const message = res.error?.code === 'INSUFFICIENT_CREDITS'
        ? t('bookings.insufficientCredits')
        : res.error?.message || t('common.error');
      toast.error(message);
    }
    setSubmitting(false);
  }

  const start = new Date(startDate);
  const end = new Date(start.getTime() + duration * 60 * 60 * 1000);
  const effectiveRate = dynamicPrice?.dynamic_pricing_active ? dynamicPrice.current_price : selectedLot?.hourly_rate;
  const estimatedCost = effectiveRate != null ? (effectiveRate * duration).toFixed(2) : null;
  const surfaceVariant = getSurfaceVariant(designTheme, navLayout);
  const isVoid = surfaceVariant === 'void';
  const shell = getStepShell(step, surfaceVariant, t);

  const slideVariants = {
    enter: (dir: number) => ({ x: dir > 0 ? 80 : -80, opacity: 0 }),
    center: { x: 0, opacity: 1 },
    exit: (dir: number) => ({ x: dir > 0 ? -80 : 80, opacity: 0 }),
  };

  return (
    <div className="space-y-6">
      {confirmed && <ConfettiOverlay />}

      <div className={`overflow-hidden rounded-[2rem] border p-5 shadow-[0_24px_70px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800/90 bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.20),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.96))]'
          : 'border-stone-200/80 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.18),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.24),_transparent_40%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))]'
      }`}>
        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
          <div className="flex items-start gap-3">
            {step > 1 && (
              <button onClick={goBack} className="btn btn-ghost btn-sm mt-0.5 p-1.5" aria-label={t('common.back', 'Go back')}>
                <ArrowLeft weight="bold" className="h-5 w-5" aria-hidden="true" />
              </button>
            )}
            <div className="space-y-2">
              <div className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] shadow-sm ${
                isVoid
                  ? 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100'
                  : 'border-emerald-200/70 bg-white/85 text-emerald-700 dark:border-emerald-900/60 dark:bg-surface-950/65 dark:text-emerald-300'
              }`}>
                {shell.kicker}
              </div>
              <div>
                <h1 className={`text-3xl font-black ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`} style={{ letterSpacing: '-0.03em' }}>
                  {shell.title}
                </h1>
                <p className={`mt-1 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {shell.description}
                </p>
              </div>
            </div>
          </div>

          <nav aria-label={t('book.progress', 'Booking progress')} className={`grid grid-cols-3 gap-2 rounded-[1.5rem] border p-2 shadow-sm backdrop-blur ${
            isVoid
              ? 'border-slate-800 bg-slate-950/70'
              : 'border-white/70 bg-white/80 dark:border-surface-800 dark:bg-surface-950/70'
          }`}>
            {[1, 2, 3].map((s) => (
              <div
                key={s}
                aria-current={s === step ? 'step' : undefined}
                className={`min-w-[92px] rounded-[1.1rem] border px-3 py-3 text-left transition-all ${
                  s === step
                    ? 'border-primary-400 bg-primary-600 text-white shadow-lg shadow-primary-500/20'
                    : s < step
                    ? 'border-primary-200 bg-primary-50 text-primary-800 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-200'
                    : 'border-surface-200 bg-white/80 text-surface-500 dark:border-surface-800 dark:bg-surface-950/50 dark:text-surface-400'
                }`}
              >
                <div className="mb-2 flex items-center gap-2">
                  <div className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ${
                    s === step
                      ? 'bg-white/20 text-white'
                      : s < step
                      ? 'bg-primary-600 text-white dark:bg-primary-500'
                      : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                  }`}>
                    {s < step ? <Check weight="bold" className="h-3.5 w-3.5" aria-hidden="true" /> : s}
                  </div>
                  <span className="text-[11px] font-semibold uppercase tracking-[0.2em]">{t(`book.stepName${s}`)}</span>
                </div>
                <p className={`text-xs leading-5 ${
                  s === step
                    ? 'text-white/90'
                    : s < step
                    ? 'text-primary-700 dark:text-primary-300'
                    : 'text-surface-500 dark:text-surface-400'
                }`}>
                  {t(`book.step${s}Label`)}
                </p>
              </div>
            ))}
          </nav>
        </div>

        <div className={`mt-5 flex flex-col gap-3 border-t pt-4 lg:flex-row lg:items-center lg:justify-between ${
          isVoid ? 'border-slate-800' : 'border-white/70 dark:border-surface-800'
        }`}>
          <div className={`flex flex-wrap items-center gap-2 text-xs font-medium ${
            isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'
          }`}>
            <span className={`rounded-full px-3 py-1 ${isVoid ? 'bg-slate-950/70' : 'bg-white/80 dark:bg-surface-950/65'}`}>{t('book.progress', 'Booking progress')}</span>
            <span className={`rounded-full px-3 py-1 ${isVoid ? 'bg-slate-950/70' : 'bg-white/80 dark:bg-surface-950/65'}`}>{step}/3</span>
          </div>
          <div className={`h-2 w-full overflow-hidden rounded-full lg:max-w-sm ${isVoid ? 'bg-slate-950/70' : 'bg-white/80 dark:bg-surface-950/65'}`}>
            <motion.div
              className="h-full rounded-full bg-gradient-to-r from-teal-500 via-primary-500 to-amber-400"
              initial={{ width: `${(step / 3) * 100}%` }}
              animate={{ width: `${(step / 3) * 100}%` }}
              transition={{ duration: 0.35, ease: 'easeOut' }}
            />
          </div>
        </div>
      </div>

      <AnimatePresence mode="wait" custom={step}>
        {step === 1 && (
          <motion.div key="step-1" custom={1} variants={slideVariants} initial="enter" animate="center" exit="exit" transition={{ duration: 0.2 }}>
            <StepSelectLot lots={lots} loading={loadingLots} onSelect={selectLot} t={t} />
          </motion.div>
        )}
        {step === 2 && selectedLot && (
          <motion.div key="step-2" custom={2} variants={slideVariants} initial="enter" animate="center" exit="exit" transition={{ duration: 0.2 }}>
            <StepSelectSlot
              lot={selectedLot}
              slots={slots}
              loading={loadingSlots}
              selectedSlot={selectedSlot}
              onSelectSlot={setSelectedSlot}
              startDate={startDate}
              onStartDateChange={setStartDate}
              duration={duration}
              onDurationChange={setDuration}
              vehicles={vehicles}
              selectedVehicle={selectedVehicle}
              onVehicleChange={setSelectedVehicle}
              onContinue={goToConfirm}
              dynamicPrice={dynamicPrice}
              t={t}
            />
          </motion.div>
        )}
        {step === 3 && selectedLot && selectedSlot && (
          <motion.div key="step-3" custom={3} variants={slideVariants} initial="enter" animate="center" exit="exit" transition={{ duration: 0.2 }}>
            <StepConfirm
              lot={selectedLot}
              slot={selectedSlot}
              start={start}
              end={end}
              duration={duration}
              estimatedCost={estimatedCost}
              vehicle={vehicles.find((vehicle) => vehicle.id === selectedVehicle)}
              submitting={submitting}
              onConfirm={handleConfirm}
              t={t}
            />
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

function isLotOpenNow(hours?: OperatingHoursData): boolean {
  if (!hours || hours.is_24h) return true;

  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
  const now = new Date();
  const dayKey = days[now.getDay()];
  const dayHours = hours[dayKey as keyof OperatingHoursData] as { open: string; close: string; closed: boolean } | undefined;

  if (!dayHours || dayHours.closed) return false;

  const [openHour, openMinute] = dayHours.open.split(':').map(Number);
  const [closeHour, closeMinute] = dayHours.close.split(':').map(Number);
  const nowMinutes = now.getHours() * 60 + now.getMinutes();
  const openMinutes = openHour * 60 + openMinute;
  const closeMinutes = closeHour * 60 + closeMinute;

  if (closeMinutes > openMinutes) return nowMinutes >= openMinutes && nowMinutes < closeMinutes;
  return nowMinutes >= openMinutes || nowMinutes < closeMinutes;
}

interface Recommendation {
  slot_id: string;
  slot_number: number;
  lot_id: string;
  lot_name: string;
  floor_name: string;
  score: number;
  reasons: string[];
  reason_badges: string[];
}

const badgeLabels: Record<string, { label: string; color: string }> = {
  your_usual_spot: { label: 'Your usual spot', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
  best_price: { label: 'Best price', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
  closest_entrance: { label: 'Closest', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
  available_now: { label: 'Available now', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
  preferred_lot: { label: 'Preferred lot', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
  accessible: { label: 'Accessible', color: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' },
};

function RecommendationsSection({ lots, onSelect, t }: { lots: ParkingLot[]; onSelect: (lot: ParkingLot) => void; t: TFunction }) {
  const [recommendations, setRecommendations] = useState<Recommendation[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .getBookingRecommendations()
      .then((res) => {
        if (res.success && res.data) setRecommendations(res.data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading || recommendations.length === 0) return null;

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <Star weight="duotone" className="h-5 w-5 text-amber-500" />
        <h2 className="text-lg font-semibold text-surface-900 dark:text-white">{t('book.recommendedForYou', 'Recommended for you')}</h2>
      </div>
      <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        {recommendations.slice(0, 3).map((rec, index) => {
          const lot = lots.find((candidate) => candidate.id === rec.lot_id);
          return (
            <motion.button
              key={rec.slot_id}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05 }}
              whileHover={{ scale: 1.01 }}
              whileTap={{ scale: 0.99 }}
              onClick={() => lot && onSelect(lot)}
              className="rounded-[1.4rem] border border-amber-200 bg-white/90 p-4 text-left shadow-[0_18px_50px_-38px_rgba(245,158,11,0.45)] transition-all hover:border-amber-400 dark:border-amber-800/50 dark:bg-surface-950/80"
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-surface-900 dark:text-white">{rec.lot_name} - Slot {rec.slot_number}</p>
                  <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{rec.floor_name}</p>
                </div>
                <div className="flex items-center gap-0.5">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                      key={star}
                      weight={star <= Math.round(rec.score / 20) ? 'fill' : 'regular'}
                      className={`h-3 w-3 ${star <= Math.round(rec.score / 20) ? 'text-amber-500' : 'text-surface-300'}`}
                    />
                  ))}
                </div>
              </div>
              <div className="mt-3 flex flex-wrap gap-1.5">
                {rec.reason_badges.map((badge) => {
                  const info = badgeLabels[badge] || { label: badge, color: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300' };
                  return <span key={badge} className={`rounded-full px-2 py-1 text-[10px] font-semibold ${info.color}`}>{info.label}</span>;
                })}
              </div>
            </motion.button>
          );
        })}
      </div>
    </div>
  );
}

function StepSelectLot({ lots, loading, onSelect, t }: {
  lots: ParkingLot[];
  loading: boolean;
  onSelect: (lot: ParkingLot) => void;
  t: TFunction;
}) {
  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {[1, 2, 3].map((index) => <SkeletonCard key={index} height="h-36" />)}
      </div>
    );
  }

  if (lots.length === 0) {
    return (
      <div className="glass-card p-8">
        <p className="text-surface-500 dark:text-surface-400">{t('book.noLots')}</p>
      </div>
    );
  }

  return (
    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_320px]">
      <div className="space-y-6">
        <RecommendationsSection lots={lots} onSelect={onSelect} t={t} />
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {lots.map((lot, index) => {
            const occupancy = lot.total_slots > 0 ? Math.round(((lot.total_slots - lot.available_slots) / lot.total_slots) * 100) : 0;
            const openNow = lot.operating_hours && !lot.operating_hours.is_24h ? isLotOpenNow(lot.operating_hours) : null;
            return (
              <motion.button
                key={lot.id}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.05, type: 'spring', stiffness: 300, damping: 24 }}
                whileHover={{ scale: 1.01, y: -2 }}
                whileTap={{ scale: 0.99 }}
                onClick={() => onSelect(lot)}
                className="group rounded-[1.75rem] border border-surface-200 bg-white p-5 text-left shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] transition-all hover:border-primary-400/60 hover:shadow-[0_24px_60px_-34px_rgba(20,184,166,0.45)] dark:border-surface-800 dark:bg-surface-950/80 dark:hover:border-primary-700/60"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-lg font-bold text-surface-900 dark:text-white">{lot.name}</p>
                    {lot.address && (
                      <p className="mt-1 flex items-center gap-1.5 text-sm text-surface-500 dark:text-surface-400">
                        <MapPin weight="regular" className="h-3.5 w-3.5 shrink-0" />
                        {lot.address}
                      </p>
                    )}
                  </div>
                  {openNow != null && (
                    <span className={`rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] ${
                      openNow
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                    }`}>
                      {openNow ? t('book.openNow', 'Open now') : t('book.closedNow', 'Closed now')}
                    </span>
                  )}
                </div>

                <div className="mt-5 rounded-[1.25rem] bg-surface-50 p-4 dark:bg-surface-900/70">
                  <div className="flex items-end justify-between gap-4">
                    <div>
                      <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.available', 'Available')}</p>
                      <p className="mt-1 text-3xl font-black text-surface-900 dark:text-white">{lot.available_slots}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.occupancy', 'Occupancy')}</p>
                      <p className="mt-1 text-lg font-bold text-surface-700 dark:text-surface-200">{occupancy}%</p>
                    </div>
                  </div>
                  <div className="mt-3 h-2 overflow-hidden rounded-full bg-white dark:bg-surface-950">
                    <div className="h-full rounded-full bg-gradient-to-r from-teal-500 to-primary-500" style={{ width: `${Math.max(8, 100 - occupancy)}%` }} />
                  </div>
                  <div className="mt-3 flex items-center justify-between text-sm text-surface-500 dark:text-surface-400">
                    <span>{t('book.availableSlots', { count: lot.available_slots, total: lot.total_slots })}</span>
                    {lot.available_slots === 0 && <span className="font-semibold text-red-600 dark:text-red-400">{t('book.full')}</span>}
                  </div>
                </div>

                <div className="mt-4 flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.rate', 'Rate')}</p>
                    <p className="mt-1 text-sm font-semibold text-surface-900 dark:text-white">
                      {lot.hourly_rate != null ? `${lot.currency || '€'}${lot.hourly_rate.toFixed(2)}/h` : t('book.rateOnRequest', 'Rate on request')}
                    </p>
                  </div>
                  <span className="rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 transition-colors group-hover:border-primary-400 group-hover:bg-primary-100 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-300">
                    {t('book.selectLotCta', 'Choose lot')}
                  </span>
                </div>
              </motion.button>
            );
          })}
        </div>
      </div>

      <aside className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
        <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-surface-400 dark:text-surface-500">{t('book.selectionGuide', 'Selection guide')}</p>
        <h2 className="mt-2 text-xl font-black text-surface-900 dark:text-white" style={{ letterSpacing: '-0.03em' }}>
          {t('book.step1AsideTitle', 'Find the right lot')}
        </h2>
        <p className="mt-2 text-sm leading-6 text-surface-600 dark:text-surface-300">
          {t('book.step1Aside', 'Start with the lot that best fits your day. Step 2 lets you set the timing, vehicle, and exact spot.')}
        </p>
        <div className="mt-5 space-y-3">
          {[
            t('book.step1Guide1', 'Check availability before you commit.'),
            t('book.step1Guide2', 'Compare live rates and operating status.'),
            t('book.step1Guide3', 'Move forward after you have a lot selected.'),
          ].map((item) => (
            <div key={item} className="flex items-start gap-3 rounded-2xl bg-surface-50 px-4 py-3 dark:bg-surface-900/70">
              <div className="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-primary-600 text-white">
                <Check weight="bold" className="h-3.5 w-3.5" />
              </div>
              <p className="text-sm text-surface-600 dark:text-surface-300">{item}</p>
            </div>
          ))}
        </div>
      </aside>
    </div>
  );
}

const SLOT_TYPE_ICON: Record<string, React.ComponentType<{ weight?: string; className?: string }>> = {
  electric: Lightning,
  handicap: Wheelchair,
  motorcycle: Motorcycle,
  vip: Star,
};

function StepSelectSlot({ lot, slots, loading, selectedSlot, onSelectSlot,
  startDate, onStartDateChange, duration, onDurationChange,
  vehicles, selectedVehicle, onVehicleChange, onContinue, dynamicPrice, t }: {
  lot: ParkingLot;
  slots: ParkingSlot[];
  loading: boolean;
  selectedSlot: ParkingSlot | null;
  onSelectSlot: (slot: ParkingSlot) => void;
  startDate: string;
  onStartDateChange: (value: string) => void;
  duration: number;
  onDurationChange: (value: number) => void;
  vehicles: Vehicle[];
  selectedVehicle: string;
  onVehicleChange: (value: string) => void;
  onContinue: () => void;
  dynamicPrice: DynamicPriceResult | null;
  t: TFunction;
}) {
  const available = slots.filter((slot) => slot.status === 'available');
  const start = new Date(startDate);
  const end = new Date(start.getTime() + duration * 60 * 60 * 1000);
  const effectiveRate = dynamicPrice?.dynamic_pricing_active ? dynamicPrice.current_price : lot.hourly_rate;
  const rateLabel = effectiveRate != null ? `${lot.currency || dynamicPrice?.currency || '€'}${effectiveRate.toFixed(2)}/h` : '—';
  const estimatedCost = effectiveRate != null ? `${lot.currency || dynamicPrice?.currency || '€'}${(effectiveRate * duration).toFixed(2)}` : null;
  const groupedSlots = groupSlotsByZone(slots);
  const selectedVehicleRecord = vehicles.find((vehicle) => vehicle.id === selectedVehicle);

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_360px]">
      <div className="space-y-6">
        <div className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-surface-400 dark:text-surface-500">{t('book.plannerLabel', 'Planner')}</p>
              <h2 className="mt-2 text-2xl font-black text-surface-900 dark:text-white" style={{ letterSpacing: '-0.03em' }}>{lot.name}</h2>
              <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{t('book.availableSlots', { count: available.length, total: slots.length })}</p>
            </div>
            {dynamicPrice?.dynamic_pricing_active && (
              <div className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold ${
                dynamicPrice.tier === 'surge'
                  ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                  : dynamicPrice.tier === 'discount'
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                  : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400'
              }`}>
                {dynamicPrice.tier === 'surge' && <TrendUp weight="bold" className="h-3.5 w-3.5" />}
                {dynamicPrice.tier === 'discount' && <TrendDown weight="bold" className="h-3.5 w-3.5" />}
                <span>
                  {dynamicPrice.tier === 'surge'
                    ? t('book.surgePricing', 'Surge pricing')
                    : dynamicPrice.tier === 'discount'
                    ? t('book.discountPricing', 'Discount pricing')
                    : t('book.livePricing', 'Live pricing')}
                </span>
                <span>{dynamicPrice.currency}{dynamicPrice.current_price.toFixed(2)}/h</span>
              </div>
            )}
          </div>

          <div className="mt-5 grid gap-4 md:grid-cols-2">
            <div className="rounded-[1.25rem] bg-surface-50 p-4 dark:bg-surface-900/70">
              <label htmlFor="book-start-time" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                <Clock weight="regular" className="mr-1 inline h-4 w-4" aria-hidden="true" />
                {t('book.startTime')}
              </label>
              <input id="book-start-time" type="datetime-local" value={startDate} onChange={(event) => onStartDateChange(event.target.value)} className="input mt-3 text-sm" />
              <div className="mt-3 rounded-2xl border border-white/70 bg-white/90 px-4 py-3 text-sm text-surface-600 shadow-sm dark:border-surface-800 dark:bg-surface-950/80 dark:text-surface-300">
                <div className="text-[11px] font-semibold uppercase tracking-[0.22em] text-surface-400 dark:text-surface-500">{t('book.window', 'Window')}</div>
                <div className="mt-1 font-semibold text-surface-900 dark:text-white">{formatDateTimeSummary(start)} - {formatTimeOnly(end)}</div>
              </div>
            </div>

            <div className="rounded-[1.25rem] bg-surface-50 p-4 dark:bg-surface-900/70">
              <span id="duration-label" className="block text-sm font-medium text-surface-700 dark:text-surface-300">{t('book.duration')}</span>
              <div className="mt-3 flex gap-2" role="group" aria-labelledby="duration-label">
                {DURATIONS.map((item) => (
                  <motion.button
                    key={item.hours}
                    onClick={() => onDurationChange(item.hours)}
                    whileHover={{ scale: 1.03 }}
                    whileTap={{ scale: 0.98 }}
                    aria-pressed={duration === item.hours}
                    className={`flex-1 rounded-xl border py-2 text-sm font-medium transition-all ${
                      duration === item.hours
                        ? 'bg-teal-600 border-teal-600 text-white shadow-md shadow-teal-500/20'
                        : 'bg-white border-surface-200 text-surface-700 hover:border-teal-400 dark:bg-surface-950/80 dark:border-surface-800 dark:text-surface-300'
                    }`}
                  >
                    {item.label}
                  </motion.button>
                ))}
              </div>
              <div className="mt-3 rounded-2xl bg-white px-4 py-3 text-sm shadow-sm dark:bg-surface-950/80">
                <div className="flex items-center justify-between">
                  <span className="text-surface-500 dark:text-surface-400">{t('book.estimatedCost')}</span>
                  <span className="font-semibold text-surface-900 dark:text-white">{estimatedCost || '—'}</span>
                </div>
              </div>
            </div>
          </div>

          {vehicles.length > 0 && (
            <div className="mt-4 rounded-[1.25rem] bg-surface-50 p-4 dark:bg-surface-900/70">
              <label htmlFor="book-vehicle" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                <Car weight="regular" className="mr-1 inline h-4 w-4" aria-hidden="true" />
                {t('book.vehicle')}
              </label>
              <select id="book-vehicle" value={selectedVehicle} onChange={(event) => onVehicleChange(event.target.value)} className="input mt-3 text-sm">
                <option value="">{t('book.noVehicle')}</option>
                {vehicles.map((vehicle) => <option key={vehicle.id} value={vehicle.id}>{formatVehicleOption(vehicle)}</option>)}
              </select>
            </div>
          )}
        </div>

        <div className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-surface-400 dark:text-surface-500">{t('book.slotPlannerLabel', 'Slot planner')}</p>
              <h3 className="mt-2 text-xl font-black text-surface-900 dark:text-white" style={{ letterSpacing: '-0.03em' }}>{t('book.selectSlot')}</h3>
            </div>
            <div className="flex flex-wrap gap-2 text-xs text-surface-500 dark:text-surface-400">
              {[
                { label: t('book.available', 'Available'), tone: 'bg-teal-500' },
                { label: t('book.occupied', 'Occupied'), tone: 'bg-red-400' },
                { label: t('book.electric', 'Electric'), tone: 'bg-amber-400' },
                { label: t('book.accessible', 'Accessible'), tone: 'bg-blue-500' },
              ].map((item) => (
                <span key={item.label} className="inline-flex items-center gap-2 rounded-full bg-surface-50 px-3 py-1 dark:bg-surface-900/70">
                  <span className={`h-2.5 w-2.5 rounded-sm ${item.tone}`} />
                  {item.label}
                </span>
              ))}
            </div>
          </div>

          {loading ? (
            <div className="mt-5 grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
              {Array.from({ length: 12 }, (_, index) => <div key={index} className="h-14 rounded-xl skeleton" />)}
            </div>
          ) : (
            <div className="mt-5 space-y-5">
              {groupedSlots.map((group) => (
                <div key={group.zone} className="rounded-[1.25rem] border border-surface-200 bg-surface-50/90 p-4 dark:border-surface-800 dark:bg-surface-900/50">
                  <div className="mb-3 flex items-center justify-between">
                    <div>
                      <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-surface-400 dark:text-surface-500">{t('book.zone', 'Zone')} {group.zone}</p>
                      <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{group.availableCount}/{group.slots.length} {t('book.available', 'Available')}</p>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-3">
                    {group.slots.map((slot) => {
                      const isAvailable = slot.status === 'available';
                      const isSelected = selectedSlot?.id === slot.id;
                      const Icon = slot.slot_type ? SLOT_TYPE_ICON[slot.slot_type] : null;
                      const tone = slot.is_accessible
                        ? 'border-blue-300'
                        : slot.slot_type === 'electric'
                        ? 'border-amber-300'
                        : isAvailable
                        ? 'border-teal-300'
                        : 'border-red-200';

                      return (
                        <motion.button
                          key={slot.id}
                          disabled={!isAvailable}
                          onClick={() => onSelectSlot(slot)}
                          whileHover={isAvailable ? { scale: 1.05 } : {}}
                          whileTap={isAvailable ? { scale: 0.98 } : {}}
                          aria-pressed={isSelected}
                          aria-label={`${t('book.slot', 'Slot')} ${slot.slot_number}${slot.slot_type ? ` (${slot.slot_type})` : ''} — ${slot.status}`}
                          title={`${slot.slot_number}${slot.slot_type ? ` (${slot.slot_type})` : ''} — ${slot.status}`}
                          className={`relative flex h-16 w-[84px] flex-col items-center justify-center rounded-2xl border-2 text-sm font-semibold transition-all ${
                            isSelected
                              ? 'border-primary-500 bg-primary-600 text-white shadow-lg shadow-primary-500/20'
                              : isAvailable
                              ? `${tone} bg-white text-surface-900 hover:border-primary-400 dark:bg-surface-950/80 dark:text-surface-100`
                              : 'cursor-not-allowed border-surface-200 bg-surface-100 text-surface-400 opacity-60 dark:border-surface-800 dark:bg-surface-900/60'
                          }`}
                        >
                          <span>{slot.slot_number}</span>
                          {Icon && <Icon weight="bold" className={`absolute right-2 top-2 h-3.5 w-3.5 ${isSelected ? 'opacity-90' : 'opacity-60'}`} />}
                          {slot.is_accessible && !Icon && <Wheelchair weight="bold" className={`absolute right-2 top-2 h-3.5 w-3.5 ${isSelected ? 'opacity-90' : 'opacity-60 text-blue-500'}`} />}
                        </motion.button>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          )}

          {!loading && available.length === 0 && <p className="mt-3 text-sm text-surface-500 dark:text-surface-400">{t('book.noAvailableSlots')}</p>}
        </div>
      </div>

      <aside className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
        <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-surface-400 dark:text-surface-500">{t('book.liveEstimate', 'Live estimate')}</p>
        <h3 className="mt-2 text-2xl font-black text-surface-900 dark:text-white" style={{ letterSpacing: '-0.03em' }}>{t('book.summaryTitle', 'Booking summary')}</h3>
        <p className="mt-2 text-sm leading-6 text-surface-600 dark:text-surface-300">{t('book.summaryHint', 'Review the live estimate before you continue')}</p>

        <div className={`mt-5 rounded-[1.5rem] border px-4 py-4 ${
          selectedSlot
            ? 'border-primary-200 bg-primary-50 dark:border-primary-900/60 dark:bg-primary-950/35'
            : 'border-dashed border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/60'
        }`}>
          <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.selectedSlot', 'Selected slot')}</p>
          <div className="mt-2 flex items-end justify-between gap-3">
            <div>
              <p className="text-3xl font-black text-surface-900 dark:text-white">{selectedSlot?.slot_number || '—'}</p>
              <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{selectedSlot ? lot.name : t('book.selectSlotPlaceholder', 'Select a spot to continue')}</p>
            </div>
            {selectedSlot?.slot_type === 'electric' && <Lightning weight="bold" className="h-5 w-5 text-amber-500" />}
            {selectedSlot?.is_accessible && selectedSlot?.slot_type !== 'electric' && <Wheelchair weight="bold" className="h-5 w-5 text-blue-500" />}
          </div>
        </div>

        <div className="mt-5 space-y-3">
          <SummaryBlock label={t('book.selectedLot', 'Selected lot')} value={lot.name} />
          <SummaryBlock label={t('book.from')} value={formatDateTimeSummary(start)} />
          <SummaryBlock label={t('book.to')} value={formatTimeOnly(end)} />
          <SummaryBlock label={t('book.duration')} value={`${duration}h`} />
          <SummaryBlock label={t('book.vehicle')} value={selectedVehicleRecord ? formatVehicleOption(selectedVehicleRecord) : t('book.noVehicle')} />
          <SummaryBlock label={t('book.rate', 'Rate')} value={rateLabel} />
          <SummaryBlock label={t('book.estimatedCost')} value={estimatedCost || '—'} emphasis />
        </div>

        <motion.button
          onClick={onContinue}
          disabled={!selectedSlot}
          whileHover={selectedSlot ? { scale: 1.01 } : {}}
          whileTap={selectedSlot ? { scale: 0.99 } : {}}
          className="btn btn-primary mt-6 w-full shadow-lg shadow-primary-500/15"
        >
          {t('book.continue')}
        </motion.button>
      </aside>
    </div>
  );
}

function StepConfirm({ lot, slot, start, end, duration, estimatedCost, vehicle, submitting, onConfirm, t }: {
  lot: ParkingLot;
  slot: ParkingSlot;
  start: Date;
  end: Date;
  duration: number;
  estimatedCost: string | null;
  vehicle?: Vehicle;
  submitting: boolean;
  onConfirm: () => void;
  t: TFunction;
}) {
  const fmt = (date: Date) => date.toLocaleString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  const rate = estimatedCost ? `${lot.currency || '€'}${(Number.parseFloat(estimatedCost) / duration).toFixed(2)}/h` : '—';

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_360px]">
      <div className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
        <div className="rounded-[1.5rem] bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.18),_transparent_38%),linear-gradient(135deg,rgba(15,23,42,0.02),rgba(20,184,166,0.08))] p-6 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.24),_transparent_40%),linear-gradient(135deg,rgba(15,23,42,0.6),rgba(20,184,166,0.12))]">
          <div className="inline-flex rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-primary-700 shadow-sm dark:bg-surface-950/70 dark:text-primary-300">
            {t('book.finalReview', 'Final review')}
          </div>
          <h2 className="mt-3 text-3xl font-black text-surface-900 dark:text-white" style={{ letterSpacing: '-0.03em' }}>
            {t('book.readyTitle', 'Ready to confirm')}
          </h2>
          <p className="mt-2 max-w-xl text-sm leading-6 text-surface-600 dark:text-surface-300">
            {t('book.readyHint', 'Review your booking details before submitting')}
          </p>
        </div>

        <div className="mt-5 grid gap-4 sm:grid-cols-2">
          <div className="rounded-[1.25rem] border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/60">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.slot')}</p>
            <p className="mt-2 text-3xl font-black text-surface-900 dark:text-white">{slot.slot_number}</p>
            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{lot.name}</p>
          </div>
          <div className="rounded-[1.25rem] border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/60">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('book.estimatedCost')}</p>
            <p className="mt-2 text-3xl font-black text-surface-900 dark:text-white">{estimatedCost ? `${lot.currency || '€'}${estimatedCost}` : '—'}</p>
            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{t('book.duration')} · {duration}h</p>
          </div>
        </div>

        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          <SummaryTile label={t('book.from')} value={fmt(start)} />
          <SummaryTile label={t('book.to')} value={fmt(end)} />
          <SummaryTile label={t('book.vehicle')} value={vehicle ? formatVehicleConfirm(vehicle) : t('book.noVehicle')} />
          <SummaryTile label={t('book.rate', 'Rate')} value={rate} />
        </div>
      </div>

      <aside className="rounded-[1.75rem] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.6)] dark:border-surface-800 dark:bg-surface-950/80">
        <div className="glass-card divide-y divide-surface-100 dark:divide-surface-800" role="region" aria-label={t('book.summary', 'Booking summary')}>
          <SummaryRow label={t('book.lot')} value={lot.name} />
          <SummaryRow label={t('book.slot')} value={slot.slot_number} />
          <SummaryRow label={t('book.from')} value={fmt(start)} />
          <SummaryRow label={t('book.to')} value={fmt(end)} />
          <SummaryRow label={t('book.duration')} value={`${duration}h`} />
          {vehicle && <SummaryRow label={t('book.vehicle')} value={formatVehicleConfirm(vehicle)} />}
          {estimatedCost && <SummaryRow label={t('book.estimatedCost')} value={`${lot.currency || '€'}${estimatedCost}`} bold />}
        </div>
        <motion.button
          onClick={onConfirm}
          disabled={submitting}
          whileHover={!submitting ? { scale: 1.01 } : {}}
          whileTap={!submitting ? { scale: 0.99 } : {}}
          className={`btn btn-primary mt-6 w-full shadow-lg shadow-primary-500/15 ${submitting ? 'btn-shimmer' : ''}`}
        >
          {submitting
            ? <><SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> {t('book.confirming')}</>
            : <><Check weight="bold" className="h-4 w-4" /> {t('book.confirm')}</>}
        </motion.button>
      </aside>
    </div>
  );
}

function SummaryRow({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <div className="flex items-center justify-between px-5 py-3">
      <span className="text-sm text-surface-500 dark:text-surface-400">{label}</span>
      <span className={`text-sm ${bold ? 'font-bold text-surface-900 dark:text-white' : 'font-medium text-surface-900 dark:text-white'}`}>{value}</span>
    </div>
  );
}

function SummaryBlock({ label, value, emphasis }: { label: string; value: string; emphasis?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl bg-surface-50 px-4 py-3 dark:bg-surface-900/60">
      <span className="text-sm text-surface-500 dark:text-surface-400">{label}</span>
      <span className={`text-right text-sm ${emphasis ? 'font-bold text-surface-900 dark:text-white' : 'font-semibold text-surface-900 dark:text-white'}`}>{value}</span>
    </div>
  );
}

function SummaryTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[1.1rem] border border-surface-200 bg-white px-4 py-3 dark:border-surface-800 dark:bg-surface-950/80">
      <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{label}</p>
      <p className="mt-2 text-sm font-semibold text-surface-900 dark:text-white">{value}</p>
    </div>
  );
}

function getSurfaceVariant(designTheme: string, navLayout: ReturnType<typeof useNavLayout>[0]): 'marble' | 'void' {
  if (designTheme === 'void' || navLayout === 'focus') return 'void';
  return 'marble';
}

function getStepShell(step: Step, surfaceVariant: 'marble' | 'void', t: TFunction) {
  if (surfaceVariant === 'void') {
    return {
      kicker: 'Booking studio',
      title: t('book.title'),
      description: '3-step reservation flow',
    };
  }

  switch (step) {
    case 1:
      return {
        kicker: t('book.shellLotKicker', 'Lot selection'),
        title: t('book.title'),
        description: t('book.step1Hint', 'Choose an open lot with the right availability and price for your stay.'),
      };
    case 2:
      return {
        kicker: t('book.detailsTitle', 'Booking details'),
        title: t('book.selectionTitle', 'Pick your parking space'),
        description: t('book.step2Hint', 'Set your time window, review pricing, and choose the exact slot you want.'),
      };
    default:
      return {
        kicker: t('book.finalReview', 'Final review'),
        title: t('book.summaryTitle', 'Booking summary'),
        description: t('book.step3Hint', 'Review timing, slot, vehicle, and price before you submit.'),
      };
  }
}

function formatDateTimeSummary(date: Date): string {
  return date.toLocaleString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatTimeOnly(date: Date): string {
  return date.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatVehicleOption(vehicle: Vehicle): string {
  return `${vehicle.plate}${vehicle.make ? ` — ${vehicle.make}${vehicle.model ? ` ${vehicle.model}` : ''}` : ''}`;
}

function formatVehicleConfirm(vehicle: Vehicle): string {
  return `${vehicle.plate}${vehicle.make ? ` (${vehicle.make})` : ''}`;
}

function groupSlotsByZone(slots: ParkingSlot[]): Array<{ zone: string; slots: ParkingSlot[]; availableCount: number }> {
  const grouped = new Map<string, ParkingSlot[]>();

  slots.forEach((slot) => {
    const zone = slot.zone_id || slot.slot_number.match(/[A-Za-z]+/)?.[0]?.toUpperCase() || 'General';
    const existing = grouped.get(zone) || [];
    existing.push(slot);
    grouped.set(zone, existing);
  });

  return Array.from(grouped.entries())
    .sort(([zoneA], [zoneB]) => zoneA.localeCompare(zoneB))
    .map(([zone, zoneSlots]) => ({
      zone,
      slots: zoneSlots.sort((left, right) => left.slot_number.localeCompare(right.slot_number, undefined, { numeric: true })),
      availableCount: zoneSlots.filter((slot) => slot.status === 'available').length,
    }));
}

function ConfettiOverlay() {
  const colors = ['#14b8a6', '#f59e0b', '#6366f1', '#ec4899', '#22c55e', '#3b82f6'];
  const pieces = Array.from({ length: 24 }, (_, index) => ({
    id: index,
    color: colors[index % colors.length],
    left: `${Math.random() * 100}%`,
    delay: Math.random() * 0.5,
    duration: 1 + Math.random() * 1.5,
    size: 4 + Math.random() * 6,
  }));

  return (
    <div className="pointer-events-none fixed inset-0 z-50 overflow-hidden" aria-hidden="true">
      {pieces.map((piece) => (
        <motion.div
          key={piece.id}
          className="absolute rounded-sm"
          style={{ left: piece.left, top: -10, width: piece.size, height: piece.size, backgroundColor: piece.color }}
          initial={{ y: -20, rotate: 0, opacity: 1 }}
          animate={{ y: '100vh', rotate: 720, opacity: 0 }}
          transition={{ duration: piece.duration, delay: piece.delay, ease: 'easeIn' }}
        />
      ))}
    </div>
  );
}
