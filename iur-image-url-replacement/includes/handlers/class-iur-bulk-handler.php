<?php
class IUR_Bulk_Handler {
    /**
     * Static initializer for bulk handler.
     */
    public static function init() {
        if (is_admin()) {
            add_action('wp_ajax_iur_process_all', [self::get_instance(), 'handle_ajax']);
        }
    }

    /**
     * Singleton instance getter.
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Handle Ajax request for bulk processing.
     */
    public function handle_ajax() {
    check_ajax_referer('iur_process_all_nonce');

    $settings = get_option('iur_settings');
    $timeout_per_image = $settings['timeout'] ?? 30;
    $limit = (int)($settings['bulk_limit'] ?? 5);
    $offset = (int)($_POST['offset'] ?? 0);

    // محاسبه تایم‌اوت کل
    $total_timeout = ($timeout_per_image * $limit) + 30;
    set_time_limit($total_timeout);

    try {
        $result = IUR_Processor::init()->process_bulk($limit, $offset);
        wp_send_json_success($result);
    } catch (Exception $e) {
        $error_handler = IUR_Error_Handler::get_instance();
        $error_handler->log('Bulk processing error: ' . $e->getMessage(), 'bulk_error');
        wp_send_json_error([
            'message' => 'Server Error: ' . $e->getMessage()
        ]);
    }
}
}