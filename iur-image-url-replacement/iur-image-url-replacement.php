<?php
/**
 * Plugin Name: IUR - Image URL Replacement
 * Description: Automatically replace product and post image URLs with links hosted on Freeimage.host or other services
 * Version: 1.0.0
 * Author: Baloch Mark
 * License: GPLv2
 */

defined('ABSPATH') || exit;

define('IUR_PLUGIN_FILE', __FILE__);
define('IUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IUR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IUR_VERSION', '1.0.0');

// Load dependencies
require_once IUR_PLUGIN_DIR . 'includes/class-iur-autoloader.php';
require_once IUR_PLUGIN_DIR . 'includes/vendor/autoload.php';

// Activation hook
register_activation_hook(__FILE__, 'iur_activate_plugin');
function iur_activate_plugin() {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    $default_settings = [
        'upload_method' => 'freeimage',
        'freeimage' => [
            'api_key' => '',
        ],
        'imgbb' => [
            'api_key' => '',
        ],
        'cloudinary' => [
            'api_key' => '',
            'api_secret' => '',
            'cloud_name' => '',
            'folder' => 'iur_uploads',
            'secure' => true
        ],
        'quality' => 'high',
        'target_content' => ['post', 'product'],
        'delete_after_replace' => 0,
        'auto_replace' => 'no',
        'process_featured_image' => 1,
        'process_content_images' => 1,
        'process_galleries' => 1,
        'process_custom_fields' => 0,
        'group_limit' => 10,
        'group_timeout' => 5
    ];

    add_option('iur_settings', $default_settings);
}

// Initialize plugin
add_action('plugins_loaded', 'iur_init_plugin');
/**
 * Initialize the plugin with proper dependency loading and error handling
 */
function iur_init_plugin() {
    // Initialize core components
    iur_initialize_core_components();
    
    // Initialize admin-related components
    if (is_admin()) {
        iur_initialize_admin_components();
    }
    
    // Register meta fields
    add_action('init', 'iur_register_meta_fields');
}

/**
 * Initialize core plugin components
 */
function iur_initialize_core_components() {
    try {
        // Initialize autoloader and load dependencies
        $autoloader = new IUR_Autoloader();
        $autoloader->init();
        
        // Initialize AJAX handlers
        IUR_Ajax_Handler::init();
        
        // Initialize settings (loads and validates settings)
        $settings = IUR_Settings::get_instance();
        $settings->init();
        
        // Initialize processor (main functionality)
        IUR_Processor::init();
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_die(__('IUR Plugin Initialization Failed: ', 'iur') . esc_html($e->getMessage()));
        }
    }
}

/**
 * Initialize admin-specific components
 */
function iur_initialize_admin_components() {
    try {
        // Initialize admin interface
        IUR_Admin::init();
        
        // Initialize bulk processor
        require_once IUR_PLUGIN_DIR . 'includes/class-iur-bulk-processor.php';
        IUR_Bulk_Processor::init();
        
        // Add AJAX handlers
        add_action('wp_ajax_iur_process_single_post', 'iur_ajax_process_single_post');
        add_action('admin_post_iur_clear_errors', 'iur_clear_errors');
        
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>' . esc_html(__('IUR Admin Initialization Error: ', 'iur')) . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}

// Register custom meta fields
function iur_register_meta_fields() {
    register_meta('post', '_iur_upload_status', [
        'type' => 'object',
        'single' => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string'],
                    'service' => ['type' => 'string'],
                    'images' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'original_url' => ['type' => 'string'],
                                'uploaded_url' => ['type' => 'string'],
                                'success' => ['type' => 'boolean'],
                                'reason' => ['type' => 'string'],
                                'error' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'auth_callback' => function() { 
            return current_user_can('edit_posts'); 
        }
    ]);
    
    register_meta('post', '_iur_last_processed', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false
    ]);
}

// AJAX handler for single post processing
add_action('wp_ajax_iur_process_single_post', 'iur_ajax_process_single_post');
function iur_ajax_process_single_post() {
    check_ajax_referer('iur_process_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access', 'iur')], 403);
    }

    $post_id = intval($_POST['post_id']);
    
    try {
        $processor = IUR_Processor::get_instance();
        $result = $processor->process_post($post_id);
        
        wp_send_json_success([
            'replaced' => $result['replaced'],
            'warnings' => $result['warnings'],
            'errors'  => $result['errors']
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ], 500);
    }
}

// Clear errors handler (now only clears any plugin-related notices, not logs)
add_action('admin_post_iur_clear_errors', 'iur_clear_errors');
function iur_clear_errors() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access', 'iur'), 403);
    }
    
    check_admin_referer('iur_clear_errors_nonce');
    
    // If you have a new error/notice handling system, call its clear method here.
    // Otherwise, leave this empty or remove if not used elsewhere.

    wp_safe_redirect(admin_url('admin.php?page=iur-settings'));
    exit;
}
