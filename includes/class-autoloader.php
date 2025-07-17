<?php
/**
 * Autoloader for Custom Theme Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Autoloader {
    
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload classes
     */
    public static function autoload($class) {
        // Check if class belongs to this plugin
        if (strpos($class, 'CTB_') !== 0) {
            return;
        }
        
        // Convert class name to file path
        $class_name = str_replace('CTB_', '', $class);
        $class_name = str_replace('_', '-', strtolower($class_name));
        
        // Define possible file paths
        $file_paths = [
            CTB_PLUGIN_PATH . 'includes/class-' . $class_name . '.php',
            CTB_PLUGIN_PATH . 'includes/admin/class-' . $class_name . '.php',
            CTB_PLUGIN_PATH . 'includes/frontend/class-' . $class_name . '.php',
            CTB_PLUGIN_PATH . 'includes/core/class-' . $class_name . '.php',
            CTB_PLUGIN_PATH . 'includes/elementor/class-' . $class_name . '.php',
        ];
        
        // Try to load the file
        foreach ($file_paths as $file_path) {
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }
}
