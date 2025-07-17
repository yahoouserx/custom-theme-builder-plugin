<?php
/**
 * Conditions meta box
 */

if (!defined('ABSPATH')) {
    exit;
}

class CTB_Conditions_Meta_Box {
    
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
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_conditions']);
        add_action('wp_ajax_ctb_add_condition', [$this, 'ajax_add_condition']);
        add_action('wp_ajax_ctb_get_condition_options', [$this, 'ajax_get_condition_options']);
        add_action('wp_ajax_ctb_get_condition_value_field', [$this, 'ajax_get_condition_value_field']);
    }
    
    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'ctb-conditions',
            __('Display Conditions', 'custom-theme-builder'),
            [$this, 'render_meta_box'],
            'ctb_template',
            'normal',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('ctb_conditions_meta', 'ctb_conditions_nonce');
        
        $conditions = CTB_Conditions::get_template_conditions($post->ID);
        ?>
        <div id="ctb-conditions-wrapper">
            <p><?php _e('Set conditions to determine where this template should be displayed.', 'custom-theme-builder'); ?></p>
            
            <div id="ctb-conditions-list">
                <?php if (empty($conditions)) : ?>
                    <div class="ctb-no-conditions">
                        <p><?php _e('No conditions set. This template will not be displayed anywhere.', 'custom-theme-builder'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($conditions as $index => $condition) : ?>
                        <?php $this->render_condition_row($condition, $index); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="ctb-condition-actions">
                <button type="button" class="button" id="ctb-add-condition">
                    <?php _e('Add Condition', 'custom-theme-builder'); ?>
                </button>
                <button type="button" class="button" id="ctb-add-condition-group">
                    <?php _e('Add Condition Group', 'custom-theme-builder'); ?>
                </button>
            </div>
        </div>
        
        <script type="text/html" id="tmpl-ctb-condition-row">
            <?php $this->render_condition_template(); ?>
        </script>
        <?php
    }
    
    /**
     * Render condition row
     */
    private function render_condition_row($condition, $index) {
        $condition_types = $this->get_condition_types();
        ?>
        <div class="ctb-condition-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="ctb-condition-controls">
                <select name="ctb_conditions[<?php echo esc_attr($index); ?>][type]" class="ctb-condition-type">
                    <option value=""><?php _e('Select Condition', 'custom-theme-builder'); ?></option>
                    <?php foreach ($condition_types as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($condition['type'], $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="ctb_conditions[<?php echo esc_attr($index); ?>][operator]" class="ctb-condition-operator">
                    <option value="include" <?php selected($condition['operator'], 'include'); ?>><?php _e('Include', 'custom-theme-builder'); ?></option>
                    <option value="exclude" <?php selected($condition['operator'], 'exclude'); ?>><?php _e('Exclude', 'custom-theme-builder'); ?></option>
                </select>
                
                <div class="ctb-condition-value">
                    <?php $this->render_condition_value_field($condition, $index); ?>
                </div>
                
                <button type="button" class="button ctb-remove-condition" aria-label="<?php _e('Remove condition', 'custom-theme-builder'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render condition value field
     */
    private function render_condition_value_field($condition, $index) {
        $type = $condition['type'];
        $value = $condition['value'];
        
        switch ($type) {
            case 'entire_site':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="all" />';
                echo '<span>' . __('Entire Site', 'custom-theme-builder') . '</span>';
                break;
                
            case 'front_page':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="front" />';
                echo '<span>' . __('Front Page', 'custom-theme-builder') . '</span>';
                break;
                
            case 'post_type':
                $post_types = get_post_types(['public' => true], 'objects');
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($post_types as $post_type) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($post_type->name),
                        selected($value, $post_type->name, false),
                        esc_html($post_type->labels->name)
                    );
                }
                echo '</select>';
                break;
                
            case 'specific_post':
                $posts = get_posts(['post_type' => 'any', 'posts_per_page' => 100]);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($posts as $post) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($post->ID),
                        selected($value, $post->ID, false),
                        esc_html($post->post_title)
                    );
                }
                echo '</select>';
                break;
                
            case 'category':
                $categories = get_categories(['hide_empty' => false]);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($categories as $category) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($category->term_id),
                        selected($value, $category->term_id, false),
                        esc_html($category->name)
                    );
                }
                echo '</select>';
                break;
                
            case 'tag':
                $tags = get_tags(['hide_empty' => false]);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($tags as $tag) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($tag->term_id),
                        selected($value, $tag->term_id, false),
                        esc_html($tag->name)
                    );
                }
                echo '</select>';
                break;
                
            case 'user_role':
                $roles = wp_roles()->get_names();
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($roles as $role_key => $role_name) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($role_key),
                        selected($value, $role_key, false),
                        esc_html($role_name)
                    );
                }
                echo '</select>';
                break;
                
            case 'page':
                $pages = get_pages(['post_status' => 'publish']);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($pages as $page) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($page->ID),
                        selected($value, $page->ID, false),
                        esc_html($page->post_title)
                    );
                }
                echo '</select>';
                break;
                
            case 'single_post':
                $posts = get_posts(['post_type' => 'post', 'posts_per_page' => 50, 'post_status' => 'publish']);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($posts as $post) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($post->ID),
                        selected($value, $post->ID, false),
                        esc_html($post->post_title)
                    );
                }
                echo '</select>';
                break;
                
            case 'author':
                $authors = get_users(['who' => 'authors']);
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($authors as $author) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($author->ID),
                        selected($value, $author->ID, false),
                        esc_html($author->display_name)
                    );
                }
                echo '</select>';
                break;
                
            case 'date':
                $options = [
                    'year' => __('Year Archive', 'custom-theme-builder'),
                    'month' => __('Month Archive', 'custom-theme-builder'),
                    'day' => __('Day Archive', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'archive':
                $options = [
                    'all' => __('All Archives', 'custom-theme-builder'),
                    'category' => __('Category Archive', 'custom-theme-builder'),
                    'tag' => __('Tag Archive', 'custom-theme-builder'),
                    'author' => __('Author Archive', 'custom-theme-builder'),
                    'date' => __('Date Archive', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'search_results':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="search" />';
                echo '<span>' . __('Search Results', 'custom-theme-builder') . '</span>';
                break;
                
            case 'error_404':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="404" />';
                echo '<span>' . __('404 Page', 'custom-theme-builder') . '</span>';
                break;
                
            case 'woocommerce_shop':
                if (class_exists('WooCommerce')) {
                    echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="shop" />';
                    echo '<span>' . __('Shop Page', 'custom-theme-builder') . '</span>';
                }
                break;
                
            case 'woocommerce_product_category':
                if (class_exists('WooCommerce')) {
                    $terms = get_terms([
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                    ]);
                    echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                    foreach ($terms as $term) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($term->term_id),
                            selected($value, $term->term_id, false),
                            esc_html($term->name)
                        );
                    }
                    echo '</select>';
                }
                break;
                
            case 'woocommerce_product_tag':
                if (class_exists('WooCommerce')) {
                    $terms = get_terms([
                        'taxonomy' => 'product_tag',
                        'hide_empty' => false,
                    ]);
                    echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                    foreach ($terms as $term) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($term->term_id),
                            selected($value, $term->term_id, false),
                            esc_html($term->name)
                        );
                    }
                    echo '</select>';
                }
                break;
                
            case 'taxonomy':
                $taxonomies = get_taxonomies(['public' => true], 'objects');
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($taxonomies as $taxonomy) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($taxonomy->name),
                        selected($value, $taxonomy->name, false),
                        esc_html($taxonomy->labels->name)
                    );
                }
                echo '</select>';
                break;
                
            case 'custom_field':
                echo '<input type="text" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="meta_key=meta_value" />';
                break;
                
            case 'url_parameter':
                echo '<input type="text" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="param=value" />';
                break;
                
            case 'device_type':
                $options = [
                    'mobile' => __('Mobile', 'custom-theme-builder'),
                    'tablet' => __('Tablet', 'custom-theme-builder'),
                    'desktop' => __('Desktop', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'user_status':
                $options = [
                    'logged_in' => __('Logged In', 'custom-theme-builder'),
                    'logged_out' => __('Logged Out', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'browser':
                $options = [
                    'chrome' => __('Chrome', 'custom-theme-builder'),
                    'firefox' => __('Firefox', 'custom-theme-builder'),
                    'safari' => __('Safari', 'custom-theme-builder'),
                    'edge' => __('Edge', 'custom-theme-builder'),
                    'internet_explorer' => __('Internet Explorer', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'operating_system':
                $options = [
                    'windows' => __('Windows', 'custom-theme-builder'),
                    'macos' => __('macOS', 'custom-theme-builder'),
                    'linux' => __('Linux', 'custom-theme-builder'),
                    'android' => __('Android', 'custom-theme-builder'),
                    'ios' => __('iOS', 'custom-theme-builder'),
                ];
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($options as $key => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($key),
                        selected($value, $key, false),
                        esc_html($label)
                    );
                }
                echo '</select>';
                break;
                
            case 'page_template':
                $templates = get_page_templates();
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                echo '<option value="default" ' . selected($value, 'default', false) . '>' . __('Default Template', 'custom-theme-builder') . '</option>';
                foreach ($templates as $template_name => $template_file) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($template_file),
                        selected($value, $template_file, false),
                        esc_html($template_name)
                    );
                }
                echo '</select>';
                break;
                
            case 'post_format':
                $formats = get_post_format_strings();
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($formats as $format => $name) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($format),
                        selected($value, $format, false),
                        esc_html($name)
                    );
                }
                echo '</select>';
                break;
                
            case 'user_role':
                $roles = wp_roles()->roles;
                echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                foreach ($roles as $role => $details) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($role),
                        selected($value, $role, false),
                        esc_html($details['name'])
                    );
                }
                echo '</select>';
                break;
                
            case 'attachment':
            case 'privacy_policy':
            case 'has_post_thumbnail':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="yes" />';
                echo '<span>' . __('Enabled', 'custom-theme-builder') . '</span>';
                break;
                
            case 'post_word_count':
                echo '<input type="number" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="' . __('Min word count', 'custom-theme-builder') . '" />';
                break;
                
            case 'post_age':
                echo '<input type="number" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="' . __('Days old', 'custom-theme-builder') . '" />';
                break;
                
            case 'time_date':
                echo '<input type="text" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="' . __('YYYY-MM-DD HH:MM', 'custom-theme-builder') . '" />';
                break;
                
            case 'language':
                if (function_exists('get_available_languages')) {
                    $languages = get_available_languages();
                    echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                    echo '<option value="' . get_locale() . '" ' . selected($value, get_locale(), false) . '>' . __('Default Language', 'custom-theme-builder') . '</option>';
                    foreach ($languages as $language) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($language),
                            selected($value, $language, false),
                            esc_html($language)
                        );
                    }
                    echo '</select>';
                } else {
                    echo '<input type="text" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" placeholder="' . __('Language code', 'custom-theme-builder') . '" />';
                }
                break;
                
            case 'woocommerce_cart':
            case 'woocommerce_checkout':
            case 'woocommerce_account':
                echo '<input type="hidden" name="ctb_conditions[' . esc_attr($index) . '][value]" value="yes" />';
                echo '<span>' . __('Enabled', 'custom-theme-builder') . '</span>';
                break;
                
            case 'woocommerce_customer_status':
                if (class_exists('WooCommerce')) {
                    $options = [
                        'guest' => __('Guest', 'custom-theme-builder'),
                        'customer' => __('Customer', 'custom-theme-builder'),
                        'returning_customer' => __('Returning Customer', 'custom-theme-builder'),
                    ];
                    echo '<select name="ctb_conditions[' . esc_attr($index) . '][value]">';
                    foreach ($options as $key => $label) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($key),
                            selected($value, $key, false),
                            esc_html($label)
                        );
                    }
                    echo '</select>';
                }
                break;
                
            default:
                echo '<input type="text" name="ctb_conditions[' . esc_attr($index) . '][value]" value="' . esc_attr($value) . '" />';
        }
    }
    
    /**
     * Render condition template
     */
    private function render_condition_template() {
        $condition_types = $this->get_condition_types();
        ?>
        <div class="ctb-condition-row" data-index="{{data.index}}">
            <div class="ctb-condition-controls">
                <select name="ctb_conditions[{{data.index}}][type]" class="ctb-condition-type">
                    <option value=""><?php _e('Select Condition', 'custom-theme-builder'); ?></option>
                    <?php foreach ($condition_types as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="ctb_conditions[{{data.index}}][operator]" class="ctb-condition-operator">
                    <option value="include"><?php _e('Include', 'custom-theme-builder'); ?></option>
                    <option value="exclude"><?php _e('Exclude', 'custom-theme-builder'); ?></option>
                </select>
                
                <div class="ctb-condition-value">
                    <input type="text" name="ctb_conditions[{{data.index}}][value]" value="" />
                </div>
                
                <button type="button" class="button ctb-remove-condition" aria-label="<?php _e('Remove condition', 'custom-theme-builder'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get condition types
     */
    private function get_condition_types() {
        $types = [
            // Core WordPress
            'entire_site' => __('Entire Site', 'custom-theme-builder'),
            'front_page' => __('Front Page', 'custom-theme-builder'),
            'post_type' => __('Post Type', 'custom-theme-builder'),
            'page' => __('Specific Page', 'custom-theme-builder'),
            'single_post' => __('Specific Post', 'custom-theme-builder'),
            'specific_post' => __('Specific Post/Page', 'custom-theme-builder'),
            'category' => __('Category', 'custom-theme-builder'),
            'tag' => __('Tag', 'custom-theme-builder'),
            'author' => __('Author', 'custom-theme-builder'),
            'user_role' => __('User Role', 'custom-theme-builder'),
            'date' => __('Date Archive', 'custom-theme-builder'),
            'archive' => __('Archive', 'custom-theme-builder'),
            'search_results' => __('Search Results', 'custom-theme-builder'),
            'error_404' => __('404 Page', 'custom-theme-builder'),
            'attachment' => __('Attachment', 'custom-theme-builder'),
            'privacy_policy' => __('Privacy Policy', 'custom-theme-builder'),
            
            // Advanced WordPress
            'taxonomy' => __('Taxonomy', 'custom-theme-builder'),
            'custom_field' => __('Custom Field', 'custom-theme-builder'),
            'url_parameter' => __('URL Parameter', 'custom-theme-builder'),
            'device_type' => __('Device Type', 'custom-theme-builder'),
            'user_status' => __('User Status', 'custom-theme-builder'),
            'browser' => __('Browser', 'custom-theme-builder'),
            'operating_system' => __('Operating System', 'custom-theme-builder'),
            'page_template' => __('Page Template', 'custom-theme-builder'),
            'post_format' => __('Post Format', 'custom-theme-builder'),
            'language' => __('Language', 'custom-theme-builder'),
            'referrer' => __('Referrer', 'custom-theme-builder'),
            'time_date' => __('Time & Date', 'custom-theme-builder'),
            'custom_post_type_archive' => __('Custom Post Type Archive', 'custom-theme-builder'),
            'parent_page' => __('Parent Page', 'custom-theme-builder'),
            'post_status' => __('Post Status', 'custom-theme-builder'),
            'comment_status' => __('Comment Status', 'custom-theme-builder'),
            'has_post_thumbnail' => __('Has Featured Image', 'custom-theme-builder'),
            'post_word_count' => __('Post Word Count', 'custom-theme-builder'),
            'post_age' => __('Post Age', 'custom-theme-builder'),
        ];
        
        // Add WooCommerce conditions if active
        if (class_exists('WooCommerce')) {
            $types['woocommerce_shop'] = __('Shop Page', 'custom-theme-builder');
            $types['woocommerce_product_category'] = __('Product Category', 'custom-theme-builder');
            $types['woocommerce_product_tag'] = __('Product Tag', 'custom-theme-builder');
            $types['woocommerce_cart'] = __('Cart Page', 'custom-theme-builder');
            $types['woocommerce_checkout'] = __('Checkout Page', 'custom-theme-builder');
            $types['woocommerce_account'] = __('Account Page', 'custom-theme-builder');
            $types['woocommerce_customer_status'] = __('Customer Status', 'custom-theme-builder');
        }
        
        return apply_filters('ctb_condition_types', $types);
    }
    
    /**
     * Save conditions
     */
    public function save_conditions($post_id) {
        // Check nonce
        if (!isset($_POST['ctb_conditions_nonce']) || !wp_verify_nonce($_POST['ctb_conditions_nonce'], 'ctb_conditions_meta')) {
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
        
        // Get conditions from POST data
        $conditions = $_POST['ctb_conditions'] ?? [];
        
        // Save conditions using the simplified system
        CTB_Conditions::save_template_conditions($post_id, $conditions);
        
        // Clear cache after saving
        wp_cache_delete('ctb_template_' . $post_id, 'ctb_templates');
    }
    
    /**
     * AJAX: Get condition options
     */
    public function ajax_get_condition_options() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ctb_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $condition_type = sanitize_text_field($_POST['condition_type']);
        $index = intval($_POST['index']);
        
        ob_start();
        $this->render_condition_value_field($condition_type, $index, '');
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX: Add condition
     */
    public function ajax_add_condition() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ctb_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $index = intval($_POST['index']);
        $condition = [
            'type' => '',
            'operator' => 'is',
            'value' => ''
        ];
        
        ob_start();
        $this->render_condition_row($condition, $index);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Get condition value field
     */
    public function ajax_get_condition_value_field() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ctb_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $condition_type = sanitize_text_field($_POST['condition_type']);
        $index = intval($_POST['index']);
        
        // Create a mock condition array for rendering
        $condition = [
            'type' => $condition_type,
            'operator' => 'include',
            'value' => ''
        ];
        
        // Start output buffering
        ob_start();
        $this->render_condition_value_field($condition, $index);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
}
