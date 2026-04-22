/**
 * Settings hub — unified entry point for user + workspace preferences.
 *
 * Ported (minimally) from the claude.ai/design v4 handoff bundle
 * (settings.jsx). The design's 858-line page models a full settings
 * app with 12 sections; this first cut ships the **Appearance** panel
 * (the truly new functionality — nav layout + theme swatches + density)
 * and links the rest (Profile, Notifications, Vehicles, Admin) to the
 * existing dedicated pages so no feature is duplicated or lost.
 *
 * Full multi-section unification + workspace-default locks are tracked
 * as T-1842 along with the nav-variants integration.
 */

import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { User, Building, Bell, Car, Keyboard, Shield, Coins, ChartLine, FileText, ArrowRight } from '@phosphor-icons/react';
import {
  SCard,
  SRow,
  SSeg,
  SToggle,
  ThemeSwatches,
  NavLayoutGrid,
  type ThemeSwatch,
} from '../components/ui/SettingsPrimitives';
import { useTheme, type DesignThemeId } from '../context/ThemeContext';
import { useNavLayout } from '../hooks/useNavLayout';
import { useDensity } from '../hooks/useDensity';

type Scope = 'user' | 'workspace';

// Both nav-layout and density persistence live in dedicated hooks
// (useNavLayout, useDensity) so every consumer shares a single source of
// truth with cross-tab + same-tab change events.

/**
 * Theme swatches exposed in the picker. Each `value` matches one of the
 * `[data-design-theme=…]` rules baked into `src/styles/themes.css`, so the
 * selection immediately re-tints the whole app via the existing theme
 * bridge — no additional CSS has to ship with this component.
 */
// Swatches are derived at render time from the single source of truth
// (DESIGN_THEMES via useTheme().designThemes) so every swatch value is
// guaranteed to be a valid DesignThemeId. Previously-hardcoded IDs
// (`residential`/`shared`/`rental`/`personal`) were use-case palette keys,
// not design-theme keys, so clicking those was a silent no-op.

