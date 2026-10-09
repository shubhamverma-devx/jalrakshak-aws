import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// In dev the React app talks to `php artisan serve` through this proxy, so the
// frontend can always call /api regardless of environment. In production Nginx
// serves this build and routes /api to PHP-FPM on the same EC2 box.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/storage': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
