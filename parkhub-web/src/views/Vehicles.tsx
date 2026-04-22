import { useActionState, useEffect, useMemo, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Car, Plus, Trash, Star, X, SpinnerGap, Lightning } from '@phosphor-icons/react';
import { api, type Vehicle } from '../api/client';
import { VehiclesSkeleton } from '../components/Skeleton';
import { stagger, fadeUp } from '../constants/animations';
import { useTranslation } from 'react-i18next';
import { useTheme } from '../context/ThemeContext';
import toast from 'react-hot-toast';

type AddResult =
  | { kind: 'idle' }
  | { kind: 'ok'; vehicle: Vehicle }
  | { kind: 'err'; message: string };

const IDLE: AddResult = { kind: 'idle' };

const COLOR_DEFS = [
  { key: 'black', bg: 'bg-gray-900' },
  { key: 'white', bg: 'bg-white border border-surface-300' },
  { key: 'silver', bg: 'bg-gray-400' },
  { key: 'gray', bg: 'bg-gray-500' },
  { key: 'blue', bg: 'bg-blue-600' },
  { key: 'red', bg: 'bg-red-600' },
  { key: 'green', bg: 'bg-green-600' },
  { key: 'brown', bg: 'bg-amber-800' },
  { key: 'beige', bg: 'bg-amber-200' },
  { key: 'other', bg: 'bg-surface-400' },
];

const colorBgMap: Record<string, string> = {
  black: 'bg-gray-900',
  white: 'bg-white border border-surface-300',
  silver: 'bg-gray-400',
  gray: 'bg-gray-500',
  blue: 'bg-blue-600',
  red: 'bg-red-600',
  green: 'bg-green-600',
  brown: 'bg-amber-800',
  beige: 'bg-amber-200',
  other: 'bg-surface-400',
  Schwarz: 'bg-gray-900',
  Wei\u00df: 'bg-white border border-surface-300',
  Silber: 'bg-gray-400',
  Grau: 'bg-gray-500',
  Blau: 'bg-blue-600',
  Rot: 'bg-red-600',
  Gr\u00fcn: 'bg-green-600',
};

