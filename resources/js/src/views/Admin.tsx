import { Outlet, Link, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import {
  ChartBar, GearSix, Users, Megaphone, ChartLine, MapPin, Translate, PresentationChart, Gauge,
  Buildings, ClockCounterClockwise, Database, Car, Wheelchair, Wrench, CurrencyDollar, UserPlus, Lightning,
  PuzzlePiece, GraphicsCard, ShieldCheck, LockKey, MapTrifold,
} from '@phosphor-icons/react';

/**
 * A tab in the admin navigation.
 *
 * `external: true` marks a destination rendered by the backend rather than
 * the SPA router. Those must be plain anchors: react-router's <Link>
 * intercepts the click, matches no client route, and renders the catch-all
 * 404 page, so the tab appears permanently broken.
 */
type AdminTab = {
  name: string;
  path: string;
  icon: typeof ChartBar;
  external?: boolean;
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
    { name: 'GraphQL', path: '/api/v1/graphql/playground', icon: GraphicsCard, external: true },
  ];

  function isActive(path: string) {
    if (path === '/admin') return location.pathname === '/admin';
    return location.pathname.startsWith(path);
  }

  function tabClassName(active: boolean) {
    return `relative flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap transition-colors ${
      active
        ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
        : 'text-surface-500 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-surface-100 dark:hover:bg-surface-800'
    }`;
  }

  return (
    <nav aria-label="Admin navigation" className="flex gap-1 overflow-x-auto pb-1 scrollbar-hide -webkit-overflow-scrolling-touch">
      {tabs.map(tab => {
        // An external destination is never the active SPA route.
        const active = !tab.external && isActive(tab.path);
        const content = (
          <>
            <tab.icon weight={active ? 'fill' : 'regular'} className="w-4.5 h-4.5" />
            {tab.name}
            {active && (
              <motion.div
                layoutId="admin-tab-indicator"
                className="absolute bottom-0 left-3 right-3 h-0.5 bg-primary-500 rounded-full"
                transition={{ type: 'spring', stiffness: 500, damping: 30 }}
              />
            )}
          </>
        );

        if (tab.external) {
          return (
            <a
              key={tab.path}
              href={tab.path}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`${tab.name} (opens in a new tab)`}
              className={tabClassName(active)}
            >
              {content}
            </a>
          );
        }

        return (
          <Link
            key={tab.path}
            to={tab.path}
            aria-current={active ? 'page' : undefined}
            className={tabClassName(active)}
          >
            {content}
          </Link>
        );
      })}
    </nav>
  );
}

export function AdminPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-surface-900 dark:text-white">{t('admin.title')}</h1>
        <p className="text-surface-500 dark:text-surface-400 mt-1">{t('admin.subtitle')}</p>
      </div>

      {/* Tab navigation */}
      <div className="relative">
        <AdminNav />
        <div className="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-surface-50 dark:from-surface-950 to-transparent pointer-events-none sm:hidden" />
      </div>

      {/* Divider */}
      <div className="border-t border-surface-200 dark:border-surface-700" />

      {/* Content */}
      <Outlet />
    </div>
  );
}
