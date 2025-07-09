<?php
class IUR_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_iur-settings' !== $hook) return;
        
        wp_enqueue_script(
            'iur-admin-js', 
            plugin_dir_url(__FILE__) . '../js/admin.js',
            ['jquery'],
            '1.0',
            true
        );
        
        // اضافه کردن استایل‌های لازم
        wp_enqueue_style(
            'iur-admin-css',
            plugin_dir_url(__FILE__) . '../assets/admin.css',
            [],
            '1.0'
        );
    }

    public function add_settings_page() {
        add_options_page(
            'تنظیمات جایگزینی تصاویر',
            'جایگزینی تصاویر',
            'manage_options',
            'iur-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('iur_settings_group', 'iur_settings');

        add_settings_section(
            'iur_main_section',
            'تنظیمات اصلی',
            [$this, 'render_section'],
            'iur-settings'
        );

        add_settings_field(
            'upload_method',
            'سرور میزبانی تصاویر',
            [$this, 'render_upload_method_field'],
            'iur-settings',
            'iur_main_section'
        );

        add_settings_field(
            'freeimage_api_key',
            'کلید API FreeImage',
            [$this, 'render_freeimage_api_field'],
            'iur-settings',
            'iur_main_section'
        );

        add_settings_field(
            'imgbb_api_key',
            'کلید API ImgBB',
            [$this, 'render_imgbb_api_field'],
            'iur-settings',
            'iur_main_section'
        );

        // اضافه کردن فیلدهای جدید
        add_settings_field(
            'target_content',
            'محل جستجوی تصاویر',
            [$this, 'render_target_content_field'],
            'iur-settings',
            'iur_main_section'
        );

        add_settings_field(
            'delete_after_replace',
            'حذف تصاویر پس از جایگزینی',
            [$this, 'render_delete_after_replace_field'],
            'iur-settings',
            'iur_main_section'
        );
    }

    public function render_section() {
        echo '<p>تنظیمات مربوط به جایگزینی خودکار آدرس تصاویر</p>';
    }

    public function render_upload_method_field() {
        $options = get_option('iur_settings');
        $method = $options['upload_method'] ?? 'freeimage';
        ?>
        <select name="iur_settings[upload_method]" id="iur-upload-method">
            <option value="freeimage" <?php selected($method, 'freeimage'); ?>>FreeImage.host</option>
            <option value="imgbb" <?php selected($method, 'imgbb'); ?>>ImgBB</option>
            <option value="wordpress" <?php selected($method, 'wordpress'); ?>>کتابخانه رسانه وردپرس</option>
        </select>
        <?php
    }

    public function render_freeimage_api_field() {
        $options = get_option('iur_settings');
        $api_key = $options['freeimage_api_key'] ?? '';
        echo '<div id="iur-freeimage-field" class="iur-service-field">';
        echo '<input type="text" name="iur_settings[freeimage_api_key]" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">کلید API را از <a href="https://freeimage.host/page/api" target="_blank">FreeImage.host</a> دریافت کنید</p>';
        echo '</div>';
    }

    public function render_imgbb_api_field() {
        $options = get_option('iur_settings');
        $api_key = $options['imgbb_api_key'] ?? '';
        echo '<div id="iur-imgbb-field" class="iur-service-field">';
        echo '<input type="text" name="iur_settings[imgbb_api_key]" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">کلید API را از <a href="https://api.imgbb.com/" target="_blank">ImgBB</a> دریافت کنید</p>';
        echo '</div>';
    }

    // تابع جدید برای نمایش فیلد انتخاب محل‌های جستجو
    public function render_target_content_field() {
        $options = get_option('iur_settings');
        // مقدار پیش‌فرض اگر وجود نداشت
        $target_content = $options['target_content'] ?? ['post', 'product'];
        ?>
        <fieldset>
            <label>
                <input type="checkbox" name="iur_settings[target_content][]" value="post" <?php checked(in_array('post', $target_content)); ?>>
                پست‌ها
            </label><br>
            <label>
                <input type="checkbox" name="iur_settings[target_content][]" value="product" <?php checked(in_array('product', $target_content)); ?>>
                محصولات
            </label><br>
            <label>
                <input type="checkbox" name="iur_settings[target_content][]" value="page" <?php checked(in_array('page', $target_content)); ?>>
                صفحات
            </label><br>
            <label>
                <input type="checkbox" name="iur_settings[target_content][]" value="attachment" <?php checked(in_array('attachment', $target_content)); ?>>
                کل کتابخانه رسانه
            </label>
        </fieldset>
        <?php
    }

    // تابع جدید برای نمایش فیلد حذف پس از جایگزینی
    public function render_delete_after_replace_field() {
        $options = get_option('iur_settings');
        $delete_after_replace = isset($options['delete_after_replace']) ? $options['delete_after_replace'] : 0;
        ?>
        <label>
            <input type="checkbox" name="iur_settings[delete_after_replace]" value="1" <?php checked($delete_after_replace, 1); ?>>
            فعال کردن
        </label>
        <p class="description">در صورت فعال‌سازی، تصاویر اصلی پس از جایگزینی موفق با لینک خارجی از سرور حذف خواهند شد</p>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>تنظیمات جایگزینی تصاویر</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('iur_settings_group');
                do_settings_sections('iur-settings');
                submit_button('ذخیره تنظیمات');
                ?>
            </form>
            
            <!-- اسکریپت برای نمایش فیلد API بر اساس انتخاب کاربر -->
            <script>
            jQuery(document).ready(function($) {
                // تابع برای نمایش فیلد API مربوطه
                function toggleApiFields() {
                    var method = $('#iur-upload-method').val();
                    $('.iur-service-field').hide();
                    
                    if (method === 'freeimage') {
                        $('#iur-freeimage-field').show();
                    } else if (method === 'imgbb') {
                        $('#iur-imgbb-field').show();
                    }
                }
                
                // اجرای اولیه
                toggleApiFields();
                
                // تغییر هنگام انتخاب سرور
                $('#iur-upload-method').change(toggleApiFields);
            });
            </script>
        </div>
        <?php
    }
}
