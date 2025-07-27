<?php
class IUR_Uploader_Factory {
  private $settings;
private $error_handler;

public function __construct(IUR_Settings $settings, IUR_Error_Handler $error_handler) {
    $this->settings      = $settings;
    $this->error_handler = $error_handler;
}
  
    public function create($service) {
    try {
        switch ($service) {
            case 'freeimage':
                $key = $this->settings->get('freeimage.api_key');
                if (empty($key)) {
                    throw new Exception('FreeImage API key not configured');
                }
                return new IUR_FreeImage_Service($key, $this->settings->get('quality'), $this->settings->get('timeout'));
            case 'imgbb':
                $key = $this->settings->get('imgbb.api_key');
                if (empty($key)) {
                    throw new Exception('ImgBB API key not configured');
                }
                return new IUR_ImgBB_Service($key, $this->settings->get('quality'), $this->settings->get('timeout'));
            case 'cloudinary':
                $cfg = $this->settings->get('cloudinary');
                foreach (['api_key','api_secret','cloud_name'] as $f) {
                    if (empty($cfg[$f])) {
                        throw new Exception("Cloudinary {$f} not configured");
                    }
                }
                return new IUR_Cloudinary_Service($cfg, $this->settings->get('timeout'));
            case 'wordpress':
                return new IUR_WP_Media_Service();
            default:
                throw new Exception("Unsupported upload service: {$service}");
        }
    } catch (Exception $e) {
        $this->error_handler->log('Uploader Factory error: '.$e->getMessage(), 'critical', ['service'=>$service]);
        throw $e;
    }
}

public function get_supported_services(): array {
    return [
        'freeimage'  => __('FreeImage.host','iur'),
        'imgbb'      => __('ImgBB','iur'),
        'cloudinary' => __('Cloudinary','iur'),
        'wordpress'  => __('WordPress Media Library','iur'),
    ];
}

}