export function VehiclesPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ plate: '', make: '', model: '', color: '' });

  useEffect(() => {
    api.getVehicles().then((res) => {
      if (res.success && res.data) setVehicles(res.data);
    }).finally(() => setLoading(false));
  }, []);

  const [addResult, addAction, isAdding] = useActionState<AddResult, FormData>(
    async (_prev, _formData) => {
      const plate = form.plate.trim().toUpperCase();
      if (!plate) return { kind: 'err', message: t('vehicles.plateRequired', 'Plate required') };
      const res = await api.createVehicle({
        plate,
        make: form.make || undefined,
        model: form.model || undefined,
        color: form.color || undefined,
      });
      if (res.success && res.data) return { kind: 'ok', vehicle: res.data };
      return { kind: 'err', message: res.error?.message || t('common.error') };
    },
    IDLE,
  );

  useEffect(() => {
    if (addResult.kind === 'ok') {
      setVehicles((prev) => [...prev, addResult.vehicle]);
      toast.success(t('vehicles.added', 'Fahrzeug hinzugef\u00fcgt'));
      setForm({ plate: '', make: '', model: '', color: '' });
      setShowForm(false);
    } else if (addResult.kind === 'err') {
      toast.error(addResult.message);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [addResult]);

  async function handleDelete(id: string) {
    const res = await api.deleteVehicle(id);
    if (res.success) {
      setVehicles((prev) => prev.filter((v) => v.id !== id));
      toast.success(t('vehicles.removed', 'Fahrzeug entfernt'));
    }
  }

  const isVoid = designTheme === 'void';
  const container = stagger;
  const item = fadeUp;
  const activeVehicles = vehicles.filter((vehicle) => isVehicleActive(vehicle)).length;
  const evVehicles = vehicles.filter((vehicle) => isEvVehicle(vehicle)).length;
  const uniqueMakes = new Set(vehicles.map((vehicle) => vehicle.make).filter(Boolean)).size;

  const defaultVehicles = vehicles.filter((vehicle) => vehicle.is_default).length;
  const stats = useMemo(() => ([
    { label: t('vehicles.registeredCount', 'Registriert'), value: String(vehicles.length), meta: t('vehicles.fleetReady', 'Fleet ready') },
    { label: t('vehicles.activeLabel', 'Aktiv'), value: String(activeVehicles), meta: `${defaultVehicles} ${t('vehicles.defaultMeta', 'default')}` },
    { label: t('vehicles.evLabel', 'EV-ready'), value: String(evVehicles), meta: `${uniqueMakes} ${t('vehicles.makesMeta', 'makes')}` },
  ]), [activeVehicles, defaultVehicles, evVehicles, t, uniqueMakes, vehicles.length]);

  if (loading) return <VehiclesSkeleton />;

  return (
    <AnimatePresence mode="wait">
      <motion.div key="vehicles-loaded" variants={container} initial="hidden" animate="show" className="space-y-6">
        <motion.div
          variants={item}
          className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
            isVoid
              ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
              : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
          }`}
        >
          <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
              <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
                isVoid
                  ? 'bg-cyan-500/10 text-cyan-100'
                  : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
              }`}>
                <Car weight="fill" className="h-3.5 w-3.5" />
                {isVoid ? 'Void fleet deck' : 'Marble vehicle registry'}
              </div>

              <h1 className="text-3xl font-black tracking-[-0.04em] text-surface-900 dark:text-white">{t('vehicles.title', 'Meine Fahrzeuge')}</h1>
              <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                {t('vehicles.subtitle', 'Fahrzeuge verwalten')}
              </p>

              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                {stats.map((stat, index) => (
                  <VehicleHeroStat
                    key={stat.label}
                    label={stat.label}
                    value={stat.value}
                    meta={stat.meta}
                    isVoid={isVoid}
                    accent={index === 0}
                  />
                ))}
              </div>
            </div>

            <div className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-white/10 bg-white/[0.04]'
                : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
            }`}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                    {t('vehicles.registryLabel', 'Registry')}
                  </p>
                  <h2 className="mt-2 text-xl font-semibold tracking-[-0.03em]">{t('vehicles.title', 'Meine Fahrzeuge')}</h2>
                  <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                    {t('vehicles.registryHint', 'Keep license plates, EV capability, and preferred defaults aligned before you book.' )}
                  </p>
                </div>
                <button onClick={() => setShowForm(true)} className="btn btn-primary shrink-0">
                  <Plus weight="bold" className="w-4 h-4" /> {t('vehicles.add', 'Hinzuf\u00fcgen')}
                </button>
              </div>

              <div className="mt-5 grid gap-3 sm:grid-cols-2">
                <PanelMetric label={t('vehicles.defaultLabel', 'Default')} value={vehicles.find((vehicle) => vehicle.is_default)?.plate ?? '—'} isVoid={isVoid} mono />
                <PanelMetric label={t('vehicles.evLabel', 'EV-ready')} value={evVehicles ? `${evVehicles}` : '0'} isVoid={isVoid} />
              </div>
            </div>
          </div>
        </motion.div>

        <AnimatePresence>
          {showForm && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 z-50 flex items-center justify-center p-4"
              role="dialog"
              aria-modal="true"
              aria-label={t('vehicles.newVehicle', 'New vehicle')}
            >
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="absolute inset-0 bg-black/50 backdrop-blur-sm"
                onClick={() => setShowForm(false)}
                aria-hidden="true"
              />
              <motion.div
                initial={{ opacity: 0, scale: 0.95, y: 20 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.95 }}
                className="relative w-full max-w-md glass-modal shadow-2xl"
              >
                <div className="flex items-center justify-between border-b border-surface-200 px-6 py-4 dark:border-surface-800">
                  <h2 className="flex items-center gap-2 text-lg font-semibold text-surface-900 dark:text-white">
                    <Car weight="fill" className="h-5 w-5 text-primary-600" />
                    {t('vehicles.newVehicle', 'Neues Fahrzeug')}
                  </h2>
                  <button onClick={() => setShowForm(false)} className="rounded-lg p-2 hover:bg-surface-100 dark:hover:bg-surface-800" aria-label={t('common.cancel', 'Close')}>
                    <X weight="bold" className="h-5 w-5 text-surface-500" aria-hidden="true" />
                  </button>
                </div>

                <form action={addAction} className="space-y-4 p-6">
                  <div>
                    <label htmlFor="vehicle-plate" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('vehicles.plate', 'Kennzeichen')} *</label>
                    <input id="vehicle-plate" type="text" value={form.plate} onChange={(e) => setForm({ ...form, plate: e.target.value })} className="input w-full" placeholder="M-AB 1234" required autoFocus />
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label htmlFor="vehicle-make" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('vehicles.make', 'Marke')}</label>
                      <input id="vehicle-make" type="text" value={form.make} onChange={(e) => setForm({ ...form, make: e.target.value })} className="input w-full" placeholder="BMW" />
                    </div>
                    <div>
                      <label htmlFor="vehicle-model" className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('vehicles.model', 'Modell')}</label>
                      <input id="vehicle-model" type="text" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} className="input w-full" placeholder="3er" />
                    </div>
                  </div>

                  <div>
                    <label className="mb-1 block text-sm font-medium text-surface-700 dark:text-surface-300">{t('vehicles.color', 'Farbe')}</label>
                    <div className="mt-1 flex flex-wrap gap-2">
                      {COLOR_DEFS.map((color) => (
                        <button
                          key={color.key}
                          type="button"
                          onClick={() => setForm({ ...form, color: form.color === color.key ? '' : color.key })}
                          aria-label={t(`vehicles.colors.${color.key}`)}
                          aria-pressed={form.color === color.key}
                          className={`h-8 w-8 rounded-full transition-all ${color.bg} ${form.color === color.key ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-900 scale-110' : ''}`}
                        />
                      ))}
                    </div>
                  </div>

                  <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={() => setShowForm(false)} className="btn btn-secondary">{t('common.cancel', 'Abbrechen')}</button>
                    <button type="submit" disabled={isAdding || !form.plate.trim()} className="btn btn-primary">
                      {isAdding ? <SpinnerGap weight="bold" className="h-4 w-4 animate-spin" /> : t('common.save', 'Speichern')}
                    </button>
                  </div>
                </form>
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>

        {vehicles.length === 0 ? (
          <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-16 text-center shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
            <Car weight="light" className="mx-auto h-20 w-20 text-surface-200 dark:text-surface-700" />
            <p className="mt-4 text-surface-500 dark:text-surface-400">{t('vehicles.noVehicles', 'Noch keine Fahrzeuge angelegt')}</p>
            <motion.button onClick={() => setShowForm(true)} className="btn btn-primary mt-6" whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
              <Plus weight="bold" className="w-4 h-4" /> {t('vehicles.add', 'Hinzuf\u00fcgen')}
            </motion.button>
          </motion.div>
        ) : (
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {vehicles.map((vehicle, index) => {
              const colorClass = vehicle.color ? (colorBgMap[vehicle.color] || 'bg-surface-400') : 'bg-surface-400';
              const evVehicle = isEvVehicle(vehicle);
              const vehicleStatus = isVehicleActive(vehicle) ? t('vehicles.activeLabel', 'Aktiv') : t('vehicles.inactiveLabel', 'Inaktiv');

              return (
                <motion.div key={vehicle.id} variants={item} className="overflow-hidden rounded-[24px] border border-surface-200 bg-white shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
                  <div className={`flex items-center gap-4 border-b border-surface-200 px-5 py-5 dark:border-surface-800 ${
                    isVoid
                      ? 'bg-[linear-gradient(135deg,rgba(8,47,73,0.65),rgba(15,23,42,0.92))]'
                      : 'bg-[linear-gradient(135deg,rgba(16,185,129,0.10),rgba(16,185,129,0.03))] dark:bg-[linear-gradient(135deg,rgba(16,185,129,0.14),rgba(15,23,42,0.12))]'
                  }`}>
                    <div className={`flex h-14 w-14 items-center justify-center rounded-[18px] ${colorClass}`}>
                      <Car weight="fill" className="h-6 w-6 text-white" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-['DM_Mono',monospace] text-lg font-semibold tracking-[0.12em] text-surface-900 dark:text-white">{vehicle.plate}</p>
                        {vehicle.is_default && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                            <Star weight="fill" className="h-3.5 w-3.5" /> {t('vehicles.isDefault', 'Standardfahrzeug')}
                          </span>
                        )}
                        {evVehicle && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-sky-500/10 px-2.5 py-1 text-[11px] font-semibold text-sky-700 dark:text-sky-300">
                            <Lightning weight="fill" className="h-3.5 w-3.5" /> EV
                          </span>
                        )}
                      </div>
                      {(vehicle.make || vehicle.model) && (
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{[vehicle.make, vehicle.model].filter(Boolean).join(' ')}</p>
                      )}
                    </div>
                    <button onClick={() => handleDelete(vehicle.id)} className="rounded-xl p-2 text-surface-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/20" aria-label={t('vehicles.deleteVehicle', { plate: vehicle.plate })}>
                      <Trash weight="regular" className="h-5 w-5" aria-hidden="true" />
                    </button>
                  </div>

                  <div className="space-y-4 p-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                      <VehicleInfoField label={t('vehicles.typeLabel', 'Typ')} value={deriveVehicleType(vehicle)} />
                      <VehicleInfoField label={t('vehicles.statusLabel', 'Status')} value={vehicleStatus} accent />
                      <VehicleInfoField label={t('vehicles.color', 'Farbe')} value={vehicle.color ? t(`vehicles.colors.${vehicle.color}`, vehicle.color) : '—'} />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-surface-200 pt-4 dark:border-surface-800">
                      <div className="text-xs uppercase tracking-[0.18em] text-surface-400 dark:text-surface-500">
                        {t('vehicles.registrySlot', 'Registry slot')} #{index + 1}
                      </div>
                      <div className="flex gap-2">
                        <button type="button" className="rounded-xl border border-surface-200 px-3 py-2 text-sm font-semibold text-surface-600 transition-colors hover:border-primary-400 hover:text-primary-700 dark:border-surface-700 dark:text-surface-300 dark:hover:text-primary-300">
                          {t('vehicles.edit', 'Bearbeiten')}
                        </button>
                        <a href="/book" className="btn btn-primary">
                          {t('vehicles.book', 'Buchen')}
                        </a>
                      </div>
                    </div>
                  </div>
                </motion.div>
              );
            })}
          </div>
        )}
      </motion.div>
    </AnimatePresence>
  );
}

