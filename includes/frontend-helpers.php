<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function book_reviews_get_next_instance_id() {
    static $instance_counter = 0;
    $instance_counter++;

    return 'book-reviews-' . $instance_counter;
}

if (!function_exists('get_media_type_icon')) {
    function get_media_type_icon($type) {
        $icons = array(
            'book' => '📚',
            'movie' => '🎬',
            'music' => '🎵',
            'game' => '🎮',
            'tv' => '📺',
        );

        return $icons[$type] ?? '📄';
    }
}

if (!function_exists('get_media_type_label')) {
    function get_media_type_label($type) {
        $labels = array(
            'book' => 'Book',
            'movie' => 'Movie',
            'music' => 'Album',
            'game' => 'Game',
            'tv' => 'TV Show',
        );

        return $labels[$type] ?? ucfirst($type);
    }
}

if (!function_exists('get_creator_label')) {
    function get_creator_label($type) {
        $labels = array(
            'book' => 'Author',
            'movie' => 'Director',
            'music' => 'Artist',
            'game' => 'Developer',
            'tv' => 'Creator',
        );

        return $labels[$type] ?? 'Creator';
    }
}

function book_reviews_get_all_status_labels() {
    return array(
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
        'want_to_play' => 'Want to Play',
        'watching' => 'Currently Watching',
    );
}

function book_reviews_normalize_media_item($item) {
    if (!$item) {
        return null;
    }

    return array(
        'id' => isset($item->id) ? absint($item->id) : 0,
        'media_type' => sanitize_key($item->media_type ?? 'book'),
        'title' => sanitize_text_field($item->title ?? ''),
        'creator' => sanitize_text_field($item->creator ?? ($item->author ?? '')),
        'category' => sanitize_text_field($item->category ?? ($item->genre ?? '')),
        'status' => sanitize_text_field($item->status ?? ($item->reading_status ?? '')),
        'completion_date' => sanitize_text_field($item->completion_date ?? ($item->date_read ?? '')),
        'date_added' => sanitize_text_field($item->date_added ?? ''),
        'rating' => isset($item->rating) ? intval($item->rating) : 0,
        'review_text' => wp_kses_post($item->review_text ?? ''),
        'cover_image_url' => esc_url_raw($item->cover_image_url ?? ''),
    );
}

function book_reviews_get_date_preset_cutoff($date_preset) {
    $days = array(
        'last30' => 30,
        'last90' => 90,
        'last365' => 365,
    );

    if (empty($days[$date_preset])) {
        return '';
    }

    return wp_date('Y-m-d', strtotime('-' . $days[$date_preset] . ' days', current_time('timestamp')));
}

function book_reviews_prepare_media_query_args($args = array()) {
    $defaults = array(
        'limit' => -1,
        'category' => '',
        'status' => '',
        'media_type' => 'all',
        'media_types' => array(),
        'date_preset' => 'all',
        'item_id' => 0,
    );

    $args = wp_parse_args($args, $defaults);

    if (empty($args['media_types'])) {
        if (is_string($args['media_type']) && $args['media_type'] !== '' && $args['media_type'] !== 'all') {
            $args['media_types'] = array_map('trim', explode(',', $args['media_type']));
        } elseif (is_array($args['media_type'])) {
            $args['media_types'] = $args['media_type'];
        } else {
            $args['media_types'] = array();
        }
    }

    $args['media_types'] = array_values(array_filter(array_map('sanitize_key', (array) $args['media_types'])));
    $args['limit'] = intval($args['limit']);
    $args['category'] = sanitize_text_field($args['category']);
    $args['status'] = sanitize_text_field($args['status']);
    $args['date_preset'] = sanitize_key($args['date_preset']);
    $args['item_id'] = absint($args['item_id']);

    return $args;
}

