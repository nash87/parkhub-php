import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export type ColorMode = 'normal' | 'protanopia' | 'deuteranopia' | 'tritanopia';
export type FontScale = 'small' | 'normal' | 'large' | 'xlarge';

interface AccessibilityStore {
  colorMode: ColorMode;
  fontScale: FontScale;
  reducedMotion: boolean;
  highContrast: boolean;
  setColorMode: (mode: ColorMode) => void;
  setFontScale: (scale: FontScale) => void;
  setReducedMotion: (v: boolean) => void;
  setHighContrast: (v: boolean) => void;
}

export const useAccessibility = create<AccessibilityStore>()(
  persist(
    (set) => ({
      colorMode: 'normal',
      fontScale: 'normal',
      reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      highContrast: false,
      setColorMode: (colorMode) => set({ colorMode }),
      setFontScale: (fontScale) => set({ fontScale }),
      setReducedMotion: (reducedMotion) => set({ reducedMotion }),
      setHighContrast: (highContrast) => set({ highContrast }),
    }),
    { name: 'parkhub-accessibility' }
  )
);

const fontSizeMap: Record<FontScale, string> = {
  small: '14px',
  normal: '16px',
  large: '18px',
  xlarge: '20px',
};

export function applyAccessibility(store: AccessibilityStore) {
  const root = document.documentElement;
  root.style.fontSize = fontSizeMap[store.fontScale];
  root.dataset.colorMode = store.colorMode;
  root.classList.toggle('high-contrast', store.highContrast);
  root.classList.toggle('reduce-motion', store.reducedMotion);
}
