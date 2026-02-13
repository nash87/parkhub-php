import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Question, CaretDown, CaretUp, MagnifyingGlass } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

interface FaqItem { key: string; admin?: boolean; }

const faqItems: FaqItem[] = [
  { key: 'bookSpot' },
  { key: 'homeOffice' },
  { key: 'vehicles' },
  { key: 'recurring' },
  { key: 'waitlist' },
  { key: 'checkin' },
  { key: 'configureLots', admin: true },
  { key: 'manageUsers', admin: true },
];

export function HelpPage() {
  const { t } = useTranslation();
  const [openItem, setOpenItem] = useState<string | null>(null);
  const [search, setSearch] = useState('');

  const filtered = faqItems.filter(item => {
    if (!search) return true;
    const q = search.toLowerCase();
    const question = t(`help.faq.${item.key}.q`).toLowerCase();
    const answer = t(`help.faq.${item.key}.a`).toLowerCase();
    return question.includes(q) || answer.includes(q);
  });

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="max-w-3xl mx-auto space-y-8 py-8 px-4">
      <div>
        <div className="flex items-center gap-3 mb-2">
          <Question weight="fill" className="w-8 h-8 text-primary-600" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('help.title')}</h1>
        </div>
        <p className="text-gray-500 dark:text-gray-400">{t('help.subtitle')}</p>
      </div>

      {/* Search */}
      <div className="relative">
        <MagnifyingGlass weight="bold" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder={t('help.searchPlaceholder')}
          className="input pl-10"
          aria-label={t('help.searchPlaceholder')}
        />
      </div>

      {/* User FAQ */}
      <div className="space-y-2">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('help.userFaq')}</h2>
        {filtered.filter(i => !i.admin).map(item => (
          <FaqAccordion key={item.key} itemKey={item.key} isOpen={openItem === item.key} onToggle={() => setOpenItem(openItem === item.key ? null : item.key)} />
        ))}
      </div>

      {/* Admin FAQ */}
      {filtered.some(i => i.admin) && (
        <div className="space-y-2">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('help.adminFaq')}</h2>
          {filtered.filter(i => i.admin).map(item => (
            <FaqAccordion key={item.key} itemKey={item.key} isOpen={openItem === item.key} onToggle={() => setOpenItem(openItem === item.key ? null : item.key)} />
          ))}
        </div>
      )}
    </motion.div>
  );
}

function FaqAccordion({ itemKey, isOpen, onToggle }: { itemKey: string; isOpen: boolean; onToggle: () => void }) {
  const { t } = useTranslation();
  return (
    <div className="card overflow-hidden">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
        aria-expanded={isOpen}
      >
        <span className="font-medium text-gray-900 dark:text-white text-sm">{t(`help.faq.${itemKey}.q`)}</span>
        {isOpen ? <CaretUp weight="bold" className="w-4 h-4 text-gray-400 flex-shrink-0" /> : <CaretDown weight="bold" className="w-4 h-4 text-gray-400 flex-shrink-0" />}
      </button>
      <AnimatePresence>
        {isOpen && (
          <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
            <p className="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{t(`help.faq.${itemKey}.a`)}</p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
