<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';

// Handle Export
if (isset($_POST['export_books']) && wp_verify_nonce($_POST['export_nonce'], 'export_books')) {
    $items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_added DESC", ARRAY_A);
    
    $filename = 'media-reviews-export-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers with new field names
    fputcsv($output, array('ID', 'Media Type', 'Title', 'Creator', 'Rating', 'Category', 'Status', 'Completion Date', 'Review', 'Cover Image URL', 'Date Added'));
    
    // Add data
    foreach ($items as $item) {
        fputcsv($output, array(
            $item['id'],
            $item['media_type'] ?? 'book',
            $item['title'],
            $item['creator'] ?? $item['author'] ?? '',
            $item['rating'],
            $item['category'] ?? $item['genre'] ?? '',
            $item['status'] ?? $item['reading_status'] ?? '',
            $item['completion_date'] ?? $item['date_read'] ?? '',
            $item['review_text'],
            $item['cover_image_url'],
            $item['date_added']
        ));
    }
    
    fclose($output);
    exit;
}

// Handle Import
if (isset($_POST['import_books']) && wp_verify_nonce($_POST['import_nonce'], 'import_books')) {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
        $file = $_FILES['import_file']['tmp_name'];
        
        if (($handle = fopen($file, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $imported = 0;
            $errors = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                // Skip if not enough columns (need at least ID, Type, Title, Creator, Rating)
                if (count($data) < 5) {
                    $errors++;
                    continue;
                }
                
                // Support both old and new CSV formats
                $is_new_format = (count($data) >= 11 && isset($data[1]) && in_array($data[1], ['book', 'movie', 'music', 'game', 'tv'], true));
                
                if ($is_new_format) {
                    // New format: ID, Media Type, Title, Creator, Rating, Category, Status, Completion Date, Review, Cover URL, Date Added
                    $item_data = array(
                        'media_type' => sanitize_text_field(wp_unslash($data[1])),
                        'title' => sanitize_text_field(wp_unslash($data[2])),
                        'creator' => sanitize_text_field(wp_unslash($data[3])),
                        'rating' => intval($data[4]),
                        'category' => isset($data[5]) ? sanitize_text_field(wp_unslash($data[5])) : '',
                        'status' => isset($data[6]) ? sanitize_text_field(wp_unslash($data[6])) : 'finished',
                        'completion_date' => isset($data[7]) && !empty($data[7]) ? sanitize_text_field(wp_unslash($data[7])) : null,
                        'review_text' => isset($data[8]) ? sanitize_textarea_field(wp_unslash($data[8])) : '',
                        'cover_image_url' => isset($data[9]) ? esc_url_raw($data[9]) : ''
                    );
                } else {
                    // Old format: ID, Title, Author, Rating, Genre, Status, Date Read, Review, Cover URL, Date Added
                    $item_data = array(
                        'media_type' => 'book',
                        'title' => sanitize_text_field(wp_unslash($data[1])),
                        'creator' => sanitize_text_field(wp_unslash($data[2])),
                        'rating' => intval($data[3]),
                        'category' => isset($data[4]) ? sanitize_text_field(wp_unslash($data[4])) : '',
                        'status' => isset($data[5]) ? sanitize_text_field(wp_unslash($data[5])) : 'finished',
                        'completion_date' => isset($data[6]) && !empty($data[6]) ? sanitize_text_field(wp_unslash($data[6])) : null,
                        'review_text' => isset($data[7]) ? sanitize_textarea_field(wp_unslash($data[7])) : '',
                        'cover_image_url' => isset($data[8]) ? esc_url_raw($data[8]) : ''
                    );
                }
                
                // Validate rating
                if ($item_data['rating'] < 0 || $item_data['rating'] > 5) {
                    $errors++;
                    continue;
                }
                
                $result = $wpdb->insert($table_name, $item_data, array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'));
                
                if ($result) {
                    $imported++;
                } else {
                    $errors++;
                }
            }
            
            fclose($handle);
            
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf('Import completed! %d media items imported successfully.', $imported);
            if ($errors > 0) {
                echo sprintf(' %d errors occurred.', $errors);
            }
            echo '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Error uploading file. Please try again.</p></div>';
    }
}
?>

<div class="wrap">
    <h1>Import/Export Media Reviews</h1>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Export Media Reviews</h2>
        <p>Download all your media reviews as a CSV file. This can be used as a backup or to import into another site.</p>
        
        <form method="post">
            <?php wp_nonce_field('export_books', 'export_nonce'); ?>
            <p>
                <button type="submit" name="export_books" class="button button-primary">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Export to CSV
                </button>
            </p>
        </form>
    </div>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Import Media Reviews</h2>
        <p>Upload a CSV file to import media reviews. The importer supports both the current multi-media format and the older book-only format.</p>
        <p><code>ID, Media Type, Title, Creator, Rating, Category, Status, Completion Date, Review, Cover Image URL, Date Added</code></p>
        <p><strong>Note:</strong> Only Title, Creator, and Rating are required in the current format. For older book-only files, Author is supported instead of Creator. The ID column is ignored and new IDs will be assigned.</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_books', 'import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="import_file">CSV File</label>
                    </th>
                    <td>
                        <input type="file" 
                               name="import_file" 
                               id="import_file" 
                               accept=".csv" 
                               required>
                        <p class="description">Select a CSV file to import</p>
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="submit" name="import_books" class="button button-primary">
                    <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Import from CSV
                </button>
            </p>
        </form>
    </div>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Sample CSV Format</h2>
        <p>Here is an example of the current CSV format:</p>
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">ID,Media Type,Title,Creator,Rating,Category,Status,Completion Date,Review,Cover Image URL,Date Added
1,book,"The Great Gatsby","F. Scott Fitzgerald",5,"Fiction","finished","2024-01-15","A masterpiece of American literature","https://example.com/image.jpg","2024-01-15 10:00:00"
2,movie,"Spirited Away","Hayao Miyazaki",5,"Animation","watched","2024-02-20","Beautiful and unforgettable","","2024-02-20 14:30:00"
3,tv,"Reservation Dogs","Sterlin Harjo",5,"Comedy","finished","2024-03-12","Funny, warm, and beautifully observed","","2024-03-12 09:00:00"</pre>

        <h3>Valid Status Values:</h3>
        <ul>
            <li><code>finished</code>, <code>currently_reading</code>, <code>want_to_read</code>, <code>abandoned</code> for books</li>
            <li><code>watched</code>, <code>want_to_watch</code>, <code>abandoned</code> for movies</li>
            <li><code>listened</code>, <code>currently_listening</code>, <code>want_to_listen</code> for music</li>
            <li><code>completed</code>, <code>playing</code>, <code>want_to_play</code>, <code>abandoned</code> for games</li>
            <li><code>finished</code>, <code>watching</code>, <code>want_to_watch</code>, <code>abandoned</code> for TV shows</li>
        </ul>
    </div>
</div>
