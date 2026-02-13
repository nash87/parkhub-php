import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  root: 'resources/js',
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
      '/sanctum': 'http://localhost:8000',
    },
  },
});
