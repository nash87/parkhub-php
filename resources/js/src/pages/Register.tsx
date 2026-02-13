import { useState } from 'react';
import { Navigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Car, Eye, EyeSlash, ArrowRight, SpinnerGap, User, Envelope, Lock , Moon, Sun, Globe } from '@phosphor-icons/react';
import { useAuth } from '../context/auth-hook';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { useTheme } from '../stores/theme';

export function RegisterPage() {
  const { t, i18n } = useTranslation();
  const { register, isAuthenticated, isLoading } = useAuth();
  const [formData, setFormData] = useState({ username: '', email: '', password: '', confirmPassword: '', name: '' });
  const { isDark, toggle: toggleTheme } = useTheme();
  const currentLang = i18n.language?.startsWith("en") ? "en" : "de";
  const toggleLang = () => i18n.changeLanguage(currentLang === "de" ? "en" : "de");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  if (isLoading) {
    return <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950"><SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" /></div>;
  }
  if (isAuthenticated) return <Navigate to="/" replace />;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (formData.password !== formData.confirmPassword) { toast.error(t('register.passwordMismatch')); return; }
    if (formData.password.length < 8) { toast.error(t('register.passwordTooShort')); return; }
    setLoading(true);
    const success = await register({ username: formData.username, email: formData.email, password: formData.password, name: formData.name });
    if (success) { toast.success(t('register.welcomeMessage')); } else { toast.error(t('register.failed')); }
    setLoading(false);
  }

  return (
    <div className="min-h-screen flex bg-gray-50 dark:bg-gray-950 relative">
      <div className="absolute top-4 right-4 z-50 flex items-center gap-2">
        <button onClick={toggleLang} className="p-2 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-1.5 text-sm font-medium"><Globe weight="bold" className="w-4 h-4" />{currentLang.toUpperCase()}</button>
        <button onClick={toggleTheme} className="p-2 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">{isDark ? <Sun weight="fill" className="w-5 h-5" /> : <Moon weight="fill" className="w-5 h-5" />}</button>
      </div>
      <div className="hidden lg:flex lg:w-1/2 bg-primary-600 dark:bg-primary-700 relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-primary-500 to-primary-700" />
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-20 left-20 w-64 h-64 bg-white rounded-full blur-3xl" />
          <div className="absolute bottom-20 right-20 w-96 h-96 bg-white rounded-full blur-3xl" />
        </div>
        <div className="relative z-10 flex flex-col justify-center px-16 text-white">
          <div className="flex items-center gap-4 mb-8">
            <div className="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center"><Car weight="fill" className="w-8 h-8" /></div>
            <span className="text-3xl font-bold">ParkHub</span>
          </div>
          <h1 className="text-4xl font-bold mb-4">{t('register.heroTitle')}</h1>
          <p className="text-lg text-white/80">{t('register.heroSubtitle')}</p>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center px-6 py-12">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <div className="lg:hidden flex items-center gap-3 mb-8">
            <div className="w-12 h-12 bg-primary-600 rounded-2xl flex items-center justify-center"><Car weight="fill" className="w-7 h-7 text-white" /></div>
            <span className="text-2xl font-bold text-gray-900 dark:text-white">ParkHub</span>
          </div>
          <div className="mb-8">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">{t('register.title')}</h2>
            <p className="text-gray-500 dark:text-gray-400 mt-2">{t('register.subtitle')}</p>
          </div>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="label">{t('register.fullName')}</label>
              <div className="relative">
                <User weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input type="text" value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })} className="input pl-11" placeholder={t('register.fullNamePlaceholder')} required />
              </div>
            </div>
            <div>
              <label className="label">{t('register.username')}</label>
              <div className="relative">
                <User weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input type="text" value={formData.username} onChange={(e) => setFormData({ ...formData, username: e.target.value })} className="input pl-11" placeholder={t('register.usernamePlaceholder')} required />
              </div>
            </div>
            <div>
              <label className="label">{t('register.email')}</label>
              <div className="relative">
                <Envelope weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input type="email" value={formData.email} onChange={(e) => setFormData({ ...formData, email: e.target.value })} className="input pl-11" placeholder={t('register.emailPlaceholder')} required />
              </div>
            </div>
            <div>
              <label className="label">{t('register.password')}</label>
              <div className="relative">
                <Lock weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input type={showPassword ? 'text' : 'password'} value={formData.password} onChange={(e) => setFormData({ ...formData, password: e.target.value })} className="input pl-11 pr-12" placeholder="••••••••" required />
                <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  {showPassword ? <EyeSlash className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                </button>
              </div>
            </div>
            <div>
              <label className="label">{t('register.confirmPassword')}</label>
              <div className="relative">
                <Lock weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input type={showPassword ? 'text' : 'password'} value={formData.confirmPassword} onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })} className="input pl-11" placeholder="••••••••" required />
              </div>
            </div>
            <p className="text-xs text-gray-400 dark:text-gray-500 mt-4 text-center">{t("register.privacyNotice")} <Link to="/privacy" className="text-primary-600 hover:underline">{t("footer.privacy")}</Link></p>
            <button type="submit" disabled={loading} className="btn btn-primary w-full justify-center mt-6">
              {loading ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" /> : <>{t('register.submit')} <ArrowRight weight="bold" className="w-5 h-5" /></>}
            </button>
          </form>
          <p className="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            {t('register.alreadyRegistered')}{' '}
            <Link to="/login" className="text-primary-600 hover:text-primary-700 font-medium">{t('register.loginLink')}</Link>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
