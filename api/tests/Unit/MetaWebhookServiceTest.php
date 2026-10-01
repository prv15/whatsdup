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

    public function testExtractsInboundMessages(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '15551234567',
                                        'id' => 'wamid.inbound1',
                                        'type' => 'text',
                                        'text' => ['body' => 'STOP'],
                                    ],
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.inbound2',
                                        'type' => 'button',
                                        'button' => ['text' => 'Unsubscribe'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $messages = MetaWebhookService::inboundMessages($payload);
        self::assertCount(2, $messages);
        self::assertSame('15551234567', $messages[0]['from']);
        self::assertSame('STOP', $messages[0]['text']);
        self::assertSame('15559876543', $messages[1]['from']);
        self::assertSame('Unsubscribe', $messages[1]['text']);
    }

    public function testExtractsTemplateUpdates(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'message_template_id' => '1234567890',
                                'message_template_name' => 'summer_promo',
                                'event' => 'APPROVED',
                            ],
                        ],
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'message_template_id' => '0987654321',
                                'message_template_name' => 'festival_offer',
                                'event' => 'REJECTED',
                                'reason' => 'INCORRECT_CATEGORY',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $updates = MetaWebhookService::templateUpdates($payload);
        self::assertCount(2, $updates);
        self::assertSame('1234567890', $updates[0]['templateId']);
        self::assertSame('summer_promo', $updates[0]['templateName']);
        self::assertSame('APPROVED', $updates[0]['event']);

        self::assertSame('0987654321', $updates[1]['templateId']);
        self::assertSame('festival_offer', $updates[1]['templateName']);
        self::assertSame('REJECTED', $updates[1]['event']);
        self::assertSame('INCORRECT_CATEGORY', $updates[1]['reason']);
    }
}
