# Hook Contract Changes

Tracks changes to existing hook/filter *call signatures* (not just new hooks) that the Pro plugin must account for. The free and Pro plugins are developed in separate repos, so there's no compiler to catch a Pro callback written against an old contract — check this doc against Pro's hook usage before every Pro release.

Newly *added* hooks (no compatibility concern, just new capability) are tracked in `CLAUDE.md`'s extension-point table instead of here.

---

## PR #9 — `edmm_meeting_row_data`'s `$request` argument can now be `null`

**Before:** `edmm_meeting_row_data` was only ever fired from inside `MeetingMinutesEndpoint::get_meeting_minutes()`, so the third argument (`$request`) was always a real `\WP_REST_Request` instance.

**After:** The per-row building logic was extracted into a new public method, `MeetingMinutesEndpoint::build_meeting_row( int $post_id, array $format_args, ?\WP_REST_Request $request = null ): array`, specifically so Pro features that need the same escaped/formatted row data *outside* a REST request (CSV/PDF export, an iCal feed, a "most recent meeting" widget) can call it directly. When called this way, `$request` will be `null`.

**Contract impact:** Any callback hooked to `edmm_meeting_row_data` that type-hints its third parameter as `\WP_REST_Request` (rather than `?\WP_REST_Request` or leaving it untyped) will throw a fatal `TypeError` the first time something calls `build_meeting_row()` outside the REST endpoint.

```php
// Before this change, safe:
add_filter( 'edmm_meeting_row_data', function ( array $row, int $post_id, \WP_REST_Request $request ) { ... }, 10, 3 );

// Now required for compatibility:
add_filter( 'edmm_meeting_row_data', function ( array $row, int $post_id, ?\WP_REST_Request $request ) { ... }, 10, 3 );
// or simply omit/relax the type hint and null-check before use.
```

**Action for Pro before release:** grep Pro's codebase for `edmm_meeting_row_data` and confirm every callback either doesn't type-hint the third parameter, type-hints it nullable, or defensively checks `$request` before calling any `\WP_REST_Request` method on it.

*(Flagged during review of PR #9 by Gemini Code Assist — as of that PR, `build_meeting_row()` passes `$request` through as `null` when the caller doesn't supply one, rather than substituting a dummy `\WP_REST_Request` instance. If that gets changed to always pass a real instance instead, update this doc.)*

---

## PR #19 — five hooks removed, replaced by `edmm_shortcode_field_registry`

**Before:** shortcode-attribute defaults, REST args, and the settings-page builder UI were four independently hand-maintained lists, glued together by five separate hooks: `edmm_shortcode_atts` (filter — new shortcode attribute defaults), `edmm_shortcode_instance_config` (filter — new per-instance JS config keys), `edmm_shortcode_builder_fields` (action — raw-HTML escape hatch for wholly new builder rows), `edmm_shortcode_builder_label_fields` (filter — entries in the builder's "Column Labels" row), `edmm_shortcode_builder_hide_fields` (filter — entries in the builder's "Hide Columns" row).

**After:** all five are removed — **no deprecation shim, this is a breaking change** — replaced by one filter, `edmm_shortcode_field_registry` (see `Shortcode/FieldRegistry.php` and the row in `CLAUDE.md`'s extension-point table). A callback on this filter returns an array of field *descriptors* instead of a bare label/default; free itself derives the shortcode default, instance-config value, REST arg (when opted in), and builder-UI row from each descriptor.

**Contract impact:** any callback still hooked to the five removed hooks stops running (`apply_filters()`/`do_action()` on an unregistered hook name is a silent no-op in WordPress — it does not error, so a callback written against the old contract will just quietly stop taking effect, not fail loudly). Every consumer needs to move to `edmm_shortcode_field_registry` before upgrading past this change.

**Not a 1:1 replacement for `edmm_shortcode_builder_fields`'s raw-HTML use case:** the new registry only covers typed field descriptors (the six `FieldRegistry` types). `edmm_shortcode_builder_fields` was a bare `do_action()` some callers used to inject arbitrary custom markup/sections into the builder form (not just a labeled input/checkbox/select). There is no equivalent escape hatch in the new filter — a caller doing that needs a different approach (e.g. its own `admin_footer`/inline-script hook on the settings page) or to accept the field is intentionally unsupported by the registry.

```php
// Before:
add_filter( 'edmm_shortcode_atts', function ( array $defaults ) {
	$defaults['location_label'] = '';
	$defaults['hide_location']  = 'false';
	return $defaults;
} );
add_filter( 'edmm_shortcode_builder_label_fields', function ( array $fields ) {
	$fields['location_label'] = __( 'Location', 'edmm-pro' );
	return $fields;
} );
add_filter( 'edmm_shortcode_builder_hide_fields', function ( array $fields ) {
	$fields['hide_location'] = __( 'Location', 'edmm-pro' );
	return $fields;
} );
add_filter( 'edmm_shortcode_instance_config', function ( array $config, array $atts ) {
	$config['locationLabel'] = sanitize_text_field( $atts['location_label'] ?? '' );
	$config['hideLocation']  = filter_var( $atts['hide_location'] ?? 'false', FILTER_VALIDATE_BOOLEAN );
	return $config;
}, 10, 2 );

// Now, one callback on the new filter replaces all four of the above:
add_filter( 'edmm_shortcode_field_registry', function ( array $fields ) {
	return array_merge( $fields, [
		[
			'key'     => 'location_label',
			'type'    => 'text',
			'group'   => 'column_labels',
			'label'   => __( 'Location', 'edmm-pro' ),
			'default' => '',
		],
		[
			'key'     => 'hide_location',
			'type'    => 'checkbox',
			'group'   => 'hide_columns',
			'label'   => __( 'Location', 'edmm-pro' ),
			'default' => false,
		],
	] );
} );
```

**Action for Pro before release:** grep Pro's codebase for all five removed hook names and migrate every callback to a descriptor on `edmm_shortcode_field_registry`. `posts_per_page` "all" support (previously bolted on via `edmm_rest_route_args` in `ProMetaFields::allow_all_posts_per_page()`) is now a core free-plugin field type (`number_with_all`) — that override method should be deleted entirely, not migrated.
