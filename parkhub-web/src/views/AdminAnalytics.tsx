import { Fragment, useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ChartBar, TrendUp, Users, Clock, CurrencyDollar, Export, CalendarBlank } from '@phosphor-icons/react';
import { getInMemoryToken } from '../api/client';
import { useTheme } from '../context/ThemeContext';

interface DailyDataPoint { date: string; value: number; }
interface HourBin { hour: number; count: number; }
interface TopLot { lot_id: string; lot_name: string; total_slots: number; bookings: number; utilization_percent: number; }
interface MonthlyGrowth { month: string; count: number; }
interface AnalyticsData {
  daily_bookings: DailyDataPoint[];
  daily_revenue: DailyDataPoint[];
  peak_hours: HourBin[];
  top_lots: TopLot[];
  user_growth: MonthlyGrowth[];
  avg_booking_duration_minutes: number;
  total_bookings: number;
  total_revenue: number;
  active_users: number;
}

type DateRange = '7' | '30' | '90' | '365';

function StatCard({ icon: Icon, label, value, sub }: { icon: any; label: string; value: string; sub?: string }) {
  return (
    <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800 shadow-sm">
      <div className="flex items-center gap-3 mb-2">
        <div className="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-950/30 flex items-center justify-center">
          <Icon weight="fill" className="w-5 h-5 text-primary-600 dark:text-primary-400" />
        </div>
        <span className="text-sm text-surface-500 dark:text-surface-400">{label}</span>
      </div>
      <div className="text-2xl font-bold text-surface-900 dark:text-white">{value}</div>
      {sub && <div className="text-xs text-surface-400 mt-1">{sub}</div>}
    </div>
  );
}

function MiniBarChart({ data, height = 120, color = 'var(--color-primary-500, #6366f1)' }: { data: { label: string; value: number }[]; height?: number; color?: string }) {
  if (!data.length) return null;
  const max = Math.max(...data.map(d => d.value), 1);
  const barW = Math.max(2, Math.min(12, (300 / data.length) - 2));
  return (
    <svg viewBox={`0 0 ${data.length * (barW + 2)} ${height}`} className="w-full" style={{ height }} preserveAspectRatio="none">
      {data.map((d, i) => (
        <rect
          key={i}
          x={i * (barW + 2)}
          y={height - (d.value / max) * (height - 4)}
          width={barW}
          height={(d.value / max) * (height - 4)}
          rx={1}
          fill={color}
          opacity={0.85}
        >
          <title>{d.label}: {d.value}</title>
        </rect>
      ))}
    </svg>
  );
}

function HeatmapChart({ peak_hours }: { peak_hours: HourBin[] }) {
  const max = Math.max(...peak_hours.map(h => h.count), 1);
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  // Spread 24 hours across a 7-day grid (synthetic — we only have hourly totals)
  return (
    <div className="grid grid-cols-[auto_repeat(24,1fr)] gap-0.5 text-xs">
      <div />
      {Array.from({ length: 24 }, (_, h) => (
        <div key={h} className="text-center text-surface-400 text-[10px]">{h}</div>
      ))}
      {days.map(day => (
        <Fragment key={`heatmap-row-${day}`}>
          <div key={day} className="pr-2 text-surface-500 text-right">{day}</div>
          {peak_hours.map((h, i) => {
            const intensity = h.count / max;
            return (
              <div
                key={`${day}-${i}`}
                className="aspect-square rounded-sm"
                style={{
                  backgroundColor: `rgba(99, 102, 241, ${Math.max(0.05, intensity * 0.9)})`,
                }}
                title={`${day} ${h.hour}:00 - ${h.count} bookings`}
              />
            );
          })}
        </Fragment>
      ))}
    </div>
  );
}

