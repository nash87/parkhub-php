import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Lock, Buildings, Car, Users, CheckCircle, ArrowRight, ArrowLeft, Database, Sparkle, ClipboardText, BuildingApartment, Heart, Coins, Globe } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import { useUseCaseStore, UseCaseType } from '../stores/usecase';

// Auth context available via provider
import toast from 'react-hot-toast';

function getAuthHeaders(): Record<string, string> {
  const token = localStorage.getItem('parkhub_token');
  return token
    ? { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
    : { 'Content-Type': 'application/json' };
}




interface OnboardingWizardProps {
  onComplete: () => void;
}

const useCaseColors: Record<string, { bg: string; bgActive: string; text: string; border: string; check: string }> = {
  corporate: { bg: "bg-primary-100 dark:bg-primary-900/40", bgActive: "bg-primary-50 dark:bg-primary-900/20", text: "text-primary-600 dark:text-primary-400", border: "border-primary-500", check: "text-primary-500" },
  residential: { bg: "bg-emerald-100 dark:bg-emerald-900/40", bgActive: "bg-emerald-50 dark:bg-emerald-900/20", text: "text-emerald-600 dark:text-emerald-400", border: "border-emerald-500", check: "text-emerald-500" },
  family: { bg: "bg-amber-100 dark:bg-amber-900/40", bgActive: "bg-amber-50 dark:bg-amber-900/20", text: "text-amber-600 dark:text-amber-400", border: "border-amber-500", check: "text-amber-500" },
  rental: { bg: "bg-purple-100 dark:bg-purple-900/40", bgActive: "bg-purple-50 dark:bg-purple-900/20", text: "text-purple-600 dark:text-purple-400", border: "border-purple-500", check: "text-purple-500" },
  public: { bg: "bg-red-100 dark:bg-red-900/40", bgActive: "bg-red-50 dark:bg-red-900/20", text: "text-red-600 dark:text-red-400", border: "border-red-500", check: "text-red-500" },
};

const useCaseIcons: Record<string, typeof Buildings> = {  corporate: Buildings,  residential: BuildingApartment,  family: Heart,  rental: Coins,  public: Globe,};
const useCaseOptions: { type: UseCaseType; icon: typeof Buildings }[] = [
  { type: "corporate", icon: Buildings },
  { type: "residential", icon: BuildingApartment },
  { type: "family", icon: Heart },
  { type: "rental", icon: Coins },
  { type: "public", icon: Globe },
];

export function OnboardingWizard({ onComplete }: OnboardingWizardProps) {
  const { t } = useTranslation();
  
  const navigate = useNavigate();
  const [step, setStep] = useState(0);
  const [visible, setVisible] = useState(false);
  const [loading, setLoading] = useState(true);

  // Form state
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [passwordError, setPasswordError] = useState('');
  const [passwordChanged, setPasswordChanged] = useState(false);
  const [companyName, setCompanyName] = useState('');
  const [loadDummyData, setLoadDummyData] = useState<boolean | null>(null);
  const [selfRegistration, setSelfRegistration] = useState(false);

  // Use-case state
  const { useCase, setUseCase } = useUseCaseStore();

  // Setup status from API
  

  useEffect(() => {
    checkSetupStatus();
  }, []);

  async function checkSetupStatus() {
    try {
      const res = await fetch('/api/v1/setup/status');
      const data = await res.json();
      if (data.success && data.data) {
        // setup status loaded
        // Show wizard if setup not complete
        if (!data.data.setup_complete) {
          setVisible(true);
          // If password needs changing, force step 0
          if (data.data.needs_password_change) {
            setStep(0);
          }
        }
      }
    } catch {
      // If API fails, check localStorage fallback
      if (!localStorage.getItem('parkhub_onboarding_done')) {
        setVisible(true);
      }
    } finally {
      setLoading(false);
    }
  }

  async function handlePasswordChange() {
    setPasswordError('');
    if (newPassword.length < 8) {
      setPasswordError(t('register.passwordTooShort'));
      return false;
    }
    if (newPassword !== confirmPassword) {
      setPasswordError(t('register.passwordMismatch'));
      return false;
    }

    try {
      // Use setup endpoint (no auth required during initial setup)
      const res = await fetch('/api/v1/setup/change-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
      });
      const data = await res.json();
      if (data.success) {
        // Re-login with new password to get fresh token
        try {
          const loginRes = await fetch('/api/v1/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: 'admin', password: newPassword }),
          });
          const loginData = await loginRes.json();
          if (loginData.success && loginData.data?.tokens?.access_token) {
            localStorage.setItem('parkhub_token', loginData.data.tokens.access_token);
            if (loginData.data.tokens.refresh_token)
              localStorage.setItem('parkhub_refresh_token', loginData.data.tokens.refresh_token);
          }
        } catch { /* best effort */ }
        setPasswordChanged(true);
        toast.success(t('onboarding.steps.password.changed'));
        return true;
      } else {
        setPasswordError(data.error?.message || t('onboarding.steps.password.changeFailed'));
        return false;
      }
    } catch {
      setPasswordError(t('onboarding.steps.password.networkError'));
      return false;
    }
  }

  async function handleCompanySave() {
    if (!companyName.trim()) return true; // Skip if empty
    try {
      await fetch('/api/v1/admin/branding', {
        method: 'PUT',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          company_name: companyName,
          primary_color: '#3B82F6',
          secondary_color: '#1D4ED8',
          login_background_color: '#2563EB',
        }),
      });
      return true;
    } catch {
      return true; // Don't block on branding errors
    }
  }

  async function handleDummyData() {
    if (!loadDummyData) return true;
    // The server already has create_sample_parking_lot function
    // We need to trigger it via API - create a lot manually
    try {
      
      // Step 1: Create lot with CreateLotRequest format
      const createRes = await fetch('/api/v1/lots', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          name: t('onboarding.steps.dummyData.lotName'),
          address: t('onboarding.steps.dummyData.lotAddress'),
          total_slots: 10,
        }),
      });
      const createData = await createRes.json();
      if (createData.success && createData.data?.id) {
        // Step 2: Set layout via PUT /api/v1/lots/:id/layout
        const newLotId = createData.data.id;
        const slots = Array.from({ length: 10 }, (_el, i) => ({
          id: crypto.randomUUID(),
          number: `P${i + 1}`,
          status: 'available',
        }));
        await fetch(`/api/v1/lots/${newLotId}/layout`, {
          method: 'PUT',
          headers: getAuthHeaders(),
          body: JSON.stringify({
            rows: [
              { id: crypto.randomUUID(), label: t('onboarding.steps.dummyData.rowA'), side: 'top', slots: slots.slice(0, 5) },
              { id: crypto.randomUUID(), label: t('onboarding.steps.dummyData.rowB'), side: 'bottom', slots: slots.slice(5, 10) },
            ],
            road_label: t('onboarding.steps.dummyData.roadLabel'),
          }),
        });
      }
      toast.success(t('onboarding.steps.dummyData.loaded'));
      return true;
    } catch {
      return true;
    }
  }

  async function completeSetup() {
    try {
      await fetch('/api/v1/setup/complete', {
        method: 'POST',
        headers: getAuthHeaders(),
      });
    } catch { /* ignore */ }
    localStorage.setItem('parkhub_onboarding_done', 'true');
    setVisible(false);
    onComplete();
    navigate('/');
  }

  async function handleNext() {
    if (step === 0) {
      // Password change step - required
      if (!passwordChanged) {
        const ok = await handlePasswordChange();
        if (!ok) return;
      }
      setStep(1);
    } else if (step === 1) {
      // Use-case selection - always proceed (default is corporate)
      setStep(2);
    } else if (step === 2) {
      // Organization name setup
      await handleCompanySave();
      setStep(3);
    } else if (step === 3) {
      // Dummy data question
      if (loadDummyData) {
        await handleDummyData();
        setStep(5); // Skip lot creation, go to users
      } else if (loadDummyData === false) {
        setStep(4); // Go to manual lot creation
      }
    } else if (step === 4) {
      // Lot creation - user can do it in admin later
      setStep(5);
    } else if (step === 5) {
      // User management
      setStep(6);
    } else if (step === 6) {
      // Done
      await completeSetup();
    }
  }

  if (loading || !visible) return null;

  const steps = [
    { icon: Lock, key: 'password' },
    { icon: Buildings, key: 'usecase' },
    { icon: Buildings, key: 'company' },
    { icon: Database, key: 'dummyData' },
    { icon: Car, key: 'lot' },
    { icon: Users, key: 'users' },
    { icon: CheckCircle, key: 'done' },
  ];

  // const Icon = steps[step].icon;
  const isLast = step === steps.length - 1;
  const canGoNext = step === 0 ? (passwordChanged || (currentPassword && newPassword && confirmPassword)) :
                    step === 3 ? loadDummyData !== null :
                    true;

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <motion.div
        initial={{ scale: 0.9, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className="card w-full max-w-lg p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto"
      >
        <div className="flex items-center justify-between mb-4 sm:mb-6">
          <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">
            {t('onboarding.title')}
          </h2>
          <span className="text-xs text-gray-400">{step + 1}/{steps.length}</span>
        </div>

        {/* Progress */}
        <div className="flex gap-1 mb-6 sm:mb-8">
          {steps.map((_, i) => (
            <div key={i} className={`flex-1 h-1.5 rounded-full transition-colors ${i <= step ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700'}`} />
          ))}
        </div>

        <AnimatePresence mode="wait">
          <motion.div
            key={step}
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
          >
            {/* Step 0: Password Change */}
            {step === 0 && (
              <div className="space-y-4">
                <div className="text-center mb-4">
                  <div className="w-16 h-16 mx-auto rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-3">
                    <Lock weight="fill" className="w-8 h-8 text-red-600 dark:text-red-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {t('onboarding.steps.password.title')}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {t('onboarding.steps.password.desc')}
                  </p>
                  <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-2">
                    <ClipboardText size={16} className="inline mr-1" />{t('onboarding.steps.password.defaultCredentials')}
                  </p>
                </div>
                {passwordChanged ? (
                  <div className="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl text-center">
                    <CheckCircle weight="fill" className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                    <p className="font-medium text-emerald-700 dark:text-emerald-300">{t('onboarding.steps.password.changed')}</p>
                  </div>
                ) : (
                  <>
                    <div>
                      <label className="label">{t('onboarding.steps.password.currentPassword')}</label>
                      <input type="password" className="input" value={currentPassword}
                        onChange={e => setCurrentPassword(e.target.value)} placeholder={t('onboarding.steps.password.defaultPlaceholder')} />
                    </div>
                    <div>
                      <label className="label">{t('onboarding.steps.password.newPassword')}</label>
                      <input type="password" className="input" value={newPassword}
                        onChange={e => setNewPassword(e.target.value)} placeholder={t('onboarding.steps.password.minCharsPlaceholder')} />
                    </div>
                    <div>
                      <label className="label">{t('onboarding.steps.password.confirmPassword')}</label>
                      <input type="password" className="input" value={confirmPassword}
                        onChange={e => setConfirmPassword(e.target.value)} placeholder={t('onboarding.steps.password.repeatPlaceholder')} />
                    </div>
                    {passwordError && <p className="text-sm text-red-500">{passwordError}</p>}
                  </>
                )}
              </div>
            )}

            {/* Step 1: Use-Case Selection */}
            {step === 1 && (
              <div className="space-y-4">
                <div className="text-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {t('usecase.title')}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {t('usecase.subtitle')}
                  </p>
                </div>
                <div className="grid grid-cols-1 gap-3">
                  {useCaseOptions.map(({ type, icon: Icon }) => (
                    <button
                      key={type}
                      onClick={() => setUseCase(type)}
                      className={`p-4 rounded-xl border-2 text-left transition-all flex items-center gap-4 ${
                        useCase === type
                          ? `${useCaseColors[type].border} ${useCaseColors[type].bgActive}`
                          : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'
                      }`}
                    >
                      <div className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${
                        useCase === type
                          ? useCaseColors[type].bg
                          : 'bg-gray-100 dark:bg-gray-800'
                      }`}>
                        <Icon weight="fill" className={`w-5 h-5 ${
                          useCase === type
                            ? useCaseColors[type].text
                            : 'text-gray-500 dark:text-gray-400'
                        }`} />
                      </div>
                      <div className="min-w-0">
                        <p className="font-medium text-sm text-gray-900 dark:text-white">
                          {t(`usecase.${type}.name`)}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                          {t(`usecase.${type}.description`)}
                        </p>
                      </div>
                      {useCase === type && (
                        <CheckCircle weight="fill" className={`w-5 h-5 ${useCaseColors[type].check} ml-auto flex-shrink-0`} />
                      )}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Step 2: Organization Name Setup */}
            {step === 2 && (
              <div className="space-y-4">
                <div className="text-center mb-4">
                  <div className="w-16 h-16 mx-auto rounded-2xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mb-3">
                    {(() => { const UCIcon = useCaseIcons[useCase || "corporate"] || Buildings; return <UCIcon weight="fill" className="w-8 h-8 text-primary-600 dark:text-primary-400" />; })()}
                  </div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {t(`onboarding.steps.company_${useCase}.title`, t("onboarding.steps.company.title"))}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {t(`onboarding.steps.company_${useCase}.desc`, t("onboarding.steps.company.desc"))}
                  </p>
                </div>
                <div>
                  <label className="label">{t(`onboarding.steps.company_${useCase}.nameLabel`, t("onboarding.steps.company.nameLabel"))}</label>
                  <input type="text" className="input" value={companyName}
                    onChange={e => setCompanyName(e.target.value)} placeholder={t(`onboarding.steps.company_${useCase}.namePlaceholder`, t("onboarding.steps.company.namePlaceholder"))} />
                </div>
              </div>
            )}

            {/* Step 3: Dummy Data */}
            {step === 3 && (
              <div className="space-y-4">
                <div className="text-center mb-4">
                  <div className="w-16 h-16 mx-auto rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-3">
                    <Database weight="fill" className="w-8 h-8 text-amber-600 dark:text-amber-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {t('onboarding.steps.dummyData.title')}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {t('onboarding.steps.dummyData.desc')}
                  </p>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    onClick={() => setLoadDummyData(true)}
                    className={`p-4 rounded-xl border-2 text-center transition-all ${loadDummyData === true ? `${useCaseColors[useCase || "corporate"].border} ${useCaseColors[useCase || "corporate"].bgActive}` : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                  >
                    <Sparkle weight="fill" className="w-6 h-6 mx-auto mb-2 text-primary-500" />
                    <p className="font-medium text-sm">{t('onboarding.steps.dummyData.yes')}</p>
                    <p className="text-xs text-gray-400 mt-1">{t('onboarding.steps.dummyData.yesDesc')}</p>
                  </button>
                  <button
                    onClick={() => setLoadDummyData(false)}
                    className={`p-4 rounded-xl border-2 text-center transition-all ${loadDummyData === false ? `${useCaseColors[useCase || "corporate"].border} ${useCaseColors[useCase || "corporate"].bgActive}` : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                  >
                    <Buildings weight="regular" className="w-6 h-6 mx-auto mb-2 text-gray-400" />
                    <p className="font-medium text-sm">{t('onboarding.steps.dummyData.no')}</p>
                    <p className="text-xs text-gray-400 mt-1">{t('onboarding.steps.dummyData.noDesc')}</p>
                  </button>
                </div>
              </div>
            )}

            {/* Step 4: Lot Creation hint */}
            {step === 4 && (
              <div className="space-y-4 text-center">
                <div className="w-16 h-16 mx-auto rounded-2xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mb-3">
                  <Car weight="fill" className="w-8 h-8 text-primary-600 dark:text-primary-400" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                  {t('onboarding.steps.lot.title')}
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {t('onboarding.steps.lot.hint')}
                </p>
                <p className="text-xs text-gray-400">
                  {t('onboarding.steps.lot.later')}
                </p>
              </div>
            )}

            {/* Step 5: Users */}
            {step === 5 && (
              <div className="space-y-4">
                <div className="text-center mb-4">
                  <div className="w-16 h-16 mx-auto rounded-2xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-3">
                    <Users weight="fill" className="w-8 h-8 text-purple-600 dark:text-purple-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {t('onboarding.steps.users.title')}
                  </h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {t('onboarding.steps.users.desc')}
                  </p>
                </div>
                <div className="grid grid-cols-1 gap-3">
                  <button
                    onClick={() => setSelfRegistration(true)}
                    className={`p-4 rounded-xl border-2 text-left transition-all ${selfRegistration ? `${useCaseColors[useCase || "corporate"].border} ${useCaseColors[useCase || "corporate"].bgActive}` : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                  >
                    <p className="font-medium text-sm">{t('onboarding.steps.users.selfRegister')}</p>
                    <p className="text-xs text-gray-400 mt-1">{t('onboarding.steps.users.selfRegisterDesc')}</p>
                  </button>
                  <button
                    onClick={() => setSelfRegistration(false)}
                    className={`p-4 rounded-xl border-2 text-left transition-all ${!selfRegistration ? `${useCaseColors[useCase || "corporate"].border} ${useCaseColors[useCase || "corporate"].bgActive}` : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                  >
                    <p className="font-medium text-sm">{t('onboarding.steps.users.manual')}</p>
                    <p className="text-xs text-gray-400 mt-1">{t('onboarding.steps.users.manualDesc')}</p>
                  </button>
                </div>
              </div>
            )}

            {/* Step 6: Done */}
            {step === 6 && (
              <div className="space-y-4 text-center">
                <div className="w-16 h-16 mx-auto rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-3">
                  <CheckCircle weight="fill" className="w-8 h-8 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                  {t('onboarding.steps.done.title')}
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {t('onboarding.steps.done.desc')}
                </p>
                <div className="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 text-left space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-500">{t('onboarding.steps.done.password')}:</span>
                    <span className="font-medium text-emerald-600 flex items-center gap-1"><CheckCircle size={16} weight="fill" />{t('onboarding.steps.done.changed')}</span>
                  </div>
                  {companyName && (
                    <div className="flex justify-between">
                      <span className="text-gray-500">{t(`onboarding.steps.done.company_${useCase}`, t('onboarding.steps.done.company'))}:</span>
                      <span className="font-medium">{companyName}</span>
                    </div>
                  )}
                  <div className="flex justify-between">
                    <span className="text-gray-500">{t('onboarding.steps.done.dummyData')}:</span>
                    <span className="font-medium">{loadDummyData ? t('common.yes') : t('common.no')}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">{t('onboarding.steps.done.registration')}:</span>
                    <span className="font-medium">{selfRegistration ? t('onboarding.steps.users.selfRegister') : t('onboarding.steps.users.manual')}</span>
                  </div>
                </div>
              </div>
            )}
          </motion.div>
        </AnimatePresence>

        <div className="flex items-center justify-between mt-6">
          <button
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0}
            className="btn btn-secondary btn-sm"
          >
            <ArrowLeft weight="bold" className="w-4 h-4" /> {t('common.back')}
          </button>
          {isLast ? (
            <button onClick={handleNext} className="btn btn-primary">
              {t('onboarding.finish')} <CheckCircle weight="bold" className="w-4 h-4" />
            </button>
          ) : (
            <button onClick={handleNext} disabled={!canGoNext} className="btn btn-primary btn-sm">
              {step === 0 && !passwordChanged ? t('onboarding.steps.password.changeBtn') : t('common.next')} <ArrowRight weight="bold" className="w-4 h-4" />
            </button>
          )}
        </div>
      </motion.div>
    </div>
  );
}
