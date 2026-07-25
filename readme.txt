=== BoardScribe ===
Contributors: equalizedigital, stevejonesdev, alh0319, williampatton
Tags: board meetings, meeting minutes, meeting agenda, agenda, minutes
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish accessible, searchable board meeting minutes and agendas for councils, HOAs, school boards, and nonprofits. No ACF required.

== Description ==

### Publish Accessible Board Meeting Minutes and Agendas

**BoardScribe** turns your board meetings into a searchable, accessible public record, without hiring a developer or burying agendas and minutes in a folder of PDFs no one can find.

BoardScribe comes from **Equalize Digital**, the team behind **Accessibility Checker**, the WordPress accessibility plugin trusted on more than 17,000 websites. We bring that same accessibility-first approach to your board's agendas and minutes.

[Plugin Website](https://equalizedigital.com) | [WP Accessibility Meetup](https://equalizedigital.com/wordpress-accessibility-meetup/) | [WP Accessibility Facebook Group](https://www.facebook.com/groups/wordpress.accessibility)

### Why Publish Board Meetings Online

Open-meeting and public-records laws (such as state "sunshine" laws in the U.S., along with the transparency policies many HOAs and nonprofits adopt) often require public bodies to make their agendas and minutes available to the community. Posting them on your website in an organized, searchable format is one of the simplest ways to meet those expectations.

Publishing your board meeting records online can help you:

* **Meet transparency and open-records obligations.** Give residents and members a single, reliable place to find every agenda and set of minutes.
* **Improve accessibility.** A screen-reader-friendly table is far more usable than a folder of scanned PDFs, and that matters where web accessibility is legally required.
* **Build public trust.** Open, easy-to-find records signal an organization with nothing to hide.
* **Save staff time.** Cut down on records requests and "where do I find the minutes?" emails.

### Built for Public Bodies and Boards

Built for city councils, county boards, school boards, HOAs, and nonprofits that are required, or simply committed, to keeping their meeting records open. Add a meeting once, and BoardScribe handles the rest: a paginated, screen-reader-friendly table your visitors can browse and sort, backed by a public REST API your dev team can build on.

No ACF. No third-party dependencies. Just native WordPress storage, a shortcode builder that writes the shortcode for you, and developer hooks throughout for anyone who wants to extend it further.

### Features

* **Native meeting records.** A custom post type with native meta boxes. No ACF or other plugins required.
* **Shortcode builder.** Generate a ready-to-copy shortcode from the admin settings page.
* **Board Meetings block.** A Gutenberg block with sidebar controls. No shortcode required.
* **Accessible table display.** Paginated, screen-reader-friendly output via the `[edbs_boardscribe]` shortcode.
* **Multiple instances.** Use several shortcodes on one page, each independently configured.
* **Responsive layout.** Columns stack cleanly on small screens with accessible labels.
* **REST API.** A public endpoint for fetching meeting data.
* **"Not held" meetings.** Mark cancelled meetings with a separate date format.
* **Extensible.** Developer hooks throughout for custom add-ons.

### Perfect For

* City councils and county boards
* School boards and library boards
* Homeowners associations (HOAs)
* Nonprofits and community organizations
* Any group with public meeting records

### Shortcode

`[edbs_boardscribe]`

Use the **Settings > Shortcode Builder** to generate the shortcode with your preferred options, or write it manually with any of the supported attributes.

**Attributes:**

* `included_years` - Comma-separated years to display (e.g. `2023,2024`). Default: all years.
* `start_date`, `end_date` - Only show meetings in this date range (`YYYY-MM-DD`, inclusive on both ends). Either can be used alone for an open-ended range. Takes priority over `included_years` when set — useful for fiscal years that don't align to the calendar year (e.g. `start_date="2025-07-01" end_date="2026-06-30"`).
* `posts_per_page` - Entries per page. Use `-1` to show all. Default: `20`.
* `held_date_format` - PHP date format for meetings that were held. Default: `l, F j, Y`.
* `not_held_date_format` - PHP date format for meetings not held. Default: `F Y`.
* `hide_title` - Set to `true` to hide the Title column. Default: `false`.
* `hide_date` - Set to `true` to hide the Date column. Default: `false`.
* `hide_agenda` - Set to `true` to hide the Agenda column. Default: `false`.
* `hide_minutes` - Set to `true` to hide the Minutes column. Default: `false`.
* `title_label`, `date_label`, `agenda_label`, `minutes_label` - Override the header text for each column. Default: the column's built-in name.
* `agenda_link_label`, `minutes_link_label` - Override the link text shown in the Agenda and Minutes cells. Default: the built-in link text.
* `equal_columns` - Set to `true` to force every column to the same width. Default: `false`.
* `class` - Custom CSS class on the `<table>` element.

**Example:**

`[edbs_boardscribe included_years="2023,2024" posts_per_page="10" held_date_format="F j, Y"]`

### Meeting Fields

Each meeting post stores:

* **Meeting Date** - Required. The date the meeting was or will be held.
* **Agenda URL** - Link to the agenda document.
* **Minutes URL** - Link to the published minutes document.
* **Meeting Not Held** - Marks that a scheduled meeting did not take place.

### Developer Hooks

The plugin exposes a full set of actions and filters for extending functionality. See the [plugin documentation](https://equalizedigital.com) for details.

### Source Code and Build Process

BoardScribe is GPL-licensed, and the human-readable source for every compiled file it ships is included in the plugin itself, under `src/`:

* `assets/build/boardscribe.js` is built from `src/js/` (front-end table rendering, pagination, and the display-template registry).
* `assets/build/block/index.js` is built from `src/js/block/` (the Board Meetings block editor script).
* `assets/build/builder/index.js` is built from `src/js/builder/` (the admin Shortcode Builder app).

No third-party JavaScript libraries are bundled. The build output contains only the plugin's own code plus webpack's module runtime; `@wordpress/*` imports resolve to the `wp.*` globals WordPress already enqueues, and are not included in the bundles.

To rebuild the compiled assets from source, run the following from the plugin directory:

`npm install`
`npm run build`

The build is [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack and Babel), configured by the bundled `webpack.config.js` and `package.json`. `npm run start` runs the same build in watch mode, and `npm run lint:js` lints the source.

### About Equalize Digital

[Equalize Digital](https://equalizedigital.com) is a mission-driven WordPress accessibility company working toward a world where everyone has equal access to the web, regardless of ability. Our team has been building custom WordPress websites, themes, and plugins since 2010, and we are the trusted accessibility partner for organizations across government, higher education, nonprofit, and enterprise. We build tools like [Accessibility Checker](https://wordpress.org/plugins/accessibility-checker/), and BoardScribe extends that mission to public meeting records.

Ready to make your meeting records open and accessible? Install BoardScribe and add your first meeting in minutes.

== Installation ==

### Install Within WordPress

1. In your WordPress dashboard, go to **Plugins > Add New**.
2. Search for `BoardScribe`.
3. Click **Install Now**, then **Activate**.

### Installing Manually

1. Download the plugin zip from WordPress.org.
2. Upload the unzipped `boardscribe` folder to `/wp-content/plugins/` using FTP or your hosting file manager.
3. Activate **BoardScribe** from the Plugins page in WordPress.

### After Activation

1. Go to **BoardScribe > Add New** to add your first meeting. Set the meeting date and, optionally, the agenda and minutes URLs.
2. Open **BoardScribe > Settings > Shortcode Builder** to configure columns, date formats, and pagination, then copy the generated shortcode.
3. Paste the `[edbs_boardscribe]` shortcode into any page or post, or add the **Board Meetings** block in the editor.

== Frequently Asked Questions ==

= Do I need Advanced Custom Fields (ACF) to use this plugin? =

No. This plugin uses native WordPress meta fields and requires no third-party plugins.

= How do I display board meetings on a page? =

Add the `[edbs_boardscribe]` shortcode to any page or post, or use the **Board Meetings** block in the editor. Both render a paginated, accessible table of your meetings with links to each agenda and minutes document. Use **BoardScribe > Settings > Shortcode Builder** to configure columns, date formats, and more.

= Will BoardScribe work with my theme or page builder? =

Yes. BoardScribe outputs a standard, accessible HTML table through a shortcode, a Gutenberg block, or a public REST endpoint, so it works with the block editor, the classic editor, and popular page builders. Use the `class` attribute or your theme's CSS to match the table to your site's styling.

= Can I display meetings for specific years only? =

Yes. Use the `included_years` shortcode attribute with a comma-separated list of years: `[edbs_boardscribe included_years="2023,2024"]`.

= Can I display meetings for a fiscal year that doesn't match the calendar year? =

Yes. Use the `start_date` and `end_date` shortcode attributes for an arbitrary date range: `[edbs_boardscribe start_date="2025-07-01" end_date="2026-06-30"]`.

= Can I use the shortcode more than once on the same page? =

Yes. Each shortcode instance is independently configured and rendered. You can use multiple shortcodes on a single page to display different years, formats, or column configurations.

= What date format is used to store meeting dates? =

Dates are stored in `Y-m-d` (ISO 8601) format, e.g. `2024-01-15`. The display format is controlled by the `held_date_format` and `not_held_date_format` shortcode attributes.

= What happens to my data if I delete the plugin? =

By default, all data is preserved when the plugin is deleted. You can enable the **Delete Data on Uninstall** option in **BoardScribe > Settings** (General tab) to remove all posts and settings when the plugin is deleted. This cannot be undone.

= Is the REST API endpoint public? =

Yes. The `/wp-json/edbs/v1/boardscribe/` endpoint is intentionally public because meeting agendas and minutes are public records. No authentication is required to read them.

== Screenshots ==

1. Accessible board meetings table with agenda and minutes links, displayed on the front end.
2. Adding a board meeting (date, agenda URL, and minutes URL) in the WordPress admin.
3. The Shortcode Builder generating a ready-to-paste meeting minutes shortcode.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom post type with native meta boxes (no ACF dependency).
* Paginated accessible table display via `[edbs_boardscribe]` shortcode.
* "Board Meetings" Gutenberg block with inspector controls.
* Shortcode builder admin settings page.
* Multiple shortcode instances supported on a single page.
* REST API endpoint for fetching meeting data.
* Responsive stacking layout on small screens.
* Full i18n support.
* Extensibility hooks for add-ons.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
