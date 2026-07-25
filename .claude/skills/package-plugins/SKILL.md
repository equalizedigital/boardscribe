---
name: package-plugins
description: Build and zip the free (repo/slug boardscribe) and Pro (repo/slug boardscribe-pro) plugins into deployable dist/ zips with the correct contents. Use when asked to package, zip, release, or deploy either plugin.
---

# Package the free and/or Pro plugin into a deploy zip

**Free plugin: run `npm run dist`.** There is no manual recipe to follow any
more — the build is `scripts/dist.sh`, matching the other Equalize Digital
plugins (accessibility-checker and its add-ons). Pro still has no build script
and keeps its manual recipe below until it gets one.

## Free plugin

```bash
cd <free-repo>
npm run dist                     # -> dist/boardscribe-<version>.zip
npm run dist:keep-build-folder   # same, but also leaves dist/boardscribe/ in place
```

`npm run dist` runs the JS build, installs Composer without dev dependencies,
packages, and then restores the dev dependencies. `dist:keep-build-folder`
deliberately skips that last restore, because it exists for CI (`make-pot.yml`
scans the unpacked `dist/boardscribe/`); after running it locally, run
`composer install` yourself to get phpcs/phpunit back.

**What ships is `package.json`'s `files` allowlist**, not this document. To add
a directory to the zip, add it there. Two things the allowlist cannot control,
handled in `scripts/dist.sh` instead: npm force-packs `package.json` and
`README.md` regardless of `files` (both are deleted after staging), and
`.DS_Store` files are stripped.

`scripts/dist.sh` fails the build if `vendor/` contains anything other than
`autoload.php` and `composer/`, so a zip can no longer be built from a dev
`vendor/` by accident.

## Critical rules

These are the invariants worth keeping in mind; the script enforces the ones it
can.

1. **The JS build must run first.** Built output is gitignored (`assets/build/`
   in free). A zip missing it installs fine but renders an empty table / broken
   block editor with a 404'd script. `npm run dist` handles this.
2. **Composer must be installed `--no-dev --optimize-autoloader` before staging,
   and restored afterwards.** Both plugins bootstrap via a Composer PSR-4
   autoloader (`vendor/autoload.php`) — a zip without it fatal-errors on
   activation. Neither plugin has a runtime Composer dependency (only `php`
   itself in `require`; everything else is `require-dev` tooling), so `--no-dev`
   leaves `vendor/` containing only Composer's own autoloader machinery
   (`vendor/autoload.php` + `vendor/composer/*`, no third-party library code) —
   safe to ship wholesale.
3. The zip must contain a single top-level directory named exactly like the
   plugin slug (`boardscribe/` or `boardscribe-pro/`) — WordPress derives the
   install path from it.
4. Never include: `node_modules/`, `tests/`, `docs/`, `dist/`, `scripts/`,
   dotfiles (`.git*`, `.eslintrc`, `.husky/`, `.editorconfig`),
   `composer.json`, `composer.lock`, `package*.json`, `phpunit*`, `phpcs.xml`,
   `webpack.config.js`, `docker-compose.yml`. (`vendor/` is a required
   exception to the general "no dev-tooling directories" rule — see above.)
   **Accepted Plugin Check warning:** the official WP.org Plugin Check tool
   flags a shipped `vendor/` with no accompanying `composer.json` as
   `missing_composer_json_file` (plugin_repo category, WARNING not ERROR).
   Deliberately not fixed — decided (PRO-1196) to keep
   `composer.json`/`composer.lock` out of the release zip rather than ship
   dev-tooling config for a warning-level, non-blocking finding. Don't re-add
   `composer.json` to the allowlist or the Pro recipe from an old checklist or
   a future Plugin Check run without checking here first.
5. In Pro, `src/js/admin/`, `src/js/front-end/`, and `src/js/editor/` are
   **plain-file enqueues and MUST ship**. In free, no `src/` ships at all
   (everything is bundled — including the block editor script,
   `src/js/block/` → `assets/build/block/`).
6. **Run the Pro bash block below as a single script**, not line-by-line —
   `$STAGE`/`$TMP_DIR` (and `$OLDPWD` inside the zip subshell) are shell
   variables/state that don't persist across separate command invocations.
