import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
const mockUseTheme = vi.fn();

vi.mock('../context/ThemeContext', () => ({
  useTheme: () => mockUseTheme(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'parkingPass.title': 'Parking Passes',
        'parkingPass.subtitle': 'Your digital parking passes',
        'parkingPass.help': 'Show this pass at the parking entrance for quick access. The QR code can be scanned to verify your booking.',
        'parkingPass.helpLabel': 'Help',
        'parkingPass.digitalPass': 'Digital Parking Pass',
        'parkingPass.slot': 'Slot',
        'parkingPass.validUntil': 'Valid until',
        'parkingPass.empty': 'No active parking passes',
        'parkingPass.status.active': 'Active',
        'parkingPass.status.expired': 'Expired',
        'parkingPass.status.revoked': 'Revoked',
        'parkingPass.status.used': 'Used',
        'common.close': 'Close',
      };
      return map[key] || key;
    },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: React.forwardRef(({ children, initial, animate, exit, transition, ...props }: any, ref: any) => (
      <div ref={ref} {...props}>{children}</div>
    )),
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

vi.mock('@phosphor-icons/react', () => ({
  Ticket: (props: any) => <span data-testid="icon-ticket" {...props} />,
  QrCode: (props: any) => <span data-testid="icon-qr" {...props} />,
  Clock: (props: any) => <span data-testid="icon-clock" {...props} />,
  MapPin: (props: any) => <span data-testid="icon-map-pin" {...props} />,
  Question: (props: any) => <span data-testid="icon-question" {...props} />,
  CalendarBlank: (props: any) => <span data-testid="icon-calendar" {...props} />,
}));

import { ParkingPassPage } from './ParkingPassView';

const samplePasses = [
  {
    id: 'p1',
    booking_id: 'b1',
    user_id: 'u1',
    user_name: 'Alice',
    lot_name: 'Garage Alpha',
    slot_number: '42',
    valid_from: '2026-03-22T08:00:00Z',
    valid_until: '2026-03-22T18:00:00Z',
    verification_code: 'abc123def456',
    qr_data: 'data:image/png;base64,iVBOR',
    status: 'active' as const,
    created_at: '2026-03-22T07:00:00Z',
  },
  {
    id: 'p2',
    booking_id: 'b2',
    user_id: 'u1',
    user_name: 'Alice',
    lot_name: 'Garage Beta',
    slot_number: '7',
    valid_from: '2026-03-23T09:00:00Z',
    valid_until: '2026-03-23T17:00:00Z',
    verification_code: 'xyz789',
    qr_data: 'data:image/png;base64,test',
    status: 'used' as const,
    created_at: '2026-03-23T08:00:00Z',
  },
];

describe('ParkingPassPage', () => {
  beforeEach(() => {
    mockUseTheme.mockReset();
    mockUseTheme.mockReturnValue({ designTheme: 'marble' });
    global.fetch = vi.fn(() =>
      Promise.resolve({
        json: () => Promise.resolve({ success: true, data: samplePasses }),
      } as Response)
    );
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders the page title', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => {
      expect(screen.getByText('Parking Passes')).toBeTruthy();
      expect(screen.getByTestId('parking-pass-shell')).toHaveAttribute('data-surface', 'marble');
    });
  });

  it('switches to the void surface when the void theme is active', async () => {
    mockUseTheme.mockReturnValue({ designTheme: 'void' });
    render(<ParkingPassPage />);
    await waitFor(() => {
      expect(screen.getByTestId('parking-pass-shell')).toHaveAttribute('data-surface', 'void');
    });
  });

  it('shows subtitle', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => expect(screen.getAllByText('Your digital parking passes').length).toBeGreaterThan(0));
  });

  it('displays passes after loading', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => {
      expect(screen.getByText('Garage Alpha')).toBeTruthy();
      expect(screen.getByText('Garage Beta')).toBeTruthy();
    });
  });

  it('shows help tooltip when clicking question icon', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => screen.getByText('Parking Passes'));
    fireEvent.click(screen.getByLabelText('Help'));
    await waitFor(() =>
      expect(screen.getAllByText(/Show this pass at the parking entrance/).length).toBeGreaterThan(0)
    );
  });

  it('shows full-screen pass when clicking a pass card', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => screen.getByText('Garage Alpha'));
    fireEvent.click(screen.getByText('Garage Alpha'));
    await waitFor(() => {
      expect(screen.getAllByText('Alice').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Digital Parking Pass').length).toBeGreaterThan(0);
      expect(screen.getAllByText('abc123def456').length).toBeGreaterThan(0);
    });
  });

  it('shows empty state when no passes', async () => {
    global.fetch = vi.fn(() =>
      Promise.resolve({
        json: () => Promise.resolve({ success: true, data: [] }),
      } as Response)
    );
    render(<ParkingPassPage />);
    await waitFor(() => expect(screen.getAllByText('No active parking passes').length).toBeGreaterThan(0));
  });

  it('closes full-screen pass after clicking close button', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => screen.getByText('Garage Alpha'));
    fireEvent.click(screen.getByText('Garage Alpha'));
    await waitFor(() => screen.getByText('Close'));
    fireEvent.click(screen.getByText('Close'));
    await waitFor(() => {
      expect(screen.queryByText('Close')).not.toBeInTheDocument();
    });
  });

  it('shows status badges', async () => {
    render(<ParkingPassPage />);
    await waitFor(() => {
      expect(screen.getByText('Active')).toBeTruthy();
      expect(screen.getByText('Used')).toBeTruthy();
    });
  });
});
