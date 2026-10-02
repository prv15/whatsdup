<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use DateTimeZone;
use PDO;
use Throwable;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Uuid;

final class AdminService
{
    private QueueService $queue;

    public function __construct(private readonly PDO $db, private readonly AuditService $audit)
    {
        $this->queue = new QueueService($this->db);
    }

    public function dashboard(): array
    {
        return [
            'businesses' => $this->safeCount('businesses', 'deleted_at IS NULL'),
            'activeBusinesses' => $this->safeCount('businesses', "status = 'active' AND deleted_at IS NULL"),
            'users' => $this->safeCount('users', 'deleted_at IS NULL'),
            'activeSessions' => $this->safeCount('user_sessions', 'revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()'),
            'queuedJobs' => $this->safeCount('queue_jobs', "status IN ('ready', 'reserved')"),
            'failedJobs' => $this->safeCount('failed_jobs', 'retried_at IS NULL'),
            'connectedWhatsApp' => $this->safeCount('whatsapp_phone_numbers', 'deleted_at IS NULL'),
            'totalCampaigns' => $this->safeCount('campaigns', 'deleted_at IS NULL'),
        ];
    }

    private function safeCount(string $table, string $condition = '1=1'): int
    {
        try {
            $statement = $this->db->query("SELECT COUNT(*) FROM `{$table}` WHERE {$condition}");
            return $statement ? (int) $statement->fetchColumn() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    public function businesses(): array
    {
        $sql = "SELECT b.id, b.name, b.slug, b.timezone, b.status, b.created_at,
                    COUNT(DISTINCT bu.user_id) user_count,
                    MAX(CASE WHEN bu.is_primary = TRUE THEN u.name END) owner_name,
                    MAX(CASE WHEN bu.is_primary = TRUE THEN u.email END) owner_email,
                    p.id plan_id, p.name plan_name, p.code plan_code, p.billing_interval,
                    s.status subscription_status, s.ends_at current_period_ends_at,
                    pn.display_phone_number, pn.verified_name phone_verified_name, pn.quality_rating phone_quality_rating,
                    wa.meta_waba_id, mc.status meta_connection_status
                FROM businesses b
                LEFT JOIN business_users bu ON bu.business_id = b.id
                LEFT JOIN users u ON u.id = bu.user_id
                LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status IN ('active', 'trialing')
                LEFT JOIN plans p ON p.id = s.plan_id
                LEFT JOIN meta_connections mc ON mc.business_id = b.id AND mc.deleted_at IS NULL
                LEFT JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
                LEFT JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.deleted_at IS NULL AND pn.is_default = 1
                WHERE b.deleted_at IS NULL
                GROUP BY b.id, b.name, b.slug, b.timezone, b.status, b.created_at, p.id, p.name, p.code, p.billing_interval, s.status, s.ends_at, pn.display_phone_number, pn.verified_name, pn.quality_rating, wa.meta_waba_id, mc.status
                ORDER BY b.created_at DESC";
        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'timezone' => $row['timezone'],
            'status' => $row['status'],
            'ownerName' => $row['owner_name'],
            'ownerEmail' => $row['owner_email'],
            'userCount' => (int) $row['user_count'],
            'createdAt' => $row['created_at'],
            'plan' => $row['plan_id'] ? [
                'id' => $row['plan_id'],
                'name' => $row['plan_name'],
                'code' => $row['plan_code'],
                'status' => $row['subscription_status'],
                'billingInterval' => $row['billing_interval'],
                'currentPeriodEndsAt' => $row['current_period_ends_at'],
            ] : null,
            'whatsapp' => $row['display_phone_number'] ? [
                'phoneNumber' => $row['display_phone_number'],
                'verifiedName' => $row['phone_verified_name'],
                'qualityRating' => $row['phone_quality_rating'],
                'wabaId' => $row['meta_waba_id'],
                'connectionStatus' => $row['meta_connection_status'] ?? 'connected',
            ] : null,
        ], $this->db->query($sql)->fetchAll());
    }

