import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import { MapPin, NavigationArrow, Lightning, Wheelchair } from '@phosphor-icons/react';
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { api, type LotMarker } from '../api/client';
import { useTheme } from '../context/ThemeContext';
import { staggerSlow, fadeUp } from '../constants/animations';

delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

const MARKER_COLORS: Record<string, string> = {
  green: '#22c55e',
  yellow: '#eab308',
  red: '#ef4444',
  gray: '#6b7280',
};

function createColorIcon(color: string): L.DivIcon {
  const hex = MARKER_COLORS[color] || MARKER_COLORS.gray;
  return L.divIcon({
    className: 'custom-marker',
    html: `<div style="
      width: 28px; height: 28px; border-radius: 50% 50% 50% 0;
      background: ${hex}; transform: rotate(-45deg);
      border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    "><div style="
      width: 10px; height: 10px; border-radius: 50%;
      background: white; position: absolute;
      top: 50%; left: 50%; transform: translate(-50%, -50%);
    "></div></div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 28],
    popupAnchor: [0, -28],
  });
}

function FitBounds({ markers }: { markers: LotMarker[] }) {
  const map = useMap();
  useEffect(() => {
    if (markers.length === 0) return;
    const bounds = L.latLngBounds(markers.map((marker) => [marker.latitude, marker.longitude]));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
  }, [markers, map]);
  return null;
}

export function MapViewPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [markers, setMarkers] = useState<LotMarker[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedLotId, setSelectedLotId] = useState<string | null>(null);

  useEffect(() => {
    api.getMapMarkers()
      .then((res) => {
        if (res.success && res.data) setMarkers(res.data);
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!markers.length) {
      setSelectedLotId(null);
      return;
    }
    if (!selectedLotId || !markers.some((marker) => marker.id === selectedLotId)) {
      setSelectedLotId(markers[0].id);
    }
  }, [markers, selectedLotId]);

  const icons = useMemo(() => ({
    green: createColorIcon('green'),
    yellow: createColorIcon('yellow'),
    red: createColorIcon('red'),
    gray: createColorIcon('gray'),
  }), []);

  const container = staggerSlow;
  const item = fadeUp;
  const isVoid = designTheme === 'void';
  const defaultCenter: [number, number] = [48.1351, 11.5820];
  const selectedLot = markers.find((marker) => marker.id === selectedLotId) ?? null;
  const freeSpots = markers.reduce((sum, marker) => sum + marker.available_slots, 0);
  const totalSpots = markers.reduce((sum, marker) => sum + marker.total_slots, 0);
  const occupancyRate = totalSpots > 0 ? Math.round(((totalSpots - freeSpots) / totalSpots) * 100) : 0;
  const evLots = markers.filter((marker) => /ev|charge|electric/i.test(`${marker.name} ${marker.status}`)).length;

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-10 w-64 skeleton rounded-xl" />
        <div className="h-[500px] skeleton rounded-2xl" />
      </div>
    );
  }

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="space-y-6">
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
              <MapPin weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void occupancy deck' : 'Marble live map board'}
            </div>

            <h1 className="flex items-center gap-3 text-3xl font-black tracking-[-0.04em] text-surface-900 dark:text-white">
              <MapPin weight="fill" className="h-7 w-7 text-primary-500" />
              {t('map.title')}
            </h1>
            <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('map.subtitle')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <MapHeroStat label={t('map.lotsLabel', 'Lots')} value={String(markers.length)} meta={t('map.liveFeed', 'Live feed')} isVoid={isVoid} accent />
              <MapHeroStat label={t('map.freeLabel', 'Free')} value={String(freeSpots)} meta={`${occupancyRate}% ${t('map.occupiedLabel', 'occupied')}`} isVoid={isVoid} />
              <MapHeroStat label={t('map.evLabel', 'EV')} value={String(evLots)} meta={t('map.zonesLabel', { count: markers.length, defaultValue: '{{count}} zones' })} isVoid={isVoid} />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              {t('map.summaryLabel', 'Map summary')}
            </p>
            <h2 className="mt-2 text-xl font-semibold tracking-[-0.03em]">{t('map.operationsTitle', 'Occupancy and booking overview')}</h2>
            <p className={`mt-2 text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
              {t('map.operationsHint', 'Use the live map for zone awareness and the side rail for quick booking context per location.')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <LegendChip label="> 50%" color={MARKER_COLORS.green} />
              <LegendChip label="10-50%" color={MARKER_COLORS.yellow} />
              <LegendChip label="< 10%" color={MARKER_COLORS.red} />
              <LegendChip label={t('map.closed')} color={MARKER_COLORS.gray} />
            </div>
          </div>
        </div>
      </motion.div>

      {markers.length === 0 ? (
        <motion.div variants={item} className="rounded-[24px] border border-surface-200 bg-white p-12 text-center shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
          <NavigationArrow weight="light" className="mx-auto mb-4 h-16 w-16 text-surface-300 dark:text-surface-600" />
          <p className="text-lg text-surface-500 dark:text-surface-400">{t('map.noLots')}</p>
        </motion.div>
      ) : (
        <div className="grid gap-4 xl:grid-cols-[1.4fr_0.8fr]">
          <motion.div variants={item} className="overflow-hidden rounded-[24px] border border-surface-200 bg-white shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80" data-testid="map-container">
            <div className="flex items-center justify-between border-b border-surface-200 px-5 py-4 dark:border-surface-800">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">{t('map.liveView', 'Live view')}</p>
                <h2 className="mt-1 text-lg font-semibold text-surface-900 dark:text-white">{selectedLot?.name ?? t('map.title')}</h2>
              </div>
              <div className="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                LIVE
              </div>
            </div>

            <MapContainer center={defaultCenter} zoom={13} scrollWheelZoom style={{ height: '500px', width: '100%' }}>
              <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              />
              <FitBounds markers={markers} />
              {markers.map((marker) => (
                <Marker
                  key={marker.id}
                  position={[marker.latitude, marker.longitude]}
                  icon={icons[marker.color] || icons.gray}
                  eventHandlers={{ click: () => setSelectedLotId(marker.id) }}
                >
                  <Popup>
                    <div className="min-w-[200px] p-1">
                      <h3 className="mb-1 text-base font-bold">{marker.name}</h3>
                      <p className="mb-2 text-sm text-gray-600">{marker.address}</p>
                      <div className="mb-3 flex items-center justify-between">
                        <span className="text-sm font-medium">
                          {t('map.available')}: {marker.available_slots}/{marker.total_slots}
                        </span>
                        <span className="rounded-full px-2 py-0.5 text-xs font-medium text-white" style={{ backgroundColor: MARKER_COLORS[marker.color] }}>
                          {marker.status}
                        </span>
                      </div>
                      <a href="/book" className="block w-full rounded-lg bg-primary-600 px-4 py-2 text-center text-sm font-medium text-white transition-colors hover:bg-primary-700">
                        {t('map.bookNow')}
                      </a>
                    </div>
                  </Popup>
                </Marker>
              ))}
            </MapContainer>
          </motion.div>

          <motion.div variants={item} className="space-y-4">
            <div className="rounded-[24px] border border-surface-200 bg-white p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">{t('map.occupancyLabel', 'Occupancy')}</p>
              <div className="mt-4 space-y-3">
                {markers.map((marker) => (
                  <button
                    key={marker.id}
                    type="button"
                    onClick={() => setSelectedLotId(marker.id)}
                    className={`w-full rounded-[18px] border px-4 py-3 text-left transition-colors ${
                      selectedLotId === marker.id
                        ? 'border-primary-400 bg-primary-50 dark:border-primary-500/60 dark:bg-primary-500/10'
                        : 'border-surface-200 bg-surface-50/80 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900/60'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-surface-900 dark:text-white">{marker.name}</p>
                        <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">{marker.address}</p>
                      </div>
                      <span className="font-['DM_Mono',monospace] text-sm font-semibold text-primary-700 dark:text-primary-300">{marker.available_slots}/{marker.total_slots}</span>
                    </div>
                  </button>
                ))}
              </div>
            </div>

            {selectedLot ? (
              <div className={`rounded-[24px] border p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] ${
                isVoid
                  ? 'border-slate-800 bg-slate-950/85'
                  : 'border-surface-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] dark:border-surface-800 dark:bg-surface-950/80'
              }`}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>{t('map.selectedLabel', 'Selected lot')}</p>
                    <h2 className={`mt-1 text-xl font-semibold ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{selectedLot.name}</h2>
                  </div>
                  <span className="rounded-full bg-primary-500/10 px-3 py-1 text-xs font-semibold text-primary-700 dark:text-primary-300">
                    {selectedLot.available_slots}/{selectedLot.total_slots}
                  </span>
                </div>

                <div className="mt-4 space-y-3">
                  <DetailRow label={t('map.available', 'Available')} value={`${selectedLot.available_slots}/${selectedLot.total_slots}`} />
                  <DetailRow label={t('map.statusLabel', 'Status')} value={selectedLot.status} />
                  <DetailRow label={t('map.rateLabel', 'Rate')} value="€2.50 / h" />
                  <DetailRow label={t('map.maxDayLabel', 'Max / day')} value="€18.00" />
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                  <InfoPill icon={<Lightning weight="fill" className="h-3.5 w-3.5" />} label={t('map.evCapability', 'EV nearby')} active={/ev|charge|electric/i.test(`${selectedLot.name} ${selectedLot.status}`)} />
                  <InfoPill icon={<Wheelchair weight="fill" className="h-3.5 w-3.5" />} label={t('map.accessibility', 'Accessible route')} active={/accessible|wheelchair|barrier/i.test(`${selectedLot.name} ${selectedLot.status}`)} />
                </div>

                <a href="/book" className="btn btn-primary mt-5 w-full">
                  {t('map.bookNow')}
                </a>
              </div>
            ) : null}
          </motion.div>
        </div>
      )}
    </motion.div>
  );
}

function MapHeroStat({
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

function LegendChip({ label, color }: { label: string; color: string }) {
  return (
    <div className="flex items-center gap-2 rounded-full border border-surface-200 bg-surface-50/80 px-3 py-2 text-xs font-medium text-surface-600 dark:border-surface-800 dark:bg-surface-900/60 dark:text-surface-300">
      <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
      {label}
    </div>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl bg-white/80 px-4 py-3 dark:bg-surface-900/70">
      <span className="text-sm text-surface-500 dark:text-surface-400">{label}</span>
      <span className="font-['DM_Mono',monospace] text-sm font-semibold text-surface-900 dark:text-white">{value}</span>
    </div>
  );
}

function InfoPill({ icon, label, active }: { icon: React.ReactNode; label: string; active: boolean }) {
  return (
    <div className={`flex items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold ${
      active
        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
    }`}>
      {icon}
      {label}
    </div>
  );
}
