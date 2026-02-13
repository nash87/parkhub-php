import { motion } from 'framer-motion';
import { FileText } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export function TermsPage() {
  const { t } = useTranslation();
  const sections = ['usage', 'accounts', 'liability', 'changes'];

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="max-w-3xl mx-auto space-y-8 py-8 px-4">
      <div className="flex items-center gap-3">
        <FileText weight="fill" className="w-8 h-8 text-primary-600" />
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('terms.title')}</h1>
      </div>
      {sections.map(s => (
        <div key={s} className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">{t(`terms.${s}.title`)}</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">{t(`terms.${s}.content`)}</p>
        </div>
      ))}
    </motion.div>
  );
}
