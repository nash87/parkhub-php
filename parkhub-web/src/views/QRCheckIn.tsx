import { useEffect, useState, useRef, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  QrCode, SignIn, SignOut, SpinnerGap, Clock,
  MapPin, CalendarBlank, ArrowClockwise,
} from '@phosphor-icons/react';
import toast from 'react-hot-toast';
import { format } from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import { api, getInMemoryToken, type Booking } from '../api/client';
import { stagger, fadeUp } from '../constants/animations';
import { useTheme } from '../context/ThemeContext';

function authHeaders(): Record<string, string> {
  const token = getInMemoryToken();
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

interface CheckInStatus {
  checked_in: boolean;
  checked_in_at: string | null;
  checked_out_at: string | null;
}

export function QRCheckInPage() {
  const { t, i18n } = useTranslation();
  const { designTheme } = useTheme();
  const dateFnsLocale = i18n.language?.startsWith('de') ? de : enUS;
  const [booking, setBooking] = useState<Booking | null>(null);
  const [checkInStatus, setCheckInStatus] = useState<CheckInStatus | null>(null);
  const [qrUrl, setQrUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const [elapsed, setElapsed] = useState('');
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  async function loadData() {
    setLoading(true);
    try {
      const bookingsRes = await api.getBookings();
      if (bookingsRes.success && bookingsRes.data) {
        const now = Date.now();
        const active = bookingsRes.data.find(
          b => (b.status === 'active' || b.status === 'confirmed') &&
               new Date(b.start_time).getTime() <= now &&
               new Date(b.end_time).getTime() > now,
        );
        if (active) {
          setBooking(active);
          // Load check-in status
          const statusRes = await fetch(`/api/v1/bookings/${active.id}/check-in`, { headers: authHeaders(), credentials: 'include' }).then(r => r.json());
          if (statusRes.success && statusRes.data) {
            setCheckInStatus(statusRes.data);
          } else {
            setCheckInStatus({ checked_in: false, checked_in_at: null, checked_out_at: null });
          }
          // Load QR code (check r.ok to avoid rendering error HTML as image)
          try {
            const qrRes = await fetch(`/api/v1/bookings/${active.id}/qr`, { headers: authHeaders(), credentials: 'include' });
            if (qrRes.ok) {
              const qrBlob = await qrRes.blob();
              setQrUrl(URL.createObjectURL(qrBlob));
            }
          /* istanbul ignore next -- network failure path */
          } catch {
            // QR endpoint may not be compiled — non-critical
          }
        } else {
          setBooking(null);
          setCheckInStatus(null);
        }
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setLoading(false);
  }

  useEffect(() => { void loadData(); }, []);

  // Elapsed timer
  useEffect(() => {
    if (timerRef.current) clearInterval(timerRef.current);
    if (checkInStatus?.checked_in && checkInStatus.checked_in_at && !checkInStatus.checked_out_at) {
      const update = () => {
        const diff = Date.now() - new Date(checkInStatus.checked_in_at!).getTime();
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        setElapsed(`${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`);
      };
      update();
      timerRef.current = setInterval(update, 1000);
    }
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [checkInStatus]);

  // Revoke QR object URL on unmount
  useEffect(() => {
    return () => { if (qrUrl) URL.revokeObjectURL(qrUrl); };
  }, [qrUrl]);

  const isVoid = designTheme === 'void';
  const activeStateLabel = checkInStatus?.checked_in && !checkInStatus?.checked_out_at
    ? t('checkin.checkedIn')
    : t('checkin.checkInBtn');

  async function handleCheckIn() {
    /* istanbul ignore next -- defensive guard; UI only renders this button when booking exists */
    if (!booking) return;
    setActing(true);
    try {
      const res = await fetch(`/api/v1/bookings/${booking.id}/check-in`, {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'include',
      }).then(r => r.json());
      if (res.success) {
        toast.success(t('checkin.checkedIn'));
        loadData();
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setActing(false);
  }

  async function handleCheckOut() {
    /* istanbul ignore next -- defensive guard; UI only renders this button when booking exists */
    if (!booking) return;
    setActing(true);
    try {
      const res = await fetch(`/api/v1/bookings/${booking.id}/check-out`, {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'include',
      }).then(r => r.json());
      if (res.success) {
        toast.success(t('checkin.checkedOut'));
        loadData();
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setActing(false);
  }

  if (loading) {
    return (
      <div className="flex justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-2 border-primary-500 border-t-transparent" />
      </div>
    );
  }

  return (
    <motion.div
      variants={stagger}
      initial="hidden"
      animate="show"
      className="space-y-6 max-w-5xl mx-auto"
      data-testid="checkin-shell"
      data-surface={isVoid ? 'void' : 'marble'}
    >
      <motion.div
        variants={fadeUp}
        className={`overflow-hidden rounded-[30px] border px-6 py-6 shadow-[0_24px_70px_-42px_rgba(15,23,42,0.45)] ${
          isVoid
            ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
            : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.14),_transparent_34%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.22),_transparent_36%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
        }`}
      >
        <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <div className="space-y-5">
            <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100'
                : 'border-emerald-200/80 bg-white/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <QrCode weight="duotone" className="h-4 w-4" />
              {isVoid ? 'Void check-in flow' : 'Marble arrival lane'}
            </div>

            <div className="flex items-start justify-between gap-4">
              <div>
                <h1 className="text-3xl font-black tracking-[-0.04em]">{t('checkin.title')}</h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('checkin.subtitle')}
                </p>
              </div>
              <button onClick={loadData} className={`inline-flex h-11 w-11 items-center justify-center rounded-2xl border transition ${
                isVoid
                  ? 'border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08]'
                  : 'border-white/80 bg-white/85 text-surface-700 hover:bg-white dark:border-white/10 dark:bg-white/[0.04] dark:text-white'
              }`}>
                <ArrowClockwise weight="bold" className="h-4 w-4" />
              </button>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <CheckinStatCard
                label={t('dashboard.slot')}
                value={booking?.slot_number ?? '—'}
                meta={booking?.lot_name ?? t('checkin.noBooking')}
                isVoid={isVoid}
                accent
              />
              <CheckinStatCard
                label={t('checkin.date')}
                value={booking ? format(new Date(booking.start_time), 'd. MMM', { locale: dateFnsLocale }) : '—'}
                meta={booking ? `${format(new Date(booking.start_time), 'HH:mm')}–${format(new Date(booking.end_time), 'HH:mm')}` : t('checkin.noBookingHint')}
                isVoid={isVoid}
              />
              <CheckinStatCard
                label={t('checkin.elapsed')}
                value={checkInStatus?.checked_in && !checkInStatus?.checked_out_at ? elapsed || '00:00:00' : '—'}
                meta={activeStateLabel}
                isVoid={isVoid}
              />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              {t('checkin.opsLabel', 'Arrival briefing')}
            </p>
            <div className="mt-4 space-y-3">
              <DigestLine
                icon={<MapPin weight="fill" className="h-4 w-4" />}
                title={t('dashboard.slot')}
                body={booking ? `${booking.lot_name} · ${booking.slot_number}` : t('checkin.noBooking')}
                meta={booking ? format(new Date(booking.start_time), 'EEEE, d. MMM', { locale: dateFnsLocale }) : t('checkin.noBookingHint')}
                isVoid={isVoid}
              />
              <DigestLine
                icon={<CalendarBlank weight="fill" className="h-4 w-4" />}
                title={t('checkin.startTime')}
                body={booking ? format(new Date(booking.start_time), 'HH:mm') : '—'}
                meta={booking ? `${t('checkin.endTime')} ${format(new Date(booking.end_time), 'HH:mm')}` : t('checkin.bookNow')}
                isVoid={isVoid}
              />
              <DigestLine
                icon={<Clock weight="fill" className="h-4 w-4" />}
                title={t('checkin.statusLabel', 'Status')}
                body={activeStateLabel}
                meta={checkInStatus?.checked_in_at ? t('checkin.since', { time: format(new Date(checkInStatus.checked_in_at), 'HH:mm', { locale: dateFnsLocale }) }) : t('checkin.scanQr')}
                isVoid={isVoid}
              />
            </div>
          </div>
        </div>
      </motion.div>

      {/* No active booking */}
      {!booking && (
        <motion.div variants={fadeUp} className={`rounded-[28px] border p-12 text-center ${
          isVoid
            ? 'border-slate-800 bg-slate-950/85 text-white'
            : 'border-surface-200 bg-white text-surface-900 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
        }`}>
          <QrCode weight="thin" className={`mx-auto mb-4 h-16 w-16 ${isVoid ? 'text-slate-600' : 'text-surface-300 dark:text-surface-600'}`} />
          <p className={`text-lg font-semibold ${isVoid ? 'text-white' : 'text-surface-700 dark:text-white'}`}>{t('checkin.noBooking')}</p>
          <p className={`mt-1 mb-5 text-sm ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>{t('checkin.noBookingHint')}</p>
          <Link to="/book" className="btn btn-primary">{t('checkin.bookNow')}</Link>
        </motion.div>
      )}

      {/* Active booking */}
      {booking && (
        <>
          <div className="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
            <motion.div variants={fadeUp} className={`rounded-[28px] border p-5 ${
              isVoid
                ? 'border-slate-800 bg-slate-950/85 text-white'
                : 'border-surface-200 bg-white text-surface-900 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
            }`}>
              <div className="flex items-center gap-2">
                <MapPin weight="fill" className="h-5 w-5 text-primary-500" />
                <span className="text-lg font-semibold">{booking.lot_name}</span>
              </div>
              <p className={`mt-1 text-sm ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>
                {t('checkin.scanQr')}
              </p>
              <div className="mt-5 grid grid-cols-2 gap-3">
                <div className={`rounded-[22px] border p-4 ${isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'}`}>
                  <p className="mb-1 text-xs text-surface-400 dark:text-surface-500">{t('dashboard.slot')}</p>
                  <p className="font-semibold text-surface-800 dark:text-surface-200">{booking.slot_number}</p>
                </div>
                <div className={`rounded-[22px] border p-4 ${isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'}`}>
                  <p className="mb-1 text-xs text-surface-400 dark:text-surface-500">{t('checkin.date')}</p>
                  <p className="font-semibold text-surface-800 dark:text-surface-200">
                    {format(new Date(booking.start_time), 'd. MMM', { locale: dateFnsLocale })}
                  </p>
                </div>
                <div className={`rounded-[22px] border p-4 ${isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'}`}>
                  <p className="mb-1 text-xs text-surface-400 dark:text-surface-500">{t('checkin.startTime')}</p>
                  <p className="flex items-center gap-1 font-semibold text-surface-800 dark:text-surface-200">
                    <CalendarBlank weight="regular" className="h-3.5 w-3.5" />
                    {format(new Date(booking.start_time), 'HH:mm')}
                  </p>
                </div>
                <div className={`rounded-[22px] border p-4 ${isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'}`}>
                  <p className="mb-1 text-xs text-surface-400 dark:text-surface-500">{t('checkin.endTime')}</p>
                  <p className="flex items-center gap-1 font-semibold text-surface-800 dark:text-surface-200">
                    <Clock weight="regular" className="h-3.5 w-3.5" />
                    {format(new Date(booking.end_time), 'HH:mm')}
                  </p>
                </div>
              </div>
            </motion.div>

            {/* Not checked in */}
            {checkInStatus && !checkInStatus.checked_in && (
              <motion.div variants={fadeUp} className={`rounded-[28px] border p-6 flex flex-col items-center ${
                isVoid
                  ? 'border-slate-800 bg-slate-950/85 text-white'
                  : 'border-surface-200 bg-white text-surface-900 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
              }`}>
                {qrUrl && (
                  <img
                    src={qrUrl}
                    alt={t('checkin.qrAlt')}
                    className="w-56 h-56 rounded-[22px] border border-surface-200 bg-white p-3 dark:border-surface-700"
                    data-testid="qr-code"
                  />
                )}
                <p className={`mt-4 text-xs ${isVoid ? 'text-slate-400' : 'text-surface-400 dark:text-surface-500'}`}>{t('checkin.scanQr')}</p>
                <button
                  onClick={handleCheckIn}
                  disabled={acting}
                  className="btn btn-primary mt-5 w-full py-3 text-base"
                  data-testid="checkin-btn"
                >
                  {acting
                    ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" />
                    : <><SignIn weight="bold" className="w-5 h-5" /> {t('checkin.checkInBtn')}</>
                  }
                </button>
              </motion.div>
            )}

            {/* Checked in */}
            {checkInStatus?.checked_in && !checkInStatus.checked_out_at && (
              <>
                <motion.div variants={fadeUp} className={`rounded-[28px] border p-6 text-center ${
                  isVoid
                    ? 'border-cyan-500/20 bg-cyan-500/10 text-white'
                    : 'border-emerald-200 bg-emerald-500/10 text-surface-900 dark:border-emerald-900/60 dark:text-white'
                }`}>
                  <p className="mb-2 text-xs font-medium uppercase tracking-wider text-surface-400 dark:text-surface-500">
                    {t('checkin.elapsed')}
                  </p>
                  <p className="text-4xl font-mono font-semibold" data-testid="elapsed-timer">{elapsed}</p>
                  {checkInStatus.checked_in_at && (
                    <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">
                      {t('checkin.since', { time: format(new Date(checkInStatus.checked_in_at), 'HH:mm', { locale: dateFnsLocale }) })}
                    </p>
                  )}
                </motion.div>
                <motion.div variants={fadeUp}>
                  <button
                    onClick={handleCheckOut}
                    disabled={acting}
                    className="btn btn-secondary w-full py-3 text-base border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                    data-testid="checkout-btn"
                  >
                    {acting
                      ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" />
                      : <><SignOut weight="bold" className="w-5 h-5" /> {t('checkin.checkOutBtn')}</>
                    }
                  </button>
                </motion.div>
              </>
            )}
          </div>
        </>
      )}
    </motion.div>
  );
}

function CheckinStatCard({
  label,
  value,
  meta,
  isVoid,
  accent = false,
}: {
  label: string;
  value: string;
  meta: string;
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
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${
        isVoid
          ? accent ? 'text-cyan-100' : 'text-white/45'
          : accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45'
      }`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-white/60' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function DigestLine({
  icon,
  title,
  body,
  meta,
  isVoid,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  meta: string;
  isVoid: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 py-4 ${
      isVoid
        ? 'border-white/10 bg-white/[0.03]'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.03]'
    }`}>
      <div className="flex items-start gap-3">
        <div className={`mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl ${
          isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        }`}>
          {icon}
        </div>
        <div className="min-w-0 flex-1">
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>{title}</p>
          <p className={`mt-2 text-sm font-semibold ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{body}</p>
          <p className={`mt-1 text-xs ${isVoid ? 'text-white/60' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
        </div>
      </div>
    </div>
  );
}