    public function users(): array
    {
        $sql = "SELECT u.id, u.name, u.email, u.status, u.email_verified_at, u.last_login_at, u.created_at,
                    GROUP_CONCAT(DISTINCT COALESCE(b.name, 'Platform') ORDER BY b.name SEPARATOR ', ') workspaces,
                    MAX(CASE WHEN r.name = 'Super Admin' THEN 'Super Admin' ELSE r.name END) role_name
                FROM users u
                LEFT JOIN business_users bu ON bu.user_id = u.id
                LEFT JOIN businesses b ON b.id = bu.business_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.deleted_at IS NULL
                GROUP BY u.id, u.name, u.email, u.status, u.email_verified_at, u.last_login_at, u.created_at
                ORDER BY u.created_at DESC";
        return array_map(static fn (array $row) => [
            'id' => $row['id'], 'name' => $row['name'], 'email' => $row['email'], 'status' => $row['status'],
            'emailVerified' => $row['email_verified_at'] !== null, 'lastLoginAt' => $row['last_login_at'],
            'createdAt' => $row['created_at'], 'workspaces' => $row['workspaces'] ?: 'Platform',
            'roleName' => $row['role_name'] ?? 'Viewer',
        ], $this->db->query($sql)->fetchAll());
    }

    public function plans(): array
    {
        $plans = $this->db->query('SELECT id, name, code, description, price_minor, annual_price_minor, currency, billing_interval, status, is_public, sort_order, limits, created_at, updated_at FROM plans ORDER BY sort_order, created_at')->fetchAll();
        $featureRows = $this->db->query('SELECT plan_id, feature_key, value FROM plan_features ORDER BY plan_id, feature_key')->fetchAll();
        $features = [];
        foreach ($featureRows as $row) {
            $value = json_decode((string) $row['value'], true, 512, JSON_THROW_ON_ERROR);
            $features[$row['plan_id']][] = is_string($value) ? $value : (string) $row['feature_key'];
        }
        return array_map(fn (array $row) => $this->formatPlan($row, $features[$row['id']] ?? []), $plans);
    }

