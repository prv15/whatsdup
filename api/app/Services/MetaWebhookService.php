<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;

final class MetaWebhookService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function process(array $payload): int
    {
        $processed = 0;
        foreach (self::statusEvents($payload) as $event) {
            $recipient = $this->db->prepare('SELECT campaign_id, status FROM campaign_contacts WHERE meta_message_id = ? LIMIT 1');
            $recipient->execute([$event['messageId']]);
            $row = $recipient->fetch();
            if (!$row) continue;

            $status = $event['status'];
            $current = (string) $row['status'];
            if (($status === 'failed' && in_array($current, ['delivered', 'read'], true)) || ($status === 'sent' && in_array($current, ['delivered', 'read'], true)) || ($status === 'delivered' && $current === 'read')) continue;

            $sentAt = in_array($status, ['sent', 'delivered', 'read'], true) ? 'COALESCE(sent_at, UTC_TIMESTAMP())' : 'sent_at';
            $deliveredAt = in_array($status, ['delivered', 'read'], true) ? 'COALESCE(delivered_at, UTC_TIMESTAMP())' : 'delivered_at';
            $readAt = $status === 'read' ? 'COALESCE(read_at, UTC_TIMESTAMP())' : 'read_at';
            $update = $this->db->prepare("UPDATE campaign_contacts SET status = ?, failure_code = ?, failure_message = ?, sent_at = {$sentAt}, delivered_at = {$deliveredAt}, read_at = {$readAt}, updated_at = UTC_TIMESTAMP() WHERE meta_message_id = ?");
            $update->execute([$status, $event['errorCode'], $event['errorMessage'], $event['messageId']]);
            $this->refreshCampaign((string) $row['campaign_id']);
            $processed++;
        }
        return $processed;
    }

    public static function statusEvents(array $payload): array
    {
        $events = [];
        foreach ($payload['entry'] ?? [] as $entry) foreach ($entry['changes'] ?? [] as $change) foreach ($change['value']['statuses'] ?? [] as $status) {
            $messageId = trim((string) ($status['id'] ?? ''));
            $state = strtolower((string) ($status['status'] ?? ''));
            if ($messageId === '' || !in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) continue;
            $error = $status['errors'][0] ?? [];
            $events[] = ['messageId' => $messageId, 'status' => $state, 'errorCode' => $state === 'failed' ? (string) ($error['code'] ?? 'meta_delivery_failed') : null, 'errorMessage' => $state === 'failed' ? mb_substr((string) ($error['message'] ?? $error['title'] ?? 'Meta reported that delivery failed.'), 0, 500) : null];
        }
        return $events;
    }

    private function refreshCampaign(string $campaignId): void
    {
        $this->db->prepare("UPDATE campaigns SET delivered_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('delivered','read')), read_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'read'), failed_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'failed'), updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$campaignId, $campaignId, $campaignId, $campaignId]);
    }
}
