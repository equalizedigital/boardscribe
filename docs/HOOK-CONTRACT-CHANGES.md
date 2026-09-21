# Hook Contract Changes

Tracks changes to existing hook/filter *call signatures* (not just new hooks) that the Pro plugin must account for. The free and Pro plugins are developed in separate repos, so there's no compiler to catch a Pro callback written against an old contract — check this doc against Pro's hook usage before every Pro release.

Newly *added* hooks (no compatibility concern, just new capability) are tracked in `CLAUDE.md`'s extension-point table instead of here.

---

## PR #9 — `edbs_meeting_row_data`'s `$request` argument can now be `null`

**Before:** `edbs_meeting_row_data` was only ever fired from inside `MeetingMinutesEndpoint::get_meeting_minutes()`, so the third argument (`$request`) was always a real `\WP_REST_Request` instance.

**After:** The per-row building logic was extracted into a new public method, `MeetingMinutesEndpoint::build_meeting_row( int $post_id, array $format_args, ?\WP_REST_Request $request = null ): array`, specifically so Pro features that need the same escaped/formatted row data *outside* a REST request (CSV/PDF export, an iCal feed, a "most recent meeting" widget) can call it directly. When called this way, `$request` will be `null`.

**Contract impact:** Any callback hooked to `edbs_meeting_row_data` that type-hints its third parameter as `\WP_REST_Request` (rather than `?\WP_REST_Request` or leaving it untyped) will throw a fatal `TypeError` the first time something calls `build_meeting_row()` outside the REST endpoint.

```php
// Before this change, safe:
add_filter( 'edbs_meeting_row_data', function ( array $row, int $post_id, \WP_REST_Request $request ) { ... }, 10, 3 );

// Now required for compatibility:
add_filter( 'edbs_meeting_row_data', function ( array $row, int $post_id, ?\WP_REST_Request $request ) { ... }, 10, 3 );
// or simply omit/relax the type hint and null-check before use.
```

**Action for Pro before release:** grep Pro's codebase for `edbs_meeting_row_data` and confirm every callback either doesn't type-hint the third parameter, type-hints it nullable, or defensively checks `$request` before calling any `\WP_REST_Request` method on it.

