<?php
// includes/interfaces/class-iur-upload-interface.php
interface IUR_Upload_Interface {
    public function upload($image_data, $post_id = 0);
    public function get_method_name();
    public function validate_credentials();
}
