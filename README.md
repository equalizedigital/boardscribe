[![CS](https://github.com/equalizedigital/boardscribe/actions/workflows/cs.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/cs.yml)
[![Lint PHP](https://github.com/equalizedigital/boardscribe/actions/workflows/lint-php.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/lint-php.yml)
[![Lint JS](https://github.com/equalizedigital/boardscribe/actions/workflows/lint-js.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/lint-js.yml)
[![Build JS](https://github.com/equalizedigital/boardscribe/actions/workflows/build-js.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/build-js.yml)
[![Security](https://github.com/equalizedigital/boardscribe/actions/workflows/security.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/security.yml)
[![Test](https://github.com/equalizedigital/boardscribe/actions/workflows/phpunit.yml/badge.svg)](https://github.com/equalizedigital/boardscribe/actions/workflows/phpunit.yml)

# BoardScribe

## What is this?

Publish board meeting dates, agendas, and minutes on your WordPress website, accessibly. BoardScribe manages meeting minutes as a native custom post type (no ACF required) and displays them with a paginated, accessible table via the `[edbs_boardscribe]` shortcode or the equivalent Gutenberg block, backed by a built-in REST API endpoint.

* [Plugin Website](https://equalizedigital.com/boardscribe)
* [Documentation](https://equalizedigital.com/boardscribe/documentation/)
* [Live Demo](https://equalizedigital.com/boardscribe/demo)

### Features

- Custom post type for meeting minutes with native meta boxes (no ACF required).
- Shortcode builder in the admin settings page generates a ready-to-copy shortcode.
- Paginated, accessible table display via `[edbs_boardscribe]` shortcode, or the equivalent **BoardScribe** Gutenberg block.
- Supports multiple shortcodes/blocks on the same page, each independently configured.
- REST API endpoint (`/wp-json/edbs/v1/boardscribe/`) for fetching meeting minutes.
- Responsive stacking layout on small screens with accessible column labels.
- Extensive filter/action hooks and JavaScript registries for building add-ons (see [Developer Hooks](#developer-hooks) below).

## Installation

1. Upload the plugin files to the `/wp-content/plugins/boardscribe` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Add meetings via **Meeting Minutes > Add New** in the admin menu.
4. Use the **Meeting Minutes > Settings** page to build your shortcode, then paste it into any page or post.

## Shortcode

```text
[edbs_boardscribe]
```

Use the **Settings > Shortcode Builder** to generate the shortcode with your preferred options. You can also write the shortcode manually using the attributes below.

### Shortcode Attributes

| Attribute | Default | Description |
|---|---|---|
| `included_years` | *(all)* | Comma-separated years to display (e.g. `2023,2024`). Leave blank for all years. Ignored when `start_date`/`end_date` is set. |
| `start_date` | *(none)* | Only show meetings on or after this date (`YYYY-MM-DD`). Takes priority over `included_years`. |
| `end_date` | *(none)* | Only show meetings on or before this date (`YYYY-MM-DD`). Takes priority over `included_years`. |
| `posts_per_page` | `20` | Number of entries per page. Use `-1` (or `all`) to show every entry, up to the site's configured maximum. |
| `held_date_format` | `l, F j, Y` | PHP `date()` format for meetings that were held. |
| `not_held_date_format` | `F Y` | PHP `date()` format for meetings that were not held. |
| `template` | *(table)* | Name of a registered display template to render with. Free ships only the built-in table; add-ons can register additional templates (see [Display Templates](#display-templates--javascript-registries)). |
| `equal_columns` | `false` | Set to `true` to force all table columns to the same width. |
| `class` | *(none)* | Custom CSS class(es) added to the `<table>` element. |
| `title_label` / `date_label` / `agenda_label` / `minutes_label` | *(default column labels)* | Overrides the column header text for the Title/Date/Agenda/Minutes columns. |
| `agenda_link_label` / `minutes_link_label` | *("View Agenda" / "View Minutes")* | Overrides the link text for the Agenda/Minutes cell links. |
| `hide_title` | `false` | Set to `true` to hide the Title column. |
| `hide_date` | `false` | Set to `true` to hide the Date column. |
| `hide_agenda` | `false` | Set to `true` to hide the Agenda column. |
| `hide_minutes` | `false` | Set to `true` to hide the Minutes column. |

### Example

```text
[edbs_boardscribe included_years="2023,2024" posts_per_page="10" held_date_format="F j, Y"]
```

## Gutenberg Block

A **BoardScribe** block (`equalize-digital/boardscribe`) is also available in the block editor, listed under the "BoardScribe" block category. It renders through the same shortcode pipeline and its Inspector Controls expose the same set of options as the shortcode attributes above (including any added by add-ons), with a few caveats: only fields the registry flags with `rest_arg` are also exposed as REST parameters, a field marked `hidden_from_ui` is excluded from both the block's Inspector Controls and the settings-page builder (though still valid on existing shortcodes/blocks that already use it), and `class`/`className` isn't rendered by the block's generic Inspector loop at all, it's handled by Gutenberg's native "Additional CSS Class(es)" Advanced-panel field instead.

## Meta Fields

Each meeting minute post has four native meta fields:

- **Meeting Date** — Required. Date the meeting occurred (`YYYY-MM-DD`).
- **Agenda URL** — Link to the meeting agenda document.
- **Minutes URL** — Link to the published meeting minutes document.
- **Meeting Not Held** — Checkbox to mark that a scheduled meeting did not take place.

## REST API

The plugin exposes a public REST API endpoint for fetching meeting minutes data. This is intentional — meeting minutes are public records.

**Endpoint:** `GET /wp-json/edbs/v1/boardscribe/`

| Parameter | Default | Description |
|---|---|---|
| `page` | `1` | Page number for pagination. |
| `posts_per_page` | `20` | Number of results per page. `-1` resolves to the site's "show all" maximum (default 500, see `edbs_rest_absolute_max_per_page`). Positive values are capped (default 100, see `edbs_rest_max_per_page`). |
| `included_years` | *(all)* | Comma-separated years filter. Ignored when `start_date`/`end_date` is set. |
| `start_date` | *(none)* | Only return meetings on or after this date (`YYYY-MM-DD`). Takes priority over `included_years`. |
| `end_date` | *(none)* | Only return meetings on or before this date (`YYYY-MM-DD`). Takes priority over `included_years`. |
| `held_date_format` | `l, F j, Y` | Date format for held meetings. |
| `not_held_date_format` | `F Y` | Date format for not-held meetings. |
| `agenda_link_label` | `View Agenda` | Link text used for the agenda link. |
| `minutes_link_label` | `View Minutes` | Link text used for the minutes link. |

**Example:**

```text
GET https://yourdomain.com/wp-json/edbs/v1/boardscribe/?included_years=2024&posts_per_page=10&page=1
```

## Developer Hooks

The plugin exposes a large set of hooks, JavaScript registries, and reusable methods for building add-ons (Equalize Digital's own Pro plugin is built entirely on top of these).

### PHP Action Hooks

| Hook | Description |
|---|---|
| `edbs_loaded` | Fires after all plugin components are registered. Primary entry point for add-ons. Passes the `Plugin` instance. |
| `edbs_before_register_cpt` / `edbs_after_register_cpt` | Fire immediately before/after the `edbs_meeting` custom post type is registered (the place to register taxonomies that bind to it). |
| `edbs_before_meta_box_fields` | Fires before the default meta box fields are rendered, for prepending fields. |
| `edbs_meta_fields` | Fires inside the meta box, after the default fields. |
| `edbs_after_agenda_url_field` / `edbs_after_minutes_url_field` | Fire immediately after the Agenda URL / Minutes URL field row in the meta box, for adding a field tightly coupled to one of those URLs. |
| `edbs_save_meeting_meta` | Fires after the default meta fields are saved on `save_post`. Passes the post ID. |
| `edbs_enqueue_assets` | Fires after the core stylesheet/script are enqueued (also fires for the settings page's live shortcode-builder preview). |
| `edbs_before_table` / `edbs_after_table` | Fire inside the shortcode wrapper markup, before/after the table container, for lightweight additions like a search box or a print button. |
| `edbs_settings_fields` | Fires on the settings page's General tab, for adding sections outside the Settings API. |
| `edbs_settings_tab_content_{$tab}` | Fires to render the panel content for a non-core settings tab registered via `edbs_settings_tabs` (e.g. `edbs_settings_tab_content_import`). |

### PHP Filter Hooks

| Hook | Description |
|---|---|
| `edbs_cpt_args` | Filters the `register_post_type()` args for the `edbs_meeting` CPT before registration. |
| `edbs_shortcode_field_registry` | Single source of truth for every field-backed shortcode/REST/block attribute. Filters the array of field descriptors (key, type, group, label, default, choices, sanitize/validate callbacks, `rest_arg`, etc.). One descriptor drives the shortcode default, REST arg, builder-UI control, and block attribute/Inspector control simultaneously. |
| `edbs_rest_query_args` | Filters the `WP_Query` args before the REST endpoint runs its query. |
| `edbs_rest_route_args` | Filters the registered args schema for the `/edbs/v1/boardscribe/` REST route, for adding REST-only params not backed by a shortcode/block field. |
| `edbs_rest_max_per_page` / `edbs_rest_absolute_max_per_page` | Filter the bounds on the REST endpoint's page size (the cap applied to a positive `posts_per_page`, default 100, and the ceiling substituted for `-1`/"show all", default 500, since the route is public/anonymous). |
| `edbs_agenda_link` / `edbs_minutes_link` | Filter the built `<a>` markup for the agenda/minutes link in a meeting row. |
| `edbs_meeting_row_data` | Filters a single meeting's row data (title, date, agenda/minutes links) before it's returned. |
| `edbs_meeting_formatted_date` | Filters the computed date display string before it's used in the date cell and the agenda/minutes link `aria-label`s. |
| `edbs_rest_response` | Filters the full REST response array before it is returned. |
| `edbs_use_native_meta_boxes` | Return `false` to suppress the native meta box UI entirely. |
| `edbs_default_meeting_title` | Filters the auto-generated title used when a meeting is saved with a blank title. |
| `edbs_utm_query_args` | Filters the query parameters appended to outbound equalizedigital.com links. |
| `edbs_settings_tabs` | Filters the settings page's tab list (slug => `{ icon, label }`); array order controls display order. |
| `edbs_block_preview_columns` | Filters the column list used by the block editor's server-rendered preview table. |
| `edbs_block_editor_preview` | Short-circuits the block editor preview with template-specific markup; return a string to use it, or `null`/anything else to fall through to the default flat table. |
| `edbs_block_preview_max_rows` | Filters the row cap applied to the block editor preview (default 5). |

### Display Templates & JavaScript Registries

The front-end table renderer can be replaced or extended without a build step. Add-ons register against these plain `window` globals before `DOMContentLoaded`:

| Registry / Helper | Description |
|---|---|
| `window.edbsExtraColumns` | Array of `{ key, label, renderCell(meeting) }` objects to add extra table columns. `renderCell()`/`label` output is inserted as raw HTML and must be escaped by the caller. |
| `window.edbsTemplates` | Display-template registry, keyed by name, selected per shortcode/block instance via the `template` attribute. A template provides `render(data, instanceCfg, container)` plus optional `renderPagination`, `renderInfo`, `focus`, `buildRequestUrl`, and `request` overrides. The built-in table is registered as `table`; unrecognized names fall back to it. |
| `window.edbsInitInstance(container)` | Initializes one `.edbs-boardscribe-wrap` instance on demand, for wrappers injected after `DOMContentLoaded`. |
| `window.edbsBuildTable(meetings, instanceCfg)` | Returns the standard `<table>` HTML string (same columns, labels, and `window.edbsExtraColumns` handling as the built-in table), useful for a template that renders multiple tables/sections. |
| `edbs.block.templateChangeAttributes` (`wp.hooks` filter) | Filters the attribute changes applied when the block's template picker changes, so the plugin owning a template can couple other attributes to the switch. |

The following `CustomEvent`s bubble on each instance's `.edbs-boardscribe-wrap` container (bind with `addEventListener`, or on `document` to catch every instance):

| Event | Fires |
|---|---|
| `edbs:table-rendered` | After the active template's `render()` call finishes updating the table/list markup. |
| `edbs:info-rendered` | After the aria-live "Showing X to Y of Z" text updates. |
| `edbs:pagination-rendered` | After the pagination controls are (re)rendered. |
| `edbs:page-changed` | When the user triggers navigation to a new page, before the refetch/re-render. |
| `edbs:fetch-error` | When a page's data request fails. |

### Reusable PHP Methods

| Method | Description |
|---|---|
| `BoardScribeEndpoint::build_meeting_row( $post_id, $format_args, $request = null )` | Builds one meeting's escaped/formatted row data (title, date, agenda/minutes links), firing `edbs_meeting_row_data`. Reuse this instead of re-implementing the same escaping for an export, feed, or widget. |
| `BoardScribeEndpoint::parse_date( $date_string )` | Parses a raw `edbs_meeting_date` meta value against the same list of accepted formats the endpoint uses. |
| `BoardScribeBlock::render_preview_table( $columns, $rows, $attributes )` | Renders one preview table for a column/row set in the block editor, the PHP analogue of `window.edbsBuildTable()`. |
| `MetaBox::generate_default_title( $meeting_date, $post_id = 0 )` | Builds the default "Board Meeting - {date}" title used when a meeting is saved with a blank title. |

## Want to contribute?

### Prerequisites

At Equalize Digital, we make use of a specific toolset to develop our code. Please ensure you have the following tools installed before contributing.

* [Composer](https://getcomposer.org/)
* [NPM](https://www.npmjs.com/)

### Getting started

Check out this repository from GitHub, then run:

```shell
composer install
npm install
npm run build
```

`assets/build/` is gitignored, so `npm run build` (or `npm start` for webpack watch mode) is required after every clone before the shortcode/block will render anything.

### Dev environment setup

There are no special requirements for the dev environment aside from the standard WordPress/PHP runtime used by the plugin, use whatever local stack you prefer (e.g. Local by Flywheel, DesktopServer, LocalWP).

As long as you follow the _Getting started_ steps above, the plugin will run in your local environment.

### Running tests

```shell
composer lint      # php-parallel-lint syntax check
composer check-cs  # phpcs (WordPress Coding Standards)
composer fix-cs     # phpcbf, auto-fixes what's fixable
npm run lint:js     # eslint over src/js
composer test       # phpunit (requires ./scripts/setup-phpunit.sh first, or Docker via npm run test:php)
```

### Package scripts

* `npm run build` - builds the frontend, block editor, and shortcode builder JavaScript bundles
* `npm start` - watches and automatically rebuilds JavaScript on change
* `npm run lint:js` - lints the plugin's JavaScript
* `composer lint` - syntax-checks the plugin's PHP
* `composer check-cs` - checks the plugin's PHP against WordPress Coding Standards
* `composer fix-cs` - auto-fixes fixable PHP Coding Standards issues
* `composer test` - runs the plugin's PHPUnit tests

## Support

This is a developer portal for BoardScribe and should not be used for support. Please visit the [support forums](https://wordpress.org/support/plugin/boardscribe/) for support.

## Contributions

Anyone is welcome to contribute to BoardScribe. Please [read the guidelines](.github/CONTRIBUTING.md) for contributing to this repository.

There are various ways you can contribute:

* [Raise an issue](https://github.com/equalizedigital/boardscribe/issues) on GitHub.
* Send us a Pull Request with your bug fixes and/or new features.

Please also review our [Code of Conduct](.github/CODE_OF_CONDUCT.md) and [Security Policy](.github/SECURITY.md) before contributing.

## Developer docs

This repository includes generated developer documentation to make it easier to work with the plugin:

- [`docs/hooks.md`](docs/hooks.md) - a generated inventory of every `edbs_` PHP action and filter in the plugin, kept current by a weekly CI job. It's a mechanical supplement to the curated [Developer Hooks](#developer-hooks) section above, not a replacement for it: it doesn't cover the JavaScript registries/events or the reusable PHP methods documented there. Regenerate it locally with:

```bash
composer run generate-hooks-docs
```

## License

This plugin is licensed under the [GPLv2 or later](LICENSE).
