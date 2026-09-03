import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type UserConfig } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const toolsDir = path.resolve(__dirname);

export interface PluginBuildOptions {
  pluginDir: string;
  pluginName: string;
  entry: Record<string, string>;
  fileName?: string;
  globalName?: string;
  sourcemap?: boolean;
}

export function createPluginConfig(options: PluginBuildOptions): UserConfig {
  const entryName = options.fileName ?? Object.keys(options.entry)[0] ?? 'app';

  return {
    define: {
      'process.env.NODE_ENV': JSON.stringify('production'),
      'process.env': '{}',
    },
    plugins: [react()],
    resolve: {
      alias: {
        '@shared': path.resolve(toolsDir, 'shared-react/src/js'),
        '@plugin-shared': path.resolve(toolsDir, 'shared-react/src/js'),
      },
    },
    build: {
      outDir: path.resolve(process.cwd(), options.pluginDir, 'dist'),
      emptyOutDir: false,
      sourcemap: options.sourcemap ?? true,
      lib: {
        entry: Object.fromEntries(
          Object.entries(options.entry).map(([name, file]) => [
            name,
            path.resolve(process.cwd(), options.pluginDir, file),
          ])
        ),
        name: options.globalName ?? 'WpPlugin',
        formats: ['iife'],
        fileName: () => `${entryName}.js`,
        cssFileName: entryName,
      },
      rollupOptions: {
        output: {
          intro: "var process = globalThis.process || { env: { NODE_ENV: 'production' } };",
        },
      },
    },
  };
}