function VehicleHeroStat({
  label,
  value,
  meta,
  isVoid,
  accent = false,
}: {
  label: string;
  value: string;
  meta: string;
  isVoid: boolean;
  accent?: boolean;
}) {
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
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? (accent ? 'text-cyan-100' : 'text-white/45') : (accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45')}`}>{label}</p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function PanelMetric({ label, value, isVoid, mono = false }: { label: string; value: string; isVoid: boolean; mono?: boolean }) {
  return (
    <div className={`rounded-[20px] border px-4 py-4 ${isVoid ? 'border-white/10 bg-slate-950/60' : 'border-surface-200 bg-surface-50/80 dark:border-surface-800 dark:bg-surface-900/60'}`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>{label}</p>
      <p className={`mt-2 text-lg font-semibold ${mono ? "font-['DM_Mono',monospace]" : ''} ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
    </div>
  );
}

function VehicleInfoField({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
  return (
    <div className="rounded-[18px] border border-surface-200 bg-surface-50/80 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/60">
      <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-surface-400 dark:text-surface-500">{label}</p>
      <p className={`mt-1 text-sm font-medium ${accent ? 'text-primary-700 dark:text-primary-300' : 'text-surface-900 dark:text-white'}`}>{value}</p>
    </div>
  );
}

function isVehicleActive(vehicle: Vehicle): boolean {
  return (vehicle as Vehicle & { status?: string }).status !== 'inactive';
}

function isEvVehicle(vehicle: Vehicle): boolean {
  const haystack = `${vehicle.make ?? ''} ${vehicle.model ?? ''}`.toLowerCase();
  return haystack.includes('tesla') || haystack.includes('ev') || haystack.includes('electric');
}

function deriveVehicleType(vehicle: Vehicle): string {
  if (isEvVehicle(vehicle)) return 'PKW (EV)';
  return 'PKW';
}
