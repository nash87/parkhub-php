import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render, screen, within } from '@testing-library/react';

// ── Mocks ──

vi.mock('react-router-dom', () => ({
  Link: ({ to, children, ...props }: any) => <a href={to} {...props}>{children}</a>,
  Outlet: () => <div data-testid="outlet">Outlet Content</div>,
  useLocation: () => ({ pathname: '/admin' }),
}));

vi.mock('../context/ThemeContext', () => ({
  useTheme: () => ({ designTheme: 'marble' }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'admin.title': 'Administration',
        'admin.subtitle': 'Manage your ParkHub instance',
        'admin.overview': 'Overview',
        'admin.settings': 'Settings',
        'admin.users': 'Users',
        'admin.lots': 'Parking Lots',
        'admin.announcements': 'Announcements',
        'admin.reports': 'Reports',
        'admin.translations': 'Translations',
        'admin.rateLimits': 'Rate Limits',
        'admin.tenants': 'Tenants',
      };
      return map[key] || key;
    },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: React.forwardRef(({ children, ...props }: any, ref: any) => (
      <div ref={ref} {...props}>{children}</div>
    )),
  },
}));

vi.mock('@phosphor-icons/react', () => ({
  ChartBar: (props: any) => <span data-testid="icon-chart" {...props} />,
  GearSix: (props: any) => <span data-testid="icon-gear" {...props} />,
  Users: (props: any) => <span data-testid="icon-users" {...props} />,
  Megaphone: (props: any) => <span data-testid="icon-megaphone" {...props} />,
  ChartLine: (props: any) => <span data-testid="icon-chart-line" {...props} />,
  MapPin: (props: any) => <span data-testid="icon-map-pin" {...props} />,
  Translate: (props: any) => <span data-testid="icon-translate" {...props} />,
  PresentationChart: (props: any) => <span data-testid="icon-presentation" {...props} />,
  Gauge: (props: any) => <span data-testid="icon-gauge" {...props} />,
  Buildings: (props: any) => <span data-testid="icon-buildings" {...props} />,
  ClockCounterClockwise: (props: any) => <span data-testid="icon-clock" {...props} />,
  Database: (props: any) => <span data-testid="icon-database" {...props} />,
  Car: (props: any) => <span data-testid="icon-car" {...props} />,
  Wheelchair: (props: any) => <span data-testid="icon-wheelchair" {...props} />,
  Wrench: (props: any) => <span data-testid="icon-wrench" {...props} />,
  CurrencyDollar: (props: any) => <span data-testid="icon-currency" {...props} />,
  UserPlus: (props: any) => <span data-testid="icon-user-plus" {...props} />,
  Lightning: (props: any) => <span data-testid="icon-lightning" {...props} />,
  PuzzlePiece: (props: any) => <span data-testid="icon-puzzle" {...props} />,
  GraphicsCard: (props: any) => <span data-testid="icon-graphql" {...props} />,
  ShieldCheck: (props: any) => <span data-testid="icon-shield" {...props} />,
  LockKey: (props: any) => <span data-testid="icon-lock-key" {...props} />,
  MapTrifold: (props: any) => <span data-testid="icon-map-trifold" {...props} />,
  ArrowsClockwise: (props: any) => <span data-testid="icon-arrows-clockwise" {...props} />,
}));

import { AdminPage } from './Admin';

describe('AdminPage', () => {
  it('renders Admin heading', () => {
    render(<AdminPage />);
    expect(screen.getByText('Administration')).toBeInTheDocument();
  });

  it('renders the subtitle', () => {
    render(<AdminPage />);
    expect(screen.getByText('Manage your ParkHub instance')).toBeInTheDocument();
  });

  it('renders all tab navigation links', () => {
    render(<AdminPage />);
    const nav = screen.getByRole('navigation', { name: 'Admin navigation' });
    expect(within(nav).getByText('Overview')).toBeInTheDocument();
    expect(within(nav).getByText('Settings')).toBeInTheDocument();
    expect(within(nav).getByText('Users')).toBeInTheDocument();
    expect(within(nav).getByText('Parking Lots')).toBeInTheDocument();
    expect(within(nav).getByText('Announcements')).toBeInTheDocument();
    expect(within(nav).getByText('Reports')).toBeInTheDocument();
    expect(within(nav).getByText('Translations')).toBeInTheDocument();
    expect(within(nav).getByText('Analytics')).toBeInTheDocument();
    expect(within(nav).getByText('Rate Limits')).toBeInTheDocument();
    expect(within(nav).getByText('Tenants')).toBeInTheDocument();
  });

  it('renders tab links with correct paths', () => {
    render(<AdminPage />);
    const nav = screen.getByRole('navigation', { name: 'Admin navigation' });
    expect(within(nav).getByText('Overview').closest('a')).toHaveAttribute('href', '/admin');
    expect(within(nav).getByText('Settings').closest('a')).toHaveAttribute('href', '/admin/settings');
    expect(within(nav).getByText('Users').closest('a')).toHaveAttribute('href', '/admin/users');
    expect(within(nav).getByText('Parking Lots').closest('a')).toHaveAttribute('href', '/admin/lots');
    expect(within(nav).getByText('Announcements').closest('a')).toHaveAttribute('href', '/admin/announcements');
    expect(within(nav).getByText('Reports').closest('a')).toHaveAttribute('href', '/admin/reports');
    expect(within(nav).getByText('Translations').closest('a')).toHaveAttribute('href', '/admin/translations');
    expect(within(nav).getByText('Analytics').closest('a')).toHaveAttribute('href', '/admin/analytics');
    expect(within(nav).getByText('Rate Limits').closest('a')).toHaveAttribute('href', '/admin/rate-limits');
    expect(within(nav).getByText('Tenants').closest('a')).toHaveAttribute('href', '/admin/tenants');
  });

  it('renders the outlet for child routes', () => {
    render(<AdminPage />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
  });
});
