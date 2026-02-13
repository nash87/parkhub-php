import { useEffect, useState, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Car, Plus, Trash, SpinnerGap, Star, X, CheckCircle, Camera, PencilSimple } from '@phosphor-icons/react';
import { api, Vehicle, CreateVehicleData, generateCarPhotoSvg } from '../api/client';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { LicensePlateInput } from '../components/LicensePlateInput';

// ═══════════════════════════════════════════════════════════════════════════════
// CAR MAKES & MODELS DATA
// ═══════════════════════════════════════════════════════════════════════════════

const CAR_MAKES = [
  'BMW', 'Mercedes-Benz', 'Volkswagen', 'Audi', 'Porsche', 'Opel', 'Ford', 'Toyota',
  'Honda', 'Hyundai', 'Kia', 'Nissan', 'Mazda', 'Skoda', 'Seat', 'Renault', 'Peugeot',
  'Citroën', 'Fiat', 'Volvo', 'Tesla', 'Mini', 'Suzuki', 'Mitsubishi', 'Subaru',
  'Lexus', 'Jeep', 'Land Rover', 'Jaguar', 'Alfa Romeo', 'Dacia', 'Cupra', 'MG',
  'BYD', 'Polestar', 'smart', 'Chevrolet', 'Dodge', 'Chrysler', 'Cadillac', 'Genesis',
  'Infiniti', 'Maserati', 'Bentley', 'Rolls-Royce', 'Ferrari', 'Lamborghini',
  'Aston Martin', 'McLaren', 'Bugatti',
];

const CAR_MODELS: Record<string, string[]> = {
  'BMW': ['1er', '2er', '3er', '4er', '5er', '7er', 'X1', 'X3', 'X5', 'iX'],
  'Mercedes-Benz': ['A-Klasse', 'B-Klasse', 'C-Klasse', 'E-Klasse', 'S-Klasse', 'GLA', 'GLC', 'GLE', 'EQA', 'EQC'],
  'Volkswagen': ['Golf', 'Polo', 'Passat', 'Tiguan', 'T-Roc', 'ID.3', 'ID.4', 'Touran', 'Up!', 'Arteon'],
  'Audi': ['A1', 'A3', 'A4', 'A5', 'A6', 'Q2', 'Q3', 'Q5', 'Q7', 'e-tron'],
  'Porsche': ['911', 'Cayenne', 'Macan', 'Taycan', 'Panamera', 'Boxster', 'Cayman'],
  'Opel': ['Corsa', 'Astra', 'Mokka', 'Grandland', 'Crossland', 'Insignia'],
  'Ford': ['Focus', 'Fiesta', 'Kuga', 'Puma', 'Mustang', 'Explorer', 'Ranger'],
  'Toyota': ['Corolla', 'Yaris', 'RAV4', 'Camry', 'C-HR', 'Aygo', 'Supra'],
  'Honda': ['Civic', 'Jazz', 'CR-V', 'HR-V', 'e:Ny1', 'ZR-V'],
  'Hyundai': ['i10', 'i20', 'i30', 'Tucson', 'Kona', 'Ioniq 5', 'Santa Fe'],
  'Kia': ['Picanto', 'Rio', 'Ceed', 'Sportage', 'Niro', 'EV6', 'Sorento'],
  'Nissan': ['Micra', 'Qashqai', 'Juke', 'X-Trail', 'Leaf', 'Ariya'],
  'Mazda': ['Mazda2', 'Mazda3', 'CX-3', 'CX-5', 'CX-30', 'MX-5', 'CX-60'],
  'Skoda': ['Fabia', 'Octavia', 'Superb', 'Karoq', 'Kodiaq', 'Enyaq', 'Kamiq'],
  'Seat': ['Ibiza', 'Leon', 'Arona', 'Ateca', 'Tarraco'],
  'Renault': ['Clio', 'Megane', 'Captur', 'Kadjar', 'Zoe', 'Arkana', 'Austral'],
  'Peugeot': ['208', '308', '3008', '5008', '2008', 'e-208'],
  'Citroën': ['C3', 'C4', 'C5 Aircross', 'Berlingo', 'C3 Aircross'],
  'Fiat': ['500', 'Panda', 'Tipo', '500X', '500L', '600'],
  'Volvo': ['XC40', 'XC60', 'XC90', 'V60', 'S60', 'C40', 'EX30'],
  'Tesla': ['Model 3', 'Model Y', 'Model S', 'Model X'],
  'Mini': ['Cooper', 'Countryman', 'Clubman', 'Cabrio'],
  'Dacia': ['Sandero', 'Duster', 'Jogger', 'Spring'],
  'Cupra': ['Born', 'Formentor', 'Leon', 'Ateca', 'Tavascan'],
};

