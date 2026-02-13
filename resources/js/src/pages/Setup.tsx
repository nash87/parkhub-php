import { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { SpinnerGap } from '@phosphor-icons/react';
import { useAuth } from '../context/auth-hook';
import { useSetupStatus } from '../components/setup-status-hook';
import { OnboardingWizard } from '../components/OnboardingWizard';
import { useTranslation } from 'react-i18next';

export function SetupPage() {
  const { t } = useTranslation();
  const { isAuthenticated, isLoading: authLoading, login } = useAuth();
  const { setupComplete, recheckSetup } = useSetupStatus();
  const [autoLoginAttempted, setAutoLoginAttempted] = useState(false);
  const [autoLoginFailed, setAutoLoginFailed] = useState(false);

  // Auto-login as admin/admin if not authenticated — MUST be before any conditional returns
  useEffect(() => {
    if (authLoading || isAuthenticated || autoLoginAttempted) return;
    const id = requestAnimationFrame(() => {
      setAutoLoginAttempted(true);
    });
    login('admin', 'admin').then(success => {
      if (!success) {
        setAutoLoginFailed(true);
      }
    });
    return () => cancelAnimationFrame(id);
  }, [authLoading, isAuthenticated, autoLoginAttempted, login]);

  // If setup is already complete, redirect to home
  if (setupComplete) {
    return <Navigate to="/" replace />;
  }

  // Show loading while auth is loading or auto-login in progress
  if (authLoading || (!isAuthenticated && !autoLoginFailed && !autoLoginAttempted)) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 gap-3">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('onboarding.preparingSetup', 'Preparing setup...')}</p>
      </div>
    );
  }

  // If auto-login failed, show a manual login form or message instead of redirecting to /login (which would loop)
  if (autoLoginFailed && !isAuthenticated) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 gap-4 p-4">
        <div className="card p-8 max-w-md w-full text-center space-y-4">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
            {t('onboarding.loginRequired', 'Admin Login Required')}
          </h2>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            {t('onboarding.loginRequiredDesc', 'Please log in with your admin credentials to complete the setup.')}
          </p>
          <a href="/login" className="btn btn-primary w-full justify-center">
            {t('auth.login', 'Login')}
          </a>
        </div>
      </div>
    );
  }

  // Auto-login still in progress
  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 gap-3">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('onboarding.preparingSetup', 'Preparing setup...')}</p>
      </div>
    );
  }

  // Authenticated — show the onboarding wizard
  return (
    <OnboardingWizard
      onComplete={async () => {
        await recheckSetup();
      }}
    />
  );
}
