import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Shield, Database, Lock, Eye, HardDrives, User, Scales, Clock, EnvelopeSimple, Code } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export function PrivacyPage() {
  const { t } = useTranslation();
  const [companyName, setCompanyName] = useState('');
  const [contactEmail, setContactEmail] = useState('');

  useEffect(() => {
    fetch(`(import.meta.env.VITE_API_URL || "")/api/v1/branding`).then(r => r.json()).then(d => {
      const data = d.data || d;
      if (data.company_name || data.instance_name) setCompanyName(data.company_name || data.instance_name);
      if (data.contact_email || data.admin_email) setContactEmail(data.contact_email || data.admin_email);
    }).catch(() => {});
  }, []);

  const sections = [
    { icon: User, key: 'controller' },
    { icon: Database, key: 'dataCollected' },
    { icon: Eye, key: 'purpose' },
    { icon: HardDrives, key: 'storage' },
    { icon: Code, key: 'technical' },
    { icon: Lock, key: 'security' },
    { icon: Clock, key: 'retention' },
    { icon: EnvelopeSimple, key: 'contact' },
  ];

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="max-w-3xl mx-auto space-y-8 py-8 px-4">
      <div>
        <div className="flex items-center gap-3 mb-2">
          <Shield weight="fill" className="w-8 h-8 text-primary-600" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('privacy.title')}</h1>
        </div>
        <p className="text-gray-500 dark:text-gray-400">{t('privacy.subtitle')}</p>
      </div>

      {sections.map(({ icon: Icon, key }) => {
        const content = t('privacy.' + key + '.content', { 
          companyName: companyName || t('privacy.defaultCompany'),
          contactEmail: contactEmail || t('privacy.defaultEmail'),
        });
        return (
          <div key={key} className="card p-6">
            <div className="flex items-center gap-3 mb-3">
              <Icon weight="fill" className="w-5 h-5 text-primary-600" />
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('privacy.' + key + '.title')}</h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">{content}</p>
          </div>
        );
      })}

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-3">
          <Scales weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('privacy.rights.title')}</h2>
        </div>
        <ul className="space-y-3 text-sm text-gray-600 dark:text-gray-400">
          {['access', 'rectification', 'erasure', 'portability', 'restriction', 'objection'].map(r => (
            <li key={r} className="flex items-start gap-2">
              <span className="text-primary-600 mt-0.5 font-bold">•</span>
              <span>{t('privacy.rights.' + r)}</span>
            </li>
          ))}
        </ul>
      </div>

      <div className="card p-4 bg-gray-50 dark:bg-gray-800/50">
        <p className="text-xs text-gray-500 dark:text-gray-400 text-center">
          {t('privacy.lastUpdated')}
        </p>
      </div>
    </motion.div>
  );
}