7. **The Pro block opens with `set -euo pipefail` and a cleanup trap — don't
   strip these when copy-pasting.** Without them, a failed
   `composer install`/`cp`/`zip` can be silently ignored and the script
   continues packaging a broken zip; worse, if `mktemp -d` itself fails,
   `STAGE=$(mktemp -d)/boardscribe-pro` degrades to the literal string
   `/boardscribe-pro` with **no error surfaced** (bash doesn't propagate the
   failure through the concatenation), making a cleanup line like
   `rm -rf "$(dirname "$STAGE")"` resolve to `rm -rf /`. The current form avoids
   this entirely: `TMP_DIR=$(mktemp -d)` is a bare assignment (so `set -e`
   reliably aborts if it fails, unlike the concatenated form), `STAGE` is built
   from `$TMP_DIR` afterward, and `trap 'rm -rf -- "$TMP_DIR"' EXIT` only ever
   removes that one known path. (Found via CodeRabbit review on boardscribe#31.)

## Free plugin: expected manifest

Verify with `unzip -l dist/boardscribe-<version>.zip`. Verified against a real
`npm run dist` run:

`boardscribe.php`, `block.json`, `uninstall.php`, `readme.txt`, `LICENSE`,
`languages/boardscribe.pot`,
`partials/{meta-box,settings-page,block-editor-preview}.php`,
`assets/build/boardscribe.js`, `assets/build/block/{index.js,index.asset.php}`,
`assets/build/builder/{index.js,index.asset.php}`,
`assets/css/{boardscribe,builder,settings}.css`, `assets/images/logo.svg`,
`vendor/autoload.php`, `vendor/composer/*.php`, `includes/Plugin.php`,
`includes/{Admin/{MetaBox,SettingsPage},PostType/BoardScribeCPT,REST/BoardScribeEndpoint,Shortcode/{FieldRegistry,BoardScribeShortcode},Block/BoardScribeBlock}.php`

## Pro plugin

Pro has no `dist.sh` yet, so it is still packaged by hand. Bringing it in line
with free is the obvious follow-up.

```bash
cd <pro-repo>   # ../boardscribe-pro relative to the free repo
set -euo pipefail
# No JS build - Pro ships plain-file JS only since the block moved to free.
composer install --no-dev --optimize-autoloader
mkdir -p dist && rm -f dist/boardscribe-pro.zip
TMP_DIR=$(mktemp -d)
STAGE="$TMP_DIR/boardscribe-pro"
trap 'rm -rf -- "$TMP_DIR"' EXIT
mkdir -p "$STAGE/assets" "$STAGE/src/js"
cp -r \
  boardscribe-pro.php readme.txt LICENSE \
  includes partials vendor \
  "$STAGE"/
cp -r assets/css "$STAGE/assets/"
cp -r src/js/admin src/js/front-end src/js/editor "$STAGE/src/js/"
find "$STAGE" -name '.DS_Store' -delete   # strip macOS cruft before zipping
( cd "$TMP_DIR" && zip -r "$OLDPWD/dist/boardscribe-pro.zip" boardscribe-pro )
composer install   # restore dev tooling (phpcs/phpunit/etc.) - don't skip this
```

Expected manifest:
`boardscribe-pro.php`, `readme.txt`, `LICENSE`,
`assets/css/{pro-meta,calendar-templates}.css`, `vendor/autoload.php`,
`vendor/composer/*.php`,
`partials/{pro-meta-fields,csv-import-page,license-section}.php`,
`includes/{Plugin,License/LicenseManager,Admin/ProMetaFields,PostType/MeetingCategory,Block/BlockExtensions,Import/CsvImporter}.php`,
`src/js/{admin/proMeta,front-end/proColumns,front-end/yearTimelineTemplate,front-end/calendarTemplates,editor/blockEditor}.js`

Note: Pro no longer ships `block.json`, `build/`, or
`partials/block-editor-preview.php` — the block moved to the free plugin
(paired `feat/move-block-to-free` branches). Don't re-add them from an old
checklist.

Note: `partials/shortcode-builder-fields.php` no longer exists (deleted in the
shortcode-field-registry refactor, PR boardscribe#19 / boardscribe-pro#11
(repos since renamed from equalize-digital-meeting-minutes/meeting-minutes-pro))
— don't re-add it if an old checklist still references it.

## After building

- Sanity-check the manifest with `unzip -l dist/*.zip` — compare against the
  lists above; investigate any new file before shipping it (new
  includes/partials belong in the zip; new dev files don't). For free, a new
  directory needs adding to `package.json`'s `files` **and** to the manifest
  above.
- Confirm `vendor/` in the zip contains *only* `autoload.php` + `composer/`. In
  free this is enforced by `scripts/dist.sh`; in Pro it is still a manual check.
  If any other top-level package directory shows up under `vendor/`, either the
  `--no-dev` install didn't take (re-run it) or a real runtime dependency was
  added to `composer.json` and this doc needs a rethink (a runtime dep would
  mean actual third-party library code ships too, not just the autoloader).
- Deploy order on a site: free plugin first, then Pro (Pro no-ops with an admin
  notice if free is inactive).
