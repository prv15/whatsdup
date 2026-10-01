<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Support\Uuid;

final class QueueService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function enqueue(
        string $queue,
        string $jobType,
        array $payload,
        ?string $businessId = null,
        ?string $idempotencyKey = null,
        int $priority = 100,
        int $delaySeconds = 0
    ): string {
        $idempotency = $idempotencyKey ?? (microtime(true) . ':' . Uuid::v4());
        $traceId = Uuid::v4();
        $availableAt = $delaySeconds > 0
            ? "DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$delaySeconds} SECOND)"
            : "UTC_TIMESTAMP()";

        $stmt = $this->db->prepare("
            INSERT INTO queue_jobs (
                business_id, queue, job_type, payload, idempotency_key, trace_id,
                status, priority, attempts, max_attempts, available_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                'ready', ?, 0, 5, {$availableAt}, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP()
        ");

        $stmt->execute([
            $businessId,
            $queue,
            $jobType,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $idempotency,
            $traceId,
            $priority,
        ]);

        return $traceId;
    }

    /**
     * Atomically claim up to $limit ready jobs for the given queue(s).
     * Prevents race conditions between concurrent worker processes.
     */
    public function claim(string|array $queues = 'campaigns', int $limit = 10, int $lockTimeoutMinutes = 10): array
    {
        $queueList = is_array($queues) ? $queues : array_map('trim', explode(',', $queues));
        if (empty($queueList)) {
            $queueList = ['campaigns', 'default'];
        }

        $placeholders = implode(',', array_fill(0, count($queueList), '?'));
        $lockToken = Uuid::v4();
        $claimed = [];

        $this->db->beginTransaction();
        try {
            // First attempt with SKIP LOCKED (MySQL 8.0+)
            $selectSql = "
                SELECT id, business_id, queue, job_type, payload, attempts, max_attempts
                FROM queue_jobs
                WHERE queue IN ({$placeholders})
                  AND status = 'ready'
                  AND available_at <= UTC_TIMESTAMP()
                ORDER BY priority ASC, id ASC
                LIMIT {$limit}
                FOR UPDATE SKIP LOCKED
            ";

            $stmt = $this->db->prepare($selectSql);
            $stmt->execute($queueList);
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                $updateStmt = $this->db->prepare("
                    UPDATE queue_jobs
                    SET status = 'reserved',
                        attempts = attempts + 1,
                        locked_at = UTC_TIMESTAMP(),
                        lock_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$lockTimeoutMinutes} MINUTE),
                        lock_token = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id IN ({$idPlaceholders})
                ");
                $updateStmt->execute(array_merge([$lockToken], $ids));
                $claimed = $rows;
            }

            $this->db->commit();
        } catch (Throwable) {
            $this->db->rollBack();
            // Fallback to atomic row-by-row reservation if FOR UPDATE SKIP LOCKED is unavailable
            $claimed = $this->claimFallback($queueList, $limit, $lockTimeoutMinutes);
        }

        return $claimed;
    }

    public function markCompleted(int|string $jobId): void
    {
        $stmt = $this->db->prepare("
            UPDATE queue_jobs
            SET status = 'completed',
                completed_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }

    public function markFailed(int|string $jobId, string $errorCode, string $errorMessage, int $retryDelayMinutes = 5): void
    {
        $stmt = $this->db->prepare("SELECT attempts, max_attempts FROM queue_jobs WHERE id = ? LIMIT 1");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();

        $attempts = (int) ($job['attempts'] ?? 1);
        $maxAttempts = (int) ($job['max_attempts'] ?? 5);
        $isFinal = $attempts >= $maxAttempts;

        $status = $isFinal ? 'failed' : 'ready';
        $truncatedError = mb_substr($errorMessage, 0, 500);

        $update = $this->db->prepare("
            UPDATE queue_jobs
            SET status = ?,
                last_error_code = ?,
                last_error = ?,
                available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$retryDelayMinutes} MINUTE),
                locked_at = NULL,
                lock_expires_at = NULL,
                lock_token = NULL,
                updated_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");
        $update->execute([$status, $errorCode, $truncatedError, $jobId]);

        if ($isFinal) {
            try {
                $this->db->prepare("
                    INSERT INTO failed_jobs (
                        original_job_id, business_id, queue, job_type, payload,
                        idempotency_key, attempts, error_code, error_message, failed_at
                    )
                    SELECT id, business_id, queue, job_type, payload,
                           idempotency_key, attempts, ?, ?, UTC_TIMESTAMP()
                    FROM queue_jobs
                    WHERE id = ?
                ")->execute([$errorCode, $truncatedError, $jobId]);
            } catch (Throwable) {}
        }
    }

    public function reclaimStaleLocks(int $timeoutMinutes = 10): int
    {
        $stmt = $this->db->prepare("
            UPDATE queue_jobs
            SET status = 'ready',
                locked_at = NULL,
                lock_expires_at = NULL,
                lock_token = NULL,
                updated_at = UTC_TIMESTAMP()
            WHERE status = 'reserved'
              AND (lock_expires_at IS NULL OR lock_expires_at <= UTC_TIMESTAMP())
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function retry(int|string $failedJobId): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM failed_jobs WHERE id = ? LIMIT 1");
        $stmt->execute([$failedJobId]);
        $failed = $stmt->fetch();
        if (!$failed) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->enqueue(
                (string) $failed['queue'],
                (string) $failed['job_type'],
                json_decode((string) $failed['payload'], true) ?: [],
                $failed['business_id'] !== null ? (string) $failed['business_id'] : null,
                'retry:' . Uuid::v4()
            );

            $this->db->prepare("UPDATE failed_jobs SET retried_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$failedJobId]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function retryAll(): int
    {
        $stmt = $this->db->query("SELECT id FROM failed_jobs WHERE retried_at IS NULL ORDER BY id ASC");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $retried = 0;
        foreach ($ids as $id) {
            if ($this->retry($id)) {
                $retried++;
            }
        }
        return $retried;
    }

    public function health(): array
    {
        $metrics = [
            'ready' => 0,
            'reserved' => 0,
            'completed' => 0,
            'failed' => 0,
            'permanent_failed' => 0,
            'stale_reserved' => 0,
            'oldest_ready_seconds' => null,
        ];

        try {
            $statusCounts = $this->db->query("
                SELECT status, COUNT(*) as cnt
                FROM queue_jobs
                GROUP BY status
            ")->fetchAll();

            foreach ($statusCounts as $row) {
                if (isset($metrics[$row['status']])) {
                    $metrics[$row['status']] = (int) $row['cnt'];
                }
            }

            $stale = $this->db->query("
                SELECT COUNT(*)
                FROM queue_jobs
                WHERE status = 'reserved'
                  AND lock_expires_at <= UTC_TIMESTAMP()
            ")->fetchColumn();
            $metrics['stale_reserved'] = (int) $stale;

            $failedTotal = $this->db->query("
                SELECT COUNT(*)
                FROM failed_jobs
                WHERE retried_at IS NULL
            ")->fetchColumn();
            $metrics['permanent_failed'] = (int) $failedTotal;

            $oldest = $this->db->query("
                SELECT TIMESTAMPDIFF(SECOND, available_at, UTC_TIMESTAMP())
                FROM queue_jobs
                WHERE status = 'ready'
                  AND available_at <= UTC_TIMESTAMP()
                ORDER BY available_at ASC
                LIMIT 1
            ")->fetchColumn();
            $metrics['oldest_ready_seconds'] = $oldest !== false ? (int) $oldest : null;
        } catch (Throwable) {}

        return $metrics;
    }

    private function claimFallback(array $queues, int $limit, int $lockTimeoutMinutes): array
    {
        $placeholders = implode(',', array_fill(0, count($queues), '?'));
        $stmt = $this->db->prepare("
            SELECT id, business_id, queue, job_type, payload, attempts, max_attempts
            FROM queue_jobs
            WHERE queue IN ({$placeholders})
              AND status = 'ready'
              AND available_at <= UTC_TIMESTAMP()
            ORDER BY priority ASC, id ASC
            LIMIT {$limit}
        ");
        $stmt->execute($queues);
        $candidates = $stmt->fetchAll();

        $claimed = [];
        $update = $this->db->prepare("
            UPDATE queue_jobs
            SET status = 'reserved',
                attempts = attempts + 1,
                locked_at = UTC_TIMESTAMP(),
                lock_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$lockTimeoutMinutes} MINUTE),
                lock_token = ?,
                updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND status = 'ready'
        ");

        foreach ($candidates as $candidate) {
            $token = Uuid::v4();
            $update->execute([$token, $candidate['id']]);
            if ($update->rowCount() === 1) {
                $claimed[] = $candidate;
            }
        }

        return $claimed;
    }
}
