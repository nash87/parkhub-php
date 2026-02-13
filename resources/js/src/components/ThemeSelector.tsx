import { useTranslation } from "react-i18next";
import { Palette, Check } from "@phosphor-icons/react";
import { PALETTES, usePalette, applyPalette } from "../stores/palette";
import { useTheme } from "../stores/theme";

interface ThemeSelectorProps {
  compact?: boolean;
}

export function ThemeSelector({ compact }: ThemeSelectorProps) {
  const { t } = useTranslation();
  const { paletteId, setPalette } = usePalette();
  const { isDark } = useTheme();

  function handleSelect(id: string) {
    setPalette(id);
    applyPalette(id, isDark);
  }

  if (compact) {
    return (
      <div className="relative group">
        <button
          className="p-2 rounded-xl bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          aria-label={t("palette.title")}
        >
          <Palette weight="bold" className="w-4 h-4" />
        </button>
        <div className="absolute right-0 top-full mt-2 p-3 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 w-56">
          <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">{t("palette.title")}</p>
          <div className="grid grid-cols-5 gap-1.5">
            {PALETTES.map((p) => {
              const colors = isDark ? p.dark : p.light;
              const isActive = paletteId === p.id;
              return (
                <button
                  key={p.id}
                  onClick={() => handleSelect(p.id)}
                  title={t(p.name)}
                  className={`w-8 h-8 rounded-lg relative overflow-hidden transition-all ${isActive ? "ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-800" : "hover:scale-110"}`}
                >
                  <div className="absolute inset-0 flex">
                    <div className="w-1/2 h-full" style={{ backgroundColor: colors.primary }} />
                    <div className="w-1/2 h-full" style={{ backgroundColor: colors.secondary }} />
                  </div>
                  {isActive && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                      <Check weight="bold" className="w-3 h-3 text-white" />
                    </div>
                  )}
                </button>
              );
            })}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div>
      <label className="label flex items-center gap-2">
        <Palette weight="regular" className="w-4 h-4" />
        {t("palette.title")}
      </label>
      <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
        {PALETTES.map((p) => {
          const colors = isDark ? p.dark : p.light;
          const isActive = paletteId === p.id;
          return (
            <button
              key={p.id}
              onClick={() => handleSelect(p.id)}
              className={`group relative rounded-xl p-3 text-left transition-all ${isActive ? "bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500" : "bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800"}`}
            >
              <div className="flex gap-1 mb-2">
                <div className="w-5 h-5 rounded-full" style={{ backgroundColor: colors.primary }} />
                <div className="w-5 h-5 rounded-full" style={{ backgroundColor: colors.secondary }} />
                <div className="w-5 h-5 rounded-full" style={{ backgroundColor: colors.accent }} />
              </div>
              <p className="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{t(p.name)}</p>
              {isActive && (
                <div className="absolute top-2 right-2">
                  <Check weight="bold" className="w-4 h-4 text-primary-600 dark:text-primary-400" />
                </div>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
