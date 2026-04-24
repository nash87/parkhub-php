import type { Meta, StoryObj } from '@storybook/react-vite';
import { V5Icon } from './index';
import { icons } from '../icons';

const meta: Meta<typeof V5Icon> = {
  title: 'v5/Primitives/V5Icon',
  component: V5Icon,
  tags: ['autodocs'],
  argTypes: {
    size: { control: { type: 'range', min: 8, max: 48, step: 1 } },
    strokeWidth: { control: { type: 'range', min: 0.5, max: 3, step: 0.1 } },
    color: { control: 'color' },
  },
  args: {
    size: 20,
    strokeWidth: 1.6,
    color: 'currentColor',
  },
};
export default meta;

type Story = StoryObj<typeof V5Icon>;

export const Single: Story = {
  args: {
    d: icons.check,
  },
};

export const MultiPath: Story = {
  args: {
    d: icons.trend,
  },
};

export const LargeStroke: Story = {
  args: {
    d: icons.plus,
    strokeWidth: 2.5,
    size: 32,
  },
};

export const AccentColor: Story = {
  args: {
    d: icons.bolt,
    color: 'var(--v5-acc)',
    size: 28,
  },
};
