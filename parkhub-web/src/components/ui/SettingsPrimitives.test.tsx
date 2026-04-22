import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import {
  NAV_LAYOUTS,
  NavLayoutGrid,
  SCard,
  SRow,
  SSeg,
  SToggle,
  ThemeSwatches,
} from './SettingsPrimitives';

describe('SettingsPrimitives', () => {
  it('renders section cards and labelled rows with lock badges', () => {
    render(
      <SCard
        id="appearance"
        title="Appearance"
        subtitle="Theme and density"
        actions={<button type="button">Reset</button>}
      >
        <SRow title="Theme" description="Accent palette" lockedBy="Policy">
          <span>Control</span>
        </SRow>
      </SCard>,
    );

    expect(screen.getByRole('heading', { name: 'Appearance' })).toBeInTheDocument();
    expect(screen.getByText('Theme and density')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
    expect(screen.getByText('Theme')).toBeInTheDocument();
    expect(screen.getByText('Accent palette')).toBeInTheDocument();
    expect(screen.getByText('Policy')).toHaveAttribute('title', 'Enforced by Policy');
    expect(screen.getByText('Control')).toBeInTheDocument();
  });

  it('changes segmented controls, switches, and swatches', async () => {
    const user = userEvent.setup();
    const onSegChange = vi.fn();
    const onToggleChange = vi.fn();
    const onSwatchChange = vi.fn();

    render(
      <>
        <SSeg
          value="cozy"
          onChange={onSegChange}
          options={[
            { value: 'compact', label: 'Compact' },
            { value: 'cozy', label: 'Cozy' },
          ]}
        />
        <SToggle value={false} onChange={onToggleChange} label="Demo mode" />
        <ThemeSwatches
          value="ocean"
          onChange={onSwatchChange}
          options={[
            { value: 'ocean', label: 'Ocean', color: '#0ea5e9' },
            { value: 'sunset', label: 'Sunset', color: '#f97316' },
          ]}
        />
      </>,
    );

    await user.click(screen.getByRole('radio', { name: 'Compact' }));
    await user.click(screen.getByRole('switch', { name: 'Demo mode' }));
    await user.click(screen.getByRole('radio', { name: 'Sunset' }));

    expect(onSegChange).toHaveBeenCalledWith('compact');
    expect(onToggleChange).toHaveBeenCalledWith(true);
    expect(onSwatchChange).toHaveBeenCalledWith('sunset');
  });

  it('renders all live nav layouts as selectable options', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    render(<NavLayoutGrid value="classic" onChange={onChange} />);

    expect(NAV_LAYOUTS).toHaveLength(5);

    const classic = screen.getByRole('radio', { name: /Classic sidebar/i });
    const rail = screen.getByRole('radio', { name: /Icon rail/i });
    const top = screen.getByRole('radio', { name: /Top tabs/i });
    const dock = screen.getByRole('radio', { name: /Floating dock/i });
    const focus = screen.getByRole('radio', { name: /Focus/i });

    expect(classic).not.toBeDisabled();
    expect(rail).not.toBeDisabled();
    expect(top).not.toBeDisabled();
    expect(dock).not.toBeDisabled();
    expect(focus).not.toBeDisabled();

    await user.click(classic);
    await user.click(rail);
    await user.click(top);
    await user.click(dock);
    await user.click(focus);

    expect(onChange).toHaveBeenCalledWith('classic');
    expect(onChange).toHaveBeenCalledWith('rail');
    expect(onChange).toHaveBeenCalledWith('top');
    expect(onChange).toHaveBeenCalledWith('dock');
    expect(onChange).toHaveBeenCalledWith('focus');
  });
});
