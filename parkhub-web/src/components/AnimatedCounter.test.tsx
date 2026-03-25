import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, act } from '@testing-library/react';

import { AnimatedCounter } from './AnimatedCounter';

describe('AnimatedCounter', () => {
  beforeEach(() => {
    // Stub rAF so the animation completes in a single frame by reporting
    // a timestamp that is well past the animation duration (>= 1000 ms).
    const startTime = performance.now();
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      // Advance time by 2000 ms so progress >= 1 and the loop stops.
      cb(startTime + 2000);
      return 0;
    });
    vi.stubGlobal('cancelAnimationFrame', () => {});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('renders a span element', () => {
    const { container } = render(<AnimatedCounter value={0} />);
    expect(container.querySelector('span')).not.toBeNull();
  });

  it('displays 0 when value is 0', () => {
    render(<AnimatedCounter value={0} />);
    expect(screen.getByText('0')).toBeInTheDocument();
  });

  it('animates to the target value', async () => {
    await act(async () => {
      render(<AnimatedCounter value={42} />);
    });
    expect(screen.getByText('42')).toBeInTheDocument();
  });

  it('accepts a custom className', () => {
    const { container } = render(<AnimatedCounter value={10} className="my-counter" />);
    const span = container.querySelector('.my-counter');
    expect(span).not.toBeNull();
  });

  it('accepts a custom duration prop', async () => {
    await act(async () => {
      render(<AnimatedCounter value={5} duration={500} />);
    });
    expect(screen.getByText('5')).toBeInTheDocument();
  });
});
