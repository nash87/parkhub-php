import { useActionState, useEffect, useMemo, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  UserPlus,
  Copy,
  ShareNetwork,
  Trash,
  QrCode,
  SpinnerGap,
  CheckCircle,
  CalendarBlank,
  MapPin,
} from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../context/AuthContext';
import { getInMemoryToken } from '../api/client';
import toast from 'react-hot-toast';
import { useTheme } from '../context/ThemeContext';

interface Lot {
  id: string;
  name: string;
}

interface Slot {
  id: string;
  number: string;
  status: 'available' | 'occupied' | 'reserved';
}

interface GuestBooking {
  id: string;
  lot_id: string;
  lot_name: string;
  slot_id: string;
  slot_number: string;
  guest_name: string;
  guest_email: string | null;
  guest_code: string;
  start_time: string;
  end_time: string;
  status: 'active' | 'expired' | 'cancelled';
  created_at: string;
}

interface FormData {
  guest_name: string;
  guest_email: string;
  lot_id: string;
  slot_id: string;
  start_time: string;
  end_time: string;
}

const emptyForm: FormData = {
  guest_name: '',
  guest_email: '',
  lot_id: '',
  slot_id: '',
  start_time: '',
  end_time: '',
};

const statusColors: Record<string, string> = {
  active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  expired: 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
  cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

function authHeaders(): Record<string, string> {
  const token = getInMemoryToken();
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

export function GuestPassPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { designTheme } = useTheme();
  const isAdmin = !!user && ['admin', 'superadmin'].includes(user.role);

  const [bookings, setBookings] = useState<GuestBooking[]>([]);
  const [lots, setLots] = useState<Lot[]>([]);
  const [slots, setSlots] = useState<Slot[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<FormData>(emptyForm);
  const [createdPass, setCreatedPass] = useState<GuestBooking | null>(null);
  const [copied, setCopied] = useState(false);

  async function loadBookings() {
    setLoading(true);
    try {
      const res = await fetch('/api/v1/bookings/guest', {
        headers: authHeaders(),
        credentials: 'include',
      }).then((response) => response.json());
      if (res.success) setBookings(res.data || []);
    } catch {
      toast.error(t('common.error'));
    }
    setLoading(false);
  }

  async function loadLots() {
    try {
      const res = await fetch('/api/v1/lots', {
        headers: authHeaders(),
        credentials: 'include',
      }).then((response) => response.json());
      if (res.success) setLots(res.data || []);
    } catch {
      toast.error(t('common.error'));
    }
  }

  useEffect(() => {
    void loadBookings();
    void loadLots();
  }, []);

  useEffect(() => {
    if (!form.lot_id) {
      setSlots([]);
      return;
    }

    void (async () => {
      try {
        const res = await fetch(`/api/v1/lots/${form.lot_id}/slots`, {
          headers: authHeaders(),
          credentials: 'include',
        }).then((response) => response.json());
        if (res.success) setSlots(res.data || []);
      } catch {
        toast.error(t('common.error'));
      }
    })();
  }, [form.lot_id]);

  const [, createAction, isSubmitting] = useActionState(async () => {
    if (!form.guest_name.trim() || !form.lot_id || !form.slot_id || !form.start_time || !form.end_time) {
      toast.error(t('guestBooking.requiredFields'));
      return null;
    }

    try {
      const res = await fetch('/api/v1/bookings/guest', {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'include',
        body: JSON.stringify({
          lot_id: form.lot_id,
          slot_id: form.slot_id,
          start_time: new Date(form.start_time).toISOString(),
          end_time: new Date(form.end_time).toISOString(),
          guest_name: form.guest_name,
          guest_email: form.guest_email || null,
        }),
      }).then((response) => response.json());

      if (res.success && res.data) {
        setCreatedPass(res.data);
        setShowForm(false);
        setForm(emptyForm);
        loadBookings();
        toast.success(t('guestBooking.created'));
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    } catch {
      toast.error(t('common.error'));
    }

    return null;
  }, null);

  async function handleCancel(id: string) {
    try {
      const res = await fetch(`/api/v1/bookings/guest/${id}`, {
        method: 'DELETE',
        headers: authHeaders(),
        credentials: 'include',
      }).then((response) => response.json());

      if (res.success) {
        toast.success(t('guestBooking.cancelled'));
        loadBookings();
      } else {
        toast.error(res.error?.message || t('common.error'));
      }
    } catch {
      toast.error(t('common.error'));
    }
  }

  function copyCode(code: string) {
    navigator.clipboard.writeText(code).then(() => {
      setCopied(true);
      toast.success(t('guestBooking.codeCopied'));
      setTimeout(() => setCopied(false), 2000);
    });
  }

  function sharePass(pass: GuestBooking) {
    const url = `${window.location.origin}/guest-pass/${pass.guest_code}`;
    const text = t('guestBooking.shareText', { name: pass.guest_name, code: pass.guest_code });
    if (navigator.share) {
      navigator.share({ title: t('guestBooking.shareTitle'), text, url }).catch(() => {});
    } else {
      navigator.clipboard.writeText(url).then(() => toast.success(t('guestBooking.linkCopied')));
    }
  }

  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';
  const activeBookings = useMemo(() => bookings.filter((booking) => booking.status === 'active').length, [bookings]);
  const expiredBookings = useMemo(() => bookings.filter((booking) => booking.status === 'expired').length, [bookings]);
  const availableSlots = useMemo(() => slots.filter((slot) => slot.status === 'available').length, [slots]);
  const spotlightPass = createdPass ?? bookings.find((booking) => booking.status === 'active') ?? null;

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
      data-testid="guest-pass-shell"
      data-surface={surfaceVariant}
    >
      <section
        className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
          isVoid
            ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
            : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
        }`}
        data-testid="guest-pass-hero"
      >
        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <UserPlus weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void guest desk' : 'Marble guest desk'}
            </div>

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
                  <UserPlus weight="duotone" className="h-7 w-7 text-primary-500" />
                  {t('guestBooking.title')}
                </h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('guestBooking.subtitle')}
                </p>
              </div>
              <button onClick={() => { setShowForm(true); setCreatedPass(null); }} className="btn btn-primary flex items-center gap-2" data-testid="create-guest-btn">
                <UserPlus weight="bold" className="h-4 w-4" />
                {t('guestBooking.create')}
              </button>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <GuestHeroStat label="Active" value={String(activeBookings)} meta={t('guestBooking.existing')} isVoid={isVoid} accent />
              <GuestHeroStat label="Expired" value={String(expiredBookings)} meta="Ledger" isVoid={isVoid} />
              <GuestHeroStat label="Lots" value={String(lots.length)} meta={`${availableSlots} open slots`} isVoid={isVoid} />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              Guest spotlight
            </p>
            <div className="mt-4 space-y-3">
              <GuestPanelMetric label={t('guestBooking.guestName')} value={spotlightPass?.guest_name ?? '—'} helper={spotlightPass?.guest_code ?? 'Ready to issue'} isVoid={isVoid} />
              <GuestPanelMetric label={t('guestBooking.lot')} value={spotlightPass?.lot_name ?? '—'} helper={spotlightPass?.slot_number ?? 'Select a slot'} isVoid={isVoid} />
              <GuestPanelMetric label={t('guestBooking.dateRange')} value={spotlightPass ? new Date(spotlightPass.start_time).toLocaleDateString() : '—'} helper={spotlightPass ? new Date(spotlightPass.end_time).toLocaleDateString() : 'Share instantly'} isVoid={isVoid} />
            </div>
          </div>
        </div>
      </section>

      <AnimatePresence>
        {createdPass && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className={`rounded-[24px] border p-6 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] ${
              isVoid
                ? 'border-cyan-500/30 bg-[radial-gradient(circle_at_top_right,_rgba(34,211,238,0.12),_transparent_30%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.94))] text-white'
                : 'border-emerald-200 bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,0.12),_transparent_30%),linear-gradient(135deg,rgba(255,255,255,0.98),rgba(240,253,250,0.92))] dark:border-emerald-900/60 dark:bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,0.14),_transparent_30%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))]'
            }`}
            data-testid="guest-pass-card"
          >
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h2 className="text-lg font-bold text-surface-900 dark:text-white">{t('guestBooking.passCreated')}</h2>
                <p className="text-sm text-surface-500 dark:text-surface-400">{t('guestBooking.shareInstructions')}</p>
              </div>
              <button
                onClick={() => setCreatedPass(null)}
                className="text-surface-400 transition-colors hover:text-surface-600 dark:hover:text-surface-200"
                data-testid="dismiss-pass"
              >
                &times;
              </button>
            </div>

            <div className="flex flex-col gap-6 sm:flex-row">
              <div className="flex flex-shrink-0 items-center justify-center rounded-xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-950/80">
                <div className="flex h-40 w-40 items-center justify-center" data-testid="qr-placeholder">
                  <QrCode weight="thin" className="h-32 w-32 text-surface-300 dark:text-surface-600" />
                  <span className="sr-only">{`/guest-pass/${createdPass.guest_code}`}</span>
                </div>
              </div>

              <div className="flex-1 space-y-3">
                <div>
                  <span className="text-xs uppercase tracking-wider text-surface-400 dark:text-surface-500">{t('guestBooking.guestName')}</span>
                  <p className="font-semibold text-surface-900 dark:text-white">{createdPass.guest_name}</p>
                </div>
                <div className="flex gap-4">
                  <div>
                    <span className="text-xs uppercase tracking-wider text-surface-400 dark:text-surface-500">{t('guestBooking.lot')}</span>
                    <p className="text-sm text-surface-700 dark:text-surface-300">{createdPass.lot_name}</p>
                  </div>
                  <div>
                    <span className="text-xs uppercase tracking-wider text-surface-400 dark:text-surface-500">{t('guestBooking.slot')}</span>
                    <p className="text-sm text-surface-700 dark:text-surface-300">{createdPass.slot_number}</p>
                  </div>
                </div>
                <div>
                  <span className="text-xs uppercase tracking-wider text-surface-400 dark:text-surface-500">{t('guestBooking.dateRange')}</span>
                  <p className="text-sm text-surface-700 dark:text-surface-300">
                    {new Date(createdPass.start_time).toLocaleString()} — {new Date(createdPass.end_time).toLocaleString()}
                  </p>
                </div>
                <div>
                  <span className="text-xs uppercase tracking-wider text-surface-400 dark:text-surface-500">{t('guestBooking.code')}</span>
                  <div className="mt-1 flex items-center gap-2">
                    <code className="rounded-lg bg-surface-100 px-3 py-1.5 font-mono text-lg font-bold tracking-widest text-primary-600 dark:bg-surface-700 dark:text-primary-400" data-testid="guest-code">
                      {createdPass.guest_code}
                    </code>
                    <button onClick={() => copyCode(createdPass.guest_code)} className="rounded-lg p-2 transition-colors hover:bg-surface-100 dark:hover:bg-surface-700" data-testid="copy-code-btn">
                      {copied ? <CheckCircle size={18} className="text-green-500" /> : <Copy size={18} className="text-surface-500 dark:text-surface-300" />}
                    </button>
                  </div>
                </div>
                <button onClick={() => sharePass(createdPass)} className="btn btn-secondary mt-2 flex items-center gap-2" data-testid="share-pass-btn">
                  <ShareNetwork size={16} />
                  {t('guestBooking.share')}
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {showForm && (
          <motion.div
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            className="rounded-[24px] border border-surface-200 bg-white p-6 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80"
            data-testid="guest-form"
          >
            <h2 className="mb-4 text-lg font-semibold text-surface-900 dark:text-white">{t('guestBooking.formTitle')}</h2>
            <form action={createAction} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.guestName')} *</label>
                <input
                  type="text"
                  value={form.guest_name}
                  onChange={(event) => setForm({ ...form, guest_name: event.target.value })}
                  className="input"
                  required
                  data-testid="input-guest-name"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.guestEmail')}</label>
                <input
                  type="email"
                  value={form.guest_email}
                  onChange={(event) => setForm({ ...form, guest_email: event.target.value })}
                  className="input"
                  data-testid="input-guest-email"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.lot')} *</label>
                <select
                  value={form.lot_id}
                  onChange={(event) => setForm({ ...form, lot_id: event.target.value, slot_id: '' })}
                  className="input"
                  required
                  data-testid="select-lot"
                >
                  <option value="">{t('guestBooking.selectLot')}</option>
                  {lots.map((lot) => (
                    <option key={lot.id} value={lot.id}>{lot.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.slot')} *</label>
                <select
                  value={form.slot_id}
                  onChange={(event) => setForm({ ...form, slot_id: event.target.value })}
                  className="input"
                  required
                  disabled={!form.lot_id}
                  data-testid="select-slot"
                >
                  <option value="">{t('guestBooking.selectSlot')}</option>
                  {slots.filter((slot) => slot.status === 'available').map((slot) => (
                    <option key={slot.id} value={slot.id}>{slot.number}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.startTime')} *</label>
                <input
                  type="datetime-local"
                  value={form.start_time}
                  onChange={(event) => setForm({ ...form, start_time: event.target.value })}
                  className="input"
                  required
                  data-testid="input-start-time"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('guestBooking.endTime')} *</label>
                <input
                  type="datetime-local"
                  value={form.end_time}
                  onChange={(event) => setForm({ ...form, end_time: event.target.value })}
                  className="input"
                  required
                  data-testid="input-end-time"
                />
              </div>
              <div className="sm:col-span-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowForm(false);
                    setForm(emptyForm);
                  }}
                  className="btn btn-secondary"
                >
                  {t('common.cancel')}
                </button>
                <button type="submit" disabled={isSubmitting} className="btn btn-primary" data-testid="submit-guest-btn">
                  {isSubmitting ? <SpinnerGap size={16} className="mr-1 inline animate-spin" /> : null}
                  {isSubmitting ? t('guestBooking.creating') : t('common.save')}
                </button>
              </div>
            </form>
          </motion.div>
        )}
      </AnimatePresence>

      <section className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">Guest ledger</p>
            <h2 className="mt-1 text-lg font-semibold text-surface-900 dark:text-white">{t('guestBooking.existing')}</h2>
          </div>
          <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
            {bookings.length} entries
          </span>
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary-500 border-t-transparent" />
          </div>
        ) : bookings.length === 0 ? (
          <div className="rounded-[20px] border border-dashed border-surface-200 py-12 text-center text-surface-400 dark:border-surface-800" data-testid="empty-state">
            <UserPlus className="mx-auto mb-3 h-12 w-12 opacity-40" />
            <p>{t('guestBooking.empty')}</p>
          </div>
        ) : (
          <div className="space-y-3" data-testid="bookings-list">
            {bookings.map((booking) => (
              <motion.div
                key={booking.id}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="flex items-center justify-between rounded-[22px] border border-surface-200/80 bg-surface-50/70 p-4 transition-colors hover:border-primary-300 hover:bg-white dark:border-surface-800 dark:bg-surface-900/80 dark:hover:border-primary-800 dark:hover:bg-surface-900"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-3">
                    <span className="font-semibold text-surface-900 dark:text-white">{booking.guest_name}</span>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[booking.status] || statusColors.active}`}>
                      {t(`guestBooking.status.${booking.status}`)}
                    </span>
                    <code className="rounded bg-surface-100 px-2 py-0.5 font-mono text-xs text-primary-600 dark:bg-surface-700 dark:text-primary-400">{booking.guest_code}</code>
                  </div>
                  <div className="mt-1 flex items-center gap-4 text-sm text-surface-500 dark:text-surface-400">
                    <span className="flex items-center gap-1">
                      <MapPin className="h-3.5 w-3.5" />
                      {booking.lot_name} / {booking.slot_number}
                    </span>
                    <span className="flex items-center gap-1">
                      <CalendarBlank className="h-3.5 w-3.5" />
                      {new Date(booking.start_time).toLocaleString()} — {new Date(booking.end_time).toLocaleString()}
                    </span>
                  </div>
                </div>

                <div className="ml-4 flex items-center gap-2">
                  <button onClick={() => sharePass(booking)} className="rounded-lg p-2 text-surface-500 transition-colors hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-700" title={t('guestBooking.share')}>
                    <ShareNetwork className="h-5 w-5" />
                  </button>
                  {isAdmin && booking.status === 'active' && (
                    <button onClick={() => handleCancel(booking.id)} className="rounded-lg p-2 text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20" title={t('guestBooking.cancel')} data-testid={`cancel-${booking.id}`}>
                      <Trash className="h-5 w-5" />
                    </button>
                  )}
                </div>
              </motion.div>
            ))}
          </div>
        )}
      </section>
    </motion.div>
  );
}

function GuestHeroStat({
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
      <p className="mt-3 text-2xl font-semibold tracking-[-0.03em]">{value}</p>
      <p className={`mt-2 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function GuestPanelMetric({
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
        : 'border-surface-200 bg-white/80 dark:border-surface-800 dark:bg-surface-900/70'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-400 dark:text-surface-500'}`}>{label}</p>
      <p className="mt-2 text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{helper}</p>
    </div>
  );
}
