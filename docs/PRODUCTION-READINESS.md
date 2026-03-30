# Production Readiness Checklist

Items needed before commercial or public distribution release.

---

## Critical Blockers

- [ ] **Remove ACF hard dependency** — replace with `register_post_meta()` + custom meta boxes or a Settings API page so the plugin works out of the box without ACF installed
- [ ] **Add admin settings page** — non-technical users should not need to know shortcode attributes; expose key settings (date format, columns, posts per page) in the WordPress admin
- [ ] **Extract CSS/JS to enqueued files** — move inline styles and scripts to `/assets/css/` and `/assets/js/` and load via `wp_enqueue_script()` / `wp_enqueue_style()` for caching, CSP compatibility, and theme overrides
- [ ] **Restructure into a proper directory layout** — single-file architecture will not scale; suggested structure:
  ```
  /assets/css/
  /assets/js/
  /src/
  /languages/
  /templates/
  equalize-digital-meeting-minutes.php
  ```

---

## Internationalization

- [ ] **Create `/languages/` directory** and generate a `.pot` file so the plugin can be translated
- [ ] **Wrap JavaScript strings** in a translatable mechanism (e.g., `wp_localize_script()` to pass translated strings from PHP to JS)

---

## User Experience

- [ ] **Admin notice when ACF is missing** (if ACF support is kept) — silent failures confuse users; display a clear notice in the admin with instructions
- [ ] **Gutenberg block** with inspector controls as an alternative to the shortcode — expected for modern WordPress plugins

---

## Code Quality

- [ ] **Conditional debug logging** — replace the commented-out `error_log()` call with a `WP_DEBUG` conditional check
- [ ] **REST API caching** — add transient caching to the REST endpoint to improve performance on busy sites
- [ ] **REST API rate limiting** — consider adding basic rate limiting to the public endpoint
- [ ] **Add unit/integration tests** — set up PHPUnit and wp-browser so updates can be verified without manual QA on every release
- [ ] **Add `uninstall.php`** — clean up CPT data and options when the plugin is deleted; expected behavior for any distributed plugin

---

## Documentation

- [ ] **Add a changelog** to README.md — required for WordPress.org and expected by users evaluating updates
- [ ] **End-user documentation** — README.md is developer-focused; add a user guide covering the admin UI, shortcode attributes with examples, and common use cases
- [ ] **Add inline developer docs** for any new classes/methods added during restructuring

---

## Compatibility & Testing

- [ ] **Document minimum PHP version** — declare a minimum PHP version in the plugin header and README; required by WordPress.org and expected by hosting customers
- [ ] **Multisite compatibility** — test and document behavior on WordPress multisite networks; many school boards and government clients run multisite
- [ ] **Conflict testing** — test against popular themes (Divi, Avada, GeneratePress) and plugins (Yoast, WooCommerce, Elementor) before release

---

## Distribution

- [ ] **Verify plugin header** is complete for WordPress.org submission (Requires Plugins field if ACF remains optional)
- [ ] **Test against current WordPress version** and update "Tested up to" in plugin header and README
- [ ] **Review GPL compliance** for any libraries or code added during restructuring
- [ ] **Set up a license key / activation system** if going premium (e.g., via Easy Digital Downloads Software Licensing or Freemius)
- [ ] **Define freemium split** — decide which features stay free vs. premium before writing any new code; this drives every architecture decision
