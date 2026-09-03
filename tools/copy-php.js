import fs from 'fs-extra';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const srcPhp = path.resolve(rootDir, 'tools/php');
const pluginsDir = path.resolve(rootDir, 'plugins');

const pluginsJson = JSON.parse(fs.readFileSync(path.resolve(rootDir, 'plugins.json'), 'utf8'));
const pluginList = pluginsJson.plugins;

for (const plugin of pluginList) {
  const dir = typeof plugin === 'string' ? plugin : plugin.dir;
  const slug = typeof plugin === 'string' ? plugin : plugin.slug;
  const usesSharedPhp = typeof plugin === 'string' ? true : plugin.usesSharedPhp !== false;

  if (!usesSharedPhp) {
    console.log(`Skipping shared PHP → plugins/${dir}/ (not opted in)`);
    continue;
  }

  const pluginDir = path.join(pluginsDir, dir);
  const dest = path.join(pluginDir, 'includes/shared');

  if (!fs.existsSync(pluginDir)) {
    console.log(`Skipping ${slug} — directory not found`);
    continue;
  }

  fs.ensureDirSync(dest);
  fs.copySync(srcPhp, dest, { overwrite: true });

  console.log(`Copied shared PHP → plugins/${dir}/includes/shared/`);
}
