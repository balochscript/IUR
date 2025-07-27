<?php
/**
 * IUR AJAX Handler
 * Handles all AJAX requests for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class IUR_Ajax_Handler {
    
    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Test connection endpoint
        add_action('wp_ajax_iur_test_connection', [__CLASS__, 'handle_test_connection']);
        
        // Single post processing endpoint
        add_action('wp_ajax_iur_process_single_post', [__CLASS__, 'handle_process_single_post']);
        
        // Clear errors endpoint
        add_action('wp_ajax_iur_clear_errors', [__CLASS__, 'handle_clear_errors']);
    }

    /**
     * Handle connection test for upload services
     */
    public static function handle_test_connection() {
        // 1. Verify nonce
        check_ajax_referer('iur_test_connection_action', 'nonce');

        // 2. Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'iur')], 403);
        }

        // 3. Sanitize input
        $service = sanitize_key($_POST['service'] ?? '');

        // 4. Validate against supported services
        $supported = ['freeimage', 'imgbb', 'cloudinary', 'wordpress'];
        if (!in_array($service, $supported, true)) {
            wp_send_json_error(['message' => __('Invalid service selected', 'iur')], 400);
        }

        // 5. Attempt to test connection
        try {
            $settings = IUR_Settings::get_instance();
            
            // Create appropriate service instance for testing
            switch ($service) {
                case 'freeimage':
                    $api_key = $settings->get_api_key('freeimage');
                    if (empty($api_key)) {
                        throw new Exception('FreeImage API key not configured');
                    }
                    $test_service = new IUR_FreeImage_Service($api_key, 'high', 30);
                    break;
                    
                case 'imgbb':
                    $api_key = $settings->get_api_key('imgbb');
                    if (empty($api_key)) {
                        throw new Exception('ImgBB API key not configured');
                    }
                    $test_service = new IUR_ImgBB_Service($api_key, 'high', 30);
                    break;
                    
                case 'cloudinary':
                    $config = $settings->get_api_key('cloudinary');
                    if (empty($config['api_key']) || empty($config['api_secret']) || empty($config['cloud_name'])) {
                        throw new Exception('Cloudinary configuration incomplete');
                    }
                    $test_service = new IUR_Cloudinary_Service($config, 30);
                    break;
                    
                case 'wordpress':
                    $test_service = new IUR_WP_Media_Service();
                    break;
                    
                default:
                    throw new Exception('Unsupported service');
            }
            
            // Validate credentials
            $test_service->validate_credentials();
            
            wp_send_json_success([
                'message' => sprintf(__('Connection to %s successful!', 'iur'), ucfirst($service))
            ]);
            
        } catch (Exception $e) {
            // Log error centrally
            IUR_Error_Handler::get_instance()->log(
                'AJAX test_connection failed: ' . $e->getMessage(),
                IUR_Error_Handler::LEVEL_ERROR,
                ['service' => $service]
            );
            
            wp_send_json_error([
                'message' => __('Connection failed: ', 'iur') . esc_html($e->getMessage())
            ], 500);
        }
    }

    /**
     * Handle single post processing
     */
    public static function handle_process_single_post() {
        // Security checks
        check_ajax_referer('iur_process_single_post_action', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'iur')], 403);
        }

        // Sanitize input
        $post_id = absint($_POST['post_id'] ?? 0);
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => __('Invalid post ID', 'iur')], 400);
        }

        try {
            $processor = IUR_Processor::get_instance();
            $result = $processor->process_post($post_id);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Processed %d images successfully', 'iur'),
                    $result['replaced']
                ),
                'replaced' => $result['replaced'],
                'errors' => $result['errors']
            ]);
            
        } catch (Exception $e) {
            IUR_Error_Handler::get_instance()->log(
                'AJAX single post processing failed: ' . $e->getMessage(),
                IUR_Error_Handler::LEVEL_ERROR,
                ['post_id' => $post_id]
            );
            
            wp_send_json_error([
                'message' => __('Processing failed: ', 'iur') . esc_html($e->getMessage())
            ], 500);
        }
    }

    /**
     * Handle clearing errors
     */
    public static function handle_clear_errors() {
        // Security checks
        check_ajax_referer('iur_clear_errors_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'iur')], 403);
        }

        // Sanitize input
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : null;
        $error_type = isset($_POST['error_type']) ? sanitize_key($_POST['error_type']) : null;

        try {
            $error_handler = IUR_Error_Handler::get_instance();
            
            if ($post_id) {
                // Clear errors for specific post
                delete_post_meta($post_id, '_iur_errors');
                $message = __('Post errors cleared successfully', 'iur');
            } elseif ($error_type) {
                // Clear errors by type
                $error_handler->clear_errors($error_type);
                $message = sprintf(__('All %s errors cleared successfully', 'iur'), $error_type);
            } else {
                // Clear all errors
                $error_handler->clear_errors();
                $message = __('All errors cleared successfully', 'iur');
            }
            
            wp_send_json_success(['message' => $message]);
            
        } catch (Exception $e) {
            IUR_Error_Handler::get_instance()->log(
                'AJAX clear errors failed: ' . $e->getMessage(),
                IUR_Error_Handler::LEVEL_ERROR,
                ['post_id' => $post_id, 'error_type' => $error_type]
            );
            
            wp_send_json_error([
                'message' => __('Failed to clear errors: ', 'iur') . esc_html($e->getMessage())
            ], 500);
        }
    }
}
