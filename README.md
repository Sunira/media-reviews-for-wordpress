# Media Reviews for WordPress

A WordPress plugin for curating, importing, and displaying books, movies, TV shows, music albums, and video games with a polished admin workflow, block support, and responsive frontend layouts.

## Version

Current version: `3.4.0`

## Overview

Media Reviews adds a dedicated WordPress workspace for building a personal media library with ratings, notes, cover art, categories, statuses, and dates. It includes API-assisted import tools, frontend shortcodes, and a Gutenberg block so you can publish either a featured item or a filterable collection anywhere on your site.

The plugin began as a book review plugin and still keeps backward compatibility with older book-only data and shortcodes.

## How It Works

At a high level, the plugin has three main surfaces:

1. **Admin management**
   You manage media items in WordPress admin through custom plugin pages for listing items, adding/editing items, import/export, shortcode generation, API-powered import, and Amazon bookmarklet setup.

2. **Data storage**
   The plugin stores media reviews in a custom database table named `{prefix}_book_reviews`. Each row represents one media item with a media type, title, creator, rating, review text, image URL, category, status, and completion date.

3. **Frontend display**
   The `[media_reviews]` shortcode and `Media Reviews` block read from the custom table and render searchable, filterable, sortable media views on the public site. The public display uses only saved WordPress data and image URLs.

## Project Structure

These are the most important moving parts in the codebase:

- [book-reviews.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/book-reviews.php)
  The main plugin bootstrap. It registers activation hooks, admin menus, scripts, and shortcodes.

- [includes/admin-form.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/admin-form.php)
  Handles the Add/Edit Media screen, validation, inserts/updates, and now Amazon-prefilled fields.

- [includes/admin-list.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/admin-list.php)
  Renders the admin listing table and media-type tabs.

- [includes/frontend-helpers.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/frontend-helpers.php)
  Contains the shared query and rendering helpers used by both the shortcode and the Gutenberg block.

- [includes/import-export.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/import-export.php)
  Handles CSV import/export.

- [includes/shortcode-generator.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/shortcode-generator.php)
  Builds shortcode examples interactively in wp-admin.

- [includes/amazon-import.php](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/includes/amazon-import.php)
  Handles Amazon bookmarklet settings, signed payload verification, API-based lookup/import, image sideloading, and the admin handoff flow.

- [assets/js/admin-script.js](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/assets/js/admin-script.js)
  Powers admin-side interactions like media upload and bookmarklet copy actions.

- [assets/js/frontend-script.js](/Users/sunira/Projects/ClaudeCode/book-reviews-plugin/assets/js/frontend-script.js)
  Powers frontend filtering, sorting, and card interactions.

## Data Flow

### Manual entry flow

1. A user opens **Add New Media**
2. The admin form collects the media fields
3. The form writes a row into `{prefix}_book_reviews`
4. The admin list and shortcode both read from that same table

### CSV import flow

1. A user uploads a CSV on the Import/Export screen
2. The plugin parses each row into normalized media fields
3. Valid rows are inserted into the custom table
4. Imported items appear immediately in admin and shortcode output

### Amazon bookmarklet flow

1. A user clicks the bookmarklet on an Amazon product page
2. The bookmarklet extracts basic metadata in the browser
3. It signs the payload with the shared secret and sends it to a WordPress admin-post endpoint
4. WordPress verifies the signature and stores the payload temporarily in a transient
5. The plugin opens **Add New Media** with the item prefilled for review before save

### API import flow

1. A user opens **Media Reviews > Add New**
2. The plugin searches public APIs server-side from wp-admin
3. The user chooses a result to import
4. The plugin downloads the selected image into the local WordPress Media Library
5. The plugin opens **Add New Media** with normalized local data prefilled for review before save

## Media Model

Every media item uses the same base storage model, with labels and status values changing by `media_type`.

- `book`
  Creator is treated as author, with reading-oriented statuses.

- `movie`
  Creator is treated as director, with watch-oriented statuses.

- `tv`
  Creator is treated as show creator, with TV watch-oriented statuses.

- `music`
  Creator is treated as artist, with listening-oriented statuses.

- `game`
  Creator is treated as developer, with play-oriented statuses.

## Features

### Admin

- Manage reviews for:
  - Books
  - Movies
  - TV shows
  - Music albums
  - Video games
- Add, edit, and delete media entries
- Upload or select cover/poster/artwork images from the WordPress Media Library
- Rate items from 0 to 5 stars
- Store review text, category/genre, status, and date information
- Use media-specific field labels:
  - Book: Author, Reading Status
  - Movie: Director, Watch Status
  - TV Show: Creator, Watch Status
  - Music: Artist, Listen Status
  - Game: Developer, Play Status
