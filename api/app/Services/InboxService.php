<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class InboxService
{
    public function __construct(
        private readonly PDO $db,
        private readonly MetaGraphClient $metaClient,
        private readonly TokenCipher $cipher,
        private readonly AuditService $audit,
    ) {
    }

    public function listConversations(string $businessId, array $filters = []): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['search'] ?? ''));

        $sql = "SELECT
            c.id,
            c.status,
            c.last_message_at,
            c.window_expires_at,
            c.unread_count,
            c.assigned_user_id,
            c.created_at,
            ct.id AS contact_id,
            ct.name AS contact_name,
            ct.phone_e164 AS contact_phone,
            u.name AS assigned_user_name,
            (SELECT cm.content FROM conversation_messages cm WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) AS last_message_content,
            (SELECT cm.direction FROM conversation_messages cm WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) AS last_message_direction,
            (SELECT cm.status FROM conversation_messages cm WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) AS last_message_status
        FROM conversations c
        JOIN contacts ct ON ct.id = c.contact_id
        LEFT JOIN users u ON u.id = c.assigned_user_id
        WHERE c.business_id = ?";

        $params = [$businessId];

        if (in_array($status, ['open', 'closed', 'snoozed'], true)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        if ($search !== '') {
            $sql .= " AND (ct.name LIKE ? OR ct.phone_e164 LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY c.last_message_at DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $now = time();
        return array_map(function (array $row) use ($now) {
            $expiresAtTimestamp = $row['window_expires_at'] ? strtotime($row['window_expires_at']) : 0;
            $isWindowOpen = $expiresAtTimestamp > $now;
            $remainingMinutes = $isWindowOpen ? max(0, (int) ceil(($expiresAtTimestamp - $now) / 60)) : 0;

            return [
                'id' => $row['id'],
                'status' => $row['status'],
                'lastMessageAt' => $row['last_message_at'],
                'windowExpiresAt' => $row['window_expires_at'],
                'isWindowOpen' => $isWindowOpen,
                'windowRemainingMinutes' => $remainingMinutes,
                'unreadCount' => (int) $row['unread_count'],
                'assignedUserId' => $row['assigned_user_id'],
                'assignedUserName' => $row['assigned_user_name'],
                'contact' => [
                    'id' => $row['contact_id'],
                    'name' => $row['contact_name'],
                    'phone' => $row['contact_phone'],
                ],
                'lastMessage' => $row['last_message_content'] !== null ? [
                    'content' => $row['last_message_content'],
                    'direction' => $row['last_message_direction'],
                    'status' => $row['last_message_status'],
                ] : null,
                'createdAt' => $row['created_at'],
            ];
        }, $rows);
    }

    public function getConversation(string $businessId, string $conversationId): array
    {
        $stmt = $this->db->prepare("SELECT
            c.id,
            c.status,
            c.last_message_at,
            c.window_expires_at,
            c.unread_count,
            c.assigned_user_id,
            c.created_at,
            ct.id AS contact_id,
            ct.name AS contact_name,
            ct.phone_e164 AS contact_phone,
            ct.email AS contact_email,
            ct.tags AS contact_tags,
            ct.consent_status AS contact_consent_status,
            u.name AS assigned_user_name
        FROM conversations c
        JOIN contacts ct ON ct.id = c.contact_id
        LEFT JOIN users u ON u.id = c.assigned_user_id
        WHERE c.id = ? AND c.business_id = ?
        LIMIT 1");

        $stmt->execute([$conversationId, $businessId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new HttpException(404, 'Conversation not found.', 'not_found');
        }

        $now = time();
        $expiresAtTimestamp = $row['window_expires_at'] ? strtotime($row['window_expires_at']) : 0;
        $isWindowOpen = $expiresAtTimestamp > $now;
        $remainingMinutes = $isWindowOpen ? max(0, (int) ceil(($expiresAtTimestamp - $now) / 60)) : 0;

        return [
            'id' => $row['id'],
            'status' => $row['status'],
            'lastMessageAt' => $row['last_message_at'],
            'windowExpiresAt' => $row['window_expires_at'],
            'isWindowOpen' => $isWindowOpen,
            'windowRemainingMinutes' => $remainingMinutes,
            'unreadCount' => (int) $row['unread_count'],
            'assignedUserId' => $row['assigned_user_id'],
            'assignedUserName' => $row['assigned_user_name'],
            'contact' => [
                'id' => $row['contact_id'],
                'name' => $row['contact_name'],
                'phone' => $row['contact_phone'],
                'email' => $row['contact_email'],
                'tags' => json_decode((string) ($row['contact_tags'] ?? '[]'), true) ?: [],
                'consentStatus' => $row['contact_consent_status'],
            ],
            'createdAt' => $row['created_at'],
        ];
    }

    public function getMessages(string $businessId, string $conversationId, int $limit = 50, ?string $beforeId = null): array
    {
        // Verify conversation belongs to tenant
        $conv = $this->db->prepare('SELECT id FROM conversations WHERE id = ? AND business_id = ? LIMIT 1');
        $conv->execute([$conversationId, $businessId]);
        if (!$conv->fetchColumn()) {
            throw new HttpException(404, 'Conversation not found.', 'not_found');
        }

        $limit = max(1, min(100, $limit));
        $params = [$conversationId, $businessId];
        $sql = "SELECT
            m.id,
            m.conversation_id,
            m.direction,
            m.sender_user_id,
            m.message_type,
            m.content,
            m.media_url,
            m.meta_message_id,
            m.status,
            m.error_code,
            m.error_message,
            m.sent_at,
            m.delivered_at,
            m.read_at,
            m.created_at,
            u.name AS sender_name
        FROM conversation_messages m
        LEFT JOIN users u ON u.id = m.sender_user_id
        WHERE m.conversation_id = ? AND m.business_id = ?";

        if ($beforeId !== null && $beforeId !== '') {
            $sql .= " AND m.created_at < (SELECT created_at FROM conversation_messages WHERE id = ?)";
            $params[] = $beforeId;
        }

        $sql .= " ORDER BY m.created_at ASC LIMIT " . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'conversationId' => $row['conversation_id'],
            'direction' => $row['direction'],
            'senderUserId' => $row['sender_user_id'],
            'senderName' => $row['sender_name'],
            'messageType' => $row['message_type'],
            'content' => $row['content'],
            'mediaUrl' => $row['media_url'],
            'metaMessageId' => $row['meta_message_id'],
            'status' => $row['status'],
            'errorCode' => $row['error_code'],
            'errorMessage' => $row['error_message'],
            'sentAt' => $row['sent_at'],
            'deliveredAt' => $row['delivered_at'],
            'readAt' => $row['read_at'],
            'createdAt' => $row['created_at'],
        ], $rows);
    }

    public function sendMessage(string $businessId, string $conversationId, string $userId, string $content): array
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 4096) {
            throw new HttpException(422, 'Message content must be between 1 and 4,096 characters.', 'validation_failed');
        }

        // Fetch conversation & contact details
        $convStmt = $this->db->prepare("SELECT
            c.id,
            c.window_expires_at,
            ct.phone_e164
        FROM conversations c
        JOIN contacts ct ON ct.id = c.contact_id
        WHERE c.id = ? AND c.business_id = ?
        LIMIT 1");
        $convStmt->execute([$conversationId, $businessId]);
        $conv = $convStmt->fetch();

        if (!$conv) {
            throw new HttpException(404, 'Conversation not found.', 'not_found');
        }

        // Strict 24-Hour WhatsApp Service Window Check
        $windowExpires = $conv['window_expires_at'] ? strtotime($conv['window_expires_at']) : 0;
        if ($windowExpires < time()) {
            throw new HttpException(422, 'The 24-hour customer service window has expired. Outbound messages require an approved WhatsApp template.', 'window_expired');
        }

        // Fetch Meta Connection & Connected Phone
        $metaStmt = $this->db->prepare("SELECT
            pn.meta_phone_number_id,
            et.ciphertext,
            et.nonce
        FROM meta_connections mc
        JOIN encrypted_tokens et ON et.id = mc.token_id
        JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
        JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.deleted_at IS NULL
        WHERE mc.business_id = ? AND mc.status = 'connected' AND mc.deleted_at IS NULL
        ORDER BY pn.is_default DESC
        LIMIT 1");
        $metaStmt->execute([$businessId]);
        $conn = $metaStmt->fetch();

        if (!$conn) {
            throw new HttpException(422, 'Connect an active Meta WhatsApp account before sending messages.', 'meta_not_connected');
        }

        $token = $this->cipher->decrypt($conn['ciphertext'], $conn['nonce']);
        $phoneNumberId = (string) $conn['meta_phone_number_id'];
        $recipientPhone = (string) $conv['phone_e164'];

        // Dispatch via Meta Graph API
        $metaResponse = $this->metaClient->sendText($phoneNumberId, $token, $recipientPhone, $content);
        $metaMessageId = (string) ($metaResponse['messages'][0]['id'] ?? '');

        $messageId = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare("INSERT INTO conversation_messages (id, conversation_id, business_id, direction, sender_user_id, message_type, content, meta_message_id, status, sent_at, created_at) VALUES (?, ?, ?, 'outbound', ?, 'text', ?, ?, 'sent', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $insert->execute([$messageId, $conversationId, $businessId, $userId, $content, $metaMessageId ?: null]);

            $this->db->prepare("UPDATE conversations SET last_message_at = UTC_TIMESTAMP(), status = 'open', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$conversationId]);

            $this->audit->record($businessId, $userId, 'inbox.message_sent', 'conversation', $conversationId, [
                'metaMessageId' => $metaMessageId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'id' => $messageId,
            'conversationId' => $conversationId,
            'direction' => 'outbound',
            'senderUserId' => $userId,
            'messageType' => 'text',
            'content' => $content,
            'metaMessageId' => $metaMessageId,
            'status' => 'sent',
            'sentAt' => gmdate('Y-m-d H:i:s'),
            'createdAt' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public function markRead(string $businessId, string $conversationId): void
    {
        $this->db->prepare("UPDATE conversations SET unread_count = 0, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?")
            ->execute([$conversationId, $businessId]);
    }

    public function updateStatus(string $businessId, string $conversationId, string $status): array
    {
        if (!in_array($status, ['open', 'closed', 'snoozed'], true)) {
            throw new HttpException(422, 'Invalid conversation status.', 'validation_failed');
        }

        $stmt = $this->db->prepare("UPDATE conversations SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?");
        $stmt->execute([$status, $conversationId, $businessId]);

        return $this->getConversation($businessId, $conversationId);
    }

    public function assign(string $businessId, string $conversationId, ?string $assignedUserId): array
    {
        if ($assignedUserId !== null && $assignedUserId !== '') {
            $userCheck = $this->db->prepare("SELECT 1 FROM business_users WHERE business_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
            $userCheck->execute([$businessId, $assignedUserId]);
            if (!$userCheck->fetchColumn()) {
                throw new HttpException(422, 'User is not an active team member of this business.', 'validation_failed');
            }
        } else {
            $assignedUserId = null;
        }

        $stmt = $this->db->prepare("UPDATE conversations SET assigned_user_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?");
        $stmt->execute([$assignedUserId, $conversationId, $businessId]);

        return $this->getConversation($businessId, $conversationId);
    }
}
