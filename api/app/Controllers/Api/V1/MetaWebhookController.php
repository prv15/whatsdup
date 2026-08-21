<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;
use WhatstheUp\Support\Response;
use WhatstheUp\Services\MetaWebhookService;

final class MetaWebhookController
{
    public function __construct(private readonly MetaWebhookService $webhooks)
    {
    }

    public function verify(Request $request): never
    {
        $mode = (string) ($request->query['hub_mode'] ?? $request->query['hub.mode'] ?? '');
        $token = (string) ($request->query['hub_verify_token'] ?? $request->query['hub.verify_token'] ?? '');
        $challenge = (string) ($request->query['hub_challenge'] ?? $request->query['hub.challenge'] ?? '');
        $expected = Env::get('META_WEBHOOK_VERIFY_TOKEN', '') ?? '';
        if ($mode !== 'subscribe' || $challenge === '' || $expected === '' || !hash_equals($expected, $token)) {
            throw new HttpException(403, 'Webhook verification failed.', 'webhook_verification_failed');
        }
        Response::text($challenge);
    }

    public function receive(Request $request): array
    {
        $secret = Env::get('META_APP_SECRET', '') ?? '';
        $signature = $request->headers['x-hub-signature-256'] ?? '';
        if ($secret === '' || $signature === '') {
            throw new HttpException(403, 'Webhook signature is required.', 'webhook_signature_missing');
        }
        $expected = 'sha256=' . hash_hmac('sha256', $request->rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new HttpException(403, 'Webhook signature is not valid.', 'webhook_signature_invalid');
        }
        $processed = $this->webhooks->process($request->json());
        return ['received' => true, 'processed' => $processed];
    }
}
