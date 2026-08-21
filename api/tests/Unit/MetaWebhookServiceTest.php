<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\MetaWebhookService;

final class MetaWebhookServiceTest extends TestCase
{
    public function testExtractsDeliveryAndFailureEvents(): void
    {
        $payload = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.sent', 'status' => 'delivered'], ['id' => 'wamid.failed', 'status' => 'failed', 'errors' => [['code' => 131026, 'message' => 'Message undeliverable']]]]]]]]]];
        self::assertSame([['messageId' => 'wamid.sent', 'status' => 'delivered', 'errorCode' => null, 'errorMessage' => null], ['messageId' => 'wamid.failed', 'status' => 'failed', 'errorCode' => '131026', 'errorMessage' => 'Message undeliverable']], MetaWebhookService::statusEvents($payload));
    }
}
