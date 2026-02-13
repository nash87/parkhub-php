import { ReactNode, useState, useEffect, useRef } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  House, Calendar,
  CalendarPlus,
  ListChecks,
  Car,
  GearSix,
  SignOut,
  Moon,
  Sun,
  List,
  X,
  Bell,
  User,
  CaretDown,
  Warning,
  Info,
  CheckCircle,
  DotsThreeCircle,
  Translate,
  GithubLogo,
  ArrowsClockwise,
  CalendarBlank, UsersThree,
} from '@phosphor-icons/react';
import { useAuth } from '../context/auth-hook';
import { api, ApiNotification } from '../api/client';
import { useUpdateStore } from '../stores/updateStore';
import { useBranding } from '../context/branding-hook';
import { useTheme, applyTheme } from '../stores/theme';
import { useTranslation } from 'react-i18next';

interface LayoutProps {
  children: ReactNode;
}

const navigationKeys = [
  { key: 'nav.dashboard', href: '/', icon: House },
  { key: 'nav.book', href: '/book', icon: CalendarPlus },
  { key: 'nav.bookings', href: '/bookings', icon: ListChecks },
  { key: 'nav.vehicles', href: '/vehicles', icon: Car },
  { key: 'nav.absences', href: '/absences', icon: Calendar },
  { key: 'nav.team', href: '/team', icon: UsersThree },
  { key: 'nav.calendar', href: '/calendar', icon: CalendarBlank },
];

const adminNavKeys = [
  { key: 'nav.admin', href: '/admin', icon: GearSix },
];

const notifIcon = { warning: Warning, info: Info, success: CheckCircle };
const notifColor = {
  warning: 'text-amber-500',
  info: 'text-primary-500',
  success: 'text-emerald-500',
};

