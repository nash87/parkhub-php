import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  Coins,
  ArrowDown,
  ArrowUp,
  ArrowClockwise,
  TrendUp,
  Sparkle,
} from '@phosphor-icons/react';
import { api, type UserCredits } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { staggerSlow, fadeUp } from '../constants/animations';

type CreditTxn = NonNullable<UserCredits['transactions']>[number];

export function CreditsPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { designTheme } = useTheme();
  const [credits, setCredits] = useState<UserCredits | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.getUserCredits().then((res) => {
      if (res.success && res.data) setCredits(res.data);
    }).finally(() => setLoading(false));
  }, []);

  const container = staggerSlow;
  const item = fadeUp;

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-10 w-64 skeleton rounded-xl" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          {[1, 2, 3].map((i) => <div key={i} className="h-32 skeleton rounded-2xl" />)}
        </div>
        <div className="h-64 skeleton rounded-2xl" />
      </div>
    );
  }

  const balance = credits?.balance ?? user?.credits_balance ?? 0;
  const quota = credits?.monthly_quota ?? user?.credits_monthly_quota ?? 10;
  const used = Math.max(0, quota - balance);
  const percentage = quota > 0 ? Math.max(0, Math.min(100, Math.round((balance / quota) * 100))) : 0;
  const lastRefill = credits?.last_refilled ? new Date(credits.last_refilled).toLocaleDateString() : '—';
  const nextRefill = credits?.last_refilled ? estimateNextRefill(credits.last_refilled) : t('credits.nextRefillFallback', 'Auto');
  const isVoid = designTheme === 'void';
  const transactions = credits?.transactions ?? [];
  const spotlight = {
    additions: transactions.filter((tx) => tx.amount > 0).reduce((sum, tx) => sum + tx.amount, 0),
    deductions: transactions.filter((tx) => tx.amount < 0).reduce((sum, tx) => sum + Math.abs(tx.amount), 0),
  };

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="space-y-6">
      <motion.section
        variants={item}
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
              <Coins weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void balance deck' : 'Marble credit ledger'}
            </div>

            <h1 className="text-3xl font-black tracking-[-0.04em]">{t('credits.title')}</h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('credits.subtitle')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <CreditHeroStat
                label={t('credits.balance', 'Balance')}
                value={String(balance)}
                meta={t('credits.noExpiry', 'No expiry')}
                accent
                isVoid={isVoid}
              />
              <CreditHeroStat
                label={t('credits.used', 'Used')}
                value={String(used)}
                meta={`${quota} ${t('credits.monthlyQuota', 'Monthly Quota')}`}
                isVoid={isVoid}
              />
              <CreditHeroStat
                label={t('credits.nextRefill', 'Next Refill')}
                value={nextRefill}
                meta={t('credits.automatic', 'Automatic')}
                isVoid={isVoid}
              />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <div className="flex items-center gap-5">
              <CreditProgressRing value={balance} total={quota} isVoid={isVoid} />
              <div className="min-w-0 flex-1">
                <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('credits.overview', 'Credit overview')}
                </p>
                <h2 className="mt-2 text-xl font-semibold tracking-[-0.03em]">{t('credits.summaryTitle', 'Keep your mobility budget visible')}</h2>
                <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('credits.summaryText', 'Credits do not expire and your monthly allowance refills automatically. Use the ledger below to understand how bookings affect your remaining balance.')}
                </p>
                <div className="mt-4 flex flex-wrap gap-2">
                  <span className={`rounded-full px-3 py-1 text-xs font-semibold ${isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'}`}>
                    +{spotlight.additions} {t('credits.incoming', 'incoming')}
                  </span>
                  <span className={`rounded-full px-3 py-1 text-xs font-semibold ${isVoid ? 'bg-rose-500/10 text-rose-100' : 'bg-rose-500/10 text-rose-700 dark:text-rose-300'}`}>
                    -{spotlight.deductions} {t('credits.outgoing', 'outgoing')}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </motion.section>

      <motion.div variants={item} className="grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <section className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">{t('credits.metricsLabel', 'Metrics')}</p>
              <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{t('credits.summaryTitle', 'Credit overview')}</h2>
            </div>
            <button type="button" className="rounded-full border border-surface-200 px-3 py-1.5 text-xs font-semibold text-surface-600 transition-colors hover:border-primary-400 hover:text-primary-700 dark:border-surface-700 dark:text-surface-300 dark:hover:text-primary-300">
              {t('credits.buyCredits', 'Buy credits')}
            </button>
          </div>

          <div className="grid gap-3 sm:grid-cols-3">
            <MetricCard
              label={t('credits.monthlyQuota', 'Monthly Quota')}
              value={String(quota)}
              meta={t('credits.creditsPerBooking', { count: 1 })}
              icon={<Sparkle weight="fill" className="h-5 w-5" />}
              tone="emerald"
            />
            <MetricCard
              label={t('credits.used', 'Used')}
              value={String(used)}
              meta={t('credits.monthToDate', 'Month to date')}
              icon={<TrendUp weight="fill" className="h-5 w-5" />}
              tone="amber"
            />
            <MetricCard
              label={t('credits.lastRefill', 'Last Refill')}
              value={lastRefill}
              meta={t('credits.nextRefill', 'Next Refill')}
              icon={<ArrowClockwise weight="fill" className="h-5 w-5" />}
              tone="sky"
            />
          </div>
        </section>

        <section className={`rounded-[24px] border p-5 ${
          isVoid
            ? 'border-slate-800 bg-slate-950/85'
            : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
        }`}>
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
            {t('credits.ledgerLabel', 'Ledger summary')}
          </p>
          <div className="mt-4 space-y-3">
            <LedgerSummaryRow
              label={t('credits.balance', 'Balance')}
              value={String(balance)}
              tone={isVoid ? 'text-cyan-200' : 'text-emerald-700 dark:text-emerald-300'}
            />
            <LedgerSummaryRow
              label={t('credits.additions', 'Additions')}
              value={`+${spotlight.additions}`}
              tone={isVoid ? 'text-emerald-200' : 'text-emerald-700 dark:text-emerald-300'}
            />
            <LedgerSummaryRow
              label={t('credits.deductions', 'Deductions')}
              value={`-${spotlight.deductions}`}
              tone={isVoid ? 'text-rose-200' : 'text-rose-700 dark:text-rose-300'}
            />
            <LedgerSummaryRow
              label={t('credits.nextRefill', 'Next Refill')}
              value={nextRefill}
              tone={isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}
            />
          </div>
        </section>
      </motion.div>

      <motion.section variants={item} className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-500 dark:text-surface-400">{t('credits.history', 'History')}</p>
            <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{t('credits.ledgerTitle', 'Transaction ledger')}</h2>
          </div>
          <span className="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
            {transactions.length} {t('credits.entries', 'entries')}
          </span>
        </div>

        {!transactions.length ? (
          <div className="rounded-[20px] border border-dashed border-surface-200 px-6 py-10 text-center dark:border-surface-800">
            <Coins weight="light" className="mx-auto h-12 w-12 text-surface-300 dark:text-surface-700" />
            <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">{t('credits.noTransactions')}</p>
          </div>
        ) : (
          <div className="space-y-3">
            {transactions.map((tx) => (
              <CreditTransactionRow key={tx.id} tx={tx} />
            ))}
          </div>
        )}
      </motion.section>
    </motion.div>
  );
}

