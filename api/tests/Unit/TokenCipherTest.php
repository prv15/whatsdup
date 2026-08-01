<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Security\TokenCipher;

final class TokenCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsWithoutStoringPlaintext(): void
    {
        putenv('TOKEN_ENCRYPTION_KEY=base64:' . base64_encode(str_repeat('k', 32)));
        $cipher = new TokenCipher();
        $encrypted = $cipher->encrypt('secret-meta-token');
        self::assertStringNotContainsString('secret-meta-token', $encrypted['ciphertext']);
        self::assertSame('secret-meta-token', $cipher->decrypt($encrypted['ciphertext'], $encrypted['nonce']));
    }
}
