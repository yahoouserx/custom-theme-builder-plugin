<?php
/**
 * Main Plugin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Plugin {
    
    /**
     * Plugin instance
     */
    private static $_instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core components
        CTB_Templates::instance();
        // CTB_Conditions is a static class, no instance needed
        
        // Initialize admin components
        if (is_admin()) {
            CTB_Admin::instance();
        }
        
        // Initialize frontend components
        if (!is_admin()) {
            CTB_Frontend::instance();
        }
        
        // Initialize Elementor integration
        CTB_Elementor_Integration::instance();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('custom-theme-builder', false, dirname(CTB_PLUGIN_BASENAME) . '/languages');
        
        // Register post type
        $this->register_post_type();
        
        // Flush rewrite rules if needed
        if (get_option('ctb_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('ctb_flush_rewrite_rules');
        }
    }
    
    /**
     * Register custom post type
     */
    private function register_post_type() {
        $args = [
            'labels' => [
                'name' => __('Theme Templates', 'custom-theme-builder'),
                'singular_name' => __('Theme Template', 'custom-theme-builder'),
                'add_new' => __('Add New Template', 'custom-theme-builder'),
                'add_new_item' => __('Add New Template', 'custom-theme-builder'),
                'edit_item' => __('Edit Template', 'custom-theme-builder'),
                'new_item' => __('New Template', 'custom-theme-builder'),
                'view_item' => __('View Template', 'custom-theme-builder'),
                'search_items' => __('Search Templates', 'custom-theme-builder'),
                'not_found' => __('No templates found', 'custom-theme-builder'),
                'not_found_in_trash' => __('No templates found in trash', 'custom-theme-builder'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => ['slug' => 'theme-template'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor', 'elementor'],
            'show_in_rest' => true,
        ];
        
        register_post_type('ctb_template', $args);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Only enqueue if needed
        if ($this->should_load_frontend_assets()) {
            wp_enqueue_style(
                'ctb-frontend',
                CTB_PLUGIN_URL . 'assets/frontend/frontend.css',
                [],
                CTB_PLUGIN_VERSION
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on relevant admin pages
        if ($this->should_load_admin_assets($hook)) {
            wp_enqueue_style(
                'ctb-admin',
                CTB_PLUGIN_URL . 'assets/admin/admin.css',
                [],
                CTB_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'ctb-admin',
                CTB_PLUGIN_URL . 'assets/admin/admin.js',
                ['jquery'],
                CTB_PLUGIN_VERSION,
                true
            );
            
            wp_localize_script('ctb-admin', 'ctb_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ctb_admin_nonce'),
                'strings' => [
                    'confirm_delete' => __('Are you sure you want to delete this template?', 'custom-theme-builder'),
                    'saving' => __('Saving...', 'custom-theme-builder'),
                    'saved' => __('Saved!', 'custom-theme-builder'),
                    'error' => __('Error occurred. Please try again.', 'custom-theme-builder'),
                ],
            ]);
        }
    }
    
    /**
     * Check if frontend assets should be loaded
     */
    private function should_load_frontend_assets() {
        // Check if any custom templates are assigned to current page
        return CTB_Template_Loader::has_custom_template();
    }
    
    /**
     * Check if admin assets should be loaded
     */
    private function should_load_admin_assets($hook) {
        global $post_type;
        
        // Load on template pages
        if ($post_type === 'ctb_template') {
            return true;
        }
        
        // Load on our custom admin pages
        if (strpos($hook, 'ctb_') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Set flag to flush rewrite rules
        update_option('ctb_flush_rewrite_rules', true);
        
        // Create default options
        $default_options = [
            'ctb_cache_enabled' => true,
            'ctb_cache_duration' => 3600,
            'ctb_debug_mode' => false,
        ];
        
        foreach ($default_options as $option => $value) {
            if (!get_option($option)) {
                update_option($option, $value);
            }
        }
        
        // No database tables needed - using post meta
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear cache
        wp_cache_flush();
        
        // Clear transients
        delete_transient('ctb_template_cache');
        delete_transient('ctb_conditions_cache');
    }
    
}
