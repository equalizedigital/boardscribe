# Equalize Digital Meeting Minutes

**Contributors**: Equalize Digital
**Requires at least**: 5.0
**Tested up to**: 6.6
**Stable tag**: 1.0.0
**License**: GPLv2 or later
**License URI**: https://www.gnu.org/licenses/gpl-2.0.html

## Description

The **Equalize Digital Meeting Minutes** plugin allows you to manage and display meeting minutes on your WordPress website. It registers a custom post type for storing meeting minutes and provides a shortcode to display them in a paginated table. It also integrates with ACF for adding meta fields and creates a custom REST API endpoint for retrieving meeting minute entries.

### Features
- Custom post type for meeting minutes.
- Non-hierarchical taxonomy (tags) for categorising meeting minutes.
- ACF (Advanced Custom Fields) integration for additional meeting metadata (date, agenda URL, notes URL).
- REST API route for fetching meeting minutes.
- A shortcode to display meeting minutes in a table with pagination.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/equalize-digital-meeting-minutes` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the provided shortcode `[edmm_meeting_minutes]` to display meeting minutes on any page or post.

## Shortcode

The plugin provides the `[edmm_meeting_minutes]` shortcode, which you can use to display meeting minutes in a paginated table. The shortcode comes with several attributes to customize the output.

### Shortcode Example

```php
[edmm_meeting_minutes included_years="2022,2023" hide_title="false" posts_per_page="5"]
```

### Shortcode Attributes
- `included_years`: Comma-separated list of years to include in the table (e.g., "2022,2023"). Default: empty (shows all years).
- `tags`: Comma-separated list of meeting tag slugs to filter by (e.g., "board,annual"). Default: empty (shows all tags).
- `hide_title`: Hides the "Title" column if set to `true`. Default: `false`.
- `hide_date`: Hides the "Date" column if set to `true`. Default: `false`.
- `hide_agenda`: Hides the "Agenda" column if set to `true`. Default: `false`.
- `hide_notes`: Hides the "Notes" column if set to `true`. Default: `false`.
- `hide_tags`: Hides the "Tags" column if set to `true`. Default: `false`.
- `tags_label`: Custom label for the "Tags" column header (also used as the `data-label` on mobile). Default: `Tags`.
- `held_date_format`: The format for dates of meetings that were held. Uses standard PHP date format (e.g., `Y/m/d`). Default: `Y/m/d`.
- `not_held_date_format`: The format for dates of meetings that were not held. Uses standard PHP date format (e.g., `Y/m`). Default: `Y/m`.
- `class`: Adds a custom CSS class to the `<table>` element. Default: empty.
- `posts_per_page`: The number of meeting entries to display per page. Default: `20`.

## REST API
The plugin exposes a custom REST API endpoint for retrieving meeting minutes:
- Endpoint: `/wp-json/edmm/v1/meeting-minutes/`
- Parameters:
  - `page`: Page number for pagination.
  - `posts_per_page`: Number of posts per page.
  - `included_years`: Comma-separated list of years.
  - `tags`: Comma-separated list of meeting tag slugs to filter by.
  - `held_date_format`: Date format for meetings that were held.
  - `not_held_date_format`: Date format for meetings that were not held.

### Example API Call
```
GET https://yourdomain.com/wp-json/edmm/v1/meeting-minutes/?included_years=2023&posts_per_page=10&page=1
```

## Taxonomy

The plugin registers a **Meeting Tags** taxonomy (`edmm_meeting_tag`) for the Meeting Minutes post type. Tags are non-hierarchical and can be managed from the Meeting Minutes admin screen. Use them to group or categorise meetings (e.g., "Board", "Annual", "Special Session").

Tag slugs are used when filtering via the `tags` shortcode attribute or the `tags` REST API parameter.

## Meta Fields
If the Advanced Custom Fields (ACF) plugin is active, the plugin adds the following fields to the Meeting Minutes custom post type:

- **Meeting Date**: A required date picker field to enter the date of the meeting.
- **Meeting Agenda URL**: A URL field for adding a link to the meeting agenda.
- **Meeting Notes URL**: A URL field for adding a link to the meeting notes.
- **Meeting Not Held**: A true/false field to mark whether the meeting was held.

## License
This plugin is licensed under the GPLv2 or later.
