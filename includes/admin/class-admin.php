<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Admin {
    
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
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_template_meta']);
        add_filter('post_row_actions', [$this, 'add_template_actions'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('wp_ajax_ctb_duplicate_template', [$this, 'ajax_duplicate_template']);
        add_filter('manage_ctb_template_posts_columns', [$this, 'add_template_columns']);
        add_action('manage_ctb_template_posts_custom_column', [$this, 'render_template_columns'], 10, 2);
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        CTB_Templates_List::instance();
        CTB_Conditions_Meta_Box::instance();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Theme Builder', 'custom-theme-builder'),
            __('Theme Builder', 'custom-theme-builder'),
            'manage_options',
            'ctb-templates',
            [$this, 'render_templates_page'],
            'dashicons-layout',
            30
        );
        
        add_submenu_page(
            'ctb-templates',
            __('All Templates', 'custom-theme-builder'),
            __('All Templates', 'custom-theme-builder'),
            'manage_options',
            'ctb-templates',
            [$this, 'render_templates_page']
        );
        
        add_submenu_page(
            'ctb-templates',
            __('Add New Template', 'custom-theme-builder'),
            __('Add New Template', 'custom-theme-builder'),
            'manage_options',
            'post-new.php?post_type=ctb_template'
        );
        
        add_submenu_page(
            'ctb-templates',
            __('Settings', 'custom-theme-builder'),
            __('Settings', 'custom-theme-builder'),
            'manage_options',
            'ctb-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'ctb-template-settings',
            __('Template Settings', 'custom-theme-builder'),
            [$this, 'render_template_settings_meta_box'],
            'ctb_template',
            'side',
            'high'
        );
    }
    
    /**
     * Render template settings meta box
     */
    public function render_template_settings_meta_box($post) {
        wp_nonce_field('ctb_template_meta', 'ctb_template_meta_nonce');
        
        $template_status = get_post_meta($post->ID, '_ctb_template_status', true);
        if (empty($template_status)) {
            $template_status = 'active';
        }
        ?>
        <p>
            <label for="ctb_template_status"><?php _e('Status:', 'custom-theme-builder'); ?></label>
            <select id="ctb_template_status" name="ctb_template_status" style="width: 100%;">
                <option value="active" <?php selected($template_status, 'active'); ?>><?php _e('Active', 'custom-theme-builder'); ?></option>
                <option value="inactive" <?php selected($template_status, 'inactive'); ?>><?php _e('Inactive', 'custom-theme-builder'); ?></option>
            </select>
        </p>
        
        <div class="ctb-template-info">
            <p><strong><?php _e('How it works:', 'custom-theme-builder'); ?></strong></p>
            <ul>
                <li><?php _e('Set conditions below to define where this template appears', 'custom-theme-builder'); ?></li>
                <li><?php _e('Template type is automatically detected from your conditions', 'custom-theme-builder'); ?></li>
                <li><?php _e('No need to manually select template type', 'custom-theme-builder'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Save template meta
     */
    public function save_template_meta($post_id) {
        // Check nonce
        if (!isset($_POST['ctb_template_meta_nonce']) || !wp_verify_nonce($_POST['ctb_template_meta_nonce'], 'ctb_template_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'ctb_template') {
            return;
        }
        
        // Auto-detect template type will be done after conditions are saved via hook
        
        // Save template status
        if (isset($_POST['ctb_template_status'])) {
            update_post_meta($post_id, '_ctb_template_status', sanitize_text_field($_POST['ctb_template_status']));
        }
        
        // Clear cache
        delete_transient('ctb_template_cache');
    }
    
    /**
     * Detect template type from conditions
     */
    private function detect_template_type_from_conditions($conditions) {
        if (empty($conditions)) {
            return false;
        }
        
        // Check for specific condition types to determine template type
        foreach ($conditions as $condition) {
            switch ($condition['type']) {
                case 'front_page':
                case 'page':
                case 'specific_page':
                    return 'page';
                    
                case 'single_post':
                case 'post_type':
                    if (isset($condition['value']) && $condition['value'] === 'product') {
                        return 'single-product';
                    }
                    return 'single-post';
                    
                case 'archive':
                case 'category':
                case 'tag':
                case 'author':
                case 'date':
                    return 'archive';
                    
                case 'woocommerce_shop':
                case 'woocommerce_product_category':
                    return 'shop-archive';
                    
                case 'header':
                    return 'header';
                    
                case 'footer':
                    return 'footer';
                    
                case 'search_results':
                    return 'page';
                    
                case 'error_404':
                    return 'page';
            }
        }
        
        return 'page'; // Default fallback
    }
    
    /**
     * Add template actions
     */
    public function add_template_actions($actions, $post) {
        if ($post->post_type === 'ctb_template') {
            $actions['duplicate'] = sprintf(
                '<a href="#" data-template-id="%d" class="ctb-duplicate-template">%s</a>',
                $post->ID,
                __('Duplicate', 'custom-theme-builder')
            );
            

        }
        
        return $actions;
    }
    
    /**
     * Add template columns
     */
    public function add_template_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['template_type'] = __('Auto-Detected Type', 'custom-theme-builder');
                $new_columns['template_conditions'] = __('Conditions', 'custom-theme-builder');
                $new_columns['template_status'] = __('Status', 'custom-theme-builder');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render template columns
     */
    public function render_template_columns($column, $post_id) {
        switch ($column) {
            case 'template_type':
                $conditions = CTB_Conditions::get_template_conditions($post_id);
                if (!empty($conditions)) {
                    $detected_type = $this->detect_template_type_from_conditions($conditions);
                    echo esc_html($detected_type);
                } else {
                    echo __('No conditions set', 'custom-theme-builder');
                }
                break;
                
            case 'template_conditions':
                $conditions = CTB_Conditions::get_template_conditions($post_id);
                if (empty($conditions)) {
                    echo __('No conditions set', 'custom-theme-builder');
                } else {
                    echo count($conditions) . ' ' . __('conditions', 'custom-theme-builder');
                }
                break;
                
            case 'template_status':
                $status = get_post_meta($post_id, '_ctb_template_status', true);
                if ($status === 'active') {
                    echo '<span class="ctb-status active">' . __('Active', 'custom-theme-builder') . '</span>';
                } else {
                    echo '<span class="ctb-status inactive">' . __('Inactive', 'custom-theme-builder') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'ctb-') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'ctb-admin-style',
            CTB_PLUGIN_URL . 'assets/admin/admin.css',
            [],
            CTB_VERSION
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'ctb-admin-script',
            CTB_PLUGIN_URL . 'assets/admin/admin.js',
            ['jquery'],
            CTB_VERSION,
            true
        );
        
        // Localize script with proper nonce
        wp_localize_script('ctb-admin-script', 'ctb_admin_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ctb_template_action'),
            'bulk_nonce' => wp_create_nonce('ctb_bulk_action'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this template?', 'custom-theme-builder'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected templates?', 'custom-theme-builder'),
                'processing' => __('Processing...', 'custom-theme-builder'),
                'error' => __('An error occurred. Please try again.', 'custom-theme-builder'),
                'success' => __('Operation completed successfully.', 'custom-theme-builder'),
                'no_templates_selected' => __('Please select at least one template.', 'custom-theme-builder'),
                'no_action_selected' => __('Please select an action.', 'custom-theme-builder'),
            ]
        ]);
    }

    /**
     * Render templates page
     */
    public function render_templates_page() {
        // Use the new modern UI templates list
        CTB_Templates_List::instance()->render_page();
    }

    /**
     * Get templates count
     */
    private function get_templates_count() {
        $count = wp_count_posts('ctb_template');
        return $count->publish + $count->draft;
    }

    /**
     * Get active templates count
     */
    private function get_active_templates_count() {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_ctb_template_status' 
             AND pm.meta_value = 'active' 
             AND p.post_type = 'ctb_template'"
        );
        return $count ? $count : 0;
    }

    /**
     * Get inactive templates count
     */
    private function get_inactive_templates_count() {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_ctb_template_status' 
             AND (pm.meta_value = 'inactive' OR pm.meta_value = '') 
             AND p.post_type = 'ctb_template'"
        );
        return $count ? $count : 0;
    }

    /**
     * Get conditions count
     */
    private function get_conditions_count() {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_ctb_conditions' 
             AND pm.meta_value != '' 
             AND p.post_type = 'ctb_template'"
        );
        return $count ? $count : 0;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $cache_enabled = get_option('ctb_cache_enabled', true);
        $cache_duration = get_option('ctb_cache_duration', 3600);
        $debug_mode = get_option('ctb_debug_mode', false);
        ?>
        <div class="wrap">
            <h1><?php _e('Theme Builder Settings', 'custom-theme-builder'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ctb_settings', 'ctb_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Caching', 'custom-theme-builder'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ctb_cache_enabled" value="1" <?php checked($cache_enabled, true); ?> />
                                <?php _e('Enable template caching for better performance', 'custom-theme-builder'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache Duration', 'custom-theme-builder'); ?></th>
                        <td>
                            <input type="number" name="ctb_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="300" max="86400" />
                            <p class="description"><?php _e('Cache duration in seconds (300-86400)', 'custom-theme-builder'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Debug Mode', 'custom-theme-builder'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ctb_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
                                <?php _e('Enable debug mode for troubleshooting', 'custom-theme-builder'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!isset($_POST['ctb_settings_nonce']) || !wp_verify_nonce($_POST['ctb_settings_nonce'], 'ctb_settings')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        update_option('ctb_cache_enabled', isset($_POST['ctb_cache_enabled']));
        update_option('ctb_cache_duration', intval($_POST['ctb_cache_duration']));
        update_option('ctb_debug_mode', isset($_POST['ctb_debug_mode']));
        
        // Clear cache
        delete_transient('ctb_template_cache');
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'custom-theme-builder') . '</p></div>';
    }
    

    
    /**
     * Ajax duplicate template
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('ctb_admin_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id']);
        
        if (!$template_id) {
            wp_die(__('Invalid template ID', 'custom-theme-builder'));
        }
        
        $new_template_id = CTB_Templates::duplicate_template($template_id);
        
        if ($new_template_id) {
            wp_send_json_success(['message' => __('Template duplicated successfully', 'custom-theme-builder')]);
        } else {
            wp_send_json_error(['message' => __('Failed to duplicate template', 'custom-theme-builder')]);
        }
    }
}
