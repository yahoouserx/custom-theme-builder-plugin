<?php
/**
 * Conditions management class
 * 
 * Handles template conditions in a simplified way using WordPress meta fields
 * 
 * @package CustomThemeBuilder
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Conditions {
    
    /**
     * Get template conditions
     */
    public static function get_template_conditions($template_id) {
        $conditions = get_post_meta($template_id, '_ctb_conditions', true);
        
        if (!is_array($conditions)) {
            return [];
        }
        
        return $conditions;
    }
    
    /**
     * Save template conditions
     */
    public static function save_template_conditions($template_id, $conditions) {
        if (!is_array($conditions)) {
            $conditions = [];
        }
        
        // Clean up conditions
        $clean_conditions = [];
        foreach ($conditions as $condition) {
            if (empty($condition['type'])) {
                continue;
            }
            
            $clean_conditions[] = [
                'type' => sanitize_text_field($condition['type']),
                'operator' => sanitize_text_field($condition['operator'] ?? 'include'),
                'value' => sanitize_text_field($condition['value'] ?? ''),
            ];
        }
        
        update_post_meta($template_id, '_ctb_conditions', $clean_conditions);
        
        // Clear cache
        self::clear_cache();
        
        return true;
    }
    
    /**
     * Delete template conditions
     */
    public static function delete_template_conditions($template_id) {
        delete_post_meta($template_id, '_ctb_conditions');
        
        // Clear cache
        self::clear_cache();
        
        return true;
    }
    
    /**
     * Evaluate conditions for current page
     */
    public static function evaluate_conditions($conditions) {
        if (empty($conditions)) {
            return false;
        }
        
        foreach ($conditions as $condition) {
            if (empty($condition['type'])) {
                continue;
            }
            
            $operator = $condition['operator'] ?? 'include';
            $value = $condition['value'] ?? '';
            $matches = false;
            
            switch ($condition['type']) {
                case 'post_type':
                    $matches = is_singular($value);
                    break;
                    
                case 'single_post':
                    $matches = is_single();
                    break;
                    
                case 'single_page':
                    $matches = is_page();
                    break;
                    
                case 'front_page':
                    $matches = is_front_page();
                    break;
                    
                case 'home':
                    $matches = is_home();
                    break;
                    
                case 'archive':
                    $matches = is_archive();
                    break;
                    
                case 'category':
                    $matches = is_category($value);
                    break;
                    
                case 'tag':
                    $matches = is_tag($value);
                    break;
                    
                case 'author':
                    $matches = is_author($value);
                    break;
                    
                case 'search':
                    $matches = is_search();
                    break;
                    
                case 'date':
                    $matches = is_date();
                    break;
                    
                case '404':
                    $matches = is_404();
                    break;
                    
                // WooCommerce conditions
                case 'shop':
                    $matches = function_exists('is_shop') && is_shop();
                    break;
                    
                case 'product':
                    $matches = function_exists('is_product') && is_product();
                    break;
                    
                case 'product_category':
                    $matches = function_exists('is_product_category') && is_product_category($value);
                    break;
                    
                case 'product_tag':
                    $matches = function_exists('is_product_tag') && is_product_tag($value);
                    break;
                    
                case 'cart':
                    $matches = function_exists('is_cart') && is_cart();
                    break;
                    
                case 'checkout':
                    $matches = function_exists('is_checkout') && is_checkout();
                    break;
                    
                case 'account':
                    $matches = function_exists('is_account_page') && is_account_page();
                    break;
                    
                default:
                    $matches = false;
                    break;
            }
            
            // Apply operator logic
            if ($operator === 'exclude') {
                $matches = !$matches;
            }
            
            // If any condition matches (OR logic), return true
            if ($matches) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add template condition (for compatibility with existing code)
     */
    public static function add_template_condition($template_id, $condition) {
        $conditions = self::get_template_conditions($template_id);
        $conditions[] = $condition;
        return self::save_template_conditions($template_id, $conditions);
    }
    
    /**
     * Clear template conditions (for compatibility with existing code)
     */
    public static function clear_template_conditions($template_id) {
        return self::delete_template_conditions($template_id);
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        // Clear template cache
        delete_transient('ctb_template_cache');
        
        // Clear any other related caches
        $cache_keys = [
            'ctb_active_templates',
            'ctb_template_conditions',
            'ctb_template_types'
        ];
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
    
}