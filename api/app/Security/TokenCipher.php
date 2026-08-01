<?php

declare(strict_types=1);

namespace WhatstheUp\Security;

use WhatstheUp\Support\Env;

final class TokenCipher
{
    public function encrypt(string $plaintext): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key());
        return ['ciphertext' => base64_encode($ciphertext), 'nonce' => base64_encode($nonce), 'keyVersion' => 1];
    }

    public function decrypt(string $ciphertext, string $nonce): string
    {
        $plaintext = sodium_crypto_secretbox_open(base64_decode($ciphertext, true) ?: '', base64_decode($nonce, true) ?: '', $this->key());
        if ($plaintext === false) {
            throw new \RuntimeException('Stored token could not be decrypted.');
        }
        return $plaintext;
    }

    private function key(): string
    {
        $configured = Env::get('TOKEN_ENCRYPTION_KEY', '') ?? '';
        $encoded = str_starts_with($configured, 'base64:') ? substr($configured, 7) : $configured;
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('TOKEN_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }
        return $key;
    }
}
