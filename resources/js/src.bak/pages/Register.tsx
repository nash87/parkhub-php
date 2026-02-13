import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { t } from '../i18n';
import { UserPlus } from '@phosphor-icons/react';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', username: '', email: '', password: '', confirmPassword: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (form.password !== form.confirmPassword) { setError(t('register.passwordMismatch')); return; }
    if (form.password.length < 8) { setError(t('register.passwordTooShort')); return; }
    setError('');
    setLoading(true);
    try {
      await register({ name: form.name, username: form.username, email: form.email, password: form.password });
      navigate('/');
    } catch (err: any) {
      setError(err.message || 'Registration failed');
    } finally {
      setLoading(false);
    }
  };

  const update = (k: string, v: string) => setForm(prev => ({ ...prev, [k]: v }));

  return (
    <div className="min-h-screen flex">
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-amber-600 to-amber-800 items-center justify-center p-12">
        <div className="text-white max-w-md">
          <UserPlus size={64} className="mb-6" />
          <h1 className="text-4xl font-bold mb-4">{t('register.heroTitle')}</h1>
          <p className="text-amber-100 text-lg">{t('register.heroSubtitle')}</p>
        </div>
      </div>
      <div className="flex-1 flex items-center justify-center p-8 bg-slate-50 dark:bg-slate-900">
        <div className="w-full max-w-md">
          <h2 className="text-xl font-semibold text-center mb-2">{t('register.title')}</h2>
          <p className="text-slate-500 text-center mb-6">{t('register.subtitle')}</p>
          {error && <div className="bg-red-50 dark:bg-red-900/30 text-red-600 p-3 rounded-lg mb-4 text-sm">{error}</div>}
          <form onSubmit={handleSubmit} className="space-y-4">
            <input type="text" value={form.name} onChange={e => update('name', e.target.value)} className="input" placeholder={t('register.fullNamePlaceholder')} required />
            <input type="text" value={form.username} onChange={e => update('username', e.target.value)} className="input" placeholder={t('register.usernamePlaceholder')} required />
            <input type="email" value={form.email} onChange={e => update('email', e.target.value)} className="input" placeholder={t('register.emailPlaceholder')} required />
            <input type="password" value={form.password} onChange={e => update('password', e.target.value)} className="input" placeholder="Password (min. 8 chars)" required />
            <input type="password" value={form.confirmPassword} onChange={e => update('confirmPassword', e.target.value)} className="input" placeholder="Confirm password" required />
            <button type="submit" disabled={loading} className="btn-primary w-full">{loading ? '...' : t('register.submit')}</button>
          </form>
          <p className="text-center text-sm mt-6 text-slate-500">
            {t('register.alreadyRegistered')} <Link to="/login" className="text-amber-600 font-medium">{t('register.loginLink')}</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
