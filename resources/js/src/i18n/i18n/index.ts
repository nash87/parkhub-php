import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { de } from './de';
import { en } from './en';
import { es } from './es';
import { fr } from './fr';
import { zh } from './zh';
import { pt } from './pt';
import { ar } from './ar';
import { hi } from './hi';
import { ja } from './ja';
import { tr } from './tr';

export const supportedLanguages = [
  { code: 'de', name: 'Deutsch', native: 'Deutsch' },
  { code: 'en', name: 'English', native: 'English' },
  { code: 'es', name: 'Spanish', native: 'Español' },
  { code: 'fr', name: 'French', native: 'Français' },
  { code: 'zh', name: 'Chinese', native: '中文' },
  { code: 'pt', name: 'Portuguese', native: 'Português' },
  { code: 'ar', name: 'Arabic', native: 'العربية', dir: 'rtl' as const },
  { code: 'hi', name: 'Hindi', native: 'हिन्दी' },
  { code: 'ja', name: 'Japanese', native: '日本語' },
  { code: 'tr', name: 'Turkish', native: 'Türkçe' },
] as const;

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      de: { translation: de },
      en: { translation: en },
      es: { translation: es },
      fr: { translation: fr },
      zh: { translation: zh },
      pt: { translation: pt },
      ar: { translation: ar },
      hi: { translation: hi },
      ja: { translation: ja },
      tr: { translation: tr },
    },
    fallbackLng: 'en',
    detection: {
      order: ['localStorage'],
      caches: ['localStorage'],
      lookupLocalStorage: 'parkhub-lang',
    },
    interpolation: {
      escapeValue: false,
    },
  });

export default i18n;
