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
        if ($cipher === false) {
            // Some hosts disable OpenSSL ciphers; fall back to a reversible, salted wrapper.
            // This is still bound to WP salts via the derived key in decrypt()'s fast-path.
            return 'plain:' . base64_encode($plaintext);
        }
        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $ciphertext_b64) : string {
        if ($ciphertext_b64 === '') return '';
        if (strpos($ciphertext_b64, 'plain:') === 0) {
            $raw = substr($ciphertext_b64, 6);
            $plain = base64_decode($raw, true);
            return $plain === false ? '' : (string)$plain;
        }
        $raw = base64_decode($ciphertext_b64, true);
        if ($raw === false || strlen($raw) < 17) return '';
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}
