import { useEffect, useMemo, useOptimistic, useState, useTransition, type ReactNode } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Bell,
  Warning,
  Info,
  CheckCircle,
  Check,
  SpinnerGap,
  ArrowClockwise,
  Sparkle,
  ClockCounterClockwise,
  Broadcast,
} from '@phosphor-icons/react';
import { api, type Notification } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { useTheme } from '../context/ThemeContext';

const notifIcon: Record<string, typeof Warning> = { warning: Warning, info: Info, success: CheckCircle };
const notifColor: Record<string, string> = {
  warning: 'text-amber-500',
  info: 'text-primary-500',
  success: 'text-emerald-500',
};
const notifBg: Record<string, string> = {
  warning: 'bg-amber-100 dark:bg-amber-900/30',
  info: 'bg-primary-100 dark:bg-primary-900/30',
  success: 'bg-emerald-100 dark:bg-emerald-900/30',
};

function resolveType(raw: string): string {
  if (raw === 'warning') return 'warning';
  if (raw === 'success') return 'success';
  return 'info';
}

function timeAgoFn(dateStr: string, t: (key: string, opts?: Record<string, unknown>) => string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return t('timeAgo.justNow');
  if (mins < 60) return t('timeAgo.minutesAgo', { count: mins });
  const hours = Math.floor(mins / 60);
  if (hours < 24) return t('timeAgo.hoursAgo', { count: hours });
  const days = Math.floor(hours / 24);
  return t('timeAgo.daysAgo', { count: days });
}

