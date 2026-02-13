import { createContext } from 'react';
import type { BrandingConfig } from '../api/client';

export interface BrandingContextType {
  branding: BrandingConfig;
  loading: boolean;
  refresh: () => Promise<void>;
}

const defaultBranding: BrandingConfig = {
  company_name: 'ParkHub',
  primary_color: '#3B82F6',
  secondary_color: '#1D4ED8',
  logo_url: null,
  favicon_url: null,
  login_background_color: '#2563EB',
  custom_css: null,
};

export const BrandingContext = createContext<BrandingContextType>({
  branding: defaultBranding,
  loading: true,
  refresh: async () => {},
});

export { defaultBranding };
