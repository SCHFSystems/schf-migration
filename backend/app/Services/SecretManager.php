<?php

namespace App\Services;

class SecretManager
{
    private string $cipher = 'aes-256-cbc';

    private string $key;

    public function __construct(?string $appKey = null)
    {
        $this->key = $this->deriveKey(
            $appKey ?? getenv('APP_KEY') ?: ''
        );
    }

    public function encrypt(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $ciphertext = openssl_encrypt($plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        $data = base64_decode($payload, true);
        if ($data === false) {
            return '';
        }
        $ivLen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLen);
        $ciphertext = substr($data, $ivLen);
        return openssl_decrypt($ciphertext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv) ?: '';
    }

    private function deriveKey(string $base): string
    {
        if (str_starts_with($base, 'base64:')) {
            $base = base64_decode(substr($base, 7));
        }
        return hash_hkdf('sha256', $base, 32, 'schf-secret-manager');
    }
}
