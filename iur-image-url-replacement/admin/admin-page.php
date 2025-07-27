<?php
function iur_settings_page_content() {
    $settings = get_option('iur_settings', [
        'freeimage_api_key' => '',
        'imgbb_api_key' => '',
        'auto_replace' => 'no',
        'post_types' => ['post', 'product'],
        'skip_existing' => 0,
        'quality' => 'high',
        'upload_method' => 'freeimage'
    ]);
    ?>
    // ثبت endpoint
add_action('rest_api_init', function() {
    register_rest_route('iur/v1', '/save-settings', [
        'methods' => 'POST',
        'callback' => 'iur_save_settings_rest',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

// تست نمایش فیلدها
function test_field_visibility() {
    $_GET['page'] = 'iur-settings';
    ob_start();
    iur_settings_page_content();
    $content = ob_get_clean();
    assert(strpos($content, 'tr_freeimage_api_key') !== false);
}
    
    <div class="wrap iur-settings">
        <h1>تنظیمات جایگزینی تصاویر</h1>
        
        <?php if (isset($_GET['saved'])) : ?>
            <div class="notice notice-success"><p>تنظیمات با موفقیت ذخیره شد!</p></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="iur_save_settings">
            <?php wp_nonce_field('iur_settings_nonce'); ?>
            
            <div class="card">
                <h2 class="title">تنظیمات API</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">سرویس آپلود</th>
                        <td>
                            <select name="upload_method" id="iur_upload_method" class="regular-text">
    <!-- گزینه های موجود -->
    <option value="freeimage" <?php selected($settings['upload_method'], 'freeimage'); ?>>FreeImage.host</option>
    <option value="imgbb" <?php selected($settings['upload_method'], 'imgbb'); ?>>ImgBB</option>
    <option value="wordpress" <?php selected($settings['upload_method'], 'wordpress'); ?>>کتابخانه رسانه وردپرس</option>
    <!-- اضافه کردن گزینه Cloudinary -->
    <option value="cloudinary" <?php selected($settings['upload_method'], 'cloudinary'); ?>>Cloudinary</option>
</select>
                            <p class="description">
                                سرویس مورد نظر برای میزبانی تصاویر را انتخاب کنید
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Cloudinary API Key -->
<tr id="tr_cloudinary_api_key" class="iur-service-field" style="display: none;">
  <th scope="row"><label for="cloudinary_api_key">Cloudinary API Key</label></th>
  <td>
    <input type="text" name="iur_settings[cloudinary_api_key]" id="cloudinary_api_key"
           value="<?php echo esc_attr($settings['cloudinary_api_key'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
  </td>
</tr>

<!-- Cloudinary API Secret -->
<tr id="tr_cloudinary_api_secret" class="iur-service-field" style="display: none;">
  <th scope="row"><label for="cloudinary_api_secret">Cloudinary API Secret</label></th>
  <td>
    <input type="password" name="iur_settings[cloudinary_api_secret]" id="cloudinary_api_secret"
           value="<?php echo esc_attr($settings['cloudinary_api_secret'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
  </td>
</tr>

<!-- Cloudinary Cloud Name -->
<tr id="tr_cloudinary_cloud_name" class="iur-service-field" style="display: none;">
  <th scope="row"><label for="cloudinary_cloud_name">Cloudinary Cloud Name</label></th>
  <td>
    <input type="text" name="iur_settings[cloudinary_cloud_name]" id="cloudinary_cloud_name"
           value="<?php echo esc_attr($settings['cloudinary_cloud_name'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
  </td>
</tr>
                    
                    <!-- فیلد FreeImage API -->
                    <tr id="tr_freeimage_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'freeimage') ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><label for="freeimage_api_key">کلید API FreeImage.host</label></th>
                        <td>
                            <input type="password" name="iur_settings[freeimage_api_key]" id="freeimage_api_key"
                                value="<?php echo esc_attr($settings['freeimage_api_key']); ?>" 
                                class="regular-text" autocomplete="off">
                            <p class="description">
                                دریافت کلید API از <a href="https://freeimage.host/api/" target="_blank">مستندات FreeImage.host</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- فیلد ImgBB API -->
                    <tr id="tr_imgbb_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'imgbb') ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><label for="imgbb_api_key">کلید API ImgBB</label></th>
                        <td>
                            <input type="password" name="iur_settings[imgbb_api_key]" id="imgbb_api_key"
                                value="<?php echo esc_attr($settings['imgbb_api_key']); ?>" 
                                class="regular-text" autocomplete="off">
                            <p class="description">
                                دریافت کلید API از <a href="https://api.imgbb.com/" target="_blank">مستندات ImgBB</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2 class="title">تنظیمات پردازش</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">جایگزینی خودکار</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_replace" value="1" 
                                    <?php checked($settings['auto_replace'], 'yes'); ?>>
                                جایگزینی خودکار تصاویر هنگام ذخیره پست
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">انواع پست‌ها</th>
                        <td>
                            <?php foreach (get_post_types(['public' => true], 'objects') as $post_type) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="post_types[]" 
                                        value="<?php echo esc_attr($post_type->name); ?>" 
                                        <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2 class="title">تنظیمات پیشرفته</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">ردیابی تصاویر پردازش شده</th>
                        <td>
                            <label>
                                <input type="checkbox" name="skip_existing" value="1" 
                                    <?php checked($settings['skip_existing'] ?? 0, 1); ?>>
                                عدم پردازش تصاویر جایگزین شده قبلی
<div class="wrap iur-settings">
    <h1><?php esc_html_e('تنظیمات جایگزینی تصاویر', 'iur'); ?></h1>
    
    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success"><p><?php esc_html_e('تنظیمات با موفقیت ذخیره شد!', 'iur'); ?></p></div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="iur_save_settings">
        <?php wp_nonce_field('iur_settings_nonce', '_iur_nonce'); ?>
        
        <?php do_action('iur_settings_before_api_section'); ?>
        
        <div class="card">
            <h2 class="title"><?php esc_html_e('تنظیمات API', 'iur'); ?></h2>
            <?php include plugin_dir_path(__FILE__) . 'admin/partials/settings-api.php'; ?>
        </div>
        
        <?php submit_button(__('ذخیره تنظیمات', 'iur')); ?>
    </form>
</div>
    <script>
document.addEventListener("DOMContentLoaded", function () {
  const methodSelect = document.getElementById("iur_upload_method");
  const allApiFields = document.querySelectorAll(".iur-api-field, .iur-service-field");

  function updateFieldVisibility() {
    const selected = methodSelect.value;
    allApiFields.forEach(row => {
      row.style.display = "none";
    });
    if (selected === "freeimage") {
      document.getElementById("tr_freeimage_api_key").style.display = "table-row";
    } else if (selected === "imgbb") {
      document.getElementById("tr_imgbb_api_key").style.display = "table-row";
    } else if (selected === "cloudinary") {
      document.getElementById("tr_cloudinary_api_key").style.display = "table-row";
      document.getElementById("tr_cloudinary_api_secret").style.display = "table-row";
      document.getElementById("tr_cloudinary_cloud_name").style.display = "table-row";
    }
  }

  methodSelect.addEventListener("change", updateFieldVisibility);
  updateFieldVisibility(); // اجرا هنگام بارگذاری اولیه
});
</script>
    <script>
  var iur_vars = {
    nonce: '<?php echo wp_create_nonce("iur_process_all_nonce"); ?>'
  };
</script>
    <?php
}