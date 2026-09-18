<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;

final class MetaTestConnectionPolicy
{
    public static function configuration(string $businessId): ?array
    {
        $allowedBusiness = Env::get('META_TEST_BUSINESS_ID', '') ?? '';
        $wabaId = Env::get('META_TEST_WABA_ID', '') ?? '';
        $phoneId = Env::get('META_TEST_PHONE_NUMBER_ID', '') ?? '';
        if (!Env::bool('META_TEST_CONNECTION_ENABLED') || $allowedBusiness === '' || $businessId !== $allowedBusiness
            || !preg_match('/^\d{5,30}$/', $wabaId) || !preg_match('/^\d{5,30}$/', $phoneId)) {
            return null;
        }
        return ['wabaId' => $wabaId, 'phoneNumberId' => $phoneId];
    }

    public static function validate(string $businessId, array $input): array
    {
        $config = self::configuration($businessId);
        if ($config === null) {
            throw new HttpException(403, 'Test connection is not enabled for this workspace.', 'meta_test_disabled');
        }
        if (($input['confirmReplacement'] ?? false) !== true) {
            throw new HttpException(422, 'Confirm replacement of the saved connection before continuing.', 'meta_test_confirmation_required');
        }
        $token = trim((string) ($input['accessToken'] ?? ''));
        if (strlen($token) < 20 || strlen($token) > 8192 || preg_match('/\s/', $token)) {
            throw new HttpException(422, 'Enter a valid Meta test access token.', 'meta_test_token_invalid');
        }
        return $config + ['accessToken' => $token];
    }
}
