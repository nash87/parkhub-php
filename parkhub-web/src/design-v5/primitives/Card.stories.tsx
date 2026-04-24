import type { Meta, StoryObj } from '@storybook/react-vite';
import { Card, SectionLabel } from './index';

const meta: Meta<typeof Card> = {
  title: 'v5/Primitives/Card',
  component: Card,
  tags: ['autodocs'],
  argTypes: {
    lift: { control: 'boolean' },
    as: { control: 'select', options: ['div', 'button', 'a'] },
    className: { control: 'text' },
  },
  args: {
    lift: true,
    as: 'div',
  },
};
export default meta;

type Story = StoryObj<typeof Card>;

export const Default: Story = {
  render: (args) => (
    <Card {...args} style={{ padding: 16, minWidth: 280 }}>
      <SectionLabel>Overview</SectionLabel>
      <div style={{ fontSize: 14, color: 'var(--v5-txt)' }}>
        Surface card with default hover lift.
      </div>
    </Card>
  ),
};

export const NoPadding: Story = {
  render: (args) => (
    <Card {...args} style={{ minWidth: 280 }}>
      <div style={{ padding: 12, borderBottom: '1px solid var(--v5-bor)' }}>Row 1</div>
      <div style={{ padding: 12 }}>Row 2</div>
    </Card>
  ),
};

export const Clickable: Story = {
  args: {
    as: 'button',
    onClick: () => window.alert('clicked'),
  },
  render: (args) => (
    <Card {...args} style={{ padding: 16, minWidth: 280, textAlign: 'left', cursor: 'pointer' }}>
      <SectionLabel>Actionable</SectionLabel>
      <div style={{ fontSize: 14, color: 'var(--v5-txt)' }}>Click me — renders as &lt;button&gt;.</div>
    </Card>
  ),
};

export const NoLift: Story = {
  args: { lift: false },
  render: (args) => (
    <Card {...args} style={{ padding: 16, minWidth: 280 }}>
      <div style={{ fontSize: 14 }}>No hover animation.</div>
    </Card>
  ),
};
