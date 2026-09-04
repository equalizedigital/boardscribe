# BoardScribe (by Equalize Digital)

Guidance for coding agents working in this repository. `CLAUDE.md` imports this file, so Claude Code, Codex and anything else that reads `AGENTS.md` all get the same instructions — keep this file self-contained and edit it here rather than adding a second copy.

WordPress plugin — display name "BoardScribe", WP.org slug/text domain `boardscribe`, code prefix `edbs_`/`EDBS_`; git repo `equalizedigital/boardscribe` (renamed from the historical `equalize-digital-meeting-minutes` — old remote URLs redirect), local checkout directory `boardscribe`. Manages meeting minutes as a custom post type (`edbs_boardscribe`), displayed via a `[edbs_boardscribe]` shortcode backed by a public REST endpoint (`/edbs/v1/boardscribe/`), plus a Gutenberg block (`equalize-digital/boardscribe`) that renders through the same shortcode pipeline (moved here from Pro — the block name is stored in existing content and must never change). Native WordPress storage only — no ACF dependency (removed during the restructure).

## This is the free plugin — Pro is a separate sibling repo

This repo is the **free, WordPress.org-distributed** half of a freemium product. The **Pro plugin** lives in a separate repo, checked out locally as a sibling plugin directory (`../boardscribe-pro`, remote `equalizedigital/boardscribe-pro` — renamed from `meeting-minutes-pro` — with its own `CLAUDE.md`); the two repos are developed in lockstep with matching branch names for paired features. This split drives real architecture decisions here:

- Every feature added to this free plugin should ask "how would Pro hook into or override this?" before being considered done.
- Prefer `apply_filters()`/`do_action()` extension points over hardcoding, especially around: CPT registration args, REST route args/query building, row/output formatting, and anywhere shortcode/table markup is assembled.
- If you extract or refactor logic that Pro would need to reuse (e.g. the per-row escaping/formatting logic), expose it as a callable public method rather than leaving it inline — duplicated logic in Pro risks re-diverging from security fixes made here.
- The premium feature list, the free/premium split, and the business context behind that split are tracked privately, not in this public repo. When a change hinges on which side of the free/Pro line a feature falls, ask rather than inferring it from the code.
- Full audit of what's needed for Pro to extend "basically everything" is an ongoing effort — check recent PRs/commit history for the state of this work before assuming a gap exists.

## Directory structure

```
boardscribe.php                        Plugin bootstrap (constants, requires, plugins_loaded hook)
block.json                             Block metadata for equalize-digital/boardscribe (editorScript points at assets/build/block/)
includes/
  Plugin.php                           Singleton; boots all components, fires edbs_loaded (Pro's entry point)
  PostType/BoardScribeCPT.php          CPT registration
  Admin/MetaBox.php                    Native meta box UI + save handling
  Admin/SettingsPage.php               Tabbed settings page (General / Shortcode Builder / Support); the Builder tab enqueues the React builder app
  Helpers/Helpers.php                  UTM link builder for outbound equalizedigital.com links (edition + days_active reporting)
  REST/BoardScribeEndpoint.php      /edbs/v1/boardscribe/ REST route + query/row building
  Shortcode/BoardScribeShortcode.php Shortcode registration, asset enqueuing, instance config
  Shortcode/FieldRegistry.php        Single source of truth for field-backed shortcode attributes (see edbs_shortcode_field_registry below)
  Block/BoardScribeBlock.php           Server-registered block wrapping the shortcode pipeline + editor preview
  Import/CsvImporter.php               CSV bulk-import (Settings page "Import" tab); columns/per-row field-saving are filterable (see edbs_csv_import_columns / edbs_csv_import_row_meta below) so Pro adds its own columns (location, livestream/recording/cc-transcript URLs, documents, date display override, category) without forking the importer
src/js/                                Frontend source modules (ES modules, bundled by webpack)
  index.js                             Entry: exposes window globals, registers table template, bootstraps
  registries.js                        window.edbsExtraColumns/edbsTemplates setup + the template contract docs
  config.js                            Reads window.edbsConfig (i18n, REST base URL)
  templates/table.js                   Built-in "table" display template
  defaults/                            Core fallbacks a template inherits unless it overrides them
    renderInfo.js / renderPagination.js / request.js / focus.js
  instance.js                          Per-shortcode-instance controller (template resolution, URL state, fetch; urlState:false opts an instance out of URL read/pushState/popstate)
  block/index.js                       Block editor script (separate webpack entry -> assets/build/block/)
  builder/                             Admin Shortcode Builder React app (separate webpack entry -> assets/build/builder/)
    index.js / app.js                  Mount + BuilderApp (grouped registry fields, generated shortcode, copy)
    build-shortcode.js / preview.js    Shortcode string generation rules; live preview running the real frontend pipeline
  shared/generic-field-control.js      Per-FieldRegistry-type control renderer shared by block + builder bundles
partials/block-editor-preview.php      One preview table (columns × rows), required per section by render_preview_table()
assets/build/                          Bundled output (GITIGNORED — run `npm run build`; release packaging must build)
assets/css/boardscribe.css             Frontend styles
assets/css/builder.css                 Admin Shortcode Builder tab layout (two-column, sticky preview)
assets/css/settings.css                Settings page layout (sidebar + tabbed panel, BoardScribe brand colors)
assets/images/logo.png                 BoardScribe logo shown in the settings sidebar header
uninstall.php                         Opt-in data cleanup on plugin deletion (edbs_settings, edbs_activation_date)
docs/                                 Planning docs (market research, premium features, readiness checklist), not user-facing — except docs/hooks.md, which is auto-generated by tools/generate-hooks-docs.php (see composer run generate-hooks-docs) and linked from README.md
tools/generate-hooks-docs.php         Regex-scans includes/ and partials/ for edbs_-prefixed do_action/apply_filters (and add_action/add_filter) calls, writes docs/hooks.md. Weekly CI (verify-hooks-docs.yml) opens a PR against develop if it drifts from the code.
tests/                                PHPUnit setup (tests/phpunit) and Jest suite (tests/jest, config jest.config.js)
```

