<?php

declare(strict_types=1);

namespace WhatstheUp\Middleware;

use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;

final class RequireScope
{
    public function __construct(private readonly string $scope)
    {
    }

    public function __invoke(Request $request, callable $next): mixed
    {
        if (($request->attributes['identity']['scope'] ?? null) !== $this->scope) {
            throw new HttpException(403, 'This endpoint is not available in the current account scope.', 'invalid_scope');
        }
        return $next($request);
    }
}
