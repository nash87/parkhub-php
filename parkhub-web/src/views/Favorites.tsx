import { useEffect, useMemo, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Star, Trash, MapPin, SpinnerGap } from '@phosphor-icons/react';
import { api, type Favorite, type ParkingLot, type ParkingSlot } from '../api/client';
import { stagger, fadeUp } from '../constants/animations';
import { useTranslation } from 'react-i18next';
import { useTheme } from '../context/ThemeContext';
import toast from 'react-hot-toast';

interface EnrichedFavorite extends Favorite {
  lot_name?: string;
  slot_number?: string;
  slot_status?: string;
}

export function FavoritesPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [favorites, setFavorites] = useState<Favorite[]>([]);
  const [lots, setLots] = useState<ParkingLot[]>([]);
  const [slotMap, setSlotMap] = useState<Record<string, ParkingSlot>>({});
  const [loading, setLoading] = useState(true);
  const [removing, setRemoving] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      api.getFavorites(),
      api.getLots(),
    ]).then(async ([favRes, lotRes]) => {
      const favs = favRes.success && favRes.data ? favRes.data : [];
      const allLots = lotRes.success && lotRes.data ? lotRes.data : [];
      setFavorites(favs);
      setLots(allLots);

      const lotIds = [...new Set(favs.map((favorite) => favorite.lot_id))];
      const slotsById: Record<string, ParkingSlot> = {};
      await Promise.all(lotIds.map(async (lotId) => {
        const res = await api.getLotSlots(lotId);
        if (res.success && res.data) {
          for (const slot of res.data) slotsById[slot.id] = slot;
        }
      }));
      setSlotMap(slotsById);
    }).finally(() => setLoading(false));
  }, []);

  const lotMap = useMemo(() => {
    const result: Record<string, ParkingLot> = {};
    for (const lot of lots) result[lot.id] = lot;
    return result;
  }, [lots]);

  const enriched = useMemo<EnrichedFavorite[]>(() => (
    favorites.map((favorite) => ({
      ...favorite,
      lot_name: lotMap[favorite.lot_id]?.name,
      slot_number: slotMap[favorite.slot_id]?.slot_number,
      slot_status: slotMap[favorite.slot_id]?.status,
    }))
  ), [favorites, lotMap, slotMap]);

  async function handleRemove(slotId: string) {
    setRemoving(slotId);
    const res = await api.removeFavorite(slotId);
    if (res.success) {
      setFavorites((current) => current.filter((favorite) => favorite.slot_id !== slotId));
      toast.success(t('favorites.removed'));
    } else {
      toast.error(res.error?.message || t('common.error'));
    }
    setRemoving(null);
  }

  const isVoid = designTheme === 'void';
  const availableCount = useMemo(
    () => enriched.filter((favorite) => favorite.slot_status === 'available').length,
    [enriched],
  );
  const monitoredLots = useMemo(
    () => new Set(enriched.map((favorite) => favorite.lot_name || favorite.lot_id)).size,
    [enriched],
  );
  const latestFavorite = useMemo(
    () => [...enriched].sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime())[0] ?? null,
    [enriched],
  );
  const container = stagger;
  const item = fadeUp;

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="skeleton h-8 w-48 rounded-lg" />
        <div className="skeleton h-4 w-64 rounded" />
        <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
          {[1, 2, 3].map((index) => <div key={index} className="skeleton h-24 rounded-xl" />)}
        </div>
      </div>
    );
  }

  return (
    <AnimatePresence mode="wait">
      <motion.div key="favorites-loaded" variants={container} initial="hidden" animate="show" className="space-y-6">
        <motion.div
          variants={item}
          data-testid="favorites-summary-surface"
          data-surface-tone={isVoid ? 'void' : 'marble'}
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
                <Star weight="fill" className="h-3.5 w-3.5" />
                {isVoid ? 'Void favorites deck' : 'Marble saved slots'}
              </div>

              <h1 className="text-3xl font-black tracking-[-0.04em]">{t('favorites.title')}</h1>
              <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                {t('favorites.subtitle')}
              </p>

              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <FavoriteHeroStat
                  label={t('favorites.savedLabel', 'Saved')}
                  value={String(enriched.length)}
                  meta={t('favorites.count', { count: favorites.length })}
                  isVoid={isVoid}
                  accent
                />
                <FavoriteHeroStat
                  label={t('favorites.availableLabel', 'Available')}
                  value={String(availableCount)}
                  meta={t('favorites.availableMeta', 'Open now')}
                  isVoid={isVoid}
                />
                <FavoriteHeroStat
                  label={t('favorites.lotsLabel', 'Lots')}
                  value={String(monitoredLots)}
                  meta={t('favorites.monitoringLabel', 'Monitoring')}
                  isVoid={isVoid}
                />
              </div>
            </div>

            <div className={`rounded-[24px] border p-5 ${
              isVoid
                ? 'border-white/10 bg-white/[0.04]'
                : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
            }`}>
              <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
                {t('favorites.latestLabel', 'Watchlist')}
              </p>
              <h2 className="mt-2 text-xl font-semibold tracking-[-0.03em]">
                {latestFavorite
                  ? t('favorites.latestSavedTitle', 'Latest saved slot')
                  : t('favorites.latestEmpty', 'Ready for your next star')}
              </h2>
              <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                {latestFavorite
                  ? `${latestFavorite.slot_number || '—'} · ${latestFavorite.lot_name || t('favorites.unknownLot')} · ${latestFavorite.slot_status === 'available' ? t('favorites.available') : t('favorites.occupied')}`
                  : t('favorites.latestEmptyHint', 'Keep one-star access ready for the lots you revisit most.')}
              </p>

              <div className="mt-5 grid gap-3 sm:grid-cols-2">
                <DetailMetric
                  label={t('favorites.latestSlotLabel', 'Latest slot')}
                  value={latestFavorite?.slot_number || '—'}
                  isVoid={isVoid}
                />
                <DetailMetric
                  label={t('favorites.latestAddedLabel', 'Added')}
                  value={latestFavorite?.created_at ? new Date(latestFavorite.created_at).toLocaleDateString() : '—'}
                  isVoid={isVoid}
                />
              </div>
            </div>
          </div>
        </motion.div>

        {enriched.length === 0 ? (
          <motion.div
            variants={item}
            className={`rounded-[24px] border px-6 py-14 text-center ${
              isVoid
                ? 'border-slate-800 bg-slate-950/85 text-white'
                : 'border-surface-200 bg-white shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80'
            }`}
          >
            <div className={`mx-auto flex h-20 w-20 items-center justify-center rounded-full ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
            }`}>
              <Star weight="light" className="h-12 w-12" />
            </div>
            <p className="mt-5 text-base font-semibold text-surface-900 dark:text-white">{t('favorites.empty')}</p>
            <p className={`mt-2 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>
              {t('favorites.emptyHint')}
            </p>
          </motion.div>
        ) : (
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {enriched.map((favorite) => {
              const isAvailable = favorite.slot_status === 'available';
              return (
                <motion.div
                  key={favorite.slot_id}
                  variants={item}
                  layout
                  className={`rounded-[24px] border p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] ${
                    isVoid
                      ? 'border-slate-800 bg-slate-950/85 text-white'
                      : 'border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-950/80'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className={`mt-0.5 flex h-11 w-11 items-center justify-center rounded-[18px] ${
                        isAvailable
                          ? 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-300'
                          : isVoid
                            ? 'bg-white/[0.06] text-slate-300'
                            : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'
                      }`}>
                        <Star weight="fill" className="h-5 w-5" />
                      </div>

                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <p className="text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">
                            {t('favorites.slot')} {favorite.slot_number || '—'}
                          </p>
                          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                            isAvailable
                              ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                              : isVoid
                                ? 'bg-white/[0.06] text-slate-300'
                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                          }`}>
                            {isAvailable ? t('favorites.available') : t('favorites.occupied')}
                          </span>
                        </div>

                        <div className={`mt-2 flex items-center gap-1.5 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>
                          <MapPin weight="regular" className="h-3.5 w-3.5" />
                          {favorite.lot_name || t('favorites.unknownLot')}
                        </div>
                      </div>
                    </div>

                    <button
                      onClick={() => handleRemove(favorite.slot_id)}
                      disabled={removing === favorite.slot_id}
                      className={`rounded-xl p-2 transition-colors ${
                        isVoid
                          ? 'text-slate-300 hover:bg-white/[0.06] hover:text-rose-200'
                          : 'text-surface-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20'
                      }`}
                      aria-label={t('favorites.remove', { slot: favorite.slot_number || favorite.slot_id })}
                    >
                      {removing === favorite.slot_id
                        ? <SpinnerGap weight="bold" className="h-5 w-5 animate-spin" />
                        : <Trash weight="regular" className="h-5 w-5" aria-hidden="true" />}
                    </button>
                  </div>

                  <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <DetailMetric
                      label={t('favorites.statusLabel', 'Status')}
                      value={isAvailable ? t('favorites.availableMetric', 'Open now') : t('favorites.occupiedMetric', 'Currently busy')}
                      isVoid={isVoid}
                    />
                    <DetailMetric
                      label={t('favorites.addedLabel', 'Added')}
                      value={favorite.created_at ? new Date(favorite.created_at).toLocaleDateString() : '—'}
                      isVoid={isVoid}
                    />
                  </div>

                  {favorite.created_at && (
                    <p className={`mt-4 border-t pt-4 text-xs ${
                      isVoid
                        ? 'border-white/10 text-slate-400'
                        : 'border-surface-100 text-surface-400 dark:border-surface-800 dark:text-surface-500'
                    }`}>
                      {t('favorites.addedOn', { date: new Date(favorite.created_at).toLocaleDateString() })}
                    </p>
                  )}
                </motion.div>
              );
            })}
          </div>
        )}
      </motion.div>
    </AnimatePresence>
  );
}

function FavoriteHeroStat({
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
    <div className={`rounded-[20px] border px-4 py-3 backdrop-blur ${
      isVoid
        ? accent
          ? 'border-cyan-500/30 bg-cyan-500/10'
          : 'border-white/10 bg-white/[0.04]'
        : accent
          ? 'border-emerald-200 bg-white/85 dark:border-emerald-900/40 dark:bg-white/[0.04]'
          : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.16em] ${isVoid ? 'text-white/50' : 'text-surface-500 dark:text-white/45'}`}>
        {label}
      </p>
      <div className="mt-2 flex items-end justify-between gap-3">
        <span className="text-2xl font-semibold tracking-[-0.03em]">{value}</span>
        <span className={`text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</span>
      </div>
    </div>
  );
}

function DetailMetric({ label, value, isVoid }: { label: string; value: string; isVoid: boolean }) {
  return (
    <div className={`rounded-[18px] border px-4 py-3 ${
      isVoid
        ? 'border-white/10 bg-slate-950/70'
        : 'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.16em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
        {label}
      </p>
      <p className="mt-2 text-sm font-medium text-surface-900 dark:text-white">{value}</p>
    </div>
  );
}