function book_reviews_get_media_items($args = array()) {
    global $wpdb;

    $args = book_reviews_prepare_media_query_args($args);
    $table_name = $wpdb->prefix . 'book_reviews';
    $sql = "SELECT * FROM {$table_name} WHERE 1=1";
    $params = array();

    if ($args['item_id'] > 0) {
        $sql .= ' AND id = %d';
        $params[] = $args['item_id'];
    }

    if (!empty($args['media_types'])) {
        $placeholders = implode(',', array_fill(0, count($args['media_types']), '%s'));
        $sql .= " AND media_type IN ({$placeholders})";
        $params = array_merge($params, $args['media_types']);
    }

    if ($args['category'] !== '') {
        $sql .= ' AND category = %s';
        $params[] = $args['category'];
    }

    if ($args['status'] !== '') {
        $statuses = array_map('trim', explode(',', $args['status']));
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql .= " AND status IN ({$placeholders})";
        $params = array_merge($params, $statuses);
    }

    $cutoff = book_reviews_get_date_preset_cutoff($args['date_preset']);
    if ($cutoff !== '') {
        $sql .= ' AND completion_date IS NOT NULL AND completion_date != "" AND completion_date >= %s';
        $params[] = $cutoff;
        $sql .= ' ORDER BY completion_date DESC, date_added DESC';
    } else {
        $sql .= ' ORDER BY date_added DESC';
    }

    if ($args['limit'] > 0) {
        $sql .= ' LIMIT %d';
        $params[] = $args['limit'];
    }

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);

    return array_values(array_filter(array_map('book_reviews_normalize_media_item', $rows)));
}

function book_reviews_get_media_item_by_id($item_id) {
    $items = book_reviews_get_media_items(array('item_id' => $item_id, 'limit' => 1));
    return !empty($items) ? $items[0] : null;
}

function book_reviews_get_collection_filter_options() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'book_reviews';
    $all_categories_raw = $wpdb->get_col("SELECT DISTINCT category FROM {$table_name} WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = array();

    foreach ($all_categories_raw as $category_string) {
        if (!empty($category_string)) {
            $category_array = array_map('trim', explode(',', $category_string));
            foreach ($category_array as $single_category) {
                if ($single_category !== '' && !in_array($single_category, $categories, true)) {
                    $categories[] = $single_category;
                }
            }
        }
    }

    sort($categories);

    $media_types = $wpdb->get_col("SELECT DISTINCT media_type FROM {$table_name} WHERE media_type IS NOT NULL ORDER BY media_type");

    return array(
        'categories' => $categories,
        'media_types' => !empty($media_types) ? $media_types : array('book'),
        'statuses' => book_reviews_get_all_status_labels(),
    );
}

