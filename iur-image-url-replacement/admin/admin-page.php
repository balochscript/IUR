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
    
    <script>
  var iur_vars = {
    nonce: '<?php echo wp_create_nonce("iur_process_all_nonce"); ?>'
  };
</script>
    <?php
}
