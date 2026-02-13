import { en } from './en';
import { de } from './de';

const translations: Record<string, any> = { en, de, es: en, fr: en, pt: en, ar: en, hi: en, ja: en, zh: en, tr: en };

let currentLang = localStorage.getItem('language') || 'en';

export function setLanguage(lang: string) {
  currentLang = lang;
  localStorage.setItem('language', lang);
}

export function getLanguage(): string {
  return currentLang;
}

export function t(key: string, params?: Record<string, string>): string {
  const keys = key.split('.');
  let value: any = translations[currentLang] || translations['en'];
  for (const k of keys) {
    value = value?.[k];
    if (value === undefined) {
      // Fallback to English
      value = translations['en'];
      for (const k2 of keys) { value = value?.[k2]; if (value === undefined) return key; }
      break;
    }
  }
  if (typeof value !== 'string') return key;
  if (params) {
    return value.replace(/\{\{(\w+)\}\}/g, (_, k) => params[k] || '');
  }
  return value;
}

export const LANGUAGES = [
  { code: 'en', name: 'English' },
  { code: 'de', name: 'Deutsch' },
  { code: 'es', name: 'Español' },
  { code: 'fr', name: 'Français' },
  { code: 'pt', name: 'Português' },
  { code: 'ar', name: 'العربية' },
  { code: 'hi', name: 'हिन्दी' },
  { code: 'ja', name: '日本語' },
  { code: 'zh', name: '中文' },
  { code: 'tr', name: 'Türkçe' },
];
