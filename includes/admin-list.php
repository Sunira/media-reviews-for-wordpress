<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    ?>
    <div class="wrap">
        <h1>Media Reviews</h1>
        <div class="notice notice-error">
            <p><strong>Database table not found!</strong></p>
            <p>It looks like the Media Reviews plugin hasn't been properly activated. Please try:</p>
            <ol>
                <li>Deactivating the plugin</li>
                <li>Reactivating the plugin</li>
            </ol>
            <p>This will create the necessary database table.</p>
        </div>
    </div>
    <?php
    return;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_book_' . $_GET['id'])) {
        $wpdb->delete($table_name, array('id' => intval($_GET['id'])), array('%d'));
        echo '<div class="notice notice-success is-dismissible"><p>Media item deleted successfully!</p></div>';
    }
}

// Get filter from query string
$media_filter = isset($_GET['media_type']) ? sanitize_text_field($_GET['media_type']) : 'all';

// Build query based on filter
$query = "SELECT * FROM $table_name";
if ($media_filter !== 'all') {
    $query .= $wpdb->prepare(" WHERE media_type = %s", $media_filter);
}
$query .= " ORDER BY date_added DESC";

// Get items
$items = $wpdb->get_results($query);

// Get counts for each media type
$book_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE media_type = 'book'");
$movie_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE media_type = 'movie'");
$music_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE media_type = 'music'");
$game_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE media_type = 'game'");
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

// Media type icons
if (!function_exists('get_media_icon')) {
    function get_media_icon($media_type) {
        $icons = array(
            'book' => '📚',
            'movie' => '🎬',
            'music' => '🎵',
            'game' => '🎮'
        );
        return $icons[$media_type] ?? '📄';
    }
}

// Media type labels
if (!function_exists('get_media_label')) {
    function get_media_label($media_type) {
        $labels = array(
            'book' => 'Book',
            'movie' => 'Movie',
            'music' => 'Album',
            'game' => 'Game'
        );
        return $labels[$media_type] ?? $media_type;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Media Reviews</h1>
    <a href="<?php echo admin_url('admin.php?page=book-reviews-add'); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <!-- Quick Link to Shortcode Generator -->
    <div class="notice notice-info" style="margin-top: 20px; padding: 15px;">
        <p style="margin: 0; font-size: 14px;">
            <strong>🎬 Want to display your media reviews on your site?</strong>
            <a href="<?php echo admin_url('admin.php?page=book-reviews-shortcode'); ?>" class="button button-primary" style="margin-left: 10px;">
                Open Shortcode Generator →
            </a>
        </p>
    </div>

    <!-- Media Type Filter Tabs -->
    <div class="subsubsub" style="margin: 20px 0;">
        <ul style="list-style: none; margin: 0; padding: 0;">
            <li style="display: inline-block; margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=book-reviews'); ?>" 
                   class="<?php echo $media_filter === 'all' ? 'current' : ''; ?>"
                   style="padding: 5px 10px; text-decoration: none;">
                    All <span class="count">(<?php echo $total_count; ?>)</span>
                </a> |
            </li>
            <li style="display: inline-block; margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=book-reviews&media_type=book'); ?>" 
                   class="<?php echo $media_filter === 'book' ? 'current' : ''; ?>"
                   style="padding: 5px 10px; text-decoration: none;">
                    📚 Books <span class="count">(<?php echo $book_count; ?>)</span>
                </a> |
            </li>
            <li style="display: inline-block; margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=book-reviews&media_type=movie'); ?>" 
                   class="<?php echo $media_filter === 'movie' ? 'current' : ''; ?>"
                   style="padding: 5px 10px; text-decoration: none;">
                    🎬 Movies <span class="count">(<?php echo $movie_count; ?>)</span>
                </a> |
            </li>
            <li style="display: inline-block; margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=book-reviews&media_type=music'); ?>" 
                   class="<?php echo $media_filter === 'music' ? 'current' : ''; ?>"
                   style="padding: 5px 10px; text-decoration: none;">
                    🎵 Albums <span class="count">(<?php echo $music_count; ?>)</span>
                </a> |
            </li>
            <li style="display: inline-block; margin: 0;">
                <a href="<?php echo admin_url('admin.php?page=book-reviews&media_type=game'); ?>" 
                   class="<?php echo $media_filter === 'game' ? 'current' : ''; ?>"
                   style="padding: 5px 10px; text-decoration: none;">
                    🎮 Games <span class="count">(<?php echo $game_count; ?>)</span>
                </a>
            </li>
        </ul>
    </div>

    <?php if (empty($items)): ?>
        <div class="notice notice-info">
            <p>No items found. <a href="<?php echo admin_url('admin.php?page=book-reviews-add'); ?>">Add your first item</a>!</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;">Type</th>
                    <th style="width: 80px;">Cover</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Category</th>
                    <th style="width: 120px;">Rating</th>
                    <th>Status</th>
                    <th>Review</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php 
                    $media_type = $item->media_type ?? 'book';
                    $creator = $item->creator ?? $item->author ?? '';
                    $category = $item->category ?? $item->genre ?? '';
                    $status_val = $item->status ?? $item->reading_status ?? '';
                    ?>
                    <tr>
                        <td style="text-align: center; font-size: 24px;">
                            <span title="<?php echo esc_attr(ucfirst(get_media_label($media_type))); ?>">
                                <?php echo get_media_icon($media_type); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item->cover_image_url): ?>
                                <img src="<?php echo esc_url($item->cover_image_url); ?>" 
                                     alt="<?php echo esc_attr($item->title); ?>" 
                                     style="max-width: 60px; height: auto;">
                            <?php else: ?>
                                <span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ccc;"></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($item->title); ?></strong></td>
                        <td><?php echo esc_html($creator); ?></td>
                        <td><?php echo esc_html($category ?: '—'); ?></td>
                        <td>
                            <?php if ($item->rating > 0): ?>
                                <div class="book-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="dashicons dashicons-star-<?php echo $i <= $item->rating ? 'filled' : 'empty'; ?>" style="color: <?php echo $i <= $item->rating ? '#FFBB00' : '#ddd'; ?>;"></span>
                                    <?php endfor; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999; font-style: italic;">Not rated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="book-status-badge" style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border-radius: 3px; font-size: 12px; font-weight: 500;">
                                <?php echo esc_html(str_replace('_', ' ', ucwords($status_val, '_'))); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $review_excerpt = wp_trim_words($item->review_text, 10, '...');
                            echo esc_html($review_excerpt);
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=book-reviews-add&action=edit&id=' . $item->id); ?>" 
                               class="button button-small">Edit</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=book-reviews&action=delete&id=' . $item->id), 'delete_book_' . $item->id); ?>" 
                               class="button button-small" 
                               onclick="return confirm('Are you sure you want to delete this item?');" 
                               style="color: #b32d2e;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.subsubsub a.current {
    font-weight: 600;
    color: #000;
}
.book-rating .dashicons {
    font-size: 16px;
}
</style>
