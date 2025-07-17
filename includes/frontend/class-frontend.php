<?php
/**
 * Frontend functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Frontend {
    
    /**
     * Instance
     */
    private static $_instance = null;
    
    /**
     * Current template ID
     */
    private $current_template_id = null;
    
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
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp', [$this, 'init_template_loader']);
        add_action('wp_head', [$this, 'add_template_css']);
        add_filter('body_class', [$this, 'add_body_classes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Template hooks
        add_action('get_header', [$this, 'replace_header']);
        add_action('get_footer', [$this, 'replace_footer']);
        add_filter('template_include', [$this, 'template_include'], 999);
        
        // Preview functionality

    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        CTB_Template_Loader::instance();
    }
    
    /**
     * Initialize template loader
     */
    public function init_template_loader() {
        CTB_Template_Loader::instance()->init();
    }
    
    /**
     * Add template CSS
     */
    public function add_template_css() {
        $active_templates = CTB_Template_Loader::get_active_templates();
        
        if (empty($active_templates)) {
            return;
        }
        
        echo '<style id="ctb-template-css">';
        
        // Add CSS for hiding default elements when using custom templates
        if (isset($active_templates['header'])) {
            echo 'body.ctb-custom-header .site-header { display: none !important; }';
        }
        
        if (isset($active_templates['footer'])) {
            echo 'body.ctb-custom-footer .site-footer { display: none !important; }';
        }
        
        echo '</style>';
    }
    
    /**
     * Add body classes
     */
    public function add_body_classes($classes) {
        $active_templates = CTB_Template_Loader::get_active_templates();
        
        foreach ($active_templates as $type => $template_id) {
            $classes[] = 'ctb-custom-' . $type;
            $classes[] = 'ctb-template-' . $template_id;
        }
        
        return $classes;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        $active_templates = CTB_Template_Loader::get_active_templates();
        
        if (empty($active_templates)) {
            return;
        }
        
        wp_enqueue_style(
            'ctb-frontend',
            CTB_PLUGIN_URL . 'assets/frontend/frontend.css',
            [],
            CTB_PLUGIN_VERSION
        );
    }
    
    /**
     * Replace header
     */
    public function replace_header() {
        $header_template = CTB_Template_Loader::get_template_for_location('header');
        
        if ($header_template) {
            // Remove default header
            remove_all_actions('wp_head');
            remove_all_actions('wp_print_styles');
            remove_all_actions('wp_print_head_scripts');
            
            // Re-add essential head actions
            add_action('wp_head', 'wp_enqueue_scripts', 1);
            add_action('wp_head', 'wp_print_styles', 8);
            add_action('wp_head', 'wp_print_head_scripts', 9);
            add_action('wp_head', 'wp_head');
            
            // Render custom header
            $this->render_template($header_template);
        }
    }
    
    /**
     * Replace footer
     */
    public function replace_footer() {
        $footer_template = CTB_Template_Loader::get_template_for_location('footer');
        
        if ($footer_template) {
            // Remove default footer
            remove_all_actions('wp_footer');
            
            // Re-add essential footer actions
            add_action('wp_footer', 'wp_print_footer_scripts', 20);
            add_action('wp_footer', 'wp_admin_bar_render', 1000);
            
            // Render custom footer
            $this->render_template($footer_template);
        }
    }
    
    /**
     * Template include filter
     */
    public function template_include($template) {
        // Skip for admin, feeds, and other non-standard requests
        if (is_admin() || is_feed() || is_robots() || is_trackback()) {
            return $template;
        }
        
        $custom_template = CTB_Template_Loader::get_template_for_current_page();
        
        if ($custom_template) {
            // Store the template ID for later use
            $this->current_template_id = $custom_template;
            
            // Use our custom template loader
            return $this->get_plugin_template_path();
        }
        
        return $template;
    }
    
    /**
     * Get plugin template path
     */
    private function get_plugin_template_path() {
        return dirname(__FILE__) . '/template-loader.php';
    }
    
    /**
     * Get current template ID based on conditions
     */
    public static function get_current_template_id() {
        if (self::$_instance && self::$_instance->current_template_id) {
            return self::$_instance->current_template_id;
        }
        
        // Fallback to direct lookup
        return CTB_Template_Loader::get_template_for_current_page();
    }
    
    /**
     * Render template content
     */
    public static function render_template_content($template_id) {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        $content = self::$_instance->get_template_content($template_id);
        return '<div class="ctb-template-content">' . $content . '</div>';
    }
    
    /**
     * Get template content
     */
    private function get_template_content($template_id) {
        $post = get_post($template_id);
        
        if (!$post || $post->post_type !== 'ctb_template') {
            return '';
        }
        
        // Check if Elementor is used
        if (class_exists('\Elementor\Plugin')) {
            $elementor_content = \Elementor\Plugin::instance()->frontend->get_builder_content($template_id);
            
            if (!empty($elementor_content)) {
                return $elementor_content;
            }
        }
        
        // Fallback to post content
        return apply_filters('the_content', $post->post_content);
    }
    
    /**
     * Render template
     */
    private function render_template($template_id) {
        $content = $this->get_template_content($template_id);
        
        if (!empty($content)) {
            echo $content;
        }
    }
    

}
