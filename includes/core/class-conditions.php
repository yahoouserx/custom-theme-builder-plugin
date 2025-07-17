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