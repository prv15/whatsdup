<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\RateLimiter;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        RateLimiter::reset();
    }

    public function testThrottlesAfterReachingLimit(): void
    {
        $limiter = new RateLimiter();
        $key = 'test_phone_1';

        self::assertTrue($limiter->throttle($key, 3, 1));
        self::assertTrue($limiter->throttle($key, 3, 1));
        self::assertTrue($limiter->throttle($key, 3, 1));
        // 4th request within 1-second window should be throttled
        self::assertFalse($limiter->throttle($key, 3, 1));
    }

    public function testBackoffBlocksThrottling(): void
    {
        $limiter = new RateLimiter();
        $key = 'test_phone_2';

        self::assertFalse($limiter->isBackingOff($key));
        self::assertTrue($limiter->throttle($key, 10, 1));

        $limiter->recordBackoff($key, 5);
        self::assertTrue($limiter->isBackingOff($key));
        self::assertFalse($limiter->throttle($key, 10, 1));

        // Different key should not be affected by backoff
        self::assertFalse($limiter->isBackingOff('other_key'));
        self::assertTrue($limiter->throttle('other_key', 10, 1));
    }

    public function testPacingEnforcesMicroIntervals(): void
    {
        $limiter = new RateLimiter();
        $key = 'test_phone_3';

        $start = microtime(true);
        $limiter->pace($key, 100); // 100 mps => 10ms interval
        $limiter->pace($key, 100);
        $elapsed = microtime(true) - $start;

        self::assertGreaterThanOrEqual(0.008, $elapsed);
    }
}
