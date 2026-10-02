<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\AdminService;
use WhatstheUp\Services\AuditService;
use WhatstheUp\Support\HttpException;

final class AdminServiceTest extends TestCase
{
    public function testAssignBusinessPlanThrowsWhenBusinessNotFound(): void
    {
        $db = $this->createMock(PDO::class);

        $bizStmt = $this->createMock(PDOStatement::class);
        $bizStmt->method('execute')->willReturn(true);
        $bizStmt->method('fetch')->willReturn(false);

        $db->method('prepare')->willReturn($bizStmt);

        $service = new AdminService($db, new AuditService($db));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Business not found.');

        $service->assignBusinessPlan('non-existent', ['planId' => 'plan-1'], 'actor-1');
    }

    public function testAssignBusinessPlanThrowsWhenPlanNotFound(): void
    {
        $db = $this->createMock(PDO::class);

        $bizStmt = $this->createMock(PDOStatement::class);
        $bizStmt->method('execute')->willReturn(true);
        $bizStmt->method('fetch')->willReturn(['id' => 'biz-1', 'name' => 'Demo Biz']);

        $planStmt = $this->createMock(PDOStatement::class);
        $planStmt->method('execute')->willReturn(true);
        $planStmt->method('fetch')->willReturn(false);

        $db->method('prepare')->willReturnOnConsecutiveCalls($bizStmt, $planStmt);

        $service = new AdminService($db, new AuditService($db));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Plan not found.');

        $service->assignBusinessPlan('biz-1', ['planId' => 'invalid-plan'], 'actor-1');
    }

    public function testUpdateUserStatusRejectsInvalidStatus(): void
    {
        $db = $this->createMock(PDO::class);

        $service = new AdminService($db, new AuditService($db));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Status must be active or suspended.');

        $service->updateUserStatus('user-1', 'deleted', 'actor-1');
    }

    public function testResetUserPasswordRejectsShortPassword(): void
    {
        $db = $this->createMock(PDO::class);

        $service = new AdminService($db, new AuditService($db));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Password must be at least 12 characters.');

        $service->resetUserPassword('user-1', 'short123', 'actor-1');
    }

    public function testRevokeUserSessionsExecutesAndReturnsCount(): void
    {
        $db = $this->createMock(PDO::class);

        $revokeStmt = $this->createMock(PDOStatement::class);
        $revokeStmt->method('execute')->willReturn(true);
        $revokeStmt->method('rowCount')->willReturn(3);

        $auditStmt = $this->createMock(PDOStatement::class);
        $auditStmt->method('execute')->willReturn(true);

        $db->method('prepare')->willReturnOnConsecutiveCalls($revokeStmt, $auditStmt);

        $service = new AdminService($db, new AuditService($db));
        $result = $service->revokeUserSessions('user-1', 'actor-1');

        self::assertSame(['revoked' => 3], $result);
    }
}