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
