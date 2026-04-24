import type { Meta, StoryObj } from '@storybook/react-vite';
import { Card, SectionLabel } from './index';

const meta: Meta<typeof SectionLabel> = {
  title: 'v5/Primitives/SectionLabel',
  component: SectionLabel,
  tags: ['autodocs'],
  argTypes: {
    children: { control: 'text' },
  },
  args: {
    children: 'OVERVIEW',
  },
};
export default meta;

type Story = StoryObj<typeof SectionLabel>;

export const Default: Story = {};

export const InCard: Story = {
  render: (args) => (
    <Card style={{ padding: 16, minWidth: 320 }}>
      <SectionLabel {...args} />
      <div style={{ fontSize: 14, color: 'var(--v5-txt)' }}>
        Content sits below the label with 8px gap.
      </div>
    </Card>
  ),
};

export const MultipleSections: Story = {
  render: () => (
    <Card style={{ padding: 16, minWidth: 320 }}>
      <SectionLabel>Today</SectionLabel>
      <div style={{ fontSize: 14 }}>12 bookings</div>
      <div style={{ height: 14 }} />
      <SectionLabel>This week</SectionLabel>
      <div style={{ fontSize: 14 }}>82 bookings</div>
    </Card>
  ),
};
