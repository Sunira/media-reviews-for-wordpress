# Book Reviews WordPress Plugin

A comprehensive WordPress plugin to manage and display book reviews with ratings, genres, reading status, and more.

## Version 2.0.0 - Feature Complete

### New in Version 2.0:
- ✅ **Frontend Display** - Shortcode to show books on any page
- ✅ **Sorting & Filtering** - By genre, status, rating, title, author, date
- ✅ **Search** - Real-time search by title or author
- ✅ **Import/Export** - Backup and restore via CSV
- ✅ **Categories/Genres** - Organize books by genre
- ✅ **Reading Status** - Track finished, reading, want to read, abandoned

## Installation

1. **Upload the plugin:**
   - Download/copy the `book-reviews-plugin` folder
   - Upload it to `/wp-content/plugins/` directory
   - Or zip the folder and upload through WordPress admin (Plugins > Add New > Upload Plugin)

2. **Activate the plugin:**
   - Go to WordPress Admin > Plugins
   - Find "Book Reviews" in the list
   - Click "Activate"

3. **Database Update:**
   - If upgrading from v1.0, the plugin will automatically add new database columns
   - No data will be lost from existing books

## Quick Start

### Admin Usage:
1. Go to **Book Reviews > Add New**
2. Fill in title, author, rating (required)
3. Optionally add: genre, status, date read, cover image, review
4. Click **Add Book**

### Frontend Display:
1. Create or edit any page
2. Add shortcode: `[book_reviews]`
3. Publish and view!

Your visitors can now search, filter, and sort your book collection.

## Shortcode Usage

### Basic:
```
[book_reviews]
```

### With Options:
```
[book_reviews view="grid" limit="12"]
[book_reviews view="list" genre="Fiction"]
[book_reviews status="currently_reading"]
```

### Attributes:
- `view` - `grid` or `list` (default: grid)
- `limit` - Number of books (default: all, -1 for all)
- `genre` - Filter by genre
- `status` - Filter by reading status

## Features

### Admin Features
- Add, edit, delete books
- Upload cover images via WordPress Media Library
- Rate books 1-5 stars
- Categorize by genre
- Track reading status (finished, reading, want to read, abandoned)
- Record date read
- Write detailed reviews
- Import/Export via CSV

### Frontend Features
- Responsive grid or list view
- Real-time search
- Filter by genre, status, rating
- Sort by date, title, author, rating
- View toggle (grid/list)
- Mobile-friendly design
- No page reloads needed

## Import/Export

### Export Books:
1. Go to **Book Reviews > Import/Export**
2. Click **Export to CSV**
3. File downloads with all your books

### Import Books:
1. Prepare CSV with format: `Title,Author,Rating,Genre,Status,Date Read,Review,Cover URL`
2. Go to **Book Reviews > Import/Export**
3. Upload CSV file
4. Click **Import**

## Database Schema

**Table:** `{prefix}_book_reviews`

| Field           | Type         | Description           |
|-----------------|--------------|-----------------------|
| id              | mediumint(9) | Primary key           |
| title           | varchar(255) | Book title            |
| author          | varchar(255) | Author name           |
| rating          | tinyint(1)   | 1-5 stars             |
| review_text     | text         | Review                |
| cover_image_url | varchar(500) | Image URL             |
| genre           | varchar(100) | Genre/category        |
| reading_status  | varchar(20)  | Reading status        |
| date_read       | date         | Completion date       |
| date_added      | datetime     | Created timestamp     |

## Customization

### CSS Classes:
- `.book-reviews-container` - Main wrapper
- `.book-card` - Individual book
- `.book-cover` - Cover image
- `.book-rating` - Stars
- `.genre-badge` - Genre tag

### Example Custom CSS:
```css
.book-card {
    border-radius: 12px;
}

.book-rating .star.filled {
    color: #your-color;
}
```

## Requirements

- WordPress 5.0+
- PHP 7.0+
- MySQL 5.6+

## Changelog

### 2.0.0
- Frontend display with shortcode
- Search and filtering
- Import/Export
- Genre and status tracking
- Grid/list view toggle
- Database migration from v1.0

### 1.0.0
- Initial release
- Admin CRUD interface
- Star ratings
- Image uploads

## Support

Enable WP_DEBUG for detailed error messages.
Check browser console for JavaScript issues.

## License

GPL v2 or later
