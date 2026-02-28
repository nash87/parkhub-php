import { motion, AnimatePresence } from 'framer-motion';
import { Warning, X } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning';
  onConfirm: () => void;
  onCancel: () => void;
  children?: React.ReactNode;
}

export function ConfirmDialog({ open, title, message, confirmLabel, cancelLabel, variant = 'danger', onConfirm, onCancel, children }: ConfirmDialogProps) {
  const { t } = useTranslation();
  const confirm = confirmLabel || t('common.confirm');
  const cancel = cancelLabel || t('common.cancel');

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-50 flex items-center justify-center p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="confirm-dialog-title"
          aria-describedby="confirm-dialog-desc"
        >
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-black/50 backdrop-blur-sm"
            onClick={onCancel}
            aria-hidden="true"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            transition={{ type: 'spring', damping: 25, stiffness: 300 }}
            className="relative w-full max-w-md card p-6 shadow-2xl"
          >
            <div className="flex items-start gap-4">
              <div className={`w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 ${variant === 'danger' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-amber-100 dark:bg-amber-900/30'}`}>
                <Warning weight="fill" className={`w-6 h-6 ${variant === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'}`} aria-hidden="true" />
              </div>
              <div className="flex-1">
                <h3 id="confirm-dialog-title" className="text-lg font-semibold text-gray-900 dark:text-white">{title}</h3>
                <p id="confirm-dialog-desc" className="text-sm text-gray-500 dark:text-gray-400 mt-1">{message}</p>
                {children && <div className="mt-3">{children}</div>}
              </div>
              <button onClick={onCancel} aria-label="Schließen" className="btn btn-ghost btn-icon -mt-1 -mr-1">
                <X weight="bold" className="w-5 h-5" aria-hidden="true" />
              </button>
            </div>
            <div className="flex justify-end gap-3 mt-6">
              <button onClick={onCancel} className="btn btn-secondary">{cancel}</button>
              <button onClick={onConfirm} autoFocus className={`btn ${variant === 'danger' ? 'btn-danger' : 'btn-primary'}`}>{confirm}</button>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
