<?php
/**
 * Templates list table
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
        add_filter('posts_where', [$this, 'filter_templates_by_type'], 10, 2);
    }
    
    /**
     * Render templates page
     */
    public function render_page() {
        $template_type = isset($_GET['template_type']) ? sanitize_text_field($_GET['template_type']) : '';
        $templates = $this->get_templates($template_type);
        
        if (empty($templates)) {
            $this->render_empty_state();
            return;
        }
        
        ?>
        <?php $this->render_filters(); ?>
        
        <form id="ctb-templates-form" method="post">
            <?php wp_nonce_field('ctb_bulk_action', 'ctb_bulk_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'custom-theme-builder'); ?></option>
                        <option value="activate"><?php _e('Activate', 'custom-theme-builder'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'custom-theme-builder'); ?></option>
                        <option value="delete"><?php _e('Delete', 'custom-theme-builder'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'custom-theme-builder'); ?>" />
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'custom-theme-builder'); ?></label>
                            <input id="cb-select-all-1" type="checkbox" />
                        </td>
                        <th scope="col" class="manage-column column-title"><?php _e('Template', 'custom-theme-builder'); ?></th>
                        <th scope="col" class="manage-column column-type"><?php _e('Type', 'custom-theme-builder'); ?></th>
                        <th scope="col" class="manage-column column-conditions"><?php _e('Conditions', 'custom-theme-builder'); ?></th>
                        <th scope="col" class="manage-column column-status"><?php _e('Status', 'custom-theme-builder'); ?></th>
                        <th scope="col" class="manage-column column-date"><?php _e('Date', 'custom-theme-builder'); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'custom-theme-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="template_ids[]" value="<?php echo esc_attr($template->ID); ?>" />
                                    </th>
                                    <td class="title column-title">
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($template->ID); ?>" class="row-title">
                                                <?php echo esc_html($template->post_title); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo get_edit_post_link($template->ID); ?>"><?php _e('Edit', 'custom-theme-builder'); ?></a> |
                                            </span>
                                            <span class="duplicate">
                                                <a href="#" data-template-id="<?php echo esc_attr($template->ID); ?>" class="ctb-duplicate-template">
                                                    <?php _e('Duplicate', 'custom-theme-builder'); ?>
                                                </a> |
                                            </span>

                                            <span class="trash">
                                                <a href="<?php echo get_delete_post_link($template->ID); ?>" class="submitdelete">
                                                    <?php _e('Delete', 'custom-theme-builder'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="type column-type">
                                        <span class="ctb-type-badge">
                                            <?php echo esc_html($this->get_template_type_label($template->ID)); ?>
                                        </span>
                                    </td>
                                    <td class="conditions column-conditions">
                                        <div class="ctb-conditions-info">
                                            <span class="dashicons dashicons-admin-settings"></span>
                                            <?php echo esc_html($this->get_template_conditions_summary($template->ID)); ?>
                                        </div>
                                    </td>
                                    <td class="status column-status">
                                        <?php echo $this->get_template_status_badge($template->ID); ?>
                                    </td>
                                    <td class="date column-date">
                                        <span class="ctb-date">
                                            <?php echo date_i18n(get_option('date_format'), strtotime($template->post_date)); ?>
                                        </span>
                                    </td>
                                    <td class="actions column-actions">
                                        <a href="<?php echo get_edit_post_link($template->ID); ?>" class="ctb-action-btn primary">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php _e('Edit', 'custom-theme-builder'); ?>
                                        </a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?action=ctb_toggle_status&template_id=' . $template->ID), 'ctb_toggle_status'); ?>" class="ctb-action-btn secondary">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php echo $this->get_template_toggle_text($template->ID); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php
    }
    
    /**
     * Render empty state
     */
    private function render_empty_state() {
        ?>
        <div class="ctb-no-templates">
            <div class="dashicons dashicons-admin-customizer"></div>
            <h3><?php _e('No Templates Yet', 'custom-theme-builder'); ?></h3>
            <p><?php _e('Start building your custom templates with our conditions-based system.', 'custom-theme-builder'); ?></p>
            <a href="<?php echo admin_url('post-new.php?post_type=ctb_template'); ?>" class="button button-primary">
                <?php _e('Create Your First Template', 'custom-theme-builder'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Render filters
     */
    private function render_filters() {
        $current_type = isset($_GET['template_type']) ? sanitize_text_field($_GET['template_type']) : '';
        
        $template_types = [
            '' => __('All Types', 'custom-theme-builder'),
            'header' => __('Header', 'custom-theme-builder'),
            'footer' => __('Footer', 'custom-theme-builder'),
            'single-post' => __('Single Post', 'custom-theme-builder'),
            'archive' => __('Blog Archive', 'custom-theme-builder'),
            'page' => __('Page', 'custom-theme-builder'),
            'single-product' => __('Single Product', 'custom-theme-builder'),
            'shop-archive' => __('Shop Archive', 'custom-theme-builder'),
        ];
        ?>
        <div class="ctb-filters">
            <form method="get">
                <input type="hidden" name="page" value="ctb-templates" />
                
                <select name="template_type" onchange="this.form.submit()">
                    <?php foreach ($template_types as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($current_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get templates
     */
    private function get_templates($template_type = '') {
        $args = [
            'post_type' => 'ctb_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        if ($template_type) {
            $args['meta_query'] = [
                [
                    'key' => '_ctb_template_type',
                    'value' => $template_type,
                    'compare' => '=',
                ],
            ];
        }
        
        return get_posts($args);
    }
    
    /**
     * Get template type label
     */
    private function get_template_type_label($template_id) {
        $type = get_post_meta($template_id, '_ctb_template_type', true);
        
        $types = [
            'header' => __('Header', 'custom-theme-builder'),
            'footer' => __('Footer', 'custom-theme-builder'),
            'single-post' => __('Single Post', 'custom-theme-builder'),
            'archive' => __('Blog Archive', 'custom-theme-builder'),
            'page' => __('Page', 'custom-theme-builder'),
            'single-product' => __('Single Product', 'custom-theme-builder'),
            'shop-archive' => __('Shop Archive', 'custom-theme-builder'),
        ];
        
        return isset($types[$type]) ? $types[$type] : __('Not Set', 'custom-theme-builder');
    }
    
    /**
     * Get template conditions summary
     */
    private function get_template_conditions_summary($template_id) {
        $conditions = CTB_Conditions::get_template_conditions($template_id);
        
        if (empty($conditions)) {
            return __('No conditions set', 'custom-theme-builder');
        }
        
        return sprintf(
            _n('%d condition', '%d conditions', count($conditions), 'custom-theme-builder'),
            count($conditions)
        );
    }
    
    /**
     * Get template status badge
     */
    private function get_template_status_badge($template_id) {
        $status = get_post_meta($template_id, '_ctb_template_status', true);
        
        if ($status === 'active') {
            return '<span class="ctb-status-badge active">' . __('Active', 'custom-theme-builder') . '</span>';
        } else {
            return '<span class="ctb-status-badge inactive">' . __('Inactive', 'custom-theme-builder') . '</span>';
        }
    }
    
    /**
     * Get template toggle text
     */
    private function get_template_toggle_text($template_id) {
        $status = get_post_meta($template_id, '_ctb_template_status', true);
        
        return $status === 'active' ? __('Deactivate', 'custom-theme-builder') : __('Activate', 'custom-theme-builder');
    }
    
    /**
     * Handle bulk action
     */
    public function handle_bulk_action() {
        check_ajax_referer('ctb_bulk_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', 'custom-theme-builder'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        $template_ids = array_map('intval', $_POST['template_ids']);
        
        if (empty($template_ids)) {
            wp_send_json_error(['message' => __('No templates selected', 'custom-theme-builder')]);
        }
        
        switch ($action) {
            case 'activate':
                foreach ($template_ids as $template_id) {
                    update_post_meta($template_id, '_ctb_template_status', 'active');
                }
                wp_send_json_success(['message' => __('Templates activated', 'custom-theme-builder')]);
                break;
                
            case 'deactivate':
                foreach ($template_ids as $template_id) {
                    update_post_meta($template_id, '_ctb_template_status', 'inactive');
                }
                wp_send_json_success(['message' => __('Templates deactivated', 'custom-theme-builder')]);
                break;
                
            case 'delete':
                foreach ($template_ids as $template_id) {
                    wp_delete_post($template_id, true);
                }
                wp_send_json_success(['message' => __('Templates deleted', 'custom-theme-builder')]);
                break;
                
            default:
                wp_send_json_error(['message' => __('Invalid action', 'custom-theme-builder')]);
        }
    }
    
    /**
     * Filter templates by type
     */
    public function filter_templates_by_type($where, $query) {
        if (!is_admin() || !$query->is_main_query()) {
            return $where;
        }
        
        if ($query->get('post_type') !== 'ctb_template') {
            return $where;
        }
        
        if (isset($_GET['template_type']) && $_GET['template_type']) {
            global $wpdb;
            $template_type = sanitize_text_field($_GET['template_type']);
            $where .= $wpdb->prepare(
                " AND {$wpdb->posts}.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_ctb_template_type' 
                    AND meta_value = %s
                )",
                $template_type
            );
        }
        
        return $where;
    }
}
