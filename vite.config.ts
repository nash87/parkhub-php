import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const REACT_RE = /node_modules\/(react|react-dom|react-router|react-router-dom)\//;
const MOTION_RE = /node_modules\/framer-motion\//;
const TANSTACK_RE = /node_modules\/@tanstack\//;
const PHOSPHOR_RE = /node_modules\/@phosphor-icons\//;

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify('1.0.0-php'),
  },
  plugins: [react()],
  root: 'resources/js',
    envDir: '../../',
  build: {
    outDir: '../../public',
    emptyOutDir: false,
    rollupOptions: {
      input: 'resources/js/index.html',
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return;
          if (REACT_RE.test(id)) return 'vendor-react';
          if (MOTION_RE.test(id)) return 'vendor-motion';
          if (TANSTACK_RE.test(id)) return 'vendor-tanstack';
          if (PHOSPHOR_RE.test(id)) return 'vendor-phosphor';
        },
      },
    },
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
      '/health': 'http://localhost:8000',
    },
  },
});
