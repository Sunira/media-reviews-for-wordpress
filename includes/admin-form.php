<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';

$allowed_media_types = array('book', 'movie', 'music', 'game', 'tv');
$prefill_payload = null;
$prefill_key = isset($_GET['import_prefill']) ? sanitize_key(wp_unslash($_GET['import_prefill'])) : '';
$import_source = isset($_GET['import_source']) ? sanitize_key(wp_unslash($_GET['import_source'])) : '';
$reference_source_url = '';
$show_import_notice = false;
$success_notice = '';

// Get media item data if editing
$item = null;
$is_edit = false;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id));
    $is_edit = true;
}

if (!$is_edit) {
    if (!empty($prefill_key)) {
        $prefill_payload = book_reviews_get_import_prefill($prefill_key);
    } else {
        $legacy_prefill_key = isset($_GET['amazon_prefill']) ? sanitize_key(wp_unslash($_GET['amazon_prefill'])) : '';
        if (!empty($legacy_prefill_key)) {
            $legacy_prefill = get_transient('book_reviews_amazon_prefill_' . $legacy_prefill_key);
            if (is_array($legacy_prefill)) {
                $prefill_payload = array(
                    'source' => 'amazon',
                    'data' => book_reviews_normalize_prefill_data($legacy_prefill),
                );
                $prefill_key = $legacy_prefill_key;
            }
        }
    }

    if (is_array($prefill_payload) && !empty($prefill_payload['data'])) {
        $show_import_notice = true;
        $import_source = !empty($prefill_payload['source']) ? sanitize_key($prefill_payload['source']) : 'api';
        $reference_source_url = !empty($prefill_payload['data']['source_url']) ? esc_url_raw($prefill_payload['data']['source_url']) : '';
    }
}

if (isset($_GET['success'])) {
    $success = sanitize_key(wp_unslash($_GET['success']));
    if ($success === 'created') {
        $success_notice = 'Media item added successfully!';
    } elseif ($success === 'updated') {
        $success_notice = 'Media item updated successfully!';
    }
}

$flash_state = get_transient('book_reviews_form_state_' . get_current_user_id());
if (is_array($flash_state)) {
    delete_transient('book_reviews_form_state_' . get_current_user_id());
}

$lookup_state = get_transient('book_reviews_api_lookup_state_' . get_current_user_id());
if (is_array($lookup_state)) {
    delete_transient('book_reviews_api_lookup_state_' . get_current_user_id());
}

$form_values = array(
    'media_type' => $item->media_type ?? 'book',
    'title' => $item->title ?? '',
    'creator' => $item->creator ?? $item->author ?? '',
    'rating' => isset($item->rating) ? intval($item->rating) : 0,
    'review_text' => $item->review_text ?? '',
    'cover_image_url' => $item->cover_image_url ?? '',
    'category' => $item->category ?? $item->genre ?? '',
    'status' => $item->status ?? $item->reading_status ?? 'finished',
    'completion_date' => $item->completion_date ?? $item->date_read ?? '',
    'source_url' => $reference_source_url,
);

if ($prefill_payload && !empty($prefill_payload['data'])) {
    $prefill_data = $prefill_payload['data'];
    $form_values['media_type'] = in_array($prefill_data['media_type'], $allowed_media_types, true) ? $prefill_data['media_type'] : 'book';
    $form_values['title'] = $prefill_data['title'] ?? '';
    $form_values['creator'] = $prefill_data['creator'] ?? '';
    $form_values['cover_image_url'] = $prefill_data['cover_image_url'] ?? '';
    $form_values['category'] = $prefill_data['category'] ?? '';
    $form_values['source_url'] = $reference_source_url;
}

