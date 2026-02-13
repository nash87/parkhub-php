import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Scales, Buildings, Code, Warning, ShieldCheck, GitBranch } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export function LegalPage() {
  const { t } = useTranslation();
  const [companyName, setCompanyName] = useState('');
  const [contactEmail, setContactEmail] = useState('');

  useEffect(() => {
    fetch('/api/v1/branding').then(r => r.json()).then(d => {
      const data = d.data || d;
      if (data.company_name || data.instance_name) setCompanyName(data.company_name || data.instance_name);
      if (data.contact_email || data.admin_email) setContactEmail(data.contact_email || data.admin_email);
    }).catch(() => {});
  }, []);

  const sections = [
    { icon: Buildings, key: 'operator' },
    { icon: Code, key: 'software' },
    { icon: Scales, key: 'license' },
    { icon: Warning, key: 'disclaimer' },
    { icon: ShieldCheck, key: 'selfHosted' },
  ];

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="max-w-3xl mx-auto space-y-8 py-8 px-4">
      <div className="flex items-center gap-3">
        <Scales weight="fill" className="w-8 h-8 text-primary-600" />
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('legal.title')}</h1>
      </div>

      {sections.map(({ icon: Icon, key }) => (
        <div key={key} className="card p-6">
          <div className="flex items-center gap-3 mb-3">
            <Icon weight="fill" className="w-5 h-5 text-primary-600" />
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('legal.' + key + '.title')}</h2>
          </div>
          <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">
            {t('legal.' + key + '.content', {
              companyName: companyName || t('privacy.defaultCompany'),
              contactEmail: contactEmail || t('privacy.defaultEmail'),
            })}
          </p>
        </div>
      ))}

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-3">
          <GitBranch weight="fill" className="w-5 h-5 text-primary-600" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('about.version.title')}</h2>
        </div>
        <div className="text-sm text-gray-600 dark:text-gray-400 space-y-1">
          <p>{t('about.version.current')}: <span className="font-mono font-medium text-gray-900 dark:text-white">v{__APP_VERSION__}</span></p>
          <p>{t('about.version.license')}: <span className="font-medium text-gray-900 dark:text-white">MIT</span></p>
          <a href="https://github.com/frostplexx/parkhub" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-primary-600 hover:underline mt-2">
            GitHub Repository
          </a>
        </div>
      </div>
    </motion.div>
  );
}
