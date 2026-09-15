# Handoff: Meeting Details meta box → React rewrite (free + Pro)

Status as of this handoff: **the free plugin (`boardscribe`) has been split into 8 commits on `develop`** (see below). **The Pro plugin (`boardscribe-pro`) is still one large uncommitted working-tree diff** — the same commit-splitting treatment hasn't been done there yet; that's the next piece of work. Nothing has been pushed or opened as a PR in either repo.

## Repos and branches

- **boardscribe** (free) — branch `develop`. Was clean at `19cc8f1`; now 8 commits ahead, `3fc7a0c` (docs) the latest as of this note, with a 9th (version bump + this doc) about to land. Run `git log --oneline 19cc8f1..HEAD` to see the full sequence:
  1. Build infra (webpack target + eslint override)
  2. `MetaBoxFieldRegistry` (the field schema, unconsumed yet)
  3. Core React app + `MetaBox.php` rewrite (also folds in the unrelated `is_native_meta_box_enabled()` dedup fix found during the earlier architecture-review pass)
  4. `resource_list` field type (Supporting Documents repeater)
  5. `attached` field mechanism (`AttachedRepeaterField`)
  6. The resource-source-tracking fix, isolated on its own (see below)
  7. Tests (`MetaBoxFieldRegistryTest.php` new, `MetaBoxRenderMetaBoxTest.php`/`MetaBoxSaveMetaTest.php` updated, `tests/jest/metabox/resource-utils.test.js` new — **jest tests were run and pass**, `npm run test:js -- tests/jest/metabox`)
  8. Docs (`AGENTS.md`, `docs/HOOK-CONTRACT-CHANGES.md`, `docs/hooks.md` regenerated)
  9. Version bump (`boardscribe.php`/`readme.txt`, 1.5.1 → 1.6.0) + this handoff doc

  Commits 3–6 are each independently buildable/lint-clean (verified: `npx eslint src/js/metabox/`, `npm run build`, `php -l`) but not independently *feature-complete* — e.g. commit 3 alone ships a working meta box, but Supporting Documents doesn't exist until commit 4. Commit 6 (the resource-source fix) required manually reconstructing "pre-fix" content for every file it touches (there was no git history to diff against, since this was all one uncommitted working-tree change) — each file was reverted, staged into 3/4/5, then restored to its final content for commit 6. If something looks subtly different from what's described in this doc, trust the actual commits (`git show <hash>`), not this prose.

- **boardscribe-pro** — branch `william/pro-1328-investigate-post-to-post-connections-between-documents-and`, HEAD still at `642248c`, **not yet split into commits**. That branch already contains real, previously-committed PRO-1328 work (a generic `Relationships\PostConnections` primitive, the Location CPT groundwork, combobox/tag-picker traits) that predates this session — this handoff's changes are *on top of* that, not a replacement for it. Don't confuse the two when reviewing `git log` vs `git diff`.

## What this work is

The Meeting Details meta box (the "Board Meetings" post-edit-screen box with Meeting Date, Agenda, Minutes, etc.) was rewritten from PHP-rendered `<table>` markup + hand-rolled jQuery into a single React app (`src/js/metabox/` in free), driven by a PHP field-registry (`MetaBoxFieldRegistry::js_schema()`) that both plugins contribute to. Pro's own picker widgets (Location, Documents) were reworked to plug into that registry as generic field types instead of hooking PHP `do_action()` render points that no longer exist.

This was a long, incremental session covering (roughly in order):

