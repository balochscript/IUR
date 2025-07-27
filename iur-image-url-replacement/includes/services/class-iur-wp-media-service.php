<?php
// includes/services/class-iur-wp-media-service.php
class IUR_WP_Media_Service implements IUR_Upload_Interface {
    private function create_temp_file($image_data) {
    $temp_dir = get_temp_dir();
    $temp_file = tempnam($temp_dir, 'iur_');
    
    if (file_put_contents($temp_file, $image_data) === false) {
        throw new Exception('Failed to create temporary file');
    }
    
    return $temp_file;
}

private function generate_filename() {
    return 'iur_upload_' . uniqid() . '.jpg';
}

public function upload($image_data, $post_id = 0) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp_file = $this->create_temp_file($image_data);
    $file_array = [
        'name' => $this->generate_filename(),
        'tmp_name' => $tmp_file
    ];
    
    $attachment_id = media_handle_sideload($file_array, $post_id);
    
    // حتماً فایل موقت را پاک کنید
    @unlink($tmp_file);
    
    if (is_wp_error($attachment_id)) {
        throw new Exception($attachment_id->get_error_message());
    }
    
    return wp_get_attachment_url($attachment_id);
}
    
    public function get_method_name() {
        return 'wordpress';
    }
    
    public function validate_credentials() {
        // No validation needed for WordPress
    }
}