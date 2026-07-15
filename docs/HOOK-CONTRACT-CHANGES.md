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
