import { useState } from 'react';
import { Question, X } from '@phosphor-icons/react';
import { motion, AnimatePresence } from 'framer-motion';

interface HelpButtonProps {
  title: string;
  content: string;
}

export function HelpButton({ title, content }: HelpButtonProps) {
  const [open, setOpen] = useState(false);

  return (
    <span className="relative inline-flex">
      <button
        onClick={() => setOpen(!open)}
        className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-primary-900/30 dark:hover:text-primary-400 transition-colors"
        aria-label={`Help: ${title}`}
        type="button"
      >
        <Question weight="bold" className="w-3 h-3" />
      </button>
      <AnimatePresence>
        {open && (
          <>
            <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: -4 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: -4 }}
              className="absolute z-50 bottom-full mb-2 left-1/2 -translate-x-1/2 w-64 card p-4 shadow-xl"
            >
              <div className="flex items-center justify-between mb-2">
                <h4 className="text-sm font-semibold text-gray-900 dark:text-white">{title}</h4>
                <button onClick={() => setOpen(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <X weight="bold" className="w-3.5 h-3.5" />
                </button>
              </div>
              <p className="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{content}</p>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </span>
  );
}