function book_reviews_render_media_filters($instance_id, $filter_options) {
    ob_start();
    ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4">
        <div class="book-reviews-toolbar" data-instance="<?php echo esc_attr($instance_id); ?>">
            <div class="book-reviews-toolbar__search">
                <label class="book-reviews-toolbar__label" for="book-search-<?php echo esc_attr($instance_id); ?>">Search</label>
                <input type="text"
                       id="book-search-<?php echo esc_attr($instance_id); ?>"
                       class="book-reviews-toolbar__input book-search-input"
                       data-instance="<?php echo esc_attr($instance_id); ?>"
                       placeholder="Search by title or creator...">
            </div>

            <div class="book-reviews-toolbar__filters" aria-label="Filter media reviews">
                <?php if (count($filter_options['media_types']) > 1): ?>
                    <select class="book-reviews-toolbar__select book-filter media-type-filter" data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="Filter by media type">
                        <option value="">Type</option>
                        <?php foreach ($filter_options['media_types'] as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html(get_media_type_label($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select class="book-reviews-toolbar__select book-filter genre-filter" data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="Filter by category">
                    <option value="">Category</option>
                    <?php foreach ($filter_options['categories'] as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="book-reviews-toolbar__select book-filter status-filter" data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="Filter by status">
                    <option value="">Status</option>
                    <?php foreach ($filter_options['statuses'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="book-reviews-toolbar__select book-filter rating-filter" data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="Filter by rating">
                    <option value="">Rating</option>
                    <option value="1">1★+</option>
                    <option value="2">2★+</option>
                    <option value="3">3★+</option>
                    <option value="4">4★+</option>
                    <option value="5">5★</option>
                </select>
            </div>

            <div class="book-reviews-toolbar__sort">
                <div class="book-reviews-toolbar__label">Sort</div>
                <select class="book-reviews-toolbar__select book-reviews-toolbar__select--sort book-sort" data-instance="<?php echo esc_attr($instance_id); ?>">
                    <option value="date-desc">Newest First</option>
                    <option value="date-asc">Oldest First</option>
                    <option value="title-asc">Title (A-Z)</option>
                    <option value="title-desc">Title (Z-A)</option>
                    <option value="rating-desc">Highest Rated</option>
                    <option value="rating-asc">Lowest Rated</option>
                </select>
            </div>
        </div>
        <div class="book-reviews-results-summary" data-instance="<?php echo esc_attr($instance_id); ?>" aria-live="polite"></div>
    </div>
    <?php

    return ob_get_clean();
}

function book_reviews_render_grid_card($item, $instance_id) {
    $status_display = str_replace('_', ' ', ucwords($item['status'], '_'));
    $category = $item['category'];
    ?>
    <div id="book-card-<?php echo esc_attr($item['id']); ?>"
         class="book-review-item book-card-container cursor-pointer"
         data-instance="<?php echo esc_attr($instance_id); ?>"
         data-item-id="<?php echo esc_attr($item['id']); ?>"
         data-title="<?php echo esc_attr(strtolower($item['title'])); ?>"
         data-creator="<?php echo esc_attr(strtolower($item['creator'])); ?>"
         data-category="<?php echo esc_attr($category); ?>"
         data-status="<?php echo esc_attr($item['status']); ?>"
         data-rating="<?php echo esc_attr($item['rating']); ?>"
         data-media-type="<?php echo esc_attr($item['media_type']); ?>"
         data-date="<?php echo esc_attr($item['date_added']); ?>">
        <div class="book-card-inner">
            <div class="book-card-front">
                <div class="book-card-front-media relative w-full h-full overflow-hidden bg-stone-200">
                    <?php if ($item['cover_image_url']): ?>
                        <img src="<?php echo esc_url($item['cover_image_url']); ?>" alt="Cover of <?php echo esc_attr($item['title']); ?>" class="h-full w-full object-cover">
                    <?php else: ?>
                        <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-stone-100 to-stone-200">
                            <span class="text-6xl opacity-30"><?php echo esc_html(get_media_type_icon($item['media_type'])); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($item['rating'] > 0): ?>
                        <div class="absolute bottom-3 left-3 flex items-center gap-1 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-md shadow-sm">
                            <span class="text-xs font-bold text-stone-800"><?php echo intval($item['rating']); ?></span>
                            <span class="text-xs text-amber-400">★</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="book-card-back">
                <div class="book-card-back-content flex flex-col h-full p-6 bg-white">
                    <h3 class="text-lg font-bold text-stone-900 leading-tight mb-1 font-serif"><?php echo esc_html($item['title']); ?></h3>
                    <p class="book-card-creator">
                        <span class="book-card-creator__icon" aria-hidden="true">✎</span>
                        <span><?php echo esc_html($item['creator']); ?></span>
                    </p>

                    <?php if ($item['rating'] > 0): ?>
                        <div class="flex items-center gap-1 mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="text-lg <?php echo $i <= $item['rating'] ? 'text-amber-400' : 'text-stone-300'; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($item['review_text'] && trim($item['review_text'])): ?>
                        <div class="book-card-review-scroll flex-1 mb-3">
                            <p class="text-sm text-stone-700 leading-relaxed italic font-serif">"<?php echo esc_html(wp_strip_all_tags($item['review_text'])); ?>"</p>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 mb-3"></div>
                    <?php endif; ?>

                    <div class="mt-auto pt-3 border-t border-stone-200">
                        <div class="flex items-center gap-2 text-xs text-stone-600">
                            <span><?php echo esc_html(get_media_type_icon($item['media_type'])); ?></span>
                            <span class="font-medium"><?php echo esc_html(get_media_type_label($item['media_type'])); ?></span>
                            <?php if ($status_display !== ''): ?>
                                <span>•</span>
                                <span><?php echo esc_html($status_display); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function book_reviews_render_list_item($item, $instance_id) {
    $categories = array_filter(array_map('trim', explode(',', $item['category'])));
    $date_label = '';

    if (!empty($item['completion_date'])) {
        $date_label = wp_date('M j, Y', strtotime($item['completion_date']));
    }
    ?>
    <article class="book-review-item book-list-item bg-white border border-stone-200 rounded-2xl p-5 shadow-sm"
             data-instance="<?php echo esc_attr($instance_id); ?>"
             data-item-id="<?php echo esc_attr($item['id']); ?>"
             data-title="<?php echo esc_attr(strtolower($item['title'])); ?>"
             data-creator="<?php echo esc_attr(strtolower($item['creator'])); ?>"
             data-category="<?php echo esc_attr($item['category']); ?>"
             data-status="<?php echo esc_attr($item['status']); ?>"
             data-rating="<?php echo esc_attr($item['rating']); ?>"
             data-media-type="<?php echo esc_attr($item['media_type']); ?>"
             data-date="<?php echo esc_attr($item['date_added']); ?>">
        <div class="book-list-item__layout">
            <div class="book-list-item__cover">
                <?php if ($item['cover_image_url']): ?>
                    <img src="<?php echo esc_url($item['cover_image_url']); ?>" alt="Cover of <?php echo esc_attr($item['title']); ?>">
                <?php else: ?>
                    <div class="book-list-item__placeholder"><?php echo esc_html(get_media_type_icon($item['media_type'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="book-list-item__content">
                <div class="book-list-item__header">
                    <div>
                        <p class="book-list-item__type"><?php echo esc_html(get_media_type_label($item['media_type'])); ?></p>
                        <h3 class="book-list-item__title"><?php echo esc_html($item['title']); ?></h3>
                        <p class="book-list-item__creator"><?php echo esc_html($item['creator']); ?></p>
                    </div>
                    <?php if ($item['rating'] > 0): ?>
                        <div class="book-list-item__rating"><?php echo str_repeat('★', $item['rating']); ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($categories)): ?>
                    <div class="book-list-item__badges">
                        <?php foreach ($categories as $category): ?>
                            <span class="book-list-item__badge"><?php echo esc_html($category); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($item['review_text'] !== ''): ?>
                    <p class="book-list-item__review"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($item['review_text']), 35)); ?></p>
                <?php endif; ?>

                <div class="book-list-item__meta">
                    <?php if ($item['status'] !== ''): ?>
                        <span><?php echo esc_html(str_replace('_', ' ', ucwords($item['status'], '_'))); ?></span>
                    <?php endif; ?>
                    <?php if ($date_label !== ''): ?>
                        <span><?php echo esc_html($date_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>
    <?php
}

function book_reviews_render_featured_item($item, $heading = '') {
    $categories = array_filter(array_map('trim', explode(',', $item['category'])));
    ob_start();
    ?>
    <div class="book-reviews-featured max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($heading !== ''): ?>
            <h2 class="book-reviews-featured__heading"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>
        <article class="book-reviews-featured__card">
            <div class="book-reviews-featured__media">
                <?php if ($item['cover_image_url']): ?>
                    <img src="<?php echo esc_url($item['cover_image_url']); ?>" alt="Cover of <?php echo esc_attr($item['title']); ?>">
                <?php else: ?>
                    <div class="book-reviews-featured__placeholder"><?php echo esc_html(get_media_type_icon($item['media_type'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="book-reviews-featured__content">
                <p class="book-reviews-featured__eyebrow"><?php echo esc_html(get_media_type_label($item['media_type'])); ?></p>
                <h3 class="book-reviews-featured__title"><?php echo esc_html($item['title']); ?></h3>
                <p class="book-reviews-featured__creator"><?php echo esc_html($item['creator']); ?></p>
                <?php if (!empty($categories)): ?>
                    <div class="book-reviews-featured__badges">
                        <?php foreach ($categories as $category): ?>
                            <span class="book-reviews-featured__badge"><?php echo esc_html($category); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($item['rating'] > 0): ?>
                    <p class="book-reviews-featured__rating"><?php echo esc_html(str_repeat('★', $item['rating'])); ?></p>
                <?php endif; ?>
                <?php if ($item['review_text'] !== ''): ?>
                    <div class="book-reviews-featured__review"><?php echo wpautop(esc_html($item['review_text'])); ?></div>
                <?php endif; ?>
            </div>
        </article>
        <?php echo book_reviews_render_attribution(); ?>
    </div>
    <?php

    return ob_get_clean();
}

function book_reviews_render_attribution() {
    ob_start();
    ?>
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
    <?php

    return ob_get_clean();
}

function book_reviews_render_collection($args = array()) {
    $args = wp_parse_args(
        $args,
        array(
            'view' => 'grid',
            'limit' => -1,
            'category' => '',
            'status' => '',
            'show_filters' => true,
            'media_type' => 'all',
            'media_types' => array(),
            'date_preset' => 'all',
            'heading' => '',
        )
    );

    $instance_id = book_reviews_get_next_instance_id();
    $items = book_reviews_get_media_items($args);
    $filter_options = book_reviews_get_collection_filter_options();
    $view = $args['view'] === 'list' ? 'list' : 'grid';
    $grid_classes = $view === 'list'
        ? 'book-reviews-items book-reviews-items--list'
        : 'book-reviews-items book-reviews-items--grid';

    ob_start();
    ?>
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

    <div class="min-h-screen w-full bg-[#fdfbf7] text-stone-900 book-reviews-render-root" data-view="<?php echo esc_attr($view); ?>" data-book-reviews-instance="<?php echo esc_attr($instance_id); ?>" id="<?php echo esc_attr($instance_id); ?>">
        <?php if ($args['heading'] !== ''): ?>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
                <h2 class="book-reviews-section-heading"><?php echo esc_html($args['heading']); ?></h2>
            </div>
        <?php endif; ?>

        <?php if (!empty($args['show_filters'])): ?>
            <?php echo book_reviews_render_media_filters($instance_id, $filter_options); ?>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="<?php echo esc_attr($grid_classes); ?>" data-instance="<?php echo esc_attr($instance_id); ?>">
                <?php if (empty($items)): ?>
                    <p class="no-books-message">No media items found.</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php if ($view === 'list') : ?>
                            <?php book_reviews_render_list_item($item, $instance_id); ?>
                        <?php else : ?>
                            <?php book_reviews_render_grid_card($item, $instance_id); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php echo book_reviews_render_attribution(); ?>
    </div>

    <div class="book-modal fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
         id="modal-<?php echo esc_attr($instance_id); ?>"
         data-instance="<?php echo esc_attr($instance_id); ?>"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
        <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden" style="max-width: 48rem; margin: auto;">
            <button class="book-modal-close absolute top-4 right-4 z-10 p-2 bg-white/90 backdrop-blur-sm rounded-full hover:bg-white transition-colors" style="position: absolute; top: 1rem; right: 1rem; z-index: 10;">
                <svg class="w-5 h-5 text-stone-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="book-modal-body"></div>
        </div>
    </div>

    <script type="text/javascript">
    window.bookReviewsData = window.bookReviewsData || {};
    window.bookReviewsData['<?php echo esc_js($instance_id); ?>'] = <?php echo wp_json_encode(array_values($items)); ?>;
    </script>
    <?php

    return ob_get_clean();
}

function book_reviews_render_media_reviews($atts = array()) {
    $atts = shortcode_atts(
        array(
            'view' => 'grid',
            'limit' => -1,
            'genre' => '',
            'category' => '',
            'status' => '',
            'show_filters' => 'true',
            'media_type' => 'all',
            'media_types' => array(),
            'date_preset' => 'all',
            'heading' => '',
            'mode' => 'collection',
            'itemId' => 0,
        ),
        $atts
    );

    if (empty($atts['category']) && !empty($atts['genre'])) {
        $atts['category'] = $atts['genre'];
    }

    $mode = sanitize_key($atts['mode']);
    if ($mode === 'single') {
        $item = book_reviews_get_media_item_by_id($atts['itemId']);
        if (!$item) {
            return '<div class="book-reviews-empty-state">No media item selected.</div>';
        }

        return book_reviews_render_featured_item($item, $atts['heading']);
    }

    $atts['show_filters'] = !($atts['show_filters'] === 'false' || $atts['show_filters'] === false);

    return book_reviews_render_collection($atts);
}
