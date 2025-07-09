<?php
/**
 * Plugin Name: IUR - Image URL Replacement
 * Description: جایگزینی خودکار آدرس تصاویر محصولات و پست‌ها با لینک‌های میزبانی شده در Freeimage.host یا سایر سرویس‌ها
 * Version: 2.0.1
 * Author: Baloch Mark
 * License: GPLv2
 */

defined('ABSPATH') || exit;

// فعال کردن گزارش خطاها
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

define('IUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IUR_PLUGIN_URL', plugin_dir_url(__FILE__));

// فعال‌سازی افزونه
register_activation_hook(__FILE__, 'iur_activate_plugin');
function iur_activate_plugin() {
    if (!current_user_can('activate_plugins')) return;
    
    add_option('iur_settings', [
        'freeimage_api_key' => '',
        'imgbb_api_key' => '',
        'auto_replace' => 'no',
        'post_types' => ['post', 'product'],
        'upload_method' => 'freeimage',
        'skip_existing' => 0,
        'quality' => 'high',
        'target_content' => ['post', 'product'], // اضافه شد
        'delete_after_replace' => 0 // اضافه شد
    ]);
}

// بارگذاری فایل‌های وابسته
require_once IUR_PLUGIN_DIR . 'includes/handlers/class-iur-error-handler.php';
require_once IUR_PLUGIN_DIR . 'includes/services/class-freeimage-api.php';
require_once IUR_PLUGIN_DIR . 'includes/helpers.php';
require_once IUR_PLUGIN_DIR . 'includes/admin/class-admin-notices.php';
require_once IUR_PLUGIN_DIR . 'includes/class-iur-processor.php';
require_once IUR_PLUGIN_DIR . 'includes/class-iur-settings.php';
require_once IUR_PLUGIN_DIR . 'includes/class-iur-uploader.php';

// مقداردهی اولیه
add_action('init', 'iur_init_plugin');
function iur_init_plugin() {
    new IUR_Error_Handler();
    new IUR_Settings();
    IUR_Processor::init();
}

// منوی ادمین
add_action('admin_menu', 'iur_admin_menu');
function iur_admin_menu() {
    $menu_slug = 'iur-image-url-replacement';
    
    add_menu_page(
        'Image URL Replacement',
        'IUR',
        'manage_options',
        $menu_slug,
        'iur_admin_page',
        'dashicons-images-alt2',
        99
    );
    
    add_submenu_page(
        $menu_slug,
        'Settings',
        'Settings',
        'manage_options',
        'iur-settings',
        'iur_settings_page'
    );
}

function iur_admin_page() {
    $settings = get_option('iur_settings');
    $process_url = wp_nonce_url(
        admin_url('admin-post.php?action=iur_process_all'),
        'iur_process_all_nonce'
    );
    ?><div class="wrap">
    <h1>Image URL Replacement</h1>
    
    <div class="card">
        <h2 class="title">Manual Processing</h2>
        
        <div id="iur-process-controls">
            <button id="iur-process-all" class="button button-primary button-large">
                پردازش همه پست‌ها
            </button>
            
            <div id="iur-progress" style="display:none; margin:20px 0;">
                <div class="iur-progress-bar" style="height:20px; background:#0073aa; width:0%;"></div>
            </div>
            
            <div id="iur-results"></div>
        </div>
    </div>
    
    <div class="card">
        <h2 class="title">خطاهای اخیر</h2>
        <div id="iur-error-log">
            <?php
            $error_log = get_option('iur_error_log', []);
            if (!empty($error_log)) : ?>
                <ul class="ul-disc">
                    <?php foreach (array_slice($error_log, -5) as $error) : ?>
                        <li>
                            <strong><?php echo esc_html($error['time']); ?>:</strong> 
                            <?php echo esc_html($error['message']); ?>
                            <small>(شناسه پست: <?php echo $error['post_id']; ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button id="iur-clear-errors" class="button">پاکسازی خطاها</button>
            <?php else : ?>
                <p>هیچ خطایی ثبت نشده است</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
        max-width: 600px;
    }
    .card .title {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .button-large {
        padding: 0 20px;
        height: 40px;
        line-height: 38px;
        font-size: 16px;
    }
    #iur-progress {
        background: #f1f1f1;
        border-radius: 3px;
        overflow: hidden;
    }
    .iur-progress-bar {
        transition: width 0.3s ease;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // تابع برای مدیریت نمایش فیلدهای API
    function toggleServiceFields() {
        $('.iur-service-field').hide();
        const service = $('#iur-upload-method').val();
        
        if(service === 'freeimage') {
            $('#iur-freeimage-field').show();
        } else if(service === 'imgbb') {
            $('#iur-imgbb-field').show();
        }
    }

    // پردازش دسته‌ای پست‌ها
    $('#iur-process-all').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        const $progressBar = $('.iur-progress-bar');
        const $progressContainer = $('#iur-progress');
        const $resultsContainer = $('#iur-results');
        
        $button.prop('disabled', true).text('در حال پردازش...');
        $progressContainer.show();
        $resultsContainer.hide().html('');
        $progressBar.css('width', '0%');
        
        // ارسال درخواست AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iur_process_all_posts',
                security: iur_vars.nonce
            },
            dataType: 'json',
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $progressBar.css('width', percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $resultsContainer.html(`
                        <div class="notice notice-success">
                            <p><strong>پردازش با موفقیت انجام شد!</strong></p>
                            <p>تعداد پست‌های پردازش شده: ${response.data.processed}</p>
                            <p>تعداد تصاویر جایگزینی شده: ${response.data.replaced}</p>
                            <p>تعداد خطاها: ${response.data.errors}</p>
                        </div>
                    `).show();
                } else {
                    $resultsContainer.html(`
                        <div class="notice notice-error">
                            <p><strong>خطا در پردازش:</strong> ${response.data.message}</p>
                        </div>
                    `).show();
                }
            },
            error: function(xhr) {
                $resultsContainer.html(`
                    <div class="notice notice-error">
                        <p><strong>خطای سیستمی:</strong> ${xhr.statusText || 'خطای ناشناخته'}</p>
                    </div>
                `).show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // پاکسازی خطاها
    $('#iur-clear-errors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('آیا مطمئن هستید می‌خواهید تمام خطاها را پاک کنید؟')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('در حال پاکسازی...');
        
        $.post(ajaxurl, {
            action: 'iur_clear_errors',
            security: iur_vars.nonce
        }, function(response) {
            if (response.success) {
                $('#iur-error-log').html('<p>هیچ خطایی ثبت نشده است</p>');
            } else {
                alert('خطا در پاکسازی خطاها: ' + response.data.message);
            }
            $button.prop('disabled', false).text(originalText);
        });
    });
});
</script>
    <?php
}

