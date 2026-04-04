<?php
/**
 * Plugin Name: Media Reviews
 * Plugin URI: https://unbrokenhorse.com/media-reviews
 * Description: A WordPress plugin to manage and display reviews for books, movies, music albums, and video games with ratings
 * Version: 3.3.0
 * Author: UnbrokenHorse.com
 * Author URI: https://unbrokenhorse.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: media-reviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BOOK_REVIEWS_VERSION', '3.3.0');
define('BOOK_REVIEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOOK_REVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BOOK_REVIEWS_PLUGIN_DIR . 'includes/amazon-import.php';
require_once BOOK_REVIEWS_PLUGIN_DIR . 'includes/frontend-helpers.php';

/**
 * Activation hook - creates database table
 */
function book_reviews_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        media_type varchar(20) DEFAULT 'book' NOT NULL,
        title varchar(255) NOT NULL,
        creator varchar(255) NOT NULL,
        rating tinyint(1) NOT NULL,
        review_text text NOT NULL,
        cover_image_url varchar(500),
        category varchar(100),
        status varchar(20) DEFAULT 'finished',
        completion_date date,
        date_added datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if we need to migrate old data
    $current_version = get_option('book_reviews_version', '0');
    if (version_compare($current_version, '3.0.0', '<')) {
        book_reviews_migrate_to_3_0();
    }

    // Store the plugin version
    update_option('book_reviews_version', BOOK_REVIEWS_VERSION);
}
register_activation_hook(__FILE__, 'book_reviews_activate');

/**
 * Migration function for v3.0.0
 * Adds new columns and updates existing data
 */
