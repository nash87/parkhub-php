import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import {
  Car, House, Airplane, Thermometer, MapPin, MagnifyingGlass, Users, ArrowClockwise
} from '@phosphor-icons/react';
import { api, TeamMember } from '../api/client';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { de, enUS } from 'date-fns/locale';

const statusConfig: Record<string, { icon: typeof Car; color: string; bgColor: string }> = {
  parked: { icon: Car, color: 'text-emerald-600 dark:text-emerald-400', bgColor: 'bg-emerald-50 dark:bg-emerald-900/30' },
  homeoffice: { icon: House, color: 'text-primary-600 dark:text-primary-400', bgColor: 'bg-primary-50 dark:bg-primary-900/30' },
  vacation: { icon: Airplane, color: 'text-amber-600 dark:text-amber-400', bgColor: 'bg-amber-50 dark:bg-amber-900/30' },
  sick: { icon: Thermometer, color: 'text-red-600 dark:text-red-400', bgColor: 'bg-red-50 dark:bg-red-900/30' },
  business_trip: { icon: MapPin, color: 'text-purple-600 dark:text-purple-400', bgColor: 'bg-purple-50 dark:bg-purple-900/30' },
  not_scheduled: { icon: Users, color: 'text-gray-400 dark:text-gray-500', bgColor: 'bg-gray-50 dark:bg-gray-800/50' },
};

export function TeamPage() {
  const { t, i18n } = useTranslation();
  const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : de;
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  function loadData() {
    setLoading(true);
    api.getTeamToday().then(res => {
      if (res.success && res.data) setMembers(res.data);
      setLoading(false);
    }).catch(() => setLoading(false));
  }

  useEffect(() => { loadData(); }, []);

  const filtered = members.filter(m =>
    m.name.toLowerCase().includes(search.toLowerCase())
  );

  const grouped = {
    parked: filtered.filter(m => m.status === 'parked'),
    homeoffice: filtered.filter(m => m.status === 'homeoffice'),
    vacation: filtered.filter(m => m.status === 'vacation'),
    sick: filtered.filter(m => m.status === 'sick'),
    not_scheduled: filtered.filter(m => m.status === 'not_scheduled'),
  };

  const totalVisible = Object.values(grouped).reduce((sum, arr) => sum + arr.length, 0);
  const todayLabel = format(new Date(), 'EEEE, d. MMMM', { locale: dateFnsLocale });

  if (loading) {
    return (
      <div className="space-y-4" role="status" aria-busy="true">
        <div className="h-8 w-48 skeleton" aria-hidden="true" />
        <div className="h-5 w-32 skeleton" aria-hidden="true" />
        {[1,2,3].map(i => <div key={i} className="h-20 skeleton rounded-2xl" aria-hidden="true" />)}
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6 max-w-full">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
            {t('team.title')}
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{todayLabel}</p>
        </div>
        <div className="flex items-center gap-2">
          <div className="relative">
            <MagnifyingGlass className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder={t('team.search')}
              className="pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-full sm:w-64 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            />
          </div>
          <button onClick={loadData} className="btn btn-secondary btn-icon" aria-label={t('common.refresh')}>
            <ArrowClockwise weight="bold" className="w-4 h-4" />
          </button>
        </div>
      </div>

      {totalVisible === 0 ? (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="card p-12 text-center">
          <Users weight="light" className="w-16 h-16 text-gray-200 dark:text-gray-700 mx-auto mb-4" />
          <p className="text-gray-500 dark:text-gray-400">
            {search ? t('team.noResults', 'Keine Personen gefunden.') : t('team.noMembers', 'Noch keine Teammitglieder für heute eingetragen.')}
          </p>
        </motion.div>
      ) : (
        Object.entries(grouped).map(([status, people]) => {
          if (people.length === 0) return null;
          const config = statusConfig[status] || statusConfig.not_scheduled;
          const Icon = config.icon;
          return (
            <div key={status}>
              <h2 className={`text-sm font-semibold uppercase tracking-wider mb-3 flex items-center gap-2 ${config.color}`}>
                <Icon weight="fill" className="w-4 h-4" aria-hidden="true" />
                {t(`team.status.${status}`)}
                <span className="text-xs font-normal opacity-70">({people.length})</span>
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {people.map(person => (
                  <motion.div
                    key={person.user_id}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className={`flex items-center gap-3 p-4 rounded-2xl ${config.bgColor} transition-all`}
                  >
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center ${config.color} bg-white/60 dark:bg-gray-900/40`}>
                      <Icon weight="fill" className="w-5 h-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="font-medium text-gray-900 dark:text-white truncate">{person.name}</p>
                      <p className="text-xs text-gray-500 dark:text-gray-400">
                        {person.slot_number ? `${t('team.slot')} ${person.slot_number}` : t(`team.status.${status}`)}
                        {person.department && ` · ${person.department}`}
                      </p>
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>
          );
        })
      )}
    </motion.div>
  );
}
