# Sikora TagDiv Post Views Sorter

**Version:** 1.0.1
**Author:** [Sikora Collective](https://sikoracollective.com/)
**Contributors:** sikoracollective
**Tags:** tagdiv, newspaper, post views, sortable columns, admin
**Requires at least:** 5.0
**Tested up to:** 6.8
**Requires PHP:** 7.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Makes the TagDiv Newspaper theme's "Views" column on the WordPress admin Posts page sortable.

## Description

### Theme compatibility

This plugin works with the **[Newspaper theme by TagDiv](https://tagdiv.com/newspaper/)**.

The Newspaper theme adds a **Views** column to the admin Posts page (**Posts → All Posts**) and saves each post's view count in the post meta field `post_views_count`. The theme does not let you sort by that column. This plugin adds sorting.

If the Newspaper theme (or its Views column) is not active, the plugin does nothing.

### What it does

- Makes the **Views** column header on the admin Posts page clickable for sorting, like the Title and Date columns.
- Sorts by view count in **descending order** (most viewed first) on the first click. Click again to switch to ascending.
- Treats posts with no recorded view count as 0 views, so they stay in the list.
- Breaks ties between posts with the same view count by publish date.
- Works with the Posts page's existing filters, search, and pagination.

### What it does not do

- **It does not change the database.** The plugin only reads the theme's existing view counts. It creates no options, tables, or post meta, and it has no activation or uninstall routines.
- It does not count views, change view counts, or change how the Views column looks.
- It does not change anything on the public side of the site. It only runs in the WordPress admin.

## Installation

1. In the WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Choose `sikora-tagdiv-post-views-sorter.zip` and click **Install Now**.
3. Click **Activate Plugin**.
4. Go to **Posts → All Posts** and click the **Views** column header.

To remove it, deactivate and delete the plugin from the **Plugins** page. There is no data to clean up.

## Frequently Asked Questions

### Does this plugin require the TagDiv Newspaper theme?

Yes. It sorts the Views column that the Newspaper theme adds. Without that theme (or its Views column), the plugin has no effect.

### Does this plugin write to the database?

No. It only reads the existing `post_views_count` post meta values. It creates no options, tables, or post meta.

### Does it count page views?

No. View counting remains handled by the TagDiv Newspaper theme. This plugin only makes the Views column sortable.

### Will it affect the front end of my site?

No. It only runs in the WordPress admin on the Posts list screen.

## Changelog

### 1.0.1

- Updated plugin display name to Sikora TagDiv Post Views Sorter.

### 1.0.0

- Initial release.

## Upgrade Notice

### 1.0.1

Plugin display name update. No functional changes.

## How it works (technical)

- On the Posts list screen (`edit.php`, post type `post`), the plugin reads the final list of columns (after every column filter has run) and finds the theme's Views column. It looks for the column key `td_post_views` first, then a column labeled "Views", then a column whose key has a "view"/"views" segment (for example `post_views`).
- It registers that column through the `manage_edit-post_sortable_columns` filter with the orderby value `sikora_td_views`, set to sort descending first.
- When the main admin query is sorted by `sikora_td_views`, a `posts_orderby` filter orders posts by the numeric value of the `post_views_count` meta field. It reads the value with a read-only subquery, so posts with no count are kept and sorted as 0.

## Requirements

- WordPress 5.0 or later
- PHP 7.0 or later
- TagDiv Newspaper theme

## Files

- `sikora-tagdiv-post-views-sorter.php` - The plugin (documented inline).
- `readme.txt` - Source of truth for documentation.
- `README.md` - Generated Markdown readme. Do not edit; run `./build.sh`.
- `build.sh` - Regenerates `README.md` and builds the installable zip.

## License

GPLv2 or later
