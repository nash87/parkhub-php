import { motion } from 'framer-motion';
import { Info, Code, Database, GitBranch, Lock, Globe, GithubLogo, Scales } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export function AboutPage() {
  const { t } = useTranslation();

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="max-w-3xl mx-auto space-y-8 py-8 px-4">
      <div>
        <div className="flex items-center gap-3 mb-2">
          <Info weight="fill" className="w-8 h-8 text-primary-600" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('about.title')}</h1>
        </div>
        <p className="text-gray-500 dark:text-gray-400">{t('about.subtitle')}</p>
      </div>

      {/* Tech Stack */}
      <div className="card p-6">
        <div className="flex items-center gap-2 mb-4">
          <Code weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('about.techStack.title')}</h2>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-2">{t('about.techStack.frontend')}</h3>
            <ul className="space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <li>• React 19 + TypeScript</li>
              <li>• TailwindCSS</li>
              <li>• Vite + PWA</li>
              <li>• Framer Motion</li>
            </ul>
          </div>
          <div>
            <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-2">{t('about.techStack.backend')}</h3>
            <ul className="space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <li>• Rust + Axum</li>
              <li>• redb (embedded DB)</li>
              <li>• JWT Authentication</li>
              <li>• REST API</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Architecture */}
      <div className="card p-6">
        <div className="flex items-center gap-2 mb-4">
          <Database weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('about.architecture.title')}</h2>
        </div>
        <div className="flex items-center justify-center py-6 gap-2 text-sm text-gray-600 dark:text-gray-400 flex-wrap">
          <div className="px-4 py-3 rounded-xl bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium">
            <Globe weight="fill" className="w-5 h-5 mx-auto mb-1" />Browser
          </div>
          <span className="text-gray-400 text-lg">→</span>
          <div className="px-4 py-3 rounded-xl bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 font-medium">HTTPS</div>
          <span className="text-gray-400 text-lg">→</span>
          <div className="px-4 py-3 rounded-xl bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-medium">
            <Code weight="fill" className="w-5 h-5 mx-auto mb-1" />Axum Server
          </div>
          <span className="text-gray-400 text-lg">→</span>
          <div className="px-4 py-3 rounded-xl bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium">
            <Database weight="fill" className="w-5 h-5 mx-auto mb-1" />redb
          </div>
        </div>
      </div>

      {/* Version */}
      <div className="card p-6">
        <div className="flex items-center gap-2 mb-3">
          <GitBranch weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('about.version.title')}</h2>
        </div>
        <div className="text-sm text-gray-600 dark:text-gray-400 space-y-1">
          <p>{t('about.version.current')}: <span className="font-mono font-medium text-gray-900 dark:text-white">v{__APP_VERSION__}</span></p>
          <p>{t('about.version.license')}: <span className="font-medium text-gray-900 dark:text-white">MIT</span></p>
          <div className="flex items-center gap-3 mt-3 flex-wrap">
            <a href="https://github.com/nash87/parkhub" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 dark:bg-gray-700 text-white text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-600 transition-colors">
              <GithubLogo weight="fill" className="w-4 h-4" /> {t('system.viewOnGithub', 'View on GitHub')}
            </a>
            <span className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium">
              <Scales weight="fill" className="w-3.5 h-3.5" /> {t('system.license', 'MIT License')}
            </span>
          </div>
          <div className="flex gap-3 mt-2 text-sm">
            <a href="https://github.com/nash87/parkhub/issues" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">{t('system.reportIssue', 'Report issue')}</a>
          </div>
        </div>
      </div>

      {/* Data Transparency */}
      <div className="card p-6">
        <div className="flex items-center gap-2 mb-3">
          <Lock weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('about.data.title')}</h2>
        </div>
        <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">{t('about.data.content')}</p>
      </div>
    </motion.div>
  );
}
