<?php
if (!defined('ABSPATH')) exit;

final class IA_Engine_Crypto {
    private const CIPHER = 'aes-256-cbc';

    private static function key(): string {
        // Use WP salts/keys; stable per installation.
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . '|' . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . '|' . (defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');
        if (trim($material) === '||') {
            $material = wp_salt('auth');
        }
        return hash('sha256', $material, true); // 32 bytes
    }

    public static function encrypt(string $plain): string {
        $plain = (string)$plain;
        if ($plain === '') return '';

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLen);

        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) return '';

        // Store as base64(iv || cipher)
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $packed): string {
        $packed = (string)$packed;
        if ($packed === '') return '';

        $raw = base64_decode($packed, true);
        if ($raw === false) return '';

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= $ivLen) return '';

        $iv = substr($raw, 0, $ivLen);
        $cipher = substr($raw, $ivLen);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        return ($plain === false) ? '' : (string)$plain;
    }
}
