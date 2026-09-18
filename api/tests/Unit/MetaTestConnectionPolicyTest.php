<?php

declare(strict_types=1);

namespace WhatstheUp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatstheUp\Services\MetaTestConnectionPolicy;
use WhatstheUp\Support\HttpException;

final class MetaTestConnectionPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('META_TEST_CONNECTION_ENABLED=true');
        putenv('META_TEST_BUSINESS_ID=workspace-one');
        putenv('META_TEST_WABA_ID=1376690200553798');
        putenv('META_TEST_PHONE_NUMBER_ID=1349858381536228');
    }

    protected function tearDown(): void
    {
        foreach (['META_TEST_CONNECTION_ENABLED','META_TEST_BUSINESS_ID','META_TEST_WABA_ID','META_TEST_PHONE_NUMBER_ID'] as $key) putenv($key);
    }

    public function testDisabledByDefault(): void
    {
        putenv('META_TEST_CONNECTION_ENABLED');
        self::assertNull(MetaTestConnectionPolicy::configuration('workspace-one'));
    }

    public function testOtherTenantCannotUseTestConnection(): void
    {
        self::assertNull(MetaTestConnectionPolicy::configuration('workspace-two'));
        $this->expectException(HttpException::class);
        MetaTestConnectionPolicy::validate('workspace-two', []);
    }

    public function testExplicitConfirmationRequired(): void
    {
        $this->expectException(HttpException::class);
        MetaTestConnectionPolicy::validate('workspace-one', ['accessToken' => str_repeat('a', 30)]);
    }

    public function testTokenWhitespaceRejected(): void
    {
        $this->expectException(HttpException::class);
        MetaTestConnectionPolicy::validate('workspace-one', ['confirmReplacement' => true, 'accessToken' => str_repeat('a ', 30)]);
    }

    public function testClientCannotOverrideAllowlistedAssets(): void
    {
        $result = MetaTestConnectionPolicy::validate('workspace-one', ['confirmReplacement' => true, 'accessToken' => str_repeat('a', 30), 'wabaId' => '999999', 'phoneNumberId' => '888888']);
        self::assertSame('1376690200553798', $result['wabaId']);
        self::assertSame('1349858381536228', $result['phoneNumberId']);
    }
}
