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
        add_filter('template_include', [$this, 'override_product_template'], 99);
    }
    
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