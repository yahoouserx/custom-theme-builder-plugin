<?php
/**
 * Templates management
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Templates {
    
    /**
     * Instance
     */
    private static $_instance = null;
    
    /**
     * Get instance
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
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('save_post', [$this, 'clear_cache_on_save']);
        add_action('wp_ajax_ctb_get_template_data', [$this, 'ajax_get_template_data']);
        add_action('wp_ajax_ctb_save_template_data', [$this, 'ajax_save_template_data']);
    }
    
    /**
     * Get template types
     */
    public static function get_template_types() {
        return [
            'header' => [
                'title' => __('Header', 'custom-theme-builder'),
                'description' => __('Custom header template', 'custom-theme-builder'),
                'icon' => 'dashicons-admin-appearance',
            ],
            'footer' => [
                'title' => __('Footer', 'custom-theme-builder'),
                'description' => __('Custom footer template', 'custom-theme-builder'),
                'icon' => 'dashicons-admin-appearance',
            ],
            'single-post' => [
                'title' => __('Single Post', 'custom-theme-builder'),
                'description' => __('Template for single blog posts', 'custom-theme-builder'),
                'icon' => 'dashicons-admin-post',
            ],
            'archive' => [
                'title' => __('Blog Archive', 'custom-theme-builder'),
                'description' => __('Template for blog archives', 'custom-theme-builder'),
                'icon' => 'dashicons-admin-post',
            ],
            'page' => [
                'title' => __('Page', 'custom-theme-builder'),
                'description' => __('Template for static pages', 'custom-theme-builder'),
                'icon' => 'dashicons-admin-page',
            ],
            'single-product' => [
                'title' => __('Single Product', 'custom-theme-builder'),
                'description' => __('Template for single product pages', 'custom-theme-builder'),
                'icon' => 'dashicons-products',
            ],
            'shop-archive' => [
                'title' => __('Shop Archive', 'custom-theme-builder'),
                'description' => __('Template for shop archives', 'custom-theme-builder'),
                'icon' => 'dashicons-store',
            ],
        ];
    }
    
    /**
     * Get template by ID
     */
    public static function get_template($template_id) {
        $post = get_post($template_id);
        
        if (!$post || $post->post_type !== 'ctb_template') {
            return false;
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'type' => get_post_meta($post->ID, '_ctb_template_type', true),
            'status' => get_post_meta($post->ID, '_ctb_template_status', true),
            'conditions' => CTB_Conditions::get_template_conditions($post->ID),
            'created' => $post->post_date,
            'modified' => $post->post_modified,
        ];
    }
    
    /**
     * Get templates by type
     */
    public static function get_templates_by_type($type) {
        $posts = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ctb_template_type',
                    'value' => $type,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);
        
        $templates = [];
        
        foreach ($posts as $post) {
            $templates[] = self::get_template($post->ID);
        }
        
        return $templates;
    }
    
    /**
     * Create template
     */
    public static function create_template($args) {
        $defaults = [
            'title' => '',
            'content' => '',
            'type' => '',
            'status' => 'inactive',
            'conditions' => [],
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Validate required fields
        if (empty($args['title']) || empty($args['type'])) {
            return new WP_Error('missing_data', __('Title and type are required', 'custom-theme-builder'));
        }
        
        // Create post
        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($args['title']),
            'post_content' => $args['content'],
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
        ]);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Save meta
        update_post_meta($post_id, '_ctb_template_type', sanitize_text_field($args['type']));
        update_post_meta($post_id, '_ctb_template_status', sanitize_text_field($args['status']));
        
        // Save conditions
        if (!empty($args['conditions'])) {
            foreach ($args['conditions'] as $condition) {
                CTB_Conditions::add_template_condition($post_id, $condition);
            }
        }
        
        // Clear cache
        self::clear_cache();
        
        return $post_id;
    }
    
    /**
     * Update template
     */
    public static function update_template($template_id, $args) {
        $template = self::get_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found', 'custom-theme-builder'));
        }
        
        // Update post
        $post_data = [
            'ID' => $template_id,
        ];
        
        if (isset($args['title'])) {
            $post_data['post_title'] = sanitize_text_field($args['title']);
        }
        
        if (isset($args['content'])) {
            $post_data['post_content'] = $args['content'];
        }
        
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update meta
        if (isset($args['type'])) {
            update_post_meta($template_id, '_ctb_template_type', sanitize_text_field($args['type']));
        }
        
        if (isset($args['status'])) {
            update_post_meta($template_id, '_ctb_template_status', sanitize_text_field($args['status']));
        }
        
        // Update conditions
        if (isset($args['conditions'])) {
            CTB_Conditions::clear_template_conditions($template_id);
            
            foreach ($args['conditions'] as $condition) {
                CTB_Conditions::add_template_condition($template_id, $condition);
            }
        }
        
        // Clear cache
        self::clear_cache();
        
        return $template_id;
    }
    
    /**
     * Delete template
     */
    public static function delete_template($template_id) {
        $template = self::get_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found', 'custom-theme-builder'));
        }
        
        // Delete conditions
        CTB_Conditions::clear_template_conditions($template_id);
        
        // Delete post
        $result = wp_delete_post($template_id, true);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete template', 'custom-theme-builder'));
        }
        
        // Clear cache
        self::clear_cache();
        
        return true;
    }
    
    /**
     * Duplicate template
     */
    public static function duplicate_template($template_id) {
        $template = self::get_template($template_id);
        
        if (!$template) {
            return false;
        }
        
        // Create new template
        $new_template_id = self::create_template([
            'title' => $template['title'] . ' (Copy)',
            'content' => $template['content'],
            'type' => $template['type'],
            'status' => 'inactive',
            'conditions' => $template['conditions'],
        ]);
        
        if (is_wp_error($new_template_id)) {
            return false;
        }
        
        return $new_template_id;
    }
    
    /**
     * Get template statistics
     */
    public static function get_template_stats() {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'by_type' => [],
        ];
        
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);
        
        $stats['total'] = count($templates);
        
        foreach ($templates as $template) {
            $status = get_post_meta($template->ID, '_ctb_template_status', true);
            $type = get_post_meta($template->ID, '_ctb_template_type', true);
            
            if ($status === 'active') {
                $stats['active']++;
            } else {
                $stats['inactive']++;
            }
            
            if ($type) {
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        CTB_Template_Loader::clear_cache();
        delete_transient('ctb_template_cache');
    }
    
    /**
     * Clear cache on save
     */
    public function clear_cache_on_save($post_id) {
        if (get_post_type($post_id) === 'ctb_template') {
            self::clear_cache();
        }
    }
    
    /**
     * Ajax get template data
     */
    public function ajax_get_template_data() {
        check_ajax_referer('ctb_admin_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id']);
        
        if (!$template_id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'custom-theme-builder')]);
        }
        
        $template = self::get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(['message' => __('Template not found', 'custom-theme-builder')]);
        }
        
        wp_send_json_success(['template' => $template]);
    }
    
    /**
     * Ajax save template data
     */
    public function ajax_save_template_data() {
        check_ajax_referer('ctb_admin_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id']);
        $template_data = $_POST['template_data'];
        
        if (!$template_id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'custom-theme-builder')]);
        }
        
        $result = self::update_template($template_id, $template_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Template saved successfully', 'custom-theme-builder')]);
    }
}
