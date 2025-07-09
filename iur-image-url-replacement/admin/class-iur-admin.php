<?php
class IUR_Admin {
    public static function init() {
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        add_action('admin_post_iur_save_settings', [self::class, 'save_settings']);
        add_action('admin_post_iur_process_all', [self::class, 'process_all']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_styles']);
    }
    
    public static function add_admin_menu() {
        add_menu_page(
            'IUR Settings',
            'Image URL Replacement',
            'manage_options',
            'iur-settings',
            [self::class, 'render_admin_page'],
            'dashicons-format-gallery'
        );
    }
    
    public static function render_admin_page() {
        require_once IUR_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    public static function save_settings() {
        check_admin_referer('iur_settings_nonce');
        
        $settings = [
            'api_key'    => sanitize_text_field($_POST['api_key']),
            'auto_replace' => isset($_POST['auto_replace']) ? 'yes' : 'no',
            'post_types' => array_map('sanitize_text_field', $_POST['post_types'])
        ];
        
        update_option('iur_settings', $settings);
        wp_redirect(admin_url('admin.php?page=iur-settings&saved=1'));
        exit;
    }
    
    public static function process_all() {
    check_admin_referer('iur_process_all_nonce');
    
    $total_processed = IUR_Processor::process_all_posts();
    
    wp_redirect(admin_url(
        'admin.php?page=iur-settings&processed=1&count=' . $total_processed
    ));
    exit;
}
    
    public static function enqueue_styles($hook) {
        if ('toplevel_page_iur-settings' !== $hook) return;
        wp_enqueue_style('iur-admin', IUR_PLUGIN_URL . 'assets/admin.css');
    }
    public static function enqueue_scripts($hook) {
    if ('toplevel_page_iur-settings' !== $hook) return;
    
    // استایل‌ها
    wp_enqueue_style('iur-admin', IUR_PLUGIN_URL . 'assets/admin.css');
    
    // جاوااسکریپت
    wp_enqueue_script(
        'iur-admin-js',
        IUR_PLUGIN_URL . 'admin/js/admin.js',
        ['jquery'],
        '1.0.0',
        true
    );
}
}
