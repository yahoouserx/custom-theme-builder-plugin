<?php
/**
 * Custom Template Loader
 */

if (!defined('ABSPATH')) { exit; }

// Get the current template ID
$template_id = CTB_Frontend::get_current_template_id();

if ($template_id) {
    get_header();
    echo CTB_Frontend::render_template_content($template_id);
    get_footer();
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