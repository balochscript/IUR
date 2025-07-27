<?php
/**
 * تنظیمات API افزونه IUR
 * 
 * این فایل شامل بخش تنظیمات سرویس‌های آپلود تصاویر می‌باشد
 */

if (!defined('ABSPATH')) {
    exit; // جلوگیری از دسترسی مستقیم
}

$settings = get_option('iur_settings', [
    'upload_method' => 'freeimage',
    'freeimage_api_key' => '',
    'imgbb_api_key' => '',
    'cloudinary_api_key' => '',
    'cloudinary_api_secret' => '',
    'cloudinary_cloud_name' => '',
    'cloudinary_folder' => 'iur_uploads', // مقدار پیش‌فرض برای پوشه
    'cloudinary_secure' => 1              // مقدار پیش‌فرض برای لینک امن
]);
?>

<table class="form-table">
    <!-- انتخاب سرویس آپلود -->
    <?php
$method_icons = [
  'freeimage'  => ['label' => esc_html__('FreeImage.host', 'iur'),     'icon' => '🖼️'],
  'imgbb'      => ['label' => esc_html__('ImgBB', 'iur'),             'icon' => '🧩'],
  'cloudinary' => ['label' => esc_html__('Cloudinary', 'iur'),        'icon' => '☁️'],
  'wordpress'  => ['label' => esc_html__('WordPress Media Library', 'iur'), 'icon' => '📁'],
];

$selected = $settings['upload_method'] ?? '';
if (!empty($method_icons[$selected])) {
  $label = $method_icons[$selected]['label'];
  $icon  = $method_icons[$selected]['icon'];
  echo '<p style="margin-top:10px; background:#eafaf1; padding:6px 12px; border-left:4px solid #28a745; border-radius:4px;">';
  echo '<strong>' . $icon . ' ' . esc_html__('Selected:', 'iur') . '</strong> <span style="color:#155724;">' . $label . '</span>';
  echo '</p>';
} else {
  echo '<p style="margin-top:10px; background:#fcebea; padding:6px 12px; border-left:4px solid #dc3545; border-radius:4px;">';
  echo '<strong>⚠️ ' . esc_html__('No upload service selected.', 'iur') . '</strong>';
  echo '</p>';
}
?>

<tr>
  <th scope="row">
    <label for="iur_upload_method"><?php esc_html_e('Upload Service', 'iur'); ?></label>
  </th>
  <td>
    <select name="iur_settings[upload_method]" id="iur_upload_method">
      <option value="freeimage" <?php selected($selected, 'freeimage'); ?>>FreeImage.host</option>
      <option value="imgbb" <?php selected($selected, 'imgbb'); ?>>ImgBB</option>
      <option value="cloudinary" <?php selected($selected, 'cloudinary'); ?>>Cloudinary</option>
      <option value="wordpress" <?php selected($selected, 'wordpress'); ?>>WordPress Media Library</option>
    </select>
    <p class="description"><?php esc_html_e('Select the service you want to use for uploading images.', 'iur'); ?></p>
  </td>
</tr>

    <!-- تنظیمات FreeImage.host -->
