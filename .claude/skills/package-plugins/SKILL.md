---
name: package-plugins
description: Build and zip the free (repo/slug boardscribe) and Pro (repo/slug boardscribe-pro) plugins into deployable dist/ zips with the correct contents. Use when asked to package, zip, release, or deploy either plugin.
---

# Package the free and/or Pro plugin into a deploy zip

**Both plugins: run `npm run dist`.** There is no manual recipe to follow any
more — each repo has its own `scripts/dist.sh`, matching the other Equalize
Digital plugins (accessibility-checker and its add-ons).

```bash
cd <free-repo>
npm run dist                     # -> dist/boardscribe-<version>.zip
npm run dist:keep-build-folder   # same, but also leaves dist/boardscribe/ in place

cd <pro-repo>                    # ../boardscribe-pro relative to the free repo
npm run dist                     # -> dist/boardscribe-pro-<version>.zip
npm run dist:keep-build-folder
```

`npm run dist` runs the JS build (free only — Pro ships plain-file JS), installs
Composer without dev dependencies, packages, and then restores the dev
dependencies. `dist:keep-build-folder` deliberately skips that last restore
because it exists for CI, where `make-pot.yml` scans the unpacked
`dist/<slug>/`; after running it locally, run `composer install` yourself to get
phpcs/phpunit back.

**What ships is each repo's `package.json` `files` allowlist**, not this
document. To add something to a zip, add it there. Three things the allowlist
cannot express, handled in `scripts/dist.sh` instead: npm force-packs
`package.json` and `README.md` whatever `files` says (both are deleted after
staging — `LICENSE` is force-packed too and that one ships), `.DS_Store` files
are stripped, and the build fails outright if `vendor/` contains anything
beyond `autoload.php` and `composer/`.

The zip contents below were verified against a real `npm run dist` run.

## Critical rules

1. **Always run the JS build first** (free repo only since the block moved there — Pro has no bundled JS anymore). Built output is gitignored (`assets/build/` in free). A zip missing it installs fine but renders an empty table / broken block editor with a 404'd script. `npm run dist` does this for you.
2. **Always run `composer install --no-dev --optimize-autoloader` before staging, and a plain `composer install` again after zipping.** Both plugins bootstrap via a Composer PSR-4 autoloader (`vendor/autoload.php`) — a zip without it fatal-errors on activation. Neither plugin has a runtime Composer dependency (only `php` itself in `require`; everything else is `require-dev` tooling), so `--no-dev` leaves `vendor/` containing only Composer's own autoloader machinery (`vendor/autoload.php` + `vendor/composer/*`, no third-party library code) — safe to ship wholesale. The restore step afterward matters: skipping it silently drops phpcs/phpunit/etc. from the working tree for the rest of the session. `npm run dist` restores them for you, including when packaging fails.
3. The zip must contain a single top-level directory named exactly like the plugin slug (`boardscribe/` or `boardscribe-pro/`) — WordPress derives the install path from it.
4. Never include: `node_modules/`, `tests/`, `docs/`, `dist/`, `scripts/`, dotfiles (`.git*`, `.eslintrc`, `.husky/`, `.editorconfig`), `composer.lock`, `package*.json`, `phpunit*`, `phpcs.xml`, `webpack.config.js`, `docker-compose.yml`. (`vendor/` is a required exception to the general "no dev-tooling directories" rule — see above.) **Ship `composer.json`:** because both plugins ship `vendor/`, the WP.org Plugin Check flags a missing top-level `composer.json` as `missing_composer_json_file` (plugin_repo category, WARNING not ERROR). Both recipes below now include `composer.json` to clear that finding. This reverses the earlier PRO-1196 decision to accept the warning — revisited while preparing the WP.org submission, where a clean Plugin Check run is worth more than avoiding a single dev-config file. `composer.lock` stays out: the check only needs `composer.json`, and the lock adds weight with no distribution benefit. Don't drop `composer.json` from the recipes without updating this note.
5. In Pro, `src/js/admin/`, `src/js/front-end/`, and `src/js/editor/` are **plain-file enqueues and MUST ship**. In free, no `src/` ships at all (everything is bundled — including the block editor script, `src/js/block/` → `assets/build/block/`). A WP.org review flagged the free zip under guideline 4 for shipping minified `assets/build/` with no reachable source; that's answered by making `equalizedigital/boardscribe` **public** and linking it from readme.txt's "Source Code and Build Process" section, not by shipping `src/`. Two consequences for packaging: the repo has to stay public and current, and if it's ever taken private again, `src/`, `webpack.config.js` and `package.json` must start shipping in the zip instead (and this recipe plus `.distignore` updated to match).
6. Both plugins keep a committed `languages/<slug>.pot`. It is the baseline `make-pot.yml` diffs against and it ships in the zip — without one, that workflow raises a translations PR on every run.