*(Flagged during review of PR #9 by Gemini Code Assist — as of that PR, `build_meeting_row()` passes `$request` through as `null` when the caller doesn't supply one, rather than substituting a dummy `\WP_REST_Request` instance. If that gets changed to always pass a real instance instead, update this doc.)*

---

## PR #19 — five hooks removed, replaced by `edbs_shortcode_field_registry`

**Before:** shortcode-attribute defaults, REST args, and the settings-page builder UI were four independently hand-maintained lists, glued together by five separate hooks: `edbs_shortcode_atts` (filter — new shortcode attribute defaults), `edbs_shortcode_instance_config` (filter — new per-instance JS config keys), `edbs_shortcode_builder_fields` (action — raw-HTML escape hatch for wholly new builder rows), `edbs_shortcode_builder_label_fields` (filter — entries in the builder's "Column Labels" row), `edbs_shortcode_builder_hide_fields` (filter — entries in the builder's "Hide Columns" row).

**After:** all five are removed — **no deprecation shim, this is a breaking change** — replaced by one filter, `edbs_shortcode_field_registry` (see `Shortcode/FieldRegistry.php` and the row in `CLAUDE.md`'s extension-point table). A callback on this filter returns an array of field *descriptors* instead of a bare label/default; free itself derives the shortcode default, instance-config value, REST arg (when opted in), and builder-UI row from each descriptor.

**Contract impact:** any callback still hooked to the five removed hooks stops running (`apply_filters()`/`do_action()` on an unregistered hook name is a silent no-op in WordPress — it does not error, so a callback written against the old contract will just quietly stop taking effect, not fail loudly). Every consumer needs to move to `edbs_shortcode_field_registry` before upgrading past this change.

**Not a 1:1 replacement for `edbs_shortcode_builder_fields`'s raw-HTML use case:** the new registry only covers typed field descriptors (the six `FieldRegistry` types). `edbs_shortcode_builder_fields` was a bare `do_action()` some callers used to inject arbitrary custom markup/sections into the builder form (not just a labeled input/checkbox/select). There is no equivalent escape hatch in the new filter — a caller doing that needs a different approach (e.g. its own `admin_footer`/inline-script hook on the settings page) or to accept the field is intentionally unsupported by the registry.

```php
// Before:
add_filter( 'edbs_shortcode_atts', function ( array $defaults ) {
	$defaults['location_label'] = '';
	$defaults['hide_location']  = 'false';
	return $defaults;
} );
add_filter( 'edbs_shortcode_builder_label_fields', function ( array $fields ) {
	$fields['location_label'] = __( 'Location', 'boardscribe-pro' );
	return $fields;
} );
add_filter( 'edbs_shortcode_builder_hide_fields', function ( array $fields ) {
	$fields['hide_location'] = __( 'Location', 'boardscribe-pro' );
	return $fields;
} );
add_filter( 'edbs_shortcode_instance_config', function ( array $config, array $atts ) {
	$config['locationLabel'] = sanitize_text_field( $atts['location_label'] ?? '' );
	$config['hideLocation']  = filter_var( $atts['hide_location'] ?? 'false', FILTER_VALIDATE_BOOLEAN );
	return $config;
}, 10, 2 );

// Now, one callback on the new filter replaces all four of the above:
add_filter( 'edbs_shortcode_field_registry', function ( array $fields ) {
	return array_merge( $fields, [
		[
			'key'     => 'location_label',
			'type'    => 'text',
			'group'   => 'column_labels',
			'label'   => __( 'Location', 'boardscribe-pro' ),
			'default' => '',
		],
		[
			'key'     => 'hide_location',
			'type'    => 'checkbox',
			'group'   => 'hide_columns',
			'label'   => __( 'Location', 'boardscribe-pro' ),
			'default' => false,
		],
	] );
} );
```

**Action for Pro before release:** grep Pro's codebase for all five removed hook names. Migrate every callback that added a typed field (label/default/checkbox/etc.) to a descriptor on `edbs_shortcode_field_registry`; for any `edbs_shortcode_builder_fields` callback injecting raw custom markup, see the raw-HTML caveat above — there is no direct replacement, so that logic needs a different approach (or to be dropped) rather than a registry migration. `posts_per_page` "all" support (previously bolted on via `edbs_rest_route_args` in `ProMetaFields::allow_all_posts_per_page()`) is now a core free-plugin field type (`number_with_all`) — that override method should be deleted entirely, not migrated.

---

## BoardScribe rebrand — every `edmm_`/`EDMM_` hook, constant, meta key, and option renamed to `edbs_`/`EDBS`

**Before:** the plugin was "Meeting Minutes" / "Meeting Minutes Pro", with code prefix `edmm_`/`EDMM_`, namespace `EqualizeDigital\MeetingMinutes[Pro]`, text domains `meeting-minutes`/`edmm-pro`, and JS globals `window.edmm*`.

**After:** rebranded to **BoardScribe** / **BoardScribe Pro**. Every hook/filter, constant, option, meta key, nonce, script/style handle, and JS global that used the `edmm_`/`EDMM_`/`edmm-`/`window.edmm*` prefix now uses `edbs_`/`EDBS`/`edbs-`/`window.edbs*` instead (see `CLAUDE.md`'s extension-point table for the current name of every hook — that table was updated in place, not duplicated here). Namespace is now `EqualizeDigital\BoardScribe` (free) / `EqualizeDigital\BoardScribePro` (Pro). Text domains are now `boardscribe` / `boardscribe-pro`. The free plugin's "is active" signal Pro checks changed from `defined('EDMM_VERSION')` to `defined('EDBS_VERSION')`.

**Kept unchanged:** the GitHub repos and `composer.json`/`package.json` "name" fields were deliberately left as their historical `equalize-digital-meeting-minutes`/`meeting-minutes-pro` values, matching a prior precedent of the WP-facing slug diverging from the git repo name — but the local checkout directories were subsequently renamed to `boardscribe`/`boardscribe-pro` to match the new plugin slugs.

**Contract impact:** every hook name in this document and in `CLAUDE.md`'s table (from `edbs_loaded` down) is the *current* name — any Pro code (or third-party integration) still written against the old `edmm_*`/`EDMM_*` names will silently stop firing/receiving these hooks, since `apply_filters()`/`do_action()` on an unregistered name is a no-op.

**Action for Pro before release:** grep for any remaining `edmm`/`EDMM`/`meeting-minutes` (excluding intentionally-kept content-type prose) in Pro's codebase and update to the `edbs_`/`EDBS_PRO_` equivalents; confirm the free-plugin-active check reads `defined('EDBS_VERSION')`.

---

## Follow-up — CPT slug, shortcode tag, and REST route renamed to `edbs_boardscribe` / `/edbs/v1/boardscribe/`

**Before:** the initial BoardScribe rebrand (above) deliberately kept the CPT slug/shortcode tag as `edbs_meeting_minutes` and the REST route as `/edbs/v1/meeting-minutes/`, treating "meeting minutes" as descriptive content-type vocabulary rather than product branding.

**After:** on request, these were renamed too: CPT slug and shortcode tag are now `edbs_boardscribe`, and the REST route is now `/edbs/v1/boardscribe/` (namespace `edbs/v1` unchanged, only the route segment changed). Pro's Gutenberg block was renamed to match: `equalize-digital/meeting-minutes` → `equalize-digital/boardscribe`.

**Kept unchanged:** meta keys (`edbs_meeting_date`, `edbs_meeting_agenda_url`, `edbs_meeting_minutes_url`, `edbs_meeting_not_held`), the `edbs_meeting_row_data`/`edbs_meeting_formatted_date`/`edbs_meeting_agenda_link`/`edbs_meeting_minutes_link` hooks, and CSS/JS presentational classes (`edbs-meeting-minutes-table`, `edbs-meeting-minutes-wrap`) — none of these were in scope for this follow-up and still use the `meeting_minutes`/`meeting-minutes` wording.

**Contract impact:** any code (Pro included) hardcoding the CPT slug `edbs_meeting_minutes`, the shortcode tag `[edbs_meeting_minutes]`, or building URLs against `/edbs/v1/meeting-minutes/` must be updated. WordPress's dynamic `save_post_{$post_type}` hook is now `save_post_edbs_boardscribe`.

**Action for Pro before release:** grep for `edbs_meeting_minutes` as a **CPT-slug/shortcode-tag token** (not the meta-key/hook variants above, which are unaffected) and for `/meeting-minutes/` as a REST route segment; update `MeetingCategory`, `ProMetaFields`, `CsvImporter`, and `MeetingMinutesBlock` query/registration code, and `block.json`'s block name.

---

## Block moved from Pro into the free plugin (paired `feat/move-block-to-free` branches)

**Before:** the `equalize-digital/boardscribe` block was registered by Pro (`Block/MeetingMinutesBlock.php` there), with the editor preview hardcoding Pro's five columns and the year-timeline template.

**After:** the free plugin registers the block (`Block/BoardScribeBlock.php`, from `block.json`; block **name unchanged** — it's stored in existing content). Pro extends it purely through new free extension points: `edbs_block_preview_columns`, `edbs_block_editor_preview`, `edbs_block_preview_max_rows`, the public `BoardScribeBlock::render_preview_table()`, and the `edbs.block.templateChangeAttributes` JS filter (see CLAUDE.md's table). Free's registration runs at `init` 20 behind a `WP_Block_Type_Registry::is_registered()` guard, so an **old Pro + new free** combination keeps working (old Pro's registration at `init` 10 wins, no notice).

**Also in this change:**
- `MeetingMinutesEndpoint::parse_date()` is now `public static` — shared surface Pro calls for year grouping (its own `MeetingMinutesBlock::parse_meeting_date()` copy is deleted).
- The core `template` field descriptor now ships only the `''` (table) choice. A plugin owning another display template must append its choice via `edbs_shortcode_field_registry` or it disappears from the builder/block pickers.

**Contract impact / version pairing:** **new Pro requires the paired new free release.** On an older free plugin, new Pro's block is simply gone (nothing registers it) and `ProMetaFields::get_meeting_year()` fatals calling the then-private `parse_date()`. The reverse skew (old Pro + new free) is handled by the registration guard above.

**Action for Pro before release:** confirm Pro no longer ships `block.json`/`build/` (update packaging manifests accordingly), that `BlockExtensions` is booted, and that the paired free release is published first.

---

## Rebrand completion — slug-derived identifiers and doc-link meta keys/hooks renamed (pre-release)

**Before:** the earlier rebrand entries above deliberately kept the frontend script/style handle `edbs-meeting-minutes`, the CSS classes `edbs-meeting-minutes-table`/`edbs-meeting-minutes-wrap`, the meta keys `edbs_meeting_agenda_url`/`edbs_meeting_minutes_url`, and the hooks `edbs_meeting_agenda_link`/`edbs_meeting_minutes_link`.

**After** (allowed because neither plugin has shipped — no stored data or third-party CSS to preserve):

| Old | New |
|---|---|
| `edbs-meeting-minutes` (script + style handle) | `edbs-boardscribe` |
| `.edbs-meeting-minutes-table` | `.edbs-boardscribe-table` |
| `.edbs-meeting-minutes-wrap` | `.edbs-boardscribe-wrap` |
| `edbs_meeting_agenda_url` (meta key + POST field) | `edbs_agenda_url` |
| `edbs_meeting_minutes_url` (meta key + POST field) | `edbs_minutes_url` |
| `edbs_meeting_agenda_link` (filter) | `edbs_agenda_link` |
| `edbs_meeting_minutes_link` (filter) | `edbs_minutes_link` |

**Kept:** `edbs_meeting_date` and `edbs_meeting_not_held` (properties of the meeting — no brand echo), and the `edbs_meeting_row_data`/`edbs_meeting_formatted_date`/`edbs_save_meeting_meta` hooks likewise.

**Contract impact:** Pro must enqueue against the `edbs-boardscribe` handle and write `edbs_agenda_url`/`edbs_minutes_url` in its CSV importer (done in the paired `fix/boardscribe-wording` branch). Any site content created against the old meta keys before this change loses its agenda/minutes links (accepted: unreleased). Accessibility Checker Pro's link-fix integration receives the new filter names via `edac_fix_file_size_and_type_additional_filters` automatically.

---

## Rebrand completion — PHP classes renamed to BoardScribe* (pre-release)

**Before / after** (namespaces unchanged; each class is named after the `edbs_boardscribe`/`boardscribe` slug it registers):

| Old | New |
|---|---|
| `PostType\MeetingMinutes` (`PostType/MeetingMinutes.php`) | `PostType\BoardScribeCPT` |
| `REST\MeetingMinutesEndpoint` (`REST/MeetingMinutesEndpoint.php`) | `REST\BoardScribeEndpoint` |
| `Shortcode\MeetingMinutesShortcode` (`Shortcode/MeetingMinutesShortcode.php`) | `Shortcode\BoardScribeShortcode` |
| `MeetingMinutesEndpoint::get_meeting_minutes()` (REST callback) | `BoardScribeEndpoint::get_meetings()` |

**Contract impact:** `BoardScribeEndpoint::build_meeting_row()` and `::parse_date()` are cross-repo public surface — Pro references the new class name (updated in the paired `fix/boardscribe-wording` branch). Older HOOK-CONTRACT entries above intentionally keep the historical class names they were written against.

---

## Shortcode Builder converted to a React app (PRO-1228) — `edbs_enqueue_assets` can now fire in wp-admin

**Before:** `edbs_enqueue_assets` only ever fired on the front end, from `BoardScribeShortcode::enqueue_assets()` during `wp_enqueue_scripts` or a front-end shortcode render.

**After:** the settings page's Shortcode Builder tab (`SettingsPage::enqueue_assets()`) also calls the shortcode's `enqueue_assets()` (now public) so its live preview runs the real frontend pipeline — meaning the action now also fires during `admin_enqueue_scripts` on that one admin screen (the `edbs-settings` page with `?tab=builder`). This is intentional: it's what makes a Pro/third-party plugin's extra columns and display templates render inside the builder preview with no extra wiring.

**Contract impact:** no signature change. But a callback on `edbs_enqueue_assets` that assumes a front-end context (e.g. calls front-end-only conditionals, enqueues assets that break admin styling, or dequeues admin scripts) now runs in wp-admin too. Callbacks should be context-safe — enqueuing the plugin's own frontend JS/CSS (the intended use) is fine as-is; anything else should guard with `is_admin()` as needed.

**Action for Pro before release:** grep Pro for `edbs_enqueue_assets` and confirm each callback only enqueues its frontend assets (safe) or guards admin-sensitive work with `is_admin()`.

---

## Settings page redesigned: Shortcode Builder folded into a tab (#27) — standalone builder submenu removed, `enqueue_builder_script()` renamed

**Before:** two submenus under the Board Meetings menu — `Settings` (`page=edbs-settings`) and `Shortcode Builder` (`page=edbs-shortcode-builder`, rendered by `SettingsPage::render_builder_page()`, assets enqueued by `SettingsPage::enqueue_builder_script()`).

**After:** one tabbed `Settings` page (`page=edbs-settings`) with `General` / `Shortcode Builder` (`&tab=builder`) / `Support` tabs. The standalone `edbs-shortcode-builder` submenu page, `add_builder_submenu_page()`, `render_builder_page()`, and the `partials/shortcode-builder-page.php` partial are gone. `enqueue_builder_script()` is renamed `enqueue_assets()` and now keys off the settings-page hook + active tab. The builder's mount node (`#edbs-shortcode-builder-root`) and its `edbs-shortcode-builder` script/style handles are unchanged.

**Contract impact:** free-plugin admin internals only — Pro has no references to the removed slug/methods (verified). Any external link to `page=edbs-shortcode-builder` should point at `page=edbs-settings&tab=builder` instead.

---

## Meeting Details meta box converted to a React app — three hooks removed, replaced by `edbs_meeting_meta_fields`

**Before:** the meta box's four fields were a hand-written `<table>` in `partials/meta-box.php`, with three PHP action hooks giving Pro row-position control: `edbs_after_agenda_url_field` and `edbs_after_minutes_url_field` (fire immediately after those two `<tr>`s, for a field tightly coupled to the URL above it — e.g. a Document picker) and `edbs_meta_fields` (fires once, after every default field, for anything else). `MetaBox::save_meta()` hardcoded sanitization for the four known `$_POST` keys.

**After:** `partials/meta-box.php` is deleted. The table is rendered client-side by a React app (`src/js/metabox/`) from `MetaBoxFieldRegistry::js_schema()` — **no deprecation shim, this is a breaking change.** A field is now a data descriptor (`key`, `type`, `label`, `description`, `required`, `placeholder`, `media_picker`, `media_title`, `insert_after`, `sanitize_callback`) added via the new `edbs_meeting_meta_fields` filter instead of a `do_action()` callback that echoes HTML. Row position is the `insert_after` key (another field's `key`; omitted = appended at the end) rather than which of the three old hooks a callback happened to use. `MetaBox::save_meta()` now loops `MetaBoxFieldRegistry::all()` and sanitizes generically by `type` (or the field's own `sanitize_callback`) instead of reading four hardcoded `$_POST` keys.

**Contract impact:** any callback still hooked to `edbs_after_agenda_url_field`, `edbs_after_minutes_url_field`, or `edbs_meta_fields` stops running (a silent no-op, per WordPress's usual behavior for an unregistered hook — it won't error). A Pro field with a bespoke UI (e.g. a Document picker backed by a CPT relationship, not a plain scalar `<input>`) needs a matching entry in `window.edbsMetaBoxControls` (keyed by that field's `type`, a React component `( { field, value, onChange, id } ) => JSX.Element` — see `src/js/metabox/index.js`) and, if its saved value isn't a plain string, its own save-time handling via the `edbs_save_meeting_meta` action (unchanged) rather than relying on the registry's generic type-based sanitizers.

**A field whose `$_POST` value isn't a plain scalar must set `saved_externally => true`.** This includes every `resource_list` field (Supporting Documents-shaped repeaters) and every `attached` field (regardless of its own `type`) — both are documented as always saved by the owning plugin itself, not `MetaBox::save_meta()`'s generic loop. Handling the value on `edbs_save_meeting_meta` is not by itself enough: without `saved_externally`, the generic loop still runs first and hits the value before your own handler does. `MetaBox::save_field()` now backstops a field that forgets this flag with a `_doing_it_wrong()` notice rather than silently mangling the repeater array through `sanitize_text_field()`, but the flag is still required for the value to actually persist as intended.

```php
// Before:
add_action( 'edbs_after_agenda_url_field', function ( \WP_Post $post ) {
	$doc_id = get_post_meta( $post->ID, '_edbs_pro_agenda_document_id', true );
	// ... echo a <tr> with a document-picker button ...
} );
add_action( 'edbs_save_meeting_meta', function ( int $post_id ) {
	if ( isset( $_POST['edbs_pro_agenda_document_id'] ) ) {
		update_post_meta( $post_id, '_edbs_pro_agenda_document_id', absint( $_POST['edbs_pro_agenda_document_id'] ) );
	}
} );

// Now:
add_filter( 'edbs_meeting_meta_fields', function ( array $fields ) {
	$fields[] = [
		'key'          => 'edbs_pro_agenda_document_id',
		'type'         => 'document_picker', // matching window.edbsMetaBoxControls entry required
		'label'        => __( 'Agenda Document', 'boardscribe-pro' ),
		'insert_after' => 'edbs_agenda_url',
	];
	return $fields;
} );
// JS (enqueued alongside/after edbs-meta-box):
// window.edbsMetaBoxControls.document_picker = ( { field, value, onChange, id } ) => ( ... );
```

**Action for Pro before release:** grep Pro for `edbs_after_agenda_url_field`, `edbs_after_minutes_url_field`, and `edbs_meta_fields`; port each to an `edbs_meeting_meta_fields` descriptor plus (for non-trivial UI) a `window.edbsMetaBoxControls` entry.

**New extension points for Pro:** the tab list is now filterable via `edbs_settings_tabs` and non-core tab content renders on `edbs_settings_tab_content_{$tab}`. This is what lets Pro fold its CSV Import page (`page=edbs-import`) into the settings page as an `import` tab instead of a standalone submenu, matching how the builder moved. **Paired Pro change (separate branch):** register the `import` tab via `edbs_settings_tabs`, move `CsvImporter::render_page()` onto `edbs_settings_tab_content_import`, drop `add_menu_page()`/the `edbs-import` submenu, enqueue import assets off the `edbs_meeting_page_edbs-settings` hook + `tab=import`, and update `get_page_url()` to `page=edbs-settings&tab=import`.

---

## PRO-1331 — three block editor preview hooks removed, `render_editor_preview()`/`render_preview_table()` deleted

**Before:** the block's editor preview rendered server-side via `ServerSideRender` → `BoardScribeBlock::render_block()`'s `REST_REQUEST` branch → `render_editor_preview()`, which ran its own `WP_Query` and built lookalike markup, filterable via `edbs_block_preview_columns` (column list), `edbs_block_editor_preview` (template-specific short-circuit), and `edbs_block_preview_max_rows` (row cap). The public `BoardScribeBlock::render_preview_table()` rendered one such table. Pro's `BlockExtensions.php` used all three to reimplement its year-timeline/list templates a second time, in PHP, for the editor only.

**After:** the editor preview is the real thing. `edit()` renders the same `.edbs-boardscribe-wrap` markup `BoardScribeShortcode::render()` emits and drives it through `window.edbsInitInstance()` — the same client-side pipeline the front end and the Shortcode Builder's live preview already use (see `src/js/block/index.js`'s `buildInstanceConfig()`). `render_block()` is now a plain `do_shortcode()` call with no editor-context branch. All three filters and `render_preview_table()` are gone — **no deprecation shim, this is a breaking change** (same treatment as PR #19 above: `apply_filters()`/`do_action()` on a removed hook name is a silent no-op, not an error, so a Pro callback still hooked to one of these just quietly stops taking effect). `BoardScribeBlock::register()` now also hooks `enqueue_block_editor_assets` to call `BoardScribeShortcode::enqueue_assets()`, so the real frontend bundle (and, via its `edbs_enqueue_assets` action, anything Pro/third-party hangs off it) loads in the editor — something nothing previously did.

**Also in this change:** `src/js/instance.js`'s `initInstance()` now resolves its table/pagination/info elements off `container.ownerDocument` instead of the bare global `document`, so it works correctly when `container` is mounted inside the block editor's iframed canvas (WP ~6.3+) — a prerequisite for the live preview working at all, not itself a contract change (every existing caller runs same-document and is unaffected). There is no more row cap — the preview paginates like the real front end. A REST fetch failure now surfaces as a visible `Notice` in the block instead of `ServerSideRender`'s own generic failure state.

**Contract impact:** any Pro/third-party callback on `edbs_block_preview_columns`, `edbs_block_editor_preview`, or `edbs_block_preview_max_rows` stops running silently. Any code calling `BoardScribeBlock::render_preview_table()` directly hits a fatal `Error: Call to undefined method`.

**Action for Pro before release:** delete `BlockExtensions::add_preview_columns()`, `raise_preview_max_rows()`, `render_year_timeline_preview()`, `render_list_preview()`, `group_rows_by_year()`, and their hook registrations — the front-end templates (`year-timeline`, `list`) they duplicated already render correctly through the live editor preview with no Pro-side change needed. Confirm `pro-list-template.css` is enqueued in the editor context too (it should be, automatically, once `enqueue_block_editor_assets` fires free's `enqueue_assets()` → `edbs_enqueue_assets` → Pro's own enqueue callback) rather than assuming it.