if (is_array($flash_state)) {
    if (!empty($flash_state['error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($flash_state['error']) . '</p></div>';
    }

    if (!empty($flash_state['form_values']) && is_array($flash_state['form_values'])) {
        $form_values = wp_parse_args($flash_state['form_values'], $form_values);
    }

    if (!empty($flash_state['prefill_key'])) {
        $prefill_key = sanitize_key($flash_state['prefill_key']);
    }

    if (!empty($flash_state['import_source'])) {
        $import_source = sanitize_key($flash_state['import_source']);
    }

    $show_import_notice = !empty($flash_state['imported']);
}

$lookup_media_type = isset($_GET['lookup_media_type']) ? sanitize_text_field(wp_unslash($_GET['lookup_media_type'])) : $form_values['media_type'];
$lookup_query = isset($_GET['lookup_query']) ? sanitize_text_field(wp_unslash($_GET['lookup_query'])) : '';
$lookup_results = array();
$lookup_error = '';

if (is_array($lookup_state) && !empty($lookup_state['error'])) {
    $lookup_error = $lookup_state['error'];
}

if (!empty($lookup_query)) {
    $search_results = book_reviews_search_api_provider($lookup_media_type, $lookup_query);
    if (is_wp_error($search_results)) {
        $lookup_error = $search_results->get_error_message();
    } else {
        $lookup_results = $search_results;
    }
}

?>

<div class="wrap">
    <h1><?php echo $is_edit ? 'Edit Media Item' : 'Add New Media Item'; ?></h1>

    <?php if (!empty($success_notice)): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php echo esc_html($success_notice); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=book-reviews')); ?>">View all items</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($show_import_notice): ?>
        <div class="notice notice-info">
            <p>
                <strong>
                    <?php echo $import_source === 'amazon' ? 'Imported from Amazon.' : 'Imported from an API lookup.'; ?>
                </strong>
                Review the prefilled fields before saving.
            </p>
        </div>
    <?php endif; ?>
    
    <div class="book-reviews-admin-layout">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="book-reviews-main-form">
        <?php wp_nonce_field('book_reviews_save', 'book_reviews_nonce'); ?>
        <input type="hidden" name="action" value="book_reviews_save_media">
        <input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>">
        <input type="hidden" name="item_id" value="<?php echo $is_edit && $item ? intval($item->id) : 0; ?>">
        <input type="hidden" name="import_prefill_key" value="<?php echo esc_attr($prefill_key); ?>">
        <input type="hidden" name="imported" value="<?php echo $show_import_notice ? '1' : '0'; ?>">
        <input type="hidden" name="import_source" value="<?php echo esc_attr($import_source); ?>">
        <input type="hidden" name="import_source_url" value="<?php echo esc_attr($form_values['source_url']); ?>">
        
        <!-- Modern Card Design -->
        <div style="background: white; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px; padding: 30px; margin-top: 20px; max-width: 900px;">
            
            <!-- Media Type Selection -->
            <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #2271b1;">
                <h2 style="margin: 0 0 4px; font-size: 16px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🎬</span> Media Type
                </h2>
                <p style="margin: 8px 0 16px; font-size: 13px; color: #646970;">Select the type of media you're reviewing</p>
                
                <select name="media_type" 
                        id="media-type" 
                        required
                        style="width: 100%; max-width: 300px; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <option value="book" <?php selected($form_values['media_type'], 'book'); ?>>📚 Book</option>
                    <option value="movie" <?php selected($form_values['media_type'], 'movie'); ?>>🎬 Movie</option>
                    <option value="music" <?php selected($form_values['media_type'], 'music'); ?>>🎵 Music Album</option>
                    <option value="game" <?php selected($form_values['media_type'], 'game'); ?>>🎮 Video Game</option>
                    <option value="tv" <?php selected($form_values['media_type'], 'tv'); ?>>📺 TV Show</option>
                </select>
            </div>
            
            <!-- Basic Information Section -->
            <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #2271b1;">
                <h2 style="margin: 0 0 4px; font-size: 16px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">📝</span> <span id="section-title">Media Information</span>
                </h2>
                <p style="margin: 8px 0 16px; font-size: 13px; color: #646970;">Basic details about this item</p>
                
                <!-- Title -->
                <div style="margin-bottom: 20px;">
                    <label for="title" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        Title <span style="color: #d63638;">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           id="title" 
                           value="<?php echo esc_attr($form_values['title']); ?>" 
                           required
                           style="width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;">The title of the <span class="media-type-name">book</span></p>
                </div>
                
                <!-- Creator (Dynamic Label) -->
                <div style="margin-bottom: 20px;">
                    <label for="creator" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        <span id="creator-label">Author</span> <span style="color: #d63638;">*</span>
                    </label>
                    <input type="text" 
                           name="creator" 
                           id="creator" 
                           value="<?php echo esc_attr($form_values['creator']); ?>" 
                           required
                           style="width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;" id="creator-help">The author of the book</p>
                </div>
                
                <!-- Category (Dynamic Label) -->
                <div style="margin-bottom: 20px;">
                    <label for="category" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        <span id="category-label">Genre</span>
                    </label>
                    <input type="text" 
                           name="category" 
                           id="category" 
                           value="<?php echo esc_attr($form_values['category']); ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;" id="category-help">e.g., Fiction, Non-Fiction, Mystery</p>
                </div>

                <?php if (!empty($form_values['source_url'])): ?>
                    <div style="margin-bottom: 20px;">
                        <label for="import-source-url-display" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                            Source URL
                        </label>
                        <input type="url"
                               id="import-source-url-display"
                               value="<?php echo esc_attr($form_values['source_url']); ?>"
                               readonly
                               style="width: 100%; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 14px; background: #f6f7f7;">
                        <p style="margin: 8px 0 0; font-size: 13px; color: #646970;">
                            Imported for reference only and not stored with the media item.
                            <a href="<?php echo esc_url($form_values['source_url']); ?>" target="_blank" rel="noopener noreferrer">Open source page</a>
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Cover Image URL -->
                <div>
                    <label for="cover_image_url" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        <span id="cover-label">Cover Image</span>
                    </label>
                    
                    <!-- Image Preview -->
                    <div id="cover-image-preview" style="margin-bottom: 10px;">
                        <?php if (!empty($form_values['cover_image_url'])): ?>
                            <img src="<?php echo esc_url($form_values['cover_image_url']); ?>" 
                                 style="max-width: 200px; height: auto; display: block; border: 1px solid #dcdcde; border-radius: 4px;">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Media Upload Button -->
                    <button type="button" 
                            id="upload-cover-button" 
                            class="button"
                            style="margin-bottom: 10px;">
                        <?php echo !empty($form_values['cover_image_url']) ? '🖼️ Change Image' : '📁 Upload Image'; ?>
                    </button>
                    
                    <?php if (!empty($form_values['cover_image_url'])): ?>
                        <button type="button" id="remove-cover-button" class="button" style="margin-left: 5px;">Remove Image</button>
                    <?php endif; ?>
                    
                    <!-- Hidden URL field -->
                    <input type="hidden" 
                           name="cover_image_url" 
                           id="cover_image_url" 
                           value="<?php echo esc_attr($form_values['cover_image_url']); ?>">
                    
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;" id="cover-help">Click "Upload Image" to choose from your media library or upload a new image</p>
                </div>
            </div>
            
            <!-- Rating & Status Section -->
            <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #2271b1;">
                <h2 style="margin: 0 0 4px; font-size: 16px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">⭐</span> Rating & Status
                </h2>
                <p style="margin: 8px 0 16px; font-size: 13px; color: #646970;">Your rating and progress</p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Rating -->
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; font-size: 14px; color: #1d2327;">
                            Rating <span style="color: #d63638;">*</span>
                        </label>
                        <div class="rating-selector" style="display: flex; align-items: center; gap: 4px;">
                            <?php $current_rating = intval($form_values['rating']); ?>
                            <!-- Hidden 0-rating option -->
                            <input type="radio" 
                                   name="rating" 
                                   value="0" 
                                   id="rating-0"
                                   <?php checked($current_rating, 0); ?> 
                                   required
                                   style="position: absolute; opacity: 0; pointer-events: none;">
                            <?php 
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <label class="rating-star" data-rating="<?php echo $i; ?>" style="display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
                                    <input type="radio" 
                                           name="rating" 
                                           value="<?php echo $i; ?>" 
                                           <?php checked($current_rating, $i); ?> 
                                           style="display: none;">
                                    <span class="dashicons dashicons-star-filled" style="font-size: 28px; line-height: 1; transition: color 0.2s;"></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Status (Dynamic Options) -->
                    <div>
                        <label for="status" style="display: block; font-weight: 600; margin-bottom: 12px; font-size: 14px; color: #1d2327;">
                            <span id="status-label">Reading Status</span> <span style="color: #d63638;">*</span>
                        </label>
                        <select name="status" 
                                id="status" 
                                required
                                style="width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                            <!-- Options populated by JavaScript based on media type -->
                        </select>
                    </div>
                </div>
                
                <!-- Completion Date (Dynamic Label) -->
                <div style="margin-top: 20px;">
                    <label for="completion_date" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        <span id="date-label">Date Read</span>
                    </label>
                    <input type="date" 
                           name="completion_date" 
                           id="completion_date" 
                           value="<?php echo esc_attr($form_values['completion_date']); ?>"
                           style="width: 100%; max-width: 300px; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;" id="date-description">When you finished reading</p>
                </div>
            </div>
            
            <!-- Review Section -->
            <div style="margin-bottom: 30px;">
                <h2 style="margin: 0 0 4px; font-size: 16px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">✍️</span> Review
                </h2>
                <p style="margin: 8px 0 16px; font-size: 13px; color: #646970;">Your thoughts and review</p>
                
                <textarea name="review_text" 
                          id="review_text" 
                          rows="8"
                          style="width: 100%; padding: 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; resize: vertical;"><?php echo esc_textarea($form_values['review_text']); ?></textarea>
                <p style="margin: 8px 0 0; font-size: 13px; color: #646970;">Share your thoughts, opinions, and what you liked or didn't like</p>
            </div>
            
            <!-- Submit Button -->
            <div style="padding-top: 20px; border-top: 1px solid #dcdcde;">
                <button type="submit" 
                        name="book_reviews_submit" 
                        class="button button-primary button-large"
                        style="padding: 8px 24px; font-size: 14px;">
                    <?php echo $is_edit ? '💾 Update Media Item' : '📚 Add Media Item'; ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=book-reviews'); ?>" 
                   class="button button-large" 
                   style="margin-left: 10px; padding: 8px 24px; font-size: 14px;">Cancel</a>
            </div>
        </div>
    </form>
    <aside class="book-reviews-lookup-panel">
        <div class="book-reviews-lookup-card">
            <h2 style="margin-top: 0;">API Lookup</h2>
            <p class="description" style="margin-bottom: 16px;">Search public APIs while adding a media item. Imported results are copied into this form and images are downloaded into your local WordPress Media Library.</p>

            <form method="get" class="book-reviews-lookup-form">
                <input type="hidden" name="page" value="book-reviews-add">
                <div class="book-reviews-lookup-fields">
                    <div class="book-reviews-lookup-field book-reviews-lookup-field-type">
                        <label for="lookup-media-type">Type</label>
                        <select id="lookup-media-type" name="lookup_media_type">
                            <option value="book" <?php selected($lookup_media_type, 'book'); ?>>Book</option>
                            <option value="movie" <?php selected($lookup_media_type, 'movie'); ?>>Movie</option>
                            <option value="music" <?php selected($lookup_media_type, 'music'); ?>>Music Album</option>
                            <option value="game" <?php selected($lookup_media_type, 'game'); ?>>Video Game</option>
                            <option value="tv" <?php selected($lookup_media_type, 'tv'); ?>>TV Show</option>
                        </select>
                    </div>
                    <div class="book-reviews-lookup-field book-reviews-lookup-field-query">
                        <label for="lookup-query">Search</label>
                        <input type="text" id="lookup-query" name="lookup_query" value="<?php echo esc_attr($lookup_query); ?>" placeholder="Title or title and creator">
                    </div>
                </div>
                <p class="book-reviews-lookup-actions">
                    <button type="submit" class="button button-primary">Search APIs</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=book-reviews-settings')); ?>" class="button">Settings</a>
                </p>
            </form>

            <p class="description" style="margin-top: 16px;">Uses Open Library, TMDb, MusicBrainz, Cover Art Archive, and RAWG during lookup only. The public site uses only saved local data.</p>
        </div>

        <?php if (!empty($lookup_error)): ?>
            <div class="notice notice-error" style="margin: 16px 0 0;">
                <p><?php echo esc_html($lookup_error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($lookup_query)): ?>
            <div class="book-reviews-lookup-results">
                <h2 style="margin: 16px 0;">Lookup Results</h2>
                <?php if (empty($lookup_results)): ?>
                    <div class="notice notice-info" style="margin: 0;"><p>No matching results were found.</p></div>
                <?php else: ?>
                    <div style="display: grid; gap: 16px;">
                        <?php foreach ($lookup_results as $lookup_result): ?>
                            <?php book_reviews_render_import_result_card($lookup_result); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
    </div>
</div>

<style>
.book-reviews-admin-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 24px;
    align-items: start;
}

.book-reviews-main-form {
    min-width: 0;
}

.book-reviews-main-form > div {
    max-width: none !important;
}

.book-reviews-lookup-panel {
    position: sticky;
    top: 24px;
}

.book-reviews-lookup-card {
    background: white;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    border-radius: 4px;
    padding: 20px;
}

.book-reviews-lookup-form {
    display: grid;
    gap: 14px;
}

.book-reviews-lookup-fields {
    display: grid;
    grid-template-columns: minmax(110px, 132px) minmax(0, 1fr);
    gap: 12px;
    align-items: end;
}

.book-reviews-lookup-field {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.book-reviews-lookup-field label {
    font-size: 12px;
    font-weight: 600;
    color: #50575e;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.book-reviews-lookup-field select,
.book-reviews-lookup-field input {
    width: 100%;
    min-height: 40px;
}

.book-reviews-lookup-actions {
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.book-reviews-lookup-actions .button {
    margin: 0;
}

/* Rating selector styling */
.rating-star {
    position: relative;
}

.rating-star .dashicons {
    color: #ddd !important; /* Light gray for unselected */
}

.rating-star:hover .dashicons {
    color: #FFA500 !important; /* Gold on hover */
    transform: scale(1.1);
}

.rating-star:has(input:checked) .dashicons {
    color: #FFBB00 !important; /* Warm gold for selected */
}

/* Show filled stars for all stars up to and including the selected one */
.rating-star:has(input:checked) .dashicons,
.rating-star:has(input:checked) ~ .rating-star .dashicons {
    color: #ddd !important;
}

.rating-star:has(input:checked) .dashicons {
    color: #FFBB00 !important;
}

/* Fill stars before the checked one */
.rating-selector:has(input[value="5"]:checked) .rating-star:nth-child(-n+6) .dashicons,
.rating-selector:has(input[value="4"]:checked) .rating-star:nth-child(-n+5) .dashicons,
.rating-selector:has(input[value="3"]:checked) .rating-star:nth-child(-n+4) .dashicons,
.rating-selector:has(input[value="2"]:checked) .rating-star:nth-child(-n+3) .dashicons,
.rating-selector:has(input[value="1"]:checked) .rating-star:nth-child(-n+2) .dashicons {
    color: #FFBB00 !important;
}

/* Input focus states */
input[type="text"]:focus,
input[type="url"]:focus,
input[type="date"]:focus,
select:focus,
textarea:focus {
    border-color: #2271b1 !important;
    box-shadow: 0 0 0 1px #2271b1 !important;
    outline: none !important;
}

/* Responsive */
@media (max-width: 768px) {
    .book-reviews-admin-layout {
        grid-template-columns: 1fr;
    }

    .book-reviews-lookup-panel {
        position: static;
    }

    .wrap > div {
        margin-top: 20px !important;
    }

    .book-reviews-lookup-fields {
        grid-template-columns: 1fr;
    }
    
    .book-reviews-main-form > div {
        padding: 20px !important;
    }
    
    .book-reviews-main-form > div > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .rating-selector {
        flex-wrap: wrap !important;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Media type configuration
    const mediaConfig = {
        book: {
            creator: { label: 'Author', help: 'The author of the book' },
            category: { label: 'Genre', help: 'e.g., Fiction, Non-Fiction, Mystery' },
            cover: { label: 'Cover Image URL', help: 'Direct URL to the book cover' },
            status: {
                label: 'Reading Status',
                options: [
                    { value: 'finished', label: 'Finished' },
                    { value: 'currently_reading', label: 'Currently Reading' },
                    { value: 'want_to_read', label: 'Want to Read' },
                    { value: 'abandoned', label: 'Abandoned' }
                ]
            },
            date: {
                finished: { label: 'Date Finished', help: 'When you finished reading' },
                currently_reading: { label: 'Date Started', help: 'When you started reading' },
                want_to_read: { label: 'Date Added', help: 'When you added to your list' },
                abandoned: { label: 'Date Abandoned', help: 'When you stopped reading' }
            },
            typeName: 'book'
        },
        movie: {
            creator: { label: 'Director', help: 'The director of the movie' },
            category: { label: 'Genre', help: 'e.g., Action, Drama, Comedy' },
            cover: { label: 'Poster Image URL', help: 'Direct URL to the movie poster' },
            status: {
                label: 'Watch Status',
                options: [
                    { value: 'watched', label: 'Watched' },
                    { value: 'want_to_watch', label: 'Want to Watch' },
                    { value: 'abandoned', label: 'Did Not Finish' }
                ]
            },
            date: {
                watched: { label: 'Date Watched', help: 'When you watched it' },
                want_to_watch: { label: 'Date Added', help: 'When you added to your list' },
                abandoned: { label: 'Date Abandoned', help: 'When you stopped watching' }
            },
            typeName: 'movie'
        },
        music: {
            creator: { label: 'Artist', help: 'The artist or band' },
            category: { label: 'Genre', help: 'e.g., Rock, Pop, Hip-Hop, Classical' },
            cover: { label: 'Album Art URL', help: 'Direct URL to the album artwork' },
            status: {
                label: 'Listen Status',
                options: [
                    { value: 'listened', label: 'Listened' },
                    { value: 'currently_listening', label: 'Currently Listening' },
                    { value: 'want_to_listen', label: 'Want to Listen' }
                ]
            },
            date: {
                listened: { label: 'Date Listened', help: 'When you listened to it' },
                currently_listening: { label: 'Date Started', help: 'When you started listening' },
                want_to_listen: { label: 'Date Added', help: 'When you added to your list' }
            },
            typeName: 'album'
        },
        game: {
            creator: { label: 'Developer', help: 'The game developer/studio' },
            category: { label: 'Genre', help: 'e.g., RPG, Action, Strategy, Puzzle' },
            cover: { label: 'Cover Art URL', help: 'Direct URL to the game cover' },
            status: {
                label: 'Play Status',
                options: [
                    { value: 'completed', label: 'Completed' },
                    { value: 'playing', label: 'Currently Playing' },
                    { value: 'want_to_play', label: 'Want to Play' },
                    { value: 'abandoned', label: 'Abandoned' }
                ]
            },
            date: {
                completed: { label: 'Date Completed', help: 'When you beat/completed it' },
                playing: { label: 'Date Started', help: 'When you started playing' },
                want_to_play: { label: 'Date Added', help: 'When you added to your list' },
                abandoned: { label: 'Date Abandoned', help: 'When you stopped playing' }
            },
            typeName: 'game'
        },
        tv: {
            creator: { label: 'Creator', help: 'The showrunner or creator' },
            category: { label: 'Genre', help: 'e.g., Drama, Comedy, Thriller' },
            cover: { label: 'Poster Image URL', help: 'Direct URL to the show poster' },
            status: {
                label: 'Watch Status',
                options: [
                    { value: 'finished', label: 'Finished' },
                    { value: 'watching', label: 'Currently Watching' },
                    { value: 'want_to_watch', label: 'Want to Watch' },
                    { value: 'abandoned', label: 'Abandoned' }
                ]
            },
            date: {
                finished: { label: 'Date Finished', help: 'When you finished the show' },
                watching: { label: 'Date Started', help: 'When you started watching' },
                want_to_watch: { label: 'Date Added', help: 'When you added to your list' },
                abandoned: { label: 'Date Abandoned', help: 'When you stopped watching' }
            },
            typeName: 'TV show'
        }
    };
    
    // Initialize form based on current media type
    const currentMediaType = $('#media-type').val();
    const currentStatus = '<?php echo esc_js($form_values['status']); ?>';
    updateFormForMediaType(currentMediaType, currentStatus);
    
    // Handle media type change
    $('#media-type').on('change', function() {
        const mediaType = $(this).val();
        updateFormForMediaType(mediaType);
    });
    
    // Handle status change to update date label
    $('#status').on('change', function() {
        const mediaType = $('#media-type').val();
        const status = $(this).val();
        updateDateLabel(mediaType, status);
    });
    
    function updateFormForMediaType(mediaType, selectedStatus = null) {
        const config = mediaConfig[mediaType];
        
        // Update creator label and help
        $('#creator-label').text(config.creator.label);
        $('#creator-help').text(config.creator.help);
        $('#creator').attr('placeholder', config.creator.label);
        
        // Update category label and help
        $('#category-label').text(config.category.label);
        $('#category-help').text(config.category.help);
        $('#category').attr('placeholder', config.category.help);
        
        // Update cover label and help
        $('#cover-label').text(config.cover.label);
        $('#cover-help').text(config.cover.help);
        
        // Update status label and options
        $('#status-label').text(config.status.label);
        const $statusSelect = $('#status');
        $statusSelect.empty();
        
        config.status.options.forEach(function(option) {
            const $option = $('<option></option>')
                .attr('value', option.value)
                .text(option.label);
            
            if (selectedStatus && option.value === selectedStatus) {
                $option.attr('selected', 'selected');
            } else if (!selectedStatus && option.value === config.status.options[0].value) {
                $option.attr('selected', 'selected');
            }
            
            $statusSelect.append($option);
        });
        
        // Update date label
        const currentStatus = selectedStatus || config.status.options[0].value;
        updateDateLabel(mediaType, currentStatus);
        
        // Update media type name in hints
        $('.media-type-name').text(config.typeName);
    }
    
    function updateDateLabel(mediaType, status) {
        const config = mediaConfig[mediaType];
        const dateConfig = config.date[status] || config.date[Object.keys(config.date)[0]];
        
        $('#date-label').text(dateConfig.label);
        $('#date-description').text(dateConfig.help);
    }
    
    // Star rating: Track current rating and handle deselect
    let currentRating = $('input[name="rating"]:checked').val() || '0';
    
    // Handle star clicks with deselect functionality
    $('.rating-star').on('click', function(e) {
        e.preventDefault(); // Prevent default label behavior
        
        const clickedRating = $(this).data('rating').toString();
        const $input = $(this).find('input[type="radio"]');
        
        // If clicking the currently selected star, clear the rating
        if (currentRating === clickedRating) {
            $('#rating-0').prop('checked', true);
            currentRating = '0';
            updateStarDisplay(0);
        } else {
            // Select the clicked star
            $input.prop('checked', true);
            currentRating = clickedRating;
            updateStarDisplay(parseInt(clickedRating));
        }
    });
    
    // Update star colors based on rating value
    function updateStarDisplay(rating) {
        $('.rating-star').each(function(index) {
            const starValue = index + 1;
            const $icon = $(this).find('.dashicons');
            
            if (starValue <= rating) {
                $icon.css('color', '#FFBB00');
            } else {
                $icon.css('color', '#ddd');
            }
        });
    }
    
    // Initialize star display on page load
    updateStarDisplay(parseInt(currentRating));
});
</script>