    public function createPlan(array $input, string $actorId): array
    {
        $plan = $this->validatePlan($input);
        $exists = $this->db->prepare('SELECT 1 FROM plans WHERE code = ? LIMIT 1');
        $exists->execute([$plan['code']]);
        if ($exists->fetchColumn()) {
            throw new HttpException(409, 'A plan with this code already exists.', 'plan_code_exists');
        }
        $id = Uuid::v4();
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('INSERT INTO plans (id, name, code, description, price_minor, annual_price_minor, currency, billing_interval, status, is_public, sort_order, limits, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
            $statement->execute([$id, $plan['name'], $plan['code'], $plan['description'], $plan['priceMinor'], $plan['annualPriceMinor'], $plan['currency'], $plan['billingInterval'], $plan['status'], $plan['isPublic'], $plan['sortOrder'], json_encode($plan['limits'], JSON_THROW_ON_ERROR)]);
            $this->replacePlanFeatures($id, $plan['features']);
            $this->audit->record(null, $actorId, 'admin.plan.created', 'plan', $id, ['code' => $plan['code']]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return $this->findPlan($id);
    }

    public function updatePlan(string $id, array $input, string $actorId): array
    {
        $plan = $this->validatePlan($input);
        $exists = $this->db->prepare('SELECT 1 FROM plans WHERE id = ? LIMIT 1');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            throw new HttpException(404, 'Plan not found.', 'not_found');
        }
        $duplicate = $this->db->prepare('SELECT 1 FROM plans WHERE code = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$plan['code'], $id]);
        if ($duplicate->fetchColumn()) {
            throw new HttpException(409, 'A plan with this code already exists.', 'plan_code_exists');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('UPDATE plans SET name = ?, code = ?, description = ?, price_minor = ?, annual_price_minor = ?, currency = ?, billing_interval = ?, status = ?, is_public = ?, sort_order = ?, limits = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
            $statement->execute([$plan['name'], $plan['code'], $plan['description'], $plan['priceMinor'], $plan['annualPriceMinor'], $plan['currency'], $plan['billingInterval'], $plan['status'], $plan['isPublic'], $plan['sortOrder'], json_encode($plan['limits'], JSON_THROW_ON_ERROR), $id]);
            $this->replacePlanFeatures($id, $plan['features']);
            $this->audit->record(null, $actorId, 'admin.plan.updated', 'plan', $id, ['code' => $plan['code'], 'status' => $plan['status']]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return $this->findPlan($id);
    }

    private function findPlan(string $id): array
    {
        $statement = $this->db->prepare('SELECT id, name, code, description, price_minor, annual_price_minor, currency, billing_interval, status, is_public, sort_order, limits, created_at, updated_at FROM plans WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            throw new HttpException(404, 'Plan not found.', 'not_found');
        }
        $features = $this->db->prepare('SELECT value FROM plan_features WHERE plan_id = ? ORDER BY feature_key');
        $features->execute([$id]);
        $labels = array_map(static fn (array $feature) => (string) json_decode((string) $feature['value'], true, 512, JSON_THROW_ON_ERROR), $features->fetchAll());
        return $this->formatPlan($row, $labels);
    }

    private function formatPlan(array $row, array $features): array
    {
        return [
            'id' => $row['id'], 'name' => $row['name'], 'code' => $row['code'], 'description' => $row['description'],
            'priceMinor' => $row['price_minor'] === null ? null : (int) $row['price_minor'], 'annualPriceMinor' => $row['annual_price_minor'] === null ? null : (int) $row['annual_price_minor'], 'currency' => $row['currency'],
            'billingInterval' => $row['billing_interval'], 'status' => $row['status'], 'isPublic' => (bool) $row['is_public'],
            'sortOrder' => (int) $row['sort_order'], 'limits' => json_decode((string) $row['limits'], true, 512, JSON_THROW_ON_ERROR),
            'features' => array_values($features), 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at'],
        ];
    }

    private function validatePlan(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'INR')));
        $billingInterval = (string) ($input['billingInterval'] ?? 'month');
        $status = (string) ($input['status'] ?? 'active');
        $priceMinor = ($input['priceMinor'] ?? null) === null || ($input['priceMinor'] ?? '') === '' ? null : filter_var($input['priceMinor'], FILTER_VALIDATE_INT);
        $annualPriceMinor = ($input['annualPriceMinor'] ?? null) === null || ($input['annualPriceMinor'] ?? '') === '' ? null : filter_var($input['annualPriceMinor'], FILTER_VALIDATE_INT);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !preg_match('/^[a-z][a-z0-9-]{1,79}$/', $code)) {
            throw new HttpException(422, 'Enter a plan name and a lowercase code using letters, numbers or hyphens.', 'validation_failed');
        }
        if (mb_strlen($description) > 500 || !preg_match('/^[A-Z]{3}$/', $currency) || !in_array($billingInterval, ['month', 'year', 'custom'], true) || !in_array($status, ['active', 'archived'], true)) {
            throw new HttpException(422, 'One or more plan details are invalid.', 'validation_failed');
        }
        if ($priceMinor === false || $annualPriceMinor === false || ($priceMinor !== null && ($priceMinor < 0 || $priceMinor > 100000000)) || ($annualPriceMinor !== null && ($annualPriceMinor < 0 || $annualPriceMinor > 1200000000))) {
            throw new HttpException(422, 'Enter a valid plan price.', 'validation_failed');
        }
        $limits = is_array($input['limits'] ?? null) ? $input['limits'] : [];
        $allowedLimits = ['phoneNumbers', 'teamMembers', 'contacts', 'monthlyRecipients'];
        $normalizedLimits = [];
        foreach ($allowedLimits as $key) {
            $value = $limits[$key] ?? null;
            if ($value === '' || $value === null) {
                $normalizedLimits[$key] = null;
                continue;
            }
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer === false || $integer < 0 || $integer > 1000000000) {
                throw new HttpException(422, 'Plan limits must be positive whole numbers or blank for custom.', 'validation_failed');
            }
            $normalizedLimits[$key] = $integer;
        }
        $features = array_values(array_unique(array_filter(array_map(static fn ($feature) => trim((string) $feature), is_array($input['features'] ?? null) ? $input['features'] : []))));
        if (count($features) > 50 || array_filter($features, static fn (string $feature) => mb_strlen($feature) > 160)) {
            throw new HttpException(422, 'Add no more than 50 concise plan features.', 'validation_failed');
        }
        return [
            'name' => $name, 'code' => $code, 'description' => $description, 'priceMinor' => $priceMinor, 'annualPriceMinor' => $annualPriceMinor,
            'currency' => $currency, 'billingInterval' => $billingInterval, 'status' => $status,
            'isPublic' => filter_var($input['isPublic'] ?? true, FILTER_VALIDATE_BOOL),
            'sortOrder' => max(0, min(10000, (int) ($input['sortOrder'] ?? 0))), 'limits' => $normalizedLimits, 'features' => $features,
        ];
    }

