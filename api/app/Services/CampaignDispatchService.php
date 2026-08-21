<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Support\HttpException;

final class CampaignDispatchService
{
    public function __construct(private readonly PDO $db, private readonly MetaGraphClient $graph, private readonly TokenCipher $cipher, private readonly AuditService $audit)
    {
    }

    public function dispatch(string $campaignId): array
    {
        $campaign = $this->db->prepare("SELECT c.id, c.business_id, c.name, t.name template_name, t.language, t.status template_status, t.header_type, t.header_media_url, pn.meta_phone_number_id, et.ciphertext, et.nonce FROM campaigns c JOIN message_templates t ON t.id = c.template_id JOIN meta_connections mc ON mc.business_id = c.business_id AND mc.status = 'connected' AND mc.deleted_at IS NULL JOIN encrypted_tokens et ON et.id = mc.token_id JOIN waba_accounts wa ON wa.meta_connection_id = mc.id JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.is_default = TRUE AND pn.deleted_at IS NULL WHERE c.id = ? LIMIT 1");
        $campaign->execute([$campaignId]); $row = $campaign->fetch();
        if (!$row) throw new \RuntimeException('Campaign cannot be dispatched because Meta connection or phone information is unavailable.');
        if ($row['template_status'] !== 'approved') throw new \RuntimeException('Campaign template is no longer approved by Meta.');
        $token = $this->cipher->decrypt((string) $row['ciphertext'], (string) $row['nonce']);
        $this->db->prepare("UPDATE campaigns SET status = 'processing', updated_at = UTC_TIMESTAMP() WHERE id = ? AND status IN ('queued', 'scheduled')")->execute([$campaignId]);
        $recipients = $this->db->prepare("SELECT contact_id, phone_e164 FROM campaign_contacts WHERE campaign_id = ? AND status = 'queued' ORDER BY contact_id");
        $recipients->execute([$campaignId]);
        $sent = 0; $failed = 0;
        $markSent = $this->db->prepare("UPDATE campaign_contacts SET status = 'sent', meta_message_id = ?, sent_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE campaign_id = ? AND contact_id = ?");
        $markFailed = $this->db->prepare("UPDATE campaign_contacts SET status = 'failed', failure_code = ?, failure_message = ?, updated_at = UTC_TIMESTAMP() WHERE campaign_id = ? AND contact_id = ?");
        foreach ($recipients->fetchAll() as $recipient) {
            try {
                if ($row['header_type'] === 'image' && empty($row['header_media_url'])) throw new \RuntimeException('Image template has no uploaded header image.');
                $result = $this->graph->sendTemplate((string) $row['meta_phone_number_id'], $token, ltrim((string) $recipient['phone_e164'], '+'), (string) $row['template_name'], (string) $row['language'], $row['header_type'] === 'image' ? (string) $row['header_media_url'] : null);
                $messageId = trim((string) ($result['messages'][0]['id'] ?? ''));
                if ($messageId === '') {
                    throw new \RuntimeException('Meta accepted the request without returning a message ID.');
                }
                $markSent->execute([$messageId, $campaignId, $recipient['contact_id']]); $sent++;
            } catch (HttpException $exception) {
                $markFailed->execute([$exception->codeName, mb_substr($exception->getMessage(), 0, 500), $campaignId, $recipient['contact_id']]); $failed++;
            } catch (Throwable $exception) {
                $markFailed->execute(['dispatch_error', 'Message could not be dispatched.', $campaignId, $recipient['contact_id']]); $failed++;
            }
        }
        $status = $sent === 0 && $failed > 0 ? 'failed' : 'completed';
        $this->db->prepare("UPDATE campaigns SET status = ?, completed_at = UTC_TIMESTAMP(), delivered_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('delivered','read')), read_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'read'), failed_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'failed'), updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$status, $campaignId, $campaignId, $campaignId, $campaignId]);
        $this->audit->record((string) $row['business_id'], null, 'campaign.dispatched', 'campaign', $campaignId, ['sent' => $sent, 'failed' => $failed]);
        return ['sent' => $sent, 'failed' => $failed];
    }
}
