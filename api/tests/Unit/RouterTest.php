<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Support\Request;
use WhatstheUp\Support\Router;

final class RouterTest extends TestCase
{
    public function testExtractsDynamicRouteParameters(): void
    {
        $router = new Router();
        $router->add('PATCH', '/api/v1/admin/businesses/{id}', static fn (Request $request) => $request->attributes['route']);
        $request = new Request('PATCH', '/api/v1/admin/businesses/business-123', [], '{}', [], [], '127.0.0.1', 'test');
        self::assertSame(['id' => 'business-123'], $router->dispatch($request));
    }
}
