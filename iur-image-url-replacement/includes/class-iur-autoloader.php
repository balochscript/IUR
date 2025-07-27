<?php
class IUR_Autoloader {
    private $loaded_dependencies = [];
    
    public function init() {
        $this->register_autoloader();
        $this->load_core_dependencies();
        $this->load_service_based_on_settings();
    }
    
    /**
     * Register the autoloader function
     */
    private function register_autoloader() {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Autoload class files
     */
    private function autoload($class_name) {
        if (strpos($class_name, 'IUR_') === 0) {
            $file_path = $this->generate_class_file_path($class_name);
            
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_dependencies[] = $class_name;
            } else {
                error_log("IUR Autoload failed for: {$class_name} at {$file_path}");
            }
        }
    }

    /**
     * Generate file path for a class
     */
    private function generate_class_file_path($class_name) {
        $file_name = strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        // جستجو در پوشه admin
        $admin_path = IUR_PLUGIN_DIR . 'admin/' . $file_name;
        if (file_exists($admin_path)) {
            return $admin_path;
        }
        
        // جستجو در پوشه includes
        $includes_path = IUR_PLUGIN_DIR . 'includes/' . $file_name;
        if (file_exists($includes_path)) {
            return $includes_path;
        }
        
        // جستجو در پوشه handlers
        $handlers_path = IUR_PLUGIN_DIR . 'includes/handlers/' . $file_name;
        if (file_exists($handlers_path)) {
            return $handlers_path;
        }
        
        // جستجو در پوشه services
        $services_path = IUR_PLUGIN_DIR . 'includes/services/' . $file_name;
        if (file_exists($services_path)) {
            return $services_path;
        }
        
        // جستجو در پوشه interfaces
        $interfaces_path = IUR_PLUGIN_DIR . 'includes/interfaces/' . $file_name;
        if (file_exists($interfaces_path)) {
            return $interfaces_path;
        }
        
        // اگر پیدا نشد، مسیر پیش‌فرض
        return IUR_PLUGIN_DIR . 'includes/' . $file_name;
    }

    /**
     * Load core plugin dependencies
     */
    private function load_core_dependencies() {
        $core_dependencies = [
            'interfaces/class-iur-upload-interface',
            'handlers/class-iur-error-handler',
            'handlers/class-iur-bulk-handler',
            'class-iur-processor',
            'class-iur-settings',
            'class-iur-uploader',
            'class-iur-ajax-handler',
            'class-iur-uploader-factory',
            'helpers'
        ];

        foreach ($core_dependencies as $file) {
            $file_path = IUR_PLUGIN_DIR . 'includes/' . $file . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_dependencies[] = $file;
            } else {
                error_log("IUR Missing dependency: {$file_path}");
            }
        }
        
        // بارگذاری وابستگی‌های پوشه admin
        $admin_dependencies = [
            'class-admin-notices',
            'class-iur-admin',
            'admin-page'
        ];
        
        foreach ($admin_dependencies as $file) {
            $file_path = IUR_PLUGIN_DIR . 'admin/' . $file . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_dependencies[] = $file;
            } else {
                error_log("IUR Missing admin dependency: {$file_path}");
            }
        }
    }

    /**
     * Load service based on plugin settings
     */
    private function load_service_based_on_settings() {
        $settings = IUR_Settings::get_instance();
        $method = $settings->get('upload_method', 'freeimage');
        
        $service_map = [
            'cloudinary' => 'services/class-iur-cloudinary-service',
            'imgbb' => 'services/class-iur-imgbb-service',
            'wordpress' => 'services/class-iur-wp-media-service',
            'freeimage' => 'services/class-iur-freeimage-service'
        ];

        if (isset($service_map[$method])) {
            $service_path = IUR_PLUGIN_DIR . 'includes/' . $service_map[$method] . '.php';
            
            if (file_exists($service_path)) {
                require_once $service_path;
                $this->loaded_dependencies[] = $service_map[$method];
            } else {
                error_log("IUR Missing service file: {$service_path}");
                // Fallback to default service
                $fallback_path = IUR_PLUGIN_DIR . 'includes/services/class-iur-freeimage-service.php';
                if (file_exists($fallback_path)) {
                    require_once $fallback_path;
                }
            }
        }
    }

    /**
     * Get list of loaded dependencies
     */
    public function get_loaded_dependencies() {
        return $this->loaded_dependencies;
    }
}