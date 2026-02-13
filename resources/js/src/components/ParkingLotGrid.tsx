import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Car, Prohibit, Lock, House } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import type { LotLayout, LotRow, SlotConfig } from '../api/client';

interface ParkingLotGridProps {
  layout: LotLayout;
  selectedSlotId?: string;
  onSlotSelect?: (slot: SlotConfig) => void;
  interactive?: boolean;
  vehiclePhotos?: Record<string, string>;
}

const statusColors: Record<SlotConfig['status'], { bg: string; border: string; text: string }> = {
  available: { bg: 'bg-emerald-100 dark:bg-emerald-900/40', border: 'border-emerald-300 dark:border-emerald-700', text: 'text-emerald-700 dark:text-emerald-300' },
  occupied: { bg: 'bg-red-100 dark:bg-red-900/40', border: 'border-red-300 dark:border-red-700', text: 'text-red-700 dark:text-red-300' },
  reserved: { bg: 'bg-amber-100 dark:bg-amber-900/40', border: 'border-amber-300 dark:border-amber-700', text: 'text-amber-700 dark:text-amber-300' },
  disabled: { bg: 'bg-gray-100 dark:bg-gray-800', border: 'border-gray-300 dark:border-gray-600 border-dashed', text: 'text-gray-400 dark:text-gray-500' },
  blocked: { bg: 'bg-gray-200 dark:bg-gray-700', border: 'border-gray-400 dark:border-gray-500', text: 'text-gray-500 dark:text-gray-400' },
  homeoffice: { bg: 'bg-sky-100 dark:bg-sky-900/30', border: 'border-sky-300 dark:border-sky-700', text: 'text-sky-700 dark:text-sky-300' },
};

function SlotBox({ slot, side, selected, interactive, onSelect, vehiclePhoto }: {
  slot: SlotConfig; side: 'top' | 'bottom'; selected: boolean; interactive: boolean; onSelect?: (slot: SlotConfig) => void; vehiclePhoto?: string;
}) {
  const { t } = useTranslation();
  const [hovered, setHovered] = useState(false);
  const colors = statusColors[slot.status];
  const clickable = interactive && (slot.status === 'available' || slot.status === 'reserved' || slot.status === 'homeoffice');
  const tooltip = slot.status === 'homeoffice' && slot.homeofficeUser ? t('grid.hoFreeFrom', { user: slot.homeofficeUser }) : undefined;

  return (
    <motion.div className="relative flex-shrink-0" whileHover={clickable ? { scale: 1.05 } : {}} whileTap={clickable ? { scale: 0.97 } : {}} onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}>
      <AnimatePresence>
        {hovered && slot.status === 'occupied' && slot.vehiclePlate && (
          <motion.div initial={{ opacity: 0, y: 4 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: 4 }}
            className={`absolute z-20 ${side === 'top' ? 'top-full mt-1' : 'bottom-full mb-1'} left-1/2 -translate-x-1/2 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg flex items-center gap-2`}>
            {vehiclePhoto && <img src={vehiclePhoto} alt="" className="w-8 h-8 rounded-full object-cover" />}
            <span>{t('grid.occupiedBy', { plate: slot.vehiclePlate })}</span>
          </motion.div>
        )}
      </AnimatePresence>
      <button disabled={!clickable} onClick={() => clickable && onSelect?.(slot)} title={tooltip}
        aria-label={t(`gridAria.${slot.status}`, { number: slot.number, plate: slot.vehiclePlate || '' })}
        className={`w-16 h-20 sm:w-20 sm:h-24 md:w-24 md:h-28 rounded-xl border-2 flex flex-col items-center justify-center gap-0.5 transition-all shadow-sm ${colors.bg} ${colors.border} ${colors.text} ${clickable ? 'cursor-pointer hover:shadow-md hover:brightness-105' : 'cursor-default'} ${selected ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900 shadow-lg shadow-primary-500/20' : ''} ${slot.status === 'disabled' ? 'opacity-60' : ''}`}>
        {slot.status === 'occupied' && <><Car weight="fill" className={`w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 ${side === 'top' ? 'rotate-180' : ''}`} /><span className="absolute top-1 right-1 w-3 h-3 opacity-40" aria-hidden="true"><svg viewBox="0 0 12 12"><line x1="0" y1="12" x2="12" y2="0" stroke="currentColor" strokeWidth="2"/><line x1="0" y1="6" x2="6" y2="0" stroke="currentColor" strokeWidth="1.5"/></svg></span></>}
        {slot.status === 'homeoffice' && <House weight="fill" className="w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7" />}
        {slot.status === 'disabled' && <Prohibit weight="bold" className="w-4 h-4 sm:w-5 sm:h-5" />}
        {slot.status === 'blocked' && <Lock weight="fill" className="w-4 h-4 sm:w-5 sm:h-5" />}
        {(slot.status === 'available') && <><Car weight="regular" className={`w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 opacity-30 ${side === 'top' ? 'rotate-180' : ''}`} /><span className="absolute top-1 right-1 w-3 h-3 rounded-full border-2 border-current opacity-40" aria-hidden="true" /></>}
        {slot.status === 'reserved' && <><Car weight="regular" className={`w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 opacity-30 ${side === 'top' ? 'rotate-180' : ''}`} /><span className="absolute top-1 right-1 w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-b-[8px] border-b-current opacity-40" aria-hidden="true" /></>}
        <span className="text-sm sm:text-base md:text-lg font-extrabold leading-tight">{slot.number}</span>
        {slot.status === 'occupied' && slot.vehiclePlate && <span className="text-[8px] sm:text-[9px] md:text-[10px] font-mono opacity-75 leading-none truncate max-w-[3.5rem] sm:max-w-[4.5rem] md:max-w-[5.5rem]">{slot.vehiclePlate}</span>}
        {slot.status === 'homeoffice' && <span className="text-[8px] sm:text-[9px] md:text-[10px] font-semibold opacity-75 leading-none">HO</span>}
      </button>
    </motion.div>
  );
}

