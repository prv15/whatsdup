<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Security\Jwt;

final class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('JWT_SECRET=test-secret-with-at-least-thirty-two-characters');
        putenv('JWT_ACCESS_TTL=900');
        putenv('APP_URL=https://api.example.test');
    }

    public function testIssuesAndVerifiesSignedToken(): void
    {
        $token = Jwt::issue(['sub' => 'user-1', 'sid' => 'session-1', 'bid' => 'business-1']);
        $claims = Jwt::verify($token);
        self::assertSame('user-1', $claims['sub']);
        self::assertSame('business-1', $claims['bid']);
        self::assertGreaterThan(time(), $claims['exp']);
    }
}
