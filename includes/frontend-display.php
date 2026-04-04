<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';

// Generate unique ID for this shortcode instance
static $instance_counter = 0;
$instance_counter++;
$instance_id = 'book-reviews-' . $instance_counter;

// Get parameters
$view = isset($atts['view']) ? $atts['view'] : 'grid';
$limit = isset($atts['limit']) ? intval($atts['limit']) : -1;
$category_filter = isset($atts['category']) ? sanitize_text_field($atts['category']) : '';
$genre_filter = isset($atts['genre']) ? sanitize_text_field($atts['genre']) : ''; // Backward compatibility
$status_filter = isset($atts['status']) ? sanitize_text_field($atts['status']) : '';
$show_filters = isset($atts['show_filters']) && $atts['show_filters'] === 'false' ? false : true;
$media_type_filter = isset($atts['media_type']) ? sanitize_text_field($atts['media_type']) : 'all';

// Use category if provided, fallback to genre for backward compatibility
if (empty($category_filter) && !empty($genre_filter)) {
    $category_filter = $genre_filter;
}

// Build query
$query = "SELECT * FROM $table_name WHERE 1=1";

// Media type filter
if ($media_type_filter !== 'all') {
    // Support comma-separated media types (e.g., "book,movie")
    $media_types = array_map('trim', explode(',', $media_type_filter));
    $media_type_placeholders = implode(',', array_fill(0, count($media_types), '%s'));
    $query .= $wpdb->prepare(" AND media_type IN ($media_type_placeholders)", $media_types);
}

// Category/Genre filter - check both old and new column names
if (!empty($category_filter)) {
    $query .= $wpdb->prepare(" AND (category = %s OR genre = %s)", $category_filter, $category_filter);
}

// Status filter
if (!empty($status_filter)) {
    // Support comma-separated statuses (e.g., "finished,abandoned")
    $statuses = array_map('trim', explode(',', $status_filter));
    $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $query .= $wpdb->prepare(" AND (status IN ($status_placeholders) OR reading_status IN ($status_placeholders))", array_merge($statuses, $statuses));
}

$query .= " ORDER BY date_added DESC";

if ($limit > 0) {
    $query .= $wpdb->prepare(" LIMIT %d", $limit);
}

$items = $wpdb->get_results($query);

// Get unique categories for filter - handle comma-separated values and both column names
$all_categories_raw = $wpdb->get_col("SELECT DISTINCT category FROM $table_name WHERE category IS NOT NULL AND category != '' 
    UNION 
    SELECT DISTINCT genre FROM $table_name WHERE genre IS NOT NULL AND genre != '' 
    ORDER BY category");
$categories = array();
foreach ($all_categories_raw as $category_string) {
    if (!empty($category_string)) {
        // Split by comma and trim whitespace
        $category_array = array_map('trim', explode(',', $category_string));
        foreach ($category_array as $single_category) {
            if (!empty($single_category) && !in_array($single_category, $categories)) {
                $categories[] = $single_category;
            }
        }
    }
}
sort($categories); // Alphabetize

// Get unique media types
$media_types_in_db = $wpdb->get_col("SELECT DISTINCT media_type FROM $table_name WHERE media_type IS NOT NULL ORDER BY media_type");
if (empty($media_types_in_db)) {
    $media_types_in_db = array('book'); // Default if no media_type column exists yet
}

// All possible statuses across all media types
$all_statuses = array(
    'finished' => 'Finished',
    'currently_reading' => 'Currently Reading',
    'want_to_read' => 'Want to Read',
    'abandoned' => 'Abandoned',
    'watched' => 'Watched',
    'want_to_watch' => 'Want to Watch',
    'listened' => 'Listened',
    'currently_listening' => 'Currently Listening',
    'want_to_listen' => 'Want to Listen',
    'completed' => 'Completed',
    'playing' => 'Playing',
    'want_to_play' => 'Want to Play'
);

// Media type icons
if (!function_exists('get_media_type_icon')) {
    function get_media_type_icon($type) {
        $icons = array(
            'book' => '📚',
            'movie' => '🎬',
            'music' => '🎵',
            'game' => '🎮'
        );
        return $icons[$type] ?? '📄';
    }
}

// Media type labels
if (!function_exists('get_media_type_label')) {
    function get_media_type_label($type) {
        $labels = array(
            'book' => 'Book',
            'movie' => 'Movie',
            'music' => 'Album',
            'game' => 'Game'
        );
        return $labels[$type] ?? ucfirst($type);
    }
}

// Get creator label based on media type
if (!function_exists('get_creator_label')) {
    function get_creator_label($type) {
        $labels = array(
            'book' => 'Author',
            'movie' => 'Director',
            'music' => 'Artist',
            'game' => 'Developer'
        );
        return $labels[$type] ?? 'Creator';
    }
}
?>

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                serif: ['Merriweather', 'Georgia', 'serif'],
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            colors: {
                amber: {
                    400: '#fbbf24',
                }
            }
        }
    }
}
</script>

