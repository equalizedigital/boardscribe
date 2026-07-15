# BoardScribe

**Contributors**: Equalize Digital
**Requires at least**: 6.7
**Tested up to**: 7.0
**Stable tag**: 1.0.0
**Requires PHP**: 7.4
**License**: GPLv2 or later
**License URI**: https://www.gnu.org/licenses/gpl-2.0.html

## Description

The **BoardScribe** plugin lets you manage and display meeting minutes as a custom post type. No third-party dependencies required — all meta fields use native WordPress storage. A built-in REST API endpoint and JavaScript table renderer power the front-end display via a simple shortcode.

### Features

- Custom post type for meeting minutes with native meta boxes (no ACF required).
- Shortcode builder in the admin settings page generates a ready-to-copy shortcode.
- Paginated, accessible table display via `[edbs_boardscribe]` shortcode.
- Supports multiple shortcodes on the same page, each independently configured.
- REST API endpoint (`/wp-json/edbs/v1/boardscribe/`) for fetching meeting minutes.
- Responsive stacking layout on small screens with accessible column labels.

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
| `included_years` | *(all)* | Comma-separated years to display (e.g. `2023,2024`). Leave blank for all years. |
| `posts_per_page` | `20` | Number of entries per page. |
| `held_date_format` | `Y/m/d` | PHP date format for meetings that were held. |
| `not_held_date_format` | `Y/m` | PHP date format for meetings that were not held. |
| `hide_title` | `false` | Set to `true` to hide the Title column. |
| `hide_date` | `false` | Set to `true` to hide the Date column. |
| `hide_agenda` | `false` | Set to `true` to hide the Agenda column. |
| `hide_notes` | `false` | Set to `true` to hide the Notes column. |
| `class` | *(none)* | Custom CSS class added to the `<table>` element. |

### Example

```text
[edbs_boardscribe included_years="2023,2024" posts_per_page="10" held_date_format="F j, Y"]
```

## Meta Fields

Each meeting minute post has four native meta fields:

- **Meeting Date** — Required. Date the meeting occurred (`YYYY-MM-DD`).
- **Agenda URL** — Link to the meeting agenda document.
- **Notes URL** — Link to the meeting notes document.
- **Meeting Not Held** — Checkbox to mark that a scheduled meeting did not take place.

## REST API

The plugin exposes a public REST API endpoint for fetching meeting minutes data. This is intentional — meeting minutes are public records.

**Endpoint:** `GET /wp-json/edbs/v1/boardscribe/`

| Parameter | Default | Description |
|---|---|---|
| `page` | `1` | Page number for pagination. |
| `posts_per_page` | `20` | Number of results per page. |
| `included_years` | *(all)* | Comma-separated years filter. |
| `held_date_format` | `Y/m/d` | Date format for held meetings. |
| `not_held_date_format` | `Y/m` | Date format for not-held meetings. |

**Example:**

```text
GET https://yourdomain.com/wp-json/edbs/v1/boardscribe/?included_years=2024&posts_per_page=10&page=1
```

## Developer Hooks

The plugin exposes several hooks for extending functionality:

| Hook | Type | Description |
|---|---|---|
| `edbs_loaded` | action | Fires after all components are registered. Entry point for add-ons. |
| `edbs_shortcode_field_registry` | filter | Single source of truth for every field-backed shortcode attribute (default, instance-config value, builder-UI row, REST arg). Replaces the old `edbs_shortcode_atts`/`edbs_shortcode_instance_config`/`edbs_shortcode_builder_*` hooks — see `docs/HOOK-CONTRACT-CHANGES.md`. |
| `edbs_rest_query_args` | filter | Filter WP_Query args in the REST endpoint. |
| `edbs_rest_response` | filter | Filter the full REST response array. |
| `edbs_meeting_row_data` | filter | Filter a single meeting row before it is returned. |
| `edbs_meta_fields` | action | Fires inside the meta box after the default fields. |
| `edbs_save_meeting_meta` | action | Fires after default meta fields are saved. |
| `edbs_after_register_cpt` | action | Fires after the CPT is registered. |
| `edbs_settings_fields` | action | Fires after default settings fields are registered. |
| `edbs_use_native_meta_boxes` | filter | Return `false` to replace the native meta box UI. |

## License

This plugin is licensed under the GPLv2 or later.
