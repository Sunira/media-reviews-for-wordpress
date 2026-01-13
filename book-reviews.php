<?php
/**
 * Plugin Name: Media Reviews
 * Plugin URI: https://unbrokenhorse.com/media-reviews
 * Description: A WordPress plugin to manage and display reviews for books, movies, music albums, and video games with ratings
 * Version: 3.0.0
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
define('BOOK_REVIEWS_VERSION', '3.0.0');
define('BOOK_REVIEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOOK_REVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));

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
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = '$table_name' AND column_name = 'media_type'");
    
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $table_name ADD media_type varchar(20) DEFAULT 'book' NOT NULL AFTER id");
    }
    
    // Rename author to creator if needed
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = '$table_name' AND column_name = 'author'");
    
    if(!empty($row)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE author creator varchar(255) NOT NULL");
    }
    
    // Rename genre to category if needed
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = '$table_name' AND column_name = 'genre'");
    
    if(!empty($row)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE genre category varchar(100)");
    }
    
    // Rename reading_status to status if needed
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = '$table_name' AND column_name = 'reading_status'");
    
    if(!empty($row)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE reading_status status varchar(20) DEFAULT 'finished'");
    }
    
    // Rename date_read to completion_date if needed
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = '$table_name' AND column_name = 'date_read'");
    
    if(!empty($row)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE date_read completion_date date");
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
    $atts = shortcode_atts(array(
        'view' => 'grid',
        'limit' => -1,
        'genre' => '',          // Backward compatibility
        'category' => '',       // New name for genre
        'status' => '',
        'show_filters' => 'true',
        'media_type' => 'all'   // New: filter by media type
    ), $atts);
    
    ob_start();
    include BOOK_REVIEWS_PLUGIN_DIR . 'includes/frontend-display.php';
    return ob_get_clean();
}

// Register both shortcodes for backward compatibility
add_shortcode('book_reviews', 'book_reviews_shortcode');
add_shortcode('media_reviews', 'book_reviews_shortcode');

/**
 * Handle AJAX requests for CRUD operations
 */
require_once BOOK_REVIEWS_PLUGIN_DIR . 'includes/ajax-handlers.php';
