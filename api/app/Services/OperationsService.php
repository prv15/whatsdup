<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Security\TokenCipher;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class OperationsService
{
    public function __construct(
        private readonly PDO $db,
        private readonly AuditService $audit,
        private readonly ?QuotaService $quota = null,
        private readonly ?MetaGraphClient $graph = null,
        private readonly ?TokenCipher $cipher = null,
    )
    {
    }

    public function dashboard(string $businessId): array
    {
        $metrics = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM contacts WHERE business_id = ? AND deleted_at IS NULL) contacts,
            (SELECT COUNT(*) FROM message_templates WHERE business_id = ? AND status = 'approved' AND deleted_at IS NULL) approved_templates,
            (SELECT COUNT(*) FROM campaigns WHERE business_id = ? AND status = 'scheduled') scheduled_campaigns,
            (SELECT COUNT(*) FROM campaign_contacts cc JOIN campaigns c ON c.id = cc.campaign_id WHERE c.business_id = ? AND cc.sent_at >= UTC_DATE()) messages_today");
        $metrics->execute([$businessId, $businessId, $businessId, $businessId]);
        $row = $metrics->fetch() ?: [];
        $metaRow = [];
        try {
            $meta = $this->db->prepare("SELECT mc.status, mc.meta_business_id, mc.business_verification_status, mc.verification_initiated_at, pn.messaging_limit_tier
                FROM meta_connections mc
                LEFT JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
                LEFT JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.deleted_at IS NULL
                WHERE mc.business_id = ? AND mc.deleted_at IS NULL LIMIT 1");
            $meta->execute([$businessId]);
            $metaRow = $meta->fetch() ?: [];
        } catch (\Throwable) {
            try {
                $meta = $this->db->prepare("SELECT mc.status, mc.meta_business_id
                    FROM meta_connections mc
                    WHERE mc.business_id = ? AND mc.deleted_at IS NULL LIMIT 1");
                $meta->execute([$businessId]);
                $metaRow = $meta->fetch() ?: [];
            } catch (\Throwable) {}
        }
        return [
            'metrics' => ['messagesToday' => (int) ($row['messages_today'] ?? 0), 'contacts' => (int) ($row['contacts'] ?? 0), 'approvedTemplates' => (int) ($row['approved_templates'] ?? 0), 'scheduledCampaigns' => (int) ($row['scheduled_campaigns'] ?? 0)],
            'metaStatus' => $metaRow['status'] ?? 'not_connected',
            'metaBusinessId' => $metaRow['meta_business_id'] ?? null,
            'businessVerificationStatus' => $metaRow['business_verification_status'] ?? 'unverified',
            'verificationInitiatedAt' => $metaRow['verification_initiated_at'] ?? null,
            'messagingLimitTier' => $metaRow['messaging_limit_tier'] ?? 'TIER_250',
            'quota' => ($this->quota ?? new QuotaService($this->db))->getUsageOverview($businessId),
        ];
    }

    public function contacts(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT id, phone_e164, name, email, tags, consent_status, consent_at, source, created_at, updated_at FROM contacts WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 200');
        $statement->execute([$businessId]);
        $contacts = array_map(fn (array $row) => $this->formatContact($row), $statement->fetchAll());
        $imports = $this->db->prepare('SELECT id, file_name, status, total_rows, imported_rows, updated_rows, skipped_rows, errors, created_at, completed_at FROM contact_imports WHERE business_id = ? ORDER BY created_at DESC LIMIT 10');
        $imports->execute([$businessId]);
        return ['contacts' => $contacts, 'groups' => $this->contactGroups($businessId), 'imports' => array_map(static fn (array $row) => ['id' => $row['id'], 'fileName' => $row['file_name'], 'status' => $row['status'], 'totalRows' => (int) $row['total_rows'], 'importedRows' => (int) $row['imported_rows'], 'updatedRows' => (int) $row['updated_rows'], 'skippedRows' => (int) $row['skipped_rows'], 'errors' => json_decode((string) $row['errors'], true, 512, JSON_THROW_ON_ERROR), 'createdAt' => $row['created_at'], 'completedAt' => $row['completed_at']], $imports->fetchAll())];
    }

    public function contactGroups(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT g.id, g.name, g.description, g.created_at, g.updated_at, COUNT(c.id) member_count FROM contact_groups g LEFT JOIN contact_group_members m ON m.group_id = g.id LEFT JOIN contacts c ON c.id = m.contact_id AND c.deleted_at IS NULL WHERE g.business_id = ? AND g.deleted_at IS NULL GROUP BY g.id, g.name, g.description, g.created_at, g.updated_at ORDER BY g.name');
        $statement->execute([$businessId]);
        return array_map(static fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'description' => $row['description'], 'memberCount' => (int) $row['member_count'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']], $statement->fetchAll());
    }

    public function createContactGroup(string $businessId, string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = $this->cleanText($input['description'] ?? null, 300);
        $contactIds = is_array($input['contactIds'] ?? null) ? array_values(array_unique(array_filter($input['contactIds'], 'is_string'))) : [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || $contactIds === []) {
            throw new HttpException(422, 'Enter a group name and select at least one contact.', 'validation_failed');
        }
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $eligible = $this->db->prepare("SELECT id FROM contacts WHERE business_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})");
        $eligible->execute([$businessId, ...$contactIds]);
        $validIds = $eligible->fetchAll(PDO::FETCH_COLUMN);
        if ($validIds === []) {
            throw new HttpException(422, 'Select contacts from this business.', 'validation_failed');
        }
        $id = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO contact_groups (id, business_id, name, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$id, $businessId, $name, $description, $userId]);
            $member = $this->db->prepare('INSERT IGNORE INTO contact_group_members (group_id, contact_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())');
            foreach ($validIds as $contactId) $member->execute([$id, $contactId]);
            $this->audit->record($businessId, $userId, 'contact_group.created', 'contact_group', $id, ['members' => count($validIds)]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            if ((string) $exception->getCode() === '23000') throw new HttpException(409, 'A group with this name already exists.', 'group_exists');
            throw $exception;
        }
        return ['id' => $id, 'name' => $name, 'description' => $description, 'memberCount' => count($validIds)];
    }

    public function importContacts(string $businessId, string $userId, array $input): array
    {
        $rows = $input['rows'] ?? null;
        $fileName = trim((string) ($input['fileName'] ?? 'contacts.csv'));
        $source = (string) ($input['source'] ?? 'import');
        if (!in_array($source, ['import', 'manual', 'paste'], true)) $source = 'import';
        if (!is_array($rows) || $rows === [] || count($rows) > 5000) {
            throw new HttpException(422, 'Upload between 1 and 5,000 contact rows at a time.', 'validation_failed');
        }
        ($this->quota ?? new QuotaService($this->db))->assertCanImportContacts($businessId, count($rows));
        $importId = Uuid::v4(); $imported = 0; $updated = 0; $skipped = 0; $errors = [];
        $exists = $this->db->prepare('SELECT id FROM contacts WHERE business_id = ? AND phone_e164 = ? LIMIT 1');
        $upsert = $this->db->prepare("INSERT INTO contacts (id, business_id, phone_e164, name, email, tags, custom_fields, consent_status, consent_at, source, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?, '{}', ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL) ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), tags = VALUES(tags), consent_status = VALUES(consent_status), consent_at = VALUES(consent_at), source = VALUES(source), deleted_at = NULL, updated_at = UTC_TIMESTAMP()");
        $this->db->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if (!is_array($row)) { $skipped++; $errors[] = ['row' => $index + 2, 'message' => 'Row is not valid.']; continue; }
                $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));
                if ($phone === null) { $skipped++; $errors[] = ['row' => $index + 2, 'message' => 'Use a phone number in international format, for example +919876543210.']; continue; }
                $email = trim((string) ($row['email'] ?? ''));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; $errors[] = ['row' => $index + 2, 'message' => 'Email is not valid.']; continue; }
                $consent = (string) ($row['consent'] ?? 'opted_in');
                if (!in_array($consent, ['opted_in', 'opted_out', 'unknown'], true)) { $consent = 'unknown'; }
                $tags = array_values(array_unique(array_filter(array_map(static fn ($tag) => trim((string) $tag), is_array($row['tags'] ?? null) ? $row['tags'] : explode(',', (string) ($row['tags'] ?? ''))))));
                $exists->execute([$businessId, $phone]);
                $existingId = $exists->fetchColumn();
                $upsert->execute([$existingId ?: Uuid::v4(), $businessId, $phone, $this->cleanText($row['name'] ?? null, 190), $email !== '' ? mb_strtolower($email) : null, json_encode($tags, JSON_THROW_ON_ERROR), $consent, $consent === 'opted_in' ? gmdate('Y-m-d H:i:s') : null, $source]);
                $existingId ? $updated++ : $imported++;
            }
            $status = $skipped > 0 ? 'completed_with_errors' : 'completed';
            $this->db->prepare('INSERT INTO contact_imports (id, business_id, created_by, file_name, status, total_rows, imported_rows, updated_rows, skipped_rows, errors, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$importId, $businessId, $userId, mb_substr($fileName ?: 'contacts.csv', 0, 255), $status, count($rows), $imported, $updated, $skipped, json_encode(array_slice($errors, 0, 100), JSON_THROW_ON_ERROR)]);
            $this->audit->record($businessId, $userId, 'contacts.imported', 'contact_import', $importId, ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return ['id' => $importId, 'importedRows' => $imported, 'updatedRows' => $updated, 'skippedRows' => $skipped, 'errors' => array_slice($errors, 0, 100)];
    }

    public function updateContact(string $businessId, string $userId, string $id, array $input): array
    {
        $phone = $this->normalizePhone((string) ($input['phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $consent = (string) ($input['consentStatus'] ?? 'unknown');
        if ($phone === null || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || !in_array($consent, ['opted_in', 'opted_out', 'unknown'], true)) {
            throw new HttpException(422, 'Enter a valid phone number, email and consent status.', 'validation_failed');
        }
        $tags = array_values(array_unique(array_filter(array_map(static fn ($tag) => trim((string) $tag), is_array($input['tags'] ?? null) ? $input['tags'] : explode(',', (string) ($input['tags'] ?? ''))))));
        try {
            $statement = $this->db->prepare("UPDATE contacts SET phone_e164 = ?, name = ?, email = ?, tags = ?, consent_status = ?, consent_at = IF(? = 'opted_in', COALESCE(consent_at, UTC_TIMESTAMP()), NULL), updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
            $statement->execute([$phone, $this->cleanText($input['name'] ?? null, 190), $email !== '' ? mb_strtolower($email) : null, json_encode($tags, JSON_THROW_ON_ERROR), $consent, $consent, $id, $businessId]);
        } catch (Throwable $exception) {
            if ((string) $exception->getCode() === '23000') throw new HttpException(409, 'Another contact already uses this phone number.', 'contact_exists');
            throw $exception;
        }
        if ($statement->rowCount() === 0) { $exists = $this->db->prepare('SELECT 1 FROM contacts WHERE id = ? AND business_id = ? AND deleted_at IS NULL'); $exists->execute([$id, $businessId]); if (!$exists->fetchColumn()) throw new HttpException(404, 'Contact not found.', 'not_found'); }
        $this->audit->record($businessId, $userId, 'contact.updated', 'contact', $id);
        $contact = $this->db->prepare('SELECT id, phone_e164, name, email, tags, consent_status, consent_at, source, created_at, updated_at FROM contacts WHERE id = ? AND business_id = ? AND deleted_at IS NULL');
        $contact->execute([$id, $businessId]);
        return $this->formatContact($contact->fetch());
    }

    public function deleteContact(string $businessId, string $userId, string $id): array
    {
        $statement = $this->db->prepare('UPDATE contacts SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND deleted_at IS NULL');
        $statement->execute([$id, $businessId]);
        if ($statement->rowCount() === 0) throw new HttpException(404, 'Contact not found.', 'not_found');
        $this->audit->record($businessId, $userId, 'contact.deleted', 'contact', $id);
        return ['id' => $id, 'deleted' => true];
    }

    public function updateContactGroup(string $businessId, string $userId, string $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new HttpException(422, 'Enter a group name between 2 and 120 characters.', 'validation_failed');
        try {
            $statement = $this->db->prepare('UPDATE contact_groups SET name = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND deleted_at IS NULL');
            $statement->execute([$name, $this->cleanText($input['description'] ?? null, 300), $id, $businessId]);
        } catch (Throwable $exception) {
            if ((string) $exception->getCode() === '23000') throw new HttpException(409, 'A group with this name already exists.', 'group_exists');
            throw $exception;
        }
        if ($statement->rowCount() === 0) { $exists = $this->db->prepare('SELECT 1 FROM contact_groups WHERE id = ? AND business_id = ? AND deleted_at IS NULL'); $exists->execute([$id, $businessId]); if (!$exists->fetchColumn()) throw new HttpException(404, 'Contact group not found.', 'not_found'); }
        $this->audit->record($businessId, $userId, 'contact_group.updated', 'contact_group', $id);
        foreach ($this->contactGroups($businessId) as $group) if ($group['id'] === $id) return $group;
        throw new HttpException(404, 'Contact group not found.', 'not_found');
    }

    public function deleteContactGroup(string $businessId, string $userId, string $id): array
    {
        $statement = $this->db->prepare('UPDATE contact_groups SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND deleted_at IS NULL');
        $statement->execute([$id, $businessId]);
        if ($statement->rowCount() === 0) throw new HttpException(404, 'Contact group not found.', 'not_found');
        $this->audit->record($businessId, $userId, 'contact_group.deleted', 'contact_group', $id);
        return ['id' => $id, 'deleted' => true];
    }

    public function templates(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT id, name, language, category, header_type, header_media_url, body, variables, status, rejection_reason, created_at, updated_at FROM message_templates WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
        $statement->execute([$businessId]);
        return array_map(static fn (array $row) => [
            'id' => $row['id'], 'name' => $row['name'], 'language' => $row['language'], 'category' => $row['category'],
            'headerType' => $row['header_type'], 'headerMediaUrl' => $row['header_media_url'], 'body' => $row['body'],
            'variables' => $row['variables'] ? json_decode((string) $row['variables'], true, 512, JSON_THROW_ON_ERROR) : [],
            'status' => $row['status'], 'rejectionReason' => $row['rejection_reason'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at'],
        ], $statement->fetchAll());
    }

    public function createTemplate(string $businessId, string $userId, array $input): array
    {
        $name = strtolower(trim((string) ($input['name'] ?? '')));
        $body = trim((string) ($input['body'] ?? ''));
        $language = trim((string) ($input['language'] ?? 'en_US'));
        $category = (string) ($input['category'] ?? 'marketing');
        $headerImage = trim((string) ($input['headerImage'] ?? ''));
        if (!preg_match('/^[a-z][a-z0-9_]{1,100}$/', $name) || $body === '' || mb_strlen($body) > 1024 || !preg_match('/^[a-z]{2}_[A-Z]{2}$/', $language) || !in_array($category, ['marketing', 'utility', 'authentication'], true)) {
            throw new HttpException(422, 'Enter a valid template name, language, category and message body.', 'validation_failed');
        }
        $id = Uuid::v4();
        $headerMediaUrl = $headerImage !== '' ? $this->storeTemplateImage($businessId, $headerImage) : null;
        $variables = null;
        if (preg_match_all('/\{\{(\d+)\}\}/', $body, $matches) && !empty($matches[1])) {
            $varKeys = array_values(array_unique($matches[1]));
            sort($varKeys, SORT_NUMERIC);
            $variables = json_encode($varKeys, JSON_THROW_ON_ERROR);
        }
        try {
            $this->db->prepare("INSERT INTO message_templates (id, business_id, name, language, category, header_type, header_media_url, body, variables, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$id, $businessId, $name, $language, $category, $headerMediaUrl !== null ? 'image' : 'none', $headerMediaUrl, $body, $variables, $userId]);
        } catch (Throwable $exception) {
            throw new HttpException(409, 'A template with this name and language already exists.', 'template_exists');
        }
        $this->audit->record($businessId, $userId, 'template.created', 'message_template', $id, ['name' => $name]);
        return $this->templateById($businessId, $id);
    }

    public function updateTemplate(string $businessId, string $userId, string $id, array $input): array
    {
        $body = trim((string) ($input['body'] ?? ''));
        $category = (string) ($input['category'] ?? 'marketing');
        if ($body === '' || mb_strlen($body) > 1024 || !in_array($category, ['marketing', 'utility', 'authentication'], true)) {
            throw new HttpException(422, 'Enter a valid category and message body.', 'validation_failed');
        }
        $variables = null;
        if (preg_match_all('/\{\{(\d+)\}\}/', $body, $matches) && !empty($matches[1])) {
            $varKeys = array_values(array_unique($matches[1]));
            sort($varKeys, SORT_NUMERIC);
            $variables = json_encode($varKeys, JSON_THROW_ON_ERROR);
        }
        $statement = $this->db->prepare("UPDATE message_templates SET category = ?, body = ?, variables = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND status = 'draft' AND deleted_at IS NULL");
        $statement->execute([$category, $body, $variables, $id, $businessId]);
        if ($statement->rowCount() === 0) {
            $draft = $this->db->prepare("SELECT 1 FROM message_templates WHERE id = ? AND business_id = ? AND status = 'draft' AND deleted_at IS NULL");
            $draft->execute([$id, $businessId]);
            if (!$draft->fetchColumn()) throw new HttpException(422, 'Only local template drafts can be edited.', 'template_not_editable');
        }
        $this->audit->record($businessId, $userId, 'template.updated', 'message_template', $id);
        return $this->templateById($businessId, $id);
    }

    public function deleteTemplate(string $businessId, string $userId, string $id): array
    {
        $statement = $this->db->prepare("SELECT id, name, meta_template_id, status FROM message_templates WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $statement->execute([$id, $businessId]);
        $tmpl = $statement->fetch();
        if (!$tmpl) {
            throw new HttpException(404, 'Template not found.', 'not_found');
        }

        if (!empty($tmpl['meta_template_id'])) {
            try {
                $connection = $this->db->prepare("SELECT et.ciphertext, et.nonce, wa.meta_waba_id 
                    FROM meta_connections mc 
                    JOIN encrypted_tokens et ON et.id = mc.token_id 
                    JOIN waba_accounts wa ON wa.meta_connection_id = mc.id 
                    WHERE mc.business_id = ? AND mc.status = 'connected' AND mc.deleted_at IS NULL LIMIT 1");
                $connection->execute([$businessId]);
                $row = $connection->fetch();
                if ($row) {
                    $cipher = $this->cipher ?? new TokenCipher();
                    $graph = $this->graph ?? new MetaGraphClient();
                    $token = $cipher->decrypt((string) $row['ciphertext'], (string) $row['nonce']);
                    $graph->deleteTemplate((string) $row['meta_waba_id'], $token, (string) $tmpl['name']);
                }
            } catch (Throwable) {
                // Ignore Meta remote deletion failures if Meta already removed it
            }
        }

        $this->db->prepare("UPDATE message_templates SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?")
            ->execute([$id, $businessId]);

        $this->audit->record($businessId, $userId, 'template.deleted', 'message_template', $id);
        return ['id' => $id, 'deleted' => true];
    }

    public function submitTemplate(string $businessId, string $userId, string $id): array
    {
        $template = $this->templateById($businessId, $id);
        if ($template['status'] === 'approved') {
            throw new HttpException(422, 'This template has already been approved by Meta.', 'template_already_approved');
        }
        if ($template['status'] === 'pending') {
            throw new HttpException(422, 'This template is already submitted to Meta and is pending approval.', 'template_pending_approval');
        }

        $connection = $this->db->prepare("SELECT et.ciphertext, et.nonce, wa.meta_waba_id 
            FROM meta_connections mc 
            JOIN encrypted_tokens et ON et.id = mc.token_id 
            JOIN waba_accounts wa ON wa.meta_connection_id = mc.id 
            WHERE mc.business_id = ? AND mc.status = 'connected' AND mc.deleted_at IS NULL LIMIT 1");
        $connection->execute([$businessId]);
        $row = $connection->fetch();
        if (!$row) {
            throw new HttpException(422, 'Connect an active Meta WhatsApp account before submitting templates to Meta.', 'meta_not_connected');
        }

        $cipher = $this->cipher ?? new TokenCipher();
        $graph = $this->graph ?? new MetaGraphClient();
        $token = $cipher->decrypt((string) $row['ciphertext'], (string) $row['nonce']);
        $wabaId = (string) $row['meta_waba_id'];

        $components = [];
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $template['body'],
        ];

        if (!empty($template['variables'])) {
            $sampleValues = [];
            foreach ($template['variables'] as $varKey) {
                $sampleValues[] = 'Sample' . $varKey;
            }
            $bodyComponent['example'] = [
                'body_text' => [$sampleValues],
            ];
        }
        $components[] = $bodyComponent;

        $payload = [
            'name' => $template['name'],
            'category' => strtoupper($template['category']),
            'language' => $template['language'],
            'components' => $components,
        ];

        $metaResponse = $graph->createTemplate($wabaId, $token, $payload);
        $metaTemplateId = (string) ($metaResponse['id'] ?? '');
        $metaStatus = match (strtoupper((string) ($metaResponse['status'] ?? ''))) {
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            default => 'pending',
        };

        $this->db->prepare("UPDATE message_templates SET meta_template_id = ?, status = ?, rejection_reason = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?")
            ->execute([$metaTemplateId ?: null, $metaStatus, $id, $businessId]);

        $this->audit->record($businessId, $userId, 'template.submitted_to_meta', 'message_template', $id, [
            'meta_template_id' => $metaTemplateId,
            'status' => $metaStatus,
        ]);

        return $this->templateById($businessId, $id);
    }

    public function campaigns(string $businessId): array
    {
        $statement = $this->db->prepare("SELECT c.id, c.name, c.audience_type, c.variable_mappings, c.status, c.scheduled_at, c.launched_at, c.completed_at, c.recipient_count, c.delivered_count, c.read_count, c.failed_count, (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id = c.id AND cc.status IN ('accepted', 'sent')) accepted_count, c.created_at, t.name template_name, t.language template_language, (SELECT cc.failure_code FROM campaign_contacts cc WHERE cc.campaign_id = c.id AND cc.status = 'failed' ORDER BY cc.updated_at DESC LIMIT 1) failure_code, (SELECT cc.failure_message FROM campaign_contacts cc WHERE cc.campaign_id = c.id AND cc.status = 'failed' ORDER BY cc.updated_at DESC LIMIT 1) failure_message FROM campaigns c JOIN message_templates t ON t.id = c.template_id WHERE c.business_id = ? ORDER BY c.created_at DESC LIMIT 100");
        $statement->execute([$businessId]);
        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'audienceType' => $row['audience_type'],
            'variableMappings' => $row['variable_mappings'] ? json_decode((string) $row['variable_mappings'], true, 512, JSON_THROW_ON_ERROR) : null,
            'status' => $row['status'],
            'scheduledAt' => $row['scheduled_at'],
            'launchedAt' => $row['launched_at'],
            'completedAt' => $row['completed_at'],
            'recipientCount' => (int) $row['recipient_count'],
            'acceptedCount' => (int) ($row['accepted_count'] ?? 0),
            'deliveredCount' => (int) $row['delivered_count'],
            'readCount' => (int) $row['read_count'],
            'failedCount' => (int) $row['failed_count'],
            'failureCode' => $row['failure_code'],
            'failureMessage' => $row['failure_message'],
            'templateName' => $row['template_name'],
            'templateLanguage' => $row['template_language'],
            'createdAt' => $row['created_at'],
        ], $statement->fetchAll());
    }

    public function createCampaign(string $businessId, string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $templateId = (string) ($input['templateId'] ?? '');
        $audience = (string) ($input['audienceType'] ?? 'all_opted_in');
        $selected = is_array($input['contactIds'] ?? null) ? array_values(array_unique(array_filter($input['contactIds'], 'is_string'))) : [];
        $groupIds = is_array($input['groupIds'] ?? null) ? array_values(array_unique(array_filter($input['groupIds'], 'is_string'))) : [];
        $scheduleAt = trim((string) ($input['scheduledAt'] ?? ''));
        $variableMappings = is_array($input['variableMappings'] ?? null) ? $input['variableMappings'] : null;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190 || !in_array($audience, ['all_opted_in', 'selected', 'groups'], true) || ($audience === 'selected' && $selected === []) || ($audience === 'groups' && $groupIds === [])) {
            throw new HttpException(422, 'Choose a name, template and eligible audience.', 'validation_failed');
        }
        $template = $this->db->prepare('SELECT id, variables FROM message_templates WHERE id = ? AND business_id = ? AND deleted_at IS NULL LIMIT 1');
        $template->execute([$templateId, $businessId]);
        $templateRow = $template->fetch();
        if (!$templateRow) {
            throw new HttpException(422, 'Choose a template from this business.', 'validation_failed');
        }
        $campaignId = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO campaigns (id, business_id, template_id, name, audience_type, variable_mappings, status, scheduled_at, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([
                $campaignId, $businessId, $templateId, $name, $audience,
                $variableMappings !== null ? json_encode($variableMappings, JSON_THROW_ON_ERROR) : null,
                $scheduleAt !== '' ? $scheduleAt : null, $userId
            ]);
            if ($audience === 'all_opted_in') {
                $this->db->prepare("INSERT INTO campaign_contacts (campaign_id, contact_id, business_id, phone_e164, status, created_at, updated_at) SELECT ?, id, business_id, phone_e164, 'queued', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM contacts WHERE business_id = ? AND consent_status = 'opted_in' AND deleted_at IS NULL")->execute([$campaignId, $businessId]);
            } elseif ($audience === 'selected') {
                $placeholders = implode(',', array_fill(0, count($selected), '?'));
                $statement = $this->db->prepare("INSERT INTO campaign_contacts (campaign_id, contact_id, business_id, phone_e164, status, created_at, updated_at) SELECT ?, id, business_id, phone_e164, 'queued', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM contacts WHERE business_id = ? AND consent_status = 'opted_in' AND deleted_at IS NULL AND id IN ({$placeholders})");
                $statement->execute([$campaignId, $businessId, ...$selected]);
            } else {
                $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
                $groups = $this->db->prepare("SELECT id FROM contact_groups WHERE business_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})");
                $groups->execute([$businessId, ...$groupIds]);
                $validGroupIds = $groups->fetchAll(PDO::FETCH_COLUMN);
                if ($validGroupIds === []) throw new HttpException(422, 'Choose groups from this business.', 'validation_failed');
                $campaignGroup = $this->db->prepare('INSERT IGNORE INTO campaign_groups (campaign_id, group_id) VALUES (?, ?)');
                foreach ($validGroupIds as $groupId) $campaignGroup->execute([$campaignId, $groupId]);
                $groupPlaceholders = implode(',', array_fill(0, count($validGroupIds), '?'));
                $statement = $this->db->prepare("INSERT IGNORE INTO campaign_contacts (campaign_id, contact_id, business_id, phone_e164, status, created_at, updated_at) SELECT ?, c.id, c.business_id, c.phone_e164, 'queued', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM contacts c JOIN contact_group_members m ON m.contact_id = c.id WHERE c.business_id = ? AND c.consent_status = 'opted_in' AND c.deleted_at IS NULL AND m.group_id IN ({$groupPlaceholders})");
                $statement->execute([$campaignId, $businessId, ...$validGroupIds]);
            }
            $count = $this->db->prepare('SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ?');
            $count->execute([$campaignId]);
            $this->db->prepare('UPDATE campaigns SET recipient_count = ? WHERE id = ?')->execute([(int) $count->fetchColumn(), $campaignId]);
            $this->audit->record($businessId, $userId, 'campaign.created', 'campaign', $campaignId, ['audience' => $audience]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return $this->campaignById($businessId, $campaignId);
    }

    public function launchCampaign(string $businessId, string $userId, string $campaignId): array
    {
        $campaign = $this->db->prepare('SELECT c.id, c.recipient_count, c.scheduled_at, t.status template_status FROM campaigns c JOIN message_templates t ON t.id = c.template_id WHERE c.id = ? AND c.business_id = ? LIMIT 1');
        $campaign->execute([$campaignId, $businessId]); $row = $campaign->fetch();
        if (!$row) { throw new HttpException(404, 'Campaign not found.', 'not_found'); }
        if ($row['template_status'] !== 'approved') { throw new HttpException(422, 'Only Meta-approved templates can be launched.', 'template_not_approved'); }
        if ((int) $row['recipient_count'] === 0) { throw new HttpException(422, 'This campaign has no opted-in recipients.', 'empty_audience'); }
        $meta = $this->db->prepare("SELECT 1 FROM meta_connections WHERE business_id = ? AND status = 'connected' AND deleted_at IS NULL LIMIT 1"); $meta->execute([$businessId]);
        if (!$meta->fetchColumn()) { throw new HttpException(422, 'Connect an active Meta WhatsApp account before launching a campaign.', 'meta_not_connected'); }
        $quota = $this->quota ?? new QuotaService($this->db);
        $quota->assertCanLaunchCampaign($businessId, (int) $row['recipient_count']);
        $status = $row['scheduled_at'] !== null && strtotime($row['scheduled_at']) > time() ? 'scheduled' : 'queued';
        $this->db->beginTransaction();
        try {
            // Serialize launch with administrative sender replacement and recheck approvals.
            $lock = $this->db->prepare('SELECT id FROM businesses WHERE id = ? FOR UPDATE');
            $lock->execute([$businessId]);
            $ready = $this->db->prepare("SELECT c.id FROM campaigns c JOIN message_templates t ON t.id = c.template_id JOIN meta_connections mc ON mc.business_id = c.business_id WHERE c.id = ? AND c.business_id = ? AND c.status = 'draft' AND t.status = 'approved' AND mc.status = 'connected' AND mc.deleted_at IS NULL FOR UPDATE");
            $ready->execute([$campaignId, $businessId]);
            if (!$ready->fetchColumn()) throw new HttpException(409, 'Campaign or sender changed. Refresh and verify the approved template before launching.', 'campaign_not_ready');
            $this->db->prepare("UPDATE campaigns SET status = ?, launched_at = IF(? = 'queued', UTC_TIMESTAMP(), NULL), updated_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'draft'")->execute([$status, $status, $campaignId]);
            $quota->recordRecipientUsage($businessId, (int) $row['recipient_count']);
            $this->db->prepare("INSERT INTO queue_jobs (business_id, queue, job_type, payload, idempotency_key, trace_id, status, priority, attempts, max_attempts, available_at, created_at, updated_at) VALUES (?, 'campaigns', 'campaign.dispatch', ?, ?, ?, 'ready', 100, 0, 5, COALESCE((SELECT scheduled_at FROM campaigns WHERE id = ?), UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$businessId, json_encode(['campaign_id' => $campaignId], JSON_THROW_ON_ERROR), 'campaign-dispatch:' . $campaignId, Uuid::v4(), $campaignId]);
            $this->audit->record($businessId, $userId, 'campaign.launched', 'campaign', $campaignId, ['status' => $status]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return $this->campaignById($businessId, $campaignId);
    }

    public function updateCampaign(string $businessId, string $userId, string $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $scheduledAt = trim((string) ($input['scheduledAt'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190) throw new HttpException(422, 'Enter a campaign name between 2 and 190 characters.', 'validation_failed');
        $statement = $this->db->prepare("UPDATE campaigns SET name = ?, scheduled_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ? AND status = 'draft'");
        $statement->execute([$name, $scheduledAt !== '' ? $scheduledAt : null, $id, $businessId]);
        if ($statement->rowCount() === 0) { $draft = $this->db->prepare("SELECT 1 FROM campaigns WHERE id = ? AND business_id = ? AND status = 'draft'"); $draft->execute([$id, $businessId]); if (!$draft->fetchColumn()) throw new HttpException(422, 'Only campaign drafts can be edited.', 'campaign_not_editable'); }
        $this->audit->record($businessId, $userId, 'campaign.updated', 'campaign', $id);
        return $this->campaignById($businessId, $id);
    }

    public function deleteCampaign(string $businessId, string $userId, string $id): array
    {
        $campaign = $this->db->prepare("SELECT id FROM campaigns WHERE id = ? AND business_id = ? AND status = 'draft' LIMIT 1");
        $campaign->execute([$id, $businessId]);
        if (!$campaign->fetchColumn()) throw new HttpException(422, 'Only campaign drafts can be deleted.', 'campaign_not_deletable');
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM campaign_contacts WHERE campaign_id = ? AND business_id = ?')->execute([$id, $businessId]);
            $this->db->prepare('DELETE FROM campaign_groups WHERE campaign_id = ?')->execute([$id]);
            $this->db->prepare("DELETE FROM campaigns WHERE id = ? AND business_id = ? AND status = 'draft'")->execute([$id, $businessId]);
            $this->audit->record($businessId, $userId, 'campaign.deleted', 'campaign', $id);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return ['id' => $id, 'deleted' => true];
    }

    public function campaignRecipients(string $businessId, string $campaignId): array
    {
        $campaign = $this->db->prepare('SELECT id, name FROM campaigns WHERE id = ? AND business_id = ? LIMIT 1');
        $campaign->execute([$campaignId, $businessId]);
        if (!$campaign->fetch()) {
            throw new HttpException(404, 'Campaign not found.', 'not_found');
        }
        $statement = $this->db->prepare("SELECT cc.contact_id, cc.phone_e164, cc.status, cc.meta_message_id, cc.failure_code, cc.failure_message, cc.sent_at, cc.delivered_at, cc.read_at, c.name contact_name FROM campaign_contacts cc LEFT JOIN contacts c ON c.id = cc.contact_id WHERE cc.campaign_id = ? ORDER BY CASE cc.status WHEN 'failed' THEN 1 WHEN 'skipped' THEN 2 WHEN 'queued' THEN 3 WHEN 'accepted' THEN 4 WHEN 'sent' THEN 5 WHEN 'delivered' THEN 6 WHEN 'read' THEN 7 ELSE 8 END, cc.updated_at DESC LIMIT 500");
        $statement->execute([$campaignId]);
        return array_map(static fn (array $row) => [
            'contactId' => $row['contact_id'],
            'phone' => $row['phone_e164'],
            'status' => $row['status'],
            'metaMessageId' => $row['meta_message_id'],
            'failureCode' => $row['failure_code'],
            'failureMessage' => $row['failure_message'],
            'sentAt' => $row['sent_at'],
            'deliveredAt' => $row['delivered_at'],
            'readAt' => $row['read_at'],
            'contactName' => $row['contact_name'],
        ], $statement->fetchAll());
    }

    private function campaignById(string $businessId, string $campaignId): array
    {
        foreach ($this->campaigns($businessId) as $campaign) { if ($campaign['id'] === $campaignId) return $campaign; }
        throw new HttpException(404, 'Campaign not found.', 'not_found');
    }

    private function templateById(string $businessId, string $id): array
    {
        foreach ($this->templates($businessId) as $template) { if ($template['id'] === $id) return $template; }
        throw new HttpException(404, 'Template not found.', 'not_found');
    }

    private function storeTemplateImage(string $businessId, string $dataUri): string
    {
        if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#', $dataUri, $matches)) {
            throw new HttpException(422, 'Upload a JPEG, PNG or WebP image.', 'invalid_template_image');
        }
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || @getimagesizefromstring($binary) === false) {
            throw new HttpException(422, 'The template image must be a valid image no larger than 5 MB.', 'invalid_template_image');
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$matches[1]];
        $relative = 'uploads/templates/' . preg_replace('/[^a-zA-Z0-9-]/', '', $businessId);
        $directory = dirname(__DIR__, 2) . '/public/' . $relative;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Template media directory could not be created.');
        }
        $file = Uuid::v4() . '.' . $extension;
        if (file_put_contents($directory . '/' . $file, $binary, LOCK_EX) === false) {
            throw new \RuntimeException('Template image could not be stored.');
        }
        return rtrim(Env::get('APP_URL', '') ?? '', '/') . '/' . $relative . '/' . $file;
    }

    private function formatContact(array $row): array
    {
        return ['id' => $row['id'], 'phone' => $row['phone_e164'], 'name' => $row['name'], 'email' => $row['email'], 'tags' => json_decode((string) $row['tags'], true, 512, JSON_THROW_ON_ERROR), 'consentStatus' => $row['consent_status'], 'consentAt' => $row['consent_at'], 'source' => $row['source'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']];
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/[\s\-\(\)\.]/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '00')) $phone = '+' . substr($phone, 2);
        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : null;
    }

    public function settings(string $businessId, string $userId): array
    {
        $bizStmt = $this->db->prepare('SELECT id, name, slug, legal_name, timezone, language, default_country_code, status, created_at FROM businesses WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $bizStmt->execute([$businessId]);
        $business = $bizStmt->fetch();
        if (!$business) {
            throw new HttpException(404, 'Business not found.', 'not_found');
        }

        $teamStmt = $this->db->prepare("SELECT u.id, u.name, u.email, u.status, bu.is_primary, bu.created_at joined_at,
                r.name role_name
            FROM business_users bu
            JOIN users u ON u.id = bu.user_id AND u.deleted_at IS NULL
            LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.business_id = bu.business_id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE bu.business_id = ? AND bu.status != 'archived'
            ORDER BY bu.is_primary DESC, u.created_at ASC");
        $teamStmt->execute([$businessId]);
        $teamRows = $teamStmt->fetchAll();

        $team = array_map(static fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'status' => $row['status'],
            'isPrimary' => (bool) $row['is_primary'],
            'role' => $row['role_name'] ?? 'Viewer',
            'joinedAt' => $row['joined_at'],
        ], $teamRows);

        $quotaService = $this->quota ?? new QuotaService($this->db);
        $quota = $quotaService->getUsageOverview($businessId);

        return [
            'business' => [
                'id' => $business['id'],
                'name' => $business['name'],
                'legalName' => $business['legal_name'],
                'slug' => $business['slug'],
                'timezone' => $business['timezone'] ?: 'Asia/Kolkata',
                'language' => $business['language'] ?: 'en',
                'defaultCountryCode' => $business['default_country_code'] ?: '+91',
                'status' => $business['status'],
                'createdAt' => $business['created_at'],
            ],
            'team' => $team,
            'quota' => $quota,
        ];
    }

    public function updateSettings(string $businessId, string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'Business name is required.', 'validation_failed');
        }
        $legalName = $this->cleanText($input['legalName'] ?? null, 190);
        $timezone = trim((string) ($input['timezone'] ?? 'Asia/Kolkata')) ?: 'Asia/Kolkata';
        $language = trim((string) ($input['language'] ?? 'en')) ?: 'en';
        $defaultCountryCode = trim((string) ($input['defaultCountryCode'] ?? '+91')) ?: '+91';

        $stmt = $this->db->prepare('UPDATE businesses SET name = ?, legal_name = ?, timezone = ?, language = ?, default_country_code = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$name, $legalName, $timezone, $language, $defaultCountryCode, $businessId]);

        $this->audit->record($businessId, $userId, 'business.profile.updated', 'business', $businessId, [
            'name' => $name,
            'timezone' => $timezone,
        ]);

        return $this->settings($businessId, $userId);
    }

    public function inviteTeamMember(string $businessId, string $userId, array $input): array
    {
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $roleName = trim((string) ($input['role'] ?? 'Viewer'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Enter a valid email address.', 'validation_failed');
        }
        if ($name === '') {
            throw new HttpException(422, 'Name is required.', 'validation_failed');
        }

        $roleStmt = $this->db->prepare("SELECT id FROM roles WHERE name = ? AND scope = 'business' LIMIT 1");
        $roleStmt->execute([$roleName]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) {
            $roleName = 'Viewer';
            $roleStmt->execute([$roleName]);
            $roleId = $roleStmt->fetchColumn();
        }

        $userStmt = $this->db->prepare('SELECT id, status FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $userStmt->execute([$email]);
        $existingUser = $userStmt->fetch();

        $this->db->beginTransaction();
        try {
            if ($existingUser) {
                $targetUserId = (string) $existingUser['id'];
                $checkBizUser = $this->db->prepare('SELECT status FROM business_users WHERE business_id = ? AND user_id = ?');
                $checkBizUser->execute([$businessId, $targetUserId]);
                $bizUserRow = $checkBizUser->fetch();
                if ($bizUserRow && $bizUserRow['status'] === 'active') {
                    throw new HttpException(409, 'This user is already a member of your workspace.', 'already_member');
                }

                if ($bizUserRow) {
                    $this->db->prepare("UPDATE business_users SET status = 'active', updated_at = UTC_TIMESTAMP() WHERE business_id = ? AND user_id = ?")->execute([$businessId, $targetUserId]);
                } else {
                    $this->db->prepare("INSERT INTO business_users (business_id, user_id, status, is_primary, created_at, updated_at) VALUES (?, ?, 'active', FALSE, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$businessId, $targetUserId]);
                }
            } else {
                $targetUserId = Uuid::v4();
                $tempPassword = bin2hex(random_bytes(16));
                $this->db->prepare("INSERT INTO users (id, name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                    ->execute([$targetUserId, $name, $email, password_hash($tempPassword, PASSWORD_DEFAULT)]);
                $this->db->prepare("INSERT INTO business_users (business_id, user_id, status, is_primary, created_at, updated_at) VALUES (?, ?, 'active', FALSE, UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                    ->execute([$businessId, $targetUserId]);
            }

            $this->db->prepare('DELETE FROM user_roles WHERE business_id = ? AND user_id = ?')->execute([$businessId, $targetUserId]);
            $this->db->prepare('INSERT INTO user_roles (business_id, user_id, role_id, assigned_by, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())')->execute([$businessId, $targetUserId, $roleId, $userId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->audit->record($businessId, $userId, 'team.member.invited', 'user', $targetUserId, [
            'email' => $email,
            'role' => $roleName,
        ]);

        return $this->settings($businessId, $userId);
    }

    public function removeTeamMember(string $businessId, string $userId, string $targetUserId): array
    {
        $stmt = $this->db->prepare('SELECT is_primary FROM business_users WHERE business_id = ? AND user_id = ?');
        $stmt->execute([$businessId, $targetUserId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new HttpException(404, 'Team member not found.', 'not_found');
        }
        if ((bool) $row['is_primary']) {
            throw new HttpException(400, 'Cannot remove the primary workspace owner.', 'cannot_remove_owner');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM user_roles WHERE business_id = ? AND user_id = ?')->execute([$businessId, $targetUserId]);
            $this->db->prepare('DELETE FROM business_users WHERE business_id = ? AND user_id = ?')->execute([$businessId, $targetUserId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->audit->record($businessId, $userId, 'team.member.removed', 'user', $targetUserId);
        return $this->settings($businessId, $userId);
    }

    public function changePassword(string $userId, array $input): array
    {
        $currentPassword = (string) ($input['currentPassword'] ?? '');
        $newPassword = (string) ($input['newPassword'] ?? '');

        if (strlen($newPassword) < 8) {
            throw new HttpException(422, 'New password must be at least 8 characters long.', 'validation_failed');
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            throw new HttpException(422, 'The current password you entered is incorrect.', 'invalid_password');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([$newHash, $userId]);

        return ['message' => 'Password changed successfully.'];
    }

    public function reports(string $businessId, array $query): array
    {
        $days = (int) ($query['days'] ?? 30);
        if (!in_array($days, [7, 14, 30, 90], true)) {
            $days = 30;
        }

        $statsStmt = $this->db->prepare("SELECT
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM campaign_contacts
            WHERE business_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)");
        $statsStmt->execute([$businessId, $days]);
        $stats = $statsStmt->fetch() ?: [];

        $totalAttempts = (int) ($stats['total_attempts'] ?? 0);
        $sentCount = (int) ($stats['sent_count'] ?? 0);
        $deliveredCount = (int) ($stats['delivered_count'] ?? 0);
        $readCount = (int) ($stats['read_count'] ?? 0);
        $failedCount = (int) ($stats['failed_count'] ?? 0);

        $deliveryRate = $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 1) : 0.0;
        $readRate = $deliveredCount > 0 ? round(($readCount / $deliveredCount) * 100, 1) : 0.0;
        $failureRate = $totalAttempts > 0 ? round(($failedCount / $totalAttempts) * 100, 1) : 0.0;

        $dailyStmt = $this->db->prepare("SELECT
                DATE(created_at) as date,
                SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM campaign_contacts
            WHERE business_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC");
        $dailyStmt->execute([$businessId, $days]);
        $dailyRows = $dailyStmt->fetchAll();

        $failStmt = $this->db->prepare("SELECT
                COALESCE(failure_code, 'unknown') as code,
                COALESCE(failure_message, 'Delivery failed or unreachable') as message,
                COUNT(*) as count
            FROM campaign_contacts
            WHERE business_id = ? AND status = 'failed' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
            GROUP BY failure_code, failure_message
            ORDER BY count DESC
            LIMIT 5");
        $failStmt->execute([$businessId, $days]);
        $failureReasons = $failStmt->fetchAll();

        $campStmt = $this->db->prepare("SELECT
                c.id, c.name, c.status, c.launched_at, c.completed_at, c.created_at,
                c.recipient_count, c.delivered_count, c.read_count, c.failed_count,
                t.name as template_name, t.category as template_category
            FROM campaigns c
            LEFT JOIN message_templates t ON t.id = c.template_id
            WHERE c.business_id = ? AND c.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
            ORDER BY c.created_at DESC
            LIMIT 50");
        $campStmt->execute([$businessId, $days]);
        $campaigns = array_map(static function (array $c) {
            $rec = (int) $c['recipient_count'];
            $del = (int) $c['delivered_count'];
            $read = (int) $c['read_count'];
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'templateName' => $c['template_name'] ?? '—',
                'templateCategory' => $c['template_category'] ?? 'marketing',
                'status' => $c['status'],
                'recipientCount' => $rec,
                'deliveredCount' => $del,
                'readCount' => $read,
                'failedCount' => (int) $c['failed_count'],
                'deliveryRate' => $rec > 0 ? round(($del / $rec) * 100, 1) : 0.0,
                'readRate' => $del > 0 ? round(($read / $del) * 100, 1) : 0.0,
                'launchedAt' => $c['launched_at'],
                'completedAt' => $c['completed_at'] ?? $c['created_at'],
            ];
        }, $campStmt->fetchAll());

        $contactsCountStmt = $this->db->prepare('SELECT COUNT(*) FROM contacts WHERE business_id = ? AND consent_status = "opted_in" AND deleted_at IS NULL');
        $contactsCountStmt->execute([$businessId]);
        $optedInContacts = (int) $contactsCountStmt->fetchColumn();

        return [
            'timeframeDays' => $days,
            'summary' => [
                'totalAttempts' => $totalAttempts,
                'sentCount' => $sentCount,
                'deliveredCount' => $deliveredCount,
                'readCount' => $readCount,
                'failedCount' => $failedCount,
                'deliveryRate' => $deliveryRate,
                'readRate' => $readRate,
                'failureRate' => $failureRate,
                'optedInContacts' => $optedInContacts,
            ],
            'daily' => $dailyRows,
            'failureReasons' => $failureReasons,
            'campaigns' => $campaigns,
        ];
    }

    private function cleanText(mixed $value, int $length): ?string
    {
        $text = trim((string) $value); return $text === '' ? null : mb_substr($text, 0, $length);
    }
}
