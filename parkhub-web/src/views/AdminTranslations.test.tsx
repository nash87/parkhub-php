import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// ── Mocks ──

const mockGetTranslationProposals = vi.fn();
const mockReviewProposal = vi.fn();
const mockToastSuccess = vi.fn();
const mockToastError = vi.fn();

vi.mock('../api/client', () => ({
  api: {
    getTranslationProposals: (...args: any[]) => mockGetTranslationProposals(...args),
    reviewProposal: (...args: any[]) => mockReviewProposal(...args),
  },
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
  Translate:       (props: any) => <span data-testid="icon-translate" {...props} />,
  SpinnerGap:      (props: any) => <span data-testid="icon-spinner" {...props} />,
  Check:           (props: any) => <span data-testid="icon-check" {...props} />,
  X:               (props: any) => <span data-testid="icon-x" {...props} />,
  Clock:           (props: any) => <span data-testid="icon-clock" {...props} />,
  Eye:             (props: any) => <span data-testid="icon-eye" {...props} />,
  ThumbsUp:        (props: any) => <span data-testid="icon-thumbs-up" {...props} />,
  ThumbsDown:      (props: any) => <span data-testid="icon-thumbs-down" {...props} />,
  ChatCircleDots:  (props: any) => <span data-testid="icon-chat" {...props} />,
  ArrowsClockwise: (props: any) => <span data-testid="icon-refresh" {...props} />,
  CheckCircle:     (props: any) => <span data-testid="icon-check-circle" {...props} />,
  XCircle:         (props: any) => <span data-testid="icon-x-circle" {...props} />,
  MagnifyingGlass: (props: any) => <span data-testid="icon-search" {...props} />,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => {
      const map: Record<string, string> = {
        'translations.admin.title': 'Translation Proposals',
        'translations.admin.pendingReview': 'pending review',
        'translations.admin.approveAll': 'Approve All',
        'translations.admin.rejectAll': 'Reject All',
        'translations.admin.approve': 'Approve',
        'translations.admin.reject': 'Reject',
        'translations.admin.approved': 'Approved',
        'translations.admin.rejected': 'Rejected',
        'translations.admin.reviewProposal': 'Review Proposal',
        'translations.admin.comment': 'Comment',
        'translations.admin.commentPlaceholder': 'Optional comment',
        'translations.admin.proposedBy': 'Proposed By',
        'translations.admin.reviewDetail': 'Review Detail',
        'translations.admin.searchProposals': 'Search proposals',
        'translations.admin.confirmBulkApprove': 'Confirm bulk action',
        'translations.admin.bulkComplete': 'Bulk action complete',
        'translations.keyLabel': 'Key',
        'translations.current': 'Current',
        'translations.proposed': 'Proposed',
        'translations.proposedBy': 'Proposed by',
        'translations.score': 'Score',
        'translations.filterStatus': 'Filter by status',
        'translations.allStatuses': 'All Statuses',
        'translations.statusPending': 'Pending',
        'translations.statusApproved': 'Approved',
        'translations.statusRejected': 'Rejected',
        'translations.noProposals': 'No proposals found',
        'translations.empty': '(empty)',
        'admin.status': 'Status',
        'common.refresh': 'Refresh',
        'common.cancel': 'Cancel',
        'common.close': 'Close',
        'common.error': 'Error',
        'ui.confirmAction': 'Confirm',
      };
      return map[key] || fallback || key;
    },
  }),
}));

vi.mock('react-hot-toast', () => ({
  default: {
    success: (...args: any[]) => mockToastSuccess(...args),
    error: (...args: any[]) => mockToastError(...args),
  },
}));

vi.mock('../components/ui/DataTable', () => ({
  DataTable: ({ data, emptyMessage }: any) =>
    data.length === 0
      ? <div data-testid="data-table-empty">{emptyMessage}</div>
      : <div data-testid="data-table">{data.map((row: any) => <div key={row.id} data-testid="proposal-row">{row.key}</div>)}</div>,
}));

vi.mock('../components/ui/ConfirmDialog', () => ({
  ConfirmDialog: ({ open, title }: any) =>
    open ? <div data-testid="confirm-dialog">{title}</div> : null,
}));

import { AdminTranslationsPage } from './AdminTranslations';

const sampleProposals = [
  {
    id: 'p1',
    key: 'nav.home',
    language: 'de',
    current_value: 'Startseite',
    proposed_value: 'Zuhause',
    proposed_by: 'u1',
    proposed_by_name: 'Alice',
    votes_for: 3,
    votes_against: 1,
    status: 'pending' as const,
    context: null,
    reviewer_name: null,
    created_at: '2026-03-01T10:00:00Z',
    updated_at: '2026-03-01T10:00:00Z',
  },
  {
    id: 'p2',
    key: 'nav.bookings',
    language: 'fr',
    current_value: 'Réservations',
    proposed_value: 'Mes Réservations',
    proposed_by: 'u2',
    proposed_by_name: 'Bob',
    votes_for: 5,
    votes_against: 0,
    status: 'approved' as const,
    context: 'More specific label',
    reviewer_name: 'Admin',
    created_at: '2026-03-02T10:00:00Z',
    updated_at: '2026-03-02T10:00:00Z',
  },
];

describe('AdminTranslationsPage', () => {
  beforeEach(() => {
    mockGetTranslationProposals.mockClear();
    mockReviewProposal.mockClear();
    mockToastSuccess.mockClear();
    mockToastError.mockClear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders the page title after loading', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Translation Proposals')).toBeInTheDocument();
    });
  });

  it('shows empty state when no proposals', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByTestId('data-table-empty')).toBeInTheDocument();
      expect(screen.getByText('No proposals found')).toBeInTheDocument();
    });
  });

  it('renders proposal rows in the table', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: sampleProposals });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByTestId('data-table')).toBeInTheDocument();
      const rows = screen.getAllByTestId('proposal-row');
      expect(rows).toHaveLength(2);
    });
  });

  it('renders search input', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByPlaceholderText('Search proposals')).toBeInTheDocument();
    });
  });

  it('renders status filter dropdown', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByRole('combobox')).toBeInTheDocument();
    });
  });

  it('shows pending count badge when there are pending proposals', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: sampleProposals });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText(/pending review/i)).toBeInTheDocument();
    });
  });

  it('shows Approve All and Reject All buttons when there are pending proposals', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: sampleProposals });
    render(<AdminTranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Approve All')).toBeInTheDocument();
      expect(screen.getByText('Reject All')).toBeInTheDocument();
    });
  });

  it('filters proposals via search input', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: sampleProposals });
    const user = userEvent.setup();
    render(<AdminTranslationsPage />);

    await waitFor(() => {
      expect(screen.getByTestId('data-table')).toBeInTheDocument();
    });

    const search = screen.getByPlaceholderText('Search proposals');
    await user.type(search, 'nav.home');
    // Searching should not cause a crash
    expect(screen.getByPlaceholderText('Search proposals')).toHaveValue('nav.home');
  });
});