- Filter the admin list by media type
- Generate frontend shortcodes from a built-in shortcode generator
- Import and export data as CSV
- Import basic Amazon product metadata with a signed bookmarklet handoff
- Search public APIs for books, movies, TV shows, albums, and games, then import results into local WordPress data

### Frontend

- Display reviews with `[media_reviews]`
- Backward-compatible shortcode support with `[book_reviews]`
- Insert a `Media Reviews` Gutenberg block in single-item or collection mode
- Use a compact single-line command bar for search, filters, rating, and sorting
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
2. Insert either the `Media Reviews` block or a shortcode such as:

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
[media_reviews media_type="movie,tv,music" limit="12"]
[media_reviews view="list" category="Fiction"]
[media_reviews status="finished,abandoned"]
[media_reviews show_filters="false"]
```

### Supported attributes

- `media_type`
  Comma-separated media types. Supported values: `book`, `movie`, `tv`, `music`, `game`. Default: `all`

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

### TV Shows

- `finished`
- `watching`
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
- `Media Reviews > Amazon Bookmarklet`
- `Media Reviews > Settings`

## API Import

The plugin includes an admin-only API import workflow inside **Media Reviews > Add New**. The right-hand API Lookup panel searches public APIs and imports the selected result into the add form before save.

### Providers used

- Open Library Search API and Covers API for books
- TMDb for movies and TV shows
- MusicBrainz and Cover Art Archive for music albums
- RAWG for video games

### How it behaves

- API calls happen only during search/import in wp-admin
- Imported images are downloaded into the WordPress Media Library
- The plugin stores the resulting local image URL in `cover_image_url`
- The frontend shortcode and block continue rendering only from your local WordPress data

### Required API keys

- TMDb API key, required for movie and TV show lookups
- RAWG API key

Open Library, MusicBrainz, and Cover Art Archive do not require user-managed keys in this plugin version.

## Amazon Bookmarklet Import

The plugin includes a signed Amazon bookmarklet flow for quickly starting a media entry from an Amazon product page.

### Setup

1. Go to **Media Reviews > Amazon Bookmarklet**
2. Confirm the WordPress handoff URL
3. Drag the generated bookmarklet to your bookmarks bar, or create a bookmark manually with the generated code

### Usage

1. Open an Amazon product page for a book, movie, TV show, album, or game
2. Click the bookmarklet
3. The plugin opens **Add New Media** with imported fields prefilled
4. Review the entry and complete rating, status, category, and review text manually

### Imported fields

- `media_type`
- `title`
- `creator` when Amazon exposes one
- `cover_image_url` when available
- `source_url` for review context on the add form

The Amazon source URL is shown on the add form for reference only and is not stored in the plugin database in v1.

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
| `media_type` | `book`, `movie`, `tv`, `music`, or `game` |
| `title` | Media title |
| `creator` | Author, director, show creator, artist, or developer |
| `rating` | Integer from 0 to 5 |
| `review_text` | Review content |
| `cover_image_url` | Cover/poster/artwork URL |
| `category` | Category or genre |
| `status` | Media-specific status |
| `completion_date` | Related date for the item |
| `date_added` | Timestamp when the row was created |

## Security Notes

The Amazon bookmarklet import is intentionally limited:

- It does not auto-save imported items.
- It requires a signed payload generated from the shared secret.
- It still relies on normal WordPress admin authentication and permissions before a user can save anything.
- Imported Amazon source URLs are shown on the form for reference only and are not stored in the database in v1.

## Backward Compatibility

Version `3.4.0` includes migration logic and compatibility support for older installations. It supports older data structures and naming, including:

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

### 3.4.0

- Added TV show support across admin forms, API lookup, frontend filters, shortcode generation, and block controls
- Added TMDb TV search integration for API imports
- Added TV statuses and labels for collection displays
- Refined the frontend filter interface into a compact command bar with unified search and dropdown styling

### 3.3.0

- Added a dynamic Gutenberg block for single-item and collection display
- Added a shared frontend rendering/query layer for the shortcode and block
- Added block-editor search for saved media items and collection filtering by completion date preset

### 3.2.0

- Added an admin-only API import workflow for books, movies, music albums, and games
- Added local image sideloading into the WordPress Media Library during API import
- Added static frontend attribution for the metadata and artwork providers used during import

### 3.1.0

- Added an Amazon bookmarklet import flow with signed handoff into the Add New Media screen
- Added an admin settings page for bookmarklet setup and secret rotation
- Added Amazon-prefilled form support for title, creator, cover image, media type, and source URL

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
