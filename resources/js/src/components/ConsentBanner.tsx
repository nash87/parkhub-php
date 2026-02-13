import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Shield, X } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export function ConsentBanner() {
  const { t } = useTranslation();
  const [visible, setVisible] = useState(() => !localStorage.getItem('parkhub_consent'));

  function accept() {
    localStorage.setItem('parkhub_consent', 'accepted');
    setVisible(false);
  }
  function decline() {
    localStorage.setItem('parkhub_consent', 'declined');
    setVisible(false);
  }

  return (
    <AnimatePresence>
      {visible && (
        <motion.div
          initial={{ y: 100, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: 100, opacity: 0 }}
          className="fixed bottom-0 left-0 right-0 z-[60] p-4 md:p-6"
        >
          <div className="max-w-3xl mx-auto card p-6 shadow-2xl border-primary-200 dark:border-primary-800">
            <div className="flex items-start gap-4">
              <Shield weight="fill" className="w-8 h-8 text-primary-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <h3 className="font-semibold text-gray-900 dark:text-white">{t('consent.title')}</h3>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{t('consent.message')}</p>
                <div className="flex flex-wrap gap-3 mt-4">
                  <button onClick={accept} className="btn btn-primary btn-sm">{t('consent.accept')}</button>
                  <button onClick={decline} className="btn btn-secondary btn-sm">{t('consent.decline')}</button>
                </div>
              </div>
              <button onClick={decline} className="btn btn-ghost btn-icon p-1" aria-label={t('common.close')}>
                <X weight="bold" className="w-4 h-4" />
              </button>
            </div>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
