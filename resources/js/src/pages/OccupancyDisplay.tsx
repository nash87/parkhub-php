import { useEffect, useState } from 'react';
import { Buildings, CheckCircle, Warning } from '@phosphor-icons/react';

interface OccupancyLot {
  id: string;
  name: string;
  address: string;
  total_slots: number;
  available_slots: number;
}

export function OccupancyDisplayPage() {
  const [lots, setLots] = useState<OccupancyLot[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadOccupancy();
    const interval = setInterval(loadOccupancy, 30000);
    return () => clearInterval(interval);
  }, []);

  async function loadOccupancy() {
    try {
      const res = await fetch(`(import.meta.env.VITE_API_URL || "")/api/v1/public/occupancy`);
      const data = await res.json();
      if (data.success && data.data) setLots(data.data);
    } catch { /* ignore */ }
    setLoading(false);
  }

  const now = new Date();
  const timeStr = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  const dateStr = now.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  return (
    <div className="min-h-screen bg-gray-950 text-white p-8">
      <div className="max-w-4xl mx-auto">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold mb-2">🅿️ Parking Availability</h1>
          <p className="text-gray-400 text-lg">{dateStr} · {timeStr}</p>
        </div>

        {loading ? (
          <div className="text-center text-gray-500">Loading...</div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {lots.map(lot => {
              const occupancy = lot.total_slots > 0 ? ((lot.total_slots - lot.available_slots) / lot.total_slots) * 100 : 0;
              const isFull = lot.available_slots === 0;
              return (
                <div key={lot.id} className={`rounded-2xl p-6 border ${isFull ? 'border-red-500/50 bg-red-950/30' : 'border-emerald-500/30 bg-gray-900'}`}>
                  <div className="flex items-start justify-between mb-4">
                    <div className="flex items-center gap-3">
                      <Buildings weight="fill" className="w-8 h-8 text-gray-400" />
                      <div>
                        <h2 className="text-xl font-bold">{lot.name}</h2>
                        <p className="text-gray-500 text-sm">{lot.address}</p>
                      </div>
                    </div>
                    {isFull ? (
                      <Warning weight="fill" className="w-8 h-8 text-red-500" />
                    ) : (
                      <CheckCircle weight="fill" className="w-8 h-8 text-emerald-500" />
                    )}
                  </div>
                  <div className="flex items-end justify-between">
                    <div>
                      <span className={`text-5xl font-bold ${isFull ? 'text-red-400' : 'text-emerald-400'}`}>{lot.available_slots}</span>
                      <span className="text-gray-500 text-xl ml-2">/ {lot.total_slots}</span>
                    </div>
                    <span className={`text-sm font-medium px-3 py-1 rounded-full ${isFull ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-400'}`}>
                      {isFull ? 'FULL' : `${Math.round(occupancy)}% occupied`}
                    </span>
                  </div>
                  <div className="mt-4 h-2 bg-gray-800 rounded-full overflow-hidden">
                    <div className={`h-full rounded-full transition-all ${isFull ? 'bg-red-500' : occupancy > 80 ? 'bg-amber-500' : 'bg-emerald-500'}`} style={{ width: `${occupancy}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        )}

        <p className="text-center text-gray-600 text-sm mt-12">Auto-refreshes every 30 seconds</p>
      </div>
    </div>
  );
}
