<?php
class IUR_Error_Handler {
    /* ----------  Add below this line  ---------- */
    /**
     * Maximum number of log entries kept in the WP option.
     * Older entries بالای این سقف حذف می‌شوند.
     */
    const MAX_OPTION_LOG = 1000;

    /**
     * حداکثر اندازهٔ فایل لاگ (برحسب بایت) پیش از چرخش.
     * 1MB = 1,048,576bytes
     */
    const MAX_LOG_FILE_SIZE = 1048576;

    /** سطح‌های مجازِ لاگ‌ نویسی */
    const LEVEL_INFO      = 'info';
    const LEVEL_WARNING   = 'warning';
    const LEVEL_ERROR     = 'error';
    const LEVEL_CRITICAL  = 'critical';
    /* ----------  End of added block  ---------- */

    public function __construct() {
        add_action('admin_notices', [$this, 'display_errors']);
    }
    
    /**
 * Initialize the error handler by registering necessary hooks
 * and preparing the log environment.
 */
/**
 * Static initializer for error handler.
 * Registers hooks and prepares logging environment.
 */
public static function init() {
    $instance = new self();

    // فقط در بخش مدیریت وردپرس فعال شود
    if (is_admin()) {
        add_action('admin_notices', [$instance, 'display_errors']);
    }

    // تعریف مسیر دقیق فایل لاگ
    if (!defined('IUR_LOG_PATH')) {
        define('IUR_LOG_PATH', plugin_dir_path(dirname(__FILE__)) . 'logs/error.log');
    }

    $log_file = IUR_LOG_PATH;
    $log_dir = dirname($log_file);

    // بررسی و ساخت پوشه لاگ در صورت نبود
    if (!is_dir($log_dir)) {
        // استفاده از mkdir به جای wp_mkdir_p در محیط‌هایی مثل اندروید
        if (!mkdir($log_dir, 0755, true)) {
            error_log('[IUR] خطا در ساخت پوشه لاگ: ' . $log_dir);
            return;
        }
    }

    // اگر فایل لاگ وجود نداره، ایجادش با بررسی مجوزها
    if (!file_exists($log_file)) {
        if (is_writable($log_dir)) {
            @file_put_contents($log_file, "=== شروع گزارش خطاهای افزونه IUR ===\n");
        } else {
            error_log('[IUR] پوشه logs قابل نوشتن نیست: ' . $log_dir);
            return;
        }
    }

    // بررسی نهایی نوشتن در فایل لاگ
    if (!is_writable($log_file)) {
        error_log('[IUR] فایل لاگ قابل نوشتن نیست: ' . $log_file);
    }
}

/**
 * Write one log entry to option + file.
 *
 * @param string $message  متن خطا/اطلاع
 * @param string $level    یکی از چهار سطح مجاز (info, warning, error, critical)
 * @param array  $context  پارامترهای کمکی (اختیاری)
 */
public function log( $message, $level = self::LEVEL_ERROR, $context = [] ) {
    // اطمینان از سطح معتبر
    $allowed = [
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
    ];
    if ( ! in_array( $level, $allowed, true ) ) {
        $level = self::LEVEL_ERROR;
    }

    $entry = [
        'timestamp' => current_time( 'mysql' ),
        'level'     => $level,
        'message'   => sanitize_textarea_field( $message ),
        'context'   => $context,
    ];

    /* ---------- پایگاه‌داده (WP Option) ---------- */
    $logs = get_option( 'iur_error_logs', [] );

    // اگر تعداد بیشتر از سقف شد، قدیمی‌ترین‌ها را حذف کن
    if ( count( $logs ) >= self::MAX_OPTION_LOG ) {
        $logs = array_slice( $logs, - self::MAX_OPTION_LOG + 1 );
    }
    $logs[] = $entry;
    update_option( 'iur_error_logs', $logs );

    /* ---------- فایل لاگ ---------- */
    if ( defined( 'IUR_LOG_FILE' ) ) {
        // گردش فایل اگر از حد گذشت
        $this->rotate_log_file_if_needed();
        $line = sprintf(
            "[%s] %s: %s %s\n",
            $entry['timestamp'],
            strtoupper( $level ),
            $entry['message'],
            empty( $context ) ? '' : json_encode( $context, JSON_UNESCAPED_UNICODE )
        );
        error_log( $line, 3, IUR_LOG_FILE );
    }
}


public static function get_instance() {
    static $instance = null;

    if ($instance === null) {
        $instance = new self();
    }

    return $instance;
}

/* ----------------------------------------------------------------
 |  Helpers – Newly Added
 *----------------------------------------------------------------*/

/** حذف همه یا بخشی از لاگ‌ها */
public function clear_errors( $level = null ) {
    if ( is_null( $level ) ) {
        delete_option( 'iur_error_logs' );
        return;
    }
    $logs = get_option( 'iur_error_logs', [] );
    $logs = array_filter(
        $logs,
        function ( $e ) use ( $level ) {
            return $e['level'] !== $level;
        }
    );
    update_option( 'iur_error_logs', $logs );
}

/** تعداد خطاها را برمی‌گرداند */
public function get_error_count( $level = null ) {
    $logs = get_option( 'iur_error_logs', [] );
    if ( is_null( $level ) ) {
        return count( $logs );
    }
    return count(
        array_filter(
            $logs,
            function( $e ) use ( $level ) { return $e['level'] === $level; }
        )
    );
}

/** فیلتر خطاها بر اساس سطح */
public function get_errors_by_type( $level ) {
    $logs = get_option( 'iur_error_logs', [] );
    return array_values(
        array_filter(
            $logs,
            fn( $e ) => $e['level'] === $level
        )
    );
}

/** بررسی و گردش لاگ‌فایل در صورت لزوم */
private function rotate_log_file_if_needed() {
    if ( ! defined( 'IUR_LOG_FILE' ) || ! file_exists( IUR_LOG_FILE ) ) {
        return;
    }
    if ( filesize( IUR_LOG_FILE ) >= self::MAX_LOG_FILE_SIZE ) {
        // تغییر نام فایل فعلی
        $new_name = IUR_LOG_FILE . '.' . time() . '.bak';
        @rename( IUR_LOG_FILE, $new_name );
        // ایجاد فایل تازه
        @touch( IUR_LOG_FILE );
    }
}


    public function display_errors() {
        global $post;
        
        if (!$post || !is_admin()) {
            return;
        }
        
        $errors = get_post_meta($post->ID, '_iur_errors', true);
        
        if (!empty($errors) && is_array($errors)) {
    echo '<div class="notice notice-error"><p><strong>' .
     esc_html__( 'خطاهای جایگزینی تصاویر:', 'iur' ) .
    delete_post_meta($post->ID, '_iur_errors');
}
    }
}
