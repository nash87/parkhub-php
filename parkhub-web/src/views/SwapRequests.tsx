import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  Swap, Check, X, SpinnerGap, Plus, ArrowClockwise,
  CalendarBlank, Clock, ChatText,
} from '@phosphor-icons/react';
import toast from 'react-hot-toast';
import { format } from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import { api, getInMemoryToken, type Booking } from '../api/client';
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
import { stagger, fadeUp, modalVariants, modalTransition } from '../constants/animations';

interface SwapRequest {
  id: string;
  requester_id: string;
  source_booking_id: string;
  target_booking_id: string;
  source_booking: { lot_name: string; slot_number: string; start_time: string; end_time: string };
  target_booking: { lot_name: string; slot_number: string; start_time: string; end_time: string };
  message: string | null;
  status: 'pending' | 'accepted' | 'declined';
  created_at: string;
}

const statusConfig: Record<string, { cls: string }> = {
  pending: { cls: 'badge badge-warning' },
  accepted: { cls: 'badge badge-success' },
  declined: { cls: 'badge badge-error' },
};

export function SwapRequestsPage() {
  const { t, i18n } = useTranslation();
  const { designTheme } = useTheme();
  const dateFnsLocale = i18n.language?.startsWith('de') ? de : enUS;
  const [requests, setRequests] = useState<SwapRequest[]>([]);
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [selectedBooking, setSelectedBooking] = useState<string>('');
  const [targetBookingId, setTargetBookingId] = useState('');
  const [swapMessage, setSwapMessage] = useState('');
  const [creating, setCreating] = useState(false);

  async function loadData() {
    setLoading(true);
    try {
      const [swapRes, bookingsRes] = await Promise.all([
        fetch('/api/v1/swap-requests', { headers: authHeaders(), credentials: 'include' }).then(r => r.json()),
        api.getBookings(),
      ]);
      if (swapRes.success && swapRes.data) setRequests(swapRes.data);
      if (bookingsRes.success && bookingsRes.data) setBookings(bookingsRes.data);
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setLoading(false);
  }

  useEffect(() => { void loadData(); }, []);

  async function handleAccept(id: string) {
    setActing(id);
    try {
      const res = await fetch(`/api/v1/swap-requests/${id}/accept`, { method: 'POST', headers: authHeaders(), credentials: 'include' }).then(r => r.json());
      if (res.success) {
        setRequests(prev => prev.map(r => r.id === id ? { ...r, status: 'accepted' } : r));
        toast.success(t('swap.accepted'));
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setActing(null);
  }

  async function handleDecline(id: string) {
    setActing(id);
    try {
      const res = await fetch(`/api/v1/swap-requests/${id}/decline`, { method: 'POST', headers: authHeaders(), credentials: 'include' }).then(r => r.json());
      if (res.success) {
        setRequests(prev => prev.map(r => r.id === id ? { ...r, status: 'declined' } : r));
        toast.success(t('swap.declined'));
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setActing(null);
  }

  async function handleCreate() {
    if (!selectedBooking || !targetBookingId) return;
    setCreating(true);
    try {
      const res = await fetch(`/api/v1/bookings/${selectedBooking}/swap-request`, {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'include',
        body: JSON.stringify({ target_booking_id: targetBookingId, message: swapMessage || null }),
      }).then(r => r.json());
      if (res.success) {
        toast.success(t('swap.created'));
        setShowModal(false);
        setSelectedBooking('');
        setTargetBookingId('');
        setSwapMessage('');
        loadData();
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
    setCreating(false);
  }

  const activeBookings = bookings.filter(b => b.status === 'active' || b.status === 'confirmed');
  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';
  const pendingCount = requests.filter((request) => request.status === 'pending').length;
  const acceptedCount = requests.filter((request) => request.status === 'accepted').length;

  if (loading) {
    return (
      <div className="flex justify-center py-12" data-testid="swap-shell" data-surface={surfaceVariant}>
        <div className="animate-spin rounded-full h-8 w-8 border-2 border-primary-500 border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="space-y-6" data-testid="swap-shell" data-surface={surfaceVariant}>
      <motion.div variants={stagger} initial="hidden" animate="show" className="space-y-6">
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
                <Swap weight="fill" className="h-3.5 w-3.5" />
                {isVoid ? 'Void exchange board' : 'Marble exchange board'}
              </div>
              <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
                    <Swap weight="duotone" className="w-7 h-7 text-primary-500" />
                    {t('swap.title')}
                  </h1>
                  <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>{t('swap.subtitle')}</p>
                </div>
                <div className="flex items-center gap-2 self-start sm:self-auto">
                  <button onClick={() => void loadData()} className="btn btn-secondary">
                    <ArrowClockwise weight="bold" className="w-4 h-4" /> {t('common.refresh')}
                  </button>
                  <button onClick={() => setShowModal(true)} className="btn btn-primary">
                    <Plus weight="bold" className="w-4 h-4" /> {t('swap.create')}
                  </button>
                </div>
              </div>

              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <SwapHeroMetric label="Pending" value={String(pendingCount)} meta="Awaiting response" isVoid={isVoid} accent />
                <SwapHeroMetric label="Accepted" value={String(acceptedCount)} meta="Successful exchanges" isVoid={isVoid} />
                <SwapHeroMetric label="Eligible bookings" value={String(activeBookings.length)} meta="Available for new requests" isVoid={isVoid} />
              </div>
            </div>

            <div className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-white/10 bg-white/[0.04]'
                : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
            }`}>
              <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                Trade lane
              </p>
              <div className="mt-4 space-y-3">
                <SwapPanelMetric label="Open requests" value={String(requests.length)} helper="All visible swap conversations" isVoid={isVoid} />
                <SwapPanelMetric label="Next action" value={pendingCount > 0 ? 'Review queue' : 'Create offer'} helper="Refresh to reconcile latest state" isVoid={isVoid} />
                <SwapPanelMetric label="Message coverage" value={requests.some((request) => request.message) ? 'Personalized' : 'Direct'} helper="Optional negotiation note supported" isVoid={isVoid} />
              </div>
            </div>
          </div>
        </motion.div>

        {/* Request list */}
        {requests.length === 0 ? (
          <motion.div variants={fadeUp} className="card p-12 text-center">
            <Swap weight="thin" className="w-16 h-16 mx-auto mb-4 text-surface-300 dark:text-surface-600" />
            <p className="text-surface-500 dark:text-surface-400 text-lg font-medium">{t('swap.empty')}</p>
            <p className="text-surface-400 dark:text-surface-500 text-sm mt-1">{t('swap.emptyHint')}</p>
          </motion.div>
        ) : (
          <div className="space-y-3">
            <AnimatePresence>
              {requests.map(req => {
                const cfg = statusConfig[req.status] || statusConfig.pending;
                return (
                  <motion.div
                    key={req.id}
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, x: -60 }}
                    className="card p-4"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex items-center gap-2">
                        <Swap weight="bold" className="w-4 h-4 text-primary-500" />
                        <span className="font-semibold text-surface-900 dark:text-white">
                          {req.source_booking.lot_name}
                        </span>
                        <span className="text-surface-400 dark:text-surface-500">→</span>
                        <span className="font-semibold text-surface-900 dark:text-white">
                          {req.target_booking.lot_name}
                        </span>
                      </div>
                      <span className={cfg.cls}>{t(`swap.status.${req.status}`)}</span>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                      {/* Source */}
                      <div className="glass-card p-3">
                        <p className="text-xs font-medium text-surface-400 dark:text-surface-500 mb-1">{t('swap.yourSlot')}</p>
                        <div className="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300">
                          <CalendarBlank weight="regular" className="w-3.5 h-3.5" />
                          {format(new Date(req.source_booking.start_time), 'd. MMM yyyy', { locale: dateFnsLocale })}
                        </div>
                        <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400 mt-1">
                          <Clock weight="regular" className="w-3.5 h-3.5" />
                          {t('dashboard.slot')} {req.source_booking.slot_number} &middot;{' '}
                          {format(new Date(req.source_booking.start_time), 'HH:mm')} — {format(new Date(req.source_booking.end_time), 'HH:mm')}
                        </div>
                      </div>
                      {/* Target */}
                      <div className="glass-card p-3">
                        <p className="text-xs font-medium text-surface-400 dark:text-surface-500 mb-1">{t('swap.theirSlot')}</p>
                        <div className="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300">
                          <CalendarBlank weight="regular" className="w-3.5 h-3.5" />
                          {format(new Date(req.target_booking.start_time), 'd. MMM yyyy', { locale: dateFnsLocale })}
                        </div>
                        <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400 mt-1">
                          <Clock weight="regular" className="w-3.5 h-3.5" />
                          {t('dashboard.slot')} {req.target_booking.slot_number} &middot;{' '}
                          {format(new Date(req.target_booking.start_time), 'HH:mm')} — {format(new Date(req.target_booking.end_time), 'HH:mm')}
                        </div>
                      </div>
                    </div>

                    {req.message && (
                      <div className="flex items-start gap-2 text-sm text-surface-600 dark:text-surface-400 mb-3">
                        <ChatText weight="regular" className="w-4 h-4 mt-0.5 flex-shrink-0" />
                        <p>{req.message}</p>
                      </div>
                    )}

                    {req.status === 'pending' && (
                      <div className="flex items-center gap-2 pt-3 border-t border-surface-100 dark:border-surface-800">
                        <button
                          onClick={() => handleAccept(req.id)}
                          disabled={acting === req.id}
                          className="btn btn-sm btn-primary"
                        >
                          {acting === req.id
                            ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                            : <><Check weight="bold" className="w-4 h-4" /> {t('swap.accept')}</>
                          }
                        </button>
                        <button
                          onClick={() => handleDecline(req.id)}
                          disabled={acting === req.id}
                          className="btn btn-sm btn-ghost text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                          <X weight="bold" className="w-4 h-4" /> {t('swap.decline')}
                        </button>
                      </div>
                    )}
                  </motion.div>
                );
              })}
            </AnimatePresence>
          </div>
        )}
      </motion.div>

      {/* Create Swap Modal */}
      <AnimatePresence>
        {showModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <motion.div
              variants={modalVariants}
              initial="initial"
              animate="animate"
              exit="exit"
              transition={modalTransition}
              className="card w-full max-w-md p-6 space-y-4"
              data-testid="swap-modal"
            >
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-surface-900 dark:text-white">{t('swap.createTitle')}</h2>
                <button onClick={() => setShowModal(false)} className="btn btn-ghost btn-sm">
                  <X weight="bold" className="w-4 h-4" />
                </button>
              </div>

              <div>
                <label className="text-sm font-medium text-surface-700 dark:text-surface-300">{t('swap.yourBooking')}</label>
                <select
                  value={selectedBooking}
                  onChange={e => setSelectedBooking(e.target.value)}
                  className="input mt-1"
                  data-testid="select-source"
                >
                  <option value="">{t('swap.selectBooking')}</option>
                  {activeBookings.map(b => (
                    <option key={b.id} value={b.id}>
                      {b.lot_name} — {t('dashboard.slot')} {b.slot_number} ({format(new Date(b.start_time), 'dd.MM HH:mm')})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-sm font-medium text-surface-700 dark:text-surface-300">{t('swap.targetBookingId')}</label>
                <input
                  type="text"
                  value={targetBookingId}
                  onChange={e => setTargetBookingId(e.target.value)}
                  placeholder={t('swap.targetPlaceholder')}
                  className="input mt-1"
                  data-testid="input-target"
                />
              </div>

              <div>
                <label className="text-sm font-medium text-surface-700 dark:text-surface-300">{t('swap.messageLabel')}</label>
                <textarea
                  value={swapMessage}
                  onChange={e => setSwapMessage(e.target.value)}
                  placeholder={t('swap.messagePlaceholder')}
                  className="input mt-1 h-20 resize-none"
                  data-testid="input-message"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button onClick={() => setShowModal(false)} className="btn btn-secondary">
                  {t('common.cancel')}
                </button>
                <button
                  onClick={handleCreate}
                  disabled={creating || !selectedBooking || !targetBookingId}
                  className="btn btn-primary"
                  data-testid="submit-swap"
                >
                  {creating
                    ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" />
                    : <><Swap weight="bold" className="w-4 h-4" /> {t('swap.send')}</>
                  }
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  );
}

function SwapHeroMetric({
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
      accent
        ? isVoid
          ? 'border-cyan-500/30 bg-cyan-500/10'
          : 'border-emerald-200 bg-emerald-500/10 dark:border-emerald-900/60'
        : isVoid
          ? 'border-white/10 bg-white/[0.04]'
          : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${
        accent
          ? isVoid
            ? 'text-cyan-100'
            : 'text-emerald-700 dark:text-emerald-300'
          : isVoid
            ? 'text-white/45'
            : 'text-surface-500 dark:text-white/45'
      }`}>
        {label}
      </p>
      <p className="mt-3 text-2xl font-semibold tracking-[-0.03em]">{value}</p>
      <p className="mt-2 text-xs text-surface-500 dark:text-surface-400">{meta}</p>
    </div>
  );
}

function SwapPanelMetric({
  label,
  value,
  helper,
  isVoid,
}: {
  label: string;
  value: string;
  helper: string;
  isVoid: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 py-4 ${
      isVoid
        ? 'border-white/10 bg-white/[0.03]'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.03]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>{label}</p>
      <p className="mt-2 text-lg font-semibold">{value}</p>
      <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{helper}</p>
    </div>
  );
}
