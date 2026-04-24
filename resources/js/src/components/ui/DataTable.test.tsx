import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createColumnHelper } from '@tanstack/react-table';
import { DataTable } from './DataTable';

const mockPdfDownload = vi.fn();
vi.mock('../../utils/exportTable', () => ({
  downloadPdfTable: (...args: any[]) => mockPdfDownload(...args),
}));

vi.mock('@phosphor-icons/react', () => ({
  CaretUp: (props: any) => <span data-testid="icon-up" {...props} />,
  CaretDown: (props: any) => <span data-testid="icon-down" {...props} />,
  DownloadSimple: (props: any) => <span data-testid="icon-dl" {...props} />,
  FilePdf: (props: any) => <span data-testid="icon-pdf" {...props} />,
}));

vi.mock('framer-motion', () => ({
  motion: {
    tr: ({ children, ...p }: any) => <tr {...p}>{children}</tr>,
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

interface Row { id: string; name: string; count: number }

const columnHelper = createColumnHelper<Row>();
const columns = [
  columnHelper.accessor('name', { header: () => 'name' }),
  columnHelper.accessor('count', { header: () => 'count' }),
];

const data: Row[] = [
  { id: '1', name: 'Alpha', count: 3 },
  { id: '2', name: 'Beta', count: 7 },
];

describe('DataTable — Tier-2 item 10', () => {
  beforeEach(() => { mockPdfDownload.mockClear(); });

  it('renders "Als CSV" and "Als PDF" buttons when exportFilename is set', () => {
    render(<DataTable data={data} columns={columns} exportFilename="rows" />);
    expect(screen.getByTestId('export-csv-rows')).toBeInTheDocument();
    expect(screen.getByTestId('export-pdf-rows')).toBeInTheDocument();
  });

  it('clicking "Als PDF" invokes downloadPdfTable with the visible rows', async () => {
    const user = userEvent.setup();
    mockPdfDownload.mockResolvedValue(undefined);
    render(<DataTable data={data} columns={columns} exportFilename="rows" />);
    await user.click(screen.getByTestId('export-pdf-rows'));
    expect(mockPdfDownload).toHaveBeenCalledTimes(1);
    const [filename, title, headers, rows] = mockPdfDownload.mock.calls[0];
    expect(filename).toBe('rows');
    expect(title).toBe('rows');
    expect(headers).toEqual(['name', 'count']);
    expect(rows).toEqual([['Alpha', 3], ['Beta', 7]]);
  });
});
