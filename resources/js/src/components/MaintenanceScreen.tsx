import { useEffect, useState } from "react";
import { SpinnerGap } from "@phosphor-icons/react";
import { useTranslation } from "react-i18next";

interface MaintenanceScreenProps {
  progress?: number;
  message?: string;
  step?: string;
}

export function MaintenanceScreen({ progress: externalProgress, message: externalMessage, step: externalStep }: MaintenanceScreenProps) {
  const { t } = useTranslation();
  const [dots, setDots] = useState("");
  const progress = externalProgress ?? 0;
  const message = externalMessage ?? "";
  const step = externalStep ?? "";

  const [timedOut, setTimedOut] = useState(false);

  useEffect(() => {
    const timeout = setTimeout(() => setTimedOut(true), 60000);
    return () => clearTimeout(timeout);
  }, []);

  useEffect(() => {
    const dotInterval = setInterval(() => {
      setDots((d) => (d.length >= 3 ? "" : d + "."));
    }, 500);

    const checkInterval = setInterval(async () => {
      try {
        const res = await fetch(`${import.meta.env.VITE_API_URL || ""}/api/v1/health`);
        if (res.ok) {
          // Server is back! Check maintenance
          const mRes = await fetch(`${import.meta.env.VITE_API_URL || ""}/api/v1/system/maintenance`);
          const data = await mRes.json();
          if (!data.maintenance) {
            window.location.href = "/login";
          }
        }
      } catch {
        // Server still down
      }
    }, 3000);

    return () => {
      clearInterval(dotInterval);
      clearInterval(checkInterval);
    };
  }, []);

  const displayProgress = progress || 0;
  const isError = step === "error";

  return (
    <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-950/95 backdrop-blur-sm">
      {!isError ? (
        <SpinnerGap weight="bold" className="w-12 h-12 text-primary-400 animate-spin mb-6" />
      ) : (
        <div className="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center mb-6">
          <span className="text-red-400 text-2xl">✕</span>
        </div>
      )}

      <h1 className="text-2xl font-bold text-white mb-2">
        {isError ? "Update Failed" : t("system.updating")}
      </h1>

      {/* Progress bar */}
      {displayProgress > 0 && !isError && (
        <div className="w-64 h-2 bg-gray-800 rounded-full overflow-hidden mt-4 mb-3">
          <div
            className="h-full bg-primary-500 rounded-full transition-all duration-500 ease-out"
            style={{ width: `${displayProgress}%` }}
          />
        </div>
      )}

      {/* Percentage */}
      {displayProgress > 0 && !isError && (
        <p className="text-primary-400 text-sm font-mono mb-2">
          {displayProgress}%
        </p>
      )}

      {/* Message */}
      <p className={`text-sm max-w-md text-center px-4 ${isError ? 'text-red-400' : 'text-gray-400'}`}>
        {message || dots}
      </p>

      {/* Error: show retry button */}
      {isError && (
        <button
          onClick={() => window.location.href = "/admin"}
          className="mt-6 px-4 py-2 text-sm bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition-colors"
        >
          Back to Admin
        </button>
      )}

      {timedOut && !isError && (
        <div className="mt-6 text-center">
          <p className="text-sm text-amber-400 mb-3">
            If the server doesn't come back, please refresh the page or restart the server manually.
          </p>
          <button
            onClick={() => window.location.reload()}
            className="px-4 py-2 text-sm bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Refresh Page
          </button>
        </div>
      )}
    </div>
  );
}
