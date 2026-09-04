import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/postcss';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginLabsDir = path.resolve(__dirname);

const aliases = {
  '@shared': path.resolve(__dirname, '../tools/shared-react/src/js'),
  '@plugin-shared': path.resolve(__dirname, '../tools/shared-react/src/js'),
  '@wp-calendar': path.resolve(__dirname, '../plugins/wp-calendar/src'),
};

const htmlPaths = {
  '/': 'index.html',
  '/wp-calendar/': 'labs/wp-calendar/index.html',
  '/wp-manifest/': 'labs/wp-manifest/index.html',
  '/shared-settings/': 'labs/shared-settings/index.html',
};

export default defineConfig(({ mode, command }) => {
  const env = loadEnv(mode, pluginLabsDir, '');

  return {
    root: pluginLabsDir,
    appType: 'mpa',
    define: {
      'process.env.NODE_ENV': JSON.stringify('production'),
      'process.env': '{}',
    },
    plugins: [
      react(),
      {
        name: 'mpa-html-serve',
        apply: 'serve',
        configureServer(server) {
          server.middlewares.use(async (req, res, next) => {
            const url = req.url || '/';
            const normalizedUrl = url.endsWith('/') ? url : url + '/';
            const htmlFile = htmlPaths[normalizedUrl];
            if (htmlFile) {
              const filePath = path.resolve(pluginLabsDir, htmlFile);
              const fileUrl = '/' + htmlFile.replace(/\\/g, '/');
              try {
                const raw = fs.readFileSync(filePath, 'utf8');
                const transformed = await server.transformIndexHtml(fileUrl, raw, url);
                res.setHeader('Content-Type', 'text/html');
                res.end(transformed);
              } catch (err) {
                next(err);
              }
              return;
            }
            next();
          });
        },
      },
    ],
    resolve: {
      alias: aliases,
      dedupe: ['react', 'react-dom'],
    },
    css: {
      postcss: {
        plugins: [tailwindcss()],
      },
    },
    server:
      command === 'serve'
        ? {
            proxy: buildApiProxy(
              env.PC_REMOTE_URL,
              '/wp-json/post-calendar/v1',
            ),
          }
        : undefined,
    build: {
      outDir: path.resolve(pluginLabsDir, 'dist'),
      emptyOutDir: true,
      rollupOptions: {
        input: {
          main: path.resolve(pluginLabsDir, 'index.html'),
          'wp-calendar': path.resolve(pluginLabsDir, 'labs/wp-calendar/index.html'),
          'wp-manifest': path.resolve(pluginLabsDir, 'labs/wp-manifest/index.html'),
          'shared-settings': path.resolve(pluginLabsDir, 'labs/shared-settings/index.html'),
        },
      },
    },
  };
});

/**
 * Proxy WordPress REST calls for a lab to a remote install so demos can
 * develop against real data without deploying plugin changes.
 */
function buildApiProxy(remoteUrl, ...paths) {
  if (!remoteUrl) {
    return undefined;
  }

  return Object.fromEntries(
    paths.map((p) => [
      p,
      { target: remoteUrl, changeOrigin: true, secure: false },
    ]),
  );
}