export function Layout({ children }: LayoutProps) {
  const { t, i18n } = useTranslation();
  const { branding } = useBranding();
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { updateAvailable, latestVersion, checkForUpdates } = useUpdateStore();
  const location = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);
  const [notifications, setNotifications] = useState<ApiNotification[]>([]);
  const { isDark, toggle } = useTheme();
  const notifRef = useRef<HTMLDivElement>(null);

  const isAdmin = user?.role === 'admin' || user?.role === 'superadmin';
  const unreadCount = notifications.filter(n => !n.read).length;

  const currentLang = i18n.language?.startsWith('en') ? 'en' : 'de';
  function toggleLang() {
    const next = currentLang === 'de' ? 'en' : 'de';
    i18n.changeLanguage(next);
  }

  useEffect(() => { applyTheme(isDark); }, [isDark]);

  useEffect(() => {
    const id = requestAnimationFrame(() => {
      setMobileMenuOpen(false);
      setUserMenuOpen(false);
      setNotifOpen(false);
    });
    return () => cancelAnimationFrame(id);
  }, [location.pathname]);

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) setNotifOpen(false);
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  
  // Check for updates periodically (admin only)
  useEffect(() => {
    if (!isAdmin) return;
    const token = localStorage.getItem('parkhub_token');
    if (!token) return;
    checkForUpdates(token);
    const interval = setInterval(() => checkForUpdates(token), 30 * 60 * 1000);
    return () => clearInterval(interval);
  }, [isAdmin, checkForUpdates]);

  // Load notifications from API
  useEffect(() => {
    async function loadNotifs() {
      try {
        const res = await api.getNotifications();
        if (res.success && res.data) setNotifications(res.data);
      } catch { /* ignore */ }
    }
    loadNotifs();
    const interval = setInterval(loadNotifs, 60000);
    return () => clearInterval(interval);
  }, []);

  function markAsRead(id: string) {
    setNotifications(prev => prev.map(n => n.id === id ? { ...n, read: true } : n));
    api.markNotificationRead(id).catch(() => {});
  }

  return (
    <div className="min-h-screen flex flex-col overflow-x-hidden max-w-full">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-100 dark:border-gray-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <Link to="/" className="flex items-center gap-3" aria-label="Home">
              <img src={branding.logo_url || "/icon.svg"} alt={branding.company_name} className="w-9 h-9 object-contain rounded-xl" />

              <span className="text-lg font-bold text-gray-900 dark:text-white">{branding.company_name}</span>
            </Link>

            <nav role="navigation" aria-label="Main navigation" className="hidden md:flex items-center gap-1">
              {navigationKeys.map((item) => {
                const Icon = item.icon;
                const isActive = location.pathname === item.href;
                return (
                  <Link key={item.href} to={item.href} className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-colors ${isActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'}`}>
                    <Icon weight={isActive ? 'fill' : 'regular'} className="w-5 h-5" />
                    {t(item.key)}
                  </Link>
                );
              })}
              {isAdmin && adminNavKeys.map((item) => {
                const Icon = item.icon;
                const isActive = location.pathname.startsWith(item.href);
                return (
                  <Link key={item.href} to={item.href} className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-colors ${isActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'}`}>
                    <Icon weight={isActive ? 'fill' : 'regular'} className="w-5 h-5" />
                    {t(item.key)}
                  </Link>
                );
              })}
            </nav>

            <div className="flex items-center gap-2">
              {/* Language Toggle */}
              <button
                onClick={toggleLang}
                className="btn btn-ghost px-2 py-1 text-xs font-bold tracking-wide"
                aria-label="Toggle language"
              >
                <Translate weight="bold" className="w-4 h-4" />
                <span>{currentLang.toUpperCase()}</span>
              </button>

              <button onClick={toggle} className="btn btn-ghost btn-icon" aria-label={t('theme.toggle')}>
                {isDark ? <Sun weight="fill" className="w-5 h-5" /> : <Moon weight="fill" className="w-5 h-5" />}
              </button>

              {/* Notifications */}
              <div ref={notifRef} className="relative">
                <button
                  onClick={() => setNotifOpen(!notifOpen)}
                  className="btn btn-ghost btn-icon relative"
                  aria-label={t('notifications.title')}
                >
                  <Bell weight={notifOpen ? 'fill' : 'regular'} className="w-5 h-5" />
                  {unreadCount > 0 && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                    >
                      {unreadCount}
                    </motion.span>
                  )}
                  {updateAvailable && unreadCount === 0 && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="absolute -top-0.5 -right-0.5 w-3 h-3 bg-amber-500 rounded-full"
                    />
                  )}
                </button>

                <AnimatePresence>
                  {notifOpen && (
                    <motion.div
                      initial={{ opacity: 0, y: 10, scale: 0.95 }}
                      animate={{ opacity: 1, y: 0, scale: 1 }}
                      exit={{ opacity: 0, y: 10, scale: 0.95 }}
                      transition={{ type: 'spring', damping: 25, stiffness: 300 }}
                      className="absolute right-0 mt-2 w-80 card p-0 shadow-lg overflow-hidden"
                    >
                      <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                        <p className="font-semibold text-gray-900 dark:text-white text-sm">{t('notifications.title')}</p>
                      </div>
                      <div className="max-h-72 overflow-y-auto">
                        {updateAvailable && (
                          <button
                            onClick={() => { setNotifOpen(false); navigate('/admin?tab=system'); }}
                            className="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors border-b border-amber-100 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/10"
                          >
                            <ArrowsClockwise weight="fill" className="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-500" />
                            <div className="flex-1 min-w-0">
                              <p className="text-sm text-gray-900 dark:text-white font-medium">
                                🔄 New version available: v{latestVersion}
                              </p>
                              <p className="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Click to update</p>
                            </div>
                          </button>
                        )}
                        {notifications.map((n) => {
                          const nType = (n.notification_type === 'warning' ? 'warning' : n.notification_type === 'success' ? 'success' : 'info') as keyof typeof notifIcon;
                          const NIcon = notifIcon[nType];
                          return (
                            <motion.button
                              key={n.id}
                              initial={{ opacity: 0, x: -10 }}
                              animate={{ opacity: 1, x: 0 }}
                              onClick={() => markAsRead(n.id)}
                              className={`w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors border-b border-gray-50 dark:border-gray-800/50 ${n.read ? 'opacity-60' : ''}`}
                            >
                              <NIcon weight="fill" className={`w-5 h-5 mt-0.5 flex-shrink-0 ${notifColor[nType]}`} />
                              <div className="flex-1 min-w-0">
                                <p className={`text-sm ${n.read ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white font-medium'}`}>
                                  {n.message}
                                </p>
                                <p className="text-xs text-gray-400 mt-0.5">{t('notifications.timeAgo')}</p>
                              </div>
                              {!n.read && <span className="w-2 h-2 bg-primary-500 rounded-full mt-2 flex-shrink-0" />}
                            </motion.button>
                          );
                        })}
                      </div>
                      {notifications.length === 0 && !updateAvailable && (
                        <div className="p-8 text-center">
                          <Bell weight="light" className="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                          <p className="text-sm text-gray-400">{t('notifications.empty')}</p>
                        </div>
                      )}
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>

              {/* User Menu */}
              <div className="relative hidden md:block">
                <button onClick={() => setUserMenuOpen(!userMenuOpen)} className="flex items-center gap-2 p-1.5 pr-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                  <div className="avatar text-sm">{user?.name?.charAt(0).toUpperCase()}</div>
                  <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{user?.name?.split(' ')[0]}</span>
                  <CaretDown weight="bold" className="w-4 h-4 text-gray-400" />
                </button>

                <AnimatePresence>
                  {userMenuOpen && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: 10 }} className="absolute right-0 mt-2 w-56 card p-2 shadow-lg">
                      <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-800 mb-2">
                        <p className="font-medium text-gray-900 dark:text-white">{user?.name}</p>
                        <p className="text-sm text-gray-500">{user?.email}</p>
                      </div>
                      <Link to="/profile" className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <User weight="regular" className="w-4 h-4" /> {t('nav.profile')}
                      </Link>
                      <Link to="/settings" className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <GearSix weight="regular" className="w-4 h-4" /> {t('nav.settings')}
                      </Link>
                      <button onClick={logout} className="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <SignOut weight="regular" className="w-4 h-4" /> {t('nav.logout')}
                      </button>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>

              <button onClick={() => setMobileMenuOpen(!mobileMenuOpen)} className="md:hidden btn btn-ghost btn-icon" aria-label="Toggle menu">
                {mobileMenuOpen ? <X weight="bold" className="w-5 h-5" /> : <List weight="bold" className="w-5 h-5" />}
              </button>
            </div>
          </div>
        </div>

        {/* Mobile Navigation */}
        <AnimatePresence>
          {mobileMenuOpen && (
            <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="md:hidden overflow-hidden border-t border-gray-100 dark:border-gray-800">
              <div className="px-4 py-3 space-y-1">
                {navigationKeys.map((item) => {
                  const Icon = item.icon;
                  const isActive = location.pathname === item.href;
                  return (
                    <Link key={item.href} to={item.href} className={`flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium ${isActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'}`}>
                      <Icon weight={isActive ? 'fill' : 'regular'} className="w-5 h-5" /> {t(item.key)}
                    </Link>
                  );
                })}
                {isAdmin && adminNavKeys.map((item) => {
                  const Icon = item.icon;
                  return (
                    <Link key={item.href} to={item.href} className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                      <Icon weight="regular" className="w-5 h-5" /> {t(item.key)}
                    </Link>
                  );
                })}
                <div className="pt-3 border-t border-gray-100 dark:border-gray-800">
                  <button onClick={logout} className="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-base font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <SignOut weight="regular" className="w-5 h-5" /> {t('nav.logout')}
                  </button>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </header>

      {/* Main Content */}
      <main className="flex-1">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-4 md:py-8">
          <motion.div key={location.pathname} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.2 }}>
            {children}
          </motion.div>
        </div>
      </main>

      {/* Footer */}
      <footer className="hidden md:block bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t('footer.tagline')} · <a href="https://github.com/nash87/parkhub/releases" target="_blank" rel="noopener noreferrer" className="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">v{__APP_VERSION__}</a>
            </p>
            <div className="flex items-center gap-4 text-sm text-gray-400 dark:text-gray-500">
              <Link to="/help" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.help')}</Link>
              <Link to="/about" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.about')}</Link>
              <Link to="/privacy" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.privacy')}</Link>
              <Link to="/terms" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.terms')}</Link>
              <Link to="/legal" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.imprint')}</Link>
              <a href="https://github.com/nash87/parkhub" target="_blank" rel="noopener noreferrer" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors"><GithubLogo weight="regular" className="w-4 h-4" /></a>
            </div>
          </div>
        </div>
      </footer>

      {/* Version badge - subtle fixed position */}
      <div className="fixed bottom-[4.5rem] md:bottom-auto md:relative z-10 left-0 right-0 pointer-events-none">
        <div className="max-w-7xl mx-auto px-3 flex justify-between items-center">
          <a href="https://github.com/nash87/parkhub" target="_blank" rel="noopener noreferrer" className="pointer-events-auto text-[10px] text-gray-300 dark:text-gray-700 hover:text-gray-500 dark:hover:text-gray-500 transition-colors flex items-center gap-1 md:hidden">
            <GithubLogo weight="regular" className="w-3 h-3" />
            <span>ParkHub v{__APP_VERSION__}</span>
          </a>
          <a href="https://github.com/nash87/parkhub/releases" target="_blank" rel="noopener noreferrer" className="pointer-events-auto text-[10px] text-gray-300 dark:text-gray-700 hover:text-gray-500 dark:hover:text-gray-500 transition-colors md:hidden">
            Release Notes
          </a>
        </div>
      </div>

      {/* Mobile Bottom Tab Bar */}
      <MobileBottomBar isAdmin={isAdmin} />
      <div className="h-16 md:hidden" />
    </div>
  );
}