export function SettingsPage() {
  const { t } = useTranslation();
  const { designTheme, setDesignTheme, setTheme, resolved, designThemes } = useTheme();

  const [scope, setScope] = useState<Scope>('user');
  const [navLayout, setNavLayoutState] = useNavLayout();
  const [density, setDensityState] = useDensity();
  const surfaceTone = designTheme === 'void' ? 'void' : 'marble';
  const isVoid = surfaceTone === 'void';

  const themeSwatches = useMemo<ThemeSwatch[]>(
    () =>
      [...designThemes]
        .sort((a, b) => {
          const priority = ['marble', 'void'];
          const ai = priority.indexOf(a.id);
          const bi = priority.indexOf(b.id);
          if (ai === -1 && bi === -1) return 0;
          if (ai === -1) return 1;
          if (bi === -1) return -1;
          return ai - bi;
        })
        .map((t) => ({
        value: t.id,
        label: t.name,
        color: `linear-gradient(135deg, ${t.previewColors.light[2]}, ${t.previewColors.light[3]})`,
      })),
    [designThemes],
  );

  const userSections = [
    { id: 'profile', label: t('settings.profile', 'Profile'), icon: User, to: '/profile' as const },
    { id: 'notifications', label: t('settings.notifications', 'Notifications'), icon: Bell, to: '/notifications' as const },
    { id: 'vehicles', label: t('settings.vehicles', 'Vehicles'), icon: Car, to: '/vehicles' as const },
    { id: 'shortcuts', label: t('settings.shortcuts', 'Keyboard shortcuts'), icon: Keyboard, to: null, hint: t('settings.pressShortcut', 'Press ⌘/') },
  ];

  const workspaceSections = [
    { id: 'org', label: t('settings.org', 'Organization'), icon: Building, to: '/admin/settings' as const },
    { id: 'policies', label: t('settings.bookingRules', 'Booking rules'), icon: FileText, to: '/admin/settings' as const },
    { id: 'sso', label: t('settings.sso', 'SSO & roles'), icon: Shield, to: '/admin/sso' as const },
    { id: 'billing', label: t('settings.billing', 'Billing'), icon: Coins, to: '/admin/billing' as const },
    { id: 'audit', label: t('settings.auditLog', 'Audit log'), icon: ChartLine, to: '/admin/audit-log' as const },
  ];
  const activeSections = scope === 'user' ? userSections : workspaceSections;
  const scopeLabel = scope === 'user'
    ? t('settings.personal', 'Personal')
    : t('settings.workspace', 'Workspace');
  const appearanceSignals = [
    {
      label: t('settings.theme', 'Theme'),
      value: themeSwatches.find((swatch) => swatch.value === designTheme)?.label ?? designTheme,
      meta: isVoid ? 'Void signal' : 'Marble editorial',
    },
    {
      label: t('settings.dark', 'Dark mode'),
      value: resolved === 'dark' ? t('common.on', 'On') : t('common.off', 'Off'),
      meta: resolved === 'dark' ? t('settings.darkEnabled', 'Night-ready') : t('settings.darkDisabled', 'Light canvas'),
    },
    {
      label: t('settings.density', 'Density'),
      value: density.charAt(0).toUpperCase() + density.slice(1),
      meta: t('settings.densityMeta', 'Shared spacing system'),
    },
    {
      label: t('settings.navLayout', 'Navigation layout'),
      value: navLayout.charAt(0).toUpperCase() + navLayout.slice(1),
      meta: t('settings.navLayoutMeta', 'Synced across tabs'),
    },
  ];

  return (
    <div className="mx-auto max-w-5xl space-y-6 p-4 pb-20 sm:p-6">
      <section
        data-testid="settings-shell"
        data-surface={surfaceTone}
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
              {scope === 'user' ? <User weight="fill" className="h-3.5 w-3.5" /> : <Building weight="fill" className="h-3.5 w-3.5" />}
              {scope === 'user' ? 'Marble preference deck' : 'Workspace command deck'}
            </div>

            <h1 className="text-3xl font-black tracking-[-0.04em]">
              {t('settings.title', 'Settings')}
            </h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('settings.subtitle', 'Control how ParkHub looks, feels, and behaves — for you and your workspace.')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              {appearanceSignals.map((signal, index) => (
                <div
                  key={signal.label}
                  className={`rounded-[20px] border p-4 ${
                    isVoid
                      ? index === 0
                        ? 'border-cyan-400/20 bg-cyan-400/10'
                        : 'border-white/10 bg-white/[0.04]'
                      : index === 0
                        ? 'border-emerald-200 bg-white/85 dark:border-emerald-500/20 dark:bg-white/[0.06]'
                        : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
                  }`}
                >
                  <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/55' : 'text-surface-500 dark:text-white/45'}`}>
                    {signal.label}
                  </p>
                  <p className="mt-2 text-lg font-semibold tracking-[-0.03em]">
                    {signal.value}
                  </p>
                  <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                    {signal.meta}
                  </p>
                </div>
              ))}
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <div
              role="tablist"
              aria-label={t('settings.scopeLabel', 'Settings scope')}
              className={`inline-flex gap-0.5 rounded-xl border p-1 ${
                isVoid
                  ? 'border-white/10 bg-slate-950/50'
                  : 'border-stone-200 bg-white/80 dark:border-white/10 dark:bg-slate-950/40'
              }`}
            >
              {(
                [
                  { k: 'user' as const, label: t('settings.personal', 'Personal'), icon: User },
                  { k: 'workspace' as const, label: t('settings.workspace', 'Workspace'), icon: Building },
                ]
              ).map((s) => {
                const active = scope === s.k;
                return (
                  <button
                    key={s.k}
                    type="button"
                    role="tab"
                    aria-selected={active}
                    onClick={() => setScope(s.k)}
                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors ${
                      active
                        ? isVoid
                          ? 'bg-cyan-400/10 text-white'
                          : 'bg-white text-surface-900 shadow-sm dark:bg-surface-100 dark:text-surface-950'
                        : isVoid
                          ? 'text-slate-300 hover:text-white'
                          : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    }`}
                  >
                    <s.icon weight="bold" className="h-3.5 w-3.5" />
                    {s.label}
                    {s.k === 'workspace' && (
                      <span className={`rounded px-1 text-[9px] font-bold uppercase tracking-wider ${
                        isVoid
                          ? 'bg-cyan-400/15 text-cyan-100'
                          : 'bg-primary-500/15 text-primary-600 dark:text-primary-300'
                      }`}>
                        Admin
                      </span>
                    )}
                  </button>
                );
              })}
            </div>

            <div className="mt-5 space-y-3">
              <div className={`rounded-[20px] border p-4 ${
                isVoid
                  ? 'border-white/10 bg-slate-950/40'
                  : 'border-stone-200 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
              }`}>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/50' : 'text-surface-500 dark:text-white/45'}`}>
                  {scopeLabel}
                </p>
                <p className="mt-2 text-lg font-semibold tracking-[-0.03em]">
                  {scope === 'user'
                    ? t('settings.scopePersonalSummary', 'Your preferences, shortcuts, and personal routes')
                    : t('settings.scopeWorkspaceSummary', 'Admin surfaces, policies, and workspace controls')}
                </p>
                <p className={`mt-1 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('settings.scopeSyncSummary', 'Appearance controls apply instantly through the shared theme system, while detailed settings stay on their dedicated pages.')}
                </p>
              </div>

              <div className={`rounded-[20px] border p-4 ${
                isVoid
                  ? 'border-white/10 bg-white/[0.03]'
                  : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
              }`}>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/50' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('settings.linkPreview', 'Quick links')}
                </p>
                <div className="mt-3 space-y-2">
                  {activeSections.slice(0, 3).map((section) => (
                    <div key={section.id} className="flex items-center justify-between gap-3 rounded-2xl border border-white/10 px-3 py-2">
                      <div className="flex min-w-0 items-center gap-2">
                        <section.icon className={`h-4 w-4 shrink-0 ${isVoid ? 'text-cyan-200' : 'text-primary-500'}`} weight="duotone" />
                        <span className="truncate text-sm font-medium">
                          {section.label}
                        </span>
                      </div>
                      {section.to ? <ArrowRight className={`h-3.5 w-3.5 shrink-0 ${isVoid ? 'text-white/55' : 'text-surface-400'}`} weight="bold" /> : null}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <SCard
          title={t('settings.appearance', 'Appearance')}
          subtitle={t('settings.appearanceSubtitle', 'Theme, density, and navigation layout')}
        >
          <SRow
            title={t('settings.theme', 'Theme')}
            description={t('settings.themeDesc', 'Pick a palette — the whole app retints via the shared design-token bridge.')}
          >
            <ThemeSwatches
              value={designTheme}
              onChange={(v) => setDesignTheme(v as DesignThemeId)}
              options={themeSwatches}
            />
          </SRow>

          <SRow
            title={t('settings.dark', 'Dark mode')}
            description={t('settings.darkDesc', 'Also togglable via ⌘⇧D.')}
          >
            <SToggle
              value={resolved === 'dark'}
              onChange={(v) => setTheme(v ? 'dark' : 'light')}
              label={t('settings.dark', 'Dark mode')}
            />
          </SRow>

          <SRow
            title={t('settings.density', 'Density')}
            description={t('settings.densityDesc', 'Default spacing between rows and cards.')}
          >
            <SSeg
              value={density}
              onChange={setDensityState}
              options={[
                { value: 'compact', label: t('settings.compact', 'Compact') },
                { value: 'cozy', label: t('settings.cozy', 'Cozy') },
                { value: 'comfortable', label: t('settings.comfortable', 'Comfortable') },
              ]}
            />
          </SRow>

          <SRow
            title={t('settings.navLayout', 'Navigation layout')}
            description={t('settings.navLayoutDesc', 'Switch instantly — your choice persists across reloads and syncs to open tabs.')}
          >
            <NavLayoutGrid value={navLayout} onChange={setNavLayoutState} />
          </SRow>
        </SCard>

        <SCard
          title={scope === 'user' ? t('settings.moreYou', 'More personal settings') : t('settings.moreWorkspace', 'More workspace settings')}
          subtitle={t('settings.linksSubtitle', 'These live on their existing pages for now. Unifying them into this hub is tracked as T-1842.')}
        >
          <div className="grid grid-cols-1 gap-2 -my-2 sm:grid-cols-2">
            {activeSections.map((s) => {
              const content = (
                <>
                  <s.icon weight="duotone" className="h-5 w-5 shrink-0 text-primary-500" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-surface-900 dark:text-white">
                      {s.label}
                    </p>
                    {'hint' in s && s.hint && (
                      <p className="truncate text-[11px] text-surface-500 dark:text-surface-400">
                        {s.hint}
                      </p>
                    )}
                  </div>
                  {s.to && <ArrowRight weight="bold" className="h-3.5 w-3.5 shrink-0 text-surface-400" />}
                </>
              );
              return s.to ? (
                <Link
                  key={s.id}
                  to={s.to}
                  className="flex items-center gap-3 rounded-lg p-3 transition-colors hover:bg-surface-100 dark:hover:bg-surface-800/60"
                >
                  {content}
                </Link>
              ) : (
                <div
                  key={s.id}
                  className="flex items-center gap-3 rounded-lg p-3 opacity-75"
                >
                  {content}
                </div>
              );
            })}
          </div>
        </SCard>
      </div>
    </div>
  );
}
