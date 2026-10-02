<?php

declare(strict_types=1);

namespace WhatstheUp\Support;

final class Request
{
    private ?array $json = null;
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly array $cookies,
        public readonly array $query,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }

    public static function capture(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (empty($headers) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        }
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
        }

        // FastCGI / Apache authorization header fallback
        if (!isset($normalized['authorization']) || trim($normalized['authorization']) === '') {
            if (!empty($normalized['x-authorization'])) {
                $normalized['authorization'] = (string) $normalized['x-authorization'];
            } elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
                $normalized['authorization'] = (string) $_SERVER['HTTP_X_AUTHORIZATION'];
            } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $normalized['authorization'] = (string) $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $normalized['authorization'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'])) {
                $normalized['authorization'] = (string) $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (preg_match('#(/api/v1/.*)$#', $rawPath, $matches)) {
            $rawPath = $matches[1];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $rawPath,
            $normalized,
            file_get_contents('php://input') ?: '',
            $_COOKIE,
            $_GET,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500),
        );
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        if ($this->rawBody === '') {
            return $this->json = [];
        }
        $decoded = json_decode($this->rawBody, true);
        if (!is_array($decoded)) {
            throw new HttpException(400, 'The request body must be valid JSON.', 'invalid_json');
        }
        return $this->json = $decoded;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->headers['authorization'] ?? '';
        return preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? $matches[1] : null;
    }
}
