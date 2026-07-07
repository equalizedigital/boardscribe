---
name: package-plugins
description: Build and zip the free (equalize-digital-meeting-minutes) and Pro (meeting-minutes-pro) plugins into deployable dist/ zips with the correct contents. Use when asked to package, zip, release, or deploy either plugin.
---

# Package the free and/or Pro plugin into a deploy zip

Both repos ship as manually-built zips in each repo's gitignored `dist/`. There is no npm `package` script (yet). The zip contents below were verified against the known-good zips built 2026-07-07 (post Composer-autoloader switch, PRO-1187).

## Critical rules

1. **Always run the JS build first.** Built output is gitignored in both repos (`assets/build/` in free, `build/` in Pro). A zip missing it installs fine but renders an empty table / broken block editor with a 404'd script.
2. **Always run `composer install --no-dev --optimize-autoloader` before staging, and a plain `composer install` again after zipping.** Both plugins bootstrap via a Composer PSR-4 autoloader (`vendor/autoload.php`) — a zip without it fatal-errors on activation. Neither plugin has a runtime Composer dependency (only `php` itself in `require`; everything else is `require-dev` tooling), so `--no-dev` leaves `vendor/` containing only Composer's own autoloader machinery (`vendor/autoload.php` + `vendor/composer/*.php`, no third-party library code) — safe to ship wholesale. The restore step afterward matters: skipping it silently drops phpcs/phpunit/etc. from the working tree for the rest of the session.
3. The zip must contain a single top-level directory named exactly like the plugin slug (`equalize-digital-meeting-minutes/` or `meeting-minutes-pro/`) — WordPress derives the install path from it.
4. Never include: `node_modules/`, `tests/`, `docs/`, `dist/`, `scripts/`, dotfiles (`.git*`, `.eslintrc`, `.husky/`, `.editorconfig`), `composer.json`, `composer.lock`, `package*.json`, `phpunit*`, `phpcs.xml`, `webpack.config.js`, `docker-compose.yml`. (`vendor/` is now a required exception to the general "no dev-tooling directories" rule — see above.)
5. In Pro, `src/js/admin/` and `src/js/front-end/` are **plain-file enqueues and MUST ship**; `src/js/block/` is bundler source and must NOT ship. In free, no `src/` ships at all (everything is bundled).

## Free plugin

```bash
cd <free-repo>
npm run build   # -> assets/build/meeting-minutes.js
composer install --no-dev --optimize-autoloader
mkdir -p dist && rm -f dist/equalize-digital-meeting-minutes.zip
STAGE=$(mktemp -d)/equalize-digital-meeting-minutes && mkdir -p "$STAGE"
cp -r --parents \
  equalize-digital-meeting-minutes.php uninstall.php readme.txt \
  includes partials languages vendor \
  assets/build assets/css \
  "$STAGE"/
( cd "$(dirname "$STAGE")" && zip -r "$OLDPWD/dist/equalize-digital-meeting-minutes.zip" equalize-digital-meeting-minutes )
composer install   # restore dev tooling (phpcs/phpunit/etc.) - don't skip this
```

Expected manifest (verify with `unzip -l`):
`equalize-digital-meeting-minutes.php`, `uninstall.php`, `readme.txt`, `languages/edmm.pot`, `partials/{meta-box,settings-page}.php`, `assets/build/meeting-minutes.js`, `assets/css/meeting-minutes.css`, `vendor/autoload.php`, `vendor/composer/*.php`, `includes/Plugin.php`, `includes/{Admin/{MetaBox,SettingsPage},PostType/MeetingMinutes,REST/MeetingMinutesEndpoint,Shortcode/{FieldRegistry,MeetingMinutesShortcode}}.php`

## Pro plugin

```bash
cd <pro-repo>   # ../meeting-minutes-pro relative to the free repo
npm run build   # -> build/index.js + build/index.asset.php
composer install --no-dev --optimize-autoloader
mkdir -p dist && rm -f dist/meeting-minutes-pro.zip
STAGE=$(mktemp -d)/meeting-minutes-pro && mkdir -p "$STAGE"
cp -r --parents \
  meeting-minutes-pro.php block.json readme.txt \
  includes partials build vendor \
  assets/css \
  src/js/admin src/js/front-end \
  "$STAGE"/
( cd "$(dirname "$STAGE")" && zip -r "$OLDPWD/dist/meeting-minutes-pro.zip" meeting-minutes-pro )
composer install   # restore dev tooling (phpcs/phpunit/etc.) - don't skip this
```

Expected manifest:
`meeting-minutes-pro.php`, `block.json`, `readme.txt`, `build/{index.js,index.asset.php}`, `assets/css/pro-meta.css`, `vendor/autoload.php`, `vendor/composer/*.php`, `partials/{pro-meta-fields,csv-import-page,license-section,block-editor-preview}.php`, `includes/{Plugin,License/LicenseManager,Admin/ProMetaFields,PostType/MeetingCategory,Block/MeetingMinutesBlock,Import/CsvImporter}.php`, `src/js/{admin/proMeta,front-end/proColumns,front-end/yearTimelineTemplate}.js`

Note: `partials/shortcode-builder-fields.php` no longer exists (deleted in the shortcode-field-registry refactor, PR equalize-digital-meeting-minutes#19 / meeting-minutes-pro#11) — don't re-add it if an old checklist still references it.

## After building

- Sanity-check both manifests with `unzip -l dist/*.zip` — compare against the lists above; investigate any new file before shipping it (new includes/partials belong in the zip; new dev files don't). Confirm `vendor/` in the zip contains *only* `autoload.php` + `composer/` — if any other top-level package directory shows up under `vendor/`, the `--no-dev` install didn't take (re-run it) or a real runtime dependency was added to `composer.json` and this doc needs a rethink (a runtime dep would mean actual third-party library code ships too, not just the autoloader).
- If a directory was added to either plugin since 2026-07-07, update the `cp` list here AND the expected manifest.
- Deploy order on a site: free plugin first, then Pro (Pro no-ops with an admin notice if free is inactive).
