import type { StorybookConfig } from '@storybook/react-vite';

/**
 * Storybook 10 config for parkhub-web v5 primitives.
 *
 * Isolated component catalog. Runs via Vite (reuses Astro's vite 8 toolchain
 * — no duplicate resolver stack). Stories are co-located beside each
 * primitive so they stay in sync with the component they document.
 */
const config: StorybookConfig = {
  stories: ['../src/design-v5/**/*.stories.@(ts|tsx|mdx)'],
  addons: ['@storybook/addon-a11y'],
  framework: {
    name: '@storybook/react-vite',
    options: {},
  },
  typescript: {
    check: false,
    reactDocgen: 'react-docgen-typescript',
  },
  core: {
    disableTelemetry: true,
    disableWhatsNewNotifications: true,
  },
};

export default config;
