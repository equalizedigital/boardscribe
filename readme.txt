=== BoardScribe - Accessible Board Meeting Agendas and Minutes ===
Contributors: equalizedigital, stevejonesdev, alh0319, williampatton
Tags: accessibility, document library, document management, meetings, agenda
Requires at least: 6.7
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Post board meeting minutes and agendas accessibly. Government, nonprofits, schools, HOA public transparency and accessibility compliance.

== Description ==

### Easily Publish Board Meeting Agendas and Minutes

**BoardScribe** makes it easy to publish board meetings dates, agendas, and minutes on your WordPress website. The plugin creates a document library with blocks and shortcodes to support your compliance not just with "sunshine" and open meetings laws, but also accessibility laws like Section 508, the ADA, and EN 301 549.

BoardScribe comes from Equalize Digital, the team behind [Accessibility Checker](https://equalizedigital.com/accessibility-checker), the WordPress accessibility plugin trusted by more than 17,000 websites worldwide. We bring that same accessibility-first approach to displaying your board's agendas and minutes.

[Plugin Website](https://equalizedigital.com/boardscribe) | [Documentation](https://equalizedigital.com/boardscribe/documentation/) | [Live Demo](https://equalizedigital.com/boardscribe/demo)

### Why Publish Board Meetings Using BoardScribe

Open-meeting and public-records laws (such as state "sunshine" laws in the U.S.), along with the transparency policies many nonprofits and HOAs adopt, require organizations to post notice in advance of meetings and make agendas and minutes available to the community. If meeting dates or documents are not shared in an accessible format, organizations may be failing to meet their obligations under transparency and accessibility laws.

BoardScribe makes it easy for board secretaries or staff members to post meeting information and documents in an accessible format, eliminating UI and accessibility problems common in other solutions:

* Gives the public a single, reliable place to find every agenda and minutes document, and see upcoming meetings.
* Provides content in an accessible table format, following HTML and accessibility best practices.
* Eliminates ambiguous links so screen reader users can quickly find the document they are looking for.
* Saves staff time. No more manually re-ordering lists of links and cuts down on records requests via email.

### BoardScribe Features

BoardScribe is simple to use and **works in all themes and page builders**, including Elementor, Divi, Beaver Builder, WP Bakery, and more.

* Dedicated Board Meeting content type.
* Integrates with the WordPress Media Library: attach files to meetings in a few clicks.
* Supports any file type for agendas and minutes: PDF, DOC, TXT, and more.
* Gutenberg block to quickly insert meetings and documents in the block editor.
* Shortcode to display meetings and documents in the classic editor or page builders.
* Visual shortcode builder so anyone can create and copy a shortcode in seconds.
* Adopts styles from your theme for consistent design.
* Straightforward admin editing experience easy for non-technical WordPress users.
* Include file type and size information via integration with Accessibility Checker.
* Supports displaying meetings all together, grouped by year, or custom fiscal years.
* Mark meetings as cancelled so they remain in the public record, but with a unique format.
* Mobile responsive tables that can be read on any device without sideways scrolling.

### Accessibility Is Built In, Not Bolted On

BoardScribe is built by one of the most trusted accessibility companies in WordPress. Both the front-end blocks and shortcodes, as well as the WordPress admin settings pages, have been tested for conformance to Web Content Accessibility Guidelines (WCAG) 2.2 AA. Every detail of BoardScribe is designed for people who rely on screen readers, keyboards, magnification, and other assistive technology.

* **Descriptive link names.** Every agenda and minutes link is announced with the meeting it belongs to, "View Agenda for Monday, January 5, 2026", instead of twenty identical "View Agenda" links. Screen reader users who pull up the page's list of links can actually tell them apart (WCAG 2.4.4, Link Purpose).
* **Real table semantics.** Column headers are marked up with `scope`, and each row's meeting title or date is marked as that row's header, so a screen reader announces "Minutes, Regular Board Meeting, January 5" rather than a bare, context-free link when navigating through tables with arrow keys.
* **Pagination that announces itself.** Moving between pages updates a polite live region, so screen reader users are told the results changed and which page they are on. Sighted users are not the only ones who notice the table refreshed.
* **Keyboard support that respects your place.** After a page change, focus moves into the new results instead of dumping you back at the top of the page, and the table itself is reachable and scrollable by keyboard.
* **No silent blanks.** A meeting with no agenda or minutes posted yet announces "Agenda not available" or "Minutes not available" to screen readers, rather than leaving an empty cell.
* **Responsive without losing meaning.** When columns stack on a phone, every cell keeps its label, so the table never collapses into an unlabeled pile of dates and links.
* **Your words, in your language.** Column headers and link text are all editable and fully translatable, so you are never stuck with wording that does not fit your organization or language.

One honest note: BoardScribe is **accessibility-ready**. It makes your meeting lists accessible, but it cannot fix the documents you link to or accessibility problems on your website. Because BoardScribe adopts your theme styles, if there are accessibility problems in your theme (such as missing link underlines, poor color contrast, etc.), these issues will likely also be present in BoardScribe’s components. To ensure your website is fully accessible, test it with a tool like [Accessibility Checker](https://equalizedigital.com/accessibility-checker) and consider having it [audited by an accessibility professional](https://equalizedigital.com/services/website-accessibility-audit/).

BoardScribe handles the accessibility of its own blocks and shortcodes, but you are responsible for the accessibility of your meeting documents and other parts of your website.

### Built for Public Organizations and Boards

Built for city councils, county boards, school boards, public libraries, nonprofits, Homeowners associations (HOAs), and other community organizations that are required, or simply committed, to keeping their meeting records open. 


### Shortcode

BoardScribe includes a Gutenberg block for the block editor, and a shortcode so you can place document records into pages, posts, templates or any area that accepts shortcodes.

BoardScribe shortcode:

`[edbs_boardscribe]`

Use the **Shortcode Builder** (Board Meetings > Settings > Shortcode Builder) to generate the shortcode with your preferred options, or write it manually with any of the supported attributes.

**Attributes:**

* `included_years` - Comma-separated years to display (e.g. `2023,2024`). Default: all years.
* `start_date`, `end_date` - Only show meetings in this date range (`YYYY-MM-DD`, inclusive on both ends). Either can be used alone for an open-ended range. Takes priority over `included_years` when set, which is useful for fiscal years that don't align to the calendar year (e.g. `start_date="2025-07-01" end_date="2026-06-30"`).
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


### Developer Information: Source Code and Build Process

BoardScribe is GPL-licensed and developed in the open at [github.com/equalizedigital/boardscribe](https://github.com/equalizedigital/boardscribe). The human-readable source for every compiled file the plugin ships lives in that repository, under `src/`:

* `assets/build/boardscribe.js` is built from `src/js/` (front-end table rendering, pagination, and the display-template registry).
* `assets/build/block/index.js` is built from `src/js/block/` (the Board Meetings block editor script).
* `assets/build/builder/index.js` is built from `src/js/builder/` (the admin Shortcode Builder app).

No third-party JavaScript libraries are bundled. The build output contains only the plugin's own code plus webpack's module runtime; `@wordpress/*` imports resolve to the `wp.*` globals WordPress already enqueues, and are not included in the bundles.

To rebuild the compiled assets from source, clone the repository and run:

`npm install`
`npm run build`

The build is [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack and Babel), configured by `webpack.config.js` and `package.json` in the repository. `npm run start` runs the same build in watch mode, and `npm run lint:js` lints the source.

### About Equalize Digital

[Equalize Digital](https://equalizedigital.com) is a mission-driven WordPress accessibility company working toward a world where everyone has equal access to the web, regardless of ability. Our team has been building custom WordPress websites, themes, and plugins since 2010, and we are the trusted accessibility partner for organizations across government, higher education, nonprofit, and enterprise. We build tools like [Accessibility Checker](https://wordpress.org/plugins/accessibility-checker/), and BoardScribe extends that mission to public meeting records.

Ready to make your meeting records open and accessible? Install BoardScribe and add your first meeting in minutes.

Have questions? Try our [live demo](https://equalizedigital.com/boardscribe/demo) or [contact our sales team](https://equalizedigital.com/contact/)

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

1. Go to **Board Meetings > Add New** to add your first meeting. Set the meeting date and, optionally, the agenda and minutes URLs.
2. Open **Board Meetings > Settings > Shortcode Builder** to configure columns, date formats, and pagination, then copy the generated shortcode.
3. Paste the `[edbs_boardscribe]` shortcode into any page or post, or add the **Board Meetings** block in the editor.

== Frequently Asked Questions ==

= Is BoardScribe accessible? =

BoardScribe is coded in accordance with WordPress Coding Standards and accessibility best practices. All components created by BoardScribe have been tested and confirmed WCAG 2.2 AA conformant. See "Accessibility Is Built In, Not Bolted On" above for additional information.

= Does BoardScribe help with ADA, Section 508, or WCAG compliance? =

It helps, but no plugin can make a site compliant on its own. BoardScribe's output follows WCAG 2.2 Level AA patterns. WCAG is the standard referenced by the ADA Title II web rule, Section 508, EN 301 549 and most government accessibility policies. Your overall compliance still depends on your theme, your other content, and, importantly, the agenda and minutes documents themselves. A perfectly accessible table linking to scanned image PDFs is still an accessibility barrier. 

If you want to test your whole site, our free [Accessibility Checker](https://wordpress.org/plugins/accessibility-checker/) plugin scans your pages for issues and can help you identify what needs to be fixed for full conformance.

= Will BoardScribe's table work for keyboard-only and screen reader users? =

Yes. The table is reachable and scrollable by keyboard, pagination controls are real buttons with accessible names and an `aria-current` marker on the current page, and focus lands inside the refreshed results after a page change rather than resetting to the top of the page.

= How do I display board meetings on a page? =

Add the `[edbs_boardscribe]` shortcode to any page or post, or use the **Board Meetings** block in the editor. Both render a paginated, accessible table of your meetings with links to each agenda and minutes document. Go to **Board Meetings > Settings > Shortcode Builder** to configure columns, date formats, and more, then generate a shortcode you can copy/paste into any page or post.

= Will BoardScribe work with my theme or page builder? =

Yes. BoardScribe outputs a standard, accessible HTML table through a shortcode, a Gutenberg block, or a public REST endpoint, so it works with the block editor, the classic editor, and popular page builders. Use the `class` attribute or your theme's CSS to match the table to your site's styling.

= Can I display meetings for specific years only? =

Yes. Use the `included_years` shortcode attribute or block settings with a comma-separated list of years: `[edbs_boardscribe included_years="2023,2024"]`.

= Can I display meetings for a fiscal year that doesn't match the calendar year? =

Yes. Use the `start_date` and `end_date` shortcode attributes or block settings for an arbitrary date range: `[edbs_boardscribe start_date="2025-07-01" end_date="2026-06-30"]`.

= Can I use the shortcode or block more than once on the same page? =

Yes. Each shortcode and block instance is independently configured and rendered. You can use multiple shortcodes or block on a single page to display different years, formats, or column configurations.

= What date format is used to store meeting dates? =

Dates are stored in `Y-m-d` (ISO 8601) format, e.g. `2024-01-15`. The display format is controlled by the `held_date_format` and `not_held_date_format` shortcode attributes or block settings.

[See the PHP manual for available date formats and how to configure dates.](https://www.php.net/manual/en/datetime.format.php#refsect1-datetime.format-parameters)

= What happens to my data if I delete the plugin? =

By default, all data is preserved when the plugin is deleted. You can enable the **Delete Data on Uninstall** option in **BoardScribe > Settings** (General tab) to remove all posts and settings when the plugin is deleted. This cannot be undone.

= Is the REST API endpoint public? =

Yes. The `/wp-json/edbs/v1/boardscribe/` endpoint is intentionally public because meeting agendas and minutes are public records. No authentication is required to read them.

= Do I need Advanced Custom Fields (ACF) to use this plugin? =

No. This plugin uses native WordPress meta fields and requires no third-party plugins.

== Screenshots ==

1. Board meeting post type edit screen with fields for Meeting Date, Agenda URL, Minutes URL, and a check box to indicate if the meeting was not held.
2. Board meeting tables output on the front end by fiscal year. Tables have columns for meeting title, date, view agenda link, and view minutes link. There is a meeting that has a month and year date format (rather than a ful date), which is marked as not held. Pagination buttons are below the table.
3. Browser dev tools inspector open showing that view minutes links have an aria-label that appends the date to make links unique. Also shown in the code: title cells have a role of rowheader, and a polite aria-live container announces pagination changes.
4. Mobile view of BoardScribe table: no horizontal scrolling and column headers remain visible.
5. Multiple Board Meetings blocks in the block editor with heading blocks. Each block has a unique style controlled by detailed options within the block settings in the right sidebar.
6. BoardScribe Shortcode Builder shows a form for display settings at right and a generated shortcode at right with a copy button above a live preview.
7. BoardScribe general settings showing an option to delete all BoardScribe meeting posts and plugin settings when BoardScribe is deleted.

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
