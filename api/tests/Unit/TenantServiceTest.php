<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\TenantService;
use WhatstheUp\Support\HttpException;

final class TenantServiceTest extends TestCase
{
    public function testRejectsRecordOwnedByAnotherBusinessWithoutDisclosingIt(): void
    {
        $service = new TenantService();
        try {
            $service->assertOwns(['business' => ['id' => 'business-a']], 'business-b');
            self::fail('Expected isolation check to fail.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame('not_found', $exception->codeName);
        }
    }
}
