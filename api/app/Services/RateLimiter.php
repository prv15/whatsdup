<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PDO;
use Throwable;
use WhatstheUp\Support\Env;

final class RateLimiter
{
    private static array $inMemoryTimestamps = [];
    private static array $backoffs = [];
    private ?object $redisClient = null;
    private bool $redisAttempted = false;

    public function __construct(private readonly ?PDO $db = null)
    {
    }

    /**
     * Microsecond pacing to ensure smooth message dispatch and avoid Meta throughput bursts.
     */
    public function pace(string $key, int $messagesPerSecond = 50): void
    {
        if ($messagesPerSecond <= 0) {
            return;
        }

        $minIntervalMicroseconds = (int) (1_000_000 / $messagesPerSecond);
        $nowMicroseconds = (int) (microtime(true) * 1_000_000);

        $lastCall = self::$inMemoryTimestamps[$key] ?? 0;
        $elapsed = $nowMicroseconds - $lastCall;

        if ($lastCall > 0 && $elapsed < $minIntervalMicroseconds) {
            $sleepTime = $minIntervalMicroseconds - $elapsed;
            usleep($sleepTime);
            self::$inMemoryTimestamps[$key] = (int) (microtime(true) * 1_000_000);
        } else {
            self::$inMemoryTimestamps[$key] = $nowMicroseconds;
        }
    }

    /**
     * Check rate limit and consume a token. Returns true if allowed, false if limit exceeded.
     */
    public function throttle(string $key, int $limit, int $windowSeconds = 1): bool
    {
        if ($this->isBackingOff($key)) {
            return false;
        }

        $redis = $this->getRedis();
        if ($redis !== null) {
            try {
                $redisKey = "rate_limit:{$key}";
                $current = (int) $redis->incr($redisKey);
                if ($current === 1) {
                    $redis->expire($redisKey, max(1, $windowSeconds));
                }
                return $current <= $limit;
            } catch (Throwable) {
                // Graceful fallback to DB / in-memory
            }
        }

        if ($this->db !== null) {
            try {
                return $this->throttleDatabase($key, $limit, $windowSeconds);
            } catch (Throwable) {
                // Graceful fallback to in-memory
            }
        }

        return $this->throttleInMemory($key, $limit, $windowSeconds);
    }

    /**
     * Record a backoff period (e.g. after receiving HTTP 429 from Meta).
     */
    public function recordBackoff(string $key, int $seconds): void
    {
        $expiry = microtime(true) + max(1, $seconds);
        self::$backoffs[$key] = $expiry;

        $redis = $this->getRedis();
        if ($redis !== null) {
            try {
                $redis->setex("backoff:{$key}", max(1, $seconds), '1');
            } catch (Throwable) {}
        }
    }

    /**
     * Check if a key is currently in backoff.
     */
    public function isBackingOff(string $key): bool
    {
        if (isset(self::$backoffs[$key])) {
            if (microtime(true) < self::$backoffs[$key]) {
                return true;
            }
            unset(self::$backoffs[$key]);
        }

        $redis = $this->getRedis();
        if ($redis !== null) {
            try {
                return (bool) $redis->exists("backoff:{$key}");
            } catch (Throwable) {}
        }

        return false;
    }

    /**
     * Clear all state (useful in tests).
     */
    public static function reset(): void
    {
        self::$inMemoryTimestamps = [];
        self::$backoffs = [];
    }

    private function throttleDatabase(string $key, int $limit, int $windowSeconds): bool
    {
        $now = microtime(true);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT tokens, last_refill FROM rate_limits WHERE rate_key = ? FOR UPDATE');
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $this->db->prepare('INSERT INTO rate_limits (rate_key, tokens, last_refill, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
                $insert->execute([$key, $limit - 1, $now]);
                $this->db->commit();
                return true;
            }

            $currentTokens = (float) $row['tokens'];
            $lastRefill = (float) $row['last_refill'];
            $timePassed = max(0.0, $now - $lastRefill);

            // Refill tokens according to elapsed time
            $refillRate = (float) $limit / (float) max(1, $windowSeconds);
            $newTokens = min((float) $limit, $currentTokens + ($timePassed * $refillRate));

            if ($newTokens >= 1.0) {
                $newTokens -= 1.0;
                $update = $this->db->prepare('UPDATE rate_limits SET tokens = ?, last_refill = ?, updated_at = UTC_TIMESTAMP() WHERE rate_key = ?');
                $update->execute([$newTokens, $now, $key]);
                $this->db->commit();
                return true;
            }

            $this->db->commit();
            return false;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function throttleInMemory(string $key, int $limit, int $windowSeconds): bool
    {
        $now = microtime(true);
        if (!isset(self::$inMemoryTimestamps[$key . '_history'])) {
            self::$inMemoryTimestamps[$key . '_history'] = [];
        }

        $cutoff = $now - $windowSeconds;
        self::$inMemoryTimestamps[$key . '_history'] = array_values(
            array_filter(self::$inMemoryTimestamps[$key . '_history'], static fn ($ts) => $ts > $cutoff)
        );

        if (count(self::$inMemoryTimestamps[$key . '_history']) < $limit) {
            self::$inMemoryTimestamps[$key . '_history'][] = $now;
            return true;
        }

        return false;
    }

    private function getRedis(): ?object
    {
        if ($this->redisAttempted) {
            return $this->redisClient;
        }

        $this->redisAttempted = true;
        $host = Env::get('REDIS_HOST', '');
        if ($host === '' || !class_exists('Redis')) {
            return null;
        }

        try {
            $redis = new \Redis();
            $port = (int) Env::get('REDIS_PORT', '6379');
            $timeout = 1.0;
            if ($redis->connect($host, $port, $timeout)) {
                $auth = Env::get('REDIS_PASSWORD', '');
                if ($auth !== '') {
                    $redis->auth($auth);
                }
                $this->redisClient = $redis;
            }
        } catch (Throwable) {
            $this->redisClient = null;
        }

        return $this->redisClient;
    }
}
