<?php
require_once __DIR__ . '/../config/config.php';

/**
 * AES-256-GCM helper for encrypting Meta access tokens at rest.
 */
final class Encryption
{
    private static function key(): string
    {
        $key = TOKEN_ENC_KEY;
        // Accept either a 64-char hex string or raw 32-byte string.
        if (ctype_xdigit($key) && strlen($key) === 64) {
            return hex2bin($key);
        }
        return substr(hash('sha256', $key, true), 0, 32);
    }

    public static function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Invalid ciphertext');
        }
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (bad key or tampered data)');
        }
        return $plain;
    }
}
