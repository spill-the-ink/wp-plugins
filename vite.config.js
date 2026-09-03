import { defineConfig, mergeConfig } from 'vite';
import { createPluginConfig } from './tools/vite.config.shared.ts';

const wpSeoConfig = () =>
  mergeConfig(
    createPluginConfig({
      pluginDir: 'plugins/wp-seo',
      pluginName: 'wp-seo',
      entry: { settings: 'src/settings.tsx' },
      globalName: 'WpSeoSettings',
    }),
    { build: { emptyOutDir: true } }
  );

export default defineConfig(({ mode }) => {
  if (mode === 'wp-seo') {
    return wpSeoConfig();
  }
  return {};
});
