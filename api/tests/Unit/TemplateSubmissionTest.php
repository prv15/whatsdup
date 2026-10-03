<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Services\AuditService;
use WhatstheUp\Services\MetaGraphClient;
use WhatstheUp\Services\OperationsService;
use WhatstheUp\Services\QuotaService;
use WhatstheUp\Support\HttpException;

final class TemplateSubmissionTest extends TestCase
{
    public function testSubmitRejectsWhenTemplateAlreadyApproved(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $quota = new QuotaService($db);
        $graph = new MetaGraphClient();
        $cipher = new TokenCipher();

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'id' => 'tmpl-1',
                'name' => 'offer_test',
                'language' => 'en_US',
                'category' => 'marketing',
                'header_type' => 'none',
                'header_media_url' => null,
                'body' => 'Hello test',
                'variables' => null,
                'status' => 'approved',
                'rejection_reason' => null,
                'created_at' => '2026-10-01 12:00:00',
                'updated_at' => '2026-10-01 12:00:00',
            ]
        ]);

        $db->method('prepare')->willReturn($stmt);

        $service = new OperationsService($db, $audit, $quota, $graph, $cipher);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('This template has already been approved by Meta.');

        $service->submitTemplate('biz-1', 'user-1', 'tmpl-1');
    }

    public function testSubmitRejectsWhenMetaNotConnected(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $quota = new QuotaService($db);
        $graph = new MetaGraphClient();
        $cipher = new TokenCipher();

        $stmtTemplates = $this->createMock(PDOStatement::class);
        $stmtTemplates->method('execute')->willReturn(true);
        $stmtTemplates->method('fetchAll')->willReturn([
            [
                'id' => 'tmpl-1',
                'name' => 'offer_draft',
                'language' => 'en_US',
                'category' => 'marketing',
                'header_type' => 'none',
                'header_media_url' => null,
                'body' => 'Hello test',
                'variables' => null,
                'status' => 'draft',
                'rejection_reason' => null,
                'created_at' => '2026-10-01 12:00:00',
                'updated_at' => '2026-10-01 12:00:00',
            ]
        ]);

        $stmtConn = $this->createMock(PDOStatement::class);
        $stmtConn->method('execute')->willReturn(true);
        $stmtConn->method('fetch')->willReturn(false);

        $db->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtTemplates, $stmtConn);

        $service = new OperationsService($db, $audit, $quota, $graph, $cipher);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Connect an active Meta WhatsApp account before submitting templates to Meta.');

        $service->submitTemplate('biz-1', 'user-1', 'tmpl-1');
    }

    public function testSubmitSuccessWithVariablesUpdatesStatus(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $quota = new QuotaService($db);
        $graph = $this->createMock(MetaGraphClient::class);
        $cipher = $this->createMock(TokenCipher::class);

        // 1. Initial template fetch (draft)
        $stmtDraft = $this->createMock(PDOStatement::class);
        $stmtDraft->method('execute')->willReturn(true);
        $stmtDraft->method('fetchAll')->willReturn([
            [
                'id' => 'tmpl-1',
                'name' => 'promo_alert',
                'language' => 'en_US',
                'category' => 'marketing',
                'header_type' => 'none',
                'header_media_url' => null,
                'body' => 'Hello {{1}}, code is {{2}}',
                'variables' => json_encode(['1', '2']),
                'status' => 'draft',
                'rejection_reason' => null,
                'created_at' => '2026-10-01 12:00:00',
                'updated_at' => '2026-10-01 12:00:00',
            ]
        ]);

        // 2. Meta connection query
        $stmtConn = $this->createMock(PDOStatement::class);
        $stmtConn->method('execute')->willReturn(true);
        $stmtConn->method('fetch')->willReturn([
            'ciphertext' => 'enc-token',
            'nonce' => 'nonce-val',
            'meta_waba_id' => '1099887766',
        ]);

        // 3. Update query
        $stmtUpdate = $this->createMock(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        // 4. Audit query (inside AuditService)
        $stmtAudit = $this->createMock(PDOStatement::class);
        $stmtAudit->method('execute')->willReturn(true);

        // 5. Final template fetch (pending)
        $stmtFinal = $this->createMock(PDOStatement::class);
        $stmtFinal->method('execute')->willReturn(true);
        $stmtFinal->method('fetchAll')->willReturn([
            [
                'id' => 'tmpl-1',
                'name' => 'promo_alert',
                'language' => 'en_US',
                'category' => 'marketing',
                'header_type' => 'none',
                'header_media_url' => null,
                'body' => 'Hello {{1}}, code is {{2}}',
                'variables' => json_encode(['1', '2']),
                'status' => 'pending',
                'rejection_reason' => null,
                'created_at' => '2026-10-01 12:00:00',
                'updated_at' => '2026-10-01 12:05:00',
            ]
        ]);

        $db->expects(self::exactly(5))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtDraft, $stmtConn, $stmtUpdate, $stmtAudit, $stmtFinal);

        $cipher->method('decrypt')->willReturn('decrypted-access-token');

        $graph->expects(self::once())
            ->method('createTemplate')
            ->with(
                '1099887766',
                'decrypted-access-token',
                self::callback(function (array $payload): bool {
                    return $payload['name'] === 'promo_alert'
                        && $payload['category'] === 'MARKETING'
                        && $payload['components'][0]['example']['body_text'] === [['Sample1', 'Sample2']];
                })
            )
            ->willReturn([
                'id' => 'meta-tmpl-999',
                'status' => 'PENDING',
            ]);

        $service = new OperationsService($db, $audit, $quota, $graph, $cipher);

        $result = $service->submitTemplate('biz-1', 'user-1', 'tmpl-1');
        self::assertSame('pending', $result['status']);
        self::assertSame('promo_alert', $result['name']);
    }
}
