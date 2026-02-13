import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Palette, Upload, SpinnerGap, Check, Eye, Image } from '@phosphor-icons/react';
import { BrandingConfig, getBranding, updateBranding, uploadBrandingLogo } from '../api/client';
import { useBranding } from '../context/branding-hook';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';

export function AdminBrandingPage() {
  const { t } = useTranslation();
  const { refresh } = useBranding();
  const [config, setConfig] = useState<BrandingConfig>({
    company_name: 'ParkHub',
    primary_color: '#3B82F6',
    secondary_color: '#1D4ED8',
    logo_url: null,
    favicon_url: null,
    login_background_color: '#2563EB',
    custom_css: null,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const logoVariants = Array.from({length: 13}, (_, i) => `/logos/variant-${i + 1}.png`);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    loadBranding();
  }, []);

  async function loadBranding() {
    try {
      const res = await getBranding();
      if (res.success && res.data) {
        setConfig(res.data);
        if (res.data.logo_url) {
          setLogoPreview(res.data.logo_url);
        }
      }
    } finally {
      setLoading(false);
    }
  }

  async function handleSave() {
    setSaving(true);
    try {
      const res = await updateBranding(config);
      if (res.success) {
        toast.success(t('admin.branding.saved', 'Branding settings saved!'));
        await refresh();
      } else {
        toast.error(res.error?.message || 'Failed to save');
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleLogoUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      toast.error(t('admin.branding.logoTooLarge', 'Logo must be under 2MB'));
      return;
    }

    if (!['image/png', 'image/jpeg', 'image/svg+xml'].includes(file.type)) {
      toast.error(t('admin.branding.invalidType', 'Only PNG, JPEG, and SVG files are accepted'));
      return;
    }

    setUploading(true);
    try {
      // Show local preview
      const reader = new FileReader();
      reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
      reader.readAsDataURL(file);

      const res = await uploadBrandingLogo(file);
      if (res.success && res.data) {
        setConfig(prev => ({ ...prev, logo_url: res.data!.logo_url }));
        setLogoPreview(res.data.logo_url);
        toast.success(t('admin.branding.logoUploaded', 'Logo uploaded!'));
        await refresh();
      } else {
        toast.error(res.error?.message || 'Upload failed');
      }
    } finally {
      setUploading(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-8">
      <div className="flex items-center gap-3">
        <Palette weight="fill" className="w-6 h-6 text-primary-600" />
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          {t('admin.branding.title', 'Corporate Identity / White-Label')}
        </h2>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Settings */}
        <div className="space-y-6">
          {/* Logo Upload */}
          <div className="card p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <Image weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.branding.logo', 'Company Logo')}
            </h3>
            <div className="flex items-center gap-6">
              <div className="w-24 h-24 bg-gray-100 dark:bg-gray-800 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300 dark:border-gray-600">
                {logoPreview ? (
                  <img src={logoPreview} alt="Logo" className="w-full h-full object-contain p-2" />
                ) : (
                  <Upload weight="light" className="w-8 h-8 text-gray-400" />
                )}
              </div>
              <div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/png,image/jpeg,image/svg+xml"
                  onChange={handleLogoUpload}
                  className="hidden"
                />
                <button
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploading}
                  className="btn btn-secondary"
                >
                  {uploading ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Upload weight="bold" className="w-4 h-4" />}
                  {t('admin.branding.uploadLogo', 'Upload Logo')}
                </button>
                <p className="text-xs text-gray-500 mt-2">PNG, JPEG, SVG · Max 2MB</p>
              </div>
            </div>

            {/* Built-in Logo Variants */}
            <div className="mt-6">
              <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                {t('admin.branding.selectLogo', 'Select Logo')}
              </h4>
              <div className="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-3 max-h-80 overflow-y-auto pr-1">
                {logoVariants.map((variant, idx) => (
                  <button
                    key={variant}
                    onClick={() => {
                      setConfig(prev => ({ ...prev, logo_url: variant }));
                      setLogoPreview(variant);
                    }}
                    className={`relative p-3 rounded-xl border-2 transition-all hover:shadow-md ${
                      config.logo_url === variant
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500/30'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                    }`}
                  >
                    <img src={variant} alt={`Variant ${idx + 1}`} className="w-full h-16 object-contain" />
                    {config.logo_url === variant && (
                      <div className="absolute top-1 right-1 w-5 h-5 bg-primary-500 rounded-full flex items-center justify-center">
                        <Check weight="bold" className="w-3 h-3 text-white" />
                      </div>
                    )}
                    <p className="text-[10px] text-gray-400 text-center mt-1">Variant {idx + 1}</p>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Company Name */}
          <div className="card p-6">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {t('admin.branding.companyName', 'Company Name')}
            </label>
            <input
              type="text"
              value={config.company_name}
              onChange={(e) => setConfig(prev => ({ ...prev, company_name: e.target.value }))}
              className="input"
              placeholder="ParkHub"
            />
          </div>

          {/* Colors */}
          <div className="card p-6 space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <Palette weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.branding.colors', 'Brand Colors')}
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.branding.primaryColor', 'Primary Color')}
                </label>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={config.primary_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, primary_color: e.target.value }))}
                    className="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer"
                  />
                  <input
                    type="text"
                    value={config.primary_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, primary_color: e.target.value }))}
                    className="input flex-1 font-mono text-sm"
                    placeholder="#3B82F6"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.branding.secondaryColor', 'Secondary Color')}
                </label>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={config.secondary_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, secondary_color: e.target.value }))}
                    className="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer"
                  />
                  <input
                    type="text"
                    value={config.secondary_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, secondary_color: e.target.value }))}
                    className="input flex-1 font-mono text-sm"
                    placeholder="#1D4ED8"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {t('admin.branding.loginBgColor', 'Login Background')}
                </label>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={config.login_background_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, login_background_color: e.target.value }))}
                    className="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer"
                  />
                  <input
                    type="text"
                    value={config.login_background_color}
                    onChange={(e) => setConfig(prev => ({ ...prev, login_background_color: e.target.value }))}
                    className="input flex-1 font-mono text-sm"
                    placeholder="#2563EB"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Custom CSS */}
          <div className="card p-6">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {t('admin.branding.customCss', 'Custom CSS (Advanced)')}
            </label>
            <textarea
              value={config.custom_css || ''}
              onChange={(e) => setConfig(prev => ({ ...prev, custom_css: e.target.value || null }))}
              className="input font-mono text-sm h-32 resize-y"
              placeholder="/* Custom CSS styles */"
            />
          </div>

          {/* Save Button */}
          <button
            onClick={handleSave}
            disabled={saving}
            className="btn btn-primary w-full"
          >
            {saving ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
            {t('admin.branding.save', 'Save Branding Settings')}
          </button>
        </div>

        {/* Live Preview */}
        <div className="space-y-6">
          <div className="card p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
              <Eye weight="fill" className="w-5 h-5 text-primary-600" />
              {t('admin.branding.preview', 'Live Preview')}
            </h3>

            {/* Header Preview */}
            <div className="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
              <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                {logoPreview ? (
                  <img src={logoPreview} alt="Logo" className="w-8 h-8 object-contain" />
                ) : (
                  <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold" style={{ backgroundColor: config.primary_color }}>
                    {config.company_name.charAt(0)}
                  </div>
                )}
                <span className="font-bold text-gray-900 dark:text-white">{config.company_name}</span>
                <div className="flex-1" />
                <div className="flex gap-1">
                  {['Dashboard', 'Book', 'Bookings'].map(tab => (
                    <span key={tab} className="px-3 py-1 rounded-lg text-xs" style={tab === 'Dashboard' ? { backgroundColor: config.primary_color + '20', color: config.primary_color } : { color: '#6B7280' }}>
                      {tab}
                    </span>
                  ))}
                </div>
              </div>

              {/* Login Preview */}
              <div className="flex h-48">
                <div className="w-1/2 p-6 flex flex-col justify-center text-white" style={{ background: `linear-gradient(135deg, ${config.primary_color}, ${config.login_background_color})` }}>
                  <div className="flex items-center gap-2 mb-3">
                    {logoPreview ? (
                      <img src={logoPreview} alt="" className="w-6 h-6 object-contain" />
                    ) : (
                      <div className="w-6 h-6 bg-white/20 rounded-md" />
                    )}
                    <span className="text-sm font-bold">{config.company_name}</span>
                  </div>
                  <p className="text-xs opacity-80">Smart Parking Management</p>
                </div>
                <div className="w-1/2 p-6 flex flex-col justify-center bg-gray-50 dark:bg-gray-900">
                  <p className="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Sign in</p>
                  <div className="space-y-2">
                    <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded" />
                    <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded" />
                    <div className="h-6 rounded text-white text-[10px] flex items-center justify-center font-medium" style={{ backgroundColor: config.primary_color }}>
                      Login
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Button Preview */}
            <div className="mt-4 flex flex-wrap gap-2">
              <button className="px-3 py-1.5 rounded-lg text-sm text-white font-medium" style={{ backgroundColor: config.primary_color }}>
                Primary Button
              </button>
              <button className="px-3 py-1.5 rounded-lg text-sm font-medium" style={{ backgroundColor: config.secondary_color, color: 'white' }}>
                Secondary
              </button>
              <span className="px-3 py-1.5 rounded-lg text-sm font-medium" style={{ backgroundColor: config.primary_color + '15', color: config.primary_color }}>
                Active Tab
              </span>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
