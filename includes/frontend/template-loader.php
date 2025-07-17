<?php
/**
 * Custom Template Loader - Only for full-page templates
 */

if (!defined('ABSPATH')) { exit; }

// Get the current template ID
$template_id = CTB_Frontend::get_current_template_id();

if ($template_id) {
    // Check if this is a full-page template
    $template_type = CTB_Template_Loader::get_template_type($template_id);
    
    if ($template_type === 'full_page') {
        // Full page replacement - no header/footer from theme
        echo CTB_Frontend::render_template_content($template_id);
    } else {
        // Content-only template - use normal theme structure
        get_header();
        echo CTB_Frontend::render_template_content($template_id);
        get_footer();
    }
} else {
    // Fallback to theme's original template
    $template_hierarchy = [
        'index.php',
        'singular.php',
        'single.php',
        'page.php',
        'archive.php'
    ];
    
    $fallback_found = false;
    foreach ($template_hierarchy as $template_file) {
        $template_path = locate_template($template_file);
        if ($template_path) {
            include $template_path;
            $fallback_found = true;
            break;
        }
    }
    
    if (!$fallback_found) {
        // Final fallback - basic WordPress structure
        get_header();
        echo '<div id="primary" class="content-area"><main id="main" class="site-main">';
        echo '<p>' . __('Template not found.', 'custom-theme-builder') . '</p>';
        echo '</main></div>';
        get_footer();
    }
}