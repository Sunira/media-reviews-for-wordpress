# Media Reviews WordPress Plugin

A WordPress plugin for managing and displaying reviews for books, movies, music albums, and video games.

## Version

Current version: `3.0.0`

## Overview

Media Reviews adds a custom admin area in WordPress where you can store review entries with ratings, notes, cover art, categories, statuses, and dates. It also includes frontend shortcodes so you can publish your collection on any page or post.

The plugin began as a book review plugin and still keeps backward compatibility with older book-only data and shortcodes.

## Features

### Admin

- Manage reviews for:
  - Books
  - Movies
  - Music albums
  - Video games
- Add, edit, and delete media entries
- Upload or select cover/poster/artwork images from the WordPress Media Library
- Rate items from 0 to 5 stars
- Store review text, category/genre, status, and date information
- Use media-specific field labels:
  - Book: Author, Reading Status
  - Movie: Director, Watch Status
  - Music: Artist, Listen Status
  - Game: Developer, Play Status
- Filter the admin list by media type
- Generate frontend shortcodes from a built-in shortcode generator
- Import and export data as CSV

### Frontend

- Display reviews with `[media_reviews]`
- Backward-compatible shortcode support with `[book_reviews]`
- Search by title or creator
- Filter by:
  - Media type
  - Category
  - Status
  - Minimum rating
- Sort by:
  - Date added
  - Title
  - Rating
- Responsive card-based layout
- Optional filter/search controls

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`, or upload a zip through WordPress Admin.
2. Activate the plugin in **Plugins**.
3. Open **Media Reviews** in the WordPress admin.

On activation, the plugin creates the `{prefix}_book_reviews` table automatically.

## Quick Start

### Add reviews

1. Go to **Media Reviews > Add New**
2. Choose a media type
3. Enter the title, creator, and rating
4. Optionally add category, status, date, image, and review text
5. Save the entry

### Display reviews

1. Create or edit a page or post
2. Insert a shortcode such as:

```text
[media_reviews]
```

3. Publish the page

## Shortcodes

### Basic

```text
[media_reviews]
```

### Backward-compatible alias

```text
[book_reviews]
```

### Examples

```text
[media_reviews media_type="book"]
[media_reviews media_type="movie,music" limit="12"]
[media_reviews view="list" category="Fiction"]
[media_reviews status="finished,abandoned"]
[media_reviews show_filters="false"]
```

### Supported attributes

- `media_type`  
  Comma-separated media types. Supported values: `book`, `movie`, `music`, `game`. Default: `all`

- `view`  
  Display mode. Supported values: `grid`, `list`. Default: `grid`

- `limit`  
  Maximum number of items to load. Default: all items

- `category`  
  Filter by category

- `genre`  
  Legacy alias for `category`

- `status`  
  Filter by one or more comma-separated statuses

- `show_filters`  
  Show or hide the search/filter UI. Supported values: `true`, `false`. Default: `true`

## Status values by media type

### Books

- `finished`
- `currently_reading`
- `want_to_read`
- `abandoned`

### Movies

- `watched`
- `want_to_watch`
- `abandoned`

### Music

- `listened`
- `currently_listening`
- `want_to_listen`

### Games

- `completed`
- `playing`
- `want_to_play`
- `abandoned`

## Admin Pages

The plugin adds these WordPress admin screens:

- `Media Reviews > All Media`
- `Media Reviews > Add New`
- `Media Reviews > Shortcode Generator`
- `Media Reviews > Import/Export`

## Import and Export

### Export

Export downloads all stored entries as CSV.

Current export columns:

```text
ID,Media Type,Title,Creator,Rating,Category,Status,Completion Date,Review,Cover Image URL,Date Added
```

### Import

The importer supports both:

- The current multi-media CSV format
- The older book-only CSV format

Required data for a valid row:

- Title
- Creator (or Author in old format)
- Rating

Ratings must be between `0` and `5`.

## Data Storage

Database table:

```text
{prefix}_book_reviews
```

Current schema fields:

| Field | Description |
| --- | --- |
| `id` | Primary key |
| `media_type` | `book`, `movie`, `music`, or `game` |
| `title` | Media title |
| `creator` | Author, director, artist, or developer |
| `rating` | Integer from 0 to 5 |
| `review_text` | Review content |
| `cover_image_url` | Cover/poster/artwork URL |
| `category` | Category or genre |
| `status` | Media-specific status |
| `completion_date` | Related date for the item |
| `date_added` | Timestamp when the row was created |

## Backward Compatibility

Version `3.0.0` includes migration logic for older installations. It supports older data structures and naming, including:

- `author` -> `creator`
- `genre` -> `category`
- `reading_status` -> `status`
- `date_read` -> `completion_date`
- `[book_reviews]` shortcode -> `[media_reviews]`

## Requirements

- WordPress 5.0+
- PHP 7.0+
- MySQL 5.6+

## Changelog

### 3.0.0

- Expanded from book reviews to multi-media reviews
- Added support for books, movies, music albums, and games
- Added media-type-aware admin forms and statuses
- Added `[media_reviews]` shortcode
- Kept backward compatibility for old shortcodes and old data formats

### 2.0.0

- Added frontend shortcode display
- Added search, filtering, and sorting
- Added CSV import/export
- Added genre/category and reading status support

### 1.0.0

- Initial book review management features

## Notes

- The plugin name in WordPress is **Media Reviews**.
- Some internal filenames still use the older `book_reviews` naming for backward compatibility.

## License

GPL v2 or later
