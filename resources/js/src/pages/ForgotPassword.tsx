import { useState } from "react";
import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import { Envelope, ArrowLeft, SpinnerGap, PaperPlaneTilt, WarningCircle } from "@phosphor-icons/react";
import { useTranslation } from "react-i18next";
import toast from "react-hot-toast";

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  // For now assume no SMTP configured — show contact admin message
  const smtpConfigured = false;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email) return;
    setLoading(true);
    try {
      const res = await fetch(`${import.meta.env.VITE_API_URL || ""}/api/v1/auth/forgot-password`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });
      if (res.ok) {
        setSent(true);
        toast.success(t("forgotPassword.emailSent"));
      } else {
        toast.error(t("forgotPassword.failed"));
      }
    } catch {
      toast.error(t("forgotPassword.failed"));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-6 py-12">
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
        <Link to="/login" className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-6 transition-colors">
          <ArrowLeft weight="bold" className="w-4 h-4" />
          {t("forgotPassword.backToLogin")}
        </Link>

        <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">{t("forgotPassword.title")}</h1>
        <p className="text-gray-500 dark:text-gray-400 mb-8">{t("forgotPassword.subtitle")}</p>

        {!smtpConfigured ? (
          <div className="card p-8 text-center space-y-4">
            <div className="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center mx-auto">
              <WarningCircle weight="fill" className="w-8 h-8 text-amber-500" />
            </div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t("forgotPassword.noSmtpTitle")}</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">{t("forgotPassword.noSmtpMessage")}</p>
          </div>
        ) : sent ? (
          <div className="card p-8 text-center space-y-4">
            <div className="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-2xl flex items-center justify-center mx-auto">
              <PaperPlaneTilt weight="fill" className="w-8 h-8 text-emerald-500" />
            </div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t("forgotPassword.sentTitle")}</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">{t("forgotPassword.sentMessage")}</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="forgot-email" className="label">{t("forgotPassword.email")}</label>
              <div className="relative">
                <Envelope weight="regular" className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="forgot-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="input pl-10"
                  placeholder={t("forgotPassword.emailPlaceholder")}
                  required
                  autoFocus
                />
              </div>
            </div>
            <button type="submit" disabled={loading} className="btn btn-primary w-full justify-center">
              {loading ? <SpinnerGap weight="bold" className="w-5 h-5 animate-spin" /> : t("forgotPassword.submit")}
            </button>
          </form>
        )}
      </motion.div>
    </div>
  );
}
