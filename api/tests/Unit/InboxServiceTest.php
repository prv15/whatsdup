<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Services\AuditService;
use WhatstheUp\Services\InboxService;
use WhatstheUp\Services\MetaGraphClient;
use WhatstheUp\Support\HttpException;

final class InboxServiceTest extends TestCase
{
    public function testSendMessageRejectsWhenWindowIsExpired(): void
    {
        $db = $this->createMock(PDO::class);
        $metaClient = new MetaGraphClient();
        $cipher = new TokenCipher();
        $audit = new AuditService($db);

        // Mock conversation statement returning expired window (yesterday)
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 'conv-123',
            'window_expires_at' => gmdate('Y-m-d H:i:s', time() - 3600), // 1 hour ago (expired)
            'phone_e164' => '+919876543210',
        ]);

        $db->expects(self::once())
            ->method('prepare')
            ->willReturn($stmt);

        $service = new InboxService($db, $metaClient, $cipher, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('The 24-hour customer service window has expired.');

        $service->sendMessage('biz-1', 'conv-123', 'user-1', 'Hello customer');
    }

    public function testSendMessageRejectsEmptyContent(): void
    {
        $db = $this->createMock(PDO::class);
        $metaClient = new MetaGraphClient();
        $cipher = new TokenCipher();
        $audit = new AuditService($db);

        $service = new InboxService($db, $metaClient, $cipher, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Message content must be between 1 and 4,096 characters.');

        $service->sendMessage('biz-1', 'conv-123', 'user-1', '   ');
    }

    public function testGetMessagesThrows404ForNonexistentConversation(): void
    {
        $db = $this->createMock(PDO::class);
        $metaClient = new MetaGraphClient();
        $cipher = new TokenCipher();
        $audit = new AuditService($db);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $db->expects(self::once())
            ->method('prepare')
            ->willReturn($stmt);

        $service = new InboxService($db, $metaClient, $cipher, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Conversation not found.');

        $service->getMessages('biz-1', 'conv-999');
    }

    public function testMarkReadUpdatesUnreadCount(): void
    {
        $db = $this->createMock(PDO::class);
        $metaClient = new MetaGraphClient();
        $cipher = new TokenCipher();
        $audit = new AuditService($db);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects(self::once())
            ->method('execute')
            ->with(['conv-123', 'biz-1']);

        $db->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('UPDATE conversations SET unread_count = 0'))
            ->willReturn($stmt);

        $service = new InboxService($db, $metaClient, $cipher, $audit);
        $service->markRead('biz-1', 'conv-123');
        self::assertTrue(true);
    }
}