function book_reviews_migrate_to_3_0() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';

    // Add media_type column if it doesn't exist
    $row = $wpdb->get_results($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = %s AND column_name = 'media_type'", $table_name));

    if(empty($row)) {
        $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD media_type varchar(20) DEFAULT 'book' NOT NULL AFTER id", $table_name));
    }

    // Rename author to creator if needed
    $row = $wpdb->get_results($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = %s AND column_name = 'author'", $table_name));

    if(!empty($row)) {
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE author creator varchar(255) NOT NULL", $table_name));
    }

    // Rename genre to category if needed
    $row = $wpdb->get_results($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = %s AND column_name = 'genre'", $table_name));

    if(!empty($row)) {
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE genre category varchar(100)", $table_name));
    }

    // Rename reading_status to status if needed
    $row = $wpdb->get_results($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = %s AND column_name = 'reading_status'", $table_name));

    if(!empty($row)) {
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE reading_status status varchar(20) DEFAULT 'finished'", $table_name));
    }

    // Rename date_read to completion_date if needed
    $row = $wpdb->get_results($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = %s AND column_name = 'date_read'", $table_name));

    if(!empty($row)) {
        $wpdb->query($wpdb->prepare("ALTER TABLE %i CHANGE date_read completion_date date", $table_name));
    }
}

/**
 * Deactivation hook
 */
function book_reviews_deactivate() {
    // Clean up if needed (we'll keep the data by default)
}
register_deactivation_hook(__FILE__, 'book_reviews_deactivate');

/**
 * Add admin menu
 */
function book_reviews_admin_menu() {
    add_menu_page(
        'Media Reviews',           // Page title
        'Media Reviews',           // Menu title
        'manage_options',          // Capability
        'book-reviews',            // Menu slug
        'book_reviews_admin_page', // Callback function
        'dashicons-format-gallery',// Icon
        30                         // Position
    );

    // Rename first submenu to "All Media" 
    add_submenu_page(
        'book-reviews',
        'All Media',
        'All Media',
        'manage_options',
        'book-reviews',
        'book_reviews_admin_page'
    );

    add_submenu_page(
        'book-reviews',
        'Add New Media',
        'Add New',
        'manage_options',
        'book-reviews-add',
        'book_reviews_add_page'
    );
    
    add_submenu_page(
        'book-reviews',
        'Shortcode Generator',
        'Shortcode Generator',
        'manage_options',
        'book-reviews-shortcode',
        'book_reviews_shortcode_page'
    );
    
    add_submenu_page(
        'book-reviews',
        'Import/Export',
        'Import/Export',
        'manage_options',
        'book-reviews-import-export',
        'book_reviews_import_export_page'
    );

    add_submenu_page(
        'book-reviews',
        'Amazon Bookmarklet',
        'Amazon Bookmarklet',
        'manage_options',
        'book-reviews-amazon',
        'book_reviews_amazon_import_page'
    );

    add_submenu_page(
        'book-reviews',
        'Settings',
        'Settings',
        'manage_options',
        'book-reviews-settings',
        'book_reviews_import_settings_page'
    );
}
add_action('admin_menu', 'book_reviews_admin_menu');

/**
 * Main admin page - list all books
 */
function book_reviews_admin_page() {
    include BOOK_REVIEWS_PLUGIN_DIR . 'includes/admin-list.php';
}

/**
 * Add/Edit book page
 */
function book_reviews_add_page() {
    include BOOK_REVIEWS_PLUGIN_DIR . 'includes/admin-form.php';
}

function book_reviews_handle_admin_form_submission() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['book_reviews_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(wp_unslash($_POST['book_reviews_nonce']), 'book_reviews_save')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    $allowed_media_types = array('book', 'movie', 'music', 'game');

    $is_edit = !empty($_POST['is_edit']);
    $item_id = $is_edit && !empty($_POST['item_id']) ? absint($_POST['item_id']) : 0;

    $media_type = isset($_POST['media_type']) ? sanitize_text_field(wp_unslash($_POST['media_type'])) : 'book';
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $creator = isset($_POST['creator']) ? sanitize_text_field(wp_unslash($_POST['creator'])) : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $review_text = isset($_POST['review_text']) ? sanitize_textarea_field(wp_unslash($_POST['review_text'])) : '';
    $cover_image_url = isset($_POST['cover_image_url']) ? esc_url_raw(wp_unslash($_POST['cover_image_url'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'finished';
    $completion_date = !empty($_POST['completion_date']) ? sanitize_text_field(wp_unslash($_POST['completion_date'])) : null;
    $import_source_url = isset($_POST['import_source_url']) ? esc_url_raw(wp_unslash($_POST['import_source_url'])) : '';
    $imported = !empty($_POST['imported']);
    $prefill_key = isset($_POST['import_prefill_key']) ? sanitize_key(wp_unslash($_POST['import_prefill_key'])) : '';
    $import_source = isset($_POST['import_source']) ? sanitize_key(wp_unslash($_POST['import_source'])) : '';

    $form_values = array(
        'media_type' => in_array($media_type, $allowed_media_types, true) ? $media_type : 'book',
        'title' => $title,
        'creator' => $creator,
        'rating' => $rating,
        'review_text' => $review_text,
        'cover_image_url' => $cover_image_url,
        'category' => $category,
        'status' => $status,
        'completion_date' => $completion_date,
        'source_url' => $import_source_url,
    );

    $redirect_args = array('page' => 'book-reviews-add');
    if ($is_edit) {
        $redirect_args['action'] = 'edit';
        $redirect_args['id'] = $item_id;
    }

    if (empty($title) || empty($creator) || $rating < 0 || $rating > 5) {
        set_transient(
            'book_reviews_form_state_' . get_current_user_id(),
            array(
                'error' => 'Please fill in all required fields correctly. Rating must be 0-5.',
                'form_values' => $form_values,
                'prefill_key' => $prefill_key,
                'import_source' => $import_source,
                'imported' => $imported,
            ),
            5 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    $data = array(
        'media_type' => $form_values['media_type'],
        'title' => $title,
        'creator' => $creator,
        'rating' => $rating,
        'review_text' => $review_text,
        'cover_image_url' => $cover_image_url,
        'category' => $category,
        'status' => $status,
        'completion_date' => $completion_date,
    );
    $format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s');

    if ($is_edit && $item_id > 0) {
        $wpdb->update($table_name, $data, array('id' => $item_id), $format, array('%d'));
        book_reviews_delete_import_prefill($prefill_key);
        wp_safe_redirect(admin_url('admin.php?page=book-reviews-add&action=edit&id=' . $item_id . '&success=updated'));
        exit;
    }

    $wpdb->insert($table_name, $data, $format);
    $new_id = $wpdb->insert_id;
    book_reviews_delete_import_prefill($prefill_key);
    wp_safe_redirect(admin_url('admin.php?page=book-reviews-add&action=edit&id=' . $new_id . '&success=created'));
    exit;
}
add_action('admin_post_book_reviews_save_media', 'book_reviews_handle_admin_form_submission');

/**
 * Shortcode generator page
 */
function book_reviews_shortcode_page() {
    include BOOK_REVIEWS_PLUGIN_DIR . 'includes/shortcode-generator.php';
}

/**
 * Import/Export page
 */
function book_reviews_import_export_page() {
    include BOOK_REVIEWS_PLUGIN_DIR . 'includes/import-export.php';
}

/**
 * Enqueue admin styles and scripts
 */
function book_reviews_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'book-reviews') === false) {
        return;
    }

    wp_enqueue_media(); // For image upload
    wp_enqueue_style('book-reviews-admin', BOOK_REVIEWS_PLUGIN_URL . 'assets/css/admin-style.css', array(), BOOK_REVIEWS_VERSION);
    wp_enqueue_script('book-reviews-admin', BOOK_REVIEWS_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), BOOK_REVIEWS_VERSION, true);
}
add_action('admin_enqueue_scripts', 'book_reviews_admin_enqueue_scripts');

function book_reviews_register_block_assets() {
    wp_register_script(
        'book-reviews-block-editor',
        BOOK_REVIEWS_PLUGIN_URL . 'assets/js/block-editor.js',
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch'),
        BOOK_REVIEWS_VERSION,
        true
    );

    wp_register_style(
        'book-reviews-block-editor',
        BOOK_REVIEWS_PLUGIN_URL . 'assets/css/block-editor.css',
        array('wp-edit-blocks'),
        BOOK_REVIEWS_VERSION
    );

    register_block_type(
        BOOK_REVIEWS_PLUGIN_DIR . 'blocks/media-reviews',
        array(
            'render_callback' => 'book_reviews_render_block',
        )
    );
}
add_action('init', 'book_reviews_register_block_assets');

function book_reviews_enqueue_block_editor_preview_assets() {
    wp_enqueue_script(
        'book-reviews-tailwind-preview',
        'https://cdn.tailwindcss.com',
        array(),
        null,
        false
    );

    wp_add_inline_script(
        'book-reviews-tailwind-preview',
        'tailwind.config = { theme: { extend: { fontFamily: { serif: ["Merriweather", "Georgia", "serif"], sans: ["Inter", "system-ui", "sans-serif"] }, colors: { amber: { 400: "#fbbf24" } } } } };',
        'after'
    );
}
add_action('enqueue_block_editor_assets', 'book_reviews_enqueue_block_editor_preview_assets');

function book_reviews_render_block($attributes) {
    $media_types = array();
    if (!empty($attributes['mediaTypes']) && is_array($attributes['mediaTypes'])) {
        $media_types = array_values(array_filter(array_map('sanitize_key', $attributes['mediaTypes'])));
    }

    return book_reviews_render_media_reviews(
        array(
            'mode' => $attributes['mode'] ?? 'collection',
            'itemId' => !empty($attributes['itemId']) ? absint($attributes['itemId']) : 0,
            'view' => $attributes['layout'] ?? 'grid',
            'show_filters' => !empty($attributes['showFilters']),
            'media_types' => $media_types,
            'date_preset' => $attributes['datePreset'] ?? 'all',
            'limit' => isset($attributes['limit']) ? intval($attributes['limit']) : -1,
            'heading' => $attributes['heading'] ?? '',
        )
    );
}

function book_reviews_register_rest_routes() {
    register_rest_route(
        'media-reviews/v1',
        '/items',
        array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => 'book_reviews_rest_search_items',
            'args' => array(
                'search' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        )
    );
}
add_action('rest_api_init', 'book_reviews_register_rest_routes');

function book_reviews_rest_search_items($request) {
    global $wpdb;

    $search = sanitize_text_field((string) $request->get_param('search'));
    $table_name = $wpdb->prefix . 'book_reviews';

    if ($search === '') {
        return rest_ensure_response(array());
    }

    $like = '%' . $wpdb->esc_like($search) . '%';
    $sql = $wpdb->prepare(
        "SELECT id, title, creator, media_type, cover_image_url
         FROM {$table_name}
         WHERE title LIKE %s OR creator LIKE %s
         ORDER BY date_added DESC
         LIMIT 20",
        $like,
        $like
    );

    $rows = $wpdb->get_results($sql);
    $results = array();

    foreach ($rows as $row) {
        $results[] = array(
            'id' => absint($row->id),
            'title' => sanitize_text_field($row->title),
            'creator' => sanitize_text_field($row->creator),
            'media_type' => sanitize_key($row->media_type),
            'cover_image_url' => esc_url_raw($row->cover_image_url),
        );
    }

    return rest_ensure_response($results);
}

/**
 * Enqueue frontend styles and scripts
 */
function book_reviews_frontend_enqueue_scripts() {
    wp_enqueue_style('book-reviews-frontend', BOOK_REVIEWS_PLUGIN_URL . 'assets/css/frontend-style.css', array(), BOOK_REVIEWS_VERSION);
    wp_enqueue_script('book-reviews-frontend', BOOK_REVIEWS_PLUGIN_URL . 'assets/js/frontend-script.js', array(), BOOK_REVIEWS_VERSION, true);
    
    // Pass AJAX URL to JavaScript
    wp_localize_script('book-reviews-frontend', 'bookReviewsAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('book_reviews_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'book_reviews_frontend_enqueue_scripts');

/**
 * Register shortcode for frontend display
 */
function book_reviews_shortcode($atts) {
    return book_reviews_render_media_reviews($atts);
}

// Register both shortcodes for backward compatibility
add_shortcode('book_reviews', 'book_reviews_shortcode');
add_shortcode('media_reviews', 'book_reviews_shortcode');

/**
 * Handle AJAX requests for CRUD operations
 */
require_once BOOK_REVIEWS_PLUGIN_DIR . 'includes/ajax-handlers.php';
