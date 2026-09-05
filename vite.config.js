import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/accademics/',

  server: {
    port: 3000,
    proxy: {
      '/backend': {
        target: 'http://localhost/Accademics',
        changeOrigin: true
      }
    }
  }
})