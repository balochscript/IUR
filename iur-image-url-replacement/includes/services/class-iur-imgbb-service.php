<?php
// includes/services/class-iur-imgbb-service.php
class IUR_ImgBB_Service implements IUR_Upload_Interface {
    private $api_key;
    private $quality;
    private $timeout;
    
    public function __construct($api_key, $quality = 'high', $timeout= '30') {
        $this->api_key = $api_key;
        $this->quality = $quality;
        $this->timeout = $timeout;
    }
    
    private function validate_image_data($image_data) {
    // بررسی MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('نوع تصویر مجاز نیست: ' . $mime_type);
    }
    // حجم حداکثر (مثلاً ۱۰ مگابایت)
    if (strlen($image_data) > 10 * 1024 * 1024) {
        throw new Exception('حجم تصویر بیش از حد مجاز است');
    }
    return $mime_type;
}

    
    public function upload($image_data, $post_id = 0) {
        $this->validate_credentials();
        $mime_type = $this->validate_image_data($image_data);
        
        $response = wp_remote_post('https://api.imgbb.com/1/upload', [
            'body' => [
                'key' => $this->api_key,
                'image' => base64_encode($image_data),
                'quality' => $this->map_quality()
            ],
            'timeout' => $this->timeout,
            'headers' => ['User-Agent' => 'WordPress/IUR-Plugin; ' . home_url()]
        ]);
        
        $data = $this->parse_response($response);
        return $data['data']['url'];
    }
    
    public function validate_credentials() {
        if (empty($this->api_key)) {
            throw new Exception('ImgBB API key is required');
        }
    }
    
    private function map_quality() {
    $quality_map = [
        'low' => 50,
        'medium' => 75,
        'high' => 90,
        'original' => 100
    ];
    return $quality_map[$this->quality] ?? 90;
}
    
    private function parse_response($response) {
    if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    error_log('ImgBB Upload Error: ' . $error_message);
    throw new Exception('HTTP Error: ' . $error_message);
}

$status_code = wp_remote_retrieve_response_code($response);
$body = json_decode(wp_remote_retrieve_body($response), true);

if ($status_code !== 200) {
    $error = $body['error']['message'] ?? 'Unknown error';
    throw new Exception("ImgBB API Error ($status_code): $error");
}

if (empty($body['data']['url'])) {
    throw new Exception('Invalid response from ImgBB API');
}

return $body;

}
    
    public function get_method_name() {
        return 'imgbb';
    }
}