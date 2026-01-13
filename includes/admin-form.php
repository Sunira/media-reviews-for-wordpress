<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';

// Get media item data if editing
$item = null;
$is_edit = false;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id));
    $is_edit = true;
}

// Handle form submission
if (isset($_POST['book_reviews_submit']) && wp_verify_nonce($_POST['book_reviews_nonce'], 'book_reviews_save')) {
    // Use wp_unslash to remove any magic quotes slashes before sanitizing
    $media_type = sanitize_text_field(wp_unslash($_POST['media_type']));
    $title = sanitize_text_field(wp_unslash($_POST['title']));
    $creator = sanitize_text_field(wp_unslash($_POST['creator']));
    $rating = intval($_POST['rating']);
    $review_text = sanitize_textarea_field(wp_unslash($_POST['review_text']));
    $cover_image_url = esc_url_raw($_POST['cover_image_url']);
    $category = sanitize_text_field(wp_unslash($_POST['category']));
    $status = sanitize_text_field(wp_unslash($_POST['status']));
    $completion_date = !empty($_POST['completion_date']) ? sanitize_text_field(wp_unslash($_POST['completion_date'])) : null;

    // Validate
    if (empty($title) || empty($creator) || $rating < 0 || $rating > 5) {
        echo '<div class="notice notice-error is-dismissible"><p>Please fill in all required fields correctly. Rating must be 0-5.</p></div>';
    } else {
        $data = array(
            'media_type' => $media_type,
            'title' => $title,
            'creator' => $creator,
            'rating' => $rating,
            'review_text' => $review_text,
            'cover_image_url' => $cover_image_url,
            'category' => $category,
            'status' => $status,
            'completion_date' => $completion_date
        );

        $format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s');

        if ($is_edit && $item) {
            // Update existing item
            $wpdb->update($table_name, $data, array('id' => $item->id), $format, array('%d'));
            echo '<div class="notice notice-success is-dismissible"><p>Media item updated successfully! <a href="' . admin_url('admin.php?page=book-reviews') . '">View all items</a></p></div>';
            // Refresh item data
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item->id));
        } else {
            // Insert new item
            $wpdb->insert($table_name, $data, $format);
            echo '<div class="notice notice-success is-dismissible"><p>Media item added successfully! <a href="' . admin_url('admin.php?page=book-reviews') . '">View all items</a></p></div>';
            
            // Clear form by redirecting
            $new_id = $wpdb->insert_id;
            wp_redirect(admin_url('admin.php?page=book-reviews-add&action=edit&id=' . $new_id . '&success=1'));
            exit;
        }
    }
}

// Default values for new items
$default_media_type = 'book';
$default_creator = '';
$default_category = '';
$default_status = 'finished';
$default_completion_date = '';

// Use item values if editing
if ($item) {
    $default_media_type = $item->media_type ?? 'book';
    $default_creator = $item->creator ?? '';
    $default_category = $item->category ?? '';
    $default_status = $item->status ?? 'finished';
    $default_completion_date = $item->completion_date ?? '';
}
?>

<div class="wrap">
    <h1><?php echo $is_edit ? 'Edit Media Item' : 'Add New Media Item'; ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('book_reviews_save', 'book_reviews_nonce'); ?>
        
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
                    <option value="book" <?php selected($default_media_type, 'book'); ?>>📚 Book</option>
                    <option value="movie" <?php selected($default_media_type, 'movie'); ?>>🎬 Movie</option>
                    <option value="music" <?php selected($default_media_type, 'music'); ?>>🎵 Music Album</option>
                    <option value="game" <?php selected($default_media_type, 'game'); ?>>🎮 Video Game</option>
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
                           value="<?php echo $item ? esc_attr($item->title) : ''; ?>" 
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
                           value="<?php echo esc_attr($default_creator); ?>" 
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
                           value="<?php echo esc_attr($default_category); ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                    <p style="margin: 8px 0 0; font-size: 13px; color: #646970;" id="category-help">e.g., Fiction, Non-Fiction, Mystery</p>
                </div>
                
                <!-- Cover Image URL -->
                <div>
                    <label for="cover_image_url" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #1d2327;">
                        <span id="cover-label">Cover Image</span>
                    </label>
                    
                    <!-- Image Preview -->
                    <div id="cover-image-preview" style="margin-bottom: 10px;">
                        <?php if ($item && !empty($item->cover_image_url)): ?>
                            <img src="<?php echo esc_url($item->cover_image_url); ?>" 
                                 style="max-width: 200px; height: auto; display: block; border: 1px solid #dcdcde; border-radius: 4px;">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Media Upload Button -->
                    <button type="button" 
                            id="upload-cover-button" 
                            class="button"
                            style="margin-bottom: 10px;">
                        <?php echo ($item && !empty($item->cover_image_url)) ? '🖼️ Change Image' : '📁 Upload Image'; ?>
                    </button>
                    
                    <?php if ($item && !empty($item->cover_image_url)): ?>
                        <button type="button" id="remove-cover-button" class="button" style="margin-left: 5px;">Remove Image</button>
                    <?php endif; ?>
                    
                    <!-- Hidden URL field -->
                    <input type="hidden" 
                           name="cover_image_url" 
                           id="cover_image_url" 
                           value="<?php echo $item ? esc_attr($item->cover_image_url) : ''; ?>">
                    
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
                            <?php $current_rating = $item ? $item->rating : 0; ?>
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
                           value="<?php echo esc_attr($default_completion_date); ?>"
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
                          style="width: 100%; padding: 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; resize: vertical;"><?php echo $item ? esc_textarea($item->review_text) : ''; ?></textarea>
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
</div>

<style>
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
    .wrap > div {
        margin-top: 20px !important;
    }
    
    .wrap > div > form {
        padding: 20px !important;
    }
    
    .wrap > div > form > div > div[style*="grid-template-columns"] {
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
        }
    };
    
    // Initialize form based on current media type
    const currentMediaType = $('#media-type').val();
    const currentStatus = '<?php echo esc_js($default_status); ?>';
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