// ═══════════════════════════════════════════════════════════════════════════════
// COLOR DATA
// ═══════════════════════════════════════════════════════════════════════════════

const COLOR_OPTIONS = [
  { value: 'Schwarz', hex: '#1f2937' },
  { value: 'Weiß', hex: '#f3f4f6' },
  { value: 'Silber', hex: '#9ca3af' },
  { value: 'Grau', hex: '#6b7280' },
  { value: 'Blau', hex: '#2563eb' },
  { value: 'Rot', hex: '#dc2626' },
  { value: 'Grün', hex: '#16a34a' },
  { value: 'Gelb', hex: '#eab308' },
  { value: 'Orange', hex: '#ea580c' },
  { value: 'Braun', hex: '#92400e' },
  { value: 'Beige', hex: '#d4b896' },
];

const colorMap: Record<string, string> = {
  'Schwarz': 'bg-gray-900', 'Weiß': 'bg-white border border-gray-300', 'Silber': 'bg-gray-400',
  'Grau': 'bg-gray-500', 'Blau': 'bg-blue-600', 'Rot': 'bg-red-600', 'Grün': 'bg-green-600',
  'Gelb': 'bg-yellow-400', 'Orange': 'bg-orange-500', 'Braun': 'bg-amber-800', 'Beige': 'bg-amber-200',
};

const colorI18nMap: Record<string, string> = {
  'Schwarz': 'colors.black', 'Weiß': 'colors.white', 'Silber': 'colors.silver',
  'Grau': 'colors.gray', 'Blau': 'colors.blue', 'Rot': 'colors.red',
  'Grün': 'colors.green', 'Gelb': 'colors.yellow', 'Orange': 'colors.orange',
  'Braun': 'colors.brown', 'Beige': 'colors.beige',
};

// ═══════════════════════════════════════════════════════════════════════════════
// AUTOCOMPLETE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════════

