import fs from 'fs-extra';
import path from 'path';
import archiver from 'archiver';

const rootDir = path.resolve(import.meta.dirname, '..');
const pluginsDir = path.resolve(rootDir, 'plugins');

// Default to today's date (YYYY-MM-DD), e.g. 2026-09-03, matching the
// WordPress performance monorepo release-tag convention. Override with
// RELEASE_TAG for same-day re-releases or semver-style tags.
const today = new Date().toISOString().slice(0, 10);
const tag = process.env.RELEASE_TAG ?? today;
const releaseDir = path.resolve(rootDir, '.release', tag);

const pluginsJson = JSON.parse(fs.readFileSync(path.resolve(rootDir, 'plugins.json'), 'utf8'));
const pluginList = pluginsJson.plugins;

// A release always includes every plugin in the registry, even if a plugin's
// version did not change — they all ride on the same single release tag.
const plugins = pluginList.map((p) => (typeof p === 'string' ? { dir: p, slug: p } : p));

if (plugins.length === 0) {
  console.error('No plugins to release');
  process.exit(1);
}

console.log(`Packaging ${plugins.map((p) => `${p.slug} (plugins/${p.dir})`).join(', ')} into ${path.relative(rootDir, releaseDir)}`);

fs.ensureDirSync(releaseDir);

for (const plugin of plugins) {
  const { dir, slug } = plugin;
  const pluginDir = path.join(pluginsDir, dir);

  if (!fs.existsSync(pluginDir)) {
    console.error(`  !! plugins/${dir} not found — skipping`);
    continue;
  }

  const mainFile = getMainPhpFile(pluginDir, slug);
  if (!mainFile) {
    console.error(`  !! No main PHP file found for "${slug}" — skipping`);
    continue;
  }

  const version = readPluginVersion(mainFile);
  if (!version) {
    console.error(`  !! Could not read "Version:" header from ${path.relative(rootDir, mainFile)} — skipping`);
    continue;
  }

  const zipPath = path.join(releaseDir, `${slug}-${version}.zip`);

  // Fresh staging dir per plugin.
  const buildDir = path.resolve(rootDir, 'build', slug);
  fs.removeSync(buildDir);

  // Copy only the plugin's release surface (files/dirs in `include`).
  const include = plugin.include && plugin.include.length ? plugin.include : fs.readdirSync(pluginDir);
  fs.ensureDirSync(buildDir);
  for (const entry of include) {
    const src = path.join(pluginDir, entry);
    if (fs.existsSync(src)) {
      fs.copySync(src, path.join(buildDir, entry), {
        filter: (s) => !/\.git$|node_modules|\.release|^.*\.(draft|zip)$/.test(s),
      });
    }
  }

  if (fs.existsSync(zipPath)) {
    fs.rmSync(zipPath, { force: true });
  }

  const output = fs.createWriteStream(zipPath);
  const archive = archiver('zip', { zlib: { level: 9 } });

  const closePromise = new Promise((resolve, reject) => {
    output.on('close', resolve);
    output.on('error', reject);
    archive.on('error', reject);
  });

  archive.pipe(output);
  archive.directory(buildDir, slug);

  await archive.finalize();
  await closePromise;

  fs.removeSync(buildDir);
  console.log(`  ${slug} v${version} -> .release/${tag}/${path.basename(zipPath)}`);
}

fs.removeSync(path.resolve(rootDir, 'build'));
console.log(`Done. Ready to attach to a single release tag "${tag}".`);

function getMainPhpFile(dir, slug) {
  const slugFile = path.join(dir, `${slug}.php`);
  if (fs.existsSync(slugFile)) {
    return slugFile;
  }
  const topPhp = fs
    .readdirSync(dir, { withFileTypes: true })
    .filter((f) => f.isFile() && f.name.endsWith('.php') && f.name !== 'uninstall.php')
    .map((f) => path.join(dir, f.name));
  return topPhp.length === 1 ? topPhp[0] : null;
}

function readPluginVersion(mainFile) {
  const content = fs.readFileSync(mainFile, 'utf8');
  const match = content.match(/^ \* Version:\s*(.+)$/m);
  return match ? match[1].trim() : null;
}