const mobileTabKeys = [
  { key: 'nav.dashboard', href: '/', icon: House },
  { key: 'nav.book', href: '/book', icon: CalendarPlus },
  { key: 'nav.bookings', href: '/bookings', icon: ListChecks },
  { key: 'nav.absences', href: '/absences', icon: Calendar },
  { key: 'nav.team', href: '/team', icon: UsersThree },
  { key: 'nav.calendar', href: '/calendar', icon: CalendarBlank },
  { key: 'nav.more', href: '#more', icon: DotsThreeCircle },
];

const moreItemKeys = [
  { key: 'nav.vehicles', href: '/vehicles', icon: Car },
  { key: 'nav.profile', href: '/profile', icon: User },
  { key: 'nav.admin', href: '/admin', icon: GearSix, adminOnly: true },
];

function MobileBottomBar({ isAdmin }: { isAdmin: boolean }) {
  const { t } = useTranslation();
  const location = useLocation();
  const [showMore, setShowMore] = useState(false);

  return (
    <>
      <AnimatePresence>
        {showMore && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-40 md:hidden" onClick={() => setShowMore(false)}>
            <div className="absolute inset-0 bg-black/30" />
            <motion.div
              initial={{ y: '100%' }}
              animate={{ y: 0 }}
              exit={{ y: '100%' }}
              transition={{ type: 'spring', damping: 25, stiffness: 300 }}
              className="absolute bottom-16 left-0 right-0 bg-white dark:bg-gray-900 rounded-t-2xl border-t border-gray-200 dark:border-gray-800 p-4 shadow-2xl"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="w-8 h-1 bg-gray-300 dark:bg-gray-700 rounded-full mx-auto mb-4" />
              <div className="space-y-1">
                {moreItemKeys.filter(i => !i.adminOnly || isAdmin).map((item) => {
                  const Icon = item.icon;
                  const isActive = location.pathname === item.href;
                  return (
                    <Link
                      key={item.href}
                      to={item.href}
                      onClick={() => setShowMore(false)}
                      className={`flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium ${isActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                    >
                      <Icon weight={isActive ? 'fill' : 'regular'} className="w-5 h-5" />
                      {t(item.key)}
                    </Link>
                  );
                })}
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      <nav role="navigation" aria-label="Mobile navigation" className="fixed bottom-0 left-0 right-0 z-30 md:hidden bg-white/90 dark:bg-gray-900/90 backdrop-blur-lg border-t border-gray-200 dark:border-gray-800 safe-area-bottom">
        <div className="flex items-center justify-around h-16">
          {mobileTabKeys.map((item) => {
            const Icon = item.icon;
            const isMore = item.href === '#more';
            const isActive = !isMore && location.pathname === item.href;
            const isMoreActive = isMore && moreItemKeys.some(m => location.pathname === m.href);

            if (isMore) {
              return (
                <button
                  key="more"
                  onClick={() => setShowMore(!showMore)}
                  className={`flex flex-col items-center justify-center gap-0.5 w-16 h-full text-xs font-medium transition-colors ${isMoreActive || showMore ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500'}`}
                >
                  <Icon weight={isMoreActive || showMore ? 'fill' : 'regular'} className="w-6 h-6" />
                  <span>{t(item.key)}</span>
                </button>
              );
            }

            return (
              <Link
                key={item.href}
                to={item.href}
                onClick={() => setShowMore(false)}
                className={`flex flex-col items-center justify-center gap-0.5 w-16 h-full text-xs font-medium transition-colors ${isActive ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500'}`}
              >
                <Icon weight={isActive ? 'fill' : 'regular'} className="w-6 h-6" />
                <span>{t(item.key)}</span>
              </Link>
            );
          })}
        </div>
      </nav>
    </>
  );
}
