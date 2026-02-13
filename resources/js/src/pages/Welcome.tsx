import { useState, useEffect, useCallback } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { supportedLanguages } from '../i18n/index';
import { useAccessibility, applyAccessibility } from '../stores/accessibility';
import { useSetupStatus } from '../components/setup-status-hook';
import {
  TextAa,
  SunDim,
  Eye,
  GlobeSimple,
  ArrowRight,
  GithubLogo,
} from '@phosphor-icons/react';

const welcomeWords = [
  { code: 'de', text: 'Willkommen' },
  { code: 'en', text: 'Welcome' },
  { code: 'es', text: 'Bienvenido' },
  { code: 'fr', text: 'Bienvenue' },
  { code: 'zh', text: '欢迎' },
  { code: 'pt', text: 'Bem-vindo' },
  { code: 'ar', text: 'أهلاً وسهلاً' },
  { code: 'hi', text: 'स्वागत है' },
  { code: 'ja', text: 'ようこそ' },
  { code: 'tr', text: 'Hoş Geldiniz' },
];

const fontScaleOptions = [
  { value: 'small', label: 'A', size: 'text-xs' },
  { value: 'normal', label: 'A', size: 'text-base' },
  { value: 'large', label: 'A', size: 'text-lg' },
  { value: 'xlarge', label: 'A', size: 'text-xl' },
] as const;