function CreditHeroStat({
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
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? (accent ? 'text-cyan-100' : 'text-white/45') : (accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45')}`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function CreditProgressRing({ value, total, isVoid }: { value: number; total: number; isVoid: boolean }) {
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const pct = total > 0 ? Math.max(0, Math.min(1, value / total)) : 0;
  const offset = circumference * (1 - pct);

  return (
    <div className="relative h-24 w-24 shrink-0">
      <svg width={96} height={96} viewBox="0 0 96 96">
        <circle cx={48} cy={48} r={radius} fill="none" stroke={isVoid ? 'rgba(148,163,184,0.18)' : '#e7e5e4'} strokeWidth={10} />
        <circle
          cx={48}
          cy={48}
          r={radius}
          fill="none"
          stroke={isVoid ? '#22d3ee' : '#10b981'}
          strokeWidth={10}
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          transform="rotate(-90 48 48)"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className={`text-lg font-black ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</span>
        <span className={`text-[10px] ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>/ {total}</span>
      </div>
    </div>
  );
}

function MetricCard({
  label,
  value,
  meta,
  icon,
  tone,
}: {
  label: string;
  value: string;
  meta: string;
  icon: React.ReactNode;
  tone: 'emerald' | 'amber' | 'sky';
}) {
  const toneClasses = {
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
  } as const;

  return (
    <div className="rounded-[20px] border border-surface-200 bg-surface-50/80 p-4 dark:border-surface-800 dark:bg-surface-900/60">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{label}</p>
          <p className="mt-2 text-2xl font-black tracking-[-0.04em] text-surface-900 dark:text-white">{value}</p>
          <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{meta}</p>
        </div>
        <div className={`flex h-10 w-10 items-center justify-center rounded-2xl ${toneClasses[tone]}`}>
          {icon}
        </div>
      </div>
    </div>
  );
}

function LedgerSummaryRow({ label, value, tone }: { label: string; value: string; tone: string }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl bg-white/80 px-4 py-3 dark:bg-surface-900/70">
      <span className="text-sm text-surface-500 dark:text-surface-400">{label}</span>
      <span className={`text-sm font-semibold ${tone}`}>{value}</span>
    </div>
  );
}

function CreditTransactionRow({ tx }: { tx: CreditTxn }) {
  const positive = tx.amount > 0;

  return (
    <div className="flex items-center gap-3 rounded-[20px] border border-surface-200 bg-surface-50/80 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/60">
      <div className={`flex h-10 w-10 items-center justify-center rounded-2xl ${
        positive
          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
          : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
      }`}>
        {positive ? <ArrowDown weight="bold" className="h-4 w-4" /> : <ArrowUp weight="bold" className="h-4 w-4" />}
      </div>

      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-surface-900 dark:text-white">
          {renderTransactionLabel(tx.type)}
        </p>
        {tx.description && (
          <p className="mt-0.5 truncate text-xs text-surface-500 dark:text-surface-400">{tx.description}</p>
        )}
      </div>

      <div className="text-right">
        <span className={`text-sm font-semibold ${positive ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'}`}>
          {positive ? '+' : ''}{tx.amount}
        </span>
        <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
          {new Date(tx.created_at).toLocaleDateString()}
        </p>
      </div>
    </div>
  );
}

function renderTransactionLabel(type: string): string {
  switch (type) {
    case 'monthly_refill':
      return 'Monthly Refill';
    case 'deduction':
      return 'Deduction';
    case 'grant':
      return 'Grant';
    default:
      return type;
  }
}

function estimateNextRefill(lastRefilled: string): string {
  const date = new Date(lastRefilled);
  date.setMonth(date.getMonth() + 1);
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}
