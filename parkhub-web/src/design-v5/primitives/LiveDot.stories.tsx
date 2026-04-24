import type { Meta, StoryObj } from '@storybook/react-vite';
import { LiveDot } from './index';

const meta: Meta<typeof LiveDot> = {
  title: 'v5/Primitives/LiveDot',
  component: LiveDot,
  tags: ['autodocs'],
  argTypes: {
    color: { control: 'text' },
  },
  args: {
    color: 'var(--v5-ok)',
  },
};
export default meta;

type Story = StoryObj<typeof LiveDot>;

export const Connected: Story = {
  args: { color: 'var(--v5-ok)' },
  render: (args) => (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
      <LiveDot {...args} /> Connected
    </span>
  ),
};

export const Disconnected: Story = {
  args: { color: 'var(--v5-err)' },
  render: (args) => (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
      <LiveDot {...args} /> Disconnected
    </span>
  ),
};

export const Warning: Story = {
  args: { color: 'var(--v5-warn)' },
  render: (args) => (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
      <LiveDot {...args} /> Degraded
    </span>
  ),
};
