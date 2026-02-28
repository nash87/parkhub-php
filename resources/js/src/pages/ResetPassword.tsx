import { useState } from "react";
import { Link, useSearchParams, useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { Lock, ArrowLeft, SpinnerGap, CheckCircle, Eye, EyeSlash } from "@phosphor-icons/react";
import { useTranslation } from "react-i18next";
import toast from "react-hot-toast";

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token") || "";

  const [password, setPassword] = useState("");
  const [passwordConfirm, setPasswordConfirm] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [done, setDone] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!token) {
      toast.error(t("resetPassword.invalidToken", "Ungültiger oder abgelaufener Link."));
      return;
    }
    if (password.length < 8) {
      toast.error(t("resetPassword.tooShort", "Passwort muss mindestens 8 Zeichen lang sein."));
      return;
    }
    if (password !== passwordConfirm) {
      toast.error(t("resetPassword.mismatch", "Passwörter stimmen nicht überein."));
      return;
    }
    setLoading(true);
    try {
      const res = await fetch(`${import.meta.env.VITE_API_URL || ""}/api/v1/auth/reset-password`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token, password, password_confirmation: passwordConfirm }),
      });
      const data = await res.json();
      if (res.ok) {
        setDone(true);
        toast.success(t("resetPassword.success", "Passwort erfolgreich geändert."));
        setTimeout(() => navigate("/login"), 2500);
      } else {
        toast.error(data?.message || t("resetPassword.failed", "Zurücksetzen fehlgeschlagen."));
      }
    } catch {
      toast.error(t("resetPassword.failed", "Zurücksetzen fehlgeschlagen."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-6 py-12">
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
        <Link to="/login" className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-6 transition-colors">
          <ArrowLeft weight="bold" className="w-4 h-4" />
          {t("forgotPassword.backToLogin", "Zurück zur Anmeldung")}
        </Link>

        <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
          {t("resetPassword.title", "Neues Passwort festlegen")}
        </h1>
        <p className="text-gray-500 dark:text-gray-400 mb-8">
          {t("resetPassword.subtitle", "Geben Sie Ihr neues Passwort ein.")}
        </p>

        {!token ? (
          <div className="card p-8 text-center space-y-4">
            <p className="text-sm text-red-500">{t("resetPassword.noToken", "Kein gültiger Reset-Token gefunden. Bitte fordern Sie einen neuen Link an.")}</p>
            <Link to="/forgot-password" className="btn btn-primary">{t("resetPassword.requestNew", "Neuen Link anfordern")}</Link>
          </div>
        ) : done ? (
          <div className="card p-8 text-center space-y-4">
            <div className="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-2xl flex items-center justify-center mx-auto">
              <CheckCircle weight="fill" className="w-8 h-8 text-emerald-500" />
            </div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t("resetPassword.doneTitle", "Passwort geändert")}
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t("resetPassword.doneMessage", "Sie werden zur Anmeldung weitergeleitet…")}
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="reset-password" className="label">
                {t("resetPassword.newPassword", "Neues Passwort")}
              </label>
              <div className="relative">
                <Lock weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="reset-password"
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="input pl-10 pr-12"
                  placeholder="••••••••"
                  minLength={8}
                  required
                  autoFocus
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                  aria-label={showPassword ? t("login.hidePassword", "Passwort verbergen") : t("login.showPassword", "Passwort anzeigen")}
                >
                  {showPassword ? <EyeSlash weight="regular" className="w-5 h-5" /> : <Eye weight="regular" className="w-5 h-5" />}
                </button>
              </div>
              <p className="text-xs text-gray-400 mt-1">{t("resetPassword.minChars", "Mindestens 8 Zeichen")}</p>
            </div>

            <div>
              <label htmlFor="reset-password-confirm" className="label">
                {t("resetPassword.confirmPassword", "Passwort bestätigen")}
              </label>
              <div className="relative">
                <Lock weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="reset-password-confirm"
                  type={showPassword ? "text" : "password"}
                  value={passwordConfirm}
                  onChange={(e) => setPasswordConfirm(e.target.value)}
                  className="input pl-10"
                  placeholder="••••••••"
                  minLength={8}
                  required
                  autoComplete="new-password"
                />
              </div>
              {passwordConfirm && password !== passwordConfirm && (
                <p className="text-xs text-red-500 mt-1">{t("resetPassword.mismatch", "Passwörter stimmen nicht überein.")}</p>
              )}
            </div>

            <button type="submit" disabled={loading || !token} className="btn btn-primary w-full justify-center disabled:opacity-60">
              {loading ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" /> : t("resetPassword.submit", "Passwort ändern")}
            </button>
          </form>
        )}
      </motion.div>
    </div>
  );
}
