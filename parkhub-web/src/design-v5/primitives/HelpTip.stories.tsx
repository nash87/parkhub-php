import type { Meta, StoryObj } from '@storybook/react-vite';
import { HelpTip } from './HelpTip';

const meta: Meta<typeof HelpTip> = {
  title: 'v5/Primitives/HelpTip',
  component: HelpTip,
  tags: ['autodocs'],
  argTypes: {
    label: { control: 'text' },
    placement: { control: 'radio', options: ['top', 'bottom'] },
  },
  args: {
    label: 'What is this?',
    placement: 'top',
  },
};
export default meta;

type Story = StoryObj<typeof HelpTip>;

export const Top: Story = {
  render: (args) => (
    <div style={{ padding: '80px 40px', display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ fontSize: 13 }}>Revenue share</span>
      <HelpTip {...args}>
        Your share of each completed booking after payment processing fees.
      </HelpTip>
    </div>
  ),
};

export const Bottom: Story = {
  args: { placement: 'bottom' },
  render: (args) => (
    <div style={{ padding: '40px 40px 80px', display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ fontSize: 13 }}>Credit balance</span>
      <HelpTip {...args}>Credits never expire and can be applied to any future booking.</HelpTip>
    </div>
  ),
};

export const RichContent: Story = {
  render: (args) => (
    <div style={{ padding: '80px 40px', display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ fontSize: 13 }}>Occupancy</span>
      <HelpTip {...args}>
        <strong>Peak</strong> occupancy measured on a rolling 15-minute window.
        Refreshed every 30 s from the gateway.
      </HelpTip>
    </div>
  ),
};
