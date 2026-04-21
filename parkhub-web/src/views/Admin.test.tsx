import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render, screen, within } from '@testing-library/react';

vi.mock('react-router-dom', () => ({
  Link: ({ to, children, ...props }: any) => <a href={to} {...props}>{children}</a>,
  Outlet: () => <div data-testid="outlet">Outlet Content</div>,
  useLocation: () => ({ pathname: '/admin' }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => {
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
      return map[key] ?? fallback ?? key;
    },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: React.forwardRef(({ children, transition, layoutId, ...props }: any, ref: any) => (
      <div ref={ref} {...props}>{children}</div>
    )),
    aside: React.forwardRef(({ children, transition, layoutId, ...props }: any, ref: any) => (
      <aside ref={ref} {...props}>{children}</aside>
    )),
    span: React.forwardRef(({ children, transition, layoutId, ...props }: any, ref: any) => (
      <span ref={ref} {...props}>{children}</span>
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
  List: (props: any) => <span data-testid="icon-list" {...props} />,
  X: (props: any) => <span data-testid="icon-x" {...props} />,
  ArrowSquareOut: (props: any) => <span data-testid="icon-arrow-square-out" {...props} />,
}));

import { AdminPage } from './Admin';

describe('AdminPage', () => {
  it('renders the desktop admin heading copy', () => {
    render(<AdminPage />);
    expect(screen.getAllByText('Administration')).not.toHaveLength(0);
    expect(screen.getByText('Manage your ParkHub instance')).toBeInTheDocument();
  });

  it('renders grouped desktop navigation sections instead of a flat scrolling tab row', () => {
    render(<AdminPage />);
    const nav = screen.getByLabelText('Admin navigation');

    expect(within(nav).getAllByText('Overview')).not.toHaveLength(0);
    expect(within(nav).getByText('Operations')).toBeInTheDocument();
    expect(within(nav).getByText('People & Access')).toBeInTheDocument();
    expect(within(nav).getByText('Compliance & Data')).toBeInTheDocument();
    expect(within(nav).getByText('Billing & Reports')).toBeInTheDocument();
    expect(within(nav).getByText('Platform')).toBeInTheDocument();
  });

  it('keeps the key admin links with the correct paths', () => {
    render(<AdminPage />);
    const nav = screen.getByLabelText('Admin navigation');

    expect(within(nav).getAllByText('Overview').at(-1)?.closest('a')).toHaveAttribute('href', '/admin');
    expect(within(nav).getByText('Settings').closest('a')).toHaveAttribute('href', '/admin/settings');
    expect(within(nav).getByText('Users').closest('a')).toHaveAttribute('href', '/admin/users');
    expect(within(nav).getByText('Parking Lots').closest('a')).toHaveAttribute('href', '/admin/lots');
    expect(within(nav).getByText('Rate Limits').closest('a')).toHaveAttribute('href', '/admin/rate-limits');
    expect(within(nav).getByText('Tenants').closest('a')).toHaveAttribute('href', '/admin/tenants');
  });

  it('renders the mobile drawer trigger with the current section label', () => {
    render(<AdminPage />);

    expect(screen.getByLabelText('Open admin navigation')).toBeInTheDocument();
    expect(screen.getAllByText('Administration')[0]).toBeInTheDocument();
  });

  it('renders the outlet for child routes', () => {
    render(<AdminPage />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
  });

  it('shows the external GraphQL playground link in platform navigation', () => {
    render(<AdminPage />);

    const graphqlLinks = screen.getAllByRole('link').filter(link => link.getAttribute('href') === '/api/v1/graphql/playground');
    expect(graphqlLinks).not.toHaveLength(0);
    expect(within(graphqlLinks[0]).getByText('GraphQL Playground')).toBeInTheDocument();
  });
});
