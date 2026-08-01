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
