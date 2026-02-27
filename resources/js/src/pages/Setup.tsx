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
  // Track whether an admin account already exists (fetched from setup/status)
  const [hasAdmin, setHasAdmin] = useState<boolean | null>(null);

  // Fetch setup status to check if an admin exists before attempting auto-login
  useEffect(() => {
    if (hasAdmin !== null) return;
    fetch((import.meta.env.VITE_API_URL || '') + '/api/v1/setup/status')
      .then(r => r.json())
      .then(d => setHasAdmin(!!d?.data?.has_admin))
      .catch(() => setHasAdmin(true)); // Assume admin exists on error — fail safe
  }, [hasAdmin]);

  // Auto-login as admin/admin ONLY when no admin account exists yet (true first install)
  // If an admin already exists, require the user to log in via /login — prevents auth bypass.
  useEffect(() => {
    if (authLoading || isAuthenticated || autoLoginAttempted || hasAdmin === null || hasAdmin) return;
    const id = requestAnimationFrame(() => {
      setAutoLoginAttempted(true);
    });
    login('admin', 'admin').then(success => {
      if (!success) {
        setAutoLoginFailed(true);
      }
    });
    return () => cancelAnimationFrame(id);
  }, [authLoading, isAuthenticated, autoLoginAttempted, hasAdmin, login]);

  // If setup is already complete, redirect to home
  if (setupComplete) {
    return <Navigate to="/" replace />;
  }

  // If an admin exists but the user is not authenticated, redirect to normal login
  if (!authLoading && hasAdmin && !isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  // Show loading while auth is loading, hasAdmin check is pending, or auto-login in progress
  if (authLoading || hasAdmin === null || (!isAuthenticated && !autoLoginFailed && !autoLoginAttempted)) {
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
