<?php
class IUR_Bulk_Processor {
    private static $instance = null;
    private $processor;
    private $settings;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->processor = IUR_Processor::init();
        $this->settings = IUR_Settings::get_instance();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_action_iur_bulk_process', [$this, 'handle_bulk_action']);
        add_filter('bulk_actions-edit-post', [$this, 'register_bulk_actions']);
        add_filter('bulk_actions-edit-product', [$this, 'register_bulk_actions']);
        add_action('admin_notices', [$this, 'bulk_admin_notices']);
    }

    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['iur_bulk_process'] = __('Process Images with IUR', 'iur');
        return $bulk_actions;
    }

    public function handle_bulk_action() {
      $settings = IUR_Settings::get_instance();
  $timeout = $settings->get('timeout', 30);
  
  
    if ( ! current_user_can('manage_options') ) {
        wp_die(__('You are not Admin.', 'iur'), '', ['response' => 403]);
    }
    
    
    if ( get_transient('iur_bulk_lock') ) {
        wp_die(__('Bulk process already running, please wait.', 'iur'), '', ['response' => 429]);
    }
    set_transient('iur_bulk_lock', 1, 300);
  
  if (function_exists('set_time_limit')) { @set_time_limit($timeout + 30); }

        if (empty($_REQUEST['post']) || !is_array($_REQUEST['post'])) {
            return;
        }

        check_admin_referer('bulk-posts');

        $post_ids = array_map('absint', $_REQUEST['post']);
        $max_execution_time = ini_get('max_execution_time');
    if ($max_execution_time > 0 && $max_execution_time < 300) {
        @set_time_limit(300); // 5 دقیقه
    }

        $results = [
    'success' => 0,
    'failed' => 0,
    'skipped' => 0,
    'error_logs' => []
];
foreach ($post_ids as $post_id) {
    try {
        $result = $this->processor->process_post($post_id);
        if (!empty($result['replaced'])) {
            $results['success']++;
        } else {
            $results['skipped']++;
        }
    } catch (Exception $e) {
        $results['failed']++;
        $results['error_logs'][] = [
            'post_id' => $post_id,
            'error'   => $e->getMessage()
        ];
        error_log('IUR Bulk Process Error for post ' . $post_id . ': ' . $e->getMessage());
    }
    if ( function_exists('wp_cache_flush') ) { wp_cache_flush(); }
    if ( function_exists('gc_collect_cycles') ) { gc_collect_cycles(); }
}

    // بازگرداندن زمان اجرا به مقدار قبلی
    if (isset($max_execution_time)) {
        @set_time_limit($max_execution_time);
    }

    set_transient('iur_bulk_process_results', $results, 300);
    delete_transient('iur_bulk_lock');
    wp_redirect(wp_get_referer());
    exit;
}

    public function bulk_admin_notices() {
    if (!empty($_REQUEST['iur_bulk_action'])) {
        $results = get_transient('iur_bulk_process_results');
        if (false === $results) { return; }
        $message = sprintf(
            __('Processed %d posts: %d success, %d skipped, %d failed.', 'iur'),
            $results['success'] + $results['skipped'] + $results['failed'],
            $results['success'],
            $results['skipped'],
            $results['failed']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p>';
        if (!empty($results['error_logs'])) {
            echo '<ul style="color:#d00">';
            foreach ($results['error_logs'] as $err) {
                echo '<li>' . sprintf(
                    __('Post %d: %s', 'iur'),
                    (int)$err['post_id'],
                    esc_html($err['error'])
                ) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
}


    public static function get_instance() {
        return self::$instance;
    }
}