1. Vendored `@wordpress/ui`'s `CollapsibleCard` into Pro (the only vendored/built JS in an otherwise no-build-step plugin) for the Location field's "Contact Details" disclosure.
2. Removed the free-plugin Location field's legacy "One-off Address" textarea from the authoring UI (data + display path kept for backward compatibility — old meetings still show it).
3. Built the Supporting Documents repeater (modal Add via Media Library/external URL, per-row editable title, drag + keyboard reorder).
4. Built Recording's "Caption Tracks" feature (VTT/SRT attachments), including a rename/genericization pass after first-draft leaked Pro-specific naming into free's generic component (see "Architecture" below).
5. **A full architecture review pass** across the entire diff in both repos, checking for free/Pro boundary leaks. Found and fixed two stale doc-comments and one real (minor) bug — `MetaBox.php`'s `edbs_use_native_meta_boxes` filter had picked up a second, undocumented `apply_filters()` call that confused the hooks-doc generator; fixed by extracting both call sites into one documented `is_native_meta_box_enabled()` helper.
6. **Resource-source tracking fix** (the most recent piece, see below) — a real bug the user caught: the meta box's resource cards were guessing "Media Library" vs "External URL" purely from same-origin URL matching, which is wrong for offloaded/CDN'd media and, concretely, for Pro's own "linked document" source (its permalink is same-origin, so it was showing as "Media Library"). Replaced the guess with an explicitly-tracked `source` value, generic and free-owned.

## Architecture (read this before touching either repo)

**Principle already established, reinforced throughout this session:** the free plugin owns the *generic mechanism*; Pro owns *feature specifics*. A free-plugin component must carry zero hardcoded knowledge of any Pro feature — every label/default/string is data the field descriptor supplies.

