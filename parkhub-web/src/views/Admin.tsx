import { Outlet, Link, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  ChartBar, GearSix, Users, Megaphone, ChartLine, MapPin, Translate, PresentationChart, Gauge,
  Buildings, ClockCounterClockwise, Database, Car, Wheelchair, Wrench, CurrencyDollar, UserPlus, Lightning,
  PuzzlePiece, GraphicsCard, ShieldCheck, LockKey, MapTrifold, ArrowsClockwise,
} from '@phosphor-icons/react';
import { useTheme } from '../context/ThemeContext';

type AdminTab = {
  name: string;
  path: string;
  icon: React.ElementType;
};

function AdminNav() {
  const { t } = useTranslation();
  const location = useLocation();

  const tabs: AdminTab[] = [
    { name: t('admin.overview'), path: '/admin', icon: ChartBar },
    { name: t('admin.settings'), path: '/admin/settings', icon: GearSix },
    { name: t('admin.users'), path: '/admin/users', icon: Users },
    { name: t('admin.lots'), path: '/admin/lots', icon: MapPin },
    { name: t('admin.announcements'), path: '/admin/announcements', icon: Megaphone },
    { name: t('admin.reports'), path: '/admin/reports', icon: ChartLine },
    { name: t('admin.translations'), path: '/admin/translations', icon: Translate },
    { name: 'Analytics', path: '/admin/analytics', icon: PresentationChart },
    { name: t('admin.rateLimits', 'Rate Limits'), path: '/admin/rate-limits', icon: Gauge },
    { name: t('admin.tenants', 'Tenants'), path: '/admin/tenants', icon: Buildings },
    { name: t('admin.auditLog', 'Audit Log'), path: '/admin/audit-log', icon: ClockCounterClockwise },
    { name: t('admin.dataManagement', 'Data'), path: '/admin/data', icon: Database },
    { name: t('admin.fleet', 'Fleet'), path: '/admin/fleet', icon: Car },
    { name: t('admin.accessible', 'Accessible'), path: '/admin/accessible', icon: Wheelchair },
    { name: t('admin.maintenance', 'Maintenance'), path: '/admin/maintenance', icon: Wrench },
    { name: t('admin.billing', 'Billing'), path: '/admin/billing', icon: CurrencyDollar },
    { name: t('admin.visitors', 'Visitors'), path: '/admin/visitors', icon: UserPlus },
    { name: t('admin.chargers', 'EV Chargers'), path: '/admin/chargers', icon: Lightning },
    { name: t('admin.plugins', 'Plugins'), path: '/admin/plugins', icon: PuzzlePiece },
    { name: t('compliance.title', 'Compliance'), path: '/admin/compliance', icon: ShieldCheck },
    { name: t('rbac.title', 'Roles'), path: '/admin/roles', icon: LockKey },
    { name: t('parkingZones.title', 'Zones'), path: '/admin/zones', icon: MapTrifold },
    { name: t('nav.updates', 'Updates'), path: '/admin/updates', icon: ArrowsClockwise },
    { name: 'GraphQL', path: '/api/v1/graphql/playground', icon: GraphicsCard },
  ];

  const sections = [
    { title: 'Core', items: tabs.slice(0, 7) },
    { title: 'Operations', items: tabs.slice(7, 18) },
    { title: 'Governance', items: tabs.slice(18) },
  ];

  function isActive(path: string) {
    if (path === '/admin') return location.pathname === '/admin';
    return location.pathname.startsWith(path);
  }

  return (
    <nav aria-label="Admin navigation" className="space-y-5">
      {sections.map(section => (
        <div key={section.title}>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">
            {section.title}
          </p>
          <div className="flex gap-1 overflow-x-auto pb-1 scrollbar-hide -webkit-overflow-scrolling-touch">
            {section.items.map(tab => {
              const active = isActive(tab.path);
              return (
                <Link
                  key={tab.path}
                  to={tab.path}
                  aria-current={active ? 'page' : undefined}
                  className={`relative flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors ${
                    active
                      ? 'border-primary-300 text-primary-700 dark:border-primary-700 dark:text-primary-300 bg-primary-50/90 dark:bg-primary-900/20'
                      : 'border-surface-200 dark:border-surface-800 text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-surface-100 dark:hover:bg-surface-800'
                  }`}
                >
                  <tab.icon weight={active ? 'fill' : 'regular'} className="w-4.5 h-4.5" />
                  {tab.name}
                  {active && (
                    <motion.div
                      layoutId="admin-tab-indicator"
                      className="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary-500"
                      transition={{ type: 'spring', stiffness: 500, damping: 30 }}
                    />
                  )}
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );
}

export function AdminPage() {
  const { t } = useTranslation();
  const location = useLocation();
  const { designTheme } = useTheme();
  const isVoid = designTheme === 'void';

  return (
    <div className="space-y-6">
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 ${
        isVoid
          ? 'border-surface-200 bg-gradient-to-br from-surface-900 via-surface-800 to-surface-950 text-white dark:border-surface-700'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.14),_transparent_42%),linear-gradient(135deg,rgba(255,251,245,0.98),rgba(236,253,245,0.9))] text-surface-900 dark:border-surface-700 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.18),_transparent_42%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] ${
              isVoid
                ? 'bg-white/10 text-white/75'
                : 'bg-white/85 text-emerald-700 dark:bg-white/10 dark:text-white/75'
            }`}>
              <span className={`inline-block h-2 w-2 rounded-full ${isVoid ? 'bg-emerald-400' : 'bg-emerald-500'}`} />
              {isVoid ? 'Admin control plane' : 'Marble governance studio'}
            </div>
            <h1 className="text-3xl font-semibold tracking-[-0.04em]">{t('admin.title')}</h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-white/70' : 'text-surface-600 dark:text-white/70'}`}>{t('admin.subtitle')}</p>
            <div className="mt-5 flex flex-wrap gap-3">
              <AdminHeroStat label={t('admin.users')} value="24" isVoid={isVoid} />
              <AdminHeroStat label={t('admin.lots')} value="6" isVoid={isVoid} />
              <AdminHeroStat label={t('admin.announcements')} value="3 live" isVoid={isVoid} />
              <AdminHeroStat label={t('admin.rateLimits', 'Rate Limits')} value="stable" isVoid={isVoid} />
            </div>
          </div>
          <div className={`rounded-[24px] border p-4 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>Operational focus</p>
            <div className="mt-4 space-y-3">
              {[
                { label: t('admin.settings'), meta: 'Policy, booking rules, waitlist, credits' },
                { label: t('admin.users'), meta: 'Quota, role, lifecycle and bulk actions' },
                { label: t('admin.announcements'), meta: 'Live comms surfaced into the product shell' },
              ].map(item => (
                <div key={item.label} className={`rounded-2xl border px-4 py-3 ${
                  isVoid
                    ? 'border-white/10 bg-white/[0.03]'
                    : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.03]'
                }`}>
                  <p className={`text-sm font-medium ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{item.label}</p>
                  <p className={`mt-1 text-xs ${isVoid ? 'text-white/55' : 'text-surface-500 dark:text-white/55'}`}>{item.meta}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <section className="rounded-[24px] border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-surface-900 dark:text-white">Admin navigation</h2>
              <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">Core, operations and governance routes grouped after the new v4 handoff structure.</p>
            </div>
            <span className="rounded-full bg-primary-500/10 px-3 py-1 text-xs font-semibold text-primary-700 dark:text-primary-300">
              {location.pathname === '/admin' ? 'Overview live' : 'Section active'}
            </span>
          </div>
          <AdminNav />
        </section>

        <section className="rounded-[24px] border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
          <h2 className="text-lg font-semibold text-surface-900 dark:text-white">Live priorities</h2>
          <div className="mt-4 space-y-3">
            {[
              { title: 'Announcements', body: 'Keep banner, notification-center and dashboard comms aligned.' },
              { title: 'EV + Zones', body: 'Operational visibility should match the new dashboard surfaces.' },
              { title: 'Compliance', body: 'Roles, audit log and configuration changes stay one click away.' },
            ].map(item => (
              <div key={item.title} className="rounded-2xl bg-surface-100 px-4 py-3 dark:bg-surface-800/80">
                <p className="text-sm font-medium text-surface-900 dark:text-white">{item.title}</p>
                <p className="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400">{item.body}</p>
              </div>
            ))}
          </div>
        </section>
      </div>

      <div className="rounded-[24px] border border-surface-200 bg-white/90 p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900/80">
        <Outlet />
      </div>
    </div>
  );
}

function AdminHeroStat({ label, value, isVoid }: { label: string; value: string; isVoid: boolean }) {
  return (
    <div className={`rounded-2xl border px-4 py-3 ${
      isVoid
        ? 'border-white/10 bg-white/[0.05]'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.05]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>{label}</p>
      <p className={`mt-2 text-2xl font-semibold tracking-[-0.04em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
    </div>
  );
}
