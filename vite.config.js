// vite-project/vite.config.js
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // FE gọi /api/... -> proxy sang Apache:
      // http://127.0.0.1/phanthanhductin/public/api/...
      "/api": {
        target: "http://127.0.0.1",
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/api/, "/phanthanhductin/public/api"),
      },
    },
  },
});
