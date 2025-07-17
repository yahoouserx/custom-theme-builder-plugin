<?php
/**
 * Custom Elementor document
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Core\DocumentTypes\Post;
use Elementor\Modules\Library\Documents\Library_Document;

class CTB_Custom_Document extends Library_Document {
    
    /**
     * Get document properties
     */
    public static function get_properties() {
        $properties = parent::get_properties();
        
        $properties['support_kit'] = true;
        $properties['show_in_finder'] = true;
        $properties['show_on_admin_bar'] = true;
        $properties['edit_capability'] = 'edit_theme_options';
        
        return $properties;
    }
    
    /**
     * Get document name
     */
    public function get_name() {
        return 'ctb_template';
    }
    
    /**
     * Get document title
     */
    public static function get_title() {
        return __('Theme Template', 'custom-theme-builder');
    }
    
    /**
     * Get document type
     */
    public static function get_type() {
        return 'ctb_template';
    }
    
    /**
     * Get document icon
     */
    public function get_icon() {
        return 'eicon-theme-builder';
    }
    
    /**
     * Get document categories
     */
    public function get_categories() {
        return ['theme'];
    }
    
    /**
     * Get document keywords
     */
    public function get_keywords() {
        return ['theme', 'template', 'header', 'footer', 'single', 'archive'];
    }
    
    /**
     * Get document location
     */
    public function get_location() {
        $template_type = get_post_meta($this->get_main_id(), '_ctb_template_type', true);
        
        $location_mapping = [
            'header' => 'header',
            'footer' => 'footer',
            'single-post' => 'single',
            'archive' => 'archive',
            'page' => 'single',
            'single-product' => 'single',
            'shop-archive' => 'archive',
        ];
        
        return isset($location_mapping[$template_type]) ? $location_mapping[$template_type] : 'single';
    }
    
    /**
     * Get document remote library config
     */
    public function get_remote_library_config() {
        $config = parent::get_remote_library_config();
        
        $config['category'] = 'theme';
        $config['type'] = 'theme';
        $config['default_route'] = 'templates/theme';
        
        return $config;
    }
    

    
    /**
     * Get document edit URL
     */
    public function get_edit_url() {
        $template_id = $this->get_main_id();
        
        return add_query_arg([
            'post' => $template_id,
            'action' => 'elementor',
        ], admin_url('post.php'));
    }
    
    /**
     * Get document CSS wrapper selector
     */
    public function get_css_wrapper_selector() {
        return 'body.elementor-template-' . $this->get_main_id();
    }
    
    /**
     * Get document container attributes
     */
    public function get_container_attributes() {
        $attributes = parent::get_container_attributes();
        
        $template_type = get_post_meta($this->get_main_id(), '_ctb_template_type', true);
        
        $attributes['class'] .= ' ctb-template ctb-template-' . $template_type;
        
        return $attributes;
    }
    
    /**
     * Get document meta
     */
    protected function get_remote_library_type() {
        return 'theme';
    }
    
    /**
     * Save document
     */
    public function save($data) {
        $result = parent::save($data);
        
        if ($result) {
            // Clear cache after saving
            CTB_Templates::clear_cache();
        }
        
        return $result;
    }
    
    /**
     * Get document auto-save data
     */
    public function get_autosave_data() {
        $data = parent::get_autosave_data();
        
        // Add custom data for auto-save
        $data['template_type'] = get_post_meta($this->get_main_id(), '_ctb_template_type', true);
        $data['template_status'] = get_post_meta($this->get_main_id(), '_ctb_template_status', true);
        
        return $data;
    }
    
    /**
     * Get document admin columns
     */
    public function get_admin_columns_keys() {
        return [
            'template_type' => __('Type', 'custom-theme-builder'),
            'template_conditions' => __('Conditions', 'custom-theme-builder'),
            'template_status' => __('Status', 'custom-theme-builder'),
        ];
    }
    
    /**
     * Get document admin column value
     */
    public function get_admin_column_value($column_name) {
        switch ($column_name) {
            case 'template_type':
                $type = get_post_meta($this->get_main_id(), '_ctb_template_type', true);
                $types = CTB_Templates::get_template_types();
                return isset($types[$type]) ? $types[$type]['title'] : __('Not Set', 'custom-theme-builder');
                
            case 'template_conditions':
                $conditions = CTB_Conditions::get_template_conditions($this->get_main_id());
                return count($conditions) . ' ' . __('conditions', 'custom-theme-builder');
                
            case 'template_status':
                $status = get_post_meta($this->get_main_id(), '_ctb_template_status', true);
                return $status === 'active' ? __('Active', 'custom-theme-builder') : __('Inactive', 'custom-theme-builder');
        }
        
        return '';
    }
    
    /**
     * Print document elements
     */
    public function print_elements($elements_data = null) {
        if (null === $elements_data) {
            $elements_data = $this->get_elements_data();
        }
        
        // Add template wrapper
        echo '<div class="ctb-template-wrapper">';
        parent::print_elements($elements_data);
        echo '</div>';
    }
    
    /**
     * Get document export data
     */
    public function get_export_data() {
        $data = parent::get_export_data();
        
        // Add template-specific data
        $data['template_type'] = get_post_meta($this->get_main_id(), '_ctb_template_type', true);
        $data['template_conditions'] = CTB_Conditions::get_template_conditions($this->get_main_id());
        
        return $data;
    }
    
    /**
     * Import document data
     */
    public function import($data) {
        $result = parent::import($data);
        
        if ($result && isset($data['template_type'])) {
            update_post_meta($this->get_main_id(), '_ctb_template_type', $data['template_type']);
        }
        
        if ($result && isset($data['template_conditions'])) {
            CTB_Conditions::clear_template_conditions($this->get_main_id());
            
            foreach ($data['template_conditions'] as $condition) {
                CTB_Conditions::add_template_condition($this->get_main_id(), $condition);
            }
        }
        
        return $result;
    }
}
