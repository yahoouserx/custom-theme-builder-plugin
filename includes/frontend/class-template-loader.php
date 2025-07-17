<?php
/**
 * Template loader
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Template_Loader {
    
    /**
     * Instance
     */
    private static $_instance = null;
    
    /**
     * Active templates cache
     */
    private static $active_templates = null;
    
    /**
     * Flag to prevent infinite recursion in template loading
     */
    private static $loading_template = false;
    
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
        // Constructor is private for singleton
    }
    
    /**
     * Initialize
     */
    public function init() {
        $this->load_active_templates();
    }
    
    /**
     * Load active templates (simplified - conditions-based only)
     */
    private function load_active_templates() {
        // This method is kept for backward compatibility
        // The new system uses direct condition evaluation in get_template_for_current_page()
        self::$active_templates = [];
    }
    
    /**
     * Find template for type (legacy method - conditions-based now)
     */
    private function find_template_for_type($type) {
        // Legacy method - no longer used since we moved to condition-based system
        return false;
    }
    
    /**
     * Check if template matches conditions
     */
    private function template_matches_conditions($template_id) {
        $conditions = CTB_Conditions::get_template_conditions($template_id);
        
        if (empty($conditions)) {
            return false;
        }
        
        foreach ($conditions as $condition) {
            if ($this->evaluate_condition($condition)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate condition
     */
    private function evaluate_condition($condition) {
        $type = $condition['type'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $matches = false;
        
        // Skip evaluation if we're already in template loading to prevent recursion
        if (self::$loading_template && in_array($type, ['post_type', 'woocommerce_product_category', 'woocommerce_product_tag'])) {
            return false;
        }
        
        switch ($type) {
            case 'entire_site':
                $matches = true;
                break;
                
            case 'front_page':
                $matches = is_front_page();
                break;
                
            case 'post_type':
                // Special handling for WooCommerce products to avoid infinite loading
                if ($value === 'product') {
                    if (function_exists('is_product') && is_product()) {
                        $matches = true;
                    } elseif (function_exists('is_woocommerce') && is_woocommerce()) {
                        $matches = is_product();
                    } elseif (is_singular('product')) {
                        $matches = true;
                    } else {
                        $matches = false;
                    }
                } else {
                    $matches = is_singular($value) || is_post_type_archive($value);
                }
                
                // Debug logging for WooCommerce products
                if ($value === 'product' && function_exists('error_log')) {
                    error_log('CTB Debug: Evaluating product condition, matches: ' . ($matches ? 'true' : 'false'));
                }
                break;
                
            case 'page':
                $matches = is_page($value);
                break;
                
            case 'single_post':
                $matches = is_single($value);
                break;
                
            case 'specific_post':
                $matches = is_singular() && get_the_ID() == $value;
                break;
                
            case 'category':
                $matches = is_category($value) || (is_single() && has_category($value));
                break;
                
            case 'tag':
                $matches = is_tag($value) || (is_single() && has_tag($value));
                break;
                
            case 'author':
                $matches = is_author($value);
                break;
                
            case 'user_role':
                $matches = is_user_logged_in() && current_user_can($value);
                break;
                
            case 'date':
                switch ($value) {
                    case 'year':
                        $matches = is_year();
                        break;
                    case 'month':
                        $matches = is_month();
                        break;
                    case 'day':
                        $matches = is_day();
                        break;
                    default:
                        $matches = is_date();
                }
                break;
                
            case 'archive':
                switch ($value) {
                    case 'all':
                        $matches = is_archive();
                        break;
                    case 'category':
                        $matches = is_category();
                        break;
                    case 'tag':
                        $matches = is_tag();
                        break;
                    case 'author':
                        $matches = is_author();
                        break;
                    case 'date':
                        $matches = is_date();
                        break;
                    default:
                        $matches = is_archive();
                }
                break;
                
            case 'search_results':
                $matches = is_search();
                break;
                
            case 'error_404':
                $matches = is_404();
                break;
                
            case 'woocommerce_shop':
                if (class_exists('WooCommerce')) {
                    $matches = function_exists('is_shop') && is_shop();
                }
                break;
                
            case 'woocommerce_product_category':
                if (class_exists('WooCommerce')) {
                    $matches = is_product_category($value) || (function_exists('is_product') && is_product() && has_term($value, 'product_cat'));
                }
                break;
                
            case 'woocommerce_product_tag':
                if (class_exists('WooCommerce')) {
                    $matches = is_product_tag($value) || (function_exists('is_product') && is_product() && has_term($value, 'product_tag'));
                }
                break;
                
            case 'woocommerce_cart':
                if (class_exists('WooCommerce') && function_exists('is_cart')) {
                    $matches = is_cart();
                }
                break;
                
            case 'woocommerce_checkout':
                if (class_exists('WooCommerce') && function_exists('is_checkout')) {
                    $matches = is_checkout();
                }
                break;
                
            case 'woocommerce_account':
                if (class_exists('WooCommerce') && function_exists('is_account_page')) {
                    $matches = is_account_page();
                }
                break;
                
            case 'woocommerce_customer_status':
                if (class_exists('WooCommerce')) {
                    $matches = $this->check_customer_status($value);
                }
                break;
                
            case 'taxonomy':
                $matches = is_tax($value);
                break;
                
            case 'custom_field':
                if (strpos($value, '=') !== false) {
                    list($meta_key, $meta_value) = explode('=', $value, 2);
                    $matches = get_post_meta(get_the_ID(), trim($meta_key), true) === trim($meta_value);
                }
                break;
                
            case 'url_parameter':
                if (strpos($value, '=') !== false) {
                    list($param, $param_value) = explode('=', $value, 2);
                    $matches = isset($_GET[trim($param)]) && $_GET[trim($param)] === trim($param_value);
                }
                break;
                
            case 'device_type':
                $matches = $this->check_device_type($value);
                break;
                
            case 'user_status':
                if ($value === 'logged_in') {
                    $matches = is_user_logged_in();
                } elseif ($value === 'logged_out') {
                    $matches = !is_user_logged_in();
                }
                break;
                
            case 'browser':
                $matches = $this->check_browser($value);
                break;
                
            case 'operating_system':
                $matches = $this->check_operating_system($value);
                break;
                
            case 'page_template':
                if (is_page()) {
                    $template = get_page_template_slug();
                    $matches = ($value === 'default' && empty($template)) || ($template === $value);
                }
                break;
                
            case 'post_format':
                if (is_singular()) {
                    $format = get_post_format() ?: 'standard';
                    $matches = $format === $value;
                }
                break;
                
            case 'user_role':
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();
                    $matches = in_array($value, $user->roles);
                }
                break;
                
            case 'attachment':
                $matches = is_attachment();
                break;
                
            case 'privacy_policy':
                $matches = is_privacy_policy();
                break;
                
            case 'has_post_thumbnail':
                if (is_singular()) {
                    $matches = has_post_thumbnail();
                }
                break;
                
            case 'post_word_count':
                if (is_singular()) {
                    $content = get_post_field('post_content');
                    $word_count = str_word_count(strip_tags($content));
                    $matches = $word_count >= (int)$value;
                }
                break;
                
            case 'post_age':
                if (is_singular()) {
                    $post_date = get_the_date('Y-m-d');
                    $days_old = (strtotime('now') - strtotime($post_date)) / (60 * 60 * 24);
                    $matches = $days_old >= (int)$value;
                }
                break;
                
            case 'time_date':
                $current_time = current_time('Y-m-d H:i');
                $matches = strtotime($current_time) >= strtotime($value);
                break;
                
            case 'language':
                $matches = get_locale() === $value;
                break;
                
            case 'referrer':
                $referrer = $_SERVER['HTTP_REFERER'] ?? '';
                $matches = strpos($referrer, $value) !== false;
                break;
                
            case 'custom_post_type_archive':
                $matches = is_post_type_archive($value);
                break;
                
            case 'parent_page':
                if (is_page()) {
                    $parent_id = wp_get_post_parent_id();
                    $matches = $parent_id == $value;
                }
                break;
                
            case 'post_status':
                if (is_singular()) {
                    $matches = get_post_status() === $value;
                }
                break;
                
            case 'comment_status':
                if (is_singular()) {
                    $matches = (comments_open() && $value === 'open') || (!comments_open() && $value === 'closed');
                }
                break;
        }
        
        // Apply operator
        if ($operator === 'exclude') {
            $matches = !$matches;
        }
        
        return $matches;
    }
    
    /**
     * Check device type
     */
    private function check_device_type($device) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        switch ($device) {
            case 'mobile':
                return wp_is_mobile() && !$this->is_tablet($user_agent);
                
            case 'tablet':
                return $this->is_tablet($user_agent);
                
            case 'desktop':
                return !wp_is_mobile();
                
            default:
                return false;
        }
    }
    
    /**
     * Check if tablet
     */
    private function is_tablet($user_agent) {
        return preg_match('/tablet|ipad|playbook|silk/i', $user_agent) && !preg_match('/mobile/i', $user_agent);
    }
    
    /**
     * Check browser
     */
    private function check_browser($browser) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        switch ($browser) {
            case 'chrome':
                return preg_match('/chrome/i', $user_agent) && !preg_match('/edg/i', $user_agent);
            case 'firefox':
                return preg_match('/firefox/i', $user_agent);
            case 'safari':
                return preg_match('/safari/i', $user_agent) && !preg_match('/chrome/i', $user_agent);
            case 'edge':
                return preg_match('/edg/i', $user_agent);
            case 'internet_explorer':
                return preg_match('/msie|trident/i', $user_agent);
            default:
                return false;
        }
    }
    
    /**
     * Check operating system
     */
    private function check_operating_system($os) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        switch ($os) {
            case 'windows':
                return preg_match('/windows/i', $user_agent);
            case 'macos':
                return preg_match('/mac os x/i', $user_agent);
            case 'linux':
                return preg_match('/linux/i', $user_agent) && !preg_match('/android/i', $user_agent);
            case 'android':
                return preg_match('/android/i', $user_agent);
            case 'ios':
                return preg_match('/iphone|ipad|ipod/i', $user_agent);
            default:
                return false;
        }
    }
    
    /**
     * Check WooCommerce customer status
     */
    private function check_customer_status($status) {
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        switch ($status) {
            case 'guest':
                return !is_user_logged_in();
                
            case 'customer':
                return is_user_logged_in();
                
            case 'returning_customer':
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();
                    $customer = new WC_Customer($user->ID);
                    return $customer->get_order_count() > 0;
                }
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Get page context hash
     */
    private function get_page_context_hash() {
        $context = [];
        
        if (is_front_page()) {
            $context[] = 'front_page';
        } elseif (is_home()) {
            $context[] = 'home';
        } elseif (is_single()) {
            $context[] = 'single_' . get_post_type();
            $context[] = 'post_' . get_the_ID();
        } elseif (is_page()) {
            $context[] = 'page';
            $context[] = 'page_' . get_the_ID();
        } elseif (is_category()) {
            $context[] = 'category';
            $context[] = 'category_' . get_queried_object_id();
        } elseif (is_tag()) {
            $context[] = 'tag';
            $context[] = 'tag_' . get_queried_object_id();
        } elseif (is_archive()) {
            $context[] = 'archive';
            $context[] = 'archive_' . get_post_type();
        }
        
        // Add user role
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context[] = 'user_' . implode('_', $user->roles);
        }
        
        return md5(implode('_', $context));
    }
    
    /**
     * Get active templates
     */
    public static function get_active_templates() {
        if (self::$active_templates === null) {
            self::instance()->load_active_templates();
        }
        
        return self::$active_templates;
    }
    
    /**
     * Get template for location
     */
    public static function get_template_for_location($location) {
        // Get all active templates
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ctb_template_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ],
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        $instance = self::instance();
        
        // Check each template's conditions and type
        foreach ($templates as $template) {
            $conditions = CTB_Conditions::get_template_conditions($template->ID);
            
            if (!empty($conditions)) {
                // Check if this template is for the requested location
                $template_type = self::get_template_type($template->ID);
                
                if ($template_type === $location) {
                    // Check if ANY condition matches for this location
                    $any_match = false;
                    foreach ($conditions as $condition) {
                        if ($instance->evaluate_condition($condition)) {
                            $any_match = true;
                            break;
                        }
                    }
                    
                    if ($any_match) {
                        return $template->ID;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get template for current page based on conditions only
     */
    public static function get_template_for_current_page() {
        // Prevent infinite recursion
        if (self::$loading_template) {
            return false;
        }
        
        self::$loading_template = true;
        
        // Get all active templates
        $templates = get_posts([
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ctb_template_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ],
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        $instance = self::instance();
        $result = false;
        
        // Check each template's conditions
        foreach ($templates as $template) {
            $conditions = CTB_Conditions::get_template_conditions($template->ID);
            
            if (!empty($conditions)) {
                // Check if ANY condition matches (OR logic is more user-friendly)
                $any_match = false;
                foreach ($conditions as $condition) {
                    if ($instance->evaluate_condition($condition)) {
                        $any_match = true;
                        break;
                    }
                }
                
                if ($any_match) {
                    $result = $template->ID;
                    break;
                }
            }
        }
        
        // Debug: Log template matching for WooCommerce products
        if (function_exists('is_product') && is_product() && function_exists('error_log')) {
            error_log('CTB Debug: WooCommerce product page detected, template result: ' . ($result ?: 'none'));
            error_log('CTB Debug: Total templates found: ' . count($templates));
            foreach ($templates as $template) {
                $conditions = CTB_Conditions::get_template_conditions($template->ID);
                error_log('CTB Debug: Template ID ' . $template->ID . ' has ' . count($conditions) . ' conditions');
            }
        }
        
        self::$loading_template = false;
        return $result;
    }
    
    /**
     * Check if has custom template
     */
    public static function has_custom_template() {
        $templates = self::get_active_templates();
        
        return !empty($templates);
    }
    
    /**
     * Detect template type based on conditions
     */
    public static function get_template_type($template_id) {
        $conditions = CTB_Conditions::get_template_conditions($template_id);
        
        if (empty($conditions)) {
            return 'content';
        }
        
        // Check template meta or post title for type hints
        $post_title = get_the_title($template_id);
        $template_name = strtolower($post_title);
        
        // Check for specific template types based on title or conditions
        if (strpos($template_name, 'header') !== false) {
            return 'header';
        }
        
        if (strpos($template_name, 'footer') !== false) {
            return 'footer';
        }
        
        // Check for header/footer specific conditions
        foreach ($conditions as $condition) {
            $type = $condition['type'];
            
            // Header templates
            if (in_array($type, ['header'])) {
                return 'header';
            }
            
            // Footer templates  
            if (in_array($type, ['footer'])) {
                return 'footer';
            }
            
            // Full page templates (replace entire page)
            if (in_array($type, ['entire_site', 'front_page', 'error_404', 'search_results'])) {
                return 'full_page';
            }
            
            // Archive templates (full page)
            if (in_array($type, ['archive', 'category', 'tag', 'author', 'date', 'woocommerce_shop', 'woocommerce_product_category'])) {
                return 'full_page';
            }
        }
        
        // Default to content replacement for single posts, pages, etc.
        return 'content';
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        global $wpdb;
        
        // Delete all cached templates
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ctb_active_templates_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ctb_active_templates_%'");
        
        // Reset instance cache
        self::$active_templates = null;
    }
}
