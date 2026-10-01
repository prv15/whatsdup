<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class QuotaService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function getActiveSubscription(string $businessId): ?array
    {
        $stmt = $this->db->prepare("SELECT
            s.id,
            s.plan_id,
            s.status,
            s.starts_at,
            s.ends_at,
            p.name AS plan_name,
            p.code AS plan_code,
            p.limits AS plan_limits
        FROM subscriptions s
        JOIN plans p ON p.id = s.plan_id
        WHERE s.business_id = ? AND s.status IN ('active', 'trialing')
        ORDER BY s.created_at DESC LIMIT 1");
        $stmt->execute([$businessId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['limits'] = json_decode((string) $row['plan_limits'], true) ?: [];
            return $row;
        }

        // Fallback: Check if there is an active launch plan if no subscription record was created yet
        $defaultPlan = $this->db->prepare("SELECT id, name, code, limits FROM plans WHERE code = 'launch' AND status = 'active' LIMIT 1");
        $defaultPlan->execute();
        $plan = $defaultPlan->fetch();
        if ($plan) {
            return [
                'id' => null,
                'plan_id' => $plan['id'],
                'status' => 'active',
                'starts_at' => gmdate('Y-m-d H:i:s'),
                'ends_at' => null,
                'plan_name' => $plan['name'],
                'plan_code' => $plan['code'],
                'limits' => json_decode((string) $plan['limits'], true) ?: [],
            ];
        }

        return null;
    }

    public function assertCanImportContacts(string $businessId, int $incomingCount): void
    {
        $sub = $this->getActiveSubscription($businessId);
        if (!$sub) {
            return;
        }

        $limits = $sub['limits'] ?? [];
        $limit = $limits['contacts'] ?? null;
        if ($limit === null) {
            return; // Unlimited
        }

        $limit = (int) $limit;
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM contacts WHERE business_id = ? AND deleted_at IS NULL');
        $countStmt->execute([$businessId]);
        $current = (int) $countStmt->fetchColumn();

        if (($current + $incomingCount) > $limit) {
            $planName = $sub['plan_name'] ?? 'Current Plan';
            throw new HttpException(
                403,
                "Contact limit of " . number_format($limit) . " exceeded for {$planName}. Currently using " . number_format($current) . " contacts. Adding " . number_format($incomingCount) . " would exceed your limit. Please upgrade your plan.",
                'quota_exceeded_contacts'
            );
        }
    }

    public function assertCanLaunchCampaign(string $businessId, int $recipientCount): void
    {
        $sub = $this->getActiveSubscription($businessId);
        if (!$sub) {
            return;
        }

        $limits = $sub['limits'] ?? [];
        $limit = $limits['monthlyRecipients'] ?? null;
        if ($limit === null) {
            return; // Unlimited
        }

        $limit = (int) $limit;
        [$periodStart, $periodEnd] = self::currentMonthPeriod();

        $subscriptionId = $sub['id'];
        $used = 0;
        if ($subscriptionId !== null) {
            $usageStmt = $this->db->prepare("SELECT quantity FROM subscription_usage WHERE business_id = ? AND subscription_id = ? AND metric_key = 'monthly_recipients' AND period_start = ? LIMIT 1");
            $usageStmt->execute([$businessId, $subscriptionId, $periodStart]);
            $used = (int) ($usageStmt->fetchColumn() ?: 0);
        }

        if (($used + $recipientCount) > $limit) {
            $planName = $sub['plan_name'] ?? 'Current Plan';
            throw new HttpException(
                403,
                "Monthly recipient quota of " . number_format($limit) . " exceeded for {$planName}. You have sent " . number_format($used) . " messages this billing cycle. Launching this campaign ({$recipientCount} recipients) requires a higher tier plan.",
                'quota_exceeded_recipients'
            );
        }
    }

    public function recordRecipientUsage(string $businessId, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $sub = $this->getActiveSubscription($businessId);
        if (!$sub || empty($sub['id'])) {
            return;
        }

        [$periodStart, $periodEnd] = self::currentMonthPeriod();
        $subscriptionId = (string) $sub['id'];

        $stmt = $this->db->prepare("INSERT INTO subscription_usage (id, business_id, subscription_id, metric_key, period_start, period_end, quantity, updated_at)
            VALUES (?, ?, ?, 'monthly_recipients', ?, ?, ?, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = UTC_TIMESTAMP()");

        $stmt->execute([
            Uuid::v4(),
            $businessId,
            $subscriptionId,
            $periodStart,
            $periodEnd,
            $count,
        ]);
    }

    public function getUsageOverview(string $businessId): array
    {
        $sub = $this->getActiveSubscription($businessId);
        $limits = $sub['limits'] ?? [];

        // Contacts usage
        $contactsStmt = $this->db->prepare('SELECT COUNT(*) FROM contacts WHERE business_id = ? AND deleted_at IS NULL');
        $contactsStmt->execute([$businessId]);
        $contactsUsed = (int) $contactsStmt->fetchColumn();
        $contactsLimit = isset($limits['contacts']) && $limits['contacts'] !== null ? (int) $limits['contacts'] : null;

        // Monthly recipients usage
        [$periodStart, $periodEnd] = self::currentMonthPeriod();
        $recipientsUsed = 0;
        if (!empty($sub['id'])) {
            $usageStmt = $this->db->prepare("SELECT quantity FROM subscription_usage WHERE business_id = ? AND subscription_id = ? AND metric_key = 'monthly_recipients' AND period_start = ? LIMIT 1");
            $usageStmt->execute([$businessId, $sub['id'], $periodStart]);
            $recipientsUsed = (int) ($usageStmt->fetchColumn() ?: 0);
        }
        $recipientsLimit = isset($limits['monthlyRecipients']) && $limits['monthlyRecipients'] !== null ? (int) $limits['monthlyRecipients'] : null;

        // Connected Phone Numbers
        $phonesStmt = $this->db->prepare("SELECT COUNT(*) FROM meta_connections WHERE business_id = ? AND status = 'connected' AND deleted_at IS NULL");
        $phonesStmt->execute([$businessId]);
        $phonesUsed = (int) $phonesStmt->fetchColumn();
        $phonesLimit = isset($limits['phoneNumbers']) && $limits['phoneNumbers'] !== null ? (int) $limits['phoneNumbers'] : null;

        return [
            'plan' => [
                'name' => $sub['plan_name'] ?? 'Trial',
                'code' => $sub['plan_code'] ?? 'trial',
                'status' => $sub['status'] ?? 'active',
            ],
            'contacts' => [
                'used' => $contactsUsed,
                'limit' => $contactsLimit,
                'percentage' => $contactsLimit ? min(100, round(($contactsUsed / $contactsLimit) * 100, 1)) : 0,
            ],
            'monthlyRecipients' => [
                'used' => $recipientsUsed,
                'limit' => $recipientsLimit,
                'percentage' => $recipientsLimit ? min(100, round(($recipientsUsed / $recipientsLimit) * 100, 1)) : 0,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
            ],
            'phoneNumbers' => [
                'used' => $phonesUsed,
                'limit' => $phonesLimit,
            ],
        ];
    }

    public static function currentMonthPeriod(): array
    {
        $start = gmdate('Y-m-01 00:00:00');
        $end = gmdate('Y-m-t 23:59:59');
        return [$start, $end];
    }
}
