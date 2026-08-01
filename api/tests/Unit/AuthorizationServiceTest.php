<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\AuthorizationService;
use WhatstheUp\Support\HttpException;

final class AuthorizationServiceTest extends TestCase
{
    public function testViewerCannotSendCampaign(): void
    {
        $service = new AuthorizationService();
        $this->expectException(HttpException::class);
        $service->require(['permissions' => ['campaigns.view', 'reports.view']], 'campaigns.send');
    }

    public function testCampaignManagerCanSendCampaign(): void
    {
        $service = new AuthorizationService();
        self::assertTrue($service->allows(['permissions' => ['campaigns.send']], 'campaigns.send'));
    }
}
