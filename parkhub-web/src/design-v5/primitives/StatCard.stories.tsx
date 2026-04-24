import type { Meta, StoryObj } from '@storybook/react-vite';
import { StatCard } from './index';

const meta: Meta<typeof StatCard> = {
  title: 'v5/Primitives/StatCard',
  component: StatCard,
  tags: ['autodocs'],
  argTypes: {
    label: { control: 'text' },
    value: { control: 'text' },
    sub: { control: 'text' },
    accent: { control: 'boolean' },
    icon: {
      control: 'select',
      options: ['credit', 'bolt', 'trend', 'car', 'predict', 'rank', 'analytics'],
    },
  },
  args: {
    label: 'Revenue',
    value: '$4,280',
    sub: '+12.4% vs last week',
    accent: false,
    icon: 'trend',
  },
};
export default meta;

type Story = StoryObj<typeof StatCard>;

export const Default: Story = {};

export const Accent: Story = {
  args: { accent: true, label: 'Credits', value: '240', icon: 'credit' },
};

export const WithoutIcon: Story = {
  args: { icon: undefined },
};

export const WithoutSub: Story = {
  args: { sub: undefined },
};

export const Grid: Story = {
  render: () => (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(180px, 220px))',
        gap: 12,
      }}
    >
      <StatCard label="Revenue" value="$4,280" sub="+12.4%" icon="trend" />
      <StatCard label="Bookings" value="128" sub="Today" icon="cal" />
      <StatCard label="Credits" value="240" sub="Available" icon="credit" accent />
      <StatCard label="EV Sessions" value="32" sub="Active" icon="bolt" />
    </div>
  ),
};
