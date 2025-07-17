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
     * Flag to prevent infinite recursion
     */
    private $loading_template = false;
    
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
        
        // DISABLED TEMPLATE HOOKS - Let pages load normally
        // add_action('get_header', [$this, 'replace_header']);
        // add_action('get_footer', [$this, 'replace_footer']);
        // add_filter('template_include', [$this, 'template_include'], 999);
        
        // Additional header/footer injection hooks
        add_action('wp_body_open', [$this, 'inject_header_template'], 1);
        add_action('wp_footer', [$this, 'inject_footer_template'], 1);
        
        // Product template override
        add_filter('template_include', [$this, 'override_product_template'], 99);
        
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
        
        // Prevent infinite recursion
        if ($this->loading_template) {
            return $template;
        }
        
        // Special handling for WooCommerce single product pages
        if (function_exists('is_product') && is_product()) {
            // Only apply content replacement for single product templates
            $custom_template = CTB_Template_Loader::get_template_for_current_page();
            
            if ($custom_template) {
                $this->current_template_id = $custom_template;
                $template_type = CTB_Template_Loader::get_template_type($custom_template);
                
                // For WooCommerce products, force content replacement to avoid infinite loading
                add_filter('the_content', [$this, 'replace_content'], 999);
                add_filter('single_post_title', [$this, 'maybe_replace_title'], 999);
                
                // Also try to hook into WooCommerce specific hooks
                add_action('woocommerce_single_product_summary', [$this, 'replace_woo_content'], 1);
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            }
            
            return $template;
        }
        
        $custom_template = CTB_Template_Loader::get_template_for_current_page();
        
        if ($custom_template) {
            // Store the template ID for later use
            $this->current_template_id = $custom_template;
            
            // Get template type to determine how to handle it
            $template_type = CTB_Template_Loader::get_template_type($custom_template);
            
            // Only replace full template for full_page types (but not for WooCommerce products)
            if ($template_type === 'full_page' && !function_exists('is_product')) {
                $this->loading_template = true;
                return $this->get_plugin_template_path();
            }
            
            // For content-only templates, use content replacement hooks
            if ($template_type === 'content') {
                add_filter('the_content', [$this, 'replace_content'], 999);
                add_filter('single_post_title', [$this, 'maybe_replace_title'], 999);
            }
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
     * Replace content for content-only templates
     */
    public function replace_content($content) {
        // Only apply to main query and singular posts
        if (!is_main_query() || !is_singular()) {
            return $content;
        }
        
        if ($this->current_template_id) {
            $template_content = $this->get_template_content($this->current_template_id);
            return $template_content ? $template_content : $content;
        }
        
        return $content;
    }
    
    /**
     * Maybe replace title for content templates
     */
    public function maybe_replace_title($title) {
        if ($this->current_template_id) {
            // Only replace title if template explicitly has one
            // For now, keep original title to maintain header/footer integrity
            return $title;
        }
        
        return $title;
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
    
    /**
     * Inject header template content
     */
    public function inject_header_template() {
        $header_template = CTB_Template_Loader::get_template_for_location('header');
        
        if ($header_template) {
            // Add CSS to hide default headers first
            echo '<style>
                .site-header, header.site-header, .main-header, .header, header, .masthead, .site-branding { display: none !important; }
                body.ctb-custom-header .site-header { display: none !important; }
                #ctb-header-template { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; }
                body { padding-top: 80px; }
            </style>';
            
            echo '<div id="ctb-header-template">';
            $this->render_template($header_template);
            echo '</div>';
        }
    }
    
    /**
     * Inject footer template content
     */
    public function inject_footer_template() {
        $footer_template = CTB_Template_Loader::get_template_for_location('footer');
        
        if ($footer_template) {
            // Add CSS to hide default footers
            echo '<style>
                .site-footer, footer.site-footer, .main-footer, .footer, footer { display: none !important; }
                body.ctb-custom-footer .site-footer { display: none !important; }
            </style>';
            
            echo '<div id="ctb-footer-template">';
            $this->render_template($footer_template);
            echo '</div>';
        }
    }
    
    /**
     * Replace WooCommerce content
     */
    public function replace_woo_content() {
        if ($this->current_template_id) {
            $template_content = $this->get_template_content($this->current_template_id);
            if ($template_content) {
                echo '<div class="ctb-woo-template-content">' . $template_content . '</div>';
            }
        }
    }


    
    /**
     * Emergency header template
     */
    public function emergency_header_template() {
        // Get any template with "header" in the title
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'post_title',
                    'value' => 'header',
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        
        // If no templates found, try by title search
        if (empty($templates)) {
            $templates = get_posts([
                'post_type' => 'ctb_template',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                's' => 'header'
            ]);
        }
        
        if (!empty($templates)) {
            $template = $templates[0];
            
            // Add CSS to hide default headers and position custom header
            echo '<style>
                .site-header, header.site-header, .main-header, .header, header, .masthead, .site-branding { display: none !important; }
                #emergency-header { position: relative; top: 0; left: 0; right: 0; z-index: 9999; background: white; }
                body { margin-top: 0; }
            </style>';
            
            // Render custom header
            echo '<div id="emergency-header">';
            $this->render_template($template->ID);
            echo '</div>';
        }
    }
    
    /**
     * Emergency footer template
     */
    public function emergency_footer_template() {
        // Get any template with "footer" in the title
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'footer'
        ]);
        
        if (!empty($templates)) {
            $template = $templates[0];
            
            // Add CSS to hide default footers
            echo '<style>
                .site-footer, footer.site-footer, .main-footer, .footer, footer { display: none !important; }
            </style>';
            
            // Render custom footer
            echo '<div id="emergency-footer">';
            $this->render_template($template->ID);
            echo '</div>';
        }
    }
    
    /**
     * Override product template completely
     */
    public function override_product_template($template) {
        if (!is_singular('product')) {
            return $template;
        }
        
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'product'
        ]);
        
        if (empty($templates)) {
            return $template;
        }
        
        $content = get_post_field('post_content', $templates[0]->ID);
        
        if (empty($content)) {
            return $template;
        }
        
        $temp_template = get_temp_dir() . 'ctb-product-template.php';
        
        $template_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product</title>
</head>
<body>
    <div style="max-width:1200px;margin:50px auto;padding:40px;background:white;box-shadow:0 0 20px rgba(0,0,0,0.1);">
        <div style="border-bottom:3px solid #0073aa;padding-bottom:20px;margin-bottom:30px;">
            <h1 style="margin:0;color:#0073aa;">Custom Product Template</h1>
        </div>
        <div style="background:#f9f9f9;padding:20px;border-left:4px solid #0073aa;">
            ' . $content . '
        </div>
    </div>
</body>
</html>';
        
        file_put_contents($temp_template, $template_content);
        return $temp_template;
    }
    


}
