<?php
/**
 * Elementor integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Elementor_Integration {
    
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
        add_action('elementor/init', [$this, 'init_elementor']);
        add_action('elementor/documents/register', [$this, 'register_documents']);
        add_filter('elementor/editor/localize_settings', [$this, 'localize_settings']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_styles']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_scripts']);
        add_filter('elementor/theme/get_location_templates', [$this, 'get_location_templates'], 10, 2);
    }
    
    /**
     * Initialize Elementor
     */
    public function init_elementor() {
        // Register custom document types
        add_action('elementor/documents/register', [$this, 'register_documents']);
        
        // Add theme builder support
        add_action('elementor/theme/register_locations', [$this, 'register_locations']);
        
        // Add custom widgets if needed
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
    }
    
    /**
     * Register documents
     */
    public function register_documents($documents_manager) {
        $documents_manager->register_document_type('ctb_template', CTB_Custom_Document::class);
    }
    
    /**
     * Register locations
     */
    public function register_locations($locations_manager) {
        $locations_manager->register_location('header', [
            'label' => __('Header', 'custom-theme-builder'),
            'multiple' => true,
            'edit_in_content' => false,
        ]);
        
        $locations_manager->register_location('footer', [
            'label' => __('Footer', 'custom-theme-builder'),
            'multiple' => true,
            'edit_in_content' => false,
        ]);
        
        $locations_manager->register_location('single', [
            'label' => __('Single', 'custom-theme-builder'),
            'multiple' => true,
            'edit_in_content' => true,
        ]);
        
        $locations_manager->register_location('archive', [
            'label' => __('Archive', 'custom-theme-builder'),
            'multiple' => true,
            'edit_in_content' => true,
        ]);
    }
    
    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        // Register custom widgets if needed
        // Example: Site title, site logo, post title, etc.
    }
    
    /**
     * Localize settings
     */
    public function localize_settings($settings) {
        $settings['ctb'] = [
            'template_types' => CTB_Templates::get_template_types(),
            'conditions' => $this->get_condition_types(),
        ];
        
        return $settings;
    }
    
    /**
     * Register styles
     */
    public function register_styles() {
        wp_register_style(
            'ctb-elementor',
            CTB_PLUGIN_URL . 'assets/elementor/elementor.css',
            [],
            CTB_PLUGIN_VERSION
        );
    }
    
    /**
     * Register scripts
     */
    public function register_scripts() {
        wp_register_script(
            'ctb-elementor',
            CTB_PLUGIN_URL . 'assets/elementor/elementor.js',
            ['elementor-frontend'],
            CTB_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Get location templates
     */
    public function get_location_templates($templates, $location) {
        $template_type = $this->get_template_type_by_location($location);
        
        if (!$template_type) {
            return $templates;
        }
        
        $ctb_templates = CTB_Templates::get_templates_by_type($template_type);
        
        foreach ($ctb_templates as $template) {
            if ($template['status'] === 'active') {
                $templates[] = $template['id'];
            }
        }
        
        return $templates;
    }
    
    /**
     * Get template type by location
     */
    private function get_template_type_by_location($location) {
        $location_mapping = [
            'header' => 'header',
            'footer' => 'footer',
            'single' => 'single-post',
            'archive' => 'archive',
        ];
        
        return isset($location_mapping[$location]) ? $location_mapping[$location] : false;
    }
    
    /**
     * Get condition types
     */
    private function get_condition_types() {
        return [
            'entire_site' => __('Entire Site', 'custom-theme-builder'),
            'front_page' => __('Front Page', 'custom-theme-builder'),
            'post_type' => __('Post Type', 'custom-theme-builder'),
            'specific_post' => __('Specific Post/Page', 'custom-theme-builder'),
            'category' => __('Category', 'custom-theme-builder'),
            'tag' => __('Tag', 'custom-theme-builder'),
            'user_role' => __('User Role', 'custom-theme-builder'),
            'woocommerce_product_category' => __('Product Category', 'custom-theme-builder'),
        ];
    }
    
    /**
     * Check if template is using Elementor
     */
    public static function is_elementor_template($template_id) {
        return get_post_meta($template_id, '_elementor_edit_mode', true) === 'builder';
    }
    
    /**
     * Get Elementor content
     */
    public static function get_elementor_content($template_id) {
        if (!self::is_elementor_template($template_id)) {
            return '';
        }
        
        if (class_exists('\Elementor\Plugin')) {
            return \Elementor\Plugin::instance()->frontend->get_builder_content($template_id);
        }
        
        return '';
    }
    
    /**
     * Set template as Elementor template
     */
    public static function set_elementor_template($template_id) {
        update_post_meta($template_id, '_elementor_edit_mode', 'builder');
        update_post_meta($template_id, '_elementor_template_type', 'theme');
    }
}