- **`MetaBoxFieldRegistry::all()`** (free, `includes/Admin/MetaBoxFieldRegistry.php`) — single source of truth. Merges free's 4 core fields with whatever Pro adds via the `edbs_meeting_meta_fields` filter, resolves `insert_after` ordering. `js_schema()` projects it into a JSON-safe shape for the React app.
- **Field types**: `date`/`url`/`checkbox`/`text`/`textarea` (built-in scalars), `resource` (single URL-shaped value shown as a card with an Add/Replace modal), `resource_list` (a reorderable repeater of `{label, url, source}` items — Supporting Documents, Caption Tracks), `html` (escape hatch: a plugin's existing PHP-rendered + plain-JS widget, via `render_callback`/`init_fn` — Location and Document pickers use this), or a fully custom type registered on `window.edbsMetaBoxControls`.
- **`attached` fields** — a field that never gets its own row; its repeater renders *inside* another field's card via `attached_field_key` (Recording's caption tracks inside Recording's own card). Generic mechanism, `AttachedRepeaterField` in free.
- **`window.edbsResourceSources`** — a `resource`/`resource_list` field's Add/Replace modal offers built-in `media_library`/`external_url` plus any plugin-registered source id here. Pro's `documentPicker.js` registers `.document` this way.
- **Native form save, not AJAX** — the React app renders real named inputs; `MetaBox::save_meta()` walks the registry and saves generically by type (or skips fields marked `saved_externally`, which the owning plugin saves itself on `edbs_save_meeting_meta`).

See both repos' `AGENTS.md` — they were kept current throughout this session and are the best next read after this doc.

## The resource-source-tracking fix (most recent change, still uncommitted)

**Problem:** `resource-utils.js`'s `classifyResourceUrl()` inferred a resource card's chip ("Media Library" vs "External URL") purely from whether the URL's origin matched the site's own. Wrong in two ways: an offloaded/CDN'd Media Library attachment isn't same-origin (mislabeled "External URL"), and Pro's "linked document" source's permalink *is* same-origin (mislabeled "Media Library" — a real, live, shipping bug).

**Fix:** track which Add/Replace-modal source actually produced a value, rather than re-deriving it.

- Free: `resource-modal.js` now stamps `source: activeSource` onto every source's `onSave` callback automatically (no individual source component needed changes). Single `resource` fields get a sibling `{key}_source` post meta, registered/saved/rendered generically by `MetaBox.php` for *any* `resource`-type field (`save_resource_source()`) — Pro's own resource fields get this for free with zero Pro code beyond registering the meta key. `resource_list` items just carry `source` as a third property alongside `label`/`url`. New `resolveResourceDisplay(url, source)` in `resource-utils.js` uses the tracked source when present, falling back to the old same-origin guess only for legacy data (saved before this existed) or data set outside the meta box UI (e.g. CSV import) — this fallback behavior was an explicit design choice, confirmed with the user before implementing.
- Pro: `ProMetaFields.php` registers `_source` siblings for Livestream/Recording/Transcript and persists `source` in `save_label_url_pairs_field()`/`sanitize_documents()` for Supporting Documents and Caption Tracks. No JS changes needed in Pro — the free-side modal wrapper covers `documentPicker.js` automatically.

**Verified live** (browser, Pro reactivated): re-linking Minutes through Pro's document picker correctly switched its chip from the (legacy, mislabeled) "Media Library" to "Choose a BoardScribe document" and persisted through reload; a same-origin external URL entered into Supporting Documents correctly read "External URL" instead of "Media Library". No console errors, ESLint/PHPCS clean, both plugins rebuilt (`npm run build` in free, `npm run build:vendor` in Pro).

## Testing done vs. not done

**Done:**
- Standalone free-plugin test with Pro fully deactivated (live browser): meta box renders only free's own fields, no console errors, save round-trip works, Add-modal correctly omits Pro's "document" source, and pre-existing Pro-authored data (a document link) still displays correctly purely from the plain URL meta free owns.
- Live combined-plugin test of the resource-source fix (see above).
- ESLint (`npx eslint src/js/metabox/`) and PHPCS on every changed file in both repos — clean.
- `npm run build` (free) and `npm run build:vendor` (Pro) — both compile clean.

**Not done / gaps:**
- **PHPUnit was never run this session** — `wp-cli`/PHPUnit in this shell can't reach the site's DB (`php_network_getaddresses: getaddrinfo failed`), and `vendor/bin/phpunit` needs `bin/install-wp-tests.sh` run first, which wasn't done. The test *files* were updated to match the new code (see below) but never executed — treat them as unverified.
- ~~Jest tests were never run~~ — now run and passing: `npm run test:js -- tests/jest/metabox` (10/10 passing, `resource-utils.test.js`).
- No full regression pass on Pro's Location picker's Edit/Create modals, Contact Details CollapsibleCard, or CSV import against the new Location CPT structured-address fields (flagged mid-session as needing rework, noted in free's `AGENTS.md` TODO).
- No accessibility/screen-reader pass on any of the new UI (repeaters, modals, reorder buttons).

## Files touched

**boardscribe (free)** — 11 modified + 5 new, ~373 insertions / ~330 deletions in modified files alone (new files add more):
```
M  .eslintrc
M  AGENTS.md
M  boardscribe.php                              (version 1.5.1 → 1.6.0)
M  docs/HOOK-CONTRACT-CHANGES.md
M  docs/hooks.md                                (auto-generated — regenerate via tools/generate-hooks-docs.php, don't hand-edit)
M  includes/Admin/MetaBox.php
D  partials/meta-box.php                        (deleted — replaced by the React app)
M  readme.txt                                   (Stable tag 1.5.1 → 1.6.0, changelog entry added)
M  tests/phpunit/MetaBoxRenderMetaBoxTest.php
M  tests/phpunit/MetaBoxSaveMetaTest.php
M  webpack.config.js                            (new metabox bundle)
?? assets/css/metabox.css
?? includes/Admin/MetaBoxFieldRegistry.php
?? src/js/metabox/                              (the whole React app: app.js, field-control.js, resource-*.js, attached-repeater-field.js, index.js, etc.)
?? tests/jest/metabox/
?? tests/phpunit/MetaBoxFieldRegistryTest.php
```

**boardscribe-pro** — 20 modified/deleted + 6 new, ~2952 insertions / ~1113 deletions:
```
M  AGENTS.md
M  assets/css/admin-combobox-picker.css
D  assets/css/admin-document-picker.css
D  assets/css/pro-meta.css
M  boardscribe-pro.php                          (version 1.2.2 → 1.3.0)
M  includes/Admin/DocumentPicker.php             (rewritten: add_document_source() filter instead of attach-method-widget hooks)
M  includes/Admin/LocationPicker.php             (rewritten: render_html() as the edbs_location_id field's render_callback)
M  includes/Admin/ProMetaFields.php              (add_meta_box_fields() replaces render_fields(); save_fields() much smaller — most fields now free's generic save path)
M  includes/Plugin.php
M  includes/PostType/LocationCPT.php             (major: structured address fields, type in_person/virtual/hybrid, room/instructions/email/meeting-link)
M  includes/PostType/MeetingDocumentRelationship.php
M  package-lock.json
M  package.json                                  (+@wordpress/ui devDependency, build:vendor script)
D  partials/pro-meta-fields.php                  (deleted — replaced by registry-driven fields)
M  readme.txt                                    (Stable tag 1.2.2 → 1.3.0, changelog entry added)
M  src/js/admin/comboboxPicker.js
M  src/js/admin/documentPicker.js                (rewritten)
M  src/js/admin/locationPicker.js                (rewritten, much larger — chooser/edit modals, structured fields)
D  src/js/admin/proMeta.js                       (deleted)
M  tests/phpunit/DocumentPickerRenderTest.php
M  tests/phpunit/LocationPickerRenderTest.php
?? .idea/                                        (IDE config — NOT plugin code, do not commit, probably wants .gitignore or just leave untracked)
?? includes/PostType/LocationFieldRegistry.php
?? src/js/admin/locationCptFields.js
?? src/js/admin/locationFieldRenderer.js
?? src/vendor-src/                               (collapsible-card.js source, built by webpack.config.js into src/js/admin/vendor/)
?? tests/phpunit/ProMetaFieldsAddMetaBoxFieldsTest.php
?? webpack.config.js
```

Note: `.idea/` showing as untracked in Pro is very likely a stray IDE directory, not part of this feature work — worth double-checking it's in `.gitignore` before any `git add -A`, rather than committing it by accident.

## What's next

**Free plugin's commit split is done** (see the 9-commit list under "Repos and branches" above) — nothing further needed there beyond a normal PR review.

**Pro plugin's commit split has not been started.** The technique used for free (see commit 6's description above) generalizes directly:

1. Group by cohesive feature area at whole-file granularity (see the outline below).
2. For any file touched by more than one feature/commit in that grouping, manually reconstruct its "before this later feature" content (using the tool-call history from this session, or by reading the current final file and removing the later addition by hand), stage the earlier commits against that reconstructed content, then restore the final content for the later commit.
3. After every reconstruction, verify before committing: `php -l` the changed PHP, `npx eslint` the changed JS (Pro has no JS build step of its own except the vendored `src/vendor-src/collapsible-card.js` — run `npm run build:vendor` after touching that), and PHPCS (`./vendor/bin/phpcs <file>`).

A starting decomposition (not yet validated with the user — confirm before executing, the same way free's plan was confirmed first):
1. CollapsibleCard vendoring (`webpack.config.js`, `src/vendor-src/`, `package.json`/`package-lock.json`, `locationFieldRenderer.js`).
2. `LocationCPT.php` structured-address rework + `LocationFieldRegistry.php` (new).
3. `LocationPicker.php` rewrite (chooser/edit modals, `render_html()`) + its JS.
4. `DocumentPicker.php` rewrite (`add_document_source()`) + `documentPicker.js`.
5. `ProMetaFields.php`'s `add_meta_box_fields()`/`save_fields()` rework — wiring everything into the free registry.
6. Recording Caption Tracks specifically (the `attached_field_key`/`META_RECORDING_CAPTIONS` pieces within `ProMetaFields.php`).
7. Deletions (`partials/pro-meta-fields.php`, `proMeta.js`, `admin-document-picker.css`, `pro-meta.css`) — likely folds into whichever commit above makes each one dead code.
8. The resource-source-tracking fix — mirrors free's commit 6: `_source` sibling meta registration for Livestream/Recording/Transcript in `ProMetaFields.php`, and `source` persistence in `save_label_url_pairs_field()`/`sanitize_documents()`. Should reconstruct cleanly the same way, since it's additive registration calls plus one added property per repeater item.
9. Tests (`DocumentPickerRenderTest.php`, `LocationPickerRenderTest.php`, new `ProMetaFieldsAddMetaBoxFieldsTest.php`) + `AGENTS.md`.
10. Version bump (`boardscribe-pro.php`/`readme.txt`, 1.2.2 → 1.3.0).

This split needs the user's sign-off before executing — some of these files changed together enough that a clean per-commit split will take real care to avoid a commit that doesn't build/pass lint on its own. Whoever picks this up should re-confirm the grouping with the user rather than assuming the above is final (this is exactly what happened for free: the initial proposal above was refined once actual execution started).
