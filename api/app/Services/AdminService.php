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
    public function __construct(private readonly PDO $db, private readonly AuditService $audit)
    {
    }

    public function dashboard(): array
    {
        return [
            'businesses' => (int) $this->db->query("SELECT COUNT(*) FROM businesses WHERE deleted_at IS NULL")->fetchColumn(),
            'activeBusinesses' => (int) $this->db->query("SELECT COUNT(*) FROM businesses WHERE status = 'active' AND deleted_at IS NULL")->fetchColumn(),
            'users' => (int) $this->db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn(),
            'activeSessions' => (int) $this->db->query("SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()")->fetchColumn(),
            'queuedJobs' => (int) $this->db->query("SELECT COUNT(*) FROM queue_jobs WHERE status IN ('ready', 'reserved')")->fetchColumn(),
            'failedJobs' => (int) $this->db->query("SELECT COUNT(*) FROM failed_jobs WHERE retried_at IS NULL")->fetchColumn(),
        ];
    }

    public function businesses(): array
    {
        $sql = "SELECT b.id, b.name, b.slug, b.timezone, b.status, b.created_at,
                    COUNT(DISTINCT bu.user_id) user_count,
                    MAX(CASE WHEN bu.is_primary = TRUE THEN u.name END) owner_name,
                    MAX(CASE WHEN bu.is_primary = TRUE THEN u.email END) owner_email
                FROM businesses b
                LEFT JOIN business_users bu ON bu.business_id = b.id
                LEFT JOIN users u ON u.id = bu.user_id
                WHERE b.deleted_at IS NULL
                GROUP BY b.id, b.name, b.slug, b.timezone, b.status, b.created_at
                ORDER BY b.created_at DESC";
        return array_map(static fn (array $row) => [
            'id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug'], 'timezone' => $row['timezone'],
            'status' => $row['status'], 'ownerName' => $row['owner_name'], 'ownerEmail' => $row['owner_email'],
            'userCount' => (int) $row['user_count'], 'createdAt' => $row['created_at'],
        ], $this->db->query($sql)->fetchAll());
    }

    public function users(): array
    {
        $sql = "SELECT u.id, u.name, u.email, u.status, u.email_verified_at, u.last_login_at, u.created_at,
                    GROUP_CONCAT(DISTINCT COALESCE(b.name, 'Platform') ORDER BY b.name SEPARATOR ', ') workspaces
                FROM users u
                LEFT JOIN business_users bu ON bu.user_id = u.id
                LEFT JOIN businesses b ON b.id = bu.business_id
                WHERE u.deleted_at IS NULL
                GROUP BY u.id, u.name, u.email, u.status, u.email_verified_at, u.last_login_at, u.created_at
                ORDER BY u.created_at DESC";
        return array_map(static fn (array $row) => [
            'id' => $row['id'], 'name' => $row['name'], 'email' => $row['email'], 'status' => $row['status'],
            'emailVerified' => $row['email_verified_at'] !== null, 'lastLoginAt' => $row['last_login_at'],
            'createdAt' => $row['created_at'], 'workspaces' => $row['workspaces'] ?: 'Platform',
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
            $this->audit->record($businessId, $actorId, 'admin.business.created', 'business', $businessId, ['owner_user_id' => $userId, 'owner_email' => $email]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return ['id' => $businessId, 'name' => $name, 'slug' => $slug, 'timezone' => $timezone, 'status' => 'active', 'ownerName' => $ownerName, 'ownerEmail' => $email, 'userCount' => 1, 'createdAt' => gmdate('Y-m-d H:i:s')];
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
}
