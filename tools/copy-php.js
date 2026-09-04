import fs from 'fs-extra';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const srcPhp = path.resolve(rootDir, 'tools/php');
const srcShared = path.resolve(rootDir, 'tools/shared-settings');
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

  if (!fs.existsSync(pluginDir)) {
    console.log(`Skipping ${slug} — directory not found`);
    continue;
  }

  // Copy shared PHP classes → includes/shared/
  const phpDest = path.join(pluginDir, 'includes/shared');
  fs.ensureDirSync(phpDest);
  fs.copySync(srcPhp, phpDest, { overwrite: true });
  console.log(`Copied shared PHP → plugins/${dir}/includes/shared/`);

  // Copy shared settings JS/CSS → includes/shared/shared-settings/
  if (fs.existsSync(srcShared)) {
    const sharedDest = path.join(pluginDir, 'includes/shared/shared-settings');
    fs.ensureDirSync(sharedDest);
    fs.copySync(srcShared, sharedDest, { overwrite: true });
    console.log(`Copied shared settings assets → plugins/${dir}/includes/shared/shared-settings/`);
  }
}
