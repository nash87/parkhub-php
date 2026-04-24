import type { Meta, StoryObj } from '@storybook/react-vite';
import { UPlotChart } from './UPlotChart';
import type uPlot from 'uplot';

// Sparse: 6 data points (low-resolution day view).
const sparse: uPlot.AlignedData = [
  [0, 1, 2, 3, 4, 5],
  [12, 9, 14, 18, 22, 17],
];

// Normal: 24 data points (hourly window).
const hours = Array.from({ length: 24 }, (_, i) => i);
const normal: uPlot.AlignedData = [
  hours,
  hours.map((h) => Math.round(60 + Math.sin(h / 3) * 20 + Math.random() * 8)),
];

// Dense: 200 data points (~3 minute resolution).
const density = Array.from({ length: 200 }, (_, i) => i);
const dense: uPlot.AlignedData = [
  density,
  density.map((i) => Math.round(80 + Math.sin(i / 8) * 30 + Math.cos(i / 17) * 12)),
];

const meta: Meta<typeof UPlotChart> = {
  title: 'v5/Primitives/UPlotChart',
  component: UPlotChart,
  tags: ['autodocs'],
  parameters: {
    layout: 'padded',
  },
  argTypes: {
    height: { control: { type: 'range', min: 120, max: 400, step: 10 } },
    ariaLabel: { control: 'text' },
  },
  args: {
    height: 220,
    ariaLabel: 'Bookings over time',
  },
};
export default meta;

type Story = StoryObj<typeof UPlotChart>;

export const Sparse: Story = {
  args: { data: sparse },
  render: (args) => (
    <div style={{ width: 520 }}>
      <UPlotChart {...args} />
    </div>
  ),
};

export const Normal: Story = {
  args: { data: normal },
  render: (args) => (
    <div style={{ width: 520 }}>
      <UPlotChart {...args} />
    </div>
  ),
};

export const Dense: Story = {
  args: { data: dense },
  render: (args) => (
    <div style={{ width: 520 }}>
      <UPlotChart {...args} />
    </div>
  ),
};

export const Mobile: Story = {
  args: { data: normal, height: 180 },
  parameters: {
    viewport: { defaultViewport: 'mobile1' },
  },
  render: (args) => (
    <div style={{ width: 320 }}>
      <UPlotChart {...args} />
    </div>
  ),
};
