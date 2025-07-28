<?php
/**
 * IUR Advanced Settings
 *
 * This file contains the advanced settings section for the Image URL Replacement plugin.
 */

// Prevent direct access to the file
if ( !defined('ABSPATH') ) {
    exit;
}

// Get current settings with default values
$settings = get_option('iur_settings', [
    'skip_existing'      => 0,
    'post_types'         => ['post'],
    'quality'            => 'high',
    'delete_after_replace'=> 0,
    'max_width'          => 0,
    'max_height'         => 0
]);
?>

<?php $post_types = get_post_types(['public' => true], 'objects'); ?>

<table class="form-table">

  <!-- Included Post Types -->
  <tr>
    <th scope="row">
      <label><?php esc_html_e('Included Post Types', 'iur'); ?></label>
    </th>
    <td>
      <fieldset>
        <?php foreach ($post_types as $type): ?>
          <label style="display: inline-block; margin: 6px 16px 6px 0;">
            <input type="checkbox" name="iur_settings[post_types][]"
              value="<?php echo esc_attr($type->name); ?>"
              <?php checked(in_array($type->name, $settings['post_types'] ?? [])); ?>>
            <?php echo esc_html($type->labels->singular_name); ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <p class="description" style="margin-top: 6px; font-size: 13px; color: #555;">
        <?php esc_html_e('Choose which post types the plugin should process (e.g. posts, pages, products, portfolios).', 'iur'); ?>
      </p>
    </td>
  </tr>

  <!-- Already Processed Tracking -->
  <tr>
    <th scope="row">
      <label for="iur_skip_existing"><?php esc_html_e('Already Processed Tracking', 'iur'); ?></label>
    </th>
    <td>
      <label>
        <input type="checkbox" name="iur_settings[skip_existing]" id="iur_skip_existing"
          value="1" <?php checked($settings['skip_existing'] ?? 0, 1); ?>>
        <?php esc_html_e('Skip images that were already replaced before.', 'iur'); ?>
      </label>
      <p class="description">
        <?php esc_html_e('If enabled, images that have already been processed will not be replaced again.', 'iur'); ?>
      </p>
    </td>
  </tr>

  <!-- Image Quality -->
  <tr>
    <th scope="row">
      <label for="iur_quality"><?php esc_html_e('Image Quality', 'iur'); ?></label>
    </th>
    <td>
      <select name="iur_settings[quality]" id="iur_quality" class="regular-text">
        <option value="original" <?php selected($settings['quality'] ?? 'high', 'original'); ?>>
          <?php esc_html_e('Original (No change)', 'iur'); ?>
        </option>
        <option value="high" <?php selected($settings['quality'] ?? 'high', 'high'); ?>>
          <?php esc_html_e('High Quality (80–90%)', 'iur'); ?>
        </option>
        <option value="medium" <?php selected($settings['quality'] ?? 'high', 'medium'); ?>>
          <?php esc_html_e('Medium Quality (60–70%)', 'iur'); ?>
        </option>
        <option value="low" <?php selected($settings['quality'] ?? 'high', 'low'); ?>>
          <?php esc_html_e('Low Quality (30–50%)', 'iur'); ?>
        </option>
      </select>
      <p class="description">
        <?php esc_html_e('Choose how much the image should be compressed before uploading. Select "Original" to send files without any changes.', 'iur'); ?>
      </p>
    </td>
  </tr>

  <!-- Delete Original After Replacement -->
  <tr>
    <th scope="row">
      <label for="iur_delete_after_replace"><?php esc_html_e('Delete Original After Replacement', 'iur'); ?></label>
    </th>
    <td>
      <label>
        <input type="checkbox" name="iur_settings[delete_after_replace]" id="iur_delete_after_replace"
          value="1" <?php checked($settings['delete_after_replace'] ?? 0, 1); ?>>
        <?php esc_html_e('Permanently remove original images once replacement is successful.', 'iur'); ?>
      </label>
      <p class="description" style="color: #dc3545; font-weight: 500;">
        &#9888; <?php esc_html_e('Warning: This action cannot be undone. Original files will be deleted permanently.', 'iur'); ?>
      </p>
    </td>
  </tr>

  <!-- Maximum Image Dimensions -->
  <tr>
    <th scope="row">
      <label><?php esc_html_e('Maximum Image Dimensions', 'iur'); ?></label>
    </th>
    <td>
      <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div>
          <label for="iur_max_width" style="display: block; margin-bottom: 4px;">
            <?php esc_html_e('Width (pixels)', 'iur'); ?>
          </label>
          <input type="number" name="iur_settings[max_width]" id="iur_max_width"
            value="<?php echo esc_attr($settings['max_width'] ?? 0); ?>"
            class="small-text" min="0" style="width: 80px;">
        </div>
        <div>
          <label for="iur_max_height" style="display: block; margin-bottom: 4px;">
            <?php esc_html_e('Height (pixels)', 'iur'); ?>
          </label>
          <input type="number" name="iur_settings[max_height]" id="iur_max_height"
            value="<?php echo esc_attr($settings['max_height'] ?? 0); ?>"
            class="small-text" min="0" style="width: 80px;">
        </div>
      </div>
      <p class="description">
        <?php esc_html_e('Used to resize images before uploading. A value of 0 means no resizing will be applied.', 'iur'); ?>
      </p>
    </td>
  </tr>

</table>
