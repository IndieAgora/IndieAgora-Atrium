<?php
if (!defined('ABSPATH')) { exit; }

class IA_Auth_Crypto {

    private function key() {
        // Derive a stable 32-byte key from WP salts.
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . '|' . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '');
        return hash('sha256', $material, true);
    }

    public function encrypt(string $plaintext) : string {
        if ($plaintext === '') return '';
        $key = $this->key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) return '';
        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $ciphertext_b64) : string {
        if ($ciphertext_b64 === '') return '';
        $raw = base64_decode($ciphertext_b64, true);
        if ($raw === false || strlen($raw) < 17) return '';
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}
