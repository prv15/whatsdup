<?php

declare(strict_types=1);

namespace WhatstheUp\Support;

final class Router
{
    /** @var array<int, array{string,string,callable,array}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler, $middleware];
    }

    public function dispatch(Request $request): mixed
    {
        foreach ($this->routes as [$method, $path, $handler, $middleware]) {
            if ($method !== $request->method) {
                continue;
            }
            $parameterNames = [];
            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];
                return '([^/]+)';
            }, $path);
            if (!preg_match('#^' . $pattern . '$#', $request->path, $matches)) {
                continue;
            }
            array_shift($matches);
            $request->attributes['route'] = array_combine($parameterNames, array_map('urldecode', $matches)) ?: [];
            $next = fn (Request $current) => $handler($current);
            foreach (array_reverse($middleware) as $layer) {
                $following = $next;
                $next = fn (Request $current) => $layer($current, $following);
            }
            return $next($request);
        }
        throw new HttpException(404, 'Endpoint not found.', 'not_found');
    }
}