## Free plugin

`npm run dist`. Its `files` allowlist lives in `package.json`.

Expected manifest (verify with `unzip -l`):
`boardscribe.php`, `block.json`, `uninstall.php`, `readme.txt`, `LICENSE`, `composer.json`, `languages/boardscribe.pot`, `partials/{meta-box,settings-page,block-editor-preview}.php`, `assets/build/boardscribe.js`, `assets/build/block/{index.js,index.asset.php}`, `assets/build/builder/{index.js,index.asset.php}`, `assets/css/{boardscribe,builder,settings}.css`, `assets/images/logo.svg`, `vendor/autoload.php`, `vendor/composer/*.php`, `includes/Plugin.php`, `includes/{Admin/{MetaBox,SettingsPage},PostType/BoardScribeCPT,REST/BoardScribeEndpoint,Shortcode/{FieldRegistry,BoardScribeShortcode},Block/BoardScribeBlock}.php`

Note: `assets/build/builder/` and `assets/css/builder.css` (the admin Shortcode Builder React app, PRO-1228) ship via the existing wildcard `cp -r assets/build assets/css assets/images` step below with no script change — this manifest entry just documents that they're expected, not new/unexpected, when verifying with `unzip -l`. `assets/images/logo.svg` (settings-sidebar logo, rendered by partials/settings-page.php) ships via that same step — it was missing from the copy list until a build review caught the broken logo; keep `assets/images` in it.

## Pro plugin

`npm run dist`, using the same `scripts/dist.sh` as free bar the slug, driven by its own `files` allowlist. No JS build step.

Expected manifest:
`boardscribe-pro.php`, `readme.txt`, `LICENSE`, `composer.json`, `languages/boardscribe-pro.pot`, `assets/css/{admin-document-picker,pro-meta}.css`, `vendor/autoload.php`, `vendor/composer/*.php`, `partials/{pro-meta-fields,csv-import-page,license-section}.php`, `includes/{Plugin,License/LicenseManager,Admin/{ProMetaFields,DocumentPicker},PostType/{MeetingCategory,MeetingType,DocumentCPT,MeetingDocumentRelationship},Block/BlockExtensions,Import/CsvImporter}.php`, `src/js/{admin/proMeta,front-end/proColumns,front-end/yearTimelineTemplate,editor/blockEditor}.js`

Note: verified against a real `npm run dist`. Since this list was last written by hand, `includes/` gained `DocumentPicker`, `DocumentCPT`, `MeetingDocumentRelationship` and `MeetingType`, `assets/css` gained `admin-document-picker.css` and lost `calendar-templates.css`, and `src/js/front-end/calendarTemplates.js` is gone.

Note: Pro no longer ships `block.json`, `build/`, or `partials/block-editor-preview.php` — the block moved to the free plugin (paired `feat/move-block-to-free` branches). Don't re-add them from an old checklist.

Note: `partials/shortcode-builder-fields.php` no longer exists (deleted in the shortcode-field-registry refactor, PR boardscribe#19 / boardscribe-pro#11 (repos since renamed from equalize-digital-meeting-minutes/meeting-minutes-pro)) — don't re-add it if an old checklist still references it.

## After building

- Sanity-check both manifests with `unzip -l dist/*.zip` — compare against the lists above; investigate any new file before shipping it (new includes/partials belong in the zip; new dev files don't). Confirm `vendor/` in the zip contains *only* `autoload.php` + `composer/`. Both plugins' `scripts/dist.sh` enforce this and fail the build otherwise — if any other top-level package directory shows up under `vendor/`, the `--no-dev` install didn't take (re-run it) or a real runtime dependency was added to `composer.json` and this doc needs a rethink (a runtime dep would mean actual third-party library code ships too, not just the autoloader).
- If a directory was added to either plugin since 2026-07-07, update the `cp` list here AND the expected manifest.
- Deploy order on a site: free plugin first, then Pro (Pro no-ops with an admin notice if free is inactive).
