# Repository Agents Guide

## Architecture

This is a **WordPress plugin monorepo** that uses **git submodules** for each plugin.

- `plugins/wp-calendar` — submodule tracking `spill-the-ink/wp-calendar` on branch **`dev`** (not main)
- `plugins/wp-manifest` — submodule tracking `spill-the-ink/wp-manifest` on branch **`main`**
- `tools/` — shared code (PHP classes, React components, settings JS/CSS, build scripts)
- `plugin-labs/` — local Vite dev playground for UI development (not part of production build)
- `plugins.json` — registry of plugins and their release file lists

**Critical**: Plugin code lives in separate repos via submodules. Changes to plugin files go to the submodule's repo, not this one. Always `git submodule status` before editing plugin code.

## Node & Package Manager

- **Node ≥ 22.12.0** (`.nvmrc` says `22`)
- **Yarn ≥ 1.22** (root uses yarn.lock; wp-calendar also uses yarn)
- wp-calendar has its own `node_modules/` — install with `yarn install --frozen-lockfile` from `plugins/wp-calendar/`

## Build Commands

### Full build (from root)
```bash
yarn build          # builds all plugins (wp-manifest copy-php → wp-calendar pot+lang+vite → copy-php)
yarn release        # build + create release zips in .release/<date>/
```

### wp-calendar (from `plugins/wp-calendar/`)
```bash
yarn build          # three Vite builds: main + admin + settings
yarn build:pot      # generate .pot translation file
yarn build:lang     # compile .mo translation files
yarn dev            # Vite dev server on port 3000 (proxies WP REST API if PC_REMOTE_URL set)
```

### wp-manifest
wp-manifest has **no JS build**. Its shared settings JS/CSS is copied from `tools/shared-settings/` by `tools/copy-php.js` (runs as part of root `yarn build`).

### plugin-labs (from `plugin-labs/`)
```bash
yarn dev            # Vite MPA dev server on port 3000
```

## Linting, Typecheck & Tests

All live **inside `plugins/wp-calendar/`**, not at root.

```bash
cd plugins/wp-calendar

yarn lint           # ESLint (config in .github/eslint.config.js)
yarn lint:fix       # ESLint auto-fix
yarn format         # Prettier (config in .github/.prettierrc)
yarn format:check   # Prettier check
yarn lint:php       # PHPCS (requires: composer install first)
yarn lint:php:fix   # PHPCBF auto-fix
yarn lint:all       # lint + format:check + lint:php
yarn typecheck      # tsc --noEmit
yarn test:unit      # vitest run (tests in src/**/*.test.ts)
yarn test:integration  # vitest run tests/integration (needs PC_REMOTE_URL)
```

**PHP linting prerequisite**: Run `composer install` inside `plugins/wp-calendar/` before `lint:php`.

## Testing

- Unit tests: `src/**/*.test.ts` in wp-calendar, Vitest with node environment
- Integration tests: `tests/integration/` in wp-calendar, require a remote WordPress install (`PC_REMOTE_URL` env var)
- No tests exist for wp-manifest or at the root level

## Environment Variables

- `PC_REMOTE_URL` — WordPress site URL for Vite dev proxy and integration tests
- `PC_ALLOW_SELF_SIGNED=1` — allow self-signed certs for local dev domains
- `RELEASE_TAG` — override release tag (defaults to today's date YYYY-MM-DD)

## Release Process

- Manual GitHub Actions workflow (`.github/workflows/release.yml`)
- Builds all plugins, creates zip files in `.release/<tag>/`
- Each plugin zip includes only files listed in `plugins.json` → `include`
- Version is read from the main PHP file's `Version:` header

## Shared Code

- `tools/php/` — shared PHP classes (copied to `plugins/*/includes/shared/` by `copy-php.js`)
- `tools/shared-react/src/js/` — shared React components/hooks (used by wp-calendar via import aliases)
- `tools/shared-settings/` — shared vanilla JS/CSS for settings pages (copied to wp-manifest)
- `tools/copy-php.js` — copies shared PHP and settings assets to plugins that opt in (`usesSharedPhp: true` in plugins.json)

## Naming Conventions

**IMPORTANT**: All agents must follow the naming conventions documented in:

**`.opencode/doc/naming-conventions.md`**

### Quick Reference
- **wp-calendar**: `wp_calendar_*` prefix, `WpCalendar` namespace
- **wp-manifest**: `wp_manifest_*` prefix, `WpManifest` namespace
- **Shared code**: `wp-polyfill-*` prefix, `WpPluginShared` namespace

### Key Rules
1. Always use full plugin prefix (no abbreviations like `wpm_`, `pc_`)
2. CSS classes: `wp-calendar-*`, `wp-manifest-*`, or `wp-polyfill-*`
3. Hooks: `wp_calendar_*`, `wp_manifest_*`
4. Meta keys: `_wp_calendar_*`, `_wp_manifest_*`
5. Constants: `WP_CALENDAR_*`, `WP_MANIFEST_*`

See `.opencode/doc/naming-conventions.md` for complete details.

## Key Gotchas

- wp-calendar's `vite.config.js` has **three build modes** (`admin`, `settings`, default) producing separate IIFE bundles — don't assume a single output
- wp-calendar ESLint config lives in `.github/eslint.config.js`, not root
- The root `vite.config.js` is essentially empty (no-op) — wp-calendar and plugin-labs have their own
- `plugin-labs/` aliases `@shared` and `@wp-calendar` to source paths for live development
- wp-calendar tracks the **`dev`** branch, not `main`
