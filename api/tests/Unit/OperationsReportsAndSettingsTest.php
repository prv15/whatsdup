<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\AuditService;
use WhatstheUp\Services\OperationsService;
use WhatstheUp\Support\HttpException;

final class OperationsReportsAndSettingsTest extends TestCase
{
    public function testUpdateSettingsRejectsEmptyName(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $service = new OperationsService($db, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Business name is required.');

        $service->updateSettings('biz-1', 'user-1', ['name' => '   ']);
    }

    public function testInviteTeamMemberRejectsInvalidEmail(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $service = new OperationsService($db, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Enter a valid email address.');

        $service->inviteTeamMember('biz-1', 'user-1', ['email' => 'invalid-email', 'name' => 'John']);
    }

    public function testRemoveTeamMemberProtectsPrimaryOwner(): void
    {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['is_primary' => 1]);

        $db->expects(self::once())
            ->method('prepare')
            ->willReturn($stmt);

        $audit = new AuditService($db);
        $service = new OperationsService($db, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Cannot remove the primary workspace owner.');

        $service->removeTeamMember('biz-1', 'user-1', 'target-owner-id');
    }

    public function testChangePasswordRejectsShortPassword(): void
    {
        $db = $this->createMock(PDO::class);
        $audit = new AuditService($db);
        $service = new OperationsService($db, $audit);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('New password must be at least 8 characters long.');

        $service->changePassword('user-1', ['currentPassword' => 'oldpass', 'newPassword' => 'short']);
    }
}