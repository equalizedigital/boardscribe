---
name: package-plugins
description: Build and zip the free (repo/slug boardscribe) and Pro (repo/slug boardscribe-pro) plugins into deployable dist/ zips with the correct contents. Use when asked to package, zip, release, or deploy either plugin.
---

# Package the free and/or Pro plugin into a deploy zip

Both repos ship as manually-built zips in each repo's gitignored `dist/`. There is no npm `package` script (yet). The zip contents below were verified against the known-good zips built 2026-07-07 (post Composer-autoloader switch, PRO-1187).

## Critical rules

1. **Always run the JS build first** (free repo only since the block moved there — Pro has no bundled JS anymore). Built output is gitignored (`assets/build/` in free). A zip missing it installs fine but renders an empty table / broken block editor with a 404'd script.
2. **Always run `composer install --no-dev --optimize-autoloader` before staging, and a plain `composer install` again after zipping.** Both plugins bootstrap via a Composer PSR-4 autoloader (`vendor/autoload.php`) — a zip without it fatal-errors on activation. Neither plugin has a runtime Composer dependency (only `php` itself in `require`; everything else is `require-dev` tooling), so `--no-dev` leaves `vendor/` containing only Composer's own autoloader machinery (`vendor/autoload.php` + `vendor/composer/*`, no third-party library code) — safe to ship wholesale. The restore step afterward matters: skipping it silently drops phpcs/phpunit/etc. from the working tree for the rest of the session.
3. The zip must contain a single top-level directory named exactly like the plugin slug (`boardscribe/` or `boardscribe-pro/`) — WordPress derives the install path from it.
4. Never include: `node_modules/`, `tests/`, `docs/`, `dist/`, `scripts/`, dotfiles (`.git*`, `.eslintrc`, `.husky/`, `.editorconfig`), `composer.json`, `composer.lock`, `package*.json`, `phpunit*`, `phpcs.xml`, `webpack.config.js`, `docker-compose.yml`. (`vendor/` is now a required exception to the general "no dev-tooling directories" rule — see above.)
5. In Pro, `src/js/admin/`, `src/js/front-end/`, and `src/js/editor/` are **plain-file enqueues and MUST ship**. In free, no `src/` ships at all (everything is bundled — including the block editor script, `src/js/block/` → `assets/build/block/`).
6. **Run each plugin's whole bash block as a single script**, not line-by-line — `$STAGE` (and `$OLDPWD` inside the zip subshell) are shell variables/state that don't persist across separate command invocations.

## Free plugin

```bash
cd <free-repo>
npm run build   # -> assets/build/boardscribe.js + assets/build/block/{index.js,index.asset.php}
composer install --no-dev --optimize-autoloader
mkdir -p dist && rm -f dist/boardscribe.zip
STAGE=$(mktemp -d)/boardscribe && mkdir -p "$STAGE/assets"
cp -r \
  boardscribe.php block.json uninstall.php readme.txt LICENSE \
  includes partials languages vendor \
  "$STAGE"/
cp -r assets/build assets/css "$STAGE/assets/"
( cd "$(dirname "$STAGE")" && zip -r "$OLDPWD/dist/boardscribe.zip" boardscribe )
rm -rf "$(dirname "$STAGE")"
composer install   # restore dev tooling (phpcs/phpunit/etc.) - don't skip this
```

Expected manifest (verify with `unzip -l`):
`boardscribe.php`, `block.json`, `uninstall.php`, `readme.txt`, `LICENSE`, `languages/boardscribe.pot`, `partials/{meta-box,settings-page,block-editor-preview}.php`, `assets/build/boardscribe.js`, `assets/build/block/{index.js,index.asset.php}`, `assets/css/boardscribe.css`, `vendor/autoload.php`, `vendor/composer/*.php`, `includes/Plugin.php`, `includes/{Admin/{MetaBox,SettingsPage},PostType/BoardScribeCPT,REST/BoardScribeEndpoint,Shortcode/{FieldRegistry,BoardScribeShortcode},Block/BoardScribeBlock}.php`

## Pro plugin

```bash
cd <pro-repo>   # ../boardscribe-pro relative to the free repo (GitHub repo also renamed to boardscribe-pro)
# No JS build - Pro ships plain-file JS only since the block moved to free.
composer install --no-dev --optimize-autoloader
mkdir -p dist && rm -f dist/boardscribe-pro.zip
STAGE=$(mktemp -d)/boardscribe-pro && mkdir -p "$STAGE/assets" "$STAGE/src/js"
cp -r \
  boardscribe-pro.php readme.txt LICENSE \
  includes partials vendor \
  "$STAGE"/
cp -r assets/css "$STAGE/assets/"
cp -r src/js/admin src/js/front-end src/js/editor "$STAGE/src/js/"
( cd "$(dirname "$STAGE")" && zip -r "$OLDPWD/dist/boardscribe-pro.zip" boardscribe-pro )
rm -rf "$(dirname "$STAGE")"
composer install   # restore dev tooling (phpcs/phpunit/etc.) - don't skip this
```

Expected manifest:
`boardscribe-pro.php`, `readme.txt`, `LICENSE`, `assets/css/{pro-meta,calendar-templates}.css`, `vendor/autoload.php`, `vendor/composer/*.php`, `partials/{pro-meta-fields,csv-import-page,license-section}.php`, `includes/{Plugin,License/LicenseManager,Admin/ProMetaFields,PostType/MeetingCategory,Block/BlockExtensions,Import/CsvImporter}.php`, `src/js/{admin/proMeta,front-end/proColumns,front-end/yearTimelineTemplate,front-end/calendarTemplates,editor/blockEditor}.js`

Note: Pro no longer ships `block.json`, `build/`, or `partials/block-editor-preview.php` — the block moved to the free plugin (paired `feat/move-block-to-free` branches). Don't re-add them from an old checklist.

Note: `partials/shortcode-builder-fields.php` no longer exists (deleted in the shortcode-field-registry refactor, PR boardscribe#19 / boardscribe-pro#11 (repos since renamed from equalize-digital-meeting-minutes/meeting-minutes-pro)) — don't re-add it if an old checklist still references it.

## After building

- Sanity-check both manifests with `unzip -l dist/*.zip` — compare against the lists above; investigate any new file before shipping it (new includes/partials belong in the zip; new dev files don't). Confirm `vendor/` in the zip contains *only* `autoload.php` + `composer/` — if any other top-level package directory shows up under `vendor/`, the `--no-dev` install didn't take (re-run it) or a real runtime dependency was added to `composer.json` and this doc needs a rethink (a runtime dep would mean actual third-party library code ships too, not just the autoloader).
- If a directory was added to either plugin since 2026-07-07, update the `cp` list here AND the expected manifest.
- Deploy order on a site: free plugin first, then Pro (Pro no-ops with an admin notice if free is inactive).
