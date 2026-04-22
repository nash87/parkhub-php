import { useEffect, useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import { Lightning, Play, Stop, Question, Clock, BatteryCharging } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { useTheme } from '../context/ThemeContext';

interface EvCharger {
  id: string;
  lot_id: string;
  label: string;
  connector_type: 'type2' | 'ccs' | 'chademo' | 'tesla';
  power_kw: number;
  status: 'available' | 'in_use' | 'offline' | 'maintenance';
  location_hint: string | null;
}

interface ChargingSession {
  id: string;
  charger_id: string;
  user_id: string;
  start_time: string;
  end_time: string | null;
  kwh_consumed: number;
  status: 'active' | 'completed' | 'cancelled';
}

interface Lot {
  id: string;
  name: string;
}

interface ChargerStats {
  total_chargers: number;
  available: number;
  in_use: number;
  offline: number;
  total_sessions: number;
  total_kwh: number;
}

const statusColors: Record<string, string> = {
  available: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  in_use: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  offline: 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
  maintenance: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const connectorLabels: Record<string, string> = {
  type2: 'Type 2',
  ccs: 'CCS',
  chademo: 'CHAdeMO',
  tesla: 'Tesla',
};

function getSurfaceTone(designTheme: string): 'marble' | 'void' {
  return designTheme === 'void' ? 'void' : 'marble';
}

export function EVChargingPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [lots, setLots] = useState<Lot[]>([]);
  const [selectedLot, setSelectedLot] = useState('');
  const [chargers, setChargers] = useState<EvCharger[]>([]);
  const [sessions, setSessions] = useState<ChargingSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [showHelp, setShowHelp] = useState(false);

  const loadLots = useCallback(async () => {
    try {
      const res = await fetch('/api/v1/lots').then(r => r.json());
      if (res.success) {
        setLots(res.data || []);
        if (res.data?.length > 0 && !selectedLot) setSelectedLot(res.data[0].id);
      }
    } catch { /* ignore */ }
  }, [selectedLot]);

  const loadChargers = useCallback(async () => {
    if (!selectedLot) return;
    setLoading(true);
    try {
      const [chRes, sesRes] = await Promise.all([
        fetch(`/api/v1/lots/${selectedLot}/chargers`).then(r => r.json()),
        fetch('/api/v1/chargers/sessions').then(r => r.json()),
      ]);
      if (chRes.success) setChargers(chRes.data || []);
      if (sesRes.success) setSessions(sesRes.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  }, [selectedLot]);

  useEffect(() => { loadLots(); }, [loadLots]);
  useEffect(() => { loadChargers(); }, [loadChargers]);

  async function handleStart(chargerId: string) {
    try {
      const res = await fetch(`/api/v1/chargers/${chargerId}/start`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
      }).then(r => r.json());
      if (res.success) { toast.success(t('evCharging.started')); loadChargers(); }
      else toast.error(res.error?.message || t('common.error'));
    } catch { toast.error(t('common.error')); }
  }

  async function handleStop(chargerId: string) {
    try {
      const res = await fetch(`/api/v1/chargers/${chargerId}/stop`, { method: 'POST' }).then(r => r.json());
      if (res.success) { toast.success(t('evCharging.stopped')); loadChargers(); }
      else toast.error(res.error?.message || t('common.error'));
    } catch { toast.error(t('common.error')); }
  }

  const activeSession = (chargerId: string) => sessions.find(s => s.charger_id === chargerId && s.status === 'active');
  const surfaceTone = getSurfaceTone(designTheme);
  const isVoid = surfaceTone === 'void';
  const selectedLotName = lots.find((lot) => lot.id === selectedLot)?.name || t('evCharging.allLots', 'Selected lot');
  const availableCount = chargers.filter((charger) => charger.status === 'available').length;
  const liveCount = sessions.filter((session) => session.status === 'active').length;
  const completedSessions = sessions.filter((session) => session.status === 'completed').slice(0, 10);
  const statusCards = [
    {
      label: t('evCharging.totalChargers', 'Total Chargers'),
      value: String(chargers.length),
      meta: selectedLotName,
    },
    {
      label: t('evCharging.status.available', 'Available'),
      value: String(availableCount),
      meta: t('evCharging.readyLabel', 'Ready to start'),
    },
    {
      label: t('evCharging.liveSessions', 'Live sessions'),
      value: String(liveCount),
      meta: liveCount > 0 ? t('evCharging.liveNow', 'Charging now') : t('evCharging.waitingLabel', 'Standing by'),
    },
  ];

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
      data-testid="ev-charging-shell"
      data-surface={surfaceTone}
    >
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,249,255,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.16),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(17,24,39,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-sky-700 dark:bg-white/10 dark:text-sky-300'
            }`}>
              <Lightning weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void charging deck' : 'Marble charging suite'}
            </div>

            <div className="flex items-start gap-3">
              <div className="min-w-0 flex-1">
                <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
                  <Lightning weight="duotone" className="h-7 w-7 text-yellow-500" />
                  {t('evCharging.title')}
                  <button onClick={() => setShowHelp(!showHelp)} className={isVoid ? 'text-white/55 hover:text-cyan-200' : 'text-surface-400 hover:text-primary-500'} aria-label="Help">
                    <Question weight="fill" className="h-5 w-5" />
                  </button>
                </h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('evCharging.subtitle')}
                </p>
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              {statusCards.map((card, index) => (
                <div
                  key={card.label}
                  className={`rounded-[20px] border p-4 ${
                    isVoid
                      ? index === 0
                        ? 'border-cyan-400/20 bg-cyan-400/10'
                        : 'border-white/10 bg-white/[0.04]'
                      : index === 0
                        ? 'border-sky-200 bg-white/85 dark:border-sky-500/20 dark:bg-white/[0.06]'
                        : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
                  }`}
                >
                  <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/55' : 'text-surface-500 dark:text-white/45'}`}>
                    {card.label}
                  </p>
                  <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{card.value}</p>
                  <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>{card.meta}</p>
                </div>
              ))}
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              {t('evCharging.routeLabel', 'Route')}
            </p>
            <div className="mt-4 space-y-4">
              <label className="block">
                <span className={`mb-2 block text-sm font-medium ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>
                  {t('evCharging.lotSelector', 'Parking lot')}
                </span>
                <select
                  value={selectedLot}
                  onChange={e => setSelectedLot(e.target.value)}
                  className={`w-full rounded-2xl border px-3 py-2 text-sm ${
                    isVoid
                      ? 'border-white/10 bg-slate-950/60 text-white'
                      : 'border-surface-200 bg-white text-surface-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white'
                  }`}
                >
                  {lots.map(lot => <option key={lot.id} value={lot.id}>{lot.name}</option>)}
                </select>
              </label>

              <div className={`rounded-[20px] border p-4 ${
                isVoid
                  ? 'border-white/10 bg-slate-950/40'
                  : 'border-stone-200 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
              }`}>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/50' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('evCharging.opsSummary', 'Live summary')}
                </p>
                <p className="mt-2 text-lg font-semibold tracking-[-0.03em]">
                  {selectedLotName}
                </p>
                <p className={`mt-1 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {chargers.length > 0
                    ? t('evCharging.help', 'Manage EV chargers and sessions.')
                    : t('evCharging.empty')}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {showHelp && (
        <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} className={`rounded-[22px] border p-4 text-sm ${
          isVoid
            ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-100'
            : 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100'
        }`}>
          <p className="font-semibold mb-1">{t('evCharging.aboutTitle')}</p>
          <p>{t('evCharging.help')}</p>
        </motion.div>
      )}

      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" /></div>
      ) : chargers.length === 0 ? (
        <div className={`rounded-[24px] border py-12 text-center ${
          isVoid
            ? 'border-white/10 bg-slate-950/40 text-slate-400'
            : 'border-surface-200 bg-white text-surface-400 dark:border-surface-800 dark:bg-surface-950/70'
        }`}>
          <Lightning className="w-12 h-12 mx-auto mb-3 opacity-40" />
          <p>{t('evCharging.empty')}</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {chargers.map(ch => {
            const active = activeSession(ch.id);
            return (
              <motion.div
                key={ch.id}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className={`rounded-[24px] border p-4 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.22)] ${
                  isVoid
                    ? 'border-white/10 bg-slate-950/45'
                    : 'border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900/70'
                }`}
              >
                <div className="flex items-center justify-between mb-3">
                  <div>
                    <span className="font-semibold text-surface-900 dark:text-white">{ch.label}</span>
                    <p className="text-xs text-surface-500 dark:text-surface-400">{selectedLotName}</p>
                  </div>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[ch.status]}`}>
                    {t(`evCharging.status.${ch.status}`)}
                  </span>
                </div>
                <div className="space-y-1 text-sm text-surface-500 dark:text-surface-300 mb-3">
                  <div className="flex items-center gap-1"><BatteryCharging className="w-4 h-4" />{connectorLabels[ch.connector_type]} - {ch.power_kw} kW</div>
                  {ch.location_hint && <div>{ch.location_hint}</div>}
                </div>
                {ch.status === 'available' && (
                  <button onClick={() => handleStart(ch.id)} className="btn-primary w-full flex items-center justify-center gap-2 text-sm">
                    <Play weight="bold" className="w-4 h-4" />{t('evCharging.startCharging')}
                  </button>
                )}
                {active && (
                  <div>
                    <div className="flex items-center gap-1 text-sm text-amber-600 mb-2">
                      <Clock className="w-4 h-4" />{t('evCharging.chargingSince')} {new Date(active.start_time).toLocaleTimeString()}
                    </div>
                    <button onClick={() => handleStop(ch.id)} className="btn-secondary w-full flex items-center justify-center gap-2 text-sm border-red-300 text-red-600 hover:bg-red-50">
                      <Stop weight="bold" className="w-4 h-4" />{t('evCharging.stopCharging')}
                    </button>
                  </div>
                )}
              </motion.div>
            );
          })}
        </div>
      )}

      {/* Session history */}
      {sessions.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-3">{t('evCharging.history')}</h2>
          <div className="space-y-2">
            {completedSessions.map(s => (
              <div
                key={s.id}
                className={`flex items-center justify-between rounded-[20px] border p-3 text-sm ${
                  isVoid
                    ? 'border-white/10 bg-slate-950/40'
                    : 'border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900/70'
                }`}
              >
                <div>
                  <span className="text-surface-900 dark:text-white font-medium">{new Date(s.start_time).toLocaleDateString()}</span>
                  <span className="text-surface-500 ml-2">{new Date(s.start_time).toLocaleTimeString()} - {s.end_time ? new Date(s.end_time).toLocaleTimeString() : '-'}</span>
                </div>
                <span className="font-semibold text-green-600">{s.kwh_consumed.toFixed(1)} kWh</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </motion.div>
  );
}

export function AdminChargersPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [stats, setStats] = useState<ChargerStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [showHelp, setShowHelp] = useState(false);
  const surfaceTone = getSurfaceTone(designTheme);
  const isVoid = surfaceTone === 'void';

  useEffect(() => {
    setLoading(true);
    fetch('/api/v1/admin/chargers').then(r => r.json()).then(res => {
      if (res.success) setStats(res.data);
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
      data-testid="admin-chargers-shell"
      data-surface-tone={surfaceTone}
    >
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(250,204,21,0.16),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(255,251,235,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(250,204,21,0.14),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-amber-700 dark:bg-white/10 dark:text-amber-300'
            }`}>
              <Lightning weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void fleet monitor' : 'Marble ops overview'}
            </div>
            <h1 className="flex items-center gap-2 text-3xl font-black tracking-[-0.04em]">
              <Lightning weight="duotone" className="h-7 w-7 text-yellow-500" />
              {t('evCharging.adminTitle')}
              <button onClick={() => setShowHelp(!showHelp)} className={isVoid ? 'text-white/55 hover:text-cyan-200' : 'text-surface-400 hover:text-primary-500'} aria-label="Help">
                <Question weight="fill" className="h-5 w-5" />
              </button>
            </h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('evCharging.adminSubtitle')}
            </p>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              {t('evCharging.opsSummary', 'Live summary')}
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <div className={`rounded-[20px] border p-4 ${
                isVoid
                  ? 'border-cyan-400/20 bg-cyan-400/10'
                  : 'border-amber-200 bg-white/85 dark:border-amber-500/20 dark:bg-white/[0.06]'
              }`}>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/55' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('evCharging.totalChargers', 'Total Chargers')}
                </p>
                <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{stats?.total_chargers ?? '—'}</p>
              </div>
              <div className={`rounded-[20px] border p-4 ${
                isVoid
                  ? 'border-white/10 bg-white/[0.04]'
                  : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
              }`}>
                <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/55' : 'text-surface-500 dark:text-white/45'}`}>
                  {t('evCharging.totalSessions', 'Sessions')}
                </p>
                <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{stats?.total_sessions ?? '—'}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {showHelp && (
        <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} className={`rounded-[22px] border p-4 text-sm ${
          isVoid
            ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-100'
            : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100'
        }`}>
          <p className="font-semibold mb-1">{t('evCharging.aboutTitle')}</p>
          <p>{t('evCharging.help')}</p>
        </motion.div>
      )}

      {loading || !stats ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" /></div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          {[
            { label: t('evCharging.totalChargers'), value: stats.total_chargers, color: 'text-surface-900 dark:text-white' },
            { label: t('evCharging.status.available'), value: stats.available, color: 'text-green-600' },
            { label: t('evCharging.status.in_use'), value: stats.in_use, color: 'text-amber-600' },
            { label: t('evCharging.status.offline'), value: stats.offline, color: 'text-surface-400' },
            { label: t('evCharging.totalSessions'), value: stats.total_sessions, color: 'text-primary-600' },
            { label: t('evCharging.totalKwh'), value: `${stats.total_kwh.toFixed(0)} kWh`, color: 'text-yellow-600' },
          ].map((s, i) => (
            <div
              key={i}
              className={`rounded-[22px] border p-4 text-center ${
                isVoid
                  ? 'border-white/10 bg-slate-950/40'
                  : 'border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900/70'
              }`}
            >
              <div className={`text-2xl font-bold ${s.color}`}>{s.value}</div>
              <div className="text-sm text-surface-500 mt-1">{s.label}</div>
            </div>
          ))}
        </div>
      )}
    </motion.div>
  );
}