<div class="min-h-screen w-full bg-[#fdfbf7] text-stone-900" data-view="<?php echo esc_attr($view); ?>" id="<?php echo esc_attr($instance_id); ?>">
    
    <!-- Filters and Search -->
    <?php if ($show_filters): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4">
        <div class="flex flex-col sm:flex-row gap-3 items-stretch">
            <!-- Search -->
            <div class="flex-1">
                <input type="text"
                       class="w-full h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-search-input"
                       data-instance="<?php echo esc_attr($instance_id); ?>"
                       placeholder="Search by title or creator...">
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <!-- Media Type Filter -->
                <?php if (count($media_types_in_db) > 1): ?>
                <select class="h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-filter media-type-filter" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="">All Types</option>
                    <?php foreach ($media_types_in_db as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>">
                            <?php echo get_media_type_icon($type); ?> <?php echo esc_html(get_media_type_label($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <!-- Category Filter -->
                <select class="h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-filter genre-filter" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Status Filter -->
                <select class="h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-filter status-filter" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="">All Statuses</option>
                    <?php foreach ($all_statuses as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Rating Filter -->
                <select class="h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-filter rating-filter" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="">All Ratings</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4+ Stars</option>
                    <option value="3">3+ Stars</option>
                    <option value="2">2+ Stars</option>
                    <option value="1">1+ Star</option>
                </select>

                <!-- Sort -->
                <select class="h-10 px-4 py-2 border border-stone-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400 text-sm book-sort" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="date-desc">Newest First</option>
                    <option value="date-asc">Oldest First</option>
                    <option value="title-asc">Title (A-Z)</option>
                    <option value="title-desc">Title (Z-A)</option>
                    <option value="rating-desc">Highest Rated</option>
                    <option value="rating-asc">Lowest Rated</option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Items Grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8" data-instance="<?php echo esc_attr($instance_id); ?>">
        <?php if (empty($items)): ?>
            <p class="no-books-message">No media items found.</p>
        <?php else: ?>
            <?php foreach ($items as $item): 
                // Support both old and new field names
                $media_type = $item->media_type ?? 'book';
                $creator = $item->creator ?? $item->author ?? '';
                $category = $item->category ?? $item->genre ?? '';
                $status = $item->status ?? $item->reading_status ?? '';
                $completion_date = $item->completion_date ?? $item->date_read ?? '';
                
                // Format status for display
                $status_display = str_replace('_', ' ', ucwords($status, '_'));
                
                // Format date based on media type and status
                $date_label = '';
                if (!empty($completion_date)) {
                    $date_formatted = date('M j, Y', strtotime($completion_date));
                    if (in_array($status, ['currently_reading', 'playing', 'currently_listening'])) {
                        $date_label = 'Started: ' . $date_formatted;
                    } elseif (in_array($status, ['finished', 'watched', 'listened', 'completed'])) {
                        $date_label = 'Finished: ' . $date_formatted;
                    } else {
                        $date_label = $date_formatted;
                    }
                }
            ?>
                <div id="book-card-<?php echo esc_attr($item->id); ?>"
                     class="book-card-container cursor-pointer"
                     data-instance="<?php echo esc_attr($instance_id); ?>"
                     data-item-id="<?php echo esc_attr($item->id); ?>"
                     data-title="<?php echo esc_attr(strtolower($item->title)); ?>"
                     data-creator="<?php echo esc_attr(strtolower($creator)); ?>"
                     data-category="<?php echo esc_attr($category); ?>"
                     data-status="<?php echo esc_attr($status); ?>"
                     data-rating="<?php echo esc_attr($item->rating); ?>"
                     data-media-type="<?php echo esc_attr($media_type); ?>"
                     data-date="<?php echo esc_attr($item->date_added); ?>">

                    <div class="book-card-inner">
                        <!-- FRONT: Cover with Rating -->
                        <div class="book-card-front">
                            <div class="relative aspect-[2/3] w-full h-full overflow-hidden bg-stone-200">
                                <?php if ($item->cover_image_url): ?>
                                    <img src="<?php echo esc_url($item->cover_image_url); ?>"
                                         alt="Cover of <?php echo esc_attr($item->title); ?>"
                                         class="h-full w-full object-cover">
                                <?php else: ?>
                                    <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-stone-100 to-stone-200">
                                        <span class="text-6xl opacity-30"><?php echo get_media_type_icon($media_type); ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Rating Badge on Cover -->
                                <?php if ($item->rating > 0): ?>
                                    <div class="absolute bottom-3 left-3 flex items-center gap-1 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-md shadow-sm">
                                        <span class="text-xs font-bold text-stone-800"><?php echo intval($item->rating); ?></span>
                                        <span class="text-xs text-amber-400">★</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- BACK: Details -->
                        <div class="book-card-back">
                            <div class="flex flex-col h-full p-6 bg-white">
                                <!-- Title -->
                                <h3 class="text-lg font-bold text-stone-900 leading-tight mb-2 font-serif"><?php echo esc_html($item->title); ?></h3>

                                <!-- Author/Creator -->
                                <p class="text-sm text-stone-600 mb-3"><?php echo esc_html($creator); ?></p>

                                <!-- Stars -->
                                <?php if ($item->rating > 0): ?>
                                    <div class="flex items-center gap-1 mb-3">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="text-lg <?php echo $i <= $item->rating ? 'text-amber-400' : 'text-stone-300'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Review Text -->
                                <?php if ($item->review_text && trim($item->review_text)): ?>
                                    <div class="flex-1 mb-3 overflow-auto">
                                        <p class="text-sm text-stone-700 leading-relaxed italic font-serif line-clamp-6">
                                            "<?php echo esc_html($item->review_text); ?>"
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="flex-1 mb-3"></div>
                                <?php endif; ?>

                                <!-- Media Type -->
                                <div class="mt-auto pt-3 border-t border-stone-200">
                                    <div class="flex items-center gap-2 text-xs text-stone-600">
                                        <span><?php echo get_media_type_icon($media_type); ?></span>
                                        <span class="font-medium"><?php echo esc_html(get_media_type_label($media_type)); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        <p class="text-xs text-stone-500 leading-relaxed">
            Metadata lookup and artwork imports for this collection may use
            <a href="https://openlibrary.org/" target="_blank" rel="noopener noreferrer" class="underline">Open Library</a>,
            <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer" class="underline">TMDb</a>,
            <a href="https://musicbrainz.org/" target="_blank" rel="noopener noreferrer" class="underline">MusicBrainz</a>,
            <a href="https://coverartarchive.org/" target="_blank" rel="noopener noreferrer" class="underline">Cover Art Archive</a>,
            and
            <a href="https://rawg.io/" target="_blank" rel="noopener noreferrer" class="underline">RAWG</a>.
            Public display is served from locally saved WordPress data and images.
        </p>
    </div>
</div>

<!-- Modal for viewing full review -->
<div class="book-modal fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm" 
     id="modal-<?php echo esc_attr($instance_id); ?>" 
     data-instance="<?php echo esc_attr($instance_id); ?>"
     style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
    <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden" style="max-width: 48rem; margin: auto;">
        <!-- Close Button -->
        <button class="book-modal-close absolute top-4 right-4 z-10 p-2 bg-white/90 backdrop-blur-sm rounded-full hover:bg-white transition-colors" style="position: absolute; top: 1rem; right: 1rem; z-index: 10;">
            <svg class="w-5 h-5 text-stone-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        
        <!-- Modal Body (populated by JavaScript) -->
        <div class="book-modal-body">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<script type="text/javascript">
// Store item data for modal
window.bookReviewsData = window.bookReviewsData || {};
window.bookReviewsData['<?php echo esc_js($instance_id); ?>'] = <?php echo json_encode(array_map(function($item) {
    return array(
        'id' => $item->id,
        'media_type' => $item->media_type ?? 'book',
        'title' => $item->title,
        'creator' => $item->creator ?? $item->author ?? '',
        'category' => $item->category ?? $item->genre ?? '',
        'rating' => $item->rating,
        'review_text' => $item->review_text,
        'cover_image_url' => $item->cover_image_url,
        'status' => $item->status ?? $item->reading_status ?? '',
        'completion_date' => $item->completion_date ?? $item->date_read ?? ''
    );
}, $items)); ?>;
</script>