<tr id="tr_freeimage_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'freeimage') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_freeimage_api_key"><?php esc_html_e('FreeImage.host API Key', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="password" name="iur_settings[freeimage][api_key]" id="iur_freeimage_api_key"
           value="<?php echo esc_attr($settings['freeimage']['api_key'] ?? ''); ?>" 
           class="regular-text" autocomplete="off">
    <p class="description">
      <?php 
      printf(
        esc_html__('Get your API key from %s', 'iur'),
        '<a href="https://freeimage.host/api/" target="_blank" rel="noopener noreferrer">FreeImage.host documentation</a>'
      ); 
      ?>
    </p>
  </td>
</tr>

<!-- تنظیمات ImgBB -->
<tr id="tr_imgbb_api_key" class="iur-api-field" style="display: <?php echo ($settings['upload_method'] === 'imgbb') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_imgbb_api_key"><?php esc_html_e('ImgBB API Key', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="password" name="iur_settings[imgbb][api_key]" id="iur_imgbb_api_key"
           value="<?php echo esc_attr($settings['imgbb']['api_key'] ?? ''); ?>" 
           class="regular-text" autocomplete="off">
    <p class="description">
      <?php 
      printf(
        esc_html__('Get your API key from %s', 'iur'),
        '<a href="https://api.imgbb.com/" target="_blank" rel="noopener noreferrer" style="color:#6A9CA5; text-decoration:underline;">ImgBB documentation</a>'
      ); 
      ?>
    </p>
  </td>
</tr>

<!-- تنظیمات Cloudinary -->
<tr id="tr_cloudinary_api_key" class="iur-service-field" style="display: <?php echo ($settings['upload_method'] === 'cloudinary') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_cloudinary_api_key"><?php esc_html_e('Cloudinary API Key', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="text" name="iur_settings[cloudinary][api_key]" id="iur_cloudinary_api_key"
           value="<?php echo esc_attr($settings['cloudinary']['api_key'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
  </td>
</tr>

<tr id="tr_cloudinary_api_secret" class="iur-service-field" style="display: <?php echo ($settings['upload_method'] === 'cloudinary') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_cloudinary_api_secret"><?php esc_html_e('Cloudinary API Secret', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="password" name="iur_settings[cloudinary][api_secret]" id="iur_cloudinary_api_secret"
           value="<?php echo esc_attr($settings['cloudinary']['api_secret'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
  </td>
</tr>

<tr id="tr_cloudinary_cloud_name" class="iur-service-field" style="display: <?php echo ($settings['upload_method'] === 'cloudinary') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_cloudinary_cloud_name"><?php esc_html_e('Cloudinary Cloud Name', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="text" name="iur_settings[cloudinary][cloud_name]" id="iur_cloudinary_cloud_name"
           value="<?php echo esc_attr($settings['cloudinary']['cloud_name'] ?? ''); ?>"
           class="regular-text" autocomplete="off">
    <p class="description">
      <?php esc_html_e('The cloud name you set up in your Cloudinary account.', 'iur'); ?>
    </p>
  </td>
</tr>

<!-- فیلد پوشه Cloudinary -->
<tr id="tr_cloudinary_folder" class="iur-service-field" style="display: <?php echo ($settings['upload_method'] === 'cloudinary') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_cloudinary_folder"><?php esc_html_e('Cloudinary Folder', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="text" name="iur_settings[cloudinary][folder]" id="iur_cloudinary_folder"
           value="<?php echo esc_attr($settings['cloudinary']['folder'] ?? 'iur_uploads'); ?>"
           class="regular-text" autocomplete="off">
    <p class="description">
      <?php esc_html_e('The folder in Cloudinary where images will be stored.', 'iur'); ?>
    </p>
  </td>
</tr>

<!-- فیلد لینک‌های امن Cloudinary -->
<tr id="tr_cloudinary_secure" class="iur-service-field" style="display: <?php echo ($settings['upload_method'] === 'cloudinary') ? 'table-row' : 'none'; ?>;">
  <th scope="row">
    <label for="iur_cloudinary_secure"><?php esc_html_e('Use secure URL', 'iur'); ?></label>
  </th>
  <td>
    <!-- تغییر نام فیلد -->
    <input type="checkbox" name="iur_settings[cloudinary][secure]" id="iur_cloudinary_secure"
           value="1" <?php checked($settings['cloudinary']['secure'] ?? 1, 1); ?>>
    <p class="description">
      <?php esc_html_e('Serve images over HTTPS.', 'iur'); ?>
    </p>
  </td>
</tr>
<form>
  <?php wp_nonce_field('iur_test_connection_action', 'nonce'); ?>
<button id="iur-test-connection" class="button"><?php _e('Test Connection', 'iur'); ?></button>
</form>
    <!-- تست اتصال -->
    <tr>
  <th scope="row"><?php esc_html_e('Connection Test', 'iur'); ?></th>
  <td>
    <button type="button" id="iur_test_connection" class="button"
            style="background-color: #6A9CA5; border-color: #6A9CA5; color: #fff;">
      <?php esc_html_e('Test connection to service', 'iur'); ?>
    </button>
    
    <span id="iur_test_result" style="margin-left: 10px;"></span>
    
    <p class="description">
      <?php esc_html_e('After saving your settings, test the API connection here.', 'iur'); ?>
    </p>
  </td>
</tr>
</table>

<!-- اسکریپت مدیریت نمایش فیلدها -->
<script>
jQuery(document).ready(function($) {
    // نمایش/مخفی کردن فیلدهای مربوط به هر سرویس
    function toggleApiFields() {
        $('.iur-api-field, .iur-service-field').hide();
        
        switch($('#iur_upload_method').val()) {
            case 'freeimage':
                $('#tr_freeimage_api_key').show();
                break;
            case 'imgbb':
                $('#tr_imgbb_api_key').show();
                break;
            case 'cloudinary':
                $('#tr_cloudinary_api_key, #tr_cloudinary_api_secret, #tr_cloudinary_cloud_name, #tr_cloudinary_folder, #tr_cloudinary_secure').show();
                break;
        }
    }

    $('#iur_upload_method').change(toggleApiFields);
    toggleApiFields(); // مقداردهی اولیه

    // تست اتصال
    $('#iur_test_connection').click(function () {
    const service = $('#iur_upload_method').val();
    let valid = true;
    let error = '';

    if (service === 'freeimage' && !$('#iur_freeimage_api_key').val()) {
        valid = false;
        error = '❌ FreeImage API key is missing.';
    }
    else if (service === 'imgbb' && !$('#iur_imgbb_api_key').val()) {
        valid = false;
        error = '❌ ImgBB API key is missing.';
    }
    else if (service === 'cloudinary') {
        if (
            !$('#iur_cloudinary_api_key').val() ||
            !$('#iur_cloudinary_api_secret').val() ||
            !$('#iur_cloudinary_cloud_name').val()
        ) {
            valid = false;
            error = '❌ Cloudinary credentials are incomplete.';
        }
    }

    if (!valid) {
        $('#iur_test_result').html('<span style="color:red;">' + error + '</span>');
        return;
    }

    $('#iur_test_result').html('<span class="spinner is-active"></span>');

    $.post(ajaxurl, {
        action: 'iur_test_api_connection',
        service: service,
        _wpnonce: '<?php echo wp_create_nonce('iur_test_api_nonce'); ?>'
    }, function (response) {
        if (response.success) {
            $('#iur_test_result').html('<span style="color:green;">✓ ' + response.data.message + '</span>');
        } else {
            $('#iur_test_result').html('<span style="color:red;">✗ ' + response.data.message + '</span>');
        }
    });
});
});
</script>