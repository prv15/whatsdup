<?php

declare(strict_types=1);

namespace WhatstheUp\Middleware;

use WhatstheUp\Services\AuthenticationService;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;

final class Authenticate
{
    public function __construct(private readonly AuthenticationService $auth)
    {
    }

    public function __invoke(Request $request, callable $next): mixed
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        $request->attributes['identity'] = $this->auth->authenticate($token);
        return $next($request);
    }
}
