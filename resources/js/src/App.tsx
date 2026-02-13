import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import Book from './pages/Book';
import Bookings from './pages/Bookings';
import Calendar from './pages/Calendar';
import Absences from './pages/Absences';
import Team from './pages/Team';
import Admin from './pages/Admin';
import Profile from './pages/Profile';
import Vehicles from './pages/Vehicles';
import Setup from './pages/Setup';
import About from './pages/About';
import Help from './pages/Help';
import Legal from './pages/Legal';
import Privacy from './pages/Privacy';
import Terms from './pages/Terms';
import OccupancyDisplay from './pages/OccupancyDisplay';
import AuditLog from './pages/AuditLog';
import Welcome from './pages/Welcome';
import { api } from './api/client';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

function AppRoutes() {
  const { user } = useAuth();
  const [setupDone, setSetupDone] = useState<boolean | null>(null);

  useEffect(() => {
    api.get<any>('/setup/status').then(res => setSetupDone(res.setup_completed)).catch(() => setSetupDone(true));
  }, []);

  if (setupDone === null) return <div className="flex items-center justify-center min-h-screen"><div className="animate-spin h-8 w-8 border-4 border-amber-600 border-t-transparent rounded-full" /></div>;

  if (!setupDone) return <Setup onComplete={() => setSetupDone(true)} />;

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" replace /> : <Login />} />
      <Route path="/register" element={user ? <Navigate to="/" replace /> : <Register />} />
      <Route path="/display" element={<OccupancyDisplay />} />
      <Route path="/about" element={<About />} />
      <Route path="/help" element={<Help />} />
      <Route path="/legal" element={<Legal />} />
      <Route path="/privacy" element={<Privacy />} />
      <Route path="/terms" element={<Terms />} />
      <Route element={<ProtectedRoute><Layout /></ProtectedRoute>}>
        <Route path="/" element={<Dashboard />} />
        <Route path="/book" element={<Book />} />
        <Route path="/bookings" element={<Bookings />} />
        <Route path="/calendar" element={<Calendar />} />
        <Route path="/absences" element={<Absences />} />
        <Route path="/team" element={<Team />} />
        <Route path="/vehicles" element={<Vehicles />} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/admin" element={<Admin />} />
        <Route path="/admin/audit-log" element={<AuditLog />} />
        <Route path="/welcome" element={<Welcome />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  );
}
