import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// ── Mocks ──

const mockSetFeatures = vi.fn();
const mockToastSuccess = vi.fn();

vi.mock('../context/FeaturesContext', () => ({
  useFeatures: () => ({
    features: ['credits', 'vehicles'],
    setFeatures: mockSetFeatures,
  }),
  FEATURE_REGISTRY: [
    { id: 'credits',   category: 'billing',  defaultEnabled: true },
    { id: 'vehicles',  category: 'core',     defaultEnabled: true },
    { id: 'absences',  category: 'collaboration', defaultEnabled: true },
    { id: 'analytics', category: 'admin',    defaultEnabled: true },
  ],
  USE_CASE_PRESETS: {
    business:    ['credits', 'vehicles'],
    residential: ['vehicles'],
    personal:    ['vehicles'],
  },
}));

vi.mock('../context/UseCaseContext', () => ({
  useUseCase: () => ({ useCase: 'business' }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => {
      const map: Record<string, string> = {
        'features.title': 'Feature Flags',
        'features.subtitle': 'Enable or disable modules',
        'features.enableAll': 'Enable All',
        'features.disableAll': 'Disable All',
        'features.resetToPreset': 'Reset to Preset',
        'features.enabled': 'Enabled',
        'features.disabled': 'Disabled',
        'features.saveChanges': 'Save Changes',
        'features.saved': 'Saved',
        'features.compliance.title': 'Compliance',
        'features.compliance.gdpr': 'GDPR compliant',
        'features.compliance.audit': 'Audit logs',
        'features.compliance.encryption': 'Data encrypted',
        'features.categories.core': 'Core',
        'features.categories.billing': 'Billing',
        'features.categories.collaboration': 'Collaboration',
        'features.categories.admin': 'Admin',
        'features.categories.experience': 'Experience',
        'nav.dashboard': 'Dashboard',
        'nav.admin': 'Admin',
        'common.info': 'More info',
      };
      return map[key] || fallback || key;
    },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: React.forwardRef(({ children, initial, animate, exit, transition, variants, ...props }: any, ref: any) => (
      <div ref={ref} {...props}>{children}</div>
    )),
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
  stagger: {},
}));

vi.mock('../constants/animations', () => ({
  stagger: { hidden: {}, show: {} },
}));

vi.mock('@phosphor-icons/react', () => ({
  ToggleLeft:     (props: any) => <span data-testid="icon-toggle-off" {...props} />,
  ToggleRight:    (props: any) => <span data-testid="icon-toggle-on" {...props} />,
  Info:           (props: any) => <span data-testid="icon-info" {...props} />,
  ShieldCheck:    (props: any) => <span data-testid="icon-shield" {...props} />,
  ArrowLeft:      (props: any) => <span data-testid="icon-arrow-left" {...props} />,
  ArrowClockwise: (props: any) => <span data-testid="icon-reset" {...props} />,
  FloppyDisk:     (props: any) => <span data-testid="icon-save" {...props} />,
  Check:          (props: any) => <span data-testid="icon-check" {...props} />,
}));

vi.mock('react-hot-toast', () => ({
  default: { success: (...args: any[]) => mockToastSuccess(...args) },
}));

import { AdminFeaturesPage } from './AdminFeatures';

describe('AdminFeaturesPage', () => {
  beforeEach(() => {
    mockSetFeatures.mockClear();
    mockToastSuccess.mockClear();
  });

  it('renders the page title', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Feature Flags')).toBeInTheDocument();
  });

  it('renders the subtitle', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Enable or disable modules')).toBeInTheDocument();
  });

  it('renders Enable All and Disable All buttons', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Enable All')).toBeInTheDocument();
    expect(screen.getByText('Disable All')).toBeInTheDocument();
  });

  it('renders Reset to Preset button', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Reset to Preset')).toBeInTheDocument();
  });

  it('renders feature category sections', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Core')).toBeInTheDocument();
    expect(screen.getByText('Billing')).toBeInTheDocument();
  });

  it('shows Compliance section', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Compliance')).toBeInTheDocument();
  });

  it('shows back link to dashboard', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Dashboard')).toBeInTheDocument();
  });

  it('shows save bar when features are toggled', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    // Click a toggle to change state (absences is not in initial features)
    const toggleButtons = screen.getAllByTestId('icon-toggle-off');
    fireEvent.click(toggleButtons[0].closest('button')!);
    expect(screen.getByText('Save Changes')).toBeInTheDocument();
  });

  it('disables all features on Disable All click', () => {
    render(
      <MemoryRouter>
        <AdminFeaturesPage />
      </MemoryRouter>
    );
    fireEvent.click(screen.getByText('Disable All'));
    // All toggles should now show off state
    expect(screen.getByText('Save Changes')).toBeInTheDocument();
  });
});