export function WelcomePage() {
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const accessibility = useAccessibility();
  const [currentWordIndex, setCurrentWordIndex] = useState(0);
  const [fade, setFade] = useState(true);
  const [showAccessibility, setShowAccessibility] = useState(false);

  const { setupComplete } = useSetupStatus();

  // Cycle welcome words
  useEffect(() => {
    const interval = setInterval(() => {
      setFade(false);
      setTimeout(() => {
        setCurrentWordIndex((prev) => (prev + 1) % welcomeWords.length);
        setFade(true);
      }, 400);
    }, 2500);
    return () => clearInterval(interval);
  }, []);

  // Clear stale state from previous installs on fresh setup
  useEffect(() => {
    if (setupComplete === false) {
      localStorage.removeItem("parkhub-lang-chosen");
      localStorage.removeItem("parkhub_token");
      localStorage.removeItem("parkhub-palette");
      localStorage.removeItem("parkhub-usecase");
      localStorage.removeItem("parkhub-favorite-slots");
    }
  }, [setupComplete]);

  const selectLanguage = useCallback(
    (code: string) => {
      i18n.changeLanguage(code);
      localStorage.setItem('parkhub-lang', code);
      localStorage.setItem('parkhub-lang-chosen', 'true');
      // Set RTL for Arabic
      const lang = supportedLanguages.find((l) => l.code === code);
      if (lang && 'dir' in lang && lang.dir === 'rtl') {
        document.documentElement.dir = 'rtl';
      } else {
        document.documentElement.dir = 'ltr';
      }
      document.documentElement.lang = code;
      // Navigate to setup if first visit, otherwise login
      if (!setupComplete) {
        navigate('/setup', { replace: true });
      } else {
        navigate('/login', { replace: true });
      }
    },
    [i18n, navigate, setupComplete]
  );

  // If setup is already complete, redirect to login/dashboard
  if (setupComplete) {
    return <Navigate to="/" replace />;
  }

  const setFontScale = (scale: 'small' | 'normal' | 'large' | 'xlarge') => {
    accessibility.setFontScale(scale);
    applyAccessibility({ ...accessibility, fontScale: scale });
  };

  const toggleHighContrast = () => {
    const next = !accessibility.highContrast;
    accessibility.setHighContrast(next);
    applyAccessibility({ ...accessibility, highContrast: next });
  };

  const toggleReducedMotion = () => {
    const next = !accessibility.reducedMotion;
    accessibility.setReducedMotion(next);
    applyAccessibility({ ...accessibility, reducedMotion: next });
  };

  const cycleColorMode = () => {
    const modes = ['normal', 'protanopia', 'deuteranopia', 'tritanopia'] as const;
    const idx = modes.indexOf(accessibility.colorMode as typeof modes[number]);
    const next = modes[(idx + 1) % modes.length];
    accessibility.setColorMode(next);
    applyAccessibility({ ...accessibility, colorMode: next });
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-50 via-white to-primary-100 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 flex flex-col items-center justify-center p-4 sm:p-8">
      {/* Logo */}
      <div className="mb-4">
        <img src="/icon.svg" alt="ParkHub" className="w-20 h-20 mx-auto rounded-2xl" />
      </div>

      {/* Cycling Welcome Text */}
      <div className="mb-10 h-20 flex items-center justify-center">
        <h1
          className={`text-5xl sm:text-6xl font-bold text-primary-600 dark:text-primary-400 transition-opacity duration-400 ${
            fade ? 'opacity-100' : 'opacity-0'
          }`}
          style={{ transitionDuration: '400ms' }}
        >
          {welcomeWords[currentWordIndex].text}
        </h1>
      </div>

      <p className="text-gray-500 dark:text-gray-400 mb-8 text-center text-lg">
        <GlobeSimple weight="bold" className="inline w-5 h-5 mr-1 -mt-0.5" />
        Select your language / Sprache wählen
      </p>

      {/* Language Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 max-w-3xl w-full mb-10">
        {supportedLanguages.map((lang) => (
          <button
            key={lang.code}
            onClick={() => selectLanguage(lang.code)}
            className="group flex flex-col items-center gap-1 p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-primary-400 dark:hover:border-primary-500 hover:shadow-lg hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
            dir={'dir' in lang && lang.dir === 'rtl' ? 'rtl' : 'ltr'}
          >
            <span className="text-lg font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
              {lang.native}
            </span>
            <span className="text-xs text-gray-400 dark:text-gray-500">
              {lang.name}
            </span>
            <ArrowRight
              weight="bold"
              className="w-4 h-4 text-gray-300 dark:text-gray-600 group-hover:text-primary-500 transition-colors mt-1"
            />
          </button>
        ))}
      </div>

      {/* Accessibility Controls */}
      <div className="w-full max-w-3xl">
        <button
          onClick={() => setShowAccessibility(!showAccessibility)}
          className="flex items-center gap-2 mx-auto text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors mb-3"
        >
          <Eye weight="bold" className="w-4 h-4" />
          Accessibility / Barrierefreiheit
        </button>

        {showAccessibility && (
          <div className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-center justify-center gap-4">
            {/* Font Size */}
            <div className="flex items-center gap-1">
              <TextAa weight="bold" className="w-4 h-4 text-gray-400 mr-1" />
              {fontScaleOptions.map((opt) => (
                <button
                  key={opt.value}
                  onClick={() => setFontScale(opt.value)}
                  className={`px-2 py-1 rounded-lg ${opt.size} font-bold transition-colors ${
                    accessibility.fontScale === opt.value
                      ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400'
                      : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>

            {/* High Contrast */}
            <button
              onClick={toggleHighContrast}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                accessibility.highContrast
                  ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400'
                  : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'
              }`}
            >
              <SunDim weight="bold" className="w-4 h-4" />
              High Contrast
            </button>

            {/* Reduced Motion */}
            <button
              onClick={toggleReducedMotion}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                accessibility.reducedMotion
                  ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400'
                  : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'
              }`}
            >
              Reduced Motion
            </button>

            {/* Colorblind Mode */}
            <button
              onClick={cycleColorMode}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                accessibility.colorMode !== 'normal'
                  ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400'
                  : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'
              }`}
            >
              <Eye weight="bold" className="w-4 h-4" />
              {accessibility.colorMode === 'normal' ? 'Colorblind' : accessibility.colorMode}
            </button>
          </div>
        )}
      </div>

      {/* Footer */}
      <p className="mt-10 text-xs text-gray-300 dark:text-gray-600 flex items-center gap-2 justify-center">
        {t('system.openSource', 'Open Source')} · {t('system.license', 'MIT License')} ·{' '}
        <a href="https://github.com/nash87/parkhub" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 hover:text-gray-500 dark:hover:text-gray-400 transition-colors">
          <GithubLogo weight="bold" className="w-3.5 h-3.5" /> GitHub
        </a>
      </p>
    </div>
  );
}
