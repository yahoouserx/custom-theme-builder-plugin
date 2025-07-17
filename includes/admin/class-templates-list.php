<?php
/**
 * Templates list - Modern UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Templates_List {
    
    /**
     * Instance
     */
    private static $_instance = null;
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_ctb_bulk_action', [$this, 'handle_bulk_action']);
        add_action('wp_ajax_ctb_toggle_template_status', [$this, 'toggle_template_status']);
        add_action('wp_ajax_ctb_duplicate_template', [$this, 'duplicate_template']);
        add_action('wp_ajax_ctb_delete_template', [$this, 'delete_template']);
    }
    
    /**
     * Render templates page
     */
    public function render_page() {
        $templates = $this->get_templates();
        $stats = $this->get_template_stats();
        ?>
        <div class="ctb-admin-page">
            <div class="ctb-main-container">
                <?php $this->render_header($stats); ?>
                <?php $this->render_stats($stats); ?>
                <?php $this->render_filters(); ?>
                <?php 
                if (empty($templates)) {
                    $this->render_empty_state();
                } else {
                    $this->render_templates_grid($templates);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render header
     */
    private function render_header($stats) {
        ?>
        <div class="ctb-page-header">
            <div class="ctb-header-content">
                <div class="ctb-header-left">
                    <h1>
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php _e('Custom Templates', 'custom-theme-builder'); ?>
                    </h1>
                    <p><?php _e('Create and manage custom templates with condition-based display rules', 'custom-theme-builder'); ?></p>
                </div>
                <div class="ctb-header-actions">
                    <a href="<?php echo admin_url('edit.php?post_type=ctb_template'); ?>" class="ctb-btn-secondary">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('View All', 'custom-theme-builder'); ?>
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=ctb_template'); ?>" class="ctb-btn-primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add New Template', 'custom-theme-builder'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render stats
     */
    private function render_stats($stats) {
        ?>
        <div class="ctb-stats-section">
            <div class="ctb-stats-grid">
                <div class="ctb-stats-card">
                    <div class="ctb-stats-card-header">
                        <div class="ctb-stats-card-title"><?php _e('Total Templates', 'custom-theme-builder'); ?></div>
                        <div class="ctb-stats-card-icon total">
                            <span class="dashicons dashicons-admin-appearance"></span>
                        </div>
                    </div>
                    <div class="ctb-stats-card-value"><?php echo $stats['total']; ?></div>
                    <div class="ctb-stats-card-label"><?php _e('All templates created', 'custom-theme-builder'); ?></div>
                </div>
                
                <div class="ctb-stats-card">
                    <div class="ctb-stats-card-header">
                        <div class="ctb-stats-card-title"><?php _e('Active Templates', 'custom-theme-builder'); ?></div>
                        <div class="ctb-stats-card-icon active">
                            <span class="dashicons dashicons-yes"></span>
                        </div>
                    </div>
                    <div class="ctb-stats-card-value"><?php echo $stats['active']; ?></div>
                    <div class="ctb-stats-card-label"><?php _e('Currently displayed', 'custom-theme-builder'); ?></div>
                </div>
                
                <div class="ctb-stats-card">
                    <div class="ctb-stats-card-header">
                        <div class="ctb-stats-card-title"><?php _e('Inactive Templates', 'custom-theme-builder'); ?></div>
                        <div class="ctb-stats-card-icon inactive">
                            <span class="dashicons dashicons-pause"></span>
                        </div>
                    </div>
                    <div class="ctb-stats-card-value"><?php echo $stats['inactive']; ?></div>
                    <div class="ctb-stats-card-label"><?php _e('Temporarily disabled', 'custom-theme-builder'); ?></div>
                </div>
                
                <div class="ctb-stats-card">
                    <div class="ctb-stats-card-header">
                        <div class="ctb-stats-card-title"><?php _e('Draft Templates', 'custom-theme-builder'); ?></div>
                        <div class="ctb-stats-card-icon drafts">
                            <span class="dashicons dashicons-edit"></span>
                        </div>
                    </div>
                    <div class="ctb-stats-card-value"><?php echo $stats['drafts']; ?></div>
                    <div class="ctb-stats-card-label"><?php _e('Work in progress', 'custom-theme-builder'); ?></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render filters
     */
    private function render_filters() {
        ?>
        <div class="ctb-filters-section">
            <div class="ctb-filters-header">
                <h3 class="ctb-filters-title"><?php _e('Filter Templates', 'custom-theme-builder'); ?></h3>
            </div>
            <div class="ctb-filters-grid">
                <div class="ctb-filter-group">
                    <label class="ctb-filter-label"><?php _e('Template Type', 'custom-theme-builder'); ?></label>
                    <select class="ctb-filter-select" id="ctb-filter-type">
                        <option value=""><?php _e('All Types', 'custom-theme-builder'); ?></option>
                        <option value="content"><?php _e('Content Templates', 'custom-theme-builder'); ?></option>
                        <option value="full_page"><?php _e('Full Page Templates', 'custom-theme-builder'); ?></option>
                        <option value="header"><?php _e('Header Templates', 'custom-theme-builder'); ?></option>
                        <option value="footer"><?php _e('Footer Templates', 'custom-theme-builder'); ?></option>
                    </select>
                </div>
                
                <div class="ctb-filter-group">
                    <label class="ctb-filter-label"><?php _e('Status', 'custom-theme-builder'); ?></label>
                    <select class="ctb-filter-select" id="ctb-filter-status">
                        <option value=""><?php _e('All Status', 'custom-theme-builder'); ?></option>
                        <option value="active"><?php _e('Active', 'custom-theme-builder'); ?></option>
                        <option value="inactive"><?php _e('Inactive', 'custom-theme-builder'); ?></option>
                        <option value="draft"><?php _e('Draft', 'custom-theme-builder'); ?></option>
                    </select>
                </div>
                
                <div class="ctb-filter-group">
                    <label class="ctb-filter-label"><?php _e('Search', 'custom-theme-builder'); ?></label>
                    <input type="text" class="ctb-filter-input" id="ctb-filter-search" placeholder="<?php _e('Search templates...', 'custom-theme-builder'); ?>">
                </div>
                
                <div class="ctb-filter-group">
                    <label class="ctb-filter-label"><?php _e('Date Range', 'custom-theme-builder'); ?></label>
                    <select class="ctb-filter-select" id="ctb-filter-date">
                        <option value=""><?php _e('All Time', 'custom-theme-builder'); ?></option>
                        <option value="today"><?php _e('Today', 'custom-theme-builder'); ?></option>
                        <option value="week"><?php _e('This Week', 'custom-theme-builder'); ?></option>
                        <option value="month"><?php _e('This Month', 'custom-theme-builder'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render templates grid
     */
    private function render_templates_grid($templates) {
        ?>
        <div class="ctb-templates-section">
            <div class="ctb-templates-header">
                <h3 class="ctb-templates-title"><?php printf(__('Templates (%d)', 'custom-theme-builder'), count($templates)); ?></h3>
                <div class="ctb-bulk-actions">
                    <select class="ctb-bulk-select" id="ctb-bulk-action">
                        <option value=""><?php _e('Bulk Actions', 'custom-theme-builder'); ?></option>
                        <option value="activate"><?php _e('Activate', 'custom-theme-builder'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'custom-theme-builder'); ?></option>
                        <option value="delete"><?php _e('Delete', 'custom-theme-builder'); ?></option>
                    </select>
                    <button class="ctb-btn-small ctb-btn-outline" id="ctb-apply-bulk"><?php _e('Apply', 'custom-theme-builder'); ?></button>
                </div>
            </div>
            
            <div class="ctb-templates-grid" id="ctb-templates-grid">
                <?php foreach ($templates as $template) : ?>
                    <?php $this->render_template_card($template); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render template card
     */
    private function render_template_card($template) {
        $status = get_post_meta($template->ID, '_ctb_template_status', true) ?: 'active';
        $conditions = CTB_Conditions::get_template_conditions($template->ID);
        $template_type = CTB_Template_Loader::get_template_type($template->ID);
        
        ?>
        <div class="ctb-template-card <?php echo $status === 'active' ? 'active' : 'inactive'; ?>" data-template-id="<?php echo $template->ID; ?>">
            <div class="ctb-template-preview">
                <div class="ctb-template-preview-icon">
                    <span class="dashicons dashicons-<?php echo $this->get_template_icon($template_type); ?>"></span>
                </div>
                <div class="ctb-template-status <?php echo $status; ?>">
                    <?php echo ucfirst($status); ?>
                </div>
            </div>
            
            <div class="ctb-template-content">
                <div class="ctb-template-title">
                    <?php echo esc_html($template->post_title); ?>
                    <span class="ctb-template-type"><?php echo ucfirst(str_replace('_', ' ', $template_type)); ?></span>
                </div>
                
                <div class="ctb-template-conditions">
                    <?php if (!empty($conditions)) : ?>
                        <?php foreach (array_slice($conditions, 0, 3) as $condition) : ?>
                            <span class="ctb-condition-tag">
                                <?php echo $this->format_condition_display($condition); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($conditions) > 3) : ?>
                            <span class="ctb-condition-tag">+<?php echo count($conditions) - 3; ?> more</span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="ctb-condition-tag"><?php _e('No conditions', 'custom-theme-builder'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="ctb-template-meta">
                    <div class="ctb-template-date">
                        <?php echo human_time_diff(strtotime($template->post_date), current_time('timestamp')) . ' ago'; ?>
                    </div>
                    <div class="ctb-template-actions">
                        <a href="<?php echo get_edit_post_link($template->ID); ?>" class="ctb-action-btn edit">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Edit', 'custom-theme-builder'); ?>
                        </a>
                        <button class="ctb-action-btn duplicate" data-template-id="<?php echo $template->ID; ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php _e('Duplicate', 'custom-theme-builder'); ?>
                        </button>
                        <button class="ctb-action-btn delete" data-template-id="<?php echo $template->ID; ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Delete', 'custom-theme-builder'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render empty state
     */
    private function render_empty_state() {
        ?>
        <div class="ctb-empty-state">
            <div class="ctb-empty-icon">
                <span class="dashicons dashicons-admin-appearance"></span>
            </div>
            <h2 class="ctb-empty-title"><?php _e('No Templates Found', 'custom-theme-builder'); ?></h2>
            <p class="ctb-empty-description">
                <?php _e('Start building your custom templates with condition-based display rules. Create templates for different parts of your site and control exactly where they appear.', 'custom-theme-builder'); ?>
            </p>
            <div class="ctb-empty-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=ctb_template'); ?>" class="ctb-btn-primary">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Create Your First Template', 'custom-theme-builder'); ?>
                </a>
                <a href="#" class="ctb-btn-secondary" id="ctb-show-help">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Learn More', 'custom-theme-builder'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get templates
     */
    private function get_templates() {
        return get_posts([
            'post_type' => 'ctb_template',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
    }
    
    /**
     * Get template stats
     */
    private function get_template_stats() {
        $templates = $this->get_templates();
        $stats = [
            'total' => count($templates),
            'active' => 0,
            'inactive' => 0,
            'drafts' => 0
        ];
        
        foreach ($templates as $template) {
            if ($template->post_status === 'draft') {
                $stats['drafts']++;
            } else {
                $status = get_post_meta($template->ID, '_ctb_template_status', true) ?: 'active';
                if ($status === 'active') {
                    $stats['active']++;
                } else {
                    $stats['inactive']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Get template icon
     */
    private function get_template_icon($template_type) {
        $icons = [
            'content' => 'admin-post',
            'full_page' => 'admin-page',
            'header' => 'editor-code',
            'footer' => 'editor-code',
            'archive' => 'category'
        ];
        
        return $icons[$template_type] ?? 'admin-appearance';
    }
    
    /**
     * Format condition display
     */
    private function format_condition_display($condition) {
        $type = $condition['type'] ?? '';
        $value = $condition['value'] ?? '';
        
        $labels = [
            'entire_site' => __('Entire Site', 'custom-theme-builder'),
            'front_page' => __('Front Page', 'custom-theme-builder'),
            'single_post' => __('Single Post', 'custom-theme-builder'),
            'page' => __('Page', 'custom-theme-builder'),
            'category' => __('Category', 'custom-theme-builder'),
            'tag' => __('Tag', 'custom-theme-builder'),
            'author' => __('Author', 'custom-theme-builder'),
            'archive' => __('Archive', 'custom-theme-builder'),
            'search_results' => __('Search Results', 'custom-theme-builder'),
            'error_404' => __('404 Page', 'custom-theme-builder'),
            'woocommerce_shop' => __('WooCommerce Shop', 'custom-theme-builder'),
            'woocommerce_product_category' => __('Product Category', 'custom-theme-builder')
        ];
        
        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_action() {
        check_ajax_referer('ctb_bulk_action', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['action']);
        $template_ids = array_map('intval', $_POST['template_ids']);
        
        foreach ($template_ids as $template_id) {
            switch ($action) {
                case 'activate':
                    update_post_meta($template_id, '_ctb_template_status', 'active');
                    break;
                case 'deactivate':
                    update_post_meta($template_id, '_ctb_template_status', 'inactive');
                    break;
                case 'delete':
                    wp_delete_post($template_id, true);
                    break;
            }
        }
        
        wp_send_json_success(['message' => __('Bulk action completed successfully.', 'custom-theme-builder')]);
    }
    
    /**
     * Toggle template status
     */
    public function toggle_template_status() {
        check_ajax_referer('ctb_template_action', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id']);
        $current_status = get_post_meta($template_id, '_ctb_template_status', true) ?: 'active';
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        update_post_meta($template_id, '_ctb_template_status', $new_status);
        
        wp_send_json_success(['status' => $new_status]);
    }
    
    /**
     * Duplicate template
     */
    public function duplicate_template() {
        check_ajax_referer('ctb_template_action', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id']);
        $original_post = get_post($template_id);
        
        if (!$original_post) {
            wp_send_json_error(['message' => __('Template not found.', 'custom-theme-builder')]);
        }
        
        $new_post = array(
            'post_title' => $original_post->post_title . ' (Copy)',
            'post_content' => $original_post->post_content,
            'post_status' => 'draft',
            'post_type' => 'ctb_template',
            'post_author' => get_current_user_id()
        );
        
        $new_post_id = wp_insert_post($new_post);
        
        if (!is_wp_error($new_post_id)) {
            // Copy meta data
            $conditions = CTB_Conditions::get_template_conditions($template_id);
            CTB_Conditions::save_template_conditions($new_post_id, $conditions);
            
            wp_send_json_success(['message' => __('Template duplicated successfully.', 'custom-theme-builder')]);
        } else {
            wp_send_json_error(['message' => __('Failed to duplicate template.', 'custom-theme-builder')]);
        }
    }
    
    /**
     * Delete template
     */
    public function delete_template() {
        check_ajax_referer('ctb_template_action', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id']);
        
        if (wp_delete_post($template_id, true)) {
            wp_send_json_success(['message' => __('Template deleted successfully.', 'custom-theme-builder')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete template.', 'custom-theme-builder')]);
        }
    }
}