    private function replacePlanFeatures(string $planId, array $features): void
    {
        $this->db->prepare('DELETE FROM plan_features WHERE plan_id = ?')->execute([$planId]);
        $statement = $this->db->prepare('INSERT INTO plan_features (plan_id, feature_key, value) VALUES (?, ?, ?)');
        foreach ($features as $index => $feature) {
            $statement->execute([$planId, sprintf('feature_%02d', $index + 1), json_encode($feature, JSON_THROW_ON_ERROR)]);
        }
    }

    public function createBusiness(array $input, string $actorId): array
    {
        $name = trim((string) ($input['businessName'] ?? ''));
        $ownerName = trim((string) ($input['ownerName'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['ownerEmail'] ?? '')));
        $password = (string) ($input['ownerPassword'] ?? '');
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $planId = trim((string) ($input['planId'] ?? ''));

        if ($name === '' || mb_strlen($name) > 190 || $ownerName === '' || mb_strlen($ownerName) > 190) {
            throw new HttpException(422, 'Business and owner names are required.', 'validation_failed');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Enter a valid owner email address.', 'validation_failed');
        }
        if (strlen($password) < 12) {
            throw new HttpException(422, 'The initial owner password must contain at least 12 characters.', 'validation_failed');
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true) && $timezone !== 'UTC') {
            throw new HttpException(422, 'Select a valid timezone.', 'validation_failed');
        }
        $exists = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new HttpException(409, 'A user with this email already exists.', 'email_exists');
        }

        // Resolve plan
        if ($planId === '') {
            $planStmt = $this->db->query("SELECT id, limits FROM plans WHERE code = 'launch' AND status = 'active' LIMIT 1");
            $defaultPlan = $planStmt->fetch();
            $planId = $defaultPlan['id'] ?? null;
            $limits = $defaultPlan['limits'] ?? '{}';
        } else {
            $planStmt = $this->db->prepare("SELECT id, limits FROM plans WHERE id = ? LIMIT 1");
            $planStmt->execute([$planId]);
            $chosenPlan = $planStmt->fetch();
            $limits = $chosenPlan['limits'] ?? '{}';
        }

        $businessId = Uuid::v4();
        $userId = Uuid::v4();
        $slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-')) ?: 'business';
        $slug = substr($slugBase, 0, 170) . '-' . substr($businessId, 0, 8);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO businesses (id, name, slug, timezone, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$businessId, $name, $slug, $timezone]);
            $this->db->prepare("INSERT INTO users (id, name, email, password_hash, email_verified_at, status, created_at, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$userId, $ownerName, $email, password_hash($password, PASSWORD_ARGON2ID)]);
            $this->db->prepare("INSERT INTO business_users (business_id, user_id, status, is_primary, joined_at, created_at, updated_at) VALUES (?, ?, 'active', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$businessId, $userId]);
            $role = $this->db->prepare("INSERT INTO user_roles (business_id, user_id, role_id, assigned_by, created_at) SELECT ?, ?, id, ?, UTC_TIMESTAMP() FROM roles WHERE name = 'Business Owner' AND scope = 'business'");
            $role->execute([$businessId, $userId, $actorId]);
            if ($role->rowCount() !== 1) {
                throw new \RuntimeException('Business Owner role has not been seeded.');
            }

            // Assign plan subscription
            if ($planId) {
                $subId = Uuid::v4();
                $now = gmdate('Y-m-d H:i:s');
                $ends = gmdate('Y-m-d H:i:s', time() + (30 * 86400));
                $this->db->prepare("INSERT INTO subscriptions (id, business_id, plan_id, provider, status, starts_at, ends_at, created_at, updated_at) VALUES (?, ?, ?, 'manual', 'active', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([
                    $subId, $businessId, $planId, $now, $ends
                ]);
            }

            $this->audit->record($businessId, $actorId, 'admin.business.created', 'business', $businessId, ['owner_user_id' => $userId, 'owner_email' => $email, 'plan_id' => $planId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return ['id' => $businessId, 'name' => $name, 'slug' => $slug, 'timezone' => $timezone, 'status' => 'active', 'ownerName' => $ownerName, 'ownerEmail' => $email, 'userCount' => 1, 'createdAt' => gmdate('Y-m-d H:i:s')];
    }

    public function assignBusinessPlan(string $businessId, array $input, string $actorId): array
    {
        $planId = trim((string) ($input['planId'] ?? ''));
        $billingInterval = isset($input['billingInterval']) && in_array($input['billingInterval'], ['month', 'year'], true) ? (string) $input['billingInterval'] : 'month';
        $status = isset($input['status']) && in_array($input['status'], ['active', 'trialing', 'past_due', 'cancelled'], true) ? (string) $input['status'] : 'active';
        $expiresDays = isset($input['expiresDays']) && (int) $input['expiresDays'] > 0 ? (int) $input['expiresDays'] : ($billingInterval === 'year' ? 365 : 30);

        $biz = $this->db->prepare('SELECT id, name FROM businesses WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $biz->execute([$businessId]);
        if (!$biz->fetch()) {
            throw new HttpException(404, 'Business not found.', 'not_found');
        }

        $planStmt = $this->db->prepare('SELECT id, name, code, limits FROM plans WHERE id = ? LIMIT 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            throw new HttpException(404, 'Plan not found.', 'not_found');
        }

        $startsAt = gmdate('Y-m-d H:i:s');
        $endsAt = gmdate('Y-m-d H:i:s', time() + ($expiresDays * 86400));
        $subscriptionId = Uuid::v4();

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE subscriptions SET status = 'cancelled', updated_at = UTC_TIMESTAMP() WHERE business_id = ? AND status IN ('active', 'trialing')")->execute([$businessId]);
            $this->db->prepare("INSERT INTO subscriptions (id, business_id, plan_id, provider, status, starts_at, ends_at, created_at, updated_at) VALUES (?, ?, ?, 'manual', ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([
                $subscriptionId, $businessId, $planId, $status, $startsAt, $endsAt
            ]);
            $this->audit->record($businessId, $actorId, 'admin.subscription.assigned', 'subscription', $subscriptionId, [
                'plan_id' => $planId, 'plan_name' => $plan['name'], 'billing_interval' => $billingInterval, 'current_period_ends_at' => $endsAt
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'subscriptionId' => $subscriptionId,
            'businessId' => $businessId,
            'planId' => $planId,
            'planName' => $plan['name'],
            'status' => $status,
            'billingInterval' => $billingInterval,
            'currentPeriodEndsAt' => $endsAt,
        ];
    }

    public function updateBusinessStatus(string $businessId, string $status, string $actorId): array
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new HttpException(422, 'Status must be active or suspended.', 'validation_failed');
        }
        $statement = $this->db->prepare("UPDATE businesses SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL");
        $statement->execute([$status, $businessId]);
        if ($statement->rowCount() !== 1) {
            throw new HttpException(404, 'Business not found.', 'not_found');
        }
        $this->audit->record($businessId, $actorId, 'admin.business.status_changed', 'business', $businessId, ['status' => $status]);
        return ['id' => $businessId, 'status' => $status];
    }

    public function metaConnections(): array
    {
        $sql = "SELECT mc.id, mc.business_id, mc.meta_business_id, mc.app_id, mc.status connection_status,
                    mc.connected_at, mc.last_synced_at, mc.last_tested_at, mc.last_error_message,
                    b.name business_name, b.slug business_slug,
                    wa.meta_waba_id, wa.name waba_name, wa.currency, wa.review_status waba_review_status,
                    pn.meta_phone_number_id, pn.display_phone_number, pn.verified_name, pn.quality_rating,
                    pn.name_status, pn.is_default,
                    ws.status webhook_status
                FROM meta_connections mc
                JOIN businesses b ON b.id = mc.business_id
                LEFT JOIN waba_accounts wa ON wa.meta_connection_id = mc.id
                LEFT JOIN whatsapp_phone_numbers pn ON pn.waba_account_id = wa.id AND pn.deleted_at IS NULL
                LEFT JOIN webhook_subscriptions ws ON ws.waba_account_id = wa.id
                WHERE mc.deleted_at IS NULL AND b.deleted_at IS NULL
                ORDER BY mc.connected_at DESC";
        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'businessId' => $row['business_id'],
            'businessName' => $row['business_name'],
            'businessSlug' => $row['business_slug'],
            'connectionStatus' => $row['connection_status'],
            'metaBusinessId' => $row['meta_business_id'],
            'wabaId' => $row['meta_waba_id'],
            'wabaName' => $row['waba_name'],
            'wabaReviewStatus' => $row['waba_review_status'],
            'phoneNumberId' => $row['meta_phone_number_id'],
            'displayPhoneNumber' => $row['display_phone_number'],
            'verifiedName' => $row['verified_name'],
            'qualityRating' => $row['quality_rating'],
            'nameStatus' => $row['name_status'],
            'webhookStatus' => $row['webhook_status'] ?? 'pending',
            'lastErrorMessage' => $row['last_error_message'],
            'connectedAt' => $row['connected_at'],
            'lastSyncedAt' => $row['last_synced_at'],
        ], $this->db->query($sql)->fetchAll());
    }

    public function queueHealth(): array
    {
        return $this->queue->health();
    }

    public function failedJobs(int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT fj.id, fj.queue_job_id, fj.business_id, fj.queue, fj.job_type, fj.error_type, fj.error_message, fj.failed_at, fj.retried_at, b.name business_name
            FROM failed_jobs fj
            LEFT JOIN businesses b ON b.id = fj.business_id
            ORDER BY fj.failed_at DESC LIMIT ?");
        $stmt->bindValue(1, min(100, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function retryJob(int $id): bool
    {
        return $this->queue->retry($id);
    }

    public function retryAllFailed(): int
    {
        return $this->queue->retryAll();
    }

    public function clearStaleLocks(): int
    {
        return $this->queue->reclaimStaleLocks();
    }

    public function updateUserStatus(string $userId, string $status, string $actorId): array
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new HttpException(422, 'Status must be active or suspended.', 'validation_failed');
        }
        $stmt = $this->db->prepare("UPDATE users SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$status, $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException(404, 'User not found.', 'not_found');
        }
        if ($status === 'suspended') {
            $this->db->prepare("UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL")->execute([$userId]);
        }
        $this->audit->record(null, $actorId, 'admin.user.status_changed', 'user', $userId, ['status' => $status]);
        return ['id' => $userId, 'status' => $status];
    }

    public function resetUserPassword(string $userId, string $newPassword, string $actorId): array
    {
        if (strlen($newPassword) < 12) {
            throw new HttpException(422, 'Password must be at least 12 characters.', 'validation_failed');
        }
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$hash, $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException(404, 'User not found.', 'not_found');
        }
        $this->db->prepare("UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL")->execute([$userId]);
        $this->audit->record(null, $actorId, 'admin.user.password_reset', 'user', $userId, []);
        return ['message' => 'Password reset successfully and sessions revoked.'];
    }

    public function revokeUserSessions(string $userId, string $actorId): array
    {
        $stmt = $this->db->prepare("UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL");
        $stmt->execute([$userId]);
        $count = $stmt->rowCount();
        $this->audit->record(null, $actorId, 'admin.user.sessions_revoked', 'user', $userId, ['revoked_sessions' => $count]);
        return ['revoked' => $count];
    }

    public function auditLogs(array $filters = []): array
    {
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $action = trim((string) ($filters['action'] ?? ''));
        $businessId = trim((string) ($filters['businessId'] ?? ''));

        $where = [];
        $params = [];

        if ($action !== '') {
            $where[] = 'al.action LIKE ?';
            $params[] = '%' . $action . '%';
        }
        if ($businessId !== '') {
            $where[] = 'al.business_id = ?';
            $params[] = $businessId;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT al.id, al.business_id, al.user_id, al.action, al.subject_type, al.subject_id,
                    al.metadata, al.created_at,
                    b.name business_name, u.name user_name, u.email user_email
                FROM audit_logs al
                LEFT JOIN businesses b ON b.id = al.business_id
                LEFT JOIN users u ON u.id = al.user_id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return array_map(static function (array $r) {
            return [
                'id' => $r['id'],
                'businessId' => $r['business_id'],
                'businessName' => $r['business_name'],
                'userId' => $r['user_id'],
                'userName' => $r['user_name'],
                'userEmail' => $r['user_email'],
                'action' => $r['action'],
                'subjectType' => $r['subject_type'],
                'subjectId' => $r['subject_id'],
                'metadata' => json_decode((string) $r['metadata'], true) ?: [],
                'createdAt' => $r['created_at'],
            ];
        }, $rows);
    }
}