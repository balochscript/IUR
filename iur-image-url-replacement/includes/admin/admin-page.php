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
                                <option value="freeimage" <?php selected($settings['upload_method'], 'freeimage'); ?>>FreeImage.host</option>
                                <option value="imgbb" <?php selected($settings['upload_method'], 'imgbb'); ?>>ImgBB</option>
                                <option value="wordpress" <?php selected($settings['upload_method'], 'wordpress'); ?>>کتابخانه رسانه وردپرس</option>
                            </select>
                            <p class="description">
                                سرویس مورد نظر برای میزبانی تصاویر را انتخاب کنید
                            </p>
                        </td>
                    </tr>
                    
                    <!-- فیلد FreeImage API -->
                    <tr id="tr_freeimage_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'freeimage') ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><label for="freeimage_api_key">کلید API FreeImage.host</label></th>
                        <td>
                            <input type="password" name="freeimage_api_key" id="freeimage_api_key"
                                value="<?php echo esc_attr($settings['freeimage_api_key']); ?>" 
                                class="regular-text">
                            <p class="description">
                                دریافت کلید API از <a href="https://freeimage.host/api/" target="_blank">مستندات FreeImage.host</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- فیلد ImgBB API -->
                    <tr id="tr_imgbb_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'imgbb') ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><label for="imgbb_api_key">کلید API ImgBB</label></th>
                        <td>
                            <input type="password" name="imgbb_api_key" id="imgbb_api_key"
                                value="<?php echo esc_attr($settings['imgbb_api_key']); ?>" 
                                class="regular-text">
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
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کیفیت تصویر</th>
                        <td>
                            <select name="quality" class="regular-text">
                                <option value="high" <?php selected($settings['quality'] ?? 'high', 'high'); ?>>بالا</option>
                                <option value="medium" <?php selected($settings['quality'] ?? 'high', 'medium'); ?>>متوسط</option>
                                <option value="low" <?php selected($settings['quality'] ?? 'high', 'low'); ?>>پایین</option>
                            </select>
                            <p class="description">
                                توجه: کیفیت بالاتر به معنی حجم فایل بیشتر است
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    </div>
    <?php
}
