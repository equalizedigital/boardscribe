# Equalize Digital Meeting Minutes

WordPress plugin: manages meeting minutes as a custom post type (`edmm_meeting_minutes`), displayed via a `[edmm_meeting_minutes]` shortcode backed by a public REST endpoint. Native WordPress storage only — no ACF dependency (removed during the restructure; some docs still mention it, see Known Doc Staleness below).

## This is the free plugin — a separate Pro plugin is coming

This repo is the **free, WordPress.org-distributed** half of a freemium product. A **Pro plugin** (not in this repo, doesn't exist locally yet) will extend it with premium features. This split drives real architecture decisions here:

- Every feature added to this free plugin should ask "how would Pro hook into or override this?" before being considered done.
- Prefer `apply_filters()`/`do_action()` extension points over hardcoding, especially around: CPT registration args, REST route args/query building, row/output formatting, and anywhere shortcode/table markup is assembled.
- If you extract or refactor logic that Pro would need to reuse (e.g. the per-row escaping/formatting logic), expose it as a callable public method rather than leaving it inline — duplicated logic in Pro risks re-diverging from security fixes made here.
- See `docs/PREMIUM-FEATURES.md` for the full premium feature list and the free/premium split, and `docs/MARKET-RESEARCH.md` for the business context behind that split.
- Full audit of what's needed for Pro to extend "basically everything" is an ongoing effort — check recent PRs/commit history for the state of this work before assuming a gap exists.

## Directory structure

```
equalize-digital-meeting-minutes.php   Plugin bootstrap (constants, requires, plugins_loaded hook)
src/
  Plugin.php                           Singleton; boots all components, fires edmm_loaded (Pro's entry point)
  PostType/MeetingMinutes.php          CPT registration
  Admin/MetaBox.php                    Native meta box UI + save handling
  Admin/SettingsPage.php               Settings page + shortcode builder UI
  REST/MeetingMinutesEndpoint.php      /edmm/v1/meeting-minutes/ REST route + query/row building
  Shortcode/MeetingMinutesShortcode.php Shortcode registration, asset enqueuing, instance config
assets/src/js/                         Frontend source modules (ES modules, bundled by webpack)
  index.js                             Entry: exposes window globals, registers table template, bootstraps
  registries.js                        window.edmmExtraColumns/edmmTemplates setup + the template contract docs
  config.js                            Reads window.edmmConfig (i18n, REST base URL)
  escape.js                            escapeAttr() (exposed as window.edmmEscapeAttr)
  templates/table.js                   Built-in "table" display template
  defaults/                            Core fallbacks a template inherits unless it overrides them
    render-info.js / render-pagination.js / request.js / focus.js
  instance.js                          Per-shortcode-instance controller (template resolution, URL state, fetch)
assets/build/                          Bundled output (GITIGNORED — run `npm run build`; release packaging must build)
assets/css/meeting-minutes.css         Frontend styles
uninstall.php                         Opt-in data cleanup on plugin deletion
docs/                                 Planning docs (market research, premium features, readiness checklist) — not user-facing
tests/                                PHPUnit setup
```

## Extension points (hooks/filters) currently in the free plugin

Keep this list current when adding or removing hooks — it's the primary reference for what Pro can already do.

| Hook | Type | File | Purpose |
|---|---|---|---|
| `edmm_loaded` | action | `Plugin.php` | Fires after all free components register. **Pro plugin's sole entry point.** |
| `edmm_before_register_cpt` / `edmm_after_register_cpt` | action | `PostType/MeetingMinutes.php` | Register taxonomies that bind to the CPT (e.g. meeting series). |
| `edmm_rest_query_args` | filter | `REST/MeetingMinutesEndpoint.php` | Modify the `WP_Query` args before querying meeting minutes. |
| `edmm_meeting_agenda_link` / `edmm_meeting_minutes_link` | filter | `REST/MeetingMinutesEndpoint.php` | Override the built `<a>` markup for agenda/minutes links (used by Accessibility Checker Pro integration). |
| `edmm_meeting_row_data` | filter | `REST/MeetingMinutesEndpoint.php` | Add/override fields on a single meeting's REST row data. |
| `edmm_rest_response` | filter | `REST/MeetingMinutesEndpoint.php` | Modify the full REST response before it's returned. |
| `edmm_enqueue_assets` | action | `Shortcode/MeetingMinutesShortcode.php` | Fires after core CSS/JS enqueue — Pro enqueues its own assets here. |
| `edmm_shortcode_atts` | filter | `Shortcode/MeetingMinutesShortcode.php` | Add new recognized shortcode attributes. |
| `edmm_shortcode_instance_config` | filter | `Shortcode/MeetingMinutesShortcode.php` | Add/override keys in the per-instance JS config (JSON-encoded into `data-config`). |
| `edmm_use_native_meta_boxes` | filter | `Admin/MetaBox.php` | Return `false` to suppress the native meta box UI entirely (for a Pro replacement). |
| `edmm_before_meta_box_fields` / `edmm_meta_fields` | action | `Admin/MetaBox.php` | Render additional meta box fields before/after the defaults. |
| `edmm_save_meeting_meta` | action | `Admin/MetaBox.php` | Fires after the default meta fields are saved — save Pro's own meta here. |
| `edmm_shortcode_builder_fields` | action | `Admin/SettingsPage.php` | Add fields to the shortcode builder UI. |
| `edmm_settings_fields` | action | `Admin/SettingsPage.php` | Add sections to the settings page outside the Settings API (e.g. license management). |
| `window.edmmExtraColumns` (JS, not a WP hook) | registry | `assets/src/js/registries.js` | Push `{ key, label, renderCell }` objects before `DOMContentLoaded` to add table columns. `renderCell()`/`label` output is inserted as raw HTML — must escape untrusted data itself. |
| `window.edmmTemplates` (JS, not a WP hook) | registry | `assets/src/js/registries.js` | Display-template registry, keyed by template name; selected per instance via the shortcode's `template=""` attribute. A template provides `render( data, instanceCfg, container )` (required) plus optional `renderPagination`/`renderInfo`/`focus` overrides and optional request-side overrides — `buildRequestUrl( instanceCfg, page )` to point the default fetch at a different/extended URL, or `request( instanceCfg, page )` returning a Promise to replace the request entirely (pairs with the server-side `edmm_rest_route_args`/`edmm_rest_query_args` filters). Anything not overridden keeps the core implementation (default REST query, pagination buttons, URL state, aria-live info). The built-in table is registered here as `table`; unknown names fall back to it. Output is raw HTML — templates must escape untrusted data (`window.edmmEscapeAttr` is exposed for this). |
| `template` shortcode attribute | config | `Shortcode/MeetingMinutesShortcode.php` | Names the `window.edmmTemplates` entry an instance renders with (sanitized with `sanitize_key()`, passed through `data-config`). Free ships only `table`; Pro registers accordion/card-grid/timeline templates and they become selectable with no further PHP changes. |
| `edmm_cpt_args` | filter | `PostType/MeetingMinutes.php` | Modify CPT registration args (e.g. enable `public`/`rewrite`/`has_archive`, or a custom `capability_type`) before `register_post_type()`. |
| `edmm_rest_route_args` | filter | `REST/MeetingMinutesEndpoint.php` | Add new params to the `/edmm/v1/meeting-minutes/` route's registered args schema (e.g. full-text search, a date range) instead of registering a duplicate route. |
| `MeetingMinutesEndpoint::build_meeting_row()` | public method | `REST/MeetingMinutesEndpoint.php` | Builds one meeting's escaped/formatted row data (title, date, agenda/minutes links) outside the REST loop — reuse this rather than re-implementing the same escaping for CSV/PDF export, an iCal feed, or a widget. Fires `edmm_meeting_row_data` internally; see `docs/HOOK-CONTRACT-CHANGES.md` for a signature caveat. |
| `edmm_before_table` / `edmm_after_table` | action | `Shortcode/MeetingMinutesShortcode.php` | Fires inside the shortcode wrapper, before/after the table container — for lightweight additions (search box, add-to-calendar button, comment form) that don't need a full display-template override. |

**Resolved gap (was a known open question):** the table's *rendering strategy* is now overridable via the `window.edmmTemplates` registry + `template=""` shortcode attribute (see table above) — Pro's accordion/card-grid/timeline templates plug in there.

**Before changing any existing hook's call signature** (not just adding new ones), check and update `docs/HOOK-CONTRACT-CHANGES.md` — the free and Pro plugins are separate repos with no compiler to catch a Pro callback written against an old contract, so this doc is the only cross-repo record of breaking changes. Check it before every Pro release.

## Commands

```bash
composer install && npm install     # setup
npm run build                       # webpack (wp-scripts) — REQUIRED after clone; assets/build/ is gitignored
npm start                           # webpack watch mode during JS development

composer lint                       # php-parallel-lint (syntax check)
composer check-cs                   # phpcs (WordPress Coding Standards) — CI's "CS" check
composer fix-cs                     # phpcbf (auto-fix what's fixable)
npm run lint:js                     # eslint over assets/src + webpack.config.js — CI's "Lint: JS" check

composer test                       # phpunit (requires ./scripts/setup-phpunit.sh first, or Docker via npm run test:php)
```

The frontend JS is bundled from `assets/src/js/` to `assets/build/` via `wp-scripts build` with a small `webpack.config.js` overriding entry/output paths (the wp-scripts defaults clash with `src/` holding PHP) and dropping `DependencyExtractionWebpackPlugin` — no `*.asset.php` is emitted. `@wordpress/*` imports resolve to `wp.*` globals via a hand-maintained `externals` map in `webpack.config.js`; each entry there needs its matching `wp-*` handle in the `wp_enqueue_script()` dependency list in `MeetingMinutesShortcode.php` (the enqueue uses `EDMM_VERSION` for cache-busting). Because `assets/build/` is gitignored, any release/deploy packaging **must run `npm run build`** — shipping a zip without it means a 404'd script and an empty table.

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