function Autocomplete({ value, onChange, options, placeholder, label }: {
  value: string;
  onChange: (val: string) => void;
  options: string[];
  placeholder?: string;
  label?: string;
}) {
  const [open, setOpen] = useState(false);
  const [filter, setFilter] = useState('');
  const ref = useRef<HTMLDivElement>(null);

  const filtered = options.filter(o =>
    o.toLowerCase().includes((filter || value).toLowerCase())
  ).slice(0, 15);

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  return (
    <div ref={ref} className="relative">
      {label && <label className="label">{label}</label>}
      <input
        type="text"
        value={value}
        onChange={(e) => {
          onChange(e.target.value);
          setFilter(e.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        placeholder={placeholder}
        className="input"
        autoComplete="off"
      />
      <AnimatePresence>
        {open && filtered.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: -4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -4 }}
            transition={{ duration: 0.15 }}
            className="absolute z-50 left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg"
          >
            {filtered.map(option => (
              <button
                key={option}
                type="button"
                onClick={() => { onChange(option); setFilter(''); setOpen(false); }}
                className="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors first:rounded-t-xl last:rounded-b-xl"
              >
                {option}
              </button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// PHOTO UPLOAD COMPONENT
// ═══════════════════════════════════════════════════════════════════════════════

function resizeImage(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        const maxW = 800;
        let w = img.width, h = img.height;
        if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d')!;
        ctx.drawImage(img, 0, 0, w, h);
        resolve(canvas.toDataURL('image/jpeg', 0.7));
      };
      img.onerror = reject;
      img.src = e.target?.result as string;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function PhotoUpload({ photoUrl, color, onPhotoChange, t }: { photoUrl?: string; color?: string; onPhotoChange: (url: string) => void; t: ReturnType<typeof useTranslation>["t"] }) {
  const [dragOver, setDragOver] = useState(false);
  const placeholder = color ? generateCarPhotoSvg(color) : undefined;
  const displayUrl = photoUrl || placeholder;

  async function handleFile(file: File) {
    if (!file.type.startsWith('image/')) return;
    try {
      const resized = await resizeImage(file);
      onPhotoChange(resized);
    } catch {
      onPhotoChange(URL.createObjectURL(file));
    }
  }

  return (
    <div className="flex flex-col items-center gap-3">
      <div onDragOver={(e) => { e.preventDefault(); setDragOver(true); }} onDragLeave={() => setDragOver(false)}
        onDrop={(e) => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); }}
        onClick={() => { const input = document.createElement('input'); input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp,image/heic'; input.capture = 'environment'; input.onchange = () => { if (input.files?.[0]) handleFile(input.files[0]); }; input.click(); }}
        className={`relative w-[120px] h-[120px] rounded-2xl overflow-hidden cursor-pointer border-2 border-dashed transition-all flex items-center justify-center ${dragOver ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 scale-105' : 'border-gray-300 dark:border-gray-600 hover:border-primary-400'}`}>
        {displayUrl ? <img src={displayUrl} alt="" className="w-full h-full object-cover" /> : (
          <div className="flex flex-col items-center gap-1 text-gray-400"><Camera weight="regular" className="w-8 h-8" /><span className="text-[10px] text-center leading-tight">{t('vehicles.uploadPhoto')}</span></div>
        )}
        {displayUrl && <div className="absolute inset-0 bg-black/0 hover:bg-black/40 transition-colors flex items-center justify-center group"><Camera weight="fill" className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" /></div>}
      </div>
      <span className="text-xs text-gray-400">{t('vehicles.uploadPhotoOrCamera')}</span>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// COLOR PICKER COMPONENT
// ═══════════════════════════════════════════════════════════════════════════════

function ColorPicker({ value, onChange, t }: { value: string; onChange: (v: string) => void; t: ReturnType<typeof useTranslation>["t"] }) {
  return (
    <div>
      <label className="label">{t('vehicles.color')}</label>
      <div className="flex flex-wrap gap-2 mt-1">
        {COLOR_OPTIONS.map(c => {
          const selected = value === c.value;
          const i18nKey = colorI18nMap[c.value];
          return (
            <button
              key={c.value}
              type="button"
              onClick={() => onChange(selected ? '' : c.value)}
              title={i18nKey ? t(i18nKey) : c.value}
              className={`w-9 h-9 rounded-full border-2 transition-all flex items-center justify-center ${
                selected
                  ? 'border-primary-500 ring-2 ring-primary-300 scale-110'
                  : 'border-gray-200 dark:border-gray-600 hover:scale-105'
              }`}
              style={{ backgroundColor: c.hex }}
            >
              {selected && <CheckCircle weight="fill" className="w-4 h-4 text-white drop-shadow" />}
            </button>
          );
        })}
      </div>
      {value && (
        <span className="text-xs text-gray-500 mt-1 block">
          {colorI18nMap[value] ? t(colorI18nMap[value]) : value}
        </span>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADD VEHICLE MODAL
// ═══════════════════════════════════════════════════════════════════════════════

function AddVehicleModal({ open, onClose, onSave }: { open: boolean; onClose: () => void; onSave: (v: Vehicle) => void }) {
  const { t } = useTranslation();
  const [formData, setFormData] = useState({ plate: '', make: '', model: '', color: '', is_default: false });
  const [photoDataUrl, setPhotoDataUrl] = useState<string | undefined>();
  const [saving, setSaving] = useState(false);

  const modelOptions = CAR_MODELS[formData.make] || [];

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!formData.plate.trim()) return;
    setSaving(true);
    try {
      const createData: CreateVehicleData = {
        plate: formData.plate.toUpperCase(),
        make: formData.make || undefined,
        model: formData.model || undefined,
        color: formData.color || undefined,
        is_default: formData.is_default,
      };
      if (photoDataUrl) {
        createData.photo = photoDataUrl;
      }
      const res = await api.createVehicle(createData);
      if (res.success && res.data) {
        onSave(res.data);
      } else {
        // Fallback: local vehicle
        const newVehicle: Vehicle = {
          id: 'v-' + Date.now(), user_id: 'demo-1', plate: formData.plate.toUpperCase(),
          make: formData.make || undefined, model: formData.model || undefined, color: formData.color || undefined,
          photo_url: photoDataUrl || (formData.color ? generateCarPhotoSvg(formData.color) : undefined), is_default: formData.is_default,
        };
        onSave(newVehicle);
      }
      setFormData({ plate: '', make: '', model: '', color: '', is_default: false });
      setPhotoDataUrl(undefined);
    } finally {
      setSaving(false);
    }
  }

  return (
    <AnimatePresence>
      {open && (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
          <motion.div initial={{ opacity: 0, scale: 0.95, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 20 }} transition={{ type: 'spring', damping: 25, stiffness: 300 }} className="relative w-full max-w-lg card p-0 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900 z-10">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Car weight="fill" className="w-5 h-5 text-primary-600" />{t('vehicles.newVehicle')}</h2>
              <button onClick={onClose} className="btn btn-ghost btn-icon"><X weight="bold" className="w-5 h-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-5">
              <PhotoUpload photoUrl={photoDataUrl} color={formData.color} onPhotoChange={setPhotoDataUrl} t={t} />

              <div>
                <label className="label">{t('vehicles.plate')} *</label>
                <LicensePlateInput value={formData.plate} onChange={(plate) => setFormData({ ...formData, plate })} required autoFocus />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <Autocomplete
                  value={formData.make}
                  onChange={(make) => setFormData({ ...formData, make, model: '' })}
                  options={CAR_MAKES}
                  placeholder={t('vehicles.makePlaceholder')}
                  label={t('vehicles.make')}
                />
                <Autocomplete
                  value={formData.model}
                  onChange={(model) => setFormData({ ...formData, model })}
                  options={modelOptions}
                  placeholder={t('vehicles.modelPlaceholder')}
                  label={t('vehicles.model')}
                />
              </div>

              <ColorPicker value={formData.color} onChange={(color) => setFormData({ ...formData, color })} t={t} />

              <label className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <input type="checkbox" checked={formData.is_default} onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })} className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                <div><span className="text-sm font-medium text-gray-900 dark:text-white">{t('vehicles.defaultVehicle')}</span><p className="text-xs text-gray-500 dark:text-gray-400">{t('vehicles.defaultVehicleDesc')}</p></div>
              </label>

              <div className="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                <button type="button" onClick={onClose} className="btn btn-secondary">{t('common.cancel')}</button>
                <button type="submit" disabled={saving || !formData.plate.trim()} className="btn btn-primary">
                  {saving ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" /> : <><CheckCircle weight="bold" className="w-4 h-4" />{t('common.save')}</>}
                </button>
              </div>
            </form>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

// ═══════════════════════════════════════════════════════════════════════════════
// VEHICLES PAGE
// ═══════════════════════════════════════════════════════════════════════════════

export function VehiclesPage() {
  const { t } = useTranslation();
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [confirmDeleteId, setConfirmDeleteId] = useState<string | null>(null);

  useEffect(() => { loadVehicles(); }, []);

  async function loadVehicles() { try { const res = await api.getVehicles(); if (res.success && res.data) setVehicles(res.data); } finally { setLoading(false); } }

  function handleAddVehicle(vehicle: Vehicle) {
    if (vehicle.is_default) setVehicles(prev => [...prev.map(v => ({ ...v, is_default: false })), vehicle]);
    else setVehicles(prev => [...prev, vehicle]);
    setShowModal(false);
    toast.success(t('vehicles.added'));
  }

  async function handleDelete(id: string) {
    await api.deleteVehicle(id);
    setVehicles(vehicles.filter(v => v.id !== id));
    toast.success(t('vehicles.removed'));
  }

  if (loading) return <div className="flex items-center justify-center h-64"><SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" /></div>;

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div><h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('vehicles.title')}</h1><p className="text-gray-500 dark:text-gray-400 mt-1">{t('vehicles.subtitle')}</p></div>
        <button onClick={() => setShowModal(true)} className="btn btn-primary"><Plus weight="bold" className="w-4 h-4" />{t('vehicles.add')}</button>
      </div>

      <AddVehicleModal open={showModal} onClose={() => setShowModal(false)} onSave={handleAddVehicle} />

      {vehicles.length === 0 ? (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="card p-16 text-center">
          <Car weight="light" className="w-24 h-24 text-gray-200 dark:text-gray-700 mx-auto mb-4" />
          <p className="text-gray-500 dark:text-gray-400 mb-2 text-lg">{t('vehicles.noVehicles')}</p>
          <p className="text-sm text-gray-400 dark:text-gray-500 mb-6">{t('vehicles.noVehiclesDesc')}</p>
          <button onClick={() => setShowModal(true)} className="btn btn-primary"><Plus weight="bold" className="w-4 h-4" />{t('vehicles.addVehicle')}</button>
        </motion.div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {vehicles.map((vehicle, index) => {
            const colorClass = vehicle.color ? (colorMap[vehicle.color] || 'bg-gray-400') : 'bg-gray-400';
            return (
              <motion.div key={vehicle.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: index * 0.05 }} className="card p-6 shadow-md dark:shadow-gray-900/50 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-4">
                    <div className="relative group">
                      {vehicle.photo_url ? <img src={vehicle.photo_url} alt={vehicle.plate} className="w-20 h-20 rounded-2xl object-cover" /> : (
                        <div className={`w-20 h-20 rounded-2xl flex items-center justify-center ${colorClass || 'bg-gray-100 dark:bg-gray-800'}`}><Car weight="fill" className="w-10 h-10 text-white/40" /></div>
                      )}
                      <button onClick={(e) => { e.stopPropagation(); const input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*'; input.onchange = async () => { if (input.files?.[0]) { try { const resized = await resizeImage(input.files[0]); const res = await api.uploadVehiclePhoto(vehicle.id, input.files[0]); if (res.success && res.data) { setVehicles(prev => prev.map(v => v.id === vehicle.id ? { ...v, photo_url: res.data!.url } : v)); } else { setVehicles(prev => prev.map(v => v.id === vehicle.id ? { ...v, photo_url: resized } : v)); } } catch { setVehicles(prev => prev.map(v => v.id === vehicle.id ? { ...v, photo_url: URL.createObjectURL(input.files![0]) } : v)); } } }; input.click(); }}
                        className="absolute inset-0 rounded-2xl bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center">
                        <PencilSimple weight="bold" className="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                      </button>
                      <div className={`absolute -bottom-1 -right-1 w-5 h-5 rounded-full ${colorClass} ring-2 ring-white dark:ring-gray-900`} />
                    </div>
                    <div>
                      <p className="text-xl font-bold text-gray-900 dark:text-white font-mono tracking-wider">{vehicle.plate}</p>
                      {(vehicle.make || vehicle.model) && <p className="text-sm text-gray-600 dark:text-gray-400 font-medium">{vehicle.make} {vehicle.model}</p>}
                      {vehicle.color && <div className="flex items-center gap-1.5 mt-1"><div className={`w-2.5 h-2.5 rounded-full ${colorClass}`} /><span className="text-xs text-gray-500 dark:text-gray-500">{vehicle.color}</span></div>}
                    </div>
                  </div>
                  <button onClick={() => setConfirmDeleteId(vehicle.id)} className="btn btn-ghost btn-icon text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"><Trash weight="regular" className="w-5 h-5" /></button>
                </div>
                {vehicle.is_default && <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800"><span className="badge badge-info"><Star weight="fill" className="w-3 h-3" />{t('vehicles.isDefault')}</span></div>}
              </motion.div>
            );
          })}
        </div>
      )}

      <ConfirmDialog open={!!confirmDeleteId} title={t('confirm.deleteVehicleTitle')} message={t('confirm.deleteVehicleMessage')} confirmLabel={t('confirm.deleteVehicleConfirm')} variant="danger"
        onConfirm={() => { if (confirmDeleteId) handleDelete(confirmDeleteId); setConfirmDeleteId(null); }} onCancel={() => setConfirmDeleteId(null)} />
    </div>
  );
}
