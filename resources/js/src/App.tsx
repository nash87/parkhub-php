import { useEffect, lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import { useAuth } from './context/auth-hook';
import { BrandingProvider } from './context/BrandingContext';
import { useTheme, applyTheme } from './stores/theme';
import { useAccessibility, applyAccessibility } from './stores/accessibility';
import { usePalette, applyPalette } from "./stores/palette";
import { useTranslation } from 'react-i18next';
import { supportedLanguages } from './i18n';
import { Layout } from './components/Layout';
import { LoginPage } from './pages/Login';
import { RegisterPage } from './pages/Register';
import { DashboardPage } from './pages/Dashboard';
import { BookPage } from './pages/Book';
import { BookingsPage } from './pages/Bookings';
import { VehiclesPage } from './pages/Vehicles';
import { ConsentBanner } from './components/ConsentBanner';
import { SpinnerGap } from '@phosphor-icons/react';
import { WelcomePage } from './pages/Welcome';
import { SetupGuard } from './components/SetupGuard';
import { useSetupStatus } from './components/setup-status-hook';
import { SetupPage } from './pages/Setup';
import { MaintenanceScreen } from './components/MaintenanceScreen';

const AdminPage = lazy(() => import('./pages/Admin').then(m => ({ default: m.AdminPage })));
const AbsencesPage = lazy(() => import('./pages/Absences').then(m => ({ default: m.AbsencesPage })));
const ProfilePage = lazy(() => import('./pages/Profile').then(m => ({ default: m.ProfilePage })));
const PrivacyPage = lazy(() => import('./pages/Privacy').then(m => ({ default: m.PrivacyPage })));
const TermsPage = lazy(() => import('./pages/Terms').then(m => ({ default: m.TermsPage })));
const LegalPage = lazy(() => import('./pages/Legal').then(m => ({ default: m.LegalPage })));
const AboutPage = lazy(() => import('./pages/About').then(m => ({ default: m.AboutPage })));
const HelpPage = lazy(() => import('./pages/Help').then(m => ({ default: m.HelpPage })));
const TeamPage = lazy(() => import('./pages/Team').then(m => ({ default: m.TeamPage })));
const CalendarPage = lazy(() => import('./pages/Calendar').then(m => ({ default: m.CalendarPage })));
const OccupancyDisplayPage = lazy(() => import('./pages/OccupancyDisplay').then(m => ({ default: m.OccupancyDisplayPage })));
const ForgotPasswordPage = lazy(() => import("./pages/ForgotPassword").then(m => ({ default: m.ForgotPasswordPage })));

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60,
      retry: 1,
    },
  },
});

function LoadingScreen() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950">
      <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
    </div>
  );
}

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();
  const { setupComplete } = useSetupStatus();
  if (isLoading) return <LoadingScreen />;
  if (!isAuthenticated) {
    if (!setupComplete) {
      return <Navigate to="/welcome" replace />;
    }
    return <Navigate to="/login" replace />;
  }
  return <Layout>{children}</Layout>;
}

function AdminRoute({ children }: { children: React.ReactNode }) {
  const { user, isAuthenticated, isLoading } = useAuth();
  const { setupComplete } = useSetupStatus();
  if (isLoading) return <LoadingScreen />;
  if (!isAuthenticated) {
    if (!setupComplete) {
      return <Navigate to="/welcome" replace />;
    }
    return <Navigate to="/login" replace />;
  }
  if (user?.role !== 'admin' && user?.role !== 'superadmin') return <Navigate to="/" replace />;
  return <Layout>{children}</Layout>;
}

function PublicPageWithLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}

function ThemeInitializer({ children }: { children: React.ReactNode }) {
  const theme = useTheme();
  const palette = usePalette();
  const accessibility = useAccessibility();
  const { i18n } = useTranslation();

  useEffect(() => { applyTheme(theme.isDark); }, [theme.isDark]);
  useEffect(() => { applyPalette(palette.paletteId, theme.isDark); }, [palette.paletteId, theme.isDark]);
  useEffect(() => { applyAccessibility(accessibility); }, [accessibility]);
  useEffect(() => {
    const normalized = i18n.resolvedLanguage ?? i18n.language ?? 'en';
    const active = supportedLanguages.find(({ code }) => normalized.startsWith(code));
    const lang = active?.code ?? 'en';
    document.documentElement.lang = lang;
    const dir = active && 'dir' in active ? active.dir : undefined;
    document.documentElement.dir = dir === 'rtl' ? 'rtl' : 'ltr';
  }, [i18n.language, i18n.resolvedLanguage]);

  return <>{children}</>;
}