## Extension points (hooks/filters) currently in the free plugin

Keep this list current when adding or removing hooks — it's the primary reference for what Pro can already do.

| Hook | Type | File | Purpose |
|---|---|---|---|
| `edbs_loaded` | action | `Plugin.php` | Fires after all free components register. **Pro plugin's sole entry point.** |
| `edbs_before_register_cpt` / `edbs_after_register_cpt` | action | `PostType/BoardScribeCPT.php` | Register taxonomies that bind to the CPT (e.g. meeting series). |
| `edbs_rest_query_args` | filter | `REST/BoardScribeEndpoint.php` | Modify the `WP_Query` args before querying meeting minutes. |
| `edbs_rest_max_per_page` / `edbs_rest_absolute_max_per_page` | filter | `REST/BoardScribeEndpoint.php` | Bounds on the public REST endpoint's page size: positive `posts_per_page` values are capped at 100 (`edbs_rest_max_per_page`), and the builder's `-1` "show all" resolves to 500 (`edbs_rest_absolute_max_per_page`) rather than truly unbounded — the route is anonymous, so `-1` can't be honored literally. Raise either if a site legitimately needs more. |
| `edbs_agenda_link` / `edbs_minutes_link` | filter | `REST/BoardScribeEndpoint.php` | Override the built `<a>` markup for agenda/minutes links (used by Accessibility Checker Pro integration). |
| `edbs_meeting_row_data` | filter | `REST/BoardScribeEndpoint.php` | Add/override fields on a single meeting's REST row data. |
| `edbs_meeting_formatted_date` | filter | `REST/BoardScribeEndpoint.php` | Override the computed date display string before it's used in the date cell and in the agenda/minutes link aria-labels (used by Pro's per-meeting date display override — sort order is unaffected, it's driven by the raw `edbs_meeting_date` value). |
| `edbs_rest_response` | filter | `REST/BoardScribeEndpoint.php` | Modify the full REST response before it's returned. |
| `edbs_enqueue_assets` | action | `Shortcode/BoardScribeShortcode.php` | Fires after core CSS/JS enqueue — Pro enqueues its own assets here. Also fires on the settings page's Shortcode Builder tab (its live preview runs the real frontend pipeline via the now-public `enqueue_assets()`), so callbacks must be admin-context-safe — see `docs/HOOK-CONTRACT-CHANGES.md`. |
| `edbs_shortcode_field_registry` | filter | `Shortcode/FieldRegistry.php` | **Single source of truth for every field-backed shortcode attribute** (the `template` attribute's own *value* is a registry field like any other, but which template name means what is still owned by `window.edbsTemplates`/the `template` row below, not this filter). Filters the list of field descriptors (`key`, `type`, `group`, `label`, `default`, `choices`, `sanitize_callback`, `validate_callback`, `rest_arg`, `config_key`, `block_attribute_key`, `hidden_from_ui` — see `FieldRegistry::add_core_fields()` docblock for the full shape). One descriptor drives the shortcode-attribute default, the per-instance JS config value, the settings-page builder app's control (the React app in `src/js/builder/` consumes `FieldRegistry::js_schema()`, grouped by `group`: `general`/`column_labels`/`hide_columns`/`show_columns`/`link_labels`), the REST route arg (when `rest_arg` is `true`), and the block's attribute + InspectorControl (`Block/BoardScribeBlock.php` builds both from this registry, so a Pro field automatically appears in the block sidebar) — no need to touch four separate places for one new field. `hidden_from_ui: true` excludes a field from `js_schema()` (both the builder app and the block sidebar) while leaving it fully valid in `all()` (shortcode/REST/block-attribute parsing) — lets a licensable plugin (Pro) stop offering a field as a *new* pick without invalidating instances that already used it; see Pro's `Plugin::boot()`/`ProMetaFields` for the concrete usage. Six field `type`s cover every control needed: `text`, `textarea`, `checkbox`, `select`, `number`, `number_with_all` (a number input paired with a "show all" toggle that resolves to `-1`/no-limit). Replaces the pre-registry `edbs_shortcode_atts`, `edbs_shortcode_instance_config`, `edbs_shortcode_builder_fields`, `edbs_shortcode_builder_label_fields`, and `edbs_shortcode_builder_hide_fields` hooks — see `docs/HOOK-CONTRACT-CHANGES.md` for the migration. |
| `edbs_use_native_meta_boxes` | filter | `Admin/MetaBox.php` | Return `false` to suppress the native meta box UI entirely (for a Pro replacement). |
| `edbs_default_meeting_title` | filter | `Admin/MetaBox.php` | Overrides the auto-generated title used when a meeting is saved with a blank title ("Board Meeting - {formatted date}"). Receives `$title, $meeting_date, $post_id`. Generation logic lives in the public `MetaBox::generate_default_title()`. |
| `edbs_before_meta_box_fields` / `edbs_meta_fields` | action | `Admin/MetaBox.php` | Render additional meta box fields before/after the defaults. |
| `edbs_after_agenda_url_field` / `edbs_after_minutes_url_field` | action | `partials/meta-box.php` | Fire immediately after the Agenda URL / Minutes URL field's own `<tr>`, so a plugin adding a field tightly coupled to one of those URLs (e.g. Pro's Document picker, which the URL field defers to) can render its row directly underneath it — `edbs_meta_fields` only fires once, after every default field. |
| `edbs_save_meeting_meta` | action | `Admin/MetaBox.php` | Fires after the default meta fields are saved — save Pro's own meta here. |
| `edbs_utm_query_args` | filter | `Helpers/Helpers.php` | Query parameters appended to outbound equalizedigital.com links by `Helpers::utm_link_builder()` (utm_source/medium/campaign/content plus php_version, platform, platform_version, software, software_version, days_active). `software` is `free`, `pro-unlicensed`, or `pro` — resolved by reading Pro's `edbs_pro_license_status` option directly, since free can't call `LicenseManager::is_licensed()`. **If Pro ever renames that option, this breaks silently**; Pro should then override `software` through this filter. |
| `edbs_settings_fields` | action | `Admin/SettingsPage.php` | Add sections to the General tab's form outside the Settings API (e.g. license management). Fires inside the General tab only. |
| `edbs_settings_tabs` | filter | `Admin/SettingsPage.php` | Register a settings-page tab. Filters the tab map (slug => `[ 'icon' => dashicons class, 'label' => string ]`); array order is display order. Core tabs: `general`, `builder`, `support`. Pro adds e.g. an `import` tab here (paired with the action below), replacing its old standalone `edbs-import` submenu. Pro enqueues its tab's own assets keyed off the `edbs_meeting_page_edbs-settings` hook + active `tab` query var (same pattern as the builder tab). |
| `edbs_settings_tab_content_{$tab}` | action | `Admin/SettingsPage.php` | Renders the panel content for a non-core tab registered via `edbs_settings_tabs`. The dynamic portion is the tab slug (e.g. `edbs_settings_tab_content_import`). Only fires for tabs present in the filtered tab list. |
| `window.edbsExtraColumns` (JS, not a WP hook) | registry | `src/js/registries.js` | Push `{ key, label, renderCell }` objects before `DOMContentLoaded` to add table columns. `renderCell()`/`label` output is inserted as raw HTML — must escape untrusted data itself. |
| `window.edbsTemplates` (JS, not a WP hook) | registry | `src/js/registries.js` | Display-template registry, keyed by template name; selected per instance via the shortcode's `template=""` attribute. A template provides `render( data, instanceCfg, container )` (required) plus optional `renderPagination`/`renderInfo`/`focus` overrides and optional request-side overrides — `buildRequestUrl( instanceCfg, page )` to point the default fetch at a different/extended URL, or `request( instanceCfg, page )` returning a Promise to replace the request entirely (pairs with the server-side `edbs_rest_route_args`/`edbs_rest_query_args` filters). Anything not overridden keeps the core implementation (default REST query, pagination buttons, URL state, aria-live info). The built-in table is registered here as `table`; unknown names fall back to it. Output is raw HTML — templates must escape untrusted data (`window.edbsEscapeAttr` is exposed for this). `instanceCfg.resolvedTemplate` (set by core, distinct from `instanceCfg.template` which may be blank/unrecognized) is the name actually rendering after the fallback; every template should add an `edbs-template-<resolvedTemplate>` class to its own root element(s) so sites can target one template's output in CSS with no `class=""` attribute needed — the built-in table uses `edbs-template-table`. |
| `template` shortcode attribute | config | `Shortcode/BoardScribeShortcode.php` | Names the `window.edbsTemplates` entry an instance renders with (sanitized with `sanitize_key()`, passed through `data-config`). Free ships only `table` — including in the template field descriptor's `choices`, so a plugin adding a template must both register it on `window.edbsTemplates` and append its choice to the `template` descriptor via `edbs_shortcode_field_registry` to make it selectable in the builder and block pickers (the block's picker renders whenever the field is present in the registry with at least one choice, same as the builder page — it is not conditional on more than one choice existing). |
| `window.edbsInitInstance( container )` (JS, not a WP hook) | helper | `src/js/instance.js` (exposed in `src/js/index.js`) | Initialises one `.edbs-boardscribe-wrap` instance on demand — for wrappers injected after `DOMContentLoaded` (the admin builder's live preview re-inits its instance this way per config change). Pass `urlState: false` in the instance's `data-config` to skip URL page read/pushState/popstate entirely (the preview does, so paginating it never rewrites the admin URL). |
| `window.edbsBuildTable( meetings, instanceCfg )` (JS, not a WP hook) | helper | `src/js/templates/table.js` | Returns a standard `<table>` HTML string (same columns/labels/`window.edbsExtraColumns` handling, and the calling template's own `edbs-template-<name>` class via `instanceCfg.resolvedTemplate`) as the built-in table template. A template that renders multiple tables/sections (e.g. one per year) calls this per section instead of re-implementing column-building logic by hand. |
| `edbs:table-rendered` / `edbs:info-rendered` / `edbs:pagination-rendered` / `edbs:page-changed` / `edbs:fetch-error` (JS, DOM `CustomEvent`) | event | `src/js/instance.js` | Bubbling events each instance dispatches on its own `.edbs-boardscribe-wrap` container at the corresponding lifecycle point — bind with `container.addEventListener()` or `document.addEventListener()` (the latter catches every instance; `event.target` is the firing container). No build step needed, same no-dependency principle as the registries below. `event.detail` always includes `instanceCfg`; see the contract doc in `registries.js` for each event's full shape. |
| `edbs_cpt_args` | filter | `PostType/BoardScribeCPT.php` | Modify CPT registration args (e.g. enable `public`/`rewrite`/`has_archive`, or a custom `capability_type`) before `register_post_type()`. |
| `edbs_rest_route_args` | filter | `REST/BoardScribeEndpoint.php` | Add REST-only params to the `/edbs/v1/boardscribe/` route's registered args schema that aren't backed by any shortcode/builder/block field (e.g. full-text search). Fields registered via `edbs_shortcode_field_registry` with `rest_arg => true` are already added before this filter runs — use that instead when the param also needs a builder/block field. |
| `BoardScribeEndpoint::build_meeting_row()` | public method | `REST/BoardScribeEndpoint.php` | Builds one meeting's escaped/formatted row data (title, date, agenda/minutes links) outside the REST loop — reuse this rather than re-implementing the same escaping for CSV/PDF export, an iCal feed, or a widget. Fires `edbs_meeting_row_data` internally; see `docs/HOOK-CONTRACT-CHANGES.md` for a signature caveat. |
| `edbs_before_table` / `edbs_after_table` | action | `Shortcode/BoardScribeShortcode.php` | Fires inside the shortcode wrapper, before/after the table container — for lightweight additions (search box, add-to-calendar button, comment form) that don't need a full display-template override. |
| `edbs_csv_import_columns` | filter | `Import/CsvImporter.php` | Filters the recognised CSV import columns, keyed by column key (`[ 'required' => bool, 'notes' => string ]`). Free ships `title` (optional — a blank value falls back to `MetaBox::generate_default_title()`, the same "Board Meeting - {date}" auto-title used when a meeting is saved without one), `date` (required), `agenda_url`, `minutes_url`, `not_held`, `publish_date`. A column added here appears in the Import tab's column-reference table and (when `required`) is enforced during import; it does not by itself save anything — read the value off `edbs_csv_import_row_meta`. Pro adds `location`, `livestream_url`, `recording_url`, `cc_transcript_url`, `documents`, `date_display_override`, and `category` here. |
| `edbs_csv_import_row_meta` | action | `Import/CsvImporter.php` | Fires once per imported CSV row, after the core fields (title, date, agenda/minutes URL, not-held) are saved to the new meeting post. Receives `$post_id, $data` — `$data` is the raw CSV row keyed by lowercased column header, trimmed but not sanitized. A plugin extending the importer (e.g. Pro) sanitizes and saves its own columns' post meta / taxonomy terms here. |
| `edbs_block_preview_columns` | filter | `Block/BoardScribeBlock.php` | Column list for the block editor's server-rendered preview. Each entry: `label`, `hidden` (bool), `render_cell` — `fn( array $row, \WP_Post $post ): string` returning **pre-escaped** cell HTML (same trust contract as `window.edbsExtraColumns` `renderCell()`). `$row` is `build_meeting_row()` output, so fields added via `edbs_meeting_row_data` are available — Pro adds its five columns here. |
| `edbs_block_editor_preview` | filter | `Block/BoardScribeBlock.php` | Short-circuit the editor preview with template-specific markup (return a string to use it; null falls through to the default flat table). Receives `$attributes, $rows, $columns, $block` — the plugin owning a display template hooks here (Pro's year-timeline renders one table per year group via `render_preview_table()`). |
| `edbs_block_preview_max_rows` | filter | `Block/BoardScribeBlock.php` | Row cap for the editor preview (default 5, applied even when postsPerPage is -1). Pro raises it to 30 for year-timeline so multiple year groups are visible. |
| `BoardScribeBlock::render_preview_table()` | public method | `Block/BoardScribeBlock.php` | Renders one preview table for a column/row set — the PHP analogue of `window.edbsBuildTable()`. An `edbs_block_editor_preview` callback rendering multiple sections calls this per section instead of duplicating the table markup. |
| `edbs.block.templateChangeAttributes` (JS, wp.hooks filter) | filter | `src/js/block/index.js` | Applied to the attribute-changes object (`{ template }`) when the block's template picker changes, with context `{ attributes, postsPerPage, lastCustomPostsPerPage }` — lets the plugin owning a template couple other attributes to the switch (Pro auto-sets postsPerPage to -1 for year-timeline). |

**Resolved gap (was a known open question):** the table's *rendering strategy* is now overridable via the `window.edbsTemplates` registry + `template=""` shortcode attribute (see table above) — Pro's accordion/card-grid/timeline templates plug in there.

**Before changing any existing hook's call signature** (not just adding new ones), check and update `docs/HOOK-CONTRACT-CHANGES.md` — the free and Pro plugins are separate repos with no compiler to catch a Pro callback written against an old contract, so this doc is the only cross-repo record of breaking changes. Check it before every Pro release.

## Commands

```bash
composer install && npm install     # setup
npm run build                       # webpack (wp-scripts) — REQUIRED after clone; assets/build/ is gitignored
npm start                           # webpack watch mode during JS development

composer lint                       # php-parallel-lint (syntax check)
composer check-cs                   # phpcs (WordPress Coding Standards) — CI's "CS" check
composer fix-cs                     # phpcbf (auto-fix what's fixable)
npm run lint:php[:fix]              # phpcs / phpcbf via npm
npm run lint:js[:fix]               # eslint over src/js, tests/jest, webpack.config.js, jest.config.js — CI's "Lint: JS" check
npm run linting                     # phpcs + eslint together

npm run test:js                     # Jest (wp-scripts test-unit-js)
npm run test:php                    # set up the docker PHPUnit env (./scripts/setup-phpunit.sh)
npm run test:php:run                # run PHPUnit in the container
npm run test:php:coverage           # PHPUnit with clover + html coverage
npm run test:php:stop
composer test                       # phpunit directly (--testdox; needs setup-phpunit.sh first)

npm run dist                        # build + package a release zip
npm run dist:keep-build-folder      # same, leaving assets/build/ in place
composer generate-hooks-docs        # regenerate docs/hooks.md (tools/generate-hooks-docs.php)

./scripts/prep_release.sh (major|minor|patch)  # bump version in boardscribe.php/package.json/readme.txt, branch off develop as release/<version>, push, print a develop..main changelog and PR link (needs `npm install -g semver`)
```

`webpack.config.js` exports **three configs**: the frontend bundle (`src/js/index.js` → `assets/build/boardscribe.js`, `DependencyExtractionWebpackPlugin` dropped — no `*.asset.php`; its `output.clean` keeps the `block/` and `builder/` subdirectories since the compilers share `assets/build/`), the block editor bundle (`src/js/block/index.js` → `assets/build/block/index.js`, which **keeps** the plugin so `block.json`'s `editorScript` gets its `index.asset.php` dependencies/version), and the shortcode builder bundle (`src/js/builder/index.js` → `assets/build/builder/index.js`, also keeping the plugin — `SettingsPage::enqueue_assets()` reads its `index.asset.php`). `@wordpress/*` imports resolve to `wp.*` globals via a hand-maintained `externals` map in `webpack.config.js`; each entry there needs its matching `wp-*` handle in the `wp_enqueue_script()` dependency list in `BoardScribeShortcode.php` (the enqueue uses `EDBS_VERSION` for cache-busting). Frontend JS uses `__()` from `@wordpress/i18n` with the `boardscribe` text domain; `wp_set_script_translations( 'edbs-boardscribe', 'boardscribe' )` after the enqueue loads the JSON translation files for those calls. Because `assets/build/` is gitignored, any release/deploy packaging **must run `npm run build`** — shipping a zip without it means a 404'd script and an empty table.

`phpcs.xml` is scoped to `.php` files only (`<arg name="extensions" value="php"/>`) — JS/CSS have their own dedicated linters and must not be re-added to PHPCS's scope; WordPress's PHP-oriented sniffs actively conflict with the JS style enforced by ESLint (e.g. `function (` vs `function(`).

## CI checks (GitHub Actions)

`CS`, `Lint: JS`, `Build: JS` (webpack build must compile; the bundle itself is gitignored so CI is the only guard), `Lint: PHP` (7.4–8.2), `Security check`, `Test` (multiple PHP × WP version combos). `WordPress version checker` is not load-bearing for feature PRs. `backport-to-develop` runs on every PR merged into `main` and opens an automatic backport PR of that same branch into `develop`, so a change that lands on `main` (a hotfix, a release-prep commit) doesn't silently drift out of `develop`.

## Workflow

- **One PR per logical change** — don't bundle unrelated fixes together.
- Two long-lived branches: `develop` (active development, target most feature/fix PRs here) and `main` (stable/release branch, matches what's tagged for WordPress.org). Branch off `develop` for normal work; the `backport-to-develop` workflow auto-opens a PR to reconcile anything merged directly into `main`.
- **Commits should be small and atomic** — each commit covers one minimal, self-contained chunk of related changes. Prefer several small commits within a PR over one large one; it keeps review and `git blame`/history useful even when the PR itself bundles a few related fixes.
- **Use Conventional Commits style wherever possible** (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `test:`, etc.) for commit subject lines.
- **Always wait for review comments — including AI reviewers (CodeRabbit, Gemini Code Assist)** — before considering a PR done or merging. Don't skim past a "pending"/"in progress" AI review status. When findings land, surface them for discussion before fixing anything.
- CodeRabbit and Gemini Code Assist both auto-review PRs on this repo; expect both, not just one.
- When replying to review threads, reference the specific commit hash that addressed the finding.
