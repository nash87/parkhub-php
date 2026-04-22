import { useEffect, useState, useCallback, type ReactNode } from 'react';
import { motion } from 'framer-motion';
import { Ticket, QrCode, Clock, MapPin, Question, CalendarBlank } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
import { useTheme } from '../context/ThemeContext';

interface ParkingPass {
  id: string;
  booking_id: string;
  user_id: string;
  user_name: string;
  lot_name: string;
  slot_number: string;
  valid_from: string;
  valid_until: string;
  verification_code: string;
  qr_data: string;
  status: 'active' | 'expired' | 'revoked' | 'used';
  created_at: string;
}

const statusColors: Record<string, string> = {
  active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  expired: 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
  revoked: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  used: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

export function ParkingPassPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [passes, setPasses] = useState<ParkingPass[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedPass, setSelectedPass] = useState<ParkingPass | null>(null);
  const [showHelp, setShowHelp] = useState(false);
  const isVoid = designTheme === 'void';

  const loadPasses = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/v1/me/passes').then(r => r.json());
      if (res.success) setPasses(res.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  }, []);

  useEffect(() => { loadPasses(); }, [loadPasses]);

  return (
    <div
      className="space-y-6 p-4 max-w-5xl mx-auto"
      data-testid="parking-pass-shell"
      data-surface={isVoid ? 'void' : 'marble'}
    >
      <section className={`overflow-hidden rounded-[30px] border px-6 py-6 shadow-[0_24px_70px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.14),_transparent_34%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.22),_transparent_36%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <div className="space-y-5">
            <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'border-cyan-400/30 bg-cyan-500/10 text-cyan-100'
                : 'border-emerald-200/80 bg-white/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <Ticket size={14} />
              {isVoid ? 'Void pass wallet' : 'Marble pass desk'}
            </div>

            <div className="flex items-start justify-between gap-4">
              <div>
                <h1 className="text-3xl font-black tracking-[-0.04em]">{t('parkingPass.title')}</h1>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  {t('parkingPass.subtitle')}
                </p>
              </div>
              <button
                onClick={() => setShowHelp(!showHelp)}
                className={`inline-flex h-11 w-11 items-center justify-center rounded-2xl border transition ${
                  isVoid
                    ? 'border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08]'
                    : 'border-white/80 bg-white/85 text-surface-700 hover:bg-white dark:border-white/10 dark:bg-white/[0.04] dark:text-white'
                }`}
                aria-label={t('parkingPass.helpLabel')}
              >
                <Question size={20} />
              </button>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <PassStatCard label={t('parkingPass.activeLabel', 'Active')} value={String(passes.filter((pass) => pass.status === 'active').length)} meta={t('parkingPass.digitalPass')} isVoid={isVoid} accent />
              <PassStatCard label={t('parkingPass.totalLabel', 'Total passes')} value={String(passes.length)} meta={selectedPass ? selectedPass.lot_name : `${passes.length} ready`} isVoid={isVoid} />
              <PassStatCard label={t('parkingPass.selectedLabel', 'Selected')} value={selectedPass ? selectedPass.slot_number : '—'} meta={selectedPass ? selectedPass.user_name : t('parkingPass.empty')} isVoid={isVoid} />
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              {t('parkingPass.opsLabel', 'Pass readiness')}
            </p>
            <div className="mt-4 space-y-3">
              <PassDigestLine
                icon={<QrCode size={16} />}
                title={t('parkingPass.digitalPass')}
                body={selectedPass ? selectedPass.lot_name : t('parkingPass.empty')}
                meta={selectedPass ? `${t('parkingPass.slot')} ${selectedPass.slot_number}` : t('parkingPass.subtitle')}
                isVoid={isVoid}
              />
              <PassDigestLine
                icon={<Clock size={16} />}
                title={t('parkingPass.validUntil')}
                body={selectedPass ? formatDate(selectedPass.valid_until) : '—'}
                meta={selectedPass ? selectedPass.verification_code : `${t('parkingPass.slot')} —`}
                isVoid={isVoid}
              />
            </div>
          </div>
        </div>
      </section>

      {/* Help tooltip */}
      {showHelp && (
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className={`rounded-[24px] border p-4 ${
            isVoid
              ? 'border-cyan-500/20 bg-cyan-500/10'
              : 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20'
          }`}
        >
          <p className={`text-sm ${isVoid ? 'text-cyan-100' : 'text-blue-700 dark:text-blue-300'}`}>
            {t('parkingPass.help')}
          </p>
        </motion.div>
      )}

      {loading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-2 border-primary-500 border-t-transparent" />
        </div>
      ) : selectedPass ? (
        /* Full-screen pass display */
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          className={`rounded-[28px] border p-6 shadow-xl max-w-sm mx-auto ${
            isVoid
              ? 'border-cyan-500/20 bg-[linear-gradient(180deg,rgba(34,211,238,0.14),rgba(15,23,42,0.95))] text-white'
              : 'border-emerald-200 bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(15,118,110,0.95))] text-white dark:border-emerald-900/60'
          }`}
        >
          <div className="text-center space-y-4">
            <div className="flex items-center justify-center gap-2 text-primary-100">
              <Ticket size={20} />
              <span className="text-sm font-medium uppercase tracking-wide">
                {t('parkingPass.digitalPass')}
              </span>
            </div>

            <h2 className="text-xl font-bold">{selectedPass.user_name}</h2>

            {/* QR Code */}
            {selectedPass.qr_data && (
              <div className="bg-white rounded-xl p-4 inline-block mx-auto">
                <img
                  src={selectedPass.qr_data}
                  alt="QR Code"
                  className="w-48 h-48"
                />
              </div>
            )}

            {/* Pass details */}
            <div className="space-y-2 text-primary-100">
              <div className="flex items-center justify-center gap-2">
                <MapPin size={16} />
                <span>{selectedPass.lot_name} — {t('parkingPass.slot')} {selectedPass.slot_number}</span>
              </div>
              <div className="flex items-center justify-center gap-2">
                <CalendarBlank size={16} />
                <span>{formatDate(selectedPass.valid_from)}</span>
              </div>
              <div className="flex items-center justify-center gap-2">
                <Clock size={16} />
                <span>{t('parkingPass.validUntil')} {formatDate(selectedPass.valid_until)}</span>
              </div>
            </div>

            <span className={`inline-block px-3 py-1 rounded-full text-xs font-medium ${statusColors[selectedPass.status]}`}>
              {t(`parkingPass.status.${selectedPass.status}`)}
            </span>

            <div className="text-xs text-primary-200 font-mono mt-2">
              {selectedPass.verification_code}
            </div>

            <button
              onClick={() => setSelectedPass(null)}
              className="mt-4 px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm"
            >
              {t('common.close')}
            </button>
          </div>
        </motion.div>
      ) : (
        /* Pass list */
        <>
          {passes.length > 0 ? (
            <div className="grid gap-4 lg:grid-cols-2">
              {passes.map(pass => (
                <motion.div
                  key={pass.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  onClick={() => setSelectedPass(pass)}
                  className={`cursor-pointer rounded-[24px] border p-5 transition hover:-translate-y-0.5 hover:shadow-lg ${
                    isVoid
                      ? 'border-slate-800 bg-slate-950/85 text-white'
                      : 'border-surface-200 bg-white text-surface-900 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] dark:border-surface-800 dark:bg-surface-950/80 dark:text-white'
                  }`}
                >
                  <div className="flex h-full flex-col justify-between gap-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className={`flex h-12 w-12 items-center justify-center rounded-2xl ${
                        isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400'
                      }`}>
                        <QrCode size={20} />
                      </div>
                      <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${statusColors[pass.status]}`}>
                        {t(`parkingPass.status.${pass.status}`)}
                      </span>
                    </div>
                    <div>
                      <p className="text-lg font-semibold">
                        {pass.lot_name}
                      </p>
                      <p className={`mt-1 text-sm ${isVoid ? 'text-slate-400' : 'text-surface-500 dark:text-surface-400'}`}>
                        {t('parkingPass.slot')} {pass.slot_number}
                      </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div className={`rounded-[18px] border px-3 py-3 ${
                        isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
                      }`}>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('parkingPass.digitalPass')}</p>
                        <p className="mt-2 text-sm font-semibold">{pass.user_name}</p>
                      </div>
                      <div className={`rounded-[18px] border px-3 py-3 ${
                        isVoid ? 'border-white/10 bg-white/[0.04]' : 'border-surface-100 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/70'
                      }`}>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{t('parkingPass.validUntil')}</p>
                        <p className="mt-2 text-sm font-semibold">{formatDate(pass.valid_until)}</p>
                      </div>
                    </div>
                    <div className="flex items-center justify-between gap-3 border-t border-surface-200/80 pt-3 dark:border-surface-800/80">
                      <div>
                        <p className="text-xs text-surface-400 dark:text-surface-500">{t('parkingPass.codeLabel', 'Verification')}</p>
                        <p className="mt-1 font-mono text-sm text-surface-700 dark:text-surface-300">{pass.verification_code}</p>
                      </div>
                      <div className={`text-xs ${isVoid ? 'text-cyan-100' : 'text-emerald-700 dark:text-emerald-300'}`}>
                        {formatDate(pass.valid_from)}
                      </div>
                    </div>
                  </div>
                </motion.div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12 text-surface-400">
              <Ticket size={48} className="mx-auto mb-3 opacity-40" />
              <p>{t('parkingPass.empty')}</p>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default ParkingPassPage;

function PassStatCard({
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
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${
        isVoid
          ? accent ? 'text-cyan-100' : 'text-white/45'
          : accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45'
      }`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.05em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${isVoid ? 'text-white/60' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function PassDigestLine({
  icon,
  title,
  body,
  meta,
  isVoid,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  meta: string;
  isVoid: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 py-4 ${
      isVoid
        ? 'border-white/10 bg-white/[0.03]'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.03]'
    }`}>
      <div className="flex items-start gap-3">
        <div className={`mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl ${
          isVoid ? 'bg-cyan-500/10 text-cyan-100' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        }`}>
          {icon}
        </div>
        <div className="min-w-0 flex-1">
          <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>{title}</p>
          <p className={`mt-2 text-sm font-semibold ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{body}</p>
          <p className={`mt-1 text-xs ${isVoid ? 'text-white/60' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
        </div>
      </div>
    </div>
  );
}
