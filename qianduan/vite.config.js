import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // 核心修改：根据当前环境加载对应的 .env 文件
  const env = loadEnv(mode, process.cwd());

  return {
    plugins: [vue()],
    server: {
      proxy: {
        '/api': {
          // 核心修改：代理目标不再写死，改为读取环境变量 VITE_API_TARGET
          target: env.VITE_API_TARGET,
          changeOrigin: true
        }
      }
    }
  }
})