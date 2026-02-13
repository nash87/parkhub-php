import React, { useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { Buildings, House, UsersThree, Key, Warehouse, MapTrifold, ArrowRight, Check } from '@phosphor-icons/react';

const USE_CASES = [
  { id: 'corporate', icon: Buildings, color: 'blue', name: 'Corporate', desc: 'Perfect for workplaces and business offices' },
  { id: 'residential', icon: House, color: 'emerald', name: 'Residential', desc: 'Great for apartment buildings and condos' },
  { id: 'family', icon: UsersThree, color: 'amber', name: 'Family', desc: 'Keep your family parking organized' },
  { id: 'rental', icon: Key, color: 'purple', name: 'Rental', desc: 'Manage parking for rental properties' },
  { id: 'public', icon: MapTrifold, color: 'red', name: 'Public', desc: 'Public or shared-use parking facility' },
];

interface Props { onComplete: () => void; }

export default function Setup({ onComplete }: Props) {
  const [step, setStep] = useState(0);
  const [useCase, setUseCase] = useState('corporate');
  const [companyName, setCompanyName] = useState('');
  const [admin, setAdmin] = useState({ username: 'admin', name: 'Administrator', email: 'admin@example.com', password: '' });
  const [sampleData, setSampleData] = useState(true);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleFinish = async () => {
    setError('');
    setLoading(true);
    try {
      await api.post('/setup/init', {
        company_name: companyName,
        admin_username: admin.username,
        admin_password: admin.password,
        admin_email: admin.email,
        admin_name: admin.name,
        use_case: useCase,
        create_sample_data: sampleData,
      });
      onComplete();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 flex items-center justify-center p-4">
      <div className="w-full max-w-2xl">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-amber-600 mb-2">🅿️ ParkHub</h1>
          <p className="text-slate-500">{t('onboarding.title')}</p>
        </div>

        {error && <div className="bg-red-50 dark:bg-red-900/30 text-red-600 p-3 rounded-lg mb-4">{error}</div>}

        {/* Step 0: Use Case */}
        {step === 0 && (
          <div>
            <h2 className="text-xl font-semibold text-center mb-6">{t('usecase.title')}</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {USE_CASES.map(uc => (
                <button key={uc.id} onClick={() => { setUseCase(uc.id); setStep(1); }}
                  className={`card text-left hover:ring-2 hover:ring-amber-500 ${useCase === uc.id ? 'ring-2 ring-amber-500' : ''}`}>
                  <uc.icon size={32} className={`text-${uc.color}-600 mb-2`} />
                  <p className="font-semibold">{uc.name}</p>
                  <p className="text-sm text-slate-500">{uc.desc}</p>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Step 1: Company Name */}
        {step === 1 && (
          <div className="card">
            <h2 className="text-xl font-semibold mb-4">Company / Organization Name</h2>
            <input type="text" value={companyName} onChange={e => setCompanyName(e.target.value)} className="input mb-4" placeholder="e.g. My Company Inc." required />
            <div className="flex gap-2">
              <button onClick={() => setStep(0)} className="btn-secondary">{t('common.back')}</button>
              <button onClick={() => setStep(2)} className="btn-primary flex-1" disabled={!companyName}>{t('common.next')} <ArrowRight size={16} className="inline ml-1" /></button>
            </div>
          </div>
        )}

        {/* Step 2: Admin Account */}
        {step === 2 && (
          <div className="card">
            <h2 className="text-xl font-semibold mb-4">Create Admin Account</h2>
            <div className="space-y-3">
              <input type="text" value={admin.name} onChange={e => setAdmin(a => ({ ...a, name: e.target.value }))} className="input" placeholder="Full name" required />
              <input type="text" value={admin.username} onChange={e => setAdmin(a => ({ ...a, username: e.target.value }))} className="input" placeholder="Username" required />
              <input type="email" value={admin.email} onChange={e => setAdmin(a => ({ ...a, email: e.target.value }))} className="input" placeholder="Email" required />
              <input type="password" value={admin.password} onChange={e => setAdmin(a => ({ ...a, password: e.target.value }))} className="input" placeholder="Password (min. 8 chars)" required />
            </div>
            <div className="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
              <label className="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" checked={sampleData} onChange={e => setSampleData(e.target.checked)} className="w-4 h-4 accent-amber-600" />
                <span className="text-sm">Create sample parking lot with 10 slots</span>
              </label>
            </div>
            <div className="flex gap-2 mt-4">
              <button onClick={() => setStep(1)} className="btn-secondary">{t('common.back')}</button>
              <button onClick={handleFinish} className="btn-primary flex-1" disabled={loading || admin.password.length < 8}>
                {loading ? 'Setting up...' : t('onboarding.finish')}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
