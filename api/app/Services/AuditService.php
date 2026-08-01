<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use WhatstheUp\Support\Uuid;

final class AuditService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(?string $businessId, ?string $userId, string $action, string $subjectType, ?string $subjectId, array $metadata = []): void
    {
        $statement = $this->db->prepare('INSERT INTO audit_logs (id, business_id, user_id, action, subject_type, subject_id, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
        $statement->execute([Uuid::v4(), $businessId, $userId, $action, $subjectType, $subjectId, json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }
}