export function AdminAnalyticsPage() {
  useTranslation(); // initialized for future i18n
  const { designTheme } = useTheme();
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [range, setRange] = useState<DateRange>('30');

  useEffect(() => {
    setLoading(true);
    const base = (import.meta as any).env?.VITE_API_URL || '';
    const token = getInMemoryToken();
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };
    fetch(`${base}/api/v1/admin/analytics/overview?days=${range}`, { headers, credentials: 'include' })
      .then(r => r.json())
      .then(json => { if (json?.data) setData(json.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [range]);

  const bookingsChartData = useMemo(() =>
    (data?.daily_bookings ?? []).map(d => ({ label: d.date, value: d.value })),
  [data]);

  const revenueChartData = useMemo(() =>
    (data?.daily_revenue ?? []).map(d => ({ label: d.date, value: d.value })),
  [data]);

  const exportCsv = () => {
    if (!data) return;
    const rows = [
      ['Date', 'Bookings', 'Revenue'],
      ...data.daily_bookings.map((d, i) => [
        d.date,
        String(d.value),
        String(data.daily_revenue[i]?.value ?? 0),
      ]),
    ];
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `analytics-${range}d.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';

  return (
    <div className="space-y-6" data-testid="admin-analytics" data-surface={surfaceVariant}>
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
              <ChartBar weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void analytics deck' : 'Marble analytics deck'}
            </div>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
                  <ChartBar weight="duotone" className="h-7 w-7 text-primary-500" />
                  Analytics
                </h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  Comprehensive parking analytics and trends
                </p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={exportCsv}
                  className={`flex items-center gap-1.5 rounded-2xl px-3 py-2 text-sm transition-colors ${
                    isVoid
                      ? 'bg-white/5 text-white/75 hover:bg-white/10'
                      : 'bg-white/80 text-surface-600 hover:bg-white dark:bg-white/10 dark:text-white/75 dark:hover:bg-white/15'
                  }`}
                >
                  <Export weight="bold" className="w-4 h-4" />
                  CSV
                </button>
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <HeroMetric label="Range" value={range === '365' ? '1 year' : `${range} days`} meta="Explorable analytics window" isVoid={isVoid} accent />
              <HeroMetric label="Daily points" value={String(data?.daily_bookings.length ?? 0)} meta="Bookings + revenue trend lines" isVoid={isVoid} />
              <HeroMetric label="Top lots" value={String(data?.top_lots.length ?? 0)} meta="Utilization leaderboard" isVoid={isVoid} />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              Observatory
            </p>
            <div className="mt-4 space-y-3">
              <PanelMetric label="Fetch state" value={loading ? 'Loading' : data ? 'Live' : 'Fallback'} helper="Admin analytics overview endpoint" isVoid={isVoid} />
              <PanelMetric label="Revenue points" value={String(data?.daily_revenue.length ?? 0)} helper="Used for CSV export and trend chart" isVoid={isVoid} />
              <PanelMetric label="User growth" value={String(data?.user_growth.length ?? 0)} helper="Twelve-month adoption pulse" isVoid={isVoid} />
            </div>
          </div>
        </div>
      </section>

      <div className="flex items-center justify-end flex-wrap gap-2">
          {(['7', '30', '90', '365'] as DateRange[]).map(r => (
            <button
              key={r}
              onClick={() => setRange(r)}
              className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                range === r
                  ? 'bg-primary-600 text-white'
                  : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-surface-200 dark:hover:bg-surface-700'
              }`}
            >
              {r === '365' ? '1y' : `${r}d`}
            </button>
          ))}
      </div>

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-28 bg-surface-100 dark:bg-surface-800 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : data ? (
        <>
          {/* Stats cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard icon={CalendarBlank} label="Total Bookings" value={String(data.total_bookings)} sub={`Last ${range} days`} />
            <StatCard icon={CurrencyDollar} label="Total Revenue" value={`${data.total_revenue.toFixed(2)}`} sub={`Last ${range} days`} />
            <StatCard icon={Clock} label="Avg Duration" value={`${Math.round(data.avg_booking_duration_minutes)} min`} />
            <StatCard icon={Users} label="Active Users" value={String(data.active_users)} sub="With bookings in period" />
          </div>

          {/* Charts row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800">
              <h3 className="text-sm font-medium text-surface-600 dark:text-surface-400 mb-4 flex items-center gap-2">
                <TrendUp weight="bold" className="w-4 h-4" />
                Daily Bookings
              </h3>
              <MiniBarChart data={bookingsChartData} height={160} color="var(--color-primary-500, #6366f1)" />
            </div>
            <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800">
              <h3 className="text-sm font-medium text-surface-600 dark:text-surface-400 mb-4 flex items-center gap-2">
                <CurrencyDollar weight="bold" className="w-4 h-4" />
                Daily Revenue
              </h3>
              <MiniBarChart data={revenueChartData} height={160} color="var(--color-emerald-500, #10b981)" />
            </div>
          </div>

          {/* Heatmap + Top Lots */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800">
              <h3 className="text-sm font-medium text-surface-600 dark:text-surface-400 mb-4">Bookings by Hour (Heatmap)</h3>
              <HeatmapChart peak_hours={data.peak_hours} />
            </div>
            <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800">
              <h3 className="text-sm font-medium text-surface-600 dark:text-surface-400 mb-4">Top Lots by Utilization</h3>
              <div className="space-y-3">
                {data.top_lots.length === 0 && <p className="text-sm text-surface-400">No lot data</p>}
                {data.top_lots.map(lot => (
                  <div key={lot.lot_id} className="flex items-center gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium text-surface-800 dark:text-surface-200 truncate">{lot.lot_name}</div>
                      <div className="text-xs text-surface-400">{lot.bookings} bookings / {lot.total_slots} slots</div>
                    </div>
                    <div className="w-24">
                      <div className="h-2 bg-surface-100 dark:bg-surface-800 rounded-full overflow-hidden">
                        <div
                          className="h-full rounded-full bg-primary-500"
                          style={{ width: `${Math.min(100, lot.utilization_percent)}%` }}
                        />
                      </div>
                    </div>
                    <span className="text-xs font-medium text-surface-600 dark:text-surface-300 w-12 text-right">
                      {lot.utilization_percent.toFixed(1)}%
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* User growth */}
          <div className="bg-white dark:bg-surface-900 rounded-xl p-5 border border-surface-200 dark:border-surface-800">
            <h3 className="text-sm font-medium text-surface-600 dark:text-surface-400 mb-4 flex items-center gap-2">
              <Users weight="bold" className="w-4 h-4" />
              User Growth (12 months)
            </h3>
            <MiniBarChart
              data={data.user_growth.map(g => ({ label: g.month, value: g.count }))}
              height={120}
              color="var(--color-violet-500, #8b5cf6)"
            />
          </div>
        </>
      ) : (
        <div className="text-center py-12 text-surface-500">Failed to load analytics data</div>
      )}
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
