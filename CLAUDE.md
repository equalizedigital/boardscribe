# BoardScribe (by Equalize Digital)

WordPress plugin — display name "BoardScribe", WP.org slug/text domain `boardscribe`, code prefix `edbs_`/`EDBS_`; git repo `equalizedigital/boardscribe` (renamed from the historical `equalize-digital-meeting-minutes` — old remote URLs redirect), local checkout directory `boardscribe`. Manages meeting minutes as a custom post type (`edbs_boardscribe`), displayed via a `[edbs_boardscribe]` shortcode backed by a public REST endpoint (`/edbs/v1/boardscribe/`), plus a Gutenberg block (`equalize-digital/boardscribe`) that renders through the same shortcode pipeline (moved here from Pro — the block name is stored in existing content and must never change). Native WordPress storage only — no ACF dependency (removed during the restructure; some docs still mention it, see Known Doc Staleness below).

## This is the free plugin — Pro is a separate sibling repo

This repo is the **free, WordPress.org-distributed** half of a freemium product. The **Pro plugin** lives in a separate repo, checked out locally as a sibling plugin directory (`../boardscribe-pro`, remote `equalizedigital/boardscribe-pro` — renamed from `meeting-minutes-pro` — with its own `CLAUDE.md`); the two repos are developed in lockstep with matching branch names for paired features. This split drives real architecture decisions here:

