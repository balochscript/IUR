<?php
if (!defined('ABSPATH')) exit;

class IUR_Admin {
    private static $instance = null;
    private $settings;
    private $error_handler;

    const MENU_SLUG = 'iur-main';

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = IUR_Settings::get_instance();
        $this->error_handler = IUR_Error_Handler::get_instance();

        add_action('admin_menu', [$this, 'setup_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_iur_save_settings', [$this, 'handle_settings_save']);
        add_action('wp_ajax_iur_bulk_process', [$this, 'handle_bulk_process']);
        add_action('wp_ajax_iur_test_api_connection', ['IUR_Ajax_Handler', 'handle_test_connection']);
        add_action('admin_post_iur_save_processing_settings', [$this, 'handle_processing_settings_save']);
    }

    /**
     * Register admin menu and submenus
     */
    public function setup_admin_menu() {
        add_menu_page(
            __('Image URL Replacement', 'iur'),
            __('Image URL Replacement', 'iur'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_dashboard'],
            'dashicons-format-gallery',
            80
        );

        $submenus = [
            [
                'title' => __('Dashboard', 'iur'),
                'slug' => self::MENU_SLUG,
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
                self::MENU_SLUG,
                $submenu['title'],
                $submenu['title'],
                'manage_options',
                $submenu['slug'],
                $submenu['callback']
            );
        }
    }

    /**
     * Enqueue admin assets only on plugin pages
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'iur-') === false) return;

        wp_enqueue_style('iur-admin-css', IUR_PLUGIN_URL . 'admin/css/admin.css', [], IUR_VERSION);
        wp_enqueue_script('iur-admin-js', IUR_PLUGIN_URL . 'admin/js/admin.js', ['jquery', 'wp-util'], IUR_VERSION, true);

        wp_localize_script('iur-admin-js', 'iur_vars', [
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('iur_bulk_process_nonce'),
            'bulk_limit' => $this->settings->get('bulk_limit', 5),
            'timeout'    => $this->settings->get('timeout', 30),
        ]);
    }

    /**
     * Handle bulk image process via AJAX
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
            wp_send_json_success(['data' => $result]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage(), 'trace' => WP_DEBUG ? $e->getTraceAsString() : null]);
        }
    }

    /**
     * Handle saving plugin settings
     */
    public function handle_settings_save() {
        check_admin_referer('iur_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'iur'));
        }
        try {
            // validate & sanitize settings ...
            $this->settings->update($new_settings);
            wp_redirect(admin_url('admin.php?page=iur-settings&saved=1'));
            exit;
        } catch (Exception $e) {
            $this->error_handler->log($e->getMessage(), 'settings_save');
            wp_die($e->getMessage());
        }
    }

    /**
     * Post Auth
     */
    private function validate_post_types($types) {
        $valid_types = get_post_types(['public' => true]);
        return array_intersect((array)$types, $valid_types);
    }

    /**
     * Manage Req AJAX
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
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null
            ]);
        }
    }

    /**
     * load assets
     */
    public function enqueue_assets($hook) {
    if (strpos($hook, 'iur-') === false) {
        return;
    }

    // CSS
    wp_enqueue_style(
        'iur-admin-css',
        IUR_PLUGIN_URL . 'admin/css/admin.css',
        [],
        IUR_VERSION
    );

    // JS
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
     * Pages Rend
     */
    public function render_dashboard() {
    $settings = $this->settings->get_all();
    $stats = $this->get_processing_stats();
    include IUR_PLUGIN_DIR . 'admin/partials/settings-processing.php';
}

    public function render_settings() {
    $settings = $this->settings->get_all();
    ?>
    <div class="wrap">
        <h1>Plugin Settings</h1>

        <div class="settings-section">
            <?php include IUR_PLUGIN_DIR . 'admin/partials/settings-api.php'; ?>
        </div>

        <div class="settings-section">
            <?php include IUR_PLUGIN_DIR . 'admin/partials/settings-advanced.php'; ?>
        </div>
    </div>
    <?php
}
    
private function get_upload_reports() {
    return [
        [
            'post_id' => 123,
            'status'  => 'success',
            'method'  => 'freeimage',
            'time'    => '2025-07-17 12:30'
        ]
    ];
}

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

    public function render_report() {
    $reports = $this->get_upload_reports();
    include IUR_PLUGIN_DIR . 'admin/views/upload-report.php';
}
}
