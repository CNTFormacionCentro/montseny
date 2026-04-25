<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Montseny_Crypto {
    private static $method = 'aes-256-cbc';

    public static function encrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function decrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $parts = explode('::', base64_decode($data), 2);
        if(count($parts) < 2) return '---';
        list($encrypted_data, $iv) = $parts;
        return openssl_decrypt($encrypted_data, self::$method, $key, 0, $iv);
    }
}
