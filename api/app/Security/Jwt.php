<?php

declare(strict_types=1);

namespace WhatstheUp\Security;

use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;

final class Jwt
{
    public static function issue(array $claims): string
    {
        $now = time();
        $claims += ['iat' => $now, 'nbf' => $now, 'exp' => $now + Env::int('JWT_ACCESS_TTL', 900), 'iss' => Env::get('APP_URL')];
        $header = self::encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = self::encode(hash_hmac('sha256', "{$header}.{$payload}", self::secret(), true));
        return "{$header}.{$payload}.{$signature}";
    }

    public static function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        [$header, $payload, $signature] = $parts;
        $expected = self::encode(hash_hmac('sha256', "{$header}.{$payload}", self::secret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        $claims = json_decode(self::decode($payload), true);
        if (!is_array($claims) || ($claims['exp'] ?? 0) < time() || ($claims['nbf'] ?? 0) > time()) {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        return $claims;
    }

    private static function secret(): string
    {
        $secret = Env::get('JWT_SECRET', '') ?? '';
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must contain at least 32 characters.');
        }
        return $secret;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
