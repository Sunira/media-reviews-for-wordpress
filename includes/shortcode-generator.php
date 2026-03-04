<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'book_reviews';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Shortcode Generator</h1>
    <hr class="wp-header-end">

    <div style="margin-top: 20px;">
        <p class="description" style="font-size: 14px; margin-bottom: 30px;">
            Configure your shortcode options below and copy the generated code to display your media reviews on any page or post.
        </p>

        <!-- Interactive Shortcode Generator -->
        <div style="background: white; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 30px; border-radius: 4px;">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 20px;">
                <!-- Left Column - Options -->
                <div>
                    <h2 style="margin-top: 0; border-bottom: 3px solid #2271b1; padding-bottom: 10px; font-size: 18px;">
                        ⚙️ Configure Options
                    </h2>
                    
                    <!-- Media Type Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Media Type:
                        </label>
                        <select id="sc-media-type" 
                                multiple 
                                class="regular-text"
                                style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; min-height: 100px;">
                            <option value="book">📚 Books</option>
                            <option value="movie">🎬 Movies</option>
                            <option value="music">🎵 Music Albums</option>
                            <option value="game">🎮 Video Games</option>
                        </select>
                        <p class="description" style="margin: 8px 0 0;">Hold Ctrl/Cmd to select multiple types. Leave empty for all types.</p>
                    </div>
                    
                    <!-- View Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Display Style:
                        </label>
                        <select id="sc-view" class="regular-text" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                            <option value="">Default (Grid)</option>
                            <option value="grid">Grid View</option>
                            <option value="list">List View</option>
                        </select>
                        <p class="description" style="margin: 8px 0 0;">How items are displayed on the page</p>
                    </div>

                    <!-- Limit Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Number of Items:
                        </label>
                        <input type="number" 
                               id="sc-limit" 
                               class="regular-text"
                               placeholder="All items"
                               min="1"
                               style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                        <p class="description" style="margin: 8px 0 0;">Leave empty to show all items</p>
                    </div>

                    <!-- Genre Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Filter by Category:
                        </label>
                        <select id="sc-genre" class="regular-text" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                            <option value="">All Categories</option>
                            <?php
                            // Get unique genres
                            $all_genres_raw = $wpdb->get_col("SELECT DISTINCT genre FROM $table_name WHERE genre IS NOT NULL AND genre != '' ORDER BY genre");
                            $genres_list = array();
                            foreach ($all_genres_raw as $genre_string) {
                                if (!empty($genre_string)) {
                                    $genre_array = array_map('trim', explode(',', $genre_string));
                                    foreach ($genre_array as $single_genre) {
                                        if (!empty($single_genre) && !in_array($single_genre, $genres_list)) {
                                            $genres_list[] = $single_genre;
                                        }
                                    }
                                }
                            }
                            sort($genres_list);
                            foreach ($genres_list as $genre):
                            ?>
                                <option value="<?php echo esc_attr($genre); ?>"><?php echo esc_html($genre); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="margin: 8px 0 0;">Show only items from this category</p>
                    </div>

                    <!-- Status Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Filter by Status:
                        </label>
                        <select id="sc-status" 
                                multiple 
                                class="regular-text"
                                style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; min-height: 100px;">
                            <option value="finished">Finished</option>
                            <option value="currently_reading">Currently Reading</option>
                            <option value="want_to_read">Want to Read</option>
                            <option value="abandoned">Abandoned</option>
                        </select>
                        <p class="description" style="margin: 8px 0 0;">
                            Hold <strong>Ctrl</strong> (Windows) or <strong>Cmd</strong> (Mac) to select multiple statuses
                        </p>
                    </div>

                    <!-- Show Filters Option -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                            Show Search & Filters:
                        </label>
                        <select id="sc-show-filters" class="regular-text" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                            <option value="true">Yes (Show controls)</option>
                            <option value="false">No (Hide controls)</option>
                        </select>
                        <p class="description" style="margin: 8px 0 0;">Hide search and filter controls for simpler displays</p>
                    </div>

                    <button type="button" 
                            id="reset-shortcode" 
                            class="button button-secondary"
                            style="width: 100%; padding: 10px; margin-top: 10px; height: auto;">
                        🔄 Reset to Defaults
                    </button>
                </div>

                <!-- Right Column - Generated Shortcode -->
                <div>
                    <h2 style="margin-top: 0; border-bottom: 3px solid #2271b1; padding-bottom: 10px; font-size: 18px;">
                        📋 Generated Shortcode
                    </h2>
                    
                    <div style="background: #1e1e1e; padding: 25px; border-radius: 6px; position: relative; margin-bottom: 20px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
                        <code id="generated-shortcode" 
                              style="color: #d4d4d4; font-size: 18px; font-family: 'Courier New', Monaco, monospace; word-break: break-all; display: block; line-height: 1.6;">
                            [media_reviews]
                        </code>
                        <button type="button" 
                                id="copy-shortcode" 
                                class="button button-primary"
                                style="position: absolute; top: 15px; right: 15px; padding: 8px 16px; font-weight: 600;">
                            📋 Copy
                        </button>
                    </div>

                    <div id="copy-feedback" 
                         style="display: none; background: #00a32a; color: white; padding: 12px 20px; border-radius: 4px; text-align: center; margin-bottom: 20px; font-weight: 600;">
                        ✓ Shortcode copied to clipboard!
                    </div>

                    <!-- Quick Examples -->
                    <div style="background: #f0f6fc; padding: 20px; border-left: 4px solid #2271b1; border-radius: 4px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 12px 0; font-size: 15px;">💡 How to Use:</h3>
                        <ol style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
                            <li>Configure the options on the left</li>
                            <li>Watch the shortcode update automatically</li>
                            <li>Click the <strong>Copy</strong> button</li>
                            <li>Paste in any <strong>Page</strong> or <strong>Post</strong> editor</li>
                            <li>Publish and view your media reviews</li>
                        </ol>
                    </div>

                    <!-- Common Presets -->
                    <div style="background: #fff9e6; padding: 20px; border-left: 4px solid #f0b429; border-radius: 4px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 15px;">⚡ Quick Presets</h3>
                        <p style="margin: 0 0 10px; font-size: 13px; color: #666;">Load common configurations with one click:</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="button" class="button preset-btn" data-preset="books" style="padding: 10px; height: auto; white-space: normal;">
                                📚 Books Only
                            </button>
                            <button type="button" class="button preset-btn" data-preset="movies" style="padding: 10px; height: auto; white-space: normal;">
                                🎬 Movies Only
                            </button>
                            <button type="button" class="button preset-btn" data-preset="music" style="padding: 10px; height: auto; white-space: normal;">
                                🎵 Music Only
                            </button>
                            <button type="button" class="button preset-btn" data-preset="games" style="padding: 10px; height: auto; white-space: normal;">
                                🎮 Games Only
                            </button>
                            <button type="button" class="button preset-btn" data-preset="reading" style="padding: 10px; height: auto; white-space: normal;">
                                📖 Currently Reading
                            </button>
                            <button type="button" class="button preset-btn" data-preset="recent" style="padding: 10px; height: auto; white-space: normal;">
                                ⭐ Recent 10 Items
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div style="background: #f6f7f7; padding: 20px; border-radius: 4px; margin-top: 30px; border: 1px solid #dcdcde;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #1d2327;">📖 Shortcode Reference</h3>
                <table class="wp-list-table widefat striped" style="background: white;">
                    <thead>
                        <tr>
                            <th style="padding: 12px; width: 20%;">Attribute</th>
                            <th style="padding: 12px; width: 40%;">Available Options</th>
                            <th style="padding: 12px; width: 20%;">Default</th>
                            <th style="padding: 12px; width: 20%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 12px;"><code>view</code></td>
                            <td style="padding: 12px;">grid, list</td>
                            <td style="padding: 12px;">grid</td>
                            <td style="padding: 12px;"><code>view="list"</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px;"><code>limit</code></td>
                            <td style="padding: 12px;">Any number (e.g., 10, 20, 50)</td>
                            <td style="padding: 12px;">All items</td>
                            <td style="padding: 12px;"><code>limit="10"</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px;"><code>category</code></td>
                            <td style="padding: 12px;">Any category from your media library</td>
                            <td style="padding: 12px;">All categories</td>
                            <td style="padding: 12px;"><code>category="Fiction"</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px;"><code>status</code></td>
                            <td style="padding: 12px;">Single: finished, currently_reading, want_to_read, abandoned<br>Multiple (comma-separated): finished,abandoned</td>
                            <td style="padding: 12px;">All statuses</td>
                            <td style="padding: 12px;"><code>status="finished,abandoned"</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px;"><code>show_filters</code></td>
                            <td style="padding: 12px;">true, false</td>
                            <td style="padding: 12px;">true</td>
                            <td style="padding: 12px;"><code>show_filters="false"</code></td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #fffbf0; border-left: 3px solid #f0b429; border-radius: 3px;">
                    <strong style="display: block; margin-bottom: 8px;">💡 Pro Tips:</strong>
                    <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                        <li>Create multiple pages with different shortcodes for different collections and statuses.</li>
                        <li><strong>Multiple statuses:</strong> Use comma-separated values like <code>status="finished,abandoned"</code> to show multiple status groups in one view.</li>
                        <li>Combine media type, category, and status filters to create focused collections.</li>
                        <li>Use <code>show_filters="false"</code> for clean, simple displays without search/filter controls</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Update shortcode function
        function updateShortcode() {
            let shortcode = '[media_reviews';
            let hasAttributes = false;

            // Media Type - handle multiselect
            const mediaTypeArray = $('#sc-media-type').val();
            if (mediaTypeArray && mediaTypeArray.length > 0) {
                const mediaTypeValue = mediaTypeArray.join(',');
                shortcode += ' media_type="' + mediaTypeValue + '"';
                hasAttributes = true;
            }

            // View
            const view = $('#sc-view').val();
            if (view && view !== '') {
                shortcode += ' view="' + view + '"';
                hasAttributes = true;
            }

            // Limit
            const limit = $('#sc-limit').val();
            if (limit && limit !== '') {
                shortcode += ' limit="' + limit + '"';
                hasAttributes = true;
            }

            // Genre (backward compatibility, but also works as category)
            const genre = $('#sc-genre').val();
            if (genre && genre !== '') {
                shortcode += ' category="' + genre + '"';
                hasAttributes = true;
            }

            // Status - handle multiselect
            const statusArray = $('#sc-status').val();
            if (statusArray && statusArray.length > 0) {
                const statusValue = statusArray.join(',');
                shortcode += ' status="' + statusValue + '"';
                hasAttributes = true;
            }

            // Show Filters
            const showFilters = $('#sc-show-filters').val();
            if (showFilters === 'false') {
                shortcode += ' show_filters="false"';
                hasAttributes = true;
            }

            shortcode += ']';

            $('#generated-shortcode').text(shortcode);
        }

        // Event listeners for all inputs
        $('#sc-view, #sc-genre, #sc-status, #sc-show-filters, #sc-media-type').on('change', updateShortcode);
        $('#sc-limit').on('input', updateShortcode);

        // Copy button
        $('#copy-shortcode').on('click', function() {
            const shortcode = $('#generated-shortcode').text();
            
            // Copy to clipboard
            navigator.clipboard.writeText(shortcode).then(function() {
                // Show feedback
                $('#copy-feedback').fadeIn(200).delay(2500).fadeOut(200);
                
                // Temporarily change button text
                const $btn = $('#copy-shortcode');
                const originalText = $btn.html();
                $btn.html('✓ Copied!').addClass('button-success').prop('disabled', true);
                
                setTimeout(function() {
                    $btn.html(originalText).removeClass('button-success').prop('disabled', false);
                }, 2500);
            }).catch(function(err) {
                alert('Failed to copy. Please select and copy manually.');
            });
        });

        // Reset button
        $('#reset-shortcode').on('click', function() {
            $('#sc-media-type').val(null).trigger('change'); // Clear media type
            $('#sc-view').val('');
            $('#sc-limit').val('');
            $('#sc-genre').val('');
            $('#sc-status').val(null).trigger('change'); // Clear multiselect
            $('#sc-show-filters').val('true');
            updateShortcode();
            
            // Visual feedback
            $(this).text('✓ Reset!');
            setTimeout(function() {
                $('#reset-shortcode').text('🔄 Reset to Defaults');
            }, 1500);
        });

        // Preset buttons
        $('.preset-btn').on('click', function() {
            const preset = $(this).data('preset');
            
            // Reset first
            $('#sc-media-type').val(null); // Clear media type
            $('#sc-view').val('');
            $('#sc-limit').val('');
            $('#sc-genre').val('');
            $('#sc-status').val(null); // Clear multiselect
            $('#sc-show-filters').val('true');

            // Apply preset
            switch(preset) {
                case 'books':
                    $('#sc-media-type').val(['book']);
                    break;
                case 'movies':
                    $('#sc-media-type').val(['movie']);
                    break;
                case 'music':
                    $('#sc-media-type').val(['music']);
                    break;
                case 'games':
                    $('#sc-media-type').val(['game']);
                    break;
                case 'reading':
                    $('#sc-status').val(['currently_reading']); // Array for multiselect
                    break;
                case 'want':
                    $('#sc-status').val(['want_to_read']); // Array for multiselect
                    break;
                case 'recent':
                    $('#sc-limit').val('10');
                    break;
                case 'list':
                    $('#sc-view').val('list');
                    break;
            }

            updateShortcode();
            
            // Visual feedback
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.text('✓ Loaded!').prop('disabled', true);
            setTimeout(function() {
                $btn.text(originalText).prop('disabled', false);
            }, 1000);
        });

        // Initialize
        updateShortcode();
    });
    </script>

    <style>
    .button-success {
        background: #00a32a !important;
        border-color: #00a32a !important;
        color: white !important;
    }
    </style>
</div>