function LoginRedirectGuard() {
  const { setupComplete } = useSetupStatus();
  // If setup not complete, redirect to welcome/onboarding instead of showing login
  if (setupComplete === false) {
    return <Navigate to="/welcome" replace />;
  }
  return <LoginPage />;
}

function AppRoutes() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/display" element={<Suspense fallback={<LoadingScreen />}><OccupancyDisplayPage /></Suspense>} />
      <Route path="/maintenance" element={<MaintenanceScreen />} />
            <Route path="/welcome" element={<WelcomePage />} />
      <Route path="/login" element={<LoginRedirectGuard />} />
      <Route path="/setup" element={<SetupPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/forgot-password" element={<Suspense fallback={<LoadingScreen />}><ForgotPasswordPage /></Suspense>} />
      <Route path="/privacy" element={<PublicPageWithLayout><Suspense fallback={<LoadingScreen />}><PrivacyPage /></Suspense></PublicPageWithLayout>} />
      <Route path="/terms" element={<PublicPageWithLayout><Suspense fallback={<LoadingScreen />}><TermsPage /></Suspense></PublicPageWithLayout>} />
      <Route path="/legal" element={<PublicPageWithLayout><Suspense fallback={<LoadingScreen />}><LegalPage /></Suspense></PublicPageWithLayout>} />
      <Route path="/about" element={<PublicPageWithLayout><Suspense fallback={<LoadingScreen />}><AboutPage /></Suspense></PublicPageWithLayout>} />
      <Route path="/help" element={<ProtectedRoute><Suspense fallback={<LoadingScreen />}><HelpPage /></Suspense></ProtectedRoute>} />

      {/* Protected */}
      <Route path="/" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
      <Route path="/book" element={<ProtectedRoute><BookPage /></ProtectedRoute>} />
      <Route path="/bookings" element={<ProtectedRoute><BookingsPage /></ProtectedRoute>} />
      <Route path="/vehicles" element={<ProtectedRoute><VehiclesPage /></ProtectedRoute>} />
      <Route path="/absences" element={<ProtectedRoute><Suspense fallback={<LoadingScreen />}><AbsencesPage /></Suspense></ProtectedRoute>} />
      <Route path="/homeoffice" element={<Navigate to="/absences" replace />} />
      <Route path="/vacation" element={<Navigate to="/absences" replace />} />
      <Route path="/profile" element={<ProtectedRoute><Suspense fallback={<LoadingScreen />}><ProfilePage /></Suspense></ProtectedRoute>} />

      <Route path="/team" element={<ProtectedRoute><Suspense fallback={<LoadingScreen />}><TeamPage /></Suspense></ProtectedRoute>} />
      <Route path="/calendar" element={<ProtectedRoute><Suspense fallback={<LoadingScreen />}><CalendarPage /></Suspense></ProtectedRoute>} />
      {/* Admin */}
      <Route path="/admin/*" element={<AdminRoute><Suspense fallback={<LoadingScreen />}><AdminPage /></Suspense></AdminRoute>} />

      {/* Catch all */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={import.meta.env.VITE_API_URL || '/'}>
        <ThemeInitializer>
          <BrandingProvider>
            <SetupGuard>
            <AuthProvider>
            <AppRoutes />
            <ConsentBanner />
            <Toaster
              position="top-right"
              toastOptions={{
                duration: 4000,
                style: {
                  background: 'var(--toast-bg, #fff)',
                  color: 'var(--toast-color, #1f2937)',
                  borderRadius: '12px',
                  boxShadow: '0 10px 40px -10px rgba(0, 0, 0, 0.2)',
                },
                success: { iconTheme: { primary: '#22c55e', secondary: '#fff' } },
                error: { iconTheme: { primary: '#ef4444', secondary: '#fff' } },
              }}
            />
          </AuthProvider>
            </SetupGuard>
          </BrandingProvider>
        </ThemeInitializer>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
