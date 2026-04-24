import type { Preview } from '@storybook/react-vite';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React, { useEffect } from 'react';

// v5 design tokens + self-hosted fonts — loaded once for every story.
import '../src/design-v5/tokens.css';
import '../src/design-v5/fonts';

import { V5ThemeProvider, type V5Mode } from '../src/design-v5/ThemeProvider';
import { V5ToastProvider } from '../src/design-v5/Toast';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false, staleTime: Infinity },
  },
});

/**
 * Applies the selected v5 mode to <html data-ph-mode> so every surface
 * repaints via the token flip pattern used in the app shell.
 */
function ThemeSwitch({ mode, children }: { mode: V5Mode; children: React.ReactNode }) {
  useEffect(() => {
    document.documentElement.setAttribute('data-ph-mode', mode);
  }, [mode]);
  return <>{children}</>;
}

const preview: Preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    a11y: {
      // Auto-run axe-core checks on every story render. 'todo' surfaces
      // violations in the a11y panel without failing test-storybook — the
      // v5 muted token palette has known color-contrast gaps that are
      // tracked separately (follow-up task). Flip to 'error' once the
      // token fixes land so CI enforces a11y on every PR.
      test: 'todo',
    },
    backgrounds: { disable: true },
    layout: 'centered',
  },
  globalTypes: {
    v5mode: {
      description: 'v5 design theme (marble light/dark/void)',
      defaultValue: 'marble_light',
      toolbar: {
        title: 'v5 mode',
        icon: 'paintbrush',
        items: [
          { value: 'marble_light', title: 'Marble Light' },
          { value: 'marble_dark', title: 'Marble Dark' },
          { value: 'void', title: 'Void' },
        ],
        dynamicTitle: true,
      },
    },
  },
  decorators: [
    (Story, context) => {
      const mode = (context.globals.v5mode ?? 'marble_light') as V5Mode;
      return (
        <QueryClientProvider client={queryClient}>
          <V5ThemeProvider>
            <V5ToastProvider>
              <ThemeSwitch mode={mode}>
                <div
                  style={{
                    background: 'var(--v5-bg)',
                    color: 'var(--v5-txt)',
                    padding: 24,
                    minWidth: 320,
                    fontFamily: 'Inter Variable, system-ui, sans-serif',
                  }}
                >
                  <Story />
                </div>
              </ThemeSwitch>
            </V5ToastProvider>
          </V5ThemeProvider>
        </QueryClientProvider>
      );
    },
  ],
};

export default preview;
