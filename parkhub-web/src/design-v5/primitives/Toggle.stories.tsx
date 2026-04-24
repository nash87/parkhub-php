import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { Toggle } from './index';

const meta: Meta<typeof Toggle> = {
  title: 'v5/Primitives/Toggle',
  component: Toggle,
  tags: ['autodocs'],
  argTypes: {
    checked: { control: 'boolean' },
    ariaLabel: { control: 'text' },
  },
  args: {
    checked: true,
    ariaLabel: 'Feature switch',
  },
};
export default meta;

type Story = StoryObj<typeof Toggle>;

export const On: Story = {
  args: { checked: true },
  render: (args) => {
    const [on, setOn] = useState(args.checked);
    return <Toggle {...args} checked={on} onChange={setOn} />;
  },
};

export const Off: Story = {
  args: { checked: false },
  render: (args) => {
    const [on, setOn] = useState(args.checked);
    return <Toggle {...args} checked={on} onChange={setOn} />;
  },
};

export const Disabled: Story = {
  args: { checked: true },
  // `onChange` omitted → disabled styling
  render: (args) => <Toggle checked={args.checked} ariaLabel={args.ariaLabel} />,
};
