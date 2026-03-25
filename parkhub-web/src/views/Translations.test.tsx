import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// ── Mocks ──

const mockGetTranslationProposals = vi.fn();
const mockCreateTranslationProposal = vi.fn();
const mockVoteOnProposal = vi.fn();
const mockToastSuccess = vi.fn();
const mockToastError = vi.fn();

vi.mock('../api/client', () => ({
  api: {
    getTranslationProposals: (...args: any[]) => mockGetTranslationProposals(...args),
    createTranslationProposal: (...args: any[]) => mockCreateTranslationProposal(...args),
    voteOnProposal: (...args: any[]) => mockVoteOnProposal(...args),
  },
}));

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 'u1', name: 'Test User' } }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => {
      const map: Record<string, string> = {
        'translations.title': 'Community Translations',
        'translations.subtitle': 'Help translate ParkHub into your language',
        'translations.proposals': 'Proposals',
        'translations.browseKeys': 'Browse Keys',
        'translations.newProposal': 'New Proposal',
        'translations.propose': 'Suggest Translation',
        'translations.noProposals': 'No proposals yet',
        'translations.proposalsCount': 'proposals',
        'translations.keyLabel': 'Key',
        'translations.value': 'Value',
        'translations.current': 'Current',
        'translations.proposed': 'Proposed',
        'translations.proposedBy': 'Proposed by',
        'translations.suggestChange': 'Suggest Change',
        'translations.submitProposal': 'Submit Proposal',
        'translations.statusPending': 'Pending',
        'translations.statusApproved': 'Approved',
        'translations.statusRejected': 'Rejected',
        'translations.filterStatus': 'Filter by status',
        'translations.allStatuses': 'All Statuses',
        'translations.search': 'Search translations',
        'translations.searchKeys': 'Search keys or values',
        'translations.language': 'Language',
        'translations.selectLanguage': 'Select language',
        'translations.proposedValue': 'Proposed Value',
        'translations.currentValue': 'Current Value',
        'translations.contextLabel': 'Context',
        'translations.contextPlaceholder': 'Optional context',
        'translations.enterTranslation': 'Enter translation',
        'translations.empty': '(empty)',
        'translations.score': 'score',
        'translations.voteFor': 'Vote for',
        'translations.voteAgainst': 'Vote against',
        'translations.showingFirst': 'Showing first 100',
        'common.close': 'Close',
        'common.cancel': 'Cancel',
      };
      return map[key] || fallback || key;
    },
    i18n: {
      language: 'en',
      getResourceBundle: (_lang: string, _ns: string) => ({
        nav: { home: 'Home', bookings: 'Bookings' },
      }),
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
  Translate:      (props: any) => <span data-testid="icon-translate" {...props} />,
  MagnifyingGlass:(props: any) => <span data-testid="icon-search" {...props} />,
  ThumbsUp:       (props: any) => <span data-testid="icon-thumbs-up" {...props} />,
  ThumbsDown:     (props: any) => <span data-testid="icon-thumbs-down" {...props} />,
  SpinnerGap:     (props: any) => <span data-testid="icon-spinner" {...props} />,
  PaperPlaneTilt: (props: any) => <span data-testid="icon-send" {...props} />,
  X:              (props: any) => <span data-testid="icon-x" {...props} />,
  Check:          (props: any) => <span data-testid="icon-check" {...props} />,
  Clock:          (props: any) => <span data-testid="icon-clock" {...props} />,
  ChatCircleDots: (props: any) => <span data-testid="icon-chat" {...props} />,
}));

vi.mock('react-hot-toast', () => ({
  default: {
    success: (...args: any[]) => mockToastSuccess(...args),
    error: (...args: any[]) => mockToastError(...args),
  },
}));

import { TranslationsPage } from './Translations';

const sampleProposals = [
  {
    id: 'p1',
    key: 'nav.home',
    language: 'en',
    current_value: 'Home',
    proposed_value: 'Main',
    proposed_by: 'u2',
    proposed_by_name: 'Bob',
    votes_for: 2,
    votes_against: 0,
    status: 'pending' as const,
    user_vote: null,
    context: null,
    review_comment: null,
    reviewer_name: null,
    created_at: '2026-03-01T10:00:00Z',
    updated_at: '2026-03-01T10:00:00Z',
  },
];

describe('TranslationsPage', () => {
  beforeEach(() => {
    mockGetTranslationProposals.mockClear();
    mockCreateTranslationProposal.mockClear();
    mockVoteOnProposal.mockClear();
    mockToastSuccess.mockClear();
    mockToastError.mockClear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders the page title', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<TranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Translations')).toBeInTheDocument();
    });
  });

  it('renders Proposals and Browse Keys tabs', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<TranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Proposals')).toBeInTheDocument();
    });
    expect(screen.getByText('Browse Keys')).toBeInTheDocument();
  });

  it('shows loading spinner initially', () => {
    mockGetTranslationProposals.mockReturnValue(new Promise(() => {}));
    render(<TranslationsPage />);
    expect(screen.getByTestId('icon-spinner')).toBeInTheDocument();
  });

  it('shows empty state when no proposals', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<TranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('No proposals yet')).toBeInTheDocument();
    });
  });

  it('renders proposals when data is loaded', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: sampleProposals });
    render(<TranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('nav.home')).toBeInTheDocument();
    });
  });

  it('shows Suggest Translation button', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    render(<TranslationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Suggest Translation')).toBeInTheDocument();
    });
  });

  it('opens proposal form when clicking Suggest Translation', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    const user = userEvent.setup();
    render(<TranslationsPage />);

    await waitFor(() => {
      expect(screen.getByText('Suggest Translation')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Suggest Translation'));

    expect(screen.getByText('New Proposal')).toBeInTheDocument();
    expect(screen.getByText('Submit Proposal')).toBeInTheDocument();
  });

  it('switches to Browse Keys tab when clicked', async () => {
    mockGetTranslationProposals.mockResolvedValue({ success: true, data: [] });
    const user = userEvent.setup();
    render(<TranslationsPage />);

    await waitFor(() => {
      expect(screen.getByText('Proposals')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Browse Keys'));
    expect(screen.getByText('Key')).toBeInTheDocument();
    expect(screen.getByText('Value')).toBeInTheDocument();
  });
});
