import { create } from 'zustand';

interface ThemeState {
  theme: 'light' | 'dark' | 'system';
  setTheme: (t: 'light' | 'dark' | 'system') => void;
  isDark: boolean;
}

function getEffectiveDark(theme: string): boolean {
  if (theme === 'dark') return true;
  if (theme === 'light') return false;
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

export const useTheme = create<ThemeState>((set) => {
  const stored = localStorage.getItem('theme') || 'system';
  const isDark = getEffectiveDark(stored);

  if (isDark) document.documentElement.classList.add('dark');
  else document.documentElement.classList.remove('dark');

  return {
    theme: stored as any,
    isDark,
    setTheme: (t) => {
      localStorage.setItem('theme', t);
      const dark = getEffectiveDark(t);
      if (dark) document.documentElement.classList.add('dark');
      else document.documentElement.classList.remove('dark');
      set({ theme: t, isDark: dark });
    },
  };
});
