import { useEffect, useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import { CurrencyDollar, ChartBar, DownloadSimple, Question, Buildings } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { useTheme } from '../context/ThemeContext';

interface CostCenterRow {
  cost_center: string;
  department: string;
  user_count: number;
  total_bookings: number;
  total_credits_used: number;
  total_amount: number;
  currency: string;
}

interface DeptRow {
  department: string;
  user_count: number;
  total_bookings: number;
  total_credits_used: number;
  total_amount: number;
  currency: string;
}

export function AdminBillingPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [ccData, setCcData] = useState<CostCenterRow[]>([]);
  const [deptData, setDeptData] = useState<DeptRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'cost-center' | 'department'>('cost-center');
  const [showHelp, setShowHelp] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [ccRes, deptRes] = await Promise.all([
        fetch('/api/v1/admin/billing/by-cost-center').then(r => r.json()),
        fetch('/api/v1/admin/billing/by-department').then(r => r.json()),
      ]);
      if (ccRes.success) setCcData(ccRes.data || []);
      if (deptRes.success) setDeptData(deptRes.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  async function handleExport() {
    try {
      const res = await fetch('/api/v1/admin/billing/export');
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `billing-export-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success(t('billing.exported', 'CSV exported'));
    /* istanbul ignore next -- network failure path */
    } catch {
      toast.error(t('common.error'));
    }
  }

  const totalAmount = ccData.reduce((sum, r) => sum + r.total_amount, 0);
  const totalBookings = ccData.reduce((sum, r) => sum + r.total_bookings, 0);
  const totalUsers = ccData.reduce((sum, r) => sum + r.user_count, 0);
  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';

  if (loading) {
    return (
      <div className="space-y-4" data-testid="billing-shell" data-surface={surfaceVariant}>
        {Array.from({ length: 3 }, (_, i) => <div key={i} className="h-24 skeleton rounded-xl" />)}
      </div>
    );
  }

  return (
    <div className="space-y-6" data-testid="billing-shell" data-surface={surfaceVariant}>
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <CurrencyDollar weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void finance desk' : 'Marble finance desk'}
            </div>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
                  <CurrencyDollar weight="duotone" className="h-7 w-7 text-primary-500" />
                  {t('billing.title', 'Cost Center Billing')}
                </h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('billing.subtitle', 'Billing breakdown by cost center and department')}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setShowHelp(!showHelp)}
                  className={`rounded-2xl p-3 transition-colors ${
                    isVoid
                      ? 'bg-white/5 text-white/75 hover:bg-white/10'
                      : 'bg-white/80 text-surface-500 hover:bg-white dark:bg-white/10 dark:text-white/75 dark:hover:bg-white/15'
                  }`}
                >
                  <Question weight="bold" className="w-5 h-5" />
                </button>
                <button onClick={handleExport} className="btn btn-secondary btn-sm" data-testid="export-btn">
                  <DownloadSimple weight="bold" className="w-4 h-4" /> {t('billing.export', 'CSV Export')}
                </button>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <HeroMetric
                label={t('billing.totalSpending', 'Total Spending')}
                value={`EUR ${totalAmount.toFixed(2)}`}
                meta={t('billing.byCostCenter', 'By Cost Center')}
                isVoid={isVoid}
                accent
              />
              <HeroMetric
                label={t('billing.totalBookings', 'Total Bookings')}
                value={String(totalBookings)}
                meta={t('billing.byDepartment', 'By Department')}
                isVoid={isVoid}
              />
              <HeroMetric
                label={t('billing.totalUsers', 'Total Users')}
                value={String(totalUsers)}
                meta={tab === 'cost-center' ? t('billing.costCenter', 'Cost Center') : t('billing.department', 'Department')}
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
              Finance pulse
            </p>
            <div className="mt-4 space-y-3">
              <PanelMetric label="Primary view" value={tab === 'cost-center' ? t('billing.byCostCenter', 'By Cost Center') : t('billing.byDepartment', 'By Department')} helper={`${ccData.length} cost centers · ${deptData.length} departments`} isVoid={isVoid} />
              <PanelMetric label="Average spend" value={totalUsers > 0 ? `EUR ${(totalAmount / totalUsers).toFixed(2)}` : 'EUR 0.00'} helper="Per active billing user" isVoid={isVoid} />
              <PanelMetric label="Export readiness" value={ccData.length + deptData.length > 0 ? 'Ready' : 'Waiting'} helper="CSV and finance handoff" isVoid={isVoid} />
            </div>
          </div>
        </div>
      </section>

      {/* Help */}
      {showHelp && (
        <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} className="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4">
          <p className="text-sm text-emerald-800 dark:text-emerald-300">
            {t('billing.help', 'This module provides billing analytics by cost center and department. Track parking spending, credit usage, and generate CSV exports for finance teams. Assign cost centers and departments in user profiles.')}
          </p>
        </motion.div>
      )}

      {/* Summary cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4" data-testid="billing-summary">
        <SummaryCard label={t('billing.totalSpending', 'Total Spending')} value={`EUR ${totalAmount.toFixed(2)}`} icon={<CurrencyDollar weight="bold" className="w-5 h-5 text-emerald-500" />} />
        <SummaryCard label={t('billing.totalBookings', 'Total Bookings')} value={totalBookings} icon={<ChartBar weight="bold" className="w-5 h-5 text-blue-500" />} />
        <SummaryCard label={t('billing.totalUsers', 'Total Users')} value={totalUsers} icon={<Buildings weight="bold" className="w-5 h-5 text-purple-500" />} />
      </div>

      {/* Tab switcher */}
      <div className="flex gap-1 bg-surface-100 dark:bg-surface-800 rounded-lg p-1" data-testid="billing-tabs">
        <button
          onClick={() => setTab('cost-center')}
          className={`flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'cost-center' ? 'bg-white dark:bg-surface-700 text-surface-900 dark:text-white shadow-sm' : 'text-surface-500 dark:text-surface-400'}`}
        >
          {t('billing.byCostCenter', 'By Cost Center')}
        </button>
        <button
          onClick={() => setTab('department')}
          className={`flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'department' ? 'bg-white dark:bg-surface-700 text-surface-900 dark:text-white shadow-sm' : 'text-surface-500 dark:text-surface-400'}`}
        >
          {t('billing.byDepartment', 'By Department')}
        </button>
      </div>

      {/* Table */}
      <div className="bg-white dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-800 overflow-hidden" data-testid="billing-table">
        <table className="w-full">
          <thead>
            <tr className="border-b border-surface-100 dark:border-surface-800 bg-surface-50 dark:bg-surface-900">
              <th className="text-left px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{tab === 'cost-center' ? t('billing.costCenter', 'Cost Center') : t('billing.department', 'Department')}</th>
              {tab === 'cost-center' && <th className="text-left px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{t('billing.department', 'Department')}</th>}
              <th className="text-right px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{t('billing.users', 'Users')}</th>
              <th className="text-right px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{t('billing.bookings', 'Bookings')}</th>
              <th className="text-right px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{t('billing.credits', 'Credits')}</th>
              <th className="text-right px-4 py-3 text-xs font-semibold text-surface-500 uppercase">{t('billing.amount', 'Amount')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-surface-100 dark:divide-surface-800">
            {tab === 'cost-center' ? (
              ccData.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-surface-500">{t('billing.empty', 'No billing data')}</td></tr>
              ) : ccData.map((r, i) => (
                <tr key={i} data-testid="billing-row" className="hover:bg-surface-50 dark:hover:bg-surface-800/50">
                  <td className="px-4 py-3 text-sm font-medium text-surface-900 dark:text-white">{r.cost_center || '-'}</td>
                  <td className="px-4 py-3 text-sm text-surface-600 dark:text-surface-400">{r.department || '-'}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.user_count}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.total_bookings}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.total_credits_used}</td>
                  <td className="px-4 py-3 text-sm text-right font-semibold text-surface-900 dark:text-white">{r.currency} {r.total_amount.toFixed(2)}</td>
                </tr>
              ))
            ) : (
              deptData.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-sm text-surface-500">{t('billing.empty', 'No billing data')}</td></tr>
              ) : deptData.map((r, i) => (
                <tr key={i} data-testid="billing-row" className="hover:bg-surface-50 dark:hover:bg-surface-800/50">
                  <td className="px-4 py-3 text-sm font-medium text-surface-900 dark:text-white">{r.department || '-'}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.user_count}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.total_bookings}</td>
                  <td className="px-4 py-3 text-sm text-right text-surface-600 dark:text-surface-400">{r.total_credits_used}</td>
                  <td className="px-4 py-3 text-sm text-right font-semibold text-surface-900 dark:text-white">{r.currency} {r.total_amount.toFixed(2)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function SummaryCard({ label, value, icon }: { label: string; value: string | number; icon: React.ReactNode }) {
  return (
    <div className="bg-white dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-800 p-4">
      <div className="flex items-center gap-2 mb-2">{icon}<span className="text-xs font-medium text-surface-500 dark:text-surface-400">{label}</span></div>
      <p className="text-2xl font-bold text-surface-900 dark:text-white">{value}</p>
    </div>
  );
}

function HeroMetric({
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

function PanelMetric({
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
