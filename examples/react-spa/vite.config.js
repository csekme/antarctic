import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
/**
 * Two run modes:
 *
 *   - Standalone SPA on a separate origin (default): set VITE_API_BASE to the
 *     backend origin (e.g. http://localhost:8080). Cookies are cross-site, so
 *     the backend must allow this origin via CORS with credentials.
 *
 *   - Same-origin dev: leave VITE_API_BASE empty and run Vite behind a proxy
 *     to the PHP backend. The `server.proxy` block below forwards `/api/v1`
 *     to APP_BACKEND_ORIGIN (default http://localhost:8080), so the browser
 *     sees a single origin and __Host- cookies survive.
 */
export default defineConfig(function (_a) {
    var _b;
    var mode = _a.mode;
    var env = loadEnv(mode, ".", "");
    var backend = (_b = env.APP_BACKEND_ORIGIN) !== null && _b !== void 0 ? _b : "http://localhost:8080";
    return {
        plugins: [react()],
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
