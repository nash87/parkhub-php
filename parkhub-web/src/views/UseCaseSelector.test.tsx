import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// ── Mocks ──

const mockNavigate = vi.fn();
const mockSetUseCase = vi.fn();
const mockApplyPreset = vi.fn();
const mockSetFeatures = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('../context/UseCaseContext', () => ({
  useUseCase: () => ({
    setUseCase: mockSetUseCase,
  }),
}));

vi.mock('../context/ThemeContext', () => ({
  useTheme: () => ({
    resolved: 'light',
    setTheme: vi.fn(),
  }),
}));

vi.mock('../components/GenerativeBg', () => ({
  useBgClass: () => '',
}));

vi.mock('../context/FeaturesContext', () => ({
  useFeatures: () => ({
    features: ['credits', 'vehicles'],
    setFeatures: mockSetFeatures,
    applyPreset: mockApplyPreset,
  }),
  FEATURE_REGISTRY: [
    { id: 'vehicles',  category: 'core',          defaultEnabled: true },
    { id: 'credits',   category: 'billing',        defaultEnabled: true },
    { id: 'absences',  category: 'collaboration',  defaultEnabled: true },
    { id: 'analytics', category: 'admin',          defaultEnabled: true },
  ],
  USE_CASE_PRESETS: {
    business:    ['credits', 'vehicles', 'absences', 'analytics'],
    residential: ['vehicles'],
    personal:    ['vehicles'],
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => {
      const map: Record<string, string> = {
        'useCase.title': 'Choose your setup',
        'useCase.subtitle': 'Customise ParkHub for your organisation',
        'useCase.continue': 'Continue',
        'useCase.skip': 'Skip',
        'useCase.applying': 'Applying...',
        'useCase.business.name': 'Business',
        'useCase.business.desc': 'For businesses',
        'useCase.business.feature1': 'Feature 1',
        'useCase.business.feature2': 'Feature 2',
        'useCase.business.feature3': 'Feature 3',
        'useCase.residential.name': 'Residential',
        'useCase.residential.desc': 'For apartment complexes',
        'useCase.residential.feature1': 'Feature 1',
        'useCase.residential.feature2': 'Feature 2',
        'useCase.residential.feature3': 'Feature 3',
        'useCase.personal.name': 'Personal',
        'useCase.personal.desc': 'For individuals',
        'useCase.personal.feature1': 'Feature 1',
        'useCase.personal.feature2': 'Feature 2',
        'useCase.personal.feature3': 'Feature 3',
        'features.onboardingTitle': 'Configure Features',
        'features.onboardingSubtitle': 'Customise which modules are active',
        'features.enabled': 'Enabled',
        'features.disabled': 'Disabled',
        'features.compliance.title': 'Compliance',
        'features.compliance.gdpr': 'GDPR',
        'features.compliance.audit': 'Audit',
        'features.compliance.encryption': 'Encryption',
        'features.categories.core': 'Core',
        'features.categories.billing': 'Billing',
        'features.categories.collaboration': 'Collaboration',
        'features.categories.admin': 'Admin',
        'features.categories.experience': 'Experience',
        'onboarding.finish': 'Finish',
        'onboarding.back': 'Back',
        'nav.switchToLight': 'Switch to Light',
        'nav.switchToDark': 'Switch to Dark',
        'common.info': 'More info',
      };
      return map[key] || fallback || key;
    },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: React.forwardRef(({ children, initial, animate, exit, transition, ...props }: any, ref: any) => (
      <div ref={ref} {...props}>{children}</div>
    )),
    button: React.forwardRef(({ children, initial, animate, exit, transition, whileHover, whileTap, ...props }: any, ref: any) => (
      <button ref={ref} {...props}>{children}</button>
    )),
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

vi.mock('@phosphor-icons/react', () => ({
  Buildings:   (props: any) => <span data-testid="icon-buildings" {...props} />,
  House:       (props: any) => <span data-testid="icon-house" {...props} />,
  UsersThree:  (props: any) => <span data-testid="icon-users-three" {...props} />,
  Car:         (props: any) => <span data-testid="icon-car" {...props} />,
  ArrowRight:  (props: any) => <span data-testid="icon-arrow-right" {...props} />,
  ArrowLeft:   (props: any) => <span data-testid="icon-arrow-left" {...props} />,
  Check:       (props: any) => <span data-testid="icon-check" {...props} />,
  SunDim:      (props: any) => <span data-testid="icon-sun" {...props} />,
  Moon:        (props: any) => <span data-testid="icon-moon" {...props} />,
  ToggleLeft:  (props: any) => <span data-testid="icon-toggle-off" {...props} />,
  ToggleRight: (props: any) => <span data-testid="icon-toggle-on" {...props} />,
  Info:        (props: any) => <span data-testid="icon-info" {...props} />,
  ShieldCheck: (props: any) => <span data-testid="icon-shield" {...props} />,
}));

import { UseCaseSelectorPage } from './UseCaseSelector';

describe('UseCaseSelectorPage', () => {
  beforeEach(() => {
    mockNavigate.mockClear();
    mockSetUseCase.mockClear();
    mockApplyPreset.mockClear();
    mockSetFeatures.mockClear();
  });

  it('renders the page title', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Choose your setup')).toBeInTheDocument();
  });

  it('renders use-case options: Business, Residential, Personal', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Business')).toBeInTheDocument();
    expect(screen.getByText('Residential')).toBeInTheDocument();
    expect(screen.getByText('Personal')).toBeInTheDocument();
  });

  it('renders Continue button', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Continue')).toBeInTheDocument();
  });

  it('renders Skip link', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );
    expect(screen.getByText('Skip')).toBeInTheDocument();
  });

  it('proceeds to features step after selecting use case and clicking Continue', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );

    // Select Business
    fireEvent.click(screen.getByText('Business'));
    // Click Continue
    fireEvent.click(screen.getByText('Continue'));

    expect(screen.getByText('Configure Features')).toBeInTheDocument();
  });

  it('navigates when Skip is clicked', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );

    fireEvent.click(screen.getByText('Skip'));

    expect(mockSetUseCase).toHaveBeenCalledWith('business');
    expect(mockApplyPreset).toHaveBeenCalledWith('business');
    expect(mockNavigate).toHaveBeenCalledWith('/welcome');
  });

  it('shows feature toggles on features step', () => {
    render(
      <MemoryRouter>
        <UseCaseSelectorPage />
      </MemoryRouter>
    );

    fireEvent.click(screen.getByText('Business'));
    fireEvent.click(screen.getByText('Continue'));

    expect(screen.getByText('Core')).toBeInTheDocument();
    expect(screen.getByText('Finish')).toBeInTheDocument();
  });
});
