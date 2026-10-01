<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;

final class CampaignDispatchService
{
    public const int BATCH_SIZE = 100;
    private readonly RateLimiter $rateLimiter;
    private readonly QueueService $queueService;

    public function __construct(
        private readonly PDO $db,
        private readonly MetaGraphClient $graph,
        private readonly TokenCipher $cipher,
        private readonly AuditService $audit,
        ?RateLimiter $rateLimiter = null,
        ?QueueService $queueService = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($db);
        $this->queueService = $queueService ?? new QueueService($db);
    }

    public function dispatch(string $campaignId): array
    {
        $campaign = $this->loadCampaign($campaignId);
        $businessId = (string) $campaign['business_id'];

        $stmt = $this->db->prepare("
            SELECT contact_id
            FROM campaign_contacts
            WHERE campaign_id = ? AND status = 'queued'
            ORDER BY contact_id
        ");
        $stmt->execute([$campaignId]);
        $queuedContactIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $totalQueued = count($queuedContactIds);

        if ($totalQueued === 0) {
            $this->reconcileCampaignStatus($campaignId);
            return ['accepted' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0];
        }

        // If audience is larger than BATCH_SIZE, partition into background sub-batches
        if ($totalQueued > self::BATCH_SIZE) {
            $chunks = array_chunk($queuedContactIds, self::BATCH_SIZE);
            $totalBatches = count($chunks);

            $this->db->prepare("
                UPDATE campaigns
                SET status = 'processing', updated_at = UTC_TIMESTAMP()
                WHERE id = ? AND status IN ('queued', 'scheduled')
            ")->execute([$campaignId]);

            foreach ($chunks as $index => $chunk) {
                $this->queueService->enqueue(
                    'campaigns',
                    'campaign.dispatch_batch',
                    [
                        'campaign_id' => $campaignId,
                        'batch_index' => $index,
                        'total_batches' => $totalBatches,
                        'contact_ids' => $chunk,
                    ],
                    $businessId,
                    "campaign-batch:{$campaignId}:{$index}"
                );
            }

            return [
                'batched' => true,
                'total_batches' => $totalBatches,
                'queued_contacts' => $totalQueued,
                'accepted' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        // Audience is within single batch limit, dispatch directly
        return $this->dispatchBatch($campaignId, $queuedContactIds);
    }

    public function dispatchBatch(string $campaignId, array $contactIds): array
    {
        if (empty($contactIds)) {
            return ['accepted' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $campaign = $this->loadCampaign($campaignId);
        $token = $this->cipher->decrypt((string) $campaign['ciphertext'], (string) $campaign['nonce']);
        $rawMappings = $campaign['variable_mappings'] ? json_decode((string) $campaign['variable_mappings'], true) : null;

        $this->db->prepare("
            UPDATE campaigns
            SET status = 'processing', updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND status IN ('queued', 'scheduled')
        ")->execute([$campaignId]);

        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $stmt = $this->db->prepare("
            SELECT cc.contact_id, cc.phone_e164, c.name, c.email, c.custom_fields, c.consent_status, c.deleted_at
            FROM campaign_contacts cc
            LEFT JOIN contacts c ON c.id = cc.contact_id
            WHERE cc.campaign_id = ? AND cc.contact_id IN ({$placeholders}) AND cc.status = 'queued'
            ORDER BY cc.contact_id
        ");
        $stmt->execute(array_merge([$campaignId], $contactIds));
        $recipients = $stmt->fetchAll();

        $accepted = 0; $failed = 0; $skipped = 0;
        $markAccepted = $this->db->prepare("UPDATE campaign_contacts SET status = 'accepted', meta_message_id = ?, sent_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE campaign_id = ? AND contact_id = ?");
        $markFailed = $this->db->prepare("UPDATE campaign_contacts SET status = 'failed', failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE campaign_id = ? AND contact_id = ?");
        $markSkipped = $this->db->prepare("UPDATE campaign_contacts SET status = 'skipped', failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE campaign_id = ? AND contact_id = ?");

        $phoneRateKey = 'meta_phone:' . (string) $campaign['meta_phone_number_id'];
        $mps = (int) (Env::get('META_RATE_LIMIT_PER_SEC', '50') ?: '50');

        foreach ($recipients as $recipient) {
            // 1. Send-time Opt-Out Suppression Guard
            if ($recipient['deleted_at'] !== null || ($recipient['consent_status'] ?? '') !== 'opted_in') {
                $markSkipped->execute(['opted_out_suppression', 'Contact has opted out or was removed.', $campaignId, $recipient['contact_id']]);
                $skipped++;
                continue;
            }

            // 2. Microsecond Rate Limiter Pacing
            $this->rateLimiter->pace($phoneRateKey, $mps);

            // 3. Check for Active Meta Backoff (HTTP 429)
            if ($this->rateLimiter->isBackingOff($phoneRateKey)) {
                $markFailed->execute(['rate_limit_backoff', 'Pacing slowed due to Meta rate limit.', $campaignId, $recipient['contact_id']]);
                $failed++;
                continue;
            }

            try {
                if ($campaign['header_type'] === 'image' && empty($campaign['header_media_url'])) {
                    throw new \RuntimeException('Image template has no uploaded header image.');
                }
                $bodyParams = $this->resolveParameters($rawMappings, $recipient);
                $result = $this->graph->sendTemplate(
                    (string) $campaign['meta_phone_number_id'],
                    $token,
                    ltrim((string) $recipient['phone_e164'], '+'),
                    (string) $campaign['template_name'],
                    (string) $campaign['language'],
                    $campaign['header_type'] === 'image' ? (string) $campaign['header_media_url'] : null,
                    $bodyParams
                );
                $messageId = trim((string) ($result['messages'][0]['id'] ?? ''));
                if ($messageId === '') {
                    throw new \RuntimeException('Meta accepted the request without returning a message ID.');
                }
                $markAccepted->execute([$messageId, $campaignId, $recipient['contact_id']]);
                $accepted++;
            } catch (HttpException $exception) {
                // If Meta returned 429 rate limit, record backoff
                if ($exception->statusCode === 429 || in_array($exception->codeName, ['130429', '80007', 'rate_limit_exceeded'], true)) {
                    $this->rateLimiter->recordBackoff($phoneRateKey, 10);
                }
                $markFailed->execute([$exception->codeName, mb_substr($exception->getMessage(), 0, 500), $campaignId, $recipient['contact_id']]);
                $failed++;
            } catch (Throwable $exception) {
                $markFailed->execute(['dispatch_error', 'Message could not be dispatched.', $campaignId, $recipient['contact_id']]);
                $failed++;
            }
        }

        // Reconcile and update campaign statistics
        $this->reconcileCampaignStatus($campaignId);
        $this->audit->record((string) $campaign['business_id'], null, 'campaign.batch_dispatched', 'campaign', $campaignId, [
            'batch_size' => count($contactIds),
            'accepted' => $accepted,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return ['accepted' => $accepted, 'sent' => $accepted, 'failed' => $failed, 'skipped' => $skipped];
    }

    private function loadCampaign(string $campaignId): array
    {
        $campaign = $this->db->prepare("
            SELECT c.id, c.business_id, c.name, c.variable_mappings,
                   t.name template_name, t.language, t.status template_status,
                   t.header_type, t.header_media_url, t.variables template_variables,
                   pn.meta_phone_number_id, et.ciphertext, et.nonce
            FROM campaigns c
            JOIN message_templates t ON t.id = c.template_id
            JOIN meta_connections mc ON mc.business_id = c.business_id AND mc.status = 'connected' AND mc.deleted_at IS NULL
            JOIN encrypted_tokens et ON et.id = mc.token_id
            JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
            JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.is_default = TRUE AND pn.deleted_at IS NULL
            WHERE c.id = ?
            LIMIT 1
        ");
        $campaign->execute([$campaignId]);
        $row = $campaign->fetch();

        if (!$row) {
            throw new \RuntimeException('Campaign cannot be dispatched because Meta connection or phone information is unavailable.');
        }
        if ($row['template_status'] !== 'approved') {
            throw new \RuntimeException('Campaign template is no longer approved by Meta.');
        }

        return $row;
    }

    private function reconcileCampaignStatus(string $campaignId): void
    {
        $unsettled = $this->db->prepare("
            SELECT COUNT(*)
            FROM campaign_contacts
            WHERE campaign_id = ? AND status = 'queued'
        ");
        $unsettled->execute([$campaignId]);
        $remainingQueued = (int) $unsettled->fetchColumn();

        $acceptedCountStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM campaign_contacts
            WHERE campaign_id = ? AND status IN ('accepted', 'sent', 'delivered', 'read')
        ");
        $acceptedCountStmt->execute([$campaignId]);
        $acceptedCount = (int) $acceptedCountStmt->fetchColumn();

        // If no more recipients are queued to be sent
        if ($remainingQueued === 0) {
            $newStatus = ($acceptedCount === 0) ? 'failed' : 'dispatched';
            $this->db->prepare("
                UPDATE campaigns
                SET status = ?,
                    delivered_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('delivered','read')),
                    read_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'read'),
                    failed_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('failed','skipped')),
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
            ")->execute([$newStatus, $campaignId, $campaignId, $campaignId, $campaignId]);
        } else {
            // Still has batches in flight, maintain 'processing'
            $this->db->prepare("
                UPDATE campaigns
                SET delivered_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('delivered','read')),
                    read_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'read'),
                    failed_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('failed','skipped')),
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
            ")->execute([$campaignId, $campaignId, $campaignId, $campaignId]);
        }
    }

    private function resolveParameters(?array $mappings, array $recipient): array
    {
        if (empty($mappings)) {
            return [];
        }
        $params = [];
        $custom = is_string($recipient['custom_fields'] ?? null)
            ? json_decode($recipient['custom_fields'], true) ?: []
            : (is_array($recipient['custom_fields'] ?? null) ? $recipient['custom_fields'] : []);

        // Sort keys numerically ('1', '2', etc.)
        $sortedKeys = array_keys($mappings);
        usort($sortedKeys, static fn ($a, $b) => (int) $a <=> (int) $b);

        foreach ($sortedKeys as $key) {
            $mapping = $mappings[$key];
            $value = '';
            if (is_string($mapping)) {
                $val = trim($mapping);
                if (str_starts_with($val, 'contact.')) {
                    $field = substr($val, 8);
                    $value = (string) ($recipient[$field] ?? $custom[$field] ?? '');
                } elseif (in_array($val, ['name', 'phone', 'email'], true)) {
                    $value = (string) ($recipient[$val] ?? '');
                } else {
                    $value = $val;
                }
            } elseif (is_array($mapping)) {
                $type = $mapping['type'] ?? 'static';
                $mapVal = (string) ($mapping['value'] ?? '');
                if ($type === 'field' || $type === 'contact') {
                    $value = (string) ($recipient[$mapVal] ?? $custom[$mapVal] ?? '');
                } else {
                    $value = $mapVal;
                }
            }
            if ($value === '' && isset($mapping['fallback'])) {
                $value = (string) $mapping['fallback'];
            }
            $params[] = $value !== '' ? $value : '—';
        }
        return $params;
    }
}
