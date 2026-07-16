=== BoardScribe ===
Contributors: equalizedigital
Tags: meeting minutes, agenda, custom post type, shortcode, table
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish accessible, searchable meeting agendas and minutes for councils, HOAs, school boards, and nonprofits — no ACF, no complexity required.

== Description ==

**BoardScribe** turns your board meetings into a searchable, accessible public record — without hiring a developer or burying agendas and minutes in a folder of PDFs no one can find.

Built for city councils, county boards, school boards, HOAs, and nonprofits that are required — or simply committed — to keeping their meeting records open. Add a meeting once, and BoardScribe handles the rest: a paginated, screen-reader-friendly table your visitors can browse and sort, backed by a public REST API your dev team can build on.

No ACF. No third-party dependencies. Just native WordPress storage, a shortcode builder that writes the shortcode for you, and developer hooks throughout for anyone who wants to extend it further.

= Features =

* Custom post type for meetings with native meta boxes — no ACF or other plugins required.
* Shortcode builder in the admin settings page generates a ready-to-copy shortcode.
* Paginated, accessible table display via `[edbs_boardscribe]` shortcode.
* Multiple shortcodes on the same page, each independently configured.
* Responsive stacking layout on small screens with accessible column labels.
* REST API endpoint for fetching meeting data.
* Marks meetings as "not held" with a separate date format for those entries.
* Extensible — developer hooks throughout for custom add-ons.

= Perfect For =

* City councils and county boards
* School boards and library boards
* Homeowners associations (HOAs)
* Nonprofits and community organizations
* Any group with public meeting records

= Shortcode =

`[edbs_boardscribe]`

Use the **Settings > Shortcode Builder** to generate the shortcode with your preferred options, or write it manually with any of the supported attributes.

**Attributes:**

* `included_years` — Comma-separated years to display (e.g. `2023,2024`). Default: all years.
* `posts_per_page` — Entries per page. Default: `20`.
* `held_date_format` — PHP date format for meetings that were held. Default: `Y/m/d`.
* `not_held_date_format` — PHP date format for meetings not held. Default: `Y/m`.
* `hide_title` — Set to `true` to hide the Title column. Default: `false`.
* `hide_date` — Set to `true` to hide the Date column. Default: `false`.
* `hide_agenda` — Set to `true` to hide the Agenda column. Default: `false`.
* `hide_notes` — Set to `true` to hide the Notes column. Default: `false`.
* `class` — Custom CSS class on the `<table>` element.

**Example:**

`[edbs_boardscribe included_years="2023,2024" posts_per_page="10" held_date_format="F j, Y"]`

= Meta Fields =

Each meeting post stores:

* **Meeting Date** — Required. The date the meeting occurred.
* **Agenda URL** — Link to the agenda document.
* **Notes URL** — Link to the notes document.
* **Meeting Not Held** — Marks that a scheduled meeting did not take place.

= Developer Hooks =

The plugin exposes a full set of actions and filters for extending functionality. See the [plugin documentation](https://equalizedigital.com) for details.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/boardscribe`, or install through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Add meetings via **BoardScribe > Add New** in the admin menu.
4. Go to **BoardScribe > Settings** to build your shortcode, then paste it into any page or post.

== Frequently Asked Questions ==

= Do I need Advanced Custom Fields (ACF) to use this plugin? =

No. This plugin uses native WordPress meta fields and requires no third-party plugins.

= Can I display meetings for specific years only? =

Yes. Use the `included_years` shortcode attribute with a comma-separated list of years: `[edbs_boardscribe included_years="2023,2024"]`.

= Can I use the shortcode more than once on the same page? =

Yes. Each shortcode instance is independently configured and rendered. You can use multiple shortcodes on a single page to display different years, formats, or column configurations.

= What date format is used to store meeting dates? =

Dates are stored in `Y-m-d` (ISO 8601) format, e.g. `2024-01-15`. The display format is controlled by the `held_date_format` and `not_held_date_format` shortcode attributes.

= What happens to my data if I delete the plugin? =

By default, all data is preserved when the plugin is deleted. You can enable the **Delete Data on Uninstall** option in **BoardScribe > Settings > Advanced** to remove all posts and settings when the plugin is deleted. This cannot be undone.

= Is the REST API endpoint public? =

Yes. The `/wp-json/edbs/v1/boardscribe/` endpoint is intentionally public because meeting agendas and minutes are public records. No authentication is required to read them.

== Screenshots ==

1. Meetings table displayed on the front end.
2. Add or edit a meeting in the WordPress admin.
3. Shortcode builder on the settings page.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom post type with native meta boxes (no ACF dependency).
* Paginated accessible table display via `[edbs_boardscribe]` shortcode.
* Shortcode builder admin settings page.
* Multiple shortcode instances supported on a single page.
* REST API endpoint for fetching meeting data.
* Responsive stacking layout on small screens.
* Full i18n support.
* Extensibility hooks for add-ons.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
