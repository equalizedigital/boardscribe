# Meeting data model (for migrations / WXR imports)

Reference for anyone writing a migration script or hand-built WXR file to move meetings into BoardScribe from another system (e.g. a custom Divi layout backed by ACF or plain custom fields). This documents what the plugin actually reads and writes — see `includes/PostType/BoardScribeCPT.php`, `includes/Admin/MetaBox.php`, and `includes/REST/BoardScribeEndpoint.php` for the source.

## Post type

- Slug: **`edbs_meeting`**
- `supports`: `title` only — no editor content, no featured image handling in the plugin's own UI or display pipeline.
- `post_status`: any standard WordPress status; not constrained by the plugin.
- `post_title`: free text. If you leave it blank, BoardScribe auto-generates `"Board Meeting - {formatted date}"`, but **only** as a side effect of the admin meta box's save handler (`MetaBox::save_meta()`, hooked on `save_post_edbs_meeting`). A WXR import or a script that writes post meta directly (`wp_insert_post()` + `update_post_meta()`, or `wp import`) does **not** go through that handler, so nothing will backfill a blank title for you — set `post_title` explicitly in the import data.
- WXR: use `<wp:post_type>edbs_meeting</wp:post_type>` on each `<item>`.

## Post meta

| Meta key | Type | Format | Required | Notes |
|---|---|---|---|---|
| `edbs_meeting_date` | string | `Y-m-d`, e.g. `2026-03-12` | Effectively yes — meetings sort on this field, and a blank/unparsable value renders as "Date not available" instead of a date | Sanitized with `sanitize_text_field()` on save. When *reading*, `BoardScribeEndpoint::parse_date()` also accepts `Ymd`, `d/m/Y`, `m/d/Y` as a legacy-data fallback, but the plugin's own UI only ever writes `Y-m-d` — target that format for anything you generate. |
| `edbs_agenda_url` | string | full URL | optional | Sanitized with `esc_url_raw()`. Leave empty/omit if there's no agenda document. Typically a Media Library attachment URL, but any URL works — the plugin stores a URL string, not an attachment ID. |
| `edbs_minutes_url` | string | full URL | optional | Same as above, for the published minutes document. |
| `edbs_meeting_not_held` | string | `'1'` or `''` | optional, defaults to not-set (= meeting was held) | **Not a real boolean** — stored and read as a string. `'1'` marks the meeting cancelled/not held (the row shows "Meeting not held" in place of the agenda link); anything else, including the key being absent, means a normal meeting. |

All four keys are registered with `register_post_meta( 'edbs_meeting', ..., [ 'single' => true, 'show_in_rest' => true, ... ] )`, so besides direct DB/`update_post_meta()` writes or WXR `<wp:postmeta>` entries, they're also settable through the core REST API (`POST /wp/v2/edbs_meeting/{id}` with a `meta` object) if that's a more convenient path for a migration script than generating WXR.

## Not part of the free data model

- **No taxonomy ships by default.** `PostType/BoardScribeCPT.php` fires `edbs_before_register_cpt` / `edbs_after_register_cpt` specifically so a taxonomy (e.g. "board" or "meeting series") can be registered against `edbs_meeting`, but free itself registers none. If the source system groups meetings by board/committee and you want that preserved as a real taxonomy rather than just folded into the title, that needs custom code (Pro or a site-specific mu-plugin) — ask if this matters before assuming it's out of scope.
- **No attachment/file ID meta** — only the two URL fields above.
- **`post_content` / excerpt are unused** by the display pipeline (shortcode, block, REST endpoint) — safe to leave blank.

## Verifying an import

Render `[edbs_boardscribe]` (or the block equivalent) against the imported posts — it reads exactly the four meta keys above via `BoardScribeEndpoint::build_meeting_row()`. "Date not available" or a missing agenda/minutes link on a row is the fastest signal that a meta key name, date format, or URL didn't land as expected. With `WP_DEBUG` on, each row logs the raw `edbs_meeting_date` value it read, which is useful for spotting a format mismatch across a bulk import.
