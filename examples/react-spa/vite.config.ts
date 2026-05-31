import path from "node:path";
import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";

/**
 * Two run modes:
 *
 *   - Standalone SPA on a separate origin (default): set VITE_API_BASE to the
 *     backend origin (e.g. http://localhost). Cookies are cross-site, so
 *     the backend must allow this origin via CORS with credentials.
 *
 *   - Same-origin dev: leave VITE_API_BASE empty and run Vite behind a proxy
 *     to the PHP backend. The `server.proxy` block below forwards `/api/v1`
 *     to APP_BACKEND_ORIGIN (default http://localhost, the docker-compose
 *     apache image's port 80), so the browser sees a single origin and
 *     __Host- cookies survive.
 */
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, ".", "");
  const backend = env.APP_BACKEND_ORIGIN ?? "http://localhost";

  return {
    plugins: [react()],
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
    server: {
      port: 5173,
      proxy: {
        "/api/v1": {
          target: backend,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      outDir: "dist",
      sourcemap: true,
    },
  };
});