// صفحه تنظیمات
function iur_settings_page() {
    require_once IUR_PLUGIN_DIR . 'includes/admin/admin-page.php';
    iur_settings_page_content();
}

// ذخیره تنظیمات
add_action('admin_post_iur_save_settings', 'iur_save_settings');
function iur_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('iur_settings_nonce');
    
    $settings = get_option('iur_settings', []);
    
    $settings['freeimage_api_key'] = sanitize_text_field($_POST['freeimage_api_key'] ?? '');
    $settings['imgbb_api_key'] = sanitize_text_field($_POST['imgbb_api_key'] ?? '');
    $settings['auto_replace'] = isset($_POST['auto_replace']) ? 'yes' : 'no';
    $settings['post_types'] = $_POST['post_types'] ?? ['post'];
    $settings['skip_existing'] = isset($_POST['skip_existing']) ? 1 : 0;
    $settings['quality'] = sanitize_text_field($_POST['quality'] ?? 'high');
    $settings['upload_method'] = sanitize_text_field($_POST['upload_method'] ?? 'freeimage');
    
    update_option('iur_settings', $settings);
    
    wp_redirect(admin_url('admin.php?page=iur-settings&saved=1'));
    exit;
}

// پردازش دسته‌ای پست‌ها
add_action('admin_post_iur_process_all', 'iur_process_all_handler');
function iur_process_all_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('iur_process_all_nonce');
    
    try {
        $result = IUR_Processor::process_all_posts();
        $count = $result['total_replaced'];
        
        wp_redirect(admin_url('admin.php?page=iur-image-url-replacement&processed=1&count=' . $count));
    } catch (Exception $e) {
        wp_redirect(admin_url(
            'admin.php?page=iur-image-url-replacement&error=1&message=' . urlencode($e->getMessage())
        ));
    }
    exit;
}

// پاکسازی خطاها
add_action('admin_post_iur_clear_errors', 'iur_clear_errors');
function iur_clear_errors() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('iur_clear_errors_nonce');
    
    update_option('iur_error_log', []);
    
    wp_redirect(admin_url('admin.php?page=iur-image-url-replacement'));
    exit;
}

// اسکریپت‌های ادمین برای صفحه تنظیمات (نسخه اصلاح شده)
add_action('admin_enqueue_scripts', 'iur_admin_scripts');
function iur_admin_scripts($hook) {
    // فقط در صفحه تنظیمات پلاگین اسکریپت را بارگذاری کن
    if ($hook === 'toplevel_page_iur-settings' || $hook === 'iur_page_iur-settings') {
        // بارگذاری jQuery
        wp_enqueue_script('jquery');
        
        // بارگذاری فایل admin.js از پوشه js
        wp_enqueue_script(
            'iur-admin-js',
            plugins_url('admin/js/admin.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );
    }
}
