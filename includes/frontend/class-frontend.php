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
        add_action('wp_head', [$this, 'force_header_template'], 0);
        add_action('wp_footer', [$this, 'force_footer_template'], 0);
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
                $full_template = $this->create_full_page_template($matching_template);
                if ($full_template) {
                    return $full_template;
                }
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
        
        // If no conditions set, template applies everywhere (emergency mode)
        if (empty($conditions)) {
            return true;
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
        
        // Get header and footer templates
        $header_content = $this->get_header_template_content();
        $footer_content = $this->get_footer_template_content();
        
        $temp_template = get_temp_dir() . 'ctb-template-' . $template_id . '.php';
        
        $template_content = '<?php
if (!defined("ABSPATH")) exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo("charset"); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(); ?></title>
    <?php wp_head(); ?>
    <style>
        .site-header, header.site-header, .main-header, .header, header { display: none !important; }
        .site-footer, footer.site-footer, .main-footer, .footer, footer { display: none !important; }
        .ctb-template-wrapper { width: 100%; }
    </style>
</head>
<body <?php body_class(); ?>>
    <?php if (!empty("' . addslashes($header_content) . '")) : ?>
    <div class="ctb-header-in-product">
        <?php echo wp_kses_post("' . addslashes($header_content) . '"); ?>
    </div>
    <?php endif; ?>
    
    <div class="ctb-template-wrapper">
        <?php echo wp_kses_post("' . addslashes($content) . '"); ?>
    </div>
    
    <?php if (!empty("' . addslashes($footer_content) . '")) : ?>
    <div class="ctb-footer-in-product">
        <?php echo wp_kses_post("' . addslashes($footer_content) . '"); ?>
    </div>
    <?php endif; ?>
    
    <?php wp_footer(); ?>
</body>
</html>';
        
        file_put_contents($temp_template, $template_content);
        return $temp_template;
    }
    
    private function get_header_template_content() {
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'header'
        ]);
        
        return !empty($templates) ? $this->get_template_content($templates[0]->ID) : '';
    }
    
    private function get_footer_template_content() {
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'footer'
        ]);
        
        return !empty($templates) ? $this->get_template_content($templates[0]->ID) : '';
    }
    
    private function get_template_content($template_id) {
        $post = get_post($template_id);
        return $post ? $post->post_content : '';
    }
    
    public function force_header_template() {
        if (is_admin()) {
            return;
        }
        
        // Find header templates
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'header'
        ]);
        
        if (!empty($templates)) {
            echo '<style>
                .site-header, header.site-header, .main-header, .header, header, 
                .site-branding, .custom-logo-link, .main-navigation, 
                .primary-navigation, .navbar, .top-bar { display: none !important; }
                .ctb-force-header { 
                    position: relative; 
                    z-index: 99999; 
                    width: 100%; 
                    display: block !important;
                }
            </style>';
            
            echo '<div class="ctb-force-header">';
            echo $this->get_template_content($templates[0]->ID);
            echo '</div>';
        }
    }
    
    public function force_footer_template() {
        if (is_admin()) {
            return;
        }
        
        // Find footer templates
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => 'footer'
        ]);
        
        if (!empty($templates)) {
            echo '<style>
                .site-footer, footer.site-footer, .main-footer, .footer, footer,
                .colophon, .site-info, .footer-widgets { display: none !important; }
                .ctb-force-footer { 
                    position: relative; 
                    z-index: 99999; 
                    width: 100%; 
                    display: block !important;
                }
            </style>';
            
            echo '<div class="ctb-force-footer">';
            echo $this->get_template_content($templates[0]->ID);
            echo '</div>';
        }
    }
}