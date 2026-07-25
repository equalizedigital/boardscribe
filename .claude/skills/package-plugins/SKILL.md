---
name: package-plugins
description: Build and zip the free (repo/slug boardscribe) and Pro (repo/slug boardscribe-pro) plugins into deployable dist/ zips with the correct contents. Use when asked to package, zip, release, or deploy either plugin.
---

# Package the free and/or Pro plugin into a deploy zip

**Both plugins: run `npm run dist`.** There is no manual recipe to follow any
more — each repo has its own `scripts/dist.sh`, matching the other Equalize
Digital plugins (accessibility-checker and its add-ons).

## Both plugins

```bash
cd <free-repo>
npm run dist                     # -> dist/boardscribe-<version>.zip
npm run dist:keep-build-folder   # same, but also leaves dist/boardscribe/ in place

cd <pro-repo>                    # ../boardscribe-pro relative to the free repo
npm run dist                     # -> dist/boardscribe-pro-<version>.zip
npm run dist:keep-build-folder
```

Pro's chain has no JS build step — it ships plain-file JS, so `npm run dist`
there is composer plus packaging.

`npm run dist` runs the JS build (free only), installs Composer without dev
dependencies, packages, and then restores the dev dependencies.
`dist:keep-build-folder` deliberately skips that last restore, because it
exists for CI — `make-pot.yml` scans the unpacked `dist/<slug>/`. After running
it locally, run `composer install` yourself to get phpcs/phpunit back.

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

1. **The JS build must run first** (free only — Pro has no bundled JS since the
   block moved to free). Built output is gitignored (`assets/build/` in free).
   A zip missing it installs fine but renders an empty table / broken block
   editor with a 404'd script. `npm run dist` handles this.
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
6. Both plugins keep a committed `languages/<slug>.pot`. It is the baseline
   `make-pot.yml` diffs against, and it ships in the zip — without it that
   workflow raises a translations PR on every run.

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

Pro uses the same `scripts/dist.sh` as free, bar the slug, driven by the same
`files` allowlist in its own `package.json`. Its expected manifest, verified
against a real `npm run dist`:

`boardscribe-pro.php`, `readme.txt`, `LICENSE`, `languages/boardscribe-pro.pot`,
`assets/css/{admin-document-picker,pro-meta}.css`, `vendor/autoload.php`,
`vendor/composer/*.php`,
`partials/{pro-meta-fields,csv-import-page,license-section}.php`,
`includes/{Plugin,License/LicenseManager,Admin/{ProMetaFields,DocumentPicker},PostType/{MeetingCategory,MeetingType,DocumentCPT,MeetingDocumentRelationship},Block/BlockExtensions,Import/CsvImporter}.php`,
`src/js/{admin/proMeta,front-end/proColumns,front-end/yearTimelineTemplate,editor/blockEditor}.js`

Note: Pro no longer ships `block.json`, `build/`, or
`partials/block-editor-preview.php` — the block moved to the free plugin
(paired `feat/move-block-to-free` branches). Don't re-add them from an old
checklist.

Note: `partials/shortcode-builder-fields.php` no longer exists (deleted in the
shortcode-field-registry refactor, PR boardscribe#19 / boardscribe-pro#11
(repos since renamed from equalize-digital-meeting-minutes/meeting-minutes-pro))
— don't re-add it if an old checklist still references it. Likewise
`src/js/front-end/calendarTemplates.js` and `assets/css/calendar-templates.css`
are gone, and `assets/css/admin-document-picker.css` is new.

## After building

- Sanity-check the manifest with `unzip -l dist/*.zip` — compare against the
  lists above; investigate any new file before shipping it (new
  includes/partials belong in the zip; new dev files don't). A new directory
  needs adding to that repo's `package.json` `files` **and** to the manifest
  above.
- Confirm `vendor/` in the zip contains *only* `autoload.php` + `composer/`.
  Both plugins' `scripts/dist.sh` now enforce this and fail the build
  otherwise. If any other top-level package directory shows up under `vendor/`,
  either the `--no-dev` install didn't take (re-run it) or a real runtime dependency was
  added to `composer.json` and this doc needs a rethink (a runtime dep would
  mean actual third-party library code ships too, not just the autoloader).
- Deploy order on a site: free plugin first, then Pro (Pro no-ops with an admin
  notice if free is inactive).
