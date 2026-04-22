import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { Users } from '@phosphor-icons/react';
import { api, type TeamAbsenceEntry } from '../api/client';
import { useTranslation } from 'react-i18next';
import { ABSENCE_CONFIG, type AbsenceType } from '../constants/absenceConfig';
import { useTheme } from '../context/ThemeContext';

type TeamTab = 'team' | 'absences';

type TeamMemberStatus = 'booked' | 'absent' | 'available' | 'home';

interface TeamMemberCardModel {
  name: string;
  department: string;
  status: TeamMemberStatus;
  statusLabel: string;
  slot: string;
  vehicle: string;
  since: string;
  entries: TeamAbsenceEntry[];
}

export function TeamPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [entries, setEntries] = useState<TeamAbsenceEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<TeamTab>('team');

  useEffect(() => {
    api.teamAbsences().then((res) => {
      if (res.success && res.data) setEntries(res.data);
    }).finally(() => setLoading(false));
  }, []);

  const todayStr = useMemo(() => new Date().toISOString().slice(0, 10), []);
  const isVoid = designTheme === 'void';

  const todayEntries = useMemo(
    () => entries.filter((entry) => entry.start_date <= todayStr && entry.end_date >= todayStr),
    [entries, todayStr],
  );

  const upcomingEntries = useMemo(
    () => entries.filter((entry) => entry.start_date > todayStr).sort((a, b) => a.start_date.localeCompare(b.start_date)),
    [entries, todayStr],
  );

  const todayByUser = useMemo(() => groupByUser(todayEntries), [todayEntries]);
  const upcomingByUser = useMemo(() => groupByUser(upcomingEntries), [upcomingEntries]);
  const teamMembers = useMemo(() => buildTeamMembers(entries, todayStr), [entries, todayStr]);

  if (loading) {
    return (
      <div className="space-y-4">
        <div className="h-8 w-48 skeleton rounded-lg" />
        {[1, 2, 3].map((i) => <div key={i} className="h-20 skeleton rounded-2xl" />)}
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <Users weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void team deck' : 'Marble roster board'}
            </div>

            <h1 className="text-3xl font-black tracking-[-0.04em]">{t('team.title', 'Team')}</h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('team.subtitle', 'Abwesenheiten im Team')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <TeamHeroStat label={t('team.presentToday', 'Present today')} value={String(Math.max(0, teamMembers.length - todayEntries.length))} isVoid={isVoid} accent />
              <TeamHeroStat label={t('team.today', 'Heute abwesend')} value={String(todayEntries.length)} isVoid={isVoid} />
              <TeamHeroStat label={t('team.upcoming', 'Kommende Abwesenheiten')} value={String(upcomingEntries.length)} isVoid={isVoid} />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <div className="flex items-center gap-3">
              <div className={`flex h-12 w-12 items-center justify-center rounded-2xl ${
                isVoid
                  ? 'bg-cyan-500/10 text-cyan-100'
                  : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
              }`}>
                <Users weight="fill" className="h-6 w-6" />
              </div>
              <div>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('team.focusLabel', 'Coordination')}
                </p>
                <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em]">{t('team.operationsTitle', 'Team availability at a glance')}</h2>
              </div>
            </div>
            <p className={`mt-4 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('team.operationsHint', 'Use the roster tab for who is on-site right now, and the absences tab for today and the upcoming schedule.')}
            </p>
          </div>
        </div>
      </section>

      <section className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <div className="inline-flex gap-1 rounded-xl border border-surface-200 bg-surface-50 p-1 dark:border-surface-800 dark:bg-surface-900/70">
            {([
              { id: 'team' as const, label: t('team.title', 'Team') },
              { id: 'absences' as const, label: t('team.absencesTab', 'Abwesenheiten') },
            ]).map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => setTab(item.id)}
                className={`rounded-lg px-4 py-2 text-sm font-semibold transition-colors ${
                  tab === item.id
                    ? 'bg-white text-surface-900 shadow-sm dark:bg-surface-800 dark:text-white'
                    : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                }`}
              >
                {item.label}
              </button>
            ))}
          </div>

          <button type="button" className="ml-auto rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-500">
            + {t('team.invite', 'Einladen')}
          </button>
        </div>

        {tab === 'team' ? (
          teamMembers.length === 0 ? (
            <EmptyState message={t('team.noMembers', 'Noch keine Teamdaten vorhanden')} />
          ) : (
            <div className="space-y-3">
              {teamMembers.map((member) => (
                <TeamRosterCard key={member.name} member={member} />
              ))}
            </div>
          )
        ) : (
          <div className="grid gap-4 lg:grid-cols-2">
            <AbsencePanel
              title={`${t('team.today', 'Heute abwesend')} (${todayEntries.length})`}
              empty={t('team.noAbsencesToday', 'Heute keine Abwesenheiten')}
              groups={todayByUser}
            />
            <AbsencePanel
              title={`${t('team.upcoming', 'Kommende Abwesenheiten')} (${upcomingEntries.length})`}
              empty={t('team.noUpcoming', 'Keine anstehenden Abwesenheiten')}
              groups={upcomingByUser}
            />
          </div>
        )}
      </section>
    </motion.div>
  );
}

function groupByUser(list: TeamAbsenceEntry[]) {
  const groups: Record<string, TeamAbsenceEntry[]> = {};
  for (const entry of list) {
    if (!groups[entry.user_name]) groups[entry.user_name] = [];
    groups[entry.user_name].push(entry);
  }
  return groups;
}

function buildTeamMembers(entries: TeamAbsenceEntry[], todayStr: string): TeamMemberCardModel[] {
  const byUser = groupByUser(entries);

  return Object.entries(byUser)
    .map(([name, userEntries], index) => {
      const current = userEntries.find((entry) => entry.start_date <= todayStr && entry.end_date >= todayStr);
      const next = [...userEntries].sort((a, b) => a.start_date.localeCompare(b.start_date))[0];
      const status = inferStatus(current?.absence_type);

      return {
        name,
        department: `Team ${index + 1}`,
        status,
        statusLabel: statusLabelFor(status),
        slot: status === 'booked' ? `A-0${(index % 8) + 1}` : '—',
        vehicle: ['BMW', 'Audi', 'Tesla', 'VW', 'Ford', 'Skoda'][index % 6],
        since: next ? formatMonthYear(next.start_date) : '—',
        entries: userEntries,
      };
    })
    .sort((a, b) => a.name.localeCompare(b.name));
}

function inferStatus(absenceType?: string): TeamMemberStatus {
  if (!absenceType) return 'booked';
  if (absenceType === 'homeoffice') return 'home';
  if (absenceType === 'vacation' || absenceType === 'sick' || absenceType === 'other') return 'absent';
  return 'available';
}

function statusLabelFor(status: TeamMemberStatus): string {
  switch (status) {
    case 'booked':
      return 'Gebucht';
    case 'absent':
      return 'Abwesend';
    case 'home':
      return 'Home Office';
    default:
      return 'Verfügbar';
  }
}

function TeamHeroStat({ label, value, isVoid, accent = false }: { label: string; value: string; isVoid: boolean; accent?: boolean }) {
  return (
    <div className={`rounded-[22px] border px-4 py-4 ${
      isVoid
        ? accent
          ? 'border-cyan-500/20 bg-cyan-500/10'
          : 'border-white/10 bg-white/[0.04]'
        : accent
        ? 'border-emerald-200 bg-emerald-500/10 dark:border-emerald-900/60'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? (accent ? 'text-cyan-100' : 'text-white/45') : (accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45')}`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
    </div>
  );
}

function TeamRosterCard({ member }: { member: TeamMemberCardModel }) {
  const toneClasses = {
    booked: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    absent: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
    available: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    home: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
  } as const;

  return (
    <div className="grid gap-4 rounded-[20px] border border-surface-200 bg-surface-50/80 px-4 py-4 dark:border-surface-800 dark:bg-surface-900/60 lg:grid-cols-[36px_minmax(0,1fr)_140px_90px_120px_88px] lg:items-center">
      <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[linear-gradient(135deg,rgba(16,185,129,0.28),rgba(16,185,129,0.08))] text-sm font-bold text-emerald-700 dark:text-emerald-300">
        {member.name[0]?.toUpperCase()}
      </div>

      <div className="min-w-0">
        <p className="text-sm font-semibold text-surface-900 dark:text-white">{member.name}</p>
        <p className="text-xs text-surface-500 dark:text-surface-400">{member.department}</p>
      </div>

      <div>
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${toneClasses[member.status]}`}>
          {member.statusLabel}
        </span>
      </div>

      <RosterField label="Slot" value={member.slot} mono />
      <RosterField label="Vehicle" value={member.vehicle} />
      <RosterField label="Since" value={member.since} />
    </div>
  );
}

function RosterField({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-surface-400 dark:text-surface-500">{label}</p>
      <p className={`mt-1 text-sm font-medium text-surface-900 dark:text-white ${mono ? "font-['DM_Mono',monospace]" : ''}`}>{value}</p>
    </div>
  );
}

function AbsencePanel({
  title,
  empty,
  groups,
}: {
  title: string;
  empty: string;
  groups: Record<string, TeamAbsenceEntry[]>;
}) {
  return (
    <section className="rounded-[22px] border border-surface-200 bg-surface-50/80 p-4 dark:border-surface-800 dark:bg-surface-900/60">
      <h2 className="text-sm font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">{title}</h2>
      {Object.keys(groups).length === 0 ? (
        <div className="rounded-2xl border border-dashed border-surface-200 px-4 py-8 text-center text-sm text-surface-500 dark:border-surface-800 dark:text-surface-400">
          {empty}
        </div>
      ) : (
        <div className="mt-4 space-y-3">
          {Object.entries(groups).map(([name, items]) => (
            <AbsenceUserGroup key={name} name={name} entries={items} />
          ))}
        </div>
      )}
    </section>
  );
}

function AbsenceUserGroup({ name, entries }: { name: string; entries: TeamAbsenceEntry[] }) {
  return (
    <div className="rounded-2xl border border-white/80 bg-white/85 px-4 py-3 dark:border-surface-800 dark:bg-surface-950/70">
      <div className="mb-2 flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-200 text-sm font-semibold text-surface-700 dark:bg-surface-700 dark:text-surface-300">
          {name.charAt(0).toUpperCase()}
        </div>
        <span className="text-sm font-medium text-surface-900 dark:text-white">{name}</span>
      </div>
      <div className="space-y-2 pl-11">
        {entries.map((entry, i) => {
          const cfg = ABSENCE_CONFIG[entry.absence_type as AbsenceType] || ABSENCE_CONFIG.other;
          return (
            <div key={i} className="flex items-center gap-2">
              <div className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
              <span className={`text-sm ${cfg.color}`}>{entry.absence_type === 'homeoffice' ? 'Homeoffice' : entry.absence_type === 'vacation' ? 'Urlaub' : entry.absence_type}</span>
              <span className="ml-auto text-xs text-surface-500 dark:text-surface-400">
                {formatDate(entry.start_date)}
                {entry.start_date !== entry.end_date && <> – {formatDate(entry.end_date)}</>}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return (
    <div className="rounded-[20px] border border-dashed border-surface-200 px-6 py-10 text-center dark:border-surface-800">
      <Users weight="light" className="mx-auto h-12 w-12 text-surface-300 dark:text-surface-700" />
      <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">{message}</p>
    </div>
  );
}

function formatMonthYear(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}
