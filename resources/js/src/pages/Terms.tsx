import React from 'react';
import { Link } from 'react-router-dom';
import { t } from '../i18n';
import { ArrowLeft } from '@phosphor-icons/react';

export default function Terms() {
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 p-8">
      <div className="max-w-3xl mx-auto">
        <Link to="/" className="text-amber-600 text-sm flex items-center gap-1 mb-6"><ArrowLeft size={16} /> Back</Link>
        <h1 className="text-3xl font-bold mb-6">{t('terms.title')}</h1>
        <div className="card prose dark:prose-invert max-w-none">
          <p>This is the Terms page for ParkHub PHP Edition.</p>
          <p>ParkHub is open source parking management software licensed under the MIT License.</p>
          <p>Source code: <a href="https://github.com/frostplexx/parkhub" className="text-amber-600">github.com/frostplexx/parkhub</a></p>
        </div>
      </div>
    </div>
  );
}
