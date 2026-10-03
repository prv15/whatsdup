<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Support\Uuid;

final class MetaWebhookService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function process(array $payload): int
    {
        $processed = 0;

        // 1. Process Status Events (Campaign Contacts & Two-Way Conversation Messages)
        foreach (self::statusEvents($payload) as $event) {
            $status = $event['status'];
            $sentAt = in_array($status, ['sent', 'delivered', 'read'], true) ? 'COALESCE(sent_at, UTC_TIMESTAMP())' : 'sent_at';
            $deliveredAt = in_array($status, ['delivered', 'read'], true) ? 'COALESCE(delivered_at, UTC_TIMESTAMP())' : 'delivered_at';
            $readAt = $status === 'read' ? 'COALESCE(read_at, UTC_TIMESTAMP())' : 'read_at';

            // 1a. Update Campaign Contacts
            $recipient = $this->db->prepare('SELECT campaign_id, status FROM campaign_contacts WHERE meta_message_id = ? LIMIT 1');
            $recipient->execute([$event['messageId']]);
            $row = $recipient->fetch();
            if ($row) {
                $current = (string) $row['status'];
                // Do not regress status
                $regress = (($status === 'failed' && in_array($current, ['delivered', 'read'], true))
                    || ($status === 'sent' && in_array($current, ['delivered', 'read'], true))
                    || ($status === 'delivered' && $current === 'read'));

                if (!$regress) {
                    $update = $this->db->prepare("UPDATE campaign_contacts SET status = ?, failure_code = ?, failure_message = ?, sent_at = {$sentAt}, delivered_at = {$deliveredAt}, read_at = {$readAt}, updated_at = UTC_TIMESTAMP() WHERE meta_message_id = ?");
                    $update->execute([$status, $event['errorCode'], $event['errorMessage'], $event['messageId']]);
                    $this->refreshCampaign((string) $row['campaign_id']);
                    $processed++;
                }
            }

            // 1b. Update Two-Way Conversation Messages
            $convMsg = $this->db->prepare('SELECT id, status FROM conversation_messages WHERE meta_message_id = ? LIMIT 1');
            $convMsg->execute([$event['messageId']]);
            $msgRow = $convMsg->fetch();
            if ($msgRow) {
                $currentMsgStatus = (string) $msgRow['status'];
                $regressMsg = (($status === 'failed' && in_array($currentMsgStatus, ['delivered', 'read'], true))
                    || ($status === 'sent' && in_array($currentMsgStatus, ['delivered', 'read'], true))
                    || ($status === 'delivered' && $currentMsgStatus === 'read'));

                if (!$regressMsg) {
                    $updateConv = $this->db->prepare("UPDATE conversation_messages SET status = ?, error_code = ?, error_message = ?, sent_at = {$sentAt}, delivered_at = {$deliveredAt}, read_at = {$readAt} WHERE id = ?");
                    $updateConv->execute([$status, $event['errorCode'], $event['errorMessage'], $msgRow['id']]);
                    $processed++;
                }
            }
        }

        // 2. Process Inbound Messages (Opt-out / Live Two-Way Chat Ingestion)
        foreach (self::inboundMessages($payload) as $msg) {
            $phone = '+' . ltrim($msg['from'], '+');
            $text = strtoupper(trim($msg['text']));

            // Check opt-out keywords
            if (in_array($text, ['STOP', 'UNSUBSCRIBE', 'OPTOUT', 'OPT OUT', 'CANCEL', 'QUIT', 'END'], true)) {
                $this->db->prepare("UPDATE contacts SET consent_status = 'opted_out', consent_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE phone_e164 = ? AND consent_status <> 'opted_out'")->execute([$phone]);
                $processed++;
            }

            // Ingest into Live Conversation if destination phone number matches a connected tenant
            $phoneNumberId = $msg['phoneNumberId'] ?? '';
            $businessId = null;
            if ($phoneNumberId !== '') {
                $connStmt = $this->db->prepare('SELECT business_id FROM whatsapp_phone_numbers WHERE meta_phone_number_id = ? AND deleted_at IS NULL LIMIT 1');
                $connStmt->execute([$phoneNumberId]);
                $businessId = $connStmt->fetchColumn() ?: null;
            }

            // Fallback: if phoneNumberId wasn't mapped, try single active business or contact phone match
            if (!$businessId) {
                $contactMatch = $this->db->prepare('SELECT business_id FROM contacts WHERE phone_e164 = ? AND deleted_at IS NULL LIMIT 1');
                $contactMatch->execute([$phone]);
                $businessId = $contactMatch->fetchColumn() ?: null;
            }

            if ($businessId) {
                // Ensure Contact exists
                $contactStmt = $this->db->prepare('SELECT id FROM contacts WHERE business_id = ? AND phone_e164 = ? AND deleted_at IS NULL LIMIT 1');
                $contactStmt->execute([$businessId, $phone]);
                $contactId = $contactStmt->fetchColumn();

                if (!$contactId) {
                    $contactId = Uuid::v4();
                    $contactName = $msg['name'] ?: 'WhatsApp User';
                    $this->db->prepare("INSERT INTO contacts (id, business_id, phone_e164, name, tags, custom_fields, consent_status, source, created_at, updated_at) VALUES (?, ?, ?, ?, '[]', '{}', 'opted_in', 'inbound_message', UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                        ->execute([$contactId, $businessId, $phone, $contactName]);
                }

                // 24-Hour WhatsApp Session Window: valid for 24 hours from inbound message
                $windowExpiresAt = gmdate('Y-m-d H:i:s', time() + 86400);

                // Ensure Conversation exists
                $convStmt = $this->db->prepare('SELECT id FROM conversations WHERE business_id = ? AND contact_id = ? LIMIT 1');
                $convStmt->execute([$businessId, $contactId]);
                $convId = $convStmt->fetchColumn();

                if (!$convId) {
                    $convId = Uuid::v4();
                    $this->db->prepare("INSERT INTO conversations (id, business_id, contact_id, meta_phone_number_id, status, last_message_at, window_expires_at, unread_count, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', UTC_TIMESTAMP(), ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                        ->execute([$convId, $businessId, $contactId, $phoneNumberId ?: null, $windowExpiresAt]);
                } else {
                    $this->db->prepare("UPDATE conversations SET meta_phone_number_id = COALESCE(?, meta_phone_number_id), status = 'open', last_message_at = UTC_TIMESTAMP(), window_expires_at = ?, unread_count = unread_count + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                        ->execute([$phoneNumberId ?: null, $windowExpiresAt, $convId]);
                }

                // Insert Inbound Message (Idempotent by meta_message_id)
                $messageId = $msg['id'] ?? '';
                $alreadyExists = false;
                if ($messageId !== '') {
                    $checkMsg = $this->db->prepare('SELECT 1 FROM conversation_messages WHERE meta_message_id = ? LIMIT 1');
                    $checkMsg->execute([$messageId]);
                    $alreadyExists = (bool) $checkMsg->fetchColumn();
                }

                if (!$alreadyExists) {
                    $newMsgId = Uuid::v4();
                    $this->db->prepare("INSERT INTO conversation_messages (id, conversation_id, business_id, direction, message_type, content, media_url, meta_message_id, status, delivered_at, created_at) VALUES (?, ?, ?, 'inbound', ?, ?, ?, ?, 'delivered', UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                        ->execute([
                            $newMsgId,
                            $convId,
                            $businessId,
                            $msg['type'] ?? 'text',
                            $msg['text'] !== '' ? $msg['text'] : '[' . ($msg['type'] ?? 'message') . ']',
                            $msg['mediaUrl'] ?? null,
                            $messageId !== '' ? $messageId : null,
                        ]);
                    $processed++;
                }
            }
        }

        // 3. Process Template Status Updates
        foreach (self::templateUpdates($payload) as $tmpl) {
            $status = match (strtoupper($tmpl['event'])) {
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'PENDING', 'IN_APPEAL' => 'pending',
                default => null,
            };
            if ($status !== null) {
                $reason = $status === 'rejected' ? ($tmpl['reason'] ?: 'Rejected by Meta.') : null;
                $this->db->prepare("UPDATE message_templates SET status = ?, rejection_reason = ?, updated_at = UTC_TIMESTAMP() WHERE meta_template_id = ? OR name = ?")->execute([
                    $status,
                    $reason,
                    $tmpl['templateId'],
                    $tmpl['templateName'],
                ]);
                $processed++;
            }
        }

        return $processed;
    }

    public static function statusEvents(array $payload): array
    {
        $events = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $messageId = trim((string) ($status['id'] ?? ''));
                    $state = strtolower((string) ($status['status'] ?? ''));
                    if ($messageId === '' || !in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
                        continue;
                    }
                    $error = $status['errors'][0] ?? [];
                    $events[] = [
                        'messageId' => $messageId,
                        'status' => $state,
                        'errorCode' => $state === 'failed' ? (string) ($error['code'] ?? 'meta_delivery_failed') : null,
                        'errorMessage' => $state === 'failed' ? mb_substr((string) ($error['message'] ?? $error['title'] ?? 'Meta reported that delivery failed.'), 0, 500) : null,
                    ];
                }
            }
        }
        return $events;
    }

    public static function inboundMessages(array $payload): array
    {
        $messages = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $val = $change['value'] ?? [];
                $phoneNumberId = trim((string) ($val['metadata']['phone_number_id'] ?? ''));
                $contacts = [];
                foreach ($val['contacts'] ?? [] as $c) {
                    $waId = (string) ($c['wa_id'] ?? '');
                    if ($waId !== '') {
                        $contacts[$waId] = (string) ($c['profile']['name'] ?? '');
                    }
                }
                foreach ($val['messages'] ?? [] as $message) {
                    $from = trim((string) ($message['from'] ?? ''));
                    $type = (string) ($message['type'] ?? 'text');
                    $body = '';
                    $mediaUrl = null;
                    if ($type === 'text') {
                        $body = (string) ($message['text']['body'] ?? '');
                    } elseif ($type === 'button') {
                        $body = (string) ($message['button']['text'] ?? $message['button']['payload'] ?? '');
                    } elseif ($type === 'interactive') {
                        $body = (string) ($message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '');
                    } elseif (in_array($type, ['image', 'document', 'audio', 'video'], true)) {
                        $body = (string) ($message[$type]['caption'] ?? "Received {$type}");
                        $mediaUrl = (string) ($message[$type]['id'] ?? '');
                    }
                    if ($from !== '') {
                        $messages[] = [
                            'from' => $from,
                            'name' => $contacts[$from] ?? null,
                            'text' => $body,
                            'type' => $type,
                            'mediaUrl' => $mediaUrl,
                            'id' => (string) ($message['id'] ?? ''),
                            'phoneNumberId' => $phoneNumberId,
                            'timestamp' => (int) ($message['timestamp'] ?? time()),
                        ];
                    }
                }
            }
        }
        return $messages;
    }

    public static function templateUpdates(array $payload): array
    {
        $updates = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? '';
                if ($field === 'message_template_status_update') {
                    $val = $change['value'] ?? [];
                    $updates[] = [
                        'templateId' => (string) ($val['message_template_id'] ?? ''),
                        'templateName' => (string) ($val['message_template_name'] ?? ''),
                        'event' => (string) ($val['event'] ?? ''),
                        'reason' => (string) ($val['reason'] ?? ''),
                    ];
                }
            }
        }
        return $updates;
    }

    private function refreshCampaign(string $campaignId): void
    {
        // Check if any recipients are still pending settlement (queued or accepted)
        $unsettled = $this->db->prepare("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('queued', 'accepted')");
        $unsettled->execute([$campaignId]);
        $unsettledCount = (int) $unsettled->fetchColumn();

        $campaign = $this->db->prepare("SELECT status FROM campaigns WHERE id = ? LIMIT 1");
        $campaign->execute([$campaignId]);
        $currentStatus = (string) $campaign->fetchColumn();

        // If campaign was dispatched/processing and all recipients have resolved, mark 'completed'
        $newStatus = ($unsettledCount === 0 && in_array($currentStatus, ['dispatched', 'processing', 'queued'], true))
            ? 'completed'
            : $currentStatus;

        $completedAtClause = ($newStatus === 'completed') ? 'completed_at = COALESCE(completed_at, UTC_TIMESTAMP()),' : '';

        $this->db->prepare("UPDATE campaigns SET status = ?, {$completedAtClause} delivered_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('delivered','read')), read_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'read'), failed_count = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('failed','skipped')), updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$newStatus, $campaignId, $campaignId, $campaignId, $campaignId]);
    }
}
