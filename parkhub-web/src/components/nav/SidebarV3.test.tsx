/**
 * SidebarV3 smoke tests.
 *
 * The component is intentionally stateful and data-driven, so the test
 * surface here stays narrow: verify the focus shell renders on empty data
 * and that the live-pass card appears for an active booking.
 *
 * Note on timing: the GitHub Actions hosted runners are consistently slower
 * at transforming + executing this 1300-line component than the local dev
 * machine. Two hardening moves keep this suite reliable in CI:
 *   - Static top-level import (vs. per-test `await import(...)`) so the
 *     component is parsed exactly once and react-router + phosphor-icons
 *     don't pay the Vite transform tax inside every `it` block.
 *   - 15 s waitFor timeout to absorb CI variance when the render pipeline
 *     queues behind other suites running in parallel.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { Booking, ParkingLot, ParkingSlot } from '../../api/client';

const { getBookingsMock, getLotsMock, getLotSlotsMock } = vi.hoisted(() => ({
  getBookingsMock: vi.fn(),
  getLotsMock: vi.fn(),
  getLotSlotsMock: vi.fn(),
}));

vi.mock('../../api/client', () => ({
  api: {
    getBookings: getBookingsMock,
    getLots: getLotsMock,
    getLotSlots: getLotSlotsMock,
  },
}));

vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({
    user: {
      id: 'u1',
      username: 'florian',
      email: 'florian@example.com',
      name: 'Florian Bauer',
      role: 'user',
    },
    loading: false,
    logout: vi.fn(),
  }),
}));

// Static import — depends on `vi.mock` hoisting by Vitest, not runtime
// dynamic import. This cuts ~8 s of redundant Vite transform per `it` block
// in CI while keeping the mock graph correct.
import { SidebarV3 } from './SidebarV3';

function ok<T>(data: T) {
  return Promise.resolve({ success: true, data } as const);
}

const WAIT_FOR_OPTS = { timeout: 15_000 } as const;

describe('SidebarV3', () => {
  beforeEach(() => {
    getBookingsMock.mockReset();
    getLotsMock.mockReset();
    getLotSlotsMock.mockReset();
  });

  it('renders without crashing when all API responses are empty', async () => {
    getBookingsMock.mockReturnValue(ok<Booking[]>([]));
    getLotsMock.mockReturnValue(ok<ParkingLot[]>([]));
    getLotSlotsMock.mockReturnValue(ok<ParkingSlot[]>([]));

    render(
      <MemoryRouter>
        <SidebarV3 />
      </MemoryRouter>,
    );

    await waitFor(() => {
      expect(screen.getByText(/No active booking/i)).toBeInTheDocument();
    }, WAIT_FOR_OPTS);
    expect(screen.getByText(/Book now/i)).toBeInTheDocument();
    expect(screen.getByText('Today')).toBeInTheDocument();
    expect(screen.getByText('Book')).toBeInTheDocument();
  }, 20_000);

  it('renders the Live Pass card for an active booking', async () => {
    const now = new Date();
    const start = new Date(now.getTime() - 60 * 60_000).toISOString();
    const end = new Date(now.getTime() + 2 * 60 * 60_000).toISOString();

    const booking: Booking = {
      id: 'b1',
      user_id: 'u1',
      lot_id: 'lot-hq',
      slot_id: 's1',
      lot_name: 'HQ — Linden St',
      slot_number: 'L1-17',
      vehicle_plate: 'M-AB 7823',
      start_time: start,
      end_time: end,
      status: 'active',
    };

    const lot: ParkingLot = {
      id: 'lot-hq',
      name: 'HQ — Linden St',
      total_slots: 184,
      available_slots: 27,
      status: 'open',
    };

    getBookingsMock.mockReturnValue(ok<Booking[]>([booking]));
    getLotsMock.mockReturnValue(ok<ParkingLot[]>([lot]));
    getLotSlotsMock.mockReturnValue(ok<ParkingSlot[]>([]));

    render(
      <MemoryRouter>
        <SidebarV3 />
      </MemoryRouter>,
    );

    await waitFor(() => {
      expect(screen.getByText('Parked')).toBeInTheDocument();
    }, WAIT_FOR_OPTS);
    expect(screen.getByText(/M-AB 7823/)).toBeInTheDocument();
    expect(screen.getByText('Show QR')).toBeInTheDocument();
  }, 20_000);
});
