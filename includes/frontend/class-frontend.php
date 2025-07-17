<?php
if (!defined('ABSPATH')) {
    exit;
}

class CTB_Frontend {
    private static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {
        add_filter('template_include', [$this, 'template_include'], 99);
        add_action('wp_head', [$this, 'inject_header_template'], 1);
        add_action('wp_footer', [$this, 'inject_footer_template'], 999);
    }
    
    public function template_include($template) {
        if (is_admin()) {
            return $template;
        }
        
        // Check for matching templates based on conditions
        $matching_template = $this->get_matching_template();
        
        if ($matching_template) {
            $template_type = $this->get_template_type($matching_template);
            
            // For single product pages, replace entire template
            if (is_singular('product') && $template_type === 'single_product') {
                return $this->create_full_page_template($matching_template);
            }
            
            // For other templates, use content replacement
            if ($template_type !== 'header' && $template_type !== 'footer') {
                add_filter('the_content', function($content) use ($matching_template) {
                    return $this->get_template_content($matching_template);
                });
            }
        }
        
        return $template;
    }
    
    private function get_matching_template() {
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        
        foreach ($templates as $template) {
            if ($this->template_matches_conditions($template->ID)) {
                return $template->ID;
            }
        }
        
        return false;
    }
    
    private function template_matches_conditions($template_id) {
        $conditions = get_post_meta($template_id, '_ctb_conditions', true);
        
        if (empty($conditions)) {
            return false;
        }
        
        return CTB_Conditions::evaluate_conditions($conditions);
    }
    
    private function get_template_type($template_id) {
        // Auto-detect based on title keywords
        $title = get_the_title($template_id);
        $title_lower = strtolower($title);
        
        if (strpos($title_lower, 'header') !== false) return 'header';
        if (strpos($title_lower, 'footer') !== false) return 'footer';
        if (strpos($title_lower, 'product') !== false) return 'single_product';
        if (strpos($title_lower, 'single') !== false) return 'single_post';
        if (strpos($title_lower, 'page') !== false) return 'single_page';
        if (strpos($title_lower, 'archive') !== false) return 'archive';
        if (strpos($title_lower, 'shop') !== false) return 'shop_archive';
        
        return 'single_post'; // default
    }
    
    private function create_full_page_template($template_id) {
        $content = $this->get_template_content($template_id);
        
        if (empty($content)) {
            return false;
        }
        
        $temp_template = get_temp_dir() . 'ctb-template-' . $template_id . '.php';
        
        $template_content = '<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo("charset"); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <div class="ctb-template-wrapper">
        ' . $content . '
    </div>
    <?php wp_footer(); ?>
</body>
</html>';
        
        file_put_contents($temp_template, $template_content);
        return $temp_template;
    }
    
    private function get_template_content($template_id) {
        $post = get_post($template_id);
        return $post ? $post->post_content : '';
    }
    
    public function inject_header_template() {
        $header_template = $this->get_matching_template();
        
        if ($header_template && $this->get_template_type($header_template) === 'header') {
            echo '<style>.site-header, header { display: none !important; }</style>';
            echo '<div class="ctb-header-template">';
            echo $this->get_template_content($header_template);
            echo '</div>';
        }
    }
    
    public function inject_footer_template() {
        $footer_template = $this->get_matching_template();
        
        if ($footer_template && $this->get_template_type($footer_template) === 'footer') {
            echo '<style>.site-footer, footer { display: none !important; }</style>';
            echo '<div class="ctb-footer-template">';
            echo $this->get_template_content($footer_template);
            echo '</div>';
        }
    }
}