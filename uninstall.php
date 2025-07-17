<?php
/**
 * Uninstall script for Custom Theme Builder
 * This file is called when the plugin is deleted
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (!current_user_can('activate_plugins')) {
    return;
}

/**
 * Remove all plugin data
 */
function ctb_uninstall_plugin() {
    global $wpdb;
    
    // Remove custom post type posts
    $templates = get_posts([
        'post_type' => 'ctb_template',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    
    foreach ($templates as $template_id) {
        // Delete post meta
        $wpdb->delete(
            $wpdb->postmeta,
            ['post_id' => $template_id],
            ['%d']
        );
        
        // Delete post
        $wpdb->delete(
            $wpdb->posts,
            ['ID' => $template_id],
            ['%d']
        );
    }
    
    // Remove custom database table
    $table_name = $wpdb->prefix . 'ctb_template_conditions';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    
    // Remove plugin options
    $options_to_delete = [
        'ctb_cache_enabled',
        'ctb_cache_duration',
        'ctb_debug_mode',
        'ctb_flush_rewrite_rules',
        'ctb_plugin_version',
        'ctb_db_version',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
        delete_site_option($option); // For multisite
    }
    
    // Remove transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ctb_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ctb_%'");
    
    // Remove user meta related to plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ctb_%'");
    
    // Remove any custom capabilities
    $roles = ['administrator', 'editor'];
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('ctb_manage_templates');
            $role->remove_cap('ctb_edit_templates');
            $role->remove_cap('ctb_delete_templates');
        }
    }
    
    // Clear any scheduled events
    wp_clear_scheduled_hook('ctb_cleanup_cache');
    wp_clear_scheduled_hook('ctb_optimize_database');
    
    // Remove uploaded files if any
    $upload_dir = wp_upload_dir();
    $ctb_upload_dir = $upload_dir['basedir'] . '/ctb-templates/';
    
    if (is_dir($ctb_upload_dir)) {
        ctb_remove_directory($ctb_upload_dir);
    }
    
    // Clear any object cache
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('ctb');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear any external cache if available
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Remove any logs
    $log_files = glob(WP_CONTENT_DIR . '/debug-ctb-*.log');
    foreach ($log_files as $log_file) {
        if (is_file($log_file)) {
            unlink($log_file);
        }
    }
    
    // Remove any temporary files
    $temp_files = glob(sys_get_temp_dir() . '/ctb-template-*');
    foreach ($temp_files as $temp_file) {
        if (is_file($temp_file)) {
            unlink($temp_file);
        }
    }
    
    // Log uninstall for debugging (if debug mode was enabled)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Custom Theme Builder: Plugin uninstalled and all data removed');
    }
}

/**
 * Recursively remove directory and its contents
 */
function ctb_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            ctb_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Clean up Elementor integration data
 */
function ctb_cleanup_elementor_data() {
    global $wpdb;
    
    // Remove Elementor meta for our templates
    $wpdb->query("
        DELETE pm FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = 'ctb_template'
        AND pm.meta_key LIKE '_elementor%'
    ");
    
    // Clear Elementor cache
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::instance()->files_manager->clear_cache();
    }
}

/**
 * Clean up WooCommerce integration data
 */
function ctb_cleanup_woocommerce_data() {
    global $wpdb;
    
    // Remove any WooCommerce specific meta
    $wpdb->query("
        DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE 'ctb_woo_%'
    ");
}

/**
 * Perform multisite cleanup
 */
function ctb_multisite_cleanup() {
    if (!is_multisite()) {
        return;
    }
    
    global $wpdb;
    
    // Get all sites
    $sites = get_sites(['number' => 0]);
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Run cleanup for each site
        ctb_uninstall_plugin();
        
        restore_current_blog();
    }
    
    // Remove network options
    delete_site_option('ctb_network_settings');
    delete_site_option('ctb_network_cache_enabled');
}

// Run the uninstall process
try {
    // Check if it's a multisite installation
    if (is_multisite()) {
        ctb_multisite_cleanup();
    } else {
        ctb_uninstall_plugin();
    }
    
    // Clean up Elementor specific data
    ctb_cleanup_elementor_data();
    
    // Clean up WooCommerce specific data if WooCommerce is active
    if (class_exists('WooCommerce')) {
        ctb_cleanup_woocommerce_data();
    }
    
} catch (Exception $e) {
    // Log error but don't stop the uninstall process
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Custom Theme Builder uninstall error: ' . $e->getMessage());
    }
}

// Final cleanup - remove any remaining traces
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Clear any remaining caches
wp_cache_flush();
