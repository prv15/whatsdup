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
            if ($method !== $request->method || $path !== $request->path) {
                continue;
            }
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
