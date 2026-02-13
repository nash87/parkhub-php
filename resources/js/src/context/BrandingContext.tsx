import { useEffect, useState, ReactNode } from 'react';
import { getBranding, type BrandingConfig } from '../api/client';
import { BrandingContext, defaultBranding } from './branding-context';

/** Convert hex color to space-separated RGB for Tailwind CSS variable */
function hexToRgb(hex: string): string {
  const h = hex.replace('#', '');
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  if (isNaN(r) || isNaN(g) || isNaN(b)) return '245 158 11'; // fallback blue
  return `${r} ${g} ${b}`;
}

/** Generate lighter/darker shades from a base hex color */
function generatePalette(hex: string): Record<string, string> {
  const h = hex.replace('#', '');
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  if (isNaN(r) || isNaN(g) || isNaN(b)) return {};

  const shades: Record<number, [number, number]> = {
    50: [0.95, 1], 100: [0.9, 1], 200: [0.75, 1], 300: [0.6, 1],
    400: [0.4, 1], 500: [0, 1], 600: [0, 0.85], 700: [0, 0.7],
    800: [0, 0.55], 900: [0, 0.4], 950: [0, 0.3],
  };

  const result: Record<string, string> = {};
  for (const [shade, [lighten, darken]] of Object.entries(shades)) {
    let sr: number, sg: number, sb: number;
    if (lighten > 0) {
      sr = Math.round(r + (255 - r) * lighten);
      sg = Math.round(g + (255 - g) * lighten);
      sb = Math.round(b + (255 - b) * lighten);
    } else {
      sr = Math.round(r * darken);
      sg = Math.round(g * darken);
      sb = Math.round(b * darken);
    }
    result[shade] = `${sr} ${sg} ${sb}`;
  }
  return result;
}

function applyBrandingColors(branding: BrandingConfig) {
  const root = document.documentElement;

  // Set the base CSS variable used by Tailwind
  root.style.setProperty('--color-primary', hexToRgb(branding.primary_color));

  // Generate and apply full palette
  const palette = generatePalette(branding.primary_color);
  for (const [shade, rgb] of Object.entries(palette)) {
    root.style.setProperty(`--color-primary-${shade}`, rgb);
  }

  // Set secondary color
  root.style.setProperty('--color-secondary', hexToRgb(branding.secondary_color));

  // Inject custom CSS if provided
  let customStyleEl = document.getElementById('parkhub-custom-css');
  if (branding.custom_css) {
    if (!customStyleEl) {
      customStyleEl = document.createElement('style');
      customStyleEl.id = 'parkhub-custom-css';
      document.head.appendChild(customStyleEl);
    }
    customStyleEl.textContent = branding.custom_css;
  } else if (customStyleEl) {
    customStyleEl.remove();
  }

  // Update page title
  document.title = branding.company_name || 'ParkHub';
}

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBranding] = useState<BrandingConfig>(defaultBranding);
  const [loading, setLoading] = useState(true);

  async function fetchBranding() {
    try {
      const res = await getBranding();
      if (res.success && res.data) {
        setBranding(res.data);
        applyBrandingColors(res.data);
      }
    } catch {
      // Use defaults
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchBranding();
  }, []);

  return (
    <BrandingContext.Provider value={{ branding, loading, refresh: fetchBranding }}>
      {children}
    </BrandingContext.Provider>
  );
}
