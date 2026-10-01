<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\QuotaService;
use WhatstheUp\Support\HttpException;

final class QuotaServiceTest extends TestCase
{
    public function testCurrentMonthPeriodReturnsValidDates(): void
    {
        [$start, $end] = QuotaService::currentMonthPeriod();
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-01 00:00:00$/', $start);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} 23:59:59$/', $end);
    }

    public function testAssertCanImportContactsAllowsWhenUnderLimit(): void
    {
        $db = $this->createMock(PDO::class);

        // Active subscription statement
        $subStmt = $this->createMock(PDOStatement::class);
        $subStmt->method('execute')->willReturn(true);
        $subStmt->method('fetch')->willReturn([
            'id' => 'sub-1',
            'plan_id' => 'plan-1',
            'status' => 'active',
            'plan_name' => 'Launch',
            'plan_code' => 'launch',
            'plan_limits' => json_encode(['contacts' => 5000]),
        ]);

        // Contacts count statement
        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(4000);

        $db->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($subStmt, $countStmt);

        $service = new QuotaService($db);
        // 4000 + 500 <= 5000 -> should not throw
        $service->assertCanImportContacts('biz-1', 500);
        self::assertTrue(true);
    }

    public function testAssertCanImportContactsThrowsWhenExceedingLimit(): void
    {
        $db = $this->createMock(PDO::class);

        $subStmt = $this->createMock(PDOStatement::class);
        $subStmt->method('execute')->willReturn(true);
        $subStmt->method('fetch')->willReturn([
            'id' => 'sub-1',
            'plan_id' => 'plan-1',
            'status' => 'active',
            'plan_name' => 'Launch',
            'plan_code' => 'launch',
            'plan_limits' => json_encode(['contacts' => 5000]),
        ]);

        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(4900);

        $db->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($subStmt, $countStmt);

        $service = new QuotaService($db);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Contact limit of 5,000 exceeded');

        // 4900 + 200 = 5100 > 5000 -> throws
        $service->assertCanImportContacts('biz-1', 200);
    }

    public function testAssertCanLaunchCampaignThrowsWhenRecipientQuotaExceeded(): void
    {
        $db = $this->createMock(PDO::class);

        $subStmt = $this->createMock(PDOStatement::class);
        $subStmt->method('execute')->willReturn(true);
        $subStmt->method('fetch')->willReturn([
            'id' => 'sub-1',
            'plan_id' => 'plan-1',
            'status' => 'active',
            'plan_name' => 'Launch',
            'plan_code' => 'launch',
            'plan_limits' => json_encode(['monthlyRecipients' => 10000]),
        ]);

        $usageStmt = $this->createMock(PDOStatement::class);
        $usageStmt->method('execute')->willReturn(true);
        $usageStmt->method('fetchColumn')->willReturn(9500);

        $db->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($subStmt, $usageStmt);

        $service = new QuotaService($db);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Monthly recipient quota of 10,000 exceeded');

        // 9500 + 600 = 10100 > 10000 -> throws
        $service->assertCanLaunchCampaign('biz-1', 600);
    }
}
