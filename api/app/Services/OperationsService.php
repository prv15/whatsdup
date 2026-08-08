<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class OperationsService
{
    public function __construct(private readonly PDO $db, private readonly AuditService $audit)
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
        $meta = $this->db->prepare('SELECT status FROM meta_connections WHERE business_id = ? AND deleted_at IS NULL LIMIT 1');
        $meta->execute([$businessId]);
        return [
            'metrics' => ['messagesToday' => (int) ($row['messages_today'] ?? 0), 'contacts' => (int) ($row['contacts'] ?? 0), 'approvedTemplates' => (int) ($row['approved_templates'] ?? 0), 'scheduledCampaigns' => (int) ($row['scheduled_campaigns'] ?? 0)],
            'metaStatus' => $meta->fetchColumn() ?: 'not_connected',
        ];
    }

    public function contacts(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT id, phone_e164, name, email, tags, consent_status, consent_at, source, created_at, updated_at FROM contacts WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 200');
        $statement->execute([$businessId]);
        $contacts = array_map(fn (array $row) => $this->formatContact($row), $statement->fetchAll());
        $imports = $this->db->prepare('SELECT id, file_name, status, total_rows, imported_rows, updated_rows, skipped_rows, errors, created_at, completed_at FROM contact_imports WHERE business_id = ? ORDER BY created_at DESC LIMIT 10');
        $imports->execute([$businessId]);
        return ['contacts' => $contacts, 'imports' => array_map(static fn (array $row) => ['id' => $row['id'], 'fileName' => $row['file_name'], 'status' => $row['status'], 'totalRows' => (int) $row['total_rows'], 'importedRows' => (int) $row['imported_rows'], 'updatedRows' => (int) $row['updated_rows'], 'skippedRows' => (int) $row['skipped_rows'], 'errors' => json_decode((string) $row['errors'], true, 512, JSON_THROW_ON_ERROR), 'createdAt' => $row['created_at'], 'completedAt' => $row['completed_at']], $imports->fetchAll())];
    }

    public function importContacts(string $businessId, string $userId, array $input): array
    {
        $rows = $input['rows'] ?? null;
        $fileName = trim((string) ($input['fileName'] ?? 'contacts.csv'));
        if (!is_array($rows) || $rows === [] || count($rows) > 5000) {
            throw new HttpException(422, 'Upload between 1 and 5,000 contact rows at a time.', 'validation_failed');
        }
        $importId = Uuid::v4(); $imported = 0; $updated = 0; $skipped = 0; $errors = [];
        $exists = $this->db->prepare('SELECT id FROM contacts WHERE business_id = ? AND phone_e164 = ? LIMIT 1');
        $upsert = $this->db->prepare("INSERT INTO contacts (id, business_id, phone_e164, name, email, tags, custom_fields, consent_status, consent_at, source, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?, '{}', ?, ?, 'import', UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL) ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), tags = VALUES(tags), consent_status = VALUES(consent_status), consent_at = VALUES(consent_at), source = 'import', deleted_at = NULL, updated_at = UTC_TIMESTAMP()");
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
                $upsert->execute([$existingId ?: Uuid::v4(), $businessId, $phone, $this->cleanText($row['name'] ?? null, 190), $email !== '' ? mb_strtolower($email) : null, json_encode($tags, JSON_THROW_ON_ERROR), $consent, $consent === 'opted_in' ? gmdate('Y-m-d H:i:s') : null]);
                $existingId ? $updated++ : $imported++;
            }
            $status = $skipped > 0 ? 'completed_with_errors' : 'completed';
            $this->db->prepare('INSERT INTO contact_imports (id, business_id, created_by, file_name, status, total_rows, imported_rows, updated_rows, skipped_rows, errors, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$importId, $businessId, $userId, mb_substr($fileName ?: 'contacts.csv', 0, 255), $status, count($rows), $imported, $updated, $skipped, json_encode(array_slice($errors, 0, 100), JSON_THROW_ON_ERROR)]);
            $this->audit->record($businessId, $userId, 'contacts.imported', 'contact_import', $importId, ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return ['id' => $importId, 'importedRows' => $imported, 'updatedRows' => $updated, 'skippedRows' => $skipped, 'errors' => array_slice($errors, 0, 100)];
    }

    public function templates(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT id, name, language, category, body, status, rejection_reason, created_at, updated_at FROM message_templates WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
        $statement->execute([$businessId]);
        return array_map(static fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'language' => $row['language'], 'category' => $row['category'], 'body' => $row['body'], 'status' => $row['status'], 'rejectionReason' => $row['rejection_reason'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']], $statement->fetchAll());
    }

    public function createTemplate(string $businessId, string $userId, array $input): array
    {
        $name = strtolower(trim((string) ($input['name'] ?? '')));
        $body = trim((string) ($input['body'] ?? ''));
        $language = trim((string) ($input['language'] ?? 'en_US'));
        $category = (string) ($input['category'] ?? 'marketing');
        if (!preg_match('/^[a-z][a-z0-9_]{1,100}$/', $name) || $body === '' || mb_strlen($body) > 1024 || !preg_match('/^[a-z]{2}_[A-Z]{2}$/', $language) || !in_array($category, ['marketing', 'utility', 'authentication'], true)) {
            throw new HttpException(422, 'Enter a valid template name, language, category and message body.', 'validation_failed');
        }
        $id = Uuid::v4();
        try { $this->db->prepare("INSERT INTO message_templates (id, business_id, name, language, category, body, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$id, $businessId, $name, $language, $category, $body, $userId]); }
        catch (Throwable $exception) { throw new HttpException(409, 'A template with this name and language already exists.', 'template_exists'); }
        $this->audit->record($businessId, $userId, 'template.created', 'message_template', $id, ['name' => $name]);
        return $this->templateById($businessId, $id);
    }

    public function campaigns(string $businessId): array
    {
        $statement = $this->db->prepare('SELECT c.id, c.name, c.audience_type, c.status, c.scheduled_at, c.launched_at, c.completed_at, c.recipient_count, c.delivered_count, c.read_count, c.failed_count, c.created_at, t.name template_name, t.language template_language FROM campaigns c JOIN message_templates t ON t.id = c.template_id WHERE c.business_id = ? ORDER BY c.created_at DESC LIMIT 100');
        $statement->execute([$businessId]);
        return array_map(static fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'audienceType' => $row['audience_type'], 'status' => $row['status'], 'scheduledAt' => $row['scheduled_at'], 'launchedAt' => $row['launched_at'], 'completedAt' => $row['completed_at'], 'recipientCount' => (int) $row['recipient_count'], 'deliveredCount' => (int) $row['delivered_count'], 'readCount' => (int) $row['read_count'], 'failedCount' => (int) $row['failed_count'], 'templateName' => $row['template_name'], 'templateLanguage' => $row['template_language'], 'createdAt' => $row['created_at']], $statement->fetchAll());
    }

    public function createCampaign(string $businessId, string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $templateId = (string) ($input['templateId'] ?? '');
        $audience = (string) ($input['audienceType'] ?? 'all_opted_in');
        $selected = is_array($input['contactIds'] ?? null) ? array_values(array_unique(array_filter($input['contactIds'], 'is_string'))) : [];
        $scheduleAt = trim((string) ($input['scheduledAt'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190 || !in_array($audience, ['all_opted_in', 'selected'], true) || ($audience === 'selected' && $selected === [])) { throw new HttpException(422, 'Choose a name, template and eligible audience.', 'validation_failed'); }
        $template = $this->db->prepare('SELECT id FROM message_templates WHERE id = ? AND business_id = ? AND deleted_at IS NULL LIMIT 1'); $template->execute([$templateId, $businessId]);
        if (!$template->fetchColumn()) { throw new HttpException(422, 'Choose a template from this business.', 'validation_failed'); }
        $campaignId = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO campaigns (id, business_id, template_id, name, audience_type, status, scheduled_at, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$campaignId, $businessId, $templateId, $name, $audience, $scheduleAt !== '' ? $scheduleAt : null, $userId]);
            if ($audience === 'all_opted_in') {
                $this->db->prepare("INSERT INTO campaign_contacts (campaign_id, contact_id, business_id, phone_e164, status, created_at, updated_at) SELECT ?, id, business_id, phone_e164, 'queued', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM contacts WHERE business_id = ? AND consent_status = 'opted_in' AND deleted_at IS NULL")->execute([$campaignId, $businessId]);
            } else {
                $placeholders = implode(',', array_fill(0, count($selected), '?'));
                $statement = $this->db->prepare("INSERT INTO campaign_contacts (campaign_id, contact_id, business_id, phone_e164, status, created_at, updated_at) SELECT ?, id, business_id, phone_e164, 'queued', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM contacts WHERE business_id = ? AND consent_status = 'opted_in' AND deleted_at IS NULL AND id IN ({$placeholders})");
                $statement->execute([$campaignId, $businessId, ...$selected]);
            }
            $count = $this->db->prepare('SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ?'); $count->execute([$campaignId]);
            $this->db->prepare('UPDATE campaigns SET recipient_count = ? WHERE id = ?')->execute([(int) $count->fetchColumn(), $campaignId]);
            $this->audit->record($businessId, $userId, 'campaign.created', 'campaign', $campaignId, ['audience' => $audience]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
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
        $status = $row['scheduled_at'] !== null && strtotime($row['scheduled_at']) > time() ? 'scheduled' : 'queued';
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE campaigns SET status = ?, launched_at = IF(? = 'queued', UTC_TIMESTAMP(), NULL), updated_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'draft'")->execute([$status, $status, $campaignId]);
            $this->db->prepare("INSERT INTO queue_jobs (business_id, queue, job_type, payload, idempotency_key, trace_id, status, priority, attempts, max_attempts, available_at, created_at, updated_at) VALUES (?, 'campaigns', 'campaign.dispatch', ?, ?, ?, 'ready', 100, 0, 5, COALESCE((SELECT scheduled_at FROM campaigns WHERE id = ?), UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$businessId, json_encode(['campaign_id' => $campaignId], JSON_THROW_ON_ERROR), 'campaign-dispatch:' . $campaignId, Uuid::v4(), $campaignId]);
            $this->audit->record($businessId, $userId, 'campaign.launched', 'campaign', $campaignId, ['status' => $status]);
            $this->db->commit();
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        return $this->campaignById($businessId, $campaignId);
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

    private function cleanText(mixed $value, int $length): ?string
    {
        $text = trim((string) $value); return $text === '' ? null : mb_substr($text, 0, $length);
    }
}
