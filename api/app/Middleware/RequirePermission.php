<?php

declare(strict_types=1);

namespace WhatstheUp\Middleware;

use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;

final class RequirePermission
{
    public function __construct(private readonly string $permission)
    {
    }

    public function __invoke(Request $request, callable $next): mixed
    {
        $identity = $request->attributes['identity'] ?? null;
        if (!is_array($identity) || !in_array($this->permission, $identity['permissions'] ?? [], true)) {
            throw new HttpException(403, 'You do not have permission to perform this action.', 'forbidden');
        }
        return $next($request);
    }
}
