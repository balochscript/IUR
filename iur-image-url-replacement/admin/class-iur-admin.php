<?php
class IUR_Admin {
    private static $instance = null;
    private $settings;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        return self::$instance;
    }

    private function __construct() {
        $this->settings = IUR_Settings::get_instance();

        add_action('admin_menu', [$this, 'setup_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_iur_save_settings', [$this, 'handle_settings_save']);
        add_action('wp_ajax_iur_bulk_process', [$this, 'handle_bulk_process']);
        add_action('wp_ajax_iur_test_api_connection', ['IUR_Ajax_Handler', 'handle_test_connection']);
        add_action('admin_post_iur_save_processing_settings', [$this, 'handle_processing_settings_save']);
    }

    /**
     * Admin menus
     */
    public function setup_admin_menu() {
        $menu_icon = 'dashicons-format-gallery';

        // Main menu
        add_menu_page(
            __('Image URL Replacement', 'iur'),
            __('Image URL Replacement', 'iur'),
            'manage_options',
            'iur-main',
            [$this, 'render_dashboard'],
            $menu_icon,
            80
        );

        // Submenus
        $submenus = [
            [
                'title' => __('Dashboard', 'iur'),
                'slug' => 'iur-main',
                'callback' => [$this, 'render_dashboard']
            ],
            [
                'title' => __('Settings', 'iur'),
                'slug' => 'iur-settings',
                'callback' => [$this, 'render_settings']
            ],
            [
                'title' => __('Upload Report', 'iur'),
                'slug' => 'iur-report',
                'callback' => [$this, 'render_report']
            ]
        ];

        foreach ($submenus as $submenu) {
            add_submenu_page(
                'iur-main',
                $submenu['title'],
                $submenu['title'],
                'manage_options',
                $submenu['slug'],
                $submenu['callback']
            );
        }
    }

    /**
     * AJAX handler for bulk process
     */
    public function handle_bulk_process() {
        check_ajax_referer('iur_bulk_process_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'iur')], 403);
        }

        try {
            @set_time_limit(0);
            @ini_set('memory_limit', '512M');

            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $bulk_limit = isset($_POST['bulk_limit']) ? intval($_POST['bulk_limit']) : 5;

            $processor = IUR_Processor::init();
            $result = $processor->process_batch($offset, $bulk_limit);

            wp_send_json_success([
                'data' => [
                    'processed' => $result['processed'],
                    'errors' => $result['errors'],
                    'total' => $result['total'],
                    'completed' => $result['completed'],
                    'message' => sprintf(__('Processed %d posts. Errors: %d', 'iur'), $result['processed'], $result['errors'])
                ]
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : null
            ]);
        }
    }

    /**
     * Save processing settings
     */
    public function handle_processing_settings_save() {
        check_admin_referer('iur_settings_action', 'iur_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'iur'));
        }

        $settings = IUR_Settings::get_instance();
        $current_settings = $settings->get_all();

        $new_processing_settings = [
            'process_featured_image' => isset($_POST['process_featured_image']) ? 1 : 0,
            'process_content_images' => isset($_POST['process_content_images']) ? 1 : 0,
            'process_galleries' => isset($_POST['process_galleries']) ? 1 : 0,
            'process_custom_fields' => isset($_POST['process_custom_fields']) ? 1 : 0,
            'bulk_limit' => isset($_POST['bulk_limit']) ? absint($_POST['bulk_limit']) : 5,
            'timeout' => isset($_POST['timeout']) ? absint($_POST['timeout']) : 30
        ];

        $updated_settings = array_merge($current_settings, $new_processing_settings);

        $settings->update($updated_settings);

        wp_redirect(admin_url('admin.php?page=iur-main&saved=1'));
        exit;
    }

    /**
     * Save settings
     */
    public function handle_settings_save() {
        check_admin_referer('iur_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'iur'));
        }

        try {
            $new_settings = [
                'upload_method' => $this->validate_upload_method($_POST['upload_method'] ?? 'freeimage'),
                'freeimage' => [
                    'api_key' => sanitize_text_field($_POST['freeimage_api_key'] ?? '')
                ],
                'imgbb' => [
                    'api_key' => sanitize_text_field($_POST['imgbb_api_key'] ?? '')
                ],
                'cloudinary' => [
                    'api_key' => sanitize_text_field($_POST['cloudinary_api_key'] ?? ''),
                    'api_secret' => sanitize_text_field($_POST['cloudinary_api_secret'] ?? ''),
                    'cloud_name' => sanitize_text_field($_POST['cloudinary_cloud_name'] ?? ''),
                    'folder' => sanitize_text_field($_POST['cloudinary_folder'] ?? 'iur_uploads'),
                    'secure' => isset($_POST['cloudinary_secure']) ? 1 : 0
                ],
                'auto_replace' => isset($_POST['auto_replace']) ? 'yes' : 'no',
                'post_types' => $this->validate_post_types($_POST['post_types'] ?? []),
                'skip_existing' => isset($_POST['skip_existing']) ? 1 : 0,
                'quality' => sanitize_text_field($_POST['quality'] ?? 'high'),
                'delete_after_replace' => isset($_POST['delete_after_replace']) ? 1 : 0,
                'max_width' => absint($_POST['max_width'] ?? 0),
                'max_height' => absint($_POST['max_height'] ?? 0),
                'process_featured_image' => isset($_POST['process_featured_image']) ? 1 : 0,
                'process_content_images' => isset($_POST['process_content_images']) ? 1 : 0,
                'process_galleries' => isset($_POST['process_galleries']) ? 1 : 0,
                'process_custom_fields' => isset($_POST['process_custom_fields']) ? 1 : 0
            ];

            $this->settings->update($new_settings);
            wp_redirect(admin_url('admin.php?page=iur-settings&saved=1'));
            exit;
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }

    /**
     * Validate post types
     */
    private function validate_post_types($types) {
        $valid_types = get_post_types(['public' => true]);
        return array_intersect((array)$types, $valid_types);
    }

    /**
     * AJAX process all
     */
    public function handle_ajax_process() {
        check_ajax_referer('iur_process_all_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'iur')], 403);
        }

        try {
            @set_time_limit(0);
            @ini_set('memory_limit', '512M');

            $processor = IUR_Processor::init();
            $results = $processor->process_all_posts();

            wp_send_json_success([
                'processed' => count($results),
                'success' => array_sum(array_column($results, 'success')),
                'errors' => array_sum(array_column($results, 'errors'))
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : null
            ]);
        }
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'iur-') === false) {
            return;
        }

        wp_enqueue_style(
            'iur-admin-css',
            IUR_PLUGIN_URL . 'admin/css/admin.css',
            [],
            IUR_VERSION
        );

        wp_enqueue_script(
            'iur-admin-js',
            IUR_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery', 'wp-util'],
            IUR_VERSION,
            true
        );

        wp_localize_script('iur-admin-js', 'iur_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('iur_bulk_process_nonce'),
            'bulk_limit'   => $this->settings->get('bulk_limit', 5),
            'timeout' => $this->settings->get('timeout', 30)
        ]);
    }

    /**
     * Get processing stats
     */
    private function get_processing_stats() {
        $args = [
            'post_type'   => $this->settings->get('post_types', ['post']),
            'post_status' => 'any',
            'fields'      => 'ids',
            'posts_per_page' => -1
        ];
        $post_ids = get_posts($args);
        $post_ids = array_unique($post_ids);
        $total = count($post_ids);

        $success = 0;
        $errors = 0;
        foreach ($post_ids as $pid) {
            if (get_post_meta($pid, '_iur_processed', true)) {
                $success++;
            }
            $err = get_post_meta($pid, '_iur_errors', true);
            if (!empty($err)) {
                $errors++;
            }
        }
        return [
            'total'   => $total,
            'success' => $success,
            'errors'  => $errors,
            'pending' => max(0, $total - ($success + $errors)),
            'percent' => $total > 0 ? round(100 * $success / $total, 1) : 0
        ];
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $settings = $this->settings->get_all();
        $stats = $this->get_processing_stats();
        include IUR_PLUGIN_DIR . 'admin/partials/settings-processing.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = $this->settings->get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plugin Settings', 'iur'); ?></h1>
            <div class="settings-section">
                <?php include IUR_PLUGIN_DIR . 'admin/partials/settings-api.php'; ?>
            </div>
            <div class="settings-section">
                <?php include IUR_PLUGIN_DIR . 'admin/partials/settings-advanced.php'; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get upload reports
     */
    private function get_upload_reports() {
        // Example static data, replace with actual implementation if needed
        return [
            [
                'post_id' => 123,
                'status'  => 'success',
                'method'  => 'freeimage',
                'time'    => '2025-07-17 12:30'
            ]
        ];
    }

    /**
     * Enqueue scripts for settings page
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_iur-settings') {
            return;
        }

        wp_enqueue_script(
            'iur-admin-script',
            plugins_url('admin/js/admin.js', IUR_PLUGIN_FILE),
            ['jquery', 'wp-util'],
            IUR_VERSION,
            true
        );

        wp_localize_script(
            'iur-admin-script',
            'iur_vars',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('iur_process_all_nonce'),
                'limit'    => get_option('iur_settings')['bulk_limit'] ?? 50,
            ]
        );
    }

    /**
     * Render upload report page
     */
    public function render_report() {
        $reports = $this->get_upload_reports();
        include IUR_PLUGIN_DIR . 'admin/views/upload-report.php';
    }
}
