import React, { useState } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { t } from '../i18n';
import { useTheme } from '../stores/theme';
import {
  House, CalendarBlank, Car, Users, Gear, SignOut, List, X,
  CalendarCheck, MapPin, CarSimple, UserCircle, ShieldCheck, Sun, Moon,
  ClockCounterClockwise, Info
} from '@phosphor-icons/react';

export default function Layout() {
  const { user, logout, isAdmin } = useAuth();
  const { isDark, setTheme, theme } = useTheme();
  const location = useLocation();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);

  const navItems = [
    { path: '/', label: t('nav.dashboard'), icon: House },
    { path: '/book', label: t('nav.book'), icon: MapPin },
    { path: '/bookings', label: t('nav.bookings'), icon: CalendarCheck },
    { path: '/calendar', label: t('nav.calendar'), icon: CalendarBlank },
    { path: '/absences', label: t('nav.absences'), icon: ClockCounterClockwise },
    { path: '/team', label: t('nav.team'), icon: Users },
    { path: '/vehicles', label: t('nav.vehicles'), icon: CarSimple },
  ];

  const handleLogout = () => { logout(); navigate('/login'); };
  const toggleTheme = () => setTheme(isDark ? 'light' : 'dark');

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900">
      {/* Mobile header */}
      <header className="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-4 py-3 flex items-center justify-between">
        <button onClick={() => setMenuOpen(!menuOpen)} className="p-1"><List size={24} /></button>
        <span className="font-bold text-amber-600">ParkHub</span>
        <button onClick={toggleTheme} className="p-1">{isDark ? <Sun size={20} /> : <Moon size={20} />}</button>
      </header>

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 transform transition-transform lg:translate-x-0 ${menuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="p-6">
          <h1 className="text-xl font-bold text-amber-600">🅿️ ParkHub</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">PHP Edition</p>
        </div>
        <nav className="px-3 space-y-1">
          {navItems.map(item => {
            const active = location.pathname === item.path;
            return (
              <Link key={item.path} to={item.path} onClick={() => setMenuOpen(false)}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${active ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
                <item.icon size={20} weight={active ? 'fill' : 'regular'} />
                {item.label}
              </Link>
            );
          })}
          {isAdmin && (
            <Link to="/admin" onClick={() => setMenuOpen(false)}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${location.pathname.startsWith('/admin') ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
              <ShieldCheck size={20} /> {t('nav.admin')}
            </Link>
          )}
        </nav>
        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-200 dark:border-slate-700">
          <Link to="/profile" className="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <UserCircle size={24} className="text-slate-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate">{user?.name}</p>
              <p className="text-xs text-slate-500 truncate">{user?.email}</p>
            </div>
          </Link>
          <div className="flex items-center gap-2 mt-2">
            <button onClick={toggleTheme} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
              {isDark ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <button onClick={handleLogout} className="flex-1 flex items-center justify-center gap-2 p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm">
              <SignOut size={18} /> {t('nav.logout')}
            </button>
          </div>
        </div>
      </aside>

      {/* Overlay */}
      {menuOpen && <div className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={() => setMenuOpen(false)} />}

      {/* Main content */}
      <main className="lg:ml-64 pt-14 lg:pt-0 min-h-screen">
        <div className="max-w-7xl mx-auto p-4 lg:p-8">
          <Outlet />
        </div>
      </main>

      {/* Footer */}
      <footer className="lg:ml-64 border-t border-slate-200 dark:border-slate-700 py-6 px-4 text-center text-sm text-slate-500">
        <p>ParkHub — Open Source Parking Management</p>
        <div className="flex justify-center gap-4 mt-2">
          <Link to="/about" className="hover:text-amber-600">About</Link>
          <Link to="/help" className="hover:text-amber-600">Help</Link>
          <Link to="/privacy" className="hover:text-amber-600">Privacy</Link>
          <Link to="/terms" className="hover:text-amber-600">Terms</Link>
          <Link to="/legal" className="hover:text-amber-600">Legal</Link>
        </div>
      </footer>
    </div>
  );
}
