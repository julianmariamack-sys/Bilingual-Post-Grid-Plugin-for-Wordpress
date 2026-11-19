# Bilingual Post Grid for TranslatePress

A WordPress plugin for responsive, paginated post grids. It includes a shortcode and a dynamic Gutenberg block, and can use TranslatePress to display translated post titles, excerpts, category labels, and interface text.

## Features

- Responsive card grid: three columns on desktop by default, with tablet and mobile layouts.
- Nine posts per page by default (three columns by three rows).
- Featured image, category, title, 20-word excerpt, Read More button, and publication date.
- Pagination. Multiple shortcode grids on the same page have independent pagination.
- Optional shortcode-specific category, posts-per-page, and sorting settings.
- Global appearance controls for all grids.
- TranslatePress-aware text and the French **En Savoir Plus** button label.
- Dynamic Gutenberg block.
- No archive heading is displayed by default.

## Requirements

- WordPress 6.2 or newer.
- PHP 7.4 or newer.
- TranslatePress is needed for multilingual translation functionality. The grid can still display posts without TranslatePress, but it will not provide TranslatePress translations.

## Installation

1. Download the plugin ZIP file.
2. In the WordPress dashboard, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the Bilingual Post Grid ZIP file and choose **Install Now**.
4. Select **Activate Plugin**.
5. Open the **Bilingual Post Grid** menu in the dashboard to configure the grid.

To update an existing installation, keep a backup of your site, then upload and install the newer plugin ZIP through the WordPress plugin installer. If WordPress does not offer an overwrite option, deactivate and delete the old plugin before uploading the new ZIP. Deleting a plugin can remove its files, so do not delete it unless you have a backup and are comfortable reinstalling it. Plugin settings are stored in the WordPress database.

## Configuration page

Open **Dashboard → Bilingual Post Grid**. The settings page contains the global options used by all grids, including:

- Included post categories (the default category filter).
- Default post order: modified date or original publication date, newest first.
- Row and column spacing.
- Card minimum height, border, radius, and optional shadow.
- Title, excerpt, category, and date typography.
- Text-area padding and title/button spacing.
- Read More button dimensions, font size, and font weight.

These appearance settings are global. A shortcode does not create a separate visual design; every grid uses the same configured card styling.

The bottom of the configuration page also includes copyable shortcode examples.

## Shortcode use

Add a **Shortcode** block to a page or post and paste in:

```text
[bilingual_post_grid]
```

With no attributes, the shortcode uses the dashboard defaults: three columns, three rows (nine posts), the global category selection, and the global sorting choice.

### Multiple independent grids

You can add multiple shortcode instances to the same page. For example:

```text
[bilingual_post_grid category="news" posts_per_page="9" sort_by="modified"]

[bilingual_post_grid category="reviews" posts_per_page="6" sort_by="date"]

[bilingual_post_grid category="tutorials" posts_per_page="12" sort_by="modified"]
```

Each grid has its own:

- Category filter.
- Number of posts per page.
- Sorting choice.
- Pagination state.

Changing the page in one grid does not change the page selected in the other grids. All grids continue to share the global appearance settings configured in the dashboard.

### Shortcode attributes

| Attribute | Purpose | Values / default |
|---|---|---|
| `category` | Filter standard posts by category slug. | Omit to use the dashboard's included categories; accepts one slug or comma-separated slugs. |
| `posts_per_page` | Number of posts shown in this grid before pagination. | 1–100; omitted value is `columns` × `rows` (9 by default). |
| `sort_by` | Choose the date used to order posts, newest first. | `modified` or `date`; omitted/invalid values use the dashboard setting. |
| `columns` | Number of columns. | 1–9; default `3`. |
| `rows` | Number of rows per page when `posts_per_page` is not supplied. | 1–9; default `3`. |
| `excerpt_words` | Maximum excerpt word count. | 1–100; default `20`. |
| `show_image` | Show featured images. | `true` or `false`; default `true`. |
| `show_excerpt` | Show excerpts. | `true` or `false`; default `true`. |
| `show_read_more` | Show the Read More button. | `true` or `false`; default `true`. |
| `show_category` | Show the category label. | `true` or `false`; default `true`. |
| `show_date` | Show the publication date. | `true` or `false`; default `true`. |
| `post_type` | Select a public post type. | Post type slug; default `post`. |
| `heading` | Optional heading displayed above that grid. | Text; default empty (no heading). |

Examples:

```text
[bilingual_post_grid columns="3" rows="3" excerpt_words="20"]
[bilingual_post_grid category="news,announcements" posts_per_page="6" sort_by="date"]
[bilingual_post_grid category="reviews" posts_per_page="12" show_date="false"]
```

**Category behaviour:** If `category` is supplied for a shortcode, it overrides the dashboard's global included-category selection for that grid. If it is omitted, the global category selection applies. The category filter applies to standard WordPress posts, not arbitrary custom post types.

**Sorting behaviour:** `sort_by="modified"` orders by the last modified date. `sort_by="date"` orders by the original publication date. Both sort newest first. If the attribute is omitted, the grid uses the default selected in the dashboard.

**Posts per page:** `posts_per_page` takes precedence over the `columns` × `rows` calculation. For example, `columns="3" rows="2"` displays six posts per page unless `posts_per_page` is also supplied.

## Pagination and caching

Each shortcode instance uses a separate query-string parameter for pagination, such as `bpg_page_1`, `bpg_page_2`, and so on, based on the order in which the grids are rendered on the page. If a caching plugin or CDN caches query-string URLs, configure it to preserve these parameters when varying or serving cached pages. Otherwise, visitors may see stale or incorrect pagination results.

## Gutenberg block

1. Edit a page or post in the WordPress block editor.
2. Add the **Bilingual Post Grid** block.
3. Use the block sidebar to set columns, rows, excerpt length, the optional heading, and display toggles for images, categories, excerpts, Read More buttons, and dates.
4. The block uses the same global appearance settings as shortcode grids.

The shortcode-specific `category`, `posts_per_page`, and `sort_by` attributes described above are intended for shortcode instances.

## TranslatePress

When TranslatePress is active, the plugin uses its `trp_translate()` function for titles, excerpts, category labels, and interface text in the active language. TranslatePress must have translations available (or automatic translation configured); this plugin does not create translations itself.

For generated excerpts, the plugin translates the full formatted source text before stripping markup and limiting the result to the configured word count. If a particular excerpt has no translation, add it through TranslatePress or use its configured automatic translation service.

The Read More button displays **En Savoir Plus** when the active language is French. In other languages it uses **Read More**, passed through TranslatePress' normal string translation lookup.

## Support and troubleshooting

- **No posts appear:** Check that posts are published, that the selected category slug is correct, and that the dashboard's global category selection is not excluding the posts. A shortcode `category` attribute overrides that global category selection for its own grid.
- **Pagination appears to affect the wrong grid:** Confirm that the page contains the current plugin version and that page caching preserves the `bpg_page_1`, `bpg_page_2`, etc. query parameters.
- **Translations are missing:** Confirm TranslatePress is active and that translations exist for the source text.
- **Styling changes affect every grid:** This is expected. Appearance settings are global by design.

## Version

Current plugin version: **1.9.6**.
