import type { Meta, StoryObj } from '@storybook/react-vite';
import { V5NamedIcon } from './index';
import { icons, type IconKey } from '../icons';

const ALL_ICONS = Object.keys(icons) as IconKey[];

const meta: Meta<typeof V5NamedIcon> = {
  title: 'v5/Primitives/V5NamedIcon',
  component: V5NamedIcon,
  tags: ['autodocs'],
  argTypes: {
    name: { control: 'select', options: ALL_ICONS },
    size: { control: { type: 'range', min: 8, max: 48, step: 1 } },
    strokeWidth: { control: { type: 'range', min: 0.5, max: 3, step: 0.1 } },
    color: { control: 'color' },
  },
  args: {
    name: 'bell',
    size: 20,
    strokeWidth: 1.6,
    color: 'currentColor',
  },
};
export default meta;

type Story = StoryObj<typeof V5NamedIcon>;

export const Default: Story = {};

export const Close: Story = {
  args: { name: 'x', size: 16 },
};

export const Plus: Story = {
  args: { name: 'plus', size: 24 },
};

export const IconRegistry: Story = {
  render: () => (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(72px, 1fr))',
        gap: 12,
        maxWidth: 520,
      }}
    >
      {ALL_ICONS.map((name) => (
        <div
          key={name}
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: 4,
            padding: 8,
            border: '1px solid var(--v5-bor)',
            borderRadius: 8,
            background: 'var(--v5-sur)',
          }}
        >
          <V5NamedIcon name={name} size={20} />
          <span
            className="v5-mono"
            style={{ fontSize: 9, color: 'var(--v5-mut)', letterSpacing: 0.6 }}
          >
            {name}
          </span>
        </div>
      ))}
    </div>
  ),
};

export const Sizes: Story = {
  render: () => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
      {[12, 16, 20, 24, 32, 40].map((size) => (
        <V5NamedIcon key={size} name="gear" size={size} />
      ))}
    </div>
  ),
};
