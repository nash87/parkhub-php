import { useState } from 'react';
import { Navigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Eye, EyeSlash, ArrowRight, SpinnerGap, Globe, GithubLogo } from '@phosphor-icons/react';
import { useAuth } from '../context/auth-hook';
import { useBranding } from '../context/branding-hook';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { ThemeSelector } from "../components/ThemeSelector";

export function LoginPage() {
  const { t, i18n } = useTranslation();
  const { login, isAuthenticated, isLoading } = useAuth();
  const { branding } = useBranding();
  const currentLang = i18n.language?.startsWith('en') ? 'en' : 'de';
  const toggleLang = () => i18n.changeLanguage(currentLang === 'de' ? 'en' : 'de');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  if (isAuthenticated) return <Navigate to="/" replace />;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    const success = await login(username, password);
    if (success) {
      if (!sessionStorage.getItem('parkhub_welcomed')) {
        toast.success(t('login.welcomeBack'));
        sessionStorage.setItem('parkhub_welcomed', '1');
      }
    }
    else { toast.error(t('login.invalidCredentials')); }
    setLoading(false);
  }

  return (
    <div className="min-h-screen flex bg-gray-50 dark:bg-gray-950 relative">
      {/* Top-right theme + language controls */}
      <div className="absolute top-4 right-4 z-50 flex items-center gap-2">
        <button onClick={toggleLang} className="p-2 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-1.5 text-sm font-medium" aria-label={t('language.toggle', 'Switch language')}>
          <Globe weight="bold" className="w-4 h-4" />{currentLang.toUpperCase()}
        </button>
<ThemeSelector compact />
      </div>
      <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden" style={{ backgroundColor: branding.login_background_color }}>
        <div className="absolute inset-0" style={{ background: `linear-gradient(135deg, ${branding.primary_color}, ${branding.login_background_color})` }} />
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-20 left-20 w-64 h-64 bg-white rounded-full blur-3xl" />
          <div className="absolute bottom-20 right-20 w-96 h-96 bg-white rounded-full blur-3xl" />
        </div>
        <div className="relative z-10 flex flex-col justify-center px-16 text-white">
          <div className="flex items-center gap-4 mb-8">
            <div className="w-14 h-14 rounded-2xl flex items-center justify-center">
              <img src={branding.logo_url || "/icon.svg"} alt={branding.company_name} className="w-10 h-10 object-contain rounded-xl" />
            </div>
            <span className="text-3xl font-bold">{branding.company_name}</span>
          </div>
          <h1 className="text-4xl font-bold mb-4">{t('login.heroTitle')}</h1>
          <p className="text-lg text-white/80 mb-8">{t('login.heroSubtitle')}</p>
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-4">
              <div className="text-3xl font-bold">24/7</div>
              <div className="text-sm text-white/70">{t('login.available247')}</div>
            </div>
            <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-4">
              <div className="text-3xl font-bold">100%</div>
              <div className="text-sm text-white/70">{t('login.openSource')}</div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center px-6 py-12">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <div className="lg:hidden flex items-center gap-3 mb-8">
            <div className="w-12 h-12 bg-primary-600 rounded-2xl flex items-center justify-center">
              <img src={branding.logo_url || "/icon.svg"} alt={branding.company_name} className="w-10 h-10 object-contain rounded-xl" />
            </div>
            <span className="text-2xl font-bold text-gray-900 dark:text-white">{branding.company_name}</span>
          </div>

          <div className="mb-8">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">{t('login.title')}</h2>
            <p className="text-gray-500 dark:text-gray-400 mt-2">{t('login.subtitle')}</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="username" className="label">{t('login.username')}</label>
              <input id="username" type="text" value={username} onChange={(e) => setUsername(e.target.value)} className="input" placeholder={t('login.usernamePlaceholder')} required autoFocus autoComplete="username" />
            </div>
            <div>
              <label htmlFor="password" className="label">{t('login.password')}</label>
              <div className="relative">
                <input id="password" type={showPassword ? 'text' : 'password'} value={password} onChange={(e) => setPassword(e.target.value)} className="input pr-12" placeholder="••••••••" required autoComplete="current-password" />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={showPassword ? t('login.hidePassword', 'Passwort verbergen') : t('login.showPassword', 'Passwort anzeigen')}
                  aria-pressed={showPassword}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded"
                >
                  {showPassword ? <EyeSlash weight="regular" className="w-5 h-5" aria-hidden="true" /> : <Eye weight="regular" className="w-5 h-5" aria-hidden="true" />}
                </button>
              </div>
            </div>
            <div className="flex items-center justify-between">
              <label htmlFor="php-remember-me" className="flex items-center gap-2 cursor-pointer">
                <input id="php-remember-me" type="checkbox" className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                <span className="text-sm text-gray-600 dark:text-gray-400">{t('login.rememberMe')}</span>
              </label>
              <Link to="/forgot-password" className="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">{t('login.forgotPassword')}</Link>
            </div>
            <button
              type="submit"
              disabled={loading}
              aria-busy={loading}
              className="btn btn-primary w-full justify-center disabled:opacity-60"
            >
              {loading ? (
                <>
                  <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" aria-hidden="true" />
                  <span>{t('login.loggingIn', 'Anmeldung läuft…')}</span>
                </>
              ) : (
                <>
                  {t('login.submit')}
                  <ArrowRight weight="bold" className="w-5 h-5" aria-hidden="true" />
                </>
              )}
            </button>
          </form>

          <p className="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            {t('login.noAccount')}{' '}
            <Link to="/register" className="text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium">{t('login.register')}</Link>
          </p>
          <p className="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
            <Link to="/privacy" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.privacy')}</Link>
            {' · '}
            <Link to="/terms" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.terms')}</Link>
            {' · '}
            <Link to="/legal" className="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">{t('footer.imprint')}</Link>
          </p>
          <p className="mt-2 text-center text-xs text-gray-300 dark:text-gray-600 flex items-center gap-1 justify-center">
            {t('system.poweredBy', 'Powered by ParkHub')} ·{' '}
            <a href="https://github.com/nash87/parkhub" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 hover:text-gray-500 transition-colors">
              <GithubLogo weight="bold" className="w-3 h-3" /> {t('system.openSource', 'Open Source')}
            </a>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
