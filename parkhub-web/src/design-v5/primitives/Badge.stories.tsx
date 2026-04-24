import type { Meta, StoryObj } from '@storybook/react-vite';
import { Badge, type BadgeVariant } from './index';

const VARIANTS: BadgeVariant[] = [
  'primary',
  'success',
  'warning',
  'error',
  'info',
  'gray',
  'ev',
  'purple',
];

const meta: Meta<typeof Badge> = {
  title: 'v5/Primitives/Badge',
  component: Badge,
  tags: ['autodocs'],
  argTypes: {
    variant: { control: 'select', options: VARIANTS },
    dot: { control: 'boolean' },
    children: { control: 'text' },
  },
  args: {
    variant: 'primary',
    dot: false,
    children: 'Active',
  },
};
export default meta;

type Story = StoryObj<typeof Badge>;

export const Primary: Story = {};

export const WithDot: Story = {
  args: { dot: true, variant: 'success', children: 'Live' },
};

export const AllVariants: Story = {
  render: () => (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
      {VARIANTS.map((variant) => (
        <Badge key={variant} variant={variant}>
          {variant}
        </Badge>
      ))}
    </div>
  ),
};

export const DotVariants: Story = {
  render: () => (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
      {VARIANTS.map((variant) => (
        <Badge key={variant} variant={variant} dot>
          {variant}
        </Badge>
      ))}
    </div>
  ),
};
