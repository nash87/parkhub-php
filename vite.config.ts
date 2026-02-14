import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

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
    },
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
      '/health': 'http://localhost:8000',
    },
  },
});
