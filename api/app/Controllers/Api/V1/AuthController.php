<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Services\AuthenticationService;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\Request;

final class AuthController
{
    public function __construct(private readonly AuthenticationService $auth)
    {
    }

    public function login(Request $request): array
    {
        $input = $request->json();
        return $this->withCookie($this->auth->login((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''), $request));
    }

    public function refresh(Request $request): array
    {
        $name = Env::get('REFRESH_COOKIE_NAME', 'whatstheup_refresh') ?? 'whatstheup_refresh';
        return $this->withCookie($this->auth->refresh((string) ($request->cookies[$name] ?? ''), $request));
    }

    public function forgotPassword(Request $request): array
    {
        $input = $request->json();
        return $this->auth->requestPasswordReset((string) ($input['email'] ?? ''), $request);
    }

    public function resetPassword(Request $request): array
    {
        $input = $request->json();
        $result = $this->auth->resetPassword((string) ($input['token'] ?? ''), (string) ($input['password'] ?? ''), $request);
        $this->clearCookie();
        return $result;
    }

    public function me(Request $request): array
    {
        return ['user' => $request->attributes['identity']];
    }

    public function logout(Request $request): array
    {
        $name = Env::get('REFRESH_COOKIE_NAME', 'whatstheup_refresh') ?? 'whatstheup_refresh';
        $this->auth->logout((string) ($request->cookies[$name] ?? ''));
        $this->clearCookie();
        return ['message' => 'Signed out.'];
    }

    public function logoutAll(Request $request): array
    {
        $this->auth->logoutAll($request->attributes['identity']['id']);
        $this->clearCookie();
        return ['message' => 'All sessions have been signed out.'];
    }

    private function withCookie(array $payload): array
    {
        $raw = $payload['_refreshToken'];
        unset($payload['_refreshToken']);
        setcookie(Env::get('REFRESH_COOKIE_NAME', 'whatstheup_refresh') ?? 'whatstheup_refresh', $raw, $this->cookieOptions(time() + Env::int('REFRESH_TOKEN_TTL', 2592000)));
        return $payload;
    }

    private function clearCookie(): void
    {
        setcookie(Env::get('REFRESH_COOKIE_NAME', 'whatstheup_refresh') ?? 'whatstheup_refresh', '', $this->cookieOptions(time() - 3600));
    }

    private function cookieOptions(int $expires): array
    {
        $domain = Env::get('COOKIE_DOMAIN', '') ?? '';
        return array_filter(['expires' => $expires, 'path' => '/api/v1/auth', 'domain' => $domain, 'secure' => Env::bool('COOKIE_SECURE'), 'httponly' => true, 'samesite' => Env::get('COOKIE_SAME_SITE', 'Lax')], static fn ($value) => $value !== '');
    }
}
