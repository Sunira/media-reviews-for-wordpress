<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handlers for media reviews
 * This file will handle any AJAX requests for inline editing or other dynamic features
 */

/**
 * Check and update database schema if needed (v3.0.0 migration)
 */
function book_reviews_check_db_update() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    
    // This is now handled by the activation hook migration function
    // Keeping this for any edge cases where activation hook didn't run
    $current_version = get_option('book_reviews_version', '0');
    if (version_compare($current_version, '3.0.0', '<')) {
        book_reviews_migrate_to_3_0();
        update_option('book_reviews_version', '3.0.0');
    }
}
add_action('admin_init', 'book_reviews_check_db_update');
