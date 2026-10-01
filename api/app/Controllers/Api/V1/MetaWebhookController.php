<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use PDO;
use Throwable;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;
use WhatstheUp\Support\Response;
use WhatstheUp\Services\MetaWebhookService;

final class MetaWebhookController
{
    public function __construct(private readonly MetaWebhookService $webhooks, private readonly ?PDO $db = null)
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

        $idempotencyKey = hash('sha256', $request->rawBody);
        if ($this->db !== null) {
            try {
                $this->db->prepare("INSERT IGNORE INTO webhook_events (event_type, idempotency_key, payload, status, created_at) VALUES ('meta.webhook', ?, ?, 'received', UTC_TIMESTAMP())")->execute([$idempotencyKey, $request->rawBody]);
            } catch (Throwable) {
                // Ensure event logging does not block webhook acknowledgement
            }
        }

        try {
            $processed = $this->webhooks->process($request->json());
            if ($this->db !== null) {
                $this->db->prepare("UPDATE webhook_events SET status = 'processed', processed_at = UTC_TIMESTAMP() WHERE idempotency_key = ?")->execute([$idempotencyKey]);
            }
        } catch (Throwable $exception) {
            if ($this->db !== null) {
                $this->db->prepare("UPDATE webhook_events SET status = 'failed', error_message = ? WHERE idempotency_key = ?")->execute([mb_substr($exception->getMessage(), 0, 500), $idempotencyKey]);
            }
            throw $exception;
        }

        return ['received' => true, 'processed' => $processed];
    }
}