- Every feature added to this free plugin should ask "how would Pro hook into or override this?" before being considered done.
- Prefer `apply_filters()`/`do_action()` extension points over hardcoding, especially around: CPT registration args, REST route args/query building, row/output formatting, and anywhere shortcode/table markup is assembled.
- If you extract or refactor logic that Pro would need to reuse (e.g. the per-row escaping/formatting logic), expose it as a callable public method rather than leaving it inline — duplicated logic in Pro risks re-diverging from security fixes made here.
- See `docs/PREMIUM-FEATURES.md` for the full premium feature list and the free/premium split, and `docs/MARKET-RESEARCH.md` for the business context behind that split.
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
  REST/BoardScribeEndpoint.php      /edbs/v1/boardscribe/ REST route + query/row building
  Shortcode/BoardScribeShortcode.php Shortcode registration, asset enqueuing, instance config
  Block/BoardScribeBlock.php           Server-registered block wrapping the shortcode pipeline + editor preview
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
uninstall.php                         Opt-in data cleanup on plugin deletion
docs/                                 Planning docs (market research, premium features, readiness checklist) — not user-facing
tests/                                PHPUnit setup
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
| `edbs_shortcode_field_registry` | filter | `Shortcode/FieldRegistry.php` | **Single source of truth for every field-backed shortcode attribute** (the `template` attribute's own *value* is a registry field like any other, but which template name means what is still owned by `window.edbsTemplates`/the `template` row below, not this filter). Filters the list of field descriptors (`key`, `type`, `group`, `label`, `default`, `choices`, `sanitize_callback`, `validate_callback`, `rest_arg`, `config_key`, `block_attribute_key` — see `FieldRegistry::add_core_fields()` docblock for the full shape). One descriptor drives the shortcode-attribute default, the per-instance JS config value, the settings-page builder app's control (the React app in `src/js/builder/` consumes `FieldRegistry::js_schema()`, grouped by `group`: `general`/`column_labels`/`hide_columns`/`show_columns`/`link_labels`), the REST route arg (when `rest_arg` is `true`), and the block's attribute + InspectorControl (`Block/BoardScribeBlock.php` builds both from this registry, so a Pro field automatically appears in the block sidebar) — no need to touch four separate places for one new field. Six field `type`s cover every control needed: `text`, `textarea`, `checkbox`, `select`, `number`, `number_with_all` (a number input paired with a "show all" toggle that resolves to `-1`/no-limit). Replaces the pre-registry `edbs_shortcode_atts`, `edbs_shortcode_instance_config`, `edbs_shortcode_builder_fields`, `edbs_shortcode_builder_label_fields`, and `edbs_shortcode_builder_hide_fields` hooks — see `docs/HOOK-CONTRACT-CHANGES.md` for the migration. |
| `edbs_use_native_meta_boxes` | filter | `Admin/MetaBox.php` | Return `false` to suppress the native meta box UI entirely (for a Pro replacement). |
| `edbs_default_meeting_title` | filter | `Admin/MetaBox.php` | Overrides the auto-generated title used when a meeting is saved with a blank title ("Board Meeting - {formatted date}"). Receives `$title, $meeting_date, $post_id`. Generation logic lives in the public `MetaBox::generate_default_title()`. |
| `edbs_before_meta_box_fields` / `edbs_meta_fields` | action | `Admin/MetaBox.php` | Render additional meta box fields before/after the defaults. |
| `edbs_after_agenda_url_field` / `edbs_after_minutes_url_field` | action | `partials/meta-box.php` | Fire immediately after the Agenda URL / Minutes URL field's own `<tr>`, so a plugin adding a field tightly coupled to one of those URLs (e.g. Pro's Document picker, which the URL field defers to) can render its row directly underneath it — `edbs_meta_fields` only fires once, after every default field. |
| `edbs_save_meeting_meta` | action | `Admin/MetaBox.php` | Fires after the default meta fields are saved — save Pro's own meta here. |
| `edbs_settings_fields` | action | `Admin/SettingsPage.php` | Add sections to the General tab's form outside the Settings API (e.g. license management). Fires inside the General tab only. |
| `edbs_settings_tabs` | filter | `Admin/SettingsPage.php` | Register a settings-page tab. Filters the tab map (slug => `[ 'icon' => dashicons class, 'label' => string ]`); array order is display order. Core tabs: `general`, `builder`, `support`. Pro adds e.g. an `import` tab here (paired with the action below), replacing its old standalone `edbs-import` submenu. Pro enqueues its tab's own assets keyed off the `edbs_meeting_page_edbs-settings` hook + active `tab` query var (same pattern as the builder tab). |
| `edbs_settings_tab_content_{$tab}` | action | `Admin/SettingsPage.php` | Renders the panel content for a non-core tab registered via `edbs_settings_tabs`. The dynamic portion is the tab slug (e.g. `edbs_settings_tab_content_import`). Only fires for tabs present in the filtered tab list. |
| `window.edbsExtraColumns` (JS, not a WP hook) | registry | `src/js/registries.js` | Push `{ key, label, renderCell }` objects before `DOMContentLoaded` to add table columns. `renderCell()`/`label` output is inserted as raw HTML — must escape untrusted data itself. |
| `window.edbsTemplates` (JS, not a WP hook) | registry | `src/js/registries.js` | Display-template registry, keyed by template name; selected per instance via the shortcode's `template=""` attribute. A template provides `render( data, instanceCfg, container )` (required) plus optional `renderPagination`/`renderInfo`/`focus` overrides and optional request-side overrides — `buildRequestUrl( instanceCfg, page )` to point the default fetch at a different/extended URL, or `request( instanceCfg, page )` returning a Promise to replace the request entirely (pairs with the server-side `edbs_rest_route_args`/`edbs_rest_query_args` filters). Anything not overridden keeps the core implementation (default REST query, pagination buttons, URL state, aria-live info). The built-in table is registered here as `table`; unknown names fall back to it. Output is raw HTML — templates must escape untrusted data (`window.edbsEscapeAttr` is exposed for this). `instanceCfg.resolvedTemplate` (set by core, distinct from `instanceCfg.template` which may be blank/unrecognized) is the name actually rendering after the fallback; every template should add an `edbs-template-<resolvedTemplate>` class to its own root element(s) so sites can target one template's output in CSS with no `class=""` attribute needed — the built-in table uses `edbs-template-table`. |
| `template` shortcode attribute | config | `Shortcode/BoardScribeShortcode.php` | Names the `window.edbsTemplates` entry an instance renders with (sanitized with `sanitize_key()`, passed through `data-config`). Free ships only `table` — including in the template field descriptor's `choices`, so a plugin adding a template must both register it on `window.edbsTemplates` and append its choice to the `template` descriptor via `edbs_shortcode_field_registry` to make it selectable in the builder and block pickers (the block's picker is hidden entirely when only one choice exists). |
| `window.edbsInitInstance( container )` (JS, not a WP hook) | helper | `src/js/instance.js` (exposed in `src/js/index.js`) | Initialises one `.edbs-boardscribe-wrap` instance on demand — for wrappers injected after `DOMContentLoaded` (the admin builder's live preview re-inits its instance this way per config change). Pass `urlState: false` in the instance's `data-config` to skip URL page read/pushState/popstate entirely (the preview does, so paginating it never rewrites the admin URL). |
| `window.edbsBuildTable( meetings, instanceCfg )` (JS, not a WP hook) | helper | `src/js/templates/table.js` | Returns a standard `<table>` HTML string (same columns/labels/`window.edbsExtraColumns` handling, and the calling template's own `edbs-template-<name>` class via `instanceCfg.resolvedTemplate`) as the built-in table template. A template that renders multiple tables/sections (e.g. one per year) calls this per section instead of re-implementing column-building logic by hand. |
| `edbs_cpt_args` | filter | `PostType/BoardScribeCPT.php` | Modify CPT registration args before `register_post_type()` — e.g. change the `board-meetings` rewrite slug, enable `has_archive` for a real archive page, disable `public`/`rewrite` back to `false`, or set a custom `capability_type`. `public` defaults to `true` with `rewrite => ['slug' => 'board-meetings']` (a meeting has a real single-page permalink, falling back to the theme's generic single template since this CPT only supports `title`; plugins like ArchiveWP that key their own features off the `public` flag directly can see it), `has_archive` defaults to `false` — no generated archive listing. |
| `edbs_rest_route_args` | filter | `REST/BoardScribeEndpoint.php` | Add REST-only params to the `/edbs/v1/boardscribe/` route's registered args schema that aren't backed by any shortcode/builder/block field (e.g. full-text search). Fields registered via `edbs_shortcode_field_registry` with `rest_arg => true` are already added before this filter runs — use that instead when the param also needs a builder/block field. |
| `BoardScribeEndpoint::build_meeting_row()` | public method | `REST/BoardScribeEndpoint.php` | Builds one meeting's escaped/formatted row data (title, date, agenda/minutes links) outside the REST loop — reuse this rather than re-implementing the same escaping for CSV/PDF export, an iCal feed, or a widget. Fires `edbs_meeting_row_data` internally; see `docs/HOOK-CONTRACT-CHANGES.md` for a signature caveat. |
| `edbs_before_table` / `edbs_after_table` | action | `Shortcode/BoardScribeShortcode.php` | Fires inside the shortcode wrapper, before/after the table container — for lightweight additions (search box, add-to-calendar button, comment form) that don't need a full display-template override. |
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
npm run lint:js                     # eslint over src/js + webpack.config.js — CI's "Lint: JS" check

composer test                       # phpunit (requires ./scripts/setup-phpunit.sh first, or Docker via npm run test:php)
```

`webpack.config.js` exports **three configs**: the frontend bundle (`src/js/index.js` → `assets/build/boardscribe.js`, `DependencyExtractionWebpackPlugin` dropped — no `*.asset.php`; its `output.clean` keeps the `block/` and `builder/` subdirectories since the compilers share `assets/build/`), the block editor bundle (`src/js/block/index.js` → `assets/build/block/index.js`, which **keeps** the plugin so `block.json`'s `editorScript` gets its `index.asset.php` dependencies/version), and the shortcode builder bundle (`src/js/builder/index.js` → `assets/build/builder/index.js`, also keeping the plugin — `SettingsPage::enqueue_assets()` reads its `index.asset.php`). `@wordpress/*` imports resolve to `wp.*` globals via a hand-maintained `externals` map in `webpack.config.js`; each entry there needs its matching `wp-*` handle in the `wp_enqueue_script()` dependency list in `BoardScribeShortcode.php` (the enqueue uses `EDBS_VERSION` for cache-busting). Frontend JS uses `__()` from `@wordpress/i18n` with the `boardscribe` text domain; `wp_set_script_translations( 'edbs-boardscribe', 'boardscribe' )` after the enqueue loads the JSON translation files for those calls. Because `assets/build/` is gitignored, any release/deploy packaging **must run `npm run build`** — shipping a zip without it means a 404'd script and an empty table.

`phpcs.xml` is scoped to `.php` files only (`<arg name="extensions" value="php"/>`) — JS/CSS have their own dedicated linters and must not be re-added to PHPCS's scope; WordPress's PHP-oriented sniffs actively conflict with the JS style enforced by ESLint (e.g. `function (` vs `function(`).

## CI checks (GitHub Actions)

`CS`, `Lint: JS`, `Build: JS` (webpack build must compile; the bundle itself is gitignored so CI is the only guard), `Lint: PHP` (7.4–8.2), `Security check`, `Test` (multiple PHP × WP version combos). `WordPress version checker` and `backport-to-develop` workflows exist but are not load-bearing for feature PRs (the backport workflow is currently disabled — `.disabled` suffix — because it targets a `develop` branch that doesn't exist in this repo).

## Workflow

- **One PR per logical change** — don't bundle unrelated fixes together.
- Branch from `main`, PR back into `main`.
- **Commits should be small and atomic** — each commit covers one minimal, self-contained chunk of related changes. Prefer several small commits within a PR over one large one; it keeps review and `git blame`/history useful even when the PR itself bundles a few related fixes.
- **Use Conventional Commits style wherever possible** (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `test:`, etc.) for commit subject lines.
- **Always wait for review comments — including AI reviewers (CodeRabbit, Gemini Code Assist)** — before considering a PR done or merging. Don't skim past a "pending"/"in progress" AI review status. When findings land, surface them for discussion before fixing anything.
- CodeRabbit and Gemini Code Assist both auto-review PRs on this repo; expect both, not just one.
- When replying to review threads, reference the specific commit hash that addressed the finding.

## Known doc staleness

`docs/PREMIUM-FEATURES.md` and `docs/MARKET-RESEARCH.md` both still list "ACF integration" under the free-tier feature set. This is stale — the ACF dependency was fully removed during the plugin restructure in favor of native `register_post_meta()` + a custom meta box (this was itself a "Critical Blocker" item in `docs/PRODUCTION-READINESS.md`). Don't treat those two docs' free/premium split as 100% current without cross-checking against the actual code.
