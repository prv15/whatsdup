<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class MetaConnectionService
{
    public function __construct(private readonly PDO $db, private readonly MetaGraphClient $graph, private readonly TokenCipher $cipher, private readonly AuditService $audit)
    {
    }

    public function configuration(): array
    {
        $required = ['META_APP_ID', 'META_APP_SECRET', 'META_CONFIG_ID', 'META_GRAPH_API_VERSION', 'TOKEN_ENCRYPTION_KEY'];
        $missing = array_values(array_filter($required, static fn (string $key) => trim(Env::get($key, '') ?? '') === ''));
        return [
            'enabled' => $missing === [], 'appId' => Env::get('META_APP_ID', ''), 'configId' => Env::get('META_CONFIG_ID', ''),
            'graphVersion' => Env::get('META_GRAPH_API_VERSION', ''), 'missing' => $missing,
            'requiresHttps' => !str_starts_with(Env::get('APP_URL', '') ?? '', 'https://'),
        ];
    }

    public function status(string $businessId): array
    {
        $statement = $this->db->prepare("SELECT mc.id, mc.status, mc.meta_business_id, mc.connected_at, mc.last_synced_at, mc.last_tested_at, mc.last_error_code, mc.last_error_message,
                wa.meta_waba_id, wa.name waba_name, wa.currency, wa.review_status,
                pn.meta_phone_number_id, pn.display_phone_number, pn.verified_name, pn.quality_rating, pn.name_status, pn.registration_status, pn.is_default,
                ws.status webhook_status
            FROM meta_connections mc
            LEFT JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
            LEFT JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.deleted_at IS NULL
            LEFT JOIN webhook_subscriptions ws ON ws.waba_account_id = wa.id
            WHERE mc.business_id = ? AND mc.deleted_at IS NULL LIMIT 1");
        $statement->execute([$businessId]);
        $row = $statement->fetch();
        if (!$row) {
            return ['status' => 'not_connected', 'waba' => null, 'phone' => null, 'webhookStatus' => 'pending', 'connectedAt' => null, 'lastSyncedAt' => null, 'lastTestedAt' => null, 'error' => null];
        }
        return [
            'status' => $row['status'], 'metaBusinessId' => $row['meta_business_id'], 'connectedAt' => $row['connected_at'], 'lastSyncedAt' => $row['last_synced_at'], 'lastTestedAt' => $row['last_tested_at'],
            'waba' => $row['meta_waba_id'] ? ['id' => $row['meta_waba_id'], 'name' => $row['waba_name'], 'currency' => $row['currency'], 'reviewStatus' => $row['review_status']] : null,
            'phone' => $row['meta_phone_number_id'] ? ['id' => $row['meta_phone_number_id'], 'number' => $row['display_phone_number'], 'verifiedName' => $row['verified_name'], 'qualityRating' => $row['quality_rating'], 'nameStatus' => $row['name_status'], 'registrationStatus' => $row['registration_status'], 'isDefault' => (bool) $row['is_default']] : null,
            'webhookStatus' => $row['webhook_status'] ?? 'pending', 'error' => $row['last_error_message'] ? ['code' => $row['last_error_code'], 'message' => $row['last_error_message']] : null,
        ];
    }

    public function complete(string $businessId, string $userId, array $input): array
    {
        $config = $this->configuration();
        if (!$config['enabled']) {
            throw new HttpException(503, 'Meta Embedded Signup is not configured by the platform administrator.', 'meta_not_configured');
        }
        $code = trim((string) ($input['code'] ?? ''));
        $wabaId = trim((string) ($input['wabaId'] ?? ''));
        $phoneId = trim((string) ($input['phoneNumberId'] ?? ''));
        if ($code === '' || !preg_match('/^\d{5,30}$/', $wabaId) || !preg_match('/^\d{5,30}$/', $phoneId)) {
            throw new HttpException(422, 'Meta did not return the required signup identifiers.', 'meta_signup_incomplete');
        }
        $existing = $this->db->prepare('SELECT 1 FROM meta_connections WHERE business_id = ? AND deleted_at IS NULL LIMIT 1');
        $existing->execute([$businessId]);
        if ($existing->fetchColumn()) {
            throw new HttpException(409, 'A Meta connection already exists for this business.', 'meta_connection_exists');
        }
        $exchange = $this->graph->exchangeCode($code);
        $token = (string) ($exchange['access_token'] ?? '');
        if ($token === '') {
            throw new HttpException(422, 'Meta did not issue an access token.', 'meta_token_missing');
        }
        $waba = $this->graph->getWaba($wabaId, $token);
        $phone = $this->graph->getPhone($phoneId, $token);
        if ((string) ($waba['id'] ?? '') !== $wabaId || (string) ($phone['id'] ?? '') !== $phoneId) {
            throw new HttpException(422, 'The returned Meta assets could not be verified.', 'meta_asset_mismatch');
        }
        $encrypted = $this->cipher->encrypt($token);
        $tokenId = Uuid::v4(); $connectionId = Uuid::v4(); $wabaLocalId = Uuid::v4(); $phoneLocalId = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO encrypted_tokens (id, business_id, provider, ciphertext, nonce, key_version, expires_at, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tokenId, $businessId, 'meta', $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['keyVersion'], json_encode(['token_type' => $exchange['token_type'] ?? 'bearer'], JSON_THROW_ON_ERROR)]);
            $metaBusinessId = (string) ($waba['owner_business_info']['id'] ?? '');
            $this->db->prepare("INSERT INTO meta_connections (id, business_id, token_id, meta_business_id, app_id, status, connected_by, connected_at, last_synced_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'connecting', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$connectionId, $businessId, $tokenId, $metaBusinessId ?: null, Env::get('META_APP_ID'), $userId]);
            $this->db->prepare('INSERT INTO waba_accounts (id, business_id, meta_connection_id, meta_waba_id, name, currency, timezone_id, review_status, status, last_synced_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$wabaLocalId, $businessId, $connectionId, $wabaId, $waba['name'] ?? null, $waba['currency'] ?? null, isset($waba['timezone_id']) ? (string) $waba['timezone_id'] : null, $waba['account_review_status'] ?? null, 'active']);
            $this->db->prepare("INSERT INTO whatsapp_phone_numbers (id, business_id, waba_account_id, meta_phone_number_id, display_phone_number, verified_name, quality_rating, name_status, connection_status, is_default, last_synced_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'connected', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$phoneLocalId, $businessId, $wabaLocalId, $phoneId, $phone['display_phone_number'] ?? null, $phone['verified_name'] ?? null, $phone['quality_rating'] ?? null, $phone['name_status'] ?? null]);
            $this->db->prepare("INSERT INTO webhook_subscriptions (id, business_id, waba_account_id, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([Uuid::v4(), $businessId, $wabaLocalId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        try {
            $this->graph->subscribeWaba($wabaId, $token);
            $this->db->prepare("UPDATE webhook_subscriptions SET status = 'active', subscribed_at = UTC_TIMESTAMP(), last_verified_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE waba_account_id = ?")->execute([$wabaLocalId]);
            $this->db->prepare("UPDATE meta_connections SET status = 'connected', updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$connectionId]);
        } catch (HttpException $exception) {
            $this->db->prepare("UPDATE webhook_subscriptions SET status = 'failed', error_message = ?, updated_at = UTC_TIMESTAMP() WHERE waba_account_id = ?")->execute([$exception->getMessage(), $wabaLocalId]);
            $this->db->prepare("UPDATE meta_connections SET status = 'webhook_error', last_error_code = ?, last_error_message = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$exception->codeName, $exception->getMessage(), $connectionId]);
        }
        $this->audit->record($businessId, $userId, 'meta.connection.completed', 'meta_connection', $connectionId, ['waba_id' => $wabaId, 'phone_number_id' => $phoneId]);
        return $this->status($businessId);
    }

    public function syncTemplates(string $businessId, string $userId): array
    {
        $connection = $this->db->prepare("SELECT et.ciphertext, et.nonce, wa.meta_waba_id FROM meta_connections mc JOIN encrypted_tokens et ON et.id = mc.token_id JOIN waba_accounts wa ON wa.meta_connection_id = mc.id WHERE mc.business_id = ? AND mc.status = 'connected' AND mc.deleted_at IS NULL LIMIT 1");
        $connection->execute([$businessId]);
        $row = $connection->fetch();
        if (!$row) {
            throw new HttpException(422, 'Connect an active Meta WhatsApp account before syncing templates.', 'meta_not_connected');
        }
        $token = $this->cipher->decrypt((string) $row['ciphertext'], (string) $row['nonce']);
        $response = $this->graph->getTemplates((string) $row['meta_waba_id'], $token);
        $templates = is_array($response['data'] ?? null) ? $response['data'] : [];
        $statement = $this->db->prepare("INSERT INTO message_templates (id, business_id, meta_template_id, name, language, category, header_type, body, status, rejection_reason, created_by, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL) ON DUPLICATE KEY UPDATE meta_template_id = VALUES(meta_template_id), category = VALUES(category), header_type = VALUES(header_type), body = VALUES(body), status = VALUES(status), rejection_reason = VALUES(rejection_reason), updated_at = UTC_TIMESTAMP(), deleted_at = NULL");
        $synced = 0;
        $this->db->beginTransaction();
        try {
            foreach ($templates as $template) {
                if (!is_array($template) || !isset($template['name'], $template['language'])) continue;
                $status = match (strtoupper((string) ($template['status'] ?? ''))) { 'APPROVED' => 'approved', 'REJECTED' => 'rejected', default => 'draft' };
                $category = strtolower((string) ($template['category'] ?? 'marketing'));
                if (!in_array($category, ['marketing', 'utility', 'authentication'], true)) $category = 'marketing';
                $body = ''; $headerType = 'none';
                foreach ((array) ($template['components'] ?? []) as $component) {
                    if (!is_array($component)) continue;
                    if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') $body = (string) ($component['text'] ?? '');
                    if (strtoupper((string) ($component['type'] ?? '')) === 'HEADER' && strtoupper((string) ($component['format'] ?? '')) === 'IMAGE') $headerType = 'image';
                }
                $statement->execute([Uuid::v4(), $businessId, (string) ($template['id'] ?? ''), strtolower((string) $template['name']), (string) $template['language'], $category, $headerType, $body, $status, $status === 'rejected' ? 'Rejected by Meta. Check Meta Business Manager for details.' : null, $userId]);
                $synced++;
            }
            $this->audit->record($businessId, $userId, 'meta.templates.synced', 'meta_connection', (string) $row['meta_waba_id'], ['count' => $synced]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return ['synced' => $synced];
    }
}