function formatAbsolute(dateStr: string) {
  const date = new Date(dateStr);
  return date.toLocaleString(undefined, {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

type OptimisticAction = { type: 'read'; id: string } | { type: 'readAll' };

export function NotificationsPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [isPending, startTransition] = useTransition();

  const [optimisticNotifs, addOptimistic] = useOptimistic(
    notifications,
    (current: Notification[], action: OptimisticAction) => {
      if (action.type === 'read') {
        return current.map((n) => (n.id === action.id ? { ...n, read: true } : n));
      }
      return current.map((n) => ({ ...n, read: true }));
    },
  );

  useEffect(() => {
    loadNotifications();
  }, []);

  async function loadNotifications() {
    setLoading(true);
    try {
      const res = await api.getNotifications();
      if (res.success && res.data) setNotifications(res.data);
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }

  function markAsRead(id: string) {
    startTransition(async () => {
      addOptimistic({ type: 'read', id });
      const res = await api.markNotificationRead(id);
      if (res.success) {
        setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read: true } : n)));
      }
    });
  }

  function markAllAsRead() {
    startTransition(async () => {
      addOptimistic({ type: 'readAll' });
      const res = await api.markAllNotificationsRead();
      if (res.success) {
        setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
        toast.success(t('notifications.allMarkedRead'));
      } else {
        toast.error(t('common.error'));
      }
    });
  }

  const isVoid = designTheme === 'void';
  const sortedNotifications = useMemo(
    () => [...optimisticNotifs].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()),
    [optimisticNotifs],
  );
  const unreadCount = useMemo(() => sortedNotifications.filter((n) => !n.read).length, [sortedNotifications]);
  const latestNotification = sortedNotifications[0] ?? null;
  const warningCount = useMemo(
    () => sortedNotifications.filter((n) => resolveType(n.notification_type) === 'warning').length,
    [sortedNotifications],
  );
  const successCount = useMemo(
    () => sortedNotifications.filter((n) => resolveType(n.notification_type) === 'success').length,
    [sortedNotifications],
  );

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-8 w-64 skeleton rounded-xl" />
        {[1, 2, 3].map((i) => <div key={i} className="h-20 skeleton rounded-2xl" />)}
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <Broadcast weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void signal board' : 'Marble inbox board'}
            </div>

            <h1 className="text-3xl font-black tracking-[-0.04em]">{t('notifications.title')}</h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {unreadCount > 0
                ? t('notifications.unreadCount', { count: unreadCount })
                : t('notifications.allRead')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <NotificationHeroStat
                label={t('notifications.unreadLabel', 'Unread')}
                value={String(unreadCount)}
                meta={t('notifications.inboxLabel', 'Inbox')}
                isVoid={isVoid}
                accent
              />
              <NotificationHeroStat
                label={t('notifications.warningLabel', 'Warnings')}
                value={String(warningCount)}
                meta={t('notifications.attentionLabel', 'Need attention')}
                isVoid={isVoid}
              />
              <NotificationHeroStat
                label={t('notifications.deliveredLabel', 'Delivered')}
                value={String(successCount)}
                meta={t('notifications.resolvedLabel', 'Resolved')}
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
              {t('notifications.opsLabel', 'Ops focus')}
            </p>
            <div className="mt-4 space-y-3">
              <NotificationDigestCard
                icon={<Sparkle weight="fill" className="h-5 w-5" />}
                title={t('notifications.latestDigest', 'Latest signal')}
                body={latestNotification ? latestNotification.title : t('notifications.empty')}
                meta={latestNotification ? timeAgoFn(latestNotification.created_at, t) : t('notifications.allRead')}
                tone={isVoid ? 'void' : 'marble'}
              />
              <NotificationDigestCard
                icon={<ClockCounterClockwise weight="fill" className="h-5 w-5" />}
                title={t('notifications.slaLabel', 'Response loop')}
                body={latestNotification ? formatAbsolute(latestNotification.created_at) : t('notifications.empty')}
                meta={t('notifications.markAllRead')}
                tone={isVoid ? 'void' : 'marble'}
              />
            </div>

            <div className="mt-5 flex flex-wrap gap-2">
              <button onClick={loadNotifications} className="btn btn-secondary">
                <ArrowClockwise weight="bold" className="h-4 w-4" /> {t('common.refresh')}
              </button>
              {unreadCount > 0 && (
                <button onClick={markAllAsRead} disabled={isPending} className="btn btn-primary">
                  {isPending ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> : <Check weight="bold" className="h-4 w-4" />}
                  {t('notifications.markAllRead')}
                </button>
              )}
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <section className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">
                {t('notifications.inboxLabel', 'Inbox')}
              </p>
              <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
                {t('notifications.title')}
              </h2>
            </div>
            <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
              {sortedNotifications.length} {t('notifications.entries', 'entries')}
            </span>
          </div>

          {sortedNotifications.length === 0 ? (
            <div className="rounded-[20px] border border-dashed border-surface-200 px-6 py-10 text-center dark:border-surface-800">
              <Bell weight="light" className="mx-auto h-12 w-12 text-surface-300 dark:text-surface-700" />
              <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">{t('notifications.empty')}</p>
            </div>
          ) : (
            <div className="space-y-3" role="list" aria-label={t('notifications.title')}>
              <AnimatePresence>
                {sortedNotifications.map((n) => {
                  const nType = resolveType(n.notification_type);
                  const NIcon = notifIcon[nType] || Info;
                  return (
                    <motion.button
                      key={n.id}
                      role="listitem"
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, x: -50 }}
                      onClick={() => { if (!n.read) markAsRead(n.id); }}
                      aria-label={`${n.title} — ${n.read ? t('notifications.allRead') : t('notifications.unread')}`}
                      className={`w-full rounded-[20px] border p-4 text-left transition-all hover:shadow-md ${
                        n.read
                          ? 'border-surface-200 bg-surface-50/80 opacity-70 dark:border-surface-800 dark:bg-surface-900/50'
                          : 'border-primary-200 bg-white shadow-[0_14px_40px_-28px_rgba(16,185,129,0.45)] dark:border-primary-900/60 dark:bg-surface-950/70'
                      }`}
                    >
                      <div className="flex items-start gap-4">
                        <div className={`mt-0.5 flex h-11 w-11 items-center justify-center rounded-2xl ${notifBg[nType]}`}>
                          <NIcon weight="fill" className={`h-5 w-5 ${notifColor[nType]}`} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                              <p className={`text-sm font-semibold ${n.read ? 'text-surface-500 dark:text-surface-400' : 'text-surface-900 dark:text-white'}`}>
                                {n.title}
                              </p>
                              <p className={`mt-1 text-sm leading-6 ${n.read ? 'text-surface-400 dark:text-surface-500' : 'text-surface-600 dark:text-surface-300'}`}>
                                {n.message}
                              </p>
                            </div>
                            <div className="flex flex-shrink-0 items-center gap-2">
                              <span className="text-xs text-surface-500 dark:text-surface-400 whitespace-nowrap">
                                {timeAgoFn(n.created_at, t)}
                              </span>
                              {!n.read && <span className="h-2.5 w-2.5 rounded-full bg-primary-500" />}
                            </div>
                          </div>
                        </div>
                      </div>
                    </motion.button>
                  );
                })}
              </AnimatePresence>
            </div>
          )}
        </section>

        <section className={`rounded-[24px] border p-5 ${
          isVoid
            ? 'border-slate-800 bg-slate-950/85'
            : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
        }`}>
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
            {t('notifications.summaryLabel', 'Queue summary')}
          </p>

          <div className="mt-4 space-y-3">
            <SummaryRow
              label={t('notifications.unreadLabel', 'Unread')}
              value={String(unreadCount)}
              tone={isVoid ? 'text-cyan-200' : 'text-emerald-700 dark:text-emerald-300'}
            />
            <SummaryRow
              label={t('notifications.warningLabel', 'Warnings')}
              value={String(warningCount)}
              tone={isVoid ? 'text-amber-200' : 'text-amber-700 dark:text-amber-300'}
            />
            <SummaryRow
              label={t('notifications.deliveredLabel', 'Delivered')}
              value={String(successCount)}
              tone={isVoid ? 'text-emerald-200' : 'text-emerald-700 dark:text-emerald-300'}
            />
            <SummaryRow
              label={t('notifications.latestDigest', 'Latest signal')}
              value={latestNotification ? latestNotification.title : t('notifications.empty')}
              tone={isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}
            />
          </div>
        </section>
      </div>
    </motion.div>
  );
}

function NotificationHeroStat({
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
          ? (accent ? 'text-cyan-100' : 'text-white/45')
          : (accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45')
      }`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-white/60' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function NotificationDigestCard({
  icon,
  title,
  body,
  meta,
  tone,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  meta: string;
  tone: 'void' | 'marble';
}) {
  const isVoid = tone === 'void';
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

function SummaryRow({ label, value, tone }: { label: string; value: string; tone: string }) {
  return (
    <div className="flex items-center justify-between rounded-2xl bg-surface-50/90 px-4 py-3 dark:bg-surface-900/60">
      <p className="text-sm text-surface-500 dark:text-surface-400">{label}</p>
      <p className={`text-sm font-semibold ${tone}`}>{value}</p>
    </div>
  );
}