function RowSlots({ row, selectedSlotId, interactive, onSlotSelect, vehiclePhotos }: { row: LotRow; selectedSlotId?: string; interactive: boolean; onSlotSelect?: (slot: SlotConfig) => void; vehiclePhotos?: Record<string, string> }) {
  return (
    <div className="flex flex-col gap-1">
      {row.label && <span className="text-[10px] font-medium text-gray-300 dark:text-gray-600 uppercase tracking-widest px-1 select-none">{row.label}</span>}
      <div className="flex gap-1.5 sm:gap-2 md:gap-2.5">{row.slots.map((slot) => <SlotBox key={slot.id} slot={slot} side={row.side} selected={slot.id === selectedSlotId} interactive={interactive} onSelect={onSlotSelect} vehiclePhoto={slot.vehiclePlate ? vehiclePhotos?.[slot.vehiclePlate] : undefined} />)}</div>
    </div>
  );
}

export function ParkingLotGrid({ layout, selectedSlotId, onSlotSelect, interactive = false, vehiclePhotos }: ParkingLotGridProps) {
  const { t } = useTranslation();
  const topRows = layout.rows.filter((r) => r.side === 'top');
  const bottomRows = layout.rows.filter((r) => r.side === 'bottom');

  return (
    <div className="space-y-3">
      {/* Contained scrollable grid - scrolls within its container, not the whole page */}
      <div className="overflow-x-auto overflow-y-hidden -mx-2 px-2 pb-2 touch-pan-x" style={{ WebkitOverflowScrolling: 'touch' }}>
        <div className="inline-flex flex-col gap-0 min-w-fit">
          {topRows.map((row) => <RowSlots key={row.id} row={row} selectedSlotId={selectedSlotId} interactive={interactive} onSlotSelect={onSlotSelect} vehiclePhotos={vehiclePhotos} />)}
          <div className="my-2 rounded-md bg-gray-200/60 dark:bg-gray-800 py-2 px-4 flex items-center gap-3">
            <div className="flex-1 border-t border-dashed border-gray-300 dark:border-gray-700" />
            <span className="text-[10px] font-medium text-gray-400 dark:text-gray-600 uppercase tracking-[0.2em] select-none whitespace-nowrap">{layout.roadLabel || t('grid.road')}</span>
            <div className="flex-1 border-t border-dashed border-gray-300 dark:border-gray-700" />
          </div>
          {bottomRows.map((row) => <RowSlots key={row.id} row={row} selectedSlotId={selectedSlotId} interactive={interactive} onSlotSelect={onSlotSelect} vehiclePhotos={vehiclePhotos} />)}
        </div>
      </div>
      <div className="flex flex-wrap gap-3 sm:gap-4 text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800" role="list" aria-label="Parking slot status legend">
        {[
          { status: 'available' as const, label: t('grid.free') },
          { status: 'occupied' as const, label: t('grid.occupied') },
          { status: 'reserved' as const, label: t('grid.reserved') },
          { status: 'disabled' as const, label: t('grid.disabled') },
          { status: 'homeoffice' as const, label: t('grid.homeoffice') },
        ].map(({ status, label }) => (
          <div key={status} className="flex items-center gap-1.5"><div className={`w-3 h-3 rounded-sm border ${statusColors[status].bg} ${statusColors[status].border}`} /><span>{label}</span></div>
        ))}
      </div>
    </div>
  );
}
