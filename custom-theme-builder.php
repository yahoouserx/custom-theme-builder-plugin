<?php
/**
 * Plugin Name: Custom Theme Builder
 * Description: Advanced theme builder functionality for Elementor with template management and conditions system.
 * Version: 1.0.0
 * Author: Custom Theme Builder
 * License: GPL v2 or later
 * Text Domain: custom-theme-builder
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CTB_PLUGIN_FILE', __FILE__);
define('CTB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CTB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CTB_PLUGIN_VERSION', '1.0.0');
define('CTB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once CTB_PLUGIN_PATH . 'includes/class-autoloader.php';
CTB_Autoloader::register();

// Initialize plugin
add_action('plugins_loaded', 'ctb_init_plugin');

/**
 * Initialize the plugin
 */
function ctb_init_plugin() {
    // Check if Elementor is active
    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', 'ctb_elementor_missing_notice');
        return;
    }

    // Check Elementor version
    if (!version_compare(ELEMENTOR_VERSION, '3.0.0', '>=')) {
        add_action('admin_notices', 'ctb_elementor_version_notice');
        return;
    }

    // Initialize plugin
    CTB_Plugin::instance();
}

/**
 * Show notice if Elementor is missing
 */
function ctb_elementor_missing_notice() {
    $message = sprintf(
        esc_html__('Custom Theme Builder requires Elementor to be installed and activated. Please install %s first.', 'custom-theme-builder'),
        '<a href="' . admin_url('plugin-install.php?s=elementor&tab=search&type=term') . '">Elementor</a>'
    );
    
    printf('<div class="notice notice-error"><p>%s</p></div>', $message);
}

/**
 * Show notice if Elementor version is too old
 */
function ctb_elementor_version_notice() {
    $message = sprintf(
        esc_html__('Custom Theme Builder requires Elementor version 3.0.0 or higher. Please update %s.', 'custom-theme-builder'),
        '<a href="' . admin_url('plugins.php') . '">Elementor</a>'
    );
    
    printf('<div class="notice notice-error"><p>%s</p></div>', $message);
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'ctb_activate_plugin');

function ctb_activate_plugin() {
    // Create database tables and set default options
    CTB_Plugin::activate();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'ctb_deactivate_plugin');

function ctb_deactivate_plugin() {
    // Cleanup temporary data
    CTB_Plugin::deactivate();
}
