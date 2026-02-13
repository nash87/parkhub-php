import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { t } from '../i18n';
import { Car, Eye, EyeSlash } from '@phosphor-icons/react';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPw, setShowPw] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(username, password);
      navigate('/');
    } catch (err: any) {
      setError(err.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex">
      {/* Left - Hero */}
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-amber-600 to-amber-800 items-center justify-center p-12">
        <div className="text-white max-w-md">
          <Car size={64} className="mb-6" />
          <h1 className="text-4xl font-bold mb-4">{t('login.heroTitle')}</h1>
          <p className="text-amber-100 text-lg">{t('login.heroSubtitle')}</p>
        </div>
      </div>
      {/* Right - Form */}
      <div className="flex-1 flex items-center justify-center p-8 bg-slate-50 dark:bg-slate-900">
        <div className="w-full max-w-md">
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-amber-600">🅿️ ParkHub</h1>
            <h2 className="text-xl font-semibold mt-4">{t('login.title')}</h2>
            <p className="text-slate-500 mt-1">{t('login.subtitle')}</p>
          </div>
          {error && <div className="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-lg mb-4 text-sm">{error}</div>}
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">{t('login.username')}</label>
              <input type="text" value={username} onChange={e => setUsername(e.target.value)} className="input" placeholder={t('login.usernamePlaceholder')} required />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">{t('login.password')}</label>
              <div className="relative">
                <input type={showPw ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} className="input pr-10" required />
                <button type="button" onClick={() => setShowPw(!showPw)} className="absolute right-3 top-2.5 text-slate-400">
                  {showPw ? <EyeSlash size={20} /> : <Eye size={20} />}
                </button>
              </div>
            </div>
            <button type="submit" disabled={loading} className="btn-primary w-full">
              {loading ? '...' : t('login.submit')}
            </button>
          </form>
          <p className="text-center text-sm mt-6 text-slate-500">
            {t('login.noAccount')} <Link to="/register" className="text-amber-600 font-medium hover:underline">{t('login.register')}</Link>
          </p>
          <div className="flex justify-center gap-4 mt-8 text-xs text-slate-400">
            <Link to="/about">About</Link>
            <Link to="/privacy">Privacy</Link>
            <Link to="/terms">Terms</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
