import React, { useEffect, useState } from 'react';
import { api } from '../api/client';
import { t } from '../i18n';
import { MapPin, Check, Clock, Car } from '@phosphor-icons/react';

export default function Book() {
  const [lots, setLots] = useState<any[]>([]);
  const [selectedLot, setSelectedLot] = useState<any>(null);
  const [slots, setSlots] = useState<any[]>([]);
  const [selectedSlot, setSelectedSlot] = useState<any>(null);
  const [duration, setDuration] = useState('480');
  const [plate, setPlate] = useState('');
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');
  const [step, setStep] = useState(1);

  useEffect(() => { api.get<any[]>('/lots').then(setLots).catch(() => {}); }, []);

  const loadSlots = async (lot: any) => {
    setSelectedLot(lot);
    setStep(2);
    const s = await api.get<any[]>(`/lots/${lot.id}/slots`);
    setSlots(s);
  };

  const handleBook = async () => {
    setError('');
    try {
      const start = new Date();
      const end = new Date(start.getTime() + parseInt(duration) * 60000);
      await api.post('/bookings', {
        lot_id: selectedLot.id,
        slot_id: selectedSlot.id,
        start_time: start.toISOString(),
        end_time: end.toISOString(),
        license_plate: plate || undefined,
      });
      setSuccess(true);
      setStep(4);
    } catch (err: any) {
      setError(err.message);
    }
  };

  if (success) {
    return (
      <div className="max-w-lg mx-auto text-center py-12">
        <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
          <Check size={32} className="text-green-600" />
        </div>
        <h2 className="text-2xl font-bold mb-2">{t('bookingSuccess.title')}</h2>
        <p className="text-slate-500 mb-4">{t('bookingSuccess.subtitle')}</p>
        <div className="card text-left mb-6">
          <p><span className="text-slate-500">{t('bookingSuccess.parkingLot')}:</span> {selectedLot?.name}</p>
          <p><span className="text-slate-500">{t('bookingSuccess.slot')}:</span> {selectedSlot?.slot_number}</p>
          {plate && <p><span className="text-slate-500">{t('bookingSuccess.plate')}:</span> {plate}</p>}
        </div>
        <button onClick={() => { setSuccess(false); setStep(1); setSelectedLot(null); setSelectedSlot(null); }} className="btn-primary">{t('bookingSuccess.newBooking')}</button>
      </div>
    );
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-1">{t('book.title')}</h1>
      <p className="text-slate-500 mb-6">{t('book.subtitle')}</p>

      {/* Step indicators */}
      <div className="flex items-center gap-2 mb-6">
        {[1,2,3].map(s => (
          <div key={s} className={`flex items-center gap-2 ${s <= step ? 'text-amber-600' : 'text-slate-400'}`}>
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${s <= step ? 'bg-amber-600 text-white' : 'bg-slate-200 dark:bg-slate-700'}`}>{s}</div>
            <span className="text-sm font-medium hidden sm:inline">{s === 1 ? t('book.step1') : s === 2 ? t('book.step2') : t('book.step3')}</span>
            {s < 3 && <div className="w-8 h-px bg-slate-300 dark:bg-slate-600" />}
          </div>
        ))}
      </div>

      {error && <div className="bg-red-50 dark:bg-red-900/30 text-red-600 p-3 rounded-lg mb-4">{error}</div>}

      {/* Step 1: Choose lot */}
      {step === 1 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {lots.map(lot => (
            <button key={lot.id} onClick={() => loadSlots(lot)} className="card text-left hover:ring-2 hover:ring-amber-500 transition-all">
              <h3 className="font-semibold">{lot.name}</h3>
              <p className="text-sm text-slate-500">{lot.address}</p>
              <p className="text-sm mt-2 text-green-600 font-medium">{lot.available_slots} {t('common.available')}</p>
            </button>
          ))}
          {lots.length === 0 && <p className="text-slate-500 col-span-2">No parking lots available. Ask your admin to create one.</p>}
        </div>
      )}

      {/* Step 2: Choose slot */}
      {step === 2 && (
        <div>
          <button onClick={() => setStep(1)} className="text-amber-600 text-sm mb-4">← {t('common.back')}</button>
          <h3 className="font-semibold mb-4">{selectedLot?.name} — {t('book.step2')}</h3>
          <div className="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-8 gap-3">
            {slots.map(slot => {
              const isAvailable = !slot.current_booking && slot.status === 'available';
              const isSelected = selectedSlot?.id === slot.id;
              return (
                <button key={slot.id} disabled={!isAvailable}
                  onClick={() => { setSelectedSlot(slot); setStep(3); }}
                  className={`p-3 rounded-lg text-center font-medium text-sm transition-all ${
                    isSelected ? 'bg-amber-600 text-white ring-2 ring-amber-400' :
                    isAvailable ? 'bg-green-100 dark:bg-green-900/30 text-green-700 hover:ring-2 hover:ring-green-400' :
                    'bg-red-100 dark:bg-red-900/30 text-red-400 cursor-not-allowed'
                  }`}>
                  {slot.slot_number}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* Step 3: Duration & confirm */}
      {step === 3 && (
        <div className="max-w-md">
          <button onClick={() => setStep(2)} className="text-amber-600 text-sm mb-4">← {t('common.back')}</button>
          <div className="card">
            <p className="text-sm text-slate-500 mb-4">{selectedLot?.name} — Slot <strong>{selectedSlot?.slot_number}</strong></p>
            <label className="block text-sm font-medium mb-2"><Clock size={16} className="inline mr-1" />{t('book.duration')}</label>
            <div className="grid grid-cols-3 gap-2 mb-4">
              {[{v:'30',l:t('book.min30')},{v:'60',l:t('book.hour1')},{v:'120',l:t('book.hour2')},{v:'240',l:t('book.hour4')},{v:'480',l:t('book.hour8')},{v:'720',l:t('book.hour12')}].map(d => (
                <button key={d.v} onClick={() => setDuration(d.v)}
                  className={`p-2 rounded-lg text-sm font-medium ${duration === d.v ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-700'}`}>{d.l}</button>
              ))}
            </div>
            <label className="block text-sm font-medium mb-2"><Car size={16} className="inline mr-1" />{t('book.licensePlate')}</label>
            <input type="text" value={plate} onChange={e => setPlate(e.target.value)} className="input mb-4" placeholder={t('book.enterPlate')} />
            <button onClick={handleBook} className="btn-primary w-full">{t('book.bookNow')}</button>
          </div>
        </div>
      )}
    </div>
  );
}
